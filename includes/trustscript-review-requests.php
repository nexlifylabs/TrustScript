<?php
/**
 * TrustScript Review Requests — manages the review request queue and lifecycle for WooCommerce products.
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrustScript_Review_Requests {

	const TABLE_SUFFIX = 'trustscript_review_requests';
	const DB_VERSION   = '1.0';

	public static function init() {
		static $initialized = false;
		if ( $initialized ) {
			return;
		}
		$initialized = true;

		add_action( 'woocommerce_refund_created', array( __CLASS__, 'handle_refund_created' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'handle_order_status_changed' ), 10, 4 );
	}

	/**
	 * Handle WooCommerce order status changes. Mark associated review requests as
	 * cancelled when order is voided (cancelled, refunded, failed).
	 *
	 * @param int      $order_id
	 * @param string   $old_status  Previous WC status (without 'wc-' prefix).
	 * @param string   $new_status  New WC status (without 'wc-' prefix).
	 * @param WC_Order $order
	 * @return void
	 */
	public static function handle_order_status_changed( $order_id, $old_status, $new_status, $order ) {
		
		$void_statuses = array( 'cancelled', 'refunded', 'failed' );
		if ( in_array( $new_status, $void_statuses, true ) ) {
			self::mark_cancelled( $order_id );
		} 
	}

	/**
	 * Handle WooCommerce refund creation.
	 * Mark associated review requests as cancelled when items are refunded.
	 *
	 * @param int   $refund_id WC_Order_Refund ID
	 * @param array $args      Refund args (contains order_id, reason, line_items, etc.)
	 * @return void
	 */
	public static function handle_refund_created( $refund_id, $args ) {
		if ( empty( $args['order_id'] ) ) {
			return;
		}

		$order_id = (int) $args['order_id'];

		if ( empty( $args['line_items'] ) || ! is_array( $args['line_items'] ) ) {
			self::mark_cancelled( $order_id );
			return;
		}
		
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( ! $refund ) {
			return;
		}

		foreach ( $refund->get_items() as $refund_item ) {
			$product_id = absint( $refund_item->get_product_id() );
			if ( ! $product_id ) {
				continue;
			}

			if ( $order ) {
				$qty_remaining = 0;
				foreach ( $order->get_items() as $order_item ) {
					if ( absint( $order_item->get_product_id() ) !== $product_id ) {
						continue;
					}
					$qty           = (int) $order_item->get_quantity();
					$qty_refunded  = (int) abs( $order->get_qty_refunded_for_item( $order_item->get_id() ) );
					$qty_remaining = $qty - $qty_refunded;
					break;
				}
				if ( $qty_remaining > 0 ) {
					continue;
				}
			}

			self::mark_cancelled( $order_id, $product_id );
		}
	}

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function table_exists() {
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	public static function create_table() {
		global $wpdb;

		$installed = get_option( 'trustscript_review_requests_db_version', '' );
		if ( $installed === self::DB_VERSION && self::table_exists() ) {
			return;
		}

		$table           = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/collate from internal methods
		$sql = "CREATE TABLE {$table} (
			id                bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id          bigint(20) UNSIGNED NOT NULL,
			product_id        bigint(20) UNSIGNED NOT NULL,
			status            varchar(30)         NOT NULL DEFAULT 'pending',
			ineligible_reason varchar(100)                 DEFAULT NULL,
			created_at        datetime            NOT NULL,
			updated_at        datetime            NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY   order_product (order_id, product_id),
			KEY          idx_status   (status),
			KEY          idx_order_id (order_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		self::run_migrations();

		update_option( 'trustscript_review_requests_db_version', self::DB_VERSION );
	}

	/**
	 * Run any necessary migrations when the DB version is updated. 
	 *
	 * @return void
	 */
	public static function run_migrations() {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$table
			)
		);

		if ( ! in_array( 'ineligible_reason', $columns, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					'ALTER TABLE %i ADD COLUMN ineligible_reason varchar(100) DEFAULT NULL AFTER status',
					$table
				)
			);
		}
	}

	/**
	 * Insert or update a review request row for this product. Used by process_order_products() and the publish webhook.
	 *
	 * @param int    $order_id
	 * @param int    $product_id
	 * @param string $status  Any of the status constants above.
	 * @return bool
	 */
	public static function upsert( $order_id, $product_id, $status = 'pending' ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table = self::get_table_name();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE order_id = %d AND product_id = %d',
				$table,
				absint( $order_id ),
				absint( $product_id )
			)
		);

		if ( $existing_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = (bool) $wpdb->update(
				$table,
				array(
					'status'     => $status,
					'updated_at' => $now,
				),
				array( 'id' => (int) $existing_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			
			return $result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = (bool) $wpdb->insert(
			$table,
			array(
				'order_id'   => absint( $order_id ),
				'product_id' => absint( $product_id ),
				'status'     => sanitize_key( $status ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		
		return $result;
	}

	/**
	 * Insert or update a review request row with 'ineligible' status and reason.
	 * Used by process_order_products() when a product is deemed ineligible for review.
	 *
	 * Reasons: 'category_filter' | 'free_product' | 'min_value' | 'fully_refunded'
	 *
	 * @param int    $order_id
	 * @param int    $product_id
	 * @param string $reason   One of the reason constants above.
	 * @return bool
	 */
	public static function upsert_ineligible( $order_id, $product_id, $reason ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table = self::get_table_name();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE order_id = %d AND product_id = %d',
				$table,
				absint( $order_id ),
				absint( $product_id )
			)
		);

		if ( $existing_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return (bool) $wpdb->update(
				$table,
				array(
					'status'            => 'ineligible',
					'ineligible_reason' => sanitize_text_field( $reason ),
					'updated_at'        => $now,
				),
				array( 'id' => (int) $existing_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->insert(
			$table,
			array(
				'order_id'          => absint( $order_id ),
				'product_id'        => absint( $product_id ),
				'status'            => 'ineligible',
				'ineligible_reason' => sanitize_text_field( $reason ),
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}
	
	/** Primary hook for webhook and auto-sync events.
	 *
	 * @param int    $order_id
	 * @param string $new_status   e.g. 'published', 'opt-out', 'scheduled'
	 * @param string $from_status  Only update rows at this current status. '' = all rows.
	 * @return bool
	 */
	public static function mark_by_order( $order_id, $new_status, $from_status = '' ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table = self::get_table_name();
		$now   = current_time( 'mysql' );

		if ( ! empty( $from_status ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = (bool) $wpdb->update(
				$table,
				array(
					'status'     => $new_status,
					'updated_at' => $now,
				),
				array(
					'order_id' => absint( $order_id ),
					'status'   => $from_status,
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);
			
			return $result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = (bool) $wpdb->update(
			$table,
			array(
				'status'     => $new_status,
				'updated_at' => $now,
			),
			array( 'order_id' => absint( $order_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		
		return $result;
	}

	/**
	 * Mark a single product in an order as 'published'.
	 * Used by webhook when a productToken publish targets a specific product.
	 *
	 * @param int $order_id
	 * @param int $product_id
	 * @return bool
	 */
	public static function mark_published( $order_id, $product_id ) {
		return self::upsert( $order_id, $product_id, 'published' );
	}

	/**
	 * Mark all pending products in an order as 'sent'.
	 * Used by the simple-flow admin send action.
	 *
	 * @param int $order_id
	 * @param int $product_id  0 = update all pending products in this order.
	 * @return bool
	 */
	public static function mark_sent( $order_id, $product_id = 0 ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table = self::get_table_name();
		$now   = current_time( 'mysql' );

		if ( $product_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = (bool) $wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET status = 'sent', updated_at = %s WHERE order_id = %d AND product_id = %d AND status IN ('pending', 'scheduled')",
					$table,
					$now,
					absint( $order_id ),
					absint( $product_id )
				)
			);
			
			return $result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = (bool) $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'sent', updated_at = %s WHERE order_id = %d AND status IN ('pending', 'scheduled')",
				$table,
				$now,
				absint( $order_id )
			)
		);
		
		return $result;
	}

	/**
	 * Get paginated items, optionally filtered by status.
	 *
	 * @param int    $page
	 * @param int    $per_page
	 * @param string $status '' = all statuses
	 * @return array { items: array, total: int }
	 */
	public static function get_items( $page = 1, $per_page = 20, $status = '' ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		$table  = self::get_table_name();
		$offset = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;

		if ( ! empty( $status ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE status = %s',
					$table,
					$status
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d',
					$table,
					$status,
					(int) $per_page,
					$offset
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i',
					$table
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
					$table,
					(int) $per_page,
					$offset
				),
				ARRAY_A
			);
		}

		return array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}

	/**
	 * Mark a product or entire order as cancelled. Used when an order is voided or refunded.
	 *
	 * @param int $order_id
	 * @param int $product_id  0 = mark all products in this order as cancelled.
	 * @return bool
	 */
	public static function mark_cancelled( $order_id, $product_id = 0 ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		if ( $product_id > 0 ) {
			$result = self::upsert( $order_id, $product_id, 'cancelled' );
		} else {
			$table = self::get_table_name();
			$now   = current_time( 'mysql' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update(
				$table,
				array(
					'status'     => 'cancelled',
					'updated_at' => $now,
				),
				array( 'order_id' => absint( $order_id ) ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			
			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( absint( $order_id ) );
				if ( $order ) {
					$product_count = 0;
					foreach ( $order->get_items() as $item ) {
						$pid = absint( $item->get_product_id() );
						if ( $pid > 0 ) {
							self::upsert( $order_id, $pid, 'cancelled' );
							$product_count++;
						}
					}
				} 
			}
			$result = true;
		}

		if ( class_exists( 'TrustScript_Queue' ) ) {
			$table = self::get_table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$has_active = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE order_id = %d AND status != 'cancelled'",
					$table,
					absint( $order_id )
				)
			);

			if ( $has_active === 0 ) {
				TrustScript_Queue::remove_by_order( $order_id );
			}
		}

		return $result;
	}

	/**
	 * Check if a review request is cancelled.
	 *
	 * @param int $order_id
	 * @param int $product_id
	 * @return bool
	 */
	public static function is_cancelled( $order_id, $product_id ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$status = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT status FROM %i WHERE order_id = %d AND product_id = %d',
				$table,
				absint( $order_id ),
				absint( $product_id )
			)
		);
		
		$is_cancelled = 'cancelled' === $status;

		return $is_cancelled;
	}

	/**
	 * Check if an order has any products with pending or scheduled review requests (i.e. sendable products).
	 *
	 * @param int $order_id
	 * @return bool
	 */
	public static function has_sendable_products( $order_id ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return true;
		}
		
		$table = self::get_table_name();
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE order_id = %d AND status IN ('pending', 'scheduled')",
				$table,
				absint( $order_id )
			)
		);
		
		return $count > 0;
	}

	/**
	 * Aggregate counts by status - covers all lifecycle statuses.
	 *
	 * @return array { pending, sent, scheduled, published, opt-out, already_published, cancelled, total }
	 */
	public static function compute_stats() {
		global $wpdb;

		$defaults = array(
			'pending'           => 0,
			'sent'              => 0,
			'scheduled'         => 0,
			'published'         => 0,
			'opt-out'           => 0,
			'already_published' => 0,
			'ineligible'        => 0,
			'cancelled'         => 0,
		);

		if ( ! self::table_exists() ) {
			return array_merge( $defaults, array( 'total' => 0 ) );
		}

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) as cnt FROM %i GROUP BY status',
				$table
			),
			ARRAY_A
		);

		$stats = $defaults;
		foreach ( $rows as $row ) {
			if ( array_key_exists( $row['status'], $stats ) ) {
				$stats[ $row['status'] ] = (int) $row['cnt'];
			}
		}
		$stats['total'] = array_sum( $stats );
		
		return $stats;
	}

	/**
	 * Process products in an order to determine review request eligibility and initial status.
	 * Called when an order is created or updated, and by a webhook when a productToken publish
	 * targets a specific product. 
	 *
	 * @param int    $order_id
	 * @param string $initial_status  'pending' or 'scheduled' — set by the caller.
	 * @return void
	 */
	public static function process_order_products( $order_id, $initial_status = 'pending' ) {

		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$void_statuses = array( 'cancelled', 'refunded', 'failed' );
		if ( in_array( $order->get_status(), $void_statuses, true ) ) {
			self::mark_cancelled( $order_id );
			return;
		}

		$billing_email = $order->get_billing_email();

		$is_opted_out = false;
		if ( class_exists( 'TrustScript_Opt_Out' ) && ! empty( $billing_email ) ) {
			$email_hash   = hash( 'sha256', strtolower( trim( $billing_email ) ) );
			$is_opted_out = TrustScript_Opt_Out::is_opted_out( $email_hash );
		}

		$consent_status = 'not_required';
		if ( class_exists( 'TrustScript_Consent_Manager' ) ) {
			$consent_status = TrustScript_Consent_Manager::get_order_consent_status( $order_id );
		}

		if ( $is_opted_out || 'declined' === $consent_status ) {
			$initial_status = 'opt-out';
		}

		$allowed_categories = array_filter(
			array_map( 'intval', (array) get_option( 'trustscript_review_categories', array() ) )
		);
		$exclude_free    = get_option( 'trustscript_woocommerce_exclude_free', '0' ) === '1';
		$min_item_value  = (float) get_option( 'trustscript_woocommerce_min_value', 0 );

		$reviewed_product_ids = array();
		if ( ! empty( $billing_email ) ) {
			$existing_reviews = get_comments( array(
				'author_email' => $billing_email,
				'status'       => 'approve',
				'type'         => 'review',
				'post_type'    => 'product',
			) );
			if ( ! empty( $existing_reviews ) ) {
				foreach ( $existing_reviews as $review ) {
					$reviewed_product_ids[] = (int) $review->comment_post_ID;
				}
			}
		}

		$processed_count = 0;
		$skipped_count   = 0;

		foreach ( $order->get_items() as $item ) {
			$product_id = absint( $item->get_product_id() );
			if ( ! $product_id ) {
				continue;
			}

			$product = $item->get_product();

			global $wpdb;
			$db_table        = self::get_table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing_status = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT status FROM %i WHERE order_id = %d AND product_id = %d',
					$db_table,
					absint( $order_id ),
					$product_id
				)
			);
			if ( $existing_status && ! in_array( $existing_status, array( 'pending', 'scheduled', 'ineligible' ), true ) ) {
				$skipped_count++;
				continue;
			}

			$ineligible_reason = null;
			$qty           = (int) $item->get_quantity();
			$qty_refunded  = (int) abs( $order->get_qty_refunded_for_item( $item->get_id() ) );
			$qty_remaining = $qty - $qty_refunded;
			if ( $qty_remaining <= 0 ) {
				$ineligible_reason = 'fully_refunded';
			}

			if ( null === $ineligible_reason && $product && $exclude_free ) {
				if ( (float) $product->get_price() <= 0 ) {
					$ineligible_reason = 'free_product';
				}
			}

			if ( null === $ineligible_reason && $product && ! empty( $allowed_categories ) ) {
				$product_cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
				if ( is_wp_error( $product_cats ) ) {
					$product_cats = array();
				}
				$product_cats = array_map( 'intval', $product_cats );
				if ( empty( array_intersect( $allowed_categories, $product_cats ) ) ) {
					$ineligible_reason = 'category_filter';
				}
			}

			if ( null === $ineligible_reason && $min_item_value > 0 ) {
				$item_value = (float) $item->get_total() + (float) $item->get_total_tax();
				if ( $item_value < $min_item_value ) {
					$ineligible_reason = 'min_value';
				}
			}

			if ( null !== $ineligible_reason ) {
				self::upsert_ineligible( $order_id, $product_id, $ineligible_reason );
				$skipped_count++;
				continue;
			}

			if ( 'opt-out' === $initial_status ) {
				self::upsert( $order_id, $product_id, 'opt-out' );
				$processed_count++;
				continue;
			}

			if ( in_array( $product_id, $reviewed_product_ids, true ) ) {
				self::upsert( $order_id, $product_id, 'already_published' );
				$processed_count++;
				continue;
			}

			self::upsert( $order_id, $product_id, $initial_status );
			$processed_count++;
		}

	}
}