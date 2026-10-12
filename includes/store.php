<?php
/**
 * Requests are kept in their own table: fast filtering and counting by status, service and date,
 * and nothing mixed into posts or post meta.
 */
defined( 'ABSPATH' ) || exit;

const SMI_STATUSES = array( 'new', 'contact', 'offer', 'won', 'lost' );

function smi_table() {
	global $wpdb;
	return $wpdb->prefix . 'smartinquiry_requests';
}

function smi_install_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = smi_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ref varchar(24) NOT NULL DEFAULT '',
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		service varchar(30) NOT NULL DEFAULT '',
		lang varchar(10) NOT NULL DEFAULT '',
		status varchar(12) NOT NULL DEFAULT 'new',
		is_read tinyint(1) NOT NULL DEFAULT 0,
		name varchar(190) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		phone varchar(40) NOT NULL DEFAULT '',
		company varchar(190) NOT NULL DEFAULT '',
		pref varchar(10) NOT NULL DEFAULT '',
		answers longtext NOT NULL,
		snapshot longtext NOT NULL,
		notes longtext NOT NULL,
		ip varchar(45) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY status (status),
		KEY service (service),
		KEY email (email)
	) {$charset};" );
	update_option( 'smartinquiry_db', SMI_DB_VERSION, false );
}

