<?php
/**
 * Admin Menu & Asset Enqueueing
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Admin
 */
class CarkeekEvents_Admin {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_action( 'admin_menu', array( $instance, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_admin_assets' ) );
		add_filter( 'manage_carkeek_event_posts_columns', array( $instance, 'add_event_columns' ) );
		add_action( 'manage_carkeek_event_posts_custom_column', array( $instance, 'render_event_columns' ), 10, 2 );
		add_filter( 'manage_edit-carkeek_event_sortable_columns', array( $instance, 'sortable_event_columns' ) );
		// Show private (expired) events in the admin list table alongside published ones.
		add_action( 'pre_get_posts', array( $instance, 'show_private_in_list_table' ) );
	}

	/**
	 * Include private (expired) posts in the admin events list table.
	 *
	 * @since 2.0.0
	 * @param WP_Query $query Current WP_Query.
	 * @return void
	 */
	public function show_private_in_list_table( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'carkeek_event' !== $query->get( 'post_type' ) ) {
			return;
		}
		// Only adjust when viewing all posts (no explicit status filter selected).
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'edit-carkeek_event' === $screen->id && ! isset( $_GET['post_status'] ) ) {
			$query->set( 'post_status', array( 'publish', 'private', 'draft', 'pending' ) );
		}
	}

	/**
	 * Register the settings submenu under Settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=carkeek_event',
			__( 'Events Settings', 'carkeek-events' ),
			__( 'Settings', 'carkeek-events' ),
			'manage_options',
			'carkeek-events',
			array( new CarkeekEvents_Settings(), 'settings_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS only on relevant screens.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		global $post;

		$is_cpt_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true )
			&& $post
			&& in_array( $post->post_type, array( 'carkeek_event', 'carkeek_location', 'carkeek_organizer' ), true );

		$is_settings_screen = 'carkeek_event_page_carkeek-events' === $hook;

		if ( ! $is_cpt_screen && ! $is_settings_screen ) {
			return;
		}

		wp_enqueue_style(
			'carkeek-events-admin',
			CARKEEKEVENTS_PLUGIN_URL . 'assets/css/carkeek-events-admin.css',
			array(),
			CARKEEKEVENTS_VERSION
		);

		wp_enqueue_script(
			'carkeek-events-admin',
			CARKEEKEVENTS_PLUGIN_URL . 'assets/js/carkeek-events-admin.js',
			array( 'jquery' ),
			CARKEEKEVENTS_VERSION,
			true
		);

		wp_localize_script( 'carkeek-events-admin', 'carkeekEventsAdmin', array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'searchNonce'    => wp_create_nonce( 'carkeek_events_admin' ),
			'geocodeNonce'   => wp_create_nonce( 'carkeek_events_geocode' ),
			'i18n'           => array(
				'geocodeConfirm'  => __( 'Overwrite existing coordinates?', 'carkeek-events' ),
				'geocoding'       => __( 'Geocoding…', 'carkeek-events' ),
				'geocodeSuccess'  => __( 'Coordinates updated.', 'carkeek-events' ),
				'geocodeNoResult' => __( 'No results found for that address.', 'carkeek-events' ),
				'geocodeError'    => __( 'Geocoding failed. Please try again.', 'carkeek-events' ),
				'geocodeQuota'    => __( 'Too many requests. Please try again later.', 'carkeek-events' ),
				'selectResult'    => __( 'Select a result', 'carkeek-events' ),
				'noResults'       => __( 'No results found.', 'carkeek-events' ),
			),
		) );
	}

	/**
	 * Add custom columns to the events list table.
	 *
	 * @since 1.0.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_event_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['start_date'] = __( 'Start Date', 'carkeek-events' );
				$new['end_date']   = __( 'End Date', 'carkeek-events' );
				$new['location']   = __( 'Location', 'carkeek-events' );
				$new['status']     = __( 'Status', 'carkeek-events' );
			}
		}
		return $new;
	}

	/**
	 * Render custom column content.
	 *
	 * @since 1.0.0
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_event_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'start_date':
				$iso  = get_post_meta( $post_id, '_carkeek_event_start', true );
				$date = $iso ? substr( $iso, 0, 10 ) : '';
				$time = ( $iso && substr( $iso, 11 ) !== '00:00:00' ) ? substr( $iso, 11, 5 ) : '';
				echo esc_html( $date . ( $time ? ' ' . $time : '' ) );
				break;

			case 'end_date':
				$iso  = get_post_meta( $post_id, '_carkeek_event_end', true );
				$date = $iso ? substr( $iso, 0, 10 ) : '';
				$time = ( $iso && substr( $iso, 11 ) !== '00:00:00' ) ? substr( $iso, 11, 5 ) : '';
				if ( $date ) {
					echo esc_html( $date . ( $time ? ' ' . $time : '' ) );
				} else {
					echo '<span style="color:#aaa;">' . esc_html__( 'No end date', 'carkeek-events' ) . '</span>';
				}
				break;

			case 'location':
				$loc_id   = (int) get_post_meta( $post_id, '_carkeek_event_location_id', true );
				$loc_text = get_post_meta( $post_id, '_carkeek_event_location_text', true );
				if ( $loc_id ) {
					$loc = get_post( $loc_id );
					if ( $loc ) {
						echo '<a href="' . esc_url( get_edit_post_link( $loc_id ) ) . '">' . esc_html( $loc->post_title ) . '</a>';
						break;
					}
				}
				echo esc_html( $loc_text ?: '—' );
				break;

			case 'status':
				$post_obj = get_post( $post_id );
				if ( $post_obj && 'private' === $post_obj->post_status ) {
					echo '<span style="color:#888;">&#9679; ' . esc_html__( 'Expired', 'carkeek-events' ) . '</span>';
				} elseif ( get_post_meta( $post_id, '_carkeek_event_hidden', true ) === '1' ) {
					echo '<span style="color:#dba617;">&#9679; ' . esc_html__( 'Hidden', 'carkeek-events' ) . '</span>';
				} else {
					echo '<span style="color:#00a32a;">&#9679; ' . esc_html__( 'Active', 'carkeek-events' ) . '</span>';
				}
				break;
		}
	}

	/**
	 * Make start_date column sortable.
	 *
	 * @since 1.0.0
	 * @param array $columns Sortable columns.
	 * @return array Modified sortable columns.
	 */
	public function sortable_event_columns( $columns ) {
		$columns['start_date'] = 'start_date';
		return $columns;
	}
}

CarkeekEvents_Admin::register();
