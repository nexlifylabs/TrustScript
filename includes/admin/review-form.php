<?php
/**
 * Admin review form setup page.
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$trustscript_saved_page_id = (int) get_option( 'trustscript_review_page_id', 0 );
$trustscript_shortcode_found = false;
if ( $trustscript_saved_page_id ) {
	$trustscript_saved_post = get_post( $trustscript_saved_page_id );
	if ( $trustscript_saved_post && has_shortcode( $trustscript_saved_post->post_content, 'trustscript_review_form' ) ) {
		$trustscript_shortcode_found = true;
	}
}
?>
<div class="trustscript-admin-section">

	<h2><?php esc_html_e( 'Review Form Setup', 'trustscript' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Customers who receive a review request email are sent to a page on your site. Follow the steps below to configure that page.', 'trustscript' ); ?>
	</p>

	<hr>

	<div class="trustscript-setup-step">
		<div class="trustscript-step-badge">1</div>
		<div class="trustscript-step-content">
			<h3><?php esc_html_e( 'Create your review page', 'trustscript' ); ?></h3>
			<p>
				<?php esc_html_e( 'Go to Pages → Add New in your WordPress dashboard. Give the page any title you like (e.g. "Write a Review"), then paste this shortcode into the page content:', 'trustscript' ); ?>
			</p>
			<div class="trustscript-shortcode-box">
				<code id="trustscript-shortcode-copy">[trustscript_review_form]</code>
				<button type="button" class="button button-secondary" id="trustscript-copy-btn" onclick="
					navigator.clipboard.writeText('[trustscript_review_form]');
					this.textContent = '<?php esc_attr_e( 'Copied!', 'trustscript' ); ?>';
					setTimeout(() => this.textContent = '<?php esc_attr_e( 'Copy', 'trustscript' ); ?>', 2000);
				"><?php esc_html_e( 'Copy', 'trustscript' ); ?></button>
			</div>
			<p class="description">
				<?php esc_html_e( 'Publish the page, then come back here for Step 2.', 'trustscript' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>" class="button" target="_blank">
					<?php esc_html_e( '+ Create New Page', 'trustscript' ); ?>
				</a>
			</p>
			<p class="description" style="color: #d63638;">
				<strong>Note:</strong> <?php esc_html_e( 'Twenty Twenty-Five theme: the review collection form styling may not work correctly. If you are just testing, consider using Twenty Twenty-Four or Twenty Twenty-Three instead.', 'trustscript' ); ?>
			</p>
		</div>
	</div>
	<hr>
	<form id="trustscript-review-form-settings">
		<?php wp_nonce_field( 'trustscript_save_review', 'trustscript_review_form_nonce', false ); ?>
		<div class="trustscript-setup-step">
			<div class="trustscript-step-badge">2</div>
			<div class="trustscript-step-content">
				<h3><?php esc_html_e( 'Select your review page', 'trustscript' ); ?></h3>
				<p>
					<?php esc_html_e( 'Choose the page you created in Step 1. The plugin will include a link to this page in every review request email.', 'trustscript' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="trustscript_review_page_id"><?php esc_html_e( 'Review Form Page', 'trustscript' ); ?></label>
						</th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'trustscript_review_page_id',
									'id'                => 'trustscript_review_page_id',
									'selected'          => esc_attr( $trustscript_saved_page_id ),
									'show_option_none'  => esc_html__( '— Select a page —', 'trustscript' ),
									'option_none_value' => '0',
									'class'             => 'regular-text',
								)
							);
							?>

					<?php if ( $trustscript_saved_page_id && ! $trustscript_shortcode_found ) : ?>
							<p class="trustscript-description-error">
								⚠️ <?php esc_html_e( 'The selected page does not contain the [trustscript_review_form] shortcode. Please add it before saving.', 'trustscript' ); ?>
							</p>
						<?php elseif ( $trustscript_shortcode_found ) : ?>
						<p class="trustscript-description-success">
							✓ <?php esc_html_e( 'Shortcode detected on this page.', 'trustscript' ); ?>
							<a href="<?php echo esc_url( get_permalink( $trustscript_saved_page_id ) ); ?>" target="_blank" class="trustscript-visit-page-link">
									<?php esc_html_e( 'Visit page ↗', 'trustscript' ); ?>
								</a>
								<span class="trustscript-page-hint"> — <?php esc_html_e( 'The form appears when a customer follows their email review link.', 'trustscript' ); ?></span>
							</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<hr>
		<div class="trustscript-setup-step">
			<div class="trustscript-step-badge">3</div>
			<div class="trustscript-step-content">
				<h3><?php esc_html_e( 'Customize your email template', 'trustscript' ); ?></h3>
				<p>
					<?php esc_html_e( 'Personalize the review request email sent to your customers.', 'trustscript' ); ?>
				</p>

				<?php
				$trustscript_email_subject = get_option(
					'trustscript_simple_email_subject',
					class_exists( 'TrustScript_Simple_Email_Review' ) ? TrustScript_Simple_Email_Review::get_default_email_subject() : ''
				);
				$trustscript_email_body    = get_option(
					'trustscript_simple_email_body',
					class_exists( 'TrustScript_Simple_Email_Review' ) ? TrustScript_Simple_Email_Review::get_default_email_body() : ''
				);
				?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="trustscript_simple_email_subject"><?php esc_html_e( 'Email Subject', 'trustscript' ); ?></label>
						</th>
						<td>
							<input type="text" id="trustscript_simple_email_subject" name="trustscript_simple_email_subject" class="regular-text" value="<?php echo esc_attr( $trustscript_email_subject ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="trustscript_simple_email_body"><?php esc_html_e( 'Email Body', 'trustscript' ); ?></label>
						</th>
						<td>
							<?php
							if ( class_exists( 'TrustScript_Simple_Email_Review' ) ) :
							$trustscript_placeholders = TrustScript_Simple_Email_Review::get_simple_email_placeholders();
								?>
							<div class="trustscript-placeholder-toolbar">
								<span class="trustscript-placeholder-toolbar-label">
									<?php esc_html_e( 'Click a tag to insert it at the cursor:', 'trustscript' ); ?>
								</span>
								<div class="trustscript-placeholder-tags">
							<?php foreach ( $trustscript_placeholders as $trustscript_token => $trustscript_label ) : ?>
							<button type="button"
								class="trustscript-insert-placeholder"
								data-placeholder="<?php echo esc_attr( $trustscript_token ); ?>"
								title="<?php echo esc_attr( $trustscript_label ); ?>">
								<?php echo esc_html( $trustscript_token ); ?>
									</button>
									<?php endforeach; ?>
								</div>
							</div>
							<?php endif; ?>

							<?php
							wp_editor(
								$trustscript_email_body,
								'trustscript_simple_email_body',
								array(
									'media_buttons' => false,
									'textarea_name' => 'trustscript_simple_email_body',
									'textarea_rows' => 12,
									'teeny'         => false,
								)
							);
							?>

							<div class="trustscript-email-actions">
								<button type="button" id="trustscript-preview-email-btn" class="button button-secondary">
									<?php esc_html_e( '👁 Preview Email', 'trustscript' ); ?>
								</button>
								<button type="button" id="trustscript-reset-email-btn" class="button trustscript-btn-reset">
									<?php esc_html_e( 'Reset to default template', 'trustscript' ); ?>
								</button>
								<span id="trustscript-preview-email-status" class="trustscript-action-status"></span>
							</div>

							<script>
							var tsDefaultEmailData = {
								subject: <?php echo wp_json_encode( class_exists( 'TrustScript_Simple_Email_Review' ) ? TrustScript_Simple_Email_Review::get_default_email_subject() : '' ); ?>,
								body: <?php echo wp_json_encode( class_exists( 'TrustScript_Simple_Email_Review' ) ? TrustScript_Simple_Email_Review::get_default_email_body() : '' ); ?>
							};
							</script>
						</td>
					</tr>
					<tr id="trustscript-email-preview-row" style="display:none;">
						<th scope="row"><?php esc_html_e( 'Preview', 'trustscript' ); ?></th>
						<td>
							<div class="trustscript-email-preview-wrapper">
								<div class="trustscript-email-preview-header">
									<span class="trustscript-email-preview-subject-label">
										<?php esc_html_e( 'Subject:', 'trustscript' ); ?>
									</span>
									<span id="trustscript-preview-subject" class="trustscript-email-preview-subject"></span>
									<button type="button" id="trustscript-preview-close" 
										class="trustscript-email-preview-close" 
										aria-label="<?php esc_attr_e( 'Close preview', 'trustscript' ); ?>">
										&times;
									</button>
								</div>
								<div id="trustscript-preview-body" class="trustscript-email-preview-body"></div>
							</div>
							<p class="trustscript-email-preview-hint">
								<?php esc_html_e( 'Sample data: customer "Jane Doe", product "Sample Product", order #1001.', 'trustscript' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>

	</form>
	<div class="trustscript-save-section">
		<button type="button" id="trustscript-save-review-form" class="button button-primary">
			<?php esc_html_e( 'Save Settings', 'trustscript' ); ?>
		</button>
		<span id="trustscript-review-form-save-status" class="trustscript-save-status"></span>
	</div>
</div>