<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap mp3p-dashboard">
    <h1 style="display:flex;align-items:center;gap:10px">
        <span class="dashicons dashicons-album" style="font-size:26px;width:26px;height:26px;color:#111"></span>
        Spindle MP3 Player
    </h1>

    <div class="mp3p-stats">
        <div class="mp3p-stat-card">
            <span class="mp3p-stat-card__icon dashicons dashicons-album"></span>
            <div class="mp3p-stat-card__data">
                <span class="mp3p-stat-card__num"><?php echo intval($total_discos); ?></span>
                <span class="mp3p-stat-card__label"><?php esc_html_e( 'Discos', 'spindle-mp3-player' ); ?></span>
            </div>
        </div>
        <div class="mp3p-stat-card">
            <span class="mp3p-stat-card__icon dashicons dashicons-format-audio"></span>
            <div class="mp3p-stat-card__data">
                <span class="mp3p-stat-card__num"><?php echo intval($total_canciones); ?></span>
                <span class="mp3p-stat-card__label"><?php esc_html_e( 'Canciones', 'spindle-mp3-player' ); ?></span>
            </div>
        </div>
        <div class="mp3p-stat-card">
            <span class="mp3p-stat-card__icon dashicons dashicons-download"></span>
            <div class="mp3p-stat-card__data">
                <span class="mp3p-stat-card__num"><?php echo intval($con_descarga); ?></span>
                <span class="mp3p-stat-card__label"><?php esc_html_e( 'Con descarga activa', 'spindle-mp3-player' ); ?></span>
            </div>
        </div>
        <div class="mp3p-stat-card">
            <span class="mp3p-stat-card__icon dashicons dashicons-lock"></span>
            <div class="mp3p-stat-card__data">
                <span class="mp3p-stat-card__num"><?php echo intval($total_canciones - $con_descarga); ?></span>
                <span class="mp3p-stat-card__label"><?php esc_html_e( 'Sin descarga', 'spindle-mp3-player' ); ?></span>
            </div>
        </div>
    </div>

    <div class="mp3p-dashboard-cols">
        <div>
            <div class="mp3p-panel">
                <div class="mp3p-panel__header">
                    <h2><?php esc_html_e( 'Últimos discos', 'spindle-mp3-player' ); ?></h2>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=mp3p_disco' ) ); ?>" class="button button-primary button-small">+ <?php esc_html_e( 'Nuevo disco', 'spindle-mp3-player' ); ?></a>
                </div>
                <?php if ( empty($ultimos_discos) ) : ?>
                    <p class="mp3p-empty" style="padding:20px"><?php
                        printf(
                            /* translators: %s: link to the importer page */
                            esc_html__( 'No hay discos. %s.', 'spindle-mp3-player' ),
                            '<a href="' . esc_url( admin_url( 'admin.php?page=mp3p_importar' ) ) . '">' . esc_html__( 'Importa tu primer disco', 'spindle-mp3-player' ) . '</a>'
                        );
                    ?></p>
                <?php else : ?>
                    <table class="widefat fixed striped mp3p-discos-table">
                        <thead><tr>
                            <th style="width:64px;white-space:nowrap"><?php esc_html_e( 'Portada', 'spindle-mp3-player' ); ?></th>
                            <th style="white-space:nowrap"><?php esc_html_e( 'Título', 'spindle-mp3-player' ); ?></th>
                            <th style="width:70px;white-space:nowrap"><?php esc_html_e( 'Pistas', 'spindle-mp3-player' ); ?></th>
                            <th style="width:260px;white-space:nowrap"><?php esc_html_e( 'Shortcode', 'spindle-mp3-player' ); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( $ultimos_discos as $mp3p_disco ) :
                            $mp3p_portada = get_the_post_thumbnail_url( $mp3p_disco->ID, 'thumbnail' );
                            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- necesario: no existe relación nativa disco→canción sin meta_query.
                            $mp3p_npistas = count( get_posts( [ 'post_type' => 'mp3p_cancion', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_mp3p_disco_id', 'meta_value' => $mp3p_disco->ID ] ) );
                            $mp3p_sc = '[mp3p_disco id="' . $mp3p_disco->ID . '"]';
                        ?>
                        <tr>
                            <td><?php if ( $mp3p_portada ) : ?><img src="<?php echo esc_url( $mp3p_portada ); ?>" style="width:52px;height:52px;object-fit:cover;border-radius:2px"><?php else : ?><span class="dashicons dashicons-album" style="font-size:40px;width:40px;height:40px;color:#ddd"></span><?php endif; ?></td>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $mp3p_disco->ID ) ); ?>"><strong><?php echo esc_html( $mp3p_disco->post_title ); ?></strong></a></td>
                            <td><?php echo intval( $mp3p_npistas ); ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:6px">
                                    <code style="font-size:11px;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo esc_html( $mp3p_sc ); ?></code>
                                    <button type="button" class="mp3p-copy-sc" data-sc="<?php echo esc_attr( $mp3p_sc ); ?>" title="<?php esc_attr_e( 'Copiar shortcode', 'spindle-mp3-player' ); ?>" style="background:none;border:none;cursor:pointer;padding:2px;color:#888;flex-shrink:0">
                                        <span class="dashicons dashicons-clipboard" style="font-size:16px;width:16px;height:16px"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    ob_start();
                    ?>
                    document.querySelectorAll('.mp3p-copy-sc').forEach(function(btn){
                        btn.addEventListener('click', function(){
                            navigator.clipboard.writeText(btn.dataset.sc).then(function(){
                                var icon = btn.querySelector('.dashicons');
                                icon.className = 'dashicons dashicons-yes';
                                icon.style.color = '#2e7d32';
                                setTimeout(function(){
                                    icon.className = 'dashicons dashicons-clipboard';
                                    icon.style.color = '';
                                }, 1500);
                            });
                        });
                    });
                    <?php
                    $mp3p_dashboard_js = ob_get_clean();
                    wp_register_script( 'mp3p-dashboard-copy', false, [], MP3P_VERSION, true );
                    wp_enqueue_script( 'mp3p-dashboard-copy' );
                    wp_add_inline_script( 'mp3p-dashboard-copy', $mp3p_dashboard_js );
                    ?>
                    <p style="padding:12px 18px"><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mp3p_disco' ) ); ?>"><?php esc_html_e( 'Ver todos los discos', 'spindle-mp3-player' ); ?> →</a></p>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="mp3p-panel">
                <div class="mp3p-panel__header"><h2><?php esc_html_e( 'Cómo usar', 'spindle-mp3-player' ); ?></h2></div>
                <div class="mp3p-help">
                    <div class="mp3p-help__item">
                        <strong><?php esc_html_e( 'Player de disco completo', 'spindle-mp3-player' ); ?></strong>
                        <code>[mp3p_disco id="X"]</code>
                        <p><?php esc_html_e( 'Portada + lista de canciones con controles y botón de descarga.', 'spindle-mp3-player' ); ?></p>
                    </div>
                    <div class="mp3p-help__item">
                        <strong><?php esc_html_e( 'Mini player de canción', 'spindle-mp3-player' ); ?></strong>
                        <code>[mp3p_cancion id="X"]</code>
                        <p><?php esc_html_e( 'Reproductor compacto para una sola pista.', 'spindle-mp3-player' ); ?></p>
                    </div>
                    <div class="mp3p-help__item">
                        <strong><?php esc_html_e( 'Web Component (otras webs)', 'spindle-mp3-player' ); ?></strong>
                        <code>&lt;mp3p-disco id="X" api="..."&gt;</code>
                        <p><?php esc_html_e( 'API:', 'spindle-mp3-player' ); ?> <a href="<?php echo esc_url($api_url); ?>" target="_blank"><?php echo esc_url($api_url); ?></a></p>
                    </div>
                    <div class="mp3p-help__item">
                        <strong><?php esc_html_e( 'Importar discos', 'spindle-mp3-player' ); ?></strong>
                        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=mp3p_importar' ) ); ?>"><?php esc_html_e( 'Sube tus MP3', 'spindle-mp3-player' ); ?> →</a> <?php esc_html_e( 'El plugin lee las etiquetas ID3 y crea el disco automáticamente.', 'spindle-mp3-player' ); ?></p>
                    </div>
                </div>
            </div>
            <div class="mp3p-panel" style="margin-top:16px">
                <div class="mp3p-panel__header"><h2><?php esc_html_e( 'Acciones rápidas', 'spindle-mp3-player' ); ?></h2></div>
                <p style="display:flex;flex-direction:column;gap:8px">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=mp3p_importar' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Importar MP3', 'spindle-mp3-player' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=mp3p_disco' ) ); ?>" class="button">+ <?php esc_html_e( 'Nuevo disco manual', 'spindle-mp3-player' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=mp3p_cancion' ) ); ?>" class="button">+ <?php esc_html_e( 'Nueva canción', 'spindle-mp3-player' ); ?></a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=mp3p_settings' ) ); ?>" class="button"><?php esc_html_e( 'Configuración', 'spindle-mp3-player' ); ?></a>
                </p>
            </div>
        </div>
    </div>
</div>
