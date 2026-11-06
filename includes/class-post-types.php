<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Post_Types {

    public static function register() {

        register_post_type( 'mp3p_disco', [
            'labels' => [
                'name'               => __( 'Discos', 'spindle-mp3-player' ),
                'singular_name'      => __( 'Disco', 'spindle-mp3-player' ),
                'add_new'            => __( 'Añadir disco', 'spindle-mp3-player' ),
                'add_new_item'       => __( 'Añadir nuevo disco', 'spindle-mp3-player' ),
                'edit_item'          => __( 'Editar disco', 'spindle-mp3-player' ),
                'all_items'          => __( 'Todos los discos', 'spindle-mp3-player' ),
                'search_items'       => __( 'Buscar discos', 'spindle-mp3-player' ),
                'not_found'          => __( 'No se encontraron discos', 'spindle-mp3-player' ),
                'not_found_in_trash' => __( 'No hay discos en la papelera', 'spindle-mp3-player' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'mp3p_menu',
            'show_in_rest' => false,
            'supports'     => [ 'title', 'thumbnail' ],
            'rewrite'      => false,
        ] );

        register_post_type( 'mp3p_cancion', [
            'labels' => [
                'name'               => __( 'Canciones', 'spindle-mp3-player' ),
                'singular_name'      => __( 'Canción', 'spindle-mp3-player' ),
                'add_new'            => __( 'Añadir canción', 'spindle-mp3-player' ),
                'add_new_item'       => __( 'Añadir nueva canción', 'spindle-mp3-player' ),
                'edit_item'          => __( 'Editar canción', 'spindle-mp3-player' ),
                'all_items'          => __( 'Todas las canciones', 'spindle-mp3-player' ),
                'search_items'       => __( 'Buscar canciones', 'spindle-mp3-player' ),
                'not_found'          => __( 'No se encontraron canciones', 'spindle-mp3-player' ),
                'not_found_in_trash' => __( 'No hay canciones en la papelera', 'spindle-mp3-player' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'mp3p_menu',
            'show_in_rest' => false,
            'supports'     => [ 'title' ],
            'rewrite'      => false,
        ] );

        register_post_type( 'mp3p_playlist', [
            'labels' => [
                'name'               => __( 'Playlists', 'spindle-mp3-player' ),
                'singular_name'      => __( 'Playlist', 'spindle-mp3-player' ),
                'add_new'            => __( 'Añadir playlist', 'spindle-mp3-player' ),
                'add_new_item'       => __( 'Añadir nueva playlist', 'spindle-mp3-player' ),
                'edit_item'          => __( 'Editar playlist', 'spindle-mp3-player' ),
                'all_items'          => __( 'Todas las playlists', 'spindle-mp3-player' ),
                'search_items'       => __( 'Buscar playlists', 'spindle-mp3-player' ),
                'not_found'          => __( 'No se encontraron playlists', 'spindle-mp3-player' ),
                'not_found_in_trash' => __( 'No hay playlists en la papelera', 'spindle-mp3-player' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'mp3p_menu',
            'show_in_rest' => false,
            'supports'     => [ 'title' ],
            'rewrite'      => false,
        ] );
    }
}
