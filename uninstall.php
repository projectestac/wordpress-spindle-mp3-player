<?php
/**
 * Desinstalación del plugin (al borrarlo desde la pantalla de plugins).
 *
 * Borra los datos propios del plugin: sus opciones y los discos, canciones y playlists con sus metadatos.
 * Los archivos de la biblioteca de medios (audio y portadas) NO se borran: pueden usarse en otras entradas o
 * páginas. Al desinstalar, el plugin no está cargado, así que el borrado en cascada no se ejecuta; solo se quita
 * la marca _mp3p_created de los archivos.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$mp3p_options = [
    'mp3p_player_variant',
    'mp3p_player_theme',
    'mp3p_player_accent',
    'mp3p_tracklist',
    'mp3p_lyrics',
    'mp3p_defaults_migrated',
];
foreach ( $mp3p_options as $mp3p_option ) {
    delete_option( $mp3p_option );
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- desinstalación: se necesitan todos los posts de los tres tipos, sea cual sea su estado.
$mp3p_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type IN ('mp3p_disco', 'mp3p_cancion', 'mp3p_playlist')" );
foreach ( $mp3p_ids as $mp3p_id ) {
    wp_delete_post( (int) $mp3p_id, true );
}

delete_post_meta_by_key( '_mp3p_created' );
