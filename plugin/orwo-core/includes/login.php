<?php
/**
 * Login hardening.
 *
 * The sign-in page moves off /wp-login.php and /wp-admin to a private path, and the old addresses stop existing for
 * anyone who is not already signed in: they answer 404 rather than redirecting, so a scanner learns nothing. Failed
 * attempts are rate limited per visitor, and the usual ways of harvesting usernames are closed.
 *
 * This is one layer, not the whole defence. It cuts out automated noise; it does not replace a strong password or
 * two-step login, which belongs in a reviewed plugin.
 *
 * Never locked out: define ORWO_LOGIN_SLUG in wp-config.php to override the stored slug, or
 * define ORWO_LOGIN_DISABLE as true to restore the standard WordPress addresses.
 *
 * @package orwo-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many failures before a visitor is asked to wait, and for how long.
 */
const ORWO_LOGIN_MAX_ATTEMPTS = 5;
const ORWO_LOGIN_LOCKOUT      = 900;

/**
 * The private login slug, or an empty string when the feature is off.
 *
 * @return string
 */
function orwo_login_slug() {
	if ( defined( 'ORWO_LOGIN_DISABLE' ) && ORWO_LOGIN_DISABLE ) {
		return '';
	}
	if ( defined( 'ORWO_LOGIN_SLUG' ) && is_string( ORWO_LOGIN_SLUG ) && '' !== ORWO_LOGIN_SLUG ) {
		return sanitize_title( ORWO_LOGIN_SLUG );
	}

	$slug = sanitize_title( (string) get_option( 'orwo_login_slug', '' ) );

	return $slug;
}

/**
 * Whether the private login path is in force.
 *
 * @return bool
 */
function orwo_login_hidden() {
	return '' !== orwo_login_slug();
}

/**
 * A readable, hard to guess default slug, offered once on the security screen.
 *
 * @return string
 */
function orwo_suggest_login_slug() {
	return 'orwo-' . strtolower( wp_generate_password( 8, false, false ) );
}

/**
 * The full sign-in URL.
 *
 * @return string
 */
function orwo_login_url() {
	$slug = orwo_login_slug();

	return $slug ? home_url( '/' . $slug . '/' ) : wp_login_url();
}

/**
 * The paths under wp-admin that must keep working for visitors who are not signed in.
 *
 * admin-ajax.php and admin-post.php are used by the front end, and load-scripts.php and load-styles.php serve the
 * login page's own assets.
 *
 * @return string[]
 */
function orwo_login_allowed_admin_files() {
	return array( 'admin-ajax.php', 'admin-post.php', 'load-scripts.php', 'load-styles.php' );
}

/**
 * Serves the private login path and makes the standard ones disappear.
 *
 * Runs before WordPress decides what the request is, so nothing of wp-login.php is exposed on the way.
 */
