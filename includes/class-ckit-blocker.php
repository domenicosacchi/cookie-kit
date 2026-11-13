<?php
/**
 * Blocker — Auto-blocco script di terze parti finché non c'è consenso.
 * Funziona in due strati:
 *  1) Filtra l'output WP (script_loader_tag, wp_footer/header inline) sostituendo `src` e `type`
 *     con `data-ckit-src` e `type="text/plain"` per gli script noti come trackers.
 *  2) Filtra il contenuto post (the_content) per disinnescare iframe di YouTube/Vimeo/Maps,
 *     sostituendoli con placeholder click-to-load.
 * Dopo il consenso, lo script frontend (ckit-blocker.js) riattiva tutti gli elementi
 * marcati con data-ckit-* secondo le categorie acconsentite.
 *
 * @package CookieKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKIT_Blocker {

	private $vendors = array();

	public function __construct() {
		if ( ! CKIT_Core::settings( 'enabled', 1 ) ) {
			return;
		}
		// Snapshot mode: blocker disattivato per far partire script terzi nativi.
		if ( CKIT_Core::is_snapshot_mode() ) {
			return;
		}
		// ckit-blocker.js also exposes window.CKIT.applyConsent (Consent Mode v2 "update"),
		// so it must load even when auto-blocking is off.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_blocker' ) );

		if ( ! CKIT_Core::settings( 'auto_block', 1 ) ) {
			return;
		}
		$this->vendors = CKIT_Core::vendors();

		add_filter( 'script_loader_tag', array( $this, 'filter_script_tag' ), 10, 3 );
		add_filter( 'the_content', array( $this, 'filter_content_iframes' ), 999 );
		add_filter( 'wp_resource_hints', array( $this, 'filter_resource_hints' ), 10, 2 );
	}

	/**
	 * Remove dns-prefetch/preconnect hints for blocked vendor hosts, so the browser does not
	 * contact them before consent.
	 *
	 * @param array  $urls          Resource hint URLs.
	 * @param string $relation_type dns-prefetch, preconnect, etc.
	 * @return array
	 */
	public function filter_resource_hints( $urls, $relation_type ) {
		if ( ! in_array( $relation_type, array( 'dns-prefetch', 'preconnect' ), true ) ) {
			return $urls;
		}
		foreach ( $urls as $i => $hint ) {
			$href = is_array( $hint ) ? ( isset( $hint['href'] ) ? $hint['href'] : '' ) : $hint;
			if ( 0 === strpos( $href, '//' ) ) {
				$href = 'https:' . $href;
			}
			$category = $this->category_for_src( $href );
			if ( $category && 'necessary' !== $category ) {
				unset( $urls[ $i ] );
			}
		}
		return $urls;
	}

	public function enqueue_blocker() {
		wp_enqueue_script(
			'ckit-blocker',
			CKIT_PLUGIN_URL . 'public/js/ckit-blocker.js',
			array(),
			CKIT_VERSION,
			false // in <head>, non in footer.
		);
		wp_localize_script(
			'ckit-blocker',
			'CKIT_BLOCKER_CFG',
			array(
				'cookieName'    => CKIT_CONSENT_COOKIE,
				'policyVersion' => CKIT_Core::settings( 'policy_version', '1.0' ),
			)
		);
	}

	/**
	 * Sposta gli script di tracking conosciuti in stato "type=text/plain" finché non c'è consenso.
	 */
	public function filter_script_tag( $tag, $handle, $src ) {
		$category = $this->category_for_src( $src );
		if ( ! $category || 'necessary' === $category ) {
			return $tag;
		}
		// Sostituisci src con data-ckit-src e type con text/plain.
		$tag = preg_replace( '#\stype=(["\'])[^"\']*\1#i', '', $tag );
		$tag = preg_replace( '#<script(\s)#i', '<script type="text/plain" data-ckit-category="' . esc_attr( $category ) . '"$1', $tag, 1 );
		$tag = preg_replace( '#\ssrc=#i', ' data-ckit-src=', $tag );
		return $tag;
	}

	private function category_for_src( $src ) {
		if ( ! $src ) {
			return null;
		}
		foreach ( $this->vendors as $v ) {
			if ( empty( $v['pattern'] ) ) {
				continue;
			}
			if ( @preg_match( $v['pattern'], $src ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				return ! empty( $v['category'] ) ? $v['category'] : 'marketing';
			}
		}
		// Match per host generico.
		foreach ( $this->vendors as $v ) {
			if ( empty( $v['host'] ) ) {
				continue;
			}
			if ( false !== stripos( $src, $v['host'] ) ) {
				return ! empty( $v['category'] ) ? $v['category'] : 'marketing';
			}
		}
		return null;
	}

	/**
	 * Sostituisci iframe di tracker noti con placeholder click-to-load.
	 */
	public function filter_content_iframes( $content ) {
		if ( ! CKIT_Core::settings( 'block_iframes', 1 ) ) {
			return $content;
		}
		return preg_replace_callback(
			'#<iframe([^>]+)src=(["\'])([^"\']+)\2([^>]*)>(.*?)</iframe>#is',
			array( $this, 'replace_iframe' ),
			$content
		);
	}

	private function replace_iframe( $m ) {
		$attrs1 = $m[1];
		$src    = $m[3];
		$attrs2 = $m[4];
		$inner  = isset( $m[5] ) ? $m[5] : '';

		$category = $this->category_for_src( $src );
		if ( ! $category || 'necessary' === $category ) {
			return $m[0];
		}

		// Sostituisci src con data-ckit-src.
		$new = sprintf(
			'<div class="ckit-iframe-placeholder" data-ckit-category="%1$s" data-ckit-iframe-src="%2$s">'
				. '<div class="ckit-iframe-placeholder__inner">'
					. '<p class="ckit-iframe-placeholder__title">%3$s</p>'
					. '<p class="ckit-iframe-placeholder__desc">%4$s</p>'
					. '<button type="button" class="ckit-iframe-placeholder__btn" data-ckit-load-iframe="1">%5$s</button>'
					. '<button type="button" class="ckit-iframe-placeholder__manage" data-ckit-open-prefs="1">%6$s</button>'
				. '</div>'
			. '</div>',
			esc_attr( $category ),
			esc_url( $src ),
			esc_html__( 'Contenuto bloccato', 'cookie-kit' ),
			sprintf( esc_html__( 'Per visualizzare questo contenuto è necessario accettare i cookie della categoria %s.', 'cookie-kit' ), '<strong>' . esc_html( $category ) . '</strong>' ),
			esc_html__( 'Carica una sola volta', 'cookie-kit' ),
			esc_html__( 'Gestisci preferenze', 'cookie-kit' )
		);
		return $new;
	}
}
