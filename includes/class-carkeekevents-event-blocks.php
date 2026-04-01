<?php
/**
 * Event Detail Blocks
 *
 * Registers four dynamic Gutenberg blocks for displaying individual event
 * data fields on single-event page layouts:
 *
 *   carkeek-events/event-date-time — Date & time range
 *   carkeek-events/event-location  — Location name / address
 *   carkeek-events/event-organizer — Organizer name / contact
 *   carkeek-events/event-details   — All three combined
 *
 * Block metadata and render files are read from the compiled build/ directory.
 * Each block.json declares `"render": "file:./render.php"` so no render_callback
 * is needed here — WordPress loads the render file automatically.
 *
 * @package carkeek-events
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Event_Blocks
 */
class CarkeekEvents_Event_Blocks {

	/**
	 * The four block slugs managed by this class.
	 *
	 * @var string[]
	 */
	private static $blocks = array(
		'event-date-time',
		'event-location',
		'event-organizer',
		'event-details',
	);

	/**
	 * Register hooks.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_action( 'init', array( $instance, 'register_blocks' ) );
		add_filter( 'allowed_block_types_all', array( $instance, 'restrict_blocks_to_event_post_type' ), 10, 2 );
	}

	/**
	 * Register each event detail block from its compiled build directory.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function register_blocks() {
		foreach ( self::$blocks as $slug ) {
			$build_dir = CARKEEKEVENTS_PLUGIN_DIR . 'build/' . $slug;
			if ( file_exists( $build_dir . '/block.json' ) ) {
				register_block_type( $build_dir );
			}
		}
	}

	/**
	 * Remove the event detail blocks from the inserter on non-event post types.
	 *
	 * block.json already declares `postTypes: ["carkeek_event"]` which handles
	 * the editor-side restriction. This filter adds a server-side belt-and-suspenders
	 * guard so the blocks cannot be inserted via REST on other post types either.
	 *
	 * When $allowed_block_types is true (default — all blocks allowed) this filter
	 * is a no-op for non-event contexts; it only acts when editing a carkeek_event.
	 *
	 * @since 2.1.0
	 *
	 * @param bool|string[] $allowed_block_types Array of block type slugs, or true for all.
	 * @param WP_Block_Editor_Context $editor_context The current block editor context.
	 * @return bool|string[] Filtered allowed block types.
	 */
	public function restrict_blocks_to_event_post_type( $allowed_block_types, $editor_context ) {
		// Only intervene when the editor is working with a non-event post.
		if (
			empty( $editor_context->post ) ||
			'carkeek_event' === $editor_context->post->post_type
		) {
			return $allowed_block_types;
		}

		// All blocks are allowed — nothing to filter out.
		if ( true === $allowed_block_types ) {
			return $allowed_block_types;
		}

		// Remove our event-only blocks from an explicit allowlist.
		$event_block_names = array_map(
			fn( $slug ) => 'carkeek-events/' . $slug,
			self::$blocks
		);

		return array_values(
			array_diff( (array) $allowed_block_types, $event_block_names )
		);
	}
}
