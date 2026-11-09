<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Settings {

    const PLAYER_TYPES = [ 'mp3p_disco', 'mp3p_cancion', 'mp3p_playlist' ];
    const VARIANTS     = [ 'full', 'compact', 'wide', 'vertical', 'horizontal', 'mini' ];
    const THEMES       = [ 'auto', 'dark', 'light' ];
    const DEFAULT_ACCENT = '#e8520a';

    public static function register() {
        add_action( 'admin_init',      [ __CLASS__, 'register_settings' ] );
        add_action( 'wp_insert_post',  [ __CLASS__, 'seed_new_player' ], 10, 3 );
        self::maybe_migrate();
    }

    public static function register_settings() {
        register_setting( 'mp3p_settings', 'mp3p_player_variant', [ 'sanitize_callback' => [ __CLASS__, 'sanitize_variant' ] ] );
        register_setting( 'mp3p_settings', 'mp3p_player_theme',   [ 'sanitize_callback' => [ __CLASS__, 'sanitize_theme' ] ] );
        register_setting( 'mp3p_settings', 'mp3p_player_accent',  [ 'sanitize_callback' => 'sanitize_hex_color' ] );
        register_setting( 'mp3p_settings', 'mp3p_tracklist',      [ 'sanitize_callback' => [ __CLASS__, 'sanitize_tracklist' ] ] );
        register_setting( 'mp3p_settings', 'mp3p_lyrics',         [ 'sanitize_callback' => [ __CLASS__, 'sanitize_lyrics' ] ] );
        register_setting( 'mp3p_settings', 'mp3p_lyrics_label',   [ 'sanitize_callback' => [ __CLASS__, 'sanitize_label' ] ] );
        register_setting( 'mp3p_settings', 'mp3p_delete_data_on_uninstall', [ 'sanitize_callback' => [ __CLASS__, 'sanitize_bool' ] ] );
    }

    const DEFAULT_LYRICS_LABEL = 'Text';

    public static function sanitize_label( $v ) {
        $v = trim( sanitize_text_field( (string) $v ) );
        return $v === '' ? self::DEFAULT_LYRICS_LABEL : mb_substr( $v, 0, 20 );
    }
    public static function sanitize_bool( $v ) { return $v === '1' || $v === 1 || $v === true ? '1' : '0'; }

    /** Texto del botón de letra en todos los players. */
    public static function lyrics_label() {
        return self::sanitize_label( get_option( 'mp3p_lyrics_label', self::DEFAULT_LYRICS_LABEL ) );
    }

    public static function sanitize_variant( $v )   { return in_array( $v, self::VARIANTS, true ) ? $v : 'full'; }
    public static function sanitize_theme( $v )     { return in_array( $v, self::THEMES, true ) ? $v : 'auto'; }
    public static function sanitize_tracklist( $v ) { return $v === 'closed' ? 'closed' : 'open'; }
    public static function sanitize_lyrics( $v )    { return $v === 'modal' ? 'modal' : 'inline'; }

    public static function get( $key, $default = '' ) {
        return get_option( 'mp3p_' . $key, $default );
    }

    /** Valores por defecto actuales de la Configuración general. */
    public static function defaults() {
        return [
            'variant'   => self::sanitize_variant( get_option( 'mp3p_player_variant', 'full' ) ),
            'theme'     => self::sanitize_theme( get_option( 'mp3p_player_theme', 'auto' ) ),
            'accent'    => sanitize_hex_color( get_option( 'mp3p_player_accent', self::DEFAULT_ACCENT ) ) ?: self::DEFAULT_ACCENT,
            'tracklist' => self::sanitize_tracklist( get_option( 'mp3p_tracklist', 'open' ) ),
            'lyrics'    => self::sanitize_lyrics( get_option( 'mp3p_lyrics', 'inline' ) ),
        ];
    }

    /**
     * Al crear un disco, canción o playlist (desde el editor o el importador) se copian
     * los valores generales en el propio player. Así, cambiar la Configuración después
     * solo afecta a los players que se creen a partir de ese momento.
     */
    public static function seed_new_player( $post_id, $post, $update ) {
        if ( $update || ! $post || ! in_array( $post->post_type, self::PLAYER_TYPES, true ) ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        self::write_missing( $post_id, self::defaults() );
    }

    private static function write_missing( $post_id, $values ) {
        foreach ( $values as $key => $val ) {
            if ( ! metadata_exists( 'post', $post_id, '_mp3p_' . $key ) ) {
                update_post_meta( $post_id, '_mp3p_' . $key, $val );
            }
        }
    }

    /**
     * Migración única (1.7.0): los players existentes que no tenían algún valor guardado
     * reciben el que estaban usando hasta ahora, para que conserven su aspecto.
     */
    private static function maybe_migrate() {
        if ( get_option( 'mp3p_defaults_migrated' ) === '1' ) return;

        $legacy = [
            'variant'   => 'full',
            'theme'     => self::sanitize_theme( get_option( 'mp3p_player_theme', 'auto' ) ),
            'accent'    => sanitize_hex_color( get_option( 'mp3p_player_accent', '#2d6cdf' ) ) ?: '#2d6cdf',
            'tracklist' => self::sanitize_tracklist( get_option( 'mp3p_tracklist', 'open' ) ),
            'lyrics'    => 'inline',
        ];

        $ids = get_posts( [
            'post_type'      => self::PLAYER_TYPES,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        foreach ( $ids as $id ) {
            self::write_missing( $id, $legacy );
        }

        update_option( 'mp3p_defaults_migrated', '1' );
    }

    // ── Pantalla de Configuración ─────────────────────────────────
    public static function render() {
        if ( ! current_user_can('manage_options') ) return;

        if ( isset($_POST['mp3p_settings_nonce']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mp3p_settings_nonce'] ) ), 'mp3p_save_settings' ) ) {
            update_option( 'mp3p_player_variant', self::sanitize_variant( sanitize_key( wp_unslash( $_POST['mp3p_player_variant'] ?? 'full' ) ) ) );
            update_option( 'mp3p_player_theme',   self::sanitize_theme( sanitize_key( wp_unslash( $_POST['mp3p_player_theme'] ?? 'auto' ) ) ) );
            update_option( 'mp3p_player_accent',  sanitize_hex_color( wp_unslash( $_POST['mp3p_player_accent'] ?? self::DEFAULT_ACCENT ) ) ?: self::DEFAULT_ACCENT );
            update_option( 'mp3p_tracklist',      self::sanitize_tracklist( sanitize_key( wp_unslash( $_POST['mp3p_tracklist'] ?? 'open' ) ) ) );
            update_option( 'mp3p_lyrics',         self::sanitize_lyrics( sanitize_key( wp_unslash( $_POST['mp3p_lyrics'] ?? 'inline' ) ) ) );
            update_option( 'mp3p_lyrics_label',   self::sanitize_label( sanitize_text_field( wp_unslash( $_POST['mp3p_lyrics_label'] ?? '' ) ) ) );
            update_option( 'mp3p_delete_data_on_uninstall', ! empty( $_POST['mp3p_delete_data_on_uninstall'] ) ? '1' : '0' );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '✓ Configuración guardada.', 'spindle-mp3-player' ) . '</p></div>';
        }

        $d   = self::defaults();
        $api = rest_url('mp3p/v1');

        $variant_basic  = [ 'full' => __( 'Full', 'spindle-mp3-player' ), 'compact' => __( 'Compact', 'spindle-mp3-player' ), 'wide' => __( 'Wide', 'spindle-mp3-player' ) ];
        $variant_modern = [ 'vertical' => __( 'Vertical', 'spindle-mp3-player' ), 'horizontal' => __( 'Horizontal', 'spindle-mp3-player' ), 'mini' => __( 'Mini', 'spindle-mp3-player' ) ];
        $theme_opts     = [ 'auto' => __( 'Auto', 'spindle-mp3-player' ), 'light' => __( 'Light', 'spindle-mp3-player' ), 'dark' => __( 'Dark', 'spindle-mp3-player' ) ];
        $tracklist_opts = [ 'open' => __( 'Desplegada', 'spindle-mp3-player' ), 'closed' => __( 'Plegada', 'spindle-mp3-player' ) ];
        $lyrics_opts    = [ 'inline' => __( 'Desplegable', 'spindle-mp3-player' ), 'modal' => __( 'Ventana', 'spindle-mp3-player' ) ];
        $accent_presets = MP3P_Meta_Boxes::accent_presets();

        $tg = function ( $name, $opts, $current ) {
            foreach ( $opts as $val => $lbl ) {
                $id = 'mp3pg_' . $name . '_' . $val;
                printf(
                    '<input type="radio" name="%1$s" value="%2$s" id="%3$s" %4$s style="display:none"><label for="%3$s" class="mp3p-tg-btn %5$s">%6$s</label>',
                    esc_attr( $name ), esc_attr( $val ), esc_attr( $id ), checked( $current, $val, false ),
                    $current === $val ? 'active' : '', esc_html( $lbl )
                );
            }
        };
        ?>
        <div class="wrap mp3p-settings">
            <h1><?php
                /* translators: plugin name, kept as-is */
                echo esc_html__( 'Configuración', 'spindle-mp3-player' ) . ' — Spindle MP3 Player';
            ?></h1>
            <form method="post">
                <?php wp_nonce_field('mp3p_save_settings','mp3p_settings_nonce'); ?>

                <div class="mp3p-panel mp3p-settings-panel">
                    <div class="mp3p-panel__header">
                        <h2><?php esc_html_e( 'Valores por defecto del player', 'spindle-mp3-player' ); ?></h2>
                    </div>
                    <div class="mp3p-settings-body mp3p-diseno-box" id="mp3p-settings-defaults">
                        <p class="mp3p-hint" style="margin:0"><?php esc_html_e( 'Se aplican a los discos, canciones y playlists que se creen a partir de ahora (también al importar). Los players ya creados conservan sus valores; cada uno se puede cambiar en su caja de diseño o con atributos del shortcode.', 'spindle-mp3-player' ); ?></p>

                        <div class="mp3p-df">
                            <div class="mp3p-dl"><?php esc_html_e( 'Variante', 'spindle-mp3-player' ); ?></div>
                            <div class="mp3p-tg" style="flex-wrap:wrap">
                                <div class="mp3p-tg-group">Basic</div>
                                <?php $tg( 'mp3p_player_variant', $variant_basic, $d['variant'] ); ?>
                                <div class="mp3p-tg-group">Modern Player</div>
                                <?php $tg( 'mp3p_player_variant', $variant_modern, $d['variant'] ); ?>
                            </div>
                        </div>

                        <div class="mp3p-df">
                            <div class="mp3p-dl"><?php esc_html_e( 'Tema', 'spindle-mp3-player' ); ?></div>
                            <div class="mp3p-tg"><?php $tg( 'mp3p_player_theme', $theme_opts, $d['theme'] ); ?></div>
                        </div>

                        <div class="mp3p-df">
                            <div class="mp3p-dl"><?php esc_html_e( 'Color de acento', 'spindle-mp3-player' ); ?></div>
                            <div class="mp3p-color-row">
                                <input type="color" name="mp3p_player_accent" id="mp3pg_accent" value="<?php echo esc_attr( $d['accent'] ); ?>" class="mp3p-color-picker">
                                <input type="text" id="mp3pg_accent_hex" value="<?php echo esc_attr( $d['accent'] ); ?>" class="mp3p-color-hex" maxlength="7">
                                <span class="mp3p-hint"><?php esc_html_e( 'Botón play, pista activa y barra de progreso', 'spindle-mp3-player' ); ?></span>
                            </div>
                            <div class="mp3p-swatches">
                                <?php foreach ( $accent_presets as $c ) : ?>
                                <button type="button" class="mp3p-swatch <?php echo strtolower( $c ) === strtolower( $d['accent'] ) ? 'active' : ''; ?>"
                                        style="background:<?php echo esc_attr( $c ); ?>" data-color="<?php echo esc_attr( $c ); ?>" title="<?php echo esc_attr( $c ); ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mp3p-df">
                            <div class="mp3p-dl"><?php esc_html_e( 'Lista de pistas', 'spindle-mp3-player' ); ?></div>
                            <div class="mp3p-tg"><?php $tg( 'mp3p_tracklist', $tracklist_opts, $d['tracklist'] ); ?></div>
                        </div>

                        <div class="mp3p-df">
                            <div class="mp3p-dl"><?php esc_html_e( 'Mostrar letra en', 'spindle-mp3-player' ); ?></div>
                            <div class="mp3p-tg"><?php $tg( 'mp3p_lyrics', $lyrics_opts, $d['lyrics'] ); ?></div>
                        </div>

                        <div class="mp3p-df">
                            <div class="mp3p-dl"><?php esc_html_e( 'Shortcode equivalente', 'spindle-mp3-player' ); ?></div>
                            <code class="mp3p-shortcode" id="mp3pg_preview"></code>
                        </div>
                    </div>
                </div>

                <div class="mp3p-panel mp3p-settings-panel" style="margin-top:20px">
                    <div class="mp3p-panel__header">
                        <h2><?php esc_html_e( 'Textos del player', 'spindle-mp3-player' ); ?></h2>
                    </div>
                    <div class="mp3p-settings-body">
                        <div class="mp3p-df">
                            <div class="mp3p-dl"><label for="mp3pg_lyrics_label"><?php esc_html_e( 'Texto del botón de letra', 'spindle-mp3-player' ); ?></label></div>
                            <input type="text" name="mp3p_lyrics_label" id="mp3pg_lyrics_label" value="<?php echo esc_attr( self::lyrics_label() ); ?>" maxlength="20" class="regular-text" style="max-width:220px">
                            <p class="mp3p-hint" style="margin:0"><?php esc_html_e( 'Aparece junto al icono en el botón que despliega la letra. Se aplica a todos los players, también a los ya creados.', 'spindle-mp3-player' ); ?></p>
                        </div>
                    </div>
                </div>

                <div class="mp3p-panel mp3p-settings-panel" style="margin-top:20px">
                    <div class="mp3p-panel__header">
                        <h2><?php esc_html_e( 'Desinstalación', 'spindle-mp3-player' ); ?></h2>
                    </div>
                    <div class="mp3p-settings-body">
                        <div class="mp3p-df">
                            <label style="display:flex;gap:8px;align-items:flex-start">
                                <input type="checkbox" name="mp3p_delete_data_on_uninstall" value="1" <?php checked( get_option( 'mp3p_delete_data_on_uninstall', '0' ), '1' ); ?> style="margin-top:2px">
                                <span><?php esc_html_e( 'Eliminar los datos al desinstalar', 'spindle-mp3-player' ); ?></span>
                            </label>
                            <p class="mp3p-hint" style="margin:0"><?php esc_html_e( 'Si está marcada, al borrar el plugin desde la pantalla de plugins se eliminarán todos los discos, canciones y playlists. Los archivos de la biblioteca de medios se conservan siempre. Las opciones del plugin se eliminan en cualquier caso.', 'spindle-mp3-player' ); ?></p>
                        </div>
                    </div>
                </div>

                <div class="mp3p-panel mp3p-settings-panel" style="margin-top:20px">
                    <div class="mp3p-panel__header">
                        <h2><?php esc_html_e( 'API REST', 'spindle-mp3-player' ); ?></h2>
                    </div>
                    <div class="mp3p-settings-body">
                        <div class="mp3p-df">
                            <div class="mp3p-dl"><?php esc_html_e( 'URL base', 'spindle-mp3-player' ); ?></div>
                            <code class="mp3p-shortcode"><?php echo esc_url( $api ); ?></code>
                            <p class="mp3p-hint" style="margin:0"><?php
                                printf(
                                    /* translators: %s is the literal "api" attribute name */
                                    esc_html__( 'Usa esta URL en el atributo %s del Web Component para webs externas.', 'spindle-mp3-player' ),
                                    '<code>api</code>'
                                );
                            ?></p>
                        </div>
                        <div class="mp3p-df">
                            <div class="mp3p-dl"><?php esc_html_e( 'Endpoints', 'spindle-mp3-player' ); ?></div>
                            <code class="mp3p-shortcode"><?php echo esc_url( $api ); ?>/disco/{id}<br><?php echo esc_url( $api ); ?>/cancion/{id}<br><?php echo esc_url( $api ); ?>/playlist/{id}<br><?php echo esc_url( $api ); ?>/discos?page=1&amp;per_page=100</code>
                            <p class="mp3p-hint" style="margin:0"><?php esc_html_e( '/discos devuelve como máximo 100 discos por petición; el total va en las cabeceras X-WP-Total y X-WP-TotalPages.', 'spindle-mp3-player' ); ?></p>
                        </div>
                    </div>
                </div>

                <?php submit_button( __( 'Guardar configuración', 'spindle-mp3-player' ) ); ?>
            </form>
        </div>
        <?php
        ob_start();
        ?>
        (function(){
            var box = document.getElementById('mp3p-settings-defaults');
            if (!box) return;
            var picker = document.getElementById('mp3pg_accent');
            var hex    = document.getElementById('mp3pg_accent_hex');
            var out    = document.getElementById('mp3pg_preview');

            function val(name) { var r = box.querySelector('input[name="' + name + '"]:checked'); return r ? r.value : ''; }
            function preview() {
                out.textContent = '[mp3p_disco id="X" variant="' + val('mp3p_player_variant') + '" theme="' + val('mp3p_player_theme')
                    + '" accent="' + picker.value + '" tracklist="' + val('mp3p_tracklist') + '" lyrics="' + val('mp3p_lyrics') + '"]';
            }
            function setAccent(c) {
                picker.value = c; hex.value = c;
                box.querySelectorAll('.mp3p-swatch').forEach(function(s){ s.classList.toggle('active', s.dataset.color.toLowerCase() === c.toLowerCase()); });
                preview();
            }

            box.querySelectorAll('.mp3p-tg').forEach(function(tg){
                tg.querySelectorAll('input[type="radio"]').forEach(function(r){
                    r.addEventListener('change', function(){
                        tg.querySelectorAll('.mp3p-tg-btn').forEach(function(b){ b.classList.toggle('active', b.getAttribute('for') === r.id); });
                        preview();
                    });
                });
            });
            box.querySelectorAll('.mp3p-swatch').forEach(function(s){ s.addEventListener('click', function(){ setAccent(s.dataset.color); }); });
            picker.addEventListener('input', function(){ setAccent(picker.value); });
            hex.addEventListener('input', function(){ if (/^#[0-9a-f]{6}$/i.test(hex.value)) setAccent(hex.value); });
            preview();
        })();
        <?php
        $js = ob_get_clean();
        wp_register_script( 'mp3p-settings', false, [], MP3P_VERSION, true );
        wp_enqueue_script( 'mp3p-settings' );
        wp_add_inline_script( 'mp3p-settings', $js );
    }
}
