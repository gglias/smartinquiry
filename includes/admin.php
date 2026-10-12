<?php
/**
 * Admin: one page (Requests + the settings screens), built by assets/admin.js from the data printed below.
 * Every AJAX action checks the nonce and a SmartInquiry capability (smi_requests, or smi_settings for settings).
 */
defined( 'ABSPATH' ) || exit;

require_once SMI_DIR . 'includes/admin-i18n.php';

const SMI_PAGE_HOOK = 'toplevel_page_smartinquiry';
const SMI_PER_PAGE  = 20;

/* ---------- Screen language (per user, independent of the WordPress language) ---------- */

function smi_ui_lang_pref() {
	$p = get_user_meta( get_current_user_id(), 'smi_ui_lang', true );
	return in_array( $p, array( 'en', 'el' ), true ) ? $p : '';
}

function smi_ui_lang() {
	$p = smi_ui_lang_pref();
	return $p ? $p : ( 0 === strpos( get_user_locale(), 'el' ) ? 'el' : 'en' );
}

add_action( 'init', function () {
	$pref = smi_ui_lang_pref();
	if ( ! $pref ) {
		return;
	}
	unload_textdomain( 'smartinquiry' ); // not reloadable, so English really stays English
	if ( 'el' === $pref ) {
		load_textdomain( 'smartinquiry', SMI_DIR . 'languages/smartinquiry-el.mo' );
	}
}, 20 );

/* ---------- Menu ---------- */

/** The Smartup "S" emblem, filled black so WordPress can recolour it like the other menu icons. */
function smi_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-3 -1 31 33"><g fill="black"><rect x="6.26" width="18.74" height="6.21"/><rect y="6.21" width="6.26" height="6.21"/><rect x="6.26" y="12.37" width="12.48" height="6.21"/><rect x="18.74" y="18.58" width="6.26" height="6.21"/><rect y="24.79" width="18.74" height="6.21"/></g></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore
}

