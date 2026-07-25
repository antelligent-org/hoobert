/**
 * ESLint flat config: the @wordpress/scripts defaults, plus the two adjustments
 * this plugin needs.
 */

const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		settings: {
			// WordPress ships these as script dependencies and wp-scripts
			// externalizes them at build time, so wp-admin resolves them at
			// runtime rather than the bundle pulling them from node_modules.
			'import/core-modules': [
				'@wordpress/commands',
				'@wordpress/data',
				'@wordpress/element',
			],
		},
		rules: {
			// Components document their props with one @param {Object} props
			// block; a tag per destructured key only repeats the signature.
			'jsdoc/require-param': [ 'error', { checkDestructured: false, checkDestructuredRoots: false } ],
		},
	},
];
