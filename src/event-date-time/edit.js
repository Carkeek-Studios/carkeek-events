import { ServerSideRender } from '@wordpress/server-side-render';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, Spinner, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, context, setAttributes } ) {
	const { postId } = context;
	const { dateTimeLabel, dateTimeSeparator } = attributes;

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
				<PanelBody title={ __( 'Date & Time Settings', 'carkeek-events' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Label', 'carkeek-events' ) }
						help={ __( 'Leave blank to hide the label.', 'carkeek-events' ) }
						value={ dateTimeLabel }
						onChange={ ( value ) => setAttributes( { dateTimeLabel: value } ) }
					/>
					<TextControl
						label={ __( 'Date/Time Separator', 'carkeek-events' ) }
						help={ __( 'Separator between date and time. Default is a line break.', 'carkeek-events' ) }
						value={ dateTimeSeparator }
						onChange={ ( value ) => setAttributes( { dateTimeSeparator: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="carkeek-events/event-date-time"
				attributes={ attributes }
				urlQueryArgs={ { postId } }
				skipBlockSupportAttributes={ true }
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Event Date & Time', 'carkeek-events' ) }
						instructions={ __( 'No date set for this event.', 'carkeek-events' ) }
					/>
				) }
			/>
		</div>
	);
}
