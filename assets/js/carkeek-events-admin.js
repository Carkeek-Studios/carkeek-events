/* global carkeekEventsAdmin, jQuery */
/**
 * Carkeek Events Admin JS
 *
 * Handles:
 *  1. Location/organizer AJAX search and selection
 *  2. Auto-fill end date from start date
 *  3. Tab switching (CPT select vs free-text)
 *  4. Google Maps geocoding button
 *  5. Settings page tools (run cron, flush rewrites)
 *  6. Password show/hide toggle for API key
 *
 * NOTE: All <button> elements that are not form-submit buttons
 * have explicit type="button" in the PHP templates to prevent
 * accidental form submission within WP Settings API forms.
 */
( function ( $ ) {
	'use strict';

	var admin = carkeekEventsAdmin;

	// ------------------------------------------------------------------
	// 1. Location / Organizer AJAX search
	// ------------------------------------------------------------------

	var searchTimeout = null;

	$( document ).on( 'input', '.carkeek-events-search-input', function () {
		var $input    = $( this );
		var $wrap     = $input.closest( '.carkeek-events-search-wrap' );
		var $results  = $wrap.find( '.carkeek-events-search-results' );
		var $hidden   = $input.closest( '.carkeek-events-relationship__panel--cpt' ).find( '.carkeek-events-cpt-id' );
		var postType  = $input.data( 'post-type' );
		var search    = $input.val().trim();

		clearTimeout( searchTimeout );

		if ( ! search ) {
			$results.empty().hide();
			return;
		}

		searchTimeout = setTimeout( function () {
			$.ajax( {
				url: admin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'carkeek_events_search_posts',
					nonce:  admin.searchNonce,
					s:      search,
					post_type: postType,
				},
				success: function ( response ) {
					$results.empty();

					if ( ! response.success || ! response.data.length ) {
						$results
							.append( '<li class="carkeek-events-search-results__none">' + admin.i18n.noResults + '</li>' )
							.show();
						return;
					}

					$.each( response.data, function ( i, item ) {
						$results.append(
							$( '<li>' )
								.addClass( 'carkeek-events-search-results__item' )
								.text( item.title )
								.attr( 'data-id', item.id )
								.attr( 'data-title', item.title )
						);
					} );

					$results.show();
				},
			} );
		}, 300 );
	} );

	// Select a result from the dropdown.
	$( document ).on( 'click', '.carkeek-events-search-results__item', function () {
		var $item    = $( this );
		var $panel   = $item.closest( '.carkeek-events-relationship__panel--cpt' );
		var $hidden  = $panel.find( '.carkeek-events-cpt-id' );
		var $input   = $panel.find( '.carkeek-events-search-input' );
		var $results = $panel.find( '.carkeek-events-search-results' );
		var $name    = $panel.find( '.carkeek-events-selected-name' );

		$hidden.val( $item.data( 'id' ) );
		$input.val( $item.data( 'title' ) );
		$results.empty().hide();

		// Update or add selected-name display.
		if ( ! $name.length ) {
			$panel.append(
				'<p class="carkeek-events-selected-name">' +
					$item.data( 'title' ) +
					' <button type="button" class="carkeek-events-clear-cpt" aria-label="Clear selection">&#x2715;</button>' +
				'</p>'
			);
		} else {
			$name.html(
				$item.data( 'title' ) +
				' <button type="button" class="carkeek-events-clear-cpt" aria-label="Clear selection">&#x2715;</button>'
			);
		}
	} );

	// Clear selected CPT.
	$( document ).on( 'click', '.carkeek-events-clear-cpt', function () {
		var $panel  = $( this ).closest( '.carkeek-events-relationship__panel--cpt' );
		$panel.find( '.carkeek-events-cpt-id' ).val( '' );
		$panel.find( '.carkeek-events-search-input' ).val( '' );
		$panel.find( '.carkeek-events-selected-name' ).remove();
	} );

	// Hide results when clicking outside.
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '.carkeek-events-search-wrap' ).length ) {
			$( '.carkeek-events-search-results' ).empty().hide();
		}
	} );

	// ------------------------------------------------------------------
	// 2. Auto-fill end date from start date
	// ------------------------------------------------------------------

	$( document ).on( 'change', '#carkeek_event_start_date', function () {
		var $end = $( '#carkeek_event_end_date' );
		if ( ! $end.val() ) {
			$end.val( $( this ).val() );
		}
	} );

	// ------------------------------------------------------------------
	// 3. Tab switching (CPT select vs create new)
	// ------------------------------------------------------------------

	$( document ).on( 'click', '.carkeek-events-tab', function () {
		var $tab  = $( this );
		var $wrap = $tab.closest( '.carkeek-events-relationship' );
		var panel = $tab.data( 'tab' );

		$wrap.find( '.carkeek-events-tab' ).removeClass( 'is-active' );
		$tab.addClass( 'is-active' );

		$wrap.find( '.carkeek-events-relationship__panel' ).removeClass( 'is-active' );
		$wrap.find( '.carkeek-events-relationship__panel--' + panel ).addClass( 'is-active' );

		// Update mode field so the save handler knows which path to take.
		$wrap.find( '.carkeek-events-relationship__mode' ).val( panel );

		// Switching to "Create new" — clear any existing CPT selection.
		if ( 'new' === panel ) {
			$wrap.find( '.carkeek-events-cpt-id' ).val( '' );
			$wrap.find( '.carkeek-events-search-input' ).val( '' );
			$wrap.find( '.carkeek-events-selected-name' ).remove();
		}
		// Switching back to "Select existing" — clear the create-new fields.
		if ( 'cpt' === panel ) {
			$wrap.find( '.carkeek-events-relationship__panel--new input' ).val( '' );
		}
	} );

	// ------------------------------------------------------------------
	// 4. Google Maps Geocoding
	// ------------------------------------------------------------------

	$( document ).on( 'click', '#carkeek-geocode-btn', function () {
		var $btn    = $( this );
		var $status = $( '#carkeek-geocode-status' );
		var $lat    = $( '#carkeek_location_lat' );
		var $lng    = $( '#carkeek_location_lng' );

		// Warn before overwriting existing coordinates.
		if ( $lat.val() || $lng.val() ) {
			if ( ! window.confirm( admin.i18n.geocodeConfirm ) ) {
				return;
			}
		}

		$btn.prop( 'disabled', true ).text( admin.i18n.geocoding );
		$status.text( '' ).hide().removeClass( 'carkeek-events-geocode-status--error carkeek-events-geocode-status--success' );

		$.ajax( {
			url:  admin.ajaxUrl,
			type: 'POST',
			data: {
				action:   'carkeek_events_geocode',
				nonce:    $btn.data( 'nonce' ),
				post_id:  $btn.data( 'post-id' ),
				address:  $( '#carkeek_location_address' ).val(),
				city:     $( '#carkeek_location_city' ).val(),
				state:    $( '#carkeek_location_state' ).val(),
				zip:      $( '#carkeek_location_zip' ).val(),
				country:  $( '#carkeek_location_country' ).val(),
			},
			success: function ( response ) {
				if ( response.success ) {
					$lat.val( response.data.lat );
					$lng.val( response.data.lng );
					showStatus( admin.i18n.geocodeSuccess, false );
				} else {
					var code = response.data && response.data.code ? response.data.code : '';
					var msg  = response.data && response.data.message ? response.data.message : admin.i18n.geocodeError;

					if ( 'quota_exceeded' === code || 'over_query_limit' === code ) {
						msg = admin.i18n.geocodeQuota;
					} else if ( 'zero_results' === code ) {
						msg = admin.i18n.geocodeNoResult;
					}
					showStatus( msg, true );
				}
			},
			error: function () {
				showStatus( admin.i18n.geocodeError, true );
			},
			complete: function () {
				$btn.prop( 'disabled', false ).text( 'Geocode Address' );
			},
		} );

		function showStatus( msg, isError ) {
			$status
				.text( msg )
				.toggleClass( 'carkeek-events-geocode-status--error', isError )
				.toggleClass( 'carkeek-events-geocode-status--success', ! isError )
				.show();
		}
	} );

	// ------------------------------------------------------------------
	// 5. Settings page tools
	// ------------------------------------------------------------------

	$( '#carkeek-run-cron' ).on( 'click', function () {
		var $btn    = $( this );
		var $status = $( '#carkeek-cron-status' );

		$btn.prop( 'disabled', true );
		$status.text( '…' );

		$.ajax( {
			url:  admin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'carkeek_events_run_cron',
				nonce:  $btn.data( 'nonce' ),
			},
			success: function ( response ) {
				$status.text( response.success ? response.data : response.data.message );
			},
			error: function () {
				$status.text( 'Error. Please try again.' );
			},
			complete: function () {
				$btn.prop( 'disabled', false );
			},
		} );
	} );

	$( '#carkeek-flush-rewrites' ).on( 'click', function () {
		var $btn    = $( this );
		var $status = $( '#carkeek-flush-status' );

		$btn.prop( 'disabled', true );
		$status.text( '…' );

		$.ajax( {
			url:  admin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'carkeek_events_flush_rewrites',
				nonce:  $btn.data( 'nonce' ),
			},
			success: function ( response ) {
				$status.text( response.success ? response.data : response.data.message );
			},
			error: function () {
				$status.text( 'Error. Please try again.' );
			},
			complete: function () {
				$btn.prop( 'disabled', false );
			},
		} );
	} );

	// ------------------------------------------------------------------
	// 6. API key show/hide toggle
	// ------------------------------------------------------------------

	$( document ).on( 'click', '.carkeek-toggle-password', function () {
		var $btn    = $( this );
		var $target = $( '#' + $btn.data( 'target' ) );

		if ( 'password' === $target.attr( 'type' ) ) {
			$target.attr( 'type', 'text' );
			$btn.text( 'Hide' );
		} else {
			$target.attr( 'type', 'password' );
			$btn.text( 'Show' );
		}
	} );

} )( jQuery );
