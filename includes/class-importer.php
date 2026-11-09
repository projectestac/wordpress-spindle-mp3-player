<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Importer {

    public static function register() {
        add_action( 'wp_ajax_mp3p_create_disco',      [ __CLASS__, 'ajax_create_disco' ] );
        add_action( 'wp_ajax_mp3p_upload_track',      [ __CLASS__, 'ajax_upload_track' ] );
        add_action( 'wp_ajax_mp3p_import_from_media', [ __CLASS__, 'ajax_import_from_media' ] );
        add_action( 'wp_ajax_mp3p_read_tags',         [ __CLASS__, 'ajax_read_tags' ] );
        add_action( 'wp_ajax_mp3p_get_cover',         [ __CLASS__, 'ajax_get_cover' ] );
    }

    public static function render() {
        if ( ! current_user_can('manage_options') ) return;
        $max = ini_get('upload_max_filesize');
        ?>
        <div class="wrap mp3p-importer">
            <h1><?php esc_html_e( 'Importar MP3', 'spindle-mp3-player' ); ?></h1>

            <nav class="nav-tab-wrapper" style="margin-bottom:20px">
                <a href="#" class="nav-tab nav-tab-active" data-tab="upload">📁 <?php esc_html_e( 'Subir archivos', 'spindle-mp3-player' ); ?></a>
                <a href="#" class="nav-tab" data-tab="media">🗂 <?php esc_html_e( 'Desde biblioteca de medios', 'spindle-mp3-player' ); ?></a>
            </nav>

            <!-- PESTAÑA 1: Subir archivos -->
            <div id="mp3p-tab-upload">
                <div class="mp3p-step" id="mp3p-step-1">
                    <h2><?php esc_html_e( 'Paso 1 — Selecciona los archivos MP3', 'spindle-mp3-player' ); ?></h2>
                    <div class="mp3p-upload-area" id="mp3p_upload_area">
                        <p><?php esc_html_e( 'Arrastra los archivos MP3 aquí o', 'spindle-mp3-player' ); ?></p>
                        <label class="button button-primary" for="mp3p_files_input"><?php esc_html_e( 'Seleccionar archivos', 'spindle-mp3-player' ); ?></label>
                        <input type="file" id="mp3p_files_input" accept="audio/mpeg,.mp3" multiple style="display:none" />
                        <p class="description" style="margin-top:8px"><?php
                            printf(
                                /* translators: %s: max upload size, e.g. "2M" */
                                esc_html__( 'Máximo por archivo: %s · Puedes seleccionar varios a la vez', 'spindle-mp3-player' ),
                                esc_html($max)
                            );
                        ?></p>
                    </div>
                    <div id="mp3p_read_progress" style="display:none;margin-top:16px">
                        <p><strong><?php esc_html_e( 'Leyendo etiquetas ID3...', 'spindle-mp3-player' ); ?></strong></p>
                        <div class="mp3p-progress-bar-wrap">
                            <div class="mp3p-progress-bar" id="mp3p_read_bar"></div>
                        </div>
                        <p id="mp3p_read_label" style="font-size:13px;color:#555"></p>
                    </div>
                </div>

                <div class="mp3p-step" id="mp3p-step-2" style="display:none">
                    <h2><?php esc_html_e( 'Paso 2 — Revisa y confirma', 'spindle-mp3-player' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Revisa los datos, edita lo que necesites y confirma la importación disco a disco.', 'spindle-mp3-player' ); ?></p>
                    <div id="mp3p_preview"></div>
                </div>
            </div>

            <!-- PESTAÑA 2: Desde biblioteca -->
            <div id="mp3p-tab-media" style="display:none">
                <div class="mp3p-step">
                    <h2><?php esc_html_e( 'Importar desde la biblioteca de medios', 'spindle-mp3-player' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Selecciona archivos MP3 que ya están en tu biblioteca de medios de WordPress. El plugin los agrupará por álbum y creará los discos automáticamente.', 'spindle-mp3-player' ); ?></p>

                    <div style="display:flex;gap:12px;align-items:center;margin:16px 0">
                        <button type="button" id="mp3p_open_media" class="button button-primary">
                            <span class="dashicons dashicons-playlist-audio" style="margin-top:3px"></span>
                            <?php esc_html_e( 'Seleccionar MP3 de la biblioteca', 'spindle-mp3-player' ); ?>
                        </button>
                        <span id="mp3p_media_count" style="color:#555;font-size:13px"></span>
                    </div>

                    <div id="mp3p_media_selected" style="display:none;margin-top:8px">
                        <div id="mp3p_media_list" style="margin-bottom:16px;max-height:300px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px"></div>
                        <button type="button" id="mp3p_media_process" class="button button-primary"><?php esc_html_e( 'Agrupar y crear discos', 'spindle-mp3-player' ); ?></button>
                        <div id="mp3p_media_read" style="display:none;margin-top:16px">
                            <p><strong><?php esc_html_e( 'Leyendo etiquetas ID3...', 'spindle-mp3-player' ); ?></strong></p>
                            <div class="mp3p-progress-bar-wrap"><div class="mp3p-progress-bar" id="mp3p_media_read_bar"></div></div>
                            <p id="mp3p_media_read_label" style="font-size:13px;color:#555"></p>
                        </div>
                    </div>

                    <div id="mp3p_media_result" style="display:none;margin-top:16px"></div>
                </div>
            </div>
        </div>

        <?php ob_start(); ?>
        (function($){
            var ajaxUrl = mp3pImporter.ajaxUrl;
            var nonce   = mp3pImporter.nonce;
            var i18n    = mp3pImporter.i18n;

            // ── Pestañas ──────────────────────────────────────────
            $('.nav-tab').on('click', function(e){
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                var tab = $(this).data('tab');
                $('#mp3p-tab-upload, #mp3p-tab-media').hide();
                $('#mp3p-tab-' + tab).show();
            });

            // ══════════════════════════════════════════════════════
            // PESTAÑA 1 — Subir archivos
            // ══════════════════════════════════════════════════════
            var allFiles = [];
            var processed = [];

            var $area = $('#mp3p_upload_area');
            $area.on('dragover', function(e){ e.preventDefault(); $area.addClass('mp3p-drag-over'); });
            $area.on('dragleave drop', function(){ $area.removeClass('mp3p-drag-over'); });
            $area.on('drop', function(e){ e.preventDefault(); handleFiles(e.originalEvent.dataTransfer.files); });
            $('#mp3p_files_input').on('change', function(){ handleFiles(this.files); });

            function handleFiles(files) {
                allFiles = Array.from(files).filter(function(f){
                    return f.type === 'audio/mpeg' || f.name.toLowerCase().endsWith('.mp3');
                });
                if (!allFiles.length) { alert(i18n.noMp3Detected); return; }
                processFiles();
            }

            function processFiles() {
                processed = [];
                $('#mp3p_read_progress').show();
                var total = allFiles.length;
                var index = 0;

                function processNext() {
                    if (index >= total) {
                        $('#mp3p_read_bar').css('width', '100%');
                        $('#mp3p_read_label').text('✓ ' + i18n.readingComplete + ' (' + total + '/' + total + ')');
                        var groups = groupByAlbum(processed);
                        renderPreview(groups);
                        $('#mp3p-step-2').show();
                        $('html,body').animate({ scrollTop: $('#mp3p-step-2').offset().top - 40 }, 400);
                        return;
                    }
                    var file = allFiles[index];
                    $('#mp3p_read_bar').css('width', Math.round((index / total) * 100) + '%');
                    $('#mp3p_read_label').text('(' + (index+1) + '/' + total + ') ' + file.name);

                    var fallback = {
                        file: file, title: file.name.replace(/\.mp3$/i,''),
                        album: i18n.noAlbum, artist: '', composer: '',
                        year: '', track: index + 1, cover: '',
                    };

                    try {
                        jsmediatags.read(file, {
                            onSuccess: function(result) {
                                var tags  = result.tags || {};
                                var cover = '';
                                if (tags.picture) {
                                    var bytes = new Uint8Array(tags.picture.data);
                                    var b64   = '';
                                    bytes.forEach(function(b){ b64 += String.fromCharCode(b); });
                                    cover = 'data:' + tags.picture.format + ';base64,' + btoa(b64);
                                }
                                var trackNum = parseInt(tags.track) || (index + 1);
                                if (typeof tags.track === 'string' && tags.track.indexOf('/') > -1) {
                                    trackNum = parseInt(tags.track.split('/')[0]) || (index + 1);
                                }
                                processed.push({
                                    file: file,
                                    lyrics:   hasLyricsTag(tags.lyrics),
                                    title:    tags.title    || fallback.title,
                                    album:    tags.album    || i18n.noAlbum,
                                    artist:   tags.artist   || '',
                                    composer: tags.composer || tags.artist || '',
                                    year:     tags.year     || '',
                                    track:    trackNum,
                                    cover:    cover,
                                });
                                index++; processNext();
                            },
                            onError: function() { processed.push(fallback); index++; processNext(); }
                        });
                    } catch(e) { processed.push(fallback); index++; processNext(); }
                }
                processNext();
            }

            // Solo para el indicador de la vista previa: la letra real la lee el servidor (getID3) al importar.
            function hasLyricsTag(l) {
                if (!l) return false;
                if (typeof l === 'string') return l.trim() !== '';
                return !!(l.lyrics && String(l.lyrics).trim() !== '');
            }

            function lyricsBadge(has) {
                return has
                    ? '<span class="mp3p-lyr-badge" title="' + esc(i18n.fileHasLyrics) + '">' + esc(i18n.lyricsCol) + '</span>'
                    : '<span class="mp3p-lyr-badge mp3p-lyr-badge--off" aria-hidden="true">—</span>';
            }

            function groupByAlbum(tracks) {
                var groups = {};
                tracks.forEach(function(t) {
                    var key = (t.album || i18n.noAlbum).trim();
                    if (!groups[key]) groups[key] = [];
                    groups[key].push(t);
                });
                Object.keys(groups).forEach(function(k) {
                    groups[k].sort(function(a,b){ return a.track - b.track; });
                });
                return groups;
            }

            function renderPreview(groups) {
                var html = '';
                var groupKeys = Object.keys(groups);
                groupKeys.forEach(function(albumName, groupIdx) {
                    var tracks = groups[albumName];
                    var albumId = 'alb_' + Math.random().toString(36).substr(2,6);
                    var cover  = (tracks.find(function(t){ return t.cover; }) || {}).cover || '';
                    var first  = tracks[0] || {};

                    html += '<div class="mp3p-album-preview" data-album="' + albumId + '" data-group-idx="' + groupIdx + '">';
                    html += '<div class="mp3p-album-preview__header">';
                    if (cover) {
                        html += '<img src="' + cover + '" class="mp3p-album-preview__cover" />';
                    } else {
                        html += '<div class="mp3p-album-preview__cover mp3p-album-preview__cover--empty"><span class="dashicons dashicons-album"></span></div>';
                    }
                    html += '<div class="mp3p-album-preview__meta">';
                    html += '<h3>' + esc(albumName) + ' <small style="font-weight:400;color:#888">(' + tracks.length + ' ' + i18n.tracksSuffix + ')</small></h3>';
                    html += '<table class="mp3p-edit-table">';
                    html += field(i18n.albumTitleLabel, 'disco_titulo',      albumName);
                    html += field(i18n.authorsLabel,    'disco_autores',     first.composer || first.artist || '');
                    html += field(i18n.performersLabel, 'disco_interpretes', first.artist   || '');
                    html += field(i18n.yearLabel,       'disco_anio',        first.year     || '');
                    html += '</table>';
                    html += '<button type="button" class="button button-primary mp3p-import-btn" style="margin-top:10px">' + i18n.importThisAlbum + '</button>';
                    html += '<span class="mp3p-import-status"></span>';
                    html += '</div></div>';

                    html += '<div class="mp3p-tracks-table"><table><thead><tr><th>#</th><th>' + i18n.thTitle + '</th><th>' + i18n.thAuthor + '</th><th>' + i18n.thPerformers + '</th><th>' + i18n.lyricsCol + '</th><th>↓</th><th style="width:110px">' + i18n.status + '</th></tr></thead><tbody>';
                    tracks.forEach(function(t, ti) {
                        html += '<tr>';
                        html += '<td><input type="number" class="t-orden" value="' + t.track + '" style="width:65px"></td>';
                        html += '<td><input type="text" class="t-titulo" value="' + esc(t.title) + '" style="width:180px"></td>';
                        html += '<td><input type="text" class="t-autor" value="' + esc(t.artist||'') + '" style="width:130px"></td>';
                        html += '<td><input type="text" class="t-interpretes" value="' + esc(t.artist||'') + '" style="width:130px"></td>';
                        html += '<td class="t-lyr">' + lyricsBadge(t.lyrics) + '</td>';
                        html += '<td><input type="checkbox" class="t-dl" checked title="' + i18n.downloadable + '"></td>';
                        html += '<td>'
                              + '<div class="mp3p-row-progress-wrap" style="height:6px;background:#e5e5e5;border-radius:3px;overflow:hidden">'
                              + '<div class="mp3p-row-progress" style="height:100%;width:0%;background:#2271b1;transition:width .2s"></div>'
                              + '</div>'
                              + '<span class="mp3p-row-status" style="font-size:11px;color:#888"></span>'
                              + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';

                    html += '<div class="mp3p-upload-progress" style="display:none;margin-top:12px">';
                    html += '<div class="mp3p-progress-bar-wrap"><div class="mp3p-progress-bar mp3p-progress-bar--album"></div></div>';
                    html += '<p class="mp3p-progress-label" style="font-size:12px;color:#555;margin-top:4px"></p>';
                    html += '</div></div>';
                });
                $('#mp3p_preview').html(html);
                bindImportButtons(groups);
            }

            function field(label, name, val) {
                return '<tr><td style="white-space:nowrap;padding-right:10px"><strong>' + label + '</strong></td>'
                    + '<td><input type="text" name="' + name + '" value="' + esc(val) + '" style="width:280px"></td></tr>';
            }

            function bindImportButtons(groups) {
                var groupKeys = Object.keys(groups);
                $('.mp3p-import-btn').on('click', function(){
                    var $btn   = $(this);
                    var $box   = $btn.closest('.mp3p-album-preview');
                    var tracks = groups[groupKeys[$box.data('group-idx')]];

                    var disco = {
                        titulo:      $box.find('[name="disco_titulo"]').val(),
                        autores:     $box.find('[name="disco_autores"]').val(),
                        interpretes: $box.find('[name="disco_interpretes"]').val(),
                        anio:        $box.find('[name="disco_anio"]').val(),
                        cover:       (tracks.find(function(t){ return t.cover; }) || {}).cover || '',
                    };

                    var tracksMeta = [];
                    $box.find('.mp3p-tracks-table tbody tr').each(function(i){
                        var $r = $(this);
                        tracksMeta.push({
                            titulo:      $r.find('.t-titulo').val(),
                            autor:       $r.find('.t-autor').val(),
                            interpretes: $r.find('.t-interpretes').val(),
                            orden:       $r.find('.t-orden').val(),
                            descargable: $r.find('.t-dl').is(':checked') ? '1' : '0',
                            file_idx:    i,
                        });
                    });

                    $btn.prop('disabled', true).text(i18n.importing);
                    $box.find('.mp3p-upload-progress').show();


                    $.post(ajaxUrl, {
                        action:      'mp3p_create_disco',
                        nonce:       nonce,
                        disco:       JSON.stringify(disco),
                        tracks_meta: JSON.stringify(tracksMeta),
                    }, function(res) {
                        if (!res.success) {
                            alert(i18n.errorPrefix + ' ' + res.data);
                            $btn.prop('disabled', false).text(i18n.importThisAlbum);
                            return;
                        }
                        var cancionIds = res.data.cancion_ids;
                        uploadTracksSequential($box, tracks, cancionIds, 0, function(){
                            $btn.text(i18n.imported).css('background','#00a32a');
                            $box.find('.mp3p-import-status').html(
                                ' <a href="' + res.data.edit_url + '" target="_blank">' + i18n.viewAlbum + '</a>'
                            );
                        });
                    });
                });
            }

            function uploadTracksSequential($box, tracks, cancionIds, index, done) {
                if (index >= cancionIds.length) { done(); return; }
                var entry = cancionIds[index];
                var file  = tracks[entry.file_idx] ? tracks[entry.file_idx].file : null;
                var $bar  = $box.find('.mp3p-progress-bar--album');
                var $lbl  = $box.find('.mp3p-progress-label');
                var $row  = $box.find('.mp3p-tracks-table tbody tr').eq(entry.file_idx);
                var $rowBar    = $row.find('.mp3p-row-progress');
                var $rowStatus = $row.find('.mp3p-row-status');

                $bar.css('width', Math.round((index / cancionIds.length) * 100) + '%');
                $lbl.text('(' + (index+1) + '/' + cancionIds.length + ') ' + i18n.uploading);
                $rowStatus.text(i18n.uploadingStatus).css('color', '#2271b1');

                if (!file) {
                    $rowStatus.text(i18n.noFile).css('color', '#d63638');
                    index++; uploadTracksSequential($box, tracks, cancionIds, index, done);
                    return;
                }

                var fd = new FormData();
                fd.append('action',     'mp3p_upload_track');
                fd.append('nonce',      nonce);
                fd.append('cancion_id', entry.cancion_id);
                fd.append('mp3_file',   file);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxUrl);
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        var p = Math.round((e.loaded / e.total) * 100);
                        $rowBar.css('width', p + '%');
                        $bar.css('width', Math.round((index / cancionIds.length) * 100 + p / cancionIds.length) + '%');
                        $lbl.text('(' + (index+1) + '/' + cancionIds.length + ') ' + p + '%');
                    }
                };
                xhr.onload = function() {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            $rowBar.css({'width':'100%','background':'#00a32a'});
                            $rowStatus.text(i18n.uploaded).css('color', '#00a32a');
                        } else {
                            $rowBar.css('background', '#d63638');
                            $rowStatus.text(i18n.errorStatus).css('color', '#d63638');
                        }
                    } catch(e) {
                        $rowBar.css('background', '#d63638');
                        $rowStatus.text(i18n.errorStatus).css('color', '#d63638');
                    }
                    index++; uploadTracksSequential($box, tracks, cancionIds, index, done);
                };
                xhr.onerror = function() {
                    $rowBar.css('background', '#d63638');
                    $rowStatus.text(i18n.networkError).css('color', '#d63638');
                    index++; uploadTracksSequential($box, tracks, cancionIds, index, done);
                };
                xhr.send(fd);
            }

            // ══════════════════════════════════════════════════════
            // PESTAÑA 2 — Desde biblioteca de medios
            // ══════════════════════════════════════════════════════
            var mediaFrame    = null;
            var selectedMedia = [];

            $('#mp3p_open_media').on('click', function(){
                if (mediaFrame) { mediaFrame.open(); return; }
                mediaFrame = wp.media({
                    title:    i18n.selectMp3Title,
                    button:   { text: i18n.useTheseFiles },
                    multiple: true,
                    library:  { type: 'audio/mpeg' },
                });
                mediaFrame.on('select', function(){
                    var selection = mediaFrame.state().get('selection');
                    selectedMedia = [];
                    selection.each(function(attachment){
                        var a = attachment.toJSON();
                        selectedMedia.push({
                            id:       a.id,
                            url:      a.url,
                            filename: a.filename || a.title,
                            title:    a.title,
                            artist:   a.meta && a.meta.artist ? a.meta.artist : '',
                            album:    a.meta && a.meta.album  ? a.meta.album  : '',
                            year:     a.meta && a.meta.year   ? a.meta.year   : '',
                        });
                    });
                    if (!selectedMedia.length) return;
                    $('#mp3p_media_count').text(selectedMedia.length + ' ' + i18n.filesSelected);
                    renderMediaList(selectedMedia);
                    $('#mp3p_media_selected').show();
                });
                mediaFrame.open();
            });

            function renderMediaList(files) {
                var html = '';
                files.forEach(function(f) {
                    html += '<div style="display:flex;align-items:center;gap:10px;padding:6px 4px;border-bottom:1px solid #f0f0f0">';
                    html += '<span class="dashicons dashicons-media-audio" style="color:#111;flex-shrink:0"></span>';
                    html += '<span style="flex:1;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(f.filename) + '</span>';
                    html += '<span style="font-size:11px;color:#888;flex-shrink:0">' + (f.album ? esc(f.album) : i18n.noAlbum) + '</span>';
                    html += '</div>';
                });
                $('#mp3p_media_list').html(html);
            }

            // Lee las etiquetas ID3 de los adjuntos en el servidor (por lotes) y luego agrupa por álbum.
            $('#mp3p_media_process').on('click', function(){
                if (!selectedMedia.length) return;
                var $btn = $(this).prop('disabled', true);
                var ids  = selectedMedia.map(function(f){ return f.id; });
                var tags = {};
                var CHUNK = 10, pos = 0;

                $('#mp3p_media_read').show();

                function nextChunk() {
                    if (pos >= ids.length) {
                        $('#mp3p_media_read_bar').css('width', '100%');
                        $('#mp3p_media_read_label').text('✓ ' + i18n.readingComplete + ' (' + ids.length + '/' + ids.length + ')');
                        $btn.prop('disabled', false);
                        buildMediaGroups(tags);
                        return;
                    }
                    var slice = ids.slice(pos, pos + CHUNK);
                    $('#mp3p_media_read_bar').css('width', Math.round((pos / ids.length) * 100) + '%');
                    $('#mp3p_media_read_label').text('(' + Math.min(pos + CHUNK, ids.length) + '/' + ids.length + ')');
                    $.post(ajaxUrl, { action: 'mp3p_read_tags', nonce: nonce, ids: slice })
                        .done(function(res){
                            if (res && res.success && res.data) $.extend(tags, res.data);
                        })
                        .always(function(){ pos += CHUNK; nextChunk(); });
                }
                nextChunk();
            });

            function buildMediaGroups(tags) {
                var groups = {};
                selectedMedia.forEach(function(f, i) {
                    var t = tags[f.id] || {};
                    var item = {
                        id:       f.id,
                        url:      f.url,
                        title:    t.title    || f.title || f.filename,
                        album:    t.album    || f.album || i18n.noAlbum,
                        artist:   t.artist   || f.artist || '',
                        composer: t.composer || '',
                        year:     t.year     || f.year || '',
                        track:    parseInt(t.track, 10) || (i + 1),
                        lyrics:   !!t.lyrics,
                        hasCover: !!t.cover,
                    };
                    var key = String(item.album).trim();
                    if (!groups[key]) groups[key] = [];
                    groups[key].push(item);
                });
                Object.keys(groups).forEach(function(k) {
                    groups[k].sort(function(a, b){ return a.track - b.track; });
                });
                renderMediaPreview(groups);
            }

            function renderMediaPreview(groups) {
                var html = '<h3 style="margin-bottom:16px">' + i18n.reviewAndConfirm + '</h3>';
                var groupKeys = Object.keys(groups);
                groupKeys.forEach(function(albumName, groupIdx) {
                    var tracks   = groups[albumName];
                    var first    = tracks[0] || {};
                    var coverSrc = tracks.find(function(t){ return t.hasCover; });

                    html += '<div class="mp3p-album-preview" data-mgroup-idx="' + groupIdx + '" data-cover-att="' + (coverSrc ? coverSrc.id : '') + '">';
                    html += '<div class="mp3p-album-preview__header">';
                    html += '<div class="mp3p-album-preview__cover mp3p-album-preview__cover--empty"><span class="dashicons dashicons-album"></span></div>';
                    html += '<div class="mp3p-album-preview__meta">';
                    html += '<h3>' + esc(albumName) + ' <small style="font-weight:400;color:#888">(' + tracks.length + ' ' + i18n.tracksSuffix + ')</small></h3>';
                    html += '<table class="mp3p-edit-table">';
                    html += field(i18n.albumTitleLabel, 'mdisco_titulo',      albumName);
                    html += field(i18n.authorsLabel,    'mdisco_autores',     first.composer || first.artist || '');
                    html += field(i18n.performersLabel, 'mdisco_interpretes', first.artist   || '');
                    html += field(i18n.yearLabel,       'mdisco_anio',        first.year     || '');
                    html += '</table>';
                    html += '<button type="button" class="button button-primary mp3p-media-import-btn" style="margin-top:10px">' + i18n.createAlbum + '</button>';
                    html += '<span class="mp3p-import-status"></span>';
                    html += '</div></div>';

                    html += '<div class="mp3p-tracks-table"><table><thead><tr><th>#</th><th>' + i18n.thTitle + '</th><th>' + i18n.thAuthor + '</th><th>' + i18n.thPerformers + '</th><th>' + i18n.lyricsCol + '</th><th>↓</th></tr></thead><tbody>';
                    tracks.forEach(function(t) {
                        html += '<tr data-att-id="' + t.id + '" data-att-url="' + esc(t.url) + '">';
                        html += '<td><input type="number" class="t-orden" value="' + t.track + '" style="width:65px"></td>';
                        html += '<td><input type="text" class="t-titulo" value="' + esc(t.title) + '" style="width:180px"></td>';
                        html += '<td><input type="text" class="t-autor" value="' + esc(t.composer || t.artist || '') + '" style="width:130px"></td>';
                        html += '<td><input type="text" class="t-interpretes" value="' + esc(t.artist || '') + '" style="width:130px"></td>';
                        html += '<td class="t-lyr">' + lyricsBadge(t.lyrics) + '</td>';
                        html += '<td><input type="checkbox" class="t-dl" checked title="' + i18n.downloadable + '"></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                    html += '<div class="mp3p-upload-progress" style="display:none;margin-top:12px">';
                    html += '<div class="mp3p-progress-bar-wrap"><div class="mp3p-progress-bar mp3p-progress-bar--malbum"></div></div>';
                    html += '<p class="mp3p-progress-label" style="font-size:12px;color:#555;margin-top:4px"></p>';
                    html += '</div></div>';
                });

                $('#mp3p_media_result').html(html).show();
                $('#mp3p_media_selected').hide();
                $('html,body').animate({ scrollTop: $('#mp3p_media_result').offset().top - 40 }, 400);
                bindMediaImportButtons(groups);
                loadMediaCovers();
            }

            // Vista previa de la portada incrustada (una por disco).
            function loadMediaCovers() {
                $('#mp3p_media_result .mp3p-album-preview').each(function(){
                    var $box  = $(this);
                    var attId = $box.data('cover-att');
                    if (!attId) return;
                    $.post(ajaxUrl, { action: 'mp3p_get_cover', nonce: nonce, id: attId }, function(res){
                        if (!res || !res.success || !res.data) return;
                        $box.find('.mp3p-album-preview__cover--empty')
                            .replaceWith('<img src="' + res.data + '" class="mp3p-album-preview__cover" />');
                    });
                });
            }

            function bindMediaImportButtons(groups) {
                var groupKeys = Object.keys(groups);
                $('.mp3p-media-import-btn').on('click', function(){
                    var $btn   = $(this);
                    var $box   = $btn.closest('.mp3p-album-preview');

                    var disco = {
                        titulo:       $box.find('[name="mdisco_titulo"]').val(),
                        autores:      $box.find('[name="mdisco_autores"]').val(),
                        interpretes:  $box.find('[name="mdisco_interpretes"]').val(),
                        anio:         $box.find('[name="mdisco_anio"]').val(),
                        cover:        '',
                        cover_att_id: $box.data('cover-att') || 0,
                    };

                    var tracksMeta = [];
                    $box.find('.mp3p-tracks-table tbody tr').each(function(){
                        var $r = $(this);
                        tracksMeta.push({
                            titulo:        $r.find('.t-titulo').val(),
                            autor:         $r.find('.t-autor').val(),
                            interpretes:   $r.find('.t-interpretes').val(),
                            orden:         $r.find('.t-orden').val(),
                            descargable:   $r.find('.t-dl').is(':checked') ? '1' : '0',
                            attachment_id: $r.data('att-id'),
                            audio_url:     $r.data('att-url'),
                        });
                    });

                    $btn.prop('disabled', true).text(i18n.creating);
                    $box.find('.mp3p-upload-progress').show();

                    $.post(ajaxUrl, {
                        action:      'mp3p_import_from_media',
                        nonce:       nonce,
                        disco:       JSON.stringify(disco),
                        tracks_meta: JSON.stringify(tracksMeta),
                    }, function(res){
                        if (!res.success) {
                            alert(i18n.errorPrefix + ' ' + res.data);
                            $btn.prop('disabled', false).text(i18n.createAlbum);
                            return;
                        }
                        $btn.text(i18n.created).css('background','#00a32a');
                        $box.find('.mp3p-import-status').html(
                            ' <a href="' + res.data.edit_url + '" target="_blank">' + i18n.viewAlbum + '</a>'
                        );
                        $box.find('.mp3p-progress-bar--malbum').css({'width':'100%','background':'#00a32a'});
                        $box.find('.mp3p-progress-label').text(i18n.albumCreated);
                    });
                });
            }

            function esc(s) {
                return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

        })(jQuery);
        <?php
        $mp3p_importer_js = ob_get_clean();
        wp_add_inline_script( 'mp3p-jsmediatags', $mp3p_importer_js );
    }

    // ── AJAX: crear disco desde archivos subidos ──────────────────
    public static function ajax_create_disco() {
        check_ajax_referer( 'mp3p_importer', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( __( 'Sin permisos.', 'spindle-mp3-player' ) );

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- el JSON se decodifica y cada campo se sanitiza individualmente más abajo (sanitize_text_field/intval).
        $disco      = json_decode( wp_unslash( $_POST['disco']       ?? '{}' ), true );
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ídem, cada elemento del array se sanitiza al usarse.
        $tracksMeta = json_decode( wp_unslash( $_POST['tracks_meta'] ?? '[]' ), true );

        if ( empty($disco['titulo']) ) wp_send_json_error( __( 'El disco necesita un título.', 'spindle-mp3-player' ) );

        $disco_id = wp_insert_post([
            'post_type'   => 'mp3p_disco',
            'post_title'  => sanitize_text_field($disco['titulo']),
            'post_status' => 'publish',
        ]);
        if ( is_wp_error($disco_id) ) wp_send_json_error( __( 'Error al crear el disco.', 'spindle-mp3-player' ) );

        update_post_meta( $disco_id, '_mp3p_autores',     sanitize_text_field($disco['autores']     ?? '') );
        update_post_meta( $disco_id, '_mp3p_interpretes', sanitize_text_field($disco['interpretes'] ?? '') );
        update_post_meta( $disco_id, '_mp3p_anio',        sanitize_text_field($disco['anio']        ?? '') );

        if ( ! empty($disco['cover']) ) {
            $cover_id = self::save_cover( $disco['cover'], $disco['titulo'], $disco_id );
            if ( $cover_id ) set_post_thumbnail( $disco_id, $cover_id );
        }

        $cancion_ids = [];
        foreach ( $tracksMeta as $t ) {
            $cancion_id = wp_insert_post([
                'post_type'   => 'mp3p_cancion',
                'post_title'  => sanitize_text_field($t['titulo'] ?? __( 'Sin título', 'spindle-mp3-player' )),
                'post_status' => 'publish',
            ]);
            if ( is_wp_error($cancion_id) ) continue;

            update_post_meta( $cancion_id, '_mp3p_disco_id',      $disco_id );
            update_post_meta( $cancion_id, '_mp3p_orden',         intval($t['orden'] ?? 0) );
            update_post_meta( $cancion_id, '_mp3p_autor',         sanitize_text_field($t['autor']       ?? '') );
            update_post_meta( $cancion_id, '_mp3p_interpretes',   sanitize_text_field($t['interpretes'] ?? '') );
            update_post_meta( $cancion_id, '_mp3p_descargable',   ($t['descargable'] ?? '0') === '1' ? '1' : '0' );
            update_post_meta( $cancion_id, '_mp3p_attachment_id', 0 );

            $cancion_ids[] = [
                'cancion_id' => $cancion_id,
                'file_idx'   => intval($t['file_idx']),
            ];
        }

        wp_send_json_success([
            'disco_id'    => $disco_id,
            'cancion_ids' => $cancion_ids,
            'edit_url'    => get_edit_post_link($disco_id, 'raw'),
        ]);
    }

    // ── AJAX: crear disco desde biblioteca de medios ──────────────
    public static function ajax_import_from_media() {
        check_ajax_referer( 'mp3p_importer', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( __( 'Sin permisos.', 'spindle-mp3-player' ) );

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- el JSON se decodifica y cada campo se sanitiza individualmente más abajo (sanitize_text_field/intval).
        $disco      = json_decode( wp_unslash( $_POST['disco']       ?? '{}' ), true );
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ídem, cada elemento del array se sanitiza al usarse.
        $tracksMeta = json_decode( wp_unslash( $_POST['tracks_meta'] ?? '[]' ), true );

        if ( empty($disco['titulo']) ) wp_send_json_error( __( 'El disco necesita un título.', 'spindle-mp3-player' ) );

        $disco_id = wp_insert_post([
            'post_type'   => 'mp3p_disco',
            'post_title'  => sanitize_text_field($disco['titulo']),
            'post_status' => 'publish',
        ]);
        if ( is_wp_error($disco_id) ) wp_send_json_error( __( 'Error al crear el disco.', 'spindle-mp3-player' ) );

        update_post_meta( $disco_id, '_mp3p_autores',     sanitize_text_field($disco['autores']     ?? '') );
        update_post_meta( $disco_id, '_mp3p_interpretes', sanitize_text_field($disco['interpretes'] ?? '') );
        update_post_meta( $disco_id, '_mp3p_anio',        sanitize_text_field($disco['anio']        ?? '') );

        // Portada incrustada en el MP3 (si la hay)
        $cover_att = absint( $disco['cover_att_id'] ?? 0 );
        if ( $cover_att && get_post_type( $cover_att ) === 'attachment' ) {
            $tags = self::read_tags( get_attached_file( $cover_att ), true );
            if ( ! empty( $tags['picture'] ) ) {
                $cover_id = self::save_cover_bytes( $tags['picture']['data'], $tags['picture']['mime'], $disco['titulo'], $disco_id );
                if ( $cover_id ) set_post_thumbnail( $disco_id, $cover_id );
            }
        }

        foreach ( $tracksMeta as $t ) {
            $att_id = intval($t['attachment_id'] ?? 0);
            if ( ! $att_id || get_post_type($att_id) !== 'attachment' ) continue;

            $cancion_id = wp_insert_post([
                'post_type'   => 'mp3p_cancion',
                'post_title'  => sanitize_text_field($t['titulo'] ?? __( 'Sin título', 'spindle-mp3-player' )),
                'post_status' => 'publish',
            ]);
            if ( is_wp_error($cancion_id) ) continue;


            update_post_meta( $cancion_id, '_mp3p_disco_id',      $disco_id );
            update_post_meta( $cancion_id, '_mp3p_orden',         intval($t['orden'] ?? 0) );
            update_post_meta( $cancion_id, '_mp3p_autor',         sanitize_text_field($t['autor']       ?? '') );
            update_post_meta( $cancion_id, '_mp3p_interpretes',   sanitize_text_field($t['interpretes'] ?? '') );
            update_post_meta( $cancion_id, '_mp3p_descargable',   ($t['descargable'] ?? '0') === '1' ? '1' : '0' );
            update_post_meta( $cancion_id, '_mp3p_attachment_id', $att_id );

            self::apply_lyrics_from_file( $cancion_id, $att_id );
        }

        wp_send_json_success([
            'disco_id' => $disco_id,
            'edit_url' => get_edit_post_link($disco_id, 'raw'),
        ]);
    }

    // ── AJAX: subir un MP3 y asignarlo a una canción ──────────────
    public static function ajax_upload_track() {
        check_ajax_referer( 'mp3p_importer', 'nonce' );
        if ( ! current_user_can('manage_options') ) wp_send_json_error( __( 'Sin permisos.', 'spindle-mp3-player' ) );

        $cancion_id = isset( $_POST['cancion_id'] ) ? absint( $_POST['cancion_id'] ) : 0;
        if ( ! $cancion_id || get_post_type($cancion_id) !== 'mp3p_cancion' ) {
            wp_send_json_error( __( 'Canción no válida.', 'spindle-mp3-player' ) );
        }

        if ( ! isset( $_FILES['mp3_file']['error'] ) || $_FILES['mp3_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( __( 'Error en la subida del archivo.', 'spindle-mp3-player' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- media_handle_upload() valida el archivo (tipo, extensión) internamente.
        $_FILES['mp3_upload'] = $_FILES['mp3_file'];
        // Solo en las subidas del importador: que WordPress no extraiga la portada incrustada como adjunto aparte
        add_filter( 'wp_read_audio_metadata', [ 'MP3P_Cascade_Delete', 'suppress_mp3_image' ], 10, 1 );
        $att_id = media_handle_upload( 'mp3_upload', $cancion_id );
        remove_filter( 'wp_read_audio_metadata', [ 'MP3P_Cascade_Delete', 'suppress_mp3_image' ], 10 );
        if ( ! is_wp_error( $att_id ) ) MP3P_Cascade_Delete::mark_created( $att_id );

        if ( is_wp_error($att_id) ) {
            wp_send_json_error( __( 'Error al subir:', 'spindle-mp3-player' ) . ' ' . $att_id->get_error_message() );
        }

        update_post_meta( $cancion_id, '_mp3p_attachment_id', $att_id );

        $has_lyrics = self::apply_lyrics_from_file( $cancion_id, $att_id );

        wp_send_json_success([ 'attachment_id' => $att_id, 'lyrics' => $has_lyrics ]);
    }

    // ── AJAX: leer etiquetas ID3 de adjuntos de la biblioteca ─────
    public static function ajax_read_tags() {
        check_ajax_referer( 'mp3p_importer', 'nonce' );
        // También desde la ficha de canción: basta con poder editar y subir archivos
        if ( ! current_user_can('edit_posts') || ! current_user_can('upload_files') ) wp_send_json_error( __( 'Sin permisos.', 'spindle-mp3-player' ) );

        $ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : [];
        $full = ! empty( $_POST['full'] ); // incluir el texto de la letra (ficha de canción)
        $out  = [];
        foreach ( array_slice( array_filter( $ids ), 0, 50 ) as $att_id ) {
            if ( get_post_type( $att_id ) !== 'attachment' ) continue;
            $tags = self::read_tags( get_attached_file( $att_id ) );
            $out[ $att_id ] = [
                'title'    => $tags['title'],
                'artist'   => $tags['artist'],
                'composer' => $tags['composer'],
                'album'    => $tags['album'],
                'year'     => $tags['year'],
                'track'    => $tags['track'],
                'lyrics'   => $tags['lyrics'] !== '',
                'cover'    => $tags['has_cover'],
            ];
            if ( $full ) $out[ $att_id ]['lyrics_text'] = $tags['lyrics'];
        }
        wp_send_json_success( $out );
    }

    // ── AJAX: portada incrustada de un adjunto (vista previa) ─────
    public static function ajax_get_cover() {
        check_ajax_referer( 'mp3p_importer', 'nonce' );
        // También desde la ficha de canción: basta con poder editar y subir archivos
        if ( ! current_user_can('edit_posts') || ! current_user_can('upload_files') ) wp_send_json_error( __( 'Sin permisos.', 'spindle-mp3-player' ) );

        $att_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $att_id || get_post_type( $att_id ) !== 'attachment' ) wp_send_json_error();

        $tags = self::read_tags( get_attached_file( $att_id ), true );
        if ( empty( $tags['picture'] ) ) wp_send_json_error();

        wp_send_json_success( 'data:' . $tags['picture']['mime'] . ';base64,' . base64_encode( $tags['picture']['data'] ) );
    }

    /**
     * Si el archivo del adjunto trae letra, la guarda en la canción
     * (el archivo manda: sustituye la que hubiera). Si no trae, no toca nada.
     *
     * @return bool true si se guardó una letra.
     */
    private static function apply_lyrics_from_file( $cancion_id, $att_id ) {
        $lyrics = self::extract_lyrics( get_attached_file( $att_id ) );
        if ( $lyrics === '' ) return false;
        update_post_meta( $cancion_id, '_mp3p_letra', $lyrics );
        return true;
    }

    /**
     * Lee la letra de un MP3. Cadena vacía si no tiene.
     */
    public static function extract_lyrics( $file_path ) {
        $tags = self::read_tags( $file_path );
        return $tags['lyrics'];
    }

    /**
     * Lee las etiquetas de un MP3 con la librería getID3 incluida en WordPress.
     *
     * @param string $file_path    Ruta del archivo.
     * @param bool   $with_picture Incluir los bytes de la portada incrustada.
     * @return array title, artist, composer, album, year, track, lyrics, has_cover y, si se pide, picture [data, mime].
     */
    private static function read_tags( $file_path, $with_picture = false ) {
        $out = [
            'title' => '', 'artist' => '', 'composer' => '', 'album' => '',
            'year'  => '', 'track'  => 0,  'lyrics'   => '', 'has_cover' => false,
        ];
        if ( ! $file_path || ! is_readable( $file_path ) ) return $out;

        if ( ! class_exists( 'getID3', false ) ) {
            require_once ABSPATH . WPINC . '/ID3/getid3.php';
        }

        try {
            $getid3 = new getID3();
            $info   = $getid3->analyze( $file_path );
            getid3_lib::CopyTagsToComments( $info );
        } catch ( Exception $e ) {
            return $out;
        }

        $c     = $info['comments'] ?? [];
        $first = function ( $keys ) use ( $c ) {
            foreach ( (array) $keys as $k ) {
                if ( empty( $c[ $k ] ) ) continue;
                foreach ( (array) $c[ $k ] as $v ) {
                    if ( is_string( $v ) || is_numeric( $v ) ) {
                        $v = trim( (string) $v );
                        if ( $v !== '' ) return $v;
                    }
                }
            }
            return '';
        };

        $out['title']    = sanitize_text_field( $first( 'title' ) );
        $out['artist']   = sanitize_text_field( $first( 'artist' ) );
        $out['composer'] = sanitize_text_field( $first( 'composer' ) );
        $out['album']    = sanitize_text_field( $first( 'album' ) );

        // Año: TYER (v2.3), TDRC (v2.4) o ID3v1; nos quedamos con los 4 dígitos.
        $year = $first( [ 'year', 'recording_time', 'date', 'original_release_time' ] );
        $out['year'] = preg_match( '/\d{4}/', $year, $m ) ? $m[0] : '';

        // Pista: "3" o "3/12"
        $out['track'] = intval( $first( [ 'track_number', 'track', 'tracknumber' ] ) );

        // Letra: USLT (getID3 ya la pasa a UTF-8) o Lyrics3
        $lyrics = [];
        if ( ! empty( $c['unsynchronised_lyric'] ) )                 $lyrics = array_merge( $lyrics, (array) $c['unsynchronised_lyric'] );
        if ( ! empty( $info['id3v2']['comments']['unsynchronised_lyric'] ) ) $lyrics = array_merge( $lyrics, (array) $info['id3v2']['comments']['unsynchronised_lyric'] );
        if ( ! empty( $info['lyrics3']['raw']['LYR'] ) )             $lyrics[] = $info['lyrics3']['raw']['LYR'];
        foreach ( $lyrics as $text ) {
            if ( ! is_string( $text ) ) continue;
            $text = str_replace( [ "\xEF\xBB\xBF", "\0" ], '', $text );   // BOM y nulos sueltos
            $text = str_replace( [ "\r\n", "\r" ], "\n", $text );
            $text = trim( sanitize_textarea_field( $text ) );
            if ( $text !== '' ) { $out['lyrics'] = $text; break; }
        }

        // Portada incrustada (APIC)
        if ( ! empty( $c['picture'] ) ) {
            foreach ( (array) $c['picture'] as $pic ) {
                if ( empty( $pic['data'] ) ) continue;
                $mime = $pic['image_mime'] ?? '';
                if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/gif' ], true ) ) continue;
                $out['has_cover'] = true;
                if ( $with_picture ) $out['picture'] = [ 'data' => $pic['data'], 'mime' => $mime ];
                break;
            }
        }

        return $out;
    }

    // ── Guardar portada base64 ────────────────────────────────────
    private static function save_cover($data_uri, $title, $post_id) {
        if ( ! preg_match('/^data:(image\/\w+);base64,(.+)$/', $data_uri, $m) ) return false;
        $data = base64_decode($m[2]);
        if (!$data) return false;
        return self::save_cover_bytes( $data, $m[1], $title, $post_id );
    }

    // ── Guardar portada a partir de sus bytes ─────────────────────
    private static function save_cover_bytes($data, $mime, $title, $post_id) {
        $ext_map = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
        if ( ! $data || ! isset( $ext_map[$mime] ) ) return false;
        $ext     = $ext_map[$mime];

        $upload = wp_upload_dir();
        $fname  = sanitize_title($title) . '-cover-' . time() . '.' . $ext;
        $fpath  = $upload['path'] . '/' . $fname;
        file_put_contents($fpath, $data);

        $att_id = wp_insert_attachment([
            'guid'           => $upload['url'] . '/' . $fname,
            'post_mime_type' => $mime,
            /* translators: %s: album title */
            'post_title'     => sprintf( __( '%s — portada', 'spindle-mp3-player' ), sanitize_text_field($title) ),
            'post_status'    => 'inherit',
        ], $fpath, $post_id);

        if ( is_wp_error($att_id) ) return false;
        MP3P_Cascade_Delete::mark_created( $att_id );
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($att_id, wp_generate_attachment_metadata($att_id, $fpath));
        return $att_id;
    }
}
