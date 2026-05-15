<?php
/**
 * Simple Review - Product Page Modal Form Template
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$trustscript_is_logged_in    = (bool) $data['is_logged_in'];
$trustscript_needs_name      = (bool) $data['needs_name'];
$trustscript_str             = (array) $data['str'];
$trustscript_star_labels     = (array) $data['star_labels'];
$trustscript_min_text_length = (int) $data['min_text_length'];
$trustscript_max_text_length = (int) $data['max_text_length'];
$trustscript_max_photos      = (int) $data['max_photos'];
$trustscript_is_verified_buyer = isset( $data['is_verified_buyer'] ) ? (bool) $data['is_verified_buyer'] : false;
?>
<div class="trustscript-review-modal-overlay"
	id="trustscript-review-modal"
	style="display:none;"
	role="dialog"
	aria-modal="true"
	aria-label="<?php echo esc_attr( $trustscript_str['dialog_label'] ); ?>">

	<div class="trustscript-review-modal">

		<div class="trustscript-review-modal-header">
			<h3><?php echo esc_html( $trustscript_str['heading'] ); ?></h3>
			<button type="button"
					class="trustscript-review-modal-close"
					id="trustscript-review-modal-close"
					aria-label="<?php echo esc_attr( $trustscript_str['close'] ); ?>">&times;</button>
		</div>

		<?php if ( $trustscript_is_verified_buyer ) : ?>
		<div class="trustscript-review-modal-steps">
			<div class="trustscript-step-indicator">
				<span class="trustscript-step-dot trustscript-step-active" data-step="1">1</span>
				<span class="trustscript-step-line"></span>
				<span class="trustscript-step-dot" data-step="2">2</span>
			</div>
		</div>
		<?php endif; ?>

		<form id="trustscript-review-form" enctype="multipart/form-data" novalidate>
			<div class="trustscript-review-step" id="trustscript-step-1" data-step="1">

				<div class="trustscript-form-group">
					<label class="trustscript-form-label">
						<?php echo esc_html( $trustscript_str['label_rating'] ); ?>
						<span class="trustscript-required">*</span>
					</label>
					<div class="trustscript-star-selector"
						id="trustscript-star-selector"
						role="radiogroup"
						aria-label="<?php echo esc_attr( $trustscript_str['label_rating'] ); ?>">
						<?php for ( $trustscript_i = 1; $trustscript_i <= 5; $trustscript_i++ ) : ?>
							<button type="button"
									class="trustscript-star-btn"
									data-rating="<?php echo esc_attr( $trustscript_i ); ?>"
									aria-label="<?php echo esc_attr( $trustscript_star_labels[ $trustscript_i ] ); ?>">
								<svg viewBox="0 0 24 24" width="32" height="32"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
							</button>
						<?php endfor; ?>
					</div>
					<input type="hidden" name="rating" id="trustscript-rating-input" value="">
					<div class="trustscript-field-error" id="trustscript-rating-error"></div>
				</div>

				<div class="trustscript-form-group">
					<label for="trustscript-review-text" class="trustscript-form-label">
						<?php echo esc_html( $trustscript_str['label_review'] ); ?>
						<span class="trustscript-required">*</span>
					</label>
					<textarea id="trustscript-review-text"
							name="review_text"
							class="trustscript-form-textarea"
							rows="4"
							minlength="<?php echo esc_attr( $trustscript_min_text_length ); ?>"
							maxlength="<?php echo esc_attr( $trustscript_max_text_length ); ?>"
							placeholder="<?php echo esc_attr( $trustscript_str['review_textarea'] ); ?>"></textarea>
					<div class="trustscript-char-count">
						<span id="trustscript-char-current">0</span> / <?php echo esc_html( $trustscript_max_text_length ); ?>
					</div>
					<div class="trustscript-field-error" id="trustscript-text-error"></div>
				</div>

				<?php if ( ! $trustscript_is_logged_in ) : ?>
					<div class="trustscript-form-group">
						<label for="trustscript-reviewer-name" class="trustscript-form-label">
							<?php echo esc_html( $trustscript_str['label_name'] ); ?>
							<span class="trustscript-required">*</span>
						</label>
						<input type="text"
							id="trustscript-reviewer-name"
							name="reviewer_name"
							class="trustscript-form-input"
							required>
						<div class="trustscript-field-error" id="trustscript-name-error"></div>
					</div>
					<div class="trustscript-form-group">
						<label for="trustscript-reviewer-email" class="trustscript-form-label">
							<?php echo esc_html( $trustscript_str['label_email'] ); ?>
							<span class="trustscript-required">*</span>
						</label>
						<input type="email"
							id="trustscript-reviewer-email"
							name="reviewer_email"
							class="trustscript-form-input"
							required>
						<p class="trustscript-field-hint"><?php echo esc_html( $trustscript_str['email_hint'] ); ?></p>
						<div class="trustscript-field-error" id="trustscript-email-error"></div>
					</div>
				<?php elseif ( $trustscript_needs_name ) : ?>
					<div class="trustscript-form-group">
						<label for="trustscript-reviewer-name" class="trustscript-form-label">
							<?php echo esc_html( $trustscript_str['label_name'] ); ?>
							<span class="trustscript-required">*</span>
						</label>
						<input type="text"
							id="trustscript-reviewer-name"
							name="reviewer_name"
							class="trustscript-form-input"
							required>
						<div class="trustscript-field-error" id="trustscript-name-error"></div>
					</div>
				<?php endif; ?>

				<div style="position:absolute;left:-9999px;" aria-hidden="true">
					<input type="text" name="trustscript_hp" tabindex="-1" autocomplete="off" value="">
				</div>

				<div class="trustscript-form-actions">
					<?php if ( $trustscript_is_verified_buyer ) : ?>
					<button type="button" class="trustscript-btn-next" id="trustscript-step-next">
						<?php echo esc_html( $trustscript_str['btn_next'] ); ?>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
						</svg>
					</button>
					<?php else : ?>
					<button type="submit" class="trustscript-btn-next" id="trustscript-submit-review-direct">
						<span class="trustscript-btn-text"><?php echo esc_html( $trustscript_str['btn_submit'] ); ?></span>
						<span class="trustscript-btn-loading" style="display:none;">
							<svg class="trustscript-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<circle cx="12" cy="12" r="10" opacity=".25"/>
								<path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
							</svg>
							<?php echo esc_html( $trustscript_str['btn_submitting'] ); ?>
						</span>
					</button>
					<?php endif; ?>
				</div>

			</div>

			<?php if ( $trustscript_is_verified_buyer ) : ?>
			<div class="trustscript-review-step" id="trustscript-step-2" data-step="2" style="display:none;">

				<div class="trustscript-form-group">
					<label class="trustscript-form-label">
						<?php echo esc_html( $trustscript_str['label_photos'] ); ?>
						<span class="trustscript-optional">(<?php echo esc_html( $trustscript_str['optional'] ); ?>)</span>
					</label>
					<p class="trustscript-field-hint"><?php echo esc_html( $trustscript_str['photo_hint'] ); ?></p>
					<div class="trustscript-modal-upload-zone" id="trustscript-upload-zone">
						<div class="trustscript-upload-placeholder" id="trustscript-upload-placeholder">
							<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
								<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
								<circle cx="8.5" cy="8.5" r="1.5"/>
								<polyline points="21 15 16 10 5 21"/>
							</svg>
							<span><?php echo esc_html( $trustscript_str['drag_drop'] ); ?></span>
						</div>
						<input type="file"
							id="trustscript-photo-input"
							name="photos[]"
							accept="image/jpeg,image/png,image/webp"
							multiple
							class="trustscript-photo-input">
					</div>
					<div class="trustscript-upload-previews" id="trustscript-upload-previews"></div>
					<div class="trustscript-field-error" id="trustscript-photo-error"></div>
				</div>

				<div class="trustscript-form-actions trustscript-form-actions-split">
					<button type="button" class="trustscript-btn-back" id="trustscript-step-back">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>
						</svg>
						<?php echo esc_html( $trustscript_str['btn_back'] ); ?>
					</button>
					<button type="submit" class="trustscript-btn-next" id="trustscript-submit-review">
						<span class="trustscript-btn-text"><?php echo esc_html( $trustscript_str['btn_submit'] ); ?></span>
						<span class="trustscript-btn-loading" style="display:none;">
							<svg class="trustscript-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<circle cx="12" cy="12" r="10" opacity=".25"/>
								<path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
							</svg>
							<?php echo esc_html( $trustscript_str['btn_submitting'] ); ?>
						</span>
					</button>
				</div>

			</div>
			<?php endif; ?>

		</form>
	</div>
</div>
