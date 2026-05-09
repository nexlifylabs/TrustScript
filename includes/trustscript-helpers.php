<?php
/**
 * Helper functions for TrustScript plugin - API requests, URL building, etc.
 *
 * @package TrustScript
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get TrustScript base URL
 *
 * Returns the base URL for TrustScript API requests, which can be overridden by a plugin setting.
 *
 * @return string Base URL without trailing slash
 */
function trustscript_get_base_url() {
	$cached_url = get_transient( 'trustscript_base_url' );
	if ( $cached_url ) {
		return rtrim( $cached_url, '/' );
	}

	$db_url = get_option( 'trustscript_base_url', '' );
	if ( ! empty( $db_url ) ) {
		set_transient( 'trustscript_base_url', $db_url, MONTH_IN_SECONDS );
		return rtrim( $db_url, '/' );
	}

	return TRUSTSCRIPT_API_BASE_URL;
}

/**
 * Get TrustScript app URL
 *
 * @return string App URL without trailing slash
 */
function trustscript_get_app_url() {
	return trustscript_get_base_url();
}

/**
 * Render verification badge SVG icon.
 *
 * @since 1.0.0
 * @return string
 */
function trustscript_get_badge_svg() {
	return '<svg class="trustscript-badge-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
		<path d="M9 12L11 14L15 10M20 12C20 16.461 14.54 19.694 12.641 20.683c-.205.107-.308.16-.45.188a1.01 1.01 0 0 1-.382 0c-.142-.028-.245-.081-.45-.188C9.46 19.694 4 16.461 4 12V8.218c0-.8 0-1.2.131-1.543a3 3 0 0 1 .547-.79c.276-.243.65-.383 1.398-.664l5.362-2.01c.208-.078.312-.117.419-.133a1.4 1.4 0 0 1 .286 0c.107.016.211.055.419.133l5.362 2.01c.748.281 1.122.421 1.398.664.244.215.432.486.547.79.131.343.131.743.131 1.543V12Z"
			stroke="currentColor"
			stroke-width="2"
			stroke-linecap="round"
			stroke-linejoin="round"/>
	</svg>';
}

/**
 * Render a play button SVG icon.
 *
 * @since 1.0.0
 * @return string SVG markup
 */
function trustscript_get_play_svg() {
	return '<svg width="20" height="20" viewBox="0 0 24 24" fill="white" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
}

/**
 * Render a thumbs up SVG icon.
 *
 * @since 1.0.0
 * @return string SVG markup
 */
function trustscript_get_thumbs_up_svg() {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3H14z"/><path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>';
}

/**
 * Render a thumbs down SVG icon.
 *
 * @since 1.0.0
 * @return string SVG markup
 */
function trustscript_get_thumbs_down_svg() {
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3H10z"/><path d="M17 2h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/></svg>';
}

/**
 * Make an authenticated API request to the TrustScript backend.
 *
 * @param string $method HTTP method (GET, POST, etc.)
 * @param string $endpoint API endpoint path (e.g. '/api/verify-api-key')
 * @param array  $payload Optional request payload for POST requests
 * @param int    $timeout Optional timeout in seconds (default 15)
 * @return array|WP_Error Decoded response data on success, WP_Error on failure
 */
