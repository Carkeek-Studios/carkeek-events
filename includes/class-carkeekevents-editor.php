<?php
/**
 * Block Editor Integration
 *
 * Two responsibilities:
 *   1. Optionally disable the block editor for the plugin's CPTs (a settings
 *      toggle), falling back to the classic editor.
 *   2. Enqueue the "Event Options" document sidebar panel (the "Hide from
 *      calendar" toggle) when the block editor is active for an event.
 *
 * @package carkeek-events
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Editor
 */
class CarkeekEvents_Editor {

	/**
	 * CPTs affected by the disable-block-editor setting.
	 *
	 * @since 2.1.0
	 * @var string[]
	 */
	const POST_TYPES = array( 'carkeek_event', 'carkeek_location', 'carkeek_organizer' );

	/**
	 * Register hooks.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_filter( 'use_block_editor_for_post_type', array( $instance, 'maybe_disable_block_editor' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $instance, 'enqueue_event_options_panel' ) );
	}

	/**
	 * Disable the block editor for the plugin CPTs when the setting is enabled.
	 *
	 * @since 2.1.0
	 * @param bool   $enabled   Whether the block editor is enabled.
	 * @param string $post_type The post type being edited.
	 * @return bool
	 */
	public function maybe_disable_block_editor( $enabled, $post_type ) {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		if ( ! empty( $settings['disable_block_editor'] ) && in_array( $post_type, self::POST_TYPES, true ) ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * Enqueue the "Event Options" document sidebar panel for events.
	 *
	 * Only fires in the block editor (this hook does not run in the classic
	 * editor), so the classic side meta box never collides with this panel.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function enqueue_event_options_panel() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'carkeek_event' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'carkeek-event-options',
			CARKEEKEVENTS_PLUGIN_URL . 'assets/js/carkeek-event-options.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-i18n' ),
			CARKEEKEVENTS_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'carkeek-event-options', 'carkeek-events' );
		}
	}
}

CarkeekEvents_Editor::register();
