import { registerBlockType } from '@wordpress/blocks';
import { check } from '@wordpress/icons';

import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	icon: check,
	edit: Edit,
	save: () => null,
} );
