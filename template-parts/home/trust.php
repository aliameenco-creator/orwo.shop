<?php
/**
 * The trust row: four short statements with thin line icons.
 *
 * @package orwo
 */

$orwo_items = orwo_trust_items();
if ( ! $orwo_items ) {
	return;
}

$orwo_icons = array(
	'<path d="M3 10h14M10 3v14"></path>',
	'<path d="M2 6h10v7H2zM12 8h3l3 3v2h-6z"></path><circle cx="5.5" cy="15" r="1.2"></circle><circle cx="14.5" cy="15" r="1.2"></circle>',
	'<path d="M10 3 3 6v4c0 4 3 6.3 7 7 4-.7 7-3 7-7V6z"></path>',
	'<path d="M4 9h12v8H4zM7 9V6a3 3 0 0 1 6 0v3"></path>',
);
?>
<section class="orwo-wrap orwo-trust">
	<ul class="orwo-trust__list">
		<?php foreach ( $orwo_items as $orwo_index => $orwo_item ) : ?>
			<li>
				<svg aria-hidden="true" width="24" height="24" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2">
					<?php echo wp_kses( $orwo_icons[ $orwo_index % count( $orwo_icons ) ], array( 'path' => array( 'd' => array() ), 'circle' => array( 'cx' => array(), 'cy' => array(), 'r' => array() ) ) ); ?>
				</svg>
				<h3><?php echo esc_html( $orwo_item['title'] ); ?></h3>
				<?php if ( '' !== $orwo_item['text'] ) : ?>
					<p><?php echo esc_html( $orwo_item['text'] ); ?></p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
