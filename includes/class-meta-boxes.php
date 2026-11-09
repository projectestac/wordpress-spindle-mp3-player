<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Meta_Boxes {

    public static function register() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_boxes' ] );
        add_action( 'save_post',      [ __CLASS__, 'save_disco' ] );
        add_action( 'save_post',      [ __CLASS__, 'save_cancion' ] );
        add_action( 'admin_head',     [ __CLASS__, 'admin_styles' ] );
        add_action( 'admin_footer',   [ __CLASS__, 'admin_scripts' ] );
    }

    public static function add_boxes() {
        add_meta_box( 'mp3p_disco_info',      __( 'Información del disco', 'spindle-mp3-player' ),   [ __CLASS__, 'render_disco_info' ],      'mp3p_disco',   'normal', 'high' );
        add_meta_box( 'mp3p_disco_canciones', __( 'Canciones del disco', 'spindle-mp3-player' ),     [ __CLASS__, 'render_disco_canciones' ], 'mp3p_disco',   'normal', 'default' );
        add_meta_box( 'mp3p_disco_diseno',    __( 'Diseño del player', 'spindle-mp3-player' ),   [ __CLASS__, 'render_disco_diseno' ],    'mp3p_disco',   'side',   'high' );
        add_meta_box( 'mp3p_cancion_info',    __( 'Información de la canción', 'spindle-mp3-player' ), [ __CLASS__, 'render_cancion_info' ],    'mp3p_cancion', 'normal', 'high' );
        add_meta_box( 'mp3p_cancion_diseno',  __( 'Diseño del player', 'spindle-mp3-player' ),    [ __CLASS__, 'render_cancion_diseno' ],  'mp3p_cancion', 'side',   'high' );
    }

    // ── DISCO: info ───────────────────────────────────────────────
    public static function render_disco_info( $post ) {
        wp_nonce_field( 'mp3p_disco_save', 'mp3p_disco_nonce' );
        $autores     = get_post_meta( $post->ID, '_mp3p_autores',     true );
        $interpretes = get_post_meta( $post->ID, '_mp3p_interpretes', true );
        $anio        = get_post_meta( $post->ID, '_mp3p_anio',        true );
        $licencia    = get_post_meta( $post->ID, '_mp3p_licencia',    true );
        $descripcion = get_post_meta( $post->ID, '_mp3p_descripcion', true );
        $licencias = [
            'CC BY 4.0', 'CC BY-SA 4.0', 'CC BY-NC 4.0',
            'CC BY-NC-SA 4.0',
            'CC0 (' . __( 'Dominio público', 'spindle-mp3-player' ) . ')',
            __( 'Todos los derechos reservados', 'spindle-mp3-player' ),
        ];
        ?>
        <table class="mp3p-form-table">
            <tr>
                <th><label><?php esc_html_e( 'Autores', 'spindle-mp3-player' ); ?> <span class="mp3p-hint">(<?php esc_html_e( 'compositores', 'spindle-mp3-player' ); ?>)</span></label></th>
                <td><input type="text" name="mp3p_autores" value="<?php echo esc_attr($autores); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Ej: Juan García, María López', 'spindle-mp3-player' ); ?>" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Intérpretes', 'spindle-mp3-player' ); ?></label></th>
                <td><input type="text" name="mp3p_interpretes" value="<?php echo esc_attr($interpretes); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Ej: Coro Municipal, Orquesta Sinfónica', 'spindle-mp3-player' ); ?>" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Año', 'spindle-mp3-player' ); ?></label></th>
                <td><input type="number" name="mp3p_anio" value="<?php echo esc_attr($anio); ?>" style="width:100px" min="1900" max="2099" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Licencia', 'spindle-mp3-player' ); ?></label></th>
                <td>
                    <select name="mp3p_licencia">
                        <option value=""><?php esc_html_e( '— Sin especificar —', 'spindle-mp3-player' ); ?></option>
                        <?php foreach ( $licencias as $lic ) : ?>
                        <option value="<?php echo esc_attr($lic); ?>" <?php selected($licencia, $lic); ?>><?php echo esc_html($lic); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Descripción', 'spindle-mp3-player' ); ?></label></th>
                <td><textarea name="mp3p_descripcion" rows="3" class="widefat"><?php echo esc_textarea($descripcion); ?></textarea></td>
            </tr>
        </table>
        <?php
    }

    // ── DISCO: diseño del player ──────────────────────────────────
    public static function render_disco_diseno( $post ) {
        wp_nonce_field( 'mp3p_disco_diseno_save', 'mp3p_disco_diseno_nonce' );
        self::render_diseno_options( $post, 'disco' );
    }

        // ── DISCO: canciones asignadas con drag & drop ───────────────
    public static function render_disco_canciones( $post ) {
        wp_nonce_field( 'mp3p_disco_orden_save', 'mp3p_disco_orden_nonce' );
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- necesario: listar canciones del disco ordenadas por su orden manual.
        $canciones = get_posts( [
            'post_type'      => 'mp3p_cancion',
            'posts_per_page' => -1,
            'meta_query'     => [ [ 'key' => '_mp3p_disco_id', 'value' => $post->ID ] ],
            'meta_key'       => '_mp3p_orden',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
        ] );
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        ?>
        <div class="mp3p-canciones-list">
        <?php if ( empty($canciones) ) : ?>
            <p class="mp3p-empty"><?php
                printf(
                    /* translators: %s: link to add the first song */
                    esc_html__( 'Sin canciones. %s', 'spindle-mp3-player' ),
                    '<a href="' . esc_url( admin_url( 'post-new.php?post_type=mp3p_cancion&disco_id=' . $post->ID ) ) . '">' . esc_html__( 'Añadir primera canción', 'spindle-mp3-player' ) . '</a>'
                );
            ?></p>
        <?php else : ?>
            <p style="color:#888;font-size:12px;margin:0 0 10px">
                <span class="dashicons dashicons-move" style="vertical-align:middle"></span>
                <?php esc_html_e( 'Arrastra las filas para cambiar el orden de reproducción. Guarda el disco para aplicar los cambios.', 'spindle-mp3-player' ); ?>
            </p>
            <input type="hidden" name="mp3p_canciones_orden" id="mp3p_canciones_orden"
                   value="<?php echo esc_attr(implode(',', wp_list_pluck($canciones, 'ID'))); ?>" />
            <table class="wp-list-table widefat fixed striped" id="mp3p_canciones_table">
                <thead><tr>
                    <th style="width:30px"></th>
                    <th><?php esc_html_e( 'Título', 'spindle-mp3-player' ); ?></th>
                    <th><?php esc_html_e( 'Autor', 'spindle-mp3-player' ); ?></th>
                    <th><?php esc_html_e( 'Intérpretes', 'spindle-mp3-player' ); ?></th>
                    <th style="width:80px"><?php esc_html_e( 'Letra', 'spindle-mp3-player' ); ?></th>
                    <th style="width:80px"><?php esc_html_e( 'Descarga', 'spindle-mp3-player' ); ?></th>
                    <th style="width:80px"><?php esc_html_e( 'Acciones', 'spindle-mp3-player' ); ?></th>
                </tr></thead>
                <tbody id="mp3p_canciones_sortable">
                <?php foreach ( $canciones as $c ) :
                    $autor       = get_post_meta( $c->ID, '_mp3p_autor',         true );
                    $interps     = get_post_meta( $c->ID, '_mp3p_interpretes',   true );
                    $descargable = get_post_meta( $c->ID, '_mp3p_descargable',   true );
                    $att_id      = get_post_meta( $c->ID, '_mp3p_attachment_id', true );
                    $tiene_letra = trim( (string) get_post_meta( $c->ID, '_mp3p_letra', true ) ) !== '';
                ?>
                <tr data-id="<?php echo intval( $c->ID ); ?>" style="cursor:default">
                    <td style="text-align:center;cursor:grab;color:#aaa;font-size:18px" class="mp3p-drag-handle" title="<?php esc_attr_e( 'Arrastrar para reordenar', 'spindle-mp3-player' ); ?>">⠿</td>
                    <td>
                        <strong><?php echo esc_html($c->post_title); ?></strong>
                        <?php if ( !$att_id ) : ?>
                        <br><span style="color:#d63638;font-size:11px">⚠ <?php esc_html_e( 'Sin archivo de audio', 'spindle-mp3-player' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($autor ?: '—'); ?></td>
                    <td><?php echo esc_html($interps ?: '—'); ?></td>
                    <td><?php if ( $tiene_letra ) : ?>
                        <span class="mp3p-badge mp3p-badge--ok">✓ <?php esc_html_e( 'Sí', 'spindle-mp3-player' ); ?></span>
                        <?php else : ?>
                        <span class="mp3p-badge mp3p-badge--no">✗ <?php esc_html_e( 'No', 'spindle-mp3-player' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php if ( $descargable === '1' ) : ?>
                        <span class="mp3p-badge mp3p-badge--ok">✓ <?php esc_html_e( 'Sí', 'spindle-mp3-player' ); ?></span>
                        <?php else : ?>
                        <span class="mp3p-badge mp3p-badge--no">✗ <?php esc_html_e( 'No', 'spindle-mp3-player' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?php echo esc_url( get_edit_post_link( $c->ID ) ); ?>" class="button button-small"><?php esc_html_e( 'Editar', 'spindle-mp3-player' ); ?></a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:10px">
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=mp3p_cancion&disco_id=' . $post->ID ) ); ?>" class="button">+ <?php esc_html_e( 'Añadir canción', 'spindle-mp3-player' ); ?></a>
            </p>
        <?php endif; ?>
        </div>
        <?php
        ob_start();
        ?>
        jQuery(function($) {
            $('#mp3p_canciones_sortable').sortable({
                handle: '.mp3p-drag-handle',
                placeholder: 'mp3p-sortable-placeholder-tr',
                axis: 'y',
                update: function() {
                    var ids = [];
                    $('#mp3p_canciones_sortable tr').each(function() {
                        ids.push($(this).data('id'));
                    });
                    $('#mp3p_canciones_orden').val(ids.join(','));
                }
            });
        });
        <?php
        $js = ob_get_clean();
        wp_register_script( 'mp3p-canciones-sortable', false, [ 'jquery', 'jquery-ui-sortable' ], MP3P_VERSION, true );
        wp_enqueue_script( 'mp3p-canciones-sortable' );
        wp_add_inline_script( 'mp3p-canciones-sortable', $js );

        ob_start();
        ?>
        .mp3p-sortable-placeholder-tr { display:table-row; height:40px; background:#f0f6fc; outline:2px dashed #2271b1; }
        .mp3p-drag-handle:active { cursor:grabbing; }
        <?php
        $css = ob_get_clean();
        wp_register_style( 'mp3p-canciones-sortable', false, [], MP3P_VERSION );
        wp_enqueue_style( 'mp3p-canciones-sortable' );
        wp_add_inline_style( 'mp3p-canciones-sortable', $css );
        ?>
        <?php
    }

    // ── DISCO: shortcode ──────────────────────────────────────────
    public static function render_disco_shortcode( $post ) {
        $api = rest_url( 'mp3p/v1' );
        ?>
        <p style="margin:0 0 6px;font-size:12px"><?php esc_html_e( 'Shortcode para Cornerstone:', 'spindle-mp3-player' ); ?></p>
        <code class="mp3p-shortcode">[mp3p_disco id="<?php echo intval( $post->ID ); ?>"]</code>
        <button class="button button-small mp3p-copy" data-clip='[mp3p_disco id="<?php echo intval( $post->ID ); ?>"]' style="margin-top:8px;width:100%"><?php esc_html_e( 'Copiar shortcode', 'spindle-mp3-player' ); ?></button>

        <hr style="margin:14px 0">
        <p style="margin:0 0 6px;font-size:12px"><?php esc_html_e( 'Web Component (cualquier web):', 'spindle-mp3-player' ); ?></p>
        <code class="mp3p-shortcode" style="font-size:10px">&lt;mp3p-disco id="<?php echo intval( $post->ID ); ?>" api="<?php echo esc_url($api); ?>"&gt;&lt;/mp3p-disco&gt;</code>
        <button class="button button-small mp3p-copy" data-clip='<mp3p-disco id="<?php echo intval( $post->ID ); ?>" api="<?php echo esc_url($api); ?>"></mp3p-disco>' style="margin-top:8px;width:100%"><?php esc_html_e( 'Copiar Web Component', 'spindle-mp3-player' ); ?></button>
        <?php
    }

    // ── CANCIÓN: info ─────────────────────────────────────────────
    public static function render_cancion_info( $post ) {
        wp_nonce_field( 'mp3p_cancion_save', 'mp3p_cancion_nonce' );

        $disco_id     = get_post_meta( $post->ID, '_mp3p_disco_id',      true );
        $orden        = get_post_meta( $post->ID, '_mp3p_orden',         true );
        $autor        = get_post_meta( $post->ID, '_mp3p_autor',         true );
        $interpretes  = get_post_meta( $post->ID, '_mp3p_interpretes',   true );
        $attachment_id= intval( get_post_meta( $post->ID, '_mp3p_attachment_id', true ) );
        $descargable  = get_post_meta( $post->ID, '_mp3p_descargable',   true );
        $letra        = get_post_meta( $post->ID, '_mp3p_letra',         true );

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo precompleta un desplegable de conveniencia, no modifica datos.
        if ( ! $disco_id && isset( $_GET['disco_id'] ) ) {
            $disco_id = intval( $_GET['disco_id'] );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $audio_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
        $discos    = get_posts( [ 'post_type' => 'mp3p_disco', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        ?>
        <table class="mp3p-form-table">
            <tr>
                <th><label><?php esc_html_e( 'Disco', 'spindle-mp3-player' ); ?></label></th>
                <td>
                    <select name="mp3p_disco_id" class="widefat">
                        <option value=""><?php esc_html_e( '— Canción sin disco —', 'spindle-mp3-player' ); ?></option>
                        <?php foreach ( $discos as $d ) : ?>
                        <option value="<?php echo intval( $d->ID ); ?>" <?php selected($disco_id, $d->ID); ?>><?php echo esc_html($d->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Nº de pista', 'spindle-mp3-player' ); ?></label></th>
                <td><input type="number" name="mp3p_orden" value="<?php echo esc_attr($orden); ?>" min="1" style="width:80px" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Autor', 'spindle-mp3-player' ); ?> <span class="mp3p-hint">(<?php esc_html_e( 'compositor', 'spindle-mp3-player' ); ?>)</span></label></th>
                <td><input type="text" name="mp3p_autor" value="<?php echo esc_attr($autor); ?>" class="widefat" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Intérpretes', 'spindle-mp3-player' ); ?></label></th>
                <td><input type="text" name="mp3p_interpretes" value="<?php echo esc_attr($interpretes); ?>" class="widefat" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Archivo MP3', 'spindle-mp3-player' ); ?></label></th>
                <td>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <input type="hidden" name="mp3p_attachment_id" id="mp3p_attachment_id" value="<?php echo esc_attr($attachment_id); ?>" />
                        <button type="button" class="button" id="mp3p_select_audio">
                            <?php echo $attachment_id ? esc_html__( 'Cambiar archivo', 'spindle-mp3-player' ) : esc_html__( 'Seleccionar MP3', 'spindle-mp3-player' ); ?>
                        </button>
                        <?php if ( $audio_url ) : ?>
                        <span id="mp3p_audio_name" style="font-size:13px;color:#555">
                            <?php echo esc_html( basename( $audio_url ) ); ?>
                        </span>
                        <audio controls style="height:32px;max-width:300px">
                            <source src="<?php echo esc_url($audio_url); ?>" type="audio/mpeg">
                        </audio>
                        <?php else : ?>
                        <span id="mp3p_audio_name" style="font-size:13px;color:#d63638"><?php esc_html_e( 'Sin archivo seleccionado', 'spindle-mp3-player' ); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="description"><?php esc_html_e( 'Selecciona un archivo MP3 de la biblioteca de medios de WordPress.', 'spindle-mp3-player' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Permitir descarga', 'spindle-mp3-player' ); ?></label></th>
                <td>
                    <label class="mp3p-toggle">
                        <input type="checkbox" name="mp3p_descargable" value="1" <?php checked($descargable, '1'); ?> />
                        <span class="mp3p-toggle__slider"></span>
                    </label>
                    <span style="margin-left:10px;vertical-align:middle;font-size:13px">
                        <?php echo $descargable === '1' ? esc_html__( 'Descarga activa', 'spindle-mp3-player' ) : esc_html__( 'Descarga desactivada (candado)', 'spindle-mp3-player' ); ?>
                    </span>
                    <p class="description mp3p-tags-note" style="margin-top:8px"><?php esc_html_e( 'Esta opción solo muestra u oculta el botón de descarga en el player. El archivo de audio es público: cualquiera que tenga su URL puede descargarlo.', 'spindle-mp3-player' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="mp3p_letra"><?php esc_html_e( 'Letra', 'spindle-mp3-player' ); ?></label></th>
                <td>
                    <textarea name="mp3p_letra" id="mp3p_letra" rows="10" class="widefat" style="font-family:inherit;line-height:1.5"><?php echo esc_textarea( $letra ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Opcional. Se muestra en un desplegable junto a la canción. Respeta los saltos de línea; deja una línea en blanco entre estrofas.', 'spindle-mp3-player' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    // ── CANCIÓN: shortcode ────────────────────────────────────────
    public static function render_cancion_shortcode( $post ) {
        $api = rest_url( 'mp3p/v1' );
        ?>
        <p style="margin:0 0 6px;font-size:12px"><?php esc_html_e( 'Shortcode para Cornerstone:', 'spindle-mp3-player' ); ?></p>
        <code class="mp3p-shortcode">[mp3p_cancion id="<?php echo intval( $post->ID ); ?>"]</code>
        <button class="button button-small mp3p-copy" data-clip='[mp3p_cancion id="<?php echo intval( $post->ID ); ?>"]' style="margin-top:8px;width:100%"><?php esc_html_e( 'Copiar shortcode', 'spindle-mp3-player' ); ?></button>

        <hr style="margin:14px 0">
        <p style="margin:0 0 6px;font-size:12px"><?php esc_html_e( 'Web Component:', 'spindle-mp3-player' ); ?></p>
        <code class="mp3p-shortcode" style="font-size:10px">&lt;mp3p-cancion id="<?php echo intval( $post->ID ); ?>" api="<?php echo esc_url($api); ?>"&gt;&lt;/mp3p-cancion&gt;</code>
        <button class="button button-small mp3p-copy" data-clip='<mp3p-cancion id="<?php echo intval( $post->ID ); ?>" api="<?php echo esc_url($api); ?>"></mp3p-cancion>' style="margin-top:8px;width:100%"><?php esc_html_e( 'Copiar Web Component', 'spindle-mp3-player' ); ?></button>
        <?php
    }

    // ── CANCIÓN: diseño del player ────────────────────────────────
    public static function render_cancion_diseno( $post ) {
        wp_nonce_field( 'mp3p_cancion_diseno_save', 'mp3p_cancion_diseno_nonce' );
        self::render_diseno_options( $post, 'cancion' );
    }

        // ── Guardar disco ─────────────────────────────────────────────
    public static function save_disco( $post_id ) {
        if ( get_post_type($post_id) !== 'mp3p_disco' ) return;
        if ( ! isset($_POST['mp3p_disco_nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mp3p_disco_nonce'] ) ), 'mp3p_disco_save') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        foreach ( ['mp3p_autores','mp3p_interpretes','mp3p_anio','mp3p_licencia'] as $f ) {
            if ( isset($_POST[$f]) ) update_post_meta( $post_id, '_'.$f, sanitize_text_field( wp_unslash( $_POST[$f] ) ) );
        }
        // La descripción conserva los saltos de línea
        if ( isset($_POST['mp3p_descripcion']) ) {
            update_post_meta( $post_id, '_mp3p_descripcion', sanitize_textarea_field( wp_unslash( $_POST['mp3p_descripcion'] ) ) );
        }

        // Guardar opciones de diseño del player
        if ( isset($_POST['mp3p_disco_diseno_nonce']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mp3p_disco_diseno_nonce'] ) ), 'mp3p_disco_diseno_save') ) {
            self::save_diseno_fields( $post_id );
        }

        // Guardar orden de canciones por drag & drop
        if ( isset($_POST['mp3p_canciones_orden']) &&
             isset($_POST['mp3p_disco_orden_nonce']) &&
             wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mp3p_disco_orden_nonce'] ) ), 'mp3p_disco_orden_save' ) ) {
            $ids   = array_filter( array_map('intval', explode(',', sanitize_text_field( wp_unslash( $_POST['mp3p_canciones_orden'] ) )) ) );
            $orden = 1;
            foreach ( $ids as $cancion_id ) {
                if ( get_post_type($cancion_id) === 'mp3p_cancion' ) {
                    update_post_meta( $cancion_id, '_mp3p_orden', $orden++ );
                }
            }
        }
    }

    // ── Guardar canción ───────────────────────────────────────────
    public static function save_cancion( $post_id ) {
        if ( get_post_type($post_id) !== 'mp3p_cancion' ) return;
        if ( ! isset($_POST['mp3p_cancion_nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mp3p_cancion_nonce'] ) ), 'mp3p_cancion_save') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        foreach ( ['mp3p_disco_id','mp3p_orden','mp3p_autor','mp3p_interpretes'] as $f ) {
            if ( isset($_POST[$f]) ) update_post_meta( $post_id, '_'.$f, sanitize_text_field( wp_unslash( $_POST[$f] ) ) );
        }

        if ( isset($_POST['mp3p_attachment_id']) ) {
            update_post_meta( $post_id, '_mp3p_attachment_id', intval($_POST['mp3p_attachment_id']) );
        }

        update_post_meta( $post_id, '_mp3p_descargable', isset($_POST['mp3p_descargable']) ? '1' : '0' );

        if ( isset($_POST['mp3p_letra']) ) {
            update_post_meta( $post_id, '_mp3p_letra', sanitize_textarea_field( wp_unslash( $_POST['mp3p_letra'] ) ) );
        }

        // Guardar opciones de diseño
        if ( isset($_POST['mp3p_cancion_diseno_nonce']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mp3p_cancion_diseno_nonce'] ) ), 'mp3p_cancion_diseno_save') ) {
            self::save_diseno_fields( $post_id );
        }
    }

    // ── Guardar campos de diseño (compartido) ─────────────────────
    // Nota: la verificación de nonce se realiza en las tres funciones que llaman
    // a este método (save_disco, save_cancion, save de playlist) antes de invocarlo.
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    public static function save_diseno_fields( $post_id ) {
        if ( isset($_POST['mp3p_variant']) )   update_post_meta( $post_id, '_mp3p_variant',   sanitize_text_field( wp_unslash( $_POST['mp3p_variant'] ) ) );
        if ( isset($_POST['mp3p_theme']) )     update_post_meta( $post_id, '_mp3p_theme',     sanitize_text_field( wp_unslash( $_POST['mp3p_theme'] ) ) );
        if ( isset($_POST['mp3p_accent']) )    update_post_meta( $post_id, '_mp3p_accent',    sanitize_hex_color( wp_unslash( $_POST['mp3p_accent'] ) ) );
        if ( isset($_POST['mp3p_width']) )     update_post_meta( $post_id, '_mp3p_width',     MP3P_Shortcodes::css_length( sanitize_text_field( wp_unslash( $_POST['mp3p_width'] ) ), '100%' ) );
        if ( isset($_POST['mp3p_max_width']) ) update_post_meta( $post_id, '_mp3p_max_width', MP3P_Shortcodes::css_length( sanitize_text_field( wp_unslash( $_POST['mp3p_max_width'] ) ), '100%' ) );
        if ( isset($_POST['mp3p_lyrics']) )    update_post_meta( $post_id, '_mp3p_lyrics',    sanitize_key( wp_unslash( $_POST['mp3p_lyrics'] ) ) === 'modal' ? 'modal' : 'inline' );
        if ( isset($_POST['mp3p_tracklist']) ) update_post_meta( $post_id, '_mp3p_tracklist', sanitize_key( wp_unslash( $_POST['mp3p_tracklist'] ) ) === 'closed' ? 'closed' : 'open' );
        if ( isset($_POST['mp3p_border_radius']) ) update_post_meta( $post_id, '_mp3p_border_radius', max( 0, min( 100, intval( $_POST['mp3p_border_radius'] ) ) ) );
        // Fondo
        if ( isset($_POST['mp3p_bg_type']) ) {
            $bg_type = sanitize_key( wp_unslash( $_POST['mp3p_bg_type'] ) );
            update_post_meta( $post_id, '_mp3p_bg_type', in_array( $bg_type, MP3P_Shortcodes::BG_TYPES, true ) ? $bg_type : 'solid' );
        }
        if ( isset($_POST['mp3p_bg_color']) )    update_post_meta( $post_id, '_mp3p_bg_color',    sanitize_hex_color( wp_unslash( $_POST['mp3p_bg_color'] ) ) );
        if ( isset($_POST['mp3p_bg_grad1']) )    update_post_meta( $post_id, '_mp3p_bg_grad1',    sanitize_hex_color( wp_unslash( $_POST['mp3p_bg_grad1'] ) ) );
        if ( isset($_POST['mp3p_bg_grad2']) )    update_post_meta( $post_id, '_mp3p_bg_grad2',    sanitize_hex_color( wp_unslash( $_POST['mp3p_bg_grad2'] ) ) );
        if ( isset($_POST['mp3p_bg_grad_dir']) ) update_post_meta( $post_id, '_mp3p_bg_grad_dir', MP3P_Shortcodes::grad_dir( sanitize_text_field( wp_unslash( $_POST['mp3p_bg_grad_dir'] ) ) ) );
        // Elementos visibles (solo los que se muestran para este tipo de contenido)
        foreach ( self::show_keys_for( get_post_type( $post_id ) ) as $key ) {
            update_post_meta( $post_id, '_mp3p_show_' . $key, isset($_POST['mp3p_show'][$key]) ? '1' : '0' );
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    // ── Elementos visibles disponibles según el tipo de contenido ──
    public static function show_keys_for( $post_type ) {
        if ( $post_type === 'mp3p_cancion' ) {
            return [ 'cover', 'autores', 'interpretes', 'anio', 'licencia', 'letra' ];
        }
        return [ 'cover', 'autores', 'interpretes', 'anio', 'licencia', 'descripcion', 'letra' ];
    }

    // Colores de acento predefinidos (naranja Braun primero)
    public static function accent_presets() {
        return [ '#e8520a', '#111111', '#2d6cdf', '#e94560', '#00a32a', '#f0ab00', '#8a2be2', '#1abc9c' ];
    }

    // ── Opciones de diseño compartidas (disco, canción, playlist) ──
    public static function render_diseno_options( $post, $tipo = 'disco' ) {
        $pid         = $post->ID;
        $gd          = MP3P_Settings::defaults();
        $variant     = get_post_meta( $pid, '_mp3p_variant',          true ) ?: $gd['variant'];
        $theme       = get_post_meta( $pid, '_mp3p_theme',            true ) ?: $gd['theme'];
        $accent      = get_post_meta( $pid, '_mp3p_accent',           true ) ?: $gd['accent'];
        $width       = get_post_meta( $pid, '_mp3p_width',            true ) ?: '100%';
        $max_width   = get_post_meta( $pid, '_mp3p_max_width',        true ) ?: '100%';
        $tracklist   = get_post_meta( $pid, '_mp3p_tracklist',        true ) ?: $gd['tracklist'];
        $lyrics_mode = get_post_meta( $pid, '_mp3p_lyrics',           true ) ?: $gd['lyrics'];
        $border_radius = intval( get_post_meta( $pid, '_mp3p_border_radius', true ) ?: 4 );
        $bg_type     = get_post_meta( $pid, '_mp3p_bg_type',          true ) ?: 'solid';
        $bg_color    = get_post_meta( $pid, '_mp3p_bg_color',         true ) ?: '#ffffff';
        $bg_grad1    = get_post_meta( $pid, '_mp3p_bg_grad1',         true ) ?: '#ffffff';
        $bg_grad2    = get_post_meta( $pid, '_mp3p_bg_grad2',         true ) ?: '#f0f0f0';
        $bg_grad_dir = get_post_meta( $pid, '_mp3p_bg_grad_dir',      true ) ?: '135deg';
        $show_keys   = self::show_keys_for( get_post_type( $pid ) );
        $show        = [];
        foreach ( $show_keys as $key ) {
            $show[ $key ] = get_post_meta( $pid, '_mp3p_show_' . $key, true ) !== '0';
        }
        $tag = $tipo === 'cancion' ? 'mp3p_cancion' : ( $tipo === 'playlist' ? 'mp3p_playlist' : 'mp3p_disco' );
        $api = rest_url('mp3p/v1');

        // Colores de acento predefinidos
        $accent_presets = self::accent_presets();

        $variant_basic  = [ 'full' => __( 'Full', 'spindle-mp3-player' ), 'compact' => __( 'Compact', 'spindle-mp3-player' ), 'wide' => __( 'Wide', 'spindle-mp3-player' ) ];
        $variant_modern = [ 'vertical' => __( 'Vertical', 'spindle-mp3-player' ), 'horizontal' => __( 'Horizontal', 'spindle-mp3-player' ), 'mini' => __( 'Mini', 'spindle-mp3-player' ) ];
        $theme_opts     = [ 'auto' => __( 'Auto', 'spindle-mp3-player' ), 'dark' => __( 'Dark', 'spindle-mp3-player' ), 'light' => __( 'Light', 'spindle-mp3-player' ) ];
        $bg_type_opts   = [ 'solid' => __( 'Sólido', 'spindle-mp3-player' ), 'gradient' => __( 'Gradiente', 'spindle-mp3-player' ), 'transparent' => __( 'Transparente', 'spindle-mp3-player' ) ];
        $grad_dir_opts  = [ '135deg' => '↘ ' . __( 'Diagonal', 'spindle-mp3-player' ), 'to right' => '→ ' . __( 'Horizontal', 'spindle-mp3-player' ), 'to bottom' => '↓ ' . __( 'Vertical', 'spindle-mp3-player' ), 'to bottom right' => '↘ ' . __( 'Diagonal 2', 'spindle-mp3-player' ) ];
        $lyrics_opts    = [ 'inline' => __( 'Desplegable', 'spindle-mp3-player' ), 'modal' => __( 'Ventana', 'spindle-mp3-player' ) ];
        $tracklist_opts = [ 'open' => __( 'Desplegada', 'spindle-mp3-player' ), 'closed' => __( 'Plegada', 'spindle-mp3-player' ) ];
        $all_labels     = [
            'cover'       => __( 'Portada', 'spindle-mp3-player' ),
            'autores'     => __( 'Autores', 'spindle-mp3-player' ),
            'interpretes' => __( 'Intérpretes', 'spindle-mp3-player' ),
            'anio'        => __( 'Año', 'spindle-mp3-player' ),
            'licencia'    => __( 'Licencia', 'spindle-mp3-player' ),
            'descripcion' => __( 'Descripción', 'spindle-mp3-player' ),
            'letra'       => __( 'Letra', 'spindle-mp3-player' ),
        ];
        $show_labels    = array_intersect_key( $all_labels, array_flip( $show_keys ) );
        ?>
        <div class="mp3p-diseno-box" id="mp3p-diseno-<?php echo intval( $pid ); ?>">

            <!-- Variante: toggle group -->
            <div class="mp3p-df">
                <div class="mp3p-dl"><?php esc_html_e( 'Variante', 'spindle-mp3-player' ); ?></div>
                <div class="mp3p-tg" data-name="mp3p_variant" style="flex-wrap:wrap">
                    <div style="width:100%;font-size:10px;color:#aaa;letter-spacing:.4px;margin-bottom:3px">Basic</div>
                    <?php foreach($variant_basic as $val=>$lbl): ?>
                    <input type="radio" name="mp3p_variant" value="<?php echo esc_attr( $val ); ?>" id="v_<?php echo esc_attr( $pid . '_' . $val ); ?>" <?php checked($variant,$val); ?> style="display:none">
                    <label for="v_<?php echo esc_attr( $pid . '_' . $val ); ?>" class="mp3p-tg-btn <?php echo esc_attr( $variant===$val?'active':'' ); ?>"><?php echo esc_html( $lbl ); ?></label>
                    <?php endforeach; ?>
                    <div style="width:100%;font-size:10px;color:#aaa;letter-spacing:.4px;margin:6px 0 3px">Modern Player</div>
                    <?php foreach($variant_modern as $val=>$lbl): ?>
                    <input type="radio" name="mp3p_variant" value="<?php echo esc_attr( $val ); ?>" id="v_<?php echo esc_attr( $pid . '_' . $val ); ?>" <?php checked($variant,$val); ?> style="display:none">
                    <label for="v_<?php echo esc_attr( $pid . '_' . $val ); ?>" class="mp3p-tg-btn <?php echo esc_attr( $variant===$val?'active':'' ); ?>"><?php echo esc_html( $lbl ); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tema: toggle group -->
            <div class="mp3p-df">
                <div class="mp3p-dl"><?php esc_html_e( 'Tema', 'spindle-mp3-player' ); ?></div>
                <div class="mp3p-tg" data-name="mp3p_theme">
                    <?php foreach($theme_opts as $val=>$lbl): ?>
                    <input type="radio" name="mp3p_theme" value="<?php echo esc_attr( $val ); ?>" id="t_<?php echo esc_attr( $pid . '_' . $val ); ?>" <?php checked($theme,$val); ?> style="display:none">
                    <label for="t_<?php echo esc_attr( $pid . '_' . $val ); ?>" class="mp3p-tg-btn <?php echo esc_attr( $theme===$val?'active':'' ); ?>"><?php echo esc_html( $lbl ); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Acento: color picker + swatches predefinidos -->
            <div class="mp3p-df">
                <div class="mp3p-dl"><?php esc_html_e( 'Color de acento', 'spindle-mp3-player' ); ?></div>
                <div class="mp3p-color-row">
                    <input type="color" name="mp3p_accent" id="mp3p_ac_<?php echo intval( $pid ); ?>" value="<?php echo esc_attr($accent); ?>" class="mp3p-color-picker">
                    <input type="text"  id="mp3p_ah_<?php echo intval( $pid ); ?>" value="<?php echo esc_attr($accent); ?>" class="mp3p-color-hex" maxlength="7">
                </div>
                <div class="mp3p-swatches">
                    <?php foreach($accent_presets as $c): ?>
                    <button type="button" class="mp3p-swatch <?php echo strtolower($c) === strtolower($accent) ? 'active' : ''; ?>"
                            style="background:<?php echo esc_attr($c); ?>"
                            data-color="<?php echo esc_attr($c); ?>"
                            title="<?php echo esc_attr($c); ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Ancho y ancho máximo -->
            <div class="mp3p-df">
                <div style="display:flex;gap:8px">
                    <div style="flex:1;min-width:0">
                        <div class="mp3p-dl" style="margin-bottom:6px"><?php esc_html_e( 'Ancho', 'spindle-mp3-player' ); ?></div>
                        <input type="text" name="mp3p_width" id="mp3p_w_<?php echo intval( $pid ); ?>" value="<?php echo esc_attr($width); ?>" placeholder="100%" class="widefat" style="font-size:12px;font-family:monospace">
                    </div>
                    <div style="flex:1;min-width:0">
                        <div class="mp3p-dl" style="margin-bottom:6px"><?php esc_html_e( 'Ancho máximo', 'spindle-mp3-player' ); ?></div>
                        <input type="text" name="mp3p_max_width" id="mp3p_mw_<?php echo intval( $pid ); ?>" value="<?php echo esc_attr($max_width); ?>" placeholder="100%, 500px…" class="widefat" style="font-size:12px;font-family:monospace">
                    </div>
                </div>
                <p class="description" style="font-size:11px;margin:0"><?php esc_html_e( 'Por defecto 100% del contenedor. Admite %, px, rem, vw.', 'spindle-mp3-player' ); ?></p>
            </div>

            <!-- Lista de pistas: desplegada / plegada -->
            <div class="mp3p-df">
                <div class="mp3p-dl"><?php esc_html_e( 'Lista de pistas', 'spindle-mp3-player' ); ?></div>
                <div class="mp3p-tg" data-name="mp3p_tracklist">
                    <?php foreach($tracklist_opts as $val=>$lbl): ?>
                    <input type="radio" name="mp3p_tracklist" value="<?php echo esc_attr( $val ); ?>" id="tl_<?php echo esc_attr( $pid . '_' . $val ); ?>" <?php checked($tracklist,$val); ?> style="display:none">
                    <label for="tl_<?php echo esc_attr( $pid . '_' . $val ); ?>" class="mp3p-tg-btn <?php echo esc_attr( $tracklist===$val?'active':'' ); ?>"><?php echo esc_html( $lbl ); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Letra: desplegable bajo la pista o ventana modal -->
            <div class="mp3p-df">
                <div class="mp3p-dl"><?php esc_html_e( 'Mostrar letra en', 'spindle-mp3-player' ); ?></div>
                <div class="mp3p-tg" data-name="mp3p_lyrics">
                    <?php foreach($lyrics_opts as $val=>$lbl): ?>
                    <input type="radio" name="mp3p_lyrics" value="<?php echo esc_attr( $val ); ?>" id="ly_<?php echo esc_attr( $pid . '_' . $val ); ?>" <?php checked($lyrics_mode,$val); ?> style="display:none">
                    <label for="ly_<?php echo esc_attr( $pid . '_' . $val ); ?>" class="mp3p-tg-btn <?php echo esc_attr( $lyrics_mode===$val?'active':'' ); ?>"><?php echo esc_html( $lbl ); ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Elementos visibles: toggles -->
            <div class="mp3p-df">
                <div class="mp3p-dl"><?php esc_html_e( 'Elementos visibles', 'spindle-mp3-player' ); ?></div>
                <div class="mp3p-toggles">
                    <?php foreach($show_labels as $key=>$lbl): ?>
                    <div class="mp3p-toggle-row">
                        <span class="mp3p-toggle-name"><?php echo esc_html( $lbl ); ?></span>
                        <label class="mp3p-sw">
                            <input type="checkbox" name="mp3p_show[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked($show[$key]); ?>>
                            <span class="mp3p-sw-track"></span>
                            <span class="mp3p-sw-thumb"></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <hr style="border:none;border-top:1px solid #f0f0f0;margin:12px 0">

            <!-- Esquinas redondeadas -->
            <div class="mp3p-df">
                <div class="mp3p-dl"><?php esc_html_e( 'Esquinas redondeadas', 'spindle-mp3-player' ); ?></div>
                <div style="display:flex;align-items:center;gap:8px">
                    <input type="range" name="mp3p_border_radius" id="mp3p_br_<?php echo intval( $pid ); ?>"
                           min="0" max="32" value="<?php echo esc_attr($border_radius); ?>"
                           style="flex:1" oninput="document.getElementById('mp3p_br_val_<?php echo intval( $pid ); ?>').textContent=this.value+'px'">
                    <span id="mp3p_br_val_<?php echo intval( $pid ); ?>" style="font-size:12px;font-family:monospace;width:36px;color:#555"><?php echo esc_html($border_radius); ?>px</span>
                </div>
            </div>

            <!-- Fondo del player -->
            <div class="mp3p-df">
                <div class="mp3p-dl"><?php esc_html_e( 'Fondo', 'spindle-mp3-player' ); ?></div>
                <div class="mp3p-tg" data-name="mp3p_bg_type">
                    <?php foreach($bg_type_opts as $val=>$lbl): ?>
                    <input type="radio" name="mp3p_bg_type" value="<?php echo esc_attr( $val ); ?>" id="bg_<?php echo esc_attr( $pid . '_' . $val ); ?>" <?php checked($bg_type,$val); ?> style="display:none">
                    <label for="bg_<?php echo esc_attr( $pid . '_' . $val ); ?>" class="mp3p-tg-btn <?php echo esc_attr( $bg_type===$val?'active':'' ); ?>"><?php echo esc_html( $lbl ); ?></label>
                    <?php endforeach; ?>
                </div>

                <div id="mp3p_bg_solid_<?php echo intval( $pid ); ?>" class="mp3p-bg-panel" style="<?php echo esc_attr( $bg_type!=='solid'?'display:none':'' ); ?>">
                    <div class="mp3p-color-row" style="margin-top:6px">
                        <input type="color" name="mp3p_bg_color" id="mp3p_bg_color_<?php echo intval( $pid ); ?>" value="<?php echo esc_attr($bg_color); ?>" class="mp3p-color-picker">
                        <input type="text" id="mp3p_bg_color_hex_<?php echo intval( $pid ); ?>" value="<?php echo esc_attr($bg_color); ?>" class="mp3p-color-hex" maxlength="7">
                    </div>
                </div>

                <div id="mp3p_bg_gradient_<?php echo intval( $pid ); ?>" class="mp3p-bg-panel" style="margin-top:6px;<?php echo esc_attr( $bg_type!=='gradient'?'display:none':'' ); ?>">
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                        <input type="color" name="mp3p_bg_grad1" id="mp3p_bg_grad1_<?php echo intval( $pid ); ?>" value="<?php echo esc_attr($bg_grad1); ?>" class="mp3p-color-picker" title="<?php esc_attr_e( 'Color inicio', 'spindle-mp3-player' ); ?>">
                        <span style="font-size:11px;color:#888">→</span>
                        <input type="color" name="mp3p_bg_grad2" id="mp3p_bg_grad2_<?php echo intval( $pid ); ?>" value="<?php echo esc_attr($bg_grad2); ?>" class="mp3p-color-picker" title="<?php esc_attr_e( 'Color fin', 'spindle-mp3-player' ); ?>">
                        <select name="mp3p_bg_grad_dir" id="mp3p_bg_grad_dir_<?php echo intval( $pid ); ?>" style="font-size:12px;flex:1">
                            <?php foreach($grad_dir_opts as $val=>$lbl): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($bg_grad_dir,$val); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <hr style="border:none;border-top:1px solid #f0f0f0;margin:12px 0">

            <!-- Shortcode generado -->
            <div class="mp3p-df">
                <div class="mp3p-dl">Shortcode</div>
                <code id="mp3p_sc_<?php echo intval( $pid ); ?>" class="mp3p-shortcode" style="font-size:10px;display:block;word-break:break-all;margin-bottom:5px"></code>
                <button type="button" class="button button-small mp3p-copy-sc-<?php echo intval( $pid ); ?>" style="width:100%"><?php esc_html_e( 'Copiar shortcode', 'spindle-mp3-player' ); ?></button>
            </div>

            <div class="mp3p-df" style="margin-top:8px">
                <div class="mp3p-dl">Web Component</div>
                <code id="mp3p_wc_<?php echo intval( $pid ); ?>" class="mp3p-shortcode" style="font-size:10px;display:block;word-break:break-all;margin-bottom:5px"></code>
                <button type="button" class="button button-small mp3p-copy-wc-<?php echo intval( $pid ); ?>" style="width:100%"><?php esc_html_e( 'Copiar Web Component', 'spindle-mp3-player' ); ?></button>
            </div>
        </div>

        <?php ob_start(); ?>
        (function(){
            var pid   = <?php echo intval( $pid ); ?>;
            var tag   = '<?php echo esc_js( $tag ); ?>';
            var api   = '<?php echo esc_js($api); ?>';
            var tipo  = '<?php echo esc_js( $tipo ); ?>';
            var i18n  = mp3pDiseno.i18n;
            var box   = document.getElementById('mp3p-diseno-' + pid);

            function getBg() {
                var btype = box.querySelector('input[name="mp3p_bg_type"]:checked')?.value || 'solid';
                if (btype === 'transparent') return 'transparent';
                if (btype === 'gradient') {
                    var c1  = document.getElementById('mp3p_bg_grad1_' + pid)?.value || '#ffffff';
                    var c2  = document.getElementById('mp3p_bg_grad2_' + pid)?.value || '#f0f0f0';
                    var dir = document.getElementById('mp3p_bg_grad_dir_' + pid)?.value || '135deg';
                    return 'linear-gradient(' + dir + ',' + c1 + ',' + c2 + ')';
                }
                // Sólido: solo devuelve el color si no es el blanco por defecto
                var c = document.getElementById('mp3p_bg_color_' + pid)?.value || '#ffffff';
                return c.toLowerCase() === '#ffffff' ? '' : c;
            }

            function update() {
                var br = document.getElementById('mp3p_br_' + pid)?.value || '4';
                var v  = box.querySelector('input[name="mp3p_variant"]:checked')?.value || 'full';
                var th = box.querySelector('input[name="mp3p_theme"]:checked')?.value   || 'auto';
                var ac = document.getElementById('mp3p_ac_' + pid)?.value || '#e8520a';
                var w  = (document.getElementById('mp3p_w_' + pid)?.value || '100%').trim();
                var mw = (document.getElementById('mp3p_mw_' + pid)?.value || '100%').trim();
                var tl = box.querySelector('input[name="mp3p_tracklist"]:checked')?.value || 'open';
                var ly = box.querySelector('input[name="mp3p_lyrics"]:checked')?.value || 'inline';
                var bg = getBg();
                // Si el tema es dark o light, ignorar el fondo personalizado
                var bgAttr = (th === 'dark' || th === 'light') ? '' : bg;
                var hidden = [];
                box.querySelectorAll('input[name^="mp3p_show"]').forEach(function(c){
                    if (!c.checked) hidden.push(c.name.replace('mp3p_show[','').replace(']',''));
                });
                var sc = '[' + tag + ' id="' + pid + '" variant="' + v + '" theme="' + th + '" accent="' + ac + '"';
                if (br && br !== '4') sc += ' radius="' + br + '"';
                if (w && w !== '100%') sc += ' width="' + w + '"';
                if (mw && mw !== '100%') sc += ' max_width="' + mw + '"';
                sc += ' tracklist="' + tl + '"';
                if (ly === 'modal') sc += ' lyrics="modal"';
                if (bgAttr) sc += ' bg="' + bgAttr + '"';
                hidden.forEach(function(k){ sc += ' show_' + k + '="0"'; });
                sc += ']';

                var wctag = tag.replace('mp3p_','mp3p-');
                var wc = '<' + wctag + ' id="' + pid + '" api="' + api + '" variant="' + v + '" theme="' + th + '" accent="' + ac + '" tracklist="' + tl + '"';
                if (ly === 'modal') wc += ' lyrics="modal"';
                hidden.forEach(function(k){ wc += ' show_' + k + '="0"'; });
                var wstyle = [];
                if (w && w !== '100%') wstyle.push('width:' + w);
                if (mw && mw !== '100%') wstyle.push('max-width:' + mw);
                if (bg && bg !== '#ffffff') wstyle.push('background:' + bg);
                if (wstyle.length) wc += ' style="' + wstyle.join(';') + '"';
                wc += '></' + wctag + '>';

                document.getElementById('mp3p_sc_' + pid).textContent = sc;
                document.getElementById('mp3p_wc_' + pid).textContent = wc;
                return { sc: sc, wc: wc };
            }

            // Toggle groups (variante, tema, bg_type)
            box.querySelectorAll('.mp3p-tg').forEach(function(tg) {
                tg.querySelectorAll('input[type="radio"]').forEach(function(r) {
                    r.addEventListener('change', function() {
                        tg.querySelectorAll('.mp3p-tg-btn').forEach(function(b){ b.classList.remove('active'); });
                        tg.querySelector('label[for="' + r.id + '"]').classList.add('active');
                        // Mostrar/ocultar paneles de fondo
                        if (r.name === 'mp3p_bg_type') {
                            var solid = document.getElementById('mp3p_bg_solid_' + pid);
                            var grad  = document.getElementById('mp3p_bg_gradient_' + pid);
                            if (solid) solid.style.display = r.value === 'solid'    ? '' : 'none';
                            if (grad)  grad.style.display  = r.value === 'gradient' ? '' : 'none';
                        }
                        // Al cambiar de variante, limpiar el ancho máximo
                        // para evitar arrastrar configuraciones de otra variante
                        if (r.name === 'mp3p_variant') {
                            var mwField = document.getElementById('mp3p_mw_' + pid);
                            if (mwField) mwField.value = '100%';
                        }
                        update();
                    });
                });
            });

            // Color picker
            var ac  = document.getElementById('mp3p_ac_' + pid);
            var ah  = document.getElementById('mp3p_ah_' + pid);
            if (ac) {
                ac.addEventListener('input', function(){
                    ah.value = ac.value;
                    syncSwatches(ac.value);
                    update();
                });
                ah.addEventListener('input', function(){
                    if (/^#[0-9a-fA-F]{6}$/.test(ah.value)) {
                        ac.value = ah.value;
                        syncSwatches(ah.value);
                        update();
                    }
                });
            }

            // Swatches
            box.querySelectorAll('.mp3p-swatch').forEach(function(sw) {
                sw.addEventListener('click', function() {
                    var c = this.dataset.color;
                    ac.value = c;
                    ah.value = c;
                    syncSwatches(c);
                    update();
                });
            });

            function syncSwatches(color) {
                box.querySelectorAll('.mp3p-swatch').forEach(function(sw){
                    sw.classList.toggle('active', sw.dataset.color.toLowerCase() === color.toLowerCase());
                });
            }

            // Border radius
            document.getElementById('mp3p_br_' + pid)?.addEventListener('input', update);

            // Fondo: color sólido
            var bgs = document.getElementById('mp3p_bg_color_' + pid);
            var bgh = document.getElementById('mp3p_bg_color_hex_' + pid);
            if (bgs) {
                bgs.addEventListener('input', function(){ if(bgh) bgh.value = bgs.value; update(); });
                if (bgh) bgh.addEventListener('input', function(){ if(/^#[0-9a-fA-F]{6}$/.test(bgh.value)){ bgs.value = bgh.value; update(); } });
            }
            // Fondo: gradiente
            ['mp3p_bg_grad1_','mp3p_bg_grad2_'].forEach(function(id){
                var el = document.getElementById(id + pid);
                if (el) el.addEventListener('input', update);
            });
            var gradDir = document.getElementById('mp3p_bg_grad_dir_' + pid);
            if (gradDir) gradDir.addEventListener('change', update);

            // Ancho y toggles
            document.getElementById('mp3p_w_' + pid)?.addEventListener('input', update);
            document.getElementById('mp3p_mw_' + pid)?.addEventListener('input', update);
            box.querySelectorAll('input[name^="mp3p_show"]').forEach(function(c){ c.addEventListener('change', update); });

            // Copiar
            document.querySelector('.mp3p-copy-sc-' + pid)?.addEventListener('click', function(){
                navigator.clipboard.writeText(update().sc).then(function(){
                    var b = document.querySelector('.mp3p-copy-sc-' + pid);
                    var orig = b.textContent;
                    b.textContent = i18n.copied;
                    setTimeout(function(){ b.textContent = orig; }, 2000);
                });
            });
            document.querySelector('.mp3p-copy-wc-' + pid)?.addEventListener('click', function(){
                navigator.clipboard.writeText(update().wc).then(function(){
                    var b = document.querySelector('.mp3p-copy-wc-' + pid);
                    var orig = b.textContent;
                    b.textContent = i18n.copied;
                    setTimeout(function(){ b.textContent = orig; }, 2000);
                });
            });

            update();
        })();
        <?php
        $mp3p_diseno_js = ob_get_clean();
        wp_register_script( 'mp3p-diseno-options-' . $pid, false, [], MP3P_VERSION, true );
        wp_enqueue_script( 'mp3p-diseno-options-' . $pid );
        wp_localize_script( 'mp3p-diseno-options-' . $pid, 'mp3pDiseno', [
            'i18n' => [ 'copied' => __( '✓ Copiado', 'spindle-mp3-player' ) ],
        ] );
        wp_add_inline_script( 'mp3p-diseno-options-' . $pid, $mp3p_diseno_js );
        ?>
        <?php
    }

    // ── Estilos admin ─────────────────────────────────────────────
    public static function admin_styles() {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array($screen->post_type, ['mp3p_disco','mp3p_cancion']) ) return;
        ?>
        <?php ob_start(); ?>
        .mp3p-form-table { width:100%;border-collapse:collapse }
        .mp3p-form-table th { width:180px;text-align:left;padding:10px 12px 10px 0;font-weight:600;vertical-align:top }
        .mp3p-form-table td { padding:8px 0 }
        .mp3p-form-table tr { border-bottom:1px solid #f0f0f0 }
        .mp3p-hint { font-weight:400;color:#888;font-size:12px }
        .mp3p-shortcode { display:block;background:#f6f7f7;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:11px;word-break:break-all;margin-top:4px }
        .mp3p-badge { display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600 }
        .mp3p-badge--ok { background:#d4edda;color:#155724 }
        .mp3p-badge--no { background:#f8d7da;color:#721c24 }
        .mp3p-empty { color:#888;font-style:italic;padding:12px 0 }
        .mp3p-toggle { position:relative;display:inline-block;width:44px;height:24px;vertical-align:middle }
        .mp3p-toggle input { opacity:0;width:0;height:0 }
        .mp3p-toggle__slider { position:absolute;cursor:pointer;inset:0;background:#ccc;border-radius:24px;transition:.3s }
        .mp3p-toggle__slider:before { content:"";position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s }
        .mp3p-toggle input:checked + .mp3p-toggle__slider { background:#2271b1 }
        .mp3p-toggle input:checked + .mp3p-toggle__slider:before { transform:translateX(20px) }
        <?php
        $mp3p_admin_css = ob_get_clean();
        wp_register_style( 'mp3p-admin-inline', false, [], MP3P_VERSION );
        wp_enqueue_style( 'mp3p-admin-inline' );
        wp_add_inline_style( 'mp3p-admin-inline', $mp3p_admin_css );
        ?>
        <?php
    }

    // ── Scripts admin ─────────────────────────────────────────────
    public static function admin_scripts() {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array($screen->post_type, ['mp3p_disco','mp3p_cancion']) ) return;
        ?>
        <?php ob_start(); ?>
        jQuery(function($){
            var i18n = mp3pAdminInline.i18n;
            // Media library picker para MP3
            var mediaFrame;
            $('#mp3p_select_audio').on('click', function(e){
                e.preventDefault();
                if (mediaFrame) { mediaFrame.open(); return; }
                mediaFrame = wp.media({
                    title: i18n.selectMp3Title,
                    button: { text: i18n.useThisFile },
                    library: { type: 'audio/mpeg' },
                    multiple: false
                });
                mediaFrame.on('select', function(){
                    var att = mediaFrame.state().get('selection').first().toJSON();
                    $('#mp3p_attachment_id').val(att.id);
                    $('#mp3p_audio_name').text(att.filename).css('color','#555');
                    $('#mp3p_select_audio').text(i18n.changeFile);
                    // Preview
                    var $audio = $('audio');
                    if ($audio.length) {
                        $audio.find('source').attr('src', att.url);
                        $audio[0].load();
                    } else {
                        $('#mp3p_audio_name').after('<audio controls style="height:32px;max-width:300px;display:block;margin-top:8px"><source src="'+att.url+'" type="audio/mpeg"></audio>');
                    }
                    fillFromTags(att.id);
                });
                mediaFrame.open();
            });

            // Rellena la ficha de la canción con las etiquetas ID3 del archivo elegido.
            // Campos vacíos: se rellenan. Letra: la del archivo sustituye a la actual si el archivo trae letra.
            function fillFromTags(attId) {
                if (!$('#mp3p_letra').length) return; // solo en la ficha de canción
                var $note = $('#mp3p_tags_note');
                if (!$note.length) {
                    $note = $('<p id="mp3p_tags_note" class="description mp3p-tags-note"></p>');
                    $('#mp3p_select_audio').closest('td').append($note);
                }
                $note.text(i18n.readingTags);

                $.post(mp3pAdminInline.ajaxUrl, {
                    action: 'mp3p_read_tags', nonce: mp3pAdminInline.nonce, ids: [attId], full: 1
                }).done(function(res){
                    var t = res && res.success && res.data ? res.data[attId] : null;
                    if (!t) { $note.text(i18n.noTagsRead); return; }
                    var filled = [];

                    function fillEmpty($el, val, label) {
                        if (!$el.length || !val || String($el.val()).trim() !== '') return;
                        $el.val(val).trigger('input').trigger('change');
                        filled.push(label);
                    }

                    var $title = $('#title');
                    if ($title.length && t.title && $title.val().trim() === '') {
                        $title.val(t.title).trigger('input');
                        $('#title-prompt-text').addClass('screen-reader-text');
                        filled.push(i18n.fTitle);
                    }
                    fillEmpty($('[name="mp3p_autor"]'),       t.composer || t.artist, i18n.fAuthor);
                    fillEmpty($('[name="mp3p_interpretes"]'), t.artist,               i18n.fPerformers);
                    fillEmpty($('[name="mp3p_orden"]'),       t.track || '',          i18n.fTrack);

                    // Disco: si no hay ninguno elegido y existe uno con el mismo título que el álbum
                    var $disco = $('[name="mp3p_disco_id"]');
                    if ($disco.length && !$disco.val() && t.album) {
                        var album = t.album.trim().toLowerCase();
                        $disco.find('option').each(function(){
                            if ($(this).val() && $(this).text().trim().toLowerCase() === album) {
                                $disco.val($(this).val()).trigger('change');
                                filled.push(i18n.fAlbum);
                                return false;
                            }
                        });
                    }

                    if (t.lyrics_text) {
                        $('#mp3p_letra').val(t.lyrics_text).trigger('input');
                        filled.push(i18n.fLyrics);
                    }

                    $note.text(filled.length ? i18n.tagsFilled + ' ' + filled.join(', ') + '. ' + i18n.rememberSave : i18n.nothingToFill);
                }).fail(function(){ $note.text(i18n.noTagsRead); });
            }

            // Copiar shortcode / web component
            $(document).on('click', '.mp3p-copy', function(){
                var text = $(this).data('clip');
                navigator.clipboard.writeText(text).then(() => {
                    var $btn = $(this);
                    var orig = $btn.text();
                    $btn.text(i18n.copied);
                    setTimeout(() => $btn.text(orig), 2000);
                });
            });
        });
        <?php
        $mp3p_admin_js = ob_get_clean();
        wp_register_script( 'mp3p-admin-inline', false, [ 'jquery' ], MP3P_VERSION, true );
        wp_enqueue_script( 'mp3p-admin-inline' );
        wp_localize_script( 'mp3p-admin-inline', 'mp3pAdminInline', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mp3p_importer' ),
            'i18n' => [
                'selectMp3Title' => __( 'Seleccionar archivo MP3', 'spindle-mp3-player' ),
                'useThisFile'    => __( 'Usar este archivo', 'spindle-mp3-player' ),
                'changeFile'     => __( 'Cambiar archivo', 'spindle-mp3-player' ),
                'copied'         => __( '✓ Copiado', 'spindle-mp3-player' ),
                'readingTags'    => __( 'Leyendo etiquetas ID3...', 'spindle-mp3-player' ),
                'noTagsRead'     => __( 'No se han podido leer las etiquetas del archivo.', 'spindle-mp3-player' ),
                'tagsFilled'     => __( 'Datos leídos del archivo:', 'spindle-mp3-player' ),
                'nothingToFill'  => __( 'El archivo no aporta datos nuevos: los campos ya estaban rellenos.', 'spindle-mp3-player' ),
                'rememberSave'   => __( 'Revísalos y guarda la canción.', 'spindle-mp3-player' ),
                'fTitle'         => __( 'título', 'spindle-mp3-player' ),
                'fAuthor'        => __( 'autor', 'spindle-mp3-player' ),
                'fPerformers'    => __( 'intérpretes', 'spindle-mp3-player' ),
                'fTrack'         => __( 'nº de pista', 'spindle-mp3-player' ),
                'fAlbum'         => __( 'disco', 'spindle-mp3-player' ),
                'fLyrics'        => __( 'letra', 'spindle-mp3-player' ),
            ],
        ] );
        wp_add_inline_script( 'mp3p-admin-inline', $mp3p_admin_js );
        ?>
        <?php
    }
}
