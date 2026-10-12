<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Leave nothing behind (on every site of a multisite network): the requests table, the settings,
// the scheduled clean-up, the anti-spam transients and every user's EN/ΕΛ choice.
function smi_uninstall_site() {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'smartinquiry_requests' ); // phpcs:ignore WordPress.DB
	delete_option( 'smartinquiry_settings' );
	delete_option( 'smartinquiry_db' );
	delete_option( 'smartinquiry_settings_rev' );
	delete_option( 'smartinquiry_roles' );
	// People who had only SmartInquiry access keep an account, as plain subscribers; the roles go.
	foreach ( array( 'smi_agent', 'smi_manager' ) as $role ) {
		foreach ( get_users( array( 'role' => $role, 'fields' => 'ID' ) ) as $uid ) {
			$u = new WP_User( $uid );
			$u->remove_role( $role );
			if ( ! $u->roles ) {
				$u->set_role( 'subscriber' );
			}
		}
		remove_role( $role );
	}
	wp_clear_scheduled_hook( 'smi_daily' );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_smi\_%' OR option_name LIKE '\_transient\_timeout\_smi\_%'" ); // phpcs:ignore WordPress.DB
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( $site_id );
		smi_uninstall_site();
		restore_current_blog();
	}
} else {
	smi_uninstall_site();
}
delete_metadata( 'user', 0, 'smi_ui_lang', '', true );
delete_metadata( 'user', 0, 'smi_desk', '', true );