function smi_new_count() {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . smi_table() . ' WHERE status = %s', 'new' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

add_action( 'admin_menu', function () {
	$n     = smi_new_count();
	$badge = $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . number_format_i18n( $n ) . '</span></span>' : '';
	add_menu_page( 'SmartInquiry', 'SmartInquiry' . $badge, SMI_CAP_REQUESTS, 'smartinquiry', 'smi_render_admin_page', smi_menu_icon(), 58 );
} );

add_filter( 'plugin_action_links_' . plugin_basename( SMI_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=smartinquiry' ) ) . '">' . esc_html__( 'Requests', 'smartinquiry' ) . '</a>' );
	return $links;
} );

add_filter( 'admin_body_class', function ( $classes ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	// smi-only: someone with SmartInquiry access only; the WordPress menu and toolbar are hidden around the screen.
	return ( $screen && SMI_PAGE_HOOK === $screen->id ) ? $classes . ' smi-page' . ( smi_is_inquiry_only() ? ' smi-only' : '' ) : $classes;
} );

function smi_render_admin_page() {
	if ( ! current_user_can( SMI_CAP_REQUESTS ) ) {
		return;
	}
	echo '<div class="wrap smi-wrap"><h1 class="screen-reader-text">SmartInquiry</h1><div id="smi-admin" class="smi-admin"><noscript>' . esc_html__( 'SmartInquiry needs JavaScript.', 'smartinquiry' ) . '</noscript></div></div>';
}

/* ---------- Assets and data for the screen ---------- */

/** Which mail plugin will deliver WordPress emails, if any. */
function smi_mailer_status() {
	$known = array(
		'FluentSMTP'    => defined( 'FLUENTMAIL' ),
		'WP Mail SMTP'  => defined( 'WPMS_PLUGIN_VER' ),
		'Post SMTP'     => defined( 'POST_SMTP_VER' ),
		'Easy WP SMTP'  => defined( 'EasyWPSMTP_PLUGIN_VERSION' ) || class_exists( 'EasyWPSMTP' ),
		'SMTP Mailer'   => class_exists( 'SMTP_MAILER' ),
		'Brevo'         => class_exists( 'SIB_Manager' ),
	);
	foreach ( $known as $name => $on ) {
		if ( $on ) {
			return $name;
		}
	}
	return has_action( 'phpmailer_init' ) ? 'custom' : '';
}

function smi_pages_list() {
	$out   = array();
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) );
	foreach ( $pages as $p ) {
		$out[] = array( 'id' => $p->ID, 'title' => '' !== $p->post_title ? $p->post_title : '#' . $p->ID );
	}
	return $out;
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( SMI_PAGE_HOOK !== $hook ) {
		return;
	}
	$s    = smi_get_settings();
	$langs = array();
	foreach ( smi_text_langs() as $code ) {
		$all            = smi_languages();
		$names          = array( 'el' => 'Ελληνικά', 'en' => 'English' );
		$langs[]        = array( 'code' => $code, 'name' => isset( $all[ $code ] ) ? $all[ $code ]['name'] : ( isset( $names[ $code ] ) ? $names[ $code ] : strtoupper( $code ) ) );
	}

	wp_enqueue_media();
	wp_enqueue_style( 'smartinquiry-admin', SMI_URL . 'assets/admin.css', array(), SMI_VERSION );
	wp_enqueue_script( 'smartinquiry-pdf', SMI_URL . 'assets/pdf.js', array(), SMI_VERSION, true );
	wp_enqueue_script( 'smartinquiry-admin', SMI_URL . 'assets/admin.js', array( 'smartinquiry-pdf' ), SMI_VERSION, true );

	$boot = array(
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'exportUrl'  => admin_url( 'admin-post.php' ),
		'nonce'      => wp_create_nonce( 'smi_admin' ),
		'uiLang'     => smi_ui_lang(),
		'langs'      => $langs,
		'siteLang'   => smi_default_lang(),
		'today'      => current_time( 'Y-m-d' ),
		'yesterday'  => wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ),
		'settings'   => smi_settings_for_admin( $s ),
		'turnstile'  => smi_turnstile_status(),
		'defaults'   => array( 'texts' => smi_default_texts(), 'float' => smi_defaults()['float'] ),
		'icons'      => SMI_ICONS,
		'logoUrl'    => smi_logo_url( $s, 'medium' ),
		'logoDefault' => SMI_URL . 'assets/logo-smartup.png',
		'pages'      => smi_pages_list(),
		'wpPrivacy'  => (int) get_option( 'wp_page_for_privacy_policy' ),
		'mailer'     => smi_mailer_status(),
		'serverPdf'  => smi_pdf_available(),
		'adminEmail' => get_option( 'admin_email' ),
		'openId'     => isset( $_GET['request'] ) ? absint( $_GET['request'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
		'perPage'    => SMI_PER_PAGE,
		'user'       => wp_get_current_user()->display_name,
		// What this person may open: settings screens, and the Access screen (people and the entry link).
		'can'        => array(
			'settings' => current_user_can( SMI_CAP_SETTINGS ),
			'access'   => current_user_can( 'manage_options' ) && current_user_can( 'list_users' ),
		),
		'only'       => smi_is_inquiry_only(),
		'myRole'     => (string) current( array_intersect( (array) wp_get_current_user()->roles, array_keys( smi_roles() ) ) ),
		'roleNames'  => smi_role_labels(),
		'access'     => ( current_user_can( 'manage_options' ) && current_user_can( 'list_users' ) ) ? smi_access_data( $s ) : null,
		'profileUrl' => admin_url( 'profile.php' ),
		'desk'       => smi_desk_pref(),
		'soundsUrl'  => SMI_URL . 'assets/sounds/',
		'logoutUrl'  => wp_logout_url( '' !== smi_access_url( $s ) ? smi_access_url( $s ) : home_url( '/' ) ),
		'i18n'       => smi_admin_i18n(),
	);
	// Printed ourselves with < > & ' " hex-encoded, so texts written in the admin can never close the script tag.
	wp_add_inline_script( 'smartinquiry-admin', smi_json_script( 'smiAdmin', $boot ), 'before' );
} );

