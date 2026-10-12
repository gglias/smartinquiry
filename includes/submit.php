<?php
/**
 * Public REST endpoints of the form:
 *   POST smartinquiry/v1/token   a signed, short-lived token (asked for when the form opens, so it works behind page caching)
 *   POST smartinquiry/v1/submit  validates the request against the current settings, stores it and emails the team
 * Nothing the browser sends is trusted: every answer must be an option that exists, every text is length-limited.
 */
defined( 'ABSPATH' ) || exit;

const SMI_RATE_LIMIT = 5; // requests per hour from the same connection

add_action( 'rest_api_init', function () {
	register_rest_route( 'smartinquiry/v1', '/token', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			// A person opens the form a few times an hour; a script minting tokens by the thousand is stopped here.
			if ( smi_rate_limited( 'mint', 60 ) ) {
				return smi_err( 'rate', 429 );
			}
			smi_rate_hit( 'mint' );
			return smi_nocache( new WP_REST_Response( array( 'token' => smi_make_token() ) ) );
		},
	) );
	register_rest_route( 'smartinquiry/v1', '/submit', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'smi_rest_submit',
	) );
} );

function smi_nocache( WP_REST_Response $r ) {
	$r->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
	return $r;
}

/** Cloudflare's published edge ranges (https://www.cloudflare.com/ips/). */
const SMI_CLOUDFLARE = array(
	'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
	'190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
	'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22', '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32',
	'2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
);

function smi_ip_in( $ip, $cidr ) {
	list( $net, $bits ) = explode( '/', $cidr );
	$a = @inet_pton( $ip );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$b = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( false === $a || false === $b || strlen( $a ) !== strlen( $b ) ) {
		return false;
	}
	$bits  = (int) $bits;
	$bytes = intdiv( $bits, 8 );
	if ( substr( $a, 0, $bytes ) !== substr( $b, 0, $bytes ) ) {
		return false;
	}
	$rest = $bits % 8;
	return 0 === $rest || ( ord( $a[ $bytes ] ) >> ( 8 - $rest ) ) === ( ord( $b[ $bytes ] ) >> ( 8 - $rest ) );
}

/**
 * The visitor's address. Behind Cloudflare the connection comes from Cloudflare, so its CF-Connecting-IP header is
 * used, but only when the connection really is from a Cloudflare address (anyone can send the header).
 * Other proxies can supply the real address with the smartinquiry_client_ip filter.
 */
function smi_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		foreach ( SMI_CLOUDFLARE as $range ) {
			if ( smi_ip_in( $ip, $range ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
				break;
			}
		}
	}
	$ip = (string) apply_filters( 'smartinquiry_client_ip', $ip );
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/** Key for the request limit. IPv6 visitors get a whole /64 each, so rotating addresses inside it doesn't help. */
function smi_rate_key( $kind ) {
	$ip = smi_client_ip();
	if ( '' === $ip ) {
		return '';
	}
	if ( false !== strpos( $ip, ':' ) ) {
		$bin = inet_pton( $ip );
		$ip  = bin2hex( substr( $bin, 0, 8 ) ) . '::/64';
	}
	return 'smi_' . $kind . '_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
}

/** Fixed one-hour window: [ count, window start ]. */
function smi_rate_count( $key ) {
	$v = $key ? get_transient( $key ) : false;
	return ( is_array( $v ) && isset( $v[0], $v[1] ) && time() - (int) $v[1] < HOUR_IN_SECONDS ) ? array( (int) $v[0], (int) $v[1] ) : array( 0, time() );
}

function smi_rate_limited( $kind, $max ) {
	$key = smi_rate_key( $kind );
	return $key && smi_rate_count( $key )[0] >= $max;
}

function smi_rate_hit( $kind ) {
	$key = smi_rate_key( $kind );
	if ( ! $key ) {
		return;
	}
	list( $n, $start ) = smi_rate_count( $key );
	set_transient( $key, array( $n + 1, $start ), max( 60, HOUR_IN_SECONDS - ( time() - $start ) ) );
}