function trustscript_api_request( $method, $endpoint, $payload = array(), $timeout = 15 ) {
	$api_key = trustscript_get_api_key();

	if ( empty( $api_key ) ) {
		return new WP_Error( 'no_api_key', __( 'Please set your API key in settings.', 'trustscript' ), array( 'status' => 400 ) );
	}

	$base_url = trustscript_get_base_url();
	$url      = trailingslashit( $base_url ) . ltrim( $endpoint, '/' );

	$args = array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Accept'        => 'application/json',
			'X-Site-URL'    => get_site_url(),
		),
		'timeout' => $timeout,
	);

	if ( strtoupper( $method ) === 'POST' && ! empty( $payload ) ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $payload );
	}

	$response = strtoupper( $method ) === 'POST'
		? wp_remote_post( $url, $args )
		: wp_remote_get( $url, $args );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	if ( $code < 200 || $code >= 300 ) {
		$message = __( 'API request failed', 'trustscript' );

		if ( $code === 401 ) {
			$message = __( 'API key authentication failed', 'trustscript' );
		} elseif ( $code === 403 ) {
			$message = __( 'Access denied', 'trustscript' );
		} elseif ( $code === 404 ) {
			$message = __( 'Resource not found', 'trustscript' );
		} elseif ( $code === 429 ) {
			$message = __( 'Rate limit exceeded', 'trustscript' );
		}

		return new WP_Error(
			'api_error',
			$message,
			array(
				'status' => $code,
				'body'   => $body,
			)
		);
	}

	$data = json_decode( $body, true );

	if ( json_last_error() !== JSON_ERROR_NONE ) {
		return new WP_Error( 'invalid_json', __( 'Invalid JSON response from API', 'trustscript' ), array( 'status' => 500 ) );
	}

	return array(
		'data' => $data,
		'code' => $code,
	);
}

/**
 * Verify webhook HMAC signature and timestamp freshness.
 *
 * Validates the X-Webhook-Timestamp and X-Webhook-Signature headers
 * against the raw request body using HMAC-SHA256.
 *
 * @since 1.0.0
 * @param WP_REST_Request $request        REST request object.
 * @param string          $webhook_secret Webhook secret from plugin settings.
 * @return true|WP_Error    True on success, WP_Error on failure.
 */
function trustscript_verify_webhook_signature( WP_REST_Request $request, $webhook_secret = '' ) {
	if ( empty( $webhook_secret ) ) {
		$webhook_secret = trustscript_get_webhook_secret();
	}

	if ( empty( $webhook_secret ) ) {
		return new WP_Error(
			'webhook_secret_missing',
			__( 'Webhook secret not configured. Please save your Webhook Secret in TrustScript settings.', 'trustscript' ),
			array( 'status' => 401 )
		);
	}

	$timestamp = $request->get_header( 'X-Webhook-Timestamp' );
	if ( empty( $timestamp ) ) {
		return new WP_Error(
			'webhook_timestamp_missing',
			__( 'X-Webhook-Timestamp header is missing.', 'trustscript' ),
			array( 'status' => 401 )
		);
	}

	if ( ! is_numeric( $timestamp ) ) {
		return new WP_Error(
			'webhook_timestamp_invalid',
			__( 'X-Webhook-Timestamp header must be numeric (Unix seconds).', 'trustscript' ),
			array( 'status' => 401 )
		);
	}

		$timestamp = (int) $timestamp;
		// time() returns the current Unix timestamp (always UTC), which correctly
		// matches the UTC timestamp sent by the backend, regardless of site timezone.
		$current_time = time();
		$time_skew    = $current_time - $timestamp;
		$max_skew     = 300; // 5 minutes in seconds

	if ( abs( $time_skew ) > $max_skew ) {
		return new WP_Error(
			'webhook_timestamp_expired',
			/* translators: %d: number of seconds for allowed timestamp skew */
			sprintf( __( 'Webhook timestamp is outside acceptable window (max ±%d seconds).', 'trustscript' ), $max_skew ),
			array( 'status' => 401 )
		);
	}

	$signature = $request->get_header( 'X-Webhook-Signature' );
	if ( empty( $signature ) ) {
		return new WP_Error(
			'webhook_signature_missing',
			__( 'X-Webhook-Signature header is missing.', 'trustscript' ),
			array( 'status' => 401 )
		);
	}

	$raw_body = $request->get_body();
	if ( empty( $raw_body ) ) {
		return new WP_Error(
			'webhook_body_read_failure',
			__( 'Could not read request body.', 'trustscript' ),
			array( 'status' => 400 )
		);
	}

	$signed_payload     = $timestamp . ':' . $raw_body;
	$expected_signature = hash_hmac( 'sha256', $signed_payload, $webhook_secret, false );

	if ( ! hash_equals( $expected_signature, $signature ) ) {
		return new WP_Error(
			'webhook_signature_invalid',
			__( 'Webhook signature verification failed. Possible causes: wrong webhook secret, modified payload, or request tampering.', 'trustscript' ),
			array( 'status' => 401 )
		);
	}

	return true;
}

