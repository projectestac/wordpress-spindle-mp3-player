<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Assets {

    public static function register() {
        add_action( 'wp_enqueue_scripts',    [ __CLASS__, 'frontend' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin' ] );
    }

    public static function frontend() {
        // JetBrains Mono — usada en variants Modern Player. Se sirve desde el propio plugin
        // (public/fonts/jetbrains-mono), sin conectar con Google Fonts.
        wp_register_style(
            'mp3p-jetbrains-mono',
            MP3P_URL . 'public/css/jetbrains-mono.css',
            [],
            MP3P_VERSION
        );
        // Howler.js — motor de audio
        wp_register_script(
            'howler',
            MP3P_URL . 'public/js/howler.min.js',
            [],
            '2.2.4',
            true
        );
        // Web Component — depende de Howler
        wp_register_script(
            'mp3p-component',
            MP3P_URL . 'public/js/mp3player.js',
            [ 'howler' ],
            MP3P_VERSION,
            true
        );
        // Textos del player (el JS incluye fallback en español para webs externas)
        wp_localize_script( 'mp3p-component', 'mp3pI18n', [
            'download'   => __( 'Descargar', 'spindle-mp3-player' ),
            'noDownload' => __( 'Sin descarga', 'spindle-mp3-player' ),
            'prev'       => __( 'Anterior', 'spindle-mp3-player' ),
            'next'       => __( 'Siguiente', 'spindle-mp3-player' ),
            /* translators: %d: number of songs (always 1) */
            'song'       => __( '%d canción', 'spindle-mp3-player' ),
            /* translators: %d: number of songs */
            'songs'      => __( '%d canciones', 'spindle-mp3-player' ),
            'hideList'   => __( 'Ocultar lista', 'spindle-mp3-player' ),
            'lyrics'     => __( 'Letra', 'spindle-mp3-player' ),
            'tracks'     => __( 'pistas', 'spindle-mp3-player' ),
            'loading'    => __( 'Cargando…', 'spindle-mp3-player' ),
            'paused'     => __( 'EN PAUSA', 'spindle-mp3-player' ),
            'playing'    => __( 'REPRODUCIENDO', 'spindle-mp3-player' ),
            'close'      => __( 'Cerrar', 'spindle-mp3-player' ),
            'noLyrics'   => __( 'Sin letra', 'spindle-mp3-player' ),
        ] );
    }

    public static function admin( $hook ) {
        $screen = get_current_screen();
        if ( ! $screen ) return;

        $is_cpt       = in_array( $screen->post_type, [ 'mp3p_disco', 'mp3p_cancion', 'mp3p_playlist' ] );
        $is_mp3p_page = strpos( $hook, 'mp3p' ) !== false;
        $is_importer  = strpos( $hook, 'mp3p_importar' ) !== false;

        if ( $is_cpt || $is_mp3p_page ) {
            wp_enqueue_media();
        }

        if ( $is_cpt || $is_mp3p_page ) {
            wp_enqueue_style(
                'mp3p-admin',
                MP3P_URL . 'public/css/mp3player-admin.css',
                [],
                MP3P_VERSION
            );
        }

        if ( $is_importer ) {
            wp_register_script(
                'mp3p-jsmediatags',
                MP3P_URL . 'public/js/jsmediatags.min.js',
                [],
                '3.9.7',
                true
            );
            wp_enqueue_script( 'mp3p-jsmediatags' );
            wp_localize_script( 'mp3p-jsmediatags', 'mp3pImporter', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mp3p_importer' ),
                'i18n'    => [
                    'noMp3Detected'      => __( 'No se detectaron archivos MP3.', 'spindle-mp3-player' ),
                    'readingComplete'    => __( 'Lectura completada', 'spindle-mp3-player' ),
                    'noAlbum'            => __( 'Sin álbum', 'spindle-mp3-player' ),
                    'albumTitleLabel'    => __( 'Título del disco', 'spindle-mp3-player' ),
                    'authorsLabel'       => __( 'Autores', 'spindle-mp3-player' ),
                    'performersLabel'    => __( 'Intérpretes', 'spindle-mp3-player' ),
                    'yearLabel'          => __( 'Año', 'spindle-mp3-player' ),
                    'tracksSuffix'       => __( 'pistas', 'spindle-mp3-player' ),
                    'importThisAlbum'    => __( 'Importar este disco', 'spindle-mp3-player' ),
                    'createAlbum'        => __( 'Crear disco', 'spindle-mp3-player' ),
                    'downloadable'       => __( 'Descargable', 'spindle-mp3-player' ),
                    'status'             => __( 'Estado', 'spindle-mp3-player' ),
                    'thTitle'            => __( 'Título', 'spindle-mp3-player' ),
                    'thAuthor'           => __( 'Autor', 'spindle-mp3-player' ),
                    'thPerformers'       => __( 'Intérpretes', 'spindle-mp3-player' ),
                    'importing'          => __( 'Importando…', 'spindle-mp3-player' ),
                    'errorPrefix'        => __( 'Error:', 'spindle-mp3-player' ),
                    'imported'           => __( '✓ Importado', 'spindle-mp3-player' ),
                    'viewAlbum'          => __( 'Ver disco', 'spindle-mp3-player' ),
                    'uploading'          => __( 'subiendo…', 'spindle-mp3-player' ),
                    'uploadingStatus'    => __( 'Subiendo…', 'spindle-mp3-player' ),
                    'noFile'             => __( 'Sin archivo', 'spindle-mp3-player' ),
                    'uploaded'           => __( '✓ Subido', 'spindle-mp3-player' ),
                    'errorStatus'        => __( '✗ Error', 'spindle-mp3-player' ),
                    'networkError'       => __( '✗ Error de red', 'spindle-mp3-player' ),
                    'selectMp3Title'     => __( 'Seleccionar archivos MP3', 'spindle-mp3-player' ),
                    'useTheseFiles'      => __( 'Usar estos archivos', 'spindle-mp3-player' ),
                    'filesSelected'      => __( 'archivo(s) seleccionado(s)', 'spindle-mp3-player' ),
                    'reviewAndConfirm'   => __( 'Revisa y confirma', 'spindle-mp3-player' ),
                    'creating'           => __( 'Creando…', 'spindle-mp3-player' ),
                    'created'            => __( '✓ Creado', 'spindle-mp3-player' ),
                    'albumCreated'       => __( '✓ Disco creado', 'spindle-mp3-player' ),
                    'lyricsCol'          => __( 'Letra', 'spindle-mp3-player' ),
                    'fileHasLyrics'      => __( 'El archivo incluye letra; se añadirá a la canción al importar', 'spindle-mp3-player' ),
                ],
            ] );
        }
    }
}
