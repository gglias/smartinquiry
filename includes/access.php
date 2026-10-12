<?php
/**
 * Access for people who work only on the requests, without the rest of WordPress.
 *
 * Two roles, given per person under Users:
 *   SmartInquiry – Requests             (smi_requests: list, status, notes, PDF, CSV, delete)
 *   SmartInquiry – Requests & settings  (+ smi_settings: every settings screen)
 * Administrators have both through user_has_cap, so nothing changes for them.
 * People with only these roles enter from home/<slug> (default "aitimata"), land on the SmartInquiry screen and
 * never see the rest of wp-admin: other admin pages send them back, the menus and the toolbar are gone.
 */
defined( 'ABSPATH' ) || exit;

const SMI_CAP_REQUESTS = 'smi_requests';
const SMI_CAP_SETTINGS = 'smi_settings';
const SMI_ROLES_REV    = '1';

function smi_roles() {
	return array(
		'smi_agent'   => array( 'SmartInquiry – Requests', array( 'read' => true, SMI_CAP_REQUESTS => true ) ),
		// upload_files: the logo picker of the PDF settings uses the Media Library.
		'smi_manager' => array( 'SmartInquiry – Requests & settings', array( 'read' => true, SMI_CAP_REQUESTS => true, SMI_CAP_SETTINGS => true, 'upload_files' => true ) ),
	);
}

/** Role names for the SmartInquiry screen, in its language (WordPress itself shows the stored English ones). */
function smi_role_labels() {
	return array(
		'smi_agent'   => __( 'SmartInquiry – Requests', 'smartinquiry' ),
		'smi_manager' => __( 'SmartInquiry – Requests & settings', 'smartinquiry' ),
	);
}

/** Creates (or refreshes) the two roles. Users keep their role across a refresh. */
function smi_install_roles() {
	foreach ( smi_roles() as $slug => $r ) {
		remove_role( $slug );
		add_role( $slug, $r[0], $r[1] );
	}
	update_option( 'smartinquiry_roles', SMI_ROLES_REV, false );
}

// An update by zip doesn't run the activation hook: the roles are put in place on the first request after it.
add_action( 'plugins_loaded', function () {
	if ( get_option( 'smartinquiry_roles' ) !== SMI_ROLES_REV ) {
		smi_install_roles();
	}
} );

// Administrators can do everything in SmartInquiry, as before.
add_filter( 'user_has_cap', function ( $allcaps ) {
	if ( ! empty( $allcaps['manage_options'] ) ) {
		$allcaps[ SMI_CAP_REQUESTS ] = true;
		$allcaps[ SMI_CAP_SETTINGS ] = true;
	}
	return $allcaps;
} );

function smi_admin_url() {
	return admin_url( 'admin.php?page=smartinquiry' );
}

/** Someone whose only business in wp-admin is SmartInquiry (no editing, no site settings). */
function smi_is_inquiry_only( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	return $user instanceof WP_User && $user->exists() && user_can( $user, SMI_CAP_REQUESTS ) && ! user_can( $user, 'edit_posts' ) && ! user_can( $user, 'manage_options' );
}

/** The entry link's last part, e.g. "aitimata"; '' turns the link off. */
function smi_access_slug( $s = null ) {
	$s = $s ? $s : smi_get_settings();
	return $s['access']['slug'];
}

function smi_access_url( $s = null ) {
	$slug = smi_access_slug( $s );
	return '' === $slug ? '' : home_url( '/' . $slug . '/' );
}

