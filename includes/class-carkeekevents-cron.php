<?php
/**
 * Expiry Cron
 *
 * Daily cron with two passes:
 *   Pass 1 — Mark expired events as hidden (_carkeek_event_hidden = 1)
 *   Pass 2 — Permanently delete events hidden longer than the grace period
 *
 * Events with no end date (_carkeek_event_end_date empty) are never hidden.
 * All date comparisons use site local time via current_time().
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
		$this->pass_delete_old();
	}

	// -----------------------------------------------------------------------
	// Pass 1: Hide expired events
	// -----------------------------------------------------------------------

	/**
	 * Hide events whose end date has passed the configured threshold.
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

		// Build base query args — all published events with an end date that aren't already hidden.
		$query_args = array(
			'post_type'      => 'carkeek_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				// Must have an end date.
				array(
					'key'     => '_carkeek_event_end_date',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_carkeek_event_end_date',
					'value'   => '',
					'compare' => '!=',
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
		);

		$expired_ids = array();
		$today       = current_time( 'Y-m-d' );
		$now         = current_time( 'Y-m-d H:i:s' );

		if ( 'end_of_day' === $behavior ) {
			// All events whose end date is strictly before today.
			$query_args['meta_query'][] = array(
				'key'     => '_carkeek_event_end_date',
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			);
			$query    = new WP_Query( $query_args );
			$expired_ids = $query->posts;

		} elseif ( 'immediate' === $behavior ) {
			// Events where end_date + end_time is in the past.
			// We query by date first, then filter by time in PHP.
			$query_args['meta_query'][] = array(
				'key'     => '_carkeek_event_end_date',
				'value'   => $today,
				'compare' => '<=',
				'type'    => 'DATE',
			);
			$query = new WP_Query( $query_args );

			foreach ( $query->posts as $post_id ) {
				$end_date = get_post_meta( $post_id, '_carkeek_event_end_date', true );
				$end_time = get_post_meta( $post_id, '_carkeek_event_end_time', true ) ?: '23:59';
				$end_dt   = $end_date . ' ' . $end_time . ':00';

				// Allow per-event threshold override by add-ons.
				$threshold = apply_filters( 'carkeek_events_expiry_threshold', $end_dt, $post_id );

				if ( $threshold < $now ) {
					$expired_ids[] = $post_id;
				}
			}
		}

		foreach ( $expired_ids as $post_id ) {
			do_action( 'carkeek_events_before_hide', $post_id );
			update_post_meta( $post_id, '_carkeek_event_hidden', 1 );
			update_post_meta( $post_id, '_carkeek_event_hidden_date', $today );
		}
	}

	// -----------------------------------------------------------------------
	// Pass 2: Permanently delete old hidden events
	// -----------------------------------------------------------------------

	/**
	 * Permanently delete events that have been hidden longer than the grace period.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function pass_delete_old() {
		$settings     = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$grace_period = max( 1, (int) ( $settings['deletion_grace_period'] ?? 30 ) );
		$cutoff_date  = gmdate( 'Y-m-d', strtotime( "-{$grace_period} days", current_time( 'timestamp' ) ) );

		$query = new WP_Query( array(
			'post_type'      => 'carkeek_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => '_carkeek_event_hidden',
					'value' => '1',
				),
				array(
					'key'     => '_carkeek_event_hidden_date',
					'value'   => $cutoff_date,
					'compare' => '<',
					'type'    => 'DATE',
				),
			),
		) );

		foreach ( $query->posts as $post_id ) {
			do_action( 'carkeek_events_before_delete', $post_id );
			wp_delete_post( $post_id, true ); // force-delete, bypass trash.
		}
	}
}

CarkeekEvents_Cron::register();
