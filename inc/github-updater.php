<?php
/**
 * Numbered GitHub release updates through WordPress's own theme upgrader.
 *
 * No repository is hard-coded: the repository is named once in wp-config.php (ORWO_GITHUB_REPO) or on the updates
 * screen, because guessing a repository name is how a site's theme ends up published to another site's repository.
 * Until one is set, the updater stays inert and says so.
 *
 * A private repository needs a read-only, repository-restricted token, pasted once in wp-admin. It is stored in
 * this site's database only, never in the theme files, and never forwarded to a download redirect host.
 *
 * @package orwo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The theme directory name, which is also the name of the release asset.
 */
const ORWO_SLUG = 'orwo-shop';

/**
 * The configured repository as owner/name, or an empty string.
 *
 * @return string
 */
function orwo_update_repo() {
	if ( defined( 'ORWO_GITHUB_REPO' ) && is_string( ORWO_GITHUB_REPO ) && '' !== ORWO_GITHUB_REPO ) {
		$repo = ORWO_GITHUB_REPO;
	} else {
		$repo = (string) get_option( 'orwo_github_repo', '' );
	}

	return preg_match( '~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repo ) ? $repo : '';
}

/**
 * The GitHub API base for the configured repository.
 *
 * @return string
 */
function orwo_update_api() {
	$repo = orwo_update_repo();

	return $repo ? 'https://api.github.com/repos/' . $repo : '';
}

/**
 * The saved token, or one from wp-config.php.
 *
 * @return string
 */
function orwo_update_token() {
	if ( defined( 'ORWO_GITHUB_TOKEN' ) && is_string( ORWO_GITHUB_TOKEN ) && '' !== ORWO_GITHUB_TOKEN ) {
		return ORWO_GITHUB_TOKEN;
	}

	return (string) get_option( 'orwo_github_token', '' );
}

/**
 * Whether a string looks like a GitHub token.
 *
 * @param string $token Token.
 * @return bool
 */
function orwo_token_valid( $token ) {
	return (bool) preg_match( '/^(github_pat_[A-Za-z0-9_]{20,255}|gh[pousr]_[A-Za-z0-9]{20,255})$/D', $token );
}

/**
 * Request headers for the GitHub API.
 *
 * @return array<string,string>
 */
function orwo_update_headers() {
	$headers = array(
		'Accept'               => 'application/vnd.github+json',
		'X-GitHub-Api-Version' => '2022-11-28',
		'User-Agent'           => 'ORWO-WordPress-Updater',
	);

	$token = orwo_update_token();
	if ( '' !== $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}

	return $headers;
}

/**
 * Checks a release response and returns what the upgrader needs.
 *
 * @param mixed $data Decoded release.
 * @return array<string,mixed>|WP_Error
 */
function orwo_release_validate( $data ) {
	if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] )
		|| ! isset( $data['tag_name'], $data['assets'] )
		|| ! preg_match( '/^v(\d+\.\d+\.\d+)$/D', $data['tag_name'], $match ) ) {
		return new WP_Error( 'release', __( 'No valid stable numbered release is available.', 'orwo' ) );
	}

	foreach ( $data['assets'] as $asset ) {
		if ( ORWO_SLUG . '.zip' !== ( $asset['name'] ?? '' ) ) {
			continue;
		}
		if ( empty( $asset['id'] ) || ! is_int( $asset['id'] )
			|| ! preg_match( '/^sha256:([a-f0-9]{64})$/D', $asset['digest'] ?? '', $digest )
			|| empty( $asset['size'] ) || $asset['size'] > 80000000 ) {
			return new WP_Error( 'asset', __( 'The release package is missing a valid SHA-256 digest or is too large.', 'orwo' ) );
		}

		return array(
			'version' => $match[1],
			'package' => orwo_update_api() . '/releases/assets/' . $asset['id'],
			'sha256'  => $digest[1],
			'size'    => (int) $asset['size'],
			'notes'   => is_string( $data['body'] ?? null ) ? $data['body'] : '',
		);
	}

	/* translators: %s: expected asset file name. */
	return new WP_Error( 'package', sprintf( __( 'The release must include an uploaded %s asset.', 'orwo' ), ORWO_SLUG . '.zip' ) );
}

