<?php
/**
 * Simple Review - Login/Register Prompt Template
 *
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$trustscript_login_url    = esc_url( $data['login_url'] );
$trustscript_register_url = esc_url( $data['register_url'] );
$trustscript_can_register = (bool) $data['can_register'];
?>
<div class="trustscript-review-form trustscript-review-form--login">
	<div class="trustscript-login-icon">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/>
			<polyline points="10 17 15 12 10 7"/>
			<line x1="15" y1="12" x2="3" y2="12"/>
		</svg>
	</div>
	<h2 class="trustscript-login-heading"><?php esc_html_e( 'Log in to submit your review', 'trustscript' ); ?></h2>
	<p class="trustscript-login-sub">
		<?php esc_html_e( 'Please log in with the email address used for your order.', 'trustscript' ); ?>
	</p>
	<div class="trustscript-login-actions">
		<a href="<?php echo esc_url( $trustscript_login_url ); ?>" class="trustscript-btn-login trustscript-btn-login-primary">
			<?php esc_html_e( 'Log In', 'trustscript' ); ?>
		</a>
		<?php if ( $trustscript_can_register ) : ?>
		<a href="<?php echo esc_url( $trustscript_register_url ); ?>" class="trustscript-btn-login trustscript-btn-login-secondary">
			<?php esc_html_e( 'Create Account', 'trustscript' ); ?>
		</a>
		<?php endif; ?>
	</div>
</div>
