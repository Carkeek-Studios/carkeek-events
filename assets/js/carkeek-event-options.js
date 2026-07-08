/**
 * Event Options — block editor document sidebar panel.
 *
 * Adds a single "Hide from calendar" toggle bound to the _carkeek_event_hidden
 * post meta. Uses useEntityProp only (a single write path) — no direct REST
 * call — so it avoids the race condition that affected the previous sidebar.
 *
 * Hand-authored with wp.* globals (no build step) to avoid re-introducing a
 * webpack entry for a one-toggle panel.
 *
 * @package carkeek-events
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var registerPlugin = wp.plugins.registerPlugin;
	var __ = wp.i18n.__;
	var ToggleControl = wp.components.ToggleControl;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;

	// PluginDocumentSettingPanel moved from @wordpress/edit-post to
	// @wordpress/editor in WP 6.6. Support both.
	var PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel || ! useEntityProp ) {
		return;
	}

	var EventOptionsPanel = function () {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( 'carkeek_event' !== postType ) {
			return null;
		}

		var entity = useEntityProp( 'postType', postType, 'meta' );
		var meta = entity[ 0 ] || {};
		var setMeta = entity[ 1 ];

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'carkeek-event-options',
				title: __( 'Event Options', 'carkeek-events' ),
			},
			el( ToggleControl, {
				label: __( 'Hide from calendar', 'carkeek-events' ),
				help: __(
					'Keeps the event published and reachable by direct link, but hides it from the events archive block and site search.',
					'carkeek-events'
				),
				checked: !! meta._carkeek_event_hidden,
				onChange: function ( value ) {
					setMeta( Object.assign( {}, meta, { _carkeek_event_hidden: value } ) );
				},
			} )
		);
	};

	registerPlugin( 'carkeek-events-options', { render: EventOptionsPanel } );
}( window.wp ) );
