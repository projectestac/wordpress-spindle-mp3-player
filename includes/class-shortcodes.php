<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Shortcodes {

    // Elementos visibles que el Web Component entiende (atributos show_*)
    const SHOW_KEYS = [ 'cover', 'autores', 'interpretes', 'anio', 'licencia', 'descripcion', 'letra' ];

    public static function register() {
        add_shortcode( 'mp3p_disco',    [ __CLASS__, 'render_disco' ] );
        add_shortcode( 'mp3p_cancion',  [ __CLASS__, 'render_cancion' ] );
        add_shortcode( 'mp3p_playlist', [ __CLASS__, 'render_playlist' ] );
    }

    /**
     * Sanea una longitud CSS (100%, 640px, 40rem, 80vw, auto…).
     * Devuelve $fallback si el valor no es válido, para evitar inyectar CSS arbitrario.
     */
    public static function css_length( $value, $fallback = '100%' ) {
        $value = strtolower( trim( (string) $value ) );
        if ( $value === '' ) return $fallback;
        if ( $value === 'auto' ) return 'auto';
        if ( preg_match( '/^\d+(\.\d+)?$/', $value ) ) return $value . 'px'; // número sin unidad → px
        if ( preg_match( '/^\d+(\.\d+)?(px|%|rem|em|vw|ch)$/', $value ) ) return $value;
        return $fallback;
    }

    // Opciones guardadas en el post (disco, canción o playlist), con fallback a los globales
    private static function player_defaults( $id ) {
        $id = intval( $id );

        // Fondo
        $bg_type = get_post_meta( $id, '_mp3p_bg_type', true ) ?: 'solid';
        if ( $bg_type === 'transparent' ) {
            $bg = 'transparent';
        } elseif ( $bg_type === 'gradient' ) {
            $c1  = get_post_meta( $id, '_mp3p_bg_grad1',    true ) ?: '#ffffff';
            $c2  = get_post_meta( $id, '_mp3p_bg_grad2',    true ) ?: '#f0f0f0';
            $dir = get_post_meta( $id, '_mp3p_bg_grad_dir', true ) ?: '135deg';
            $bg  = 'linear-gradient(' . $dir . ',' . $c1 . ',' . $c2 . ')';
        } else {
            $bg = get_post_meta( $id, '_mp3p_bg_color', true ) ?: '#ffffff';
        }

        $radius = get_post_meta( $id, '_mp3p_border_radius', true );
        $gd     = MP3P_Settings::defaults();

        $defaults = [
            'api'       => rest_url( 'mp3p/v1' ),
            'variant'   => get_post_meta( $id, '_mp3p_variant',   true ) ?: $gd['variant'],
            'theme'     => get_post_meta( $id, '_mp3p_theme',     true ) ?: $gd['theme'],
            'accent'    => get_post_meta( $id, '_mp3p_accent',    true ) ?: $gd['accent'],
            'width'     => get_post_meta( $id, '_mp3p_width',     true ) ?: '100%',
            'max_width' => get_post_meta( $id, '_mp3p_max_width', true ) ?: '100%',
            'radius'    => ( $radius !== '' && $radius !== false ) ? (string) intval( $radius ) : '',
            'tracklist' => get_post_meta( $id, '_mp3p_tracklist', true ) ?: $gd['tracklist'],
            'lyrics'    => get_post_meta( $id, '_mp3p_lyrics',    true ) ?: $gd['lyrics'],
            'bg'        => $bg,
        ];
        foreach ( self::SHOW_KEYS as $key ) {
            $defaults[ 'show_' . $key ] = get_post_meta( $id, '_mp3p_show_' . $key, true ) !== '0' ? '1' : '0';
        }
        return $defaults;
    }

    // Render común para los tres tipos de player
    private static function render_player( $tag, $raw_atts ) {
        $raw_atts = is_array( $raw_atts ) ? $raw_atts : [];
        $id       = intval( $raw_atts['id'] ?? 0 );
        if ( ! $id ) {
            $errors = [
                'mp3p-disco'    => __( 'Error: falta id en [mp3p_disco]', 'spindle-mp3-player' ),
                'mp3p-cancion'  => __( 'Error: falta id en [mp3p_cancion]', 'spindle-mp3-player' ),
                'mp3p-playlist' => __( 'Error: falta id en [mp3p_playlist]', 'spindle-mp3-player' ),
            ];
            return '<p style="color:red">' . esc_html( $errors[ $tag ] ) . '</p>';
        }

        // 1) Defaults guardados en el post  2) atributos escritos en el shortcode (prioridad)
        $atts = shortcode_atts( self::player_defaults( $id ), $raw_atts );
        foreach ( $raw_atts as $key => $val ) {
            if ( $val !== '' && $val !== null && $key !== 'id' && array_key_exists( $key, $atts ) ) {
                $atts[ $key ] = $val;
            }
        }

        wp_enqueue_script( 'mp3p-component' );
        if ( in_array( $atts['variant'], [ 'vertical', 'horizontal', 'mini' ], true ) ) {
            wp_enqueue_style( 'jetbrains-mono' ); // Variants Modern Player
        }

        // Ancho (por defecto 100% del contenedor) y ancho máximo
        $width     = self::css_length( $atts['width'], '100%' );
        $max_width = self::css_length( $atts['max_width'], '100%' );
        $style     = 'width:' . $width . ';';
        if ( $max_width !== '100%' ) $style .= 'max-width:' . $max_width . ';';

        $html  = '<' . $tag . ' id="' . $id . '"';
        $html .= ' api="'       . esc_url( $atts['api'] ) . '"';
        $html .= ' theme="'     . esc_attr( $atts['theme'] ) . '"';
        $html .= ' accent="'    . esc_attr( $atts['accent'] ) . '"';
        $html .= ' variant="'   . esc_attr( $atts['variant'] ) . '"';
        $html .= ' tracklist="' . ( $atts['tracklist'] === 'closed' ? 'closed' : 'open' ) . '"';
        $html .= ' lyrics="'    . ( $atts['lyrics'] === 'modal' ? 'modal' : 'inline' ) . '"';
        foreach ( self::SHOW_KEYS as $key ) {
            $html .= ' show_' . $key . '="' . ( $atts[ 'show_' . $key ] === '0' ? '0' : '1' ) . '"';
        }
        if ( ! empty( $atts['bg'] ) )  $html .= ' bg="' . esc_attr( $atts['bg'] ) . '"';
        if ( $atts['radius'] !== '' )  $html .= ' radius="' . intval( $atts['radius'] ) . '"';
        $html .= ' style="' . esc_attr( $style ) . '"';
        $html .= '></' . $tag . '>';
        return $html;
    }

    public static function render_disco( $atts ) {
        return self::render_player( 'mp3p-disco', $atts );
    }

    public static function render_cancion( $atts ) {
        return self::render_player( 'mp3p-cancion', $atts );
    }

    public static function render_playlist( $atts ) {
        return self::render_player( 'mp3p-playlist', $atts );
    }
}