function smi_make_token() {
	$t = time();
	$r = wp_generate_password( 16, false, false );
	return $t . '.' . $r . '.' . hash_hmac( 'sha256', $t . '|' . $r, wp_salt( 'nonce' ) );
}

/** Valid when signed by this site, at least 3 seconds old (people don't fill 5 steps faster), at most 3 hours, used once. */
function smi_check_token( $token ) {
	if ( ! is_string( $token ) || ! preg_match( '/^(\d{9,11})\.([A-Za-z0-9]{16})\.([a-f0-9]{64})$/', $token, $m ) ) {
		return false;
	}
	if ( ! hash_equals( hash_hmac( 'sha256', $m[1] . '|' . $m[2], wp_salt( 'nonce' ) ), $m[3] ) ) {
		return false;
	}
	$age = time() - (int) $m[1];
	if ( $age < 3 || $age > 3 * HOUR_IN_SECONDS ) {
		return false;
	}
	$used = 'smi_tok_' . substr( $m[3], 0, 32 );
	// wp_cache_add is atomic where a persistent object cache exists; the transient covers sites without one.
	if ( get_transient( $used ) || ( wp_using_ext_object_cache() && ! wp_cache_add( $used, 1, 'smartinquiry', 3 * HOUR_IN_SECONDS ) ) ) {
		return false;
	}
	set_transient( $used, 1, 3 * HOUR_IN_SECONDS );
	return true;
}

/** Text as typed: tags and control characters removed, a lone "<" kept as "<" (every output escapes it). */
function smi_clean_text( $v, $multiline = false ) {
	$v = $multiline ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
	return str_replace( '&lt;', '<', $v );
}

/** Addresses with ? & % = or quotes are refused: they would turn a mailto: link into one with hidden headers. */
function smi_valid_email( $email ) {
	return is_email( $email ) && ! preg_match( '/[?&%=\'"\s]/', $email );
}

function smi_err( $code, $status = 400, $field = '' ) {
	return smi_nocache( new WP_REST_Response( array( 'ok' => false, 'code' => $code, 'field' => $field ), $status ) );
}

function smi_limit( $v, $max ) {
	$v = is_scalar( $v ) ? (string) $v : '';
	return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
}

/**
 * Checks the answers of one step against its questions. Returns [ clean answers, clean "other" texts ] or a field id on error.
 */
function smi_validate_step( array $questions, array $answers, array $other ) {
	$clean = array();
	$texts = array();
	foreach ( $questions as $q ) {
		$id = $q['id'];
		if ( $q['show_if'] ) {
			$parent = isset( $clean[ $q['show_if']['q'] ] ) ? $clean[ $q['show_if']['q'] ] : null;
			if ( $parent !== $q['show_if']['opt'] ) {
				continue; // hidden: whatever was sent for it is dropped
			}
		}
		$v       = isset( $answers[ $id ] ) ? $answers[ $id ] : null;
		$allowed = wp_list_pluck( $q['opts'], 'id' );
		if ( $q['other'] ) {
			$allowed[] = 'other';
		}
		if ( 'single' === $q['type'] ) {
			$v = is_scalar( $v ) ? (string) $v : '';
			if ( '' === $v ) {
				if ( $q['req'] ) {
					return $id;
				}
				continue;
			}
			if ( ! in_array( $v, $allowed, true ) ) {
				return $id;
			}
			$clean[ $id ] = $v;
		} elseif ( 'multi' === $q['type'] ) {
			$v = is_array( $v ) ? array_values( array_unique( array_filter( $v, 'is_string' ) ) ) : array();
			if ( array_diff( $v, $allowed ) ) {
				return $id;
			}
			if ( ! $v ) {
				if ( $q['req'] ) {
					return $id;
				}
				continue;
			}
			$clean[ $id ] = $v;
		} else {
			$v = trim( 'textarea' === $q['type'] ? smi_clean_text( smi_limit( $v, 3000 ), true ) : smi_clean_text( smi_limit( $v, 500 ) ) );
			if ( '' === $v ) {
				if ( $q['req'] ) {
					return $id;
				}
				continue;
			}
			$clean[ $id ] = $v;
		}
		$picked = (array) $clean[ $id ];
		if ( $q['other'] && in_array( 'other', $picked, true ) && isset( $other[ $id ] ) ) {
			$o = trim( smi_clean_text( smi_limit( $other[ $id ], 300 ) ) );
			if ( '' !== $o ) {
				$texts[ $id ] = $o;
			}
		}
	}
	return array( $clean, $texts );
}

