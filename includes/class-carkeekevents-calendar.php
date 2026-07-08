<?php
/**
 * Add to Calendar
 *
 * Builds "Add to Calendar" links for a single event:
 *   - a Google Calendar URL (calendar.google.com/calendar/render?action=TEMPLATE)
 *   - a downloadable .ics served from a query-var endpoint (?carkeek_ical=1 on the
 *     event permalink) — no rewrite rule, so no rewrite flush is ever required.
 *
 * All output is gated by the "Add to Calendar" field-in-use setting
 * (CarkeekEvents_Display::field_enabled( 'add_to_calendar' )).
 *
 * Times are stored as LOCAL ISO 8601 (YYYY-MM-DDTHH:MM:SS); a 00:00:00 time means
 * "no time set" = all-day. Timed events are emitted in UTC (…THHMMSSZ); all-day
 * events use VALUE=DATE with an exclusive DTEND.
 *
 * @package carkeek-events
 * @since   2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Calendar
 */
class CarkeekEvents_Calendar {

	/**
	 * The public query var that triggers the .ics response.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'carkeek_ical';

	/**
	 * Register hooks.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_filter( 'query_vars', array( $instance, 'register_query_var' ) );
		add_action( 'template_redirect', array( $instance, 'maybe_serve_ics' ) );
	}

	/**
	 * Register the .ics query var so get_query_var() resolves it.
	 *
	 * @since 2.2.0
	 * @param string[] $vars Registered query vars.
	 * @return string[]
	 */
	public function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	// -----------------------------------------------------------------------
	// .ics endpoint
	// -----------------------------------------------------------------------

	/**
	 * Serve a single event's .ics when ?carkeek_ical=1 is requested on its permalink.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public function maybe_serve_ics() {
		if ( ! is_singular( 'carkeek_event' ) || ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$post_id = get_queried_object_id();

		// Feature gate + never leak password-protected content. Let WP render its 404.
		if ( ! CarkeekEvents_Display::field_enabled( 'add_to_calendar' ) || post_password_required( $post_id ) ) {
			status_header( 404 );
			return;
		}

		$ics = self::get_ics( $post_id );
		if ( '' === $ics ) {
			// No start date — nothing to export.
			status_header( 404 );
			return;
		}

		$slug = get_post_field( 'post_name', $post_id );
		$slug = $slug ? sanitize_file_name( $slug ) : 'event';

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $slug . '.ics"' );
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/calendar body, escaped per RFC 5545.
		exit;
	}

	// -----------------------------------------------------------------------
	// Public builders
	// -----------------------------------------------------------------------

	/**
	 * The .ics download URL for an event (query-var endpoint on its permalink).
	 *
	 * @since 2.2.0
	 * @param int $post_id Event post ID.
	 * @return string
	 */
	public static function get_ics_url( $post_id ) {
		return add_query_arg( self::QUERY_VAR, '1', get_permalink( $post_id ) );
	}

	/**
	 * The Google Calendar "add event" URL for an event.
	 *
	 * @since 2.2.0
	 * @param int $post_id Event post ID.
	 * @return string Empty string when the event has no start date.
	 */
	public static function get_google_url( $post_id ) {
		$times = self::get_event_times( $post_id );
		if ( ! $times ) {
			return '';
		}

		if ( $times['all_day'] ) {
			$dates = $times['start']->format( 'Ymd' ) . '/' . $times['end']->modify( '+1 day' )->format( 'Ymd' );
		} else {
			$dates = self::to_utc_z( $times['start'] ) . '/' . self::to_utc_z( $times['end'] );
		}

		$details = self::get_description( $post_id );
		// Google drops URLs over its limit; keep details well under ~1000 chars.
		if ( strlen( $details ) > 996 ) {
			$details = substr( $details, 0, 996 );
		}

		$params = array(
			'action'   => 'TEMPLATE',
			'text'     => get_the_title( $post_id ),
			'dates'    => $dates,
			'details'  => $details,
			'location' => CarkeekEvents_Display::get_location_string( $post_id ),
		);

		return 'https://calendar.google.com/calendar/render?' . http_build_query( array_filter( $params ) );
	}

