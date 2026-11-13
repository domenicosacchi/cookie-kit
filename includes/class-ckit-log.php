<?php
/**
 * Consent log — CRUD for the table of collected consents.
 * Stores: timestamp, consent UUID, choice, accepted categories, IP hash, user agent, URL, language.
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

		// Store only a salted hash of the IP address, never the plain IP.
		if ( empty( $row['ip_hash'] ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip            = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			$row['ip_hash'] = hash( 'sha256', $ip . wp_salt() );
		}

		// Respect column sizes (strict SQL modes reject oversized values).
		$row['page_url']       = substr( (string) $row['page_url'], 0, 500 );
		$row['user_agent']     = substr( (string) $row['user_agent'], 0, 500 );
		$row['language']       = substr( (string) $row['language'], 0, 10 );
		$row['policy_version'] = substr( (string) $row['policy_version'], 0, 20 );

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
		$columns = array( 'id', 'created_at', 'consent_uuid', 'action', 'categories', 'policy_version', 'page_url', 'ip_hash', 'language' );
		$handle  = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $handle, $columns, ',', '"', '' );
		foreach ( $rows as $r ) {
			$line = array();
			foreach ( $columns as $col ) {
				$line[] = $this->csv_safe( isset( $r[ $col ] ) ? (string) $r[ $col ] : '' );
			}
			fputcsv( $handle, $line, ',', '"', '' );
		}
		rewind( $handle );
		$out = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $out;
	}

	/**
	 * Neutralize spreadsheet formula injection (cells starting with = + - @ tab or CR).
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private function csv_safe( $value ) {
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			return "'" . $value;
		}
		return $value;
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