function smi_rest_submit( WP_REST_Request $req ) {
	$p = $req->get_json_params();
	if ( ! is_array( $p ) ) {
		return smi_err( 'invalid' );
	}
	// Bots fill every field; people never see this one.
	if ( '' !== smi_str( $p, 'website' ) ) {
		return smi_err( 'invalid' );
	}
	// The limit counts stored requests only, so a visitor fixing a mistake never locks themselves out.
	if ( smi_rate_limited( 'sent', SMI_RATE_LIMIT ) ) {
		return smi_err( 'rate', 429 );
	}
	if ( ! smi_check_token( smi_str( $p, 'token' ) ) ) {
		return smi_err( 'token', 403 );
	}

	$s    = smi_get_settings();
	$lang = sanitize_key( smi_str( $p, 'lang' ) );
	if ( ! in_array( $lang, smi_text_langs(), true ) ) {
		$lang = smi_default_lang();
	}
	$svc = smi_service( sanitize_key( smi_str( $p, 'service' ) ), $s );
	if ( ! $svc || ! $svc['on'] ) {
		return smi_err( 'invalid', 400, 'service' );
	}

	$answers = isset( $p['answers'] ) && is_array( $p['answers'] ) ? $p['answers'] : array();
	$other   = isset( $p['other'] ) && is_array( $p['other'] ) ? $p['other'] : array();
	$steps   = array();
	foreach ( array( 'details', 'features' ) as $step ) {
		$r = smi_validate_step( $svc[ $step ], $answers, $other );
		if ( ! is_array( $r ) ) {
			return smi_err( 'invalid', 400, $r );
		}
		$steps[ $step ] = $r;
	}

	$when   = smi_str( $p, 'when' );
	$budget = smi_str( $p, 'budget' );
	if ( ! in_array( $when, wp_list_pluck( $s['form']['when'], 'id' ), true ) ) {
		return smi_err( 'invalid', 400, 'when' );
	}
	if ( ! in_array( $budget, wp_list_pluck( $svc['budget'], 'id' ), true ) ) {
		return smi_err( 'invalid', 400, 'budget' );
	}
	$notes = trim( smi_clean_text( smi_limit( smi_str( $p, 'notes' ), 3000 ), true ) );

	$name    = trim( smi_clean_text( smi_limit( smi_str( $p, 'name' ), 190 ) ) );
	$email   = sanitize_email( smi_limit( smi_str( $p, 'email' ), 190 ) );
	$phone   = trim( preg_replace( '/[^0-9+()\-.\s\/]/', '', smi_limit( smi_str( $p, 'phone' ), 40 ) ) );
	$company = trim( smi_clean_text( smi_limit( smi_str( $p, 'company' ), 190 ) ) );
	$pref    = 'email' === smi_str( $p, 'pref' ) ? 'email' : ( 'phone' === smi_str( $p, 'pref' ) ? 'phone' : '' );
	if ( '' === $name ) {
		return smi_err( 'invalid', 400, 'name' );
	}
	if ( ! smi_valid_email( $email ) || $email !== trim( smi_str( $p, 'email' ) ) ) {
		return smi_err( 'invalid', 400, 'email' );
	}
	if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 10 ) {
		return smi_err( 'invalid', 400, 'phone' );
	}
	if ( '' === $pref ) {
		return smi_err( 'invalid', 400, 'pref' );
	}
	if ( true !== ( isset( $p['consent'] ) ? $p['consent'] : false ) ) {
		return smi_err( 'invalid', 400, 'consent' );
	}
	// Last of the checks: a Turnstile token works once, so it is only spent when everything else is right.
	if ( smi_turnstile_active( $s ) ) {
		$ts = smi_turnstile_verify( smi_str( $p, 'turnstile' ), $s );
		if ( 'ok' !== $ts ) {
			return 'down' === $ts ? smi_err( 'turnstile_down', 503 ) : smi_err( 'turnstile', 400 );
		}
	}

	$raw = array(
		'details'        => $steps['details'][0],
		'features'       => $steps['features'][0],
		'other'          => array_merge( $steps['details'][1], $steps['features'][1] ),
		'when'           => $when,
		'budget'         => $budget,
		'notes'          => $notes,
	);
	$row = smi_insert_request( array(
		'service'  => $svc['id'],
		'lang'     => $lang,
		'name'     => $name,
		'email'    => $email,
		'phone'    => $phone,
		'company'  => $company,
		'pref'     => $pref,
		'answers'  => $raw,
		'snapshot' => smi_build_snapshot( $svc, $raw, $s ),
		'ip'       => $s['privacy']['store_ip'] ? smi_client_ip() : '',
	) );
	if ( ! $row ) {
		return smi_err( 'server', 500 );
	}
	smi_rate_hit( 'sent' );

	smi_notify_after_response( $row['id'] );

	return smi_nocache( new WP_REST_Response( array(
		'ok'   => true,
		'ref'  => $row['ref'],
		'date' => get_date_from_gmt( $row['created_at'], 'd/m/Y' ),
		// The visitor's copy, drawn by the server (the browser makes it only when the server can't).
		'pdf'  => smi_pdf_available() ? smi_pdf_link( $row['id'] ) : '',
	) ) );
}