/**
 * Asks GitHub for the latest release, with a cached answer.
 *
 * @param bool $force Ignore the cache.
 * @return array<string,mixed>|WP_Error
 */
function orwo_release_check( $force = false ) {
	if ( ! orwo_update_repo() ) {
		return new WP_Error( 'repo', __( 'No GitHub repository is set for this theme yet, so updates are switched off.', 'orwo' ) );
	}
	if ( ! $force ) {
		$cached = get_site_transient( 'orwo_github_release' );
		if ( is_array( $cached ) || is_wp_error( $cached ) ) {
			return $cached;
		}
	}

	$response = wp_safe_remote_get(
		orwo_update_api() . '/releases/latest',
		array(
			'headers'             => orwo_update_headers(),
			'timeout'             => 20,
			'redirection'         => 0,
			'limit_response_size' => 1000000,
		)
	);

	if ( is_wp_error( $response ) ) {
		$error = new WP_Error( 'network', __( 'GitHub could not be reached. Try again later.', 'orwo' ) );
		set_site_transient( 'orwo_github_release', $error, 5 * MINUTE_IN_SECONDS );

		return $error;
	}
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		$error = new WP_Error( 'github', __( 'GitHub did not return a release. Check that a release exists, and that a token is saved below if the repository is private.', 'orwo' ) );
		set_site_transient( 'orwo_github_release', $error, 5 * MINUTE_IN_SECONDS );

		return $error;
	}

	$release = orwo_release_validate( json_decode( wp_remote_retrieve_body( $response ), true ) );
	set_site_transient( 'orwo_github_release', $release, is_wp_error( $release ) ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS );

	return $release;
}

/**
 * Offers the update to WordPress.
 *
 * @param object $transient Theme update transient.
 * @return object
 */
function orwo_release_updates( $transient ) {
	if ( ! is_object( $transient ) || ! orwo_update_repo() ) {
		return $transient;
	}
	// Only contact GitHub from wp-admin, cron or the upgrader, never on a front-end page load.
	if ( ! is_admin() && ! wp_doing_cron() && ! is_array( get_site_transient( 'orwo_github_release' ) ) ) {
		return $transient;
	}

	$release = orwo_release_check();
	if ( is_wp_error( $release ) ) {
		unset( $transient->response[ ORWO_SLUG ] );

		return $transient;
	}

	$item = array(
		'theme'       => ORWO_SLUG,
		'new_version' => $release['version'],
		'url'         => 'https://github.com/' . orwo_update_repo() . '/releases',
		'package'     => $release['package'],
	);

	if ( version_compare( $release['version'], wp_get_theme( ORWO_SLUG )->get( 'Version' ), '>' ) ) {
		$transient->response[ ORWO_SLUG ] = $item;
		unset( $transient->no_update[ ORWO_SLUG ] );
	} else {
		unset( $transient->response[ ORWO_SLUG ] );
		$transient->no_update[ ORWO_SLUG ] = $item;
	}

	return $transient;
}
add_filter( 'site_transient_update_themes', 'orwo_release_updates' );

/**
 * Updates to this theme are never automatic: a shop should update when someone is watching.
 *
 * @param bool   $update Whether to update.
 * @param object $item   Update offer.
 * @return bool
 */
function orwo_no_auto_update( $update, $item ) {
	return ORWO_SLUG === ( $item->theme ?? '' ) ? false : $update;
}
add_filter( 'auto_update_theme', 'orwo_no_auto_update', 10, 2 );

/**
 * Checks the downloaded ZIP before WordPress unpacks it over the live theme.
 *
 * @param string $file    Local file.
 * @param string $version Expected version.
 * @return true|WP_Error
 */
