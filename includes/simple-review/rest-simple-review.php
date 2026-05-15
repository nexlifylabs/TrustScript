<?php
/**
 * TrustScript Simple Review REST Controller
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrustScript_Rest_Simple_Review {

	const MAX_PHOTOS           = 3;
	const MAX_PHOTO_SIZE       = 5242880;
	const MIN_TEXT_LENGTH      = 10;
	const MAX_TEXT_LENGTH      = 5000;
	const ALLOWED_MIMES        = array( 'image/jpeg', 'image/png', 'image/webp' );
	const RATE_LIMIT_WINDOW    = 300;
	const RATE_LIMIT_MAX       = 15;
	const TOKEN_EXPIRY_SECONDS = 2592000;

	/**
	 * Initialize REST API routes for the simple review feature.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes for handling simple review submissions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'trustscript/v1',
			'/submit-simple-review',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_submission' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return $param > 0;
						},
					),
					'rating'         => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $param ) {
							return $param >= 1 && $param <= 5;
						},
					),
					'review_text'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => function ( $param ) {
							$len = mb_strlen( $param );
							return $len >= self::MIN_TEXT_LENGTH && $len <= self::MAX_TEXT_LENGTH;
						},
					),
					'review_token'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'reviewer_name'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'reviewer_email' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function ( $param ) {
							return empty( $param ) || is_email( $param );
						},
					),
					'comment_id'     => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'trustscript_hp' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Handle incoming review submission requests, including validation, comment creation/updating, and response generation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_submission( WP_REST_Request $request ) {

		// Nonce verification.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'invalid_nonce',
				__( 'Security check failed. Please refresh and try again.', 'trustscript' ),
				array( 'status' => 403 )
			);
		}

		if ( ! self::is_enabled() ) {
			return new WP_Error(
				'disabled',
				__( 'On-site reviews are currently disabled.', 'trustscript' ),
				array( 'status' => 403 )
			);
		}

		$honeypot = $request->get_param( 'trustscript_hp' );
		if ( ! empty( $honeypot ) ) {
			return new WP_Error(
				'spam',
				__( 'Submission blocked.', 'trustscript' ),
				array( 'status' => 403 )
			);
		}

		if ( ! self::check_rate_limit() ) {
			return new WP_Error(
				'rate_limited',
				__( 'Too many submissions. Please wait a few minutes and try again.', 'trustscript' ),
				array( 'status' => 429 )
			);
		}

		$product_id  = absint( $request->get_param( 'product_id' ) );
		$rating      = absint( $request->get_param( 'rating' ) );
		$review_text = sanitize_textarea_field( wp_unslash( $request->get_param( 'review_text' ) ) );
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return new WP_Error(
				'invalid_product',
				__( 'Product not found.', 'trustscript' ),
				array( 'status' => 400 )
			);
		}

		if ( $product->get_parent_id() > 0 ) {
			$product_id = $product->get_parent_id();
			$product    = wc_get_product( $product_id );
			if ( ! $product ) {
				return new WP_Error(
					'invalid_product',
					__( 'Product not found.', 'trustscript' ),
					array( 'status' => 400 )
				);
			}
		}

		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error(
				'invalid_rating',
				__( 'Please select a rating between 1 and 5.', 'trustscript' ),
				array( 'status' => 400 )
			);
		}

		$text_len = mb_strlen( $review_text );
		if ( $text_len < self::MIN_TEXT_LENGTH ) {
			return new WP_Error(
				'review_too_short',
				sprintf(
					/* translators: %d: minimum number of characters required for a review. */
					__( 'Review must be at least %d characters.', 'trustscript' ),
					self::MIN_TEXT_LENGTH
				),
				array( 'status' => 400 )
			);
		}
		if ( $text_len > self::MAX_TEXT_LENGTH ) {
			return new WP_Error(
				'review_too_long',
				sprintf(
					/* translators: %d is the maximum number of characters allowed for a review. */
					__( 'Review must be no more than %d characters.', 'trustscript' ),
					self::MAX_TEXT_LENGTH
				),
				array( 'status' => 400 )
			);
		}

		$review_token   = sanitize_text_field( $request->get_param( 'review_token' ) );
		$token_order    = null;
		$is_logged_in   = is_user_logged_in();
		$reviewer_name  = '';
		$reviewer_email = '';
		$user_id        = 0;
		$is_token_flow  = false;

		if ( ! empty( $review_token ) ) {
			$result = self::validate_token_submission(
				$review_token,
				$request,
				$product_id,
				$is_logged_in
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$token_order    = $result['order'];
			$reviewer_name  = $result['name'];
			$reviewer_email = $result['email'];
			$user_id        = $result['user_id'];
			$is_token_flow  = true;

		} elseif ( $is_logged_in ) {
			$user           = wp_get_current_user();
			$user_id        = $user->ID;
			$reviewer_email = $user->user_email;
			$reviewer_name  = $user->display_name;

			if ( empty( trim( $reviewer_name ) ) ) {
				$reviewer_name = sanitize_text_field( wp_unslash( $request->get_param( 'reviewer_name' ) ) );
				if ( empty( $reviewer_name ) ) {
					return new WP_Error(
						'missing_name',
						__( 'Please enter your name.', 'trustscript' ),
						array( 'status' => 400 )
					);
				}
			}
		} else {
			$reviewer_name  = sanitize_text_field( wp_unslash( $request->get_param( 'reviewer_name' ) ) );
			$reviewer_email = sanitize_email( wp_unslash( $request->get_param( 'reviewer_email' ) ) );

			if ( empty( $reviewer_name ) ) {
				return new WP_Error(
					'missing_name',
					__( 'Please enter your name.', 'trustscript' ),
					array( 'status' => 400 )
				);
			}
			if ( empty( $reviewer_email ) || ! is_email( $reviewer_email ) ) {
				return new WP_Error(
					'invalid_email',
					__( 'Please enter a valid email address.', 'trustscript' ),
					array( 'status' => 400 )
				);
			}
		}

		$edit_comment_id = absint( $request->get_param( 'comment_id' ) );
		$is_edit         = false;
		$comment_to_edit = null;

		if ( $edit_comment_id > 0 && $is_logged_in ) {
			$comment_to_edit = get_comment( $edit_comment_id );
			if ( $comment_to_edit && (int) $comment_to_edit->user_id === $user_id && (int) $comment_to_edit->comment_post_ID === $product_id ) {
				$is_edit = true;
			} else {
				return new WP_Error(
					'unauthorized_edit',
					__( 'You do not have permission to edit this review.', 'trustscript' ),
					array( 'status' => 403 )
				);
			}
		}

		if ( ! $is_token_flow && ! $is_edit ) {
			$duplicate_error = self::check_duplicate_review( $product_id, $reviewer_email );
			if ( is_wp_error( $duplicate_error ) ) {
				return $duplicate_error;
			}
		}

		$is_verified = $is_token_flow ? true : self::check_verified_purchase( $reviewer_email, $product_id );

		$auto_publish   = get_option( 'trustscript_auto_publish', false );
		$comment_status = ( $auto_publish && $is_verified ) ? 1 : 0;
		if ( $is_edit ) {
			$comment_status = 0;
		}

		if ( $is_token_flow ) {
			$lock_key = 'trustscript_submit_lock_' . md5( $review_token . '_' . $product_id );
			if ( get_transient( $lock_key ) ) {
				return new WP_Error(
					'already_submitted',
					__( 'Your review is already being processed.', 'trustscript' ),
					array( 'status' => 409 )
				);
			}
			set_transient( $lock_key, 1, 60 );
		}

		if ( $is_edit ) {
			$comment_id = self::update_review_comment(
				$edit_comment_id,
				$product_id,
				$review_text
			);
		} else {
			$comment_id = self::create_review_comment(
				$product_id,
				$reviewer_name,
				$reviewer_email,
				$review_text,
				$comment_status,
				$user_id
			);
		}

		if ( ! $comment_id ) {
			return new WP_Error(
				'insert_failed',
				__( 'Could not save your review. Please try again.', 'trustscript' ),
				array( 'status' => 500 )
			);
		}

		if ( ! $is_edit && class_exists( 'TrustScript_Review_Guard' ) ) {
			$_inserted_comment = get_comment( $comment_id );
			if ( $_inserted_comment ) {
				$comment_status = (int) $_inserted_comment->comment_approved;
			}
		}

		update_comment_meta( $comment_id, 'rating', $rating );
		update_comment_meta( $comment_id, '_trustscript_rating', $rating );
		update_comment_meta( $comment_id, '_trustscript_simple_review', '1' );

		if ( $is_verified ) {
			update_comment_meta( $comment_id, '_trustscript_verified_purchase', '1' );
			update_comment_meta( $comment_id, 'verified', 1 );
		}

		if ( $is_token_flow && $token_order ) {
			self::update_token_order_state(
				$token_order,
				$product_id,
				$reviewer_email
			);
		}

		$media_urls = self::handle_photo_uploads( $comment_id );
		if ( ! empty( $media_urls ) ) {
			if ( $is_edit ) {
				$existing_media_json = get_comment_meta( $comment_id, '_trustscript_media_urls', true );
				$existing_media      = ! empty( $existing_media_json ) ? json_decode( $existing_media_json, true ) : array();
				if ( ! is_array( $existing_media ) ) {
					$existing_media = array();
				}
				$media_urls = array_merge( $existing_media, $media_urls );
			}
			update_comment_meta( $comment_id, '_trustscript_media_urls', wp_json_encode( $media_urls ) );
		}

		if ( class_exists( 'TrustScript_Review_Renderer' ) ) {
			TrustScript_Review_Renderer::flush_stats_cache( $product_id );
		}
		if ( class_exists( 'TrustScript_Shop_Display' ) ) {
			TrustScript_Shop_Display::clear_product_rating_cache( $product_id );
		}

		if ( ! $is_token_flow && function_exists( 'wc_get_orders' ) && ! empty( $reviewer_email ) ) {
			self::update_matching_orders( $reviewer_email, $product_id );
		}

		$response = array(
			'success'    => true,
			'comment_id' => $comment_id,
			'approved'   => (bool) $comment_status,
			'message'    => $is_edit
				? __( 'Thank you! Your review is awaiting moderation.', 'trustscript' )
				: ( $comment_status
					? __( 'Thank you! Your review has been published.', 'trustscript' )
					: __( 'Thank you! Your review is awaiting moderation.', 'trustscript' ) ),
		);

		if ( $comment_status && class_exists( 'TrustScript_Review_Renderer' ) ) {
			$comment                    = get_comment( $comment_id );
			$comment->rating            = $rating;
			$comment->verified_purchase = $is_verified;
			$comment->media_urls        = $media_urls;
			$comment->helpful_yes       = 0;
			$comment->helpful_no        = 0;
			$comment->user_vote         = false;
			$comment->review_title      = '';

			$response['html'] = TrustScript_Review_Renderer::render_card(
				$comment,
				array(
					'show_stars'          => true,
					'show_verification'   => true,
					'show_verified_label' => true,
					'show_voting'         => (bool) get_option( 'trustscript_enable_voting', false ),
					'date_format'         => 'full',
					'excerpt_length'      => 0,
				)
			);
		}

		if ( $is_token_flow && $token_order ) {
			$remaining_products = self::get_remaining_products_in_order(
				$token_order,
				$product_id,
				$reviewer_email
			);
			if ( ! empty( $remaining_products ) ) {
				$response['next_products'] = $remaining_products;
			}
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Is the simple review feature enabled via settings?
	 *
	 * @return bool
	 */
	private static function is_enabled() {
		$simple = (bool) get_option( 'trustscript_simple_review_enabled', true );
		$api    = (bool) get_option( 'trustscript_api_review_collection_enabled', false );
		return $simple || $api;
	}

	/**
	 * Rate limit check based on IP address.
	 *
	 * @return bool True if allowed, false if rate-limited.
	 */
	private static function check_rate_limit() {
		$ip    = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
		$key   = 'trustscript_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_MAX ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );
		return true;
	}

	/**
	 * Validate token-based submission (email flow).
	 *
	 * @param string          $review_token    The review token.
	 * @param WP_REST_Request $request         The request object.
	 * @param int             $product_id      The product ID.
	 * @param bool            $is_logged_in    Whether user is logged in.
	 * @return array|WP_Error Array with order/reviewer data or error.
	 */
	private static function validate_token_submission(
		$review_token,
		$request,
		$product_id,
		$is_logged_in
	) {
		if ( ! class_exists( 'TrustScript_Simple_Email_Review' ) ) {
			return new WP_Error(
				'invalid_token',
				__( 'Invalid review token.', 'trustscript' ),
				array( 'status' => 403 )
			);
		}

		$token_order = TrustScript_Simple_Email_Review::get_order_by_token( $review_token );
		if ( ! $token_order ) {
			return new WP_Error(
				'invalid_token',
				__( 'Invalid or expired review token.', 'trustscript' ),
				array( 'status' => 403 )
			);
		}

		if ( class_exists( 'TrustScript_Review_Requests' ) && TrustScript_Review_Requests::is_cancelled( $token_order->get_id(), $product_id ) ) {
			return new WP_Error(
				'order_cancelled',
				__( 'This product order has been refunded or cancelled. Review submission is no longer available.', 'trustscript' ),
				array( 'status' => 403 )
			);
		}

		$created = (int) $token_order->get_meta( '_trustscript_simple_review_token_created' );
		if ( $created > 0 && ( time() - $created ) > self::TOKEN_EXPIRY_SECONDS ) {
			return new WP_Error(
				'token_expired',
				__( 'This review link has expired.', 'trustscript' ),
				array( 'status' => 403 )
			);
		}

		$billing_email = $token_order->get_billing_email();
		$already_done  = get_comments(
			array(
				'post_id'      => $product_id,
				'author_email' => $billing_email,
				'type'         => 'review',
				'status'       => 'any',
				'number'       => 1,
			)
		);
		if ( ! empty( $already_done ) ) {
			return new WP_Error(
				'already_submitted',
				__( 'You have already reviewed this product.', 'trustscript' ),
				array( 'status' => 409 )
			);
		}

		if ( ! $is_logged_in ) {
			return new WP_Error(
				'login_required',
				__( 'You must be logged in to submit a review.', 'trustscript' ),
				array( 'status' => 403 )
			);
		}

		$current_user = wp_get_current_user();
		$customer_id  = (int) $token_order->get_customer_id();

		if ( $customer_id > 0 ) {
			if ( $current_user->ID !== $customer_id ) {
				return new WP_Error(
					'user_mismatch',
					__( 'This review link is not valid for your account.', 'trustscript' ),
					array( 'status' => 403 )
				);
			}
		} elseif ( strcasecmp( $current_user->user_email, $token_order->get_billing_email() ) !== 0 ) {
				return new WP_Error(
					'user_mismatch',
					__( 'This review link is not valid for your account.', 'trustscript' ),
					array( 'status' => 403 )
				);
		}

		$product_in_order = false;
		foreach ( $token_order->get_items() as $item ) {
			$parent_match    = absint( $item->get_product_id() ) === $product_id;
			$variation_match = $item->get_variation_id() > 0 && absint( $item->get_variation_id() ) === $product_id;
			if ( $parent_match || $variation_match ) {
				$product_in_order = true;
				break;
			}
		}
		if ( ! $product_in_order ) {
			return new WP_Error(
				'invalid_product',
				__( 'This product is not in your order.', 'trustscript' ),
				array( 'status' => 400 )
			);
		}

		$reviewer_name  = trim( $token_order->get_billing_first_name() . ' ' . $token_order->get_billing_last_name() );
		$reviewer_email = $token_order->get_billing_email();

		return array(
			'order'   => $token_order,
			'name'    => $reviewer_name,
			'email'   => $reviewer_email,
			'user_id' => $current_user->ID,
		);
	}

	/**
	 * Check for duplicate reviews by the same email for the same product.
	 *
	 * @param int    $product_id      The product ID.
	 * @param string $reviewer_email  The reviewer's email.
	 * @return true|WP_Error True if no duplicate, WP_Error if duplicate found.
	 */
	private static function check_duplicate_review( $product_id, $reviewer_email ) {
		$existing = get_comments(
			array(
				'post_id'      => $product_id,
				'author_email' => $reviewer_email,
				'type'         => 'review',
				'status'       => 'any',
				'number'       => 1,
			)
		);

		if ( empty( $existing ) ) {
			$existing = get_comments(
				array(
					'post_id'      => $product_id,
					'author_email' => $reviewer_email,
					'type'         => '',
					'status'       => 'any',
					'number'       => 1,
				)
			);
		}

		if ( ! empty( $existing ) ) {
			return new WP_Error(
				'duplicate_review',
				__( 'You have already reviewed this product.', 'trustscript' ),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/**
	 * Check if the reviewer has a verified purchase for the product.
	 *
	 * @param string $email       The reviewer's email.
	 * @param int    $product_id  The product ID.
	 * @return bool True if verified purchase found.
	 */
	private static function check_verified_purchase( $email, $product_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}

		$orders = wc_get_orders(
			array(
				'billing_email' => $email,
				'status'        => array( 'wc-completed', 'wc-processing', 'wc-delivered' ),
				'limit'         => 10,
				'return'        => 'ids',
			)
		);

		if ( empty( $orders ) ) {
			return false;
		}

		foreach ( $orders as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order ) {
				continue;
			}

			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === (int) $product_id ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Create a new review comment in WordPress.
	 *
	 * Passes the comment data through TrustScript_Review_Guard before inserting,
	 * which may override comment_approved to 0 (hold) or 'spam' depending on
	 * WordPress moderation_keys / disallowed_keys and the TrustScript keyword
	 * blocklist. If no word-list match is found, the caller-supplied
	 * $comment_status (driven by trustscript_auto_publish) is preserved.
	 *
	 * @param int    $product_id      Product ID.
	 * @param string $reviewer_name   Reviewer name.
	 * @param string $reviewer_email  Reviewer email.
	 * @param string $review_text     Review text.
	 * @param int    $comment_status  Initial approval status (0 or 1) from auto-publish setting.
	 * @param int    $user_id         WordPress user ID (0 for guests).
	 * @return int|false Comment ID or false on failure.
	 */
	private static function create_review_comment(
		$product_id,
		$reviewer_name,
		$reviewer_email,
		$review_text,
		$comment_status,
		$user_id
	) {
		$comment_data = array(
			'comment_post_ID'      => $product_id,
			'comment_author'       => $reviewer_name,
			'comment_author_email' => $reviewer_email,
			'comment_content'      => $review_text,
			'comment_type'         => 'review',
			'comment_approved'     => $comment_status,
			'user_id'              => $user_id,
		);

		if ( class_exists( 'TrustScript_Review_Guard' ) ) {
			$comment_data = TrustScript_Review_Guard::apply_to_comment_data( $comment_data );
		}

		$comment_id = wp_insert_comment( $comment_data );
		return $comment_id ?: false;
	}

	/**
	 * Update an existing review comment in WordPress.
	 *
	 * @param int    $comment_id  Comment ID to update.
	 * @param int    $product_id  Product ID (for validation).
	 * @param string $review_text New review text.
	 * @return int|false Updated comment ID or false.
	 */
	private static function update_review_comment(
		$comment_id,
		$product_id,
		$review_text
	) {
		$pending_edits = get_comments(
			array(
				'post_id'    => $product_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'   => '_trustscript_edit_of',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value' => $comment_id,
				'status'     => 'hold',
				'number'     => 1,
			)
		);

		if ( ! empty( $pending_edits ) ) {
			$edit_comment_id = $pending_edits[0]->comment_ID;

			// Run moderation check on updated text — may escalate from hold (0) to spam.
			$edit_approved = 0;
			if ( class_exists( 'TrustScript_Review_Guard' ) ) {
				$guard_result  = TrustScript_Review_Guard::apply_to_comment_data(
					array(
						'comment_author'       => $pending_edits[0]->comment_author,
						'comment_author_email' => $pending_edits[0]->comment_author_email,
						'comment_content'      => $review_text,
						'comment_approved'     => 0,
					)
				);
				$edit_approved = $guard_result['comment_approved'];
			}

			wp_update_comment(
				array(
					'comment_ID'       => $edit_comment_id,
					'comment_content'  => $review_text,
					'comment_approved' => $edit_approved,
				)
			);

			if ( get_option( 'moderation_notify' ) ) {
				wp_notify_moderator( $edit_comment_id );
			}

			return $edit_comment_id;
		} else {
			$original  = get_comment( $comment_id );
			$edit_data = array(
				'comment_post_ID'      => $product_id,
				'comment_author'       => $original ? $original->comment_author : '',
				'comment_author_email' => $original ? $original->comment_author_email : '',
				'comment_content'      => $review_text,
				'comment_type'         => 'review',
				'comment_approved'     => 0,
				'user_id'              => $original ? (int) $original->user_id : 0,
			);

			// Run moderation check on the new shadow comment — may escalate to spam.
			if ( class_exists( 'TrustScript_Review_Guard' ) ) {
				$edit_data = TrustScript_Review_Guard::apply_to_comment_data( $edit_data );
			}

			$edit_comment_id = wp_insert_comment( $edit_data );

			if ( $edit_comment_id ) {
				update_comment_meta( $edit_comment_id, '_trustscript_edit_of', $comment_id );
				
				if ( get_option( 'moderation_notify' ) ) {
					wp_notify_moderator( $edit_comment_id );
				}
			}

			return $edit_comment_id ?: false;
		}
	}

	/**
	 * Update token order state after a review submission. 
	 *
	 * @param WC_Order $token_order    The order object.
	 * @param int      $product_id     The product ID just reviewed.
	 * @param string   $reviewer_email The reviewer's email.
	 * @return void
	 */
	private static function update_token_order_state(
		$token_order,
		$product_id,
		$reviewer_email
	) {
		$all_done = true;
		foreach ( $token_order->get_items() as $item ) {
			$pid = absint( $item->get_product_id() );

			$reviewed = get_comments(
				array(
					'post_id'      => $pid,
					'author_email' => $reviewer_email,
					'type'         => 'review',
					'status'       => 'any',
					'number'       => 1,
				)
			);

			if ( empty( $reviewed ) ) {
				$all_done = false;
				break;
			}
		}

		$token_order->update_meta_data( '_trustscript_review_published', 'yes' );
		$token_order->update_meta_data( '_trustscript_review_published_at', current_time( 'mysql' ) );

		if ( $all_done ) {
			$token_order->update_meta_data( '_trustscript_simple_review_submitted', '1' );
			$token_order->update_meta_data( '_trustscript_simple_review_submitted_at', current_time( 'mysql' ) );
		}

		$token_order->save_meta_data();

		if ( class_exists( 'TrustScript_Review_Requests' ) ) {
			TrustScript_Review_Requests::upsert(
				$token_order->get_id(),
				$product_id,
				'published'
			);
		}
	}

	/**
	 * Update matching orders for a reviewer after a review submission, marking them as
	 * having a published review for the product.
	 *
	 * @param string $reviewer_email The reviewer's email.
	 * @param int    $product_id     The product ID just reviewed.
	 * @return void
	 */
	private static function update_matching_orders( $reviewer_email, $product_id ) {
		$matching_orders = wc_get_orders(
			array(
				'billing_email' => $reviewer_email,
				'limit'         => 10,
				'orderby'       => 'date',
				'order'         => 'DESC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'    => array(
					array(
						'key'   => '_trustscript_email_sent',
						'value' => '1',
					),
				),
			)
		);

		foreach ( $matching_orders as $match_order ) {
			$items = $match_order->get_items();
			foreach ( $items as $item ) {
				if ( absint( $item->get_product_id() ) === $product_id ) {
					$match_order->update_meta_data( '_trustscript_review_published', 'yes' );
					$match_order->update_meta_data( '_trustscript_review_published_at', current_time( 'mysql' ) );
					$match_order->save_meta_data();

					if ( class_exists( 'TrustScript_Review_Requests' ) ) {
						TrustScript_Review_Requests::upsert(
							$match_order->get_id(),
							$product_id,
							'published'
						);
					}

					break 2;
				}
			}
		}
	}

	/**
	 * Get remaining products in order that need reviewing.
	 *
	 * @param WC_Order $token_order    The order object.
	 * @param int      $product_id     The product ID just reviewed.
	 * @param string   $reviewer_email The reviewer's email.
	 * @return array Array of remaining product data for frontend.
	 */
	private static function get_remaining_products_in_order(
		$token_order,
		$product_id,
		$reviewer_email
	) {
		$remaining = array();
		$void_statuses = array( 'cancelled', 'refunded', 'failed' );
		if ( in_array( $token_order->get_status(), $void_statuses, true ) ) {
			return $remaining;
		}

		foreach ( $token_order->get_items() as $item ) {
			$pid = absint( $item->get_product_id() );

			if ( $pid === $product_id ) {
				continue; // Just reviewed.
			}

			if ( class_exists( 'TrustScript_Review_Requests' ) && TrustScript_Review_Requests::is_cancelled( $token_order->get_id(), $pid ) ) {
				continue;
			}

			$reviewed = get_comments(
				array(
					'post_id'      => $pid,
					'author_email' => $reviewer_email,
					'type'         => 'review',
					'status'       => 'any',
					'number'       => 1,
				)
			);

			if ( empty( $reviewed ) ) {
				$next_product = wc_get_product( $pid );
				if ( $next_product ) {
					$img_id  = $next_product->get_image_id();
					$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : wc_placeholder_img_src();

					$remaining[] = array(
						'productId'   => $pid,
						'productName' => $next_product->get_name(),
						'imageUrl'    => $img_url,
					);
				}
			}
		}

		return $remaining;
	}

	/**
	 * Handle photo uploads associated with a review comment. Validates and processes
	 * uploaded files, attaching them to the comment and returning their URLs.
	 *
	 * @param int $comment_id The comment ID associated with the photos.
	 * @return array Array of uploaded photo URLs.
	 */
	private static function handle_photo_uploads( $comment_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_submission
		if ( empty( $_FILES['photos'] ) ) {
			return array();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$photos = $_FILES['photos'];
		$urls   = array();

		if ( ! is_array( $photos['name'] ) ) {
			$photos = array(
				'name'     => array( $photos['name'] ),
				'type'     => array( $photos['type'] ),
				'tmp_name' => array( $photos['tmp_name'] ),
				'error'    => array( $photos['error'] ),
				'size'     => array( $photos['size'] ),
			);
		}

		$count      = min( count( $photos['name'] ), self::MAX_PHOTOS );
		$custom_dir = function ( $dirs ) {
			$dirs['subdir'] = '/trustscript-reviews';
			$dirs['path']   = $dirs['basedir'] . '/trustscript-reviews';
			$dirs['url']    = $dirs['baseurl'] . '/trustscript-reviews';
			return $dirs;
		};

		for ( $i = 0; $i < $count; $i++ ) {
			if ( $photos['error'][ $i ] !== UPLOAD_ERR_OK || $photos['size'][ $i ] > self::MAX_PHOTO_SIZE ) {
				continue;
			}

			$file_data = array(
				'name'     => $photos['name'][ $i ],
				'type'     => $photos['type'][ $i ],
				'tmp_name' => $photos['tmp_name'][ $i ],
				'error'    => $photos['error'][ $i ],
				'size'     => $photos['size'][ $i ],
			);

			$check = wp_check_filetype_and_ext( $file_data['tmp_name'], $file_data['name'] );
			if ( empty( $check['type'] ) || ! in_array( $check['type'], self::ALLOWED_MIMES, true ) ) {
				continue;
			}

			$overrides = array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'webp'     => 'image/webp',
				),
			);

			add_filter( 'upload_dir', $custom_dir );
			$uploaded = wp_handle_upload( $file_data, $overrides );
			remove_filter( 'upload_dir', $custom_dir );

			if ( isset( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
				continue;
			}

			$wp_ft     = wp_check_filetype( basename( $uploaded['file'] ), null );
			$attach_id = wp_insert_attachment(
				array(
					'post_mime_type' => $wp_ft['type'],
					'post_title'     => sprintf( 'TrustScript Review Photo - %d', $comment_id ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				),
				$uploaded['file']
			);

			if ( $attach_id ) {
				wp_update_attachment_metadata(
					$attach_id,
					wp_generate_attachment_metadata( $attach_id, $uploaded['file'] )
				);
			}

			$urls[] = $uploaded['url'];
		}

		return $urls;
	}
}