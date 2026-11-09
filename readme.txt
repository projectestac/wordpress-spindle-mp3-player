=== Spindle MP3 Player ===
Contributors: juanjome
Tags: audio, mp3, player, music, playlist
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress audio player for albums, tracks and playlists, with six visual styles, an ID3-tag importer and its own REST API.

== Description ==

Spindle MP3 Player is an audio player for WordPress built as a Web Component with Shadow DOM. It lets you create albums, standalone tracks and playlists from MP3 files, choose from six visual player styles (Full, Compact, Wide, Vertical, Horizontal and Mini) and embed them on any page with a shortcode.

= Features =

* Custom content types: Album, Track and Playlist
* MP3 importer that reads ID3 metadata (title, artist, album, year, cover art) and automatically groups tracks by album
* Import option that reuses audio files already in the WordPress media library
* Own public REST API to consume player data from any website
* Six player styles: Full, Compact, Wide (minimalist) and Vertical, Horizontal, Mini (physical hardware look)
* Per-player customization of theme (light/dark/auto), accent color, background (solid/gradient/transparent) and corner radius
* Single-playback coordinator: only one player plays at a time on the same page
* Optional per-track download button

= External services =

This plugin does not connect to any external service. The **JetBrains Mono** typeface used by the "Modern Player" styles (Vertical, Horizontal and Mini) is bundled with the plugin and served from your own site.

JetBrains Mono is Copyright 2020 The JetBrains Mono Project Authors and is licensed under the SIL Open Font License 1.1 (see `public/fonts/jetbrains-mono/OFL.txt`).

== Installation ==

1. Upload the `spindle-mp3-player` folder to `/wp-content/plugins/`
2. Activate the plugin from the WordPress "Plugins" menu
3. Go to the "MP3 Player" menu to create your first album or import existing MP3 files

== Frequently Asked Questions ==

= Does the plugin connect to any external service? =

No. Audio, covers and the JetBrains Mono typeface are all served from your own site.

= Does "Allow download" protect my audio files? =

No. It only shows or hides the download button in the player. The MP3 file is a regular, public file in your media library: anyone who knows its URL can download it.

= Can the importer read lyrics, covers and other tags? =

Yes. When importing, the plugin reads the ID3 tags of each MP3: title, artist, composer, album, year, track number, embedded cover and unsynchronised lyrics (USLT or Lyrics3). If a file has no lyrics you can add them by hand in the song screen.

= What happens to my media files when I delete an album or a song? =

Only files created by the plugin are deleted: audio files uploaded with the importer and covers extracted from ID3 tags. Even then, a file is kept if you are not allowed to delete it, if another song uses it, if it is the featured image of another post, or if it appears in the content of any post or page. Files that were already in your media library are never deleted.

= What happens when I uninstall the plugin? =

The plugin options are always removed. Albums, songs and playlists are only removed if you tick "Delete data on uninstall" in the plugin settings. Media library files are always kept.

= Who can manage albums, songs and playlists? =

Any user who can edit posts (administrators, editors, authors…) can manage them from the "MP3 Player" menu. The importer and the settings page are for administrators only.

= Can I embed a player on another website? =

Yes. Use the `<mp3p-disco>`, `<mp3p-cancion>` or `<mp3p-playlist>` Web Component with the `api` attribute pointing to your site's REST API (shown in the plugin settings). Inside WordPress, the shortcodes always use your own site's API.

= How do I get all albums from the REST API? =

`/wp-json/mp3p/v1/discos` returns up to 100 albums per request. Use the `page` and `per_page` parameters (maximum 100) to paginate; the total number of albums and pages is returned in the `X-WP-Total` and `X-WP-TotalPages` headers.

== Changelog ==

