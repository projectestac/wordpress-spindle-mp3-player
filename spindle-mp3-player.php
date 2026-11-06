<?php
/**
 * Plugin Name: Spindle MP3 Player
 * Description: Audio player for albums, tracks and playlists. Web Component with Shadow DOM, own REST API, ID3-tag importer. Works on any site.
 * Version:     1.7.1
 * Author:      dseny
 * Author URI:  https://www.dseny.es
 * Text Domain: spindle-mp3-player
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MP3P_VERSION', '1.7.1' );
define( 'MP3P_DIR',     plugin_dir_path( __FILE__ ) );
define( 'MP3P_URL',     plugin_dir_url( __FILE__ ) );

require_once MP3P_DIR . 'includes/class-post-types.php';
require_once MP3P_DIR . 'includes/class-meta-boxes.php';
require_once MP3P_DIR . 'includes/class-playlist-meta.php';
require_once MP3P_DIR . 'includes/class-api.php';
require_once MP3P_DIR . 'includes/class-shortcodes.php';
require_once MP3P_DIR . 'includes/class-assets.php';
require_once MP3P_DIR . 'includes/class-admin-pages.php';
require_once MP3P_DIR . 'includes/class-settings.php';
require_once MP3P_DIR . 'includes/class-importer.php';
require_once MP3P_DIR . 'includes/class-cascade-delete.php';

// Cargar las traducciones empaquetadas en /languages antes que cualquier otro
// hook 'init' que ya use __()/_e(), de ahí la prioridad 1.
add_action( 'init', function() {
    // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- el plugin incluye sus propias traducciones (es_ES, ca) en /languages, que WordPress no carga automáticamente.
    load_plugin_textdomain(
        'spindle-mp3-player',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}, 1 );

add_action( 'init',          [ 'MP3P_Post_Types',     'register' ] );
add_action( 'init',          [ 'MP3P_Shortcodes',     'register' ] );
add_action( 'init',          [ 'MP3P_Assets',         'register' ] );
add_action( 'init',          [ 'MP3P_Admin_Pages',    'register' ] );
add_action( 'init',          [ 'MP3P_Meta_Boxes',     'register' ] );
add_action( 'init',          [ 'MP3P_Playlist_Meta',  'register' ] );
add_action( 'init',          [ 'MP3P_Settings',       'register' ] );
add_action( 'init',          [ 'MP3P_Importer',       'register' ] );
add_action( 'init',          [ 'MP3P_Cascade_Delete', 'register' ] );
add_action( 'rest_api_init', [ 'MP3P_API',            'register' ] );

register_activation_hook( __FILE__, function() {
    MP3P_Post_Types::register();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
} );