	/**
	 * Build the raw .ics (VCALENDAR) text for a single event.
	 *
	 * @since 2.2.0
	 * @param int $post_id Event post ID.
	 * @return string Empty string when the event has no start date.
	 */
	public static function get_ics( $post_id ) {
		$times = self::get_event_times( $post_id );
		if ( ! $times ) {
			return '';
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = $host ? $host : 'localhost';

		$lines   = array();
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//Carkeek Studios//Carkeek Events//EN';
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'METHOD:PUBLISH';
		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:carkeek-event-' . (int) $post_id . '@' . $host;
		$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );

		if ( $times['all_day'] ) {
			$lines[] = 'DTSTART;VALUE=DATE:' . $times['start']->format( 'Ymd' );
			$lines[] = 'DTEND;VALUE=DATE:' . $times['end']->modify( '+1 day' )->format( 'Ymd' );
		} else {
			$lines[] = 'DTSTART:' . self::to_utc_z( $times['start'] );
			$lines[] = 'DTEND:' . self::to_utc_z( $times['end'] );
		}

		$lines[] = 'SUMMARY:' . self::ics_escape( get_the_title( $post_id ) );

		$description = self::get_description( $post_id );
		if ( '' !== $description ) {
			$lines[] = 'DESCRIPTION:' . self::ics_escape( $description );
		}

		$location = CarkeekEvents_Display::get_location_string( $post_id );
		if ( '' !== $location ) {
			$lines[] = 'LOCATION:' . self::ics_escape( $location );
		}

		$lines[] = 'URL:' . self::ics_escape( get_permalink( $post_id ) );
		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", array_map( array( __CLASS__, 'ics_fold' ), $lines ) ) . "\r\n";
	}

