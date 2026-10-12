<?php
/**
 * Front end: the form is built by assets/front.js from the settings printed below, and opens from any link to
 * #smartup-inquiry (optionally -<service> and/or -<language>) or any element with the class js-inquiry.
 */
defined( 'ABSPATH' ) || exit;

/** What the browser needs: only the services that are switched on, and nothing admin-only. */
function smi_front_config() {
	$s        = smi_get_settings();
	$services = array();
	foreach ( $s['form']['services'] as $svc ) {
		if ( $svc['on'] ) {
			$services[] = $svc;
		}
	}
	$privacy = array();
	foreach ( smi_text_langs() as $lang ) {
		$privacy[ $lang ] = smi_privacy_url( $lang );
	}

	return array(
		'lang'     => smi_current_lang(),
		'langs'    => smi_text_langs(),
		'rest'     => array(
			'token'  => esc_url_raw( rest_url( 'smartinquiry/v1/token' ) ),
			'submit' => esc_url_raw( rest_url( 'smartinquiry/v1/submit' ) ),
		),
		'look'     => $s['look'],
		'services' => $services,
		'when'     => $s['form']['when'],
		'texts'    => $s['texts'],
		'privacy'  => $privacy,
		'pdf'      => array(
			'logo'    => esc_url_raw( smi_logo_url( $s ) ),
			'company' => $s['pdf']['company'],
			'footer'  => $s['pdf']['footer'],
		),
		'float'    => smi_front_float( $s ),
		// Only the public site key, and only when the check is on and has keys.
		'turnstile' => smi_turnstile_active( $s ) ? array( 'site' => smi_turnstile_keys( $s )[0] ) : null,
		// Stored answers from an older form (e.g. after questions changed) are discarded.
		'version'  => substr( md5( wp_json_encode( array( $services, $s['form']['when'] ) ) ), 0, 10 ),
	);
}

/**
 * The floating button for this page, or null: off, or this page is excluded. An excluded page counts in every
 * language, through its WPML translations.
 */
function smi_front_float( $s ) {
	$f = $s['float'];
	if ( ! $f['enabled'] ) {
		return null;
	}
	if ( $f['exclude'] && is_page() ) {
		$cur = (int) get_queried_object_id();
		foreach ( $f['exclude'] as $pid ) {
			if ( $cur === (int) $pid || $cur === (int) apply_filters( 'wpml_object_id', $pid, 'page', false ) ) {
				return null;
			}
		}
	}
	return array(
		'position' => $f['position'],
		'label'    => $f['label'],
		'bg'       => $f['bg'],
		'color'    => $f['color'],
		'mobile'   => $f['mobile'],
	);
}

/** JSON safe inside a <script>: < > & ' " are hex-encoded, so no text written in the admin can close the tag. */
function smi_json_script( $var, $data ) {
	return 'var ' . $var . ' = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ) . ';';
}

/**
 * Inside a page builder's editing preview the form stays out: its click listener would open the form and keep the
 * builder from selecting the button. Elementor's preview frame carries ?elementor-preview.
 */
function smi_is_builder_preview() {
	return isset( $_GET['elementor-preview'] ) || is_customize_preview(); // phpcs:ignore WordPress.Security.NonceVerification
}

add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || is_feed() || is_embed() || smi_is_builder_preview() || ! apply_filters( 'smartinquiry_load', true ) ) {
		return;
	}
	$s = smi_get_settings();
	if ( ! array_filter( wp_list_pluck( $s['form']['services'], 'on' ) ) ) {
		return; // nothing to ask
	}
	wp_enqueue_style( 'smartinquiry', SMI_URL . 'assets/front.css', array(), SMI_VERSION );
	wp_enqueue_script( 'smartinquiry-pdf', SMI_URL . 'assets/pdf.js', array(), SMI_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_enqueue_script( 'smartinquiry', SMI_URL . 'assets/front.js', array( 'smartinquiry-pdf' ), SMI_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_add_inline_script( 'smartinquiry', smi_json_script( 'smartInquiry', smi_front_config() ), 'before' );
} );
