<?php
/**
 * Every catalogue listing: /shop/, a format, a genre, a creator, a label, /new/, /sale/ and /search/.
 *
 * One template serves them all because they differ only in their heading and which filters are offered.
 *
 * @package orwo
 */

get_header();

$orwo_view    = orwo_current_view();
$orwo_filters = orwo_core_active() ? orwo_current_filters() : array();
$orwo_counts  = orwo_core_active() ? orwo_facet_counts( $orwo_filters, $orwo_view ) : array();
$orwo_chips   = orwo_core_active() ? orwo_filter_chips( $orwo_filters, $orwo_view ) : array();
$orwo_total   = (int) $GLOBALS['wp_query']->found_posts;
?>

<div class="orwo-wrap orwo-archive__head">
	<h1><?php echo esc_html( $orwo_view['title'] ); ?></h1>
	<?php if ( $orwo_view['subtitle'] ) : ?>
		<p class="orwo-archive__subtitle"><?php echo esc_html( $orwo_view['subtitle'] ); ?></p>
	<?php elseif ( $orwo_view['description'] ) : ?>
		<p class="orwo-archive__subtitle"><?php echo esc_html( $orwo_view['description'] ); ?></p>
	<?php endif; ?>

	<?php if ( 'search' === $orwo_view['kind'] ) : ?>
		<div class="orwo-archive__search">
			<?php orwo_search_form( isset( $orwo_filters['q'] ) ? $orwo_filters['q'] : '' ); ?>
		</div>
	<?php endif; ?>
</div>

<div class="orwo-wrap orwo-archive">
	<div class="orwo-archive__panel">
		<?php
		get_template_part(
			'template-parts/filter-panel',
			null,
			array(
				'view'    => $orwo_view,
				'filters' => $orwo_filters,
				'counts'  => $orwo_counts,
			)
		);
		?>
	</div>

	<div class="orwo-archive__main">
		<div class="orwo-archive__toolbar">
			<p class="orwo-archive__count" data-orwo-summary>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: number of products. */
						_n( '%s product', '%s products', $orwo_total, 'orwo' ),
						number_format_i18n( $orwo_total )
					)
				);
				?>
			</p>

			<button type="button" class="orwo-button orwo-button--filter" data-orwo-filters-open hidden>
				<?php esc_html_e( 'Filter', 'orwo' ); ?>
			</button>

			<?php orwo_sort_control( $orwo_view, $orwo_filters ); ?>
		</div>

		<?php // The list is always in the markup, hidden while empty, so the script has somewhere to put the chips. ?>
		<ul class="orwo-chips" data-orwo-chips <?php echo $orwo_chips ? '' : 'hidden'; ?>>
			<?php if ( $orwo_chips ) : ?>
				<?php foreach ( $orwo_chips as $orwo_chip ) : ?>
					<?php
					$orwo_without = $orwo_filters;
					if ( 'price' === $orwo_chip['facet'] ) {
						unset( $orwo_without['min_price'], $orwo_without['max_price'] );
					} elseif ( is_array( $orwo_without[ $orwo_chip['facet'] ] ?? null ) ) {
						$orwo_without[ $orwo_chip['facet'] ] = array_values(
							array_diff( (array) $orwo_without[ $orwo_chip['facet'] ], array( $orwo_chip['value'] ) )
						);
						if ( ! $orwo_without[ $orwo_chip['facet'] ] ) {
							unset( $orwo_without[ $orwo_chip['facet'] ] );
						}
					} else {
						unset( $orwo_without[ $orwo_chip['facet'] ] );
					}
					?>
					<li>
						<a class="orwo-chip" href="<?php echo esc_url( orwo_filter_url( $orwo_view['base'], $orwo_without ) ); ?>"
							data-orwo-chip-remove="<?php echo esc_attr( $orwo_chip['facet'] ); ?>"
							data-orwo-chip-value="<?php echo esc_attr( $orwo_chip['value'] ); ?>">
							<span><?php echo esc_html( $orwo_chip['label'] ); ?></span>
							<span class="orwo-chip__x" aria-hidden="true">&times;</span>
							<span class="orwo-visually-hidden"><?php esc_html_e( 'Remove this filter', 'orwo' ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
				<li>
					<a class="orwo-chip orwo-chip--clear" href="<?php echo esc_url( $orwo_view['base'] ); ?>" data-orwo-chip-clear>
						<?php esc_html_e( 'Clear all', 'orwo' ); ?>
					</a>
				</li>
			<?php endif; ?>
		</ul>

		<div class="orwo-grid" data-orwo-grid aria-busy="false">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/product-card', null, array( 'product_id' => get_the_ID() ) );
				endwhile;
				?>
			<?php else : ?>
				<p class="orwo-grid__empty">
					<?php if ( $orwo_chips ) : ?>
						<?php esc_html_e( 'Nothing matches those filters yet. Try removing one.', 'orwo' ); ?>
					<?php elseif ( 'search' === $orwo_view['kind'] ) : ?>
						<?php esc_html_e( 'Nothing matched that search. Try a different word, or ask us.', 'orwo' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'There is nothing here yet.', 'orwo' ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>

		<?php orwo_pagination( $GLOBALS['wp_query'], $orwo_view, $orwo_filters ); ?>
	</div>
</div>

<?php
get_footer();
