import { registerBlockType } from '@wordpress/blocks';
import ServerSideRender from '@wordpress/server-side-render';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata, {
	edit( { context } ) {
		const { postId } = context;

		if ( ! postId ) {
			return (
				<div className="components-placeholder">
					<Spinner />
				</div>
			);
		}

		return (
			<ServerSideRender
				block="carkeek-events/event-date-time"
				attributes={ {} }
				urlQueryArgs={ { postId } }
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Event Date & Time', 'carkeek-events' ) }
						instructions={ __( 'No date set for this event.', 'carkeek-events' ) }
					/>
				) }
			/>
		);
	},
	save: () => null,
} );