function orwo_release_zip_validate( $file, $version ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'zip', __( 'The server needs the PHP ZIP extension to verify this update.', 'orwo' ) );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $file ) ) {
		return new WP_Error( 'zip', __( 'The downloaded package is not a valid ZIP.', 'orwo' ) );
	}

	$error = null;
	$total = 0;

	if ( $zip->numFiles > 3000 ) {
		$error = __( 'Too many files in the theme ZIP.', 'orwo' );
	}

	for ( $i = 0; ! $error && $i < $zip->numFiles; ++$i ) {
		$entry  = $zip->statIndex( $i );
		$name   = $entry['name'];
		$total += $entry['size'];

		if ( ! str_starts_with( $name, ORWO_SLUG . '/' ) || str_contains( $name, '\\' )
			|| preg_match( '~(^|/)(\.{1,2}|\.[^/]+)(/|$)~', $name ) || $total > 200000000 ) {
			$error = __( 'The theme ZIP contains an unsafe path or is too large unpacked.', 'orwo' );
			break;
		}

		$opsys      = 0;
		$attributes = 0;
		if ( $zip->getExternalAttributesIndex( $i, $opsys, $attributes ) && 0120000 === ( ( $attributes >> 16 ) & 0170000 ) ) {
			$error = __( 'Symbolic links are not allowed in update packages.', 'orwo' );
		}
	}

	$style = $zip->getFromName( ORWO_SLUG . '/style.css' );
	if ( ! $error && ( ! is_string( $style )
		|| ! preg_match( '/^Version:\s*' . preg_quote( $version, '/' ) . '\s*$/m', $style )
		|| false === $zip->locateName( ORWO_SLUG . '/functions.php' )
		|| false === $zip->locateName( ORWO_SLUG . '/theme.json' ) ) ) {
		$error = __( 'The package version or required theme files do not match the release.', 'orwo' );
	}

	if ( ! $error ) {
		preg_match( '/^Requires at least:\s*([\d.]+)/m', (string) $style, $wp );
		preg_match( '/^Requires PHP:\s*([\d.]+)/m', (string) $style, $php );
		if ( version_compare( get_bloginfo( 'version' ), $wp[1] ?? '6.6', '<' ) || version_compare( PHP_VERSION, $php[1] ?? '8.1', '<' ) ) {
			$error = __( 'Update WordPress or PHP to the versions this theme requires first.', 'orwo' );
		}
	}

	$zip->close();

	return $error ? new WP_Error( 'package', $error ) : true;
}

/**
 * Downloads the release asset itself, so the API credential is sent to GitHub's API and nowhere else.
 *
 * Without this handler the GitHub API answers an asset request with JSON rather than the ZIP, and the update fails
 * with PCLZIP_ERR_BAD_FORMAT, which is a lesson already paid for on another site in this portfolio.
 *
 * @param mixed  $reply      Current reply.
 * @param string $package    Package URL.
 * @param object $upgrader   Upgrader.
 * @param array  $hook_extra Extra arguments.
 * @return string|WP_Error|mixed
 */
function orwo_release_download( $reply, $package, $upgrader, $hook_extra = array() ) {
	if ( ! orwo_update_repo() || ! str_starts_with( (string) $package, orwo_update_api() . '/releases/assets/' ) ) {
		return $reply;
	}

	$release = orwo_release_check();
	if ( is_wp_error( $release ) || $package !== $release['package'] ) {
		return new WP_Error( 'package', __( 'The requested update does not match the checked release.', 'orwo' ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$file = wp_tempnam( ORWO_SLUG . '.zip' );
	if ( ! $file ) {
		return new WP_Error( 'file', __( 'Cannot create the update temporary file.', 'orwo' ) );
	}

	$headers           = orwo_update_headers();
	$headers['Accept'] = 'application/octet-stream';
	$args              = array(
		'headers'             => $headers,
		'timeout'             => 120,
		'redirection'         => 0,
		'stream'              => true,
		'filename'            => $file,
		'limit_response_size' => 80000001,
	);

	$response = wp_safe_remote_get( $package, $args );

	if ( ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 301, 302, 303, 307, 308 ), true ) ) {
		$url  = wp_remote_retrieve_header( $response, 'location' );
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME )
			|| ! in_array( $host, array( 'release-assets.githubusercontent.com', 'objects.githubusercontent.com' ), true )
			|| wp_parse_url( $url, PHP_URL_USER ) || wp_parse_url( $url, PHP_URL_PASS ) ) {
			wp_delete_file( $file );

			return new WP_Error( 'redirect', __( 'GitHub returned an unexpected download host.', 'orwo' ) );
		}

		// Never forward the API credential to a download host or its redirects.
		$args['headers']     = array( 'User-Agent' => 'ORWO-WordPress-Updater' );
		$args['redirection'] = 3;
		$response            = wp_safe_remote_get( $url, $args );
	}

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response )
		|| filesize( $file ) !== $release['size']
		|| ! hash_equals( $release['sha256'], (string) hash_file( 'sha256', $file ) ) ) {
		wp_delete_file( $file );

		return new WP_Error( 'download', __( 'The update failed size or SHA-256 verification. No theme files were installed.', 'orwo' ) );
	}

	$valid = orwo_release_zip_validate( $file, $release['version'] );
	if ( is_wp_error( $valid ) ) {
		wp_delete_file( $file );

		return $valid;
	}

	return $file;
}
add_filter( 'upgrader_pre_download', 'orwo_release_download', 10, 4 );