	/**
	 * Render the "Add to Calendar" disclosure control.
	 *
	 * @since 2.2.0
	 * @param int   $post_id Event post ID.
	 * @param array $args    Optional labels: buttonLabel, googleLabel, icalLabel.
	 * @return string HTML, or empty string when the event has no start date.
	 */
	public static function render_button( $post_id, $args = array() ) {
		$google = self::get_google_url( $post_id );
		if ( '' === $google ) {
			return '';
		}
		$ics = self::get_ics_url( $post_id );

		$button_label = ! empty( $args['buttonLabel'] ) ? $args['buttonLabel'] : __( 'Add to Calendar', 'carkeek-events' );
		$google_label = ! empty( $args['googleLabel'] ) ? $args['googleLabel'] : __( 'Google Calendar', 'carkeek-events' );
		$ical_label   = ! empty( $args['icalLabel'] ) ? $args['icalLabel'] : __( 'Download .ics', 'carkeek-events' );

		// Loads only when the control actually renders (printed in the footer on the
		// front end). Self-register so this is safe in the editor's REST preview too.
		if ( ! wp_style_is( 'carkeek-events-frontend', 'registered' ) ) {
			wp_register_style(
				'carkeek-events-frontend',
				CARKEEKEVENTS_PLUGIN_URL . 'assets/css/carkeek-events-frontend.css',
				array(),
				CARKEEKEVENTS_VERSION
			);
		}
		wp_enqueue_style( 'carkeek-events-frontend' );

		ob_start();
		?>
		<details class="carkeek-add-to-calendar">
			<summary class="carkeek-add-to-calendar__toggle wp-element-button"><?php echo esc_html( $button_label ); ?></summary>
			<div class="carkeek-add-to-calendar__menu" role="menu">
				<a class="carkeek-add-to-calendar__item" role="menuitem" href="<?php echo esc_url( $google ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $google_label ); ?></a>
				<a class="carkeek-add-to-calendar__item" role="menuitem" href="<?php echo esc_url( $ics ); ?>" download><?php echo esc_html( $ical_label ); ?></a>
			</div>
		</details>
		<?php
		return trim( ob_get_clean() );
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Resolve an event's normalized start/end as DateTimeImmutable in the site timezone.
	 *
	 * Returns:
	 *   all_day => bool
	 *   start   => DateTimeImmutable  (timed: exact start; all-day: midnight of start date)
	 *   end     => DateTimeImmutable  (timed: exact end; all-day: midnight of the INCLUSIVE last day)
	 *
	 * @since 2.2.0
	 * @param int $post_id Event post ID.
	 * @return array|null Null when no start date is set.
	 */
	private static function get_event_times( $post_id ) {
		$start_iso = get_post_meta( $post_id, '_carkeek_event_start', true );
		if ( ! $start_iso ) {
			return null;
		}

		$tz = wp_timezone();

		$start_date = substr( $start_iso, 0, 10 );
		$start_time = ( strlen( $start_iso ) > 10 && substr( $start_iso, 11 ) !== '00:00:00' )
			? substr( $start_iso, 11, 5 ) : '';
		$all_day = ( '' === $start_time );

		$start = date_create_immutable( $start_date . ' ' . ( $start_time ? $start_time . ':00' : '00:00:00' ), $tz );
		if ( ! $start ) {
			return null;
		}

		$end_iso  = get_post_meta( $post_id, '_carkeek_event_end', true );
		$end_date = $end_iso ? substr( $end_iso, 0, 10 ) : '';
		$end_time = ( $end_iso && strlen( $end_iso ) > 10 && substr( $end_iso, 11 ) !== '00:00:00' )
			? substr( $end_iso, 11, 5 ) : '';

		if ( $all_day ) {
			// All-day: work in whole dates. Inclusive last day = end date or start date.
			$last = $end_date ? $end_date : $start_date;
			$end  = date_create_immutable( $last . ' 00:00:00', $tz ) ?: $start;
		} elseif ( $end_time ) {
			// Timed with an explicit end time.
			$end = date_create_immutable( ( $end_date ? $end_date : $start_date ) . ' ' . $end_time . ':00', $tz ) ?: $start->modify( '+1 hour' );
		} else {
			// Timed with no end time — default to a one-hour duration.
			$end = $start->modify( '+1 hour' );
		}

		// Guard against an end that precedes the start.
		if ( ! $all_day && $end <= $start ) {
			$end = $start->modify( '+1 hour' );
		}

		return array(
			'all_day' => $all_day,
			'start'   => $start,
			'end'     => $end,
		);
	}

	/**
	 * Build the calendar entry body: excerpt (fallback trimmed content) + a link back.
	 *
	 * @since 2.2.0
	 * @param int $post_id Event post ID.
	 * @return string Plain text.
	 */
	private static function get_description( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		if ( $post->post_excerpt ) {
			$text = $post->post_excerpt;
		} else {
			$text = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 55, '…' );
		}

		$text = trim( wp_strip_all_tags( $text ) );

		$more = sprintf(
			/* translators: %s: event permalink */
			__( 'More info: %s', 'carkeek-events' ),
			get_permalink( $post_id )
		);

		return $text ? $text . "\n\n" . $more : $more;
	}

	/**
	 * Format a DateTimeImmutable as a UTC iCalendar timestamp (YYYYMMDDTHHMMSSZ).
	 *
	 * @since 2.2.0
	 * @param DateTimeImmutable $dt Datetime in any timezone.
	 * @return string
	 */
	private static function to_utc_z( $dt ) {
		return $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	}

	/**
	 * Escape a value for an iCalendar TEXT field (RFC 5545 §3.3.11).
	 *
	 * @since 2.2.0
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function ics_escape( $text ) {
		return str_replace(
			array( '\\', ';', ',', "\r\n", "\n", "\r" ),
			array( '\\\\', '\\;', '\\,', '\\n', '\\n', '\\n' ),
			(string) $text
		);
	}

	/**
	 * Fold a content line to 75 octets with CRLF + space continuations (RFC 5545 §3.1).
	 *
	 * Folding on octet boundaries is spec-safe: unfolding rejoins the exact bytes.
	 *
	 * @since 2.2.0
	 * @param string $line A single content line (no CRLF).
	 * @return string Folded line (may contain internal CRLF + space).
	 */
	public static function ics_fold( $line ) {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}

		$folded = substr( $line, 0, 75 );
		$i      = 75;
		$len    = strlen( $line );
		while ( $i < $len ) {
			$folded .= "\r\n " . substr( $line, $i, 74 );
			$i      += 74;
		}
		return $folded;
	}
}

CarkeekEvents_Calendar::register();
