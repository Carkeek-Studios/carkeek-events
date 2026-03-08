import './style.scss';
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import icons from './icons';
import EventsArchiveEdit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	...metadata,
	icon: icons.calendar,
	edit: EventsArchiveEdit,
	// No save — rendered server-side via render_callback.
	save: () => null,
} );
