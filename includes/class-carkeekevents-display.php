<?php
/**
 * Display Helpers
 *
 * Static helpers for formatting event dates, locations, and organizers
 * according to the global display settings.
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Display
 */
class CarkeekEvents_Display {

	// -----------------------------------------------------------------------
	// Date / time formatting
	// -----------------------------------------------------------------------

	/**
	 * Format an event date/time range into a human-readable string.
	 *
	 * Same-day rule: if start and end share the same date, output is
	 *   {date}, {start time} – {end time}
	 * Multi-day rule:
	 *   {start date}, {start time} – {end date}, {end time}
	 *
	 * Fires the `carkeek_events_date_range` filter so developers can override.
	 *
	 * @since 1.0.0
	 * @param string $start_date Y-m-d
	 * @param string $start_time H:i  (may be empty)
	 * @param string $end_date   Y-m-d (may be empty)
	 * @param string $end_time   H:i  (may be empty)
	 * @return string Formatted, escaped HTML string.
	 */
	public static function format_date_range( $start_date, $start_time, $end_date, $end_time ) {
		if ( ! $start_date ) {
			return '';
		}

		$settings    = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$date_format = ! empty( $settings['date_format'] ) ? $settings['date_format'] : get_option( 'date_format' );
		$time_format = ! empty( $settings['time_format'] ) ? $settings['time_format'] : get_option( 'time_format' );

		$start_ts = strtotime( $start_date );
		$end_ts   = ( $end_date && $end_date !== $start_date ) ? strtotime( $end_date ) : 0;
		$same_day = ! $end_ts; // no end_ts means same day (or no end date).

		$output = date_i18n( $date_format, $start_ts );

		if ( $start_time ) {
			$start_time_ts = strtotime( $start_date . ' ' . $start_time );
			$output       .= ', ' . date_i18n( $time_format, $start_time_ts );
		}

		if ( $same_day ) {
			// Same day: append end time if present.
			if ( $start_time && $end_time ) {
				$end_time_ts = strtotime( $start_date . ' ' . $end_time );
				$output     .= ' &ndash; ' . date_i18n( $time_format, $end_time_ts );
			}
		} else {
			// Multi-day: append full end date (and time if present).
			$output .= ' &ndash; ' . date_i18n( $date_format, $end_ts );
			if ( $end_time ) {
				$end_time_ts = strtotime( $end_date . ' ' . $end_time );
				$output     .= ', ' . date_i18n( $time_format, $end_time_ts );
			}
		}

		return apply_filters( 'carkeek_events_date_range', $output, $start_date, $start_time, $end_date, $end_time );
	}

	// -----------------------------------------------------------------------
	// Location display
	// -----------------------------------------------------------------------

	/**
	 * Build location HTML per the `location_display` setting.
	 *
	 * Modes:
	 *   link               — linked location name (default)
	 *   address            — formatted address block
	 *   address_directions — address + Google Maps directions link
	 *
	 * Fires `carkeek_events_location_display` filter before returning.
	 *
	 * @since 1.0.0
	 * @param int    $location_id   Linked carkeek_location post ID (0 if none).
	 * @param string $location_text Free-text fallback.
	 * @param int    $post_id       Parent event post ID (passed to filter).
	 * @return string HTML, or empty string if nothing to display.
	 */
	public static function get_location_html( $location_id, $location_text = '', $post_id = 0 ) {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$mode     = ! empty( $settings['location_display'] ) ? $settings['location_display'] : 'link';

		$html = '';

		if ( $location_id ) {
			$loc = get_post( $location_id );
			if ( $loc && 'publish' === $loc->post_status ) {
				if ( 'link' === $mode ) {
					$html = '<a href="' . esc_url( get_permalink( $location_id ) ) . '">' . esc_html( $loc->post_title ) . '</a>';
				} else {
					$html = self::build_address_html( $location_id, $loc->post_title, $mode );
				}
			}
		}

		if ( ! $html && $location_text ) {
			$html = esc_html( $location_text );
		}

		return apply_filters( 'carkeek_events_location_display', $html, $post_id );
	}

