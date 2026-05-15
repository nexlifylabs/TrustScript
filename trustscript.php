<?php
/**
 * Plugin Name: TrustScript
 * Description: Automated review collection for WooCommerce - verified, visual, AI-assisted, and 100% privacy compliant. No PII. No manual work.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Tested up to: 6.9
 * Requires PHP: 8.0
 * Plugin URI: https://trustscript.io
 * Author: TrustScript
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trustscript
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

define('TRUSTSCRIPT_PLUGIN_FILE', __FILE__);
define('TRUSTSCRIPT_VERSION', '1.0.0');
define('TRUSTSCRIPT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TRUSTSCRIPT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TRUSTSCRIPT_API_BASE_URL', 'https://trustscript.io');
define('TRUSTSCRIPT_DASHBOARD_URL', TRUSTSCRIPT_API_BASE_URL . '/dashboard');
define('TRUSTSCRIPT_PRICING_URL', TRUSTSCRIPT_API_BASE_URL . '/pricing');
define('TRUSTSCRIPT_DOCS_URL', TRUSTSCRIPT_API_BASE_URL . '/docs');
define('TRUSTSCRIPT_SUPPORT_URL', TRUSTSCRIPT_API_BASE_URL . '/support');
/**
 * Default endpoint for TrustScript API key verification.
 */
define('TRUSTSCRIPT_VERIFY_ENDPOINT', TRUSTSCRIPT_API_BASE_URL . '/api/verify-api-key');

require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/trustscript-helpers.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/pricing-config.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/placeholder-mapper.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/trustscript-service-manager.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/trustscript-sync-service.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/consent/privacy-settings.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/consent/consent-manager.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/admin/settings.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/admin/review-setting.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/admin/review-request.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/admin/pending-queue.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/trustscript-admin.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/admin/review-guard.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/webhook.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/settings-sync.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/consent/consent.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/consent/consent-block-checkout.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/media-upload.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/auto-sync.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/consent/consent-capture.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/consent/consent-confirmation.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/consent/review-queue.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/immutability.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/order-status.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/compatibility.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/review-voting.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/order-registry.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/trustscript-queue.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/trustscript-review-requests.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/opt-out.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/review-query.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/review-renderer-eng.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/woocommerce-reviews.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/simple-review/simple-template-loader.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/simple-review/simple-email-sender.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/simple-review/rest-simple-review.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/simple-review/class-trustscript-simple-review.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/ts-simple-email-review.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/ts-shop-display.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/date-formatter.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/frontend-reviews-base.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/memberpress-reviews.php';
require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/memberpress-reviews-metabox.php';

if (file_exists(TRUSTSCRIPT_PLUGIN_PATH . 'includes/integration/elementor/class-trustscript-elementor.php')) {
	require_once TRUSTSCRIPT_PLUGIN_PATH . 'includes/integration/elementor/class-trustscript-elementor.php';
}

function trustscript_plugin_init() {
	$service_manager = TrustScript_Service_Manager::get_instance();
	$woocommerce_active = class_exists('WooCommerce');

	if (!$service_manager->has_active_services()) {
		add_action('admin_notices', 'trustscript_no_services_notice');
	}

	if ($woocommerce_active) {
		new TrustScript_Compatibility();
		new TrustScript_Order_Status();
		new TrustScript_WooCommerce_Reviews();
	}

	if (is_admin()) {
		TrustScript_Plugin_Admin::get_instance();
		new TrustScript_Review_Request_Page();
		new TrustScript_Pending_Queue_Page();
	}

	new TrustScript_Webhook();
	new TrustScript_Media_Upload();
	new TrustScript_Settings_Sync();
	new TrustScript_Immutability();
	new TrustScript_Review_Voting();
	new TrustScript_Auto_Sync();

	TrustScript_Review_Renderer::boot();
	TrustScript_Simple_Review::boot();
	TrustScript_Rest_Simple_Review::boot();
	TrustScript_Simple_Email_Review::boot();

	TrustScript_Queue::init_cron_hook();

	if ( is_admin() ) {
		TrustScript_Queue::register_cron_job();
		TrustScript_Queue::register_cleanup_cron();
	}

	if ( ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		TrustScript_Review_Requests::create_table();
	}
	TrustScript_Review_Requests::init();

	if (!is_admin() && class_exists('WooCommerce')) {
		new TrustScript_Checkout_Consent();
		new TrustScript_Shop_Display();
	}

}
add_action('plugins_loaded', 'trustscript_plugin_init');


