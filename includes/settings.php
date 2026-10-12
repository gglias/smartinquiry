<?php
/**
 * Settings: one option holding the form (services, questions, options), the texts and the other settings.
 * Everything is forced into its expected shape both when saved and when read, so values that reached the
 * database some other way (WP-CLI, an import, another plugin) can't end up in a page or an email as-is.
 */
defined( 'ABSPATH' ) || exit;

const SMI_ICONS = array( 'phone', 'app', 'spark', 'globe', 'cart', 'chat', 'form', 'brush', 'shield', 'camera', 'megaphone', 'code' );
const SMI_TYPES = array( 'single', 'multi', 'text', 'textarea' );

function smi_get_settings() {
	static $cache = null;
	if ( null === $cache ) {
		$saved = get_option( SMI_OPTION, null );
		$cache = is_array( $saved ) ? smi_sanitize_settings( $saved ) : smi_defaults();
		if ( '' === $cache['notify']['subject'] ) {
			$cache['notify']['subject'] = smi_default_subject();
		}
	}
	return $cache;
}

function smi_save_settings( $raw ) {
	$clean = smi_sanitize_settings( is_array( $raw ) ? $raw : array() );
	update_option( SMI_OPTION, $clean, false );
	return $clean;
}

/**
 * One-off changes to settings saved by an older version. New defaults only reach sites that never saved,
 * so a change to a default service is also applied to the saved copy, once.
 */
function smi_migrate_settings() {
	if ( (int) get_option( 'smartinquiry_settings_rev', 0 ) >= 1 ) {
		return;
	}
	$saved = get_option( SMI_OPTION, null );
	if ( is_array( $saved ) && isset( $saved['form']['services'] ) && is_array( $saved['form']['services'] ) ) {
		// 1.2.0: "E-shop" gives its place (and on/off state) to "UI/UX design". Nothing else is touched.
		$services = $saved['form']['services'];
		$ids      = array();
		foreach ( $services as $svc ) {
			$ids[] = is_array( $svc ) ? smi_str( $svc, 'id' ) : '';
		}
		$at = array_search( 'eshop', $ids, true );
		if ( false !== $at && ! in_array( 'design', $ids, true ) ) {
			$design       = smi_default_design_service();
			$design['on'] = empty( $services[ $at ]['on'] ) ? 0 : 1;

			$services[ $at ]            = $design;
			$saved['form']['services'] = $services;
			update_option( SMI_OPTION, $saved, false );
			// Cached pages still carry the old form; the plugin update runs in the admin, where the purge lives.
			add_action( 'init', function () {
				if ( function_exists( 'smi_purge_page_caches' ) ) {
					smi_purge_page_caches();
				}
			}, 99 );
		}
	}
	update_option( 'smartinquiry_settings_rev', 1, true );
}

/** Language codes a per-language text may hold: the active ones plus Greek and English. */
function smi_text_langs() {
	return array_values( array_unique( array_merge( smi_lang_codes(), array( 'el', 'en' ) ) ) );
}

/** A per-language text: only known language codes, strings only, trimmed and length-limited. */
function smi_clean_map( $map, $multiline = false, $max = 400 ) {
	$out = array();
	if ( ! is_array( $map ) ) {
		return $out;
	}
	foreach ( smi_text_langs() as $lang ) {
		$v = smi_str( $map, $lang );
		$out[ $lang ] = smi_clean_text( smi_limit( $v, $max ), $multiline );
	}
	return $out;
}

function smi_clean_id( $v, $max = 30 ) {
	$id = substr( sanitize_key( is_scalar( $v ) ? (string) $v : '' ), 0, $max );
	// Never __proto__, constructor and the like: ids become keys of plain objects in the browser.
	return ( 0 === strpos( $id, '__' ) || in_array( $id, array( 'constructor', 'prototype', 'hasownproperty', 'tostring', 'valueof' ), true ) ) ? '' : $id;
}

