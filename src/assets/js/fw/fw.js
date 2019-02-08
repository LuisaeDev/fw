define('fw', [
	'fw/helpers',
	'fw/storage',
	'fw/tz'
], function(helpers, storage, tz) {

	// Directorios
	var paths = {};

	// Obtiene las configuracones pasada por Voyager FW.
	var fwConf = window._fw;

	// Agrega en paths el directorio base
	paths['baseUrl'] = fwConf.baseUrl;

	// Verifica o define si las cookies relacionadas al timezone
	if (!storage.cookie.exists('fw_tz_offset')) {
		tz.setCookieTzOffset();
	}
	if (!storage.cookie.exists('fw_tz')) {
		tz.setCookieTz();
	}

	/**
	 * Parser de URL's.
	 *
	 * @param {String} url URL a procesar
	 *
	 * @return {String} Url procesada
	 */
	function parserURL(url) {

		// Si la URL comienza con '@'
		if (url.charAt(0) == '@') {

			// Se divide la URL por el caracter '/'
			url = url.split('/');

			// Obtiene el path
			var path = url[0].substr(1);
			if (path in paths) {
				path = paths[path];

				// Remueve la '/' al final del path si estaba definida
				if (path.substr(-1) == '/') {
					url[0] = path.slice(0, -1);
				} else {
					url[0] = path;
				}
			}

			// Construye nuevamente la URL
			url = url.join('/');
		}

		return url
	}

	/**
	 * Devuelve una url con su directorio base.
	 *
	 * @param  {String} url Url a la cual se agregará el directorio base
	 * @return {String}     Url con el directorio base
	 */
	function baseUrl(url) {
		if (url) {
			return parserURL('@baseUrl/' + url);
		} else {
			return paths['baseUrl'];
		}
	}

	// API del módulo.
	return {

		/**
		 * Objeto con variables definidas en el backend.
		 *
		 * {Object}
		 */
		vars: fwConf.vars,

		/**
		 * Define si existe una sesión de usuario.
		 *
		 * @return {Number}
		 */
		isLogged: fwConf.logged,

		/**
		 * Determina el idioma utilizado por el Framework
		 *
		 * @return {String}
		 */
		locale: fwConf.locale,

		/**
		 * Parser de URL's.
		 *
		 * @param {String} url URL a procesar
		 *
		 * @return {String} Url procesada
		 */
		url: parserURL,

		/**
		 * Devuelve una url con su directorio base.
		 *
		 * @param  {String} url Url a la cual se agregará el directorio base
		 * @return {String}     Url con el directorio base
		 */
		baseUrl: baseUrl,

		/**
		 * Redirige a una url.
		 *
		 * @param {String} url Url a redirigir
		 *
		 * @return {void}
		 */
		redirect: function(url) {
			window.location.replace(this.url(url));
		}
	};
});
