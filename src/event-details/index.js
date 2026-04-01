import { registerBlockType } from '@wordpress/blocks';
import { ServerSideRender } from '@wordpress/server-side-render';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import	Edit from './edit';

import './editor.scss';
import './style.scss';

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
