<?php
/**
 * Updates from GitHub Releases.
 *
 * Every site checks https://github.com/jadealombro/dejafe-forms/releases/latest (cached for 6 hours)
 * and shows the update on the Plugins screen like any other plugin. Releases are built by
 * .github/workflows/release.yml when a new version reaches the main branch.
 *
 * Private repository: add a fine-grained token with read access to this repo's contents
 * in wp-config.php: define( 'DEJAFE_FORMS_GITHUB_TOKEN', 'github_pat_...' );
 *
 * @package dejafe-forms
 */

defined( 'ABSPATH' ) || exit;

define( 'DEJAFE_FORMS_REPO', 'jadealombro/dejafe-forms' );

/**
 * Latest release, or null when unavailable.
 *
 * @param bool $force Skip the cache.
 * @return array|null
 */
function dejafe_forms_latest_release( $force = false ) {
	$cached = $force ? false : get_site_transient( 'dejafe_forms_release' );
	if ( false !== $cached ) {
		return $cached ? $cached : null;
	}

	$headers = array(
		'Accept'     => 'application/vnd.github+json',
		'User-Agent' => 'dejafe-forms/' . DEJAFE_FORMS_VERSION,
	);
	if ( defined( 'DEJAFE_FORMS_GITHUB_TOKEN' ) && DEJAFE_FORMS_GITHUB_TOKEN ) {
		$headers['Authorization'] = 'Bearer ' . DEJAFE_FORMS_GITHUB_TOKEN;
	}
	$response = wp_remote_get(
		'https://api.github.com/repos/' . DEJAFE_FORMS_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => $headers,
		)
	);
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		set_site_transient( 'dejafe_forms_release', '', HOUR_IN_SECONDS );
		return null;
	}

	$data    = json_decode( wp_remote_retrieve_body( $response ), true );
	$package = '';
	foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
		if ( 'dejafe-forms.zip' === ( $asset['name'] ?? '' ) ) {
			// Private repos need the API URL (downloaded with the token), public ones the direct link.
			$package = isset( $headers['Authorization'] ) ? $asset['url'] : $asset['browser_download_url'];
		}
	}

	$release = array(
		'version'   => ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' ),
		'package'   => $package,
		'url'       => (string) ( $data['html_url'] ?? '' ),
		'notes'     => (string) ( $data['body'] ?? '' ),
		'published' => (string) ( $data['published_at'] ?? '' ),
	);
	set_site_transient( 'dejafe_forms_release', $release, 6 * HOUR_IN_SECONDS );
	return $release;
}

// Tell WordPress about new versions (works through the plugin's "Update URI" header).
add_filter(
	'update_plugins_github.com',
	function ( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( DEJAFE_FORMS_FILE ) !== $plugin_file ) {
			return $update;
		}
		$release = dejafe_forms_latest_release();
		if ( ! $release || ! $release['version'] || ! $release['package'] ) {
			return $update;
		}
		return array(
			'id'           => 'github.com/' . DEJAFE_FORMS_REPO,
			'slug'         => 'dejafe-forms',
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => '6.6',
			'requires_php' => '7.4',
		);
	},
	10,
	3
);

// "View details" popup on the Plugins screen.
add_filter(
	'plugins_api',
	function ( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || 'dejafe-forms' !== ( $args->slug ?? '' ) ) {
			return $result;
		}
		$release = dejafe_forms_latest_release();
		if ( ! $release ) {
			return $result;
		}
		return (object) array(
			'name'          => 'Dejafe Forms',
			'slug'          => 'dejafe-forms',
			'version'       => $release['version'],
			'author'        => '<a href="https://dejafe.dev/">Dejafe</a>',
			'homepage'      => $release['url'],
			'requires'      => '6.6',
			'requires_php'  => '7.4',
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				'changelog' => $release['notes'] ? wpautop( esc_html( $release['notes'] ) ) : esc_html__( 'See the release on GitHub.', 'dejafe-forms' ),
			),
		);
	},
	10,
	3
);

// Private repos: authenticate the zip download.
add_filter(
	'http_request_args',
	function ( $args, $url ) {
		if ( defined( 'DEJAFE_FORMS_GITHUB_TOKEN' ) && DEJAFE_FORMS_GITHUB_TOKEN && 0 === strpos( $url, 'https://api.github.com/repos/' . DEJAFE_FORMS_REPO . '/releases/assets/' ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . DEJAFE_FORMS_GITHUB_TOKEN;
			$args['headers']['Accept']        = 'application/octet-stream';
		}
		return $args;
	},
	10,
	2
);

// Forget the cached release after updating, and when someone clicks "Check again".
add_action(
	'upgrader_process_complete',
	function () {
		delete_site_transient( 'dejafe_forms_release' );
	}
);
add_action(
	'load-update-core.php',
	function () {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			delete_site_transient( 'dejafe_forms_release' );
		}
	}
);
