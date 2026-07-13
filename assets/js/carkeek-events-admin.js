/* global carkeekEventsAdmin, jQuery */
/**
 * Carkeek Events Admin JS
 *
 * Handles:
 *  1. Location/organizer combobox (search existing + create-new footer + inline edit)
 *  2. Auto-fill end date from start date
 *  3. Inline geocoding (location details panel in the event editor)
 *  4. Google Maps geocoding button (standalone Location edit screen)
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
	// 1. Location / Organizer combobox
	// ------------------------------------------------------------------

	var searchTimeout = null;

	function relWrap( el ) {
		return $( el ).closest( '.carkeek-events-relationship' );
	}

	function openList( $input ) {
		$input.siblings( '.carkeek-events-combobox__list' ).prop( 'hidden', false );
		$input.attr( 'aria-expanded', 'true' );
	}

	function closeList( $wrap ) {
		$wrap.find( '.carkeek-events-combobox__list' ).prop( 'hidden', true )
			.find( '.is-active' ).removeClass( 'is-active' );
		$wrap.find( '.carkeek-events-combobox__input' ).attr( 'aria-expanded', 'false' );
	}

	function closeAllLists() {
		$( '.carkeek-events-relationship' ).each( function () {
			closeList( $( this ) );
		} );
	}

	function showSelected( $wrap ) {
		$wrap.addClass( 'is-selected' );
		$wrap.find( '.carkeek-events-combobox' ).prop( 'hidden', true );
		$wrap.find( '.carkeek-events-relationship__selected' ).prop( 'hidden', false );
		$wrap.find( '.carkeek-events-relationship__details' ).prop( 'hidden', false );
	}

	function showCombobox( $wrap ) {
		$wrap.removeClass( 'is-selected' );
		$wrap.find( '.carkeek-events-combobox' ).prop( 'hidden', false );
		$wrap.find( '.carkeek-events-relationship__selected' ).prop( 'hidden', true );
		$wrap.find( '.carkeek-events-relationship__details' ).prop( 'hidden', true );
	}

	function clearDetails( $wrap ) {
		$wrap.find( '.carkeek-events-relationship__details input' ).val( '' );
	}

	function renderOptions( $list, items ) {
		$list.find( '.carkeek-events-combobox__option, .carkeek-events-combobox__none' ).remove();
		var $create = $list.find( '.carkeek-events-combobox__create' );

		if ( items && items.length ) {
			$.each( items, function ( i, item ) {
				$( '<li>' )
					.addClass( 'carkeek-events-combobox__option' )
					.attr( { role: 'option', tabindex: '-1', 'data-id': item.id, 'data-title': item.title } )
					.text( item.title )
					.insertBefore( $create );
			} );
		} else {
			$( '<li>' )
				.addClass( 'carkeek-events-combobox__none' )
				.attr( 'role', 'presentation' )
				.text( admin.i18n.noResults )
				.insertBefore( $create );
		}
	}

	// Open the dropdown on focus (shows the "Create new" footer even when empty).
	$( document ).on( 'focus', '.carkeek-events-combobox__input', function () {
		openList( $( this ) );
	} );

	// Debounced search of existing records.
	$( document ).on( 'input', '.carkeek-events-combobox__input', function () {
		var $input   = $( this );
		var $list    = $input.siblings( '.carkeek-events-combobox__list' );
		var postType = $input.data( 'post-type' );
		var search   = $input.val().trim();

		openList( $input );
		clearTimeout( searchTimeout );

		if ( ! search ) {
			$list.find( '.carkeek-events-combobox__option, .carkeek-events-combobox__none' ).remove();
			return;
		}

		searchTimeout = setTimeout( function () {
			$.ajax( {
				url: admin.ajaxUrl,
				type: 'POST',
				data: {
					action:    'carkeek_events_search_posts',
					nonce:     admin.searchNonce,
					s:         search,
					post_type: postType,
				},
				success: function ( response ) {
					renderOptions( $list, response.success ? response.data : [] );
				},
			} );
		}, 300 );
	} );

	// Select an existing record → link it and load its fields for inline editing.
	function selectExisting( $wrap, id, title ) {
		$wrap.find( '.carkeek-events-relationship__mode' ).val( 'cpt' );
		$wrap.find( '.carkeek-events-cpt-id' ).val( id );
		$wrap.find( '.carkeek-events-relationship__loaded' ).val( '0' );
		$wrap.find( '.carkeek-events-relationship__selected-name' ).text( title );
		$wrap.find( '.carkeek-events-relationship__usage' ).text( '' );
		showSelected( $wrap );
		closeList( $wrap );

		var $details = $wrap.find( '.carkeek-events-relationship__details' );
		var $usage   = $wrap.find( '.carkeek-events-relationship__usage' );

		$.ajax( {
			url: admin.ajaxUrl,
			type: 'POST',
			data: {
				action:    'carkeek_events_get_cpt_fields',
				nonce:     admin.searchNonce,
				post_type: $wrap.data( 'post-type' ),
				id:        id,
			},
			success: function ( response ) {
				if ( ! response.success ) {
					$usage.text( admin.i18n.loadError );
					return;
				}
				$.each( response.data.fields, function ( key, val ) {
					$details.find( '[data-field="' + key + '"]' ).val( val );
				} );
				$usage.text( response.data.usageText || '' );
				$wrap.find( '.carkeek-events-relationship__loaded' ).val( '1' );
			},
			error: function () {
				$usage.text( admin.i18n.loadError );
			},
		} );
	}

	$( document ).on( 'click', '.carkeek-events-combobox__option', function () {
		var id = $( this ).data( 'id' );
		if ( ! id ) {
			return;
		}
		selectExisting( relWrap( this ), id, $( this ).data( 'title' ) );
	} );

	// Create new → open a blank details panel.
	$( document ).on( 'click', '.carkeek-events-combobox__create', function () {
		var $wrap = relWrap( this );
		$wrap.find( '.carkeek-events-relationship__mode' ).val( 'new' );
		$wrap.find( '.carkeek-events-cpt-id' ).val( '' );
		$wrap.find( '.carkeek-events-relationship__loaded' ).val( '1' );
		$wrap.find( '.carkeek-events-relationship__selected-name' ).text( admin.i18n.newRecord );
		$wrap.find( '.carkeek-events-relationship__usage' ).text( '' );
		clearDetails( $wrap );
		showSelected( $wrap );
		closeList( $wrap );
		$wrap.find( '.carkeek-events-relationship__details [data-field="name"]' ).trigger( 'focus' );
	} );

	// Clear the selection / creation.
	$( document ).on( 'click', '.carkeek-events-relationship__clear', function () {
		var $wrap = relWrap( this );
		$wrap.find( '.carkeek-events-relationship__mode' ).val( '' );
		$wrap.find( '.carkeek-events-cpt-id' ).val( '' );
		$wrap.find( '.carkeek-events-relationship__loaded' ).val( '0' );
		$wrap.find( '.carkeek-events-relationship__selected-name' ).text( '' );
		$wrap.find( '.carkeek-events-relationship__usage' ).text( '' );
		clearDetails( $wrap );
		showCombobox( $wrap );
		$wrap.find( '.carkeek-events-combobox__input' ).val( '' ).trigger( 'focus' );
	} );

	// Keyboard navigation within the combobox.
	$( document ).on( 'keydown', '.carkeek-events-combobox__input', function ( e ) {
		var $input = $( this );
		var $list  = $input.siblings( '.carkeek-events-combobox__list' );
		var $items = $list.find( '.carkeek-events-combobox__option, .carkeek-events-combobox__create' );

		if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
			e.preventDefault();
			openList( $input );
			var idx = $items.index( $items.filter( '.is-active' ) );
			idx = 'ArrowDown' === e.key ? idx + 1 : idx - 1;
			if ( idx < 0 ) { idx = $items.length - 1; }
			if ( idx >= $items.length ) { idx = 0; }
			$items.removeClass( 'is-active' );
			$items.eq( idx ).addClass( 'is-active' );
		} else if ( 'Enter' === e.key ) {
			if ( ! $list.prop( 'hidden' ) ) {
				e.preventDefault();
				$items.filter( '.is-active' ).trigger( 'click' );
			}
		} else if ( 'Escape' === e.key ) {
			closeList( relWrap( this ) );
		}
	} );

	// Close dropdowns when clicking outside a combobox.
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '.carkeek-events-combobox' ).length ) {
			closeAllLists();
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
	// 3. Inline geocode (location details panel in the event editor)
	// ------------------------------------------------------------------

	function inlineStatus( $status, msg, isError ) {
		$status
			.text( msg )
			.toggleClass( 'carkeek-events-geocode-status--error', isError )
			.toggleClass( 'carkeek-events-geocode-status--success', ! isError )
			.show();
	}

	$( document ).on( 'click', '.carkeek-events-inline-geocode', function () {
		var $btn    = $( this );
		var $wrap   = relWrap( this );
		var $status = $wrap.find( '.carkeek-events-relationship__geocode-status' );
		var $lat    = $wrap.find( '[data-field="lat"]' );
		var $lng    = $wrap.find( '[data-field="lng"]' );
		var orig    = $btn.text();

		if ( ( $lat.val() || $lng.val() ) && ! window.confirm( admin.i18n.geocodeConfirm ) ) {
			return;
		}

		$btn.prop( 'disabled', true ).text( admin.i18n.geocoding );
		$status.hide().removeClass( 'carkeek-events-geocode-status--error carkeek-events-geocode-status--success' );

		$.ajax( {
			url:  admin.ajaxUrl,
			type: 'POST',
			data: {
				action:  'carkeek_events_geocode',
				nonce:   $btn.data( 'nonce' ),
				post_id: $wrap.find( '.carkeek-events-cpt-id' ).val() || 0,
				address: $wrap.find( '[data-field="address"]' ).val(),
				city:    $wrap.find( '[data-field="city"]' ).val(),
				state:   $wrap.find( '[data-field="state"]' ).val(),
				zip:     $wrap.find( '[data-field="zip"]' ).val(),
				country: $wrap.find( '[data-field="country"]' ).val(),
			},
			success: function ( response ) {
				if ( response.success ) {
					$lat.val( response.data.lat );
					$lng.val( response.data.lng );
					inlineStatus( $status, admin.i18n.geocodeSuccess, false );
				} else {
					var code = response.data && response.data.code ? response.data.code : '';
					var msg  = response.data && response.data.message ? response.data.message : admin.i18n.geocodeError;
					if ( 'quota_exceeded' === code || 'over_query_limit' === code ) {
						msg = admin.i18n.geocodeQuota;
					} else if ( 'zero_results' === code ) {
						msg = admin.i18n.geocodeNoResult;
					}
					inlineStatus( $status, msg, true );
				}
			},
			error: function () {
				inlineStatus( $status, admin.i18n.geocodeError, true );
			},
			complete: function () {
				$btn.prop( 'disabled', false ).text( orig );
			},
		} );
	} );

	// ------------------------------------------------------------------
	// 4. Google Maps Geocoding (standalone Location edit screen)
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
