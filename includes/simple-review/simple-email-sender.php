<?php
/**
 * TrustScript Simple Email Sender
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrustScript_Simple_Email_Sender {

	/**
	 * Get the list of available placeholders for the simple review request email templates.
	 *
	 * @return array  token => human label
	 */
	public static function get_simple_email_placeholders() {
		return array(
			'{customer_name}'  => esc_html__( 'Customer Name', 'trustscript' ),
			'{customer_email}' => esc_html__( 'Customer Email', 'trustscript' ),
			'{product_name}'   => esc_html__( 'Product Name', 'trustscript' ),
			'{order_number}'   => esc_html__( 'Order Number', 'trustscript' ),
			'{order_date}'     => esc_html__( 'Order Date', 'trustscript' ),
			'{order_total}'    => esc_html__( 'Order Total', 'trustscript' ),
			'{store_name}'     => esc_html__( 'Store Name', 'trustscript' ),
			'{store_url}'      => esc_html__( 'Store URL', 'trustscript' ),
			'{review_link}'    => esc_html__( 'Review Link (required)', 'trustscript' ),
			'{opt_out_link}'   => esc_html__( 'Opt-Out Link', 'trustscript' ),
		);
	}

	/**
	 * Get the default email subject template for review requests, with a placeholder for the store name.
	 *
	 * @return string
	 */
	public static function get_default_email_subject() {
		/* translators: Default review request email subject line. */
		return esc_html__( 'Share your feedback — {store_name} would love to hear from you', 'trustscript' );
	}

	/**
	 * Get the default email body template for review requests, containing placeholders
	 * for customer and order details, and a call-to-action button linking to the review form.
	 *
	 * @return string
	 */
	public static function get_default_email_body() {
		$greeting = esc_html__( 'Hi {customer_name},', 'trustscript' );
		$intro    = sprintf(
			/* translators: %s: store name */
			esc_html__( 'Thank you for your purchase from %s. We hope you\'re enjoying your recent order.', 'trustscript' ),
			'<strong>{store_name}</strong>'
		);
		$sub          = esc_html__( 'Your feedback helps other customers make informed decisions and helps us improve.', 'trustscript' );
		$details_head = esc_html__( 'Order Details', 'trustscript' );
		$label_prod   = esc_html__( 'Product', 'trustscript' );
		$label_order  = esc_html__( 'Order Number', 'trustscript' );
		$label_date   = esc_html__( 'Order Date', 'trustscript' );
		$label_total  = esc_html__( 'Order Total', 'trustscript' );
		$cta_label    = esc_html__( 'Leave a Review', 'trustscript' );
		$cta_sub      = esc_html__( 'It only takes a minute and we truly appreciate your time.', 'trustscript' );
		$sent_by      = esc_html__( 'Sent by', 'trustscript' );
		$sent_to      = esc_html__( 'This email was sent to {customer_email}', 'trustscript' );
		$opt_out_msg  = esc_html__( 'Unsubscribe from review requests', 'trustscript' );

		return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f5f5f5;padding:30px 0;font-family:Arial,Helvetica,sans-serif;">' .
			'<tbody>' .
				'<tr>' .
					'<td align="center">' .
						'<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:6px;padding:30px;">' .
							'<tbody>' .
								'<tr>' .
									'<td style="font-size:22px;font-weight:bold;color:#333333;padding-bottom:10px;">{store_name}</td>' .
								'</tr>' .
								'<tr>' .
									'<td style="font-size:16px;color:#333333;padding-bottom:15px;">' . esc_html( $greeting ) . '</td>' .
								'</tr>' .
								'<tr>' .
									'<td style="font-size:15px;color:#555555;line-height:1.6;padding-bottom:20px;">' .
										wp_kses( $intro, array( 'strong' => array() ) ) . '<br><br>' . esc_html( $sub ) .
									'</td>' .
								'</tr>' .
								'<tr>' .
									'<td style="background:#f9f9f9;border:1px solid #eeeeee;padding:15px;font-size:14px;color:#333333;line-height:1.6;border-radius:4px;">' .
										'<strong>' . esc_html( $details_head ) . '</strong><br>' .
										esc_html( $label_prod ) . ': {product_name}<br>' .
										esc_html( $label_order ) . ': {order_number}<br>' .
										esc_html( $label_date ) . ': {order_date}<br>' .
										esc_html( $label_total ) . ': {order_total}' .
									'</td>' .
								'</tr>' .
								'<tr><td style="padding:20px 0;"></td></tr>' .
								'<tr>' .
									'<td align="center">' .
										'<a href="{review_link}" style="background:#16A34A;color:#ffffff;text-decoration:none;padding:12px 32px;border-radius:4px;font-size:15px;font-weight:bold;display:inline-block;">' . esc_html( $cta_label ) . '</a>' .
									'</td>' .
								'</tr>' .
								'<tr>' .
									'<td style="padding-top:25px;font-size:14px;color:#666666;line-height:1.6;">' . esc_html( $cta_sub ) . '</td>' .
								'</tr>' .
								'<tr>' .
									'<td style="padding-top:25px;font-size:13px;color:#888888;line-height:1.6;border-top:1px solid #eeeeee;text-align:center;">' .
										esc_html( $sent_by ) . ' <strong>{store_name}</strong><br>' .
										'<a href="{store_url}" style="color:#16A34A;text-decoration:none;">{store_url}</a><br><br>' .
										esc_html( $sent_to ) . '<br><br>' .
										'<a href="{opt_out_link}" style="color:#888888;text-decoration:underline;">' . esc_html( $opt_out_msg ) . '</a>' .
									'</td>' .
								'</tr>' .
							'</tbody>' .
						'</table>' .
					'</td>' .
				'</tr>' .
			'</tbody>' .
			'</table>';
	}

	/**
	 * Send a review request email to the customer associated with the given WooCommerce order ID,
	 * if the simple review flow is enabled and the order is eligible.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return bool True on successful wp_mail() dispatch, false otherwise.
	 */
	public static function send_review_request( $order_id ) {
		if ( ! empty( get_option( 'trustscript_api_review_collection_enabled' ) ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$void_statuses = array( 'cancelled', 'refunded', 'failed' );
		if ( in_array( $order->get_status(), $void_statuses, true ) ) {
			return false;
		}

		$items = $order->get_items();
		if ( empty( $items ) ) {
			return false;
		}

		$eligible_items = array();
		foreach ( $items as $item ) {
			$product_id = absint( $item->get_product_id() );
			if ( ! $product_id ) {
				continue;
			}

			$qty           = (int) $item->get_quantity();
			$qty_refunded  = (int) abs( $order->get_qty_refunded_for_item( $item->get_id() ) );
			$qty_remaining = $qty - $qty_refunded;
			if ( $qty_remaining <= 0 ) {
				continue;
			}

			if ( class_exists( 'TrustScript_Review_Requests' ) && TrustScript_Review_Requests::is_cancelled( $order_id, $product_id ) ) {
				continue;
			}

			$eligible_items[] = $item;
		}

		if ( empty( $eligible_items ) ) {
			return false;
		}

		$token = $order->get_meta( '_trustscript_simple_review_token' );
		if ( empty( $token ) ) {
			$token = bin2hex( random_bytes( 16 ) );
			$order->update_meta_data( '_trustscript_simple_review_token', $token );
			$order->update_meta_data( '_trustscript_simple_review_token_created', time() );
			$order->save();
		}

		$customer_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$customer_email = $order->get_billing_email();
		if ( empty( $customer_email ) ) {
			return false;
		}

		if ( class_exists( 'TrustScript_Opt_Out' ) ) {
			$email_hash = hash( 'sha256', strtolower( trim( $customer_email ) ) );
			if ( TrustScript_Opt_Out::is_opted_out( $email_hash ) ) {
				if ( class_exists( 'TrustScript_Review_Requests' ) ) {
					TrustScript_Review_Requests::mark_by_order( $order_id, 'opt-out' );
				}
				$order->update_meta_data( '_trustscript_customer_opted_out', '1' );
				$order->save_meta_data();
				return false;
			}
		}

		$first_item   = reset( $eligible_items );
		$product      = $first_item->get_product();
		$product_name = $product ? $product->get_name() : $first_item->get_name();
		$store_name   = get_bloginfo( 'name' );
		
		$page_id      = get_option( 'trustscript_review_page_id' );
		$base_url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );
		$review_link  = add_query_arg( 'trustscript_review_token', $token, $base_url );
		$opt_out_link = add_query_arg( 'trustscript_opt_out', '1', $review_link );
		
		$subject = get_option( 'trustscript_simple_email_subject', self::get_default_email_subject() );
		$body    = get_option( 'trustscript_simple_email_body', self::get_default_email_body() );

		if ( strpos( $body, '{opt_out_link}' ) === false ) {
			$opt_out_msg = __( 'Unsubscribe from review requests', 'trustscript' );
			$body       .= '<div style="text-align:center;padding:20px;font-family:Arial,Helvetica,sans-serif;"><a href="{opt_out_link}" style="color:#888888;text-decoration:underline;font-size:12px;">' . esc_html( $opt_out_msg ) . '</a></div>';
		}

		$date_created = $order->get_date_created();
		$order_date   = $date_created ? wp_date( get_option( 'date_format' ), $date_created->getTimestamp() ) : '';
		$order_total  = function_exists( 'wc_price' ) ? wc_price( $order->get_total() ) : '$' . number_format( (float) $order->get_total(), 2 );

		$search  = array_keys( self::get_simple_email_placeholders() );
		$replace = array(
			$customer_name,
			$customer_email,
			$product_name,
			$order->get_order_number(),
			$order_date,
			$order_total,
			$store_name,
			esc_url( home_url() ),
			esc_url( $review_link ),
			esc_url( $opt_out_link ),
		);

		$subject = str_replace( $search, $replace, $subject );
		$body    = str_replace( $search, $replace, $body );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $customer_email, $subject, wpautop( $body ), $headers );
	}
}
