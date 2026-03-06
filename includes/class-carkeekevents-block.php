<?php
/**
 * Events Archive Block
 *
 * Registers the `carkeek-events/archive` Gutenberg block with a PHP
 * server-side render callback. Block metadata is read from
 * build/events-archive/block.json (compiled from src/events-archive/).
 *
 * Query behaviour:
 *   - Default: upcoming/current events only (end >= now, or no end date).
 *   - includePastEvents: show all events regardless of date.
 *   - onlyPastEvents: show only events whose end date has passed.
 *   - Hidden events (_carkeek_event_hidden = 1) are always excluded.
 *
 * Theme template override: {theme}/carkeek-events/event-card/default.php
 *
 * @package carkeek-events
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Block
 */
class CarkeekEvents_Block {

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_action( 'init', array( $instance, 'register_block' ) );
	}

	/**
	 * Register the block type, reading metadata from block.json.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_block() {
		register_block_type(
			CARKEEKEVENTS_PLUGIN_DIR . 'build/events-archive',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Server-side render callback for the carkeek-events/archive block.
	 *
	 * @since 2.0.0
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	public function render( $attributes ) {
		$now = current_time( 'Y-m-d\TH:i:s' );

		$args = array(
			'post_type'      => 'carkeek_event',
			'post_status'    => 'publish',
			'posts_per_page' => isset( $attributes['numberOfPosts'] ) ? (int) $attributes['numberOfPosts'] : 6,
			'meta_key'       => '_carkeek_event_start',
			'orderby'        => 'meta_value',
			'order'          => ! empty( $attributes['sortOrder'] ) ? $attributes['sortOrder'] : 'ASC',
			'meta_query'     => array(
				'relation' => 'AND',
				// Exclude hidden events.
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

		$include_past = ! empty( $attributes['includePastEvents'] );
		$only_past    = ! empty( $attributes['onlyPastEvents'] );

		if ( $only_past ) {
			// Only show events that have already ended.
			$args['meta_query'][] = array(
				'key'     => '_carkeek_event_end',
				'value'   => $now,
				'compare' => '<',
				'type'    => 'CHAR',
			);
		} elseif ( ! $include_past ) {
			// Default: upcoming and ongoing events (end >= now, or no end date).
			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => '_carkeek_event_end',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_carkeek_event_end',
					'value'   => '',
					'compare' => '=',
				),
				array(
					'key'     => '_carkeek_event_end',
					'value'   => $now,
					'compare' => '>=',
					'type'    => 'CHAR',
				),
			);
		}

		// Category filter.
		if ( ! empty( $attributes['filterByCategory'] ) && ! empty( $attributes['catTermsSelected'] ) ) {
			$term_ids = array_map( 'intval', explode( ',', $attributes['catTermsSelected'] ) );
			$term_ids = array_filter( $term_ids );
			if ( $term_ids ) {
				$args['tax_query'] = array(
					array(
						'taxonomy' => 'carkeek_event_category',
						'field'    => 'term_id',
						'terms'    => $term_ids,
					),
				);
			}
		}

		// Allow add-on modifications.
		$args  = apply_filters( 'carkeek_events_block_query_args', $args, $attributes );
		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			if ( ! empty( $attributes['hideIfEmpty'] ) ) {
				return '';
			}
			$msg = ! empty( $attributes['emptyMessage'] )
				? $attributes['emptyMessage']
				: __( 'No upcoming events.', 'carkeek-events' );
			return '<p class="carkeek-events-archive__empty">' . esc_html( $msg ) . '</p>';
		}

		$layout   = ! empty( $attributes['postLayout'] ) ? $attributes['postLayout'] : 'grid';
		$columns  = ! empty( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
		$tablet   = ! empty( $attributes['columnsTablet'] ) ? (int) $attributes['columnsTablet'] : 2;
		$mobile   = ! empty( $attributes['columnsMobile'] ) ? (int) $attributes['columnsMobile'] : 1;

		$classes = array_filter( array(
			'carkeek-events-archive',
			'is-' . esc_attr( $layout ),
			'grid' === $layout ? 'columns-' . $columns : '',
			'grid' === $layout ? 'columns-tablet-' . $tablet : '',
			'grid' === $layout ? 'columns-mobile-' . $mobile : '',
			! empty( $attributes['className'] ) ? esc_attr( $attributes['className'] ) : '',
		) );

		$card_template = self::locate_card_template();

		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$post = get_post();
			include $card_template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		}

		wp_reset_postdata();
		echo '</div>';

		if ( ! empty( $attributes['showPagination'] ) ) {
			echo paginate_links( array( 'total' => $query->max_num_pages ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return ob_get_clean();
	}

	/**
	 * Locate the event card template, allowing theme overrides.
	 *
	 * @since 2.0.0
	 * @return string Absolute path to the card template file.
	 */
	private static function locate_card_template() {
		$theme_template = locate_template( 'carkeek-events/event-card/default.php' );
		return $theme_template ?: CARKEEKEVENTS_PLUGIN_DIR . 'templates/event-card/default.php';
	}
}

CarkeekEvents_Block::register();
