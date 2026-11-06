/*!
 * Spindle MP3 Player — Web Component v1.3.0
 * Diseño Dieter Rams · Shadow DOM · Dark/Light/Auto · Full/Compact/Wide · Vertical/Horizontal/Mini
 * Requiere: howler.min.js
 */
(function () {
'use strict';

/* ── Textos (localizados desde WP con mp3pI18n; fallback en español) ── */
var T = Object.assign({
  download:   'Descargar',
  noDownload: 'Sin descarga',
  prev:       'Anterior',
  next:       'Siguiente',
  song:       '%d canción',
  songs:      '%d canciones',
  hideList:   'Ocultar lista',
  lyrics:     'Letra',
  tracks:     'pistas',
  loading:    'Cargando…',
  paused:     'EN PAUSA',
  playing:    'REPRODUCIENDO',
  close:      'Cerrar',
  noLyrics:   'Sin letra',
}, (typeof window !== 'undefined' && window.mp3pI18n) || {});

function nSongs(n) { return (n === 1 ? T.song : T.songs).replace('%d', n); }

function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* Validación de los valores que llegan por atributos o por la API y que acaban en CSS o en URLs.
   Solo se aceptan formatos conocidos; cualquier otra cosa se descarta, para no inyectar CSS ni HTML. */
function safeColor(c, fallback) {
  c = String(c == null ? '' : c).trim();
  return /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(c) ? c : (fallback === undefined ? '' : fallback);
}

// Fondo: '', 'transparent', un color hexadecimal o linear-gradient(<ángulo|to dirección>, <color>, <color>)
function safeBg(bg) {
  bg = String(bg == null ? '' : bg).trim();
  if (bg === '' || bg === 'transparent') return bg;
  if (safeColor(bg)) return bg;
  var m = bg.match(/^linear-gradient\(\s*(\d{1,3}deg|to (?:top|bottom|left|right)(?: (?:top|bottom|left|right))?)\s*,\s*([^,()]+?)\s*,\s*([^,()]+?)\s*\)$/i);
  if (m && safeColor(m[2]) && safeColor(m[3])) return 'linear-gradient(' + m[1] + ',' + m[2] + ',' + m[3] + ')';
  return '';
}

function safeRadius(r, fallback) {
  var n = parseInt(r, 10);
  return isNaN(n) ? fallback : String(Math.max(0, Math.min(n, 200)));
}

// URLs de portada y audio: solo http(s), relativas al protocolo o a la raíz del sitio (nunca javascript:, data:…)
function safeUrl(u) {
  u = String(u == null ? '' : u).trim();
  return /^(?:https?:)?\/\//i.test(u) || /^\/(?!\/)/.test(u) ? u : '';
}

function escUrl(u) { return esc(safeUrl(u)); }

/* Plegar / desplegar un panel animando su altura.
   Abierto = height:'' (auto), así crece si dentro se abre otro panel (p.ej. una letra). */
function setCollapse(panel, open, animate) {
  if (!panel) return;
  panel.dataset.open = open ? '1' : '0';
  if (!animate) { panel.style.height = open ? '' : '0px'; return; }
  var h = panel.scrollHeight;
  if (open) {
    panel.style.height = '0px';
    void panel.offsetHeight;
    panel.style.height = h + 'px';
    var done = function (e) {
      if (e.target !== panel) return;
      panel.removeEventListener('transitionend', done);
      if (panel.dataset.open === '1') panel.style.height = '';
    };
    panel.addEventListener('transitionend', done);
  } else {
    panel.style.height = h + 'px';
    void panel.offsetHeight;
    panel.style.height = '0px';
  }
}

/* Botones de letra: cada botón [data-index] abre su panel [data-index] */
function bindLyrics(eng, btnSel, panelSel) {
  eng.qa(btnSel + ':not(.off)').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (eng.opts && eng.opts.lyrics === 'modal') {
        openLyricsModal(eng.tracks[parseInt(btn.dataset.index)], eng.modalOpts(btn));
        return;
      }
      var pnl  = eng.q(panelSel + '[data-index="' + btn.dataset.index + '"]');
      var open = !btn.classList.contains('on');
      btn.classList.toggle('on', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      setCollapse(pnl, open, true);
    });
  });
}

/* Línea de metadatos de una pista según los elementos visibles.
   perTrack: en playlists no hay cabecera de disco, así que año/licencia van por pista. */
function trackMeta(t, show, perTrack) {
  var sc = function (k) { return show[k] !== false; };
  return [
    sc('autores') && t.autor,
    sc('interpretes') && t.interpretes,
    perTrack && sc('anio') && t.anio,
  ].filter(Boolean).join(' · ');
}

/* ── Coordinador global de reproducción ─────────────────────── */
// Solo un player puede sonar a la vez en la misma página
var MP3P_ACTIVE = null; // referencia al MP3PlayerEngine activo

/* ── SVGs outline (stroke, no fill) ─────────────────────────── */
const I = {
  play:  '<svg viewBox="0 0 24 24" width="15" height="15"><polygon points="5 3 19 12 5 21 5 3" stroke="currentColor" fill="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
  pause: '<svg viewBox="0 0 24 24" width="15" height="15"><rect x="6" y="4" width="4" height="16" rx="1" stroke="currentColor" fill="currentColor" stroke-width="0"/><rect x="14" y="4" width="4" height="16" rx="1" stroke="currentColor" fill="currentColor" stroke-width="0"/></svg>',
  prev:  '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg>',
  next:  '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg>',
  dl:    '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  lock:  '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  disc:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/></svg>',
  chev:  '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>',
  lyr:   '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="11" x2="20" y2="11"/><line x1="4" y1="16" x2="13" y2="16"/></svg>',
};

