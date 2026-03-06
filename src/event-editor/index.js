import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const EventHidePanel = () => {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	if ( postType !== 'carkeek_event' ) {
		return null;
	}

	const isHidden = meta?._carkeek_event_hidden === '1';

	return (
		<PluginDocumentSettingPanel
			name="carkeek-event-visibility"
			title={ __( 'Event Visibility', 'carkeek-events' ) }
		>
			<ToggleControl
				label={ __( 'Hide from Events Archive', 'carkeek-events' ) }
				help={ __(
					'When enabled, this event will not appear in the archive block or events list.',
					'carkeek-events'
				) }
				checked={ isHidden }
				onChange={ ( value ) =>
					setMeta( { ...meta, _carkeek_event_hidden: value ? '1' : '0' } )
				}
				__nextHasNoMarginBottom
			/>
		</PluginDocumentSettingPanel>
	);
};

registerPlugin( 'carkeek-event-visibility', { render: EventHidePanel } );
