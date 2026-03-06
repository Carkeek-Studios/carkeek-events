const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'events-archive/index': './src/events-archive/index.js',
		'event-editor/index':   './src/event-editor/index.js',
	},
};
