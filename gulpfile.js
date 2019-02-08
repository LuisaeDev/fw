/**
 * Plugins Gulp
 */
	var gulp 			= require('gulp');
	var gulpConcat 		= require('gulp-concat');
	var gulpNunjucks 	= require('gulp-nunjucks');
	var gulpUglify 		= require('gulp-uglify');
	var gulpCssmin 		= require('gulp-cssmin');
	var gulpIf 			= require('gulp-if');
	var gulpImage 		= require('gulp-image');
	var gulpLess 		= require('gulp-less');
	var gulpRename 		= require('gulp-rename');
	var gulpSourcemaps 	= require('gulp-sourcemaps');
	var gulpStylus 		= require('gulp-stylus');
	var mainBowerFiles 	= require('main-bower-files');
	var del 			= require('del');
	var mkdirp 			= require('mkdirp');
	var fs 				= require('fs');
	var pump            = require('pump');

/**
 * Variables
 */

	// Argumentos pasados por CLI
	var args = require('yargs').argv;

	// Importa los parámetros generales
	var params = require('./gulpfile-params.js');

/**
 * Tareas
 */

	/**
	 * Construye en modo de desarrollo o distribución
	 */
	gulp.task('build', function() {
		if (args.dist) {
			build('dist');
		} else {
			build('dev');
		}
	});

	/**
	 * Monitorea cambios y construye en modo de desarrollo
	 */
	gulp.task('watch', function() {

		// Directorios del framework
		if ((args.fw) || (args.all)) {
			gulp.watch(params.fw, function(evt) {
				buildFile('dev', evt.path);
			});
		}

		// Directorio namespace
		if ((args.namespaces) || (args.all)) {
			gulp.watch(params.namespaces, function(evt) {
				buildFile('dev', evt.path);
			});
		}

		// Directorio constrollers
		if ((args.controllers) || (args.all)) {
			gulp.watch(params.controllers, function(evt) {
				buildFile('dev', evt.path);
			});
		}

		// Directorio views
		if ((args.views) || (args.all)) {
			gulp.watch(params.views, function(evt) {
				buildFile('dev', evt.path);
			});
		}

		// Directorio resources
		if ((args.resources) || (args.all)) {
			gulp.watch(params.resources, function(evt) {
				buildFile('dev', evt.path);
			});
		}

		// Directorio lang
		if ((args.lang) || (args.all)) {
			gulp.watch(params.lang, function(evt) {
				buildFile('dev', evt.path);
			});
		}

		// Directorio assets
		if ((args.assets) || (args.all)) {

			// Monitorea cambios de estilos de css
			gulp.watch('src/assets/css/**/{*.styl,*.css}', function(evt) {
				buildAssets('dev', 'stylus', evt.path);
			});

			// Monitorea cambios de javascript
			gulp.watch('src/assets/js/**/*.js', function(evt) {
				buildAssets('dev', 'js', evt.path);
			});

			// Monitorea cambios de nunjucks
			gulp.watch('src/assets/templates/**/*.njk', function(evt) {
				buildAssets('dev', 'nunjucks', evt.path);
			});

			// Monitorea cambios de imágenes
			gulp.watch(params.assets.images, function(evt) {
				buildAssets('dev', 'images', evt.path);
			});

			// Monitorea cambios de otros archivos
			gulp.watch(params.assets.files, function(evt) {
				buildAssets('dev', 'files', evt.path);
			});
		}
	});

	/**
	 * Realiza el deploy de toda la aplicación web
	 */
	gulp.task('deploy', function() {
		args.all = true;
		build('dist');
	});

