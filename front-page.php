<?php
/**
 * The homepage.
 *
 * One job: get a visitor to the right product in two clicks, by browsing a format or by telling the chatbot what
 * they want. Everything except the hero and the editorial band fills itself from the catalogue, and any row with
 * nothing in it hides itself.
 *
 * @package orwo
 */

get_header();
?>

<?php get_template_part( 'template-parts/home/hero' ); ?>
<?php get_template_part( 'template-parts/home/formats' ); ?>

<?php
get_template_part(
	'template-parts/home/row',
	null,
	array(
		'title' => __( 'New arrivals', 'orwo' ),
		'view'  => 'new',
		'limit' => 8,
		'url'   => user_trailingslashit( home_url( '/new' ) ),
	)
);
?>

<?php get_template_part( 'template-parts/home/editorial' ); ?>

<?php
get_template_part(
	'template-parts/home/row',
	null,
	array(
		'title' => __( 'Limited and back in stock', 'orwo' ),
		'view'  => 'limited',
		'limit' => 8,
		'url'   => orwo_shop_url(),
	)
);
?>

<?php get_template_part( 'template-parts/home/trust' ); ?>
<?php get_template_part( 'template-parts/home/genres' ); ?>

<?php
get_footer();
