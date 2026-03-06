<?php
/**
 * Meta Boxes
 *
 * Registers and renders classic meta boxes for event, location, and organizer
 * CPTs. All data is stored in standard WordPress post meta.
 *
 * @package carkeek-events
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CarkeekEvents_Meta_Boxes
 */
class CarkeekEvents_Meta_Boxes {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register() {
		$instance = new self();
		add_action( 'add_meta_boxes', array( $instance, 'add_meta_boxes' ) );
		add_action( 'save_post_carkeek_event', array( $instance, 'save_event_meta' ), 10, 2 );
		add_action( 'save_post_carkeek_location', array( $instance, 'save_location_meta' ), 10, 2 );
		add_action( 'save_post_carkeek_organizer', array( $instance, 'save_organizer_meta' ), 10, 2 );
		add_action( 'wp_ajax_carkeek_events_search_posts', array( $instance, 'ajax_search_posts' ) );
	}

	/**
	 * Register meta boxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'carkeek_event_details',
			__( 'Event Details', 'carkeek-events' ),
			array( $this, 'render_event_meta_box' ),
			'carkeek_event',
			'normal',
			'high'
		);

		add_meta_box(
			'carkeek_location_details',
			__( 'Location Details', 'carkeek-events' ),
			array( $this, 'render_location_meta_box' ),
			'carkeek_location',
			'normal',
			'high'
		);

		add_meta_box(
			'carkeek_organizer_details',
			__( 'Organizer Details', 'carkeek-events' ),
			array( $this, 'render_organizer_meta_box' ),
			'carkeek_organizer',
			'normal',
			'high'
		);
	}

	// -----------------------------------------------------------------------
	// Event Meta Box
	// -----------------------------------------------------------------------

	/**
	 * Render the Event Details meta box.
	 *
	 * @since 1.0.0
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_event_meta_box( $post ) {
		wp_nonce_field( 'carkeek_event_meta_save', 'carkeek_event_meta_nonce' );

		$start_iso  = get_post_meta( $post->ID, '_carkeek_event_start', true );
		$start_date = $start_iso ? substr( $start_iso, 0, 10 ) : '';
		$start_time = ( $start_iso && strlen( $start_iso ) > 10 && substr( $start_iso, 11 ) !== '00:00:00' )
			? substr( $start_iso, 11, 5 ) : '';

		$end_iso  = get_post_meta( $post->ID, '_carkeek_event_end', true );
		$end_date = $end_iso ? substr( $end_iso, 0, 10 ) : '';
		$end_time = ( $end_iso && strlen( $end_iso ) > 10 && substr( $end_iso, 11 ) !== '00:00:00' )
			? substr( $end_iso, 11, 5 ) : '';

		$hidden = get_post_meta( $post->ID, '_carkeek_event_hidden', true );
		$location_id      = (int) get_post_meta( $post->ID, '_carkeek_event_location_id', true );
		$location_text    = get_post_meta( $post->ID, '_carkeek_event_location_text', true );
		$organizer_id     = (int) get_post_meta( $post->ID, '_carkeek_event_organizer_id', true );
		$organizer_text   = get_post_meta( $post->ID, '_carkeek_event_organizer_text', true );
		$event_website    = get_post_meta( $post->ID, '_carkeek_event_website', true );
		$event_btn_label  = get_post_meta( $post->ID, '_carkeek_event_button_label', true );

		// Resolve location/organizer titles for display.
		$location_title  = '';
		$organizer_title = '';

		if ( $location_id ) {
			$location_post = get_post( $location_id );
			if ( $location_post && 'publish' === $location_post->post_status ) {
				$location_title = $location_post->post_title;
			} else {
				// Post was deleted or unpublished — clear the ID.
				$location_id = 0;
			}
		}

		if ( $organizer_id ) {
			$organizer_post = get_post( $organizer_id );
			if ( $organizer_post && 'publish' === $organizer_post->post_status ) {
				$organizer_title = $organizer_post->post_title;
			} else {
				$organizer_id = 0;
			}
		}
		?>
		<div class="carkeek-events-meta-box">

			<div class="carkeek-events-row carkeek-events-dates-row">
				<div class="carkeek-events-col">
					<label for="carkeek_event_start_date"><?php esc_html_e( 'Start Date', 'carkeek-events' ); ?></label>
					<input type="date" id="carkeek_event_start_date" name="carkeek_event_start_date"
						value="<?php echo esc_attr( $start_date ); ?>" />
				</div>
				<div class="carkeek-events-col">
					<label for="carkeek_event_start_time"><?php esc_html_e( 'Start Time', 'carkeek-events' ); ?></label>
					<input type="time" id="carkeek_event_start_time" name="carkeek_event_start_time"
						value="<?php echo esc_attr( $start_time ); ?>" />
				</div>
				<div class="carkeek-events-col">
					<label for="carkeek_event_end_date">
						<?php esc_html_e( 'End Date', 'carkeek-events' ); ?>
					</label>
					<input type="date" id="carkeek_event_end_date" name="carkeek_event_end_date"
						value="<?php echo esc_attr( $end_date ); ?>" />
				</div>
				<div class="carkeek-events-col">
					<label for="carkeek_event_end_time"><?php esc_html_e( 'End Time', 'carkeek-events' ); ?></label>
					<input type="time" id="carkeek_event_end_time" name="carkeek_event_end_time"
						value="<?php echo esc_attr( $end_time ); ?>" />
				</div>
			</div>

			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label>
						<input type="checkbox" name="carkeek_event_hidden" value="1"
							<?php checked( $hidden, '1' ); ?> />
						<?php esc_html_e( 'Hide from event listings (event remains accessible via direct URL)', 'carkeek-events' ); ?>
					</label>
				</div>
			</div>

			<?php do_action( 'carkeek_events_meta_box_after_dates', $post ); ?>

			<hr />

			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label><?php esc_html_e( 'Location', 'carkeek-events' ); ?></label>
					<?php $this->render_relationship_field( 'location', $location_id, $location_title, $location_text ); ?>
				</div>
			</div>

			<?php do_action( 'carkeek_events_meta_box_after_location', $post ); ?>

			<hr />

			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label><?php esc_html_e( 'Organizer', 'carkeek-events' ); ?></label>
					<?php $this->render_relationship_field( 'organizer', $organizer_id, $organizer_title, $organizer_text ); ?>
				</div>
			</div>

			<?php do_action( 'carkeek_events_meta_box_after_organizer', $post ); ?>

			<hr />

			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label for="carkeek_event_website"><?php esc_html_e( 'Event Website / Registration URL', 'carkeek-events' ); ?></label>
					<input type="url" id="carkeek_event_website" name="carkeek_event_website"
						value="<?php echo esc_attr( $event_website ); ?>" class="widefat"
						placeholder="https://" />
					<p class="description"><?php esc_html_e( 'When set, a button linking to this URL will appear in event templates.', 'carkeek-events' ); ?></p>
				</div>
			</div>

			<div class="carkeek-events-row">
				<div class="carkeek-events-col">
					<label for="carkeek_event_button_label"><?php esc_html_e( 'Button Label', 'carkeek-events' ); ?></label>
					<input type="text" id="carkeek_event_button_label" name="carkeek_event_button_label"
						value="<?php echo esc_attr( $event_btn_label ); ?>"
						placeholder="<?php esc_attr_e( 'Sign Up', 'carkeek-events' ); ?>" />
					<p class="description"><?php esc_html_e( 'Defaults to "Sign Up" if left blank.', 'carkeek-events' ); ?></p>
				</div>
			</div>

			<?php do_action( 'carkeek_events_meta_box_after_link', $post ); ?>

		</div>
		<?php
	}

	/**
	 * Render a location/organizer selector with inline create-new option.
	 *
	 * @since 1.0.0
	 * @param string $type     'location' or 'organizer'.
	 * @param int    $cpt_id   Currently linked post ID (0 if none).
	 * @param string $cpt_name Currently linked post title.
	 * @param string $text     Unused — kept for call-site compatibility.
	 * @return void
	 */
	private function render_relationship_field( $type, $cpt_id, $cpt_name, $text ) {
		$post_type = 'location' === $type ? 'carkeek_location' : 'carkeek_organizer';
		?>
		<div class="carkeek-events-relationship" data-type="<?php echo esc_attr( $type ); ?>">

			<input type="hidden"
				name="carkeek_event_<?php echo esc_attr( $type ); ?>_mode"
				class="carkeek-events-relationship__mode"
				value="cpt" />

			<div class="carkeek-events-relationship__tabs">
				<button type="button" class="carkeek-events-tab is-active" data-tab="cpt">
					<?php esc_html_e( 'Select existing', 'carkeek-events' ); ?>
				</button>
				<button type="button" class="carkeek-events-tab" data-tab="new">
					<?php esc_html_e( 'Create new', 'carkeek-events' ); ?>
				</button>
			</div>

			<div class="carkeek-events-relationship__panel carkeek-events-relationship__panel--cpt is-active">
				<input type="hidden"
					name="carkeek_event_<?php echo esc_attr( $type ); ?>_id"
					id="carkeek_event_<?php echo esc_attr( $type ); ?>_id"
					class="carkeek-events-cpt-id"
					value="<?php echo esc_attr( $cpt_id ); ?>" />
				<div class="carkeek-events-search-wrap">
					<input type="text"
						class="carkeek-events-search-input"
						data-post-type="<?php echo esc_attr( $post_type ); ?>"
						placeholder="<?php echo esc_attr( sprintf( __( 'Search %s…', 'carkeek-events' ), ucfirst( $type ) ) ); ?>"
						value="<?php echo esc_attr( $cpt_name ); ?>"
						autocomplete="off" />
					<ul class="carkeek-events-search-results"></ul>
				</div>
				<?php if ( $cpt_id ) : ?>
					<p class="carkeek-events-selected-name">
						<?php echo esc_html( $cpt_name ); ?>
						<button type="button" class="carkeek-events-clear-cpt" aria-label="<?php esc_attr_e( 'Clear selection', 'carkeek-events' ); ?>">&#x2715;</button>
					</p>
				<?php endif; ?>
			</div>

			<div class="carkeek-events-relationship__panel carkeek-events-relationship__panel--new">
				<?php $this->render_new_cpt_fields( $type ); ?>
			</div>

		</div>
		<?php
	}

