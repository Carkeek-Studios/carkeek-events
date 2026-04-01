<?php
/**
 * Query Integration
 *
 * Applies a chronological default sort to front-end carkeek_event queries
 * and integrates with the carkeek-blocks custom-archive block via its
 * query_args filter.
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Query
 */
class CarkeekEvents_Query {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_action( 'pre_get_posts', array( $instance, 'apply_default_sort' ) );

		// Integrate with carkeek-blocks custom-archive block (only if filter exists).
		add_filter( 'carkeek_block_custom_post_layout__query_args', array( $instance, 'inject_event_meta_query' ), 10, 2 );

		// Handle sortable start_date column in admin.
		add_action( 'pre_get_posts', array( $instance, 'handle_admin_sort' ) );
	}

	/**
	 * Apply chronological default sort to front-end main queries for carkeek_event.
	 *
	 * @since 1.0.0
	 * @param WP_Query $query The current WP_Query instance.
	 * @return void
	 */
	public function apply_default_sort( $query ) {
		if ( is_admin() ) {
			return;
		}
		if ( ! $query->is_main_query() ) {
			return;
		}
		if ( 'carkeek_event' !== $query->get( 'post_type' ) ) {
			return;
		}

		// Apply chronological default sort if not already set.
		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'meta_key', '_carkeek_event_start' );
			$query->set( 'order', 'ASC' );
		}
	}

	/**
	 * Apply default sort to carkeek-blocks custom-archive block queries for events.
	 *
	 * @since 1.0.0
	 * @param array $args       WP_Query args from carkeek-blocks.
	 * @param array $attributes Block attributes.
	 * @return array Modified query args.
	 */
	public function inject_event_meta_query( $args, $attributes ) {
		if ( ( $args['post_type'] ?? '' ) !== 'carkeek_event' ) {
			return $args;
		}

		// Allow add-ons to further modify the query args.
		$args = apply_filters( 'carkeek_events_query_args', $args, $attributes );

		// Apply chronological default sort if not already set.
		if ( empty( $args['orderby'] ) || 'date' === $args['orderby'] ) {
			$args['orderby']  = 'meta_value';
			$args['meta_key'] = '_carkeek_event_start';
			$args['order']    = 'ASC';
		}

		return $args;
	}

	/**
	 * Handle start_date column sort in admin list table.
	 *
	 * @since 1.0.0
	 * @param WP_Query $query Current WP_Query.
	 * @return void
	 */
	public function handle_admin_sort( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'carkeek_event' !== $query->get( 'post_type' ) ) {
			return;
		}
		if ( 'start_date' === $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'meta_key', '_carkeek_event_start' );
		}
	}
}

CarkeekEvents_Query::register();
