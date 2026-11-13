<?php
/**
 * Banner — Output del banner front-end e del modale di personalizzazione.
 * Inietta variabili JS/CSS, gestisce shortcode [ckit_button].
 *
 * @package CookieKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKIT_Banner {

	public function __construct() {
		if ( ! CKIT_Core::settings( 'enabled', 1 ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_head', array( $this, 'inline_consent_mode_default' ), 1 );
		add_action( 'wp_footer', array( $this, 'render' ), 99 );
		add_shortcode( 'ckit_button', array( $this, 'shortcode_button' ) );
	}

	public function enqueue() {
		wp_register_style(
			'ckit-banner',
			CKIT_PLUGIN_URL . 'public/css/ckit-banner.css',
			array(),
			CKIT_VERSION
		);
		wp_register_script(
			'ckit-banner',
			CKIT_PLUGIN_URL . 'public/js/ckit-banner.js',
			array(),
			CKIT_VERSION,
			true
		);

		$config = array(
			'cookieName'       => CKIT_CONSENT_COOKIE,
			'repromptDays'     => absint( CKIT_Core::settings( 'reprompt_days', CKIT_REPROMPT_DAYS ) ),
			'autoBlock'        => (bool) CKIT_Core::settings( 'auto_block', 1 ),
			'consentModeV2'    => (bool) CKIT_Core::settings( 'consent_mode_v2', 1 ),
			'policyVersion'    => CKIT_Core::settings( 'policy_version', '1.0' ),
			'restUrl'          => esc_url_raw( rest_url( 'cookiekit/v1/consent' ) ),
			'restNonce'        => wp_create_nonce( 'wp_rest' ),
			'categories'       => CKIT_Core::categories(),
			'vendors'          => CKIT_Core::vendors(),
			'cookiesByCategory' => $this->cookies_by_category(),
			'texts'            => CKIT_Core::texts(),
			'showFloating'     => (bool) CKIT_Core::settings( 'show_floating_button', 1 ),
			'floatingPos'      => CKIT_Core::settings( 'floating_position', 'bottom-left' ),
			'theme'            => CKIT_Core::settings( 'theme', 'light' ),
			'primaryColor'     => CKIT_Core::settings( 'primary_color', '#0a3d62' ),
			'acceptColor'      => CKIT_Core::settings( 'accept_color', '#0a3d62' ),
			'rejectColor'      => CKIT_Core::settings( 'reject_color', '#6c757d' ),
			'showLogo'         => (bool) CKIT_Core::settings( 'show_logo', 0 ),
			'logoUrl'          => esc_url( CKIT_Core::settings( 'logo_url', '' ) ),
			'logoMaxHeight'    => absint( CKIT_Core::settings( 'logo_max_height', 40 ) ),
			'banner'           => array(
				'position' => CKIT_Core::settings( 'banner_position', 'bottom' ),
				'layout'   => CKIT_Core::settings( 'banner_layout', 'box' ),
			),
			'privacyPolicyUrl' => CKIT_Core::settings( 'privacy_policy_url', '/informativa-privacy/' ),
			'cookiePolicyUrl'  => CKIT_Core::settings( 'cookie_policy_url', '/cookie-policy/' ),
			'lang'             => substr( get_locale(), 0, 2 ),
		);
		wp_localize_script( 'ckit-banner', 'CKIT_CFG', $config );

		wp_enqueue_style( 'ckit-banner' );
		wp_enqueue_script( 'ckit-banner' );

		// Scanner client-side: si attiva solo se admin loggato e URL contiene ?ckit-snapshot=1.
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['ckit-snapshot'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['ckit-snapshot'] ) ) && current_user_can( 'manage_options' ) ) {
			wp_register_script(
				'ckit-snapshot',
				CKIT_PLUGIN_URL . 'public/js/ckit-snapshot.js',
				array(),
				CKIT_VERSION,
				true
			);
			$cats = array_keys( CKIT_Core::categories() );
			// Lista URL da scansionare in sequenza: home + 4 pagine/post random per intercettare cookie installati solo su template specifici (es. articoli con embed YouTube).
			$urls = array( home_url( '/' ) );
			$pages = get_pages( array( 'number' => 2 ) );
			foreach ( $pages as $p ) {
				$urls[] = get_permalink( $p );
			}
			$posts = get_posts( array( 'numberposts' => 2, 'orderby' => 'rand' ) );
			foreach ( $posts as $p ) {
				$urls[] = get_permalink( $p );
			}
			$urls = array_values( array_unique( array_filter( $urls ) ) );
			// Aggiungi parametro snapshot a tutti gli URL
			$urls = array_map(
				function ( $u ) {
					return add_query_arg( 'ckit-snapshot', '1', $u );
				},
				$urls
			);
			wp_localize_script(
				'ckit-snapshot',
				'CKIT_SNAP',
				array(
					'restUrl'       => esc_url_raw( rest_url( 'cookiekit/v1/snapshot' ) ),
					'restNonce'     => wp_create_nonce( 'wp_rest' ),
					'cookieName'    => CKIT_CONSENT_COOKIE,
					'policyVersion' => CKIT_Core::settings( 'policy_version', '1.0' ),
					'categories'    => $cats,
					'waitSeconds'   => 5,
					'adminUrl'      => esc_url_raw( admin_url( 'admin.php?page=cookie-kit&tab=scanner' ) ),
					'pages'         => array_values( $urls ),
				)
			);
			wp_enqueue_script( 'ckit-snapshot' );
		}

		// Inline CSS variables (theming).
		$css = sprintf(
			':root{--ckit-primary:%s;--ckit-accept:%s;--ckit-reject:%s;}',
			esc_attr( CKIT_Core::settings( 'primary_color', '#0a3d62' ) ),
			esc_attr( CKIT_Core::settings( 'accept_color', '#0a3d62' ) ),
			esc_attr( CKIT_Core::settings( 'reject_color', '#6c757d' ) )
		);
		wp_add_inline_style( 'ckit-banner', $css );
	}

	/**
	 * Inietta Google Consent Mode v2 in modalità default (denied) prima di GTM/GA.
	 * Va inserito molto in alto in <head>, prima di qualunque script di tracciamento.
	 */
	public function inline_consent_mode_default() {
		if ( ! CKIT_Core::settings( 'consent_mode_v2', 1 ) ) {
			return;
		}
		// Snapshot mode: tutti i segnali partono granted così script di tracking installano cookie nativi.
		if ( CKIT_Core::is_snapshot_mode() ) {
			?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
  'ad_storage': 'granted',
  'ad_user_data': 'granted',
  'ad_personalization': 'granted',
  'analytics_storage': 'granted',
  'functionality_storage': 'granted',
  'personalization_storage': 'granted',
  'security_storage': 'granted'
});
</script>
			<?php
			return;
		}
		?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
  'ad_storage': 'denied',
  'ad_user_data': 'denied',
  'ad_personalization': 'denied',
  'analytics_storage': 'denied',
  'functionality_storage': 'granted',
  'personalization_storage': 'denied',
  'security_storage': 'granted',
  'wait_for_update': 500
});
gtag('set', 'ads_data_redaction', true);
gtag('set', 'url_passthrough', true);
</script>
		<?php
	}

	public function render() {
		// Snapshot mode: niente banner, lo snapshot script lavora senza interferenze.
		if ( CKIT_Core::is_snapshot_mode() ) {
			echo '<div id="ckit-root" aria-hidden="true"></div>';
			return;
		}
		echo '<div id="ckit-root" aria-hidden="true"></div>';
	}

	/**
	 * Costruisce per ogni categoria la lista dei cookie da mostrare nel modale di personalizzazione.
	 * Fonte di verità: tabella wp_ckit_cookie_scan (cookie REALI rilevati dallo scanner).
	 * Il dizionario interno (cookie_meta_dictionary) arricchisce solo durata e finalità per cookie noti.
	 * Niente cookie "dichiarati" nei vendor: il banner mostra solo cookie effettivamente rilevati.
	 *
	 * @return array { cat_id => [ {name, vendor, duration, description, party}, ... ] }
	 */
	public function cookies_by_category() {
		$categories = CKIT_Core::categories();
		$meta_dict  = $this->cookie_meta_dictionary();
		$out        = array();

		foreach ( $categories as $cid => $cat ) {
			$out[ $cid ] = array();
		}

		// Fonte unica: cookie effettivamente rilevati dallo Scanner (passivo + client-side).
		global $wpdb;
		$scan_table = $wpdb->prefix . 'ckit_cookie_scan';
		$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $scan_table ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $exists ) {
			$rows = $wpdb->get_results( "SELECT cookie_name, cookie_domain, category, vendor, duration, party FROM {$scan_table} ORDER BY category, cookie_name" ); // phpcs:ignore WordPress.DB
			if ( $rows ) {
				$seen = array();
				foreach ( $rows as $r ) {
					$cid = $r->category;
					if ( ! isset( $out[ $cid ] ) || 'unclassified' === $cid ) {
						continue;
					}
					$key = $cid . '|' . $r->cookie_name;
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ] = true;
					$lookup       = $this->lookup_cookie_meta( $r->cookie_name, $meta_dict );
					$out[ $cid ][] = array(
						'name'        => $r->cookie_name,
						'vendor'      => $r->vendor ? $r->vendor : ( $r->cookie_domain ? $r->cookie_domain : '—' ),
						'duration'    => $r->duration && '—' !== $r->duration ? $r->duration : $lookup['duration'],
						'description' => $lookup['description'],
						'party'       => $r->party ? $r->party : 'first',
					);
				}
			}
		}

		// Fallback necessary: il cookie di consenso del plugin deve sempre comparire.
		if ( isset( $out['necessary'] ) && ! $this->has_cookie( $out['necessary'], CKIT_CONSENT_COOKIE ) ) {
			$out['necessary'][] = array(
				'name'        => CKIT_CONSENT_COOKIE,
				'vendor'      => __( 'Sito', 'cookie-kit' ),
				'duration'    => __( '6 mesi', 'cookie-kit' ),
				'description' => __( 'Memorizza le preferenze cookie espresse dall\'utente.', 'cookie-kit' ),
				'party'       => 'first',
			);
		}

		return $out;
	}

	private function has_cookie( $list, $name ) {
		foreach ( $list as $c ) {
			if ( $c['name'] === $name ) {
				return true;
			}
		}
		return false;
	}

	private function lookup_cookie_meta( $name, $dict ) {
		// Match esatto, poi pattern wildcard *.
		if ( isset( $dict[ $name ] ) ) {
			return $dict[ $name ];
		}
		foreach ( $dict as $pattern => $meta ) {
			if ( false === strpos( $pattern, '*' ) ) {
				continue;
			}
			$regex = '/^' . str_replace( '\\*', '.*', preg_quote( $pattern, '/' ) ) . '$/i';
			if ( preg_match( $regex, $name ) ) {
				return $meta;
			}
		}
		return array(
			'duration'    => '—',
			'description' => '',
		);
	}

	private function cookie_meta_dictionary() {
		return array(
			// Tecnici / WP / sito.
			'PHPSESSID'              => array( 'duration' => __( 'Sessione', 'cookie-kit' ), 'description' => __( 'Identificatore tecnico della sessione PHP.', 'cookie-kit' ) ),
			'wordpress_logged_in_*'  => array( 'duration' => __( '48 ore', 'cookie-kit' ), 'description' => __( 'Mantiene autenticato l\'utente registrato in area riservata.', 'cookie-kit' ) ),
			'wordpress_sec_*'        => array( 'duration' => __( 'Sessione', 'cookie-kit' ), 'description' => __( 'Cookie di sicurezza dell\'area riservata.', 'cookie-kit' ) ),
			'wp-settings-*'          => array( 'duration' => __( '1 anno', 'cookie-kit' ), 'description' => __( 'Memorizza le preferenze della bacheca WordPress.', 'cookie-kit' ) ),
			'wp-settings-time-*'     => array( 'duration' => __( '1 anno', 'cookie-kit' ), 'description' => __( 'Timestamp delle preferenze bacheca WordPress.', 'cookie-kit' ) ),
			'ckit_consent'           => array( 'duration' => __( '6 mesi', 'cookie-kit' ), 'description' => __( 'Memorizza le preferenze cookie espresse dall\'utente.', 'cookie-kit' ) ),
			// Cloudflare.
			'__cf_bm'                => array( 'duration' => __( '30 minuti', 'cookie-kit' ), 'description' => __( 'Cloudflare Bot Management: distingue traffico umano da bot.', 'cookie-kit' ) ),
			'__cflb'                 => array( 'duration' => __( '1 giorno', 'cookie-kit' ), 'description' => __( 'Cloudflare Load Balancing.', 'cookie-kit' ) ),
			'__cfduid'               => array( 'duration' => __( '30 giorni', 'cookie-kit' ), 'description' => __( 'Cloudflare: identifica il client per applicare regole di sicurezza.', 'cookie-kit' ) ),
			'cf_clearance'           => array( 'duration' => __( '30 giorni', 'cookie-kit' ), 'description' => __( 'Cloudflare: superamento di una sfida anti-bot.', 'cookie-kit' ) ),
			'cf_chl_*'               => array( 'duration' => __( 'Sessione', 'cookie-kit' ), 'description' => __( 'Cloudflare: stato di una sfida anti-bot in corso.', 'cookie-kit' ) ),
			// Google Analytics.
			'_ga'                    => array( 'duration' => __( '24 mesi', 'cookie-kit' ), 'description' => __( 'Google Analytics: identifica univocamente l\'utente.', 'cookie-kit' ) ),
			'_ga_*'                  => array( 'duration' => __( '24 mesi', 'cookie-kit' ), 'description' => __( 'Google Analytics 4: persistenza dello stato di sessione.', 'cookie-kit' ) ),
			'_gid'                   => array( 'duration' => __( '24 ore', 'cookie-kit' ), 'description' => __( 'Google Analytics: distingue gli utenti entro 24 ore.', 'cookie-kit' ) ),
			'_gat'                   => array( 'duration' => __( '1 minuto', 'cookie-kit' ), 'description' => __( 'Google Analytics: limita il volume delle richieste (throttling).', 'cookie-kit' ) ),
			// Google Tag Manager.
			'_dc_gtm_*'              => array( 'duration' => __( '1 minuto', 'cookie-kit' ), 'description' => __( 'Google Tag Manager: throttling delle richieste.', 'cookie-kit' ) ),
			// Google Ads / Marketing Platform.
			'IDE'                    => array( 'duration' => __( '13 mesi', 'cookie-kit' ), 'description' => __( 'Google Ads: pubblicità personalizzata e remarketing.', 'cookie-kit' ) ),
			'test_cookie'            => array( 'duration' => __( '15 minuti', 'cookie-kit' ), 'description' => __( 'Google Ads: verifica che il browser supporti i cookie.', 'cookie-kit' ) ),
			'DSID'                   => array( 'duration' => __( '14 giorni', 'cookie-kit' ), 'description' => __( 'Google: identificatore pubblicitario cross-device.', 'cookie-kit' ) ),
			'id'                     => array( 'duration' => __( '13 mesi', 'cookie-kit' ), 'description' => __( 'Google DoubleClick: identificatore univoco per la pubblicità.', 'cookie-kit' ) ),
			'RUL'                    => array( 'duration' => __( '12 mesi', 'cookie-kit' ), 'description' => __( 'Google DoubleClick: tracciamento di reindirizzamenti pubblicitari.', 'cookie-kit' ) ),
			'_gcl_au'                => array( 'duration' => __( '3 mesi', 'cookie-kit' ), 'description' => __( 'Google Conversion Linker: collega visite e conversioni Google Ads.', 'cookie-kit' ) ),
			'_gcl_aw'                => array( 'duration' => __( '3 mesi', 'cookie-kit' ), 'description' => __( 'Google Ads: tracciamento click campagne pubblicitarie.', 'cookie-kit' ) ),
			'_gcl_dc'                => array( 'duration' => __( '3 mesi', 'cookie-kit' ), 'description' => __( 'Google Display Network: tracciamento click.', 'cookie-kit' ) ),
			'1P_JAR'                 => array( 'duration' => __( '1 mese', 'cookie-kit' ), 'description' => __( 'Google: raccolta statistiche e personalizzazione annunci.', 'cookie-kit' ) ),
			// Facebook / Meta.
			'_fbp'                   => array( 'duration' => __( '90 giorni', 'cookie-kit' ), 'description' => __( 'Meta Pixel: tracciamento conversioni e campagne pubblicitarie.', 'cookie-kit' ) ),
			'_fbc'                   => array( 'duration' => __( '90 giorni', 'cookie-kit' ), 'description' => __( 'Meta Pixel: tracciamento click pubblicitari.', 'cookie-kit' ) ),
			'fr'                     => array( 'duration' => __( '90 giorni', 'cookie-kit' ), 'description' => __( 'Facebook: pubblicità personalizzata.', 'cookie-kit' ) ),
			// LinkedIn.
			'bcookie'                => array( 'duration' => __( '12 mesi', 'cookie-kit' ), 'description' => __( 'LinkedIn: identificatore browser.', 'cookie-kit' ) ),
			'li_sugr'                => array( 'duration' => __( '90 giorni', 'cookie-kit' ), 'description' => __( 'LinkedIn: identificazione utente per pubblicità.', 'cookie-kit' ) ),
			// X / Twitter.
			'guest_id'               => array( 'duration' => __( '24 mesi', 'cookie-kit' ), 'description' => __( 'X: identificatore visitatore.', 'cookie-kit' ) ),
			'personalization_id'     => array( 'duration' => __( '24 mesi', 'cookie-kit' ), 'description' => __( 'X: personalizzazione contenuti e pubblicità.', 'cookie-kit' ) ),
			// YouTube.
			'VISITOR_INFO1_LIVE'     => array( 'duration' => __( '6 mesi', 'cookie-kit' ), 'description' => __( 'YouTube: stima banda e statistiche video.', 'cookie-kit' ) ),
			'VISITOR_PRIVACY_METADATA' => array( 'duration' => __( '6 mesi', 'cookie-kit' ), 'description' => __( 'YouTube: memorizza le preferenze privacy dell\'utente nel player video.', 'cookie-kit' ) ),
			'YSC'                    => array( 'duration' => __( 'Sessione', 'cookie-kit' ), 'description' => __( 'YouTube: tracciamento visualizzazioni nella sessione.', 'cookie-kit' ) ),
			'PREF'                   => array( 'duration' => __( '8 mesi', 'cookie-kit' ), 'description' => __( 'YouTube/Google: preferenze utente.', 'cookie-kit' ) ),
			// Google Maps.
			'NID'                    => array( 'duration' => __( '6 mesi', 'cookie-kit' ), 'description' => __( 'Google: personalizzazione annunci e contenuti.', 'cookie-kit' ) ),
			'CONSENT'                => array( 'duration' => __( '24 mesi', 'cookie-kit' ), 'description' => __( 'Google: stato del consenso utente.', 'cookie-kit' ) ),
			// Microsoft Clarity.
			'_clck'                  => array( 'duration' => __( '12 mesi', 'cookie-kit' ), 'description' => __( 'Microsoft Clarity: identificatore utente per heatmap e session replay.', 'cookie-kit' ) ),
			'_clsk'                  => array( 'duration' => __( '24 ore', 'cookie-kit' ), 'description' => __( 'Microsoft Clarity: identificatore di sessione.', 'cookie-kit' ) ),
			'CLID'                   => array( 'duration' => __( '12 mesi', 'cookie-kit' ), 'description' => __( 'Microsoft Clarity: identificatore client.', 'cookie-kit' ) ),
			'MUID'                   => array( 'duration' => __( '13 mesi', 'cookie-kit' ), 'description' => __( 'Microsoft: identificatore univoco cross-property.', 'cookie-kit' ) ),
			// Hotjar.
			'_hjSessionUser_*'       => array( 'duration' => __( '12 mesi', 'cookie-kit' ), 'description' => __( 'Hotjar: identificatore utente per heatmap.', 'cookie-kit' ) ),
			'_hjSession_*'           => array( 'duration' => __( '30 minuti', 'cookie-kit' ), 'description' => __( 'Hotjar: identificatore di sessione.', 'cookie-kit' ) ),
			'_hjFirstSeen'           => array( 'duration' => __( 'Sessione', 'cookie-kit' ), 'description' => __( 'Hotjar: prima visita dell\'utente.', 'cookie-kit' ) ),
			// Vimeo.
			'vuid'                   => array( 'duration' => __( '24 mesi', 'cookie-kit' ), 'description' => __( 'Vimeo: identificatore utente per analytics video.', 'cookie-kit' ) ),
			'player'                 => array( 'duration' => __( '12 mesi', 'cookie-kit' ), 'description' => __( 'Vimeo: preferenze del player video (qualità, volume, dimensioni).', 'cookie-kit' ) ),
			'continuous_play_v3'     => array( 'duration' => __( '12 mesi', 'cookie-kit' ), 'description' => __( 'Vimeo: gestione riproduzione continua dei video.', 'cookie-kit' ) ),
			// Amazon affiliazione.
			'session-id'             => array( 'duration' => __( '12 mesi', 'cookie-kit' ), 'description' => __( 'Amazon: identificatore di sessione utente.', 'cookie-kit' ) ),
			'ubid-main'              => array( 'duration' => __( '12 mesi', 'cookie-kit' ), 'description' => __( 'Amazon: identificatore univoco.', 'cookie-kit' ) ),
		);
	}

	public function shortcode_button( $atts = array() ) {
		$atts  = shortcode_atts(
			array(
				'label' => CKIT_Core::texts( 'floating_button' ),
				'class' => 'ckit-manage-link',
			),
			$atts
		);
		$label = $atts['label'] ? $atts['label'] : __( 'Gestisci cookie', 'cookie-kit' );
		return sprintf(
			'<a href="#" class="%s" data-ckit-open-prefs="1">%s</a>',
			esc_attr( $atts['class'] ),
			esc_html( $label )
		);
	}
}
