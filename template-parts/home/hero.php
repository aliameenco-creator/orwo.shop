<?php
/**
 * The hero: one editorial photograph, one h1, one support line, then the chat entry bar with its quick-start chips.
 *
 * The chips are plain links to filtered views, so they work with JavaScript switched off; with it, they open Ask
 * ORWO with that request already answered.
 *
 * @package orwo
 */

$orwo_image   = (int) orwo_home_option( 'hero_image' );
$orwo_heading = (string) orwo_home_option( 'hero_heading' );
$orwo_text    = (string) orwo_home_option( 'hero_text' );
$orwo_link    = (string) orwo_home_option( 'hero_link_url' );
$orwo_label   = (string) orwo_home_option( 'hero_link_text' );
$orwo_chips   = orwo_chat_chips();
?>
<section class="orwo-hero<?php echo $orwo_image ? ' has-image' : ''; ?>">
	<?php if ( $orwo_image && wp_attachment_is_image( $orwo_image ) ) : ?>
		<div class="orwo-hero__media">
			<?php
			echo wp_get_attachment_image(
				$orwo_image,
				'full',
				false,
				array(
					'class'         => 'orwo-hero__image',
					'alt'           => '',
					'fetchpriority' => 'high',
					'decoding'      => 'sync',
					'sizes'         => '100vw',
				)
			);
			?>
		</div>
	<?php endif; ?>

	<div class="orwo-wrap orwo-hero__inner">
		<h1 class="orwo-hero__heading"><?php echo esc_html( $orwo_heading ); ?></h1>
		<?php if ( '' !== $orwo_text ) : ?>
			<p class="orwo-hero__text"><?php echo esc_html( $orwo_text ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $orwo_label && '' !== $orwo_link ) : ?>
			<p class="orwo-hero__cta">
				<a class="orwo-button" href="<?php echo esc_url( 0 === strpos( $orwo_link, '/' ) ? home_url( $orwo_link ) : $orwo_link ); ?>">
					<?php echo esc_html( $orwo_label ); ?>
				</a>
			</p>
		<?php endif; ?>

		<div class="orwo-hero__chat">
			<p class="orwo-hero__prompt"><?php echo esc_html( (string) orwo_home_option( 'chat_prompt' ) ); ?></p>
			<form class="orwo-hero__bar" data-orwo-chat-start action="<?php echo esc_url( user_trailingslashit( home_url( '/search' ) ) ); ?>" method="get">
				<label class="orwo-visually-hidden" for="orwo-hero-ask"><?php esc_html_e( 'Tell us what you are after', 'orwo' ); ?></label>
				<input type="text" id="orwo-hero-ask" name="q" autocomplete="off" placeholder="<?php esc_attr_e( 'A gift for a film fan, jazz on vinyl…', 'orwo' ); ?>">
				<button type="submit" class="orwo-button"><?php esc_html_e( 'Ask ORWO', 'orwo' ); ?></button>
			</form>

			<?php if ( $orwo_chips ) : ?>
				<ul class="orwo-hero__chips">
					<?php foreach ( $orwo_chips as $orwo_chip ) : ?>
						<li>
							<a class="orwo-chip orwo-chip--start"
								href="<?php echo esc_url( add_query_arg( 'q', $orwo_chip, user_trailingslashit( home_url( '/search' ) ) ) ); ?>"
								data-orwo-chip="<?php echo esc_attr( $orwo_chip ); ?>">
								<?php echo esc_html( $orwo_chip ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>