/**
 * Funciones de procesamiento
 */

	/**
	 * Construye en modo de desarrollo o distribución
	 * @param {string} dest Directorio de destino
	 */
	function build(dest) {

		// Borra el contenido del directorio de destino
		if (args.all == true) {
			empty('' + dest);
		}

		// Construye el framework
		if ((args.fw) || (args.all)) {
			empty('' + dest + '/fw');
			empty('' + dest + '/conf');
			empty('' + dest, false);

			// Secuencia de streams
			runSequence([

				// Exporta todos los archivos del framework
				function() {
					return buildDir(dest, params.fw);
				}
			]);
		}

		// Construye el directorio namespaces
		if ((args.namespaces) || (args.all)) {
			empty('' + dest + '/namespaces');
			buildDir(dest, params.namespaces);
		}

		// Construye el directorio controllers
		if ((args.controllers) || (args.all)) {
			empty('' + dest + '/controllers');
			buildDir(dest, params.controllers);
		}

		// Construye el directorio views
		if ((args.views) || (args.all)) {
			empty('' + dest + '/views');
			buildDir(dest, params.views);
		}

		// Construye el directorio resources
		if ((args.resources) || (args.all)) {
			empty('' + dest + '/resources');
			buildDir(dest, params.resources);
		}

		// Construye el directorio lang
		if ((args.lang) || (args.all)) {
			empty('' + dest + '/lang');
			buildDir(dest, params.lang);
		}

		// Construye el directorio assets
		if ((args.assets) || (args.all)) {
			empty('' + dest + '/assets');
			buildAssets(dest, 'stylus');
			buildAssets(dest, 'js');
			buildAssets(dest, 'nunjucks');
			buildAssets(dest, 'images');
			buildAssets(dest, 'files');
		}

		// Exporta las librerías vendor
		if ((args.vendor) || (args.all)) {
			buildVendor(dest);
		}

		// Exporta las librerías components
		if ((args.components) || (args.all)) {
			buildComponents(dest);
		}

		// Crea directorios del framework
		for (var i in params.mkdir) {
			mkdirp(dest +  '/' + params.mkdir[i]);
		}
	};

	/**
	 * Construye un directorio del framework
	 * @param  {string}       dest Directorio de destino
	 * @param  {string|array} src  Directorio fuente
	 */
	function buildDir(dest, src) {

		// Directorio de destino
		dest = '' + dest +'/';

		// Exporta el directorio
		return gulp.src(src, {
			base: 'src/',
			dot: true
		}).pipe(gulp.dest(dest));
	}

	/**
	 * Actualiza un archivo modificado
	 * @param  {string} dest Directorio de destino
	 * @param  {string} src  Directorio del archivo modificado
	 */
	function buildFile(dest, fileChanged) {

		// Directorio de destino
		dest = '' + dest +'/';

		// Exporta el archivo al directorio de destino
		gulp.src(fileChanged, { base: 'src/' }).pipe(gulp.dest(dest));
	}

	/**
	 * Construye el directorio assets
	 * @param {string} 				dest        Directorio de destino
	 * @param {string} 				processType Tipo de recurso a procesar
	 * @param {[string, undefined]} fileChanged Directorio de un archivo específico a exportar
	 */
	function buildAssets(dest, processType, fileChanged) {

		// Directorio fuente y destino
		switch(dest) {
			case 'dev':
				var dist = false;
				var dest = 'dev/assets';
				break;

			case 'dist':
				var dist = true;
				var dest = 'dist/assets';
				break;
		}

		// Se identifica el tipo de proceso
		switch(processType) {
			case 'stylus':
				for (i in params.assets.stylus.concat) {
					gulp.src(params.assets.stylus.concat[i].files)
						.pipe(gulpIf((!dist), gulpSourcemaps.init()))
						.pipe(gulpStylus({
							nib: 	  true,
							compress: (dist == true),
							paths:    [ 'src/assets/css' ],
							import:   'conf.styl'
						}))
						.pipe(gulpConcat(i))
						.pipe(gulpIf((!dist), gulpSourcemaps.write({ sourceRoot: params.assets.stylus.concat[i].sourceMap })))
						.pipe(gulp.dest(dest + '/css'));
				}
				gulp.src(params.assets.stylus.src, { base: 'src/assets/css' })
					.pipe(gulpIf((!dist), gulpSourcemaps.init()))
					.pipe(gulpStylus({
						nib: 	  true,
						compress: (dist == true),
						paths:    [ 'src/assets/css' ],
						import:   'conf.styl'
					}))
					.pipe(gulp.dest(dest + '/css'));
				break;

			case 'js':
				for (i in params.assets.js.concat) {
					pump([
						gulp.src(params.assets.js.concat[i].files),
						gulpIf((!dist), gulpSourcemaps.init()),
						gulpConcat(i),
						gulpIf(dist, gulpUglify()),
						gulpIf((!dist), gulpSourcemaps.write({ sourceRoot: params.assets.js.concat[i].sourceMap })),
						gulp.dest(dest + '/js')
					], function(err) {
						console.log(err);
					});
				}
				pump([
					gulp.src(params.assets.js.src, { base: 'src/assets/js' }),
					gulpIf(dist, gulpUglify()),
					gulp.dest(dest + '/js')
				], function(err) {
					console.log(err);
				});
				break;

			case 'nunjucks':
				for (i in params.assets.nunjucks.concat) {
					gulp.src(params.assets.nunjucks.concat[i].files, { base: params.assets.nunjucks.concat[i].base })
						.pipe(gulpNunjucks.precompile({
							name: function(file) {
								name = file.relative;
								name = name.replace(/\\/g, '/');
								// name = name.substring(name.lastIndexOf('/') + 1);
								name = name.replace('.njk', '');
								return name;
							}
						}))
						.pipe(gulpIf((!dist), gulpSourcemaps.init()))
						.pipe(gulpConcat(i))
						.pipe(gulpIf(dist, gulpUglify()))
						.pipe(gulpIf((!dist), gulpSourcemaps.write({ sourceRoot: params.assets.nunjucks.concat[i].sourceMap })))
						.pipe(gulpRename(function (path) {
							path.extname = '.js';
						}))
						.pipe(gulp.dest(dest + '/templates'));
				}
				gulp.src(params.assets.nunjucks.src, { base: './src/assets/templates' })
					.pipe(gulpNunjucks.precompile({
						name: function(file) {
							name = file.relative;
							name = name.replace(/\\/g, '/');
							// name = name.substring(name.lastIndexOf('/') + 1);
							name = name.replace('.njk', '');
							return name;
						}
					}))
					.pipe(gulpIf(dist, gulpUglify()))
					.pipe(gulpRename(function (path) {
						path.extname = '.js';
					}))
					.pipe(gulp.dest(dest + '/templates'));
				break;

			case 'images':
				if (fileChanged) {
					var src = fileChanged;
				} else {
					var src = params.assets.images;
				}
				gulp.src(src, { base: 'src/assets' })
					.pipe(gulpIf(dist, gulpImage()))
					.pipe(gulp.dest(dest));
				break;

			case 'files':
				if (fileChanged) {
					var src = fileChanged;
				} else {
					var src = params.assets.files;
				}
				gulp.src(src, { base: 'src/assets' })
					.pipe(gulp.dest(dest));
				break;
		}
	}

	/**
	 * Exporta el directorio vendor
	 * @param {string} dest Directorio de destino
	 */
	function buildVendor(dest) {

		// Exporta el directorio @vendor
		gulp.src('vendor/**/*.*', { base: 'vendor' })
			.pipe(gulp.dest('' + dest + '/vendor'));
	}

	/**
	 * Procesa y exporta el directorio components
	 * @param {string} dest Directorio de destino
	 */
	function buildComponents(dest) {

		// Directorio de destino
		switch(dest) {
			case 'dev':
				dest = 'dev/components';
				var dist = false;
				break;

			case 'dist':
				dest = 'dist/components';
				var dist = true;
				break;
		}

		// Remueve archivos y folders en el directorio de destino
		empty(dest);

		// Secuencia de streams
		runSequence([

			// Exporta todos los archivos principales de las librerías
			function() {
				return gulp.src(mainBowerFiles({
						overrides: params.components.bowerOverrides
					}), { base: 'bower_components' })
					.pipe(gulp.dest(dest));
			},

			// Minifíca todos los archivos js
			function() {
				if (dist) {
					return gulp.src(dest + '/**/*.js')
						.pipe(gulpUglify())
						.pipe(gulp.dest(dest));
				}
			},

			// Concatena los archivos principales de javascript
			function() {
				return gulp.src(params.components.concat.js)
					.pipe(gulpConcat('components.js'))
					.pipe(gulpIf(dist, gulpUglify()))
					.pipe(gulp.dest(dest));
			},

			// Minifíca todos los archivos de css
			function() {
				if (dist) {
					return gulp.src(dest + '/**/*.css')
						.pipe(gulpCssmin())
						.pipe(gulp.dest(dest));
				}
			},

			// Concatena los archivos principales de css
			function() {
				return gulp.src(params.components.concat.css)
					.pipe(gulpConcat('components.css'))
					.pipe(gulpIf(dist, gulpCssmin()))
					.pipe(gulp.dest(dest));
			}
		]);
	}

