<?php
/**
 * Languages. With WPML: its active languages, read only through its public hooks. Without it: the site language.
 * Every text the visitor sees is stored per language code ( [ 'el' => '…', 'en' => '…' ] ).
 */
defined( 'ABSPATH' ) || exit;

/** String value of a key, or '' when missing or not a scalar (arrays must never reach string functions). */
function smi_str( $arr, $key ) {
	return ( is_array( $arr ) && isset( $arr[ $key ] ) && is_scalar( $arr[ $key ] ) ) ? (string) $arr[ $key ] : '';
}

/** Two-letter code of a WordPress locale: el_GR / el -> el, en_US -> en. */
function smi_locale_code( $locale ) {
	$code = strtolower( substr( (string) $locale, 0, 2 ) );
	return preg_match( '/^[a-z]{2}$/', $code ) ? $code : 'en';
}

/**
 * Active languages, default first: code => [ code, name, default ].
 * Always at least one entry (the site language when WPML isn't there).
 */
function smi_languages() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$langs = array();
	$wpml  = apply_filters( 'wpml_active_languages', null, 'skip_missing=0' );
	if ( is_array( $wpml ) && $wpml ) {
		$default = (string) apply_filters( 'wpml_default_language', null );
		foreach ( $wpml as $code => $l ) {
			$code = sanitize_key( (string) $code );
			if ( '' === $code || 'all' === $code || strlen( $code ) > 10 ) {
				continue;
			}
			$name           = smi_str( $l, 'native_name' );
			$langs[ $code ] = array(
				'code'    => $code,
				'name'    => '' !== $name ? $name : strtoupper( $code ),
				'default' => $code === $default,
			);
		}
		uasort( $langs, function ( $a, $b ) {
			return (int) $b['default'] - (int) $a['default'];
		} );
		if ( $langs && ! array_filter( wp_list_pluck( $langs, 'default' ) ) ) {
			$first                     = key( $langs );
			$langs[ $first ]['default'] = true;
		}
	}
	if ( ! $langs ) {
		$code  = smi_locale_code( get_locale() );
		$names = array( 'el' => 'Ελληνικά', 'en' => 'English' );
		$langs = array( $code => array( 'code' => $code, 'name' => isset( $names[ $code ] ) ? $names[ $code ] : strtoupper( $code ), 'default' => true ) );
	}
	$cache = $langs;
	return $cache;
}

function smi_lang_codes() {
	return array_keys( smi_languages() );
}

function smi_default_lang() {
	return key( smi_languages() );
}

/** Language of the current page. */
function smi_current_lang() {
	$langs = smi_languages();
	$cur   = sanitize_key( (string) apply_filters( 'wpml_current_language', null ) );
	return isset( $langs[ $cur ] ) ? $cur : smi_default_lang();
}

/** One language of a per-language text, falling back to the default language, then English, then anything. */
function smi_tr( $map, $lang ) {
	if ( ! is_array( $map ) ) {
		return is_scalar( $map ) ? (string) $map : '';
	}
	foreach ( array( $lang, smi_default_lang(), 'en', 'el' ) as $l ) {
		$v = smi_str( $map, $l );
		if ( '' !== $v ) {
			return $v;
		}
	}
	foreach ( $map as $v ) {
		if ( is_scalar( $v ) && '' !== (string) $v ) {
			return (string) $v;
		}
	}
	return '';
}
