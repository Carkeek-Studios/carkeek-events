<?php
/**
 * Plugin Settings
 *
 * WP Settings API implementation. All settings stored under a single option
 * key (CARKEEKEVENTS_OPTION_NAME) as an array.
 *
 * Default values:
 *   content_expiry_days  => 365
 *   disable_wp_archive   => '1'
 *   archive_slug         => 'events'
 *   google_maps_api_key  => ''
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Settings
 */
class CarkeekEvents_Settings {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_action( 'admin_init', array( $instance, 'register_settings' ) );
		add_action( 'wp_ajax_carkeek_events_run_cron', array( $instance, 'ajax_run_cron' ) );
		add_action( 'wp_ajax_carkeek_events_flush_rewrites', array( $instance, 'ajax_flush_rewrites' ) );
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'carkeek_events_settings_group',
			CARKEEKEVENTS_OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		// Section 1: Event Expiry.
		add_settings_section(
			'carkeek_events_expiry_section',
			__( 'Event Expiry', 'carkeek-events' ),
			array( $this, 'expiry_section_description' ),
			'carkeek-events'
		);

		add_settings_field(
			'content_expiry_days',
			__( 'Content Expiry Period', 'carkeek-events' ),
			array( $this, 'content_expiry_days_callback' ),
			'carkeek-events',
			'carkeek_events_expiry_section'
		);

		// Section 2: Archive.
		add_settings_section(
			'carkeek_events_archive_section',
			__( 'Archive', 'carkeek-events' ),
			array( $this, 'archive_section_description' ),
			'carkeek-events'
		);

		add_settings_field(
			'disable_wp_archive',
			__( 'WordPress Archive', 'carkeek-events' ),
			array( $this, 'disable_wp_archive_callback' ),
			'carkeek-events',
			'carkeek_events_archive_section'
		);

		add_settings_field(
			'archive_slug',
			__( 'Archive Slug', 'carkeek-events' ),
			array( $this, 'archive_slug_callback' ),
			'carkeek-events',
			'carkeek_events_archive_section'
		);

		// Section 3: Display.
		add_settings_section(
			'carkeek_events_display_section',
			__( 'Display', 'carkeek-events' ),
			array( $this, 'display_section_description' ),
			'carkeek-events'
		);

		add_settings_field(
			'use_plugin_template',
			__( 'Single Event Template', 'carkeek-events' ),
			array( $this, 'use_plugin_template_callback' ),
			'carkeek-events',
			'carkeek_events_display_section'
		);

		add_settings_field(
			'date_format',
			__( 'Date Format', 'carkeek-events' ),
			array( $this, 'date_format_callback' ),
			'carkeek-events',
			'carkeek_events_display_section'
		);

		add_settings_field(
			'time_format',
			__( 'Time Format', 'carkeek-events' ),
			array( $this, 'time_format_callback' ),
			'carkeek-events',
			'carkeek_events_display_section'
		);

		add_settings_field(
			'location_display',
			__( 'Location Display', 'carkeek-events' ),
			array( $this, 'location_display_callback' ),
			'carkeek-events',
			'carkeek_events_display_section'
		);

		add_settings_field(
			'organizer_display',
			__( 'Organizer Display', 'carkeek-events' ),
			array( $this, 'organizer_display_callback' ),
			'carkeek-events',
			'carkeek_events_display_section'
		);

		// Section 3: Maps Integration.
		add_settings_section(
			'carkeek_events_maps_section',
			__( 'Maps Integration', 'carkeek-events' ),
			array( $this, 'maps_section_description' ),
			'carkeek-events'
		);