/* ---------- AJAX ---------- */

function smi_ajax_guard( $cap = SMI_CAP_REQUESTS ) {
	check_ajax_referer( 'smi_admin', 'nonce' );
	if ( ! current_user_can( $cap ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'smartinquiry' ) ), 403 );
	}
}

function smi_post( $key ) {
	return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security
}

/** Filters of the request list, from the AJAX call or the export form. */
function smi_list_args( $src ) {
	$status  = smi_str( $src, 'status' );
	$service = sanitize_key( smi_str( $src, 'service' ) );
	$lang    = sanitize_key( smi_str( $src, 'lang' ) );
	return array(
		'status'  => in_array( $status, SMI_STATUSES, true ) ? $status : '',
		'service' => $service,
		'lang'    => $lang,
		'q'       => sanitize_text_field( smi_limit( smi_str( $src, 'q' ), 100 ) ),
	);
}

/** The budget chosen (text of the language asked for), read from the snapshot. */
function smi_row_budget( array $r, $lang ) {
	$snap = smi_snapshot_lang( $r, $lang );
	foreach ( isset( $snap['sections'] ) ? (array) $snap['sections'] : array() as $sec ) {
		if ( isset( $sec['key'] ) && 'plan' === $sec['key'] && isset( $sec['rows'][1][1] ) ) {
			return (string) $sec['rows'][1][1];
		}
	}
	return '';
}

function smi_row_for_list( array $r ) {
	$lang = smi_ui_lang();
	$snap = smi_snapshot_lang( $r, $lang );
	return array(
		'id'      => $r['id'],
		'ref'     => $r['ref'],
		'date'    => get_date_from_gmt( $r['created_at'], 'Y-m-d H:i' ),
		'service' => $r['service'],
		'svcName' => isset( $snap['service'] ) ? $snap['service'] : $r['service'],
		'lang'    => $r['lang'],
		'status'  => $r['status'],
		'isRead'  => $r['is_read'],
		'name'    => $r['name'],
		'email'   => $r['email'],
		'company' => $r['company'],
		'budget'  => smi_row_budget( $r, $lang ),
	);
}