/* ── CSS ─────────────────────────────────────────────────────── */
function buildCSS(accent, bg, radius) {
  accent = safeColor(accent, '#e8520a');
  bg     = safeBg(bg);
  radius = safeRadius(radius, '4') + 'px';
  // El fondo personalizado solo aplica cuando no se fuerza un tema dark/light
  // Si theme es 'dark' o 'light', el fondo del tema tiene prioridad
  var bgCSS = '';
  if (bg === 'transparent') {
    bgCSS = 'background:transparent !important;border:none !important;';
  } else if (bg && bg !== '' && bg !== '#ffffff' && bg !== '#111111') {
    bgCSS = 'background:' + bg + ' !important;';
  }

  var lightTx2 = (bg === 'transparent') ? '#333333' : '#999';
  var dark  = '.mp.dark{--bg:#111111;--bg2:#1a1a1a;--tx:#e8e8e8;--tx2:#555;--bd:rgba(255,255,255,.06);--ac:' + accent + ';background:var(--bg);color:var(--tx);border:0.5px solid rgba(255,255,255,.08);display:block;}';
  var light = '.mp.light{--bg:#ffffff;--bg2:#f0f0f0;--tx:#1a1a1a;--tx2:' + lightTx2 + ';--bd:rgba(0,0,0,.10);--ac:' + accent + ';background:var(--bg);color:var(--tx);border:0.5px solid rgba(0,0,0,.10);display:block;}';
  // bgRule aplica el fondo personalizado a todos los elementos visibles
  // pero NO sobreescribe las variables CSS del tema (--tx, --tx2, etc.)
  var bgSelectors = [
    '.mp.dark','.mp.light',
    '.mp.dark .wide-cover','.mp.light .wide-cover',
    '.mp.dark .wide-right','.mp.light .wide-right',
    '.mp.dark .cbody','.mp.light .cbody',
    '.mp.dark .chover','.mp.light .chover'
  ].join(',');
  var bgRule = bgCSS ? bgSelectors + '{' + bgCSS + '}' : '';

  return ':host{display:block !important;width:100%;max-width:100%}'
  + bgRule
  + '*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}'
  + '.mp{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;font-size:14px;letter-spacing:.01em;border-radius:' + radius + ';overflow:hidden;display:block !important;contain:layout style}'
  + dark + light
  + '@keyframes mp3p-bounce{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.2)}}'
  + '.bars{display:flex;align-items:flex-end;gap:2px;height:13px;flex-shrink:0}'
  + '.bars span{width:2px;background:var(--ac);border-radius:1px;animation:mp3p-bounce .9s ease-in-out infinite;transform-origin:bottom}'
  + '.bars span:nth-child(1){height:5px;animation-delay:0s}'
  + '.bars span:nth-child(2){height:10px;animation-delay:.15s}'
  + '.bars span:nth-child(3){height:13px;animation-delay:.30s}'
  + '.bars span:nth-child(4){height:7px;animation-delay:.08s}'
  + '.bars span:nth-child(5){height:9px;animation-delay:.22s}'
  + '.bars.paused span{animation-play-state:paused}'
  + '.mini-bars{display:flex;align-items:flex-end;gap:1.5px;height:10px;width:16px;flex-shrink:0}'
  + '.mini-bars span{width:2px;background:var(--ac);border-radius:1px;animation:mp3p-bounce .9s ease-in-out infinite;transform-origin:bottom}'
  + '.mini-bars span:nth-child(1){height:5px;animation-delay:0s}'
  + '.mini-bars span:nth-child(2){height:9px;animation-delay:.2s}'
  + '.mini-bars span:nth-child(3){height:7px;animation-delay:.1s}'
  + '.mini-bars.paused span{animation-play-state:paused}'
  + '.btn{background:none;border:none;cursor:pointer;color:var(--tx);display:flex;align-items:center;justify-content:center;border-radius:2px;transition:opacity .15s;flex-shrink:0;padding:5px}'
  + '.btn:hover{opacity:.5}'
  + '.btn svg{display:block;flex-shrink:0}'
  + '.btn-play{width:40px;height:40px;background:var(--ac);color:#fff;padding:0;border-radius:2px}'
  + '.btn-play:hover{opacity:.82}'
  + '.btn-play svg{stroke:#fff !important}'
  + '.btn-sec{width:32px;height:32px;opacity:.65}'
  + '.btn-sec:hover{opacity:1 !important}'
  + '.prog-track{height:1px;background:var(--bd);position:relative;cursor:pointer;margin-bottom:6px}'
  + '.prog-track:hover{height:2px}'
  + '.prog-fill{height:100%;background:var(--tx);width:0%;position:relative;pointer-events:none}'
  + '.prog-fill::after{content:"";position:absolute;right:-3px;top:50%;transform:translateY(-50%);width:5px;height:5px;background:var(--ac);border-radius:50%}'
  + '.times{display:flex;justify-content:space-between;font-size:11px;color:var(--tx2);font-variant-numeric:tabular-nums}'
  + '.tl{border-top:0.5px solid var(--bd);display:block !important}'
  + '.trk{border-bottom:0.5px solid var(--bd)}'
  + '.trk:last-child{border-bottom:none}'
  + '.track{display:flex !important;align-items:center;gap:9px;padding:9px 0;cursor:pointer;transition:opacity .1s}'
  + '.track:hover{opacity:.6}'
  + '.track-num{width:20px;text-align:right;font-size:12px;color:var(--tx2);flex-shrink:0;font-variant-numeric:tabular-nums}'
  + '.track-info{flex:1;min-width:0}'
  + '.track-name{display:block;font-size:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--tx)}'
  + '.track.active .track-name{color:var(--ac)}'
  + '.track-sub{display:block;font-size:14px;color:var(--tx2);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  + '.track-lic{display:inline-block;font-size:10px;line-height:1.4;padding:0 4px;border:0.5px solid var(--tx2);border-radius:2px;margin-left:6px;vertical-align:1px}'
  + '.track-actions{flex-shrink:0;display:flex;align-items:center;gap:2px}'
  + '.lyr-btn{display:flex;align-items:center;justify-content:center;width:22px;height:22px;flex-shrink:0;background:none;border:none;cursor:pointer;color:var(--tx2);padding:0;border-radius:2px;transition:color .12s}'
  + '.lyr-btn:hover{color:var(--tx)}'
  + '.lyr-btn.on{color:var(--ac)}'
  + '.lyr-btn.off{opacity:.25;cursor:default;pointer-events:none}'
  + '.lyr{overflow:hidden;transition:height .25s ease}'
  + '.lyr-in{padding:2px 24px 16px 29px;font-size:14px;line-height:1.65;color:var(--tx2);white-space:pre-line;max-height:360px;overflow-y:auto}'
  /* Plegar/desplegar lista (Full y Wide) */
  + '.tl-toggle{display:flex !important;align-items:center;gap:6px;width:100%;padding:10px 0;background:none;border:none;border-top:0.5px solid var(--bd);cursor:pointer;color:var(--tx2);font-size:12px;font-family:inherit;text-align:left;transition:opacity .12s}'
  + '.tl-toggle:hover{opacity:.6}'
  + '.tl-toggle svg{transition:transform .25s;transform:rotate(-90deg);flex-shrink:0}'
  + '.tl-toggle.open svg{transform:none}'
  + '.tl-panel{overflow:hidden;transition:height .3s ease}'
  /* Descripción y datos del disco */
  + '.desc{font-size:13px;line-height:1.55;color:var(--tx2);white-space:pre-line;margin-top:10px}'
  + '.comp-info{text-align:center;font-size:12px;color:var(--tx2);margin-top:12px}'
  + '.comp-info .full-badge{margin:0 0 0 6px;vertical-align:1px}'
  + '.comp-info .desc{text-align:left;margin-top:8px}'
  + '.dl{display:flex;align-items:center;justify-content:center;width:22px;height:22px;flex-shrink:0;background:none;border:none;cursor:pointer;color:var(--tx2);padding:0;border-radius:2px;text-decoration:none;transition:color .12s}'
  + '.dl:hover{color:var(--tx)}'
  + '.dl.off{opacity:.12;cursor:not-allowed;pointer-events:none}'
  + '.np{display:flex;align-items:center;gap:8px;min-height:18px;overflow:hidden}'
  + '.np-text{font-size:13px;color:var(--tx2);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  + '.np-text strong{color:var(--ac);font-weight:500}'
  + '.full-header{display:flex !important;gap:16px;align-items:flex-start;padding:20px 20px 16px;border-bottom:0.5px solid var(--bd)}'
  + '.full-thumb{width:64px;height:64px;flex-shrink:0;border-radius:2px;overflow:hidden;background:var(--bg2);display:flex;align-items:center;justify-content:center}'
  + '.full-thumb img{width:100%;height:100%;object-fit:cover;display:block}'
  + '.full-thumb svg{width:28px;height:28px;opacity:.25}'
  + '.full-meta{flex:1;min-width:0;padding-top:2px}'
  + '.full-title{font-size:22px;font-weight:400;letter-spacing:-.5px;line-height:1.15;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  + '.full-sub{font-size:14px;color:var(--tx2);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  + '.full-badge{display:inline-block;font-size:11px;padding:2px 6px;border:0.5px solid var(--tx2);color:var(--tx2);margin-top:8px;border-radius:2px}'
  + '.full-ctrl{display:flex !important;align-items:center;justify-content:center;gap:12px;padding:16px 20px 0}'
  + '.full-prog{display:block !important;padding:12px 20px 14px}'
  + '.full-np{display:flex !important;padding:0 20px 12px}'
  + '.full-tl{display:block !important;padding:0 20px 4px}'
  + '.cov{width:100%;aspect-ratio:1;background:var(--bg2);position:relative;display:flex;align-items:center;justify-content:center;overflow:hidden}'
  + '.cov img{width:100%;height:100%;object-fit:cover;display:block}'
  + '.cov-ph{display:flex;align-items:center;justify-content:center;width:100%;height:100%;opacity:.2}'
  + '.cov-ph svg{width:52px;height:52px}'
  + '.cov-overlay{position:absolute;bottom:0;left:0;right:0;padding:12px 14px;background:linear-gradient(transparent,rgba(0,0,0,.82))}'
  + '.cov-title{font-size:16px;font-weight:400;color:#fff;letter-spacing:-.2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  + '.cov-sub{font-size:13px;color:rgba(255,255,255,.5);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  + '.comp-body{padding:14px 16px 10px;border-bottom:0.5px solid var(--bd)}'
  + '.comp-prog{margin-bottom:10px}'
  + '.comp-ctrl{display:flex !important;align-items:center;justify-content:center;gap:12px}'
  + '.chover{overflow:hidden;max-height:0;opacity:0;transition:max-height .25s ease,opacity .2s ease}'
  + '.chover.open{max-height:400px;opacity:1}'
  + '.chover.pinned{max-height:none;opacity:1}'
  + '.cexpand{display:flex !important;align-items:center;gap:6px;width:100%;padding:9px 16px;background:none;border:none;border-top:0.5px solid var(--bd);cursor:pointer;color:var(--tx2);font-size:12px;font-family:inherit;transition:opacity .12s}'
  + '.cexpand:hover{opacity:.6}'
  + '.cexpand svg{transition:transform .25s;flex-shrink:0}'
  + '.cexpand.open svg{transform:rotate(180deg)}'
  + '.cpanel{overflow:hidden;transition:height .3s ease}'
  + '.comp-tl{padding:0 16px 4px}'
  + '.wide-cover{background:var(--bg);position:relative;overflow:hidden;display:flex;align-items:flex-start;justify-content:center}'
  + '.wide-cover img{width:100%;height:auto;object-fit:contain;display:block}'
  + '.wide-cover-ph{display:flex;align-items:center;justify-content:center;width:100%;height:100%;opacity:.15}'
  + '.wide-cover-ph svg{width:52px;height:52px}'
  + '.wide-info{margin-bottom:20px;padding-bottom:18px;border-bottom:0.5px solid var(--bd)}'
  + '.wide-title{font-size:22px;font-weight:400;letter-spacing:-.5px;line-height:1.15;margin-bottom:5px}'
  + '.wide-sub{font-size:14px;color:var(--tx2);margin-top:2px}'
  + '.wide-badge{display:inline-block;font-size:11px;padding:2px 6px;border:0.5px solid var(--tx2);color:var(--tx2);margin-top:10px;border-radius:2px}'
  + '.wide-ctrl{display:flex !important;align-items:center;gap:10px;margin-bottom:14px}'
  + '.wide-prog{margin-bottom:16px}'
  + '.wide-np{margin-bottom:14px}'
  + '.wide-tl{overflow-y:auto}'
  + '.wide-right{display:flex !important;flex-direction:column;padding:24px 22px;overflow:hidden;background:var(--bg)}'
  + '.wide-grid{display:grid !important;grid-template-columns:minmax(160px,0.5fr) 1fr;border-radius:' + radius + ';overflow:hidden;align-items:start}'
  + '.wide-grid.nocover{grid-template-columns:1fr}'
  + '@media(max-width:460px){.wide-grid{grid-template-columns:1fr !important}.wide-cover{aspect-ratio:16/7}}'
  + '.loading{padding:32px;text-align:center;color:var(--tx2);font-size:13px}'
  + '.error{color:#e94560;font-size:12px;padding:12px 16px;border-left:2px solid #e94560;margin:12px}';
}


/* ── Helpers ─────────────────────────────────────────────────── */
function fmt(s) {
  s = Math.floor(s || 0);
  return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
}

function resolveTheme(attr, bg) {
  if (attr === 'dark' || attr === 'light') return attr;
  // theme="auto" + fondo transparente: forzar texto/iconos oscuros
  // (legibles sobre cualquier fondo de página) en lugar de detectar el sistema
  if (bg === 'transparent') return 'light';
  return (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'dark';
}

function getShow(el) {
  return {
    cover:       el.getAttribute('show_cover')       !== '0',
    autores:     el.getAttribute('show_autores')     !== '0',
    interpretes: el.getAttribute('show_interpretes') !== '0',
    anio:        el.getAttribute('show_anio')        !== '0',
    licencia:    el.getAttribute('show_licencia')    !== '0',
    descripcion: el.getAttribute('show_descripcion') !== '0',
    letra:       el.getAttribute('show_letra')       !== '0',
  };
}

// Estado inicial de la lista de pistas: tracklist="open" (defecto) | "closed"
function getOpen(el) {
  return el.getAttribute('tracklist') !== 'closed';
}

// Cómo se muestra la letra: lyrics="inline" (desplegable, defecto) | "modal" (ventana)
function getLyricsMode(el) {
  return el.getAttribute('lyrics') === 'modal' ? 'modal' : 'inline';
}

/* ── Ventana modal de letra ──────────────────────────────────────
   Se monta en <body> (fuera del Shadow DOM del player) para que position:fixed
   funcione aunque el builder aplique transform/contain a los contenedores. */
var MP3P_MODAL = null;

function closeLyricsModal() {
  if (!MP3P_MODAL) return;
  var m = MP3P_MODAL; MP3P_MODAL = null;
  document.removeEventListener('keydown', m.onKey, true);
  document.documentElement.style.overflow = m.prevOverflow;
  m.ov.classList.remove('in');
  setTimeout(function () { if (m.host.parentNode) m.host.parentNode.removeChild(m.host); }, 200);
  if (m.trigger && m.trigger.focus) { try { m.trigger.focus({ preventScroll: true }); } catch (e) {} }
}

function openLyricsModal(t, o) {
  if (!t || !t.letra) return;
  o = o || {};
  closeLyricsModal();
  var dark = o.theme === 'dark';
  var c = dark
    ? { bg: '#161616', tx: '#e8e8e8', tx2: '#777', bd: 'rgba(255,255,255,.08)' }
    : { bg: '#ffffff', tx: '#1a1a1a', tx2: '#888', bd: 'rgba(0,0,0,.10)' };
  var ac   = safeColor(o.accent, '#e8520a');
  var font = o.mono ? '"JetBrains Mono",monospace' : '-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif';
  var sub  = trackMeta(t, o.show || {}, false);
  var css = ':host{all:initial}'
    + '*{box-sizing:border-box;margin:0;padding:0}'
    + '.ov{position:fixed;inset:0;z-index:2147483000;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:24px;opacity:0;transition:opacity .2s ease}'
    + '.ov.in{opacity:1}'
    + '.box{background:' + c.bg + ';color:' + c.tx + ';width:100%;max-width:520px;max-height:min(80vh,720px);display:flex;flex-direction:column;border-radius:' + (o.mono ? '16px' : '4px') + ';box-shadow:0 24px 60px rgba(0,0,0,.35);font-family:' + font + ';transform:translateY(8px);transition:transform .2s ease;overflow:hidden}'
    + '.ov.in .box{transform:none}'
    + '.hd{display:flex;align-items:flex-start;gap:12px;padding:20px 22px 14px;border-bottom:0.5px solid ' + c.bd + '}'
    + '.lbl{font-size:10px;letter-spacing:.8px;text-transform:uppercase;color:' + ac + ';margin-bottom:6px}'
    + '.ti{font-size:' + (o.mono ? '16px' : '20px') + ';font-weight:' + (o.mono ? '700' : '400') + ';letter-spacing:-.3px;line-height:1.2}'
    + '.sub{font-size:12px;color:' + c.tx2 + ';margin-top:4px}'
    + '.x{margin-left:auto;flex-shrink:0;width:32px;height:32px;border:none;background:none;color:' + c.tx2 + ';cursor:pointer;border-radius:2px;display:flex;align-items:center;justify-content:center;transition:color .12s}'
    + '.x:hover,.x:focus-visible{color:' + c.tx + '}'
    + '.x:focus-visible{outline:1px solid ' + ac + ';outline-offset:2px}'
    + '.bd{padding:18px 22px 26px;overflow-y:auto;white-space:pre-line;font-size:' + (o.mono ? '13px' : '16px') + ';line-height:1.75;color:' + c.tx + '}';
  var host = document.createElement('div');
  host.setAttribute('data-mp3p-lyrics', '');
  var root = host.attachShadow({ mode: 'open' });
  root.innerHTML = '<style>' + css + '</style>'
    + '<div class="ov" role="dialog" aria-modal="true" aria-label="' + esc(T.lyrics) + '"><div class="box">'
    + '<div class="hd"><div><div class="lbl">' + esc(T.lyrics) + '</div><div class="ti">' + esc(t.titulo) + '</div>' + (sub ? '<div class="sub">' + esc(sub) + '</div>' : '') + '</div>'
    + '<button class="x" aria-label="' + esc(T.close) + '" title="' + esc(T.close) + '"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg></button></div>'
    + '<div class="bd">' + esc(t.letra) + '</div>'
    + '</div></div>';
  document.body.appendChild(host);
  var ov = root.querySelector('.ov');
  var m = {
    host: host, ov: ov, trigger: o.trigger || null,
    prevOverflow: document.documentElement.style.overflow,
    onKey: function (e) { if (e.key === 'Escape') { e.preventDefault(); closeLyricsModal(); } },
  };
  MP3P_MODAL = m;
  document.documentElement.style.overflow = 'hidden';
  document.addEventListener('keydown', m.onKey, true);
  ov.addEventListener('click', function (e) { if (e.target === ov) closeLyricsModal(); });
  root.querySelector('.x').addEventListener('click', closeLyricsModal);
  requestAnimationFrame(function () { ov.classList.add('in'); });
  root.querySelector('.x').focus({ preventScroll: true });
}

function dlBtn(t) {
  return t.descargable
    ? '<a href="' + escUrl(t.audio_url) + '" download class="dl on" title="' + esc(T.download) + '">' + I.dl + '</a>'
    : '<span class="dl off" title="' + esc(T.noDownload) + '">' + I.lock + '</span>';
}

// Año + licencia + descripción (cabecera de disco / playlist / canción)
function discInfo(d, show) {
  var sc = function (k) { return show[k] !== false; };
  return {
    anio: sc('anio') && d.anio ? esc(d.anio) : '',
    lic:  sc('licencia') && d.licencia ? esc(d.licencia) : '',
    desc: sc('descripcion') && d.descripcion ? '<p class="desc">' + esc(d.descripcion) + '</p>' : '',
  };
}

function barsHtml(cls) {
  return '<div class="' + (cls||'bars') + '"><span></span><span></span><span></span><span></span><span></span></div>';
}

function miniHtml() {
  return '<div class="mini-bars"><span></span><span></span><span></span></div>';
}

function ctrlHtml() {
  return '<button class="btn btn-sec btn-prev" aria-label="' + esc(T.prev) + '">' + I.prev + '</button>'
       + '<button class="btn btn-play btn-toggle"><span class="i-play">' + I.play + '</span><span class="i-pause" style="display:none">' + I.pause + '</span></button>'
       + '<button class="btn btn-sec btn-next" aria-label="' + esc(T.next) + '">' + I.next + '</button>';
}

function progHtml() {
  return '<div class="prog-track"><div class="prog-fill"></div></div>'
       + '<div class="times"><span class="t-cur">0:00</span><span class="t-tot">0:00</span></div>';
}

function tracklistHtml(tracks, show, perTrack, mode) {
  show = show || {};
  var sc = function(k) { return show[k] !== false; };
  return '<div class="tl">' + tracks.map(function(t, i) {
    var meta   = trackMeta(t, show, perTrack);
    var lic    = perTrack && sc('licencia') && t.licencia ? '<span class="track-lic">' + esc(t.licencia) + '</span>' : '';
    var hasLyr = sc('letra') && t.letra;
    var inline = hasLyr && mode !== 'modal';
    var lyrBtn = !sc('letra') ? ''
      : hasLyr ? '<button class="lyr-btn" data-index="' + i + '" title="' + esc(T.lyrics) + '" aria-label="' + esc(T.lyrics) + '" aria-expanded="false">' + I.lyr + '</button>'
      :          '<span class="lyr-btn off" title="' + esc(T.noLyrics) + '" aria-hidden="true">' + I.lyr + '</span>';
    return '<div class="trk">'
      + '<div class="track" data-index="' + i + '">'
        + '<div class="track-num"><span class="track-idx">' + (i+1) + '</span></div>'
        + '<div class="track-info"><span class="track-name">' + esc(t.titulo) + '</span>'
        + (meta || lic ? '<span class="track-sub">' + esc(meta) + lic + '</span>' : '')
        + '</div>'
        + '<div class="track-actions">'
          + lyrBtn
          + dlBtn(t)
        + '</div>'
      + '</div>'
      + (inline ? '<div class="lyr" data-index="' + i + '" style="height:0px"><div class="lyr-in">' + esc(t.letra) + '</div></div>' : '')
      + '</div>';
  }).join('') + '</div>';
}

// Lista plegable (Full y Wide)
function foldableListHtml(tracks, show, perTrack, open, mode) {
  return '<button class="tl-toggle' + (open ? ' open' : '') + '" aria-expanded="' + (open ? 'true' : 'false') + '">' + I.chev + '<span>' + esc(nSongs(tracks.length)) + '</span></button>'
       + '<div class="tl-panel"' + (open ? '' : ' style="height:0px"') + ' data-open="' + (open ? '1' : '0') + '">' + tracklistHtml(tracks, show, perTrack, mode) + '</div>';
}

/* ── Render: FULL ────────────────────────────────────────────── */
function renderFull(d, tracks, theme, accent, show, bg, radius, opts) {
  show = show || {}; opts = opts || {};
  var sc   = function(k) { return show[k] !== false; };
  var meta = esc([sc('autores') && d.autores, sc('interpretes') && d.interpretes].filter(Boolean).join(' · '));
  var info = discInfo(d, show);
  var h    = '<style>' + buildCSS(accent, bg, radius) + '</style><div class="mp ' + theme + '">';

  h += '<div class="full-header">';
  if (sc('cover')) {
    h += '<div class="full-thumb">' + (d.portada ? '<img src="' + escUrl(d.portada) + '" alt="">' : I.disc) + '</div>';
  }
  h += '<div class="full-meta"><div class="full-title">' + esc(d.titulo) + '</div>';
  if (meta)      h += '<div class="full-sub">' + meta + '</div>';
  if (info.anio) h += '<div class="full-sub">' + info.anio + '</div>';
  if (info.lic)  h += '<span class="full-badge">' + info.lic + '</span>';
  h += info.desc;
  h += '</div></div>';

  h += '<div class="full-ctrl">' + ctrlHtml() + '</div>';
  h += '<div class="full-prog">' + progHtml() + '</div>';
  h += '<div class="full-np np">' + barsHtml() + '<span class="np-text">—</span></div>';

  if (tracks.length) h += '<div class="full-tl">' + foldableListHtml(tracks, show, opts.perTrack, opts.open !== false, opts.lyrics) + '</div>';

  return h + '</div>';
}

/* ── Render: COMPACT ─────────────────────────────────────────── */
function renderCompact(d, tracks, theme, accent, show, bg, radius, opts) {
  show       = show || {}; opts = opts || {};
  var first  = tracks[0] || {};
  var info   = discInfo(d, show);
  var open   = opts.open !== false;
  var h      = '<style>' + buildCSS(accent, bg, radius) + '</style><div class="mp ' + theme + '">';

  h += '<div class="cov">';
  if (d.portada && show.cover !== false) {
    h += '<img src="' + escUrl(d.portada) + '" alt="">';
  } else {
    h += '<div class="cov-ph">' + I.disc + '</div>';
  }
  h += '<div class="cov-overlay"><div class="cov-title">' + esc(first.titulo || d.titulo) + '</div>'
     + '<div class="cov-sub">' + esc(d.titulo) + '</div></div></div>';

  h += '<div class="comp-body"><div class="comp-prog">' + progHtml() + '</div>'
     + '<div class="comp-ctrl">' + ctrlHtml() + '</div>';
  if (info.anio || info.lic || info.desc) {
    h += '<div class="comp-info">' + info.anio + (info.lic ? '<span class="full-badge">' + info.lic + '</span>' : '') + info.desc + '</div>';
  }
  h += '</div>';

  if (tracks.length) {
    h += '<div class="chover' + (open ? ' open pinned' : '') + '"><button class="cexpand' + (open ? ' open' : '') + '">' + I.chev + '<span>' + esc(open ? T.hideList : nSongs(tracks.length)) + '</span></button>'
       + '<div class="cpanel"' + (open ? '' : ' style="height:0px"') + ' data-open="' + (open ? '1' : '0') + '"><div class="comp-tl">' + tracklistHtml(tracks, show, opts.perTrack, opts.lyrics) + '</div></div></div>';
  }

  return h + '</div>';
}

/* ── Render: WIDE ────────────────────────────────────────────── */
function renderWide(d, tracks, theme, accent, show, bg, radius, opts) {
  show     = show || {}; opts = opts || {};
  var sc   = function(k) { return show[k] !== false; };
  var meta = esc([sc('autores') && d.autores, sc('interpretes') && d.interpretes].filter(Boolean).join(' · '));
  var info = discInfo(d, show);
  var h    = '<style>' + buildCSS(accent, bg, radius) + '</style><div class="mp ' + theme + '"><div class="wide-grid' + (sc('cover') ? '' : ' nocover') + '">';

  if (sc('cover')) {
    h += '<div class="wide-cover">';
    h += d.portada ? '<img src="' + escUrl(d.portada) + '" alt="">' : '<div class="wide-cover-ph">' + I.disc + '</div>';
    h += '</div>';
  }

  h += '<div class="wide-right">';
  h += '<div class="wide-info"><div class="wide-title">' + esc(d.titulo) + '</div>';
  if (meta)      h += '<div class="wide-sub">' + meta + '</div>';
  if (info.anio) h += '<div class="wide-sub">' + info.anio + '</div>';
  if (info.lic)  h += '<span class="wide-badge">' + info.lic + '</span>';
  h += info.desc;
  h += '</div>';

  h += '<div class="wide-ctrl">' + ctrlHtml() + '</div>';
  h += '<div class="wide-prog">' + progHtml() + '</div>';
  h += '<div class="wide-np np">' + barsHtml() + '<span class="np-text">—</span></div>';

  if (tracks.length) h += '<div class="wide-tl">' + foldableListHtml(tracks, show, opts.perTrack, opts.open !== false, opts.lyrics) + '</div>';

  h += '</div></div></div>';
  return h;
}

/* ── Motor de audio ──────────────────────────────────────────── */
class MP3PlayerEngine {
  constructor(root, tracks, show, opts) {
    this.root    = root;
    this.tracks  = tracks;
    this.show    = show || {};
    this.opts    = opts || {};
    this.current = 0;
    this.howl    = null;
    this.seeking = false;
    this._raf    = null;
  }

  q(s)  { return this.root.querySelector(s); }
  qa(s) { return this.root.querySelectorAll(s); }
  modalOpts(trigger) { return { theme: this.opts.theme, accent: this.opts.accent, show: this.show, mono: false, trigger: trigger }; }

  bindEvents() {
    this.q('.btn-toggle')?.addEventListener('click', () => this.togglePlay());
    this.q('.btn-prev')  ?.addEventListener('click', () => this.prev());
    this.q('.btn-next')  ?.addEventListener('click', () => this.next());

    this.qa('.track').forEach(row => {
      row.addEventListener('click', e => {
        if (e.target.closest('.dl, .lyr-btn')) return;
        const idx = parseInt(row.dataset.index);
        idx === this.current && this.howl ? this.togglePlay() : this.loadTrack(idx, true);
      });
    });

    // Seek
    ['.prog-track'].forEach(sel => {
      const el = this.q(sel);
      if (!el) return;
      const doSeek = e => {
        if (!this.howl) return;
        const r = el.getBoundingClientRect();
        const p = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
        this.howl.seek(p * (this.howl.duration() || 0));
        this._setProgress(p);
      };
      el.addEventListener('mousedown', e => {
        this.seeking = true; doSeek(e);
        const mv = e => { if (this.seeking) doSeek(e); };
        const up = () => { this.seeking = false; document.removeEventListener('mousemove', mv); document.removeEventListener('mouseup', up); };
        document.addEventListener('mousemove', mv);
        document.addEventListener('mouseup', up);
      });
    });

    // Letras (desplegable bajo cada pista)
    bindLyrics(this, '.lyr-btn', '.lyr');

    // Full / Wide: plegar y desplegar la lista
    const tlt = this.q('.tl-toggle');
    if (tlt) {
      const pnl = this.q('.tl-panel');
      tlt.addEventListener('click', () => {
        const open = !tlt.classList.contains('open');
        tlt.classList.toggle('open', open);
        tlt.setAttribute('aria-expanded', open ? 'true' : 'false');
        setCollapse(pnl, open, true);
      });
    }

    // Compact: la barra "Ver N canciones" aparece al pasar el ratón;
    // si la lista está desplegada (.pinned) permanece visible
    const mp     = this.q('.mp');
    const chover = this.q('.chover');
    if (mp && chover) {
      mp.addEventListener('mouseenter', () => chover.classList.add('open'));
      mp.addEventListener('mouseleave', () => {
        if (!chover.classList.contains('pinned')) chover.classList.remove('open');
      });
    }

    // Compact: desplegar / plegar
    const cexp = this.q('.cexpand');
    if (cexp) {
      const pnl = this.q('.cpanel');
      cexp.addEventListener('click', () => {
        const open = !cexp.classList.contains('open');
        cexp.classList.toggle('open', open);
        cexp.querySelector('span').textContent = open ? T.hideList : nSongs(this.tracks.length);
        if (chover) chover.classList.toggle('pinned', open);
        setCollapse(pnl, open, true);
      });
    }
  }

  loadTrack(idx, autoplay) {
    // Parar el player activo si es diferente a este
    if (MP3P_ACTIVE && MP3P_ACTIVE !== this) {
      MP3P_ACTIVE.stop();
    }
    if (this.howl)  { this.howl.unload(); this.howl = null; }
    if (this._raf)  { cancelAnimationFrame(this._raf); this._raf = null; }
    this.current = idx;
    const t      = this.tracks[idx];

    // Actualizar overlay now-playing
    const np = this.q('.np-text');
    const autor = this.show.autores !== false ? t.autor : '';
    if (np) np.innerHTML = '<strong>' + esc(t.titulo) + '</strong>' + (autor ? ' <span style="opacity:.6">\xb7 ' + esc(autor) + '</span>' : '');

    // Overlay compact
    const ct = this.q('.cov-title');
    const cs = this.q('.cov-sub');
    if (ct) ct.textContent = t.titulo;
    if (cs) cs.textContent = trackMeta(t, this.show, false) || this.tracks[0]?.disco || '';

    // Tracklist activa
    this.qa('.track').forEach((row, i) => {
      row.classList.toggle('active', i === idx);
      const numEl = row.querySelector('.track-num');
      if (numEl) {
        if (i === idx) {
          numEl.innerHTML = miniHtml();
        } else {
          numEl.innerHTML = '<span class="track-idx">' + (i+1) + '</span>';
        }
      }
    });

    this.howl = new Howl({
      src: [t.audio_url], html5: true, format: ['mp3'],
      onplay: () => {
        MP3P_ACTIVE = this; // registrar como player activo
        this._setPlayState(true);
        const loop = () => {
          if (!this.howl?.playing()) return;
          const seek = this.howl.seek() || 0;
          const dur  = this.howl.duration() || 1;
          if (!this.seeking) this._setProgress(seek / dur);
          const tc = this.q('.t-cur');
          const tt = this.q('.t-tot');
          if (tc) tc.textContent = fmt(seek);
          if (tt) tt.textContent = fmt(dur);
          this._raf = requestAnimationFrame(loop);
        };
        this._raf = requestAnimationFrame(loop);
      },
      onpause: () => this._setPlayState(false),
      onstop:  () => this._setPlayState(false),
      onend:   () => this.next(),
      onload:  () => {
        const tt = this.q('.t-tot');
        if (tt) tt.textContent = fmt(this.howl.duration());
      },
      onloaderror: (id, e) => console.error('MP3P error:', e),
    });

    if (autoplay) this.howl.play();
  }

  stop() {
    if (this.howl) { this.howl.pause(); }
    if (this._raf) { cancelAnimationFrame(this._raf); this._raf = null; }
    this._setPlayState(false);
    if (MP3P_ACTIVE === this) MP3P_ACTIVE = null;
  }

  togglePlay() {
    if (!this.howl) { this.loadTrack(this.current, true); return; }
    if (this.howl.playing()) {
      this.howl.pause();
      if (MP3P_ACTIVE === this) MP3P_ACTIVE = null;
    } else {
      // Parar cualquier otro player antes de reproducir
      if (MP3P_ACTIVE && MP3P_ACTIVE !== this) MP3P_ACTIVE.stop();
      MP3P_ACTIVE = this;
      this.howl.play();
    }
  }

  prev() { this.loadTrack(this.current > 0 ? this.current - 1 : this.tracks.length - 1, true); }
  next() { this.loadTrack(this.current < this.tracks.length - 1 ? this.current + 1 : 0, !!(this.howl?.playing())); }

  _setProgress(pct) {
    const p  = Math.max(0, Math.min(1, pct)) * 100 + '%';
    const pf = this.q('.prog-fill');
    if (pf) pf.style.width = p;
  }

  _setPlayState(playing) {
    // Botón play/pause
    const ip = this.q('.i-play');
    const iP = this.q('.i-pause');
    if (ip) ip.style.display = playing ? 'none' : '';
    if (iP) iP.style.display = playing ? '' : 'none';

    // Barras animadas
    this.qa('.bars, .mini-bars').forEach(b => b.classList.toggle('paused', !playing));
  }
}

/* ── Base Component ──────────────────────────────────────────── */
class MP3PBase extends HTMLElement {

  static get observedAttributes() {
    return ['variant', 'theme', 'accent', 'bg', 'radius', 'id', 'api', 'tracklist', 'lyrics'];
  }

  attributeChangedCallback(name, oldVal, newVal) {
    // Si el componente ya está inicializado y cambia el variant (o id/api),
    // re-renderizar para aplicar el nuevo diseño
    if (!this._shadow) return; // aún no inicializado
    if (oldVal === newVal) return;
    if (name === 'variant' || name === 'id' || name === 'api' || name === 'tracklist' || name === 'lyrics') {
      // No recreamos el shadow (attachShadow solo puede llamarse una vez)
      // sino que lo limpiamos y volvemos a cargar
      this._initialized = false;
      setTimeout(() => this._rerender(), 0);
    }
  }

  _rerender() {
    if (this._initialized) return;
    this._initialized = true;
    const theme  = resolveTheme(this.getAttribute('theme'), safeBg(this.getAttribute('bg')));
    const accent = safeColor(this.getAttribute('accent'), '#e8520a');
    const forcedTheme = this.getAttribute('theme');
    const rawBg  = safeBg(this.getAttribute('bg'));
    const bg     = (forcedTheme === 'dark' || forcedTheme === 'light') ? '' : rawBg;
    const radius = safeRadius(this.getAttribute('radius'), '4');
    const show   = getShow(this);
    const api    = this.getAttribute('api');
    const id     = String(parseInt(this.getAttribute('id'), 10) || '');
    if (!api || !id) { this._err('Faltan atributos id y/o api.', theme, accent, bg, radius); return; }
    this._load(api, id, theme, accent, show, bg, radius);
  }

  connectedCallback() {
    if (this._shadow) return; // ya inicializado
    // Pequeño delay para que el builder (Cornerstone) tenga tiempo
    // de setear todos los atributos antes de inicializar el componente
    setTimeout(() => this._init(), 0);
  }

  _init() {
    if (this._shadow) return;
    this._initialized = true;

    this._shadow = this.attachShadow({ mode: 'closed' });
    const accent = safeColor(this.getAttribute('accent'), '#e8520a');
    const rawBg  = safeBg(this.getAttribute('bg'));
    const radius = safeRadius(this.getAttribute('radius'), '4');
    const show   = getShow(this);
    // El fondo personalizado solo aplica si el tema no está forzado a dark/light
    const forcedTheme = this.getAttribute('theme');
    const bg = (forcedTheme === 'dark' || forcedTheme === 'light') ? '' : rawBg;
    const theme = resolveTheme(forcedTheme, bg);
    this._setContent('<style>' + buildCSS(accent, bg, radius) + '</style><div class="mp ' + theme + '"><div class="loading">' + esc(T.loading) + '</div></div>');
    const api = this.getAttribute('api');
    const id  = String(parseInt(this.getAttribute('id'), 10) || '');
    if (!api || !id) { this._err('Faltan atributos id y/o api.', theme, accent, bg, radius); return; }
    this._load(api, id, theme, accent, show, bg, radius);
  }

  _setContent(html) {
    const tpl = document.createElement('template');
    tpl.innerHTML = html;
    while (this._shadow.firstChild) this._shadow.removeChild(this._shadow.firstChild);
    this._shadow.appendChild(tpl.content.cloneNode(true));
  }

  _err(msg, theme, accent, bg, radius) {
    this._setContent('<style>' + buildCSS(accent||'#e8520a', bg||'', radius||'4') + '</style><div class="mp ' + (theme||'dark') + '"><p class="error">' + esc(msg) + '</p></div>');
  }
}

/* ── mp3p-disco ──────────────────────────────────────────────── */
class Mp3pDisco extends MP3PBase {
  _load(api, id, theme, accent, show, bg, radius) {
    const variant = this.getAttribute('variant') || 'full';
    const opts    = { open: getOpen(this), perTrack: false, theme: theme, accent: accent, lyrics: getLyricsMode(this) };
    fetch(api + '/disco/' + id)
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(d => {
        const tracks = d.tracks || [];
        const isHW   = variant === 'vertical' || variant === 'horizontal' || variant === 'mini';
        let html;
        if (isHW) {
          html = variant === 'horizontal' ? renderHorizontal(d, tracks, accent, show, opts)
               : variant === 'mini'       ? renderMini(d, tracks, accent, show, opts)
               :                            renderVertical(d, tracks, accent, show, opts);
          this._setContent(html);
          if (tracks.length) new HWPlayerEngine(this._shadow, tracks, accent, show, opts).bindEvents();
        } else {
          html = variant === 'compact' ? renderCompact(d, tracks, theme, accent, show, bg, radius, opts)
               : variant === 'wide'    ? renderWide(d, tracks, theme, accent, show, bg, radius, opts)
               :                         renderFull(d, tracks, theme, accent, show, bg, radius, opts);
          this._setContent(html);
          if (tracks.length) new MP3PlayerEngine(this._shadow, tracks, show, opts).bindEvents();
        }
      })
      .catch(() => this._err('No se pudo cargar el disco.', theme, accent));
  }
}

/* ── mp3p-cancion ────────────────────────────────────────────── */
class Mp3pCancion extends MP3PBase {
  _load(api, id, theme, accent, show, bg, radius) {
    const opts = { open: getOpen(this), perTrack: false, theme: theme, accent: accent, lyrics: getLyricsMode(this) };
    fetch(api + '/cancion/' + id)
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(t => {
        const d = { titulo: t.titulo, portada: t.portada, autores: t.autor, interpretes: t.interpretes, anio: t.anio || '', licencia: t.licencia || '', descripcion: '' };
        this._setContent(renderCompact(d, [t], theme, accent, show, bg, radius, opts));
        new MP3PlayerEngine(this._shadow, [t], show, opts).bindEvents();
      })
      .catch(() => this._err('No se pudo cargar la canci\xf3n.', theme, accent));
  }
}

/* ── mp3p-playlist ───────────────────────────────────────────── */
class Mp3pPlaylist extends MP3PBase {
  _load(api, id, theme, accent, show, bg, radius) {
    const variant = this.getAttribute('variant') || 'full';
    // En una playlist no hay cabecera de disco: año y licencia se muestran por pista
    const opts    = { open: getOpen(this), perTrack: true, theme: theme, accent: accent, lyrics: getLyricsMode(this) };
    fetch(api + '/playlist/' + id)
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(d => {
        const tracks = d.tracks || [];
        const disco  = { titulo: d.titulo, portada: tracks[0]?.portada || '', autores: '', interpretes: '', anio: '', licencia: '', descripcion: d.descripcion };
        if (variant === 'vertical' || variant === 'horizontal' || variant === 'mini') {
          const html = variant === 'vertical'   ? renderVertical(disco, tracks, accent, show, opts)
                     : variant === 'horizontal' ? renderHorizontal(disco, tracks, accent, show, opts)
                     :                            renderMini(disco, tracks, accent, show, opts);
          this._setContent(html);
          if (tracks.length) new HWPlayerEngine(this._shadow, tracks, accent, show, opts).bindEvents();
        } else {
          const html = variant === 'compact' ? renderCompact(disco, tracks, theme, accent, show, bg, radius, opts)
                     : variant === 'wide'    ? renderWide(disco, tracks, theme, accent, show, bg, radius, opts)
                     :                         renderFull(disco, tracks, theme, accent, show, bg, radius, opts);
          this._setContent(html);
          if (tracks.length) new MP3PlayerEngine(this._shadow, tracks, show, opts).bindEvents();
        }
      })
      .catch(() => this._err('No se pudo cargar la playlist.', theme, accent));
  }
}


/* ══════════════════════════════════════════════════════════════
   VARIANTS HARDWARE: vertical · horizontal · mini
   Diseño inspirado en hardware físico · JetBrains Mono
   ══════════════════════════════════════════════════════════════ */

/* ── CSS compartido hardware ─────────────────────────────────── */
function buildHWCSS(accent) {
  accent = safeColor(accent, '#e8520a');

  // Lighten accent for gradient top
  function lighten(hex) {
    hex = hex.replace('#','');
    if (hex.length===3) hex=hex.split('').map(function(c){return c+c;}).join('');
    var n=parseInt(hex,16), r=(n>>16)&255, g=(n>>8)&255, b=n&255;
    var l=function(v){return Math.min(255,Math.round(v+(255-v)*0.35));};
    return 'rgb('+l(r)+','+l(g)+','+l(b)+')';
  }
  function hexToRgb(hex) {
    hex = hex.replace('#','');
    if (hex.length===3) hex=hex.split('').map(function(c){return c+c;}).join('');
    var n=parseInt(hex,16);
    return [((n>>16)&255),((n>>8)&255),(n&255)].join(',');
  }

  var accentGrad = 'linear-gradient(160deg,'+lighten(accent)+','+accent+')';
  var accentShadow = 'rgba('+hexToRgb(accent)+',.4)';

  var accentRgb = hexToRgb(accent);
  var accentGradVol = 'linear-gradient(90deg,' + accent + ',' + lighten(accent) + ')';
  return ':host{--hw-ac:' + accent + ';--hw-ac-grad:' + accentGradVol + '}'
  + ':host{display:block !important;width:100%;max-width:100%}'
  + '*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}'
  + '.hw{font-family:"JetBrains Mono",monospace;border-radius:24px;padding:16px;background:linear-gradient(160deg,#fbfbfa,#e4e2dd);box-shadow:0 20px 50px -10px rgba(0,0,0,.3),0 2px 4px rgba(0,0,0,.08),inset 0 1px 0 rgba(255,255,255,.9);position:relative;display:flex;flex-direction:column;gap:12px}'
  + '.hw-strip{position:absolute;right:-3px;top:70px;width:5px;height:70px;border-radius:3px;background:linear-gradient(90deg,#c9c7c1,#f2f0eb);box-shadow:1px 0 2px rgba(0,0,0,.15)}'
  + '.hw-cover{width:100%;aspect-ratio:1;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.25),inset 0 0 0 1px rgba(0,0,0,.06);background:#c9c7c1;display:flex;align-items:center;justify-content:center}'
  + '.hw-cover img{width:100%;height:100%;object-fit:cover;display:block}'
  + '.hw-cover-ph{opacity:.4;font-size:10px;letter-spacing:.5px;color:#888}'
  /* Pantalla */
  + '@keyframes hw-eq{0%,100%{transform:scaleY(1)}25%{transform:scaleY(.3)}50%{transform:scaleY(.7)}75%{transform:scaleY(.15)}}'  + '.hw-screen{background:#050505;border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:6px;box-shadow:inset 0 2px 0 rgba(255,255,255,.1),inset 0 1px 3px rgba(255,255,255,.05),inset 0 -2px 6px rgba(0,0,0,.8),inset 0 0 0 1px rgba(255,255,255,.05),0 0 0 1px rgba(0,0,0,.9),0 0 0 3px rgba(255,255,255,.04),0 16px 40px rgba(0,0,0,.7),0 4px 8px rgba(0,0,0,.5);position:relative;overflow:hidden;cursor:pointer}'  + '.hw-screen::before{content:"";position:absolute;top:0;left:0;right:0;bottom:60%;background:linear-gradient(180deg,rgba(255,255,255,.07) 0%,rgba(255,255,255,.02) 40%,transparent 100%);clip-path:ellipse(90% 100% at 50% 0%);pointer-events:none;z-index:1}'  + '.hw-screen::after{content:"";position:absolute;top:1px;left:1px;right:1px;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.2),rgba(255,255,255,.3),rgba(255,255,255,.2),transparent);pointer-events:none;z-index:2}'  + '.hw-screen-lines{position:absolute;inset:0;background:repeating-linear-gradient(0deg,rgba(255,255,255,.01) 0px,rgba(255,255,255,.01) 1px,transparent 1px,transparent 4px);pointer-events:none;z-index:0}'
  + '.hw-screen-top{display:flex;justify-content:space-between;color:#8a8a8a;font-size:10px;letter-spacing:.5px}'
  + '.hw-status{display:flex;align-items:center;gap:4px}'
  + '.hw-status-dot{width:5px;height:5px;border-radius:50%}'
  + '.hw-status.playing{color:#e2543a}'
  + '.hw-status.playing .hw-status-dot{background:#e2543a;box-shadow:0 0 5px #e2543a}'
  + '.hw-status.paused{color:#8a8a8a}'
  + '.hw-status.paused .hw-status-dot{background:#8a8a8a}'
  + '.hw-waveform{display:flex;align-items:flex-end;gap:3px;height:36px;flex:1}'
  + '.hw-bar{border-radius:2px;flex-shrink:0;transform-origin:bottom}'
  + '.hw-bar.playing{animation:hw-eq .8s ease-in-out infinite}'
  + '.hw-bar.playing:nth-child(2n){animation-delay:.15s}'
  + '.hw-bar.playing:nth-child(3n){animation-delay:.3s}'
  + '.hw-bar.playing:nth-child(4n){animation-delay:.1s}'
  + '.hw-bar.playing:nth-child(5n){animation-delay:.22s}'
  + '.hw-time{color:#f2f2f2;font-size:18px;font-weight:700;letter-spacing:1px;font-family:"JetBrains Mono",monospace;text-shadow:0 0 8px rgba(255,255,255,.15)}'
  /* Track info */
  + '.hw-track-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 2px}'
  + '.hw-track-title{font-size:13px;font-weight:700;color:#2a2a2a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  + '.hw-track-artist{font-size:11px;color:#8a8886;margin-top:1px}'
  + '.hw-track-pos{font-size:10px;color:#aaa;flex-shrink:0}'
  /* Transport */
  + '.hw-transport{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}'
  + '.hw-btn{border-radius:10px;height:50px;display:flex;align-items:center;justify-content:center;cursor:pointer;user-select:none;transition:transform .1s}'
  + '.hw-btn:active{transform:scale(.96)}'
  + '.hw-btn.sec{background:linear-gradient(160deg,#ffffff,#e2e0da);box-shadow:0 1px 2px rgba(0,0,0,.15),inset 0 1px 0 rgba(255,255,255,.9),inset 0 -2px 3px rgba(0,0,0,.06)}'
  + '.hw-btn.pri{background:'+accentGrad+';box-shadow:0 3px 8px '+accentShadow+',inset 0 1px 0 rgba(255,255,255,.5)}'
  /* Volumen */
  + '.hw-vol-row{display:flex;align-items:center;gap:10px;padding:2px 2px 4px}'
  + '.hw-vol-label{font-size:11px;color:#777;flex-shrink:0}'
  + '.hw-vol-track{flex:1;height:5px;background:#dad8d2;border-radius:3px;box-shadow:inset 0 1px 2px rgba(0,0,0,.2);position:relative;cursor:pointer}'
  + '.hw-vol-fill{position:absolute;left:0;top:0;height:5px;background:' + accentGradVol + ';border-radius:3px}'
  + '.hw-vol-thumb{position:absolute;top:50%;width:12px;height:12px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.4);transform:translate(-50%,-50%)}'
  + '.hw-icon-btn{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0}'
  + '.hw-icon-btn.playlist{background:radial-gradient(circle at 35% 30%,#a8a333,#6f6a1c);box-shadow:0 1px 3px rgba(0,0,0,.3),inset 0 1px 1px rgba(255,255,255,.4)}'
  + '.hw-icon-btn.download{background:linear-gradient(160deg,#fff,#ddd);box-shadow:0 1px 2px rgba(0,0,0,.2);font-size:13px;color:#444}'
  /* Playlist */
  + '.hw-pl-header{font-size:12px;color:#555;display:flex;justify-content:space-between;cursor:pointer;border-top:1px solid rgba(0,0,0,.08);padding-top:10px}'
  + '.hw-pl-list{display:flex;flex-direction:column;gap:2px;margin-top:8px}'
  + '.hw-pl-track{display:flex;align-items:center;justify-content:space-between;padding:6px;border-radius:6px;cursor:pointer}'
  + '.hw-pl-track.active{background:rgba(0,0,0,.05)}'
  + '.hw-pl-track:hover{background:rgba(0,0,0,.04)}'
  + '.hw-pl-left{display:flex;align-items:center;gap:8px;min-width:0}'
  + '.hw-pl-mark{width:14px;font-size:10px;flex-shrink:0}'
  + '.hw-pl-title{font-size:11px;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
  + '.hw-pl-dur{font-size:10px;color:#999;flex-shrink:0}'
  /* Grille + brand */
  + '.hw-footer{display:flex;align-items:center;justify-content:space-between;padding-top:2px}'
  + '.hw-grille{display:grid;grid-template-columns:repeat(14,1fr);gap:3px;width:120px}'
  + '.hw-gdot{width:3px;height:3px;border-radius:50%;background:#c9c7c1}'
  + '.hw-brand{font-size:9px;color:#b3b1ab;letter-spacing:1px}'
  + '.hw-pl-dl{display:flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:4px;color:#777;text-decoration:none;flex-shrink:0;background:none;border:none;cursor:pointer;padding:0;transition:color .12s}'
  + '.hw-pl-dl:hover{color:#333}'
  + '.hw-pl-dl.off{opacity:.25;cursor:not-allowed}'
  /* Letra */
  + '.hw-lyr-btn.on{color:var(--hw-ac)}'
  + '.hw-icon-btn.download.btn-lyr-hw{color:#444}'
  + '.hw-icon-btn.btn-lyr-hw.on{color:var(--hw-ac)}'
  + '.hw-lyr{overflow:hidden;transition:height .25s ease}'
  + '.hw-lyr-in{font-size:11px;line-height:1.7;color:#555;white-space:pre-line;padding:4px 8px 12px 28px;max-height:280px;overflow-y:auto}'
  /* Año, licencia, descripción */
  + '.hw-info{padding:0 2px;display:flex;flex-direction:column;gap:4px}'
  + '.hw-info-line{font-size:10px;color:#8a8886;letter-spacing:.3px}'
  + '.hw-info-desc{font-size:11px;line-height:1.55;color:#666;white-space:pre-line}'
  /* Toast */
  + '.hw-toast{position:absolute;left:16px;right:16px;top:12px;background:#1a1a1a;color:#fff;font-size:11px;text-align:center;padding:6px 0;border-radius:8px;box-shadow:0 6px 16px rgba(0,0,0,.3);z-index:10}'
  /* Horizontal */
  + '.hw.horizontal{border-radius:20px}'
  + '.hw-h-body{display:flex;align-items:stretch;gap:16px}'
  + '.hw-h-left{display:flex;flex-direction:column;gap:10px;flex:0 0 200px}'
  + '.hw-h-right{flex:1;min-width:0;display:flex;flex-direction:column;gap:10px}'
  + '.hw-h-left .hw-screen{max-height:188px}'
  /* Mini */
  + '.hw.mini{border-radius:16px;padding:12px}'
  + '.hw-mini-body{display:flex;align-items:center;gap:10px}'
  + '.hw-mini-cover{width:52px;height:52px;border-radius:8px;overflow:hidden;flex-shrink:0;background:#c9c7c1;box-shadow:0 1px 3px rgba(0,0,0,.25)}'
  + '.hw-mini-cover img{width:100%;height:100%;object-fit:cover;display:block}'
  + '.hw-mini-info{flex:1;min-width:0}'
  + '.hw-mini-prog{height:3px;background:#dad8d2;border-radius:2px;position:relative;cursor:pointer;margin-top:5px}'
  + '.hw-mini-prog-fill{height:100%;background:linear-gradient(90deg,#c22a1e,#e6402f);border-radius:2px}'
  + '.hw-mini-ctrl{display:flex;gap:6px;align-items:center;flex-shrink:0}'
  + '.hw-btn.mini-btn{border-radius:8px;height:36px;width:36px}'
  + '.hw-mini-footer{display:flex;align-items:center;gap:10px;margin-top:8px;padding-top:8px;border-top:1px solid rgba(0,0,0,.08)}'
  + '.hw-mini-vol{display:flex;align-items:center;gap:8px;flex:1}'
  /* ── Temas (Light: pantalla blanca · Dark: chasis oscuro) ── */
  + '.hw-bar{background:#3a3a3a}.hw-bar.on{background:#f5f5f5}'
  + '.hw-btn.sec{color:#333}'
  + '.hw-footer{gap:12px}.hw-grille{flex-shrink:1;min-width:0;overflow:hidden}'
  + '.hw-brand{white-space:nowrap;flex-shrink:0}'
  + '.hw-lyr-btn.off{pointer-events:none;cursor:default}'
  + '.hw.t-light .hw-screen{background:#ffffff;box-shadow:inset 0 1px 3px rgba(0,0,0,.10),inset 0 0 0 1px rgba(0,0,0,.08),0 1px 0 rgba(255,255,255,.9),0 2px 6px rgba(0,0,0,.08)}'
  + '.hw.t-light .hw-screen::before,.hw.t-light .hw-screen::after,.hw.t-light .hw-screen-lines{display:none}'
  + '.hw.t-light .hw-screen-top{color:#999}'
  + '.hw.t-light .hw-status.paused{color:#999}.hw.t-light .hw-status.paused .hw-status-dot{background:#bbb}'
  + '.hw.t-light .hw-bar{background:#dedede}.hw.t-light .hw-bar.on{background:#1a1a1a}'
  + '.hw.t-light .hw-time{color:#1a1a1a;text-shadow:none}'
  + '.hw.t-dark{background:linear-gradient(160deg,#2c2c2b,#151515);box-shadow:0 20px 50px -10px rgba(0,0,0,.6),0 2px 4px rgba(0,0,0,.3),inset 0 1px 0 rgba(255,255,255,.08)}'
  + '.t-dark .hw-strip{background:linear-gradient(90deg,#111,#3a3a3a)}'
  + '.t-dark .hw-cover,.t-dark .hw-mini-cover{background:#333;box-shadow:0 1px 3px rgba(0,0,0,.5)}'
  + '.t-dark .hw-cover-ph{color:#777}'
  + '.t-dark .hw-track-title{color:#eeeeee}'
  + '.t-dark .hw-track-artist,.t-dark .hw-info-line{color:#8a8a8a}'
  + '.t-dark .hw-track-pos{color:#666}'
  + '.t-dark .hw-info-desc{color:#aaa}'
  + '.t-dark .hw-btn.sec{background:linear-gradient(160deg,#3b3b3a,#232322);box-shadow:0 1px 2px rgba(0,0,0,.5),inset 0 1px 0 rgba(255,255,255,.08),inset 0 -2px 3px rgba(0,0,0,.3);color:#dddddd}'
  + '.t-dark .hw-vol-label{color:#888}'
  + '.t-dark .hw-vol-track,.t-dark .hw-mini-prog{background:#0d0d0d;box-shadow:inset 0 1px 2px rgba(0,0,0,.6)}'
  + '.t-dark .hw-icon-btn.download{background:linear-gradient(160deg,#3b3b3a,#232322);color:#ddd;box-shadow:0 1px 2px rgba(0,0,0,.5)}'
  + '.t-dark .hw-pl-header{color:#aaa;border-top-color:rgba(255,255,255,.08)}'
  + '.t-dark .hw-pl-track.active{background:rgba(255,255,255,.06)}'
  + '.t-dark .hw-pl-track:hover{background:rgba(255,255,255,.04)}'
  + '.t-dark .hw-pl-title{color:#ddd}'
  + '.t-dark .hw-pl-dur{color:#777}'
  + '.t-dark .hw-pl-dl{color:#999}.t-dark .hw-pl-dl:hover{color:#fff}'
  + '.t-dark .hw-lyr-btn.on,.t-dark .hw-icon-btn.btn-lyr-hw.on{color:var(--hw-ac)}'
  + '.t-dark .hw-lyr-in{color:#aaa}'
  + '.t-dark .hw-gdot{background:#3a3a3a}'
  + '.t-dark .hw-brand{color:#555}'
  + '.t-dark .hw-mini-footer{border-top-color:rgba(255,255,255,.08)}'
  + '.loading{padding:32px;text-align:center;color:#888;font-size:12px;font-family:"JetBrains Mono",monospace}'
  + '.error{color:#e94560;font-size:12px;padding:12px 16px;border-left:2px solid #e94560;margin:12px}';
}

/* ── Helpers hardware ─────────────────────────────────────────── */
function hwWaveform(trackIdx, playedFraction, height) {
  height = height || 36;
  var seed = (trackIdx + 1) * 97;
  var rand = function(){ seed=(seed*9301+49297)%233280; return seed/233280; };
  var count = 22;
  var bars = '';
  var played = Math.round(playedFraction * count);
  for (var i=0; i<count; i++) {
    var h = Math.round(6 + rand() * (height - 10));
    bars += '<div class="hw-bar'+(i < played ? ' on' : '')+'" style="width:5px;height:'+h+'px"></div>';
  }
  // playhead
  var pctX = Math.round(playedFraction * 100);
  return '<div class="hw-waveform" style="height:'+height+'px;position:relative">'
    + bars
    + '<div style="position:absolute;left:'+pctX+'%;top:0;bottom:0;width:2px;background:#ff5a3c;border-radius:1px;transform:translateX(-50%)"></div>'
    + '</div>';
}

function hwGrille() {
  var d = '';
  for (var i=0;i<42;i++) d += '<div class="hw-gdot"></div>';
  return '<div class="hw-grille">' + d + '</div>';
}

function hwTransport() {
  return '<div class="hw-transport">'
    + '<div class="hw-btn sec btn-prev"><svg width="16" height="16" viewBox="0 0 16 16"><rect x="1" y="2" width="2" height="12" fill="currentColor"/><polygon points="15,2 4,8 15,14" fill="currentColor"/></svg></div>'
    + '<div class="hw-btn pri btn-play"><span class="i-play"><svg width="18" height="18" viewBox="0 0 18 18"><polygon points="4,2 16,9 4,16" fill="#fff"/></svg></span><span class="i-pause" style="display:none"><svg width="16" height="16" viewBox="0 0 16 16"><rect x="3" y="2" width="3.5" height="12" fill="#fff"/><rect x="9.5" y="2" width="3.5" height="12" fill="#fff"/></svg></span></div>'
    + '<div class="hw-btn sec btn-next"><svg width="16" height="16" viewBox="0 0 16 16"><rect x="13" y="2" width="2" height="12" fill="currentColor"/><polygon points="1,2 12,8 1,14" fill="currentColor"/></svg></div>'
    + '</div>';
}

function hwVolRow() {
  return '<div class="hw-vol-row">'
    + '<span class="hw-vol-label">VOL</span>'
    + '<div class="hw-vol-track vol-track"><div class="hw-vol-fill vol-fill" style="width:75%"></div><div class="hw-vol-thumb" style="left:75%"></div></div>'
    + '</div>';
}

function hwLyrIcon() {
  return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="11" x2="20" y2="11"/><line x1="4" y1="16" x2="13" y2="16"/></svg>';
}

function hwPlaylist(tracks, current, accent, show, open, mode) {
  show = show || {};
  var items = tracks.map(function(t, i) {
    var active = i === current;
    var markColor = active ? safeColor(accent, '#e8520a') : '#999';
    var hasLyr = show.letra !== false && t.letra;
    var lyr = show.letra === false ? ''
      : hasLyr ? '<button class="hw-pl-dl hw-lyr-btn" data-index="'+i+'" title="'+esc(T.lyrics)+'" aria-label="'+esc(T.lyrics)+'" aria-expanded="false">'+hwLyrIcon()+'</button>'
      :          '<span class="hw-pl-dl hw-lyr-btn off" title="'+esc(T.noLyrics)+'" aria-hidden="true">'+hwLyrIcon()+'</span>';
    var dl = t.descargable
      ? '<a href="'+escUrl(t.audio_url)+'" download class="hw-pl-dl" title="'+esc(T.download)+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></a>'
      : '<span class="hw-pl-dl off"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>';
    return '<div class="hw-pl-track' + (active?' active':'') + '" data-index="'+i+'">'
      + '<div class="hw-pl-left"><span class="hw-pl-mark" style="color:'+markColor+'">'+(active?'●':'▸')+'</span>'
      + '<span class="hw-pl-title">'+esc(t.titulo)+'</span></div>'
      + '<div style="display:flex;align-items:center;gap:4px"><span class="hw-pl-dur" style="margin-right:4px">'+fmt(t.duration || 0)+'</span>'+lyr+dl+'</div>'
      + '</div>'
      + (hasLyr && mode !== 'modal' ? '<div class="hw-lyr" data-index="'+i+'" style="height:0px"><div class="hw-lyr-in">'+esc(t.letra)+'</div></div>' : '');
  }).join('');
  return '<div class="hw-pl-header hw-pl-toggle">'
    + '<span style="display:flex;align-items:center;gap:6px"><span class="hw-pl-caret" style="font-size:16px">'+(open?'▾':'▸')+'</span>PLAYLIST</span>'
    + '<span>'+tracks.length+' '+esc(T.tracks)+'</span></div>'
    + '<div class="hw-pl-list"'+(open?'':' style="display:none"')+'>'+items+'</div>';
}

// Año · licencia · descripción en estilo hardware
function hwInfo(d, show) {
  var info = discInfo(d, show);
  var line = [info.anio, info.lic].filter(Boolean).join(' · ');
  if (!line && !info.desc) return '';
  return '<div class="hw-info">'
    + (line ? '<div class="hw-info-line">'+line+'</div>' : '')
    + (info.desc ? '<div class="hw-info-desc">'+esc(d.descripcion)+'</div>' : '')
    + '</div>';
}

function hwClock() {
  var now = new Date();
  return now.toLocaleTimeString('es-ES',{hour:'2-digit',minute:'2-digit'});
}

/* ── Render: VERTICAL ────────────────────────────────────────── */
function renderVertical(d, tracks, accent, show, opts) {
  show = show || {}; opts = opts || {};
  var first = tracks[0] || {};
  var portada = d.portada ? '<img src="'+escUrl(d.portada)+'" alt="'+esc(d.titulo)+'">' : '<span class="hw-cover-ph">PORTADA</span>';
  return '<style>'+buildHWCSS(accent)+'</style>'
    + '<div class="hw vertical t-'+(opts.theme === 'dark' ? 'dark' : 'light')+'">'
    + '<div class="hw-strip"></div>'
    + (show.cover !== false ? '<div class="hw-cover">'+portada+'</div>' : '')
    + '<div class="hw-screen hw-seek">'
      + '<div class="hw-screen-lines"></div>'
      + '<div class="hw-screen-top"><span class="hw-clock">'+hwClock()+'</span><span class="hw-status paused"><span class="hw-status-dot"></span><span class="hw-status-label">'+esc(T.paused)+'</span></span></div>'
      + hwWaveform(0, 0, 36)
      + '<div class="hw-time"><span class="t-cur">0:00</span>&nbsp;/&nbsp;<span class="t-tot">0:00</span></div>'
    + '</div>'
    + '<div class="hw-track-row"><div style="min-width:0"><div class="hw-track-title np-title">'+esc(first.titulo||d.titulo)+'</div><div class="hw-track-artist np-artist">'+esc(trackMeta(first, show, opts.perTrack))+'</div></div><div class="hw-track-pos np-pos">1/'+tracks.length+'</div></div>'
    + hwInfo(d, show)
    + hwTransport()
    + hwVolRow()
    + '<div>'+hwPlaylist(tracks, 0, accent, show, opts.open !== false, opts.lyrics)+'</div>'
    + '<div class="hw-footer">'+hwGrille()+'<span class="hw-brand">SPINDLE MP3 PLAYER</span></div>'
    + '</div>';
}

/* ── Render: HORIZONTAL ──────────────────────────────────────── */
function renderHorizontal(d, tracks, accent, show, opts) {
  show = show || {}; opts = opts || {};
  var first = tracks[0] || {};
  var portada = d.portada ? '<img src="'+escUrl(d.portada)+'" alt="'+esc(d.titulo)+'">' : '<span class="hw-cover-ph">PORTADA</span>';
  return '<style>'+buildHWCSS(accent)+'</style>'
    + '<div class="hw horizontal t-'+(opts.theme === 'dark' ? 'dark' : 'light')+'">'
    + '<div class="hw-h-body">'
      + '<div class="hw-h-left">'
        + (show.cover !== false ? '<div class="hw-cover" style="height:auto">'+portada+'</div>' : '')
        + '<div class="hw-screen hw-seek" style="flex:1">'
          + '<div class="hw-screen-lines"></div>'
          + '<div class="hw-screen-top"><span class="hw-clock">'+hwClock()+'</span><span class="hw-status paused"><span class="hw-status-dot"></span><span class="hw-status-label">'+esc(T.paused)+'</span></span></div>'
          + hwWaveform(0, 0, 28)
          + '<div class="hw-time" style="font-size:14px"><span class="t-cur">0:00</span>&nbsp;/&nbsp;<span class="t-tot">0:00</span></div>'
        + '</div>'
      + '</div>'
      + '<div class="hw-h-right">'
        + '<div class="hw-track-row" style="padding:0"><div style="min-width:0"><div class="hw-track-title np-title" style="font-size:15px">'+esc(first.titulo||d.titulo)+'</div><div class="hw-track-artist np-artist">'+esc(trackMeta(first, show, opts.perTrack))+'</div></div><div class="hw-track-pos np-pos">1/'+tracks.length+'</div></div>'
        + hwInfo(d, show)
        + hwTransport()
        + hwVolRow()
        + '<div>'+hwPlaylist(tracks, 0, accent, show, opts.open !== false, opts.lyrics)+'</div>'
        + '<div class="hw-footer">'+hwGrille()+'<span class="hw-brand">SPINDLE MP3 PLAYER</span></div>'
      + '</div>'
    + '</div>'
    + '</div>';
}

/* ── Render: MINI ────────────────────────────────────────────── */
function renderMini(d, tracks, accent, show, opts) {
  show = show || {}; opts = opts || {};
  var first = tracks[0] || {};
  var portada = d.portada ? '<img src="'+escUrl(d.portada)+'" alt="'+esc(d.titulo)+'">' : '';
  var anyLyr  = show.letra !== false;
  var inlineLyr = anyLyr && opts.lyrics !== 'modal';
  return '<style>'+buildHWCSS(accent)+'</style>'
    + '<div class="hw mini t-'+(opts.theme === 'dark' ? 'dark' : 'light')+'">'
    + '<div class="hw-mini-body">'
      + (show.cover !== false ? '<div class="hw-mini-cover">'+portada+'</div>' : '')
      + '<div class="hw-mini-info">'
        + '<div class="hw-track-title np-title" style="font-size:12px">'+esc(first.titulo||d.titulo)+'</div>'
        + '<div class="hw-track-artist np-artist" style="font-size:10px">'+esc(trackMeta(first, show, opts.perTrack))+'</div>'
        + '<div class="hw-mini-prog hw-seek"><div class="hw-mini-prog-fill prog-fill-hw" style="width:0%"></div></div>'
      + '</div>'
      + '<div class="hw-mini-ctrl">'
        + '<div class="hw-btn sec mini-btn btn-prev"><svg width="12" height="12" viewBox="0 0 16 16"><rect x="1" y="2" width="2" height="12" fill="currentColor"/><polygon points="15,2 4,8 15,14" fill="currentColor"/></svg></div>'
        + '<div class="hw-btn pri mini-btn btn-play"><span class="i-play"><svg width="14" height="14" viewBox="0 0 18 18"><polygon points="4,2 16,9 4,16" fill="#fff"/></svg></span><span class="i-pause" style="display:none"><svg width="12" height="12" viewBox="0 0 16 16"><rect x="3" y="2" width="3.5" height="12" fill="#fff"/><rect x="9.5" y="2" width="3.5" height="12" fill="#fff"/></svg></span></div>'
        + '<div class="hw-btn sec mini-btn btn-next"><svg width="12" height="12" viewBox="0 0 16 16"><rect x="13" y="2" width="2" height="12" fill="currentColor"/><polygon points="1,2 12,8 1,14" fill="currentColor"/></svg></div>'
      + '</div>'
    + '</div>'
    + '<div class="hw-mini-footer">'
      + '<div class="hw-mini-vol"><span class="hw-vol-label">VOL</span><div class="hw-vol-track vol-track"><div class="hw-vol-fill vol-fill" style="width:75%"></div><div class="hw-vol-thumb" style="left:75%"></div></div></div>'
      + '<div style="display:flex;gap:6px">'
        + (anyLyr ? '<div class="hw-icon-btn download btn-lyr-hw" title="'+esc(first.letra ? T.lyrics : T.noLyrics)+'" role="button" tabindex="0" aria-expanded="false"'+(first.letra ? '' : ' style="opacity:.3;pointer-events:none"')+'>'+hwLyrIcon()+'</div>' : '')
        + '<div class="hw-icon-btn download btn-dl-hw" title="'+esc(T.download)+'">↓</div>'
      + '</div>'
    + '</div>'
    + (inlineLyr ? '<div class="hw-lyr hw-mini-lyr" style="height:0px"><div class="hw-lyr-in" style="padding-left:6px">'+esc(first.letra || '')+'</div></div>' : '')
    + '<span class="hw-brand" style="display:block;text-align:right;margin-top:4px;font-size:8px">SPINDLE MP3 PLAYER</span>'
    + '</div>';
}

/* ── Motor hardware ──────────────────────────────────────────── */
class HWPlayerEngine {
  constructor(root, tracks, accent, show, opts) {
    this.root    = root;
    this.tracks  = tracks;
    this.accent  = accent || '#e8520a';
    this.show    = show || {};
    this.opts    = opts || {};
    this.current = 0;
    this.howl    = null;
    this.seeking = false;
    this._raf    = null;
    this._vol    = 75;
    this._plOpen = this.opts.open !== false;
  }

  q(s)  { return this.root.querySelector(s); }
  qa(s) { return this.root.querySelectorAll(s); }
  modalOpts(trigger) { return { theme: this.opts.theme, accent: this.accent, show: this.show, mono: true, trigger: trigger }; }

  bindEvents() {
    this.q('.btn-play') ?.addEventListener('click', () => this.togglePlay());
    this.q('.btn-prev') ?.addEventListener('click', () => this.prev());
    this.q('.btn-next') ?.addEventListener('click', () => this.next());

    // Seek en pantalla o barra mini
    ['.hw-seek', '.hw-mini-prog'].forEach(sel => {
      var el = this.q(sel);
      if (!el) return;
      el.addEventListener('click', e => {
        if (!this.howl) return;
        var r = el.getBoundingClientRect();
        var p = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
        this.howl.seek(p * (this.howl.duration() || 0));
      });
    });

    // Volumen
    var vt = this.q('.vol-track');
    if (vt) {
      var doVol = e => {
        var r = vt.getBoundingClientRect();
        var p = Math.max(0, Math.min(100, Math.round((e.clientX - r.left) / r.width * 100)));
        this._vol = p;
        this._setVol(p);
        if (this.howl) this.howl.volume(p/100);
      };
      vt.addEventListener('mousedown', e => {
        doVol(e);
        var mv = e => doVol(e);
        var up = () => { document.removeEventListener('mousemove', mv); document.removeEventListener('mouseup', up); };
        document.addEventListener('mousemove', mv);
        document.addEventListener('mouseup', up);
      });
    }

    // Playlist toggle
    var plh = this.q('.hw-pl-toggle');
    if (plh) {
      plh.addEventListener('click', () => {
        this._plOpen = !this._plOpen;
        var list = this.q('.hw-pl-list');
        var caret = this.q('.hw-pl-caret');
        if (list) list.style.display = this._plOpen ? '' : 'none';
        if (caret) caret.textContent = this._plOpen ? '▾' : '▸';
      });
    }

    // Playlist tracks
    this.qa('.hw-pl-track').forEach(row => {
      row.addEventListener('click', e => {
        if (e.target.closest('.hw-pl-dl')) return; // descarga / letra no cambian de pista
        var idx = parseInt(row.dataset.index);
        if (!isNaN(idx)) this.loadTrack(idx, true);
      });
    });

    // Letras en la playlist
    bindLyrics(this, '.hw-lyr-btn', '.hw-lyr');

    // Mini: letra de la pista actual
    var lyrBtn = this.q('.btn-lyr-hw');
    if (lyrBtn) {
      lyrBtn.addEventListener('click', () => {
        if (this.opts.lyrics === 'modal') { openLyricsModal(this.tracks[this.current], this.modalOpts(lyrBtn)); return; }
        var open = !lyrBtn.classList.contains('on');
        lyrBtn.classList.toggle('on', open);
        lyrBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        setCollapse(this.q('.hw-mini-lyr'), open, true);
      });
    }

    // Download current
    var dlBtn = this.q('.btn-dl-hw');
    if (dlBtn) {
      dlBtn.addEventListener('click', () => {
        var t = this.tracks[this.current];
        if (t && t.descargable && t.audio_url) {
          var a = document.createElement('a');
          a.href = t.audio_url; a.download = t.titulo + '.mp3';
          a.click();
        }
      });
    }

    // Clock update
    this._clockTimer = setInterval(() => {
      var cl = this.q('.hw-clock');
      if (cl) cl.textContent = hwClock();
    }, 30000);
  }

  loadTrack(idx, autoplay) {
    if (MP3P_ACTIVE && MP3P_ACTIVE !== this) MP3P_ACTIVE.stop();
    if (this.howl) { this.howl.unload(); this.howl = null; }
    if (this._raf) { cancelAnimationFrame(this._raf); this._raf = null; }
    this.current = idx;
    var t = this.tracks[idx];

    // Actualizar UI
    var nt = this.q('.np-title'); if (nt) nt.textContent = t.titulo;
    var na = this.q('.np-artist'); if (na) na.textContent = trackMeta(t, this.show, this.opts.perTrack);

    // Mini: actualizar la letra de la pista actual
    var ml = this.q('.hw-mini-lyr');
    var lb = this.q('.btn-lyr-hw');
    if (lb) {
      var txt = this.show.letra !== false ? (t.letra || '') : '';
      if (ml) ml.querySelector('.hw-lyr-in').textContent = txt;
      lb.style.opacity = txt ? '' : '.3';
      lb.style.pointerEvents = txt ? '' : 'none';
      lb.title = txt ? T.lyrics : T.noLyrics;
      if (!txt && ml && lb.classList.contains('on')) { lb.classList.remove('on'); setCollapse(ml, false, true); }
    }
    var np = this.q('.np-pos'); if (np) np.textContent = (idx+1)+'/'+this.tracks.length;

    // Waveform
    var wf = this.q('.hw-waveform');
    if (wf) {
      var parent = wf.parentElement;
      var h = wf.offsetHeight || 36;
      wf.outerHTML = hwWaveform(idx, 0, h);
    }

    // Playlist activa
    this.qa('.hw-pl-track').forEach((row, i) => {
      row.classList.toggle('active', i === idx);
      var mark = row.querySelector('.hw-pl-mark');
      if (mark) { mark.textContent = i===idx?'●':'▸'; mark.style.color = i===idx ? this.accent : '#999'; }
    });

    this.howl = new Howl({
      src: [t.audio_url], html5: true, format: ['mp3'], volume: this._vol/100,
      onplay: () => {
        MP3P_ACTIVE = this;
        this._setPlayState(true);
        var loop = () => {
          if (!this.howl?.playing()) return;
          var seek = this.howl.seek() || 0;
          var dur  = this.howl.duration() || 1;
          if (!this.seeking) {
            this._setProgress(seek/dur);
            var tc = this.q('.t-cur'); if (tc) tc.textContent = fmt(seek);
            var tt = this.q('.t-tot'); if (tt) tt.textContent = fmt(dur);
          }
          this._raf = requestAnimationFrame(loop);
        };
        this._raf = requestAnimationFrame(loop);
      },
      onpause: () => this._setPlayState(false),
      onstop:  () => this._setPlayState(false),
      onend:   () => this.next(),
      onload:  () => {
        var tt = this.q('.t-tot'); if (tt) tt.textContent = fmt(this.howl.duration());
      },
      onloaderror: (id, e) => console.error('HW player error:', e),
    });

    if (autoplay) this.howl.play();
  }

  togglePlay() {
    if (!this.howl) { this.loadTrack(this.current, true); return; }
    if (this.howl.playing()) {
      this.howl.pause();
      if (MP3P_ACTIVE === this) MP3P_ACTIVE = null;
    } else {
      if (MP3P_ACTIVE && MP3P_ACTIVE !== this) MP3P_ACTIVE.stop();
      MP3P_ACTIVE = this;
      this.howl.play();
    }
  }

  stop() {
    if (this.howl) this.howl.pause();
    if (this._raf) { cancelAnimationFrame(this._raf); this._raf = null; }
    this._setPlayState(false);
    if (MP3P_ACTIVE === this) MP3P_ACTIVE = null;
  }

  prev() { this.loadTrack(this.current > 0 ? this.current-1 : this.tracks.length-1, true); }
  next() { this.loadTrack(this.current < this.tracks.length-1 ? this.current+1 : 0, !!(this.howl?.playing())); }

  _setProgress(pct) {
    pct = Math.max(0, Math.min(1, pct));
    // Mini bar
    var mf = this.q('.prog-fill-hw'); if (mf) mf.style.width = (pct*100)+'%';
    // Waveform — recolor bars
    var bars = this.qa('.hw-bar');
    var played = Math.round(pct * bars.length);
    bars.forEach((b, i) => { b.classList.toggle('on', i < played); });
    // Playhead line
    var ph = this.q('.hw-waveform + div, .hw-waveform ~ div');
    // playhead inside waveform wrapper
    var wfw = this.q('.hw-waveform')?.parentElement;
    if (wfw) {
      var pl = wfw.querySelector('div[style*="border-radius:1px"]');
      if (pl) pl.style.left = (pct*100)+'%';
    }
  }

  _setVol(pct) {
    var vf = this.q('.vol-fill'); if (vf) vf.style.width = pct+'%';
    var vt = this.q('.hw-vol-thumb'); if (vt) vt.style.left = pct+'%';
  }

  _setPlayState(playing) {
    var ip = this.q('.i-play');  if (ip) ip.style.display = playing ? 'none' : '';
    var iP = this.q('.i-pause'); if (iP) iP.style.display = playing ? '' : 'none';
    var st = this.q('.hw-status');
    if (st) {
      st.className = 'hw-status ' + (playing ? 'playing' : 'paused');
      var sl = st.querySelector('.hw-status-label');
      if (sl) sl.textContent = playing ? T.playing : T.paused;
    }
    // Animar barras del ecualizador
    this.qa('.hw-bar').forEach(function(b) {
      b.classList.toggle('playing', playing);
    });
  }
}


/* ── Registrar ───────────────────────────────────────────────── */
if (!customElements.get('mp3p-disco'))    customElements.define('mp3p-disco',    Mp3pDisco);
if (!customElements.get('mp3p-cancion'))  customElements.define('mp3p-cancion',  Mp3pCancion);
if (!customElements.get('mp3p-playlist')) customElements.define('mp3p-playlist', Mp3pPlaylist);

})();
