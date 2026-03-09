<?php
/**
 * Plugin Name:       Carkeek Events
 * Description:       Lightweight, developer-friendly event management. Registers Event, Location, and Organizer custom post types with meta boxes, expiry cron, and optional Google Maps geocoding. Integrates with carkeek-blocks custom-archive block.
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Version:           2.0.1
 * Author:            Carkeek Studios
 * Author URI:        https://carkeekstudios.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       carkeek-events
 * GitHub Plugin URI: Carkeek-Studios/carkeek-events
 * Primary Branch:    main
 *
 * @package carkeek-events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CarkeekEvents' ) ) {

	/**
	 * Main CarkeekEvents Class.
	 *
	 * @since 1.0.0
	 */
	final class CarkeekEvents {

		/**
		 * The plugin's instance.
		 *
		 * @var CarkeekEvents
		 * @since 1.0.0
		 */
		private static $instance;

		/**
		 * Main CarkeekEvents instance.
		 *
		 * Ensures only one instance exists.
		 *
		 * @since 1.0.0
		 * @static
		 * @return CarkeekEvents
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof CarkeekEvents ) ) {
				self::$instance = new CarkeekEvents();
				self::$instance->setup_constants();
				self::$instance->init();
			}
			return self::$instance;
		}

		/**
		 * Throw error on object clone.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheating huh?', 'carkeek-events' ), '1.0.0' );
		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheating huh?', 'carkeek-events' ), '1.0.0' );
		}

		/**
		 * Setup plugin constants.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		private function setup_constants() {
			$plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
			$this->define( 'CARKEEKEVENTS_VERSION', $plugin_data['Version'] );
			$this->define( 'CARKEEKEVENTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			$this->define( 'CARKEEKEVENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			$this->define( 'CARKEEKEVENTS_PLUGIN_FILE', __FILE__ );
			$this->define( 'CARKEEKEVENTS_PLUGIN_BASE', plugin_basename( __FILE__ ) );
			$this->define( 'CARKEEKEVENTS_OPTION_NAME', 'carkeek_events_settings' );
		}

		/**
		 * Define a constant if not already set.
		 *
		 * @param string $name  Constant name.
		 * @param mixed  $value Constant value.
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * Load actions.
		 *
		 * All hooks run on the plugins_loaded cycle:
		 *   priority 15 – includes (load all classes)
		 *   priority 99 – load_textdomain
		 *
		 * @since 1.0.0
		 * @return void
		 */
		private function init() {
			add_action( 'plugins_loaded', array( $this, 'includes' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 99 );
			// Flush rewrite rules automatically when archive settings change.
			add_action( 'update_option_carkeek_events_settings', 'flush_rewrite_rules' );
		}

		/**
		 * Include required files.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function includes() {
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-post-types.php';
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-display.php';
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-meta.php';
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-meta-boxes.php';
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-settings.php';
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-admin.php';
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-cron.php';
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-query.php';
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-geocode.php';
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-gamajo-template-loader.php';
			require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-template-loader.php';
			if ( file_exists( CARKEEKEVENTS_PLUGIN_DIR . 'build/events-archive/index.asset.php' ) ) {
				require_once CARKEEKEVENTS_PLUGIN_DIR . 'includes/class-carkeekevents-block.php';
			}
		}

		/**
		 * Loads the plugin language files.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'carkeek-events', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
	}
}

/**
 * Returns the main CarkeekEvents instance.
 *
 * @since 1.0.0
 * @return CarkeekEvents
 */
function carkeek_events() {
	return CarkeekEvents::instance();
}

// Bootstrap at plugins_loaded priority 1 — multisite-safe.
add_action( 'plugins_loaded', 'carkeek_events', 1 );

/**
 * Plugin activation hook.
 *
 * Flushes rewrite rules and schedules the daily cron.
 */
function carkeek_events_activate() {
	// Load post types so rewrite rules exist before flush.
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-carkeekevents-post-types.php';
	CarkeekEvents_Post_Types::register();
	flush_rewrite_rules();

	// Schedule daily cron.
	if ( ! wp_next_scheduled( 'carkeek_events_daily_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'carkeek_events_daily_cron' );
	}
}
register_activation_hook( __FILE__, 'carkeek_events_activate' );

/**
 * Plugin deactivation hook.
 *
 * Flushes rewrite rules and unschedules the daily cron.
 */
function carkeek_events_deactivate() {
	flush_rewrite_rules();

	$timestamp = wp_next_scheduled( 'carkeek_events_daily_cron' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'carkeek_events_daily_cron' );
	}
}
register_deactivation_hook( __FILE__, 'carkeek_events_deactivate' );

/**
 * Plugin uninstall hook.
 *
 * Deletes all plugin options.
 */
function carkeek_events_uninstall() {
	delete_option( 'carkeek_events_settings' );
}
register_uninstall_hook( __FILE__, 'carkeek_events_uninstall' );
