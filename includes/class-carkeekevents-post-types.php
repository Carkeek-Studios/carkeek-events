<?php
/**
 * Post Types and Taxonomy Registration
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Post_Types
 *
 * Registers the carkeek_event, carkeek_location, and carkeek_organizer CPTs
 * along with the carkeek_event_category taxonomy.
 */
class CarkeekEvents_Post_Types {

	/**
	 * Register all CPTs and taxonomy, and hook into WordPress.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_action( 'init', array( $instance, 'register_post_types' ) );
		add_action( 'init', array( $instance, 'register_taxonomy' ) );
		add_action( 'before_delete_post', array( $instance, 'clear_stale_location_organizer_ids' ) );
	}

	/**
	 * Register the three CPTs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_types() {
		// Read archive settings — controls has_archive at registration time.
		$settings        = get_option( 'carkeek_events_settings', array() );
		$disable_archive = ! empty( $settings['disable_wp_archive'] );
		$archive_slug    = sanitize_title( $settings['archive_slug'] ?? 'events' ) ?: 'events';
		$has_archive     = $disable_archive ? false : $archive_slug;

		// --- carkeek_event ---
		$event_labels = array(
			'name'                  => _x( 'Events', 'Post type general name', 'carkeek-events' ),
			'singular_name'         => _x( 'Event', 'Post type singular name', 'carkeek-events' ),
			'menu_name'             => _x( 'Events', 'Admin Menu text', 'carkeek-events' ),
			'name_admin_bar'        => _x( 'Event', 'Add New on Toolbar', 'carkeek-events' ),
			'add_new'               => __( 'Add New Event', 'carkeek-events' ),
			'add_new_item'          => __( 'Add New Event', 'carkeek-events' ),
			'new_item'              => __( 'New Event', 'carkeek-events' ),
			'edit_item'             => __( 'Edit Event', 'carkeek-events' ),
			'view_item'             => __( 'View Event', 'carkeek-events' ),
			'all_items'             => __( 'All Events', 'carkeek-events' ),
			'search_items'          => __( 'Search Events', 'carkeek-events' ),
			'not_found'             => __( 'No events found.', 'carkeek-events' ),
			'not_found_in_trash'    => __( 'No events found in Trash.', 'carkeek-events' ),
			'featured_image'        => _x( 'Event Image', 'Overrides the Featured Image phrase', 'carkeek-events' ),
			'set_featured_image'    => _x( 'Set event image', 'Overrides Set featured image', 'carkeek-events' ),
			'remove_featured_image' => _x( 'Remove event image', 'Overrides Remove featured image', 'carkeek-events' ),
			'use_featured_image'    => _x( 'Use as event image', 'Overrides Use as featured image', 'carkeek-events' ),
		);

		register_post_type(
			'carkeek_event',
			array(
				'labels'        => $event_labels,
				'public'        => true,
				'show_in_rest'  => true,
				'has_archive'   => $has_archive,
				'menu_icon'     => 'dashicons-calendar-alt',
				'menu_position' => 5,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'rewrite'       => array( 'slug' => 'event', 'with_front' => false ),
			)
		);

		// --- carkeek_location ---
		$location_labels = array(
			'name'               => _x( 'Locations', 'Post type general name', 'carkeek-events' ),
			'singular_name'      => _x( 'Location', 'Post type singular name', 'carkeek-events' ),
			'menu_name'          => _x( 'Locations', 'Admin Menu text', 'carkeek-events' ),
			'name_admin_bar'     => _x( 'Location', 'Add New on Toolbar', 'carkeek-events' ),
			'add_new'            => __( 'Add New Location', 'carkeek-events' ),
			'add_new_item'       => __( 'Add New Location', 'carkeek-events' ),
			'new_item'           => __( 'New Location', 'carkeek-events' ),
			'edit_item'          => __( 'Edit Location', 'carkeek-events' ),
			'view_item'          => __( 'View Location', 'carkeek-events' ),
			'all_items'          => __( 'All Locations', 'carkeek-events' ),
			'search_items'       => __( 'Search Locations', 'carkeek-events' ),
			'not_found'          => __( 'No locations found.', 'carkeek-events' ),
			'not_found_in_trash' => __( 'No locations found in Trash.', 'carkeek-events' ),
		);

		register_post_type(
			'carkeek_location',
			array(
				'labels'        => $location_labels,
				'public'        => true,
				'show_in_rest'  => true,
				'show_in_menu'  => 'edit.php?post_type=carkeek_event',
				'supports'      => array( 'title', 'editor', 'thumbnail' ),
				'rewrite'       => array( 'slug' => 'locations', 'with_front' => false ),
			)
		);

		// --- carkeek_organizer ---
		$organizer_labels = array(
			'name'               => _x( 'Organizers', 'Post type general name', 'carkeek-events' ),
			'singular_name'      => _x( 'Organizer', 'Post type singular name', 'carkeek-events' ),
			'menu_name'          => _x( 'Organizers', 'Admin Menu text', 'carkeek-events' ),
			'name_admin_bar'     => _x( 'Organizer', 'Add New on Toolbar', 'carkeek-events' ),
			'add_new'            => __( 'Add New Organizer', 'carkeek-events' ),
			'add_new_item'       => __( 'Add New Organizer', 'carkeek-events' ),
			'new_item'           => __( 'New Organizer', 'carkeek-events' ),
			'edit_item'          => __( 'Edit Organizer', 'carkeek-events' ),
			'view_item'          => __( 'View Organizer', 'carkeek-events' ),
			'all_items'          => __( 'All Organizers', 'carkeek-events' ),
			'search_items'       => __( 'Search Organizers', 'carkeek-events' ),
			'not_found'          => __( 'No organizers found.', 'carkeek-events' ),
			'not_found_in_trash' => __( 'No organizers found in Trash.', 'carkeek-events' ),
		);

		register_post_type(
			'carkeek_organizer',
			array(
				'labels'        => $organizer_labels,
				'public'        => true,
				'show_in_rest'  => true,
				'show_in_menu'  => 'edit.php?post_type=carkeek_event',
				'supports'      => array( 'title', 'editor', 'thumbnail' ),
				'rewrite'       => array( 'slug' => 'organizers', 'with_front' => false ),
			)
		);
	}

	/**
	 * Register the event category taxonomy.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => _x( 'Event Categories', 'taxonomy general name', 'carkeek-events' ),
			'singular_name'     => _x( 'Event Category', 'taxonomy singular name', 'carkeek-events' ),
			'search_items'      => __( 'Search Event Categories', 'carkeek-events' ),
			'all_items'         => __( 'All Event Categories', 'carkeek-events' ),
			'parent_item'       => __( 'Parent Event Category', 'carkeek-events' ),
			'parent_item_colon' => __( 'Parent Event Category:', 'carkeek-events' ),
			'edit_item'         => __( 'Edit Event Category', 'carkeek-events' ),
			'update_item'       => __( 'Update Event Category', 'carkeek-events' ),
			'add_new_item'      => __( 'Add New Event Category', 'carkeek-events' ),
			'new_item_name'     => __( 'New Event Category Name', 'carkeek-events' ),
			'menu_name'         => __( 'Event Categories', 'carkeek-events' ),
		);

		register_taxonomy(
			'carkeek_event_category',
			'carkeek_event',
			array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'event-category', 'with_front' => false ),
			)
		);
	}

	/**
	 * When a Location or Organizer post is permanently deleted, clear stale
	 * post IDs from any events that referenced it.
	 *
	 * @since 1.0.0
	 * @param int $post_id The post ID being deleted.
	 * @return void
	 */
	public function clear_stale_location_organizer_ids( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		if ( 'carkeek_location' === $post->post_type ) {
			$events = get_posts( array(
				'post_type'      => 'carkeek_event',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_carkeek_event_location_id',
						'value' => $post_id,
					),
				),
			) );
			foreach ( $events as $event_id ) {
				delete_post_meta( $event_id, '_carkeek_event_location_id' );
			}
		}

		if ( 'carkeek_organizer' === $post->post_type ) {
			$events = get_posts( array(
				'post_type'      => 'carkeek_event',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_carkeek_event_organizer_id',
						'value' => $post_id,
					),
				),
			) );
			foreach ( $events as $event_id ) {
				delete_post_meta( $event_id, '_carkeek_event_organizer_id' );
			}
		}
	}
}

CarkeekEvents_Post_Types::register();
