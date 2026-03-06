<?php
/**
 * Expiry Cron
 *
 * Daily cron with two passes:
 *   Pass 1 — Mark expired events as hidden (_carkeek_event_hidden = 1)
 *   Pass 2 — Set post_status = private for events whose end date is older
 *             than content_expiry_days (default 365). Private posts return
 *             404 to logged-out users; no data is permanently deleted.
 *
 * Events with no end date (_carkeek_event_end empty) are never hidden or expired.
 * All date comparisons use ISO 8601 CHAR sorting against site local time.
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Cron
 */
class CarkeekEvents_Cron {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_action( 'carkeek_events_daily_cron', array( $instance, 'run' ) );
	}

	/**
	 * Run both cron passes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run() {
		$this->pass_hide_expired();
		$this->pass_expire_old();
	}

	// -----------------------------------------------------------------------
	// Pass 1: Hide expired events
	// -----------------------------------------------------------------------

	/**
	 * Hide events whose end datetime has passed the configured threshold.
	 *
	 * Uses ISO 8601 CHAR comparison on _carkeek_event_end so the full
	 * datetime is considered in a single meta query without PHP-level filtering.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function pass_hide_expired() {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$behavior = $settings['expiry_behavior'] ?? 'end_of_day';

		if ( 'never' === $behavior ) {
			return;
		}

		// Determine the threshold ISO string based on behavior.
		if ( 'end_of_day' === $behavior ) {
			// Hide events whose end date is before today (at start of today = end of yesterday).
			$threshold = current_time( 'Y-m-d' ) . 'T00:00:00';
		} else {
			// 'immediate' — hide as soon as end datetime has passed.
			$threshold = current_time( 'Y-m-d\TH:i:s' );
		}

		$query = new WP_Query( array(
			'post_type'      => 'carkeek_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				// Must have a non-empty end datetime.
				array(
					'key'     => '_carkeek_event_end',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_carkeek_event_end',
					'value'   => '',
					'compare' => '!=',
				),
				// End datetime is before the threshold.
				array(
					'key'     => '_carkeek_event_end',
					'value'   => $threshold,
					'compare' => '<',
					'type'    => 'CHAR',
				),
				// Not already hidden.
				array(
					'relation' => 'OR',
					array(
						'key'     => '_carkeek_event_hidden',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_carkeek_event_hidden',
						'value'   => '1',
						'compare' => '!=',
					),
				),
			),
		) );

		foreach ( $query->posts as $post_id ) {
			// Allow per-event threshold override by add-ons.
			$end_iso   = get_post_meta( $post_id, '_carkeek_event_end', true );
			$threshold = apply_filters( 'carkeek_events_expiry_threshold', $threshold, $post_id );

			if ( $end_iso >= $threshold ) {
				continue;
			}

			do_action( 'carkeek_events_before_hide', $post_id );
			update_post_meta( $post_id, '_carkeek_event_hidden', '1' );
		}
	}

	// -----------------------------------------------------------------------
	// Pass 2: Expire old events (set post_status = private)
	// -----------------------------------------------------------------------

	/**
	 * Set post_status = private for events whose end date is older than
	 * content_expiry_days. Private posts return 404 to logged-out users.
	 * No data is permanently deleted — posts remain restorable by an admin.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function pass_expire_old() {
		$settings     = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$expiry_days  = absint( $settings['content_expiry_days'] ?? 365 );
		$expiry_days  = max( 1, $expiry_days );
		$cutoff       = date( 'Y-m-d\T00:00:00', strtotime( "-{$expiry_days} days", current_time( 'timestamp' ) ) );

		$query = new WP_Query( array(
			'post_type'      => 'carkeek_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				// Must have a non-empty end datetime.
				array(
					'key'     => '_carkeek_event_end',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_carkeek_event_end',
					'value'   => '',
					'compare' => '!=',
				),
				// End datetime is older than the expiry cutoff.
				array(
					'key'     => '_carkeek_event_end',
					'value'   => $cutoff,
					'compare' => '<',
					'type'    => 'CHAR',
				),
			),
		) );

		foreach ( $query->posts as $post_id ) {
			do_action( 'carkeek_events_before_expire', $post_id );
			wp_update_post( array(
				'ID'          => $post_id,
				'post_status' => 'private',
			) );
		}
	}
}

CarkeekEvents_Cron::register();
