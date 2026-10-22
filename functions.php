<?php
/**
 * ORWO theme.
 *
 * Design and templates only: the catalogue, filters, URLs and chatbot index live in the ORWO Core plugin. One
 * stylesheet, one small deferred script on browsing pages, and the chatbot loaded when the browser is idle.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ORWO_VERSION', wp_get_theme( 'orwo-shop' )->get( 'Version' ) );

require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/performance.php';
require_once __DIR__ . '/inc/assets.php';
require_once __DIR__ . '/inc/template-tags.php';
require_once __DIR__ . '/inc/woo.php';
require_once __DIR__ . '/inc/seo.php';
require_once __DIR__ . '/inc/security.php';
require_once __DIR__ . '/inc/homepage.php';
require_once __DIR__ . '/inc/chat.php';
require_once __DIR__ . '/inc/setup-screen.php';
require_once __DIR__ . '/inc/github-updater.php';

/**
 * Whether the ORWO Core plugin is active. Without it the theme still renders pages, but there is no catalogue.
 *
 * @return bool
 */
function orwo_core_active() {
	return class_exists( 'Orwo_Formats' );
}

/**
 * An admin notice when the catalogue plugin is missing, so the shop is never silently empty.
 */
function orwo_core_missing_notice() {
	if ( orwo_core_active() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'The ORWO theme needs the ORWO Core plugin.', 'orwo' ); ?></strong>
			<?php esc_html_e( 'Formats, filters, product URLs and the chatbot come from that plugin. Activate it under Plugins.', 'orwo' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'orwo_core_missing_notice' );
