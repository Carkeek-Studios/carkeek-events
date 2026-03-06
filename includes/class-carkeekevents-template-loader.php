<?php
/**
 * Template Loader & Template Hooks
 *
 * Provides theme-override capability for event templates via the Gamajo
 * Template Loader pattern.
 *
 * Theme override directory: {theme}/carkeek-events/
 * Plugin template directory: carkeek-events/templates/
 *
 * Single event template: templates/single-carkeek_event.php
 *   Theme override: {theme}/carkeek-events/single-carkeek_event.php
 *
 * Event card template (used by carkeek-blocks custom-archive block):
 *   templates/event-card/default.php
 *   Theme override: {theme}/carkeek-blocks/custom-archive/default.php
 *     (or hook carkeek_events_card_template to supply a custom path)
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gamajo_Template_Loader' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'class-gamajo-template-loader.php';
}

/**
 * Template loader subclass for Carkeek Events.
 */
class CarkeekEvents_Template_Loader extends Gamajo_Template_Loader {

	/**
	 * Prefix for filter names.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $filter_prefix = 'carkeek-events';

	/**
	 * Directory name in theme for template overrides.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $theme_template_directory = 'carkeek-events';

	/**
	 * Root directory path of this plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $plugin_directory = CARKEEKEVENTS_PLUGIN_DIR;

	/**
	 * Directory name where templates are found in this plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $plugin_template_directory = 'templates';
}

/**
 * Register template-related hooks.
 *
 * Filters single_template to load plugin template for carkeek_event posts.
 * Filters carkeek_block_custom_post_layout__template to use event card template
 * when the carkeek-blocks custom-archive block queries events.
 */
function carkeek_events_register_template_hooks() {
	// Single event template.
	add_filter( 'single_template', 'carkeek_events_single_template' );

	// Card template for carkeek-blocks custom-archive block.
	// Note: carkeek_block_custom_post_layout__template passes (template, attributes) — 2 args only.
	add_filter( 'carkeek_block_custom_post_layout__template', 'carkeek_events_card_template', 10, 2 );
}
add_action( 'plugins_loaded', 'carkeek_events_register_template_hooks', 20 );

/**
 * Return the single event template path.
 *
 * Checks theme first (carkeek-events/single-carkeek_event.php), then plugin.
 *
 * @since 1.0.0
 * @param string $template Default template path.
 * @return string
 */
function carkeek_events_single_template( $template ) {
	global $post;
	if ( ! $post || 'carkeek_event' !== $post->post_type ) {
		return $template;
	}

	// If the setting is disabled, let WordPress use the theme's own template.
	$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
	if ( isset( $settings['use_plugin_template'] ) && '0' === $settings['use_plugin_template'] ) {
		return $template;
	}

	// Allow complete override via filter.
	$custom = apply_filters( 'carkeek_events_single_template', '' );
	if ( $custom && file_exists( $custom ) ) {
		return $custom;
	}

	// Check theme first.
	$theme_template = locate_template( array( 'carkeek-events/single-carkeek_event.php' ) );
	if ( $theme_template ) {
		return $theme_template;
	}

	// Fall back to plugin template.
	$plugin_template = CARKEEKEVENTS_PLUGIN_DIR . 'templates/single-carkeek_event.php';
	if ( file_exists( $plugin_template ) ) {
		return $plugin_template;
	}

	return $template;
}

/**
 * Return the event card template path for carkeek-blocks custom-archive block.
 *
 * carkeek_block_custom_post_layout__template passes only (template, attributes).
 * The current post is retrieved via get_post().
 *
 * @since 1.0.0
 * @param string $template   Default template path from carkeek-blocks.
 * @param array  $attributes Block attributes.
 * @return string
 */
function carkeek_events_card_template( $template, $attributes ) {
	$post = get_post();
	if ( ! $post || 'carkeek_event' !== $post->post_type ) {
		return $template;
	}

	$card_template = CARKEEKEVENTS_PLUGIN_DIR . 'templates/event-card/default.php';

	return apply_filters( 'carkeek_events_card_template', $card_template, $post, $attributes );
}
