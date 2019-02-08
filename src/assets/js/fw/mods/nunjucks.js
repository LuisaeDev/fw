define('fw/nunjucks', [ 'fw', 'nunjucks' ], function(fw, nunjucks) {

	// Construye un entorno de Nunjucks
	var nunjucksEnv = new nunjucks.Environment(new nunjucks.WebLoader(fw.baseUrl('assets/templates')));

	// Extensión de filtros y funciones
	nunjucksEnv.addGlobal('_url', function(value) {
		return fw.url(value);
	});
	nunjucksEnv.addGlobal('_baseUrl', function(value) {
		return fw.baseUrl(value);
	});
	nunjucksEnv.addFilter('url', function(value) {
		return fw.url(value);
	});
	nunjucksEnv.addFilter('baseUrl', function(value) {
		return fw.baseUrl(value);
	});

	// Extiende el método compile al entorno
	nunjucksEnv.compile = function(str, opts) {
		return nunjucks.compile(str, nunjucksEnv, opts);
	};

	/**
	 * Retorna el entorno de Nunjucks
	 *
	 * @return {nunjucks.Environment}
	 */
	return nunjucksEnv;
});