= 1.8.0 =
* Borrado de archivos: el plugin marca los archivos que crea (audios subidos con el importador y portadas extraídas de las etiquetas ID3). Al borrar un disco o una canción solo se borran esos archivos, y solo si el usuario puede borrarlos, ninguna otra canción los usa, no son la imagen destacada de otra entrada y no aparecen en el contenido de ninguna entrada o página. Los archivos que ya estaban en la biblioteca no se borran nunca
* La portada del disco sigue las mismas reglas (antes se borraba siempre)
* Importar desde la biblioteca ya no cambia la entrada a la que estaba adjunto un archivo
* Los avisos de confirmación de borrado explican qué archivos se borran
* Editores y autores ven el menú "MP3 Player" y pueden gestionar discos, canciones y playlists; el importador y la Configuración siguen siendo solo para administradores
* Nuevo botón de letra con icono y texto ("Text" por defecto, configurable en Configuración); los botones de letra y descarga llevan borde redondo
* Variants Vertical y Horizontal: barra de progreso en la pantalla, bajo las barras de sonido, que también permite saltar a otro punto de la canción; la línea de posición sobre las barras usa el color de acento del player
* Nuevo uninstall.php: al borrar el plugin se eliminan sus opciones; los discos, canciones y playlists solo se eliminan si está marcada la opción "Eliminar los datos al desinstalar". Los archivos de la biblioteca se conservan
* API /discos: paginación con los parámetros page y per_page (máximo 100 por petición) y cabeceras X-WP-Total y X-WP-TotalPages. Por defecto devuelve los 100 primeros discos
* La imagen incrustada en los MP3 solo se omite en las subidas del importador; antes el filtro afectaba a todas las subidas de audio del sitio
* En la ficha de la canción, un aviso explica que "Permitir descarga" solo muestra u oculta el botón: el archivo de audio es público
* Nueva sección de preguntas frecuentes en el readme
* Traducción catalana: URL en masculino

= 1.7.1 =
* Seguridad: el reproductor escapa todos los datos que inserta en el HTML (títulos de disco y pista, autores, intérpretes, portadas, URL de audio y mensajes de error) en las seis variantes, la ventana de letra y el indicador de reproducción
* Seguridad: el color de acento, el fondo y el radio de las esquinas se validan en el reproductor y en el shortcode; solo se aceptan colores hexadecimales, degradados lineales con colores hexadecimales y números enteros
* Seguridad: las URL de portadas y audios solo se aceptan si son http(s) o relativas
* Seguridad: el shortcode usa siempre la API del propio sitio; se ignora el atributo api
* Seguridad: la dirección del degradado solo admite los valores que ofrece el editor de diseño
* La búsqueda de canciones del editor de playlists puede usarla cualquier usuario que pueda editar playlists (editores, autores…), no solo los administradores; solo devuelve canciones publicadas
* La fuente JetBrains Mono (variants Vertical, Horizontal y Mini) se sirve desde el propio plugin en lugar de Google Fonts; el plugin ya no se conecta a ningún servicio externo

= 1.7.0 =
* Nuevo: la Configuración general define los valores por defecto de variante, tema, color de acento, lista de pistas y modo de letra, con la misma interfaz que la caja de diseño de cada player
* Estos valores se copian en cada disco, canción o playlist al crearlo (también desde el importador); los players ya existentes conservan su aspecto
* El color de acento por defecto pasa a ser el naranja #e8520a, primera muestra de la paleta predefinida
* Vista previa del shortcode equivalente en la Configuración

= 1.6.1 =
* En la ficha del disco, la lista de canciones muestra una columna "Letra" (Sí / No) junto a la de descarga

= 1.6.0 =
* Nuevo: en la ficha de una canción, al elegir un MP3 de la biblioteca se leen sus etiquetas ID3 y se rellenan los campos vacíos (título, autor, intérpretes, nº de pista y disco si coincide con el álbum); la letra del archivo sustituye a la actual si el archivo la trae
* Aviso bajo el selector de archivo con los campos que se han rellenado

= 1.5.0 =
* Importar desde la biblioteca de medios ahora lee las etiquetas ID3 de cada archivo en el servidor: título, intérprete, compositor, álbum, año, número de pista, letra y portada incrustada, igual que al subir archivos
* Las pistas se agrupan por el álbum de la etiqueta y se ordenan por su número de pista
* La portada incrustada se muestra en la vista previa y se guarda como imagen destacada del disco
* El campo "Autor" de cada pista usa el compositor de la etiqueta (o el intérprete si no hay compositor)

= 1.4.0 =
* Nuevo: al importar, si el MP3 incluye la letra en sus etiquetas (ID3v2 USLT o Lyrics3) se guarda automáticamente en el campo "Letra" de la canción; funciona tanto al subir archivos como desde la biblioteca de medios
* Nuevo: columna "Letra" en la vista previa del importador que indica qué pistas traen letra antes de confirmar
* Si el archivo no trae letra, el campo queda libre para añadirla a mano como hasta ahora

