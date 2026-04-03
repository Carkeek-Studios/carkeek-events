import { ServerSideRender } from '@wordpress/server-side-render';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, Spinner, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, context, setAttributes } ) {
	const { postId } = context;
	const { organizerLabel } = attributes;

	if ( ! postId ) {
		return (
			<div className="components-placeholder">
				<Spinner />
			</div>
		);
	}

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Organizer Settings', 'carkeek-events' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Label', 'carkeek-events' ) }
						help={ __( 'Leave blank to hide the label.', 'carkeek-events' ) }
						value={ organizerLabel }
						onChange={ ( value ) => setAttributes( { organizerLabel: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="carkeek-events/event-organizer"
				attributes={ attributes }
				urlQueryArgs={ { postId } }
				skipBlockSupportAttributes={ true }
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Event Organizer', 'carkeek-events' ) }
						instructions={ __( 'No organizer set for this event.', 'carkeek-events' ) }
					/>
				) }
			/>
		</div>
	);
}