/**
 * Forgets the cached release once an update has run.
 *
 * @param object $upgrader Upgrader.
 * @param array  $options  Options.
 */
function orwo_clear_release_cache( $upgrader, $options ) {
	if ( 'theme' === ( $options['type'] ?? '' ) ) {
		delete_site_transient( 'orwo_github_release' );
	}
}
add_action( 'upgrader_process_complete', 'orwo_clear_release_cache', 10, 2 );

/**
 * The updates screen.
 */
function orwo_updates_menu() {
	add_theme_page(
		__( 'ORWO Updates', 'orwo' ),
		__( 'ORWO Updates', 'orwo' ),
		'update_themes',
		'orwo-updates',
		'orwo_updates_screen'
	);
}
add_action( 'admin_menu', 'orwo_updates_menu' );

/**
 * Renders the updates screen.
 */
function orwo_updates_screen() {
	if ( ! current_user_can( 'update_themes' ) ) {
		return;
	}

	$force = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['orwo_check'] );
	if ( $force ) {
		check_admin_referer( 'orwo-check-updates' );
		delete_site_transient( 'orwo_github_release' );
	}

	$theme = wp_get_theme( ORWO_SLUG );
	echo '<div class="wrap"><h1>' . esc_html__( 'ORWO Updates', 'orwo' ) . '</h1>';
	echo '<p>' . esc_html__( 'Installed version:', 'orwo' ) . ' <strong>' . esc_html( $theme->get( 'Version' ) ) . '</strong></p>';

	orwo_repo_form();

	if ( ! orwo_update_repo() ) {
		echo '<p>' . esc_html__( 'Set the repository above to switch updates on.', 'orwo' ) . '</p></div>';

		return;
	}

	echo '<form method="post">';
	wp_nonce_field( 'orwo-check-updates' );
	echo '<button class="button" type="submit" name="orwo_check" value="1">' . esc_html__( 'Check for updates', 'orwo' ) . '</button></form>';

	$release = orwo_release_check( $force );
	if ( is_wp_error( $release ) ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( $release->get_error_message() ) . '</p></div>';
	} else {
		echo '<p>' . esc_html__( 'Latest release:', 'orwo' ) . ' <strong>' . esc_html( $release['version'] ) . '</strong></p>';
		if ( version_compare( $release['version'], $theme->get( 'Version' ), '>' ) ) {
			$url = wp_nonce_url( admin_url( 'update.php?action=upgrade-theme&theme=' . ORWO_SLUG ), 'upgrade-theme_' . ORWO_SLUG );
			echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Update the theme', 'orwo' ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'The theme is up to date.', 'orwo' ) . '</p>';
		}
		echo '<h2>' . esc_html__( 'Release notes', 'orwo' ) . '</h2><div style="white-space:pre-wrap">' . esc_html( $release['notes'] ) . '</div>';
	}

	echo '<p>' . esc_html__( 'Back up first. An update replaces theme files and keeps pages, menus, media, products and settings.', 'orwo' ) . '</p>';
	orwo_token_form();
	echo '</div>';
}

/**
 * The repository box.
 */
