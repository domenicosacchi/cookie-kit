<?php
/**
 * Consent log — CRUD per la tabella dei consensi raccolti.
 * Conserva: timestamp, uuid consenso, scelta, categorie attive, hash IP, UA, URL, lingua.
 *
 * @package CookieKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKIT_Log {

	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'ckit_consent_log';
	}

	public function insert( $data ) {
		global $wpdb;
		$defaults = array(
			'created_at'     => current_time( 'mysql', true ),
			'consent_uuid'   => wp_generate_uuid4(),
			'action'         => 'unknown',
			'categories'     => '',
			'policy_version' => CKIT_Core::settings( 'policy_version', '1.0' ),
			'page_url'       => '',
			'ip_hash'        => '',
			'user_agent'     => '',
			'language'       => '',
			'expires_at'     => null,
		);
		$row = wp_parse_args( $data, $defaults );

		// Hash IP (no IP in chiaro).
		if ( empty( $row['ip_hash'] ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip            = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			$row['ip_hash'] = hash( 'sha256', $ip . wp_salt() );
		}

		if ( is_array( $row['categories'] ) ) {
			$row['categories'] = wp_json_encode( $row['categories'] );
		}

		$wpdb->insert( $this->table(), $row ); // phpcs:ignore WordPress.DB
		return $wpdb->insert_id;
	}

	public function get_recent( $limit = 100, $offset = 0, $filters = array() ) {
		global $wpdb;
		$table  = $this->table();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['action'] ) ) {
			$where[]  = 'action = %s';
			$params[] = $filters['action'];
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $filters['date_to'];
		}

		$where_sql = implode( ' AND ', $where );
		$params[]  = (int) $limit;
		$params[]  = (int) $offset;

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public function count( $filters = array() ) {
		global $wpdb;
		$table  = $this->table();
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $filters['action'] ) ) {
			$where[]  = 'action = %s';
			$params[] = $filters['action'];
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $filters['date_to'];
		}
		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	public function purge_older_than( $days ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * absint( $days ) );
		$table  = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	public function export_csv() {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
		$out  = "id,created_at,consent_uuid,action,categories,policy_version,page_url,ip_hash,language\n";
		foreach ( $rows as $r ) {
			$out .= sprintf(
				"%d,%s,%s,%s,\"%s\",%s,%s,%s,%s\n",
				$r['id'],
				$r['created_at'],
				$r['consent_uuid'],
				$r['action'],
				str_replace( '"', '""', $r['categories'] ),
				$r['policy_version'],
				$r['page_url'],
				$r['ip_hash'],
				$r['language']
			);
		}
		return $out;
	}

	public function export_json() {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
		foreach ( $rows as &$r ) {
			$cat = json_decode( $r['categories'], true );
			if ( is_array( $cat ) ) {
				$r['categories'] = $cat;
			}
		}
		return wp_json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}
}
