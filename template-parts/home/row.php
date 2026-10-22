<?php
/**
 * One automatic row of product cards. It hides itself when there is nothing to show.
 *
 * @package orwo
 */

$orwo_title = isset( $args['title'] ) ? (string) $args['title'] : '';
$orwo_view  = isset( $args['view'] ) ? (string) $args['view'] : '';
$orwo_limit = isset( $args['limit'] ) ? (int) $args['limit'] : 8;
$orwo_url   = isset( $args['url'] ) ? (string) $args['url'] : '';

if ( ! orwo_core_active() ) {
	return;
}

$orwo_query = orwo_products(
	array(
		'view'  => $orwo_view,
		'limit' => $orwo_limit,
	)
);

if ( ! $orwo_query->have_posts() ) {
	return;
}
?>
<section class="orwo-wrap orwo-row">
	<div class="orwo-row__head">
		<h2><?php echo esc_html( $orwo_title ); ?></h2>
		<?php if ( $orwo_url ) : ?>
			<a href="<?php echo esc_url( $orwo_url ); ?>"><?php esc_html_e( 'View all', 'orwo' ); ?></a>
		<?php endif; ?>
	</div>
	<div class="orwo-grid orwo-grid--row">
		<?php foreach ( $orwo_query->posts as $orwo_post ) : ?>
			<?php echo orwo_product_card( (int) $orwo_post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the card template. ?>
		<?php endforeach; ?>
	</div>
</section>