add_action( 'wp_ajax_smi_list', function () {
	smi_ajax_guard();
	$args = smi_list_args( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
	$page = max( 1, absint( smi_post( 'page' ) ) );
	$res  = smi_list_requests( $args, $page, SMI_PER_PAGE );
	wp_send_json_success( array(
		'total'  => $res['total'],
		'rows'   => array_map( 'smi_row_for_list', $res['rows'] ),
		'counts' => smi_counts( $args ),
	) );
} );

add_action( 'wp_ajax_smi_get', function () {
	smi_ajax_guard();
	$r = smi_get_request( absint( smi_post( 'id' ) ) );
	if ( ! $r ) {
		wp_send_json_error( array( 'message' => __( 'This request no longer exists.', 'smartinquiry' ) ), 404 );
	}
	if ( ! $r['is_read'] ) {
		smi_update_request( $r['id'], array( 'is_read' => 1 ) );
	}
	$ui = smi_ui_lang();
	wp_send_json_success( array_merge( smi_row_for_list( $r ), array(
		'phone'    => $r['phone'],
		'pref'     => $r['pref'],
		'notes'    => $r['notes'],
		'ip'       => $r['ip'],
		'view'     => smi_snapshot_lang( $r, $ui ),        // for the screen, in the admin's language when available
		'visitor'  => smi_snapshot_lang( $r, $r['lang'] ), // for the PDF, in the language the visitor used
		'pdfDate'  => get_date_from_gmt( $r['created_at'], 'd/m/Y' ),
	) ) );
} );

add_action( 'wp_ajax_smi_update', function () {
	smi_ajax_guard();
	$fields = array();
	if ( '' !== smi_post( 'status' ) ) {
		$fields['status'] = smi_post( 'status' );
	}
	if ( isset( $_POST['notes'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$fields['notes'] = smi_limit( smi_post( 'notes' ), 5000 ); // by characters: Greek must never be cut mid-letter
	}
	if ( '' !== smi_post( 'isRead' ) ) {
		$fields['is_read'] = '1' === smi_post( 'isRead' );
	}
	$ok = smi_update_request( absint( smi_post( 'id' ) ), $fields );
	$ok ? wp_send_json_success() : wp_send_json_error( null, 400 );
} );

add_action( 'wp_ajax_smi_bulk', function () {
	smi_ajax_guard();
	$ids = array_filter( array_map( 'absint', explode( ',', (string) smi_post( 'ids' ) ) ) );
	$do  = smi_post( 'do' );
	if ( ! $ids ) {
		wp_send_json_error( null, 400 );
	}
	if ( 'delete' === $do ) {
		wp_send_json_success( array( 'n' => smi_delete_requests( $ids ) ) );
	}
	if ( in_array( $do, SMI_STATUSES, true ) ) {
		foreach ( $ids as $id ) {
			smi_update_request( $id, array( 'status' => $do ) );
		}
		wp_send_json_success( array( 'n' => count( $ids ) ) );
	}
	wp_send_json_error( null, 400 );
} );

add_action( 'wp_ajax_smi_save', function () {
	smi_ajax_guard( SMI_CAP_SETTINGS );
	$raw = json_decode( (string) smi_post( 'settings' ), true );
	if ( ! is_array( $raw ) ) {
		wp_send_json_error( array( 'message' => __( 'Could not save. Please try again.', 'smartinquiry' ) ), 400 );
	}
	// The entry link belongs to the Access screen, which only administrators see.
	if ( ! current_user_can( 'manage_options' ) ) {
		$raw['access'] = smi_get_settings()['access'];
	}
	$clean = smi_save_settings( smi_turnstile_keep_secret( $raw ) );
	if ( '' === $clean['notify']['subject'] ) {
		$clean['notify']['subject'] = smi_default_subject();
	}
	// Cached pages carry the form's settings; an old copy would offer questions that no longer exist.
	$caches = smi_purge_page_caches();
	wp_send_json_success( array( 'settings' => smi_settings_for_admin( $clean ), 'logoUrl' => smi_logo_url( $clean, 'medium' ), 'caches' => $caches ) );
} );

add_action( 'wp_ajax_smi_test_mail', function () {
	smi_ajax_guard( SMI_CAP_SETTINGS );
	$to   = smi_recipients();
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$ok   = wp_mail(
		$to,
		/* translators: %s: site name */
		sprintf( __( 'SmartInquiry test email from %s', 'smartinquiry' ), $site ),
		'<p style="font-family:sans-serif">' . esc_html__( 'If you can read this, new requests will reach this address.', 'smartinquiry' ) . '</p>',
		array( 'Content-Type: text/html; charset=UTF-8' )
	);
	$ok ? wp_send_json_success( array( 'to' => $to ) ) : wp_send_json_error( array( 'to' => $to ), 500 );
} );

/* ---------- Desktop notifications (per person, while the screen is open in a tab) ---------- */

/** The notification sounds: confirm (default) is Kenney's "confirmation_002" (CC0), chime is drawn by the browser. */
const SMI_TONES = array( 'chime', 'confirm' );

/** This person's choice: { on, sound, tone }. */
function smi_desk_pref( $user_id = 0 ) {
	$p = get_user_meta( $user_id ? $user_id : get_current_user_id(), 'smi_desk', true );
	$p = is_array( $p ) ? $p : array();
	return array(
		'on'    => ! empty( $p['on'] ),
		'sound' => ! isset( $p['sound'] ) || ! empty( $p['sound'] ),
		'tone'  => isset( $p['tone'] ) && in_array( $p['tone'], SMI_TONES, true ) ? $p['tone'] : 'confirm',
	);
}

add_action( 'wp_ajax_smi_desk', function () {
	smi_ajax_guard();
	$tone = smi_post( 'tone' );
	update_user_meta( get_current_user_id(), 'smi_desk', array(
		'on'    => '1' === smi_post( 'on' ),
		'sound' => '1' === smi_post( 'sound' ),
		'tone'  => in_array( $tone, SMI_TONES, true ) ? $tone : 'confirm',
	) );
	wp_send_json_success( smi_desk_pref() );
} );

/**
 * Requests newer than the last one the screen knows about. The first call (init=1) only returns the newest id, so
 * opening the screen never announces requests that are already there; with no requests yet that id is 0, and the
 * very first request is still announced.
 */
add_action( 'wp_ajax_smi_poll', function () {
	smi_ajax_guard();
	global $wpdb;
	$since  = absint( smi_post( 'since' ) );
	$latest = (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . smi_table() ); // phpcs:ignore WordPress.DB
	$items  = array();
	if ( '1' !== smi_post( 'init' ) && $latest > $since ) {
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . smi_table() . ' WHERE id > %d ORDER BY id ASC LIMIT 10', $since ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $r ) {
			$r       = smi_decode_row( $r );
			$items[] = array_intersect_key( smi_row_for_list( $r ), array_flip( array( 'id', 'ref', 'name', 'svcName', 'company' ) ) );
		}
	}
	wp_send_json_success( array( 'latest' => $latest, 'items' => $items, 'new' => smi_new_count() ) );
} );

add_action( 'wp_ajax_smi_ui_lang', function () {
	smi_ajax_guard();
	$lang = sanitize_key( smi_post( 'lang' ) );
	if ( ! in_array( $lang, array( 'en', 'el' ), true ) ) {
		wp_send_json_error( null, 400 );
	}
	update_user_meta( get_current_user_id(), 'smi_ui_lang', $lang );
	wp_send_json_success();
} );

/* ---------- PDF downloads (form posts, so the browser saves the file) ---------- */

add_action( 'admin_post_smi_pdf', function () {
	check_admin_referer( 'smi_admin', 'nonce' );
	if ( ! current_user_can( SMI_CAP_REQUESTS ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'smartinquiry' ), 403 );
	}
	$r = smi_get_request( absint( smi_post( 'id' ) ) );
	if ( ! $r ) {
		wp_die( esc_html__( 'This request no longer exists.', 'smartinquiry' ), 404 );
	}
	$doc   = smi_pdf_doc( $r );
	$bytes = smi_pdf_bytes( $doc );
	if ( '' === $bytes ) {
		wp_die( esc_html__( 'The server could not make the PDF.', 'smartinquiry' ), 500 );
	}
	smi_pdf_send( $bytes, $doc['filename'] );
} );

/** A sample with made-up answers, drawn with the settings on screen (saved or not; nothing is stored). */
add_action( 'admin_post_smi_pdf_sample', function () {
	check_admin_referer( 'smi_admin', 'nonce' );
	if ( ! current_user_can( SMI_CAP_SETTINGS ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'smartinquiry' ), 403 );
	}
	$raw  = json_decode( (string) smi_post( 'settings' ), true );
	$s    = smi_sanitize_settings( is_array( $raw ) ? $raw : smi_get_settings() );
	$lang = sanitize_key( smi_post( 'lang' ) );
	$lang = in_array( $lang, smi_text_langs(), true ) ? $lang : smi_default_lang();
	$t    = function ( $k ) use ( $s, $lang ) {
		return smi_tr( $s['texts'][ $k ], $lang );
	};
	$svc  = $s['form']['services'] ? $s['form']['services'][0] : null;
	$rows = array();
	foreach ( $svc ? $svc['details'] : array() as $q ) {
		if ( $q['opts'] && count( $rows ) < 3 ) {
			$rows[] = array( smi_short_label( smi_tr( $q['label'], $lang ) ), smi_tr( $q['opts'][0]['label'], $lang ) );
		}
	}
	$ref  = $s['ref_prefix'] . '-' . wp_date( 'Y' ) . '-0001';
	$foot = smi_tr( $s['pdf']['footer'], $lang );
	$doc  = array(
		'lang'     => $lang,
		'title'    => $t( 'pdf_title' ),
		'ref'      => $ref,
		'date'     => wp_date( 'd/m/Y' ),
		'company'  => $s['pdf']['company'],
		'footer'   => '' !== $foot ? $foot : $t( 'reply_time' ),
		'accent'   => $s['look']['accent'],
		'logo'     => smi_logo_file( $s ),
		'sections' => array(
			array( 'title' => $t( 'pdf_service' ), 'rows' => array( array( $t( 'pdf_need' ), $svc ? smi_tr( $svc['label'], $lang ) : '' ) ) ),
			array( 'title' => $t( 'step_details' ), 'rows' => $rows ),
			array( 'title' => $t( 'step_plan' ), 'rows' => array( array( smi_short_label( $t( 'q_when' ) ), smi_tr( $s['form']['when'][0]['label'], $lang ) ), array( smi_short_label( $t( 'q_budget' ) ), $svc ? smi_tr( $svc['budget'][0]['label'], $lang ) : '' ) ) ),
			array( 'title' => $t( 'pdf_contact' ), 'rows' => array( array( $t( 'f_name' ), $t( 'f_name_ph' ) ), array( $t( 'f_email' ), $t( 'f_email_ph' ) ), array( $t( 'f_phone' ), $t( 'f_phone_ph' ) ) ) ),
		),
	);
	$bytes = smi_pdf_bytes( $doc );
	if ( '' === $bytes ) {
		wp_die( esc_html__( 'The server could not make the PDF.', 'smartinquiry' ), 500 );
	}
	smi_pdf_send( $bytes, smi_pdf_filename( $t( 'pdf_file' ), 'sample' ) );
} );

