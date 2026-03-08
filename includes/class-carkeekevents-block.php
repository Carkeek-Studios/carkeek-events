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
		add_action( 'wp_ajax_carkeek_events_load_more',        array( $instance, 'ajax_load_more' ) );
		add_action( 'wp_ajax_nopriv_carkeek_events_load_more', array( $instance, 'ajax_load_more' ) );
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
		$config = $this->get_post_type_config( $attributes );
		$query  = new WP_Query( $this->build_query_args( $attributes ) );

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

		$list_classes = array_filter( array(
			'carkeek-events-archive',
			'carkeek-events-archive__list',
			'is-' . esc_attr( $layout ),
			'grid' === $layout ? 'columns-' . $columns : '',
			'grid' === $layout ? 'columns-tablet-' . $tablet : '',
			'grid' === $layout ? 'columns-mobile-' . $mobile : '',
		) );

		$slots = $this->get_slots( $attributes );

		$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'carkeek-events-archive-block' ) );

		ob_start();
		echo '<div ' . $wrapper_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $attributes['headline'] ) ) {
			echo '<h2 class="carkeek-events-archive__headline">' . esc_html( $attributes['headline'] ) . '</h2>';
		}

		echo '<div class="' . esc_attr( implode( ' ', $list_classes ) ) . '">';

		$this->prime_linked_posts( $query->posts, $config['is_alt'] );

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id   = get_the_ID();
			$permalink = get_permalink( $post_id );
			echo $this->render_single_card( $post_id, $permalink, $slots, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		wp_reset_postdata();
		echo '</div>'; // .carkeek-events-archive__list

		if ( ! empty( $attributes['showPagination'] ) ) {
			echo paginate_links( array( 'total' => $query->max_num_pages ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$num_posts = isset( $attributes['numberOfPosts'] ) ? (int) $attributes['numberOfPosts'] : 6;
		if ( ! empty( $attributes['enableLoadMore'] ) && -1 !== $num_posts && $query->found_posts > $num_posts ) {
			$label           = ! empty( $attributes['loadMoreLabel'] ) ? $attributes['loadMoreLabel'] : __( 'Load More', 'carkeek-events' );
			$load_more_attrs = array_merge( $attributes, array( 'showPagination' => false ) );
			echo '<div class="carkeek-events-archive__load-more-wrap">';
			echo '<button type="button" class="button js-carkeek-events-load-more"'
				. ' data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"'
				. ' data-nonce="' . esc_attr( wp_create_nonce( 'carkeek_events_load_more' ) ) . '"'
				. ' data-current-page="1"'
				. ' data-default-label="' . esc_attr( $label ) . '"'
				. ' data-loading-label="' . esc_attr( __( 'Loading\u2026', 'carkeek-events' ) ) . '"'
				. ' data-error-label="' . esc_attr( __( 'Unable to load more events.', 'carkeek-events' ) ) . '"'
				. ' data-attributes="' . esc_attr( wp_json_encode( $load_more_attrs ) ) . '"'
				. '>' . esc_html( $label ) . '</button>';
			echo '<div class="carkeek-events-archive__load-more-status js-carkeek-events-load-more-status" aria-live="polite"></div>';
			echo '</div>';
		}

		echo '</div>'; // .carkeek-events-archive-block

		return ob_get_clean();
	}

	// -----------------------------------------------------------------------
	// Post type configuration (carkeek_event or alternative CPT)
	// -----------------------------------------------------------------------

	/**
	 * Resolve the active post type configuration from block attributes.
	 *
	 * Returns an array with keys: post_type, start_meta, end_meta, taxonomy, is_alt.
	 * Falls back to carkeek_event defaults if the alternative post type is missing
	 * or refers to an unregistered post type.
	 *
	 * @since 2.1.0
	 * @param array $attributes Block attributes.
	 * @return array
	 */
	private function get_post_type_config( $attributes ) {
		$use_alt = ! empty( $attributes['useAltPostType'] );

		if ( $use_alt ) {
			$post_type  = sanitize_key( $attributes['altPostType'] ?? '' );
			$start_meta = sanitize_key( $attributes['altStartMetaKey'] ?? '' );
			$end_meta   = sanitize_key( $attributes['altEndMetaKey'] ?? '' );
			$taxonomy   = sanitize_key( $attributes['altTaxonomy'] ?? '' );

			// Guard: fall back to carkeek_event if CPT or start meta are missing/invalid.
			if ( ! $post_type || ! $start_meta || ! post_type_exists( $post_type ) ) {
				$use_alt = false;
			}
		}

		if ( ! $use_alt ) {
			return array(
				'post_type'  => 'carkeek_event',
				'start_meta' => '_carkeek_event_start',
				'end_meta'   => '_carkeek_event_end',
				'taxonomy'   => 'carkeek_event_category',
				'is_alt'     => false,
			);
		}

		return array(
			'post_type'  => $post_type,
			'start_meta' => $start_meta,
			'end_meta'   => $end_meta,
			'taxonomy'   => $taxonomy,
			'is_alt'     => true,
		);
	}

	// -----------------------------------------------------------------------
	// Query builder (shared by render() and ajax_load_more())
	// -----------------------------------------------------------------------

	/**
	 * Build WP_Query args from block attributes, with an optional offset for pagination.
	 *
	 * @since 2.0.0
	 * @param array $attributes Block attributes.
	 * @param int   $offset     Row offset for load-more pages (0 = first page).
	 * @return array WP_Query args (already passed through the filter).
	 */
	private function build_query_args( $attributes, $offset = 0 ) {
		$now    = current_time( 'Y-m-d\TH:i:s' );
		$config = $this->get_post_type_config( $attributes );

		$num_posts = isset( $attributes['numberOfPosts'] ) ? (int) $attributes['numberOfPosts'] : 6;

		$args = array(
			'post_type'      => $config['post_type'],
			'post_status'    => 'publish',
			'posts_per_page' => ( -1 === $num_posts ) ? -1 : max( 1, $num_posts ),
			'meta_key'       => $config['start_meta'],
			'orderby'        => 'meta_value',
			'order'          => ( isset( $attributes['sortOrder'] ) && 'DESC' === strtoupper( $attributes['sortOrder'] ) ) ? 'DESC' : 'ASC',
			'meta_query'     => array(
				'relation' => 'AND',
			),
		);

		// Hidden event exclusion only applies to carkeek_event.
		if ( ! $config['is_alt'] ) {
			$args['meta_query'][] = CarkeekEvents_Query::hidden_exclusion_clause();
		}

		if ( $offset > 0 ) {
			$args['offset'] = $offset;
		}

		$include_past = ! empty( $attributes['includePastEvents'] );
		$only_past    = ! empty( $attributes['onlyPastEvents'] );
		$end_meta     = $config['end_meta'];

		if ( $only_past && $end_meta ) {
			$args['meta_query'][] = array(
				'key' => $end_meta, 'value' => $now, 'compare' => '<', 'type' => 'CHAR',
			);
		} elseif ( ! $include_past && $end_meta ) {
			$args['meta_query'][] = array(
				'relation' => 'OR',
				array( 'key' => $end_meta, 'compare' => 'NOT EXISTS' ),
				array( 'key' => $end_meta, 'value'   => '', 'compare' => '=' ),
				array( 'key' => $end_meta, 'value'   => $now, 'compare' => '>=', 'type' => 'CHAR' ),
			);
		}

		if ( ! empty( $attributes['filterByCategory'] ) && ! empty( $attributes['catTermsSelected'] ) && $config['taxonomy'] ) {
			$term_ids = array_filter( array_map( 'intval', explode( ',', $attributes['catTermsSelected'] ) ) );
			if ( $term_ids ) {
				$operator = ( isset( $attributes['catFilterMode'] ) && 'exclude' === $attributes['catFilterMode'] )
					? 'NOT IN' : 'IN';
				$args['tax_query'] = array(
					array(
						'taxonomy' => $config['taxonomy'],
						'field'    => 'term_id',
						'terms'    => $term_ids,
						'operator' => $operator,
					),
				);
			}
		}

		return apply_filters( 'carkeek_events_block_query_args', $args, $attributes );
	}

	/**
	 * Render a single event card (used by both render() and ajax_load_more()).
	 *
	 * @since 2.0.0
	 * @param int    $post_id    Event post ID (must be the current post in the loop).
	 * @param string $permalink  Event permalink.
	 * @param array  $slots      Ordered slot identifiers.
	 * @param array  $attributes Block attributes.
	 * @return string HTML for the card.
	 */
	private function render_single_card( $post_id, $permalink, $slots, $attributes ) {
		$html = '<div class="carkeek-event-card">';

		if ( ! empty( $attributes['displayFeaturedImage'] ) && has_post_thumbnail( $post_id ) ) {
			$html .= '<a class="carkeek-event-card__image-link" href="' . esc_url( $permalink ) . '">';
			$html .= get_the_post_thumbnail( $post_id, 'medium_large' );
			$html .= '</a>';
		}

		$html .= '<div class="carkeek-event-card__content">';
		$before_slots = apply_filters( 'carkeek_events_block_before_slots', '', $post_id, $attributes );
		if ( $before_slots ) {
			$html .= '<div class="carkeek-event-card__before-slots">' . wp_kses_post( $before_slots ) . '</div>';
		}

		foreach ( $slots as $slot ) {
			$slot_html = $this->render_slot( $slot, $post_id, $permalink, $attributes );
			if ( '' !== $slot_html ) {
				$html .= '<div class="carkeek-event-card__slot carkeek-event-card__slot--' . esc_attr( $slot ) . '">';
				$html .= $slot_html; // already escaped in each render_* method
				$html .= '</div>';
			}
		}
		$after_slots = apply_filters( 'carkeek_events_block_after_slots', '', $post_id, $attributes );
		if ( $after_slots ) {
			$html .= '<div class="carkeek-event-card__after-slots">' . wp_kses_post( $after_slots ) . '</div>';
		}

		$html .= '</div>'; // .carkeek-event-card__content
		$html .= '</div>'; // .carkeek-event-card

		return $html;
	}

	/**
	 * AJAX handler for the Load More button.
	 *
	 * @since 2.0.0
	 * @return void Sends JSON and exits.
	 */
	public function ajax_load_more() {
		if ( ! check_ajax_referer( 'carkeek_events_load_more', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		$attributes_json = isset( $_POST['attributes'] ) ? wp_unslash( $_POST['attributes'] ) : ''; // phpcs:ignore
		$attributes      = json_decode( $attributes_json, true );
		$page            = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 2; // phpcs:ignore

		if ( ! is_array( $attributes ) ) {
			wp_send_json_error( array( 'message' => 'Invalid attributes.' ), 400 );
		}

		$page     = max( 2, $page );
		$per_page = isset( $attributes['numberOfPosts'] ) ? (int) $attributes['numberOfPosts'] : 6;

		if ( $per_page <= 0 ) {
			wp_send_json_error( array( 'message' => 'Show-all mode does not support load more.' ), 400 );
		}

		// Cap per-page to prevent DoS via inflated numberOfPosts from client payload.
		$per_page = min( $per_page, 100 );

		// Whitelist sortOrder to prevent unexpected values reaching WP_Query.
		if ( isset( $attributes['sortOrder'] ) && ! in_array( strtoupper( $attributes['sortOrder'] ), array( 'ASC', 'DESC' ), true ) ) {
			$attributes['sortOrder'] = 'ASC';
		}

		$offset = ( $page - 1 ) * $per_page;
		$config = $this->get_post_type_config( $attributes );
		$query  = new WP_Query( $this->build_query_args( $attributes, $offset ) );

		$slots      = $this->get_slots( $attributes );
		$items_html = '';

		$this->prime_linked_posts( $query->posts, $config['is_alt'] );

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id    = get_the_ID();
			$permalink  = get_permalink( $post_id );
			$items_html .= $this->render_single_card( $post_id, $permalink, $slots, $attributes );
		}

		$has_more = ( $offset + $per_page ) < (int) $query->found_posts;
		wp_reset_postdata();

		wp_send_json_success( array(
			'itemsHtml' => $items_html,
			'nextPage'  => $page,
			'hasMore'   => $has_more,
		) );
	}

	/**
	 * Parse the contentSlots attribute into an ordered array of slot identifiers.
	 *
	 * @since 2.1.0
	 * @param array $attributes Block attributes.
	 * @return array
	 */
	private function get_slots( $attributes ) {
		return ! empty( $attributes['contentSlots'] )
			? array_filter( explode( ',', $attributes['contentSlots'] ) )
			: array( 'title', 'date_time' );
	}

	/**
	 * Prime the WordPress object cache with linked location and organizer posts
	 * before the render loop to avoid N+1 queries per card.
	 * Only runs for carkeek_event — alt CPTs do not use location/organizer IDs.
	 *
	 * @since 2.1.0
	 * @param int[]  $post_ids   Array of event post IDs from WP_Query.
	 * @param bool   $is_alt     True when rendering an alternative post type.
	 * @return void
	 */
	private function prime_linked_posts( $post_ids, $is_alt = false ) {
		if ( $is_alt ) {
			return;
		}
		if ( empty( $post_ids ) ) {
			return;
		}

		$loc_ids = array_filter( array_unique( array_map( function( $id ) {
			return (int) get_post_meta( $id, '_carkeek_event_location_id', true );
		}, $post_ids ) ) );

		$org_ids = array_filter( array_unique( array_map( function( $id ) {
			return (int) get_post_meta( $id, '_carkeek_event_organizer_id', true );
		}, $post_ids ) ) );

		if ( $loc_ids ) {
			get_posts( array(
				'post__in'       => array_values( $loc_ids ),
				'post_type'      => 'carkeek_location',
				'posts_per_page' => count( $loc_ids ),
				'no_found_rows'  => true,
			) );
		}

		if ( $org_ids ) {
			get_posts( array(
				'post__in'       => array_values( $org_ids ),
				'post_type'      => 'carkeek_organizer',
				'posts_per_page' => count( $org_ids ),
				'no_found_rows'  => true,
			) );
		}
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

			case 'button_link':
				$label = ! empty( $attributes['buttonLinkLabel'] )
					? esc_html( $attributes['buttonLinkLabel'] )
					: esc_html( get_the_title( $post_id ) );
				return '<a class="arrow-link" href="' . esc_url( $permalink ) . '">' . $label . '</a>';

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
		$config    = $this->get_post_type_config( $attributes );
		$start_iso = get_post_meta( $post_id, $config['start_meta'], true );
		$end_iso   = $config['end_meta'] ? get_post_meta( $post_id, $config['end_meta'], true ) : '';

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