/**
 * The email (with its PDF) is sent once the visitor already has their answer, so a slow mail server never makes the
 * form wait or time out (which could make someone send the same request twice). Without PHP-FPM it simply runs at
 * the end of the request, as before.
 */
function smi_notify_after_response( $id ) {
	if ( ! apply_filters( 'smartinquiry_mail_after_response', true ) ) {
		$r = smi_get_request( $id );
		return $r ? smi_notify( $r ) : false;
	}
	register_shutdown_function( function () use ( $id ) {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		ignore_user_abort( true );
		$r = smi_get_request( $id );
		if ( $r ) {
			smi_notify( $r );
		}
	} );
}

/**
 * Human-readable copy of the answers in every language, taken at the moment of sending.
 * The admin, the email and the PDF read this, so renaming or deleting an option later never changes an old request.
 */
function smi_build_snapshot( array $svc, array $raw, array $s ) {
	$out = array();
	foreach ( smi_text_langs() as $lang ) {
		$t        = function ( $key ) use ( $s, $lang ) {
			return smi_tr( $s['texts'][ $key ], $lang );
		};
		$sections = array();
		foreach ( array( 'details' => 'step_details', 'features' => 'step_features' ) as $step => $title ) {
			$rows = array();
			foreach ( $svc[ $step ] as $q ) {
				if ( ! isset( $raw[ $step ][ $q['id'] ] ) ) {
					continue;
				}
				$rows[] = array( smi_short_label( smi_tr( $q['label'], $lang ) ), smi_answer_text( $q, $raw[ $step ][ $q['id'] ], isset( $raw['other'][ $q['id'] ] ) ? $raw['other'][ $q['id'] ] : '', $lang, $t( 'other' ) ) );
			}
			if ( $rows ) {
				$sections[] = array( 'key' => $step, 'title' => $t( $title ), 'rows' => $rows );
			}
		}
		$plan = array(
			array( smi_short_label( $t( 'q_when' ) ), smi_opt_label( $s['form']['when'], $raw['when'], $lang ) ),
			array( smi_short_label( $t( 'q_budget' ) ) . ' (' . $t( 'budget_hint' ) . ')', smi_opt_label( $svc['budget'], $raw['budget'], $lang ) ),
		);
		if ( '' !== $raw['notes'] ) {
			$plan[] = array( smi_short_label( $t( 'q_notes' ) ), $raw['notes'] );
		}
		$sections[]   = array( 'key' => 'plan', 'title' => $t( 'step_plan' ), 'rows' => $plan );
		$out[ $lang ] = array( 'service' => smi_tr( $svc['label'], $lang ), 'sections' => $sections );
	}
	return $out;
}

