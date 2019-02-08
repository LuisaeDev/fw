/**
 * Manejador de paquetes de idioma.
 */
define('fw/LangPackage', [ 'fw/helpers' ], function(helpers) {

	/**
	 * Almacena los paquetes cargados.
	 * 
	 * @type {Object}
	 */
	var packages = {};

	/**
	 * Constructor de la clase.
	 * 
	 * @param {String} name Identificador del paquete
	 */
	function LangPackage(name) {

		// Verifica si el paquete ya fue cargado
		if (name in packages) {
			this.package = packages[name];
			return;
		}

		// Verifica si el paquete está especificado en el DOM
		var package = $('#' + name).html();
		if (package) {
			this.package = packages[name] = helpers.jsonDecode(package);
		} else {
			this.package = {};
		}
	}

	/**
	 * Devuelve un caption del paquete.
	 * 
	 * @param  {String} 		  path   Ruta del caption
	 * @param  {Object|undefined} params Parámetros a reemplazar en el caption
	 * 
	 * @return {String|undefined} Caption procesado
	 */
	LangPackage.prototype.get = function(path, params) {

		// Remueve de la ruta los delimitadores al inicio y final y los espacios en blanco
		path = path.trim();
		if (path.substr(0, 1) == '.') {
			path = path.substr(1);
		}
		if (path.substr(-1) == '.') {
			path = path.slice(0,-1);
		}

		// Obtiene el caption
		if (path in this.package) {
			var caption = caption = this.package[path];
		} else {
			return '';
		}

		// Se identifican expresiones de tipo "{ $var, a|b|c }"
		if (caption.indexOf('{') >= 0) {
			var regex = /\{[\s]*\$(\w+(?:\-\w+)*)+[\s]*\,([\|*|\$*\w+\W*\s*]+)\}/gi;
			var match;
			while (match = regex.exec(caption)) {

				// Verifica si la variable está especificada en params
				if ((typeof params == 'object') && (match[1] in params)) {
					var result = this._resolveAmmount(params[match[1]], match[2].split('|'));
				} else {
					var result = '';
				}
				caption = caption.substr(0, match.index) + result + caption.substr(match.index + match[0].length);
			}
		}

		// Se identifican variables de tipo "$var"
		if (caption.indexOf('$') >= 0) {
			var regex = /\$(\w+(?:\-\w+)*)/gi;
			var match;
			while (match = regex.exec(caption)) {

				// Verifica si la variable está especificada en params
				if ((typeof params == 'object') && (match[1] in params)) {
					var result = params[match[1]];
				} else {
					var result = '';
				}
				caption = caption.substr(0, match.index) + result + caption.substr(match.index + match[1].length + 1);
			}
		}

		// Retorna el caption procesado
		return caption;
	}

	/**
	 * Resuelve una expresión de tipo ammount.
	 * 
	 * @param  {Number} value    Valor a evaluar
	 * @param  {Array}  captions Opciones de captions
	 * 
	 * @return {String} Caption resultante
	 */
	LangPackage.prototype._resolveAmmount = function(value, captions) {
		var caption = '';

		// Evalua el valor y selecciona un caption
		if ((value > 1) && (captions[0])) {
			caption = captions[0];
		} else if ((value == 1) && (captions[1])) {
			caption = captions[1];
		} else if ((value <= 0) && (captions[2])) {
			caption = captions[2];
		}

		// Retorna el caption
		return caption.trim();
	}

	/**
	 * Devuelve la clase.
	 */
	return LangPackage;
});