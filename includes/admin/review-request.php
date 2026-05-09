<?php
/**
 * Review Requests Management Page
 *
 * Reads from trustscript_review_requests (per-product tracking table).
 *
 * @package TrustScript
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrustScript_Review_Request_Page {

	const PER_PAGE = 20;

	public function __construct() {
		add_action( 'wp_ajax_trustscript_fetch_review_requests',    array( $this, 'handle_fetch_requests' ) );
		add_action( 'wp_ajax_trustscript_send_simple_review_email', array( $this, 'handle_send_simple_email' ) );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap" style="max-width:1400px;margin:0 auto;padding-top:20px;">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Review Requests', 'trustscript' ); ?></h1>

			<div class="trustscript-card">
				<h2><?php esc_html_e( 'Track Review Requests', 'trustscript' ); ?></h2>
				<p>
					<?php esc_html_e( 'View per-product review tracking for every processed order. Products the customer has already reviewed appear as "Already Reviewed" — no action needed.', 'trustscript' ); ?>
				</p>
			</div>

			<div id="review-requests-stats" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:16px;margin-bottom:32px;">
				<div class="trustscript-stat-card trustscript-stat-card-primary">
					<div class="trustscript-stat-label"><?php esc_html_e( 'Total', 'trustscript' ); ?></div>
					<div id="rr-stat-total" class="trustscript-stat-value">-</div>
				</div>
				<div class="trustscript-stat-card trustscript-stat-card-info">
					<div class="trustscript-stat-label"><?php esc_html_e( 'Pending', 'trustscript' ); ?></div>
					<div id="rr-stat-pending" class="trustscript-stat-value">-</div>
				</div>
				<div class="trustscript-stat-card trustscript-stat-card-warning">
					<div class="trustscript-stat-label"><?php esc_html_e( 'Scheduled', 'trustscript' ); ?></div>
					<div id="rr-stat-scheduled" class="trustscript-stat-value">-</div>
				</div>
				<div class="trustscript-stat-card trustscript-stat-card-success">
					<div class="trustscript-stat-label"><?php esc_html_e( 'Sent', 'trustscript' ); ?></div>
					<div id="rr-stat-sent" class="trustscript-stat-value">-</div>
				</div>
				<div class="trustscript-stat-card trustscript-stat-card-success">
					<div class="trustscript-stat-label"><?php esc_html_e( 'Published', 'trustscript' ); ?></div>
					<div id="rr-stat-published" class="trustscript-stat-value">-</div>
				</div>
				<div class="trustscript-stat-card trustscript-stat-card-purple">
					<div class="trustscript-stat-label"><?php esc_html_e( 'Opt-Out', 'trustscript' ); ?></div>
					<div id="rr-stat-optout" class="trustscript-stat-value">-</div>
				</div>
				<div class="trustscript-stat-card trustscript-stat-card-warning">
					<div class="trustscript-stat-label"><?php esc_html_e( 'Already Reviewed', 'trustscript' ); ?></div>
					<div id="rr-stat-already-published" class="trustscript-stat-value">-</div>
				</div>
				<div class="trustscript-stat-card trustscript-stat-card-danger">
					<div class="trustscript-stat-label"><?php esc_html_e( 'Cancelled', 'trustscript' ); ?></div>
					<div id="rr-stat-cancelled" class="trustscript-stat-value">-</div>
				</div>
			</div>

			<div class="trustscript-review-requests-main-card">
				<div class="trustscript-review-requests-filters-section">
					<h3 class="trustscript-review-requests-filters-title"><?php esc_html_e( 'All Review Requests', 'trustscript' ); ?></h3>
					<div class="trustscript-review-requests-filters-grid">
						<div class="trustscript-review-requests-filter-group">
							<label for="rr-search"><?php esc_html_e( 'Search', 'trustscript' ); ?></label>
							<input type="text" id="rr-search" class="trustscript-review-requests-filter-input"
								placeholder="<?php esc_attr_e( 'Order #', 'trustscript' ); ?>">
						</div>
						<div class="trustscript-review-requests-filter-group">
							<label for="rr-status-filter"><?php esc_html_e( 'Status', 'trustscript' ); ?></label>
							<select id="rr-status-filter" class="trustscript-review-requests-filter-select">
								<option value=""><?php esc_html_e( 'All Statuses', 'trustscript' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pending', 'trustscript' ); ?></option>
								<option value="scheduled"><?php esc_html_e( 'Scheduled', 'trustscript' ); ?></option>
								<option value="sent"><?php esc_html_e( 'Sent', 'trustscript' ); ?></option>
								<option value="published"><?php esc_html_e( 'Published', 'trustscript' ); ?></option>
								<option value="opt-out"><?php esc_html_e( 'Opt-Out', 'trustscript' ); ?></option>
								<option value="already_published"><?php esc_html_e( 'Already Reviewed', 'trustscript' ); ?></option>
								<option value="ineligible"><?php esc_html_e( 'Ineligible', 'trustscript' ); ?></option>
								<option value="cancelled"><?php esc_html_e( 'Cancelled', 'trustscript' ); ?></option>
							</select>
						</div>
						<div class="trustscript-review-requests-filter-group">
							<label for="rr-date-filter"><?php esc_html_e( 'Date Range', 'trustscript' ); ?></label>
							<select id="rr-date-filter" class="trustscript-review-requests-filter-select">
								<option value="0"><?php esc_html_e( 'All Time', 'trustscript' ); ?></option>
								<option value="7"><?php esc_html_e( 'Last 7 Days', 'trustscript' ); ?></option>
								<option value="30"><?php esc_html_e( 'Last 30 Days', 'trustscript' ); ?></option>
								<option value="90"><?php esc_html_e( 'Last 90 Days', 'trustscript' ); ?></option>
							</select>
						</div>
					</div>
					<button type="button" id="rr-apply-filters" class="trustscript-review-requests-apply-btn">
						<?php esc_html_e( 'Apply Filters', 'trustscript' ); ?>
					</button>
				</div>

				<div id="review-requests-loading" class="trustscript-review-requests-loading">
					<div><?php esc_html_e( 'Loading...', 'trustscript' ); ?></div>
				</div>

				<div id="review-requests-list" class="trustscript-review-requests-list" style="display:none;">
					<div id="rr-results-info" class="trustscript-review-requests-results-info"></div>
					<table class="trustscript-review-requests-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Order', 'trustscript' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'trustscript' ); ?></th>
								<th><?php esc_html_e( 'Product', 'trustscript' ); ?></th>
								<th><?php esc_html_e( 'Order Date', 'trustscript' ); ?></th>
								<th><?php esc_html_e( 'Consent', 'trustscript' ); ?></th>
								<th><?php esc_html_e( 'Status', 'trustscript' ); ?></th>
								<th><?php esc_html_e( 'Action', 'trustscript' ); ?></th>
							</tr>
						</thead>
						<tbody id="review-requests-tbody"></tbody>
					</table>

					<div id="rr-pagination" class="trustscript-review-requests-pagination">
						<div id="rr-pagination-info" class="trustscript-review-requests-pagination-info"></div>
						<div id="rr-pagination-buttons" class="trustscript-review-requests-pagination-buttons"></div>
					</div>
				</div>

				<div id="review-requests-empty" class="trustscript-review-requests-empty" style="display:none;">
					<div><?php esc_html_e( 'No tracked orders found.', 'trustscript' ); ?></div>
				</div>
			</div>

			<div class="trustscript-review-requests-action-buttons">
				<button type="button" id="refresh-review-requests" class="trustscript-review-requests-refresh-btn">
					<?php esc_html_e( 'Refresh', 'trustscript' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	public function handle_fetch_requests() {
		check_ajax_referer( 'trustscript_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 401 );
			return;
		}

		$page          = max( 1, absint( wp_unslash( $_POST['page'] ?? 1 ) ) );
		$search        = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$status_filter = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		$date_days     = absint( wp_unslash( $_POST['date_range'] ?? 0 ) );
		$per_page      = self::PER_PAGE;

		if ( ! class_exists( 'TrustScript_Review_Requests' ) || ! TrustScript_Review_Requests::table_exists() ) {
			wp_send_json_success( array(
				'stats'        => array( 'pending' => 0, 'sent' => 0, 'already_published' => 0, 'total' => 0 ),
				'orders'       => array(),
				'total'        => 0,
				'pages'        => 1,
				'page'         => 1,
				'perPage'      => $per_page,
				'canSendSimple' => false,
			) );
			return; 
		}

		global $wpdb;
		$table = TrustScript_Review_Requests::get_table_name();
		$count_sql  = $wpdb->prepare( 'SELECT COUNT(*) FROM %i rr WHERE 1=1', $table );
		$select_sql = $wpdb->prepare(
			'SELECT rr.order_id, rr.product_id, rr.status, rr.created_at FROM %i rr WHERE 1=1',
			$table
		);

		if ( ! empty( $status_filter ) ) {
			$segment     = $wpdb->prepare( ' AND rr.status = %s', $status_filter );
			$count_sql  .= $segment;
			$select_sql .= $segment;
		}

		if ( $date_days > 0 ) {
			$after = gmdate( 'Y-m-d 00:00:00', strtotime( "-{$date_days} days" ) );
			$segment     = $wpdb->prepare( ' AND rr.created_at >= %s', $after );
			$count_sql  .= $segment;
			$select_sql .= $segment;
		}

		if ( ! empty( $search ) ) {
			$clean = ltrim( $search, '#' );
			if ( is_numeric( $clean ) ) {
				$segment     = $wpdb->prepare( ' AND rr.order_id = %d', (int) $clean );
				$count_sql  .= $segment;
				$select_sql .= $segment;
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( $count_sql );

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = min( $page, $total_pages );
		$offset      = ( $page - 1 ) * $per_page;
		$select_sql .= $wpdb->prepare(
			' ORDER BY rr.created_at DESC, rr.order_id DESC LIMIT %d OFFSET %d',
			$per_page,
			$offset
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $select_sql, ARRAY_A ) ?: array();

		$orders_data = array();
		foreach ( $rows as $row ) {
			$order = wc_get_order( (int) $row['order_id'] );
			if ( ! $order ) {
				continue;
			}

			$product_id   = (int) $row['product_id'];
			$product      = $product_id ? wc_get_product( $product_id ) : null;
			$product_name = $product ? $product->get_name() : __( 'Unknown product', 'trustscript' );
			$product_url  = $product ? (string) get_permalink( $product_id ) : '';
			$date_created = $order->get_date_created();
			$processed_by_any_service = (bool) $order->get_meta( '_trustscript_email_sent' );
			$consent_status = class_exists( 'TrustScript_Consent_Manager' )
				? TrustScript_Consent_Manager::get_order_consent_status( (int) $row['order_id'] )
				: 'not_required';
			
			$billing_country = class_exists( 'TrustScript_Consent_Manager' )
				? TrustScript_Consent_Manager::get_order_billing_country( (int) $row['order_id'] )
				: '';
			
			$consent_type = class_exists( 'TrustScript_Consent_Manager' )
				? TrustScript_Consent_Manager::get_consent_type_for_country( $billing_country )
				: 'not_required';

			$display_consent_status = 'N/A';
			$consent_given_at = null;
			$consent_confirmed_at = null;

			if ( 'not_required' === $consent_status ) {
				$display_consent_status = esc_html__( 'Not Required', 'trustscript' );
			} elseif ( 'declined' === $consent_status ) {
				$display_consent_status = esc_html__( 'Not Given', 'trustscript' );
			} elseif ( 'confirmed' === $consent_status ) {
				$display_consent_status = esc_html__( 'Confirmed', 'trustscript' );
				$consent_confirmed_at = $order->get_meta( '_trustscript_consent_confirmed_at' );
			} elseif ( 'pending' === $consent_status ) {
				if ( 'double_optin' === $consent_type ) {
					$consent_given_at = $order->get_meta( '_trustscript_consent_given_at' );
					if ( ! empty( $consent_given_at ) ) {
						$given_timestamp = strtotime( $consent_given_at );
						if ( false !== $given_timestamp && ( time() - $given_timestamp ) > ( 7 * DAY_IN_SECONDS ) ) {
							$display_consent_status = esc_html__( 'Expired', 'trustscript' );
						} else {
							$display_consent_status = esc_html__( 'Waiting', 'trustscript' );
						}
					} else {
						$display_consent_status = esc_html__( 'Waiting', 'trustscript' );
					}
				} else {
					$display_consent_status = esc_html__( 'Pending', 'trustscript' );
				}
			}

			$orders_data[] = array(
				'rawOrderId'          => $order->get_id(),
				'orderId'             => '#' . $order->get_order_number(),
				'orderAdminUrl'       => (string) get_edit_post_link( $order->get_id(), '' ),
				'customerName'        => $order->get_formatted_billing_full_name() ?: esc_html__( 'Guest', 'trustscript' ),
				'productName'         => $product_name,
				'productUrl'          => $product_url,
				'orderDate'           => $date_created ? wp_date( 'M j, Y', $date_created->getTimestamp() ) : '—',
				'sentDate'            => 'sent' === $row['status'] ? TrustScript_Date_Formatter::format( $row['created_at'], 'datetime' ) : null,
				'status'              => $row['status'],
				'ineligibleReason'    => $row['ineligible_reason'] ?? null,
				'emailSent'           => 'sent' === $row['status'] || $processed_by_any_service,
				'consentStatus'       => $consent_status,
				'consentType'         => $consent_type,
				'displayConsentStatus' => $display_consent_status,
			);
		}

		$stats         = TrustScript_Review_Requests::compute_stats();
		$can_send_simple = empty( get_option( 'trustscript_api_review_collection_enabled' ) ) && ! empty( get_option( 'trustscript_simple_review_enabled' ) );

		wp_send_json_success( array(
			'stats'         => $stats,
			'orders'        => $orders_data,
			'total'         => $total,
			'pages'         => $total_pages,
			'page'          => $page,
			'perPage'       => $per_page,
			'canSendSimple' => $can_send_simple,
		) );
	}

	public function handle_send_simple_email() {
		check_ajax_referer( 'trustscript_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'trustscript' ) ), 401 );
			return;
		}

		$order_id = isset( $_POST['order_id'] ) ? intval( wp_unslash( $_POST['order_id'] ) ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID', 'trustscript' ) ) );
		}

		if ( class_exists( 'TrustScript_Opt_Out' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$customer_email = $order->get_billing_email();
				if ( ! empty( $customer_email ) ) {
					$email_hash = hash( 'sha256', strtolower( trim( $customer_email ) ) );
					if ( TrustScript_Opt_Out::is_opted_out( $email_hash ) ) {
						if ( class_exists( 'TrustScript_Review_Requests' ) ) {
							TrustScript_Review_Requests::mark_by_order( $order_id, 'opt-out' );
						}
						wp_send_json_error( array( 'message' => __( 'Customer has opted out of review requests.', 'trustscript' ) ) );
						return;
					}
				}
			}
		}

		if ( class_exists( 'TrustScript_Review_Requests' ) && TrustScript_Review_Requests::table_exists() ) {
			global $wpdb;
			$table = TrustScript_Review_Requests::get_table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$non_cancelled = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE order_id = %d AND status != 'cancelled'",
				$table,
				$order_id
			) );
			if ( 0 === $non_cancelled ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$has_any = (int) $wpdb->get_var( $wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE order_id = %d',
					$table,
					$order_id
				) );
				if ( $has_any > 0 ) {
					wp_send_json_error( array( 'message' => __( 'All products in this order have been cancelled or refunded. No review request was sent.', 'trustscript' ) ) );
					return;
				}
			}
		}

		if ( class_exists( 'TrustScript_Review_Queue_Gating' ) ) {
			if ( ! TrustScript_Review_Queue_Gating::can_send_review_request( $order_id ) ) {
				$blocking_reason = TrustScript_Review_Queue_Gating::get_blocking_reason( $order_id );
				if ( class_exists( 'TrustScript_Review_Requests' ) ) {
					TrustScript_Review_Requests::process_order_products( $order_id, 'opt-out' );
				}
				wp_send_json_error( array( 'message' => __( 'Cannot send review request due to customer consent restrictions.', 'trustscript' ) ) );
				return;
			}
		}

		if ( class_exists( 'TrustScript_Simple_Email_Review' ) ) {
			$sent = TrustScript_Simple_Email_Review::send_review_request( $order_id );
			if ( $sent ) {
				TrustScript_Queue::remove_by_order( $order_id, 'simple' );

				if ( class_exists( 'TrustScript_Review_Requests' ) ) {
					TrustScript_Review_Requests::mark_sent( $order_id );
				}

				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order->update_meta_data( '_trustscript_email_sent', '1' );
					$order->update_meta_data( '_trustscript_review_sent_at', current_time( 'mysql' ) );
					$order->update_meta_data( '_trustscript_service_type', 'simple' );
					$order->save_meta_data();
					$order->add_order_note( __( 'TrustScript Simple Review request emailed to customer (Manual).', 'trustscript' ) );
				}
				wp_send_json_success( array( 'message' => __( 'Email sent successfully.', 'trustscript' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to send email. Check plugin settings or all products may be refunded.', 'trustscript' ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'Simple review component not active.', 'trustscript' ) ) );
		}
	}
}