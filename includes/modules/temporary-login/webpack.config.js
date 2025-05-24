const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'src/admin', 'index.tsx' ), // Changed process.cwd() to __dirname
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/dist' ), // Changed process.cwd() to __dirname and path to 'assets/dist'
	},
};
