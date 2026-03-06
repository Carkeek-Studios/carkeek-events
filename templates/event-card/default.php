<?php
/**
 * Event Card Template
 *
 * Used by the carkeek-blocks custom-archive block when querying carkeek_event
 * post type. Override this template by placing a copy at:
 *   {your-theme}/carkeek-events/event-card/default.php
 *   (or hook carkeek_events_card_template to supply a custom path)
 *
 * Available variables:
 *   $post  — current WP_Post object (set by carkeek-blocks render loop)
 *   $data  — block attributes object from carkeek-blocks
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! isset( $post ) ) {
	$post = get_post();
}

$post_id        = $post->ID;
$start_date     = get_post_meta( $post_id, '_carkeek_event_start_date', true );
$start_time     = get_post_meta( $post_id, '_carkeek_event_start_time', true );
$end_date       = get_post_meta( $post_id, '_carkeek_event_end_date', true );
$end_time       = get_post_meta( $post_id, '_carkeek_event_end_time', true );
$location_id    = (int) get_post_meta( $post_id, '_carkeek_event_location_id', true );
$location_text  = get_post_meta( $post_id, '_carkeek_event_location_text', true );
$organizer_id   = (int) get_post_meta( $post_id, '_carkeek_event_organizer_id', true );
$organizer_text = get_post_meta( $post_id, '_carkeek_event_organizer_text', true );
$permalink      = get_permalink( $post_id );

$date_range        = CarkeekEvents_Display::format_date_range( $start_date, $start_time, $end_date, $end_time );
$location_display  = CarkeekEvents_Display::get_location_html( $location_id, $location_text, $post_id );
$organizer_display = CarkeekEvents_Display::get_organizer_html( $organizer_id, $organizer_text, $post_id );
$event_link        = CarkeekEvents_Display::get_event_link_html( $post_id );
?>
<div class="ck-columns-item ck-custom-archive-item carkeek-event-card">

	<?php if ( has_post_thumbnail( $post_id ) ) : ?>
		<a class="carkeek-event-card__image-link" href="<?php echo esc_url( $permalink ); ?>">
			<?php echo get_the_post_thumbnail( $post_id, 'medium_large' ); ?>
		</a>
	<?php endif; ?>

	<div class="ck-custom-archive__content-wrap">

		<a class="ck-custom-archive-title_link" href="<?php echo esc_url( $permalink ); ?>">
			<?php echo esc_html( get_the_title( $post_id ) ); ?>
		</a>

		<?php if ( $date_range ) : ?>
			<div class="carkeek-event-card__date">
				<?php echo wp_kses_post( $date_range ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $location_display ) : ?>
			<div class="carkeek-event-card__location">
				<?php echo wp_kses_post( $location_display ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $organizer_display ) : ?>
			<div class="carkeek-event-card__organizer">
				<?php echo wp_kses_post( $organizer_display ); ?>
			</div>
		<?php endif; ?>

		<?php
		$excerpt = get_the_excerpt( $post_id );
		if ( $excerpt ) :
			?>
			<div class="ck-custom-archive-excerpt">
				<?php echo wp_kses_post( $excerpt ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $event_link ) : ?>
			<div class="carkeek-event-card__link">
				<?php echo wp_kses_post( $event_link ); ?>
			</div>
		<?php endif; ?>

	</div>

</div>
