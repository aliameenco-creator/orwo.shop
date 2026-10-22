<?php
/**
 * The editorial band: two columns, an image and a short hand-written text about one collection or staff pick.
 *
 * @package orwo
 */

$orwo_heading = (string) orwo_home_option( 'editorial_heading' );
$orwo_text    = (string) orwo_home_option( 'editorial_text' );
$orwo_image   = (int) orwo_home_option( 'editorial_image' );
$orwo_link    = (string) orwo_home_option( 'editorial_link' );

if ( '' === $orwo_heading && '' === $orwo_text ) {
	return;
}
?>
<section class="orwo-editorial">
	<div class="orwo-wrap orwo-editorial__inner">
		<div class="orwo-editorial__media">
			<?php if ( $orwo_image && wp_attachment_is_image( $orwo_image ) ) : ?>
				<?php
				echo wp_get_attachment_image(
					$orwo_image,
					'orwo-tile',
					false,
					array(
						'class'    => 'orwo-editorial__image',
						'alt'      => '',
						'loading'  => 'lazy',
						'decoding' => 'async',
						'sizes'    => '(max-width: 900px) 92vw, 600px',
					)
				);
				?>
			<?php endif; ?>
		</div>
		<div class="orwo-editorial__text">
			<h2><?php echo esc_html( $orwo_heading ); ?></h2>
			<p><?php echo esc_html( $orwo_text ); ?></p>
			<?php if ( '' !== $orwo_link ) : ?>
				<p>
					<a class="orwo-link" href="<?php echo esc_url( 0 === strpos( $orwo_link, '/' ) ? home_url( $orwo_link ) : $orwo_link ); ?>">
						<?php esc_html_e( 'Read more', 'orwo' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</div>
</section>
