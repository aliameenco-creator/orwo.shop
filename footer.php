<?php
/**
 * Footer: four columns, the newsletter sign-up, then payment methods, legal links and the copyright line.
 *
 * The year is rendered at runtime, never written into a template.
 *
 * @package orwo
 */

?>
</main>

<footer class="orwo-footer">
	<div class="orwo-wrap orwo-footer__columns">
		<div class="orwo-footer__column">
			<h2><?php esc_html_e( 'Shop', 'orwo' ); ?></h2>
			<ul>
				<?php foreach ( orwo_format_links() as $orwo_format ) : ?>
					<li><a href="<?php echo esc_url( $orwo_format['url'] ); ?>"><?php echo esc_html( $orwo_format['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="orwo-footer__column">
			<h2><?php esc_html_e( 'Help', 'orwo' ); ?></h2>
			<?php if ( has_nav_menu( 'help' ) ) : ?>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'help',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
				?>
			<?php else : ?>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/shipping/' ) ); ?>"><?php esc_html_e( 'Shipping', 'orwo' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/returns/' ) ); ?>"><?php esc_html_e( 'Returns', 'orwo' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/track-order/' ) ); ?>"><?php esc_html_e( 'Track order', 'orwo' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php esc_html_e( 'FAQ', 'orwo' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'orwo' ); ?></a></li>
				</ul>
			<?php endif; ?>
		</div>

		<div class="orwo-footer__column">
			<h2><?php esc_html_e( 'About', 'orwo' ); ?></h2>
			<?php if ( has_nav_menu( 'about' ) ) : ?>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'about',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
				?>
			<?php else : ?>
				<ul><li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About ORWO', 'orwo' ); ?></a></li></ul>
			<?php endif; ?>
		</div>

		<div class="orwo-footer__column orwo-footer__column--signup">
			<h2><?php esc_html_e( 'Updates', 'orwo' ); ?></h2>
			<p><?php esc_html_e( 'New arrivals and the occasional find, by email.', 'orwo' ); ?></p>
			<form class="orwo-signup" data-orwo-signup>
				<label class="orwo-visually-hidden" for="orwo-signup-email"><?php esc_html_e( 'Your email address', 'orwo' ); ?></label>
				<input type="email" id="orwo-signup-email" name="email" required placeholder="<?php esc_attr_e( 'you@example.com', 'orwo' ); ?>" autocomplete="email">
				<span class="orwo-signup__trap" aria-hidden="true">
					<label for="orwo-signup-website"><?php esc_html_e( 'Leave this field empty', 'orwo' ); ?></label>
					<input type="text" id="orwo-signup-website" name="website" tabindex="-1" autocomplete="off">
				</span>
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'orwo_signup' ) ); ?>">
				<button type="submit" class="orwo-button"><?php esc_html_e( 'Sign up', 'orwo' ); ?></button>
				<p class="orwo-signup__status" role="status" aria-live="polite"></p>
			</form>
		</div>
	</div>

	<div class="orwo-wrap orwo-footer__base">
		<p class="orwo-footer__pay"><?php esc_html_e( 'Card and PayPal accepted. Payments are handled by the payment provider.', 'orwo' ); ?></p>

		<?php if ( has_nav_menu( 'legal' ) ) : ?>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'legal',
					'container'      => false,
					'menu_class'     => 'orwo-footer__legal',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		<?php endif; ?>

		<p class="orwo-footer__copy">
			<?php
			/* translators: 1: current year, 2: site name. */
			echo esc_html( sprintf( __( '&copy; %1$s %2$s', 'orwo' ), orwo_current_year(), (string) get_bloginfo( 'name' ) ) );
			?>
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
