<?php
if (!defined('ABSPATH')) {
	exit;
}

class TrustScript_Plugin_Admin
{

	private static $instance = null;

	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Find WooCommerce order by review token
	 * 
	 * @param string $unique_token The review token to search for
	 * @return WC_Order|null Order object if token found on real order, null otherwise
	 */
	public static function find_order_by_review_token($unique_token)
	{
		if (empty($unique_token) || !is_string($unique_token)) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for cross-storage compatibility, caching handled by wc_get_order()
		$order_ids = wc_get_orders(array(
			'limit' => 1,
			'return' => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key' => '_trustscript_review_token',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value' => $unique_token,
		));

		if (!empty($order_ids)) {
			$order_id = $order_ids[0];

			$order = wc_get_order($order_id);
			if ($order) {
				return $order;
			}
		}

		$order_ids_by_order_token = wc_get_orders(array(
			'limit' => 1,
			'return' => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key' => '_trustscript_order_token',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value' => $unique_token,
		));

		if (!empty($order_ids_by_order_token)) {
			$order = wc_get_order($order_ids_by_order_token[0]);
			if ($order) {
				return $order;
			}
		}

		return null;
	}

	private function __construct()
	{
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_notices', array($this, 'display_api_key_invalid_notice'));
		add_action('admin_notices', array($this, 'display_quota_exceeded_notice'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('wp_ajax_trustscript_delete_api_key', array($this, 'handle_delete_api_key'));
		add_action('wp_ajax_trustscript_save_review_settings', array($this, 'handle_save_review_settings'));
		add_action('wp_ajax_trustscript_save_moderation_settings', array($this, 'handle_save_moderation_settings'));
		add_action('wp_ajax_trustscript_sync_orders', array($this, 'handle_sync_orders'));
		add_action('wp_ajax_trustscript_save_service_settings', array($this, 'handle_save_service_settings'));
		add_action('wp_ajax_trustscript_save_optional_data_settings', array($this, 'handle_save_optional_data_settings'));
		add_action('wp_ajax_trustscript_dismiss_notice', array($this, 'handle_dismiss_notice'));
		add_action('wp_ajax_trustscript_save_trust_strip_settings', array($this, 'handle_save_trust_strip_settings'));
		add_action('wp_ajax_trustscript_save_uninstall_preference', array($this, 'handle_save_uninstall_preference'));
		add_action('wp_ajax_trustscript_save_privacy_settings',    array( new TrustScript_Privacy_Settings_Page(), 'handle_save_privacy_settings' ) );
		add_action('wp_ajax_trustscript_clear_logs', array($this, 'handle_clear_logs'));
		add_action('update_option_trustscript_api_key', array($this, 'on_api_key_updated'), 10, 2);
		add_action('admin_init', array($this, 'maybe_redirect_after_api_key_save'));
		add_action('wp_ajax_trustscript_save_verification_modal_settings', array($this, 'handle_save_verification_modal_settings'));
		add_action('transition_comment_status', array($this, 'handle_edit_approval'), 10, 3);
		add_filter('comment_text', array($this, 'modify_admin_comment_text'), 10, 2);
	}

	/**
	 * After saving an API key from the first-time setup page.
	 */
	public function maybe_redirect_after_api_key_save()
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect logic only, no data processing.
		if (!isset($_GET['page']) || $_GET['page'] !== 'trustscript-settings') {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (empty($_GET['first-time']) || empty($_GET['settings-updated'])) {
			return;
		}
		if (empty(get_option('trustscript_api_key', ''))) {
			return;
		}
		wp_safe_redirect(admin_url('admin.php?page=trustscript-settings&settings-updated=true'));
		exit;
	}

	/**
	 * Handle approval of a shadow review edit.
	 */
	public function handle_edit_approval( $new_status, $old_status, $comment ) {
		if ( $new_status === 'approved' && $old_status !== 'approved' && $comment->comment_type === 'review' ) {
			$original_id = get_comment_meta( $comment->comment_ID, '_trustscript_edit_of', true );
			if ( $original_id ) {
				$original = get_comment( $original_id );
				if ( $original ) {
					$update_data = array(
						'comment_ID'      => $original_id,
						'comment_content' => $comment->comment_content,
					);
					wp_update_comment( $update_data );
					
					$rating = get_comment_meta( $comment->comment_ID, 'rating', true );
					if ( $rating ) {
						update_comment_meta( $original_id, 'rating', $rating );
						update_comment_meta( $original_id, '_trustscript_rating', $rating );
					}
					
					$photos = get_comment_meta( $comment->comment_ID, '_trustscript_media_urls', true );
					if ( $photos ) {
						update_comment_meta( $original_id, '_trustscript_media_urls', $photos );
					}
					
					wp_trash_comment( $comment->comment_ID );

					if ( class_exists( 'TrustScript_Review_Renderer' ) ) {
						TrustScript_Review_Renderer::flush_stats_cache( $comment->comment_post_ID );
					}
					if ( class_exists( 'TrustScript_Shop_Display' ) ) {
						TrustScript_Shop_Display::clear_product_rating_cache( $comment->comment_post_ID );
					}
				}
			}
		}
	}

	public function modify_admin_comment_text( $comment_text, $comment ) {
		if ( ! is_admin() || $comment->comment_type !== 'review' ) {
			return $comment_text;
		}

		$append = '';
		$media_json = get_comment_meta( $comment->comment_ID, '_trustscript_media_urls', true );
		if ( ! empty( $media_json ) ) {
			$media = json_decode( $media_json, true );
			if ( is_array( $media ) && ! empty( $media ) ) {
				$append .= '<div style="margin-top: 10px;">';
				$append .= '<strong>' . esc_html__( 'Attached Photos:', 'trustscript' ) . '</strong><br>';
				foreach ( $media as $url ) {
					$append .= '<a href="' . esc_url( $url ) . '" target="_blank"><img src="' . esc_url( $url ) . '" style="max-width: 80px; max-height: 80px; border-radius: 4px; margin-right: 8px; margin-top: 5px; border: 1px solid #ddd;" /></a>';
				}
				$append .= '</div>';
			}
		}

		if ( $comment->comment_approved === '0' ) {
			$original_id = get_comment_meta( $comment->comment_ID, '_trustscript_edit_of', true );
			if ( $original_id ) {
				$original = get_comment( $original_id );
				if ( $original ) {
					$old_rating = get_comment_meta( $original_id, '_trustscript_rating', true ) ?: get_comment_meta( $original_id, 'rating', true );
					$new_rating = get_comment_meta( $comment->comment_ID, 'rating', true );
					
					$append .= '<div style="margin-top: 15px; padding: 10px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px;">';
					$append .= '<strong style="color: #1e3a8a;">' . esc_html__( '✏️ This is an EDIT of an existing review.', 'trustscript' ) . '</strong><br><br>';
					$append .= '<strong>' . esc_html__( 'Original Rating:', 'trustscript' ) . '</strong> ' . esc_html( $old_rating ) . ' ★<br>';
					$append .= '<strong>' . esc_html__( 'New Rating:', 'trustscript' ) . '</strong> ' . esc_html( $new_rating ) . ' ★<br><br>';
					$append .= '<strong>' . esc_html__( 'Original Text:', 'trustscript' ) . '</strong><br><blockquote style="margin: 5px 0 0 0; padding-left: 10px; border-left: 4px solid #93c5fd; color: #4b5563;">' . wp_kses_post( $original->comment_content ) . '</blockquote>';
					
					$old_media_json = get_comment_meta( $original_id, '_trustscript_media_urls', true );
					if ( ! empty( $old_media_json ) ) {
						$old_media = json_decode( $old_media_json, true );
						if ( is_array( $old_media ) && ! empty( $old_media ) ) {
							$append .= '<br><strong>' . esc_html__( 'Original Photos:', 'trustscript' ) . '</strong><br>';
							foreach ( $old_media as $url ) {
								$append .= '<a href="' . esc_url( $url ) . '" target="_blank"><img src="' . esc_url( $url ) . '" style="max-width: 60px; max-height: 60px; border-radius: 4px; margin-right: 8px; margin-top: 5px; opacity: 0.7; border: 1px solid #bfdbfe;" /></a>';
							}
						}
					}

					$append .= '</div>';
				}
			}
		}

		return $comment_text . $append;
	}
	private function __clone()
	{
		// Singleton pattern - do not allow cloning
	}

	public function __wakeup()
	{
		throw new Exception('Cannot unserialize singleton');
	}

	public function add_admin_menu()
	{
		// Main Menu — Analytics is the landing page
		add_menu_page(
			__('Analytics', 'trustscript'),
			__('TrustScript', 'trustscript'),
			'manage_options',
			'trustscript-reviews',
			array('TrustScript_Review_Request_Page', 'render'),
			'dashicons-star-filled',
			58
		);

		add_submenu_page(
			'trustscript-reviews',
			__('Analytics', 'trustscript'),
			__('Analytics', 'trustscript'),
			'manage_options',
			'trustscript-reviews',
			array('TrustScript_Review_Request_Page', 'render')
		);

		add_submenu_page(
			'trustscript-reviews',
			__('Review Settings', 'trustscript'),
			__('Review Settings', 'trustscript'),
			'manage_options',
			'trustscript-review-settings',
			array('TrustScript_Reviews_Page', 'render')
		);

		add_submenu_page(
			'trustscript-reviews',
			__('Keyword Blocklist', 'trustscript'),
			__('Keyword Blocklist', 'trustscript'),
			'manage_options',
			'trustscript-keyword-blocklist',
			array('TrustScript_Review_Guard', 'render_page')
		);

		add_submenu_page(
			'trustscript-reviews',
			__('API Settings', 'trustscript'),
			__('API Settings', 'trustscript'),
			'manage_options',
			'trustscript-settings',
			array('TrustScript_Settings_Page', 'render')
		);

		add_submenu_page(
			'trustscript-reviews',
			__('Privacy & Compliance', 'trustscript'),
			__('Privacy & Compliance', 'trustscript'),
			'manage_options',
			'trustscript-privacy',
			array('TrustScript_Privacy_Settings_Page', 'render')
		);

		add_submenu_page(
			'trustscript-reviews',
			__('Review Form Setup', 'trustscript'),
			__('Review Form', 'trustscript'),
			'manage_options',
			'trustscript-review-form-setup',
			array( __CLASS__, 'render_review_form_setup_page' )
		);

		$queue_count = absint( TrustScript_Queue::count_pending() );
		$queue_label = __('Pending Queue', 'trustscript');
		if ($queue_count > 0) {
			$queue_label .= ' <span class="update-plugins count-' . $queue_count . '"><span class="plugin-count">' . $queue_count . '</span></span>';
		}
		add_submenu_page(
			'trustscript-reviews', 
			__('Pending Queue', 'trustscript'),
			$queue_label,
			'manage_options',
			'trustscript-queue',
			array('TrustScript_Pending_Queue_Page', 'render')
		);

	}

	/**
	 * Render the Review Form Setup admin page.
	 */
	public static function render_review_form_setup_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'trustscript' ) );
		}
		wp_enqueue_editor();
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Review Form Setup', 'trustscript' ) . '</h1>';
		require_once plugin_dir_path( __FILE__ ) . 'admin/review-form.php';
		echo '</div>';
	}

	/**
	 * Display API key invalid notice on TrustScript admin pages
	 */
	public function display_api_key_invalid_notice()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$screen = get_current_screen();
		if (!$screen || !is_string($screen->id) || false === strpos($screen->id, 'trustscript')) {
			return;
		}

		$invalid_notice = get_transient('trustscript_api_key_invalid_notice');

		if (!$invalid_notice) {
			return;
		}

		?>
		<div class="notice notice-error is-dismissible trustscript-dismissible-notice" data-notice="api_key_invalid">
			<h3><?php esc_html_e('API Key Invalid or Expired', 'trustscript'); ?></h3>
			<p><?php esc_html_e('Your TrustScript API key is no longer valid. This might be because:', 'trustscript'); ?></p>
			<ul>
				<li><?php esc_html_e('The key has expired (development keys expire after 24 hours)', 'trustscript'); ?></li>
				<li><?php esc_html_e('The key was deleted from your TrustScript dashboard', 'trustscript'); ?></li>
				<li><?php esc_html_e('Your account or key was revoked', 'trustscript'); ?></li>
			</ul>
			<p>
				<a href="<?php echo esc_url(admin_url('admin.php?page=trustscript-settings')); ?>"
					class="button button-primary">
					<?php esc_html_e('Go to Settings & Update Key', 'trustscript'); ?>
				</a>
				<a href="<?php echo esc_url( TRUSTSCRIPT_DASHBOARD_URL . '/api-keys' ); ?>" target="_blank" class="button">
					<?php esc_html_e('Generate New Key', 'trustscript'); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Display quota exceeded notice on TrustScript admin pages
	 */
	public function display_quota_exceeded_notice()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$screen = get_current_screen();
		if (!$screen || !is_string($screen->id) || false === strpos($screen->id, 'trustscript')) {
			return;
		}

		$quota_info = get_transient('trustscript_quota_exceeded_notice');

		if (!$quota_info || !isset($quota_info['quotaExceeded']) || !$quota_info['quotaExceeded']) {
			return;
		}

		$current_plan = isset($quota_info['currentPlan']) ? sanitize_text_field($quota_info['currentPlan']) : 'unknown';
		$next_plan = isset($quota_info['nextPlan']) ? sanitize_text_field($quota_info['nextPlan']) : null;
		$next_limit = isset($quota_info['nextLimit']) ? intval($quota_info['nextLimit']) : null;
		$reset_date = isset($quota_info['resetDate']) ? sanitize_text_field($quota_info['resetDate']) : null;
		$plan_label = ucfirst(str_replace('_', ' ', $current_plan));

		$upgrade_message = sprintf(
			/* translators: %s: plan name */
			esc_html__('Monthly review limit reached for your %s plan.', 'trustscript'),
			esc_html($plan_label)
		);

		if ($next_plan && $next_limit) {
			$next_plan_label = ucfirst(str_replace('_', ' ', $next_plan));
			$upgrade_message .= sprintf(
				/* translators: %1$s: next plan name, %2$d: review limit */
				esc_html__(' Upgrade to %1$s for %2$d reviews per month.', 'trustscript'),
				esc_html($next_plan_label),
				intval($next_limit)
			);
		}

		if ($reset_date) {
			try {
				$reset_datetime = new DateTime($reset_date, new DateTimeZone('UTC'));
				$formatted_date = $reset_datetime->format('F j, Y');
				$upgrade_message .= sprintf(
					/* translators: %s: reset date */
					esc_html__(' Or wait until %s for your limit to reset.', 'trustscript'),
					esc_html($formatted_date)
				);
			} catch (Exception $e) {
				$upgrade_message .= sprintf(
					/* translators: %s: reset date */
					esc_html__(' Or wait until %s for your limit to reset.', 'trustscript'),
					esc_html($reset_date)
				);
			}
		}

		?>
		<div class="notice notice-warning is-dismissible trustscript-dismissible-notice" data-notice="quota_exceeded">
			<h3><?php esc_html_e('Review Quota Limit Reached', 'trustscript'); ?></h3>
			<p><?php echo wp_kses_post($upgrade_message); ?></p>
			<p>
				<a href="<?php echo esc_url( TRUSTSCRIPT_PRICING_URL ); ?>" target="_blank" class="button button-primary">
					<?php esc_html_e('Upgrade Your Plan', 'trustscript'); ?>
				</a>
			</p>
		</div>
		<?php
	}

	public function register_settings()
	{
		register_setting('trustscript_options', 'trustscript_api_key', array(
			'sanitize_callback' => array($this, 'sanitize_api_key'),
		));
		register_setting('trustscript_options', 'trustscript_webhook_secret', array(
			'type' => 'string',
			'sanitize_callback' => array($this, 'sanitize_webhook_secret'),
			'default' => '',
		));
		register_setting('trustscript_options', 'trustscript_data_consent', array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		));
		register_setting( 'trustscript_options', 'trustscript_enable_trust_strip', array(
			'type'              => 'boolean',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => true,
		) );
		register_setting('trustscript_options', 'trustscript_enable_verification_modal', array(
			'type' => 'boolean',
			'sanitize_callback' => array($this, 'sanitize_checkbox'),
			'default' => true,
		));
		register_setting('trustscript_options', 'trustscript_simple_review_enabled', array(
			'type' => 'boolean',
			'sanitize_callback' => array($this, 'sanitize_checkbox'),
			'default' => true,
		));
		register_setting('trustscript_options', 'trustscript_api_review_collection_enabled', array(
			'type' => 'boolean',
			'sanitize_callback' => array($this, 'sanitize_checkbox'),
			'default' => false,
		));
	}

	/**
	 * Sanitize checkbox value
	 */
	public function sanitize_checkbox($value)
	{
		return !empty($value) ? true : false;
	}

	/**
	 * Validate and sanitize the webhook secret when it's saved.
	 */
	public function sanitize_webhook_secret($value)
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by settings_fields() / options.php handler.
		$is_api_key_form = ! empty( $_POST['trustscript_api_key_form_intent'] );

		$value = sanitize_text_field($value);
		if (empty($value)) {
			$existing = get_option('trustscript_webhook_secret', '');
			if (!empty($existing)) {
				return $existing;
			}
			return '';
		}

		if ( ! $is_api_key_form ) {
			return $value;
		}

		if (!preg_match('/^TSS-[A-F0-9-]+$/i', $value)) {
			$decrypted = trustscript_decrypt_data($value);
			if (!empty($decrypted) && preg_match('/^TSS-[A-F0-9-]+$/i', $decrypted)) {
				return $value;
			}
			
			add_settings_error(
				'trustscript_webhook_secret',
				'invalid_format',
				esc_html__('Invalid webhook secret format. Webhook secrets should look like: TSS-XXXX-XXXX-XXXX', 'trustscript')
			);
			return '';
		}

		$encrypted = trustscript_encrypt_data( $value );
		
		if (empty($encrypted)) {
			add_settings_error(
				'trustscript_webhook_secret',
				'encryption_failed',
				esc_html__('Failed to encrypt webhook secret. Please try again.', 'trustscript')
			);
			return '';
		}
		
		return $encrypted;
	}

	/**
	 * Validate and sanitize the API key when it's saved.
	 */

	public function sanitize_api_key($value)	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by settings_fields() / options.php handler.
		$is_api_key_form = ! empty( $_POST['trustscript_api_key_form_intent'] );

		$value = sanitize_text_field($value);

		if (empty($value)) {
			$existing = get_option('trustscript_api_key', '');
			if (!empty($existing)) {
				return $existing;
			}
			if ( $is_api_key_form ) {
				delete_transient('trustscript_base_url');
			}
			return '';
		}

		if ( ! $is_api_key_form ) {
			return $value;
		}

		if (!preg_match('/^TSK-[A-F0-9-]+$/i', $value)) {
			$decrypted = trustscript_decrypt_data($value);
			if (!empty($decrypted) && preg_match('/^TSK-[A-F0-9-]+$/i', $decrypted)) {
				return $value;
			}
			
			add_settings_error(
				'trustscript_api_key',
				'invalid_format',
				esc_html__('Invalid API key format. API keys should look like: TSK-XXXX-XXXX-XXXX', 'trustscript')
			);
			return '';
		}

		$site_url = get_site_url();
		$site_url_normalized = rtrim($site_url, '/');

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by settings_fields() / options.php handler.
		$post_consent = isset($_POST['trustscript_data_consent']) ? sanitize_text_field(wp_unslash($_POST['trustscript_data_consent'])) : '';
		$data_consent = !empty($post_consent) ? $post_consent : get_option('trustscript_data_consent', '');
		if (empty($data_consent)) {
			add_settings_error(
				'trustscript_api_key',
				'consent_required',
				esc_html__('You must agree to data sharing before verifying your API key.', 'trustscript')
			);
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by settings_fields() / options.php handler.
		$post_webhook_secret = isset($_POST['trustscript_webhook_secret']) ? sanitize_text_field(wp_unslash($_POST['trustscript_webhook_secret'])) : '';
		$webhook_secret = !empty($post_webhook_secret) ? $post_webhook_secret : get_option('trustscript_webhook_secret', '');

		if (empty($webhook_secret)) {
			add_settings_error(
				'trustscript_api_key',
				'webhook_secret_required',
				esc_html__('Webhook secret is required. Please generate both an API key and webhook secret from the TrustScript dashboard.', 'trustscript')
			);
			return '';
		}

		if (!preg_match('/^TSS-[A-F0-9-]+$/i', $webhook_secret)) {
			add_settings_error(
				'trustscript_api_key',
				'invalid_webhook_secret_format',
				esc_html__('Invalid webhook secret format. Webhook secrets should look like: TSS-XXXX-XXXX-XXXX', 'trustscript')
			);
			return '';
		}

		$verify_url = apply_filters('trustscript_verify_endpoint', TRUSTSCRIPT_VERIFY_ENDPOINT);

		$args = array(
			'method' => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			),
			'body' => wp_json_encode(array(
				'apiKey' => $value,
				'domain' => $site_url_normalized,
			)),
			'timeout' => 15,
			'sslverify' => true,
		);

		$response = wp_remote_post($verify_url, $args);

		if (is_wp_error($response)) {
			add_settings_error(
				'trustscript_api_key',
				'verify_failed',
				sprintf(
					/* translators: %s: error message */
					esc_html__('Could not verify your API key with TrustScript: %s. Please check your internet connection and try again.', 'trustscript'),
					esc_html($response->get_error_message())
				)
			);
			return '';
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($code < 200 || $code >= 300 || !$data || empty($data['valid'])) {
			$message = 'API key verification failed (HTTP ' . $code . ').';
			if ($data && !empty($data['message'])) {
				$message = $data['message'];
			}
			add_settings_error('trustscript_api_key', 'verify_failed', esc_html($message));
			return '';
		}

		if (!empty($data['apiUrl'])) {
			update_option('trustscript_base_url', $data['apiUrl']);


			set_transient('trustscript_base_url', $data['apiUrl'], 3600);
		}

		delete_transient('trustscript_user_plan');
		delete_transient('trustscript_api_key_invalid_notice');
		delete_transient('trustscript_quota_exceeded_notice');

		$current_errors = get_settings_errors('trustscript_api_key');
		$already_added = false;
		foreach ($current_errors as $err) {
			if ($err['code'] === 'api_key_verified') {
				$already_added = true;
				break;
			}
		}

		if (!$already_added) {
			$success_message = esc_html__('API key verified successfully! Your site is now connected to TrustScript.', 'trustscript');

			if (isset($data['quota']) && is_array($data['quota'])) {
				$quota = $data['quota'];
				$limit = isset($quota['limit']) ? intval($quota['limit']) : 0;
				$used = isset($quota['used']) ? intval($quota['used']) : 0;
				$remaining = isset($quota['remaining']) ? intval($quota['remaining']) : 0;
				$reset = isset($quota['resetDate']) ? sanitize_text_field($quota['resetDate']) : '';
				$exceeded = isset($quota['isExceeded']) ? boolval($quota['isExceeded']) : false;
				$plan = isset($quota['plan']) ? sanitize_text_field($quota['plan']) : '';
				$next_plan = isset($quota['nextPlan']) ? sanitize_text_field($quota['nextPlan']) : '';
				$next_limit = isset($quota['nextLimit']) ? intval($quota['nextLimit']) : 0;

				// Cache the plan for internal use
				if (!empty($plan)) {
					set_transient('trustscript_user_plan', $plan, WEEK_IN_SECONDS);
				}

				if ($exceeded) {
					$plan_label = !empty($plan) ? ucfirst(str_replace('_', ' ', $plan)) : 'unknown';
					$success_message = sprintf(
						/* translators: %s: plan name */
						esc_html__('Monthly review limit reached for your %s plan.', 'trustscript'),
						esc_html($plan_label)
					);

					if (!empty($next_plan) && $next_limit > 0) {
						$next_plan_label = ucfirst(str_replace('_', ' ', $next_plan));
						$success_message .= sprintf(
							/* translators: %1$s: next plan name, %2$d: review limit */
							esc_html__(' Upgrade to %1$s for %2$d reviews per month.', 'trustscript'),
							esc_html($next_plan_label),
							intval($next_limit)
						);
					}

					if (!empty($reset)) {
						try {
							$reset_datetime = new DateTime($reset, new DateTimeZone('UTC'));
							$formatted_date = $reset_datetime->format('F j, Y');
							$success_message .= sprintf(
								/* translators: %s: reset date */
								esc_html__(' Or wait until %s for your limit to reset.', 'trustscript'),
								esc_html($formatted_date)
							);
						} catch (Exception $e) {
							$success_message .= sprintf(
								/* translators: %s: reset date */
								esc_html__(' Or wait until %s for your limit to reset.', 'trustscript'),
								esc_html($reset)
							);
						}
					}
					$notice_type = 'warning';
				} else {
					$formatted_reset = $reset;
					if (!empty($reset)) {
						try {
							$reset_datetime = new DateTime($reset, new DateTimeZone('UTC'));
							$formatted_reset = $reset_datetime->format('F j, Y');
						} catch (Exception $e) {
							$formatted_reset = $reset;
						}
					}
					$success_message .= sprintf(
						/* translators: 1: remaining reviews, 2: total limit, 3: reset date */
						esc_html__(' You have %1$d/%2$d reviews remaining this month (resets on %3$s).', 'trustscript'),
						$remaining,
						$limit,
						$formatted_reset
					);
					$notice_type = 'success';
				}

				set_transient(
					'trustscript_last_quota',
					array(
						'limit' => $limit,
						'used' => $used,
						'remaining' => $remaining,
						'isExceeded' => $exceeded,
						'resetDate' => $reset,
						'plan' => $plan,
						'nextPlan' => $next_plan,
						'nextLimit' => $next_limit,
						'timestamp' => time(),
					),
					3600
				);
			} else {
				$notice_type = 'success';
			}

			add_settings_error(
				'trustscript_api_key',
				'api_key_verified',
				$success_message,
				$notice_type
			);
		}

		$encrypted = trustscript_encrypt_data( $value );
		
		if (empty($encrypted)) {
			add_settings_error(
				'trustscript_api_key',
				'encryption_failed',
				esc_html__('Failed to encrypt API key. Please try again.', 'trustscript')
			);
			return '';
		}
		
		return $encrypted;
	}

	/**
	 * Handle API key updates: if the key changed and we have pending queue items, attempt to auto-process the queue with the new key. This allows merchants to fix their connection issues by simply updating/pasting a valid API key, without needing to manually trigger a separate "process queue" action after reconnecting.
	 * 
	 * @param mixed $old_value The old option value
	 * @param mixed $new_value The new option value
	 * @return void
	 */
	public function on_api_key_updated($old_value, $new_value)
	{
		if ($old_value === $new_value) {
			return;
		}

		if (empty($new_value)) {
			return;
		}

		$pending_count = TrustScript_Queue::count_pending();
		if ($pending_count === 0) {
			return;
		}

		do_action('trustscript_process_quota_queue');
	}

	/**
	 * Get TrustScript base URL
	 */
	public function get_trustscript_base_url()
	{
		return trustscript_get_base_url();
	}

	public function enqueue_assets($hook)
	{
		if (!is_string($hook) || false === strpos($hook, 'trustscript')) {
			return;
		}

		// Enqueue CSS & JS & Media Library
		$base_url = TRUSTSCRIPT_PLUGIN_URL;
		$base_dir = TRUSTSCRIPT_PLUGIN_PATH;
		$admin_css_ver = file_exists($base_dir . 'assets/css/trustscript-admin.css') ? filemtime($base_dir . 'assets/css/trustscript-admin.css') : '0.2.0';
		$admin_notices_css_ver = file_exists($base_dir . 'assets/css/trustscript-admin-notices.css') ? filemtime($base_dir . 'assets/css/trustscript-admin-notices.css') : '0.2.0';
		$admin_js_ver = file_exists($base_dir . 'assets/js/admin.js') ? filemtime($base_dir . 'assets/js/admin.js') : '0.2.0';
		wp_enqueue_style('trustscript-admin-css', $base_url . 'assets/css/trustscript-admin.css', array(), $admin_css_ver);
		wp_enqueue_style('trustscript-admin-notices', $base_url . 'assets/css/trustscript-admin-notices.css', array(), $admin_notices_css_ver);
		wp_enqueue_script('trustscript-admin-js', $base_url . 'assets/js/admin.js', array('jquery'), $admin_js_ver, true);

		if (strpos($hook, 'trustscript-reviews-list') !== false || strpos($hook, 'trustscript-review-form-setup') !== false || strpos($hook, 'trustscript-review-settings') !== false || strpos($hook, 'trustscript-keyword-blocklist') !== false) {
			$reviews_js_ver = file_exists($base_dir . 'assets/js/reviews.js') ? filemtime($base_dir . 'assets/js/reviews.js') : '0.2.0';
			wp_enqueue_script('trustscript-reviews-js', $base_url . 'assets/js/reviews.js', array('jquery'), $reviews_js_ver, true);
		}

		if (strpos($hook, 'trustscript-review-requests') !== false || ( strpos($hook, 'trustscript-reviews') !== false && strpos($hook, 'trustscript-reviews-list') === false ) ) {
			$review_requests_js_ver = file_exists($base_dir . 'assets/js/review-requests.js') ? filemtime($base_dir . 'assets/js/review-requests.js') : '0.2.0';
			wp_enqueue_script('trustscript-review-requests-js', $base_url . 'assets/js/review-requests.js', array('jquery'), $review_requests_js_ver, true);
		}

		// Localize the script with settings and translation strings.
		wp_localize_script('trustscript-admin-js', 'TrustscriptAdmin', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('trustscript_admin'),

			'save_review_nonce' => wp_create_nonce('trustscript_save_review'),
			'site_url' => get_site_url(),
			'i18n' => array(
				'noActivity' => __('No recent activity', 'trustscript'),
				'configureSettings' => __('Start by configuring review settings', 'trustscript'),
				'reviewsGenerated' => __('Reviews Generated', 'trustscript'),
				'loadFailed' => __('Failed to load analytics data. Please check your API connection in Settings.', 'trustscript'),
				'refreshing' => __('Refreshing...', 'trustscript'),
				'refreshButton' => __('Refresh Analytics', 'trustscript'),
				'saving' => __('Saving...', 'trustscript'),
				'saveButton' => __('Save Review Settings', 'trustscript'),
				'saveSuccess' => __('Settings saved successfully!', 'trustscript'),
				'saveFailed' => __('Failed to save', 'trustscript'),
				'networkError' => __('Network error', 'trustscript'),
				'syncing' => __('Syncing...', 'trustscript'),
				'syncButton' => __('Sync Completed Orders', 'trustscript'),
				'syncConfirm' => __('This will create review requests for all completed orders in the selected time range. Continue?', 'trustscript'),
				'syncFailed' => __('Failed to sync', 'trustscript'),
				'delayDelivered' => __('Short delay recommended (1-2 days) - customers already have the product.', 'trustscript'),
				'delayCompleted' => __('Longer delay recommended to ensure product delivery before review request. International orders use this same delay by default. To use a different delay for international orders, enable "Handle international orders differently" below.', 'trustscript'),
				'confirmClear' => __('Are you sure? This will remove the item from the queue.', 'trustscript'),
				'confirmClearQueue' => __('Remove this order from the queue? This will not cancel the order, but the review request will NOT be retried.', 'trustscript'),
				'apiKeyRequired' => __('Please paste your TrustScript API key (TSK-XXXX-XXXX-XXXX).', 'trustscript'),
				'apiKeyRequiredTitle' => __('API Key Required', 'trustscript'),
				'invalidFormat' => __('Invalid format. Your key should look like: TSK-XXXX-XXXX-XXXX. Copy it from your TrustScript dashboard.', 'trustscript'),
				'invalidFormatTitle' => __('Invalid API Key Format', 'trustscript'),
				'deleting' => __('Deleting...', 'trustscript'),
				'failedToDelete' => __('Failed to delete API key', 'trustscript'),
				'unknownError' => __('Unknown error', 'trustscript'),
				'queuedForProcessing' => __('Queued for processing...', 'trustscript'),
				'processingBackground' => __('Processing your orders in the background — please check back in a few minutes.', 'trustscript'),
				'allCategories' => __('All categories', 'trustscript'),
				'oneCategory' => __('1 category', 'trustscript'),
				/* translators: %d: number of categories */
				'nCategories' => __('%d categories', 'trustscript'),
				'cancel' => __('Cancel', 'trustscript'),
				'retrying' => __('Retrying...', 'trustscript'),
				'clearing' => __('Clearing...', 'trustscript'),
				'retry' => __('Retry', 'trustscript'),
				'clear' => __('Clear', 'trustscript'),
				'processQueueNow' => __('Process Queue Now', 'trustscript'),
				'savePreference' => __('Save Preference', 'trustscript'),
				'retryFailed' => __('Failed to retry', 'trustscript'),
				'clearFailed' => __('Failed to clear', 'trustscript'),
				'processQueueFailed' => __('Failed to process queue', 'trustscript'),
				'savePreferenceFailed' => __('Failed to save preference', 'trustscript'),
				'pasteApiKeyPlaceholder' => __('Paste new API key to replace current key…', 'trustscript'),
				'saveChanges' => __('Save Changes', 'trustscript'),
				'syncBreakdown' => __('Breakdown:', 'trustscript'),
				'syncReviewsPublished' => __('review(s) published', 'trustscript'),
				'syncOrdersSent' => __('new order(s) sent to TrustScript', 'trustscript'),
				'syncOrdersSkipped' => __('order(s) already published (skipped)', 'trustscript'),
			),
		));
	}

	/**
	 * Handle AJAX request to dismiss quota exceeded or API key invalid notice
	 */
	public function handle_dismiss_notice()
	{
		check_ajax_referer('trustscript_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Permission denied', 'trustscript')));
		}

		$notice = isset($_POST['notice']) ? sanitize_key($_POST['notice']) : '';

		$allowed = array(
			'quota_exceeded' => 'trustscript_quota_exceeded_notice',
			'api_key_invalid' => 'trustscript_api_key_invalid_notice',
		);

		if (!isset($allowed[$notice])) {
			wp_send_json_error(array('message' => esc_html__('Unknown notice', 'trustscript')));
		}

		delete_transient($allowed[$notice]);
		wp_send_json_success();
	}

	/**
	 * Handle AJAX request to save verification modal settings
	 */
	public function handle_save_verification_modal_settings()
	{
		check_ajax_referer('trustscript_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Permission denied', 'trustscript')));
		}

		$is_enabled = isset($_POST['enable_verification_modal']) 
			&& 'true' === sanitize_text_field(wp_unslash($_POST['enable_verification_modal']));

		update_option('trustscript_enable_verification_modal', $is_enabled);

		wp_send_json_success(array(
			'message' => esc_html__('Verification modal settings saved successfully!', 'trustscript'),
			'enabled' => $is_enabled,
		));
	}

	/**
	 * Handle AJAX request to save trust strip settings
	 */
	public function handle_save_trust_strip_settings() {
		check_ajax_referer( 'trustscript_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied', 'trustscript' ) ) );
		}

		$is_enabled = isset( $_POST['enable_trust_strip'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['enable_trust_strip'] ) );

		$updated = update_option( 'trustscript_enable_trust_strip', $is_enabled );

		if ( $updated ) {
			wp_send_json_success( array(
				'message' => esc_html__( 'Trust strip settings saved successfully!', 'trustscript' ),
				'enabled' => $is_enabled,
			) );
		} else {
			wp_send_json_error( array(
				'message' => esc_html__( 'Failed to save setting – option may not have changed or database error occurred.', 'trustscript' ),
			) );
		}
	}

	/**
	 * Save uninstall preference via AJAX
	 */
	public function handle_save_uninstall_preference()
	{
		check_ajax_referer('trustscript_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Permission denied', 'trustscript')));
		}

		$delete_data = isset($_POST['delete_data']) ? $this->sanitize_checkbox(sanitize_text_field(wp_unslash($_POST['delete_data']))) : false;
		update_option('trustscript_delete_data_on_uninstall', $delete_data);

		$status = $delete_data ? __('enabled', 'trustscript') : __('disabled', 'trustscript');
		wp_send_json_success(array(
			/* translators: %s is the status of data deletion (enabled or disabled) */
			'message' => sprintf(__('Data deletion on uninstall %s', 'trustscript'), $status),
			'delete_data' => $delete_data,
		));
	}

	public function handle_delete_api_key()
	{
		check_ajax_referer('trustscript_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Unauthorized', 'trustscript')), 401);
		}

		delete_option('trustscript_api_key');
		delete_option('trustscript_webhook_secret');
		delete_transient('trustscript_user_plan');

		wp_send_json_success(array('message' => esc_html__('API key deleted successfully', 'trustscript')));
	}

	/**
	 * Handle AJAX request to save review settings.
	 *
	 * @since 1.0.0
	 */
	public function handle_save_moderation_settings()
	{
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

		if (!$nonce || !wp_verify_nonce($nonce, 'trustscript_save_review') && !wp_verify_nonce($nonce, 'trustscript_admin')) {
			wp_send_json_error(array(
				'message' => esc_html__('Security check failed', 'trustscript'),
			));
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array(
				'message' => esc_html__('Unauthorized', 'trustscript'),
			));
		}

		if (isset($_POST['trustscript_review_blocked_words'])) {
			$raw_input = sanitize_textarea_field(wp_unslash($_POST['trustscript_review_blocked_words']));
			$clean = array();
			
			if (!empty($raw_input)) {
				$lines = explode("\n", $raw_input);
				foreach ($lines as $line) {
					$line = sanitize_text_field($line);
					if ('' !== $line) {
						$clean[] = $line;
					}
				}
			}
			
			update_option('trustscript_review_blocked_words', implode("\n", $clean));
		}

		wp_send_json_success(array(
			'message' => esc_html__('Keyword blocklist saved successfully', 'trustscript'),
		));
	}

	/**
	 * Handle AJAX request to save review settings.
	 *
	 * @since 1.0.0
	 */
	public function handle_save_review_settings()
	{
		$nonce = isset($_POST['nonce'])
			? sanitize_text_field(wp_unslash($_POST['nonce']))
			: '';

		if (!$nonce || !wp_verify_nonce($nonce, 'trustscript_save_review')) {
			wp_send_json_error(array(
				'message' => esc_html__('Security check failed', 'trustscript'),
			));
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array(
				'message' => esc_html__('Unauthorized', 'trustscript'),
			));
		}

		// Boolean values 
		$enabled = isset( $_POST['enabled'] )
			&& in_array(
				sanitize_text_field( wp_unslash( $_POST['enabled'] ) ),
				array( '1', 'true' ),
				true
			);

		$simple_review_enabled = !empty($_POST['simple_review_enabled'])
			&& in_array(
				sanitize_text_field(wp_unslash($_POST['simple_review_enabled'])),
				array('1', 'true'),
				true
			);

		$api_review_collection_enabled = !empty($_POST['api_review_collection_enabled'])
			&& in_array(
				sanitize_text_field(wp_unslash($_POST['api_review_collection_enabled'])),
				array('1', 'true'),
				true
			);

		$auto_publish = isset( $_POST['auto_publish'] )
			&& in_array(
				sanitize_text_field( wp_unslash( $_POST['auto_publish'] ) ),
				array( '1', 'true' ),
				true
			);

		$enable_voting = isset( $_POST['enable_voting'] )
			&& in_array(
				sanitize_text_field( wp_unslash( $_POST['enable_voting'] ) ),
				array( '1', 'true' ),
				true
			);

		$auto_sync_enabled = isset( $_POST['auto_sync_enabled'] )
			&& in_array(
				sanitize_text_field( wp_unslash( $_POST['auto_sync_enabled'] ) ),
				array( '1', 'true' ),
				true
			);

		$enable_international_handling = isset( $_POST['enable_international_handling'] )
			&& in_array(
				sanitize_text_field( wp_unslash( $_POST['enable_international_handling'] ) ),
				array( '1', 'true' ),
				true
			);

		$delay_hours = isset($_POST['delay_hours'])
			? absint(wp_unslash($_POST['delay_hours']))
			: 1;

		$auto_sync_lookback = isset($_POST['auto_sync_lookback'])
			? absint(wp_unslash($_POST['auto_sync_lookback']))
			: 2;

		$international_delay_hours = isset($_POST['international_delay_hours'])
			? absint(wp_unslash($_POST['international_delay_hours']))
			: 336;

		$trigger_status = isset($_POST['trigger_status'])
			? sanitize_text_field(wp_unslash($_POST['trigger_status']))
			: 'delivered';

		$auto_sync_time = isset($_POST['auto_sync_time'])
			? sanitize_text_field(wp_unslash($_POST['auto_sync_time']))
			: '02:00';

		if (!preg_match('/^\d{2}:\d{2}$/', $auto_sync_time)) {
			$auto_sync_time = '02:00';
		}

		$categories = array();
		if (isset($_POST['categories']) && is_array($_POST['categories'])) {
			$all_valid_categories = get_terms(array(
				'taxonomy' => 'product_cat',
				'hide_empty' => false,
				'fields' => 'id=>parent',
			));

			if (!is_wp_error($all_valid_categories) && !empty($all_valid_categories)) {
				$requested_categories = array_map('absint', (array) wp_unslash($_POST['categories']));
				$requested_categories = array_unique($requested_categories);
				
				$filtered_categories = array();
				foreach ($requested_categories as $cat_id) {
					$should_include = true;
					
					foreach ($requested_categories as $other_cat_id) {
						if ($other_cat_id !== $cat_id && $this->is_category_ancestor($other_cat_id, $cat_id, $all_valid_categories)) {
							$should_include = false;
							break;
						}
					}
					
					if ($should_include) {
						$filtered_categories[] = $cat_id;
					}
				}
				
				$categories = array_values($filtered_categories);
			}
		}

		$memberpress_memberships = array();
		if (isset($_POST['trustscript_memberpress_memberships']) && is_array($_POST['trustscript_memberpress_memberships'])) {
			$memberpress_memberships = array_map('absint', wp_unslash($_POST['trustscript_memberpress_memberships']));
		}

		$memberpress_delay_days = 0;
		if (isset($_POST['trustscript_memberpress_delay_days'])) {
			$memberpress_delay_days = absint(wp_unslash($_POST['trustscript_memberpress_delay_days']));
			if ($memberpress_delay_days > 90) {
				$memberpress_delay_days = 0;
			}
		}

		$woocommerce_min_value = 0;
		if (isset($_POST['trustscript_woocommerce_min_value'])) {
			$woocommerce_min_value = floatval(wp_unslash($_POST['trustscript_woocommerce_min_value']));
			$woocommerce_min_value = max(0, $woocommerce_min_value);
		}

		$woocommerce_exclude_free = '0';
		if (isset($_POST['trustscript_woocommerce_exclude_free'])) {
			$woocommerce_exclude_free = '1' === sanitize_text_field(wp_unslash($_POST['trustscript_woocommerce_exclude_free']))
				? '1'
				: '0';
		}

		// Keywords
		$keywords = array();
		if (isset($_POST['trustscript_review_keywords']) && is_array($_POST['trustscript_review_keywords'])) {
			$keywords = array_filter(
				array_map('sanitize_text_field', wp_unslash($_POST['trustscript_review_keywords']))
			);
		}

		// Validation and sanitization
		if ($delay_hours > 2160) {
			$delay_hours = 0;
		}
		if ($international_delay_hours > 2160) {
			$international_delay_hours = 336;
		}
		if ($auto_sync_lookback < 1 || $auto_sync_lookback > 2) {
			$auto_sync_lookback = 2;
		}

		if (!in_array($trigger_status, array('delivered', 'completed'), true)) {
			$trigger_status = 'delivered';
		}

		if ( isset( $_POST['trustscript_review_page_id'] ) ) {
			$review_page_id = absint( wp_unslash( $_POST['trustscript_review_page_id'] ) );
			update_option( 'trustscript_review_page_id', $review_page_id );
		}

		if ( isset( $_POST['trustscript_simple_email_subject'] ) ) {
			$simple_email_subject = sanitize_text_field( wp_unslash( $_POST['trustscript_simple_email_subject'] ) );
			update_option( 'trustscript_simple_email_subject', $simple_email_subject );
		}

		if ( isset( $_POST['trustscript_simple_email_body'] ) ) {
			$simple_email_body = wp_kses_post( wp_unslash( $_POST['trustscript_simple_email_body'] ) );
			update_option( 'trustscript_simple_email_body', $simple_email_body );
		}

		// Save options
		update_option('trustscript_simple_review_enabled', $simple_review_enabled ? 1 : 0);
		update_option('trustscript_api_review_collection_enabled', $api_review_collection_enabled ? 1 : 0);
		update_option('trustscript_reviews_enabled', $enabled);
		update_option('trustscript_review_categories', $categories);
		update_option('trustscript_memberpress_memberships', $memberpress_memberships);
		update_option('trustscript_memberpress_delay_days', $memberpress_delay_days);
		update_option('trustscript_woocommerce_min_value', $woocommerce_min_value);
		update_option('trustscript_woocommerce_exclude_free', $woocommerce_exclude_free);
		update_option('trustscript_auto_publish', $auto_publish);
		update_option('trustscript_enable_voting', $enable_voting);
		update_option('trustscript_review_delay_hours', $delay_hours);
		update_option('trustscript_review_trigger_status', $trigger_status);
		update_option('trustscript_auto_sync_enabled', $auto_sync_enabled);
		update_option('trustscript_auto_sync_time', $auto_sync_time);
		update_option('trustscript_auto_sync_lookback', $auto_sync_lookback);
		update_option('trustscript_enable_international_handling', $enable_international_handling);
		update_option('trustscript_international_delay_hours', $international_delay_hours);
		update_option('trustscript_review_keywords', $keywords);

		if (class_exists('TrustScript_Auto_Sync')) {
			if ($auto_sync_enabled) {
				TrustScript_Auto_Sync::schedule_cron();
			} else {
				TrustScript_Auto_Sync::unschedule_cron();
			}
		}

		$response = array(
			'message' => esc_html__('Review settings saved successfully', 'trustscript'),
			'settings' => array(
				'enabled' => $enabled,
				'categories' => $categories,
				'auto_publish' => $auto_publish,
				'enable_voting' => $enable_voting,
				'delay_hours' => $delay_hours,
				'trigger_status' => $trigger_status,
				'auto_sync_enabled' => $auto_sync_enabled,
				'auto_sync_time' => $auto_sync_time,
				'auto_sync_lookback' => $auto_sync_lookback,
				'enable_international_handling' => $enable_international_handling,
				'international_delay_hours' => $international_delay_hours,
				'keywords' => $keywords,
			),
		);

		wp_send_json_success($response);
	}

	/**
	 * Syncing existing completed orders and approved reviews
	 */
	public function handle_sync_orders()
	{
		check_ajax_referer('trustscript_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Unauthorized', 'trustscript')), 401);
			return;
		}

		$service_manager = TrustScript_Service_Manager::get_instance();
		$active_providers = $service_manager->get_active_providers();

		if (empty($active_providers)) {
			wp_send_json_error(array('message' => esc_html__('No active service providers detected. Please enable at least one service (WooCommerce or MemberPress).', 'trustscript')), 400);
		}

		$days = isset($_POST['days']) ? sanitize_text_field(wp_unslash($_POST['days'])) : '2';

		if ($days !== 'all') {
			$days = max(1, min(2, intval($days)));
		} else {
			$days = 2;
		}

		$reviews_published = $this->fetch_and_publish_approved_reviews();

		if (!class_exists('TrustScript_Sync_Service')) {
			wp_send_json_error(array('message' => esc_html__('Sync service not available', 'trustscript')), 500);
		}

		$sync_service = new TrustScript_Sync_Service();
		$orders_synced = 0;
		$orders_skipped = 0;
		$orders_total = 0;

		foreach ($active_providers as $service_id => $provider) {
			try {
				$is_enabled = get_option("trustscript_enable_service_{$service_id}", '1') === '1';

				if (!$is_enabled) {
					continue;
				}

				$trigger_status = get_option("trustscript_trigger_status_{$service_id}", '');
				if (empty($trigger_status)) {
					continue;
				}

				$result = $sync_service->sync_service_orders($provider, $service_id, $days);
				$orders_synced += $result['processed'] ?? 0;
				$orders_skipped += $result['skipped'] ?? 0;
				$orders_total += $result['total'] ?? 0;
			} catch (Exception $e) {
				// Fall silently.. 
			}
		}

		$total_processed = $reviews_published + $orders_synced;

		if ($total_processed === 0) {
			$status_message = __('No new data to sync', 'trustscript');
			if ($orders_skipped > 0) {
				$status_message .= sprintf(
					/* translators: %d: number of orders that were already published */
					__(' (but %d order(s) were already published and skipped)', 'trustscript'),
					$orders_skipped
				);
			}
			wp_send_json_success(array(
				'message' => $status_message,
				'processed' => 0,
				'reviews_published' => 0,
				'orders_synced' => 0,
				'orders_skipped' => $orders_skipped,
				'orders_total' => $orders_total,
			));
			return;
		}

		$message = sprintf(
			/* translators: %1$d: reviews published, %2$d: orders synced */
			__('Sync complete! Reviews published: %1$d, New orders sent: %2$d', 'trustscript'),
			$reviews_published,
			$orders_synced
		);

		if ($orders_skipped > 0) {
			$message .= sprintf(
				/* translators: %d: orders that were already published and skipped */
				__(', %d order(s) already published (skipped re-sync)', 'trustscript'),
				$orders_skipped
			);
		}

		wp_send_json_success(array(
			'message' => $message,
			'processed' => $total_processed,
			'reviews_published' => $reviews_published,
			'orders_synced' => $orders_synced,
			'orders_skipped' => $orders_skipped,
			'orders_total' => $orders_total,
		));
	}

	/**
	 * Fetch approved reviews from TrustScript API and publish them as WordPress comments
	 */
	private function fetch_and_publish_approved_reviews()
	{
		$result = trustscript_api_request('GET', 'api/wordpress-orders/sync');

		if (is_wp_error($result)) {
			return 0;
		}

		$data = $result['data'];

		if (!isset($data['orders']) || !is_array($data['orders'])) {
			return 0;
		}

		$approved_reviews = array_filter($data['orders'], function ($order) {
			return isset($order['status']) && $order['status'] === 'approved';
		});

		if (empty($approved_reviews)) {
			return 0;
		}

		$published_count = 0;

		foreach ($approved_reviews as $review) {
			if ($this->publish_single_review($review)) {
				$published_count++;
			}
		}

		return $published_count;
	}

	private function publish_single_review($review)
	{
		if (empty($review['uniqueToken']) || !is_string($review['uniqueToken'])) {
			return false;
		}

		if (isset($review['projectStatus']['status']) && $review['projectStatus']['status'] !== 'active') {
			return false;
		}

		$review_text = sanitize_textarea_field($review['finalText'] ?? $review['reviewText'] ?? '');
		if (empty($review_text)) {
			return false;
		}

		$unique_token = sanitize_text_field($review['uniqueToken']);
		$rating = isset($review['rating']) ? intval($review['rating']) : 5;

		$comment_date = current_time('mysql');
		$comment_date_gmt = current_time('mysql', true);

		if (isset($review['approvedAt']) && !empty($review['approvedAt'])) {
			$approved_timestamp = strtotime($review['approvedAt']);
			if ($approved_timestamp) {
				$comment_date = wp_date('Y-m-d H:i:s', $approved_timestamp);
				$comment_date_gmt = wp_date('Y-m-d H:i:s', $approved_timestamp, new DateTimeZone('UTC'));
			}
		}

		$order = self::find_order_by_review_token($unique_token);

		if (!$order) {
			return false;
		}

		$order_id = $order->get_id();

		$service_id = $order->get_meta('_trustscript_service_type');
		if (empty($service_id)) {
			$service_id = 'woocommerce';
		}

		$is_published = TrustScript_Order_Registry::is_published($service_id, $order_id);
		if ($is_published) {
			return false;
		}

		$items = $order->get_items();

		if (empty($items)) {
			return false;
		}

		$stored_hash = $order->get_meta('_trustscript_verification_hash');
		$incoming_hash = !empty($review['verificationHash']) ? sanitize_text_field($review['verificationHash']) : '';

		if (!empty($stored_hash) && !hash_equals($stored_hash, $incoming_hash)) {
			return false;
		}

		$order->update_meta_data('_trustscript_review_token', $unique_token);
		$order->update_meta_data('_trustscript_review_published', 'yes');
		$order->update_meta_data('_trustscript_review_published_at', current_time('mysql'));
		$order->update_meta_data('_trustscript_publishing_mode', 'manual_sync');
		$order->save();

		$customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
		$customer_email = $order->get_billing_email();

		$published_count = 0;

		foreach ($items as $item) {
			$product_id = $item->get_product_id();

			if (!$product_id) {
				continue;
			}

			$existing_reviews = get_comments(array(
				'post_id' => $product_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- we need to query by meta key
				'meta_key' => '_trustscript_review_token',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- we need to query by meta value
				'meta_value' => $unique_token,
				'count' => true,
			));

			if ($existing_reviews > 0) {
				continue;
			}

			$review_data = array(
				'comment_post_ID' => $product_id,
				'comment_content' => $review_text,
				'comment_author' => $customer_name,
				'comment_author_email' => $customer_email,
				'comment_approved' => 1,
				'comment_type' => 'review',
				'user_id' => 0,
				'comment_date' => $comment_date,
				'comment_date_gmt' => $comment_date_gmt,
			);

			$comment_id = wp_insert_comment($review_data, true);

			if (is_wp_error($comment_id)) {
				continue;
			}

			update_comment_meta($comment_id, 'rating', $rating);
			update_comment_meta($comment_id, '_trustscript_review_token', $unique_token);
			update_comment_meta($comment_id, 'verified', 1);

			if (isset($review['verificationHash']) && !empty($review['verificationHash'])) {
				update_comment_meta($comment_id, '_trustscript_verification_hash', sanitize_text_field($review['verificationHash']));
			}

			$published_count++;
		}

		if ($published_count > 0) {
			TrustScript_Order_Registry::mark_published($service_id, $order_id, null, null, 'manual_sync');
			$this->notify_trustscript_published($unique_token);
			return true;
		}

		return false;
	}

	private function notify_trustscript_published($unique_token)
	{
		$result = trustscript_api_request('POST', 'api/wordpress-orders/admin-notification', array(
			'uniqueToken' => $unique_token,
			'publishingStatus' => 'published',
			'publishedAt' => current_time('mysql', true),
			'publishingMode' => 'manual_sync',
		));

		if (is_wp_error($result)) {
			return false;
		}

		return true;
	}


	/**
	 * Handle AJAX request to save service settings
	 */
	public function handle_save_service_settings()
	{
		check_ajax_referer('trustscript_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Permission denied', 'trustscript')));
		}

		$service_manager = TrustScript_Service_Manager::get_instance();
		$active_services = $service_manager->get_active_providers();

		if (empty($active_services)) {
			wp_send_json_error(array('message' => esc_html__('No services detected', 'trustscript')));
		}

		foreach ($active_services as $service_id => $provider) {
			$enabled_key = 'trustscript_enable_service_' . $service_id;
			$is_enabled = isset($_POST[$enabled_key]) && '1' === sanitize_text_field(wp_unslash($_POST[$enabled_key]));
			update_option($enabled_key, $is_enabled ? '1' : '0');

			$trigger_key = 'trustscript_trigger_status_' . $service_id;
			if (isset($_POST[$trigger_key])) {
				$trigger_value = sanitize_text_field(wp_unslash($_POST[$trigger_key]));
				$allowed_statuses = array_keys($provider->get_available_statuses());
				if (in_array($trigger_value, $allowed_statuses, true)) {
					update_option($trigger_key, $trigger_value);
				}
			}
		}

		wp_send_json_success(array(
			'message' => esc_html__('Service settings saved successfully!', 'trustscript'),
			'services_updated' => count($active_services),
		));
	}

	/**
	 * Get icon for services
	 */
	public static function get_service_icon($service_id)
	{
		$icons = array(
			'woocommerce' => '🛒',
			'memberpress' => '👥',
		);

		return isset($icons[$service_id]) ? $icons[$service_id] : '⚙️';
	}


	/**
	 * Check if a category is an ancestor of another category
	 * 
	 * @param int $potential_ancestor Category ID to check
	 * @param int $category_id Category ID to check against
	 * @param array $categories_hierarchy Map of category_id => parent_id
	 * @return bool True if potential_ancestor is an ancestor of category_id
	 */
	private function is_category_ancestor($potential_ancestor, $category_id, $categories_hierarchy)
	{
		if ($potential_ancestor === $category_id) {
			return false;
		}

		$current_id = $category_id;
		while ($current_id > 0 && isset($categories_hierarchy[$current_id])) {
			$parent_id = $categories_hierarchy[$current_id];
			if ($parent_id === 0) {
				return false;
			}
			if ($parent_id === $potential_ancestor) {
				return true;
			}
			$current_id = $parent_id;
		}
		return false;
	}

	/**
	 * Handle AJAX request to save optional data collection settings (product names, order dates, etc.)
	 */
	public function handle_save_optional_data_settings()
	{
		check_ajax_referer('trustscript_admin', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Permission denied', 'trustscript')));
		}

		$include_product_names = isset($_POST['trustscript_include_product_names'])
			&& '1' === sanitize_text_field(wp_unslash($_POST['trustscript_include_product_names']));

		$include_order_dates = isset($_POST['trustscript_include_order_dates'])
			&& '1' === sanitize_text_field(wp_unslash($_POST['trustscript_include_order_dates']));

		update_option('trustscript_include_product_names', $include_product_names ? '1' : '0');
		update_option('trustscript_include_order_dates', $include_order_dates ? '1' : '0');

		wp_send_json_success(array(
			'message' => esc_html__('Privacy settings saved successfully!', 'trustscript'),
			'include_product_names' => $include_product_names,
			'include_order_dates' => $include_order_dates,
		));
	}
}