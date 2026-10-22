<?php
/**
 * The six format tiles, each with an image, the name and the item count.
 *
 * The tile image is the newest product's image in that format, so the homepage keeps itself current with no extra
 * work in the admin.
 *
 * @package orwo
 */

$orwo_formats = orwo_format_links();
if ( ! $orwo_formats ) {
	return;
}
?>
<section class="orwo-wrap orwo-tiles">
	<h2 class="orwo-section__title"><?php esc_html_e( 'Browse by format', 'orwo' ); ?></h2>
	<ul class="orwo-tiles__list">
		<?php foreach ( $orwo_formats as $orwo_format ) : ?>
			<?php
			$orwo_count  = orwo_format_count( $orwo_format['slug'] );
			$orwo_latest = orwo_products(
				array(
					'format' => $orwo_format['slug'],
					'limit'  => 1,
				)
			);
			$orwo_image  = 0;
			if ( $orwo_latest->have_posts() ) {
				$orwo_image = (int) get_post_thumbnail_id( $orwo_latest->posts[0]->ID );
			}
			?>
			<li class="orwo-tile">
				<a href="<?php echo esc_url( $orwo_format['url'] ); ?>">
					<span class="orwo-tile__media">
						<?php if ( $orwo_image ) : ?>
							<?php
							echo wp_get_attachment_image(
								$orwo_image,
								'orwo-card',
								false,
								array(
									'class'    => 'orwo-tile__image',
									'alt'      => '',
									'loading'  => 'lazy',
									'decoding' => 'async',
									'sizes'    => '(max-width: 640px) 46vw, (max-width: 1024px) 31vw, 200px',
								)
							);
							?>
						<?php else : ?>
							<span class="orwo-tile__placeholder" aria-hidden="true"></span>
						<?php endif; ?>
					</span>
					<span class="orwo-tile__name"><?php echo esc_html( $orwo_format['label'] ); ?></span>
					<span class="orwo-tile__count">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of items. */
								_n( '%s item', '%s items', $orwo_count, 'orwo' ),
								number_format_i18n( $orwo_count )
							)
						);
						?>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