	/**
	 * Build an address block from location meta.
	 *
	 * @since 1.0.0
	 * @param int    $location_id CPT post ID.
	 * @param string $name        Post title.
	 * @param string $mode        'address' or 'address_directions'.
	 * @return string HTML.
	 */
	private static function build_address_html( $location_id, $name, $mode ) {
		$address = get_post_meta( $location_id, '_carkeek_location_address', true );
		$city    = get_post_meta( $location_id, '_carkeek_location_city', true );
		$state   = get_post_meta( $location_id, '_carkeek_location_state', true );
		$zip     = get_post_meta( $location_id, '_carkeek_location_zip', true );
		$country = get_post_meta( $location_id, '_carkeek_location_country', true );

		// If no address data, just return the name.
		if ( ! $address && ! $city && ! $state ) {
			return esc_html( $name );
		}

		$lines = array();
		if ( $name ) {
			$lines[] = '<strong>' . esc_html( $name ) . '</strong>';
		}
		if ( $address ) {
			$lines[] = esc_html( $address );
		}
		$city_line = array_filter( array( $city, $state, $zip ) );
		if ( $city_line ) {
			$lines[] = esc_html( implode( ', ', $city_line ) );
		}
		if ( $country ) {
			$lines[] = esc_html( $country );
		}

		$html = implode( '<br>', $lines );

		if ( 'address_directions' === $mode ) {
			$query_parts = array_filter( array( $address, $city, $state, $zip, $country ) );
			if ( $query_parts ) {
				$maps_url = add_query_arg(
					array(
						'api'         => '1',
						'destination' => implode( ', ', $query_parts ),
					),
					'https://www.google.com/maps/dir/'
				);
				$html .= '<br><a href="' . esc_url( $maps_url ) . '" target="_blank" rel="noopener noreferrer">'
					. esc_html__( 'Get Directions', 'carkeek-events' )
					. '</a>';
			}
		}

		return $html;
	}

	// -----------------------------------------------------------------------
	// Organizer display
	// -----------------------------------------------------------------------

	/**
	 * Build organizer HTML per the `organizer_display` setting.
	 *
	 * Modes:
	 *   link — linked organizer name (default)
	 *   info — name + email + phone + website inline
	 *
	 * Fires `carkeek_events_organizer_display` filter before returning.
	 *
	 * @since 1.0.0
	 * @param int    $organizer_id   Linked carkeek_organizer post ID (0 if none).
	 * @param string $organizer_text Free-text fallback.
	 * @param int    $post_id        Parent event post ID (passed to filter).
	 * @return string HTML, or empty string if nothing to display.
	 */
	public static function get_organizer_html( $organizer_id, $organizer_text = '', $post_id = 0 ) {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$mode     = ! empty( $settings['organizer_display'] ) ? $settings['organizer_display'] : 'link';

		$html = '';

		if ( $organizer_id ) {
			$org = get_post( $organizer_id );
			if ( $org && 'publish' === $org->post_status ) {
				if ( 'link' === $mode ) {
					$html = '<a href="' . esc_url( get_permalink( $organizer_id ) ) . '">' . esc_html( $org->post_title ) . '</a>';
				} else {
					$html = self::build_organizer_info_html( $organizer_id, $org->post_title );
				}
			}
		}

		if ( ! $html && $organizer_text ) {
			$html = esc_html( $organizer_text );
		}

		return apply_filters( 'carkeek_events_organizer_display', $html, $post_id );
	}

	/**
	 * Build an inline organizer info block from organizer meta.
	 *
	 * @since 1.0.0
	 * @param int    $organizer_id CPT post ID.
	 * @param string $name         Post title.
	 * @return string HTML.
	 */
	private static function build_organizer_info_html( $organizer_id, $name ) {
		$email   = get_post_meta( $organizer_id, '_carkeek_organizer_email', true );
		$phone   = get_post_meta( $organizer_id, '_carkeek_organizer_phone', true );
		$website = get_post_meta( $organizer_id, '_carkeek_organizer_website', true );

		$lines = array();

		if ( $name ) {
			$lines[] = '<strong>' . esc_html( $name ) . '</strong>';
		}
		if ( $email ) {
			$lines[] = '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
		}
		if ( $phone ) {
			$clean_phone = preg_replace( '/[^0-9+]/', '', $phone );
			$lines[]     = '<a href="tel:' . esc_attr( $clean_phone ) . '">' . esc_html( $phone ) . '</a>';
		}
		if ( $website ) {
			$lines[] = '<a href="' . esc_url( $website ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $website ) . '</a>';
		}

		return implode( '<br>', $lines );
	}
}