/** Words that must never be taken over by the entry link. */
function smi_clean_access_slug( $v ) {
	$slug = sanitize_title( is_scalar( $v ) ? (string) $v : '' );
	$slug = substr( $slug, 0, 60 );
	return in_array( $slug, array( 'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'xmlrpc', 'login', 'admin' ), true ) ? '' : $slug;
}

/* ---------- The entry link: home/<slug> → login → the SmartInquiry screen ---------- */

add_action( 'init', function () {
	if ( is_admin() || wp_doing_ajax() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$slug = smi_access_slug();
	if ( '' === $slug ) {
		return;
	}
	$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $base && 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	if ( strtolower( rawurldecode( trim( $path, '/' ) ) ) !== strtolower( rawurldecode( $slug ) ) ) {
		return;
	}
	nocache_headers();
	// Never in search results, even if the address is posted somewhere. (Not listed in robots.txt on purpose:
	// that file is public and would only advertise the address.)
	header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	wp_safe_redirect( is_user_logged_in() ? smi_admin_url() : wp_login_url( smi_admin_url() ) );
	exit;
}, 0 );

// The SmartInquiry screen itself, for the same reason.
add_action( 'admin_init', function () {
	if ( isset( $_GET['page'] ) && 'smartinquiry' === $_GET['page'] && ! headers_sent() ) { // phpcs:ignore WordPress.Security.NonceVerification
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	}
} );

// After signing in, people with only SmartInquiry go straight to it (not to the profile page).
add_filter( 'login_redirect', function ( $to, $requested, $user ) {
	return ( $user instanceof WP_User && smi_is_inquiry_only( $user ) && false === strpos( (string) $to, 'page=smartinquiry' ) ) ? smi_admin_url() : $to;
}, 20, 3 );

/* ---------- Nothing else of wp-admin for them ---------- */

add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! smi_is_inquiry_only() ) {
		return;
	}
	global $pagenow;
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ( 'admin.php' === $pagenow && 'smartinquiry' === $page ) || in_array( $pagenow, array( 'profile.php', 'admin-post.php', 'async-upload.php' ), true ) ) {
		return;
	}
	wp_safe_redirect( smi_admin_url() );
	exit;
}, 1 );

// "WordPress x.y is available! Please notify the site administrator" and the like are not for them.
add_action( 'admin_init', function () {
	if ( smi_is_inquiry_only() ) {
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'network_admin_notices', 'update_nag', 3 );
		remove_action( 'admin_notices', 'maintenance_nag', 10 );
	}
} );

// Pages WordPress refuses before admin_init runs ("Sorry, you are not allowed…"): back to SmartInquiry instead.
add_action( 'admin_page_access_denied', function () {
	if ( smi_is_inquiry_only() ) {
		wp_safe_redirect( smi_admin_url() );
		exit;
	}
} );

add_action( 'admin_menu', function () {
	if ( ! smi_is_inquiry_only() ) {
		return;
	}
	global $menu;
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && ! in_array( $item[2], array( 'smartinquiry', 'profile.php' ), true ) ) {
			remove_menu_page( $item[2] );
		}
	}
}, 999 );

add_action( 'admin_bar_menu', function ( $bar ) {
	if ( smi_is_inquiry_only() ) {
		foreach ( array( 'wp-logo', 'comments', 'new-content', 'updates', 'customize', 'search', 'edit' ) as $id ) {
			$bar->remove_node( $id );
		}
	}
}, 999 );

add_filter( 'show_admin_bar', function ( $show ) {
	return smi_is_inquiry_only() ? false : $show;
} );

/* ---------- What the "Access" screen shows (administrators only) ---------- */

function smi_access_data( $s ) {
	$slug  = smi_access_slug( $s );
	$users = array();
	foreach ( get_users( array( 'role__in' => array_keys( smi_roles() ), 'orderby' => 'display_name' ) ) as $u ) {
		$role    = array_values( array_intersect( (array) $u->roles, array_keys( smi_roles() ) ) );
		$users[] = array(
			'id'      => $u->ID,
			'name'    => $u->display_name,
			'email'   => $u->user_email,
			'role'    => $role ? $role[0] : '',
			'editUrl' => get_edit_user_link( $u->ID ),
		);
	}
	// A page or post already at that address would never be reachable again.
	$taken = '' !== $slug && (bool) get_posts( array( 'name' => $slug, 'post_type' => 'any', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) );
	return array(
		'home'     => home_url( '/' ),
		'users'    => $users,
		'addUrl'   => admin_url( 'user-new.php' ),
		'taken'    => $taken,
		'takenFor' => $slug,
	);
}
