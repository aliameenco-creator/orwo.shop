<?php
/**
 * Plugin Name:       ORWO Core
 * Plugin URI:        https://orwo.shop/
 * Description:       The ORWO catalogue: the six formats and their fields, the genre, creator and label taxonomies, clean URLs, the filter engine and REST endpoint, the Ask ORWO chatbot index, enquiries and CSV import and export. Design lives in the ORWO theme, so a redesign never touches the catalogue.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            ORWO
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       orwo-core
 * WC requires at least: 8.0
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ORWO_CORE_VERSION', '0.1.0' );
define( 'ORWO_CORE_FILE', __FILE__ );
define( 'ORWO_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ORWO_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once ORWO_CORE_PATH . 'includes/class-orwo-formats.php';
require_once ORWO_CORE_PATH . 'includes/taxonomies.php';
require_once ORWO_CORE_PATH . 'includes/post-types.php';
require_once ORWO_CORE_PATH . 'includes/fields.php';
require_once ORWO_CORE_PATH . 'includes/products.php';
require_once ORWO_CORE_PATH . 'includes/filters.php';
require_once ORWO_CORE_PATH . 'includes/rewrites.php';
require_once ORWO_CORE_PATH . 'includes/rest.php';
require_once ORWO_CORE_PATH . 'includes/chat-index.php';
require_once ORWO_CORE_PATH . 'includes/login.php';

if ( is_admin() ) {
	require_once ORWO_CORE_PATH . 'includes/admin.php';
	require_once ORWO_CORE_PATH . 'includes/import.php';
}

/**
 * Declares compatibility with WooCommerce's high performance order storage.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ORWO_CORE_FILE, true );
		}
	}
);

/**
 * First run: seed the fixed terms, enable high performance order storage and flush the rewrite rules.
 */
function orwo_activate() {
	orwo_register_taxonomies();
	orwo_register_post_types();
	orwo_seed_terms();
	orwo_seed_enquiry_statuses();
	orwo_add_rewrite_rules();
	flush_rewrite_rules();
	update_option( 'orwo_catalogue_version', orwo_catalogue_version() + 1, false );
}
register_activation_hook( __FILE__, 'orwo_activate' );

/**
 * Leaves no rewrite rules behind on deactivation. Catalogue data is kept.
 */
function orwo_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'orwo_deactivate' );

/**
 * Makes sure the fixed terms exist even when the plugin was activated before this version.
 */
function orwo_check_setup() {
	$installed = (string) get_option( 'orwo_core_installed', '' );
	if ( ORWO_CORE_VERSION === $installed ) {
		return;
	}

	orwo_seed_terms();
	orwo_seed_enquiry_statuses();
	flush_rewrite_rules();
	update_option( 'orwo_core_installed', ORWO_CORE_VERSION, false );
}
add_action( 'admin_init', 'orwo_check_setup' );

/**
 * A shop manager can run the catalogue and orders but not settings or code.
 */
function orwo_shop_manager_caps() {
	$role = get_role( 'shop_manager' );
	if ( ! $role ) {
		return;
	}
	foreach ( array( 'edit_posts', 'edit_published_posts', 'publish_posts', 'delete_posts' ) as $cap ) {
		$role->add_cap( $cap );
	}
	foreach ( array( 'edit_themes', 'edit_plugins', 'manage_options', 'install_plugins', 'update_core' ) as $cap ) {
		$role->remove_cap( $cap );
	}
}
add_action( 'orwo_core_roles', 'orwo_shop_manager_caps' );

/**
 * Keeps the catalogue version moving when a product is created or changed through any route, including the REST API
 * and the CSV importer.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function orwo_touch_catalogue( $post_id, $post ) {
	if ( ! $post || 'product' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
		return;
	}
	orwo_flush_filter_cache();
	orwo_queue_chat_index_rebuild();
}
add_action( 'wp_after_insert_post', 'orwo_touch_catalogue', 20, 2 );