		add_settings_field(
			'google_maps_api_key',
			__( 'Google Maps API Key', 'carkeek-events' ),
			array( $this, 'google_maps_api_key_callback' ),
			'carkeek-events',
			'carkeek_events_maps_section'
		);
	}

	/**
	 * Expiry section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function expiry_section_description() {
		echo '<p class="description">' . esc_html__( 'Control when past events are automatically trashed. Events with no end date are never expired.', 'carkeek-events' ) . '</p>';
	}

	/**
	 * Display section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_section_description() {
		echo '<p class="description">' . esc_html__( 'Control how event dates, locations, and organizers are rendered on the front end.', 'carkeek-events' ) . '</p>';
	}

	/**
	 * Single event template field callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function use_plugin_template_callback() {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$value    = $settings['use_plugin_template'] ?? '1';
		?>
		<fieldset>
			<label>
				<input type="radio"
					name="<?php echo esc_attr( CARKEEKEVENTS_OPTION_NAME ); ?>[use_plugin_template]"
					value="1"
					<?php checked( $value, '1' ); ?> />
				<?php esc_html_e( 'Use plugin template — loads the plugin\'s single event template (theme can override at carkeek-events/single-carkeek_event.php)', 'carkeek-events' ); ?>
			</label>
			<br>
			<label>
				<input type="radio"
					name="<?php echo esc_attr( CARKEEKEVENTS_OPTION_NAME ); ?>[use_plugin_template]"
					value="0"
					<?php checked( $value, '0' ); ?> />
				<?php esc_html_e( 'Use theme template — WordPress falls back to the theme\'s single.php or singular.php', 'carkeek-events' ); ?>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Date format field callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function date_format_callback() {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$value    = $settings['date_format'] ?? '';
		$wp_default = get_option( 'date_format' );
		?>
		<input type="text"
			name="<?php echo esc_attr( CARKEEKEVENTS_OPTION_NAME ); ?>[date_format]"
			id="carkeek_date_format"
			value="<?php echo esc_attr( $value ); ?>"
			size="20"
			placeholder="<?php echo esc_attr( $wp_default ); ?>" />
		<p class="description">
			<?php
			printf(
				/* translators: 1: current WP date format, 2: example output */
				esc_html__( 'PHP date format string. Leave blank to use the site default (%1$s → %2$s).', 'carkeek-events' ),
				'<code>' . esc_html( $wp_default ) . '</code>',
				'<code>' . esc_html( date_i18n( $wp_default ) ) . '</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Time format field callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function time_format_callback() {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$value    = $settings['time_format'] ?? '';
		$wp_default = get_option( 'time_format' );
		?>
		<input type="text"
			name="<?php echo esc_attr( CARKEEKEVENTS_OPTION_NAME ); ?>[time_format]"
			id="carkeek_time_format"
			value="<?php echo esc_attr( $value ); ?>"
			size="20"
			placeholder="<?php echo esc_attr( $wp_default ); ?>" />
		<p class="description">
			<?php
			printf(
				/* translators: 1: current WP time format, 2: example output */
				esc_html__( 'PHP date format string. Leave blank to use the site default (%1$s → %2$s).', 'carkeek-events' ),
				'<code>' . esc_html( $wp_default ) . '</code>',
				'<code>' . esc_html( date_i18n( $wp_default ) ) . '</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Location display field callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function location_display_callback() {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$value    = $settings['location_display'] ?? 'link';
		$options  = array(
			'link'               => __( 'Link — show location name linked to its page', 'carkeek-events' ),
			'address'            => __( 'Address — show formatted address (name, street, city, state)', 'carkeek-events' ),
			'address_directions' => __( 'Address + Directions — address with a "Get Directions" link to Google Maps', 'carkeek-events' ),
		);
		?>
		<fieldset>
			<?php foreach ( $options as $key => $label ) : ?>
				<label style="display:block;margin-bottom:4px;">
					<input type="radio"
						name="<?php echo esc_attr( CARKEEKEVENTS_OPTION_NAME ); ?>[location_display]"
						value="<?php echo esc_attr( $key ); ?>"
						<?php checked( $value, $key ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Organizer display field callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function organizer_display_callback() {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$value    = $settings['organizer_display'] ?? 'link';
		$options  = array(
			'link' => __( 'Link — show organizer name linked to their page', 'carkeek-events' ),
			'info' => __( 'Info — show organizer name, email, phone, and website inline', 'carkeek-events' ),
		);
		?>
		<fieldset>
			<?php foreach ( $options as $key => $label ) : ?>
				<label style="display:block;margin-bottom:4px;">
					<input type="radio"
						name="<?php echo esc_attr( CARKEEKEVENTS_OPTION_NAME ); ?>[organizer_display]"
						value="<?php echo esc_attr( $key ); ?>"
						<?php checked( $value, $key ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Maps section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maps_section_description() {
		echo '<p class="description">' . esc_html__( 'Optionally provide a Google Maps API key to enable the geocoding button on location records. The key is stored server-side and never exposed to the browser.', 'carkeek-events' ) . '</p>';
	}

	/**
	 * Content expiry period field callback.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function content_expiry_days_callback() {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$value    = isset( $settings['content_expiry_days'] ) ? absint( $settings['content_expiry_days'] ) : 365;
		?>
		<input type="number"
			name="<?php echo esc_attr( CARKEEKEVENTS_OPTION_NAME ); ?>[content_expiry_days]"
			id="carkeek_content_expiry_days"
			value="<?php echo esc_attr( $value ); ?>"
			min="1"
			step="1"
			style="width:80px;" />
		<span><?php esc_html_e( 'days', 'carkeek-events' ); ?></span>
		<p class="description"><?php esc_html_e( 'Events are set to private (returning 404 to visitors) this many days after their end date. Default is 365 days — useful for annual events you want to keep for one year. Minimum 1. Private posts remain in the database and can be restored by an admin.', 'carkeek-events' ); ?></p>
		<?php
	}

	/**
	 * Archive section description.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function archive_section_description() {
		echo '<p class="description">' . esc_html__( 'Control the WordPress built-in event archive. Disable it when you want to create your own page with the events slug and use the Carkeek Events Archive block.', 'carkeek-events' ) . '</p>';
	}

	/**
	 * Disable WordPress archive field callback.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function disable_wp_archive_callback() {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$value    = $settings['disable_wp_archive'] ?? '1';
		?>
		<label>
			<input type="checkbox"
				name="<?php echo esc_attr( CARKEEKEVENTS_OPTION_NAME ); ?>[disable_wp_archive]"
				value="1"
				<?php checked( $value, '1' ); ?> />
			<?php esc_html_e( 'Disable the built-in WordPress archive. Recommended when using the Carkeek Events Archive block on a custom page.', 'carkeek-events' ); ?>
		</label>
		<?php
	}

	/**
	 * Archive slug field callback.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function archive_slug_callback() {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$value    = $settings['archive_slug'] ?? 'events';
		?>
		<input type="text"
			name="<?php echo esc_attr( CARKEEKEVENTS_OPTION_NAME ); ?>[archive_slug]"
			id="carkeek_archive_slug"
			value="<?php echo esc_attr( $value ); ?>"
			size="20"
			placeholder="events" />
		<p class="description"><?php esc_html_e( 'Slug for the WordPress event archive (used only when the WordPress archive is enabled). Default: events. Rewrite rules flush automatically when saved.', 'carkeek-events' ); ?></p>
		<?php
	}

	/**
	 * Google Maps API key field callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function google_maps_api_key_callback() {
		$settings = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$value    = $settings['google_maps_api_key'] ?? '';
		?>
		<input type="password"
			name="<?php echo esc_attr( CARKEEKEVENTS_OPTION_NAME ); ?>[google_maps_api_key]"
			id="carkeek_google_maps_api_key"
			value="<?php echo esc_attr( $value ); ?>"
			size="50"
			autocomplete="off" />
		<button type="button" class="button carkeek-toggle-password"
			data-target="carkeek_google_maps_api_key"
			aria-label="<?php esc_attr_e( 'Show/hide API key', 'carkeek-events' ); ?>">
			<?php esc_html_e( 'Show', 'carkeek-events' ); ?>
		</button>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to Google Maps API docs */
				esc_html__( 'Requires the Geocoding API to be enabled. %s', 'carkeek-events' ),
				'<a href="https://developers.google.com/maps/documentation/geocoding/overview" target="_blank" rel="noopener">' . esc_html__( 'Learn more', 'carkeek-events' ) . '</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @since 1.0.0
	 * @param array $input Raw input array.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Expiry.
		$expiry_days = absint( $input['content_expiry_days'] ?? 365 );
		$sanitized['content_expiry_days'] = max( 1, $expiry_days );

		// Archive.
		$sanitized['disable_wp_archive'] = ! empty( $input['disable_wp_archive'] ) ? '1' : '0';
		$sanitized['archive_slug']       = sanitize_title( $input['archive_slug'] ?? 'events' ) ?: 'events';

		// Display.
		$sanitized['use_plugin_template'] = ( '0' === ( $input['use_plugin_template'] ?? '1' ) ) ? '0' : '1';
		$sanitized['date_format']         = sanitize_text_field( $input['date_format'] ?? '' );
		$sanitized['time_format']         = sanitize_text_field( $input['time_format'] ?? '' );

		$allowed_location = array( 'link', 'address', 'address_directions' );
		$sanitized['location_display'] = in_array( $input['location_display'] ?? '', $allowed_location, true )
			? $input['location_display']
			: 'link';

		$allowed_organizer = array( 'link', 'info' );
		$sanitized['organizer_display'] = in_array( $input['organizer_display'] ?? '', $allowed_organizer, true )
			? $input['organizer_display']
			: 'link';

		// Maps.
		$sanitized['google_maps_api_key'] = sanitize_text_field( $input['google_maps_api_key'] ?? '' );

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Events Settings', 'carkeek-events' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'carkeek_events_settings_group' );
				do_settings_sections( 'carkeek-events' );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Tools', 'carkeek-events' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Use these tools for manual maintenance.', 'carkeek-events' ); ?></p>

			<p>
				<button type="button" id="carkeek-run-cron" class="button"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'carkeek_events_run_cron' ) ); ?>">
					<?php esc_html_e( 'Run Expiry Check Now', 'carkeek-events' ); ?>
				</button>
				<span id="carkeek-cron-status" style="margin-left:10px;"></span>
			</p>
			<p>
				<button type="button" id="carkeek-flush-rewrites" class="button"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'carkeek_events_flush_rewrites' ) ); ?>">
					<?php esc_html_e( 'Flush Rewrite Rules', 'carkeek-events' ); ?>
				</button>
				<span id="carkeek-flush-status" style="margin-left:10px;"></span>
			</p>
		</div>
		<?php
	}

	/**
	 * AJAX: manually trigger the expiry cron.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_run_cron() {
		check_ajax_referer( 'carkeek_events_run_cron', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		do_action( 'carkeek_events_daily_cron' );
		wp_send_json_success( __( 'Expiry check complete.', 'carkeek-events' ) );
	}

	/**
	 * AJAX: flush rewrite rules.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_flush_rewrites() {
		check_ajax_referer( 'carkeek_events_flush_rewrites', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}
		flush_rewrite_rules();
		wp_send_json_success( __( 'Rewrite rules flushed.', 'carkeek-events' ) );
	}
}

CarkeekEvents_Settings::register();
