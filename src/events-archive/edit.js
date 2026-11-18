import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Disabled } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import EventsInspector from './inspector';
import icons from './icons';

const EventsArchiveEdit = ( props ) => {
	const blockProps = useBlockProps();

	return (
		<>
			<EventsInspector { ...props } />
			<div { ...blockProps }>
				<Disabled>
					<ServerSideRender
						block="carkeek-events/archive"
						attributes={ props.attributes }
					/>
				</Disabled>
			</div>
		</>
	);
};

export default EventsArchiveEdit;
