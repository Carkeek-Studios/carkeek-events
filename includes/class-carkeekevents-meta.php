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
		// Use edit_post (singular, with post ID) so ownership is respected:
		// Contributors can only edit meta on their own posts, not arbitrary ones.
		$auth_callback = function( $allowed, $meta_key, $object_id ) {
			return current_user_can( 'edit_post', $object_id );
		};

		// ---------------------------------------------------------------
		// carkeek_event meta fields
		// ---------------------------------------------------------------

		// Combined start datetime: ISO 8601 local time (2026-03-15T10:00:00).
		// Time component is 00:00:00 when no time is set.
		register_meta( 'post', '_carkeek_event_start', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Combined end datetime. Empty string for open-ended events (they will never expire).
		register_meta( 'post', '_carkeek_event_end', array(
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

		// Hidden flag. '1' = excluded from archive listings, but direct URL still works.
		// Set by the block editor sidebar plugin via the REST API, or automatically by cron
		// when the event end date passes.
		register_meta( 'post', '_carkeek_event_hidden', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'default'        => '0',
			'show_in_rest'   => true,
			'auth_callback'  => $auth_callback,
		) );

		// Manual restore flag. '1' = an editor explicitly unhid this event via the sidebar.
		// The daily cron checks this flag and will not auto-re-hide events where it is set.
		// Cleared (set to '0') when an editor manually hides an event.
		register_meta( 'post', '_carkeek_event_manually_restored', array(
			'object_subtype' => 'carkeek_event',
			'type'           => 'string',
			'single'         => true,
			'default'        => '0',
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
