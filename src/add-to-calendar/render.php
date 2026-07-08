<?php
/**
 * Render callback for carkeek-events/add-to-calendar.
 *
 * Renders the "Add to Calendar" control (Google + .ics) for a Carkeek Event.
 *
 * @var array    $attributes Block attributes (buttonLabel, googleLabel, icalLabel).
 * @var string   $content    Inner block HTML (unused).
 * @var WP_Block $block      Block instance.
 *
 * @package carkeek-events
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the event post ID.
 *
 * 1. $_GET['postId'] — SSR editor preview via ServerSideRender urlQueryArgs.
 * 2. $block->context['postId'] — when nested inside a core/query loop.
 * 3. get_the_ID() — standard single-event page.
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
if ( ! $post || 'carkeek_event' !== $post->post_type || 'trash' === $post->post_status ) {
	return;
}

$html = CarkeekEvents_Display::get_add_to_calendar_html( $post_id, $attributes );
if ( '' === $html ) {
	return;
}

$wrapper_attrs = get_block_wrapper_attributes();

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper attrs from WP core; $html built with esc_* internally.
echo '<div ' . $wrapper_attrs . '>' . $html . '</div>';
