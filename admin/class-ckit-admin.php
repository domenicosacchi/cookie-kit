<?php
/**
 * Admin — CookieKit administration page.
 * Tabs: Settings · Categories · Vendors · Scanner · Consent log · Texts.
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
		// Media uploader for the logo.
		wp_enqueue_media();
	}

	public function register_settings() {
		register_setting(
			'ckit_settings_group',
			CKIT_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize the main settings array submitted from the Settings tab.
	 *
	 * Unchecked checkboxes are stored as 0: before 1.2.0 they were dropped from the array,
	 * so CKIT_Core::settings() fell back to the default (1) and unchecking had no effect.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$old = get_option( CKIT_OPTION_KEY, array() );
		$old = is_array( $old ) ? $old : array();
		if ( ! is_array( $input ) ) {
			return $old;
		}
		// Start from the stored value so keys not present in the form are preserved.
		$out = $old;

		foreach ( array( 'enabled', 'auto_block', 'block_iframes', 'consent_mode_v2', 'show_floating_button', 'show_logo' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$choices = array(
			'banner_position'   => array( 'bottom', 'top', 'center' ),
			'banner_layout'     => array( 'bar', 'box' ),
			'theme'             => array( 'light', 'dark' ),
			'floating_position' => array( 'bottom-left', 'bottom-right' ),
		);
		foreach ( $choices as $key => $allowed ) {
			if ( isset( $input[ $key ] ) && in_array( $input[ $key ], $allowed, true ) ) {
				$out[ $key ] = $input[ $key ];
			}
		}

		foreach ( array( 'primary_color', 'accept_color', 'reject_color' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$color = sanitize_hex_color( $input[ $key ] );
				if ( $color ) {
					$out[ $key ] = $color;
				}
			}
		}

		$ranges = array(
			'reprompt_days'      => array( 30, 365 ),
			'log_retention_days' => array( 30, 3650 ),
			'logo_max_height'    => array( 20, 120 ),
		);
		foreach ( $ranges as $key => $range ) {
			if ( isset( $input[ $key ] ) && is_numeric( $input[ $key ] ) ) {
				$out[ $key ] = min( $range[1], max( $range[0], absint( $input[ $key ] ) ) );
			}
		}

		if ( isset( $input['policy_version'] ) ) {
			$version = substr( sanitize_text_field( $input['policy_version'] ), 0, 20 );
			$out['policy_version'] = '' !== $version ? $version : '1.0';
		}

		foreach ( array( 'privacy_policy_url', 'cookie_policy_url', 'logo_url' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = esc_url_raw( trim( (string) $input[ $key ] ) );
			}
		}

		return $out;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'settings'   => __( 'Settings', 'cookie-kit' ),
			'categories' => __( 'Categories', 'cookie-kit' ),
			'vendors'    => __( 'Vendors', 'cookie-kit' ),
			'scanner'    => __( 'Scanner', 'cookie-kit' ),
			'log'        => __( 'Consent log', 'cookie-kit' ),
			'texts'      => __( 'Texts', 'cookie-kit' ),
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
					<th><?php esc_html_e( 'Plugin enabled', 'cookie-kit' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[enabled]" value="1" <?php checked( (bool) CKIT_Core::settings( 'enabled', 1 ) ); ?>>
						<?php esc_html_e( 'Show the banner to visitors', 'cookie-kit' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Script auto-blocking', 'cookie-kit' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[auto_block]" value="1" <?php checked( (bool) CKIT_Core::settings( 'auto_block', 1 ) ); ?>>
						<?php esc_html_e( 'Block third-party scripts until consent is given', 'cookie-kit' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Iframe blocking', 'cookie-kit' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[block_iframes]" value="1" <?php checked( (bool) CKIT_Core::settings( 'block_iframes', 1 ) ); ?>>
						<?php esc_html_e( 'Replace YouTube/Vimeo/Maps embeds with click-to-load placeholders', 'cookie-kit' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Google Consent Mode v2', 'cookie-kit' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[consent_mode_v2]" value="1" <?php checked( (bool) CKIT_Core::settings( 'consent_mode_v2', 1 ) ); ?>>
						<?php esc_html_e( 'Print consent default=denied signals before Analytics/GTM', 'cookie-kit' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Footer button', 'cookie-kit' ); ?></th>
					<td>
						<label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[show_floating_button]" value="1" <?php checked( (bool) CKIT_Core::settings( 'show_floating_button', 1 ) ); ?>>
							<?php esc_html_e( 'Show the persistent "Manage cookies" icon', 'cookie-kit' ); ?></label><br>
						<select name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[floating_position]">
							<option value="bottom-left" <?php selected( $opts['floating_position'] ?? '', 'bottom-left' ); ?>>bottom-left</option>
							<option value="bottom-right" <?php selected( $opts['floating_position'] ?? '', 'bottom-right' ); ?>>bottom-right</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Re-prompt (days)', 'cookie-kit' ); ?></th>
					<td><input type="number" min="30" max="365" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[reprompt_days]" value="<?php echo esc_attr( $opts['reprompt_days'] ?? 180 ); ?>"> <span class="description"><?php esc_html_e( 'After how many days the banner is shown again. Default 180 (the Garante Privacy guidelines indicate at least 6 months).', 'cookie-kit' ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Log retention (days)', 'cookie-kit' ); ?></th>
					<td><input type="number" min="30" max="3650" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[log_retention_days]" value="<?php echo esc_attr( $opts['log_retention_days'] ?? 1825 ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Current policy version', 'cookie-kit' ); ?></th>
					<td><input type="text" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[policy_version]" value="<?php echo esc_attr( $opts['policy_version'] ?? '1.0' ); ?>"> <span class="description"><?php esc_html_e( 'Change it to show the banner again to visitors who consented under an older version', 'cookie-kit' ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Privacy Policy URL', 'cookie-kit' ); ?></th>
					<td><input type="text" class="large-text" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[privacy_policy_url]" value="<?php echo esc_attr( $opts['privacy_policy_url'] ?? '' ); ?>" placeholder="<?php echo esc_attr( CKIT_Core::default_privacy_policy_url() ); ?>"> <p class="description"><?php esc_html_e( 'Empty = default path shown as placeholder.', 'cookie-kit' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Cookie Policy URL', 'cookie-kit' ); ?></th>
					<td><input type="text" class="large-text" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[cookie_policy_url]" value="<?php echo esc_attr( $opts['cookie_policy_url'] ?? '' ); ?>" placeholder="<?php echo esc_attr( CKIT_Core::default_cookie_policy_url() ); ?>"> <p class="description"><?php esc_html_e( 'Empty = default path shown as placeholder.', 'cookie-kit' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Banner position', 'cookie-kit' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[banner_position]">
							<option value="bottom" <?php selected( $opts['banner_position'] ?? '', 'bottom' ); ?>>bottom</option>
							<option value="top" <?php selected( $opts['banner_position'] ?? '', 'top' ); ?>>top</option>
							<option value="center" <?php selected( $opts['banner_position'] ?? '', 'center' ); ?>>center (<?php esc_html_e( 'modal', 'cookie-kit' ); ?>)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Banner layout', 'cookie-kit' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[banner_layout]">
							<option value="bar" <?php selected( $opts['banner_layout'] ?? '', 'bar' ); ?>><?php esc_html_e( 'Bar — full-width strip', 'cookie-kit' ); ?></option>
							<option value="box" <?php selected( $opts['banner_layout'] ?? '', 'box' ); ?>><?php esc_html_e( 'Box — centered box', 'cookie-kit' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Theme', 'cookie-kit' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[theme]">
							<option value="light" <?php selected( $opts['theme'] ?? '', 'light' ); ?>>light</option>
							<option value="dark" <?php selected( $opts['theme'] ?? '', 'dark' ); ?>>dark</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Primary color', 'cookie-kit' ); ?></th>
					<td><input type="color" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[primary_color]" value="<?php echo esc_attr( $opts['primary_color'] ?? '#0a3d62' ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( '"Accept" color', 'cookie-kit' ); ?></th>
					<td><input type="color" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[accept_color]" value="<?php echo esc_attr( $opts['accept_color'] ?? '#0a3d62' ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( '"Reject" color', 'cookie-kit' ); ?></th>
					<td><input type="color" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[reject_color]" value="<?php echo esc_attr( $opts['reject_color'] ?? '#6c757d' ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Show logo in the banner', 'cookie-kit' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[show_logo]" value="1" <?php checked( (bool) CKIT_Core::settings( 'show_logo', 0 ) ); ?>>
						<?php esc_html_e( 'Display the site logo above the banner title and in the preferences modal', 'cookie-kit' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Logo URL', 'cookie-kit' ); ?></th>
					<td>
						<input type="text" id="ckit-logo-url" class="large-text" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[logo_url]" value="<?php echo esc_attr( $opts['logo_url'] ?? '' ); ?>" placeholder="https://www.example.com/wp-content/uploads/.../logo.png">
						<p>
							<button type="button" class="button" id="ckit-upload-logo">📁 <?php esc_html_e( 'Upload / choose from the Media Library', 'cookie-kit' ); ?></button>
							<button type="button" class="button" id="ckit-remove-logo"><?php esc_html_e( 'Remove', 'cookie-kit' ); ?></button>
						</p>
						<p class="description"><?php esc_html_e( 'Recommended: PNG/SVG with a transparent background, about 80-120px high, landscape format.', 'cookie-kit' ); ?></p>
						<div id="ckit-logo-preview" style="margin-top:10px;<?php echo empty( $opts['logo_url'] ) ? 'display:none' : ''; ?>">
							<img src="<?php echo esc_url( $opts['logo_url'] ?? '' ); ?>" alt="" style="max-height:60px;max-width:300px;border:1px solid #ddd;padding:8px;background:#fff;border-radius:4px">
						</div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Max logo height (px)', 'cookie-kit' ); ?></th>
					<td><input type="number" min="20" max="120" name="<?php echo esc_attr( CKIT_OPTION_KEY ); ?>[logo_max_height]" value="<?php echo esc_attr( $opts['logo_max_height'] ?? 56 ); ?>"> <span class="description"><?php esc_html_e( 'Maximum logo height in the banner. Default 56px.', 'cookie-kit' ); ?></span></td>
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
					title: '<?php echo esc_js( __( 'Choose a logo for the cookie banner', 'cookie-kit' ) ); ?>',
					button: { text: '<?php echo esc_js( __( 'Use this logo', 'cookie-kit' ) ); ?>' },
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
		$categories = is_array( $categories ) ? $categories : array();
		$defaults   = CKIT_Core::default_categories();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ckit_save_categories' ); ?>
			<input type="hidden" name="action" value="ckit_save_categories">
			<p><?php esc_html_e( 'Categories define how users can choose which cookies to accept. The "necessary" category is always active and cannot be disabled.', 'cookie-kit' ); ?></p>
			<p class="description"><?php esc_html_e( 'Leave a field empty to use the default text (shown as placeholder), translated into the site language.', 'cookie-kit' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Name', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Description', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Required', 'cookie-kit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $categories as $id => $cat ) : ?>
						<tr>
							<td><code><?php echo esc_html( $id ); ?></code></td>
							<td><input type="text" name="cat[<?php echo esc_attr( $id ); ?>][name]" value="<?php echo esc_attr( $cat['name'] ?? '' ); ?>" placeholder="<?php echo esc_attr( $defaults[ $id ]['name'] ?? '' ); ?>"></td>
							<td><textarea name="cat[<?php echo esc_attr( $id ); ?>][description]" rows="2" cols="60" placeholder="<?php echo esc_attr( $defaults[ $id ]['description'] ?? '' ); ?>"><?php echo esc_textarea( $cat['description'] ?? '' ); ?></textarea></td>
							<td><input type="checkbox" name="cat[<?php echo esc_attr( $id ); ?>][required]" value="1" <?php checked( ! empty( $cat['required'] ) ); ?> <?php disabled( 'necessary' === $id ); ?>></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save categories', 'cookie-kit' ) ); ?>
		</form>
		<?php
	}

	private function view_vendors() {
		$vendors = get_option( 'ckit_vendors', array() );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ckit_save_vendors' ); ?>
			<input type="hidden" name="action" value="ckit_save_vendors">
			<p><?php esc_html_e( 'Third-party vendors. The "pattern" regex matches the scripts to block; "host" is the vendor\'s domain; "cookies" is the comma-separated list of cookie names the vendor sets (wildcard * supported).', 'cookie-kit' ); ?></p>
			<table class="widefat striped ckit-vendors-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Category', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Host', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Pattern (regex)', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Cookie (CSV)', 'cookie-kit' ); ?></th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody id="ckit-vendor-rows">
					<?php foreach ( $vendors as $i => $v ) : ?>
						<tr>
							<td><input type="text" name="vendor[<?php echo absint( $i ); ?>][name]" value="<?php echo esc_attr( $v['name'] ?? '' ); ?>"></td>
							<td>
								<select name="vendor[<?php echo absint( $i ); ?>][category]">
									<?php foreach ( CKIT_Core::categories() as $cid => $c ) : ?>
										<option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $v['category'] ?? '', $cid ); ?>><?php echo esc_html( $c['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="text" name="vendor[<?php echo absint( $i ); ?>][host]" value="<?php echo esc_attr( $v['host'] ?? '' ); ?>"></td>
							<td><input type="text" name="vendor[<?php echo absint( $i ); ?>][pattern]" value="<?php echo esc_attr( $v['pattern'] ?? '' ); ?>"></td>
							<td><input type="text" name="vendor[<?php echo absint( $i ); ?>][cookies]" value="<?php echo esc_attr( $v['cookies'] ?? '' ); ?>"></td>
							<td><button type="button" class="button-link ckit-remove-vendor"><?php esc_html_e( 'Remove', 'cookie-kit' ); ?></button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="ckit-add-vendor"><?php esc_html_e( 'Add vendor', 'cookie-kit' ); ?></button></p>
			<?php submit_button( __( 'Save vendors', 'cookie-kit' ) ); ?>
		</form>
		<script>
		(function(){
			var tbody = document.getElementById('ckit-vendor-rows');
			document.getElementById('ckit-add-vendor').addEventListener('click', function(){
				var i = tbody.children.length;
				var row = document.createElement('tr');
				row.innerHTML = '<td><input type="text" name="vendor['+i+'][name]"></td>'
					+ '<td><select name="vendor['+i+'][category]"><?php foreach ( CKIT_Core::categories() as $cid => $c ) : ?><option value="<?php echo esc_attr( $cid ); ?>"><?php echo esc_js( $c['name'] ); ?></option><?php endforeach; ?></select></td>'
					+ '<td><input type="text" name="vendor['+i+'][host]"></td>'
					+ '<td><input type="text" name="vendor['+i+'][pattern]"></td>'
					+ '<td><input type="text" name="vendor['+i+'][cookies]"></td>'
					+ '<td><button type="button" class="button-link ckit-remove-vendor"><?php echo esc_js( __( 'Remove', 'cookie-kit' ) ); ?></button></td>';
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
		<h2 style="margin-top:0;color:#0a3d62"><?php esc_html_e( '★ Browser scan (recommended)', 'cookie-kit' ); ?></h2>
		<p><?php esc_html_e( 'Opens the site home page in a new tab with a script that simulates accepting cookies and captures every cookie actually set in the browser, including those set client-side by JavaScript such as Google Analytics, GTM, Meta Pixel, etc. This is the most accurate scan.', 'cookie-kit' ); ?></p>
		<p>
			<a class="button button-primary button-hero" href="<?php echo esc_url( $home_snap ); ?>" target="_blank" rel="noopener">▶ <?php esc_html_e( 'Start browser scan', 'cookie-kit' ); ?></a>
		</p>
		<p class="description">
			<?php esc_html_e( 'When the new tab opens, let the script work: it waits about 5 seconds on each page (home + up to 2 pages + 2 posts) and shows an overlay at the top right. When it is done, come back here and reload the page to see the updated cookies in the table.', 'cookie-kit' ); ?>
		</p>

		<hr style="margin:24px 0">
		<h2><?php esc_html_e( 'Passive server-side scan', 'cookie-kit' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Server-side HTTP requests to identify cookies set before JavaScript runs (Cloudflare, PHP session, frameworks). It does not replace the browser scan; it complements it.', 'cookie-kit' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
			<?php wp_nonce_field( 'ckit_run_scanner' ); ?>
			<input type="hidden" name="action" value="ckit_run_scanner">
			<?php submit_button( __( 'Run passive scan', 'cookie-kit' ), 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:8px">
			<?php wp_nonce_field( 'ckit_clear_scanner' ); ?>
			<input type="hidden" name="action" value="ckit_clear_scanner">
			<?php submit_button( __( 'Clear all results', 'cookie-kit' ), 'delete', 'submit', false ); ?>
		</form>
		<hr style="margin:24px 0">
		<?php if ( $rows ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Cookie', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Domain', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Category', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Party', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Vendor', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Duration', 'cookie-kit' ); ?></th>
						<th><?php esc_html_e( 'Found on', 'cookie-kit' ); ?></th>
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
							<td><a href="<?php echo esc_url( $r->source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( wp_parse_url( $r->source_url, PHP_URL_PATH ) ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><em><?php esc_html_e( 'No scan run yet.', 'cookie-kit' ); ?></em></p>
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
			<div><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> <?php esc_html_e( 'total consents', 'cookie-kit' ); ?></div>
			<div><strong><?php echo esc_html( number_format_i18n( $accept ) ); ?></strong> <?php esc_html_e( 'accept all', 'cookie-kit' ); ?></div>
			<div><strong><?php echo esc_html( number_format_i18n( $reject ) ); ?></strong> <?php esc_html_e( 'reject all', 'cookie-kit' ); ?></div>
			<div><strong><?php echo esc_html( number_format_i18n( $custom ) ); ?></strong> <?php esc_html_e( 'customized', 'cookie-kit' ); ?></div>
		</div>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ckit_export_log&format=csv' ), 'ckit_export_log' ) ); ?>"><?php esc_html_e( 'Export CSV', 'cookie-kit' ); ?></a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ckit_export_log&format=json' ), 'ckit_export_log' ) ); ?>"><?php esc_html_e( 'Export JSON', 'cookie-kit' ); ?></a>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>ID</th>
					<th><?php esc_html_e( 'Date/time (UTC)', 'cookie-kit' ); ?></th>
					<th>UUID</th>
					<th><?php esc_html_e( 'Action', 'cookie-kit' ); ?></th>
					<th><?php esc_html_e( 'Categories', 'cookie-kit' ); ?></th>
					<th><?php esc_html_e( 'Policy version', 'cookie-kit' ); ?></th>
					<th><?php esc_html_e( 'Page', 'cookie-kit' ); ?></th>
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
						<td><a href="<?php echo esc_url( $r->page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( wp_parse_url( $r->page_url, PHP_URL_PATH ) ); ?></a></td>
						<td><?php echo esc_html( $r->language ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function view_texts() {
		$texts    = get_option( 'ckit_texts', array() );
		$texts    = is_array( $texts ) ? $texts : array();
		$defaults = CKIT_Core::default_texts();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ckit_save_texts' ); ?>
			<input type="hidden" name="action" value="ckit_save_texts">
			<p class="description"><?php esc_html_e( 'Leave a field empty to use the default text (shown as placeholder), translated into the site language. In the messages you can add links with the syntax [link text](https://example.com/page/).', 'cookie-kit' ); ?></p>
			<table class="form-table">
				<?php
				$labels = array(
					'banner_title'      => __( 'Banner title', 'cookie-kit' ),
					'banner_message'    => __( 'Banner message', 'cookie-kit' ),
					'btn_accept_all'    => __( '"Accept all" button', 'cookie-kit' ),
					'btn_reject_all'    => __( '"Reject all" button', 'cookie-kit' ),
					'btn_customize'     => __( '"Customize" button', 'cookie-kit' ),
					'btn_save'          => __( '"Save preferences" button', 'cookie-kit' ),
					'btn_close'         => __( '"Close" button', 'cookie-kit' ),
					'modal_title'       => __( 'Preferences modal title (empty = use Banner title)', 'cookie-kit' ),
					'modal_intro'       => __( 'Preferences modal intro (empty = use Banner message)', 'cookie-kit' ),
					'floating_button'   => __( 'Footer button label', 'cookie-kit' ),
					'privacy_link_text' => __( 'Privacy link label', 'cookie-kit' ),
					'cookies_link_text' => __( 'Cookie Policy link label', 'cookie-kit' ),
				);
				foreach ( $labels as $k => $label ) :
					$is_long = in_array( $k, array( 'banner_message', 'modal_intro' ), true );
					?>
					<tr>
						<th><?php echo esc_html( $label ); ?></th>
						<td>
							<?php if ( $is_long ) : ?>
								<textarea name="texts[<?php echo esc_attr( $k ); ?>]" rows="4" cols="80" placeholder="<?php echo esc_attr( $defaults[ $k ] ?? '' ); ?>"><?php echo esc_textarea( $texts[ $k ] ?? '' ); ?></textarea>
							<?php else : ?>
								<input type="text" class="large-text" name="texts[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $texts[ $k ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $defaults[ $k ] ?? '' ); ?>">
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( __( 'Save texts', 'cookie-kit' ) ); ?>
		</form>
		<?php
	}

	// ==================== Handlers ====================

	public function handle_scanner() {
		check_admin_referer( 'ckit_run_scanner' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'cookie-kit' ), 403 );
		}
		$scanner = CKIT_Core::instance()->scanner;
		$scanner->scan_urls( $scanner->default_urls() );
		wp_safe_redirect( add_query_arg( 'tab', 'scanner', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	public function handle_scanner_clear() {
		check_admin_referer( 'ckit_clear_scanner' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'cookie-kit' ), 403 );
		}
		CKIT_Core::instance()->scanner->clear();
		wp_safe_redirect( add_query_arg( 'tab', 'scanner', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	public function handle_export_log() {
		check_admin_referer( 'ckit_export_log' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'cookie-kit' ), 403 );
		}
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
		$log    = CKIT_Core::instance()->log;
		nocache_headers();
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
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'cookie-kit' ), 403 );
		}
		$cat_in = isset( $_POST['cat'] ) && is_array( $_POST['cat'] ) ? wp_unslash( $_POST['cat'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$out    = array();
		foreach ( $cat_in as $id => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = sanitize_key( $id );
			$out[ $id ] = array(
				'name'        => sanitize_text_field( $row['name'] ?? '' ),
				'description' => sanitize_textarea_field( $row['description'] ?? '' ),
				'required'    => ! empty( $row['required'] ) ? 1 : 0,
			);
		}
		// "necessary" is always required.
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
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'cookie-kit' ), 403 );
		}
		$in  = isset( $_POST['vendor'] ) && is_array( $_POST['vendor'] ) ? wp_unslash( $_POST['vendor'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$out = array();
		foreach ( $in as $row ) {
			if ( ! is_array( $row ) || empty( $row['name'] ) ) {
				continue;
			}
			$pattern = sanitize_text_field( $row['pattern'] ?? '' );
			// Drop invalid regular expressions instead of storing a pattern that can never match.
			if ( '' !== $pattern && false === @preg_match( $pattern, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$pattern = '';
			}
			$out[] = array(
				'name'     => sanitize_text_field( $row['name'] ),
				'category' => sanitize_key( $row['category'] ?? 'marketing' ),
				'host'     => sanitize_text_field( $row['host'] ?? '' ),
				'pattern'  => $pattern,
				'cookies'  => sanitize_text_field( $row['cookies'] ?? '' ),
			);
		}
		update_option( 'ckit_vendors', $out );
		wp_safe_redirect( add_query_arg( 'tab', 'vendors', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * Text keys editable from the "Texts" tab.
	 *
	 * @return string[]
	 */
	private function text_keys() {
		return array( 'banner_title', 'banner_message', 'btn_accept_all', 'btn_reject_all', 'btn_customize', 'btn_save', 'btn_close', 'modal_title', 'modal_intro', 'floating_button', 'privacy_link_text', 'cookies_link_text' );
	}

	public function handle_save_texts() {
		check_admin_referer( 'ckit_save_texts' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'cookie-kit' ), 403 );
		}
		$in  = isset( $_POST['texts'] ) && is_array( $_POST['texts'] ) ? wp_unslash( $_POST['texts'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$out = get_option( 'ckit_texts', array() );
		$out = is_array( $out ) ? $out : array();
		foreach ( $in as $k => $v ) {
			$k = sanitize_key( $k );
			// Only known text keys, only strings.
			if ( ! in_array( $k, $this->text_keys(), true ) || ! is_string( $v ) ) {
				continue;
			}
			$out[ $k ] = sanitize_textarea_field( $v );
		}
		update_option( 'ckit_texts', $out );
		wp_safe_redirect( add_query_arg( 'tab', 'texts', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}
}
