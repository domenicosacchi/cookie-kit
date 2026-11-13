<?php
/**
 * Blocker — Auto-blocks third-party scripts until consent is given.
 * Works in two layers:
 *  1) Filters enqueued scripts (script_loader_tag), replacing `src` and `type` with
 *     `data-ckit-src` and `type="text/plain"` for scripts that match a configured vendor.
 *  2) Filters post content (the_content) to defuse YouTube/Vimeo/Maps iframes,
 *     replacing them with click-to-load placeholders.
 * After consent, the front-end script (ckit-blocker.js) re-activates every element
 * marked with data-ckit-* according to the accepted categories.
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
		// Snapshot mode: blocker disabled so that third-party scripts run natively.
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
			false // In <head>, not in the footer.
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
	 * Turn known tracking scripts into type="text/plain" until consent is given.
	 */
	public function filter_script_tag( $tag, $handle, $src ) {
		$category = $this->category_for_src( $src );
		if ( ! $category || 'necessary' === $category ) {
			return $tag;
		}
		// Replace src with data-ckit-src and type with text/plain.
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
		// Generic host match.
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
	 * Replace iframes of known trackers with click-to-load placeholders.
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
		$src = $m[3];

		$category = $this->category_for_src( $src );
		if ( ! $category || 'necessary' === $category ) {
			return $m[0];
		}

		$categories = CKIT_Core::categories();
		$cat_label  = isset( $categories[ $category ]['name'] ) ? $categories[ $category ]['name'] : $category;

		// Keep the original src in data-ckit-iframe-src; the iframe is created only after consent.
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
			esc_html__( 'Content blocked', 'cookie-kit' ),
			sprintf(
				/* translators: %s: cookie category name. */
				esc_html__( 'To view this content you need to accept cookies in the %s category.', 'cookie-kit' ),
				'<strong>' . esc_html( $cat_label ) . '</strong>'
			),
			esc_html__( 'Load once', 'cookie-kit' ),
			esc_html__( 'Manage preferences', 'cookie-kit' )
		);
		return $new;
	}
}