function orwo_repo_form() {
	echo '<h2>' . esc_html__( 'Repository', 'orwo' ) . '</h2>';

	if ( defined( 'ORWO_GITHUB_REPO' ) && ORWO_GITHUB_REPO ) {
		echo '<p>' . esc_html__( 'Set in wp-config.php:', 'orwo' ) . ' <code>' . esc_html( orwo_update_repo() ) . '</code></p>';

		return;
	}

	$saved = orwo_update_repo();
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="orwo_save_repo">';
	wp_nonce_field( 'orwo_save_repo' );
	echo '<p><input type="text" name="orwo_repo" class="regular-text" placeholder="owner/repository" value="' . esc_attr( $saved ) . '"> ';
	echo '<button class="button" type="submit">' . esc_html__( 'Save the repository', 'orwo' ) . '</button></p>';
	echo '<p class="description">' . esc_html__( 'The GitHub repository that holds this theme, as owner/repository.', 'orwo' ) . '</p></form>';
}

/**
 * Saves the repository.
 */
function orwo_save_repo() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'update_themes' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'orwo' ) );
	}
	check_admin_referer( 'orwo_save_repo' );

	$repo = trim( (string) wp_unslash( $_POST['orwo_repo'] ?? '' ) );
	if ( '' === $repo ) {
		delete_option( 'orwo_github_repo' );
	} elseif ( preg_match( '~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repo ) ) {
		update_option( 'orwo_github_repo', $repo, false );
	}

	delete_site_transient( 'orwo_github_release' );
	delete_site_transient( 'update_themes' );
	wp_safe_redirect( admin_url( 'themes.php?page=orwo-updates' ) );
	exit;
}
add_action( 'admin_post_orwo_save_repo', 'orwo_save_repo' );

/**
 * The connection box for a private repository. A saved token is never printed back.
 */
function orwo_token_form() {
	$saved = (string) get_option( 'orwo_github_token', '' );
	echo '<h2>' . esc_html__( 'GitHub connection', 'orwo' ) . '</h2>';

	if ( defined( 'ORWO_GITHUB_TOKEN' ) && ORWO_GITHUB_TOKEN ) {
		echo '<p>' . esc_html__( 'Connected through ORWO_GITHUB_TOKEN in wp-config.php.', 'orwo' ) . '</p>';

		return;
	}

	if ( '' !== $saved ) {
		echo '<p>' . esc_html__( 'Using a saved token ending in', 'orwo' ) . ' <code>' . esc_html( substr( $saved, -4 ) ) . '</code>.</p>';
	} else {
		echo '<p>' . esc_html__( 'No token saved. A public repository needs none; a private one does.', 'orwo' ) . '</p>';
	}

	echo '<p>' . esc_html__( 'Create a fine-grained personal access token with access to only this repository and Contents: Read-only, and paste it here once.', 'orwo' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="orwo_save_token">';
	wp_nonce_field( 'orwo_save_token' );
	echo '<p><input type="password" name="orwo_token" class="regular-text" autocomplete="off" placeholder="github_pat_…"> ';
	echo '<button class="button button-primary" type="submit">' . esc_html__( 'Save the token', 'orwo' ) . '</button>';
	if ( '' !== $saved ) {
		echo ' <button class="button" type="submit" name="orwo_remove" value="1">' . esc_html__( 'Remove the token', 'orwo' ) . '</button>';
	}
	echo '</p></form>';
}

/**
 * Saves or removes the token.
 */
function orwo_save_token() {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'update_themes' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'orwo' ) );
	}
	check_admin_referer( 'orwo_save_token' );

	if ( ! empty( $_POST['orwo_remove'] ) ) {
		delete_option( 'orwo_github_token' );
	} else {
		$token = trim( (string) wp_unslash( $_POST['orwo_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidationSanitization.InputNotSanitized -- Checked against a strict pattern.
		if ( orwo_token_valid( $token ) ) {
			update_option( 'orwo_github_token', $token, false );
		}
	}

	delete_site_transient( 'orwo_github_release' );
	delete_site_transient( 'update_themes' );
	wp_safe_redirect( admin_url( 'themes.php?page=orwo-updates' ) );
	exit;
}
add_action( 'admin_post_orwo_save_token', 'orwo_save_token' );
