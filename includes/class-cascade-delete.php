<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Borrado en cascada de discos y canciones.
 *
 * Solo se borran de la biblioteca de medios los archivos que creó el propio plugin
 * (audios subidos con el importador y portadas extraídas de las etiquetas ID3), y
 * solo si el usuario puede borrarlos y no se usan en ningún otro sitio. Los archivos
 * que ya estaban en la biblioteca no se borran nunca.
 */
class MP3P_Cascade_Delete {

    const CREATED_META = '_mp3p_created';

    private static $processing = [];

    public static function register() {
        add_action( 'before_delete_post', [ __CLASS__, 'on_delete_disco' ],   10, 1 );
        add_action( 'before_delete_post', [ __CLASS__, 'on_delete_cancion' ], 10, 1 );
    }

    /** Marca un adjunto como creado por el plugin. */
    public static function mark_created( $att_id ) {
        if ( $att_id ) update_post_meta( $att_id, self::CREATED_META, '1' );
    }

    // ── Suprimir imagen incrustada del MP3 (solo durante las subidas del importador) ──
    public static function suppress_mp3_image( $metadata ) {
        if ( isset( $metadata['image'] ) ) {
            unset( $metadata['image'] );
        }
        return $metadata;
    }

    // ── Al borrar un disco ────────────────────────────────────────
    public static function on_delete_disco( $post_id ) {
        if ( get_post_type( $post_id ) !== 'mp3p_disco' ) return;

        $thumbnail_id = get_post_thumbnail_id( $post_id );

        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- necesario: relación disco→canción almacenada en postmeta.
        $canciones = get_posts( [
            'post_type'      => 'mp3p_cancion',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ [ 'key' => '_mp3p_disco_id', 'value' => $post_id ] ],
        ] );
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

        // Recoger los adjuntos antes de borrar nada
        $attachments = [];
        foreach ( $canciones as $cancion_id ) {
            $att_id = intval( get_post_meta( $cancion_id, '_mp3p_attachment_id', true ) );
            if ( $att_id ) $attachments[] = $att_id;
            self::$processing[] = $cancion_id;
        }

        foreach ( $canciones as $cancion_id ) {
            wp_delete_post( $cancion_id, true );
        }

        $exclude = array_merge( [ $post_id ], $canciones );
        foreach ( array_unique( $attachments ) as $att_id ) {
            self::maybe_delete_attachment( $att_id, $exclude );
        }
        if ( $thumbnail_id ) {
            self::maybe_delete_attachment( $thumbnail_id, $exclude );
        }

        self::$processing = [];
    }

    // ── Al borrar una canción suelta ──────────────────────────────
    public static function on_delete_cancion( $post_id ) {
        if ( get_post_type( $post_id ) !== 'mp3p_cancion' ) return;
        if ( in_array( $post_id, self::$processing, true ) ) return;

        $att_id = intval( get_post_meta( $post_id, '_mp3p_attachment_id', true ) );
        if ( $att_id ) {
            self::maybe_delete_attachment( $att_id, [ $post_id ] );
        }
    }

    /**
     * Borra el adjunto solo si: lo creó el plugin, el usuario puede borrarlo,
     * ninguna otra canción lo usa, no es la imagen destacada de otra entrada
     * y no aparece en el contenido de ninguna entrada o página.
     *
     * @param int   $att_id      Adjunto.
     * @param int[] $exclude_ids Entradas que se están borrando (no cuentan como uso).
     * @return bool true si se borró.
     */
    public static function maybe_delete_attachment( $att_id, $exclude_ids = [] ) {
        $att_id      = intval( $att_id );
        $exclude_ids = array_map( 'intval', (array) $exclude_ids );

        if ( ! $att_id || get_post_type( $att_id ) !== 'attachment' ) return false;
        if ( get_post_meta( $att_id, self::CREATED_META, true ) !== '1' ) return false;
        if ( ! current_user_can( 'delete_post', $att_id ) ) return false;
        if ( self::is_used_elsewhere( $att_id, $exclude_ids ) ) return false;

        return (bool) wp_delete_attachment( $att_id, true );
    }

    private static function is_used_elsewhere( $att_id, $exclude_ids ) {
        $exclude_ids = array_values( array_filter( array_map( 'intval', (array) $exclude_ids ) ) );
        $types       = array_values( array_diff( get_post_types(), [ 'revision', 'attachment', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'user_request' ] ) );

        // 1) Otra canción usa el mismo audio · 2) Imagen destacada de otra entrada
        // phpcs:disable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in, WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- comprobación puntual al borrar; la lista de exclusión es pequeña.
        $meta_use = new WP_Query( [
            'post_type'              => $types,
            'post_status'            => 'any',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'post__not_in'           => $exclude_ids,
            'meta_query'             => [
                'relation' => 'OR',
                [ 'key' => '_mp3p_attachment_id', 'value' => (string) $att_id ],
                [ 'key' => '_thumbnail_id',       'value' => (string) $att_id ],
            ],
        ] );
        if ( $meta_use->posts ) return true;

        // 3) Aparece en el contenido de alguna entrada o página (ruta del archivo o clase wp-image-ID)
        $needles = [ 'wp-image-' . $att_id . '"', 'wp-image-' . $att_id . ' ', '"id":' . $att_id . '}', '"id":' . $att_id . ',' ];
        $file    = get_post_meta( $att_id, '_wp_attached_file', true );
        if ( $file ) {
            $needles[] = preg_replace( '/\.[a-z0-9]+$/i', '', $file ); // sin extensión: cubre también los tamaños intermedios
        }
        foreach ( $needles as $needle ) {
            $content_use = new WP_Query( [
                'post_type'              => $types,
                'post_status'            => 'any',
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'post__not_in'           => $exclude_ids,
                's'                      => $needle,
                'sentence'               => true,
                'search_columns'         => [ 'post_content' ],
            ] );
            if ( $content_use->posts ) return true;
        }
        // phpcs:enable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in, WordPress.DB.SlowDBQuery.slow_db_query_meta_query

        return false;
    }
}
