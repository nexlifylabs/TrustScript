<?php
/**
 * TrustScript Review Guard — Content Moderation
 *
 * Runs every incoming review through a three-layer moderation check before it
 * is written to the database, regardless of how the review was submitted.
 *
 * No review is auto-published if it matches a word list, even when the
 * trustscript_auto_publish option is enabled.
 *
 * @package TrustScript
 * @since   1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TrustScript_Review_Guard {

	/**
	 * Indicates no word-list match was found. The existing comment_approved value
	 * driven by the trustscript_auto_publish option is left unchanged.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const STATUS_APPROVED = 'approved';

	/**
	 * Indicates a match with moderation_keys or the TrustScript keyword blocklist.
	 * comment_approved is forced to 0, sending reviews to the Pending queue.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const STATUS_HOLD = 'hold';

	/**
	 * Indicates a match with disallowed_keys for hard blocking.
	 * comment_approved is forced to 'spam', sending reviews to the Spam queue.
	 *
	 * @since 1.0.1
	 * @var string
	 */
	const STATUS_SPAM = 'spam';

	/**
	 * Runs all moderation layers against a single review.
	 *
	 * @since 1.0.1
	 * @param string $author  Reviewer display name.
	 * @param string $email   Reviewer e-mail address.
	 * @param string $content Review body text.
	 * @param string $ip      Submitter IP address. Pass empty string if unavailable.
	 * @return string One of STATUS_APPROVED, STATUS_HOLD, or STATUS_SPAM.
	 */
	public static function run_moderation_check( $author, $email, $content, $ip = '' ) {

		if ( function_exists( 'wp_check_comment_disallowed_list' ) ) {
			if ( wp_check_comment_disallowed_list( $author, $email, '', $content, $ip, '' ) ) {
				return self::STATUS_SPAM;
			}
		}

		$mod_keys = trim( get_option( 'moderation_keys', '' ) );
		if ( ! empty( $mod_keys ) ) {
			$words    = explode( "\n", $mod_keys );
			$haystack = $author . ' ' . $email . ' ' . $content . ' ' . $ip;
			foreach ( $words as $word ) {
				$word = trim( $word );
				if ( '' === $word ) {
					continue;
				}
				$pattern = sprintf( '#%s#iu', preg_quote( $word, '#' ) );
				if ( preg_match( $pattern, $haystack ) ) {
					return self::STATUS_HOLD;
				}
			}
		}

		$raw_blocklist = get_option( 'trustscript_review_blocked_words', '' );

		if ( ! empty( $raw_blocklist ) ) {
			$blocked_words = array_filter(
				array_map( 'trim', explode( "\n", $raw_blocklist ) ),
				static function ( $word ) {
					return '' !== $word;
				}
			);

			if ( ! empty( $blocked_words ) ) {
				$haystack = mb_strtolower( $author . ' ' . $email . ' ' . $content );

				foreach ( $blocked_words as $word ) {
					if ( false !== mb_strpos( $haystack, mb_strtolower( $word ) ) ) {
						return self::STATUS_HOLD;
					}
				}
			}
		}

		return self::STATUS_APPROVED;
	}

	/**
	 * Applies moderation result directly to a comment_data array.
	 *
	 * @since 1.0.1
	 * @param array $comment_data WordPress comment data array passed to
	 *                            wp_insert_comment() or wp_update_comment().
	 * @return array The same array with comment_approved potentially overridden.
	 */
	public static function apply_to_comment_data( array $comment_data ) {
		$author  = isset( $comment_data['comment_author'] )       ? (string) $comment_data['comment_author']       : '';
		$email   = isset( $comment_data['comment_author_email'] ) ? (string) $comment_data['comment_author_email'] : '';
		$content = isset( $comment_data['comment_content'] )      ? (string) $comment_data['comment_content']      : '';

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		$status = self::run_moderation_check( $author, $email, $content, $ip );

		switch ( $status ) {
			case self::STATUS_SPAM:
				$comment_data['comment_approved'] = 'spam';
				break;

			case self::STATUS_HOLD:
				$comment_data['comment_approved'] = 0;
				break;
		}

		return $comment_data;
	}

	/**
	 * Renders the Keyword Blocklist admin page.
	 *
	 * @since 1.0.1
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		?>
		<div class="wrap trustscript-admin-wrap">
			<h1><?php esc_html_e( 'Keyword Blocklist', 'trustscript' ); ?></h1>
			<p><?php esc_html_e( 'Control which reviews are held for manual approval or marked as spam based on their content.', 'trustscript' ); ?></p>
			
			<div class="trustscript-card" style="margin-top: 20px;">
				<h2><?php esc_html_e( 'Review Moderation', 'trustscript' ); ?></h2>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="trustscript_review_blocked_words">
								<?php esc_html_e( 'Keyword Blocklist', 'trustscript' ); ?>
							</label>
						</th>
						<td>
							<textarea
								id="trustscript_review_blocked_words"
								name="trustscript_review_blocked_words"
								rows="8"
								cols="50"
								class="large-text code"
								placeholder="<?php esc_attr_e( 'one keyword or phrase per line', 'trustscript' ); ?>"
							><?php echo esc_textarea( get_option( 'trustscript_review_blocked_words', '' ) ); ?></textarea>
					
							<p class="description">
								<?php esc_html_e( 'Reviews whose author name, email address, or text contain any of these keywords or phrases will be held for manual approval in Products → Reviews → Pending.', 'trustscript' ); ?>
								<br>
								<?php esc_html_e( 'One keyword or phrase per line. Case-insensitive. This list is separate from WordPress\'s own moderation lists in Settings → Discussion.', 'trustscript' ); ?>
							</p>
					
							<p class="description">
								<strong><?php esc_html_e( 'How moderation is applied on every review', 'trustscript' ); ?></strong>
								<br>
								<?php
								printf(
									/* translators: 1: Settings link opening tag, 2: Settings link closing tag */
									esc_html__( '1. %1$sDisallowed Comment Keys%2$s → review moved to Spam.', 'trustscript' ),
									'<a href="' . esc_url( admin_url( 'options-discussion.php' ) ) . '">',
									'</a>'
								);
								?>
								<br>
								<?php
								printf(
									/* translators: 1: Settings link opening tag, 2: Settings link closing tag */
									esc_html__( '2. %1$sComment Moderation Keywords%2$s → review held for approval.', 'trustscript' ),
									'<a href="' . esc_url( admin_url( 'options-discussion.php' ) ) . '">',
									'</a>'
								);
								?>
								<br>
								<?php esc_html_e( '3. TrustScript Keyword Blocklist → review held for approval.', 'trustscript' ); ?>
							</p>
						</td>
					</tr>
				
	
				<p class="submit">
					<button type="button" id="trustscript-save-moderation-settings" class="trustscript-btn trustscript-btn-primary">
						<?php esc_html_e( 'Save Blocklist', 'trustscript' ); ?>
					</button>
				</p>
				<div id="trustscript-moderation-save-status" style="margin-top: 10px;"></div>
			</div>
		</div>
		<?php
	}
}