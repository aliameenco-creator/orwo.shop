<?php
/**
 * The full-screen phone menu: the six formats as large rows that expand into genres.
 *
 * @package orwo
 */

$orwo_formats = orwo_format_links();
?>
<div class="orwo-mobile" id="orwo-mobile-menu" hidden>
	<div class="orwo-mobile__head">
		<span class="orwo-mobile__title"><?php esc_html_e( 'Menu', 'orwo' ); ?></span>
		<button type="button" class="orwo-mobile__close" data-orwo-menu-close>
			<span class="orwo-visually-hidden"><?php esc_html_e( 'Close the menu', 'orwo' ); ?></span>
			<svg aria-hidden="true" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m3.5 3.5 11 11M14.5 3.5l-11 11"></path></svg>
		</button>
	</div>

	<nav class="orwo-mobile__nav" aria-label="<?php esc_attr_e( 'Shop', 'orwo' ); ?>">
		<ul>
			<?php foreach ( $orwo_formats as $orwo_format ) : ?>
				<?php $orwo_genres = orwo_top_terms( 'orwo_genre', $orwo_format['slug'], 8 ); ?>
				<li>
					<div class="orwo-mobile__row">
						<a href="<?php echo esc_url( $orwo_format['url'] ); ?>"><?php echo esc_html( $orwo_format['label'] ); ?></a>
						<?php if ( $orwo_genres ) : ?>
							<button type="button" class="orwo-mobile__expand" data-orwo-expand aria-expanded="false">
								<span class="orwo-visually-hidden">
									<?php
									/* translators: %s: format name. */
									echo esc_html( sprintf( __( 'Show genres in %s', 'orwo' ), $orwo_format['label'] ) );
									?>
								</span>
								<svg aria-hidden="true" width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m3 5.5 4 4 4-4"></path></svg>
							</button>
						<?php endif; ?>
					</div>
					<?php if ( $orwo_genres ) : ?>
						<ul class="orwo-mobile__sub" hidden>
							<?php foreach ( $orwo_genres as $orwo_term ) : ?>
								<li><a href="<?php echo esc_url( (string) get_term_link( $orwo_term ) ); ?>"><?php echo esc_html( $orwo_term->name ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
			<li><div class="orwo-mobile__row"><a href="<?php echo esc_url( user_trailingslashit( home_url( '/new' ) ) ); ?>"><?php esc_html_e( 'New arrivals', 'orwo' ); ?></a></div></li>
			<li><div class="orwo-mobile__row"><a href="<?php echo esc_url( user_trailingslashit( home_url( '/sale' ) ) ); ?>"><?php esc_html_e( 'Sale', 'orwo' ); ?></a></div></li>
			<li><div class="orwo-mobile__row"><a href="<?php echo esc_url( orwo_shop_url() ); ?>"><?php esc_html_e( 'All products', 'orwo' ); ?></a></div></li>
		</ul>
	</nav>

	<div class="orwo-mobile__foot">
		<button type="button" class="orwo-button orwo-button--full" data-orwo-chat-open aria-controls="orwo-chat-panel"><?php esc_html_e( 'Ask ORWO', 'orwo' ); ?></button>
		<?php if ( has_nav_menu( 'help' ) ) : ?>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'help',
					'container'      => false,
					'menu_class'     => 'orwo-mobile__help',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		<?php endif; ?>
	</div>
</div>
