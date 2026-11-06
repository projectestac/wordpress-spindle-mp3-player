<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Cascade_Delete {

    private static $processing = [];

    public static function register() {
        add_action( 'before_delete_post', [ __CLASS__, 'on_delete_disco' ],   10, 1 );
        add_action( 'before_delete_post', [ __CLASS__, 'on_delete_cancion' ], 10, 1 );

        // Evitar que WordPress extraiga la imagen incrustada del MP3
        // al subirlo a la biblioteca de medios
        add_filter( 'wp_read_audio_metadata',  [ __CLASS__, 'suppress_mp3_image' ], 10, 1 );
    }

    // ── Suprimir imagen incrustada del MP3 al subir ───────────────
    // WordPress extrae automáticamente la portada del ID3 y la sube
    // como attachment separado. Lo evitamos devolviendo los metadatos
    // sin la imagen.
    public static function suppress_mp3_image( $metadata ) {
        if ( isset( $metadata['image'] ) ) {
            unset( $metadata['image'] );
        }
        return $metadata;
    }

    // ── Al borrar un disco ────────────────────────────────────────
    public static function on_delete_disco( $post_id ) {
        if ( get_post_type( $post_id ) !== 'mp3p_disco' ) return;

        // Borrar thumbnail (portada) del disco
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            wp_delete_attachment( $thumbnail_id, true );
        }

        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- necesario: relación disco→canción almacenada en postmeta.
        $canciones = get_posts( [
            'post_type'      => 'mp3p_cancion',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ [ 'key' => '_mp3p_disco_id', 'value' => $post_id ] ],
        ] );
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

        if ( empty( $canciones ) ) return;

        // Recoger attachment IDs antes de borrar nada
        $attachments = [];
        foreach ( $canciones as $cancion_id ) {
            $att_id = intval( get_post_meta( $cancion_id, '_mp3p_attachment_id', true ) );
            if ( $att_id ) $attachments[ $cancion_id ] = $att_id;
            self::$processing[] = $cancion_id;
        }

        // Borrar canciones
        foreach ( $canciones as $cancion_id ) {
            wp_delete_post( $cancion_id, true );
        }

        // Borrar attachments de audio huérfanos
        foreach ( $attachments as $cancion_id => $att_id ) {
            self::delete_attachment_if_orphan( $att_id, $canciones );
        }

        self::$processing = [];
    }

    // ── Al borrar una canción suelta ──────────────────────────────
    public static function on_delete_cancion( $post_id ) {
        if ( get_post_type( $post_id ) !== 'mp3p_cancion' ) return;
        if ( in_array( $post_id, self::$processing ) ) return;

        $att_id = intval( get_post_meta( $post_id, '_mp3p_attachment_id', true ) );
        if ( $att_id ) {
            self::delete_attachment_if_orphan( $att_id, [ $post_id ] );
        }
    }

    // ── Borrar attachment si ninguna otra canción lo usa ──────────
    private static function delete_attachment_if_orphan( $att_id, $exclude_ids ) {
        // phpcs:disable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in, WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- lista de exclusión siempre pequeña (canciones del mismo disco en borrado en cascada); necesario comprobar si el archivo de audio lo usa otra canción antes de borrarlo.
        $otras = get_posts( [
            'post_type'      => 'mp3p_cancion',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post__not_in'   => $exclude_ids,
            'meta_query'     => [ [
                'key'   => '_mp3p_attachment_id',
                'value' => $att_id,
            ] ],
        ] );
        // phpcs:enable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in, WordPress.DB.SlowDBQuery.slow_db_query_meta_query

        if ( empty( $otras ) ) {
            wp_delete_attachment( $att_id, true );
        }
    }
}
