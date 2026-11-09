<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Shortcodes {

    // Elementos visibles que el Web Component entiende (atributos show_*)
    const SHOW_KEYS = [ 'cover', 'autores', 'interpretes', 'anio', 'licencia', 'descripcion', 'letra' ];

    // Direcciones de degradado que ofrece el editor de diseño
    const GRAD_DIRS = [ '135deg', 'to right', 'to bottom', 'to bottom right' ];
    const BG_TYPES  = [ 'solid', 'gradient', 'transparent' ];

    /** Color hexadecimal o $fallback. */
    public static function hex( $value, $fallback = '' ) {
        $hex = sanitize_hex_color( trim( (string) $value ) );
        return $hex ? $hex : $fallback;
    }

    /** Dirección de degradado de la lista o la diagonal por defecto. */
    public static function grad_dir( $value ) {
        $value = strtolower( trim( (string) $value ) );
        return in_array( $value, self::GRAD_DIRS, true ) ? $value : '135deg';
    }

    /**
     * Fondo válido: transparent, #hex o linear-gradient(dir,#hex,#hex[,...]) con dir de la lista.
     * Cualquier otra cosa se descarta (cadena vacía = fondo del tema).
     */
    public static function sanitize_bg( $value ) {
        $value = trim( (string) $value );
        if ( $value === '' || $value === 'transparent' ) return $value;
        if ( self::hex( $value ) ) return self::hex( $value );
        if ( ! preg_match( '/^linear-gradient\((.+)\)$/i', $value, $m ) ) return '';
        $parts = array_map( 'trim', explode( ',', $m[1] ) );
        if ( count( $parts ) < 3 || ! in_array( strtolower( $parts[0] ), self::GRAD_DIRS, true ) ) return '';
        $out = [ strtolower( $parts[0] ) ];
        foreach ( array_slice( $parts, 1 ) as $c ) {
            $h = self::hex( $c );
            if ( ! $h ) return '';
            $out[] = $h;
        }
        return 'linear-gradient(' . implode( ',', $out ) . ')';
    }

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
            $c1  = self::hex( get_post_meta( $id, '_mp3p_bg_grad1', true ), '#ffffff' );
            $c2  = self::hex( get_post_meta( $id, '_mp3p_bg_grad2', true ), '#f0f0f0' );
            $dir = self::grad_dir( get_post_meta( $id, '_mp3p_bg_grad_dir', true ) );
            $bg  = 'linear-gradient(' . $dir . ',' . $c1 . ',' . $c2 . ')';
        } else {
            $bg = self::hex( get_post_meta( $id, '_mp3p_bg_color', true ), '#ffffff' );
        }

        $radius = get_post_meta( $id, '_mp3p_border_radius', true );
        $gd     = MP3P_Settings::defaults();

        $defaults = [
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

        // Validación: solo valores conocidos, colores hex, fondos válidos y enteros
        $gd = MP3P_Settings::defaults();
        $atts['variant'] = in_array( $atts['variant'], MP3P_Settings::VARIANTS, true ) ? $atts['variant'] : $gd['variant'];
        $atts['theme']   = in_array( $atts['theme'],   MP3P_Settings::THEMES,   true ) ? $atts['theme']   : $gd['theme'];
        $atts['accent']  = self::hex( $atts['accent'], $gd['accent'] );
        $atts['bg']      = self::sanitize_bg( $atts['bg'] );
        $atts['radius']  = ( $atts['radius'] !== '' && is_numeric( $atts['radius'] ) ) ? (string) max( 0, min( 100, intval( $atts['radius'] ) ) ) : '';

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
        // Siempre la API de este sitio: el atributo api del shortcode se ignora
        $html .= ' api="'       . esc_url( rest_url( 'mp3p/v1' ) ) . '"';
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
