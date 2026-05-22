module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
	},
	globals: {
		smig: 'readonly',
		jQuery: 'readonly',
	},
	ignorePatterns: [ 'node_modules/', 'vendor/' ],
	rules: {
		// jQuery wrapper and admin AJAX patterns.
		'no-var': 'off',
		camelcase: 'off',
		'no-alert': 'off',
		'no-redeclare': 'off',
		'jsdoc/check-tag-names': 'off',
		'jsdoc/empty-tags': 'off',
	},
};
