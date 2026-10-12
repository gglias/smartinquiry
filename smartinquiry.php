<?php
/**
 * Plugin Name:       SmartInquiry
 * Description:       Step-by-step quote request form in a popup. Visitors pick a service, answer a few questions and download their request as a PDF; you get every request in the admin and by email. WPML ready.
 * Version:           1.3.0
 * Author:            GG
 * Author URI:        https://gglias.github.io
 * Plugin URI:        https://github.com/gglias/smartinquiry
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smartinquiry
 * Domain Path:       /languages
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Update URI:        https://github.com/gglias/smartinquiry
 */

defined( 'ABSPATH' ) || exit;

define( 'SMI_VERSION', '1.3.0' );
define( 'SMI_DB_VERSION', '1' );
define( 'SMI_FILE', __FILE__ );
define( 'SMI_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMI_URL', plugin_dir_url( __FILE__ ) );
define( 'SMI_OPTION', 'smartinquiry_settings' );

require_once SMI_DIR . 'includes/languages.php';
require_once SMI_DIR . 'includes/defaults.php';
require_once SMI_DIR . 'includes/settings.php';
require_once SMI_DIR . 'includes/store.php';
require_once SMI_DIR . 'includes/pdf.php';
require_once SMI_DIR . 'includes/submit.php';
require_once SMI_DIR . 'includes/turnstile.php';
require_once SMI_DIR . 'includes/access.php';
require_once SMI_DIR . 'includes/front.php';
if ( is_admin() ) {
	require_once SMI_DIR . 'includes/admin.php';
}

add_action( 'init', function () {
	load_plugin_textdomain( 'smartinquiry', false, dirname( plugin_basename( SMI_FILE ) ) . '/languages' );
} );

register_activation_hook( __FILE__, function () {
	smi_install_table();
	smi_install_roles();
	if ( ! wp_next_scheduled( 'smi_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'smi_daily' );
	}
} );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'smi_daily' );
} );

// Table upgrades after a zip update (activation hooks don't run on updates).
add_action( 'plugins_loaded', function () {
	if ( get_option( 'smartinquiry_db' ) !== SMI_DB_VERSION ) {
		smi_install_table();
	}
	smi_migrate_settings();
} );

// The daily clean-up is scheduled on activation; if it ever goes missing (e.g. network activation), it is put back.
add_action( 'admin_init', function () {
	if ( ! wp_next_scheduled( 'smi_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'smi_daily' );
	}
} );

/*
 * SmartInquiry is updated only by uploading a new zip. The "Update URI" header already tells wordpress.org not to
 * offer updates for it; this makes sure no update offered from anywhere else can replace this code either.
 */
add_filter( 'site_transient_update_plugins', function ( $updates ) {
	$file = plugin_basename( SMI_FILE );
	if ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) {
		unset( $updates->response[ $file ] );
	}
	return $updates;
} );
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	return ( is_object( $item ) && isset( $item->plugin ) && plugin_basename( SMI_FILE ) === $item->plugin ) ? false : $update;
}, 10, 2 );
