import { ServerSideRender } from '@wordpress/server-side-render';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { PanelBody, CheckboxControl, RadioControl, TextControl, ToggleControl } from "@wordpress/components";
import { __ } from '@wordpress/i18n';

export default function Edit(props) {
	const { attributes, context, setAttributes } = props;
	const { postId } = context;

	const { dateTimeLabel, dateTimeSeparator, locationLabel, organizerLabel, showDirectionsLink } = attributes;

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
				<PanelBody title={ __( 'Event Details Layout', 'carkeek-events' ) }  initialOpen={ true }>
					<p>Customize the labels for the event details leave blank to hide.</p>
					<TextControl
						label={ __( 'Date and Time Label', 'carkeek-events' ) }
						value={ dateTimeLabel }
						onChange={ ( value ) => setAttributes( { dateTimeLabel: value } ) }
					/>
					<TextControl
						label={ __( 'Date and Time Separator', 'carkeek-events' ) }
						value={ dateTimeSeparator }
						help={ __( 'Separator between date and time, default is a line break.', 'carkeek-events' ) }
						onChange={ ( value ) => setAttributes( { dateTimeSeparator: value } ) }
					/>
					<TextControl
						label={ __( 'Location Label', 'carkeek-events' ) }
						value={ locationLabel }
						onChange={ ( value ) => setAttributes( { locationLabel: value } ) }
					/>
					<ToggleControl
						label={ __( 'Show Directions Link', 'carkeek-events' ) }
						checked={ showDirectionsLink }
						onChange={ ( value ) => setAttributes( { showDirectionsLink: value } ) }
					/>
					<TextControl
						label={ __( 'Organizer Label', 'carkeek-events' ) }
						value={ organizerLabel }
						onChange={ ( value ) => setAttributes( { organizerLabel: value } ) }
					/>

				</PanelBody>
			</InspectorControls>
			<p className="note">Details Placeholder set date and time at bottom of template</p>
			<ServerSideRender
				block="carkeek-events/event-details"
				attributes={ attributes }
				urlQueryArgs={ { postId } }
				skipBlockSupportAttributes={ true }
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Event Details', 'carkeek-events' ) }
						instructions={ __( 'No details set for this event, set details below.', 'carkeek-events' ) }
					/>
				) }
			/>
		</div>
	);
}
