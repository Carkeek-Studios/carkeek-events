<?php
/**
 * Single Event Template (classic)
 *
 * Loaded when the block editor is disabled for events (see
 * carkeek_events_single_template()). Renders the plugin's default two-column
 * layout: an "Events" tag + title, a meta column, a media column (featured
 * image + optional Add to Calendar), and the full-width content below.
 *
 * Labels, the date/time separator, the landing-page link, and the Add to
 * Calendar toggle are all controlled under Events > Settings.
 *
 * Extension hooks:
 *   filter carkeek_events_single_title_block( $html, $post_id )
 *   action carkeek_events_before_title( $post_id )
 *   action carkeek_events_after_title( $post_id )
 *   action carkeek_events_before_featured_image( $post_id )
 *   action carkeek_events_after_featured_image( $post_id )
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

	$post_id = get_the_ID();

	$date_range = CarkeekEvents_Display::get_date_range_html( $post_id, CarkeekEvents_Display::datetime_separator(), CarkeekEvents_Display::datetime_label() );
	$location   = CarkeekEvents_Display::get_event_location_html( $post_id, CarkeekEvents_Display::location_label() );
	$organizer  = CarkeekEvents_Display::get_event_organizer_html( $post_id, CarkeekEvents_Display::organizer_label() );
	$event_link = CarkeekEvents_Display::get_event_link_html( $post_id );
	$add_to_cal = CarkeekEvents_Display::show_add_to_calendar_single()
		? CarkeekEvents_Display::get_add_to_calendar_html( $post_id )
		: '';

	$has_media = has_post_thumbnail()
		|| '' !== $add_to_cal
		|| has_action( 'carkeek_events_before_featured_image' )
		|| has_action( 'carkeek_events_after_featured_image' );

	// Build the tag + title block, then let it be filtered/replaced wholesale.
	ob_start();
	do_action( 'carkeek_events_before_title', $post_id );
	?>
	<p class="carkeek-event__tag">
		<a href="<?php echo esc_url( CarkeekEvents_Display::events_landing_url() ); ?>"><?php esc_html_e( 'Events', 'carkeek-events' ); ?></a>
	</p>
	<h1 class="carkeek-event__title"><?php the_title(); ?></h1>
	<?php
	do_action( 'carkeek_events_after_title', $post_id );
	$title_block = ob_get_clean();
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'carkeek-event page-content' ); ?>>

		<header class="carkeek-event__header">
			<?php echo apply_filters( 'carkeek_events_single_title_block', $title_block, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above; documented developer filter. ?>
		</header>

		<div class="carkeek-event__body<?php echo $has_media ? '' : ' carkeek-event__body--no-media'; ?>">

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
			</div>

			<?php if ( $has_media ) : ?>
				<div class="carkeek-event__media">
					<?php do_action( 'carkeek_events_before_featured_image', $post_id ); ?>

					<?php if ( has_post_thumbnail() ) : ?>
						<div class="carkeek-event__featured-image">
							<?php the_post_thumbnail( 'large' ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $add_to_cal ) : ?>
						<div class="carkeek-event__add-to-calendar"><?php echo wp_kses_post( $add_to_cal ); ?></div>
					<?php endif; ?>

					<?php do_action( 'carkeek_events_after_featured_image', $post_id ); ?>
				</div>
			<?php endif; ?>

		</div>

		<div class="carkeek-event__content entry-content">
			<?php the_content(); ?>
		</div>

	</article>

<?php endwhile; ?>

<?php get_footer(); ?>