function orwo_guard_login() {
	if ( ! orwo_login_hidden() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ) {
		return;
	}

	$path = orwo_request_path();
	$slug = orwo_login_slug();

	if ( $slug === $path ) {
		// The login page itself is served from wp_loaded: wp-login.php needs constants WordPress only defines
		// after this point in the boot, and nothing has been sent to the browser by then either.
		add_action( 'wp_loaded', 'orwo_serve_login_page', 1 );

		return;
	}

	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

	// wp-login.php: hidden, except the actions that must keep working from a link in an email or a form post.
	if ( 'wp-login.php' === $path ) {
		$public_actions = array( 'postpass', 'logout', 'lostpassword', 'rp', 'resetpass', 'confirmaction' );
		if ( in_array( $action, $public_actions, true ) ) {
			return;
		}
		orwo_login_not_found();

		return;
	}

	// wp-admin: only for people already signed in.
	if ( 'wp-admin' === $path || 0 === strpos( $path, 'wp-admin/' ) ) {
		$file = basename( $path );
		if ( in_array( $file, orwo_login_allowed_admin_files(), true ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			orwo_login_not_found();
		}
	}
}
add_action( 'plugins_loaded', 'orwo_guard_login', 1 );

/**
 * Loads the real login page at the private path.
 */
function orwo_serve_login_page() {
	global $pagenow, $error, $interim_login, $action, $errors, $redirect_to, $user_login;

	$pagenow = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Serving the login page from another path.

	if ( ! defined( 'WP_USE_THEMES' ) ) {
		define( 'WP_USE_THEMES', false );
	}

	nocache_headers();

	// wp-login.php includes wp-load.php itself, which is already loaded, so this simply runs the login screen.
	require_once ABSPATH . 'wp-login.php';
	exit;
}

/**
 * Answers a plain 404 and stops.
 *
 * This runs on plugins_loaded, before the theme exists, and the executing script is wp-login.php or a file in
 * wp-admin, so there is no way to hand the request to the theme's 404 template. A short, unbranded page is what a
 * scanner sees, which says nothing about what software the site runs.
 */
function orwo_login_not_found() {
	status_header( 404 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );

	echo "<!doctype html>\n<html><head><meta charset=\"utf-8\"><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>\n";
	exit;
}

/**
 * Rewrites every login and logout URL WordPress generates to the private path.
 *
 * @param string $url Generated URL.
 * @return string
 */
function orwo_filter_login_url( $url ) {
	if ( ! orwo_login_hidden() || ! is_string( $url ) || false === strpos( $url, 'wp-login.php' ) ) {
		return $url;
	}

	$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
	$args  = array();
	if ( $query ) {
		wp_parse_str( $query, $args );
	}

	// Password-protected posts, logout and password resets keep using the core endpoint, which stays reachable.
	if ( isset( $args['action'] ) && in_array( $args['action'], array( 'postpass', 'logout', 'lostpassword', 'rp', 'resetpass', 'confirmaction' ), true ) ) {
		return $url;
	}

	$new = home_url( '/' . orwo_login_slug() . '/' );

	return $args ? add_query_arg( $args, $new ) : $new;
}
add_filter( 'login_url', 'orwo_filter_login_url', 20 );
add_filter( 'lostpassword_url', 'orwo_filter_login_url', 20 );
add_filter( 'register_url', 'orwo_filter_login_url', 20 );

/**
 * Keeps redirects off the hidden path's old address.
 *
 * @param string $location Redirect target.
 * @return string
 */
function orwo_filter_login_redirect( $location ) {
	return orwo_filter_login_url( $location );
}
add_filter( 'wp_redirect', 'orwo_filter_login_redirect', 20 );

/**
 * The site_url() calls that build the login form's own action attribute.
 *
 * @param string $url    Full URL.
 * @param string $path   Requested path.
 * @param string $scheme Scheme.
 * @return string
 */
function orwo_filter_site_url( $url, $path, $scheme ) {
	if ( ! orwo_login_hidden() || 'login' !== $scheme && 'login_post' !== $scheme ) {
		return $url;
	}

	return orwo_filter_login_url( $url );
}
add_filter( 'site_url', 'orwo_filter_site_url', 20, 3 );
add_filter( 'network_site_url', 'orwo_filter_site_url', 20, 3 );

/**
 * Rate limits sign-in attempts per visitor.
 *
 * @param WP_User|WP_Error|null $user     Authentication result so far.
 * @param string                $username Submitted username.
 * @return WP_User|WP_Error|null
 */
function orwo_throttle_login( $user, $username ) {
	if ( '' === (string) $username ) {
		return $user;
	}

	$key      = 'orwo_login_' . md5( orwo_client_fingerprint() );
	$attempts = (int) get_transient( $key );

	if ( $attempts >= ORWO_LOGIN_MAX_ATTEMPTS ) {
		return new WP_Error(
			'orwo_locked_out',
			sprintf(
				/* translators: %d: number of minutes. */
				esc_html__( 'Too many sign-in attempts. Please wait %d minutes and try again.', 'orwo-core' ),
				(int) ceil( ORWO_LOGIN_LOCKOUT / 60 )
			)
		);
	}

	return $user;
}
add_filter( 'authenticate', 'orwo_throttle_login', 30, 2 );

/**
 * Counts a failure.
 */
function orwo_count_login_failure() {
	$key      = 'orwo_login_' . md5( orwo_client_fingerprint() );
	$attempts = (int) get_transient( $key ) + 1;
	set_transient( $key, $attempts, ORWO_LOGIN_LOCKOUT );
}
add_action( 'wp_login_failed', 'orwo_count_login_failure' );

/**
 * Clears the count on a successful sign-in.
 */
function orwo_clear_login_failures() {
	delete_transient( 'orwo_login_' . md5( orwo_client_fingerprint() ) );
}
add_action( 'wp_login', 'orwo_clear_login_failures' );

/**
 * Closes the usual ways of harvesting usernames: the author archives and the users REST route.
 */
function orwo_block_author_enumeration() {
	if ( is_admin() || ! isset( $_GET['author'] ) ) {
		return;
	}
	if ( is_numeric( wp_unslash( $_GET['author'] ) ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'orwo_block_author_enumeration', 0 );

/**
 * The users route is for people who may already list users.
 *
 * @param mixed           $result  Current result.
 * @param WP_REST_Server  $server  Server.
 * @param WP_REST_Request $request Request.
 * @return mixed
 */
function orwo_restrict_users_route( $result, $server, $request ) {
	if ( null !== $result ) {
		return $result;
	}
	$route = (string) $request->get_route();
	if ( 0 !== strpos( $route, '/wp/v2/users' ) ) {
		return $result;
	}
	if ( current_user_can( 'list_users' ) ) {
		return $result;
	}

	return new WP_Error( 'orwo_rest_forbidden', __( 'You cannot list users.', 'orwo-core' ), array( 'status' => 401 ) );
}
add_filter( 'rest_pre_dispatch', 'orwo_restrict_users_route', 10, 3 );

/**
 * A customer has no reason to see wp-admin: send them to their account page instead.
 */
function orwo_redirect_customers() {
	if ( ! is_admin() || wp_doing_ajax() || ! is_user_logged_in() ) {
		return;
	}
	if ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
	wp_safe_redirect( $account ? $account : home_url( '/' ) );
	exit;
}
add_action( 'admin_init', 'orwo_redirect_customers' );

/**
 * Registers the security screen.
 */
function orwo_security_menu() {
	add_options_page(
		__( 'ORWO security', 'orwo-core' ),
		__( 'ORWO security', 'orwo-core' ),
		'manage_options',
		'orwo-security',
		'orwo_render_security_screen'
	);
}
add_action( 'admin_menu', 'orwo_security_menu' );

/**
 * The security screen: the private sign-in address and what it does and does not cover.
 */
function orwo_render_security_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot change these settings.', 'orwo-core' ) );
	}

	$notice = '';
	if ( isset( $_POST['orwo_save_security'] ) && check_admin_referer( 'orwo_save_security' ) ) {
		$submitted = sanitize_title( (string) wp_unslash( $_POST['orwo_login_slug'] ?? '' ) );
		$reserved  = array_merge( orwo_reserved_segments(), array( 'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'wp-json', 'shop', 'cart', 'checkout', 'account', 'my-account', 'admin', 'login' ) );

		if ( '' === $submitted ) {
			delete_option( 'orwo_login_slug' );
			$notice = __( 'The private sign-in address has been switched off. WordPress is back to /wp-login.php.', 'orwo-core' );
		} elseif ( in_array( $submitted, $reserved, true ) ) {
			$notice = __( 'That address is already used by the shop. Choose another.', 'orwo-core' );
		} elseif ( strlen( $submitted ) < 5 ) {
			$notice = __( 'Use at least five characters, and something nobody would guess.', 'orwo-core' );
		} else {
			update_option( 'orwo_login_slug', $submitted, false );
			flush_rewrite_rules();
			/* translators: %s: the new sign-in URL. */
			$notice = sprintf( __( 'Saved. Sign in at %s from now on. Save that address somewhere safe before you sign out.', 'orwo-core' ), home_url( '/' . $submitted . '/' ) );
		}
	}

	$slug      = orwo_login_slug();
	$suggested = $slug ? $slug : orwo_suggest_login_slug();
	$forced    = defined( 'ORWO_LOGIN_SLUG' ) && ORWO_LOGIN_SLUG;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'ORWO security', 'orwo-core' ); ?></h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Private sign-in address', 'orwo-core' ); ?></h2>
		<p><?php esc_html_e( 'With an address set, /wp-login.php and /wp-admin answer "page not found" to anyone who is not signed in, so automated scanners find nothing to attack. Password resets and logout links keep working.', 'orwo-core' ); ?></p>

		<?php if ( $forced ) : ?>
			<p><strong><?php esc_html_e( 'The address is set in wp-config.php through ORWO_LOGIN_SLUG and cannot be changed here.', 'orwo-core' ); ?></strong></p>
			<p><code><?php echo esc_html( orwo_login_url() ); ?></code></p>
		<?php else : ?>
			<form method="post">
				<?php wp_nonce_field( 'orwo_save_security' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="orwo_login_slug"><?php esc_html_e( 'Sign in at', 'orwo-core' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url( '/' ) ) ); ?></code>
							<input type="text" id="orwo_login_slug" name="orwo_login_slug" class="regular-text" value="<?php echo esc_attr( $slug ); ?>" placeholder="<?php echo esc_attr( $suggested ); ?>">
							<code>/</code>
							<p class="description">
								<?php esc_html_e( 'Leave it empty to go back to the standard WordPress address. A suggestion you can use:', 'orwo-core' ); ?>
								<code><?php echo esc_html( $suggested ); ?></code>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save the sign-in address', 'orwo-core' ), 'primary', 'orwo_save_security' ); ?>
			</form>
		<?php endif; ?>

		<?php if ( $slug ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<strong><?php esc_html_e( 'Write this address down now:', 'orwo-core' ); ?></strong>
					<code><?php echo esc_html( orwo_login_url() ); ?></code>
				</p>
				<p>
					<?php esc_html_e( 'If it is ever lost, add this line to wp-config.php over FTP or the file manager to get back in:', 'orwo-core' ); ?>
					<code>define( 'ORWO_LOGIN_DISABLE', true );</code>
				</p>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Also switched on', 'orwo-core' ); ?></h2>
		<ul style="list-style:disc;margin-left:1.5em">
			<li><?php echo esc_html( sprintf( /* translators: 1: attempts, 2: minutes. */ __( 'Sign-in attempts are limited to %1$d per visitor, then a %2$d minute wait.', 'orwo-core' ), ORWO_LOGIN_MAX_ATTEMPTS, (int) ( ORWO_LOGIN_LOCKOUT / 60 ) ) ); ?></li>
			<li><?php esc_html_e( 'A failed sign-in never says which half was wrong.', 'orwo-core' ); ?></li>
			<li><?php esc_html_e( 'Author archives and the users REST route no longer hand out usernames.', 'orwo-core' ); ?></li>
			<li><?php esc_html_e( 'XML-RPC is off, file editing in the admin is off and the WordPress version is hidden.', 'orwo-core' ); ?></li>
			<li><?php esc_html_e( 'Customers who sign in are sent to their account page, not the admin.', 'orwo-core' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Still worth doing', 'orwo-core' ); ?></h2>
		<ul style="list-style:disc;margin-left:1.5em">
			<li><?php esc_html_e( 'Two-step login for every administrator and shop manager, through one reviewed plugin. A private address is not a substitute for it.', 'orwo-core' ); ?></li>
			<li><?php esc_html_e( 'A long, unique password per account, kept in a password manager.', 'orwo-core' ); ?></li>
			<li><?php esc_html_e( 'Daily host backups, and a manual backup before every update.', 'orwo-core' ); ?></li>
			<li><?php esc_html_e( 'Keep WordPress, WooCommerce and the payment gateways updated.', 'orwo-core' ); ?></li>
		</ul>
	</div>
	<?php
}
