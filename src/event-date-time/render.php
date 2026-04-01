<?php
/**
 * Render callback for carkeek-events/event-date-time.
 *
 * Variables provided by WordPress:
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block HTML (unused).
 * @var WP_Block $block      Block instance.
 *
 * @package carkeek-events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the event post ID.
 *
 * Priority order:
 * 1. $_GET['postId'] — SSR editor preview via ServerSideRender urlQueryArgs.
 * 2. $block->context['postId'] — when nested inside a core/query loop.
 * 3. get_the_ID() — standard single-event page (most common frontend path).
 */
if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! empty( $_GET['postId'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post_id = absint( $_GET['postId'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
} elseif ( ! empty( $block->context['postId'] ) ) {
	$post_id = (int) $block->context['postId'];
} else {
	$post_id = (int) get_the_ID();
}

if ( ! $post_id ) {
	return;
}

$post = get_post( $post_id );
if ( ! $post || 'carkeek_event' !== $post->post_type || 'publish' !== $post->post_status ) {
	return;
}

// Prime the object cache — loads all event meta in one query so subsequent
// individual get_post_meta() calls in Display methods hit cache, not the DB.
get_post_meta( $post_id );

$html = CarkeekEvents_Display::get_date_range_html( $post_id );
if ( ! $html ) {
	return;
}

$wrapper_attrs = get_block_wrapper_attributes();
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper attrs from WP core; html constructed with esc_* internally, passed through documented developer filter.
echo '<div ' . $wrapper_attrs . '>' . wp_kses_post( $html ) . '</div>';
