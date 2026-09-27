/**
 * ESLint flat config: the @wordpress/scripts defaults plus an exception for
 * the @wordpress/* packages, which WordPress provides at runtime and which are
 * therefore not installed as npm dependencies.
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		rules: {
			'import/no-extraneous-dependencies': 'off',
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
		},
	},
];
