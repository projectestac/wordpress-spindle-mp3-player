<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_API {

    const NS = 'mp3p/v1';

    public static function register() {
        register_rest_route( self::NS, '/discos', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_discos' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NS, '/disco/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_disco' ],
            'permission_callback' => '__return_true',
            'args'                => [ 'id' => [ 'validate_callback' => fn($v) => is_numeric($v) ] ],
        ] );
        register_rest_route( self::NS, '/cancion/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_cancion' ],
            'permission_callback' => '__return_true',
            'args'                => [ 'id' => [ 'validate_callback' => fn($v) => is_numeric($v) ] ],
        ] );
        register_rest_route( self::NS, '/playlist/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_playlist' ],
            'permission_callback' => '__return_true',
            'args'                => [ 'id' => [ 'validate_callback' => fn($v) => is_numeric($v) ] ],
        ] );
        // Endpoint para buscar canciones (usado en el editor de playlist)
        register_rest_route( self::NS, '/search/canciones', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'search_canciones' ],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ] );
    }

    public static function get_discos( $request ) {
        $discos = get_posts([
            'post_type' => 'mp3p_disco', 'posts_per_page' => -1,
            'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish',
        ]);
        return self::response( array_map( fn($d) => self::format_disco_summary($d), $discos ) );
    }

    public static function get_disco( $request ) {
        $id    = intval($request['id']);
        $disco = get_post($id);
        if ( !$disco || $disco->post_type !== 'mp3p_disco' || $disco->post_status !== 'publish' ) {
            return new WP_Error('not_found', __( 'Disco no encontrado.', 'spindle-mp3-player' ), ['status'=>404]);
        }
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- necesario: relación disco→canción almacenada en postmeta, sin alternativa nativa.
        $canciones = get_posts([
            'post_type' => 'mp3p_cancion', 'posts_per_page' => -1, 'post_status' => 'publish',
            'meta_query' => [['key'=>'_mp3p_disco_id','value'=>$id]],
            'meta_key' => '_mp3p_orden', 'orderby' => 'meta_value_num', 'order' => 'ASC',
        ]);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        $data = array_merge( self::format_disco_summary($disco), ['tracks' => array_map(fn($c) => self::format_cancion($c), $canciones)] );
        return self::response($data);
    }

    public static function get_cancion( $request ) {
        $id      = intval($request['id']);
        $cancion = get_post($id);
        if ( !$cancion || $cancion->post_type !== 'mp3p_cancion' || $cancion->post_status !== 'publish' ) {
            return new WP_Error('not_found', __( 'Canción no encontrada.', 'spindle-mp3-player' ), ['status'=>404]);
        }
        return self::response( self::format_cancion($cancion) );
    }

    public static function get_playlist( $request ) {
        $id       = intval($request['id']);
        $playlist = get_post($id);
        if ( !$playlist || $playlist->post_type !== 'mp3p_playlist' || $playlist->post_status !== 'publish' ) {
            return new WP_Error('not_found', __( 'Playlist no encontrada.', 'spindle-mp3-player' ), ['status'=>404]);
        }
        $track_ids = get_post_meta($id, '_mp3p_playlist_tracks', true) ?: [];
        $tracks    = [];
        foreach ( $track_ids as $cancion_id ) {
            $cancion = get_post($cancion_id);
            if ( $cancion && $cancion->post_type === 'mp3p_cancion' && $cancion->post_status === 'publish' ) {
                $tracks[] = self::format_cancion($cancion);
            }
        }
        return self::response([
            'id'          => $id,
            'titulo'      => $playlist->post_title,
            'descripcion' => get_post_meta($id, '_mp3p_playlist_descripcion', true) ?: '',
            'tracks'      => $tracks,
        ]);
    }

    public static function search_canciones( $request ) {
        $q = sanitize_text_field($request->get_param('q') ?? '');
        $canciones = get_posts([
            'post_type' => 'mp3p_cancion', 'posts_per_page' => 20,
            'post_status' => 'publish', 's' => $q,
            'orderby' => 'title', 'order' => 'ASC',
        ]);
        $data = array_map(function($c) {
            $disco_id    = intval(get_post_meta($c->ID, '_mp3p_disco_id', true));
            $disco_title = $disco_id ? get_the_title($disco_id) : '';
            $portada     = $disco_id ? (get_the_post_thumbnail_url($disco_id, 'thumbnail') ?: '') : '';
            return [
                'id'          => $c->ID,
                'titulo'      => $c->post_title,
                'autor'       => get_post_meta($c->ID, '_mp3p_autor', true) ?: '',
                'disco'       => $disco_title,
                'disco_id'    => $disco_id,
                'portada'     => $portada,
            ];
        }, $canciones);
        return self::response($data);
    }

    private static function format_disco_summary($disco) {
        $id = $disco->ID;
        return [
            'id'          => $id,
            'titulo'      => $disco->post_title,
            'autores'     => get_post_meta($id, '_mp3p_autores',     true) ?: '',
            'interpretes' => get_post_meta($id, '_mp3p_interpretes', true) ?: '',
            'anio'        => get_post_meta($id, '_mp3p_anio',        true) ?: '',
            'licencia'    => get_post_meta($id, '_mp3p_licencia',    true) ?: '',
            'descripcion' => get_post_meta($id, '_mp3p_descripcion', true) ?: '',
            'portada'     => get_the_post_thumbnail_url($id, 'medium') ?: '',
        ];
    }

    private static function format_cancion($cancion) {
        $id            = $cancion->ID;
        $attachment_id = intval(get_post_meta($id, '_mp3p_attachment_id', true));
        $audio_url     = $attachment_id ? wp_get_attachment_url($attachment_id) : '';
        $disco_id      = intval(get_post_meta($id, '_mp3p_disco_id', true));
        $portada       = $disco_id ? (get_the_post_thumbnail_url($disco_id, 'thumbnail') ?: '') : '';
        return [
            'id'          => $id,
            'titulo'      => $cancion->post_title,
            'autor'       => get_post_meta($id, '_mp3p_autor',       true) ?: '',
            'interpretes' => get_post_meta($id, '_mp3p_interpretes', true) ?: '',
            'orden'       => intval(get_post_meta($id, '_mp3p_orden', true)),
            'disco_id'    => $disco_id,
            'disco'       => $disco_id ? get_the_title($disco_id) : '',
            'audio_url'   => $audio_url,
            'descargable' => get_post_meta($id, '_mp3p_descargable', true) === '1',
            'portada'     => $portada,
            // Datos heredados del disco (útiles en playlists, donde no hay cabecera de disco)
            'anio'        => $disco_id ? ( get_post_meta($disco_id, '_mp3p_anio',     true) ?: '' ) : '',
            'licencia'    => $disco_id ? ( get_post_meta($disco_id, '_mp3p_licencia', true) ?: '' ) : '',
            'letra'       => get_post_meta($id, '_mp3p_letra', true) ?: '',
        ];
    }

    private static function response($data) {
        $r = new WP_REST_Response($data, 200);
        $r->header('Access-Control-Allow-Origin',  '*');
        $r->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $r->header('Cache-Control',                'public, max-age=300');
        return $r;
    }
}
