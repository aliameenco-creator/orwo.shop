<?php
/**
 * The product card, used everywhere: on the 4:5 tile, with small format and condition labels, title, creator and
 * price. The second image loads only on hover.
 *
 * @package orwo
 */

$orwo_id = isset( $args['product_id'] ) ? (int) $args['product_id'] : get_the_ID();
if ( ! $orwo_id ) {
	return;
}

$orwo_format    = orwo_product_format( $orwo_id );
$orwo_condition = orwo_product_condition( $orwo_id );
$orwo_creator   = orwo_creator_name( $orwo_id );
$orwo_hover     = orwo_hover_image_url( $orwo_id );
$orwo_badges    = orwo_product_badges( $orwo_id );
$orwo_sold_out  = orwo_is_sold_out( $orwo_id );
?>
<article class="orwo-card<?php echo $orwo_sold_out ? ' is-sold-out' : ''; ?>">
	<a class="orwo-card__link" href="<?php echo esc_url( (string) get_permalink( $orwo_id ) ); ?>">
		<span class="orwo-card__tile"<?php echo $orwo_hover ? ' data-hover="' . esc_url( $orwo_hover ) . '"' : ''; ?>>
			<?php if ( has_post_thumbnail( $orwo_id ) ) : ?>
				<?php
				echo get_the_post_thumbnail(
					$orwo_id,
					'orwo-card',
					array(
						'class'    => 'orwo-card__image',
						'loading'  => 'lazy',
						'decoding' => 'async',
						'alt'      => get_the_title( $orwo_id ),
					)
				);
				?>
			<?php else : ?>
				<span class="orwo-card__placeholder" aria-hidden="true"></span>
			<?php endif; ?>

			<?php if ( $orwo_badges ) : ?>
				<span class="orwo-card__badges">
					<?php foreach ( $orwo_badges as $orwo_badge ) : ?>
						<span class="orwo-badge orwo-badge--<?php echo esc_attr( $orwo_badge['kind'] ); ?>"><?php echo esc_html( $orwo_badge['text'] ); ?></span>
					<?php endforeach; ?>
				</span>
			<?php endif; ?>
		</span>

		<span class="orwo-card__meta">
			<?php if ( $orwo_format ) : ?>
				<span class="orwo-card__format"><?php echo esc_html( Orwo_Formats::label( $orwo_format ) ); ?></span>
			<?php endif; ?>
			<?php if ( $orwo_condition ) : ?>
				<span class="orwo-card__condition"><?php echo esc_html( $orwo_condition ); ?></span>
			<?php endif; ?>
		</span>

		<h3 class="orwo-card__title"><?php echo esc_html( get_the_title( $orwo_id ) ); ?></h3>

		<?php if ( $orwo_creator ) : ?>
			<p class="orwo-card__creator"><?php echo esc_html( $orwo_creator ); ?></p>
		<?php endif; ?>

		<p class="orwo-card__price<?php echo orwo_is_on_sale( $orwo_id ) ? ' is-sale' : ''; ?>">
			<?php echo wp_kses_post( orwo_price_html( $orwo_id ) ); ?>
		</p>
	</a>
</article>
