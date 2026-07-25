/**
 * Prettier configuration, at the repo root so editors find it from any file.
 *
 * It re-exports @wordpress/prettier-config rather than restating its options.
 * That config is already the source of truth twice over: `wp-scripts format`
 * applies it, and @wordpress/eslint-plugin feeds it to the `prettier/prettier`
 * rule after merging whatever config it discovers here on top. Restating the
 * options would let the two drift apart and turn every save into a lint error.
 *
 * The package lives in the plugin's node_modules, since that is where the only
 * package.json is, so resolve it from there rather than from this directory.
 *
 * Note that `prettier` here is wp-prettier, a fork carrying the `parenSpacing`
 * option behind WordPress's `foo( bar )` style. Editors that format with stock
 * prettier will ignore that option and fight ESLint; see .vscode/settings.json.
 */

const path = require( 'path' );

module.exports = require( require.resolve( '@wordpress/prettier-config', {
	paths: [ path.join( __dirname, 'plugin', 'hoobert' ) ],
} ) );
