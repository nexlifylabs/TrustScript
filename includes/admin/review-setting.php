<?php
/**
 * TrustScript Reviews Page Handler
 *
 * @package TrustScript
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrustScript_Reviews_Page {
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$app_url            = trustscript_get_app_url();
		$enabled            = get_option( 'trustscript_reviews_enabled', false );
		$categories         = get_option( 'trustscript_review_categories', array() );
		$auto_publish       = get_option( 'trustscript_auto_publish', false );
		$collect_rating     = get_option( 'trustscript_collect_rating', true );
		$collect_photos     = get_option( 'trustscript_collect_photos', true );
		$collect_videos     = get_option( 'trustscript_collect_videos', false );
		$delay_hours        = get_option( 'trustscript_review_delay_hours', 1 );
		$intl_delay_hours   = get_option( 'trustscript_international_delay_hours', 336 );
		$preset_delays      = array( 0, 1, 12, 24, 48, 72, 168, 336, 504, 672 );
		$delay_is_custom    = ! in_array( (int) $delay_hours, $preset_delays, true );
		$custom_delay_value = 0;
		$custom_delay_unit  = 'hours';

		if ( $delay_is_custom ) {
			if ( (int) $delay_hours % 24 === 0 ) {
				$custom_delay_value = (int) $delay_hours / 24;
				$custom_delay_unit  = 'days';
			} else {
				$custom_delay_value = (int) $delay_hours;
				$custom_delay_unit  = 'hours';
			}
		}

		$intl_preset_delays      = array( 336, 504, 672, 1344 );
		$intl_delay_is_custom    = ! in_array( (int) $intl_delay_hours, $intl_preset_delays, true );
		$custom_intl_delay_value = 0;
		$custom_intl_delay_unit  = 'hours';

		if ( $intl_delay_is_custom ) {
			if ( (int) $intl_delay_hours % 24 === 0 ) {
				$custom_intl_delay_value = (int) $intl_delay_hours / 24;
				$custom_intl_delay_unit  = 'days';
			} else {
				$custom_intl_delay_value = (int) $intl_delay_hours;
				$custom_intl_delay_unit  = 'hours';
			}
		}

		$trigger_status     = get_option( 'trustscript_review_trigger_status', 'delivered' );
		$auto_sync_enabled  = get_option( 'trustscript_auto_sync_enabled', false );
		$auto_sync_time     = get_option( 'trustscript_auto_sync_time', '02:00' );
		$auto_sync_lookback = get_option( 'trustscript_auto_sync_lookback', 2 );
		$review_keywords    = get_option( 'trustscript_review_keywords', array() );
		$available_keywords = TrustScript_Review_Renderer::get_available_keywords();
		$next_run           = TrustScript_Auto_Sync::get_next_run();
		$last_run           = TrustScript_Auto_Sync::get_last_run_time();
		$last_stats         = TrustScript_Auto_Sync::get_last_run_stats();

		$order_status              = new TrustScript_Order_Status();
		$existing_delivered_status = $order_status->get_existing_delivered_status();
		$delivered_status_name     = TrustScript_Order_Status::get_delivered_status_name();

		$wc_categories = array();
		if ( function_exists( 'wc_get_product_terms' ) || taxonomy_exists( 'product_cat' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				$wc_categories = $terms;
			}
		}

		$admin = TrustScript_Plugin_Admin::get_instance();

		?>
		<div class="wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Review Settings', 'trustscript' ); ?></h1>
			
			<div class="trustscript-card trustscript-mb-24">
				<h2><?php esc_html_e( 'Review Settings', 'trustscript' ); ?></h2>
				<p><?php esc_html_e( 'Configure how TrustScript generates and collects AI-powered reviews from your customers across all services.', 'trustscript' ); ?></p>
			</div>
			<?php
			self::render_service_detection_ui();

			$simple_review_enabled = ! empty( get_option( 'trustscript_simple_review_enabled' ) );
			$api_review_enabled    = ! empty( get_option( 'trustscript_api_review_collection_enabled' ) );
			?>

			<div class="trustscript-card trustscript-mb-24">
				<h2><?php esc_html_e( 'Review Collection Method', 'trustscript' ); ?></h2>
				<p><?php esc_html_e( 'Choose how customers can leave reviews. Both methods can be enabled simultaneously.', 'trustscript' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="trustscript_simple_review_enabled"><?php esc_html_e( 'Simple On-Site Reviews', 'trustscript' ); ?></label>
						</th>
						<td>
							<label class="trustscript-toggle">
								<input type="checkbox" id="trustscript_simple_review_enabled" name="trustscript_simple_review_enabled" value="1" <?php checked( $simple_review_enabled ); ?>>
								<span class="trustscript-toggle-slider"></span>
							</label>
							<p class="description"><?php esc_html_e( 'Show a "Write a Review" button on product pages. Customers can submit star ratings, reviews, and photos directly on your site. Free — no API key required.', 'trustscript' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="trustscript_api_review_collection_enabled"><?php esc_html_e( 'API Review Collection', 'trustscript' ); ?></label>
						</th>
						<td>
							<label class="trustscript-toggle">
								<input type="checkbox" id="trustscript_api_review_collection_enabled" name="trustscript_api_review_collection_enabled" value="1" <?php checked( $api_review_enabled ); ?>>
								<span class="trustscript-toggle-slider"></span>
							</label>
							<p class="description"><?php esc_html_e( 'Collect reviews via the TrustScript API with AI-powered email automation, verification hashes, and sentiment analysis. Requires an API key.', 'trustscript' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="trustscript-card trustscript-faq-card">
				<div class="trustscript-faq-header">
					<h2><?php esc_html_e( 'Review Email Timing Guide', 'trustscript' ); ?></h2>
					<p><?php esc_html_e( 'Common questions about when and how to send review requests', 'trustscript' ); ?></p>
				</div>

				<div class="trustscript-faq-item">
					<button type="button" class="trustscript-faq-question" aria-expanded="false">
						<span class="trustscript-faq-icon">+</span>
						<span class="trustscript-faq-text"><?php esc_html_e( 'Why shouldn\'t I use "Completed" status?', 'trustscript' ); ?></span>
					</button>
					<div class="trustscript-faq-answer">
						<p>
							<?php esc_html_e( 'Most stores mark orders as "Completed" when shipped, NOT when delivered. This means customers receive review requests while the package is still in transit - before they even have the product!', 'trustscript' ); ?>
						</p>
						<div class="trustscript-faq-warning">
							<span class="dashicons dashicons-warning"></span>
							<strong><?php esc_html_e( 'Result:', 'trustscript' ); ?></strong>
							<?php esc_html_e( 'Low response rates, frustrated customers, and poor quality reviews.', 'trustscript' ); ?>
						</div>
					</div>
				</div>

				<div class="trustscript-faq-item">
					<button type="button" class="trustscript-faq-question" aria-expanded="false">
						<span class="trustscript-faq-icon">+</span>
						<span class="trustscript-faq-text"><?php esc_html_e( 'What is the "Delivered" status?', 'trustscript' ); ?></span>
					</button>
					<div class="trustscript-faq-answer">
						<p>
							<?php esc_html_e( 'TrustScript creates a new "Delivered" order status. Mark orders as "Delivered" only when customers actually receive their products.', 'trustscript' ); ?>
						</p>
						<ul class="trustscript-faq-list">
							<li><?php esc_html_e( '✅ Customers have the product in hand', 'trustscript' ); ?></li>
							<li><?php esc_html_e( '✅ Review emails sent at the perfect time', 'trustscript' ); ?></li>
							<li><?php esc_html_e( '✅ Higher response rates and quality reviews', 'trustscript' ); ?></li>
							<li><?php esc_html_e( '✅ Professional customer experience', 'trustscript' ); ?></li>
						</ul>
					</div>
				</div>

				<div class="trustscript-faq-item">
					<button type="button" class="trustscript-faq-question" aria-expanded="false">
						<span class="trustscript-faq-icon">+</span>
						<span class="trustscript-faq-text"><?php esc_html_e( 'How do I use the Delivered status?', 'trustscript' ); ?></span>
					</button>
					<div class="trustscript-faq-answer">
						<?php if ( $existing_delivered_status ) : ?>
							<div class="trustscript-faq-success">
								<strong><?php esc_html_e( 'Delivered Status Detected!', 'trustscript' ); ?></strong>
								<p>
									<?php
									printf(
										/* translators: %s: status name */
										esc_html__( 'TrustScript found an existing "Delivered" status. We\'ll use this automatically!', 'trustscript' ),
										'<code>' . esc_html( $existing_delivered_status ) . '</code>'
									);
									?>
								</p>
							</div>
						<?php endif; ?>
						
						<div class="trustscript-faq-method">
							<h4><?php esc_html_e( 'Manual Method:', 'trustscript' ); ?></h4>
							<ol>
								<li><?php esc_html_e( 'Go to WooCommerce → Orders', 'trustscript' ); ?></li>
								<li><?php esc_html_e( 'When a customer confirms delivery, change order status to "Delivered"', 'trustscript' ); ?></li>
								<li><?php esc_html_e( 'TrustScript will send review request after configured delay', 'trustscript' ); ?></li>
							</ol>
						</div>

						<div class="trustscript-faq-method">
							<h4><?php esc_html_e( 'Automatic Method (Recommended):', 'trustscript' ); ?></h4>
							<ul>
								<li><?php esc_html_e( 'Use a shipment tracking plugin like ShipStation, TrackShip, or WooCommerce Shipment Tracking', 'trustscript' ); ?></li>
								<li><?php esc_html_e( 'Configure it to auto-update orders to "Delivered" when tracking shows delivery', 'trustscript' ); ?></li>
								<li><?php esc_html_e( 'TrustScript will handle the rest automatically!', 'trustscript' ); ?></li>
							</ul>
						</div>
					</div>
				</div>

				<div class="trustscript-faq-item">
					<button type="button" class="trustscript-faq-question" aria-expanded="false">
						<span class="trustscript-faq-icon">+</span>
						<span class="trustscript-faq-text"><?php esc_html_e( 'Can I still use "Completed" status?', 'trustscript' ); ?></span>
					</button>
					<div class="trustscript-faq-answer">
						<div class="trustscript-faq-tip">
							<span class="dashicons dashicons-lightbulb"></span>
							<p>
								<strong><?php esc_html_e( 'Yes, but with caution.', 'trustscript' ); ?></strong>
								<?php esc_html_e( 'You can use "Completed" status, but set a longer delay to ensure customers receive products first. By default, international orders use this same domestic delay. To send international orders on a different schedule, enable the "Handle international orders differently" option above to set a separate delay.', 'trustscript' ); ?>
							</p>
						</div>
					</div>
				</div>
			</div>

			<div class="trustscript-grid" style="margin-top: 24px;">
				<div class="trustscript-card">
					<h2><?php esc_html_e( 'Review Collection', 'trustscript' ); ?></h2>
					<div class="trustscript-form-group">
						<label class="trustscript-toggle">
							<input type="checkbox" id="trustscript_reviews_enabled" <?php checked( $enabled ); ?>>
							<span class="trustscript-toggle-slider"></span>
						</label>
						<label for="trustscript_reviews_enabled" style="margin-left: 10px; cursor: pointer;">
							<?php esc_html_e( 'Enable automatic review requests', 'trustscript' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When enabled, TrustScript will automatically send review request emails to customers after order completion.', 'trustscript' ); ?></p>
					</div>

					<div class="trustscript-form-group">
						<label for="trustscript_review_delay_hours"><?php esc_html_e( 'Domestic Review Request Delay', 'trustscript' ); ?></label>
						<div style="display: flex; gap: 10px; align-items: flex-end;">
							<div style="flex: 1;">
								<select id="trustscript_review_delay_hours" class="trustscript-form-input">
									<option value="0" <?php selected( ! $delay_is_custom && $delay_hours == 0 ); ?>><?php esc_html_e( 'Immediately', 'trustscript' ); ?></option>
									<option value="1" <?php selected( ! $delay_is_custom && $delay_hours == 1 ); ?>><?php esc_html_e( '1 hour', 'trustscript' ); ?></option>
									<option value="12" <?php selected( ! $delay_is_custom && $delay_hours == 12 ); ?>><?php esc_html_e( '12 hours', 'trustscript' ); ?></option>
									<option value="24" <?php selected( ! $delay_is_custom && $delay_hours == 24 ); ?>><?php esc_html_e( '1 day', 'trustscript' ); ?></option>
									<option value="48" <?php selected( ! $delay_is_custom && $delay_hours == 48 ); ?>><?php esc_html_e( '2 days', 'trustscript' ); ?></option>
									<option value="72" <?php selected( ! $delay_is_custom && $delay_hours == 72 ); ?>><?php esc_html_e( '3 days', 'trustscript' ); ?></option>
									<option value="168" <?php selected( ! $delay_is_custom && $delay_hours == 168 ); ?>><?php esc_html_e( '1 week', 'trustscript' ); ?></option>
									<option value="336" <?php selected( ! $delay_is_custom && $delay_hours == 336 ); ?>><?php esc_html_e( '2 weeks', 'trustscript' ); ?></option>
									<option value="504" <?php selected( ! $delay_is_custom && $delay_hours == 504 ); ?>><?php esc_html_e( '3 weeks', 'trustscript' ); ?></option>
									<option value="672" <?php selected( ! $delay_is_custom && $delay_hours == 672 ); ?>><?php esc_html_e( '4 weeks (1 month)', 'trustscript' ); ?></option>
									<option value="custom" <?php selected( $delay_is_custom ); ?> style="color: #10b981; font-weight: bold;"><?php esc_html_e( 'Custom...', 'trustscript' ); ?></option>
								</select>
							</div>
							<div style="flex: 1; display: <?php echo $delay_is_custom ? 'flex' : 'none'; ?>;" id="trustscript-custom-delay-wrapper">
								<div style="display: flex; gap: 8px;">
									<input type="number" id="trustscript_custom_delay_value" class="trustscript-form-input" min="0" placeholder="<?php esc_attr_e( 'Value', 'trustscript' ); ?>" value="<?php echo esc_attr( $custom_delay_value ); ?>" style="max-width: 80px;">
									<select id="trustscript_custom_delay_unit" class="trustscript-form-input" style="min-width: 100px;">
										<option value="hours" <?php selected( $custom_delay_unit, 'hours' ); ?>><?php esc_html_e( 'Hours', 'trustscript' ); ?></option>
										<option value="days" <?php selected( $custom_delay_unit, 'days' ); ?>><?php esc_html_e( 'Days', 'trustscript' ); ?></option>
									</select>
								</div>
							</div>
						</div>
						<p class="description" id="delay-description"><?php esc_html_e( 'Time to wait after order status change before sending review request to customers.', 'trustscript' ); ?></p>
					</div>

					<div class="trustscript-form-group" style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 20px;">
						<label class="trustscript-toggle">
							<input type="checkbox" id="trustscript_enable_international_handling" <?php checked( get_option( 'trustscript_enable_international_handling', false ) ); ?>>
							<span class="trustscript-toggle-slider"></span>
						</label>
						<label for="trustscript_enable_international_handling" style="margin-left: 10px; cursor: pointer;">
							<?php esc_html_e( 'Handle international orders differently', 'trustscript' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When enabled, international orders will use a separate, longer review request delay. This gives international customers more time to receive and verify their orders.', 'trustscript' ); ?></p>
					</div>

					<div id="trustscript-international-delay-section" style="display: <?php echo get_option( 'trustscript_enable_international_handling', false ) ? 'block' : 'none'; ?>; padding: 16px; background: #f0f9ff; border: 1px solid #93c5fd; border-radius: 8px; margin-top: 12px;">
						<h3 style="margin-top: 0; color: #1e40af;"><?php esc_html_e( 'International Order Review Request Delay', 'trustscript' ); ?></h3>
						<div class="trustscript-form-group">
							<label for="trustscript_international_delay_hours"><?php esc_html_e( 'International Delay', 'trustscript' ); ?></label>
							<div style="display: flex; gap: 10px; align-items: flex-end;">
								<div style="flex: 1;">
									<select id="trustscript_international_delay_hours" class="trustscript-form-input">
										<option value="336" <?php selected( ! $intl_delay_is_custom && $intl_delay_hours == 336 ); ?>><?php esc_html_e( '2 weeks', 'trustscript' ); ?></option>
										<option value="504" <?php selected( ! $intl_delay_is_custom && $intl_delay_hours == 504 ); ?>><?php esc_html_e( '3 weeks', 'trustscript' ); ?></option>
										<option value="672" <?php selected( ! $intl_delay_is_custom && $intl_delay_hours == 672 ); ?>><?php esc_html_e( '4 weeks (1 month)', 'trustscript' ); ?></option>
										<option value="1344" <?php selected( ! $intl_delay_is_custom && $intl_delay_hours == 1344 ); ?>><?php esc_html_e( '8 weeks (2 months)', 'trustscript' ); ?></option>
										<option value="custom" <?php selected( $intl_delay_is_custom ); ?> style="color: #10b981; font-weight: bold;"><?php esc_html_e( 'Custom...', 'trustscript' ); ?></option>
									</select>
								</div>
								<div style="flex: 1; display: <?php echo $intl_delay_is_custom ? 'flex' : 'none'; ?>;" id="trustscript-custom-intl-delay-wrapper">
									<div style="display: flex; gap: 8px;">
										<input type="number" id="trustscript_custom_intl_delay_value" class="trustscript-form-input" min="0" placeholder="<?php esc_attr_e( 'Value', 'trustscript' ); ?>" value="<?php echo esc_attr( $custom_intl_delay_value ); ?>" style="max-width: 80px;">
										<select id="trustscript_custom_intl_delay_unit" class="trustscript-form-input" style="min-width: 100px;">
											<option value="hours" <?php selected( $custom_intl_delay_unit, 'hours' ); ?>><?php esc_html_e( 'Hours', 'trustscript' ); ?></option>
											<option value="days" <?php selected( $custom_intl_delay_unit, 'days' ); ?>><?php esc_html_e( 'Days', 'trustscript' ); ?></option>
										</select>
									</div>
								</div>
							</div>
							<p class="description"><?php esc_html_e( 'International shipments typically take longer. Set a longer delay to ensure customers receive and verify orders before review requests arrive.', 'trustscript' ); ?></p>
						</div>
					</div>
				</div>

				<div class="trustscript-card">
					<h2><?php esc_html_e( 'Service-Specific Settings', 'trustscript' ); ?></h2>
					<p><?php esc_html_e( 'Configure filtering and options for each active service independently.', 'trustscript' ); ?></p>
					
					<?php
					$service_manager = TrustScript_Service_Manager::get_instance();
					$active_services = $service_manager->get_active_providers();
					?>
					
					<?php if ( count( $active_services ) > 1 ) : ?>
						<div class="trustscript-service-tabs">
							<div class="trustscript-tab-navigation">
								<?php $first = true; ?>
								<?php foreach ( $active_services as $service_id => $provider ) : ?>
									<button type="button" class="trustscript-tab-button <?php echo $first ? 'active' : ''; ?>" 
											data-service="<?php echo esc_attr( $service_id ); ?>">
										<?php echo esc_html( $provider->get_service_icon() . ' ' . $provider->get_service_name() ); ?>
									</button>
									<?php $first = false; ?>
								<?php endforeach; ?>
							</div>
							
							<div class="trustscript-tab-content-wrapper">
								<?php $first = true; ?>
								<?php foreach ( $active_services as $service_id => $provider ) : ?>
									<div class="trustscript-tab-panel <?php echo $first ? 'active' : ''; ?>" 
										id="trustscript-service-<?php echo esc_attr( $service_id ); ?>">
										<?php self::render_service_specific_settings( $service_id, $provider, $categories ); ?>
									</div>
									<?php $first = false; ?>
								<?php endforeach; ?>
							</div>
						</div>
					<?php else : ?>
						<?php foreach ( $active_services as $service_id => $provider ) : ?>
							<?php self::render_service_specific_settings( $service_id, $provider, $categories ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
					
					<?php if ( empty( $active_services ) ) : ?>
						<div class="trustscript-alert trustscript-alert-warning">
							<p><?php esc_html_e( 'No active services detected. Please install and activate WooCommerce, MemberPress, or other supported plugins.', 'trustscript' ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<div class="trustscript-card">
					<h2><?php esc_html_e( 'Review Publishing', 'trustscript' ); ?></h2>
					<div class="trustscript-setting-row">
						<div class="trustscript-setting-control">
							<label class="trustscript-toggle">
								<input type="checkbox" id="trustscript_auto_publish" <?php checked( $auto_publish ); ?>>
								<span class="trustscript-toggle-slider"></span>
							</label>
						</div>
						<div class="trustscript-setting-label">
							<label for="trustscript_auto_publish">
								<?php esc_html_e( 'Auto-publish approved reviews', 'trustscript' ); ?>
							</label>
							<p class="trustscript-setting-description">
								<?php esc_html_e( 'When enabled, customer-approved reviews will be published immediately to your WooCommerce product pages. When disabled, reviews will require admin approval first.', 'trustscript' ); ?>
							</p>
						</div>
					</div>
				</div>

				<div class="trustscript-card">
					<div class="trustscript-alert trustscript-alert-info">
						<span class="dashicons dashicons-info"></span>
						<strong><?php esc_html_e( 'Review & Email Settings', 'trustscript' ); ?></strong>
						<p class="trustscript-alert-content"><?php esc_html_e( 'Depending on your review collection mode, email and media settings are managed in different places:', 'trustscript' ); ?></p>
						
						<ul class="trustscript-alert-list">
							<li>
								<strong><?php esc_html_e( 'Simple Mode (On-Site):', 'trustscript' ); ?></strong>
								<?php esc_html_e( 'Configure your review collection form, email templates, and media settings directly within WordPress.', 'trustscript' ); ?>
								<div class="trustscript-email-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustscript-review-form-setup' ) ); ?>" class="button button-secondary">
										<?php esc_html_e( 'Configure Simple Review Form', 'trustscript' ); ?>
									</a>
								</div>
							</li>
							<li>
								<strong><?php esc_html_e( 'API Mode (TrustScript):', 'trustscript' ); ?></strong>
								<?php esc_html_e( 'Manage Email Mode Settings (Auto/Manual) and Media Uploads from your TrustScript Dashboard.', 'trustscript' ); ?>
								<div class="trustscript-email-actions">
									<a href="<?php echo esc_url( $app_url . '/dashboard/wordpress-orders' ); ?>" target="_blank" class="button button-primary">
										<?php esc_html_e( 'Go to TrustScript Dashboard →', 'trustscript' ); ?>
									</a>
								</div>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="trustscript-setting-box">
				<h2><?php esc_html_e( 'Review Filter Keywords', 'trustscript' ); ?></h2>
				<p><?php esc_html_e( 'Select which keywords should appear as filter chips on product pages. Keywords are only displayed if they exist in actual customer reviews. You can also configure product-specific keywords from the product editor.', 'trustscript' ); ?></p>
				
				<div class="trustscript-form-group">
					<label><?php esc_html_e( 'Available Keywords', 'trustscript' ); ?></label>
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-top: 12px;">
						<?php foreach ( $available_keywords as $keyword ) : ?>
							<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px; background: #f9fafb; transition: all 0.2s;">
								<input type="checkbox" 
										name="trustscript_keywords[]" 
										value="<?php echo esc_attr( $keyword ); ?>"
										class="trustscript-keyword-checkbox"
										<?php checked( in_array( $keyword, (array) $review_keywords, true ) ); ?>>
								<span><?php echo esc_html( $keyword ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description" style="margin-top: 12px;">
						<?php esc_html_e( '💡 Tip: Select the keywords that matter most for your products. Unselected keywords will never appear as filter chips, even if found in reviews.', 'trustscript' ); ?>
					</p>
				</div>
			</div>

			<div class="trustscript-setting-box">
				<h2><?php esc_html_e( 'Review Voting', 'trustscript' ); ?></h2>
				<p><?php esc_html_e( 'Allow logged-in users to vote on review helpfulness. One vote per review per user.', 'trustscript' ); ?></p>
				
				<div class="trustscript-setting-row">
					<div class="trustscript-setting-control">
						<label class="trustscript-toggle">
							<input type="checkbox" id="trustscript_enable_voting" <?php checked( get_option( 'trustscript_enable_voting', false ) ); ?>>
							<span class="trustscript-toggle-slider"></span>
						</label>
					</div>
					<div class="trustscript-setting-label">
						<label for="trustscript_enable_voting">
							<?php esc_html_e( 'Enable upvote/downvote on reviews', 'trustscript' ); ?>
						</label>
						<p class="trustscript-setting-description">
							<?php esc_html_e( 'When enabled, logged-in users can vote on review helpfulness. Users must be logged in to vote, and each user can vote once per review.', 'trustscript' ); ?>
						</p>
					</div>
				</div>  

				<div class="trustscript-privacy-notice">
					<p class="trustscript-privacy-title">
						<span class="dashicons dashicons-shield"></span>
						<?php esc_html_e( 'Privacy Guarantee', 'trustscript' ); ?>
					</p>
					<p class="trustscript-privacy-description">
						<?php esc_html_e( '✓ Vote data is stored ONLY on your WordPress site', 'trustscript' ); ?><br>
						<?php esc_html_e( '✓ NO voting data is ever sent to or stored on TrustScript servers', 'trustscript' ); ?><br>
						<?php esc_html_e( '✓ All vote tracking is handled locally using WordPress user accounts', 'trustscript' ); ?><br>
						<?php esc_html_e( '✓ Users must be logged in to vote', 'trustscript' ); ?>
					</p>
				</div>
			</div>

			<div class="trustscript-card trustscript-setting-box">
				<h2><?php esc_html_e( 'Automatic Daily Sync', 'trustscript' ); ?></h2>
				
				<div class="trustscript-privacy-notice">
					<h3 class="trustscript-privacy-title">
						<strong><?php esc_html_e( 'Two-way synchronization', 'trustscript' ); ?></strong>
					</h3>
					<p class="trustscript-sync-info-box__body"><?php esc_html_e( 'TrustScript runs a complete synchronization once every day. During this cycle, it:', 'trustscript' ); ?></p>
					<ol class="trustscript-sync-info-box__list">
						<li><?php esc_html_e( 'Publishes any approved reviews from TrustScript to your products (WooCommerce, MemberPress, etc.)', 'trustscript' ); ?></li>
						<li><?php esc_html_e( 'Sends completed orders/bookings to TrustScript (WooCommerce, MemberPress)', 'trustscript' ); ?></li>
					</ol>
					<p class="trustscript-sync-info-box__note"><?php esc_html_e( 'This ensures all your services stay synced even if webhooks are unavailable or manual sync is not triggered.', 'trustscript' ); ?></p>
				</div>

				<div class="trustscript-setting-row">
					<div class="trustscript-setting-control">
						<label class="trustscript-toggle">
							<input type="checkbox" id="trustscript_auto_sync_enabled" <?php checked( $auto_sync_enabled ); ?>>
							<span class="trustscript-toggle-slider"></span>
						</label>
					</div>
					<div class="trustscript-setting-label">
						<label for="trustscript_auto_sync_enabled">
							<?php esc_html_e( 'Enable automatic daily sync', 'trustscript' ); ?>
						</label>
						<p class="trustscript-setting-description">
							<?php esc_html_e( 'When enabled, TrustScript will automatically check for missed orders once per day.', 'trustscript' ); ?>
						</p>
					</div>
				</div>

				<div class="trustscript-setting-row">
					<div class="trustscript-setting-label">
						<label for="trustscript_auto_sync_time"><?php esc_html_e( 'Daily Sync Time', 'trustscript' ); ?></label>
						<p class="trustscript-setting-description"><?php esc_html_e( 'Time of day to run automatic sync (in your site timezone).', 'trustscript' ); ?></p>
					</div>
					<div class="trustscript-setting-control">
						<input 
							type="time" 
							id="trustscript_auto_sync_time" 
							class="trustscript-form-input" 
							value="<?php echo esc_attr( $auto_sync_time ); ?>"
							style="min-width: 200px;"
						>
					</div>
				</div>

				<?php if ( $next_run ) : ?>
					<div class="trustscript-alert trustscript-alert-success">
						<strong class="trustscript-alert-label">
							<?php esc_html_e( 'Next Scheduled Sync:', 'trustscript' ); ?>
						</strong>
						<span class="trustscript-alert-time">
							<?php echo esc_html( wp_date( 'F j, Y \a\t g:i A', $next_run ) ); ?>
						</span>
					</div>
				<?php endif; ?>

				<?php if ( $last_run ) : ?>
					<div class="trustscript-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 16px;">
						<div class="trustscript-stat-card" style="background: white; color: black">
							<div class="trustscript-stat-value"><?php echo esc_html( $last_stats['processed'] ); ?></div>
							<div class="trustscript-stat-label"><?php esc_html_e( 'Last Run: Processed', 'trustscript' ); ?></div>
						</div>
						<div class="trustscript-stat-card" style="background: white; color: black">
							<div class="trustscript-stat-value"><?php echo esc_html( $last_stats['skipped'] ); ?></div>
							<div class="trustscript-stat-label"><?php esc_html_e( 'Last Run: Skipped', 'trustscript' ); ?></div>
						</div>
						<div class="trustscript-stat-card" style="background: white; color: black">
							<div class="trustscript-stat-value"><?php echo esc_html( $last_stats['errors'] ); ?></div>
							<div class="trustscript-stat-label"><?php esc_html_e( 'Last Run: Errors', 'trustscript' ); ?></div>
						</div>
					</div>
					<p class="description" style="margin-top: 8px; font-size: 12px;">
						<?php esc_html_e( 'Last run:', 'trustscript' ); ?> 
						<?php echo esc_html( wp_date( 'F j, Y \a\t g:i A', $last_run ) ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="trustscript-save-button-wrapper">
				<button type="button" id="trustscript-save-review-settings" class="trustscript-btn trustscript-btn-primary">
					<?php esc_html_e( 'Save Review Settings', 'trustscript' ); ?>
				</button>
				<span id="trustscript-review-save-status" class="trustscript-save-status"></span>
			</div>

			<div class="trustscript-card trustscript-manual-sync-card">
				<h2 class="trustscript-manual-sync-title">
					<span class="dashicons dashicons-update trustscript-update-icon"></span>
					<?php esc_html_e( 'Manual Sync & Test', 'trustscript' ); ?>
				</h2>
				<p>
					<?php esc_html_e( 'Run a complete sync immediately without waiting for the daily schedule. This is helpful for testing on staging sites.', 'trustscript' ); ?>
				</p>
				<p style="background: #dbeafe; padding: 12px; border-radius: 8px; border-left: 4px solid #3b82f6; margin-bottom: 16px;">
					<strong>
						<strong>⚡<?php esc_html_e( 'What happens when you click:', 'trustscript' ); ?></strong>
					</strong><br>
					<?php esc_html_e( '1. Fetches any approved reviews from TrustScript and publishes them to your products/services', 'trustscript' ); ?><br>
					<?php esc_html_e( '2. Sends existing orders/bookings from all enabled services (WooCommerce, MemberPress, etc.) to TrustScript', 'trustscript' ); ?>
				</p>
				
				<div class="trustscript-form-group">
					<label for="trustscript-sync-days"><?php esc_html_e( 'Sync orders from the last:', 'trustscript' ); ?></label>
					<select id="trustscript-sync-days" class="trustscript-form-input trustscript-sync-select">
						<option value="1">1 day (24 hours)</option>
						<option value="2" selected>2 days (48 hours)</option>
					</select>
				</div>

				<button type="button" id="trustscript-sync-orders" class="trustscript-btn trustscript-btn-secondary">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Run Complete Sync Now', 'trustscript' ); ?>
				</button>
				<span id="trustscript-sync-status" class="trustscript-sync-status"></span>
				
				<div id="trustscript-sync-results" class="trustscript-sync-results-card">
					<div class="trustscript-stat-card trustscript-stat-card-success trustscript-sync-results-success">
						<div class="trustscript-stat-value trustscript-sync-results-value" id="sync-count">0</div>
						<div class="trustscript-stat-label trustscript-sync-results-label"><?php esc_html_e( 'TOTAL PROCESSED', 'trustscript' ); ?></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
	public static function render_service_specific_settings($service_id, $provider, $categories = array())
	{
		switch ($service_id) {
			case 'woocommerce':
				self::render_woocommerce_settings($categories);
				break;
			case 'memberpress':
				self::render_memberpress_settings();
				break;
			default:
				echo '<p class="description">' . esc_html__('No additional filters available for this service. All orders/bookings will be processed.', 'trustscript') . '</p>';
				break;
		}
	}

	/**
	 * Build a recursive category tree from flat list of categories, calculating total product counts including children
	 */
	private static function build_recursive_category_tree($categories_by_id, $parent_id = 0)
	{
		$tree = array();

		foreach ($categories_by_id as $term_id => $term) {
			if ((int) $term->parent === (int) $parent_id) {
				$children = self::build_recursive_category_tree($categories_by_id, $term_id);
				
				$total_count = $term->count;
				if (!empty($children)) {
					foreach ($children as $child) {
						$total_count += $child['term']->count;
					}
				}
				
				$tree[$term_id] = array(
					'term' => $term,
					'children' => $children,
					'total_count' => $total_count,
				);
			}
		}

		return $tree;
	}

	/**
	 * Filter category tree to only show categories with products or children with products
	 */
	private static function filter_category_tree($tree)
	{
		$filtered = array();

		foreach ($tree as $term_id => $node) {
			$filtered_children = self::filter_category_tree($node['children']);
			if ($node['term']->count > 0 || !empty($filtered_children)) {
				$node['children'] = $filtered_children;
				$filtered[$term_id] = $node;
			}
		}

		return $filtered;
	}

	/**
	 * Recursively render category tree with proper nesting and expand/collapse support
	 */
	private static function render_category_tree_recursive($tree, $selected_categories, $depth = 0)
	{
		foreach ($tree as $term_id => $node) {
			$term = $node['term'];
			$children = $node['children'];
			$is_checked = in_array($term->term_id, (array) $selected_categories);
			$has_children = !empty($children);
			$indent_style = $depth > 0 ? 'margin-left: ' . ($depth * 24) . 'px;' : '';
			?>

			<label class="trustscript-checkbox-label trustscript-parent-category" 
				data-parent-id="<?php echo esc_attr($term->term_id); ?>" style="<?php echo esc_attr($indent_style); ?>">
				<input type="checkbox" name="trustscript_review_categories[]"
					value="<?php echo esc_attr($term->term_id); ?>" class="trustscript-category-checkbox trustscript-parent-checkbox"
					data-category-name="<?php echo esc_attr(strtolower($term->name)); ?>"
					data-has-children="<?php echo $has_children ? '1' : '0'; ?>"
					data-depth="<?php echo esc_attr($depth); ?>" <?php checked($is_checked); ?>>
				<span>
					<?php echo esc_html($term->name); ?>
					<span class="trustscript-product-categories-label-count">
						(<?php echo esc_html($node['total_count']); ?>)
					</span>
				</span>
				<?php if ($has_children): ?>
					<span class="trustscript-expand-toggle">▶</span>
				<?php endif; ?>
			</label>

			<?php if ($has_children): ?>
				<div class="trustscript-subcategories" data-parent-id="<?php echo esc_attr($term->term_id); ?>" data-depth="<?php echo esc_attr($depth); ?>">
					<?php self::render_category_tree_recursive($children, $selected_categories, $depth + 1); ?>
				</div>
			<?php endif; ?>
			<?php
		}
	}

	/**
	 * Render WooCommerce settings form
	 */
	private static function render_woocommerce_settings($categories = array())
	{
		$wc_categories = get_terms(array(
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
		));

		$category_tree = array();
		$categories_by_id = array();

		if (!is_wp_error($wc_categories) && !empty($wc_categories)) {
			foreach ($wc_categories as $term) {
				$categories_by_id[$term->term_id] = $term;
			}

			$category_tree = self::build_recursive_category_tree($categories_by_id, 0);
			$category_tree = self::filter_category_tree($category_tree);
		}

		$min_order_value = get_option('trustscript_woocommerce_min_value', '0');
		$exclude_free = get_option('trustscript_woocommerce_exclude_free', '0');

		?>
		<div class="trustscript-service-settings-section">
			<h3>📦<?php esc_html_e('Product Category Filtering', 'trustscript'); ?></h3>
			<p class="description">
				<?php esc_html_e('Select specific product categories to collect reviews for. Leave all unchecked to include all products.', 'trustscript'); ?>
				<br><em><?php esc_html_e('Note: When you select a parent category, all products in that category tree are included. Child categories should not be selected alongside their parent.', 'trustscript'); ?></em>
			</p>

			<?php if (!empty($category_tree)): ?>
				<div class="trustscript-category-search-toolbar">
					<input type="text" id="trustscript-category-search" class="trustscript-category-search-input"
						placeholder="<?php esc_attr_e('Search categories...', 'trustscript'); ?>">
					<button type="button" id="trustscript-select-all-categories"
						class="button button-secondary trustscript-category-button">
						✓ <?php esc_html_e('Select All', 'trustscript'); ?>
					</button>
					<button type="button" id="trustscript-deselect-all-categories"
						class="button button-secondary trustscript-category-button">
						✕ <?php esc_html_e('Deselect All', 'trustscript'); ?>
					</button>
					<span id="trustscript-category-count" class="trustscript-category-count">
						<?php
						/* translators: %d: number of categories */
						printf(esc_html__('%d categories', 'trustscript'), count($category_tree));
						?>
					</span>
				</div>

				<div class="trustscript-product-categories-list">
					<?php self::render_category_tree_recursive($category_tree, $categories, 0); ?>
				</div>
			<?php else: ?>
				<p class="description"><?php esc_html_e('No product categories found.', 'trustscript'); ?></p>
			<?php endif; ?>
			<hr style="margin: 24px 0;">

			<h3>💰<?php esc_html_e('Order Value Filtering', 'trustscript'); ?></h3>
			<div class="trustscript-form-group">
				<label for="trustscript_woocommerce_min_value">
					<?php
					$currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
					/* translators: %s: store currency symbol */
					printf(esc_html__('Minimum Order Value (%s):', 'trustscript'), esc_html($currency_symbol));
					?>
				</label>
				<input type="number" id="trustscript_woocommerce_min_value" name="trustscript_woocommerce_min_value"
					value="<?php echo esc_attr($min_order_value); ?>" min="0" step="0.01" class="trustscript-form-input"
					style="max-width: 200px;">
				<p class="description">
					<?php esc_html_e('Only send review requests for orders above this amount. Set to 0 to disable.', 'trustscript'); ?>
				</p>
			</div>

			<div class="trustscript-form-group">
				<label class="trustscript-checkbox-label">
					<input type="checkbox" id="trustscript_woocommerce_exclude_free" name="trustscript_woocommerce_exclude_free"
						value="1" <?php checked($exclude_free, '1'); ?>>
					<?php
					$currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
					/* translators: %s: store currency symbol */
					printf(esc_html__('Exclude free products and %s0 orders', 'trustscript'), esc_html($currency_symbol));
					?>
				</label>
			</div>
		</div>
		<?php
	}

	private static function render_memberpress_settings()
	{
		if (!class_exists('MeprProduct')) {
			echo '<p class="description">' . esc_html__('MemberPress is not properly loaded.', 'trustscript') . '</p>';
			return;
		}

		$memberships = \MeprProduct::get_all();
		$selected_memberships = (array) get_option('trustscript_memberpress_memberships', array());
		$delay_days = get_option('trustscript_memberpress_delay_days', '0');

		?>
		<div class="trustscript-service-settings-section">
			<h3>👥<?php esc_html_e('Membership Tier Filtering', 'trustscript'); ?></h3>
			<p class="description">
				<?php esc_html_e('Select which membership tiers should trigger review requests. Leave all unchecked to include all memberships.', 'trustscript'); ?>
			</p>

			<?php if (!empty($memberships)): ?>
				<div class="trustscript-product-categories-list">
					<?php foreach ($memberships as $membership): ?>
						<label class="trustscript-checkbox-label">
							<input type="checkbox" name="trustscript_memberpress_memberships[]"
								value="<?php echo esc_attr($membership->ID); ?>" <?php checked(in_array($membership->ID, $selected_memberships)); ?>>
							<?php echo esc_html($membership->post_title); ?>
						</label>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<p class="description"><?php esc_html_e('No memberships found.', 'trustscript'); ?></p>
			<?php endif; ?>

			<hr style="margin: 24px 0;">

			<h3>⏰<?php esc_html_e('Review Request Timing', 'trustscript'); ?></h3>
			<div class="trustscript-form-group">
				<label for="trustscript_memberpress_delay_days">
					<?php esc_html_e('Send review request after:', 'trustscript'); ?>
				</label>
				<select id="trustscript_memberpress_delay_days" name="trustscript_memberpress_delay_days"
					class="trustscript-form-input" style="max-width: 200px;">
					<option value="0" <?php selected($delay_days, '0'); ?>>
						<?php esc_html_e('Immediately', 'trustscript'); ?>
					</option>
					<option value="7" <?php selected($delay_days, '7'); ?>><?php esc_html_e('7 days', 'trustscript'); ?>
					</option>
					<option value="14" <?php selected($delay_days, '14'); ?>><?php esc_html_e('14 days', 'trustscript'); ?>
					</option>
					<option value="30" <?php selected($delay_days, '30'); ?>><?php esc_html_e('30 days', 'trustscript'); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e('Allow members time to experience your content before requesting a review.', 'trustscript'); ?>
				</p>
			</div>
		</div>
		<?php
	}


	/**
	 * Render the service detection and configuration UI in the admin dashboard
	 */
	public static function render_service_detection_ui()
	{
		$service_manager = TrustScript_Service_Manager::get_instance();
		$active_services = $service_manager->get_active_providers();

		if (empty($active_services)) {
			?>
			<div class="trustscript-notice-warning">
				<p class="trustscript-notice-warning-title">
					<strong><?php esc_html_e('No Supported Services Detected', 'trustscript'); ?></strong>
				</p>
				<p class="trustscript-notice-warning-subtitle">
					<?php esc_html_e('TrustScript works with WooCommerce, MemberPress, and more.', 'trustscript'); ?>
				</p>
				<p class="trustscript-notice-warning-content">
					<?php esc_html_e('Install and activate a supported plugin to start collecting reviews.', 'trustscript'); ?>
					<a href="<?php echo esc_url(trustscript_get_app_url() . '/docs/wordpress/supported-platforms'); ?>"
						target="_blank">
						<?php esc_html_e('View Supported Platforms', 'trustscript'); ?>
					</a>
				</p>
			</div>
			<?php
			return;
		}

		?>
		<div class="trustscript-card trustscript-mb-24">
			<h2><?php esc_html_e('Detected Services', 'trustscript'); ?></h2>
			<p><?php esc_html_e('TrustScript has detected the following services on your site. Configure trigger statuses for each service to automatically collect reviews.', 'trustscript'); ?></p>

			<div class="trustscript-services-grid">
				<?php foreach ($active_services as $service_id => $provider):
					$service_name = $provider->get_service_name();
					$service_icon = TrustScript_Plugin_Admin::get_service_icon($service_id);
					$all_statuses = $provider->get_available_statuses();
					$current_trigger = get_option('trustscript_trigger_status_' . $service_id, '');
					$is_enabled = get_option('trustscript_enable_service_' . $service_id, '0') === '1';

					if (empty($current_trigger) && !empty($all_statuses)) {
						$current_trigger = array_key_first($all_statuses);
					}
					?>
					<div class="trustscript-service-card <?php echo $is_enabled ? 'active' : 'inactive'; ?>"
						data-service-id="<?php echo esc_attr($service_id); ?>">
						<div class="trustscript-service-header">
							<div class="trustscript-service-title">
								<span class="trustscript-service-icon"><?php echo esc_html($service_icon); ?></span>
								<h3><?php echo esc_html($service_name); ?></h3>
							</div>
							<label class="trustscript-toggle">
								<input type="checkbox" name="trustscript_enable_service_<?php echo esc_attr($service_id); ?>"
									value="1" <?php checked($is_enabled, true); ?> class="trustscript-service-toggle"
									data-service-id="<?php echo esc_attr($service_id); ?>" />
								<span class="trustscript-toggle-slider"></span>
							</label>
						</div>

						<div
							class="trustscript-service-body<?php echo !$is_enabled ? ' trustscript-service-body-disabled' : ''; ?>">
							<div class="trustscript-service-setting">
								<label for="trustscript_trigger_status_<?php echo esc_attr($service_id); ?>">
									<strong><?php esc_html_e('Trigger Status:', 'trustscript'); ?></strong>
									<span
										class="description"><?php esc_html_e('Send review request when order reaches this status', 'trustscript'); ?></span>
								</label>
								<select name="trustscript_trigger_status_<?php echo esc_attr($service_id); ?>"
									id="trustscript_trigger_status_<?php echo esc_attr($service_id); ?>"
									class="trustscript-service-trigger" data-service-id="<?php echo esc_attr($service_id); ?>">
									<?php foreach ($all_statuses as $status_key => $status_label): ?>
										<option value="<?php echo esc_attr($status_key); ?>" <?php selected($current_trigger, $status_key); ?>>
											<?php echo esc_html($status_label); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="trustscript-service-stats">
								<small class="description">
									<?php
									printf(
										/* translators: %s is the number of available statuses */
										esc_html__('%d status options available', 'trustscript'),
										count($all_statuses)
									);
									?>
								</small>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="trustscript-save-button-wrapper">
				<button type="button" id="trustscript-save-service-settings" class="trustscript-btn trustscript-btn-primary">
					<?php esc_html_e('Save Service Settings', 'trustscript'); ?>
				</button>
				<span id="trustscript-service-save-status" class="trustscript-save-status"></span>
			</div>
		</div>

		<div class="trustscript-card trustscript-mb-24">
			<h2><?php esc_html_e('Optional Data Collection', 'trustscript'); ?></h2>
			<p><?php esc_html_e('Enhance review requests with additional context. You can disable these options for privacy-sensitive products (e.g., medical, adult content).', 'trustscript'); ?></p>

			<table class="form-table trustscript-form-table">
				<tr>
					<th scope="row"><?php esc_html_e('Include Product Names', 'trustscript'); ?></th>
					<td>
						<div class="trustscript-checkbox-wrapper">
							<label for="trustscript-include-product-names">
								<input 
									type="checkbox" 
									id="trustscript-include-product-names" 
									name="trustscript_include_product_names"
									value="1" 
									<?php checked(get_option('trustscript_include_product_names', '1'), '1'); ?>
									class="trustscript-optional-data-toggle"
								/>
								<span><?php esc_html_e('Show specific product names in review requests (e.g., "Blue Wireless Headphones").', 'trustscript'); ?></span>
							</label>
						</div>
						<p class="description">
							<?php esc_html_e('If disabled, customers will see generic text like "your recent purchase" instead. Disable this for privacy-sensitive products.', 'trustscript'); ?>
						</p>

						<div class="trustscript-alert trustscript-alert-info">
							<strong>💡 <?php esc_html_e('Why this matters:', 'trustscript'); ?></strong>
							<p class="trustscript-alert-content">
								<?php esc_html_e('Specific product names help customers write better, more relevant reviews. However, for sensitive items (health, personal care, gifts), you may want to keep purchases private.', 'trustscript'); ?>
							</p>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Include Order Dates', 'trustscript'); ?></th>
					<td>
						<div class="trustscript-checkbox-wrapper">
							<label for="trustscript-include-order-dates">
								<input 
									type="checkbox" 
									id="trustscript-include-order-dates" 
									name="trustscript_include_order_dates"
									value="1" 
									<?php checked(get_option('trustscript_include_order_dates', '1'), '1'); ?>
									class="trustscript-optional-data-toggle"
								/>
								<span><?php esc_html_e('Share the purchase date with customers.', 'trustscript'); ?></span>
							</label>
						</div>
						<p class="description">
							<?php esc_html_e('This helps with timing review requests and adds context to their feedback.', 'trustscript'); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="trustscript-save-button-wrapper">
				<button type="button" id="trustscript-save-optional-data-settings" class="trustscript-btn trustscript-btn-primary">
					<?php esc_html_e('Save Privacy Settings', 'trustscript'); ?>
				</button>
				<span id="trustscript-optional-data-save-status" class="trustscript-save-status"></span>
			</div>
		</div>
		<?php
	}
}