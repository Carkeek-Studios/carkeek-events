import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const EventHidePanel = () => {
	const { postType, postId, isHidden } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const meta   = editor.getEditedPostAttribute( 'meta' );
		return {
			postType: editor.getCurrentPostType(),
			postId:   editor.getCurrentPostId(),
			isHidden: meta?._carkeek_event_hidden === '1',
		};
	}, [] );

	const { editPost } = useDispatch( 'core/editor' );

	if ( postType !== 'carkeek_event' ) {
		return null;
	}

	const handleChange = async ( value ) => {
		const metaValue = value ? '1' : '0';

		// Update the entity store immediately so the main Save button
		// stays in sync and doesn't clobber this field.
		editPost( { meta: { _carkeek_event_hidden: metaValue } } );

		// Also save directly via REST API — this is the reliable write
		// that persists regardless of entity-store timing issues.
		try {
			await apiFetch( {
				path:   `/wp/v2/carkeek_event/${ postId }`,
				method: 'POST',
				data:   { meta: { _carkeek_event_hidden: metaValue } },
			} );
		} catch {
			// Revert entity store on failure.
			editPost( { meta: { _carkeek_event_hidden: value ? '0' : '1' } } );
		}
	};

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
				onChange={ handleChange }
				__nextHasNoMarginBottom
			/>
		</PluginDocumentSettingPanel>
	);
};

registerPlugin( 'carkeek-event-visibility', { render: EventHidePanel } );
