import { ServerSideRender } from '@wordpress/server-side-render';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl, Spinner, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, context, setAttributes } ) {
	const { postId } = context;
	const { locationLabel, showDirectionsLink } = attributes;

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
				<PanelBody title={ __( 'Location Settings', 'carkeek-events' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Label', 'carkeek-events' ) }
						help={ __( 'Leave blank to hide the label.', 'carkeek-events' ) }
						value={ locationLabel }
						onChange={ ( value ) => setAttributes( { locationLabel: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Directions Link', 'carkeek-events' ) }
						checked={ showDirectionsLink }
						onChange={ ( value ) => setAttributes( { showDirectionsLink: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="carkeek-events/event-location"
				attributes={ attributes }
				urlQueryArgs={ { postId } }
				skipBlockSupportAttributes={ true }
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Event Location', 'carkeek-events' ) }
						instructions={ __( 'No location set for this event.', 'carkeek-events' ) }
					/>
				) }
			/>
		</div>
	);
}
