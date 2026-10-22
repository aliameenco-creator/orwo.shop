<?php
/**
 * The filter panel.
 *
 * Every control is a real form control inside a real form, so the panel works with JavaScript switched off: the
 * form submits to the current view and `pre_get_posts` does the filtering. With JavaScript, the script takes the
 * same values, calls the REST endpoint and swaps the grid in place.
 *
 * Each option carries a live count. Options with no results are disabled, so nobody lands on an empty page.
 *
 * @package orwo
 */

$orwo_view    = isset( $args['view'] ) ? $args['view'] : orwo_current_view();
$orwo_filters = isset( $args['filters'] ) ? $args['filters'] : orwo_current_filters();
$orwo_facets  = orwo_facet_definitions( $orwo_view['format'] );
$orwo_counts  = isset( $args['counts'] ) ? $args['counts'] : array();
$orwo_bounds  = orwo_price_bounds();
$orwo_is_new  = ! empty( $orwo_filters['condition'] ) && array( 'new' ) === $orwo_filters['condition'];
?>
<form class="orwo-filters" method="get" action="<?php echo esc_url( $orwo_view['base'] ); ?>" data-orwo-filters>
	<div class="orwo-filters__head">
		<h2><?php esc_html_e( 'Filter', 'orwo' ); ?></h2>
		<button type="button" class="orwo-filters__done" data-orwo-filters-close hidden>
			<?php esc_html_e( 'Close', 'orwo' ); ?>
		</button>
	</div>

	<?php if ( isset( $orwo_filters['q'] ) ) : ?>
		<input type="hidden" name="q" value="<?php echo esc_attr( $orwo_filters['q'] ); ?>">
	<?php endif; ?>
	<?php if ( isset( $orwo_filters['sort'] ) ) : ?>
		<input type="hidden" name="sort" value="<?php echo esc_attr( $orwo_filters['sort'] ); ?>">
	<?php endif; ?>

	<?php foreach ( $orwo_facets as $orwo_var => $orwo_facet ) : ?>
		<?php
		// Media and sleeve grades are meaningless on a new item.
		if ( ! empty( $orwo_facet['used_items'] ) && $orwo_is_new ) {
			continue;
		}

		$orwo_options = array();

		if ( 'taxonomy' === $orwo_facet['source'] ) {
			$orwo_terms = get_terms(
				array(
					'taxonomy'   => $orwo_facet['taxonomy'],
					'hide_empty' => true,
					'number'     => 60,
					'orderby'    => 'name',
				)
			);
			if ( is_wp_error( $orwo_terms ) ) {
				continue;
			}
			foreach ( $orwo_terms as $orwo_term ) {
				$orwo_options[ $orwo_term->slug ] = $orwo_term->name;
			}
			if ( ! empty( $orwo_facet['order'] ) ) {
				$orwo_sorted = array();
				foreach ( $orwo_facet['order'] as $orwo_slug ) {
					if ( isset( $orwo_options[ $orwo_slug ] ) ) {
						$orwo_sorted[ $orwo_slug ] = $orwo_options[ $orwo_slug ];
					}
				}
				$orwo_options = $orwo_sorted + $orwo_options;
			}
		} elseif ( 'meta' === $orwo_facet['source'] && 'checkbox' === $orwo_facet['type'] ) {
			foreach ( $orwo_facet['order'] as $orwo_option ) {
				$orwo_options[ $orwo_option ] = html_entity_decode( $orwo_option, ENT_QUOTES, 'UTF-8' );
			}
			if ( ! $orwo_options && isset( $orwo_counts[ $orwo_var ] ) ) {
				// A free-text field such as vinyl colour: the options are whatever the catalogue holds.
				foreach ( array_keys( $orwo_counts[ $orwo_var ] ) as $orwo_option ) {
					$orwo_options[ $orwo_option ] = $orwo_option;
				}
			}
		}

		$orwo_active = isset( $orwo_filters[ $orwo_var ] ) ? $orwo_filters[ $orwo_var ] : array();
		?>

		<?php if ( 'range' === $orwo_facet['type'] ) : ?>
			<fieldset class="orwo-filter orwo-filter--price">
				<legend><?php echo esc_html( $orwo_facet['label'] ); ?></legend>
				<div class="orwo-filter__price">
					<label>
						<span class="orwo-visually-hidden"><?php esc_html_e( 'Lowest price', 'orwo' ); ?></span>
						<input type="number" name="min_price" inputmode="decimal" min="<?php echo esc_attr( (string) $orwo_bounds['min'] ); ?>"
							max="<?php echo esc_attr( (string) $orwo_bounds['max'] ); ?>" step="1"
							placeholder="<?php echo esc_attr( (string) $orwo_bounds['min'] ); ?>"
							value="<?php echo esc_attr( isset( $orwo_filters['min_price'] ) ? (string) $orwo_filters['min_price'] : '' ); ?>">
					</label>
					<span aria-hidden="true">&ndash;</span>
					<label>
						<span class="orwo-visually-hidden"><?php esc_html_e( 'Highest price', 'orwo' ); ?></span>
						<input type="number" name="max_price" inputmode="decimal" min="<?php echo esc_attr( (string) $orwo_bounds['min'] ); ?>"
							max="<?php echo esc_attr( (string) $orwo_bounds['max'] ); ?>" step="1"
							placeholder="<?php echo esc_attr( (string) $orwo_bounds['max'] ); ?>"
							value="<?php echo esc_attr( isset( $orwo_filters['max_price'] ) ? (string) $orwo_filters['max_price'] : '' ); ?>">
					</label>
				</div>
			</fieldset>

		<?php elseif ( 'toggle' === $orwo_facet['type'] ) : ?>
			<div class="orwo-filter orwo-filter--toggle">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $orwo_var ); ?>" value="1" <?php checked( ! empty( $orwo_filters[ $orwo_var ] ) ); ?>>
					<?php echo esc_html( 'instock' === $orwo_var ? __( 'In stock only', 'orwo' ) : $orwo_facet['label'] ); ?>
					<?php if ( isset( $orwo_counts[ $orwo_var ]['1'] ) ) : ?>
						<span class="orwo-filter__count">(<?php echo esc_html( number_format_i18n( (int) $orwo_counts[ $orwo_var ]['1'] ) ); ?>)</span>
					<?php endif; ?>
				</label>
			</div>

		<?php elseif ( $orwo_options ) : ?>
			<fieldset class="orwo-filter" data-orwo-facet="<?php echo esc_attr( $orwo_var ); ?>">
				<legend><?php echo esc_html( $orwo_facet['label'] ); ?></legend>
				<ul class="orwo-filter__options<?php echo count( $orwo_options ) > 8 ? ' is-scrollable' : ''; ?>">
					<?php foreach ( $orwo_options as $orwo_value => $orwo_label ) : ?>
						<?php
						// When counts were worked out for this facet, an option missing from them has no results:
						// show it as zero and disable it, so nobody lands on an empty page.
						if ( isset( $orwo_counts[ $orwo_var ] ) && is_array( $orwo_counts[ $orwo_var ] ) ) {
							$orwo_count = (int) ( $orwo_counts[ $orwo_var ][ (string) $orwo_value ] ?? 0 );
						} else {
							$orwo_count = null;
						}
						$orwo_checked  = in_array( (string) $orwo_value, array_map( 'strval', (array) $orwo_active ), true );
						$orwo_disabled = ( null !== $orwo_count && 0 === $orwo_count && ! $orwo_checked );
						?>
						<li>
							<label<?php echo $orwo_disabled ? ' class="is-disabled"' : ''; ?>>
								<input type="checkbox" name="<?php echo esc_attr( $orwo_var ); ?>[]" value="<?php echo esc_attr( (string) $orwo_value ); ?>"
									<?php checked( $orwo_checked ); ?> <?php disabled( $orwo_disabled ); ?>>
								<span class="orwo-filter__label"><?php echo esc_html( $orwo_label ); ?></span>
								<?php if ( null !== $orwo_count ) : ?>
									<span class="orwo-filter__count" data-orwo-count="<?php echo esc_attr( (string) $orwo_value ); ?>"><?php echo esc_html( number_format_i18n( $orwo_count ) ); ?></span>
								<?php endif; ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</fieldset>
		<?php endif; ?>
	<?php endforeach; ?>

	<div class="orwo-filters__actions">
		<button type="submit" class="orwo-button orwo-button--full"><?php esc_html_e( 'Show results', 'orwo' ); ?></button>
		<a class="orwo-filters__clear" href="<?php echo esc_url( $orwo_view['base'] ); ?>"><?php esc_html_e( 'Clear all', 'orwo' ); ?></a>
	</div>
</form>
