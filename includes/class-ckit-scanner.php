<?php
/**
 * Scanner — Scansione passiva del sito per individuare i cookie installati.
 * Tecnica: GET di una serie di URL del sito tramite wp_remote_get(), parsing degli header
 * `Set-Cookie` e parsing dell'HTML alla ricerca di script terze parti riconosciuti.
 * NB: per cookie JS lazy-loaded servirebbe headless browser; questa scansione coglie i cookie
 * impostati lato server e i vendor caricati nell'HTML.
 *
 * @package CookieKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKIT_Scanner {

	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'ckit_cookie_scan';
	}

	public function scan_urls( array $urls ) {
		$result = array(
			'urls_scanned' => 0,
			'cookies'      => array(),
			'vendors'      => array(),
			'errors'       => array(),
		);

		foreach ( $urls as $url ) {
			if ( ! $this->is_own_url( $url ) ) {
				continue;
			}
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'     => 15,
					'redirection' => 3,
					// Loopback request to the site itself: same SSL policy as WordPress core loopbacks.
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
					'user-agent'  => 'CookieKit-Scanner/' . CKIT_VERSION,
				)
			);

			if ( is_wp_error( $response ) ) {
				$result['errors'][] = array(
					'url'   => $url,
					'error' => $response->get_error_message(),
				);
				continue;
			}

			$result['urls_scanned']++;

			// Parse Set-Cookie headers.
			$cookies = wp_remote_retrieve_cookies( $response );
			if ( is_array( $cookies ) ) {
				foreach ( $cookies as $cookie ) {
					$name   = $cookie->name;
					$domain = ! empty( $cookie->domain ) ? $cookie->domain : wp_parse_url( $url, PHP_URL_HOST );
					$expire = ! empty( $cookie->expires ) ? gmdate( 'Y-m-d', $cookie->expires ) : 'Sessione';
					$result['cookies'][ $name . '|' . $domain ] = array(
						'name'     => $name,
						'domain'   => $domain,
						'expires'  => $expire,
						'source'   => $url,
					);
				}
			}

			// Parse HTML body per trovare vendor terzi.
			$body    = wp_remote_retrieve_body( $response );
			$vendors = CKIT_Core::vendors();
			foreach ( $vendors as $v ) {
				if ( empty( $v['host'] ) ) {
					continue;
				}
				if ( false !== stripos( $body, $v['host'] ) ) {
					$result['vendors'][ $v['name'] ] = array(
						'name'     => $v['name'],
						'category' => isset( $v['category'] ) ? $v['category'] : 'marketing',
						'cookies'  => isset( $v['cookies'] ) ? $v['cookies'] : '',
					);
				}
			}
		}

		// Persist into DB.
		$this->save_scan( $result );
		return $result;
	}

	private function save_scan( $result ) {
		global $wpdb;
		$table = $this->table();
		foreach ( $result['cookies'] as $c ) {
			// Try to classify based on vendors map.
			$category = $this->classify_cookie( $c['name'], $c['domain'] );
			$wpdb->replace( // phpcs:ignore WordPress.DB
				$table,
				array(
					'scanned_at'    => current_time( 'mysql', true ),
					'cookie_name'   => $c['name'],
					'cookie_domain' => $c['domain'],
					'category'      => $category['category'],
					'party'         => $category['party'],
					'vendor'        => $category['vendor'],
					'duration'      => $c['expires'],
					'source_url'    => $c['source'],
				)
			);
		}
	}

	private function classify_cookie( $name, $domain ) {
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$party     = ( $domain && false !== stripos( $domain, $home_host ) ) ? 'first' : 'third';
		$vendors   = CKIT_Core::vendors();
		foreach ( $vendors as $v ) {
			if ( empty( $v['cookies'] ) ) {
				continue;
			}
			$patterns = array_map( 'trim', explode( ',', $v['cookies'] ) );
			foreach ( $patterns as $p ) {
				$regex = '/^' . str_replace( '\\*', '.*', preg_quote( $p, '/' ) ) . '$/i';
				if ( preg_match( $regex, $name ) ) {
					return array(
						'category' => isset( $v['category'] ) ? $v['category'] : 'unclassified',
						'party'    => $party,
						'vendor'   => $v['name'],
					);
				}
			}
		}
		// Match per dominio del cookie con host di un vendor configurato.
		// Es: cookie su `.youtube.com` → vendor "YouTube Embed" (host youtube.com) → categoria marketing.
		if ( $domain ) {
			$d = ltrim( strtolower( $domain ), '.' );
			foreach ( $vendors as $v ) {
				if ( empty( $v['host'] ) ) {
					continue;
				}
				$h = strtolower( $v['host'] );
				if ( false !== strpos( $d, $h ) || false !== strpos( $h, $d ) ) {
					return array(
						'category' => isset( $v['category'] ) ? $v['category'] : 'unclassified',
						'party'    => $party,
						'vendor'   => $v['name'],
					);
				}
			}
		}

		// First-party: cookie tecnici WP/PHP/CKIT.
		if ( 'first' === $party && preg_match( '/^(PHPSESSID|wordpress|wp-|wp_|ckit_)/i', $name ) ) {
			return array(
				'category' => 'necessary',
				'party'    => $party,
				'vendor'   => 'WordPress / Sito',
			);
		}
		// Cloudflare (sicurezza, anti-bot, load balancing): tecnici, esenti da consenso ex art. 122 Codice Privacy.
		if ( preg_match( '/^(__cf_bm|__cflb|__cfduid|__cfwaitingroom|cf_clearance|cf_chl_.*|cf_ob_info|cf_use_ob)$/i', $name ) ) {
			return array(
				'category' => 'necessary',
				'party'    => $party,
				'vendor'   => 'Cloudflare',
			);
		}
		// Token CSRF / sicurezza form (tipici di framework PHP, Symfony, Laravel, ecc.).
		if ( preg_match( '/^(_csrf|XSRF-TOKEN|csrf_token|csrftoken|laravel_session|symfony_)/i', $name ) ) {
			return array(
				'category' => 'necessary',
				'party'    => $party,
				'vendor'   => 'Sicurezza form / CSRF',
			);
		}
		// Cookie WooCommerce (sessione carrello, checkout): tecnici.
		if ( 'first' === $party && preg_match( '/^(woocommerce_|wc_|tk_)/i', $name ) ) {
			return array(
				'category' => 'necessary',
				'party'    => $party,
				'vendor'   => 'WooCommerce',
			);
		}
		return array(
			'category' => 'unclassified',
			'party'    => $party,
			'vendor'   => '',
		);
	}

	public function get_all() {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY category, cookie_name" );
	}

	/**
	 * Vendor catalog enrichment: dopo lo snapshot client-side, fetch dell'HTML server-side
	 * per rilevare vendor presenti (iframe YouTube, script GTM, embed Vimeo, ecc.) e aggiungere
	 * automaticamente i loro cookie dichiarati come third-party.
	 * Necessario perché il browser per Same-Origin Policy non espone a JavaScript i cookie
	 * dei domini terzi (es. .youtube.com), quindi lo snapshot client da solo li perde.
	 *
	 * @param string $page_url URL della pagina da analizzare.
	 * @return int numero di righe aggiunte.
	 */
	public function enrich_from_html( $page_url ) {
		// Only fetch pages of this site (prevents server-side requests to arbitrary hosts).
		if ( empty( $page_url ) || ! $this->is_own_url( $page_url ) ) {
			return 0;
		}
		$response = wp_safe_remote_get(
			$page_url,
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
				'user-agent'  => 'CookieKit-Scanner/' . CKIT_VERSION,
			)
		);
		if ( is_wp_error( $response ) ) {
			return 0;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return 0;
		}

		global $wpdb;
		$table   = $this->table();
		$vendors = CKIT_Core::vendors();
		$count   = 0;

		foreach ( $vendors as $v ) {
			if ( empty( $v['host'] ) ) {
				continue;
			}
			$host  = $v['host'];
			$found = false;
			// Match host nell'HTML (iframe src, script src, link href).
			if ( false !== stripos( $body, $host ) ) {
				$found = true;
			}
			// Match anche pattern regex se definito.
			if ( ! $found && ! empty( $v['pattern'] ) ) {
				if ( @preg_match( $v['pattern'], $body ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
					$found = true;
				}
			}
			if ( ! $found ) {
				continue;
			}
			// Vendor presente nella pagina: aggiungi i suoi cookie dichiarati alla tabella scan.
			if ( empty( $v['cookies'] ) ) {
				continue;
			}
			$cookie_names = array_filter( array_map( 'trim', explode( ',', $v['cookies'] ) ) );
			$category     = isset( $v['category'] ) ? $v['category'] : 'unclassified';
			$vendor_name  = isset( $v['name'] ) ? $v['name'] : '';
			$cookie_dom   = '.' . preg_replace( '#^(www\.)?#', '', $host );

			foreach ( $cookie_names as $name ) {
				// Espandi pattern wildcard *: per ora salta (li tratta solo classify_cookie via regex).
				if ( false !== strpos( $name, '*' ) ) {
					continue;
				}
				$wpdb->replace( // phpcs:ignore WordPress.DB
					$table,
					array(
						'scanned_at'    => current_time( 'mysql', true ),
						'cookie_name'   => $name,
						'cookie_domain' => $cookie_dom,
						'category'      => $category,
						'party'         => 'third',
						'vendor'        => $vendor_name,
						'duration'      => '—',
						'source_url'    => $page_url,
					)
				);
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Salva uno snapshot di cookie raccolti dal browser dell'admin loggato (scanner client-side).
	 * Per ogni cookie applica classify_cookie() per determinare categoria, parte, vendor.
	 *
	 * @param array  $cookies array di nomi cookie (stringhe), oppure array di array con chiavi 'name' (e opzionali 'domain', 'expires').
	 * @param string $page_url URL della pagina visitata.
	 * @return int numero di righe inserite/aggiornate.
	 */
	public function save_snapshot( array $cookies, $page_url = '' ) {
		global $wpdb;
		$table = $this->table();
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$count = 0;
		foreach ( $cookies as $entry ) {
			if ( is_string( $entry ) ) {
				$name   = sanitize_text_field( $entry );
				$domain = $home_host;
				$expire = '';
			} elseif ( is_array( $entry ) ) {
				$name   = isset( $entry['name'] ) ? sanitize_text_field( $entry['name'] ) : '';
				$domain = isset( $entry['domain'] ) ? sanitize_text_field( $entry['domain'] ) : $home_host;
				$expire = isset( $entry['expires'] ) ? sanitize_text_field( $entry['expires'] ) : '';
			} else {
				continue;
			}
			if ( '' === $name ) {
				continue;
			}
			$class = $this->classify_cookie( $name, $domain );
			$wpdb->replace( // phpcs:ignore WordPress.DB
				$table,
				array(
					'scanned_at'    => current_time( 'mysql', true ),
					'cookie_name'   => $name,
					'cookie_domain' => $domain,
					'category'      => $class['category'],
					'party'         => $class['party'],
					'vendor'        => $class['vendor'],
					'duration'      => $expire ? $expire : '—',
					'source_url'    => $page_url,
				)
			);
			$count++;
		}
		return $count;
	}

	/**
	 * Whether a URL points to this site (same host as home_url()).
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	public function is_own_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host && $home && strtolower( $host ) === strtolower( $home );
	}

	public function clear() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->query( "TRUNCATE TABLE {$this->table()}" );
	}

	public function default_urls() {
		$urls   = array( home_url( '/' ) );
		$pages  = get_pages( array( 'number' => 5 ) );
		foreach ( $pages as $p ) {
			$urls[] = get_permalink( $p );
		}
		$posts = get_posts( array( 'numberposts' => 3 ) );
		foreach ( $posts as $p ) {
			$urls[] = get_permalink( $p );
		}
		return array_unique( array_filter( $urls ) );
	}
}
