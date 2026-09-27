import { registerBlockType } from '@wordpress/blocks';
import { megaphone } from '@wordpress/icons';

import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	icon: megaphone,
	edit: Edit,
	save: () => null,
} );