	/**
	 * Render the inline create-new fields for a location or organizer.
	 *
	 * @since 1.0.0
	 * @param string $type 'location' or 'organizer'.
	 * @return void
	 */
	private function render_new_cpt_fields( $type ) {
		?>
		<div class="carkeek-events-new-cpt-fields">

			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label for="carkeek_event_<?php echo esc_attr( $type ); ?>_new_name">
						<?php esc_html_e( 'Name', 'carkeek-events' ); ?> <span aria-hidden="true">*</span>
					</label>
					<input type="text"
						id="carkeek_event_<?php echo esc_attr( $type ); ?>_new_name"
						name="carkeek_event_<?php echo esc_attr( $type ); ?>_new_name"
						class="widefat" />
				</div>
			</div>

			<?php if ( 'location' === $type ) : ?>

				<div class="carkeek-events-row">
					<div class="carkeek-events-col carkeek-events-col--full">
						<label for="carkeek_event_location_new_address"><?php esc_html_e( 'Street Address', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_new_address" name="carkeek_event_location_new_address" class="widefat" />
					</div>
				</div>

				<div class="carkeek-events-row">
					<div class="carkeek-events-col">
						<label for="carkeek_event_location_new_city"><?php esc_html_e( 'City', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_new_city" name="carkeek_event_location_new_city" />
					</div>
					<div class="carkeek-events-col">
						<label for="carkeek_event_location_new_state"><?php esc_html_e( 'State / Province', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_new_state" name="carkeek_event_location_new_state" />
					</div>
					<div class="carkeek-events-col">
						<label for="carkeek_event_location_new_zip"><?php esc_html_e( 'Zip / Postal Code', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_new_zip" name="carkeek_event_location_new_zip" />
					</div>
					<div class="carkeek-events-col">
						<label for="carkeek_event_location_new_country"><?php esc_html_e( 'Country', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_new_country" name="carkeek_event_location_new_country" />
					</div>
				</div>

				<div class="carkeek-events-row">
					<div class="carkeek-events-col carkeek-events-col--full">
						<label for="carkeek_event_location_new_website"><?php esc_html_e( 'Website', 'carkeek-events' ); ?></label>
						<input type="url" id="carkeek_event_location_new_website" name="carkeek_event_location_new_website" class="widefat" />
					</div>
				</div>

			<?php else : ?>

				<div class="carkeek-events-row">
					<div class="carkeek-events-col">
						<label for="carkeek_event_organizer_new_email"><?php esc_html_e( 'Email', 'carkeek-events' ); ?></label>
						<input type="email" id="carkeek_event_organizer_new_email" name="carkeek_event_organizer_new_email" />
					</div>
					<div class="carkeek-events-col">
						<label for="carkeek_event_organizer_new_phone"><?php esc_html_e( 'Phone', 'carkeek-events' ); ?></label>
						<input type="tel" id="carkeek_event_organizer_new_phone" name="carkeek_event_organizer_new_phone" />
					</div>
				</div>

				<div class="carkeek-events-row">
					<div class="carkeek-events-col carkeek-events-col--full">
						<label for="carkeek_event_organizer_new_website"><?php esc_html_e( 'Website', 'carkeek-events' ); ?></label>
						<input type="url" id="carkeek_event_organizer_new_website" name="carkeek_event_organizer_new_website" class="widefat" />
					</div>
				</div>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Save event meta.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_event_meta( $post_id, $post ) {
		if ( ! isset( $_POST['carkeek_event_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['carkeek_event_meta_nonce'] ), 'carkeek_event_meta_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Combine separate date and time inputs into a single ISO 8601 datetime string.
		$start_date = sanitize_text_field( wp_unslash( $_POST['carkeek_event_start_date'] ?? '' ) );
		$start_time = sanitize_text_field( wp_unslash( $_POST['carkeek_event_start_time'] ?? '' ) );
		if ( $start_date ) {
			update_post_meta( $post_id, '_carkeek_event_start', $start_date . 'T' . ( $start_time ? $start_time . ':00' : '00:00:00' ) );
		} else {
			delete_post_meta( $post_id, '_carkeek_event_start' );
		}

		$end_date = sanitize_text_field( wp_unslash( $_POST['carkeek_event_end_date'] ?? '' ) );
		$end_time = sanitize_text_field( wp_unslash( $_POST['carkeek_event_end_time'] ?? '' ) );
		// Default end date to start date when left blank.
		if ( ! $end_date && $start_date ) {
			$end_date = $start_date;
		}
		if ( $end_date ) {
			update_post_meta( $post_id, '_carkeek_event_end', $end_date . 'T' . ( $end_time ? $end_time . ':00' : '00:00:00' ) );
		} else {
			delete_post_meta( $post_id, '_carkeek_event_end' );
		}

		// Hidden checkbox — manual hide from archive listings.
		update_post_meta( $post_id, '_carkeek_event_hidden', isset( $_POST['carkeek_event_hidden'] ) ? '1' : '0' );

		// Location.
		$location_mode = sanitize_key( wp_unslash( $_POST['carkeek_event_location_mode'] ?? 'cpt' ) );
		if ( 'new' === $location_mode ) {
			$this->create_and_link_cpt( $post_id, 'location' );
		} else {
			$location_id = absint( $_POST['carkeek_event_location_id'] ?? 0 );
			if ( $location_id ) {
				$loc_post = get_post( $location_id );
				if ( ! $loc_post || 'carkeek_location' !== $loc_post->post_type ) {
					$location_id = 0;
				}
			}
			update_post_meta( $post_id, '_carkeek_event_location_id', $location_id );
		}

		// Organizer.
		$organizer_mode = sanitize_key( wp_unslash( $_POST['carkeek_event_organizer_mode'] ?? 'cpt' ) );
		if ( 'new' === $organizer_mode ) {
			$this->create_and_link_cpt( $post_id, 'organizer' );
		} else {
			$organizer_id = absint( $_POST['carkeek_event_organizer_id'] ?? 0 );
			if ( $organizer_id ) {
				$org_post = get_post( $organizer_id );
				if ( ! $org_post || 'carkeek_organizer' !== $org_post->post_type ) {
					$organizer_id = 0;
				}
			}
			update_post_meta( $post_id, '_carkeek_event_organizer_id', $organizer_id );
		}

		// Event website URL.
		if ( isset( $_POST['carkeek_event_website'] ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['carkeek_event_website'] ) );
			if ( $url ) {
				update_post_meta( $post_id, '_carkeek_event_website', $url );
			} else {
				delete_post_meta( $post_id, '_carkeek_event_website' );
			}
		}

		// Button label.
		if ( isset( $_POST['carkeek_event_button_label'] ) ) {
			$label = sanitize_text_field( wp_unslash( $_POST['carkeek_event_button_label'] ) );
			if ( $label ) {
				update_post_meta( $post_id, '_carkeek_event_button_label', $label );
			} else {
				delete_post_meta( $post_id, '_carkeek_event_button_label' );
			}
		}
	}

	/**
	 * Create a new location or organizer post from inline form data and link it to the event.
	 *
	 * @since 1.0.0
	 * @param int    $event_id The event post ID.
	 * @param string $type     'location' or 'organizer'.
	 * @return void
	 */
	private function create_and_link_cpt( $event_id, $type ) {
		$name = sanitize_text_field( wp_unslash( $_POST[ "carkeek_event_{$type}_new_name" ] ?? '' ) );
		if ( ! $name ) {
			return;
		}

		$new_id = wp_insert_post( array(
			'post_type'   => "carkeek_{$type}",
			'post_title'  => $name,
			'post_status' => 'publish',
		) );

		if ( ! $new_id || is_wp_error( $new_id ) ) {
			return;
		}

		if ( 'location' === $type ) {
			$text_fields = array(
				'_carkeek_location_address' => 'carkeek_event_location_new_address',
				'_carkeek_location_city'    => 'carkeek_event_location_new_city',
				'_carkeek_location_state'   => 'carkeek_event_location_new_state',
				'_carkeek_location_zip'     => 'carkeek_event_location_new_zip',
				'_carkeek_location_country' => 'carkeek_event_location_new_country',
			);
			foreach ( $text_fields as $meta_key => $post_key ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ?? '' ) );
				if ( $val ) {
					update_post_meta( $new_id, $meta_key, $val );
				}
			}
			$website = esc_url_raw( wp_unslash( $_POST['carkeek_event_location_new_website'] ?? '' ) );
			if ( $website ) {
				update_post_meta( $new_id, '_carkeek_location_website', $website );
			}
		} else {
			$email   = sanitize_email( wp_unslash( $_POST['carkeek_event_organizer_new_email'] ?? '' ) );
			$phone   = sanitize_text_field( wp_unslash( $_POST['carkeek_event_organizer_new_phone'] ?? '' ) );
			$website = esc_url_raw( wp_unslash( $_POST['carkeek_event_organizer_new_website'] ?? '' ) );
			if ( $email )   update_post_meta( $new_id, '_carkeek_organizer_email', $email );
			if ( $phone )   update_post_meta( $new_id, '_carkeek_organizer_phone', $phone );
			if ( $website ) update_post_meta( $new_id, '_carkeek_organizer_website', $website );
		}

		update_post_meta( $event_id, "_carkeek_event_{$type}_id", $new_id );
	}

	// -----------------------------------------------------------------------
	// Location Meta Box
	// -----------------------------------------------------------------------

	/**
	 * Render the Location Details meta box.
	 *
	 * @since 1.0.0
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_location_meta_box( $post ) {
		wp_nonce_field( 'carkeek_location_meta_save', 'carkeek_location_meta_nonce' );

		$address = get_post_meta( $post->ID, '_carkeek_location_address', true );
		$city    = get_post_meta( $post->ID, '_carkeek_location_city', true );
		$state   = get_post_meta( $post->ID, '_carkeek_location_state', true );
		$zip     = get_post_meta( $post->ID, '_carkeek_location_zip', true );
		$country = get_post_meta( $post->ID, '_carkeek_location_country', true );
		$website = get_post_meta( $post->ID, '_carkeek_location_website', true );
		$lat     = get_post_meta( $post->ID, '_carkeek_location_lat', true );
		$lng     = get_post_meta( $post->ID, '_carkeek_location_lng', true );

		$settings    = get_option( CARKEEKEVENTS_OPTION_NAME, array() );
		$has_api_key = ! empty( $settings['google_maps_api_key'] );
		?>
		<div class="carkeek-events-meta-box">

			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label for="carkeek_location_address"><?php esc_html_e( 'Street Address', 'carkeek-events' ); ?></label>
					<input type="text" id="carkeek_location_address" name="carkeek_location_address"
						value="<?php echo esc_attr( $address ); ?>" class="widefat" />
				</div>
			</div>

			<div class="carkeek-events-row">
				<div class="carkeek-events-col">
					<label for="carkeek_location_city"><?php esc_html_e( 'City', 'carkeek-events' ); ?></label>
					<input type="text" id="carkeek_location_city" name="carkeek_location_city"
						value="<?php echo esc_attr( $city ); ?>" />
				</div>
				<div class="carkeek-events-col">
					<label for="carkeek_location_state"><?php esc_html_e( 'State / Province', 'carkeek-events' ); ?></label>
					<input type="text" id="carkeek_location_state" name="carkeek_location_state"
						value="<?php echo esc_attr( $state ); ?>" />
				</div>
				<div class="carkeek-events-col">
					<label for="carkeek_location_zip"><?php esc_html_e( 'Zip / Postal Code', 'carkeek-events' ); ?></label>
					<input type="text" id="carkeek_location_zip" name="carkeek_location_zip"
						value="<?php echo esc_attr( $zip ); ?>" />
				</div>
				<div class="carkeek-events-col">
					<label for="carkeek_location_country"><?php esc_html_e( 'Country', 'carkeek-events' ); ?></label>
					<input type="text" id="carkeek_location_country" name="carkeek_location_country"
						value="<?php echo esc_attr( $country ); ?>" />
				</div>
			</div>

			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label for="carkeek_location_website"><?php esc_html_e( 'Website', 'carkeek-events' ); ?></label>
					<input type="url" id="carkeek_location_website" name="carkeek_location_website"
						value="<?php echo esc_attr( $website ); ?>" class="widefat" />
				</div>
			</div>

			<hr />

			<div class="carkeek-events-row carkeek-events-row--geocode">
				<div class="carkeek-events-col">
					<label for="carkeek_location_lat"><?php esc_html_e( 'Latitude', 'carkeek-events' ); ?></label>
					<input type="text" id="carkeek_location_lat" name="carkeek_location_lat"
						value="<?php echo esc_attr( $lat ); ?>"
						placeholder="e.g. 47.6062" />
				</div>
				<div class="carkeek-events-col">
					<label for="carkeek_location_lng"><?php esc_html_e( 'Longitude', 'carkeek-events' ); ?></label>
					<input type="text" id="carkeek_location_lng" name="carkeek_location_lng"
						value="<?php echo esc_attr( $lng ); ?>"
						placeholder="e.g. -122.3321" />
				</div>
				<?php if ( $has_api_key ) : ?>
				<div class="carkeek-events-col carkeek-events-col--geocode-btn">
					<label>&nbsp;</label>
					<button type="button" id="carkeek-geocode-btn" class="button"
						data-post-id="<?php echo esc_attr( $post->ID ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'carkeek_events_geocode' ) ); ?>">
						<?php esc_html_e( 'Geocode Address', 'carkeek-events' ); ?>
					</button>
				</div>
				<?php else : ?>
				<div class="carkeek-events-col carkeek-events-col--geocode-btn">
					<label>&nbsp;</label>
					<button type="button" class="button" disabled
						title="<?php esc_attr_e( 'Add a Google Maps API key in Events > Settings to enable geocoding.', 'carkeek-events' ); ?>">
						<?php esc_html_e( 'Geocode Address', 'carkeek-events' ); ?>
					</button>
					<p class="description">
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=carkeek_event&page=carkeek-events' ) ); ?>">
							<?php esc_html_e( 'Configure API key', 'carkeek-events' ); ?>
						</a>
					</p>
				</div>
				<?php endif; ?>
			</div>
			<div id="carkeek-geocode-status" class="carkeek-events-geocode-status" style="display:none;"></div>

		</div>
		<?php
	}

	/**
	 * Save location meta.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_location_meta( $post_id, $post ) {
		if ( ! isset( $_POST['carkeek_location_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['carkeek_location_meta_nonce'] ), 'carkeek_location_meta_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$text_fields = array(
			'_carkeek_location_address' => 'carkeek_location_address',
			'_carkeek_location_city'    => 'carkeek_location_city',
			'_carkeek_location_state'   => 'carkeek_location_state',
			'_carkeek_location_zip'     => 'carkeek_location_zip',
			'_carkeek_location_country' => 'carkeek_location_country',
			'_carkeek_location_lat'     => 'carkeek_location_lat',
			'_carkeek_location_lng'     => 'carkeek_location_lng',
		);

		foreach ( $text_fields as $meta_key => $post_key ) {
			if ( isset( $_POST[ $post_key ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) );
			}
		}

		if ( isset( $_POST['carkeek_location_website'] ) ) {
			update_post_meta( $post_id, '_carkeek_location_website', esc_url_raw( wp_unslash( $_POST['carkeek_location_website'] ) ) );
		}
	}

	// -----------------------------------------------------------------------
	// Organizer Meta Box
	// -----------------------------------------------------------------------

	/**
	 * Render the Organizer Details meta box.
	 *
	 * @since 1.0.0
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_organizer_meta_box( $post ) {
		wp_nonce_field( 'carkeek_organizer_meta_save', 'carkeek_organizer_meta_nonce' );

		$email   = get_post_meta( $post->ID, '_carkeek_organizer_email', true );
		$phone   = get_post_meta( $post->ID, '_carkeek_organizer_phone', true );
		$website = get_post_meta( $post->ID, '_carkeek_organizer_website', true );
		?>
		<div class="carkeek-events-meta-box">
			<div class="carkeek-events-row">
				<div class="carkeek-events-col">
					<label for="carkeek_organizer_email"><?php esc_html_e( 'Email', 'carkeek-events' ); ?></label>
					<input type="email" id="carkeek_organizer_email" name="carkeek_organizer_email"
						value="<?php echo esc_attr( $email ); ?>" />
				</div>
				<div class="carkeek-events-col">
					<label for="carkeek_organizer_phone"><?php esc_html_e( 'Phone', 'carkeek-events' ); ?></label>
					<input type="tel" id="carkeek_organizer_phone" name="carkeek_organizer_phone"
						value="<?php echo esc_attr( $phone ); ?>" />
				</div>
			</div>
			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label for="carkeek_organizer_website"><?php esc_html_e( 'Website', 'carkeek-events' ); ?></label>
					<input type="url" id="carkeek_organizer_website" name="carkeek_organizer_website"
						value="<?php echo esc_attr( $website ); ?>" class="widefat" />
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save organizer meta.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_organizer_meta( $post_id, $post ) {
		if ( ! isset( $_POST['carkeek_organizer_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['carkeek_organizer_meta_nonce'] ), 'carkeek_organizer_meta_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( isset( $_POST['carkeek_organizer_email'] ) ) {
			update_post_meta( $post_id, '_carkeek_organizer_email', sanitize_email( wp_unslash( $_POST['carkeek_organizer_email'] ) ) );
		}
		if ( isset( $_POST['carkeek_organizer_phone'] ) ) {
			update_post_meta( $post_id, '_carkeek_organizer_phone', sanitize_text_field( wp_unslash( $_POST['carkeek_organizer_phone'] ) ) );
		}
		if ( isset( $_POST['carkeek_organizer_website'] ) ) {
			update_post_meta( $post_id, '_carkeek_organizer_website', esc_url_raw( wp_unslash( $_POST['carkeek_organizer_website'] ) ) );
		}
	}

	// -----------------------------------------------------------------------
	// AJAX: Post search for location/organizer selectors
	// -----------------------------------------------------------------------

	/**
	 * AJAX handler: search for location or organizer posts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_search_posts() {
		check_ajax_referer( 'carkeek_events_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$search    = sanitize_text_field( wp_unslash( $_POST['s'] ?? '' ) );
		$post_type = sanitize_key( $_POST['post_type'] ?? '' );

		$allowed_types = array( 'carkeek_location', 'carkeek_organizer' );
		if ( ! in_array( $post_type, $allowed_types, true ) ) {
			wp_send_json_error( 'Invalid post type' );
		}

		$posts = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			's'              => $search,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$results = array();
		foreach ( $posts as $p ) {
			$results[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title,
			);
		}

		wp_send_json_success( $results );
	}
}

CarkeekEvents_Meta_Boxes::register();