/** Saves a new request and gives it its reference (e.g. SU-2026-0148). Returns the row, or null. */
function smi_insert_request( array $row ) {
	global $wpdb;
	$ok = $wpdb->insert(
		smi_table(),
		array(
			'ref'        => '',
			'created_at' => current_time( 'mysql', true ),
			'service'    => $row['service'],
			'lang'       => $row['lang'],
			'status'     => 'new',
			'is_read'    => 0,
			'name'       => $row['name'],
			'email'      => $row['email'],
			'phone'      => $row['phone'],
			'company'    => $row['company'],
			'pref'       => $row['pref'],
			'answers'    => wp_json_encode( $row['answers'] ),
			'snapshot'   => wp_json_encode( $row['snapshot'] ),
			'notes'      => '',
			'ip'         => $row['ip'],
		),
		array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	if ( ! $ok ) {
		return null;
	}
	$id  = (int) $wpdb->insert_id;
	$s   = smi_get_settings();
	$ref = sprintf( '%s-%s-%04d', $s['ref_prefix'], gmdate( 'Y' ), $id );
	$wpdb->update( smi_table(), array( 'ref' => $ref ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	return smi_get_request( $id );
}

function smi_decode_row( $r ) {
	if ( ! $r ) {
		return null;
	}
	$r             = (array) $r;
	$r['id']       = (int) $r['id'];
	$r['is_read']  = isset( $r['is_read'] ) ? (int) $r['is_read'] : 0;
	// The list query leaves out the long columns it doesn't need.
	$answers       = isset( $r['answers'] ) ? json_decode( (string) $r['answers'], true ) : null;
	$snapshot      = isset( $r['snapshot'] ) ? json_decode( (string) $r['snapshot'], true ) : null;
	$r['answers']  = is_array( $answers ) ? $answers : array();
	$r['snapshot'] = is_array( $snapshot ) ? $snapshot : array();
	return $r;
}

function smi_get_request( $id ) {
	global $wpdb;
	return smi_decode_row( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . smi_table() . ' WHERE id = %d', $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * WHERE clause for the admin list. Every value goes through $wpdb->prepare.
 * $args: status, service, lang, q.
 */
function smi_where( array $args, $skip = '' ) {
	global $wpdb;
	$w = array( '1=1' );
	if ( 'status' !== $skip && ! empty( $args['status'] ) && in_array( $args['status'], SMI_STATUSES, true ) ) {
		$w[] = $wpdb->prepare( 'status = %s', $args['status'] );
	}
	if ( ! empty( $args['service'] ) ) {
		$w[] = $wpdb->prepare( 'service = %s', $args['service'] );
	}
	if ( ! empty( $args['lang'] ) ) {
		$w[] = $wpdb->prepare( 'lang = %s', $args['lang'] );
	}
	if ( isset( $args['q'] ) && '' !== trim( $args['q'] ) ) {
		$like = '%' . $wpdb->esc_like( trim( $args['q'] ) ) . '%';
		$w[]  = $wpdb->prepare( '(name LIKE %s OR email LIKE %s OR company LIKE %s OR ref LIKE %s OR phone LIKE %s)', $like, $like, $like, $like, $like );
	}
	return implode( ' AND ', $w );
}

/** One page of requests, newest first, without the long columns. */
function smi_list_requests( array $args, $page, $per_page ) {
	global $wpdb;
	$t      = smi_table();
	$where  = smi_where( $args );
	$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	$offset = max( 0, ( $page - 1 ) * $per_page );
	$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT id, ref, created_at, service, lang, status, is_read, name, email, phone, company, snapshot FROM {$t} WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	return array( 'total' => $total, 'rows' => array_map( 'smi_decode_row', $rows ? $rows : array() ) );
}

/** Counts for the tabs (per status, with the other filters applied), the side menu (per service) and the cards. */
function smi_counts( array $args ) {
	global $wpdb;
	$t        = smi_table();
	$where    = smi_where( $args, 'status' );
	$status   = array_fill_keys( SMI_STATUSES, 0 );
	$by_state = $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$t} WHERE {$where} GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	foreach ( (array) $by_state as $r ) {
		if ( isset( $status[ $r['status'] ] ) ) {
			$status[ $r['status'] ] = (int) $r['n'];
		}
	}
	$service = array();
	foreach ( (array) $wpdb->get_results( "SELECT service, COUNT(*) n FROM {$t} GROUP BY service", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
		$service[ $r['service'] ] = (int) $r['n'];
	}
	$now   = time();
	$week  = gmdate( 'Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS );
	$month = gmdate( 'Y-m-01 00:00:00', $now );
	$all   = array_fill_keys( SMI_STATUSES, 0 );
	foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$t} GROUP BY status", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
		if ( isset( $all[ $r['status'] ] ) ) {
			$all[ $r['status'] ] = (int) $r['n'];
		}
	}
	return array(
		'status'  => $status,
		'service' => $service,
		'cards'   => array(
			'new'   => $all['new'],
			'week'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE created_at >= %s", $week ) ), // phpcs:ignore WordPress.DB.PreparedSQL
			'month' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE created_at >= %s", $month ) ), // phpcs:ignore WordPress.DB.PreparedSQL
			'won'   => $all['won'],
			'lost'  => $all['lost'],
		),
	);
}

function smi_update_request( $id, array $fields ) {
	global $wpdb;
	$data = array();
	$fmt  = array();
	if ( isset( $fields['status'] ) && in_array( $fields['status'], SMI_STATUSES, true ) ) {
		$data['status'] = $fields['status'];
		$fmt[]          = '%s';
	}
	if ( isset( $fields['notes'] ) ) {
		$data['notes'] = smi_clean_text( (string) $fields['notes'], true );
		$fmt[]         = '%s';
	}
	if ( isset( $fields['is_read'] ) ) {
		$data['is_read'] = $fields['is_read'] ? 1 : 0;
		$fmt[]           = '%d';
	}
	return $data ? false !== $wpdb->update( smi_table(), $data, array( 'id' => (int) $id ), $fmt, array( '%d' ) ) : false;
}

function smi_delete_requests( array $ids ) {
	global $wpdb;
	$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
	if ( ! $ids ) {
		return 0;
	}
	$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . smi_table() . " WHERE id IN ({$in})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/* ---------- Daily clean-up: old requests (retention setting) and nothing else ---------- */

add_action( 'smi_daily', function () {
	global $wpdb;
	$months = (int) smi_get_settings()['privacy']['retention'];
	if ( $months > 0 ) {
		$before = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $months . ' months' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . smi_table() . ' WHERE created_at < %s', $before ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
} );

/* ---------- WordPress privacy tools: export and erase by email address ---------- */

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
	$exporters['smartinquiry'] = array(
		'exporter_friendly_name' => __( 'SmartInquiry requests', 'smartinquiry' ),
		'callback'               => function ( $email, $page = 1 ) {
			global $wpdb;
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . smi_table() . ' WHERE email = %s ORDER BY id', $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
			$items = array();
			foreach ( (array) $rows as $r ) {
				$r    = smi_decode_row( $r );
				$data = array(
					array( 'name' => __( 'Reference', 'smartinquiry' ), 'value' => $r['ref'] ),
					array( 'name' => __( 'Date', 'smartinquiry' ), 'value' => $r['created_at'] . ' UTC' ),
					array( 'name' => __( 'Name', 'smartinquiry' ), 'value' => $r['name'] ),
					array( 'name' => __( 'Email', 'smartinquiry' ), 'value' => $r['email'] ),
					array( 'name' => __( 'Phone', 'smartinquiry' ), 'value' => $r['phone'] ),
					array( 'name' => __( 'Company', 'smartinquiry' ), 'value' => $r['company'] ),
				);
				if ( '' !== $r['ip'] ) {
					$data[] = array( 'name' => __( 'IP address', 'smartinquiry' ), 'value' => $r['ip'] );
				}
				if ( '' !== $r['notes'] ) {
					$data[] = array( 'name' => __( 'Internal notes', 'smartinquiry' ), 'value' => $r['notes'] );
				}
				foreach ( smi_snapshot_rows( $r, $r['lang'] ) as $row ) {
					$data[] = array( 'name' => $row[0], 'value' => $row[1] );
				}
				$items[] = array( 'group_id' => 'smartinquiry', 'group_label' => __( 'Quote requests', 'smartinquiry' ), 'item_id' => 'smartinquiry-' . $r['id'], 'data' => $data );
			}
			return array( 'data' => $items, 'done' => true );
		},
	);
	return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
	$erasers['smartinquiry'] = array(
		'eraser_friendly_name' => __( 'SmartInquiry requests', 'smartinquiry' ),
		'callback'             => function ( $email, $page = 1 ) {
			global $wpdb;
			$n = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . smi_table() . ' WHERE email = %s', $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL
			return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
		},
	);
	return $erasers;
} );
