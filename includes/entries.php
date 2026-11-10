<?php
/**
 * Form entries: private, admin-only backup of submissions with automatic deletion.
 *
 * @package dejafe-forms
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_type(
			'dejafe_entry',
			array(
				'labels'              => array(
					'name'               => __( 'Form entries', 'dejafe-forms' ),
					'singular_name'      => __( 'Form entry', 'dejafe-forms' ),
					'menu_name'          => __( 'Form entries', 'dejafe-forms' ),
					'all_items'          => __( 'All entries', 'dejafe-forms' ),
					'edit_item'          => __( 'Entry', 'dejafe-forms' ),
					'search_items'       => __( 'Search entries', 'dejafe-forms' ),
					'not_found'          => __( 'No entries yet.', 'dejafe-forms' ),
					'not_found_in_trash' => __( 'No entries in the trash.', 'dejafe-forms' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_position'       => 26,
				'menu_icon'           => 'dashicons-email-alt',
				'supports'            => false,
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'delete_posts'       => 'manage_options',
					'publish_posts'      => 'do_not_allow',
					'read_private_posts' => 'manage_options',
					'create_posts'       => 'do_not_allow',
				),
			)
		);
	}
);

/**
 * Save a submission.
 *
 * @param array $fields  Sanitized fields.
 * @param int   $post_id Page the form was on.
 * @return int Entry ID or 0.
 */
function dejafe_forms_save_entry( $fields, $post_id ) {
	if ( ! dejafe_forms_setting( 'store_entries' ) ) {
		return 0;
	}
	$name = trim( $fields['first_name'] . ' ' . $fields['last_name'] );
	$id   = wp_insert_post(
		array(
			'post_type'   => 'dejafe_entry',
			'post_status' => 'private',
			'post_title'  => $name ? $name : $fields['email'],
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( $id, '_dejafe_fields', $fields );
	update_post_meta( $id, '_dejafe_page', (int) $post_id );
	return (int) $id;
}

/* ---------- List screen ---------- */

add_filter(
	'manage_dejafe_entry_posts_columns',
	function () {
		return array(
			'cb'           => '<input type="checkbox" />',
			'title'        => __( 'Name', 'dejafe-forms' ),
			'dejafe_email' => __( 'Email', 'dejafe-forms' ),
			'dejafe_phone' => __( 'Phone', 'dejafe-forms' ),
			'dejafe_page'  => __( 'Page', 'dejafe-forms' ),
			'dejafe_mail'  => __( 'Email sent', 'dejafe-forms' ),
			'dejafe_date'  => __( 'Received', 'dejafe-forms' ),
		);
	}
);

add_action(
	'manage_dejafe_entry_posts_custom_column',
	function ( $column, $id ) {
		$f = (array) get_post_meta( $id, '_dejafe_fields', true );
		switch ( $column ) {
			case 'dejafe_email':
				$email = $f['email'] ?? '';
				echo $email ? '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>' : '';
				break;
			case 'dejafe_phone':
				echo esc_html( $f['phone'] ?? '' );
				break;
			case 'dejafe_page':
				$page = (int) get_post_meta( $id, '_dejafe_page', true );
				echo $page ? esc_html( get_the_title( $page ) ) : '';
				break;
			case 'dejafe_date':
				echo esc_html( get_the_date( '', $id ) . ' ' . get_the_time( '', $id ) );
				break;
			case 'dejafe_mail':
				$status = get_post_meta( $id, '_dejafe_mail', true );
				echo 'failed' === $status ? '<span style="color:#b32d2e">' . esc_html__( 'Failed', 'dejafe-forms' ) . '</span>' : esc_html__( 'Yes', 'dejafe-forms' );
				break;
		}
	},
	10,
	2
);

// Entries are private posts; show them under "All" and hide the misleading "Private" label.
add_filter(
	'display_post_states',
	function ( $states, $post ) {
		if ( 'dejafe_entry' === $post->post_type ) {
			unset( $states['private'] );
		}
		return $states;
	},
	10,
	2
);

// Row actions: View and Delete permanently (no editing, no trash).
add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( 'dejafe_entry' !== $post->post_type ) {
			return $actions;
		}
		return array(
			'view'   => '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'View', 'dejafe-forms' ) . '</a>',
			'delete' => '<a class="submitdelete" href="' . esc_url( get_delete_post_link( $post->ID, '', true ) ) . '">' . esc_html__( 'Delete permanently', 'dejafe-forms' ) . '</a>',
		);
	},
	10,
	2
);

add_filter(
	'manage_edit-dejafe_entry_sortable_columns',
	function ( $columns ) {
		$columns['dejafe_date'] = 'date';
		return $columns;
	}
);

add_filter(
	'bulk_actions-edit-dejafe_entry',
	function () {
		return array( 'delete' => __( 'Delete permanently', 'dejafe-forms' ) );
	}
);

