<?php
/**
 * Runs when the plugin is deleted from the Plugins screen (not when it is only deactivated).
 *
 * Removes everything Dejafe Forms stored: its settings, every form entry with its details,
 * the daily clean-up event, the cached release and the rate-limit counters.
 * Forms already placed in pages stay in the page content.
 *
 * @package dejafe-forms
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove the plugin's data from the current site.
 */
function dejafe_forms_uninstall_site() {
	global $wpdb;

	delete_option( 'dejafe_forms_settings' );
	wp_clear_scheduled_hook( 'dejafe_forms_cleanup' );

	// Entries, in batches. wp_delete_post() also removes their meta.
	do {
		$ids = get_posts(
			array(
				'post_type'        => 'dejafe_entry',
				'post_status'      => 'any',
				'fields'           => 'ids',
				'posts_per_page'   => 200,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	} while ( $ids );

	// Rate-limit counters expire after 10 minutes, but are removed now rather than left behind.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_dejafe_rl_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_dejafe_rl_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $dejafe_forms_site_id ) {
		switch_to_blog( $dejafe_forms_site_id );
		dejafe_forms_uninstall_site();
		restore_current_blog();
	}
} else {
	dejafe_forms_uninstall_site();
}

// The cached GitHub release is stored network-wide.
delete_site_transient( 'dejafe_forms_release' );