function trustscript_plugin_activate() {
	trustscript_initialize_encryption_key();

	TrustScript_Order_Registry::create_table();
	TrustScript_Queue::create_table();
	TrustScript_Review_Requests::create_table();
	TrustScript_Opt_Out::create_table();
	TrustScript_Consent_Manager::create_tables();
	TrustScript_Review_Voting::create_votes_table();

	// Register custom intervals FIRST so they exist when wp_schedule_event() runs.
	add_filter( 'cron_schedules', array( 'TrustScript_Queue', 'add_custom_cron_intervals' ) );
	TrustScript_Queue::register_cron_job();
	TrustScript_Queue::register_cleanup_cron();
	TrustScript_Auto_Sync::schedule_cron();

	if (!get_option('trustscript_api_key')) {
		set_transient('trustscript_activation_redirect', true, 30);
	}
}

register_activation_hook(TRUSTSCRIPT_PLUGIN_FILE, 'trustscript_plugin_activate');

function trustscript_plugin_deactivate() {
	TrustScript_Queue::unregister_cron_job();
	TrustScript_Auto_Sync::unschedule_cron();
}

register_deactivation_hook(TRUSTSCRIPT_PLUGIN_FILE, 'trustscript_plugin_deactivate');

function trustscript_activation_redirect() {
	if (!get_transient('trustscript_activation_redirect')) {
		return;
	}

	delete_transient('trustscript_activation_redirect');

	if (isset($_GET['activate-multi'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe redirect on plugin activation.
		return;
	}

	wp_safe_redirect(admin_url('admin.php?page=trustscript-settings&first-time=1'));
	exit;
}

add_action('admin_init', 'trustscript_activation_redirect');

function trustscript_no_services_notice() {
	?>
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e('TrustScript', 'trustscript'); ?>:</strong>
			<?php esc_html_e('No supported services detected. TrustScript works with WooCommerce, MemberPress, and more.', 'trustscript'); ?>
		</p>
		<p>
			<?php esc_html_e('Install and activate a supported plugin to start collecting reviews.', 'trustscript'); ?>
			<a href="<?php echo esc_url( TRUSTSCRIPT_DOCS_URL . '/supported-platforms' ); ?>"
				target="_blank"><?php esc_html_e('View Supported Platforms', 'trustscript'); ?></a>
		</p>
	</div>
	<?php
}

function trustscript_plugin_action_links($links) {
	$custom_links = array(
		'<a href="' . esc_url( TRUSTSCRIPT_PRICING_URL ) . '" target="_blank" rel="noopener noreferrer" style="color: #10b981; font-weight: bold;">'
		. esc_html__('Go Pro', 'trustscript') . '</a>',

		'<a href="' . esc_url(admin_url('admin.php?page=trustscript-settings')) . '">'
		. esc_html__('Settings', 'trustscript') . '</a>',

		'<a href="' . esc_url( TRUSTSCRIPT_DOCS_URL ) . '" target="_blank" rel="noopener noreferrer">'
		. esc_html__('Docs', 'trustscript') . '</a>',

		'<a href="' . esc_url( TRUSTSCRIPT_SUPPORT_URL ) . '" target="_blank" rel="noopener noreferrer">'
		. esc_html__('Support', 'trustscript') . '</a>',
	);

	return array_merge($custom_links, $links);
}

add_filter('plugin_action_links_' . plugin_basename(TRUSTSCRIPT_PLUGIN_FILE), 'trustscript_plugin_action_links');