= 1.3.0 =
* Nuevo: opción "Mostrar letra en" (desplegable bajo la pista o ventana modal) por player; atributo lyrics="modal" en el shortcode
* El icono de letra aparece en todas las pistas y se ve atenuado cuando la canción no tiene letra
* Vertical, Horizontal y Mini respetan el tema: Light con pantalla del ecualizador en blanco, Dark con chasis e interfaz oscuros
* La marca de los players Modern pasa de "MP3 PLAYER PRO" a "SPINDLE MP3 PLAYER"
* Traducidos los estados de la pantalla (EN PAUSA / REPRODUCIENDO) al español y al catalán

= 1.2.0 =
* Nuevo: opción "Lista de pistas" (desplegada / plegada) en todos los players, con valor global en Configuración y atributo tracklist="open|closed" en el shortcode
* Nuevo: campo "Letra" en cada canción; se muestra en un desplegable junto a la pista (todas las variantes; en Mini, botón de letra de la pista actual)
* Nuevo: opción "Ancho" del reproductor (por defecto 100% del contenedor) además de "Ancho máximo"; atributo width="…" en el shortcode
* Los "Elementos visibles" ahora se aplican en el front en todas las variantes: descripción, año y licencia por disco; autor, intérpretes, año y licencia por pista en las playlists; nuevo interruptor "Letra"
* Los shortcodes de canción y playlist ahora respetan las opciones de diseño guardadas (tema, acento, fondo, esquinas, elementos visibles)
* Fix: el radio de esquinas guardado en el disco no se aplicaba si no se escribía en el shortcode
* Fix: la descripción del disco perdía los saltos de línea al guardar
* Fix: en Vertical/Horizontal, pulsar descargar en la playlist también cambiaba de pista

= 1.1.3 =
* Fix: los archivos de traducción (es_ES, ca) incluidos en /languages no se cargaban porque faltaba la llamada a load_plugin_textdomain()

= 1.1.2 =
* Corrección de versión: la 1.1.0/1.1.1 nunca llegó a actualizar correctamente la cabecera del plugin ni este changelog (quedaban en 1.0.7 por un fallo en el proceso de publicación)

= 1.1.1 =
* Añadidas traducciones al español (es_ES) y catalán (ca)

= 1.1.0 =
* Internacionalización completa: todos los textos del admin (dashboard, importador, metaboxes, editor de playlist, configuración) ya son traducibles con el text domain spindle-mp3-player
* Añadida la plantilla de traducción languages/spindle-mp3-player.pot

= 1.0.7 =
* Widened the track-order column in the importer so two-digit numbers display fully
* Capped the equalizer/waveform screen height at 188px in the Horizontal player style so it no longer stretches with long track lists

= 1.0.6 =
* Fixed Contributors username casing (juanjome, lowercase, matching the WordPress.org profile slug exactly)

= 1.0.5 =
* Converted all remaining inline <script>/<style> blocks (11 total, across the admin pages, meta boxes, playlist editor and dashboard) to properly registered and enqueued assets via wp_register_script/wp_enqueue_script/wp_add_inline_script and their style equivalents, per WordPress.org review feedback
* Fixed Contributors username in readme.txt

= 1.0.4 =
* Fixed duplicate Plugin URI / Author URI header value (Plugin Check error)

= 1.0.3 =
* Renamed the plugin to Spindle MP3 Player
* Fixed phpcs:ignore comment scoping so it actually covers the flagged lines inside array literals (nonce check on album_id, meta_value/meta_query/post__not_in warnings)

= 1.0.2 =
* Full Plugin Check compliance pass: fixed all flagged escaping issues (esc_url, esc_js, esc_attr, intval), removed a disallowed compressed archive file, properly enqueued the ID3-reading script instead of a raw <script> tag, readme.txt rewritten in English, "Tested up to" updated

= 1.0.1 =
* Security hardening: consistent wp_unslash() on all $_POST/$_GET reads, output escaping (esc_html, esc_attr, esc_js, esc_url, intval) across the admin views, properly enqueued admin scripts, Plugin Check compliance pass

= 1.0.0 =
* First production release
* Six player styles: Full, Compact, Wide, Vertical, Horizontal, Mini
* MP3 importer with ID3 tag reading, album grouping and import from the media library
* Own REST API for albums, tracks and playlists
* Per-player theme, accent, background and corner-radius customization
* Single-playback coordinator across multiple players on the same page
* Admin dashboard, design metaboxes and importer screen