// Show the delete action for entries even though they're never trashed.
add_action(
	'load-edit.php',
	function () {
		if ( isset( $_GET['post_type'] ) && 'dejafe_entry' === $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_filter( 'views_edit-dejafe_entry', '__return_empty_array' );
		}
	}
);

/* ---------- Single entry screen (read only) ---------- */

add_action(
	'add_meta_boxes_dejafe_entry',
	function () {
		remove_meta_box( 'submitdiv', 'dejafe_entry', 'side' );
		remove_meta_box( 'slugdiv', 'dejafe_entry', 'normal' );
		add_meta_box( 'dejafe-entry', __( 'Submission', 'dejafe-forms' ), 'dejafe_forms_entry_box', 'dejafe_entry', 'normal', 'high' );
		add_meta_box( 'dejafe-entry-actions', __( 'Entry', 'dejafe-forms' ), 'dejafe_forms_entry_actions', 'dejafe_entry', 'side', 'high' );
	}
);

/**
 * Entry details.
 *
 * @param WP_Post $post Entry.
 */
function dejafe_forms_entry_box( $post ) {
	$f      = (array) get_post_meta( $post->ID, '_dejafe_fields', true );
	$labels = array(
		'first_name' => __( 'First name', 'dejafe-forms' ),
		'last_name'  => __( 'Last name', 'dejafe-forms' ),
		'email'      => __( 'Email', 'dejafe-forms' ),
		'phone'      => __( 'Phone', 'dejafe-forms' ),
		'message'    => __( 'Message', 'dejafe-forms' ),
	);
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $labels as $key => $label ) {
		if ( ! isset( $f[ $key ] ) || '' === $f[ $key ] ) {
			continue;
		}
		$value = 'message' === $key ? wpautop( esc_html( $f[ $key ] ) ) : esc_html( $f[ $key ] );
		if ( 'email' === $key ) {
			$value = '<a href="' . esc_url( 'mailto:' . $f[ $key ] ) . '">' . esc_html( $f[ $key ] ) . '</a>';
		}
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . wp_kses_post( $value ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * Entry meta and delete link.
 *
 * @param WP_Post $post Entry.
 */
function dejafe_forms_entry_actions( $post ) {
	$page   = (int) get_post_meta( $post->ID, '_dejafe_page', true );
	$days   = (int) dejafe_forms_setting( 'retention_days' );
	$expiry = strtotime( $post->post_date_gmt . ' UTC' ) + $days * DAY_IN_SECONDS;
	echo '<p><strong>' . esc_html__( 'Received:', 'dejafe-forms' ) . '</strong> ' . esc_html( get_the_date( '', $post ) . ' ' . get_the_time( '', $post ) ) . '</p>';
	if ( $page ) {
		echo '<p><strong>' . esc_html__( 'Page:', 'dejafe-forms' ) . '</strong> ' . esc_html( get_the_title( $page ) ) . '</p>';
	}
	if ( 'failed' === get_post_meta( $post->ID, '_dejafe_mail', true ) ) {
		echo '<p style="color:#b32d2e">' . esc_html__( 'The notification email failed to send. Reply to this person directly.', 'dejafe-forms' ) . '</p>';
	}
	/* translators: %s: date. */
	echo '<p class="description">' . esc_html( sprintf( __( 'Deleted automatically on %s.', 'dejafe-forms' ), wp_date( get_option( 'date_format' ), $expiry ) ) ) . '</p>';
	echo '<p><a class="button button-link-delete" href="' . esc_url( get_delete_post_link( $post->ID, '', true ) ) . '">' . esc_html__( 'Delete permanently', 'dejafe-forms' ) . '</a></p>';
}

// No title field or editor on entries.
add_action(
	'admin_head-post.php',
	function () {
		if ( 'dejafe_entry' === get_post_type() ) {
			echo '<style>#post-body-content,#titlediv{display:none}.page-title-action{display:none}</style>';
		}
	}
);

/* ---------- Automatic deletion ---------- */

add_action(
	'dejafe_forms_cleanup',
	function () {
		$days = max( 1, (int) dejafe_forms_setting( 'retention_days' ) );
		do {
			$ids = get_posts(
				array(
					'post_type'        => 'dejafe_entry',
					'post_status'      => 'any',
					'fields'           => 'ids',
					'posts_per_page'   => 200,
					'no_found_rows'    => true,
					'suppress_filters' => true,
					'date_query'       => array(
						array(
							'column' => 'post_date_gmt',
							'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
						),
					),
				)
			);
			foreach ( $ids as $id ) {
				wp_delete_post( $id, true );
			}
		} while ( count( $ids ) === 200 );
	}
);

// Make sure the daily cleanup stays scheduled (e.g. after a migration).
add_action(
	'admin_init',
	function () {
		if ( ! wp_next_scheduled( 'dejafe_forms_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'dejafe_forms_cleanup' );
		}
	}
);
