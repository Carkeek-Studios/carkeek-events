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

	$post_id    = get_the_ID();

	$date_range = CarkeekEvents_Display::get_date_range_html( $post_id );
	$location   = CarkeekEvents_Display::get_event_location_html( $post_id );
	$organizer  = CarkeekEvents_Display::get_event_organizer_html( $post_id );
	$event_link = CarkeekEvents_Display::get_event_link_html( $post_id );
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

				<?php if ( $location ) : ?>
					<div class="carkeek-event__location">
						<?php echo wp_kses_post( $location ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $organizer ) : ?>
					<div class="carkeek-event__organizer">
						<?php echo wp_kses_post( $organizer ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $event_link ) : ?>
					<div class="carkeek-event__link">
						<?php echo wp_kses_post( $event_link ); ?>
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