/**
 * Retrieve the persistent 256-bit encryption key.
 *
 * If the key is missing, initializes it on the fly.
 *
 * @since 1.0.0
 * @return string Raw 32-byte binary key suitable for AES-256 encryption.
 */
function trustscript_get_encryption_key() {
	$stored_key = get_option( 'trustscript_encryption_key' );

	if ( ! empty( $stored_key ) ) {
		$decoded = base64_decode( $stored_key, true );
		if ( $decoded && strlen( $decoded ) === 32 ) {
			return $decoded;
		}
	}

	trustscript_initialize_encryption_key();
	return base64_decode( get_option( 'trustscript_encryption_key' ), true );
}

/**
 * Initialize the persistent encryption key on plugin activation.
 *
 * @since 1.0.0
 */
function trustscript_initialize_encryption_key() {
	if ( ! empty( get_option( 'trustscript_encryption_key' ) ) ) {
		return;
	}

	update_option( 'trustscript_encryption_key', base64_encode( random_bytes( 32 ) ) );
}

/**
 * Encrypt data using AES-256-CBC.
 *
 * @since 1.0.0
 * @param string $data Plain text data to encrypt.
 * @return string Base64-encoded IV + ciphertext, or empty string on failure.
 */
function trustscript_encrypt_data( $data ) {
	if ( empty( $data ) ) {
		return '';
	}

	$encryption_key = trustscript_get_encryption_key();
	$iv             = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
	$encrypted      = openssl_encrypt(
		$data,
		'aes-256-cbc',
		$encryption_key,
		OPENSSL_RAW_DATA,
		$iv
	);

	if ( $encrypted === false ) {
		return '';
	}
	$result = base64_encode( $iv . $encrypted );

	return $result;
}

/**
 * Decrypt AES-256-CBC encrypted data.
 *
 * @since 1.0.0
 * @param string $encrypted_data Base64-encoded IV + ciphertext.
 * @return string Decrypted plain text, or empty string on failure.
 */
function trustscript_decrypt_data( $encrypted_data ) {
	if ( empty( $encrypted_data ) ) {
		return '';
	}

	try {
		$encryption_key = trustscript_get_encryption_key();
		$cipher         = 'aes-256-cbc';
		$iv_length      = openssl_cipher_iv_length( $cipher );
		$data           = base64_decode( $encrypted_data, true );

		if ( $data === false ) {
			return '';
		}

		$iv        = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );
		$decrypted = openssl_decrypt(
			$encrypted,
			$cipher,
			$encryption_key,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( $decrypted === false ) {
			return '';
		}

		return $decrypted;
	} catch ( Exception $e ) {
		return '';
	}
}

/**
 * Get the decrypted API key.
 *
 * Uses a request-scoped static cache to avoid redundant decryption.
 *
 * @since 1.0.0
 * @return string Decrypted API key, or empty string if not set.
 */
function trustscript_get_api_key() {
	static $cached_api_key    = null;
	static $cache_initialized = false;

	if ( $cache_initialized ) {
		return $cached_api_key;
	}

	$encrypted = get_option( 'trustscript_api_key', '' );

	if ( empty( $encrypted ) ) {
		$cache_initialized = true;
		return '';
	}

	$decrypted = trustscript_decrypt_data( $encrypted );

	if ( empty( $decrypted ) ) {
		$cache_initialized = true;
		return '';
	}

	$cached_api_key    = $decrypted;
	$cache_initialized = true;

	return $cached_api_key;
}

