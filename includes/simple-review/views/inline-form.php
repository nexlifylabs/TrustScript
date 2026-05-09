<?php
/**
 * Simple Review - Shortcode / Inline Form Template
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$trustscript_token              = sanitize_text_field( $data['token'] );
$trustscript_initial_product_id = (int) $data['initial_product_id'];
$trustscript_product_image      = esc_url( $data['product_image'] );
$trustscript_product_name       = esc_html( $data['product_name'] );
$trustscript_customer_name      = esc_html( $data['customer_name'] );
$trustscript_customer_email     = esc_attr( $data['customer_email'] );
$trustscript_is_single          = (bool) $data['is_single'];
?>
<div id="trustscript-inline-form-wrap" <?php echo $trustscript_is_single ? '' : 'style="display:none;"'; ?>>
	<div class="trustscript-product-hero">
		<div class="trustscript-product-img-wrap">
			<img id="trustscript-hero-img" src="<?php echo esc_url( $trustscript_product_image ); ?>" alt="">
		</div>
		<div class="trustscript-product-meta">
			<div class="trustscript-product-name" id="trustscript-hero-title"><?php echo esc_html( $trustscript_product_name ); ?></div>
			<div class="trustscript-verification-badge">
				<svg viewBox="0 0 12 12" fill="none">
					<path d="M2 6l3 3 5-5" stroke="#166534" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
				<?php esc_html_e( 'Verified Purchase', 'trustscript' ); ?>
			</div>
		</div>
	</div>
	<div class="trustscript-review-modal-steps">
		<div class="trustscript-step-indicator">
			<span class="trustscript-step-dot trustscript-step-active" data-step="1">1</span>
			<span class="trustscript-step-line"></span>
			<span class="trustscript-step-dot" data-step="2">2</span>
		</div>
	</div>
	<div class="trustscript-form-body">
		<form id="trustscript-review-form" novalidate>
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $trustscript_initial_product_id ); ?>">
			<input type="hidden" name="review_token" value="<?php echo esc_attr( $trustscript_token ); ?>">
			<input type="hidden" id="trustscript-rating-input" name="rating" value="">
			<input type="text" name="trustscript_hp" style="display:none" tabindex="-1" autocomplete="off">
			<div class="trustscript-step-panel" id="trustscript-panel-1">
				<div class="trustscript-section-title">
					<?php esc_html_e( 'Share your', 'trustscript' ); ?> <span><?php esc_html_e( 'experience', 'trustscript' ); ?></span>
				</div>

				<div class="trustscript-field">
					<div class="trustscript-field-label">
						<span class="trustscript-field-label-text">
							<?php esc_html_e( 'Your Rating', 'trustscript' ); ?>
							<span class="trustscript-required">*</span>
						</span>
					</div>
					<div class="trustscript-star-selector" id="trustscript-star-selector" role="radiogroup" aria-label="<?php esc_attr_e( 'Rating', 'trustscript' ); ?>">
						<?php for ( $trustscript_i = 1; $trustscript_i <= 5; $trustscript_i++ ) : ?>
						<button type="button" class="trustscript-star-btn" data-rating="<?php echo esc_attr( $trustscript_i ); ?>" aria-label="
							<?php
							/* translators: %d: rating value */
							echo esc_attr( sprintf( __( '%d star', 'trustscript' ), $trustscript_i ) );
							?>
							">
							<svg viewBox="0 0 24 24" width="32" height="32"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						</button>
						<?php endfor; ?>
					</div>
					<div class="trustscript-star-label" id="trustscript-star-label"><?php esc_html_e( 'Tap a star to rate', 'trustscript' ); ?></div>
					<div class="trustscript-field-error" id="trustscript-rating-error"></div>
				</div>

				<div class="trustscript-field">
					<div class="trustscript-field-label">
						<span class="trustscript-field-label-text">
							<?php esc_html_e( 'Your Review', 'trustscript' ); ?>
							<span class="trustscript-required">*</span>
						</span>
						<span class="trustscript-optional"><?php esc_html_e( 'min. 10 characters', 'trustscript' ); ?></span>
					</div>
					<textarea class="trustscript-textarea" id="trustscript-review-text" name="review_text" maxlength="2000"
						placeholder="<?php esc_attr_e( 'What did you love? Would you recommend it?', 'trustscript' ); ?>"></textarea>
					<div class="trustscript-char-row">
						<span class="trustscript-char-count" id="trustscript-char-count">0</span>&nbsp;/ 2000
					</div>
					<div class="trustscript-field-error" id="trustscript-text-error"></div>
				</div>

				<?php if ( ! empty( $data['customer_name'] ) && ! empty( $data['customer_email'] ) ) : ?>
				<input type="hidden" id="trustscript-name" name="reviewer_name" value="<?php echo esc_attr( $data['customer_name'] ); ?>">
				<input type="hidden" id="trustscript-email" name="reviewer_email" value="<?php echo esc_attr( $data['customer_email'] ); ?>">
				<p class="trustscript-reviewing-as">
					<?php
					/* translators: %s: customer name */
					printf( esc_html__( 'Reviewing as %s', 'trustscript' ), '<strong>' . esc_html( $data['customer_name'] ) . '</strong>' );
					?>
				</p>
				<?php else : ?>
				<div class="trustscript-field">
					<div class="trustscript-field-label">
						<span class="trustscript-field-label-text"><?php esc_html_e( 'Your Details', 'trustscript' ); ?></span>
					</div>
					<div class="trustscript-input-row">
						<div>
							<input type="text" class="trustscript-input" id="trustscript-name" name="reviewer_name"
								placeholder="<?php esc_attr_e( 'Full name', 'trustscript' ); ?>" autocomplete="name">
							<div class="trustscript-field-error" id="trustscript-name-error"></div>
						</div>
						<div>
							<input type="email" class="trustscript-input" id="trustscript-email" name="reviewer_email"
								placeholder="<?php esc_attr_e( 'Email address', 'trustscript' ); ?>" autocomplete="email">
							<div class="trustscript-field-error" id="trustscript-email-error"></div>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<div class="trustscript-actions end">
					<button type="button" class="trustscript-btn-next" id="trustscript-next-btn">
						<?php esc_html_e( 'Continue', 'trustscript' ); ?>
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
							<path d="M6 12l4-4-4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</button>
				</div>
			</div>
			<div class="trustscript-step-panel" id="trustscript-panel-2" style="display:none">
				<div class="trustscript-section-title">
					<?php esc_html_e( 'Add', 'trustscript' ); ?> <span><?php esc_html_e( 'photos', 'trustscript' ); ?></span>
				</div>

				<div class="trustscript-field">
					<div class="trustscript-upload-zone" id="trustscript-upload-zone">
						<input type="file" class="trustscript-photo-input" id="trustscript-photo-input"
							accept="image/jpeg,image/png,image/webp" multiple>
						<div id="trustscript-upload-placeholder">
							<div class="trustscript-upload-icon">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
									<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
								</svg>
							</div>
							<div class="trustscript-upload-title"><?php esc_html_e( 'Drop photos here', 'trustscript' ); ?></div>
							<div class="trustscript-upload-sub"><?php echo wp_kses_post( __( 'or <strong>browse to upload</strong> &mdash; JPEG, PNG, WebP &bull; max 5 MB each', 'trustscript' ) ); ?></div>
						</div>
					</div>
					<div class="trustscript-previews" id="trustscript-previews"></div>
					<div class="trustscript-upload-note" id="trustscript-photo-note"><?php esc_html_e( 'Up to 3 photos', 'trustscript' ); ?></div>
					<div class="trustscript-field-error" id="trustscript-photo-error"></div>
				</div>

				<div class="trustscript-actions">
					<button type="button" class="trustscript-btn-ghost" id="trustscript-back-btn">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none">
							<path d="M10 12L6 8l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<?php esc_html_e( 'Back', 'trustscript' ); ?>
					</button>
					<div class="trustscript-actions-right">
						<button type="button" class="trustscript-skip-link" id="trustscript-skip-btn"><?php esc_html_e( 'Skip photos', 'trustscript' ); ?></button>
						<button type="submit" class="trustscript-btn-next" id="trustscript-submit-btn">
							<span id="trustscript-submit-text"><?php esc_html_e( 'Submit Review', 'trustscript' ); ?></span>
							<span id="trustscript-submit-loading" style="display:none;">
								<svg class="trustscript-spinner" width="16" height="16" viewBox="0 0 16 16" fill="none">
									<circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="2" stroke-dasharray="20 18"/>
								</svg>
								<?php esc_html_e( 'Submitting…', 'trustscript' ); ?>
							</span>
						</button>
					</div>
				</div>
			</div>
		</form>
		<div class="trustscript-confirmation-panel" id="trustscript-confirmation" style="display:none;">
			<div class="trustscript-success-icon">
				<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M6 16l7 7 13-13"/>
				</svg>
			</div>
			<div class="trustscript-success-heading"><?php esc_html_e( 'Thank you!', 'trustscript' ); ?></div>
			<p class="trustscript-success-sub"><?php esc_html_e( 'Your review has been submitted and is pending approval.', 'trustscript' ); ?></p>
			<div class="trustscript-remaining-products">
				<p class="trustscript-confirmation-prompt"><?php esc_html_e( 'Would you also like to review these products?', 'trustscript' ); ?></p>
				<div class="trustscript-remaining-products-list" id="trustscript-remaining-products-list"></div>
			</div>
		</div>
		<div class="trustscript-success-panel" id="trustscript-success" style="display:none;">
			<div class="trustscript-success-icon">
				<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M6 16l7 7 13-13"/>
				</svg>
			</div>
			<div class="trustscript-success-heading"><?php esc_html_e( 'Thank you!', 'trustscript' ); ?></div>
			<p class="trustscript-success-sub"><?php esc_html_e( 'Your review has been submitted and is pending approval.', 'trustscript' ); ?></p>
		</div>
	</div>
</div>
