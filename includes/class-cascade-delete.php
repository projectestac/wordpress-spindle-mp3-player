<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MP3P_Cascade_Delete {

    /**
     * Meta que marca los archivos (audio y portadas) creados por el propio plugin, con el ID del disco o canción
     * para el que se crearon. Solo estos archivos se pueden borrar en cascada: los que ya estaban en la biblioteca
     * de medios (importados desde la biblioteca o elegidos en la ficha) nunca se borran.
     */
    const CREATED_META = '_mp3p_created';

    private static $processing = [];

    public static function register() {
        add_action( 'before_delete_post', [ __CLASS__, 'on_delete_disco' ],   10, 1 );
        add_action( 'before_delete_post', [ __CLASS__, 'on_delete_cancion' ], 10, 1 );
    }

    // ── Marcar un archivo como creado por el plugin ──────────────
    public static function mark_created( $att_id, $owner_id ) {
        if ( $att_id ) {
            update_post_meta( $att_id, self::CREATED_META, (int) $owner_id );
        }
    }

    // ── Suprimir imagen incrustada del MP3 al subir ───────────────
    // WordPress extrae automáticamente la portada del ID3 y la sube
    // como attachment separado. Lo evitamos devolviendo los metadatos
    // sin la imagen. Solo se aplica a las subidas del importador (que
    // guarda la portada él mismo), no a las del resto del sitio.
    public static function suppress_mp3_image( $metadata ) {
        if ( isset( $metadata['image'] ) ) {
            unset( $metadata['image'] );
        }
        return $metadata;
    }

    // ── Al borrar un disco ────────────────────────────────────────
    public static function on_delete_disco( $post_id ) {
        if ( get_post_type( $post_id ) !== 'mp3p_disco' ) return;

        // Portada del disco: solo si la creó el plugin y nadie más la usa
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            self::delete_attachment_if_unused( $thumbnail_id, [], [ $post_id ] );
        }

        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- necesario: relación disco→canción almacenada en postmeta.
        $canciones = get_posts( [
            'post_type'      => 'mp3p_cancion',
            'post_status'    => 'any',
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

        // Borrar los archivos de audio que creó el plugin y que ya no se usan
        foreach ( array_unique( $attachments ) as $att_id ) {
            self::delete_attachment_if_unused( $att_id, $canciones, [ $post_id ] );
        }

        self::$processing = [];
    }

    // ── Al borrar una canción suelta ──────────────────────────────
    public static function on_delete_cancion( $post_id ) {
        if ( get_post_type( $post_id ) !== 'mp3p_cancion' ) return;
        if ( in_array( $post_id, self::$processing ) ) return;

        $att_id = intval( get_post_meta( $post_id, '_mp3p_attachment_id', true ) );
        if ( $att_id ) {
            self::delete_attachment_if_unused( $att_id, [ $post_id ], [ $post_id ] );
        }
    }

    /**
     * Borra un archivo solo si se cumplen todas las condiciones. Si falla alguna, se conserva.
     *  1. Lo creó el plugin (meta CREATED_META): nunca se borran archivos que ya estaban en la biblioteca.
     *  2. El usuario actual puede borrarlo.
     *  3. Ninguna otra canción lo usa como audio.
     *  4. Ninguna otra entrada lo usa como imagen destacada.
     *  5. No aparece en el contenido de ninguna entrada o página (bloques, enlaces, imágenes de cualquier tamaño).
     *
     * @param int   $att_id             Archivo.
     * @param int[] $exclude_songs      Canciones que se están borrando (no cuentan como uso).
     * @param int[] $exclude_thumb_posts Posts que se están borrando (su imagen destacada no cuenta como uso).
     */
    private static function delete_attachment_if_unused( $att_id, $exclude_songs, $exclude_thumb_posts ) {
        global $wpdb;

        $att_id = (int) $att_id;
        if ( ! $att_id || get_post_type( $att_id ) !== 'attachment' ) return;

        if ( ! get_post_meta( $att_id, self::CREATED_META, true ) ) return;

        if ( ! current_user_can( 'delete_post', $att_id ) ) return;

        // phpcs:disable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in, WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- lista de exclusión siempre pequeña; necesario comprobar si otra canción usa el archivo.
        $otras = get_posts( [
            'post_type'      => 'mp3p_cancion',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'post__not_in'   => array_map( 'intval', $exclude_songs ),
            'meta_query'     => [ [
                'key'   => '_mp3p_attachment_id',
                'value' => $att_id,
            ] ],
        ] );
        // phpcs:enable WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        if ( ! empty( $otras ) ) return;

        // Imagen destacada de otra entrada (cualquier tipo de post)
        $exclude_thumb_posts = array_map( 'intval', $exclude_thumb_posts ) ?: [ 0 ];
        $placeholders        = implode( ',', array_fill( 0, count( $exclude_thumb_posts ), '%d' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- consulta puntual al borrar; los placeholders se generan arriba.
        $thumb_use = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = %d AND post_id NOT IN ($placeholders) LIMIT 1",
            array_merge( [ $att_id ], $exclude_thumb_posts )
        ) );
        if ( $thumb_use ) return;

        // Referencia en el contenido: se busca el nombre del archivo sin extensión, que también encuentra las
        // versiones redimensionadas de las imágenes (nombre-150x150.jpg), y el ID en bloques y clases (wp-image-ID).
        $file = get_attached_file( $att_id );
        $name = $file ? pathinfo( $file, PATHINFO_FILENAME ) : '';
        $likes = [ '%wp-image-' . $att_id . '"%', '%wp-image-' . $att_id . ' %', '%"id":' . $att_id . '}%', '%"id":' . $att_id . ',%' ];
        if ( $name !== '' ) $likes[] = '%' . $wpdb->esc_like( $name ) . '%';
        $where = implode( ' OR ', array_fill( 0, count( $likes ), 'post_content LIKE %s' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- consulta puntual al borrar; los placeholders se generan arriba.
        $content_use = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type NOT IN ('revision', 'attachment') AND ($where) LIMIT 1",
            $likes
        ) );
        if ( $content_use ) return;

        wp_delete_attachment( $att_id, true );
    }
}