/** A question without its trailing question mark (Latin or Greek), for tables. Unicode-aware: rtrim() would cut Greek letters. */
function smi_short_label( $label ) {
	$out = preg_replace( '/[\s?;:\x{037E}]+$/u', '', trim( (string) $label ) );
	return null === $out ? trim( (string) $label ) : $out;
}

function smi_opt_label( array $opts, $id, $lang ) {
	foreach ( $opts as $o ) {
		if ( $o['id'] === $id ) {
			return smi_tr( $o['label'], $lang );
		}
	}
	return '';
}

function smi_answer_text( array $q, $value, $other_text, $lang, $other_label ) {
	if ( ! in_array( $q['type'], array( 'single', 'multi' ), true ) ) {
		return (string) $value;
	}
	$parts = array();
	foreach ( (array) $value as $id ) {
		$parts[] = 'other' === $id ? $other_label . ( '' !== $other_text ? ': ' . $other_text : '' ) : smi_opt_label( $q['opts'], $id, $lang );
	}
	return implode( ', ', array_filter( $parts, 'strlen' ) );
}

/** The snapshot of a request in one language (falling back to the language it was sent in), as flat rows. */
function smi_snapshot_lang( array $r, $lang ) {
	$snap = $r['snapshot'];
	if ( isset( $snap[ $lang ] ) ) {
		return $snap[ $lang ];
	}
	if ( isset( $snap[ $r['lang'] ] ) ) {
		return $snap[ $r['lang'] ];
	}
	return $snap ? reset( $snap ) : array( 'service' => $r['service'], 'sections' => array() );
}

function smi_snapshot_rows( array $r, $lang ) {
	$snap = smi_snapshot_lang( $r, $lang );
	$rows = array( array( __( 'Service', 'smartinquiry' ), isset( $snap['service'] ) ? $snap['service'] : '' ) );
	foreach ( isset( $snap['sections'] ) ? (array) $snap['sections'] : array() as $sec ) {
		foreach ( (array) $sec['rows'] as $row ) {
			$rows[] = array( (string) $row[0], (string) $row[1] );
		}
	}
	return $rows;
}

/* ---------- Email to the team ---------- */

/** Where notifications go: the addresses in the settings, or the site admin's when none is set. */
function smi_recipients() {
	$s = smi_get_settings();
	return $s['notify']['recipients'] ? $s['notify']['recipients'] : array( get_option( 'admin_email' ) );
}

