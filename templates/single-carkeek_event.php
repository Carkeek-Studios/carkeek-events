<?php
/**
 * Single Event Template
 *
 * Override this template by placing a copy at:
 *   {your-theme}/carkeek-events/single-carkeek_event.php
 *
 * @package carkeek-events
 * @since   1.0.0
 */

get_header();

while ( have_posts() ) :
	the_post();

	$post_id        = get_the_ID();
	$start_date     = get_post_meta( $post_id, '_carkeek_event_start_date', true );
	$start_time     = get_post_meta( $post_id, '_carkeek_event_start_time', true );
	$end_date       = get_post_meta( $post_id, '_carkeek_event_end_date', true );
	$end_time       = get_post_meta( $post_id, '_carkeek_event_end_time', true );
	$location_id    = (int) get_post_meta( $post_id, '_carkeek_event_location_id', true );
	$location_text  = get_post_meta( $post_id, '_carkeek_event_location_text', true );
	$organizer_id   = (int) get_post_meta( $post_id, '_carkeek_event_organizer_id', true );
	$organizer_text = get_post_meta( $post_id, '_carkeek_event_organizer_text', true );

	$date_range        = CarkeekEvents_Display::format_date_range( $start_date, $start_time, $end_date, $end_time );
	$location_display  = CarkeekEvents_Display::get_location_html( $location_id, $location_text, $post_id );
	$organizer_display = CarkeekEvents_Display::get_organizer_html( $organizer_id, $organizer_text, $post_id );
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'carkeek-event' ); ?>>

		<header class="carkeek-event__header">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="carkeek-event__featured-image">
					<?php the_post_thumbnail( 'large' ); ?>
				</div>
			<?php endif; ?>

			<h1 class="carkeek-event__title"><?php the_title(); ?></h1>

			<div class="carkeek-event__meta">
				<?php if ( $date_range ) : ?>
					<div class="carkeek-event__dates">
						<?php echo wp_kses_post( $date_range ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $location_display ) : ?>
					<div class="carkeek-event__location">
						<?php echo wp_kses_post( $location_display ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $organizer_display ) : ?>
					<div class="carkeek-event__organizer">
						<?php echo wp_kses_post( $organizer_display ); ?>
					</div>
				<?php endif; ?>
			</div>
		</header>

		<div class="carkeek-event__content entry-content">
			<?php the_content(); ?>
		</div>

	</article>

<?php endwhile; ?>

<?php get_footer(); ?>
