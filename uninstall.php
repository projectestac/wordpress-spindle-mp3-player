<?php
/**
 * Desinstalación de Spindle MP3 Player.
 *
 * Siempre: elimina las opciones del plugin.
 * Solo si está marcada «Eliminar los datos al desinstalar» en Configuración:
 * elimina también los discos, canciones y playlists (y sus metadatos).
 * Los archivos de la biblioteca de medios se conservan siempre.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

function mp3p_uninstall_site() {
    if ( get_option( 'mp3p_delete_data_on_uninstall', '0' ) === '1' ) {
        $mp3p_ids = get_posts( [
            'post_type'      => [ 'mp3p_disco', 'mp3p_cancion', 'mp3p_playlist' ],
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        // El borrado en cascada del plugin ya no está activo aquí: wp_delete_post()
        // solo borra la entrada y sus metadatos; los adjuntos no se tocan.
        foreach ( $mp3p_ids as $mp3p_id ) {
            wp_delete_post( $mp3p_id, true );
        }
    }

    foreach ( [
        'mp3p_player_variant', 'mp3p_player_theme', 'mp3p_player_accent', 'mp3p_tracklist',
        'mp3p_lyrics', 'mp3p_lyrics_label', 'mp3p_defaults_migrated', 'mp3p_delete_data_on_uninstall',
    ] as $mp3p_opt ) {
        delete_option( $mp3p_opt );
    }

    // Marcas internas en los adjuntos (los archivos se conservan)
    delete_post_meta_by_key( '_mp3p_created' );
}

function mp3p_uninstall() {
    if ( ! is_multisite() ) {
        mp3p_uninstall_site();
        return;
    }
    foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $mp3p_site_id ) {
        switch_to_blog( $mp3p_site_id );
        mp3p_uninstall_site();
        restore_current_blog();
    }
}

mp3p_uninstall();
