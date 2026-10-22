<?php
/**
 * The three-layer header: announcement strip, main bar, format bar.
 *
 * @package orwo
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="orwo-skip" href="#orwo-main"><?php esc_html_e( 'Skip to content', 'orwo' ); ?></a>

<?php // The strip and the header share one fixed wrapper, so hiding the format bar on scroll shifts nothing. ?>
<div class="orwo-top" data-orwo-top>

<?php $announcement = (string) orwo_home_option( 'announcement' ); ?>
<?php if ( '' !== $announcement ) : ?>
	<div class="orwo-strip" data-orwo-strip hidden>
		<p><?php echo esc_html( $announcement ); ?></p>
		<button type="button" class="orwo-strip__close" data-orwo-strip-close>
			<span class="orwo-visually-hidden"><?php esc_html_e( 'Dismiss', 'orwo' ); ?></span>
			<svg aria-hidden="true" width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m2 2 8 8M10 2l-8 8"></path></svg>
		</button>
	</div>
<?php endif; ?>

<header class="orwo-header" data-orwo-header>
	<div class="orwo-header__bar">
		<div class="orwo-wrap orwo-header__inner">
			<div class="orwo-header__brand">
				<button type="button" class="orwo-header__menu" data-orwo-menu-open aria-expanded="false" aria-controls="orwo-mobile-menu">
					<span class="orwo-visually-hidden"><?php esc_html_e( 'Menu', 'orwo' ); ?></span>
					<svg aria-hidden="true" width="22" height="22" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 6h16M3 11h16M3 16h16"></path></svg>
				</button>
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a class="orwo-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
				<?php endif; ?>
			</div>

			<div class="orwo-header__search">
				<?php orwo_search_form(); ?>
			</div>

			<div class="orwo-header__tools">
				<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
					<a class="orwo-header__link" href="<?php echo esc_url( (string) wc_get_page_permalink( 'myaccount' ) ); ?>">
						<svg aria-hidden="true" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="6" r="3"></circle><path d="M3.5 15.5c0-2.8 2.5-4.5 5.5-4.5s5.5 1.7 5.5 4.5"></path></svg>
						<span><?php esc_html_e( 'Account', 'orwo' ); ?></span>
					</a>
					<a class="orwo-header__link orwo-header__cart" href="<?php echo esc_url( (string) wc_get_page_permalink( 'cart' ) ); ?>" data-orwo-cart>
						<svg aria-hidden="true" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2.5 3h2l1.6 8.5h8.2l1.2-6H5"></path><circle cx="7.5" cy="15" r="1.1"></circle><circle cx="13.5" cy="15" r="1.1"></circle></svg>
						<span><?php esc_html_e( 'Cart', 'orwo' ); ?></span>
						<span class="orwo-header__count" data-orwo-cart-count hidden>0</span>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php get_template_part( 'template-parts/format-bar' ); ?>
</header>

</div>
<?php // Holds the space the fixed header takes, so nothing jumps when the format bar slides away. ?>
<div class="orwo-top-space" data-orwo-top-space></div>

<?php get_template_part( 'template-parts/mobile-menu' ); ?>

<?php if ( ! is_front_page() ) : ?>
	<?php orwo_the_breadcrumbs(); ?>
<?php endif; ?>

<main id="orwo-main" class="orwo-main">
