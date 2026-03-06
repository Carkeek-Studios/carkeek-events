<?php
/**
 * Google Maps Geocoding
 *
 * Server-side AJAX handler for geocoding location addresses.
 * The API key is stored server-side and never exposed to the browser.
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Geocode
 */
class CarkeekEvents_Geocode {

	/**
	 * Google Maps Geocoding API endpoint.
	 *
	 * @var string
	 */
	const API_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_action( 'wp_ajax_carkeek_events_geocode', array( $instance, 'handle_geocode' ) );
	}

	/**
	 * AJAX handler: geocode an address and return lat/lng.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_geocode() {
		check_ajax_referer( 'carkeek_events_geocode', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array(
				'code'    => 'unauthorized',
				'message' => __( 'You do not have permission to do this.', 'carkeek-events' ),
			) );
		}

		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$api_key  = $settings['google_maps_api_key'] ?? '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array(
				'code'    => 'no_api_key',
				'message' => __( 'No Google Maps API key configured.', 'carkeek-events' ),
			) );
		}

		// Build address string from posted fields.
		$parts   = array_filter( array(
			sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['zip'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ),
		) );
		$address = implode( ', ', $parts );

		if ( empty( $address ) ) {
			wp_send_json_error( array(
				'code'    => 'no_address',
				'message' => __( 'Please enter an address before geocoding.', 'carkeek-events' ),
			) );
		}

		$url      = add_query_arg( array(
			'address' => rawurlencode( $address ),
			'key'     => $api_key,
		), self::API_URL );

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array(
				'code'    => 'request_failed',
				'message' => $response->get_error_message(),
			) );
		}

		$http_code = wp_remote_retrieve_response_code( $response );

		if ( 429 === $http_code ) {
			wp_send_json_error( array(
				'code'    => 'quota_exceeded',
				'message' => __( 'Too many requests. Please try again later.', 'carkeek-events' ),
			) );
		}

		if ( 403 === $http_code ) {
			wp_send_json_error( array(
				'code'    => 'invalid_key',
				'message' => __( 'API key is invalid or missing required permissions.', 'carkeek-events' ),
			) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body ) || ! isset( $body['status'] ) ) {
			wp_send_json_error( array(
				'code'    => 'parse_error',
				'message' => __( 'Unexpected response from Google Maps.', 'carkeek-events' ),
			) );
		}

		if ( 'ZERO_RESULTS' === $body['status'] ) {
			wp_send_json_error( array(
				'code'    => 'zero_results',
				'message' => __( 'No results found for that address.', 'carkeek-events' ),
			) );
		}

		if ( 'REQUEST_DENIED' === $body['status'] || 'OVER_QUERY_LIMIT' === $body['status'] ) {
			wp_send_json_error( array(
				'code'    => strtolower( $body['status'] ),
				'message' => $body['error_message'] ?? __( 'Google Maps request denied.', 'carkeek-events' ),
			) );
		}

		if ( 'OK' !== $body['status'] || empty( $body['results'][0] ) ) {
			wp_send_json_error( array(
				'code'    => 'api_error',
				'message' => $body['error_message'] ?? __( 'Geocoding failed.', 'carkeek-events' ),
			) );
		}

		$location = $body['results'][0]['geometry']['location'];
		$lat      = (string) $location['lat'];
		$lng      = (string) $location['lng'];
		$post_id  = absint( $_POST['post_id'] ?? 0 );

		// Fire action so add-ons can hook in.
		do_action( 'carkeek_events_after_geocode', $post_id, $lat, $lng );

		wp_send_json_success( array(
			'lat'            => $lat,
			'lng'            => $lng,
			'formatted_address' => $body['results'][0]['formatted_address'] ?? $address,
		) );
	}
}

CarkeekEvents_Geocode::register();