/**
 * Get the decrypted webhook secret.
 *
 * Uses a request-scoped static cache to avoid redundant decryption.
 *
 * @since 1.0.0
 * @return string Decrypted webhook secret, or empty string if not set.
 */
function trustscript_get_webhook_secret() {
	static $cached_webhook_secret = null;
	static $cache_initialized     = false;

	if ( $cache_initialized ) {
		return $cached_webhook_secret;
	}

	$encrypted = get_option( 'trustscript_webhook_secret', '' );

	if ( empty( $encrypted ) ) {
		$cache_initialized = true;
		return '';
	}

	$decrypted = trustscript_decrypt_data( $encrypted );

	if ( empty( $decrypted ) ) {
		$cache_initialized = true;
		return '';
	}

	$cached_webhook_secret = $decrypted;
	$cache_initialized     = true;

	return $cached_webhook_secret;
}

/**
 * Link guest orders to newly registered user accounts.
 *
 * @param int $user_id Newly registered user ID.
 */
function trustscript_link_guest_orders_on_registration( $user_id ) {
	if ( ! function_exists( 'wc_update_new_customer_past_orders' ) ) {
		return;
	}

	wc_update_new_customer_past_orders( $user_id );
}
add_action( 'user_register', 'trustscript_link_guest_orders_on_registration' );

/**
 * Ensure customers are redirected back to their review after login or registration.
 *
 * @param string $redirect Existing redirect URL.
 * @return string Modified redirect URL.
 */
function trustscript_woocommerce_auth_redirect( $redirect ) {
	$redirect_decoded = rawurldecode( $redirect );

	if ( strpos( $redirect_decoded, 'trustscript_review_token' ) !== false ) {
		return esc_url_raw( $redirect_decoded );
	}

	$sources = array(
		isset( $_POST['redirect'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Handled by WooCommerce authentication flow
		isset( $_POST['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Handled by WooCommerce authentication flow
		isset( $_GET['redirect'] ) ? sanitize_text_field( wp_unslash( $_GET['redirect'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Handled by WooCommerce authentication flow
		isset( $_GET['redirect_to'] ) ? sanitize_text_field( wp_unslash( $_GET['redirect_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Handled by WooCommerce authentication flow
	);

	foreach ( $sources as $url ) {
		$url = esc_url_raw( rawurldecode( $url ) );
		if ( ! empty( $url ) && strpos( $url, 'trustscript_review_token' ) !== false ) {
			return $url;
		}
	}

	return $redirect;
}
add_filter( 'woocommerce_login_redirect', 'trustscript_woocommerce_auth_redirect', 9999 );
add_filter( 'woocommerce_registration_redirect', 'trustscript_woocommerce_auth_redirect', 9999 );

/**
 * Preserve the review return URL inside the WooCommerce registration form.
 */
function trustscript_preserve_review_return_url_on_register() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Handled by WooCommerce authentication flow
	$redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : '';
	if ( ! empty( $redirect ) && strpos( $redirect, 'trustscript_review_token' ) !== false ) {
		echo '<input type="hidden" name="redirect" value="' . esc_attr( $redirect ) . '">';
	}
}
add_action( 'woocommerce_register_form', 'trustscript_preserve_review_return_url_on_register' );

/**
 * Preserve the review return URL inside the WooCommerce registration form.
 */
function trustscript_preserve_review_return_url_on_login() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Handled by WooCommerce authentication flow
	$redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : '';
	if ( ! empty( $redirect ) && strpos( $redirect, 'trustscript_review_token' ) !== false ) {
		echo '<input type="hidden" name="redirect" value="' . esc_attr( $redirect ) . '">';
	}
}
add_action( 'woocommerce_login_form', 'trustscript_preserve_review_return_url_on_login' );
