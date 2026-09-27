/**
 * Extends the @wordpress/scripts default config with the admin set editor
 * entry. Blocks are discovered automatically from src/blocks/*\/block.json.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const baseEntry =
	typeof defaultConfig.entry === 'function' ? defaultConfig.entry() : defaultConfig.entry;

module.exports = {
	...defaultConfig,
	entry: {
		...baseEntry,
		'admin/set-editor': path.resolve( __dirname, 'src/admin/set-editor/index.js' ),
	},
};
