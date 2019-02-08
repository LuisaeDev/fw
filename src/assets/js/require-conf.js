require([ 'fw', 'fw/helpers' ], function(fw, helpers) {

	// Verifica los módulos definidos para pre-cargar, cargar y asincrónicos. Los módulos pueden ser definidos como un string o array
	if ('async' in fw.vars) {
		if (helpers.isString(fw.vars.async)) {
			fw.vars.async = [ fw.vars.async ];
		}
	} else {
		fw.vars.async = [];
	}
	if ('preload' in fw.vars) {
		if (helpers.isString(fw.vars.preload)) {
			fw.vars.preload = [ fw.vars.preload ];
		}
	} else {
		fw.vars.preload = [];
	}
	if ('require' in fw.vars) {
		if (helpers.isString(fw.vars.require)) {
			fw.vars.require = [ fw.vars.require ];
		}
	} else {
		fw.vars.require = [];
	}

	// Configuración de RequireJS
	requirejs.config({
		waitSeconds: 20,

		// URL base
		baseUrl: fw.baseUrl('assets/js'),

		// Directorios
		paths: {
			'@templates': 	fw.baseUrl('assets/templates'),
			'nunjucks': 	fw.baseUrl('components/nunjucks/browser/nunjucks.min'),
			'domReady': 	fw.baseUrl('components/domReady/domReady')
		},
		shim: {
			 'nunjucks': { deps: [ '@templates/bundle' ] }
		},

		// Carga las dependencias iniciales pasadas por 'preload'
		deps: fw.vars.preload,

		// Carga de dependencias a cargar pasadas por 'require'
		callback: function() {
			require(fw.vars.require, function() {

				// Obtiene los módulos por los argumentos cargados y los inicializa
				var mods = Array.prototype.slice.call(arguments);
				mods.forEach(function(mod) {
					if ((mod !== undefined) && ('initialize' in mod) && (typeof mod['initialize'] == 'function')) {
						mod.initialize();
					} else if ((mod !== undefined) && (jQuery.isEmptyObject(mod.prototype)) && (typeof mod == 'function')) {
						mod();
					}
				});
			});
		}
	});

	// Carga las dependencias asíncronas
	if (fw.vars.async.length > 0) {
		require(fw.vars.async);
	}
});
