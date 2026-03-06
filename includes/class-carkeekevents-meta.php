<?php
/**
 * Meta Field Registration
 *
 * Registers all post meta fields with show_in_rest: true so they are
 * accessible via the REST API and block editor.
 *
 * Meta keys follow the pattern _carkeek_event_*, _carkeek_location_*,
 * _carkeek_organizer_* to namespace clearly and avoid collisions.
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Meta
 */
class CarkeekEvents_Meta {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_action( 'init', array( $instance, 'register_meta_fields' ) );
	}

	/**
	 * Register all meta fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_meta_fields() {
		$auth_callback = function() {
			return current_user_can( 'edit_posts' );
		};

		// ---------------------------------------------------------------
		// carkeek_event meta fields
		// ---------------------------------------------------------------

		// Start date: YYYY-MM-DD (site local time).
		register_meta( 'post', '_carkeek_event_start_date', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Start time: HH:MM (site local time).
		register_meta( 'post', '_carkeek_event_start_time', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// End date: YYYY-MM-DD. Leave blank for open-ended events (they will never expire).
		register_meta( 'post', '_carkeek_event_end_date', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// End time: HH:MM. Used with immediate expiry mode.
		register_meta( 'post', '_carkeek_event_end_time', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Location post ID. 0 if not linked to a CPT record.
		register_meta( 'post', '_carkeek_event_location_id', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'integer',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Location free-text fallback. Used when no CPT record is linked.
		register_meta( 'post', '_carkeek_event_location_text', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Organizer post ID. 0 if not linked to a CPT record.
		register_meta( 'post', '_carkeek_event_organizer_id', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'integer',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Organizer free-text fallback.
		register_meta( 'post', '_carkeek_event_organizer_text', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// External website / registration URL. When set, templates render a CTA button.
		register_meta( 'post', '_carkeek_event_website', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// CTA button label. Defaults to "Sign Up" at render time if blank.
		register_meta( 'post', '_carkeek_event_button_label', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Hidden flag set by the expiry cron. 1 = hidden from front end.
		register_meta( 'post', '_carkeek_event_hidden', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'boolean',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Date when the event was hidden by cron (YYYY-MM-DD). Used for grace period calc.
		register_meta( 'post', '_carkeek_event_hidden_date', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// ---------------------------------------------------------------
		// carkeek_location meta fields
		// ---------------------------------------------------------------

		register_meta( 'post', '_carkeek_location_address', array(
			'object_subtype' => 'carkeek_location',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		register_meta( 'post', '_carkeek_location_city', array(
			'object_subtype' => 'carkeek_location',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		register_meta( 'post', '_carkeek_location_state', array(
			'object_subtype' => 'carkeek_location',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		register_meta( 'post', '_carkeek_location_zip', array(
			'object_subtype' => 'carkeek_location',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		register_meta( 'post', '_carkeek_location_country', array(
			'object_subtype' => 'carkeek_location',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		register_meta( 'post', '_carkeek_location_website', array(
			'object_subtype' => 'carkeek_location',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Latitude (decimal string). Auto-populated by geocoding if API key configured.
		register_meta( 'post', '_carkeek_location_lat', array(
			'object_subtype' => 'carkeek_location',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Longitude (decimal string). Auto-populated by geocoding if API key configured.
		register_meta( 'post', '_carkeek_location_lng', array(
			'object_subtype' => 'carkeek_location',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// ---------------------------------------------------------------
		// carkeek_organizer meta fields
		// ---------------------------------------------------------------

		register_meta( 'post', '_carkeek_organizer_email', array(
			'object_subtype' => 'carkeek_organizer',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		register_meta( 'post', '_carkeek_organizer_phone', array(
			'object_subtype' => 'carkeek_organizer',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		register_meta( 'post', '_carkeek_organizer_website', array(
			'object_subtype' => 'carkeek_organizer',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );
	}
}

CarkeekEvents_Meta::register();