/**
 * Utilería
 */

	/**
	 * Corre secuencialmente una serie de streams
	 * @param {array}  streams Array de funciones de streams por ejecutar
	 * @param {number} i       Índice del stream a ejecutar
	 */
	function runSequence(streams, i) {

		// Verifica si se ha definido la iteración
		if (i == undefined) {
			i = 0;
		}

		// Ejecuta la función que contiene el stream de la iteración actual
		var stream = streams[i]();

		// Aumenta una iteración
		i++;

		// Si la función devolvió un stram
		if (stream) {

			// Escucha el evento 'end' para ejecutar el stream siguiente
			stream.on('end', function() {
				if (i < streams.length) {
					runSequence(streams, i)
				}
			});

		// Ejecuta el stream siguiente
		} else {
			if (i < streams.length) {
				runSequence(streams, i)
			}
		}
	}

	/**
	 * Remueve archivos y folders en un directorio
	 * @param {path} path Directorio a afectar
	 */
	function empty(path, subFolders) {
		if (subFolders == false) {
			del.sync([path + '/*.*', '!' + path], { force: true });
		} else {
			del.sync([path + '/**/*.*', path + '/**/', '!' + path], { force: true });
		}
	}

	/**
	 * Verifica si un valor es de tipo string
	 * @param  {mixed}   value Valor a verificar
	 * @return {boolean}
	 */
	function isString(value) {
		if (Object.prototype.toString.call(value) == '[object String]') {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Verifica si un valor es de tipo objeto
	 * @param  {mixed}   value Valor a verificar
	 * @return {boolean}
	 */
	function isObject(value) {
		if (Object.prototype.toString.call(value) == '[object Object]') {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Verifica si un valor es de tipo array
	 * @param  {mixed}   value Valor a verificar
	 * @return {boolean}
	 */
	function isArray(value) {
		if (Object.prototype.toString.call(value) == '[object Array]') {
			return true;
		} else {
			return false;
		}
	}