function smi_notify( array $r ) {
	$s = smi_get_settings();
	if ( ! $s['notify']['enabled'] ) {
		return false;
	}
	$snap    = smi_snapshot_lang( $r, $r['lang'] );
	$subject = strtr( $s['notify']['subject'], array(
		'{service}' => $snap['service'],
		'{name}'    => $r['name'],
		'{ref}'     => $r['ref'],
	) );
	// No line breaks may reach a header.
	$subject = trim( preg_replace( '/[\r\n\t]+/', ' ', wp_specialchars_decode( $subject, ENT_QUOTES ) ) );
	$reply   = trim( preg_replace( '/[\r\n"<>,;]+/', ' ', $r['name'] ) );
	$headers = array( 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $reply . ' <' . $r['email'] . '>' );

	// The PDF goes along as an attachment, in a folder of its own so it keeps its readable name, then is removed.
	$attach = array();
	$dir    = '';
	if ( $s['notify']['attach'] ) {
		$doc   = smi_pdf_doc( $r );
		$bytes = smi_pdf_bytes( $doc );
		if ( '' !== $bytes ) {
			$dir = trailingslashit( get_temp_dir() ) . 'smi-' . wp_generate_password( 12, false, false );
			if ( wp_mkdir_p( $dir ) && false !== file_put_contents( $dir . '/' . $doc['filename'], $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				$attach[] = $dir . '/' . $doc['filename'];
			}
		}
	}
	$sent = wp_mail( smi_recipients(), $subject, smi_email_body( $r, $snap ), $headers, $attach );
	foreach ( $attach as $file ) {
		wp_delete_file( $file );
	}
	if ( $dir && is_dir( $dir ) ) {
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $sent;
}

function smi_email_body( array $r, array $snap ) {
	$s      = smi_get_settings();
	$accent = $s['look']['accent'];
	$url    = add_query_arg( array( 'page' => 'smartinquiry', 'request' => $r['id'] ), admin_url( 'admin.php' ) );
	$pref   = 'phone' === $r['pref'] ? __( 'Phone', 'smartinquiry' ) : __( 'Email', 'smartinquiry' );
	$row    = function ( $k, $v ) {
		return '<tr><td style="padding:8px 12px 8px 0;border-bottom:1px solid #eaecf0;color:#667085;width:40%;vertical-align:top">' . esc_html( $k ) . '</td><td style="padding:8px 0;border-bottom:1px solid #eaecf0;color:#101828;font-weight:600;vertical-align:top">' . nl2br( esc_html( $v ) ) . '</td></tr>';
	};
	$h  = '<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.5;color:#101828;max-width:620px">';
	$h .= '<div style="border-bottom:3px solid ' . esc_attr( $accent ) . ';padding-bottom:12px;margin-bottom:16px"><div style="color:#667085;font-size:13px">' . esc_html( $r['ref'] ) . '</div><div style="font-size:20px;font-weight:700">' . esc_html( $r['name'] ) . ' · ' . esc_html( $snap['service'] ) . '</div></div>';
	$h .= '<table style="width:100%;border-collapse:collapse;margin-bottom:18px">';
	$h .= $row( __( 'Email', 'smartinquiry' ), $r['email'] ) . $row( __( 'Phone', 'smartinquiry' ), $r['phone'] );
	if ( '' !== $r['company'] ) {
		$h .= $row( __( 'Company', 'smartinquiry' ), $r['company'] );
	}
	$h .= $row( __( 'Prefers to be contacted by', 'smartinquiry' ), $pref ) . $row( __( 'Form language', 'smartinquiry' ), strtoupper( $r['lang'] ) ) . '</table>';
	foreach ( isset( $snap['sections'] ) ? (array) $snap['sections'] : array() as $sec ) {
		$h .= '<div style="margin:18px 0 6px;color:#667085;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">' . esc_html( $sec['title'] ) . '</div><table style="width:100%;border-collapse:collapse">';
		foreach ( (array) $sec['rows'] as $x ) {
			$h .= $row( $x[0], $x[1] );
		}
		$h .= '</table>';
	}
	$h .= '<p style="margin-top:24px"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:10px 16px;border-radius:8px;background:' . esc_attr( $accent ) . ';color:#000;text-decoration:none;font-weight:600">' . esc_html__( 'Open in the admin', 'smartinquiry' ) . '</a></p>';
	$h .= '</div>';
	return $h;
}
