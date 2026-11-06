=== Spindle MP3 Player ===
Contributors: juanjome
Tags: audio, mp3, player, music, playlist
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.4
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

This plugin doesn't connect to any external service. The **JetBrains Mono** typeface, used by the "Modern Player" styles (Vertical, Horizontal and Mini), is bundled with the plugin (`public/fonts/jetbrains-mono`, from the npm package `@fontsource-variable/jetbrains-mono` 5.3.0) and is licensed under the SIL Open Font License 1.1 (`public/fonts/jetbrains-mono/OFL.txt`).

== Installation ==

1. Upload the `spindle-mp3-player` folder to `/wp-content/plugins/`
2. Activate the plugin from the WordPress "Plugins" menu
3. Go to the "MP3 Player" menu to create your first album or import existing MP3 files

== Changelog ==

= 1.7.4 =
* La tipografía JetBrains Mono (variantes Vertical, Horizontal y Mini) se sirve desde el propio plugin en lugar de Google Fonts: el plugin ya no se conecta a ningún servicio externo (@fontsource-variable/jetbrains-mono 5.3.0, subconjuntos latin y latin-ext, licencia SIL OFL 1.1)

= 1.7.3 =
* La búsqueda de canciones del editor de playlists la pueden usar todos los usuarios que pueden editar playlists (antes solo los administradores, aunque editores y autores también pueden crear playlists). Solo devuelve canciones publicadas

= 1.7.2 =
* Al borrar un disco o una canción solo se borran los archivos (audio y portada) creados por el propio plugin, y solo si el usuario puede borrarlos, ninguna otra canción los usa, no son la imagen destacada de otra entrada y no aparecen en el contenido de ninguna entrada o página. Los archivos que ya estaban en la biblioteca de medios nunca se borran
* La portada del disco ya no se borra siempre: sigue las mismas reglas que el audio
* Los archivos creados antes de esta versión no se borran en cascada (no tienen la marca de archivo creado por el plugin)
* Importar desde la biblioteca ya no cambia la entrada a la que estaba adjunto un archivo

= 1.7.1 =
* Seguridad: el player escapa todos los datos que inserta en el HTML (títulos, autores, intérpretes, portadas, URL de audio y mensajes de error), en todas las variantes, en el modal de letra y en el indicador de reproducción. Un título, una etiqueta ID3 o una respuesta de API manipulados ya no pueden ejecutar código en la página
* Seguridad: el color de acento, el fondo y el radio se validan (solo colores hexadecimales, degradados lineales con colores hexadecimales y enteros), tanto en el shortcode como en el player, para no inyectar CSS ni HTML
* Seguridad: las URL de portada y audio solo se aceptan si son http(s) o relativas
* Seguridad: el shortcode usa siempre la API del propio sitio (se ignora el atributo api)
* La dirección del degradado solo admite los valores del editor de diseño

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
