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
	 * Get the formatted date/time range HTML for an event, fetching meta automatically.
	 *
	 * Convenience wrapper — pass the event post ID and get back fully marked-up HTML.
	 * Date and time are wrapped in separate <span> tags so templates can control
	 * layout via CSS (e.g. display:block for separate lines, or inline with comma).
	 *
	 * Fires the `carkeek_events_date_range` filter so developers can override.
	 *
	 * @since 1.1.0
	 * @param int    $post_id   Event post ID.
	 * @param string $separator Separator rendered between the date span and time span.
	 *                          Defaults to ', '. Pass '<br>' to stack on separate lines,
	 *                          or '' to rely purely on CSS.
	 * @return string HTML string, or empty string if no start date is set.
	 */
	public static function get_date_range_html( $post_id, $separator = ', ', $date_time_label = '' ) {
		$start = get_post_meta( $post_id, '_carkeek_event_start', true );
		$end   = get_post_meta( $post_id, '_carkeek_event_end', true );

		return self::format_date_range( $start, $end, $separator, $date_time_label );
	}

	/**
	 * Format an event date/time range into span-wrapped HTML.
	 *
	 * Accepts ISO 8601 local-time strings (YYYY-MM-DDTHH:MM:SS). Time component
	 * 00:00:00 is treated as "no time set" and omitted from display.
	 *
	 * Date values are wrapped in <span class="carkeek-event-date"> and time values
	 * in <span class="carkeek-event-time"> so CSS can control layout independently.
	 *
	 * Same-day: <date> {separator} <start-time> &ndash; <end-time>
	 * Multi-day: <start-date> {separator} <start-time> &ndash; <end-date> {separator} <end-time>
	 *
	 * Fires the `carkeek_events_date_range` filter so developers can override.
	 *
	 * @since 1.0.0
	 * @param string $start_iso ISO 8601 start datetime (YYYY-MM-DDTHH:MM:SS).
	 * @param string $end_iso   ISO 8601 end datetime. Empty for open-ended events.
	 * @param string $separator Separator between date and time spans. Default ', '.
	 * @return string Formatted HTML string.
	 */
	public static function format_date_range( $start_iso, $end_iso = '', $separator = ', ', $date_time_label = '' ) {
		if ( ! $start_iso ) {
			return '';
		}

		$settings    = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$date_format = ! empty( $settings['date_format'] ) ? $settings['date_format'] : get_option( 'date_format' );
		$time_format = ! empty( $settings['time_format'] ) ? $settings['time_format'] : get_option( 'time_format' );

		// Parse start ISO into date and time components.
		$start_date = substr( $start_iso, 0, 10 );
		$start_time = ( strlen( $start_iso ) > 10 && substr( $start_iso, 11 ) !== '00:00:00' )
			? substr( $start_iso, 11, 5 ) : '';

		// Parse end ISO into date and time components.
		$end_date = $end_iso ? substr( $end_iso, 0, 10 ) : '';
		$end_time = ( $end_iso && strlen( $end_iso ) > 10 && substr( $end_iso, 11 ) !== '00:00:00' )
			? substr( $end_iso, 11, 5 ) : '';

		$start_ts = strtotime( $start_date );
		$end_ts   = ( $end_date && $end_date !== $start_date ) ? strtotime( $end_date ) : 0;
		$same_day = ! $end_ts;

		$label_html = $date_time_label ? '<div class="carkeek-event-label carkeek-event-date-time-label">' . esc_html( $date_time_label ) . '</div> ' : '';

		$start_date_str = '<span class="carkeek-event-date">' . esc_html( date_i18n( $date_format, $start_ts ) ) . '</span>';

		$output = $label_html . $start_date_str;

		if ( $start_time ) {
			$start_time_ts  = strtotime( $start_date . ' ' . $start_time );
			$start_time_str = date_i18n( $time_format, $start_time_ts );

			if ( $same_day && $end_time ) {
				// Same day with end time: show time range in a single time span.
				$end_time_ts    = strtotime( $start_date . ' ' . $end_time );
				$end_time_str   = date_i18n( $time_format, $end_time_ts );
				$output .= $separator . '<span class="carkeek-event-time">' . esc_html( $start_time_str ) . ' &ndash; ' . esc_html( $end_time_str ) . '</span>';
			} else {
				$output .= $separator . '<span class="carkeek-event-time">' . esc_html( $start_time_str ) . '</span>';
			}
		}

		if ( ! $same_day ) {
			// Multi-day: append end date (and end time if present).
			$end_date_str = '<span class="carkeek-event-date">' . esc_html( date_i18n( $date_format, $end_ts ) ) . '</span>';
			$output      .= ' &ndash; ' . $end_date_str;

			if ( $end_time ) {
				$end_time_ts  = strtotime( $end_date . ' ' . $end_time );
				$end_time_str = date_i18n( $time_format, $end_time_ts );
				$output      .= $separator . '<span class="carkeek-event-time">' . esc_html( $end_time_str ) . '</span>';
			}
		}

		return apply_filters( 'carkeek_events_date_range', $output, $start_date, $start_time, $end_date, $end_time, $date_time_label );
	}

	// -----------------------------------------------------------------------
	// Location display
	// -----------------------------------------------------------------------

	/**
	 * Get the location HTML for an event by post ID, fetching meta automatically.
	 *
	 * Convenience wrapper — pass the event post ID and get back fully marked-up HTML
	 * without having to retrieve location meta keys manually in the template.
	 *
	 * @since 1.1.0
	 * @param int $post_id Event post ID.
	 * @return string HTML, or empty string if no location is set.
	 */
	public static function get_event_location_html( $post_id, $location_label = '', $show_directions_link = true ) {
		$location_id   = (int) get_post_meta( $post_id, '_carkeek_event_location_id', true );
		$location_text = get_post_meta( $post_id, '_carkeek_event_location_text', true );
		return self::get_location_html( $location_id, $location_text, $post_id, $location_label, $show_directions_link );
	}

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
	public static function get_location_html( $location_id, $location_text = '', $post_id = 0, $location_label = '', $show_directions_link = true ) {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$mode     = ! empty( $settings['location_display'] ) ? $settings['location_display'] : 'link';

		$html = '';

		if ( $location_id ) {
			$loc = get_post( $location_id );
			if ( $loc && 'publish' === $loc->post_status ) {
				if ( 'link' === $mode ) {
					$html = '<a href="' . esc_url( get_permalink( $location_id ) ) . '">' . esc_html( $loc->post_title ) . '</a>';
				} else {
					if ( ! $show_directions_link ) {
						$mode = 'address'; // fallback to address-only if directions link is disabled.
					}
					$html = self::build_address_html( $location_id, $loc->post_title, $mode );
				}
			}
		}

		if ( ! $html && $location_text ) {
			$html = esc_html( $location_text );
		}
		$html = $location_label && !empty( $html ) ? '<div class="carkeek-event-label carkeek-event-location-label">' . esc_html( $location_label ) . '</div> ' . $html : $html;

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
			$lines[] = '<div class="carkeek-event-sublabel carkeek-event-location-name">' . esc_html( $name ) . '</div>';
		}
		if ( $address ) {
			$lines[] = '<div class="carkeek-event-meta carkeek-event-location-address">' . esc_html( $address ) . '</div>';
		}
		$city_line = array_filter( array( $city, $state, $zip ) );
		if ( $city_line ) {
			$lines[] = '<div class="carkeek-event-meta carkeek-event-location-city">' . esc_html( implode( ', ', $city_line ) ) . '</div>';
		}
		if ( $country ) {
			$lines[] = '<div class="carkeek-event-meta carkeek-event-location-country">' . esc_html( $country ) . '</div>';
		}

		$html = implode( '', $lines );

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
				$html .= '<div class="carkeek-event-meta carkeek-event-location-directions"><a href="' . esc_url( $maps_url ) . '" target="_blank" rel="noopener noreferrer">'
					. esc_html__( 'Get Directions', 'carkeek-events' )
					. '</a></div>';
			}
		}

		return $html;
	}

	// -----------------------------------------------------------------------
	// Event link / CTA button
	// -----------------------------------------------------------------------

	/**
	 * Build an event CTA button/link HTML.
	 *
	 * Returns an <a> element linking to the event website URL stored in
	 * `_carkeek_event_website`. The button label comes from
	 * `_carkeek_event_button_label` and defaults to "Sign Up" when blank.
	 * Returns empty string if no URL is set.
	 *
	 * Fires `carkeek_events_link_html` filter before returning.
	 *
	 * @since 1.1.0
	 * @param int $post_id Event post ID.
	 * @return string HTML, or empty string if no website URL is set.
	 */
	public static function get_event_link_html( $post_id ) {
		$url   = get_post_meta( $post_id, '_carkeek_event_website', true );
		$label = get_post_meta( $post_id, '_carkeek_event_button_label', true );

		if ( ! $url ) {
			return '';
		}

		if ( ! $label ) {
			$label = __( 'Sign Up', 'carkeek-events' );
		}

		$html = '<a href="' . esc_url( $url ) . '" class="carkeek-event-link wp-element-button" target="_blank" rel="noopener noreferrer">'
			. esc_html( $label )
			. '</a>';

		return apply_filters( 'carkeek_events_link_html', $html, $post_id, $url, $label );
	}

	// -----------------------------------------------------------------------
	// Organizer display
	// -----------------------------------------------------------------------

	/**
	 * Get the organizer HTML for an event by post ID, fetching meta automatically.
	 *
	 * Convenience wrapper — pass the event post ID and get back fully marked-up HTML
	 * without having to retrieve organizer meta keys manually in the template.
	 *
	 * @since 1.1.0
	 * @param int $post_id Event post ID.
	 * @return string HTML, or empty string if no organizer is set.
	 */
	public static function get_event_organizer_html( $post_id, $organizer_label = '' ) {
		$organizer_id   = (int) get_post_meta( $post_id, '_carkeek_event_organizer_id', true );
		$organizer_text = get_post_meta( $post_id, '_carkeek_event_organizer_text', true );
		return self::get_organizer_html( $organizer_id, $organizer_text, $post_id, $organizer_label );
	}

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
	public static function get_organizer_html( $organizer_id, $organizer_text = '', $post_id = 0, $organizer_label = '' ) {
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

		if ( $organizer_label && !empty( $html ) ) {
			$html = '<div class="carkeek-event-label carkeek-event-organizer-label">' . esc_html( $organizer_label ) . '</div> ' . $html;
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
			$lines[] = '<div class="carkeek-event-sublabel carkeek-event-organizer-name">' . esc_html( $name ) . '</div>';
		}
		if ( $email ) {
			$lines[] = '<div class="carkeek-event-meta carkeek-event-organizer-email"><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></div>';
		}
		if ( $phone ) {
			$clean_phone = preg_replace( '/[^0-9+]/', '', $phone );
			$lines[]     = '<div class="carkeek-event-meta carkeek-event-organizer-phone"><a href="tel:' . esc_attr( $clean_phone ) . '">' . esc_html( $phone ) . '</a></div>';
		}
		if ( $website ) {
			$lines[] = '<div class="carkeek-event-meta carkeek-event-organizer-website"><a href="' . esc_url( $website ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $website ) . '</a></div>';
		}

		return implode( '', $lines );
	}
}
