import { ServerSideRender } from '@wordpress/server-side-render';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, Spinner, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, context, setAttributes } ) {
	const { postId } = context;
	const { buttonLabel, googleLabel, icalLabel } = attributes;

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
				<PanelBody title={ __( 'Add to Calendar Settings', 'carkeek-events' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Button Label', 'carkeek-events' ) }
						value={ buttonLabel }
						onChange={ ( value ) => setAttributes( { buttonLabel: value } ) }
					/>
					<TextControl
						label={ __( 'Google Label', 'carkeek-events' ) }
						value={ googleLabel }
						onChange={ ( value ) => setAttributes( { googleLabel: value } ) }
					/>
					<TextControl
						label={ __( 'iCal Label', 'carkeek-events' ) }
						value={ icalLabel }
						onChange={ ( value ) => setAttributes( { icalLabel: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="carkeek-events/add-to-calendar"
				attributes={ attributes }
				urlQueryArgs={ { postId } }
				skipBlockSupportAttributes={ true }
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Add to Calendar', 'carkeek-events' ) }
						instructions={ __( 'No date set for this event, or the feature is disabled in Events settings.', 'carkeek-events' ) }
					/>
				) }
			/>
		</div>
	);
}