/** Options of a question: ids unique, at least a label in some language, at most 40. */
function smi_clean_opts( $opts, $max = 40 ) {
	$out  = array();
	$seen = array();
	if ( ! is_array( $opts ) ) {
		return $out;
	}
	foreach ( $opts as $o ) {
		if ( count( $out ) >= $max ) {
			break;
		}
		if ( ! is_array( $o ) ) {
			continue;
		}
		$id    = smi_clean_id( smi_str( $o, 'id' ), 20 );
		$label = smi_clean_map( isset( $o['label'] ) ? $o['label'] : array(), false, 160 );
		if ( '' === $id || isset( $seen[ $id ] ) || ! array_filter( $label, 'strlen' ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$out[]       = array( 'id' => $id, 'label' => $label );
	}
	return $out;
}

/** Questions of one step. Ids are unique per service ($used is shared between the two steps of a service). */
function smi_clean_questions( $qs, array &$used ) {
	$out = array();
	if ( ! is_array( $qs ) ) {
		return $out;
	}
	foreach ( $qs as $q ) {
		if ( count( $out ) >= 20 ) {
			break;
		}
		if ( ! is_array( $q ) ) {
			continue;
		}
		$id   = smi_clean_id( smi_str( $q, 'id' ) );
		$type = in_array( smi_str( $q, 'type' ), SMI_TYPES, true ) ? $q['type'] : 'single';
		if ( '' === $id || isset( $used[ $id ] ) || in_array( $id, array( 'when', 'budget', 'notes', 'name', 'email', 'phone', 'company', 'pref', 'consent', 'service' ), true ) ) {
			continue;
		}
		$choice = in_array( $type, array( 'single', 'multi' ), true );
		$clean  = array(
			'id'      => $id,
			'type'    => $type,
			'req'     => empty( $q['req'] ) ? 0 : 1,
			'other'   => ( $choice && ! empty( $q['other'] ) ) ? 1 : 0,
			'label'   => smi_clean_map( isset( $q['label'] ) ? $q['label'] : array(), false, 200 ),
			'hint'    => smi_clean_map( isset( $q['hint'] ) ? $q['hint'] : array(), false, 80 ),
			'ph'      => smi_clean_map( isset( $q['ph'] ) ? $q['ph'] : array(), false, 200 ),
			'opts'    => $choice ? smi_clean_opts( isset( $q['opts'] ) ? $q['opts'] : array() ) : array(),
			'show_if' => null,
		);
		if ( ! array_filter( $clean['label'], 'strlen' ) || ( $choice && ! $clean['opts'] ) ) {
			continue;
		}
		// "Only show if" may only point at an earlier single-choice question of the same step, and one of its options.
		if ( isset( $q['show_if'] ) && is_array( $q['show_if'] ) ) {
			$pq = smi_clean_id( smi_str( $q['show_if'], 'q' ) );
			$po = smi_clean_id( smi_str( $q['show_if'], 'opt' ), 20 );
			foreach ( $out as $prev ) {
				if ( $prev['id'] === $pq && 'single' === $prev['type'] && in_array( $po, wp_list_pluck( $prev['opts'], 'id' ), true ) ) {
					$clean['show_if'] = array( 'q' => $pq, 'opt' => $po );
				}
			}
		}
		$used[ $id ] = true;
		$out[]       = $clean;
	}
	return $out;
}

function smi_clean_services( $services ) {
	$out  = array();
	$seen = array();
	if ( ! is_array( $services ) ) {
		return $out;
	}
	foreach ( $services as $s ) {
		if ( count( $out ) >= 20 ) {
			break;
		}
		if ( ! is_array( $s ) ) {
			continue;
		}
		$id = smi_clean_id( smi_str( $s, 'id' ), 24 );
		// Ids are used in links (#smartup-inquiry-eshop) next to language codes, so they must never be one.
		if ( strlen( $id ) < 2 || in_array( $id, smi_text_langs(), true ) || isset( $seen[ $id ] ) ) {
			continue;
		}
		$used  = array();
		$clean = array(
			'id'             => $id,
			'on'             => empty( $s['on'] ) ? 0 : 1,
			'icon'           => in_array( smi_str( $s, 'icon' ), SMI_ICONS, true ) ? $s['icon'] : 'form',
			'label'          => smi_clean_map( isset( $s['label'] ) ? $s['label'] : array(), false, 80 ),
			'desc'           => smi_clean_map( isset( $s['desc'] ) ? $s['desc'] : array(), false, 160 ),
			'details_title'  => smi_clean_map( isset( $s['details_title'] ) ? $s['details_title'] : array(), false, 160 ),
			'details_sub'    => smi_clean_map( isset( $s['details_sub'] ) ? $s['details_sub'] : array(), false, 300 ),
			'features_title' => smi_clean_map( isset( $s['features_title'] ) ? $s['features_title'] : array(), false, 160 ),
			'features_sub'   => smi_clean_map( isset( $s['features_sub'] ) ? $s['features_sub'] : array(), false, 300 ),
			'budget'         => smi_clean_opts( isset( $s['budget'] ) ? $s['budget'] : array(), 20 ),
		);
		$clean['details']  = smi_clean_questions( isset( $s['details'] ) ? $s['details'] : array(), $used );
		$clean['features'] = smi_clean_questions( isset( $s['features'] ) ? $s['features'] : array(), $used );
		if ( ! array_filter( $clean['label'], 'strlen' ) ) {
			continue;
		}
		if ( ! $clean['budget'] ) {
			$clean['budget'] = smi_opts( array( smi_t( 'Δεν ξέρω ακόμα', 'Not sure yet' ) ) );
		}
		$seen[ $id ] = true;
		$out[]       = $clean;
	}
	return $out;
}

function smi_sanitize_settings( $in ) {
	$in = is_array( $in ) ? $in : array();
	$d  = smi_defaults();

	$form     = isset( $in['form'] ) && is_array( $in['form'] ) ? $in['form'] : array();
	$services = smi_clean_services( isset( $form['services'] ) ? $form['services'] : $d['form']['services'] );
	$when     = smi_clean_opts( isset( $form['when'] ) ? $form['when'] : $d['form']['when'], 12 );

	// Only known text keys; a text left empty in every language falls back to the default.
	$texts = array();
	$tin   = isset( $in['texts'] ) && is_array( $in['texts'] ) ? $in['texts'] : array();
	foreach ( $d['texts'] as $key => $def ) {
		$map = smi_clean_map( isset( $tin[ $key ] ) ? $tin[ $key ] : $def, true, 600 );
		foreach ( $map as $lang => $v ) {
			if ( '' === $v && '' !== smi_str( $def, $lang ) && ! isset( $tin[ $key ][ $lang ] ) ) {
				$map[ $lang ] = $def[ $lang ];
			}
		}
		$texts[ $key ] = $map;
	}

	$look = isset( $in['look'] ) && is_array( $in['look'] ) ? $in['look'] : array();
	$hex  = function ( $key ) use ( $look, $d ) {
		$c = sanitize_hex_color( trim( smi_str( $look, $key ) ) );
		return $c ? strtolower( $c ) : $d['look'][ $key ];
	};

	$notify     = isset( $in['notify'] ) && is_array( $in['notify'] ) ? $in['notify'] : array();
	$recipients = array();
	if ( isset( $notify['recipients'] ) && is_array( $notify['recipients'] ) ) {
		foreach ( $notify['recipients'] as $r ) {
			$r = sanitize_email( is_scalar( $r ) ? (string) $r : '' );
			if ( $r && is_email( $r ) && ! in_array( $r, $recipients, true ) && count( $recipients ) < 10 ) {
				$recipients[] = $r;
			}
		}
	}

	$pdf     = isset( $in['pdf'] ) && is_array( $in['pdf'] ) ? $in['pdf'] : array();
	$company = array();
	foreach ( $d['pdf']['company'] as $key => $unused ) {
		$company[ $key ] = smi_clean_text( smi_limit( isset( $pdf['company'] ) ? smi_str( $pdf['company'], $key ) : '', 200 ) );
	}
	$logo = isset( $pdf['logo_id'] ) ? absint( $pdf['logo_id'] ) : 0;
	if ( $logo && ! wp_attachment_is_image( $logo ) ) {
		$logo = 0;
	}

	$privacy = isset( $in['privacy'] ) && is_array( $in['privacy'] ) ? $in['privacy'] : array();
	$pages   = array();
	if ( isset( $privacy['pages'] ) && is_array( $privacy['pages'] ) ) {
		foreach ( smi_text_langs() as $lang ) {
			$pid = absint( smi_str( $privacy['pages'], $lang ) );
			if ( $pid && 'page' === get_post_type( $pid ) ) {
				$pages[ $lang ] = $pid;
			}
		}
	}
	$retention = (int) smi_str( $privacy, 'retention' );

	$prefix = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', smi_str( $in, 'ref_prefix' ) ) );
	$ts     = isset( $in['turnstile'] ) && is_array( $in['turnstile'] ) ? $in['turnstile'] : array();

	$float   = isset( $in['float'] ) && is_array( $in['float'] ) ? $in['float'] : array();
	$f_label = smi_clean_map( isset( $float['label'] ) ? $float['label'] : $d['float']['label'], false, 40 );
	foreach ( $f_label as $lang => $v ) {
		if ( '' === $v && '' !== smi_str( $d['float']['label'], $lang ) ) {
			$f_label[ $lang ] = $d['float']['label'][ $lang ];
		}
	}
	$f_hex     = function ( $key ) use ( $float, $d ) {
		$c = sanitize_hex_color( trim( smi_str( $float, $key ) ) );
		return $c ? strtolower( $c ) : $d['float'][ $key ];
	};
	$f_exclude = array();
	if ( isset( $float['exclude'] ) && is_array( $float['exclude'] ) ) {
		foreach ( $float['exclude'] as $pid ) {
			$pid = absint( is_scalar( $pid ) ? $pid : 0 );
			if ( $pid && ! in_array( $pid, $f_exclude, true ) && count( $f_exclude ) < 100 && 'page' === get_post_type( $pid ) ) {
				$f_exclude[] = $pid;
			}
		}
	}

	return array(
		'form'       => array( 'services' => $services, 'when' => $when ? $when : $d['form']['when'] ),
		'texts'      => $texts,
		'look'       => array( 'accent' => $hex( 'accent' ), 'accent_text' => $hex( 'accent_text' ), 'panel' => $hex( 'panel' ) ),
		'float'      => array(
			'enabled'  => empty( $float['enabled'] ) ? 0 : 1,
			'position' => 'right' === smi_str( $float, 'position' ) ? 'right' : 'left',
			'label'    => $f_label,
			'bg'       => $f_hex( 'bg' ),
			'color'    => $f_hex( 'color' ),
			'mobile'   => 'hide' === smi_str( $float, 'mobile' ) ? 'hide' : 'show',
			'exclude'  => $f_exclude,
		),
		'notify'     => array(
			'enabled'    => empty( $notify['enabled'] ) ? 0 : 1,
			'attach'     => ( isset( $notify['attach'] ) && empty( $notify['attach'] ) ) ? 0 : 1,
			'recipients' => $recipients,
			'subject'    => smi_clean_text( smi_limit( smi_str( $notify, 'subject' ), 200 ) ),
		),
		'pdf'        => array(
			'logo_id' => $logo,
			'company' => $company,
			'footer'  => smi_clean_map( isset( $pdf['footer'] ) ? $pdf['footer'] : array(), false, 200 ),
		),
		'privacy'    => array(
			'pages'     => $pages,
			'retention' => in_array( $retention, array( 0, 6, 12, 24, 36 ), true ) ? $retention : 12,
			'store_ip'  => empty( $privacy['store_ip'] ) ? 0 : 1,
		),
		'turnstile'  => array(
			'enabled' => empty( $ts['enabled'] ) ? 0 : 1,
			'site'    => smi_turnstile_clean_key( smi_str( $ts, 'site' ) ),
			'secret'  => smi_turnstile_clean_key( smi_str( $ts, 'secret' ) ),
		),
		'access'     => array(
			'slug' => isset( $in['access'] ) && is_array( $in['access'] ) && isset( $in['access']['slug'] ) ? smi_clean_access_slug( $in['access']['slug'] ) : $d['access']['slug'],
		),
		'ref_prefix' => '' !== $prefix ? substr( $prefix, 0, 6 ) : 'SU',
	);
}

/** A service of the settings by id, or null. */
function smi_service( $id, $s = null ) {
	$s = $s ? $s : smi_get_settings();
	foreach ( $s['form']['services'] as $svc ) {
		if ( $svc['id'] === $id ) {
			return $svc;
		}
	}
	return null;
}

/** Privacy policy URL for a language: the page chosen in the settings, else the WordPress one (translated by WPML). */
function smi_privacy_url( $lang ) {
	$s   = smi_get_settings();
	$pid = isset( $s['privacy']['pages'][ $lang ] ) ? (int) $s['privacy']['pages'][ $lang ] : 0;
	if ( ! $pid ) {
		$pid = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $pid ) {
			$pid = (int) apply_filters( 'wpml_object_id', $pid, 'page', true, $lang );
		}
	}
	return ( $pid && 'publish' === get_post_status( $pid ) ) ? (string) get_permalink( $pid ) : '';
}
