<?php
/**
 * Cloudflare Turnstile before "Send request" (optional, off by default).
 *
 * Keys: the ones typed in SmartInquiry → Privacy, or else the ones already saved in Contact Form 7 → Integration
 * (read only, never changed). Without a complete pair the check is skipped, so the form never locks itself up when,
 * say, Contact Form 7 is switched off; the admin screen warns about it.
 * The secret never reaches a browser: the admin screen only learns whether one is saved.
 */
defined( 'ABSPATH' ) || exit;

const SMI_TURNSTILE_VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const SMI_TURNSTILE_ACTION = 'smartinquiry';

/** Site keys and secrets are short strings of letters, digits, "-" and "_". */
function smi_turnstile_clean_key( $v ) {
	$v = is_scalar( $v ) ? trim( (string) $v ) : '';
	return preg_match( '/^[A-Za-z0-9_-]{1,200}$/', $v ) ? $v : '';
}

/** The keys saved in Contact Form 7 (6.1+), through its own API so its filters apply; [ site, secret ] or empties. */
function smi_turnstile_cf7_keys() {
	$site   = '';
	$secret = '';
	if ( class_exists( 'WPCF7_Turnstile' ) && method_exists( 'WPCF7_Turnstile', 'get_instance' ) ) {
		$cf7 = WPCF7_Turnstile::get_instance();
		if ( is_object( $cf7 ) && method_exists( $cf7, 'get_sitekey' ) && method_exists( $cf7, 'get_secret' ) ) {
			$site   = $cf7->get_sitekey();
			$secret = $cf7->get_secret( $site );
		}
	} else {
		// Contact Form 7 not loaded: its option, { site key: secret } under "turnstile".
		$opt = get_option( 'wpcf7' );
		if ( is_array( $opt ) && ! empty( $opt['turnstile'] ) && is_array( $opt['turnstile'] ) ) {
			$site   = (string) array_key_first( $opt['turnstile'] );
			$secret = $opt['turnstile'][ $site ];
		}
	}
	return array( smi_turnstile_clean_key( $site ), smi_turnstile_clean_key( $secret ) );
}

/** Keys in use: [ site, secret, source ] where source is 'own', 'cf7' or ''. */
function smi_turnstile_keys( $s = null ) {
	$s = $s ? $s : smi_get_settings();
	$t = $s['turnstile'];
	if ( '' !== $t['site'] && '' !== $t['secret'] ) {
		return array( $t['site'], $t['secret'], 'own' );
	}
	list( $site, $secret ) = smi_turnstile_cf7_keys();
	return ( '' !== $site && '' !== $secret ) ? array( $site, $secret, 'cf7' ) : array( '', '', '' );
}

/** Switched on and with a complete pair of keys: only then is the check shown and required. */
function smi_turnstile_active( $s = null ) {
	$s = $s ? $s : smi_get_settings();
	return $s['turnstile']['enabled'] && '' !== smi_turnstile_keys( $s )[2];
}

/**
 * Asks Cloudflare about a token. 'ok', 'fail' (missing, wrong, used or expired token, or one made for another form)
 * or 'down' (Cloudflare didn't answer: the request is refused too, the visitor is asked to try again).
 */
function smi_turnstile_verify( $token, $s = null ) {
	$token = is_string( $token ) ? trim( $token ) : '';
	if ( '' === $token || strlen( $token ) > 2048 ) {
		return 'fail';
	}
	list( , $secret ) = smi_turnstile_keys( $s );
	$body = array( 'secret' => $secret, 'response' => $token );
	$ip   = smi_client_ip();
	if ( '' !== $ip ) {
		$body['remoteip'] = $ip;
	}
	$res = wp_remote_post( SMI_TURNSTILE_VERIFY, array( 'timeout' => 10, 'body' => $body ) );
	$res = apply_filters( 'smartinquiry_turnstile_response', $res, $body ); // tests only
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return 'down';
	}
	$d = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $d ) ) {
		return 'down';
	}
	if ( empty( $d['success'] ) ) {
		$codes = isset( $d['error-codes'] ) ? (array) $d['error-codes'] : array();
		return in_array( 'internal-error', $codes, true ) ? 'down' : 'fail';
	}
	// A token from another Turnstile on the site (e.g. a Contact Form 7 form) is not accepted here.
	if ( isset( $d['action'] ) && '' !== $d['action'] && SMI_TURNSTILE_ACTION !== $d['action'] ) {
		return 'fail';
	}
	return 'ok';
}

/** Settings as the admin screen gets them: the secret replaced by "is one saved?". */
function smi_settings_for_admin( array $s ) {
	$s['turnstile']['has_secret'] = '' !== $s['turnstile']['secret'];
	$s['turnstile']['secret']     = '';
	return $s;
}

/** On save, an empty secret field keeps the saved secret, unless "remove" was asked for. */
function smi_turnstile_keep_secret( array $raw ) {
	$in = isset( $raw['turnstile'] ) && is_array( $raw['turnstile'] ) ? $raw['turnstile'] : array();
	if ( '' === smi_str( $in, 'secret' ) && empty( $in['clear_secret'] ) ) {
		$raw['turnstile']['secret'] = smi_get_settings()['turnstile']['secret'];
	}
	return $raw;
}

/** What the admin screen shows about the keys. */
function smi_turnstile_status() {
	list( $site, $secret ) = smi_turnstile_cf7_keys();
	return array(
		'cf7'     => '' !== $site && '' !== $secret,
		'cf7Site' => $site,
	);
}
