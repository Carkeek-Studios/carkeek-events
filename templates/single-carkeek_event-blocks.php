<?php
/**
 * Single Event Template — block editor variant.
 *
 * Loaded when the block editor is active for events. Block-composed content
 * (event-details / event-date-time / etc.) controls the layout, so this
 * template renders the title and the_content() only.
 *
 * Safety net: if the author did not add a carkeek-events/event-details block,
 * fall back to the PHP meta header so dates/location/organizer/button are never
 * silently lost. Field-in-use settings are honored by the display helpers.
 *
 * Override this template by placing a copy at:
 *   {your-theme}/carkeek-events/single-carkeek_event-blocks.php
 *
 * @package carkeek-events
 * @since   2.1.0
 */

get_header();

while ( have_posts() ) :
	the_post();

	$post_id = get_the_ID();
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'carkeek-event' ); ?>>

		<h1 class="carkeek-event__title"><?php the_title(); ?></h1>

		<?php if ( ! has_block( 'carkeek-events/event-details', $post_id ) ) : ?>
			<?php
			$date_range      = CarkeekEvents_Display::get_date_range_html( $post_id );
			$location        = CarkeekEvents_Display::get_event_location_html( $post_id );
			$organizer       = CarkeekEvents_Display::get_event_organizer_html( $post_id );
			$event_link      = CarkeekEvents_Display::get_event_link_html( $post_id );
			$add_to_calendar = CarkeekEvents_Display::get_add_to_calendar_html( $post_id );
			?>
			<div class="carkeek-event__meta">
				<?php if ( $date_range ) : ?>
					<div class="carkeek-event__dates"><?php echo wp_kses_post( $date_range ); ?></div>
				<?php endif; ?>
				<?php if ( $location ) : ?>
					<div class="carkeek-event__location"><?php echo wp_kses_post( $location ); ?></div>
				<?php endif; ?>
				<?php if ( $organizer ) : ?>
					<div class="carkeek-event__organizer"><?php echo wp_kses_post( $organizer ); ?></div>
				<?php endif; ?>
				<?php if ( $event_link ) : ?>
					<div class="carkeek-event__link"><?php echo wp_kses_post( $event_link ); ?></div>
				<?php endif; ?>
				<?php if ( $add_to_calendar ) : ?>
					<div class="carkeek-event__add-to-calendar"><?php echo wp_kses_post( $add_to_calendar ); ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="carkeek-event__content entry-content">
			<?php the_content(); ?>
		</div>

	</article>

<?php endwhile; ?>

<?php get_footer(); ?>
