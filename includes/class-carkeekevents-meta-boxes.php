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
		add_action( 'wp_ajax_carkeek_events_get_cpt_fields', array( $instance, 'ajax_get_cpt_fields' ) );
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

		// "Hide from calendar" side box — only in the classic editor. In the block
		// editor the "Event Options" document sidebar panel handles this instead,
		// so registering here would produce a duplicate control.
		$block_editor = ! function_exists( 'use_block_editor_for_post_type' )
			|| use_block_editor_for_post_type( 'carkeek_event' );
		if ( ! $block_editor ) {
			add_meta_box(
				'carkeek_event_options',
				__( 'Event Options', 'carkeek-events' ),
				array( $this, 'render_event_options_box' ),
				'carkeek_event',
				'side',
				'default'
			);
		}

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

		// Field-in-use flags — hide input rows for field groups the site does not use.
		$use_locations  = CarkeekEvents_Display::field_enabled( 'locations' );
		$use_organizers = CarkeekEvents_Display::field_enabled( 'organizers' );
		$use_button     = CarkeekEvents_Display::field_enabled( 'button' );
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

				<?php do_action( 'carkeek_events_meta_box_after_dates', $post ); ?>

			<?php if ( $use_locations ) : ?>
			<hr />

			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label><?php esc_html_e( 'Location', 'carkeek-events' ); ?></label>
					<?php $this->render_relationship_field( 'location', $location_id, $location_title, $location_text ); ?>
				</div>
			</div>

			<?php do_action( 'carkeek_events_meta_box_after_location', $post ); ?>
			<?php endif; ?>

			<?php if ( $use_organizers ) : ?>
			<hr />

			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label><?php esc_html_e( 'Organizer', 'carkeek-events' ); ?></label>
					<?php $this->render_relationship_field( 'organizer', $organizer_id, $organizer_title, $organizer_text ); ?>
				</div>
			</div>

			<?php do_action( 'carkeek_events_meta_box_after_organizer', $post ); ?>
			<?php endif; ?>

			<?php if ( $use_button ) : ?>
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
			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Render the Event Options side meta box (classic editor only).
	 *
	 * @since 2.1.0
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_event_options_box( $post ) {
		wp_nonce_field( 'carkeek_event_options_save', 'carkeek_event_options_nonce' );
		$hidden = (bool) get_post_meta( $post->ID, '_carkeek_event_hidden', true );
		?>
		<p>
			<label>
				<input type="checkbox" name="carkeek_event_hidden" value="1" <?php checked( $hidden ); ?> />
				<?php esc_html_e( 'Hide from calendar', 'carkeek-events' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Keeps the event published and reachable by direct link, but hides it from the events archive block and site search.', 'carkeek-events' ); ?>
		</p>
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
		$has_cpt   = (int) $cpt_id > 0;

		// Prefill the details panel with the linked record's current fields.
		$values = $has_cpt ? $this->get_cpt_field_values( $type, $cpt_id ) : array();
		if ( $has_cpt && '' === ( $values['name'] ?? '' ) ) {
			$values['name'] = $cpt_name;
		}
		$usage = $has_cpt ? $this->count_linked_events( $type, $cpt_id ) : 0;

		/* translators: %s: 'location' or 'organizer'. */
		$placeholder = sprintf( __( 'Search or select a %s…', 'carkeek-events' ), $type );
		/* translators: %s: 'location' or 'organizer'. */
		$create_label = sprintf( __( '+ Create new %s', 'carkeek-events' ), $type );
		?>
		<div class="carkeek-events-relationship <?php echo $has_cpt ? 'is-selected' : ''; ?>"
			data-type="<?php echo esc_attr( $type ); ?>"
			data-post-type="<?php echo esc_attr( $post_type ); ?>">

			<input type="hidden"
				name="carkeek_event_<?php echo esc_attr( $type ); ?>_mode"
				class="carkeek-events-relationship__mode"
				value="<?php echo $has_cpt ? 'cpt' : ''; ?>" />

			<input type="hidden"
				name="carkeek_event_<?php echo esc_attr( $type ); ?>_id"
				id="carkeek_event_<?php echo esc_attr( $type ); ?>_id"
				class="carkeek-events-cpt-id"
				value="<?php echo esc_attr( $cpt_id ); ?>" />

			<input type="hidden"
				name="carkeek_event_<?php echo esc_attr( $type ); ?>_fields_loaded"
				class="carkeek-events-relationship__loaded"
				value="<?php echo $has_cpt ? '1' : '0'; ?>" />

			<div class="carkeek-events-combobox" <?php echo $has_cpt ? 'hidden' : ''; ?>>
				<input type="text"
					class="carkeek-events-combobox__input"
					role="combobox"
					aria-expanded="false"
					aria-autocomplete="list"
					data-post-type="<?php echo esc_attr( $post_type ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					autocomplete="off" />
				<ul class="carkeek-events-combobox__list" role="listbox" hidden>
					<li class="carkeek-events-combobox__create" role="option" tabindex="-1" data-action="create"><?php echo esc_html( $create_label ); ?></li>
				</ul>
			</div>

			<div class="carkeek-events-relationship__selected" <?php echo $has_cpt ? '' : 'hidden'; ?>>
				<span class="carkeek-events-relationship__selected-name"><?php echo esc_html( $has_cpt ? $cpt_name : '' ); ?></span>
				<button type="button" class="carkeek-events-relationship__clear" aria-label="<?php esc_attr_e( 'Clear selection', 'carkeek-events' ); ?>">&#x2715;</button>
				<span class="carkeek-events-relationship__usage" aria-live="polite">
					<?php echo $usage ? esc_html( $this->usage_hint_text( $usage ) ) : ''; ?>
				</span>
			</div>

			<div class="carkeek-events-relationship__details" <?php echo $has_cpt ? '' : 'hidden'; ?>>
				<?php $this->render_cpt_fields( $type, $values ); ?>
			</div>

		</div>
		<?php
	}

	/**
	 * "Used by N events" hint text.
	 *
	 * @since 2.4.0
	 * @param int $count Number of events linking the record.
	 * @return string
	 */
	private function usage_hint_text( $count ) {
		/* translators: %d: number of events. */
		return sprintf( _n( 'Used by %d event — edits apply to all.', 'Used by %d events — edits apply to all.', $count, 'carkeek-events' ), $count );
	}

	/**
	 * Render the editable fields for a location or organizer.
	 *
	 * Serves both "create new" (blank values) and "edit selected existing"
	 * (prefilled) states. Field names use the carkeek_event_{type}_field_{key}
	 * convention so a single panel drives both save paths.
	 *
	 * @since 1.0.0
	 * @param string $type   'location' or 'organizer'.
	 * @param array  $values Optional prefill values keyed by field (name, address, …).
	 * @return void
	 */
	private function render_cpt_fields( $type, $values = array() ) {
		$v = function ( $key ) use ( $values ) {
			return isset( $values[ $key ] ) ? $values[ $key ] : '';
		};
		?>
		<div class="carkeek-events-cpt-fields">

			<div class="carkeek-events-row">
				<div class="carkeek-events-col carkeek-events-col--full">
					<label for="carkeek_event_<?php echo esc_attr( $type ); ?>_field_name">
						<?php esc_html_e( 'Name', 'carkeek-events' ); ?> <span aria-hidden="true">*</span>
					</label>
					<input type="text"
						id="carkeek_event_<?php echo esc_attr( $type ); ?>_field_name"
						name="carkeek_event_<?php echo esc_attr( $type ); ?>_field_name"
						class="widefat" data-field="name"
						value="<?php echo esc_attr( $v( 'name' ) ); ?>" />
				</div>
			</div>

			<?php if ( 'location' === $type ) : ?>

				<div class="carkeek-events-row">
					<div class="carkeek-events-col carkeek-events-col--full">
						<label for="carkeek_event_location_field_address"><?php esc_html_e( 'Street Address', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_field_address" name="carkeek_event_location_field_address" class="widefat" data-field="address" value="<?php echo esc_attr( $v( 'address' ) ); ?>" />
					</div>
				</div>

				<div class="carkeek-events-row">
					<div class="carkeek-events-col">
						<label for="carkeek_event_location_field_city"><?php esc_html_e( 'City', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_field_city" name="carkeek_event_location_field_city" data-field="city" value="<?php echo esc_attr( $v( 'city' ) ); ?>" />
					</div>
					<div class="carkeek-events-col">
						<label for="carkeek_event_location_field_state"><?php esc_html_e( 'State / Province', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_field_state" name="carkeek_event_location_field_state" data-field="state" value="<?php echo esc_attr( $v( 'state' ) ); ?>" />
					</div>
					<div class="carkeek-events-col">
						<label for="carkeek_event_location_field_zip"><?php esc_html_e( 'Zip / Postal Code', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_field_zip" name="carkeek_event_location_field_zip" data-field="zip" value="<?php echo esc_attr( $v( 'zip' ) ); ?>" />
					</div>
					<div class="carkeek-events-col">
						<label for="carkeek_event_location_field_country"><?php esc_html_e( 'Country', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_field_country" name="carkeek_event_location_field_country" data-field="country" value="<?php echo esc_attr( $v( 'country' ) ); ?>" />
					</div>
				</div>

				<div class="carkeek-events-row">
					<div class="carkeek-events-col carkeek-events-col--full">
						<label for="carkeek_event_location_field_website"><?php esc_html_e( 'Website', 'carkeek-events' ); ?></label>
						<input type="url" id="carkeek_event_location_field_website" name="carkeek_event_location_field_website" class="widefat" data-field="website" value="<?php echo esc_attr( $v( 'website' ) ); ?>" />
					</div>
				</div>

				<div class="carkeek-events-row carkeek-events-row--geocode">
					<div class="carkeek-events-col">
						<label for="carkeek_event_location_field_lat"><?php esc_html_e( 'Latitude', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_field_lat" name="carkeek_event_location_field_lat" data-field="lat" value="<?php echo esc_attr( $v( 'lat' ) ); ?>" placeholder="e.g. 47.6062" />
					</div>
					<div class="carkeek-events-col">
						<label for="carkeek_event_location_field_lng"><?php esc_html_e( 'Longitude', 'carkeek-events' ); ?></label>
						<input type="text" id="carkeek_event_location_field_lng" name="carkeek_event_location_field_lng" data-field="lng" value="<?php echo esc_attr( $v( 'lng' ) ); ?>" placeholder="e.g. -122.3321" />
					</div>
					<div class="carkeek-events-col carkeek-events-col--geocode-btn">
						<label>&nbsp;</label>
						<button type="button" class="button carkeek-events-inline-geocode"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'carkeek_events_geocode' ) ); ?>">
							<?php esc_html_e( 'Geocode Address', 'carkeek-events' ); ?>
						</button>
					</div>
				</div>
				<div class="carkeek-events-relationship__geocode-status carkeek-events-geocode-status" style="display:none;"></div>

			<?php else : ?>

				<div class="carkeek-events-row">
					<div class="carkeek-events-col">
						<label for="carkeek_event_organizer_field_email"><?php esc_html_e( 'Email', 'carkeek-events' ); ?></label>
						<input type="email" id="carkeek_event_organizer_field_email" name="carkeek_event_organizer_field_email" data-field="email" value="<?php echo esc_attr( $v( 'email' ) ); ?>" />
					</div>
					<div class="carkeek-events-col">
						<label for="carkeek_event_organizer_field_phone"><?php esc_html_e( 'Phone', 'carkeek-events' ); ?></label>
						<input type="tel" id="carkeek_event_organizer_field_phone" name="carkeek_event_organizer_field_phone" data-field="phone" value="<?php echo esc_attr( $v( 'phone' ) ); ?>" />
					</div>
				</div>

				<div class="carkeek-events-row">
					<div class="carkeek-events-col carkeek-events-col--full">
						<label for="carkeek_event_organizer_field_website"><?php esc_html_e( 'Website', 'carkeek-events' ); ?></label>
						<input type="url" id="carkeek_event_organizer_field_website" name="carkeek_event_organizer_field_website" class="widefat" data-field="website" value="<?php echo esc_attr( $v( 'website' ) ); ?>" />
					</div>
				</div>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Read a linked record's field values for prefilling the details panel.
	 *
	 * @since 2.4.0
	 * @param string $type   'location' or 'organizer'.
	 * @param int    $cpt_id The linked post ID.
	 * @return array Values keyed by field.
	 */
	private function get_cpt_field_values( $type, $cpt_id ) {
		$post = get_post( $cpt_id );
		if ( ! $post ) {
			return array();
		}
		$values = array( 'name' => $post->post_title );
		foreach ( $this->cpt_field_map( $type ) as $field => $meta_key ) {
			$values[ $field ] = get_post_meta( $cpt_id, $meta_key, true );
		}
		return $values;
	}

	/**
	 * Map of details-panel field keys to their record meta keys.
	 *
	 * @since 2.4.0
	 * @param string $type 'location' or 'organizer'.
	 * @return array field => meta_key (excludes 'name', which is the post title).
	 */
	private function cpt_field_map( $type ) {
		if ( 'location' === $type ) {
			return array(
				'address' => '_carkeek_location_address',
				'city'    => '_carkeek_location_city',
				'state'   => '_carkeek_location_state',
				'zip'     => '_carkeek_location_zip',
				'country' => '_carkeek_location_country',
				'website' => '_carkeek_location_website',
				'lat'     => '_carkeek_location_lat',
				'lng'     => '_carkeek_location_lng',
			);
		}
		return array(
			'email'   => '_carkeek_organizer_email',
			'phone'   => '_carkeek_organizer_phone',
			'website' => '_carkeek_organizer_website',
		);
	}

	/**
	 * Count how many events currently link a given location/organizer record.
	 *
	 * @since 2.4.0
	 * @param string $type   'location' or 'organizer'.
	 * @param int    $cpt_id The record post ID.
	 * @return int
	 */
	private function count_linked_events( $type, $cpt_id ) {
		$query = new WP_Query( array(
			'post_type'      => 'carkeek_event',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => "_carkeek_event_{$type}_id",
					'value' => (int) $cpt_id,
				),
			),
		) );
		return count( $query->posts );
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

		// Location / Organizer relationships. Each only processes when the field
		// group is in use AND its mode input was submitted — otherwise a hidden row
		// would zero out a stored link.
		if ( CarkeekEvents_Display::field_enabled( 'locations' ) ) {
			$this->save_relationship( $post_id, 'location' );
		}
		if ( CarkeekEvents_Display::field_enabled( 'organizers' ) ) {
			$this->save_relationship( $post_id, 'organizer' );
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

		// Hide from calendar (classic editor side box). The block editor writes
		// this meta via the REST API, where its own nonce is absent, so this
		// branch is skipped and there is no double write.
		if ( isset( $_POST['carkeek_event_options_nonce'] )
			&& wp_verify_nonce( sanitize_key( $_POST['carkeek_event_options_nonce'] ), 'carkeek_event_options_save' )
		) {
			if ( ! empty( $_POST['carkeek_event_hidden'] ) ) {
				update_post_meta( $post_id, '_carkeek_event_hidden', '1' );
			} else {
				delete_post_meta( $post_id, '_carkeek_event_hidden' );
			}
		}
	}

	/**
	 * Save one relationship (location or organizer) from the submitted meta box.
	 *
	 * Modes (from the hidden carkeek_event_{type}_mode input):
	 *   'new' — create a new record from the details fields and link it.
	 *   'cpt' — link the selected record, and (when its fields were loaded and the
	 *           user may edit it) write inline edits back to that shared record.
	 *   ''    — clear the link.
	 *
	 * @since 2.4.0
	 * @param int    $event_id Event post ID.
	 * @param string $type     'location' or 'organizer'.
	 * @return void
	 */
	private function save_relationship( $event_id, $type ) {
		$mode_key = "carkeek_event_{$type}_mode";
		if ( ! isset( $_POST[ $mode_key ] ) ) {
			return; // Field not present (e.g. hidden by "Fields in Use") — never zero the link.
		}

		$mode      = sanitize_key( wp_unslash( $_POST[ $mode_key ] ) );
		$post_type = "carkeek_{$type}";
		$meta_key  = "_carkeek_event_{$type}_id";

		if ( 'new' === $mode ) {
			$this->create_and_link_cpt( $event_id, $type );
			return;
		}

		if ( 'cpt' === $mode ) {
			$cpt_id = absint( $_POST["carkeek_event_{$type}_id"] ?? 0 );
			if ( $cpt_id ) {
				$cpt = get_post( $cpt_id );
				if ( ! $cpt || $post_type !== $cpt->post_type ) {
					$cpt_id = 0;
				}
			}
			update_post_meta( $event_id, $meta_key, $cpt_id );

			// Write inline edits back to the shared record only when its fields were
			// actually loaded (guards against blanking on a failed AJAX populate) and
			// the current user may edit that specific record.
			$loaded = ! empty( $_POST["carkeek_event_{$type}_fields_loaded"] );
			if ( $cpt_id && $loaded && current_user_can( 'edit_post', $cpt_id ) ) {
				$name = sanitize_text_field( wp_unslash( $_POST["carkeek_event_{$type}_field_name"] ?? '' ) );
				if ( '' !== $name && $name !== get_post_field( 'post_title', $cpt_id ) ) {
					wp_update_post( array( 'ID' => $cpt_id, 'post_title' => $name ) );
				}
				$this->save_cpt_fields_from_post( $cpt_id, $type );
			}
			return;
		}

		// Empty mode — the link was cleared.
		update_post_meta( $event_id, $meta_key, 0 );
	}

	/**
	 * Create a new location or organizer post from the details fields and link it.
	 *
	 * @since 1.0.0
	 * @param int    $event_id The event post ID.
	 * @param string $type     'location' or 'organizer'.
	 * @return void
	 */
	private function create_and_link_cpt( $event_id, $type ) {
		$name = sanitize_text_field( wp_unslash( $_POST[ "carkeek_event_{$type}_field_name" ] ?? '' ) );
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

		$this->save_cpt_fields_from_post( $new_id, $type );

		update_post_meta( $event_id, "_carkeek_event_{$type}_id", $new_id );
	}

	/**
	 * Persist the details-panel fields onto a location/organizer record.
	 *
	 * Reads the carkeek_event_{type}_field_{key} inputs. A blank value deletes the
	 * meta (so inline edits can clear a field). Shared by the create and edit paths.
	 *
	 * @since 2.4.0
	 * @param int    $cpt_id The location/organizer post ID.
	 * @param string $type   'location' or 'organizer'.
	 * @return void
	 */
	private function save_cpt_fields_from_post( $cpt_id, $type ) {
		foreach ( $this->cpt_field_map( $type ) as $field => $meta_key ) {
			$raw = wp_unslash( $_POST[ "carkeek_event_{$type}_field_{$field}" ] ?? '' );

			if ( 'email' === $field ) {
				$val = sanitize_email( $raw );
			} elseif ( 'website' === $field ) {
				$val = esc_url_raw( $raw );
			} else {
				$val = sanitize_text_field( $raw );
			}

			if ( '' !== $val ) {
				update_post_meta( $cpt_id, $meta_key, $val );
			} else {
				delete_post_meta( $cpt_id, $meta_key );
			}
		}
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

	/**
	 * AJAX handler: return a location/organizer record's fields + usage count.
	 *
	 * Used to populate the inline details panel when an existing record is selected.
	 *
	 * @since 2.4.0
	 * @return void
	 */
	public function ajax_get_cpt_fields() {
		check_ajax_referer( 'carkeek_events_admin', 'nonce' );

		$post_type = sanitize_key( $_POST['post_type'] ?? '' );
		$id        = absint( $_POST['id'] ?? 0 );

		$type_map = array(
			'carkeek_location'  => 'location',
			'carkeek_organizer' => 'organizer',
		);
		if ( ! isset( $type_map[ $post_type ] ) ) {
			wp_send_json_error( 'Invalid post type' );
		}
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$post = get_post( $id );
		if ( ! $post || $post_type !== $post->post_type ) {
			wp_send_json_error( 'Not found' );
		}

		$type   = $type_map[ $post_type ];
		$fields = array( 'name' => $post->post_title );
		foreach ( $this->cpt_field_map( $type ) as $field => $meta_key ) {
			$fields[ $field ] = get_post_meta( $id, $meta_key, true );
		}

		$usage = $this->count_linked_events( $type, $id );

		wp_send_json_success( array(
			'fields'    => $fields,
			'usage'     => $usage,
			'usageText' => $usage ? $this->usage_hint_text( $usage ) : '',
		) );
	}
}

CarkeekEvents_Meta_Boxes::register();
