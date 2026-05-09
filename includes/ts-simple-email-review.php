<?php
/**
 * TrustScript_Simple_Email_Review class
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrustScript_Simple_Email_Review {

	// Token expiry time: 30 days (in seconds).
	const TOKEN_EXPIRY_SECONDS = 2592000;

	// Boot method to register hooks and shortcodes.
	public static function boot() {
		add_shortcode( 'trustscript_review_form', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
		add_action( 'wp_ajax_trustscript_preview_simple_email', array( __CLASS__, 'handle_preview_ajax' ) );
	}

	/**
	 * Get the list of placeholders for the simple email template.
	 * @return array
	 */
	public static function get_simple_email_placeholders() {
		return TrustScript_Simple_Email_Sender::get_simple_email_placeholders();
	}

	/**
	 * Get the default email subject for the simple email template.
	 * @return string
	 */
	public static function get_default_email_subject() {
		return TrustScript_Simple_Email_Sender::get_default_email_subject();
	}

	/**
	 * Get the default email body for the simple email template.
	 * @return string
	 */
	public static function get_default_email_body() {
		return TrustScript_Simple_Email_Sender::get_default_email_body();
	}

	/**
	 * Send a review request email for the given order ID.
	 * @param int $order_id
	 * @return bool
	 */
	public static function send_review_request( $order_id ) {
		return TrustScript_Simple_Email_Sender::send_review_request( $order_id );
	}

	/**
	 * Enqueue assets if the current page contains the review form shortcode.
	 * This method is safe to call on every page load and will only enqueue
	 * assets when necessary.
	 */
	public static function maybe_enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'trustscript_review_form' ) ) {
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

			if ( class_exists( 'TrustScript_Simple_Review' ) ) {
				wp_localize_script( 'trustscript-review-form', 'trustscriptStrings', TrustScript_Simple_Review::get_js_strings() );
			}
		}
	}

	/**
	 * Return a preview of the email with sample data substituted.
	 * Admin-only, nonce-checked.
	 */
	public static function handle_preview_ajax() {
		check_ajax_referer( 'trustscript_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'trustscript' ) ), 401 );
		}

		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$body    = wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) );

		$store_name  = get_bloginfo( 'name' );
		$store_url   = home_url();
		$page_id     = get_option( 'trustscript_review_page_id' );
		$review_page = $page_id ? get_permalink( $page_id ) : home_url( '/' );
		$preview_url = add_query_arg( 'trustscript_review_token', 'preview-token-example', $review_page );
		$opt_out_url = add_query_arg( 'trustscript_opt_out', '1', $preview_url );
		$order_total = function_exists( 'wc_price' ) ? wc_price( 49.99 ) : '$49.99';

		$search  = array_keys( TrustScript_Simple_Email_Sender::get_simple_email_placeholders() );
		$replace = array(
			'Jane Smith',
			'jane@example.com',
			'Sample Product',
			'#1001',
			wp_date( get_option( 'date_format' ) ),
			$order_total,
			$store_name,
			esc_url( $store_url ),
			esc_url( $preview_url ),
			esc_url( $opt_out_url ),
		);

		wp_send_json_success( array(
			'subject' => esc_html( str_replace( $search, $replace, $subject ) ),
			'body'    => wpautop( str_replace( $search, $replace, $body ) ),
		) );
	}

	/**
	 * Get the order associated with a given review token.
	 *
	 * @param string $token
	 * @return WC_Order|false
	 */
	public static function get_order_by_token( $token ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}
		$orders = wc_get_orders( array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'   => '_trustscript_simple_review_token',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value' => sanitize_text_field( $token ),
			'limit'      => 1,
		) );
		return ! empty( $orders ) ? $orders[0] : false;
	}

	/**
	 * Render the review form shortcode. Handles token validation, login gating, and form rendering.
	 *
	 * @return string HTML output.
	 */
	public static function render_shortcode() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token validated server-side below.
		$token = isset( $_GET['trustscript_review_token'] ) ? sanitize_text_field( wp_unslash( $_GET['trustscript_review_token'] ) ) : '';

		if ( empty( $token ) ) {
			return '<p class="trustscript-notice">' . esc_html__( 'No review token provided. Please use the link from your email.', 'trustscript' ) . '</p>';
		}

		$simple_review_enabled = get_option( 'trustscript_simple_review_enabled', true );
		if ( ! $simple_review_enabled ) {
			return '<p class="trustscript-notice">' . esc_html__( 'Review collection is currently disabled.', 'trustscript' ) . '</p>';
		}

		$order = self::get_order_by_token( $token );
		if ( ! $order ) {
			return '<p class="trustscript-notice">' . esc_html__( 'Invalid or expired review link.', 'trustscript' ) . '</p>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token validated server-side above.
		if ( isset( $_GET['trustscript_opt_out'] ) && '1' === $_GET['trustscript_opt_out'] ) {
			if ( class_exists( 'TrustScript_Opt_Out' ) ) {
				$email_hash = hash( 'sha256', strtolower( trim( $order->get_billing_email() ) ) );
				TrustScript_Opt_Out::record_opt_out( $email_hash );
				TrustScript_Opt_Out::backfill_pending_orders( $email_hash );
				
				if ( class_exists( 'TrustScript_Review_Requests' ) ) {
					TrustScript_Review_Requests::mark_by_order( $order->get_id(), 'opt-out' );
				}
				$order->update_meta_data( '_trustscript_customer_opted_out', '1' );
				$order->save_meta_data();
				
				return '<div class="trustscript-notice trustscript-success"><p>' . esc_html__( 'You have successfully opted out of future review requests.', 'trustscript' ) . '</p></div>';
			}
		}

		if ( class_exists( 'TrustScript_Review_Requests' ) ) {
			$has_active = false;
			foreach ( $order->get_items() as $item ) {
				$pid = absint( $item->get_product_id() );
				if ( $pid && ! TrustScript_Review_Requests::is_cancelled( $order->get_id(), $pid ) ) {
					$has_active = true;
					break;
				}
			}
			if ( ! $has_active ) {
				return '<p class="trustscript-notice">' . esc_html__( 'This order has been refunded or cancelled. Review requests are no longer available.', 'trustscript' ) . '</p>';
			}
		}

		$created = (int) $order->get_meta( '_trustscript_simple_review_token_created' );
		if ( $created > 0 && ( time() - $created ) > self::TOKEN_EXPIRY_SECONDS ) {
			return '<p class="trustscript-notice">' . esc_html__( 'This review link has expired. Please contact the store for a new one.', 'trustscript' ) . '</p>';
		}

		$already_submitted = $order->get_meta( '_trustscript_simple_review_submitted' );
		if ( $already_submitted ) {
			return '<p class="trustscript-notice">' . esc_html__( 'You have already reviewed all products in this order. Thank you!', 'trustscript' ) . '</p>';
		}

		$logged_in = is_user_logged_in();
		if ( ! $logged_in ) {
			return self::render_login_prompt( $token );
		}

		$current_user = wp_get_current_user();
		$customer_id  = (int) $order->get_customer_id();
		
		if ( $customer_id > 0 ) {
			if ( $current_user->ID !== $customer_id ) {
				return '<p class="trustscript-notice">' . esc_html__( 'This review link is not valid for your account.', 'trustscript' ) . '</p>';
			}
		} else {
			if ( strcasecmp( $current_user->user_email, $order->get_billing_email() ) !== 0 ) {
				return '<p class="trustscript-notice">' . esc_html__( 'This review link is not valid for your account. Please log in with the email address used for your order.', 'trustscript' ) . '</p>';
			}
		}

		$billing_email = $order->get_billing_email();
		$products      = array();
		foreach ( $order->get_items() as $item ) {
			$parent_id   = absint( $item->get_product_id() );
			$parent_prod = $parent_id ? wc_get_product( $parent_id ) : null;
			if ( ! $parent_prod ) {
				continue;
			}
			if ( class_exists( 'TrustScript_Review_Requests' ) && TrustScript_Review_Requests::is_cancelled( $order->get_id(), $parent_id ) ) {
				continue;
			}
			$existing = get_comments( array(
				'post_id'      => $parent_id,
				'author_email' => $billing_email,
				'type'         => 'review',
				'status'       => 'any',
				'number'       => 1,
			) );
			if ( empty( $existing ) ) {
				$products[] = $parent_prod;
			}
		}

		if ( empty( $products ) ) {
			return '<p class="trustscript-notice">' . esc_html__( 'You have already reviewed all products in this order. Thank you!', 'trustscript' ) . '</p>';
		}

		wp_enqueue_style( 'trustscript-review-form', TRUSTSCRIPT_PLUGIN_URL . 'assets/css/trustscript-review-form.css', array(), TRUSTSCRIPT_VERSION );
		wp_enqueue_script( 'trustscript-review-form', TRUSTSCRIPT_PLUGIN_URL . 'assets/js/trustscript-review-form.js', array(), TRUSTSCRIPT_VERSION, true );

		$initial_product    = $products[0];
		$initial_product_id = $initial_product->get_id();
		$is_single          = count( $products ) === 1;
		$customer_name      = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$customer_email     = $order->get_billing_email();

		$img_id        = $initial_product->get_image_id();
		$product_image = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : wc_placeholder_img_src();

		$js_config = array(
			'rest_url'      => esc_url_raw( rest_url() ),
			'wp_rest_nonce' => wp_create_nonce( 'wp_rest' ),
			'is_logged_in'  => true,
			'product_id'    => $initial_product_id,
			'review_token'  => $token,
			'standalone'    => true,
		);

		wp_localize_script( 'trustscript-review-form', 'trustscriptConfig', $js_config );
		if ( class_exists( 'TrustScript_Simple_Review' ) ) {
			wp_localize_script( 'trustscript-review-form', 'trustscriptStrings', TrustScript_Simple_Review::get_js_strings() );
		}

		ob_start();
		echo '<div class="trustscript-review-form" data-config="' . esc_attr( wp_json_encode( $js_config ) ) . '">';

		if ( ! $is_single ) {
			echo TrustScript_Template_Loader::capture( 'product-selector', array( 'products' => $products ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		// Variables are passed as data and escaped inside inline-form.php via
		// esc_url(), esc_html(), esc_attr(), sanitize_text_field(), and int casts.
		echo TrustScript_Template_Loader::capture( 'inline-form', array(
			'token'              => $token,
			'initial_product_id' => $initial_product_id,
			'product_image'      => $product_image,
			'product_name'       => $initial_product->get_name(),
			'customer_name'      => $customer_name,
			'customer_email'     => $customer_email,
			'is_single'          => $is_single,
		) );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Render the login gate for unauthenticated visitors.
	 *
	 * @param string $token
	 * @return string HTML.
	 */
	private static function render_login_prompt( $token ) {
		$current_url = add_query_arg( 'trustscript_review_token', rawurlencode( $token ), get_permalink() );

		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ) {
			$myaccount = wc_get_page_permalink( 'myaccount' );
			if ( $myaccount ) {
				$login_url    = add_query_arg( 'redirect', $current_url, $myaccount );
				$can_register = ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) || 'yes' === get_option( 'woocommerce_enable_checkout_login_reminder' ) );
				$register_url = $can_register ? add_query_arg( array( 'action' => 'register', 'redirect' => $current_url ), $myaccount ) : '';
			} else {
				$login_url    = wp_login_url( $current_url );
				$can_register = (bool) get_option( 'users_can_register' );
				$register_url = $can_register ? add_query_arg( 'redirect', $current_url, wp_registration_url() ) : '';
			}
		} else {
			$login_url    = wp_login_url( $current_url );
			$can_register = (bool) get_option( 'users_can_register' );
			$register_url = $can_register ? add_query_arg( 'redirect', $current_url, wp_registration_url() ) : '';
		}

		return TrustScript_Template_Loader::capture( 'login-prompt', array(
			'login_url'    => $login_url,
			'register_url' => $register_url,
			'can_register' => $can_register,
		) );
	}
}