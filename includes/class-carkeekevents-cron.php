<?php
/**
 * Expiry Cron
 *
 * Daily cron that trashes events whose end date is older than content_expiry_days
 * (default 365). Trashed posts are restorable via the standard Trash screen.
 *
 * Events with no end date (_carkeek_event_end empty) are never trashed.
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
	 * Run the cron job.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run() {
		$this->pass_expire_old();
	}

	// -----------------------------------------------------------------------
	// Expire old events (trash)
	// -----------------------------------------------------------------------

	/**
	 * Trash events whose end date is older than content_expiry_days.
	 * Trashed posts are restorable by an admin via the standard Trash screen.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	private function pass_expire_old() {
		$settings    = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$expiry_days = absint( $settings['content_expiry_days'] ?? 365 );
		$expiry_days = max( 1, $expiry_days );
		// wp_date() respects the WordPress timezone setting (unlike PHP's date()).
		$cutoff      = wp_date( 'Y-m-d\T00:00:00', strtotime( "-{$expiry_days} days" ) );

		$batch_size = absint( apply_filters( 'carkeek_events_cron_batch_size', 200 ) );

		$query = new WP_Query( array(
			'post_type'      => 'carkeek_event',
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'fields'         => 'ids',
			'no_found_rows'  => true,
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
			wp_trash_post( $post_id );
		}
	}
}

CarkeekEvents_Cron::register();
