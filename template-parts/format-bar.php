<?php
/**
 * The format bar, with a mega menu per format.
 *
 * The top genres and creators in each panel are generated from the catalogue by product count, so the menu keeps
 * itself current. The panel opens on hover and on keyboard focus.
 *
 * @package orwo
 */

$orwo_formats = orwo_format_links();
if ( ! $orwo_formats ) {
	return;
}

$orwo_current = orwo_current_view();
?>
<nav class="orwo-formats" aria-label="<?php esc_attr_e( 'Formats', 'orwo' ); ?>" data-orwo-formats>
	<div class="orwo-wrap orwo-formats__inner">
		<ul class="orwo-formats__list">
			<?php foreach ( $orwo_formats as $orwo_format ) : ?>
				<?php
				$orwo_is_current = 'format' === $orwo_current['kind'] && $orwo_current['format'] === $orwo_format['slug'];
				$orwo_genres     = orwo_top_terms( 'orwo_genre', $orwo_format['slug'], 6 );
				$orwo_creators   = orwo_top_terms( 'orwo_creator', $orwo_format['slug'], 6 );
				$orwo_panel_id   = 'orwo-mega-' . $orwo_format['slug'];
				$orwo_has_panel  = $orwo_genres || $orwo_creators;
				?>
				<li class="orwo-formats__item<?php echo $orwo_has_panel ? ' has-panel' : ''; ?>" data-orwo-mega>
					<a href="<?php echo esc_url( $orwo_format['url'] ); ?>"
						<?php echo $orwo_is_current ? ' aria-current="page"' : ''; ?>
						<?php echo $orwo_has_panel ? ' aria-expanded="false" aria-controls="' . esc_attr( $orwo_panel_id ) . '"' : ''; ?>>
						<?php echo esc_html( $orwo_format['label'] ); ?>
					</a>

					<?php if ( $orwo_has_panel ) : ?>
						<div class="orwo-mega" id="<?php echo esc_attr( $orwo_panel_id ); ?>" hidden>
							<div class="orwo-wrap orwo-mega__inner">
								<?php if ( $orwo_genres ) : ?>
									<div class="orwo-mega__column">
										<h3><?php esc_html_e( 'Genres', 'orwo' ); ?></h3>
										<ul>
											<?php foreach ( $orwo_genres as $orwo_term ) : ?>
												<li><a href="<?php echo esc_url( (string) get_term_link( $orwo_term ) ); ?>"><?php echo esc_html( $orwo_term->name ); ?></a></li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>

								<?php if ( $orwo_creators ) : ?>
									<div class="orwo-mega__column">
										<h3><?php esc_html_e( 'Creators', 'orwo' ); ?></h3>
										<ul>
											<?php foreach ( $orwo_creators as $orwo_term ) : ?>
												<li><a href="<?php echo esc_url( (string) get_term_link( $orwo_term ) ); ?>"><?php echo esc_html( $orwo_term->name ); ?></a></li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endif; ?>

								<?php
								$orwo_featured = orwo_products(
									array(
										'format' => $orwo_format['slug'],
										'limit'  => 1,
										'view'   => 'limited',
									)
								);
								if ( ! $orwo_featured->have_posts() ) {
									$orwo_featured = orwo_products(
										array(
											'format' => $orwo_format['slug'],
											'limit'  => 1,
										)
									);
								}
								?>
								<?php if ( $orwo_featured->have_posts() ) : ?>
									<div class="orwo-mega__feature">
										<h3><?php esc_html_e( 'Featured', 'orwo' ); ?></h3>
										<?php echo orwo_product_card( (int) $orwo_featured->posts[0]->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the card template. ?>
									</div>
								<?php endif; ?>

								<p class="orwo-mega__all">
									<a href="<?php echo esc_url( $orwo_format['url'] ); ?>">
										<?php
										/* translators: %s: format name. */
										echo esc_html( sprintf( __( 'View all %s', 'orwo' ), $orwo_format['label'] ) );
										?>
									</a>
								</p>
							</div>
						</div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>

			<li class="orwo-formats__item orwo-formats__item--extra">
				<a href="<?php echo esc_url( user_trailingslashit( home_url( '/new' ) ) ); ?>" <?php echo 'new' === $orwo_current['kind'] ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'New', 'orwo' ); ?></a>
			</li>
			<li class="orwo-formats__item orwo-formats__item--extra">
				<a href="<?php echo esc_url( user_trailingslashit( home_url( '/sale' ) ) ); ?>" <?php echo 'sale' === $orwo_current['kind'] ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Sale', 'orwo' ); ?></a>
			</li>
			<li class="orwo-formats__item orwo-formats__item--ask">
				<button type="button" data-orwo-chat-open aria-controls="orwo-chat-panel" aria-expanded="false"><?php esc_html_e( 'Ask ORWO', 'orwo' ); ?></button>
			</li>
		</ul>
	</div>
</nav>
