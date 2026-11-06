<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Playlist_Meta {

    public static function register() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_boxes' ] );
        add_action( 'save_post',      [ __CLASS__, 'save' ] );
        add_action( 'admin_footer',   [ __CLASS__, 'scripts' ] );
    }

    public static function add_boxes() {
        add_meta_box( 'mp3p_playlist_tracks',  __( 'Canciones de la playlist', 'spindle-mp3-player' ),
            [ __CLASS__, 'render_tracks' ], 'mp3p_playlist', 'normal', 'high' );
        add_meta_box( 'mp3p_playlist_info',    __( 'Información', 'spindle-mp3-player' ),
            [ __CLASS__, 'render_info' ], 'mp3p_playlist', 'normal', 'default' );
        add_meta_box( 'mp3p_playlist_diseno',  __( 'Diseño del player', 'spindle-mp3-player' ),
            [ __CLASS__, 'render_diseno' ], 'mp3p_playlist', 'side', 'high' );
    }

    // ── Info básica ───────────────────────────────────────────────
    public static function render_info( $post ) {
        $desc = get_post_meta($post->ID, '_mp3p_playlist_descripcion', true);
        ?>
        <table class="mp3p-form-table">
            <tr>
                <th><label><?php esc_html_e( 'Descripción', 'spindle-mp3-player' ); ?></label></th>
                <td><textarea name="mp3p_playlist_descripcion" rows="3" class="widefat"><?php echo esc_textarea($desc); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    // ── Shortcode ─────────────────────────────────────────────────
    public static function render_shortcode( $post ) {
        $api = rest_url('mp3p/v1');
        ?>
        <p style="margin:0 0 6px;font-size:12px"><?php esc_html_e( 'Shortcode para Cornerstone:', 'spindle-mp3-player' ); ?></p>
        <code class="mp3p-shortcode">[mp3p_playlist id="<?php echo intval( $post->ID ); ?>"]</code>
        <button class="button button-small mp3p-copy" data-clip='[mp3p_playlist id="<?php echo intval( $post->ID ); ?>"]' style="margin-top:8px;width:100%"><?php esc_html_e( 'Copiar shortcode', 'spindle-mp3-player' ); ?></button>
        <hr style="margin:14px 0">
        <p style="margin:0 0 6px;font-size:12px"><?php esc_html_e( 'Web Component:', 'spindle-mp3-player' ); ?></p>
        <code class="mp3p-shortcode" style="font-size:10px">&lt;mp3p-playlist id="<?php echo intval( $post->ID ); ?>" api="<?php echo esc_url($api); ?>"&gt;&lt;/mp3p-playlist&gt;</code>
        <button class="button button-small mp3p-copy" data-clip='<mp3p-playlist id="<?php echo intval( $post->ID ); ?>" api="<?php echo esc_url($api); ?>"></mp3p-playlist>' style="margin-top:8px;width:100%"><?php esc_html_e( 'Copiar Web Component', 'spindle-mp3-player' ); ?></button>
        <?php
    }

    // ── Editor de tracks con drag & drop ──────────────────────────
    public static function render_tracks( $post ) {
        wp_nonce_field('mp3p_playlist_save', 'mp3p_playlist_nonce');
        $track_ids = get_post_meta($post->ID, '_mp3p_playlist_tracks', true) ?: [];

        // Cargar datos de cada canción guardada
        $tracks = [];
        foreach ( $track_ids as $cid ) {
            $c = get_post($cid);
            if (!$c || $c->post_type !== 'mp3p_cancion') continue;
            $disco_id = intval(get_post_meta($cid, '_mp3p_disco_id', true));
            $tracks[] = [
                'id'      => $cid,
                'titulo'  => $c->post_title,
                'autor'   => get_post_meta($cid, '_mp3p_autor', true) ?: '',
                'disco'   => $disco_id ? get_the_title($disco_id) : '',
                'portada' => $disco_id ? (get_the_post_thumbnail_url($disco_id, 'thumbnail') ?: '') : '',
            ];
        }

        // Todos los discos para el selector
        $discos = get_posts(['post_type'=>'mp3p_disco','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC']);
        ?>
        <div class="mp3p-playlist-editor">

            <!-- Buscador de canciones -->
            <div class="mp3p-playlist-search">
                <h4 style="margin:0 0 10px"><?php esc_html_e( 'Añadir canciones', 'spindle-mp3-player' ); ?></h4>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input type="text" id="mp3p_search_cancion" placeholder="<?php esc_attr_e( 'Buscar por título de canción...', 'spindle-mp3-player' ); ?>" style="flex:1;min-width:200px" class="regular-text" />
                    <button type="button" class="button" id="mp3p_btn_search"><?php esc_html_e( 'Buscar', 'spindle-mp3-player' ); ?></button>
                </div>
                <div id="mp3p_search_results" style="display:none;margin-top:10px;border:1px solid #ddd;border-radius:4px;max-height:240px;overflow-y:auto;background:#fff"></div>
            </div>

            <!-- Selector de disco completo -->
            <div class="mp3p-playlist-disco-add" style="margin-top:14px;padding-top:14px;border-top:1px solid #f0f0f0">
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <select id="mp3p_add_disco" style="flex:1;min-width:200px">
                        <option value=""><?php esc_html_e( '— Añadir disco completo —', 'spindle-mp3-player' ); ?></option>
                        <?php foreach ($discos as $d) : ?>
                        <option value="<?php echo intval( $d->ID ); ?>"><?php echo esc_html($d->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" id="mp3p_btn_add_disco"><?php esc_html_e( 'Añadir disco', 'spindle-mp3-player' ); ?></button>
                </div>
            </div>

            <!-- Lista de tracks (drag & drop) -->
            <div style="margin-top:18px">
                <h4 style="margin:0 0 10px">
                    <?php esc_html_e( 'Orden de reproducción', 'spindle-mp3-player' ); ?>
                    <span style="font-weight:400;color:#888;font-size:12px">(<?php esc_html_e( 'arrastra para reordenar', 'spindle-mp3-player' ); ?>)</span>
                </h4>
                <input type="hidden" name="mp3p_playlist_track_ids" id="mp3p_playlist_track_ids"
                       value="<?php echo esc_attr(implode(',', array_column($tracks, 'id'))); ?>" />

                <ul id="mp3p_tracks_list" class="mp3p-sortable-list">
                    <?php if (empty($tracks)) : ?>
                    <li class="mp3p-sortable-empty" id="mp3p_empty_msg">
                        <?php esc_html_e( 'Aún no hay canciones. Búscalas arriba o añade un disco completo.', 'spindle-mp3-player' ); ?>
                    </li>
                    <?php else : ?>
                    <?php foreach ($tracks as $t) : ?>
                    <li class="mp3p-sortable-item" data-id="<?php echo intval( $t['id'] ); ?>">
                        <?php echo self::track_row_html($t); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- track_row_html() escapa internamente cada dato dinámico (esc_url/esc_html). ?>
                    </li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <?php
    }

    // ── HTML de una fila de track ─────────────────────────────────
    public static function track_row_html($t) {
        ob_start();
        ?>
        <span class="mp3p-sortable-handle" title="<?php esc_attr_e( 'Arrastrar para reordenar', 'spindle-mp3-player' ); ?>">⠿</span>
        <?php if ($t['portada']) : ?>
        <img src="<?php echo esc_url($t['portada']); ?>" class="mp3p-sortable-cover" />
        <?php else : ?>
        <span class="mp3p-sortable-cover mp3p-sortable-cover--empty dashicons dashicons-format-audio"></span>
        <?php endif; ?>
        <span class="mp3p-sortable-info">
            <strong><?php echo esc_html($t['titulo']); ?></strong>
            <?php if ($t['autor'] || $t['disco']) : ?>
            <span class="mp3p-sortable-meta">
                <?php echo esc_html(implode(' · ', array_filter([$t['autor'], $t['disco']]))); ?>
            </span>
            <?php endif; ?>
        </span>
        <button type="button" class="mp3p-sortable-remove button-link" title="<?php esc_attr_e( 'Quitar de la playlist', 'spindle-mp3-player' ); ?>">✕</button>
        <?php
        return ob_get_clean();
    }

    // ── Diseño del player ────────────────────────────────────────
    public static function render_diseno( $post ) {
        wp_nonce_field( 'mp3p_playlist_diseno_save', 'mp3p_playlist_diseno_nonce' );
        MP3P_Meta_Boxes::render_diseno_options( $post, 'playlist' );
    }

        // ── Guardar ───────────────────────────────────────────────────
    public static function save($post_id) {
        if (get_post_type($post_id) !== 'mp3p_playlist') return;
        if (!isset($_POST['mp3p_playlist_nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mp3p_playlist_nonce'] ) ), 'mp3p_playlist_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Guardar descripción
        update_post_meta($post_id, '_mp3p_playlist_descripcion',
            sanitize_textarea_field( wp_unslash( $_POST['mp3p_playlist_descripcion'] ?? '' ) ));

        // Guardar orden de tracks
        $raw = sanitize_text_field( wp_unslash( $_POST['mp3p_playlist_track_ids'] ?? '' ) );
        $ids = array_values(array_filter(array_map('intval', explode(',', $raw))));
        update_post_meta($post_id, '_mp3p_playlist_tracks', $ids);

        // Guardar opciones de diseño
        if ( isset($_POST['mp3p_playlist_diseno_nonce']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mp3p_playlist_diseno_nonce'] ) ), 'mp3p_playlist_diseno_save') ) {
            MP3P_Meta_Boxes::save_diseno_fields( $post_id );
        }
    }

    // ── Scripts del editor ────────────────────────────────────────
    public static function scripts() {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'mp3p_playlist') return;
        ?>
        <?php ob_start(); ?>
        jQuery(function($) {
            var i18n = mp3pPlaylist.i18n;

            // ── Drag & Drop con jQuery UI Sortable ───────────────
            $('#mp3p_tracks_list').sortable({
                handle:      '.mp3p-sortable-handle',
                placeholder: 'mp3p-sortable-placeholder',
                axis:        'y',
                update: function() { updateTrackIds(); }
            });

            // ── Actualizar input hidden con IDs en orden ─────────
            function updateTrackIds() {
                var ids = [];
                $('#mp3p_tracks_list .mp3p-sortable-item').each(function() {
                    ids.push($(this).data('id'));
                });
                $('#mp3p_playlist_track_ids').val(ids.join(','));
                toggleEmpty(ids.length === 0);
            }

            function toggleEmpty(show) {
                if (show) {
                    if (!$('#mp3p_empty_msg').length) {
                        $('#mp3p_tracks_list').append('<li class="mp3p-sortable-empty" id="mp3p_empty_msg">' + i18n.noTracksYet + '</li>');
                    }
                } else {
                    $('#mp3p_empty_msg').remove();
                }
            }

            // ── Quitar canción de la lista ───────────────────────
            $(document).on('click', '.mp3p-sortable-remove', function() {
                $(this).closest('.mp3p-sortable-item').remove();
                updateTrackIds();
            });

            // ── Buscar canciones ─────────────────────────────────
            function searchCanciones(q) {
                $.ajax({
                    url: mp3pPlaylist.apiUrl + '/search/canciones',
                    data: { q: q },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', mp3pPlaylist.nonce);
                    },
                    success: function(results) {
                        renderSearchResults(results);
                    },
                    error: function() {
                        $('#mp3p_search_results').html('<p style="padding:10px;color:#d63638">' + i18n.searchError + '</p>').show();
                    }
                });
            }

            function renderSearchResults(results) {
                var $box = $('#mp3p_search_results');
                if (!results.length) {
                    $box.html('<p style="padding:10px;color:#888">' + i18n.noResultsFound + '</p>').show();
                    return;
                }
                var html = '<ul style="margin:0;padding:0;list-style:none">';
                results.forEach(function(c) {
                    var alreadyAdded = $('#mp3p_tracks_list [data-id="' + c.id + '"]').length > 0;
                    html += '<li style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid #f0f0f0;cursor:pointer" class="mp3p-result-item' + (alreadyAdded ? ' mp3p-result-added' : '') + '" data-id="' + c.id + '" data-titulo="' + esc(c.titulo) + '" data-autor="' + esc(c.autor) + '" data-disco="' + esc(c.disco) + '" data-portada="' + esc(c.portada) + '">';
                    if (c.portada) {
                        html += '<img src="' + c.portada + '" style="width:32px;height:32px;object-fit:cover;border-radius:3px;flex-shrink:0" />';
                    } else {
                        html += '<span class="dashicons dashicons-format-audio" style="font-size:28px;width:32px;height:32px;color:#ccc;flex-shrink:0"></span>';
                    }
                    html += '<span style="flex:1;min-width:0"><strong style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + esc(c.titulo) + '</strong>';
                    if (c.autor || c.disco) html += '<span style="font-size:12px;color:#888">' + esc([c.autor, c.disco].filter(Boolean).join(' · ')) + '</span>';
                    html += '</span>';
                    html += alreadyAdded ? '<span style="color:#888;font-size:12px">' + i18n.alreadyAdded + '</span>' : '<span style="color:#2271b1;font-size:12px">+ ' + i18n.add + '</span>';
                    html += '</li>';
                });
                html += '</ul>';
                $box.html(html).show();
            }

            // Búsqueda en tiempo real al escribir
            var searchTimer = null;
            $('#mp3p_search_cancion').on('input', function() {
                var q = $(this).val().trim();
                clearTimeout(searchTimer);
                if (q.length === 0) { $('#mp3p_search_results').hide(); return; }
                if (q.length < 2) return; // Esperar al menos 2 caracteres
                searchTimer = setTimeout(function() { searchCanciones(q); }, 300);
            });

            $('#mp3p_btn_search').on('click', function() {
                var q = $('#mp3p_search_cancion').val().trim();
                if (q) searchCanciones(q);
            });

            $('#mp3p_search_cancion').on('keypress', function(e) {
                if (e.which === 13) { e.preventDefault(); clearTimeout(searchTimer); searchCanciones($(this).val().trim()); }
            });

            // Cerrar resultados al hacer clic fuera
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#mp3p_search_results, #mp3p_search_cancion, #mp3p_btn_search').length) {
                    $('#mp3p_search_results').hide();
                }
            });

            // ── Añadir canción desde resultados ──────────────────
            $(document).on('click', '.mp3p-result-item:not(.mp3p-result-added)', function() {
                var $item = $(this);
                addTrack({
                    id:      $item.data('id'),
                    titulo:  $item.data('titulo'),
                    autor:   $item.data('autor'),
                    disco:   $item.data('disco'),
                    portada: $item.data('portada'),
                });
                $item.addClass('mp3p-result-added').find('span:last').text(i18n.alreadyAdded).css('color','#888');
            });

            // ── Añadir disco completo ────────────────────────────
            $('#mp3p_btn_add_disco').on('click', function() {
                var discoId = $('#mp3p_add_disco').val();
                if (!discoId) return;
                var $btn = $(this);
                $btn.prop('disabled', true).text(i18n.loading);

                $.ajax({
                    url: mp3pPlaylist.apiUrl + '/disco/' + discoId,
                    beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', mp3pPlaylist.nonce); },
                    success: function(disco) {
                        (disco.tracks || []).forEach(function(t) {
                            if (!$('#mp3p_tracks_list [data-id="' + t.id + '"]').length) {
                                addTrack({
                                    id:      t.id,
                                    titulo:  t.titulo,
                                    autor:   t.autor,
                                    disco:   disco.titulo,
                                    portada: t.portada,
                                });
                            }
                        });
                        $btn.prop('disabled', false).text(i18n.addAlbum);
                        $('#mp3p_add_disco').val('');
                    },
                    error: function() {
                        $btn.prop('disabled', false).text(i18n.addAlbum);
                        alert(i18n.errorLoadingAlbum);
                    }
                });
            });

            // ── Añadir track a la lista ──────────────────────────
            function addTrack(t) {
                $('#mp3p_empty_msg').remove();
                var coverHtml = t.portada
                    ? '<img src="' + t.portada + '" class="mp3p-sortable-cover" />'
                    : '<span class="mp3p-sortable-cover mp3p-sortable-cover--empty dashicons dashicons-format-audio"></span>';
                var meta = [t.autor, t.disco].filter(Boolean).join(' · ');
                var $li = $('<li class="mp3p-sortable-item" data-id="' + t.id + '">' +
                    '<span class="mp3p-sortable-handle" title="' + i18n.dragToReorder + '">⠿</span>' +
                    coverHtml +
                    '<span class="mp3p-sortable-info">' +
                    '<strong>' + esc(t.titulo) + '</strong>' +
                    (meta ? '<span class="mp3p-sortable-meta">' + esc(meta) + '</span>' : '') +
                    '</span>' +
                    '<button type="button" class="mp3p-sortable-remove button-link" title="' + i18n.remove + '">✕</button>' +
                    '</li>');
                $('#mp3p_tracks_list').append($li);
                updateTrackIds();
            }

            function esc(s) {
                return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            // ── Copiar shortcodes ────────────────────────────────
            $(document).on('click', '.mp3p-copy', function() {
                var text = $(this).data('clip');
                navigator.clipboard.writeText(text).then(function() {
                    var $b = $(this); $b.text(i18n.copied);
                    setTimeout(function(){ $b.text(i18n.copyShortcode); }, 2000);
                }.bind(this));
            });
        });
        <?php
        $mp3p_playlist_js = ob_get_clean();
        wp_register_script( 'mp3p-playlist-editor', false, [ 'jquery', 'jquery-ui-sortable' ], MP3P_VERSION, true );
        wp_enqueue_script( 'mp3p-playlist-editor' );
        wp_localize_script( 'mp3p-playlist-editor', 'mp3pPlaylist', [
            'apiUrl' => rest_url( 'mp3p/v1' ),
            'nonce'  => wp_create_nonce( 'wp_rest' ),
            'i18n'   => [
                'noTracksYet'      => __( 'Aún no hay canciones. Búscalas arriba o añade un disco completo.', 'spindle-mp3-player' ),
                'searchError'      => __( 'Error al buscar.', 'spindle-mp3-player' ),
                'noResultsFound'   => __( 'No se encontraron canciones.', 'spindle-mp3-player' ),
                'alreadyAdded'     => __( 'Ya añadida', 'spindle-mp3-player' ),
                'add'              => __( 'Añadir', 'spindle-mp3-player' ),
                'loading'          => __( 'Cargando...', 'spindle-mp3-player' ),
                'addAlbum'         => __( 'Añadir disco', 'spindle-mp3-player' ),
                'errorLoadingAlbum'=> __( 'Error al cargar el disco.', 'spindle-mp3-player' ),
                'dragToReorder'    => __( 'Arrastrar', 'spindle-mp3-player' ),
                'remove'           => __( 'Quitar', 'spindle-mp3-player' ),
                'copied'           => __( '✓ Copiado', 'spindle-mp3-player' ),
                'copyShortcode'    => __( 'Copiar shortcode', 'spindle-mp3-player' ),
            ],
        ] );
        wp_add_inline_script( 'mp3p-playlist-editor', $mp3p_playlist_js );
        ?>
        <?php ob_start(); ?>
        .mp3p-playlist-editor { }
        .mp3p-playlist-search { background:#f9f9f9; border:1px solid #e0e0e0; border-radius:6px; padding:14px 16px; }
        .mp3p-sortable-list { margin:0; padding:0; list-style:none; min-height:40px; }
        .mp3p-sortable-item { display:flex; align-items:center; gap:10px; background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:8px 12px; margin-bottom:6px; transition:box-shadow .15s; }
        .mp3p-sortable-item:hover { box-shadow:0 2px 8px rgba(0,0,0,.08); }
        .mp3p-sortable-handle { cursor:grab; color:#aaa; font-size:18px; flex-shrink:0; user-select:none; }
        .mp3p-sortable-handle:active { cursor:grabbing; }
        .mp3p-sortable-placeholder { height:46px; background:#f0f6fc; border:2px dashed #2271b1; border-radius:6px; margin-bottom:6px; }
        .mp3p-sortable-cover { width:36px; height:36px; object-fit:cover; border-radius:4px; flex-shrink:0; }
        .mp3p-sortable-cover--empty { display:flex; align-items:center; justify-content:center; background:#f0f0f0; color:#aaa; }
        .mp3p-sortable-info { flex:1; min-width:0; }
        .mp3p-sortable-info strong { display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:13px; }
        .mp3p-sortable-meta { display:block; font-size:11px; color:#888; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:1px; }
        .mp3p-sortable-remove { color:#d63638 !important; flex-shrink:0; font-size:16px; line-height:1; padding:0 4px !important; }
        .mp3p-sortable-empty { color:#888; font-style:italic; padding:16px; text-align:center; border:2px dashed #e0e0e0; border-radius:6px; }
        .mp3p-result-item:hover { background:#f0f6fc !important; }
        .mp3p-result-added { opacity:.6; cursor:default !important; }
        <?php
        $mp3p_playlist_css = ob_get_clean();
        wp_register_style( 'mp3p-playlist-editor', false, [], MP3P_VERSION );
        wp_enqueue_style( 'mp3p-playlist-editor' );
        wp_add_inline_style( 'mp3p-playlist-editor', $mp3p_playlist_css );
        ?>
        <?php
    }
}
