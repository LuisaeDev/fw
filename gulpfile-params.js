module.exports = {
	fw: [
		"./src/*",
		"./src/fw/**/*.*",
		"./src/conf/**/*.*"
	],
	namespaces:  "./src/namespaces/**/*.php",
	controllers: "./src/controllers/**/*.php",
	views: 		 "./src/views/**/*.twig",
	resources: 	 "./src/resources/**/*.*",
	lang: 	     "./src/lang/**/*.*",
	assets: 	{
		stylus: {
			concat: {
				'styles.css': {
					files: [
						'./src/assets/css/styles.styl',
						'./src/assets/css/popups.styl',
						'!./src/assets/css/conf.styl'
					]
				}
			},
			src: [
				'./src/assets/css/**/*.styl',
				'!./src/assets/css/styles.styl',
				'!./src/assets/css/popups.styl',
				'!./src/assets/css/conf.styl'
			]
		},
		js: {
			concat: {
				'fw.js': {
					files: [
						'./src/assets/js/fw/class/*.js',
						'./src/assets/js/fw/mods/*.js',
						'./src/assets/js/fw/fw.js',
						'./src/assets/js/require-conf.js'
					],
					sourceMap: './src/assets/js/fw'
				}
			},
			src: [
				'./src/assets/js/**/*.js',
				'!./src/assets/js/fw/**/*.js',
				'!./src/assets/js/require-conf.js'
			]
		},
		nunjucks: 	{
			concat: {
				'bundle.js': {
					files: [
						'./src/assets/templates/bundle/**/*.njk'
					],
					base: './src/assets/templates/bundle/',
					sourceMap: './src/assets/templates/bundle/'
				}
			},
			src: [
				'./src/assets/templates/**/*.njk',
				'!./src/assets/templates/bundle/**/*.njk',
			]
		},
		images: './src/assets/img/**/{*.jpg,*.jpeg,*.png,*.gif}',
		files: 	[
			'./src/assets/**/*.*',
			'!./src/assets/css/**/*.*',
			'!./src/assets/js/**/*.*',
			'!./src/assets/templates/**/*.*',
			'!./src/assets/img/**/*.*'
		]
	},
	components: {
		"concat": {
			"js": [
				"./bower_components/jquery/dist/jquery.js",
				"./bower_components/underscore/underscore.js",
				"./bower_components/backbone/backbone.js",
				"./bower_components/requirejs/require.js"
			],
			"css": [
			]
		},
		"bowerOverrides": {
			"nunjucks": {
				"main": [
					"browser/nunjucks.min.js",
					"browser/nunjucks-slim.min.js"
				]
			}
		}
	},
	mkdir: [
		'repo',
		'cache'
	]
}
