<?php
/**
 * TrustScript Simple Review — Bootstrapper and frontend renderer for the simple review feature.
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrustScript_Simple_Review {

	const MAX_PHOTOS        = 3;
	const MAX_PHOTO_SIZE    = 5242880; // 5 MB
	const MIN_TEXT_LENGTH   = 10;
	const MAX_TEXT_LENGTH   = 5000;
	const ALLOWED_MIMES     = array( 'image/jpeg', 'image/png', 'image/webp' );
	const RATE_LIMIT_WINDOW = 300;
	const RATE_LIMIT_MAX    = 10;

	/**
	 * Initialize hooks for the simple review feature.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function boot() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_form_assets' ) );
	}

	/**
	 * Check if the simple review feature is enabled via settings.
	 * 
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_enabled() {
		$simple = (bool) get_option( 'trustscript_simple_review_enabled', true );
		$api    = (bool) get_option( 'trustscript_api_review_collection_enabled', false );
		return $simple || $api;
	}

	/**
	 * Enqueue CSS and JS assets for the review form, only if the feature is enabled.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function enqueue_form_assets() {
		if ( ! self::is_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'trustscript-review-form',
			TRUSTSCRIPT_PLUGIN_URL . 'assets/css/trustscript-review-form.css',
			array(),
			TRUSTSCRIPT_VERSION
		);

		wp_enqueue_script(
			'trustscript-review-form',
			TRUSTSCRIPT_PLUGIN_URL . 'assets/js/trustscript-review-form.js',
			array(),
			TRUSTSCRIPT_VERSION,
			true
		);

		wp_localize_script(
			'trustscript-review-form',
			'trustscriptConfig',
			array(
				'rest_url'      => esc_url_raw( rest_url() ),
				'wp_rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'is_logged_in'  => is_user_logged_in(),
			)
		);

		wp_localize_script(
			'trustscript-review-form',
			'trustscriptStrings',
			self::get_js_strings()
		);
	}

	/**
	 * Get localized strings for JavaScript usage in the review form, including validation errors and UI text.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_js_strings() {
		return array(
			'error_rating'     => esc_html__( 'Please select a star rating.', 'trustscript' ),
			'error_text_short' => sprintf(
				/* translators: %d: minimum number of characters required for a review. */
				esc_html__( 'Review must be at least %d characters.', 'trustscript' ),
				self::MIN_TEXT_LENGTH
			),
			'error_text_long'  => esc_html__( 'Review is too long.', 'trustscript' ),
			'error_name'       => esc_html__( 'Please enter your name.', 'trustscript' ),
			'error_email'      => esc_html__( 'Please enter a valid email address.', 'trustscript' ),
			'error_photo_size' => esc_html__( 'Photo exceeds 5MB limit and was not added.', 'trustscript' ),
			'error_photo_type' => esc_html__( 'Only JPEG, PNG, and WebP photos are allowed.', 'trustscript' ),
			'error_generic'    => esc_html__( 'Something went wrong. Please try again.', 'trustscript' ),
			'success'          => esc_html__( 'Thank you! Your review has been submitted.', 'trustscript' ),
			'moderation'       => esc_html__( 'Thank you! Your review is awaiting moderation.', 'trustscript' ),
			'star_hover_1'     => esc_html__( 'Not what I expected', 'trustscript' ),
			'star_hover_2'     => esc_html__( 'Could be better', 'trustscript' ),
			'star_hover_3'     => esc_html__( 'It\'s okay overall', 'trustscript' ),
			'star_hover_4'     => esc_html__( 'I\'m really happy with it', 'trustscript' ),
			'star_hover_5'     => esc_html__( 'I absolutely love it', 'trustscript' ),
			'btn_write_review' => esc_html__( 'Write Review', 'trustscript' ),
		);
	}

	/**
	 * Render the "Write a Review" trigger button for a product page.
	 *
	 * @param int $product_id
	 * @return string HTML string.
	 */
	public static function render_write_review_button( $product_id ) {
		if ( ! self::is_enabled() ) {
			return '';
		}

		$already_reviewed = false;
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$existing     = get_comments(
				array(
					'post_id'      => $product_id,
					'author_email' => $current_user->user_email,
					'type'         => 'review',
					'status'       => 'any',
					'number'       => 1,
				)
			);
			if ( ! empty( $existing ) ) {
				$already_reviewed = true;
			}
		}

		ob_start();
		?>
		<div class="trustscript-review-trigger">
			<button type="button" class="trustscript-btn-next" id="trustscript-write-review-btn"
					data-product-id="<?php echo esc_attr( $product_id ); ?>"
					<?php
					if ( $already_reviewed ) {
						echo 'data-already-reviewed="1"';}
					?>
					>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
				<?php esc_html_e( 'Write a Review', 'trustscript' ); ?>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the review form modal HTML, including localized strings and user state (logged in or not).
	 *
	 * @return string HTML string, empty string if reviews disabled.
	 */
	public static function render_review_form_modal( $product_id = 0 ) {
		if ( ! self::is_enabled() ) {
			return '';
		}

		if ( ! $product_id && function_exists( 'is_product' ) && is_product() ) {
			$product_id = get_the_ID();
		}

		$is_logged_in = is_user_logged_in();
		$needs_name   = true;
		$is_verified_buyer = false;

		if ( $is_logged_in ) {
			$user       = wp_get_current_user();
			$needs_name = empty( trim( $user->display_name ) );
			
			if ( $product_id && function_exists( 'wc_customer_bought_product' ) ) {
				$is_verified_buyer = wc_customer_bought_product( $user->user_email, $user->ID, $product_id );
			}
		}

		$str = array(
			'dialog_label'    => esc_html__( 'Write a Review', 'trustscript' ),
			'heading'         => esc_html__( 'Write a Review', 'trustscript' ),
			'close'           => esc_html__( 'Close', 'trustscript' ),
			'label_rating'    => esc_html__( 'Your Rating', 'trustscript' ),
			'label_review'    => esc_html__( 'Your Review', 'trustscript' ),
			'label_name'      => esc_html__( 'Your Name', 'trustscript' ),
			'label_email'     => esc_html__( 'Your Email', 'trustscript' ),
			'label_photos'    => esc_html__( 'Add Photos', 'trustscript' ),
			'optional'        => esc_html__( 'optional', 'trustscript' ),
			'email_hint'      => esc_html__( 'Your email will not be published.', 'trustscript' ),
			'drag_drop'       => esc_html__( 'Drag & drop photos here or click to browse', 'trustscript' ),
			'btn_next'        => esc_html__( 'Next: Add Photos', 'trustscript' ),
			'btn_back'        => esc_html__( 'Back', 'trustscript' ),
			'btn_submit'      => esc_html__( 'Submit Review', 'trustscript' ),
			'btn_submitting'  => esc_html__( 'Submitting...', 'trustscript' ),
			'review_textarea' => esc_html__( 'Share your experience with this product...', 'trustscript' ),
			'photo_hint'      => sprintf(
				/* translators: %d: maximum number of photos allowed */
				esc_html__( 'Up to %d photos (JPEG, PNG, WebP, max 5MB each)', 'trustscript' ),
				self::MAX_PHOTOS
			),
		);

		$star_labels = array(
			1 => esc_html__( '1 star', 'trustscript' ),
			2 => esc_html__( '2 stars', 'trustscript' ),
			3 => esc_html__( '3 stars', 'trustscript' ),
			4 => esc_html__( '4 stars', 'trustscript' ),
			5 => esc_html__( '5 stars', 'trustscript' ),
		);

		return TrustScript_Template_Loader::capture(
			'modal-form',
			array(
				'is_logged_in'      => $is_logged_in,
				'needs_name'        => $needs_name,
				'is_verified_buyer' => $is_verified_buyer,
				'str'               => $str,
				'star_labels'       => $star_labels,
				'min_text_length'   => self::MIN_TEXT_LENGTH,
				'max_text_length'   => self::MAX_TEXT_LENGTH,
				'max_photos'        => self::MAX_PHOTOS,
			)
		);
	}
}
