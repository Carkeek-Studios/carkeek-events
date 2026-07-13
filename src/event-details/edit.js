import { ServerSideRender } from '@wordpress/server-side-render';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit( props ) {
	const { attributes, context } = props;
	const { postId } = context;

	if ( ! postId ) {
		return (
			<div className="components-placeholder">
				<Spinner />
			</div>
		);
	}

	return (
		<div { ...useBlockProps() }>
			<div className="event-details-preview">
				<p className="note">
					{ __(
						'Event details are set in the meta box below. Labels and separator are configured in Events → Settings.',
						'carkeek-events'
					) }
				</p>
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
		</div>
	);
}
