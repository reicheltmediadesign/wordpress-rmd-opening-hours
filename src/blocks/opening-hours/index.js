import { registerBlockType } from '@wordpress/blocks';
import { time } from '@wordpress/icons';

import metadata from './block.json';
import Edit from './edit';
import './editor.scss';

registerBlockType( metadata.name, {
	icon: time,
	edit: Edit,
	save: () => null,
} );
