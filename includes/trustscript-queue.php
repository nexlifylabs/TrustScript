<?php
/**
 * TrustScript Quota Queue - Manages a queue of orders that failed to send to TrustScript due
 * to quota limits or other retryable errors, with scheduled retries and admin management.
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrustScript_Queue {

	const TABLE_SUFFIX = 'trustscript_queue';
	const DB_VERSION   = '1.0';

	/**
	 * Return the fully-qualified table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Check if the queue table exists.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Create or upgrade the queue table.
	 *
	 * Safe to call on every init — uses dbDelta for idempotency.
	 *
	 * @since 1.0.0
	 */
	public static function create_table() {
		global $wpdb;

		$installed = get_option( 'trustscript_queue_db_version', '' );
		if ( $installed === self::DB_VERSION && self::table_exists() ) {
			return;
		}

		$table           = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// dbDelta() does not support parameterised queries — table name from internal method is safe.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "CREATE TABLE {$table} (
			id              bigint(20)  UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id        bigint(20)  UNSIGNED NOT NULL,
			service_id      varchar(50)          NOT NULL,
			failure_reason  varchar(20)          NOT NULL DEFAULT 'quota',
			retry_count     tinyint(3)  UNSIGNED NOT NULL DEFAULT 0,
			queued_at       datetime             NOT NULL,
			scheduled_for   datetime                      DEFAULT NULL,
			last_attempt_at datetime                      DEFAULT NULL,
			status          varchar(20)          NOT NULL DEFAULT 'pending',
			PRIMARY KEY  (id),
			UNIQUE KEY   order_service (order_id, service_id),
			KEY          idx_status (status),
			KEY          idx_scheduled_for (scheduled_for),
			KEY          idx_queued_at (queued_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		self::run_migrations();

		update_option( 'trustscript_queue_db_version', self::DB_VERSION );
	}

	/**
	 * Run schema migrations when the DB version is updated.
	 *
	 * @since 1.0.0
	 */
	public static function run_migrations() {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$table
			),
			ARRAY_A
		);

		$column_names = wp_list_pluck( $columns, 'COLUMN_NAME' );

		if ( ! in_array( 'scheduled_for', $column_names, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					'ALTER TABLE %i ADD COLUMN scheduled_for datetime DEFAULT NULL AFTER queued_at',
					$table
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$indexes = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$table
			),
			ARRAY_A
		);

		$index_names = wp_list_pluck( $indexes, 'INDEX_NAME' );

		if ( ! in_array( 'idx_scheduled_for', $index_names, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					'ALTER TABLE %i ADD KEY idx_scheduled_for (scheduled_for)',
					$table
				)
			);
		}
	}

	/**
	 * Add an order to the queue with a failure reason and optional delay.
	 *
	 * @since 1.0.0
	 * @param int    $order_id       WooCommerce order ID.
	 * @param string $service_id     Service slug e.g. 'woocommerce', 'memberpress'.
	 * @param string $failure_reason 'quota' | 'rate_limit' | 'network' | 'api_error' | 'delay'.
	 * @param int    $delay_seconds  Seconds from now to schedule processing. Default 0.
	 * @return bool True on insertion, false if already queued or provider inactive.
	 */
	public static function add( $order_id, $service_id, $failure_reason = 'quota', $delay_seconds = 0 ) {
		global $wpdb;

		$table = self::get_table_name();

		if ( (int) $delay_seconds === 0 && 'delay' === $failure_reason ) {
			if ( 'simple' === $service_id ) {
				if ( class_exists( 'TrustScript_Review_Requests' ) ) {
					TrustScript_Review_Requests::process_order_products( absint( $order_id ), 'pending' );
				}
				return true;
			} else {
				$can_send = true;
				if ( class_exists( 'TrustScript_Review_Queue_Gating' ) ) {
					$can_send = TrustScript_Review_Queue_Gating::can_send_review_request( $order_id );
				}

				if ( $can_send ) {
					$service_manager = TrustScript_Service_Manager::get_instance();
					$providers       = $service_manager->get_active_providers();

					if ( isset( $providers[ $service_id ] ) ) {
						$provider = $providers[ $service_id ];
						$success  = $provider->retry_review_request( $order_id );

						if ( $success ) {
							return true;
						}

						$last_error = $provider->get_last_api_error();

						if ( 'quota' === $last_error || 'api_key_invalid' === $last_error ) {
							$delay_seconds  = 86400;
							$failure_reason = $last_error;
						} else {
							$delay_seconds  = 300;
							$failure_reason = $last_error ?: 'api_error';
						}
					} else {
						return false;
					}
				} else {
					$delay_seconds  = 0;
					$failure_reason = 'delay';
				}
			}
		}

		$scheduled_for = null;
		if ( $delay_seconds > 0 ) {
			$scheduled_for = wp_date( 'Y-m-d H:i:s', time() + $delay_seconds );
		}

		if ( null !== $scheduled_for ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO %i
					(order_id, service_id, failure_reason, retry_count, queued_at, scheduled_for, status)
				VALUES (%d, %s, %s, 0, %s, %s, 'pending')",
				$table,
				absint( $order_id ),
				sanitize_key( $service_id ),
				sanitize_key( $failure_reason ),
				wp_date( 'Y-m-d H:i:s' ),
				$scheduled_for
			)
		);
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO %i
					(order_id, service_id, failure_reason, retry_count, queued_at, status)
				VALUES (%d, %s, %s, 0, %s, 'pending')",
				$table,
				absint( $order_id ),
				sanitize_key( $service_id ),
				sanitize_key( $failure_reason ),
				wp_date( 'Y-m-d H:i:s' )
			)
		);
	}

		return (bool) $rows;
	}

	/**
	 * Remove an item from the queue by ID. Used for manual cleanup or if processing determines
	 * the item should no longer be retried.
	 *
	 * @since 1.0.0
	 * @param int $id
	 * @return bool
	 */
	public static function remove( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Remove an item from the queue by order ID.
	 * Used when an order is fully cancelled/refunded so we don't try to process it.
	 *
	 * @since 1.0.0
	 * @param int $order_id
	 * @return bool
	 */
	public static function remove_by_order( $order_id, $service_id = null ) {
		global $wpdb;
		$table = self::get_table_name();
		
		$where        = array( 'order_id' => absint( $order_id ) );
		$where_format = array( '%d' );

		if ( ! empty( $service_id ) ) {
			$where['service_id'] = $service_id;
			$where_format[]      = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->delete( $table, $where, $where_format );
	}

	/**
	 * Mark a queue item as completed by ID. Used when processing succeeds in the cron.
	 *
	 * @since 1.0.0
	 * @param int $id Queue item ID
	 * @return bool
	 */
	public static function mark_completed( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$table,
			array(
				'status'          => 'completed',
				'last_attempt_at' => wp_date( 'Y-m-d H:i:s' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark a queue item as completed by order ID and service ID. Used when processing succeeds
	 * outside of the cron (e.g. manual retry from admin or successful webhook notification).
	 *
	 * @since 1.0.0
	 * @param int    $order_id
	 * @param string $service_id
	 * @return bool
	 */
	public static function mark_completed_by_order( $order_id, $service_id ) {
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$table,
			array(
				'status'          => 'completed',
				'last_attempt_at' => wp_date( 'Y-m-d H:i:s' ),
			),
			array(
				'order_id'   => absint( $order_id ),
				'service_id' => sanitize_key( $service_id ),
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Reset a queue item to pending by ID, clearing retry count and last attempt timestamp.
	 * Used for manual retries from the admin.
	 *
	 * @since 1.0.0
	 * @param int $id
	 * @return bool
	 */
	public static function reset_to_pending( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, retry_count = %d, last_attempt_at = NULL WHERE id = %d',
				$table,
				'pending',
				0,
				absint( $id )
			)
		);
	}


	/**
	 * Registers the cron job for processing the queue.
	 *
	 * Ensures the scheduled event exists with the correct interval. If an
	 * existing event uses an invalid schedule, it is replaced.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_cron_job() {
		$timestamp = wp_next_scheduled( 'trustscript_process_queue_cron' );

		if ( $timestamp ) {
			$cron_array = _get_cron_array();
			$has_valid   = false;
			foreach ( $cron_array as $cron_events ) {
				if ( isset( $cron_events['trustscript_process_queue_cron'] ) ) {
					foreach ( $cron_events['trustscript_process_queue_cron'] as $event ) {
						if ( isset( $event['schedule'] ) && 'every_10_minutes' === $event['schedule'] ) {
							$has_valid = true;
							break 2;
						}
					}
				}
			}

			if ( ! $has_valid ) {
				wp_unschedule_event( $timestamp, 'trustscript_process_queue_cron' );
				$timestamp = false;
			}
		}

		if ( ! $timestamp ) {
			wp_schedule_event( time(), 'every_10_minutes', 'trustscript_process_queue_cron' );
		}
	}

	/**
	 * Hook callback to process the queue. Registered on 'trustscript_process_queue_cron' action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init_cron_hook() {
		add_action( 'trustscript_process_queue_cron', array( __CLASS__, 'process_queue_cron' ) );
		add_action( 'trustscript_queue_cleanup', array( __CLASS__, 'cleanup_old_items' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_custom_cron_intervals' ) );
	}

	/**
	 * Clear the cron job on plugin deactivation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function unregister_cron_job() {
		$timestamp = wp_next_scheduled( 'trustscript_process_queue_cron' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'trustscript_process_queue_cron' );
		}
		self::unregister_cleanup_cron();
	}

	/**
	 * Register the daily cleanup cron job.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_cleanup_cron() {
		if ( ! wp_next_scheduled( 'trustscript_queue_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'trustscript_queue_cleanup' );
		}
	}

	/**
	 * Clear the daily cleanup cron on plugin deactivation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function unregister_cleanup_cron() {
		$timestamp = wp_next_scheduled( 'trustscript_queue_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'trustscript_queue_cleanup' );
		}
	}

	/**
	 * Delete completed and failed queue rows older than 30 days.
	 *
	 * Keeps the table compact and index scans fast. Safe to call multiple
	 * times — rows without a last_attempt_at are never deleted.
	 *
	 * @since 1.0.0
	 * @return int Number of rows deleted.
	 */
	public static function cleanup_old_items() {
		global $wpdb;

		$table  = self::get_table_name();
		$cutoff = wp_date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i
				WHERE status IN ('completed','failed')
				AND last_attempt_at IS NOT NULL
				AND last_attempt_at < %s",
				$table,
				$cutoff
			)
		);
	}

	/**
	 * Register custom WP-Cron intervals.
	 *
	 * @since 1.0.0
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_custom_cron_intervals( $schedules ) {
		if ( ! isset( $schedules['every_10_minutes'] ) ) {
			$schedules['every_10_minutes'] = array(
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 10 minutes', 'trustscript' ),
			);
		}
		if ( ! isset( $schedules['every_6_hours'] ) ) {
			$schedules['every_6_hours'] = array(
				'interval' => 6 * HOUR_IN_SECONDS,
				'display'  => __( 'Every 6 hours', 'trustscript' ),
			);
		}
		return $schedules;
	}

	/**
	 * Cron callback to process the queue. Processes a batch of pending items that are ready (scheduled_for <= now).
	 *
	 * @since 1.0.0
	 * @return bool True if items were processed, false if skipped due to rate limit or empty queue.
	 */
	public static function process_queue_cron() {
		global $wpdb;

		$table = self::get_table_name();

		if ( ! self::table_exists() ) {
			return false;
		}

		// Shared lock used by both the cron and admin-triggered processing to prevent
		// overlapping runs. TTL = 15 minutes — enough for a full batch to finish.
		$lock_key = 'trustscript_queue_lock';

		if ( get_transient( $lock_key ) ) {
			return false;
		}

		set_transient( $lock_key, 1, 15 * MINUTE_IN_SECONDS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$ready_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i
				 WHERE status = 'pending'
				 AND (scheduled_for IS NULL OR scheduled_for <= %s)",
				$table,
				wp_date( 'Y-m-d H:i:s' )
			)
		);

		if ( $ready_count === 0 ) {
			delete_transient( $lock_key );
			return false;
		}

		$results = self::process_batch( 20, false );

		delete_transient( $lock_key );

		return isset( $results['processed'] ) && $results['processed'] > 0;
	}

	/**
	 * Count pending items.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public static function count_pending() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, 'pending' ) );
	}

	/**
	 * Count failed items.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public static function count_failed() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, 'failed' ) );
	}

	/**
	 * Count completed items.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public static function count_completed() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, 'completed' ) );
	}

	/**
	 * Count all items regardless of status.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * Count pending items that are scheduled for a future time (scheduled_for > now).
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public static function count_scheduled() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i
				 WHERE status = 'pending'
				 AND scheduled_for IS NOT NULL
				 AND scheduled_for > %s",
				$table,
				wp_date( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * Count pending items that are ready to be processed (scheduled_for IS NULL OR scheduled_for <= now).
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public static function count_ready() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i
				 WHERE status = 'pending'
				 AND (scheduled_for IS NULL OR scheduled_for <= %s)",
				$table,
				wp_date( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * Get queue items with pagination and optional status filter.
	 *
	 * @since 1.0.0
	 * @param int    $page     1-based page number. Default 1.
	 * @param int    $per_page Items per page. Default 25.
	 * @param string $status   'pending' | 'failed' | '' for all. Default 'pending'.
	 * @return array {
	 *     @type array $items Queued items.
	 *     @type int   $total Total matching items.
	 * }
	 */
	public static function get_items( $page = 1, $per_page = 25, $status = 'pending' ) {
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
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, $status )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s ORDER BY queued_at ASC LIMIT %d OFFSET %d',
					$table,
					$status,
					(int) $per_page,
					$offset
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY queued_at ASC LIMIT %d OFFSET %d',
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
	 * Process a batch of pending queue items.
	 *
	 * Fetches up to $batch_size pending items and sends review requests via the
	 * appropriate service provider. Halts early on quota exhaustion or invalid API key.
	 * Items exceeding 5 retries are marked permanently failed.
	 *
	 * @since 1.0.0
	 * @param int  $batch_size        Max items to process in one batch. Default 20.
	 * @param bool $exclude_scheduled If true, skip items with a scheduled_for date. Default false.
	 * @return array {
	 *     @type int $processed Number of items successfully sent.
	 *     @type int $skipped   Number of items skipped or batch-halted.
	 *     @type int $failed    Number of items permanently failed (5 retries exhausted).
	 *     @type int $waiting   Number of pending items still awaiting their scheduled time.
	 * }
	 */
	public static function process_batch( $batch_size = 20, $exclude_scheduled = false ) {
		global $wpdb;

		$table   = self::get_table_name();
		$results = array(
			'processed' => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'waiting'   => 0,
		);

		if ( $exclude_scheduled ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i
					WHERE status = 'pending' AND scheduled_for IS NULL
					ORDER BY queued_at ASC
					LIMIT %d",
					$table,
					$batch_size
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i
					WHERE status = 'pending'
					AND (scheduled_for IS NULL OR scheduled_for <= %s)
					ORDER BY queued_at ASC
					LIMIT %d",
					$table,
					wp_date( 'Y-m-d H:i:s' ),
					$batch_size
				),
				ARRAY_A
			);
		}

		if ( empty( $items ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$waiting_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i
					WHERE status = 'pending'
					AND scheduled_for > %s",
					$table,
					wp_date( 'Y-m-d H:i:s' )
				)
			);

			if ( $waiting_count > 0 ) {
				$results['waiting'] = $waiting_count;
			}

			return $results;
		}

		$service_manager = TrustScript_Service_Manager::get_instance();
		$providers       = $service_manager->get_active_providers();

		foreach ( $items as $item ) {
			$id          = (int) $item['id'];
			$order_id    = (int) $item['order_id'];
			$service_id  = $item['service_id'];
			$retry_count = (int) $item['retry_count'] + 1;

			if ( TrustScript_Order_Registry::is_published( $service_id, $order_id ) ) {
				self::mark_completed( $id );
				++$results['processed'];
				continue;
			}

			if ( class_exists( 'TrustScript_Review_Requests' ) ) {
				$rr_table = TrustScript_Review_Requests::get_table_name();
				if ( TrustScript_Review_Requests::table_exists() ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$any_non_published = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM %i WHERE order_id = %d AND status NOT IN ('already_published','published')",
							$rr_table,
							$order_id
						)
					);
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$total_tracked = $wpdb->get_var(
						$wpdb->prepare(
							'SELECT COUNT(*) FROM %i WHERE order_id = %d',
							$rr_table,
							$order_id
						)
					);
					if ( (int) $total_tracked > 0 && (int) $any_non_published === 0 ) {
						self::mark_completed( $id );
						++$results['skipped'];
						continue;
					}
				}
			}

			// Transition service type if API is disabled and Simple is enabled
			if ( 'simple' !== $service_id ) {
				$api_collection_on = ! empty( get_option( 'trustscript_api_review_collection_enabled' ) );
				$simple_review_on  = ! empty( get_option( 'trustscript_simple_review_enabled' ) );

				if ( ! $api_collection_on && $simple_review_on ) {
					$service_id = 'simple';
				}
			}

			if ( 'simple' === $service_id ) {
				// Move the order into Review Requests as 'pending' so the admin
				// can review it and send the email manually from the Review
				// Requests page. Do NOT auto-send the email here.
				if ( class_exists( 'TrustScript_Review_Requests' ) ) {
					TrustScript_Review_Requests::process_order_products( $order_id, 'pending' );
				}
				self::remove( $id );
				++$results['processed'];
				continue;
			}

			if ( ! isset( $providers[ $service_id ] ) ) {
				++$results['skipped'];
				continue;
			}

			if ( class_exists( 'TrustScript_Review_Queue_Gating' ) ) {
				if ( ! TrustScript_Review_Queue_Gating::can_send_review_request( $order_id ) ) {
					$blocking_reason = TrustScript_Review_Queue_Gating::get_blocking_reason( $order_id );

					if ( 'pending' === $blocking_reason ) {
						$given_at   = class_exists( 'TrustScript_Consent_Manager' ) ? TrustScript_Consent_Manager::get_order_consent_given_at( $order_id ) : '';
						$is_expired = false;

						if ( ! empty( $given_at ) ) {
							$given_timestamp = strtotime( $given_at );
							if ( false !== $given_timestamp && ( time() - $given_timestamp ) > ( 7 * DAY_IN_SECONDS ) ) {
								$is_expired = true;
							}
						} else {
							$order = wc_get_order( $order_id );
							if ( $order ) {
								$order_date = $order->get_date_created();
								if ( $order_date && ( time() - $order_date->getTimestamp() ) > ( 7 * DAY_IN_SECONDS ) ) {
									$is_expired = true;
								}
							}
						}

						if ( ! $is_expired ) {
							$hold_until = wp_date( 'Y-m-d H:i:s', time() + 6 * HOUR_IN_SECONDS );
							
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->update(
								$table,
								array( 'scheduled_for' => $hold_until ),
								array( 'id' => $id ),
								array( '%s' ),
								array( '%d' )
							);
							++$results['skipped'];
							continue;
						}
					}

					self::mark_completed( $id );
					if ( class_exists( 'TrustScript_Review_Requests' ) ) {
						TrustScript_Review_Requests::process_order_products( $order_id, 'opt-out' );
					}
					++$results['processed'];
					continue;
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'last_attempt_at' => wp_date( 'Y-m-d H:i:s' ),
					'retry_count'     => $retry_count,
				),
				array( 'id' => $id ),
				array( '%s', '%d' ),
				array( '%d' )
			);

			$provider = $providers[ $service_id ];
			$success  = $provider->retry_review_request( $order_id );

			if ( $success ) {
				self::mark_completed( $id );
				++$results['processed'];
			} else {
				$last_error = $provider->get_last_api_error();

				if ( 'quota' === $last_error ) {
					$hold_until = wp_date( 'Y-m-d H:i:s', time() + 86400 );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$table,
						array( 'scheduled_for' => $hold_until ),
						array( 'id' => $id ),
						array( '%s' ),
						array( '%d' )
					);
					++$results['skipped'];
					break;
				}

				if ( 'api_key_invalid' === $last_error ) {
					++$results['skipped'];
					break;
				}

				if ( $retry_count >= 5 ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$table,
						array( 'status' => 'failed' ),
						array( 'id' => $id ),
						array( '%s' ),
						array( '%d' )
					);
					++$results['failed'];
				} else {
					++$results['skipped'];
				}
			}

		}

		return $results;
	}
}
