<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Admin_Pages {

    public static function register() {
        add_action( 'admin_menu',               [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_menu',               [ __CLASS__, 'reorder_menu' ], 999 );
        add_action( 'admin_footer',             [ __CLASS__, 'delete_confirm_script' ] );
        add_action( 'wp_ajax_mp3p_delete_post', [ __CLASS__, 'ajax_delete_post' ] );
        add_filter( 'post_row_actions',         [ __CLASS__, 'custom_row_actions' ], 10, 2 );
    }

    public static function add_menu() {
        add_menu_page(
            'Spindle MP3 Player', 'MP3 Player', 'manage_options',
            'mp3p_menu', [ __CLASS__, 'render_dashboard' ],
            'dashicons-album', 30
        );
        add_submenu_page( 'mp3p_menu', __( 'Dashboard', 'spindle-mp3-player' ), __( 'Dashboard', 'spindle-mp3-player' ),
            'manage_options', 'mp3p_menu', [ __CLASS__, 'render_dashboard' ] );
        add_submenu_page( 'mp3p_menu', __( 'Importar MP3', 'spindle-mp3-player' ), __( 'Importar MP3', 'spindle-mp3-player' ),
            'manage_options', 'mp3p_importar', [ 'MP3P_Importer', 'render' ] );
        add_submenu_page( 'mp3p_menu', __( 'Configuración', 'spindle-mp3-player' ), __( 'Configuración', 'spindle-mp3-player' ),
            'manage_options', 'mp3p_settings', [ 'MP3P_Settings', 'render' ] );
    }

    // ── Forzar que "Dashboard" aparezca siempre primero en el submenú ─
    public static function reorder_menu() {
        global $submenu;
        if ( empty( $submenu['mp3p_menu'] ) ) return;

        $items      = $submenu['mp3p_menu'];
        $dashboard  = null;
        $rest       = [];

        foreach ( $items as $item ) {
            // $item[2] es el slug de la página
            if ( $item[2] === 'mp3p_menu' ) {
                $dashboard = $item;
            } else {
                $rest[] = $item;
            }
        }

        if ( $dashboard ) {
            $submenu['mp3p_menu'] = array_merge( [ $dashboard ], $rest );
        }
    }

    // ── Sustituir enlace "Papelera" por "Eliminar definitivamente" ─
    public static function custom_row_actions( $actions, $post ) {
        if ( ! in_array( $post->post_type, [ 'mp3p_disco', 'mp3p_cancion' ] ) ) return $actions;
        if ( ! current_user_can( 'delete_post', $post->ID ) ) return $actions;

        // Eliminar los enlaces estándar de papelera
        unset( $actions['trash'] );
        unset( $actions['delete'] );
        unset( $actions['untrash'] );

        $label = $post->post_type === 'mp3p_disco'
            ? __( 'Eliminar disco y canciones', 'spindle-mp3-player' )
            : __( 'Eliminar canción', 'spindle-mp3-player' );

        $nonce = wp_create_nonce( 'mp3p_delete_' . $post->ID );
        $actions['mp3p_delete'] = sprintf(
            '<a href="#" class="mp3p-delete-btn submitdelete" data-id="%d" data-nonce="%s" data-type="%s" data-title="%s">%s</a>',
            $post->ID,
            $nonce,
            $post->post_type,
            esc_attr( $post->post_title ),
            esc_html( $label )
        );

        return $actions;
    }

    // ── Script de confirmación y borrado AJAX ─────────────────────
    public static function delete_confirm_script() {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, [ 'mp3p_disco', 'mp3p_cancion' ] ) ) return;

        ob_start();
        ?>
        .mp3p-delete-btn { color: #d63638 !important; }
        .mp3p-delete-btn:hover { color: #a00 !important; }

        /* Modal de confirmación */
        #mp3p-delete-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0,0,0,.6);
            align-items: center;
            justify-content: center;
        }
        #mp3p-delete-modal.active { display: flex; }
        #mp3p-delete-modal__box {
            background: #fff;
            border-radius: 8px;
            padding: 28px 32px;
            max-width: 440px;
            width: 90%;
            box-shadow: 0 8px 40px rgba(0,0,0,.3);
            text-align: center;
        }
        #mp3p-delete-modal__box h3 { margin: 0 0 12px; font-size: 18px; color: #1d2327; }
        #mp3p-delete-modal__box p  { color: #555; margin: 0 0 24px; line-height: 1.6; font-size: 14px; }
        #mp3p-delete-modal__box .mp3p-modal-actions { display: flex; gap: 10px; justify-content: center; }
        #mp3p-delete-modal__box .mp3p-modal-actions .button { min-width: 120px; }
        .mp3p-modal-warning { background: #fcf0f1; border: 1px solid #f0b8bb; border-radius: 6px; padding: 10px 14px; margin-bottom: 20px; font-size: 13px; color: #8a1f1f; }
        <?php
        $css = ob_get_clean();
        wp_register_style( 'mp3p-delete-modal', false, [], MP3P_VERSION );
        wp_enqueue_style( 'mp3p-delete-modal' );
        wp_add_inline_style( 'mp3p-delete-modal', $css );
        ?>

        <!-- Modal -->
        <div id="mp3p-delete-modal">
            <div id="mp3p-delete-modal__box">
                <h3 id="mp3p-modal-title"><?php esc_html_e( '¿Eliminar definitivamente?', 'spindle-mp3-player' ); ?></h3>
                <div class="mp3p-modal-warning" id="mp3p-modal-warning"></div>
                <p id="mp3p-modal-desc"></p>
                <div class="mp3p-modal-actions">
                    <button class="button button-primary" id="mp3p-modal-confirm" style="background:#d63638;border-color:#d63638"><?php esc_html_e( 'Sí, eliminar', 'spindle-mp3-player' ); ?></button>
                    <button class="button" id="mp3p-modal-cancel"><?php esc_html_e( 'Cancelar', 'spindle-mp3-player' ); ?></button>
                </div>
            </div>
        </div>

        <?php
        ob_start();
        ?>
        jQuery(function($) {
            var pendingId    = null;
            var pendingNonce = null;
            var $row         = null;
            var i18n         = mp3pAdminPages.i18n;

            // Abrir modal al hacer clic en "Eliminar"
            $(document).on('click', '.mp3p-delete-btn', function(e) {
                e.preventDefault();
                var $btn  = $(this);
                pendingId    = $btn.data('id');
                pendingNonce = $btn.data('nonce');
                var type     = $btn.data('type');
                var title    = $btn.data('title');
                $row         = $btn.closest('tr');

                $('#mp3p-modal-title').text(i18n.confirmTitle);

                if (type === 'mp3p_disco') {
                    $('#mp3p-modal-warning').html('⚠️ <strong>' + i18n.cannotUndo + '</strong>');
                    $('#mp3p-modal-desc').html(
                        i18n.deleteDiscoBefore + ' <strong>' + $('<div>').text(title).html() + '</strong>' + i18n.deleteDiscoAfter
                    );
                } else {
                    $('#mp3p-modal-warning').html('⚠️ <strong>' + i18n.cannotUndo + '</strong>');
                    $('#mp3p-modal-desc').html(
                        i18n.deleteCancionBefore + ' <strong>' + $('<div>').text(title).html() + '</strong>' + i18n.deleteCancionAfter
                    );
                }

                $('#mp3p-delete-modal').addClass('active');
            });

            // Cancelar
            $('#mp3p-modal-cancel, #mp3p-delete-modal').on('click', function(e) {
                if (e.target !== this && e.target.id !== 'mp3p-modal-cancel') return;
                $('#mp3p-delete-modal').removeClass('active');
                pendingId = pendingNonce = $row = null;
            });

            // Confirmar borrado
            $('#mp3p-modal-confirm').on('click', function() {
                if (!pendingId) return;
                var $btn = $(this);
                $btn.text(i18n.deleting).prop('disabled', true);

                $.post(mp3pAdminPages.ajaxUrl, {
                    action: 'mp3p_delete_post',
                    id:     pendingId,
                    nonce:  pendingNonce,
                }, function(res) {
                    $('#mp3p-delete-modal').removeClass('active');
                    $btn.text(i18n.confirmDelete).prop('disabled', false);
                    if (res.success) {
                        // Animar y eliminar la fila de la tabla
                        if ($row) {
                            $row.css({ background: '#fcf0f1' });
                            $row.fadeOut(400, function() { $(this).remove(); });
                        }
                    } else {
                        alert(i18n.errorPrefix + ' ' + (res.data || i18n.couldNotDelete));
                    }
                    pendingId = pendingNonce = $row = null;
                }).fail(function() {
                    $btn.text(i18n.confirmDelete).prop('disabled', false);
                    alert(i18n.connectionError);
                });
            });
        });
        <?php
        $js = ob_get_clean();
        wp_register_script( 'mp3p-delete-confirm', false, [ 'jquery' ], MP3P_VERSION, true );
        wp_enqueue_script( 'mp3p-delete-confirm' );
        wp_localize_script( 'mp3p-delete-confirm', 'mp3pAdminPages', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'i18n'    => [
                'confirmTitle'        => __( '¿Eliminar definitivamente?', 'spindle-mp3-player' ),
                'cannotUndo'          => __( 'Esta acción no se puede deshacer.', 'spindle-mp3-player' ),
                'deleteDiscoBefore'   => __( 'Se eliminarán', 'spindle-mp3-player' ),
                'deleteDiscoAfter'    => __( ', todas sus canciones y los archivos de audio y portada que creó el plugin, si no se usan en ningún otro lugar.', 'spindle-mp3-player' ),
                'deleteCancionBefore' => __( 'Se eliminará la canción', 'spindle-mp3-player' ),
                'deleteCancionAfter'  => __( ' y su archivo de audio, si lo creó el plugin y no se usa en ningún otro lugar.', 'spindle-mp3-player' ),
                'deleting'            => __( 'Eliminando...', 'spindle-mp3-player' ),
                'confirmDelete'       => __( 'Sí, eliminar', 'spindle-mp3-player' ),
                'errorPrefix'         => __( 'Error:', 'spindle-mp3-player' ),
                'couldNotDelete'      => __( 'No se pudo eliminar.', 'spindle-mp3-player' ),
                'connectionError'     => __( 'Error de conexión.', 'spindle-mp3-player' ),
            ],
        ] );
        wp_add_inline_script( 'mp3p-delete-confirm', $js );
    }

    // ── AJAX: borrado definitivo ──────────────────────────────────
    public static function ajax_delete_post() {
        $id    = isset( $_POST['id'] )    ? absint( $_POST['id'] ) : 0;
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'mp3p_delete_' . $id ) ) {
            wp_send_json_error( __( 'Nonce inválido.', 'spindle-mp3-player' ) );
        }
        if ( ! current_user_can( 'delete_post', $id ) ) {
            wp_send_json_error( __( 'Sin permisos.', 'spindle-mp3-player' ) );
        }

        $post_type = get_post_type( $id );
        if ( ! in_array( $post_type, [ 'mp3p_disco', 'mp3p_cancion' ] ) ) {
            wp_send_json_error( __( 'Tipo de post no válido.', 'spindle-mp3-player' ) );
        }

        // wp_delete_post con force_delete=true dispara before_delete_post
        // que a su vez ejecuta nuestro cascade delete
        $result = wp_delete_post( $id, true );

        if ( $result ) {
            wp_send_json_success( [ 'deleted' => $id ] );
        } else {
            wp_send_json_error( __( 'No se pudo eliminar.', 'spindle-mp3-player' ) );
        }
    }

    // ── Dashboard ─────────────────────────────────────────────────
    public static function render_dashboard() {
        $total_discos    = wp_count_posts('mp3p_disco')->publish   + wp_count_posts('mp3p_disco')->draft;
        $total_canciones = wp_count_posts('mp3p_cancion')->publish + wp_count_posts('mp3p_cancion')->draft;
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- necesario: no hay campo nativo alternativo para filtrar por descarga activa.
        $con_descarga    = count( get_posts(['post_type'=>'mp3p_cancion','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>[['key'=>'_mp3p_descargable','value'=>'1']]]) );
        $ultimos_discos  = get_posts(['post_type'=>'mp3p_disco','posts_per_page'=>5,'orderby'=>'date','order'=>'DESC']);
        $api_url         = rest_url('mp3p/v1');
        include MP3P_DIR . 'admin/views/dashboard.php';
    }
}