/* ---------- CSV export (a form post, so the browser downloads the file) ---------- */

/** Cells that start with = + - @ would run as formulas in Excel; a leading apostrophe keeps them text. */
function smi_csv_cell( $v ) {
	$v = (string) $v;
	return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
}

add_action( 'admin_post_smi_export', function () {
	check_admin_referer( 'smi_admin', 'nonce' );
	if ( ! current_user_can( SMI_CAP_REQUESTS ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'smartinquiry' ), 403 );
	}
	global $wpdb;
	$ids = array_filter( array_map( 'absint', explode( ',', isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '' ) ) );
	if ( $ids ) {
		$in   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . smi_table() . " WHERE id IN ({$in}) ORDER BY created_at DESC", $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	} else {
		$where = smi_where( smi_list_args( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$rows  = $wpdb->get_results( 'SELECT * FROM ' . smi_table() . " WHERE {$where} ORDER BY created_at DESC LIMIT 10000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	$lang = smi_ui_lang();
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="smartinquiry-' . wp_date( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // BOM: Excel then reads Greek correctly
	$labels = array( 'new' => __( 'New', 'smartinquiry' ), 'contact' => __( 'In contact', 'smartinquiry' ), 'offer' => __( 'Offer sent', 'smartinquiry' ), 'won' => __( 'Won', 'smartinquiry' ), 'lost' => __( 'Lost', 'smartinquiry' ) );
	fputcsv( $out, array( __( 'Reference', 'smartinquiry' ), __( 'Date', 'smartinquiry' ), __( 'Status', 'smartinquiry' ), __( 'Name', 'smartinquiry' ), __( 'Email', 'smartinquiry' ), __( 'Phone', 'smartinquiry' ), __( 'Company', 'smartinquiry' ), __( 'Form language', 'smartinquiry' ), __( 'Answers', 'smartinquiry' ), __( 'Internal notes', 'smartinquiry' ) ), ',', '"', '' );
	foreach ( (array) $rows as $r ) {
		$r       = smi_decode_row( $r );
		$answers = array();
		foreach ( smi_snapshot_rows( $r, $lang ) as $row ) {
			$answers[] = $row[0] . ': ' . $row[1];
		}
		fputcsv( $out, array_map( 'smi_csv_cell', array(
			$r['ref'],
			get_date_from_gmt( $r['created_at'], 'Y-m-d H:i' ),
			isset( $labels[ $r['status'] ] ) ? $labels[ $r['status'] ] : $r['status'],
			$r['name'],
			$r['email'],
			$r['phone'],
			$r['company'],
			strtoupper( $r['lang'] ),
			implode( "\n", $answers ),
			$r['notes'],
		) ), ',', '"', '' );
	}
	fclose( $out );
	exit;
} );

/* ---------- Page caches: emptied after saving, so pages never keep an older copy of the form ---------- */

/**
 * Empties the page cache of the common caching plugins and hosts. Returns the names of the caches that were cleared.
 * (Same list as SmartBanner's.)
 */
function smi_purge_page_caches() {
	$purgers = array(
		'WP Rocket'           => function () {
			if ( ! function_exists( 'rocket_clean_domain' ) ) {
				return false;
			}
			rocket_clean_domain();
			return true;
		},
		'LiteSpeed Cache'     => function () {
			if ( ! defined( 'LSCWP_V' ) ) {
				return false;
			}
			do_action( 'litespeed_purge_all' );
			return true;
		},
		'W3 Total Cache'      => function () {
			if ( ! function_exists( 'w3tc_flush_all' ) ) {
				return false;
			}
			w3tc_flush_all();
			return true;
		},
		'WP Super Cache'      => function () {
			if ( ! function_exists( 'wp_cache_clear_cache' ) ) {
				return false;
			}
			wp_cache_clear_cache();
			return true;
		},
		'WP Fastest Cache'    => function () {
			if ( ! isset( $GLOBALS['wp_fastest_cache'] ) || ! method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
				return false;
			}
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
			return true;
		},
		'WP-Optimize'         => function () {
			if ( ! function_exists( 'wpo_cache_flush' ) ) {
				return false;
			}
			wpo_cache_flush();
			return true;
		},
		'SiteGround Optimizer' => function () {
			if ( ! function_exists( 'sg_cachepress_purge_cache' ) ) {
				return false;
			}
			sg_cachepress_purge_cache();
			return true;
		},
		'Breeze'              => function () {
			if ( ! class_exists( 'Breeze_PurgeCache' ) ) {
				return false;
			}
			do_action( 'breeze_clear_all_cache' );
			return true;
		},
		'Hummingbird'         => function () {
			if ( ! class_exists( 'Hummingbird\\WP_Hummingbird' ) && ! class_exists( 'WP_Hummingbird' ) ) {
				return false;
			}
			do_action( 'wphb_clear_page_cache' );
			return true;
		},
		'Cache Enabler'       => function () {
			if ( ! class_exists( 'Cache_Enabler' ) ) {
				return false;
			}
			do_action( 'cache_enabler_clear_complete_cache' );
			return true;
		},
		'Comet Cache'         => function () {
			if ( ! class_exists( 'comet_cache' ) || ! method_exists( 'comet_cache', 'clear' ) ) {
				return false;
			}
			comet_cache::clear();
			return true;
		},
		'Swift Performance'   => function () {
			if ( ! class_exists( 'Swift_Performance_Cache' ) || ! method_exists( 'Swift_Performance_Cache', 'clear_all_cache' ) ) {
				return false;
			}
			Swift_Performance_Cache::clear_all_cache();
			return true;
		},
		'Autoptimize'         => function () {
			if ( ! class_exists( 'autoptimizeCache' ) || ! method_exists( 'autoptimizeCache', 'clearall' ) ) {
				return false;
			}
			autoptimizeCache::clearall();
			return true;
		},
		'Nginx Helper'        => function () {
			if ( ! defined( 'NGINX_HELPER_BASEPATH' ) ) {
				return false;
			}
			do_action( 'rt_nginx_helper_purge_all' );
			return true;
		},
		'WP Engine'           => function () {
			if ( ! class_exists( 'WpeCommon' ) ) {
				return false;
			}
			if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) {
				WpeCommon::purge_memcached();
			}
			if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
				WpeCommon::purge_varnish_cache();
			}
			return true;
		},
	);

	$done = array();
	foreach ( $purgers as $name => $purge ) {
		try {
			if ( $purge() ) {
				$done[] = $name;
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			// Another plugin failing must not stop the reset; that cache is simply not listed.
		}
	}
	return $done;
}
