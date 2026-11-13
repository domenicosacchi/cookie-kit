<?php
/**
 * Admin — Pagina di amministrazione del plugin CookieKit.
 * Tab: Impostazioni · Categorie · Vendor · Scanner · Log consensi · Aspetto.
 *
 * @package CookieKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CKIT_Admin {

	const SLUG = 'cookie-kit';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_ckit_run_scanner', array( $this, 'handle_scanner' ) );
		add_action( 'admin_post_ckit_clear_scanner', array( $this, 'handle_scanner_clear' ) );
		add_action( 'admin_post_ckit_export_log', array( $this, 'handle_export_log' ) );
		add_action( 'admin_post_ckit_save_categories', array( $this, 'handle_save_categories' ) );
		add_action( 'admin_post_ckit_save_vendors', array( $this, 'handle_save_vendors' ) );
		add_action( 'admin_post_ckit_save_texts', array( $this, 'handle_save_texts' ) );
	}

	public function menu() {
		add_menu_page(
			__( 'Cookie Consent', 'cookie-kit' ),
			__( 'Cookie Consent', 'cookie-kit' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' ),
			'dashicons-shield',
			80
		);
	}

	public function enqueue( $hook ) {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'ckit-admin',
			CKIT_PLUGIN_URL . 'admin/css/ckit-admin.css',
			array(),
			CKIT_VERSION
		);
		// Media uploader per upload logo.
		wp_enqueue_media();
	}

	public function register_settings() {
		register_setting( 'ckit_settings_group', CKIT_OPTION_KEY );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'settings'   => __( 'Impostazioni', 'cookie-kit' ),
			'categories' => __( 'Categorie', 'cookie-kit' ),
			'vendors'    => __( 'Vendor', 'cookie-kit' ),
			'scanner'    => __( 'Scanner', 'cookie-kit' ),
			'log'        => __( 'Log consensi', 'cookie-kit' ),
			'texts'      => __( 'Testi e aspetto', 'cookie-kit' ),
		);
		?>
		<div class="wrap ckit-admin">
			<h1><?php echo esc_html__( 'CookieKit', 'cookie-kit' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="?page=<?php echo esc_attr( self::SLUG ); ?>&tab=<?php echo esc_attr( $key ); ?>"
					   class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="ckit-tab-content">
				<?php
				switch ( $tab ) {
					case 'categories':
						$this->view_categories();
						break;
					case 'vendors':
						$this->view_vendors();
						break;
					case 'scanner':
						$this->view_scanner();
						break;
					case 'log':
						$this->view_log();
						break;
					case 'texts':
						$this->view_texts();
						break;
					default:
						$this->view_settings();
				}
				?>
			</div>
		</div>
		<?php
	}

	private function view_settings() {
		$opts = get_option( CKIT_OPTION_KEY, array() );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'ckit_settings_group' ); ?>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Plugin attivo', 'cookie-kit' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[enabled]" value="1" <?php checked( ! empty( $opts['enabled'] ) ); ?>>
						<?php esc_html_e( 'Mostra il banner ai visitatori', 'cookie-kit' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Auto-blocco script', 'cookie-kit' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[auto_block]" value="1" <?php checked( ! empty( $opts['auto_block'] ) ); ?>>
						<?php esc_html_e( 'Blocca script di terze parti finché non c\'è consenso', 'cookie-kit' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Blocco iframe', 'cookie-kit' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[block_iframes]" value="1" <?php checked( ! empty( $opts['block_iframes'] ) ); ?>>
						<?php esc_html_e( 'Sostituisci YouTube/Vimeo/Maps con placeholder click-to-load', 'cookie-kit' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Google Consent Mode v2', 'cookie-kit' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[consent_mode_v2]" value="1" <?php checked( ! empty( $opts['consent_mode_v2'] ) ); ?>>
						<?php esc_html_e( 'Inietta segnali consent default=denied prima di Analytics/GTM', 'cookie-kit' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Pulsante footer', 'cookie-kit' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[show_floating_button]" value="1" <?php checked( ! empty( $opts['show_floating_button'] ) ); ?>>
							<?php esc_html_e( 'Mostra icona persistente "Gestisci cookie"', 'cookie-kit' ); ?></label><br>
						<select name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[floating_position]">
							<option value="bottom-left" <?php selected( $opts['floating_position'] ?? '', 'bottom-left' ); ?>>bottom-left</option>
							<option value="bottom-right" <?php selected( $opts['floating_position'] ?? '', 'bottom-right' ); ?>>bottom-right</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Re-prompt (giorni)', 'cookie-kit' ); ?></th>
					<td><input type="number" min="30" max="365" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[reprompt_days]" value="<?php echo esc_attr( $opts['reprompt_days'] ?? 180 ); ?>"> <span class="description"><?php esc_html_e( 'Dopo quanti giorni ripresentare il banner. Default 180 (linee guida Garante)', 'cookie-kit' ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Retention log (giorni)', 'cookie-kit' ); ?></th>
					<td><input type="number" min="30" max="3650" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[log_retention_days]" value="<?php echo esc_attr( $opts['log_retention_days'] ?? 1825 ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Versione policy corrente', 'cookie-kit' ); ?></th>
					<td><input type="text" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[policy_version]" value="<?php echo esc_attr( $opts['policy_version'] ?? '1.0' ); ?>"> <span class="description"><?php esc_html_e( 'Cambiare per forzare la ripresentazione del banner ai vecchi consensi', 'cookie-kit' ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'URL Informativa Privacy', 'cookie-kit' ); ?></th>
					<td><input type="text" class="large-text" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[privacy_policy_url]" value="<?php echo esc_attr( $opts['privacy_policy_url'] ?? '/informativa-privacy/' ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'URL Cookie Policy', 'cookie-kit' ); ?></th>
					<td><input type="text" class="large-text" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[cookie_policy_url]" value="<?php echo esc_attr( $opts['cookie_policy_url'] ?? '/cookie-policy/' ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Posizione banner', 'cookie-kit' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[banner_position]">
							<option value="bottom" <?php selected( $opts['banner_position'] ?? '', 'bottom' ); ?>>bottom</option>
							<option value="top" <?php selected( $opts['banner_position'] ?? '', 'top' ); ?>>top</option>
							<option value="center" <?php selected( $opts['banner_position'] ?? '', 'center' ); ?>>center (modale)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Layout banner', 'cookie-kit' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[banner_layout]">
							<option value="bar" <?php selected( $opts['banner_layout'] ?? '', 'bar' ); ?>><?php esc_html_e( 'Bar — striscia a tutta larghezza (Cookiebot-style)', 'cookie-kit' ); ?></option>
							<option value="box" <?php selected( $opts['banner_layout'] ?? '', 'box' ); ?>><?php esc_html_e( 'Box — riquadro centrato', 'cookie-kit' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Tema', 'cookie-kit' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[theme]">
							<option value="light" <?php selected( $opts['theme'] ?? '', 'light' ); ?>>light</option>
							<option value="dark" <?php selected( $opts['theme'] ?? '', 'dark' ); ?>>dark</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Colore primario', 'cookie-kit' ); ?></th>
					<td><input type="color" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[primary_color]" value="<?php echo esc_attr( $opts['primary_color'] ?? '#0a3d62' ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Colore "Accetta"', 'cookie-kit' ); ?></th>
					<td><input type="color" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[accept_color]" value="<?php echo esc_attr( $opts['accept_color'] ?? '#0a3d62' ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Colore "Rifiuta"', 'cookie-kit' ); ?></th>
					<td><input type="color" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[reject_color]" value="<?php echo esc_attr( $opts['reject_color'] ?? '#6c757d' ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Mostra logo nel banner', 'cookie-kit' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[show_logo]" value="1" <?php checked( ! empty( $opts['show_logo'] ) ); ?>>
						<?php esc_html_e( 'Visualizza il logo del sito sopra il titolo del banner e del modale di personalizzazione', 'cookie-kit' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'URL logo', 'cookie-kit' ); ?></th>
					<td>
						<input type="text" id="ckit-logo-url" class="large-text" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[logo_url]" value="<?php echo esc_attr( $opts['logo_url'] ?? '' ); ?>" placeholder="https://www.example.com/wp-content/uploads/.../logo.png">
						<p>
							<button type="button" class="button" id="ckit-upload-logo">📁 <?php esc_html_e( 'Carica / scegli dalla Libreria Media', 'cookie-kit' ); ?></button>
							<button type="button" class="button" id="ckit-remove-logo"><?php esc_html_e( 'Rimuovi', 'cookie-kit' ); ?></button>
						</p>
						<p class="description"><?php esc_html_e( 'Consigliato: PNG/SVG con sfondo trasparente, altezza ~80-120px. Formato landscape.', 'cookie-kit' ); ?></p>
						<div id="ckit-logo-preview" style="margin-top:10px;<?php echo empty( $opts['logo_url'] ) ? 'display:none' : ''; ?>">
							<img src="<?php echo esc_attr( $opts['logo_url'] ?? '' ); ?>" style="max-height:60px;max-width:300px;border:1px solid #ddd;padding:8px;background:#fff;border-radius:4px">
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Altezza max logo (px)', 'cookie-kit' ); ?></th>
					<td><input type="number" min="20" max="120" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[logo_max_height]" value="<?php echo esc_attr( $opts['logo_max_height'] ?? 56 ); ?>"> <span class="description"><?php esc_html_e( 'Altezza massima del logo nel banner. Default 56px.', 'cookie-kit' ); ?></span></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<script>
		(function(){
			var btnUpload = document.getElementById('ckit-upload-logo');
			var btnRemove = document.getElementById('ckit-remove-logo');
			var input = document.getElementById('ckit-logo-url');
			var preview = document.getElementById('ckit-logo-preview');
			var previewImg = preview ? preview.querySelector('img') : null;
			if (!btnUpload || !input) return;
			btnUpload.addEventListener('click', function(e){
				e.preventDefault();
				var frame = wp.media({
					title: '<?php echo esc_js( __( 'Scegli logo per il banner cookie', 'cookie-kit' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'Usa questo logo', 'cookie-kit' ) ); ?>' },
					library: { type: 'image' },
					multiple: false
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					input.value = att.url;
					if (previewImg) previewImg.src = att.url;
					if (preview) preview.style.display = '';
				});
				frame.open();
			});
			if (btnRemove) {
				btnRemove.addEventListener('click', function(e){
					e.preventDefault();
					input.value = '';
					if (preview) preview.style.display = 'none';
				});
			}
		})();
		</script>
		<?php
	}

	private function view_categories() {
		$categories = get_option( 'ckit_categories', array() );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ckit_save_categories' ); ?>
			<input type="hidden" name="action" value="ckit_save_categories">
			<p><?php esc_html_e( 'Le categorie definiscono come l\'utente può scegliere quali cookie accettare. La categoria "necessary" è sempre attiva e non può essere disabilitata.', 'cookie-kit' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Nome', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Descrizione', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Obbligatoria', 'cookie-kit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $categories as $id => $cat ) : ?>
						<tr>
							<td><code><?php echo esc_html( $id ); ?></code></td>
							<td><input type="text" name="cat[<?php echo esc_attr( $id ); ?>][name]" value="<?php echo esc_attr( $cat['name'] ); ?>"></td>
							<td><textarea name="cat[<?php echo esc_attr( $id ); ?>][description]" rows="2" cols="60"><?php echo esc_textarea( $cat['description'] ); ?></textarea></td>
							<td><input type="checkbox" name="cat[<?php echo esc_attr( $id ); ?>][required]" value="1" <?php checked( ! empty( $cat['required'] ) ); ?> <?php disabled( 'necessary' === $id ); ?>></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Salva categorie', 'cookie-kit' ) ); ?>
		</form>
		<?php
	}

	private function view_vendors() {
		$vendors = get_option( 'ckit_vendors', array() );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ckit_save_vendors' ); ?>
			<input type="hidden" name="action" value="ckit_save_vendors">
			<p><?php esc_html_e( 'Vendor di terze parti. La regex "pattern" intercetta gli script da bloccare; "host" è il dominio del fornitore; "cookies" è la lista dei nomi cookie installati (uno per riga, supporta wildcard *).', 'cookie-kit' ); ?></p>
			<table class="widefat striped ckit-vendors-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Nome', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Categoria', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Host', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Pattern (regex)', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Cookie (CSV)', 'cookie-kit' ); ?></th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody id="ckit-vendor-rows">
					<?php foreach ( $vendors as $i => $v ) : ?>
						<tr>
							<td><input type="text" name="vendor[<?php echo $i; ?>][name]" value="<?php echo esc_attr( $v['name'] ?? '' ); ?>"></td>
							<td>
								<select name="vendor[<?php echo $i; ?>][category]">
									<?php foreach ( get_option( 'ckit_categories', array() ) as $cid => $c ) : ?>
										<option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $v['category'] ?? '', $cid ); ?>><?php echo esc_html( $c['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="text" name="vendor[<?php echo $i; ?>][host]" value="<?php echo esc_attr( $v['host'] ?? '' ); ?>"></td>
							<td><input type="text" name="vendor[<?php echo $i; ?>][pattern]" value="<?php echo esc_attr( $v['pattern'] ?? '' ); ?>"></td>
							<td><input type="text" name="vendor[<?php echo $i; ?>][cookies]" value="<?php echo esc_attr( $v['cookies'] ?? '' ); ?>"></td>
							<td><button type="button" class="button-link ckit-remove-vendor"><?php esc_html_e( 'Rimuovi', 'cookie-kit' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="ckit-add-vendor"><?php esc_html_e( 'Aggiungi vendor', 'cookie-kit' ); ?></button></p>
			<?php submit_button( __( 'Salva vendor', 'cookie-kit' ) ); ?>
		</form>
		<script>
		(function(){
			var tbody = document.getElementById('ckit-vendor-rows');
			document.getElementById('ckit-add-vendor').addEventListener('click', function(){
				var i = tbody.children.length;
				var row = document.createElement('tr');
				row.innerHTML = '<td><input type="text" name="vendor['+i+'][name]"></td>'
					+ '<td><select name="vendor['+i+'][category]"><?php foreach ( get_option( 'ckit_categories', array() ) as $cid => $c ) : ?><option value="<?php echo esc_attr( $cid ); ?>"><?php echo esc_js( $c['name'] ); ?></option><?php endforeach; ?></select></td>'
					+ '<td><input type="text" name="vendor['+i+'][host]"></td>'
					+ '<td><input type="text" name="vendor['+i+'][pattern]"></td>'
					+ '<td><input type="text" name="vendor['+i+'][cookies]"></td>'
					+ '<td><button type="button" class="button-link ckit-remove-vendor">Rimuovi</button></td>';
				tbody.appendChild(row);
			});
			tbody.addEventListener('click', function(e){
				if (e.target.classList.contains('ckit-remove-vendor')) {
					e.target.closest('tr').remove();
				}
			});
		})();
		</script>
		<?php
	}

	private function view_scanner() {
		$scanner   = CKIT_Core::instance()->scanner;
		$rows      = $scanner->get_all();
		$home_snap = add_query_arg( 'ckit-snapshot', '1', home_url( '/' ) );
		?>
		<h2 style="margin-top:0;color:#0a3d62"><?php esc_html_e( '★ Scansione browser (consigliata)', 'cookie-kit' ); ?></h2>
		<p><?php esc_html_e( 'Apre la home del sito in una nuova scheda con uno script che simula l\'accettazione dei cookie e cattura tutti i cookie effettivamente installati nel browser, incluso ciò che gli script JavaScript di Google Analytics, GTM, Meta Pixel, ecc. installano lato client. È la scansione più accurata.', 'cookie-kit' ); ?></p>
		<p>
			<a class="button button-primary button-hero" href="<?php echo esc_url( $home_snap ); ?>" target="_blank" rel="noopener">▶ <?php esc_html_e( 'Avvia scansione browser', 'cookie-kit' ); ?></a>
		</p>
		<p class="description">
			<?php esc_html_e( 'Quando si apre la nuova scheda, lascia che lo script lavori ~8 secondi (vedrai un overlay in alto a destra). Al termine torna qui e ricarica la pagina per vedere i cookie aggiornati nella tabella.', 'cookie-kit' ); ?>
		</p>

		<hr style="margin:24px 0">
		<h2><?php esc_html_e( 'Scansione passiva server-side', 'cookie-kit' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Richieste HTTP server-side per identificare cookie installati prima del caricamento JS (Cloudflare, sessione PHP, framework). Non sostituisce la scansione browser, è complementare.', 'cookie-kit' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
			<?php wp_nonce_field( 'ckit_run_scanner' ); ?>
			<input type="hidden" name="action" value="ckit_run_scanner">
			<?php submit_button( __( 'Esegui scansione passiva', 'cookie-kit' ), 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:8px">
			<?php wp_nonce_field( 'ckit_clear_scanner' ); ?>
			<input type="hidden" name="action" value="ckit_clear_scanner">
			<?php submit_button( __( 'Svuota tutti i risultati', 'cookie-kit' ), 'delete', 'submit', false ); ?>
		</form>
		<hr style="margin:24px 0">
		<?php if ( $rows ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Cookie', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Dominio', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Categoria', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Parte', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Vendor', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Durata', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Trovato in', 'cookie-kit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><code><?php echo esc_html( $r->cookie_name ); ?></code></td>
							<td><?php echo esc_html( $r->cookie_domain ); ?></td>
							<td><?php echo esc_html( $r->category ); ?></td>
							<td><?php echo esc_html( $r->party ); ?></td>
							<td><?php echo esc_html( $r->vendor ); ?></td>
							<td><?php echo esc_html( $r->duration ); ?></td>
							<td><a href="<?php echo esc_url( $r->source_url ); ?>" target="_blank"><?php echo esc_html( wp_parse_url( $r->source_url, PHP_URL_PATH ) ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><em><?php esc_html_e( 'Nessuna scansione ancora eseguita.', 'cookie-kit' ); ?></em></p>
		<?php endif; ?>
		<?php
	}

	private function view_log() {
		$log    = CKIT_Core::instance()->log;
		$rows   = $log->get_recent( 200 );
		$total  = $log->count();
		$accept = $log->count( array( 'action' => 'accept_all' ) );
		$reject = $log->count( array( 'action' => 'reject_all' ) );
		$custom = $log->count( array( 'action' => 'customize' ) );
		?>
		<div class="ckit-stats">
			<div><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> <?php esc_html_e( 'consensi totali', 'cookie-kit' ); ?></div>
			<div><strong><?php echo esc_html( number_format_i18n( $accept ) ); ?></strong> <?php esc_html_e( 'accept all', 'cookie-kit' ); ?></div>
			<div><strong><?php echo esc_html( number_format_i18n( $reject ) ); ?></strong> <?php esc_html_e( 'reject all', 'cookie-kit' ); ?></div>
			<div><strong><?php echo esc_html( number_format_i18n( $custom ) ); ?></strong> <?php esc_html_e( 'personalizzati', 'cookie-kit' ); ?></div>
		</div>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ckit_export_log&format=csv' ), 'ckit_export_log' ) ); ?>"><?php esc_html_e( 'Esporta CSV', 'cookie-kit' ); ?></a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ckit_export_log&format=json' ), 'ckit_export_log' ) ); ?>"><?php esc_html_e( 'Esporta JSON', 'cookie-kit' ); ?></a>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>ID</th>
					<th><?php esc_html_e( 'Data/ora (UTC)', 'cookie-kit' ); ?></th>
					<th>UUID</th>
					<th><?php esc_html_e( 'Azione', 'cookie-kit' ); ?></th>
					<th><?php esc_html_e( 'Categorie', 'cookie-kit' ); ?></th>
					<th><?php esc_html_e( 'Versione policy', 'cookie-kit' ); ?></th>
					<th URL><?php esc_html_e( 'Pagina', 'cookie-kit' ); ?></th>
					<th>Lang</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo (int) $r->id; ?></td>
						<td><?php echo esc_html( $r->created_at ); ?></td>
						<td><code><?php echo esc_html( substr( $r->consent_uuid, 0, 8 ) . '…' ); ?></code></td>
						<td><?php echo esc_html( $r->action ); ?></td>
						<td><code><?php echo esc_html( $r->categories ); ?></code></td>
						<td><?php echo esc_html( $r->policy_version ); ?></td>
						<td><a href="<?php echo esc_url( $r->page_url ); ?>" target="_blank"><?php echo esc_html( wp_parse_url( $r->page_url, PHP_URL_PATH ) ); ?></a></td>
						<td><?php echo esc_html( $r->language ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function view_texts() {
		$texts = get_option( 'ckit_texts', array() );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ckit_save_texts' ); ?>
			<input type="hidden" name="action" value="ckit_save_texts">
			<table class="form-table">
				<?php
				$labels = array(
					'banner_title'      => __( 'Titolo banner', 'cookie-kit' ),
					'banner_message'    => __( 'Messaggio banner', 'cookie-kit' ),
					'btn_accept_all'    => __( 'Pulsante "Accetta tutti"', 'cookie-kit' ),
					'btn_reject_all'    => __( 'Pulsante "Rifiuta tutti"', 'cookie-kit' ),
					'btn_customize'     => __( 'Pulsante "Personalizza"', 'cookie-kit' ),
					'btn_save'          => __( 'Pulsante "Salva preferenze"', 'cookie-kit' ),
					'btn_close'         => __( 'Pulsante "Chiudi"', 'cookie-kit' ),
					'modal_title'       => __( 'Titolo modale preferenze (vuoto = usa Titolo banner)', 'cookie-kit' ),
					'modal_intro'       => __( 'Intro modale preferenze (vuoto = usa Messaggio banner)', 'cookie-kit' ),
					'floating_button'   => __( 'Etichetta pulsante footer', 'cookie-kit' ),
					'privacy_link_text' => __( 'Etichetta link Privacy', 'cookie-kit' ),
					'cookies_link_text' => __( 'Etichetta link Cookie Policy', 'cookie-kit' ),
				);
				foreach ( $labels as $k => $label ) :
					$is_long = in_array( $k, array( 'banner_message', 'modal_intro' ), true );
					?>
					<tr>
						<th><?php echo esc_html( $label ); ?></th>
						<td>
							<?php if ( $is_long ) : ?>
								<textarea name="texts[<?php echo esc_attr( $k ); ?>]" rows="4" cols="80"><?php echo esc_textarea( $texts[ $k ] ?? '' ); ?></textarea>
							<?php else : ?>
								<input type="text" class="large-text" name="texts[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $texts[ $k ] ?? '' ); ?>">
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( __( 'Salva testi', 'cookie-kit' ) ); ?>
		</form>
		<?php
	}

	// ==================== Handler =========================.

	public function handle_scanner() {
		check_admin_referer( 'ckit_run_scanner' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		$scanner = CKIT_Core::instance()->scanner;
		$scanner->scan_urls( $scanner->default_urls() );
		wp_safe_redirect( add_query_arg( 'tab', 'scanner', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	public function handle_scanner_clear() {
		check_admin_referer( 'ckit_clear_scanner' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		CKIT_Core::instance()->scanner->clear();
		wp_safe_redirect( add_query_arg( 'tab', 'scanner', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	public function handle_export_log() {
		check_admin_referer( 'ckit_export_log' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		$format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : 'csv';
		$log    = CKIT_Core::instance()->log;
		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="ckit-consent-log-' . gmdate( 'Y-m-d' ) . '.json"' );
			echo $log->export_json(); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="ckit-consent-log-' . gmdate( 'Y-m-d' ) . '.csv"' );
			echo $log->export_csv(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		exit;
	}

	public function handle_save_categories() {
		check_admin_referer( 'ckit_save_categories' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		$cat_in = isset( $_POST['cat'] ) ? wp_unslash( $_POST['cat'] ) : array();
		$out    = array();
		foreach ( $cat_in as $id => $row ) {
			$id = sanitize_key( $id );
			$out[ $id ] = array(
				'name'        => sanitize_text_field( $row['name'] ?? '' ),
				'description' => sanitize_textarea_field( $row['description'] ?? '' ),
				'required'    => ! empty( $row['required'] ) ? 1 : 0,
			);
		}
		// Forza necessary required.
		if ( isset( $out['necessary'] ) ) {
			$out['necessary']['required'] = 1;
		}
		update_option( 'ckit_categories', $out );
		wp_safe_redirect( add_query_arg( 'tab', 'categories', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	public function handle_save_vendors() {
		check_admin_referer( 'ckit_save_vendors' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		$in  = isset( $_POST['vendor'] ) ? wp_unslash( $_POST['vendor'] ) : array();
		$out = array();
		foreach ( $in as $row ) {
			if ( empty( $row['name'] ) ) {
				continue;
			}
			$out[] = array(
				'name'     => sanitize_text_field( $row['name'] ),
				'category' => sanitize_key( $row['category'] ?? 'marketing' ),
				'host'     => sanitize_text_field( $row['host'] ?? '' ),
				'pattern'  => sanitize_text_field( $row['pattern'] ?? '' ),
				'cookies'  => sanitize_text_field( $row['cookies'] ?? '' ),
			);
		}
		update_option( 'ckit_vendors', $out );
		wp_safe_redirect( add_query_arg( 'tab', 'vendors', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	public function handle_save_texts() {
		check_admin_referer( 'ckit_save_texts' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden' );
		}
		$in  = isset( $_POST['texts'] ) ? wp_unslash( $_POST['texts'] ) : array();
		$out = array();
		foreach ( $in as $k => $v ) {
			$out[ sanitize_key( $k ) ] = sanitize_textarea_field( $v );
		}
		update_option( 'ckit_texts', $out );
		wp_safe_redirect( add_query_arg( 'tab', 'texts', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}
}
