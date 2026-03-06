<?php
/**
 * Events Archive Block
 *
 * Registers the `carkeek-events/archive` Gutenberg block with a PHP
 * server-side render callback. Block metadata is read from
 * build/events-archive/block.json (compiled from src/events-archive/).
 *
 * Content slots:
 *   The `contentSlots` attribute is a comma-separated ordered list of items
 *   to render per card. Supported values:
 *     title      — event title as a permalink anchor
 *     date_time  — full date + time range (start → end)
 *     date       — date portion only
 *     time       — time portion only
 *     location   — location name (name only, not full address)
 *     organizer  — organizer name (name only)
 *     excerpt    — post excerpt, trimmed to excerptLength words
 *
 *   The optional `slotDateFormat` / `slotTimeFormat` attributes override the
 *   plugin-level date/time format settings for this block instance.
 *   The `showEndDateTime` attribute controls whether end date/time is shown.
 *
 * Featured image is rendered before the slot content when displayFeaturedImage
 * is enabled — it is not a slot because it lives outside the text content area.
 *
 * Theme template override for the card wrapper:
 *   {theme}/carkeek-events/event-card/default.php
 *   (override only applies when using carkeek-blocks integration, not this block)
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
		add_action( 'enqueue_block_editor_assets', array( $instance, 'enqueue_event_editor' ) );
	}

	/**
	 * Enqueue the event-editor sidebar plugin script for the carkeek_event post type.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function enqueue_event_editor() {
		$asset_file = CARKEEKEVENTS_PLUGIN_DIR . 'build/event-editor/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script(
			'carkeek-event-editor',
			CARKEEKEVENTS_PLUGIN_URL . 'build/event-editor/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'carkeek-event-editor', 'carkeek-events' );
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

	// -----------------------------------------------------------------------
	// Render
	// -----------------------------------------------------------------------

	/**
	 * Server-side render callback for the carkeek-events/archive block.
	 *
	 * @since 2.0.0
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	public function render( $attributes ) {
		$now = current_time( 'Y-m-d\TH:i:s' );

		$num_posts = isset( $attributes['numberOfPosts'] ) ? (int) $attributes['numberOfPosts'] : 6;

		$args = array(
			'post_type'      => 'carkeek_event',
			'post_status'    => 'publish',
			'posts_per_page' => ( -1 === $num_posts ) ? -1 : max( 1, $num_posts ),
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
			$args['meta_query'][] = array(
				'key'     => '_carkeek_event_end',
				'value'   => $now,
				'compare' => '<',
				'type'    => 'CHAR',
			);
		} elseif ( ! $include_past ) {
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

		if ( ! empty( $attributes['filterByCategory'] ) && ! empty( $attributes['catTermsSelected'] ) ) {
			$term_ids = array_map( 'intval', explode( ',', $attributes['catTermsSelected'] ) );
			$term_ids = array_filter( $term_ids );
			if ( $term_ids ) {
				$operator = ( isset( $attributes['catFilterMode'] ) && 'exclude' === $attributes['catFilterMode'] )
					? 'NOT IN'
					: 'IN';
				$args['tax_query'] = array(
					array(
						'taxonomy' => 'carkeek_event_category',
						'field'    => 'term_id',
						'terms'    => $term_ids,
						'operator' => $operator,
					),
				);
			}
		}

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

		$layout  = ! empty( $attributes['postLayout'] ) ? $attributes['postLayout'] : 'grid';
		$columns = ! empty( $attributes['columns'] ) ? (int) $attributes['columns'] : 3;
		$tablet  = ! empty( $attributes['columnsTablet'] ) ? (int) $attributes['columnsTablet'] : 2;
		$mobile  = ! empty( $attributes['columnsMobile'] ) ? (int) $attributes['columnsMobile'] : 1;

		$classes = array_filter( array(
			'carkeek-events-archive',
			'is-' . esc_attr( $layout ),
			'grid' === $layout ? 'columns-' . $columns : '',
			'grid' === $layout ? 'columns-tablet-' . $tablet : '',
			'grid' === $layout ? 'columns-mobile-' . $mobile : '',
			! empty( $attributes['className'] ) ? esc_attr( $attributes['className'] ) : '',
		) );

		// Parse content slots — default to title + date_time.
		$slots = ! empty( $attributes['contentSlots'] )
			? array_filter( explode( ',', $attributes['contentSlots'] ) )
			: array( 'title', 'date_time' );

		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id   = get_the_ID();
			$permalink = get_permalink( $post_id );

			echo '<div class="carkeek-event-card">';

			// Featured image renders before slot content.
			if ( ! empty( $attributes['displayFeaturedImage'] ) && has_post_thumbnail( $post_id ) ) {
				echo '<a class="carkeek-event-card__image-link" href="' . esc_url( $permalink ) . '">';
				echo get_the_post_thumbnail( $post_id, 'medium_large' );
				echo '</a>';
			}

			echo '<div class="carkeek-event-card__content">';

			foreach ( $slots as $slot ) {
				$slot_html = $this->render_slot( $slot, $post_id, $permalink, $attributes );
				if ( '' !== $slot_html ) {
					echo '<div class="carkeek-event-card__slot carkeek-event-card__slot--' . esc_attr( $slot ) . '">';
					echo $slot_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '</div>';
				}
			}

			echo '</div>'; // .carkeek-event-card__content
			echo '</div>'; // .carkeek-event-card
		}

		wp_reset_postdata();
		echo '</div>'; // .carkeek-events-archive

		if ( ! empty( $attributes['showPagination'] ) ) {
			echo paginate_links( array( 'total' => $query->max_num_pages ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// Slot renderers
	// -----------------------------------------------------------------------

	/**
	 * Render a single content slot.
	 *
	 * @since 2.0.0
	 * @param string $slot       Slot type identifier.
	 * @param int    $post_id    Event post ID.
	 * @param string $permalink  Event permalink.
	 * @param array  $attributes Block attributes.
	 * @return string HTML for the slot, or empty string if nothing to show.
	 */
	private function render_slot( $slot, $post_id, $permalink, $attributes ) {
		switch ( $slot ) {
			case 'title':
				return '<a class="carkeek-event-card__title" href="' . esc_url( $permalink ) . '">'
					. esc_html( get_the_title( $post_id ) )
					. '</a>';

			case 'date_time':
			case 'date':
			case 'time':
				return $this->render_date_slot( $slot, $post_id, $attributes );

			case 'location':
				return $this->render_location_name( $post_id );

			case 'organizer':
				return $this->render_organizer_name( $post_id );

			case 'excerpt':
				return $this->render_excerpt( $post_id, $attributes );

			default:
				return '';
		}
	}

	/**
	 * Render a date/time slot (date_time, date, or time).
	 *
	 * Uses slotDateFormat / slotTimeFormat block attributes when set,
	 * otherwise falls back to the plugin's global format settings.
	 *
	 * @since 2.0.0
	 * @param string $slot       'date_time', 'date', or 'time'.
	 * @param int    $post_id    Event post ID.
	 * @param array  $attributes Block attributes.
	 * @return string HTML with span-wrapped date/time values, or empty string.
	 */
	private function render_date_slot( $slot, $post_id, $attributes ) {
		$start_iso = get_post_meta( $post_id, '_carkeek_event_start', true );
		$end_iso   = get_post_meta( $post_id, '_carkeek_event_end', true );

		if ( ! $start_iso ) {
			return '';
		}

		$show_end = isset( $attributes['showEndDateTime'] ) ? (bool) $attributes['showEndDateTime'] : true;

		// Parse ISO strings into date and time components.
		$start_date = substr( $start_iso, 0, 10 );
		$start_time = ( strlen( $start_iso ) > 10 && substr( $start_iso, 11 ) !== '00:00:00' )
			? substr( $start_iso, 11, 5 ) : '';

		$end_date = $end_iso ? substr( $end_iso, 0, 10 ) : '';
		$end_time = ( $end_iso && strlen( $end_iso ) > 10 && substr( $end_iso, 11 ) !== '00:00:00' )
			? substr( $end_iso, 11, 5 ) : '';

		// Resolve format overrides.
		$plugin_settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$date_format     = ! empty( $attributes['slotDateFormat'] )
			? $attributes['slotDateFormat']
			: ( ! empty( $plugin_settings['date_format'] ) ? $plugin_settings['date_format'] : get_option( 'date_format' ) );
		$time_format     = ! empty( $attributes['slotTimeFormat'] )
			? $attributes['slotTimeFormat']
			: ( ! empty( $plugin_settings['time_format'] ) ? $plugin_settings['time_format'] : get_option( 'time_format' ) );

		if ( 'date' === $slot ) {
			return $this->format_date_only( $start_date, $end_date, $date_format, $show_end );
		}

		if ( 'time' === $slot ) {
			return $this->format_time_only( $start_date, $start_time, $end_date, $end_time, $time_format, $show_end );
		}

		// 'date_time' — full range with both date and time spans.
		return $this->format_date_time( $start_date, $start_time, $end_date, $end_time, $date_format, $time_format, $show_end );
	}

	/**
	 * Format the date portion only.
	 *
	 * @since 2.0.0
	 * @param string $start_date  Y-m-d
	 * @param string $end_date    Y-m-d (may be empty)
	 * @param string $date_format PHP date format string.
	 * @param bool   $show_end    Whether to include the end date.
	 * @return string HTML.
	 */
	private function format_date_only( $start_date, $end_date, $date_format, $show_end ) {
		$start_ts = strtotime( $start_date );
		$output   = '<span class="carkeek-event-date">' . esc_html( date_i18n( $date_format, $start_ts ) ) . '</span>';

		if ( $show_end && $end_date && $end_date !== $start_date ) {
			$end_ts  = strtotime( $end_date );
			$output .= ' &ndash; <span class="carkeek-event-date">' . esc_html( date_i18n( $date_format, $end_ts ) ) . '</span>';
		}

		return $output;
	}

	/**
	 * Format the time portion only.
	 *
	 * @since 2.0.0
	 * @param string $start_date  Y-m-d
	 * @param string $start_time  H:i (may be empty)
	 * @param string $end_date    Y-m-d (may be empty)
	 * @param string $end_time    H:i (may be empty)
	 * @param string $time_format PHP time format string.
	 * @param bool   $show_end    Whether to include the end time.
	 * @return string HTML, or empty string if no start time is set.
	 */
	private function format_time_only( $start_date, $start_time, $end_date, $end_time, $time_format, $show_end ) {
		if ( ! $start_time ) {
			return '';
		}

		$start_ts = strtotime( $start_date . ' ' . $start_time );
		$output   = '<span class="carkeek-event-time">' . esc_html( date_i18n( $time_format, $start_ts ) ) . '</span>';

		if ( $show_end && $end_time ) {
			$end_context = $end_date ?: $start_date;
			$end_ts      = strtotime( $end_context . ' ' . $end_time );
			$output     .= ' &ndash; <span class="carkeek-event-time">' . esc_html( date_i18n( $time_format, $end_ts ) ) . '</span>';
		}

		return $output;
	}

	/**
	 * Format a full date + time range (same logic as CarkeekEvents_Display::format_date_range
	 * but with per-block format overrides applied).
	 *
	 * @since 2.0.0
	 * @param string $start_date  Y-m-d
	 * @param string $start_time  H:i (may be empty)
	 * @param string $end_date    Y-m-d (may be empty)
	 * @param string $end_time    H:i (may be empty)
	 * @param string $date_format PHP date format string.
	 * @param string $time_format PHP time format string.
	 * @param bool   $show_end    Whether to include the end date/time.
	 * @return string HTML.
	 */
	private function format_date_time( $start_date, $start_time, $end_date, $end_time, $date_format, $time_format, $show_end ) {
		if ( ! $show_end ) {
			$end_date = '';
			$end_time = '';
		}

		$start_ts = strtotime( $start_date );
		$end_ts   = ( $end_date && $end_date !== $start_date ) ? strtotime( $end_date ) : 0;
		$same_day = ! $end_ts;

		$output = '<span class="carkeek-event-date">' . esc_html( date_i18n( $date_format, $start_ts ) ) . '</span>';

		if ( $start_time ) {
			$start_time_ts = strtotime( $start_date . ' ' . $start_time );
			$start_time_fmt = esc_html( date_i18n( $time_format, $start_time_ts ) );

			if ( $same_day && $end_time ) {
				$end_time_ts  = strtotime( $start_date . ' ' . $end_time );
				$end_time_fmt = esc_html( date_i18n( $time_format, $end_time_ts ) );
				$output .= ', <span class="carkeek-event-time">' . $start_time_fmt . ' &ndash; ' . $end_time_fmt . '</span>';
			} else {
				$output .= ', <span class="carkeek-event-time">' . $start_time_fmt . '</span>';
			}
		}

		if ( ! $same_day ) {
			$end_date_fmt = '<span class="carkeek-event-date">' . esc_html( date_i18n( $date_format, $end_ts ) ) . '</span>';
			$output      .= ' &ndash; ' . $end_date_fmt;

			if ( $end_time ) {
				$end_time_ts  = strtotime( $end_date . ' ' . $end_time );
				$end_time_fmt = esc_html( date_i18n( $time_format, $end_time_ts ) );
				$output      .= ', <span class="carkeek-event-time">' . $end_time_fmt . '</span>';
			}
		}

		return $output;
	}

	/**
	 * Render the location name only (no address, no link).
	 *
	 * @since 2.0.0
	 * @param int $post_id Event post ID.
	 * @return string Escaped location name, or empty string.
	 */
	private function render_location_name( $post_id ) {
		$location_id   = (int) get_post_meta( $post_id, '_carkeek_event_location_id', true );
		$location_text = get_post_meta( $post_id, '_carkeek_event_location_text', true );

		if ( $location_id ) {
			$loc = get_post( $location_id );
			if ( $loc && 'publish' === $loc->post_status ) {
				return esc_html( $loc->post_title );
			}
		}

		return $location_text ? esc_html( $location_text ) : '';
	}

	/**
	 * Render the organizer name only (no contact info, no link).
	 *
	 * @since 2.0.0
	 * @param int $post_id Event post ID.
	 * @return string Escaped organizer name, or empty string.
	 */
	private function render_organizer_name( $post_id ) {
		$organizer_id   = (int) get_post_meta( $post_id, '_carkeek_event_organizer_id', true );
		$organizer_text = get_post_meta( $post_id, '_carkeek_event_organizer_text', true );

		if ( $organizer_id ) {
			$org = get_post( $organizer_id );
			if ( $org && 'publish' === $org->post_status ) {
				return esc_html( $org->post_title );
			}
		}

		return $organizer_text ? esc_html( $organizer_text ) : '';
	}

	/**
	 * Render the post excerpt, trimmed to excerptLength words.
	 *
	 * @since 2.0.0
	 * @param int   $post_id    Event post ID.
	 * @param array $attributes Block attributes.
	 * @return string Excerpt HTML, or empty string.
	 */
	private function render_excerpt( $post_id, $attributes ) {
		$length = ! empty( $attributes['excerptLength'] ) ? (int) $attributes['excerptLength'] : 25;

		// Use the post's manual excerpt if set, otherwise auto-generate from content.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		if ( $post->post_excerpt ) {
			$excerpt = $post->post_excerpt;
		} else {
			$excerpt = wp_trim_words( strip_shortcodes( $post->post_content ), $length );
		}

		return $excerpt ? wp_kses_post( $excerpt ) : '';
	}

	// -----------------------------------------------------------------------
	// Card template (used by carkeek-blocks integration, not this block)
	// -----------------------------------------------------------------------

	/**
	 * Locate the event card template, allowing theme overrides.
	 * Used by the carkeek-blocks custom-archive integration.
	 *
	 * @since 2.0.0
	 * @return string Absolute path to the card template file.
	 */
	public static function locate_card_template() {
		$theme_template = locate_template( 'carkeek-events/event-card/default.php' );
		return $theme_template ?: CARKEEKEVENTS_PLUGIN_DIR . 'templates/event-card/default.php';
	}
}

CarkeekEvents_Block::register();
