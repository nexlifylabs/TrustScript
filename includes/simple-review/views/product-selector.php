<?php
/**
 * Simple Review — Multi Product Selector Template
 * 
 * @package TrustScript
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$trustscript_products = (array) $data['products'];
?>
<div class="trustscript-product-selector" id="trustscript-product-selector">
	<div class="trustscript-section-title trustscript-section-title--center">
		<?php esc_html_e( 'Which product would you like to review?', 'trustscript' ); ?>
	</div>
	<div class="trustscript-product-list">
		<?php
		foreach ( $trustscript_products as $trustscript_product ) :
			$trustscript_image_id  = $trustscript_product->get_image_id();
			$trustscript_image_url = $trustscript_image_id ? wp_get_attachment_image_url( $trustscript_image_id, 'medium_large' ) : wc_placeholder_img_src();
			?>
		<div class="trustscript-product-item">
			<img src="<?php echo esc_url( $trustscript_image_url ); ?>" alt="" class="trustscript-product-item-img">
			<div class="trustscript-product-item-body">
				<h3 class="trustscript-product-item-name"><?php echo esc_html( $trustscript_product->get_name() ); ?></h3>
				<button type="button"
					class="trustscript-btn-ghost trustscript-btn-next"
					data-product-id="<?php echo esc_attr( $trustscript_product->get_id() ); ?>"
					data-product-name="<?php echo esc_attr( $trustscript_product->get_name() ); ?>"
					data-product-image="<?php echo esc_url( $trustscript_image_url ); ?>">
					<?php esc_html_e( 'Write Review', 'trustscript' ); ?>
				</button>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</div>
