/**
 * Administra el almacenamiento del lado cliente.
 */
define('fw/storage', [ 'fw/helpers' ], function(helpers) {

	/**
	 * Almacena una variable en un contexto de almacenamiento en el lado cliente.
	 * 
	 * @param  {String} context Contexto de almacenamiento 'local' o 'session'
	 * @param  {String} varName Nombre de la variable
	 * @param  {mixed}  value   Valor de la variable a almacenar
	 * @return {void}
	 */
	function set(context, varName, value) {
		
		// Si el objeto es de tipo Object lo serializa en JSON
		if ((value) && (helpers.isObject(value)))  {
			value = JSON.stringify(value);
		}

		// Verifica el context en donde se almacenará la variable
		switch (context) {
			case 'local':
				window.localStorage[varName] = value;
				break;

			case 'session':
				window.sessionStorage[varName] = value;
				break;
		}
	}

	/**
	 * Obtiene una variable en un contexto de almacenamiento en el lado cliente.
	 * 
	 * @param  {String} context Contexto de almacenamiento 'local' o 'session'
	 * @param  {String} varName Nombre de la variable
	 * @return {mixed}          Valor de la variable obtenida
	 */
	function get(context, varName) {
		switch (context) {
			case 'local':
				value = localStorage[varName];
				break;

			case 'session':
				value = sessionStorage[varName];
				break;
		}

		// Verifica si el valor obtenido es de tipo JSON y lo deserializa
		if ((value) && (helpers.isJSON(value))) {
			return JSON.parse(value);
		} else {
			return value;
		}
	}

	/**
	 * Remueve una variable en un contexto de almacenamiento en el lado cliente.
	 * 
	 * @param  {String} context Contexto de almacenamiento 'local' o 'session'
	 * @param  {String} varName Nombre de la variable
	 * @return {void}
	 */
	function unset(context, varName) {
		switch (context) {
			case 'local':
				localStorage.removeItem(varName);
				break;

			case 'session':
				sessionStorage.removeItem(varName);
				break;
		}
	}

	/**
	 * Obtiene una cookie.
	 * 
	 * @param  {String} cookieName Nombre de la cookie
	 * @return {mixed}
	 */
	function getCookie(cookieName) {
		var cookieValue = document.cookie;
		var c_start = cookieValue.indexOf(' ' + cookieName + '=');
		if (c_start == -1) {
		  c_start = cookieValue.indexOf(cookieName + '=');
		}
		if (c_start == -1) {
			cookieValue = null;
		} else {
			c_start = cookieValue.indexOf('=', c_start) + 1;
			var c_end = cookieValue.indexOf(';', c_start);
			if (c_end == -1) {
				c_end = cookieValue.length;
			}
			cookieValue = unescape(cookieValue.substring(c_start,c_end));
		}
		return cookieValue;
	}

	/**
	 * API del módulo.
	 */
	return {

		// Almacenamiento en sesión
		session: {

			/**
			 * Almacena una variable en sessionStorage en el lado cliente.
			 * 
			 * @param  {String} varName Nombre de la variable
			 * @param  {mixed}  value   Valor de la variable a almacenar
			 * @return {void}
			 */
			set: function(varName, value) {
				set('session', varName, value);
			},

			/**
			 * Obtiene una variable de sessionStorage.
			 * 
			 * @param  {String} varName Nombre de la variable
			 * @return {mixed}
			 */
			get: function(varName) {
				return get('session', varName);
			},

			/**
			 * Remueve una variable de sessionStorage.
			 * 
			 * @param  {String} varName Nombre de la variable
			 * @return {void}
			 */
			unset: function(varName) {
				unset('session', varName);
			},

			/**
			 * Verifica si una variable de sessionStorage existe.
			 * 
			 * @param  {String} varName Nombre de la variable
			 * @return {Boolean}
			 */
			exists: function(varName) {
				if (get('session', varName) !== undefined) {
					return true;
				} else {
					return false;
				}
			}
		},

		// Almacenamiento en local
		local: {

			/**
			 * Almacena una variable en localStorage en el lado cliente.
			 * 
			 * @param  {String} varName Nombre de la variable
			 * @param  {mixed}  value   Valor de la variable a almacenar
			 * @return {void}
			 */
			set: function(varName, value) {
				set('local', varName, value);
			},

			/**
			 * Obtiene una variable de localStorage.
			 * 
			 * @param  {String} varName Nombre de la variable
			 * @return {mixed}
			 */
			get: function(varName) {
				return get('local', varName);
			},

			/**
			 * Remueve una variable de localStorage.
			 * 
			 * @param  {String} varName Nombre de la variable
			 * @return {void}
			 */
			unset: function(varName) {
				unset('local', varName);
			},

			/**
			 * Verifica si una variable de localStorage existe.
			 * 
			 * @param  {String} varName Nombre de la variable
			 * @return {Boolean}
			 */
			exists: function(varName) {
				if (get('local', varName) !== undefined) {
					return true;
				} else {
					return false;
				}
			}
		},

		// Almacenamiento en cookies
		cookie: {

			/**
			 * Define una cookie.
			 * 
			 * @param  {String} 		  cookieName Nombre de la cookie
			 * @param  {mixed}  		  value      Valor de la cookie a almacenar
			 * @param  {Number|undefined} expDays    Días de expiración para la cookie
			 * @return {void}
			 */
			set: function(cookieName, value, expDays) {
				var expDate = new Date();
				expDate.setDate(expDate.getDate() + expDays);
				var cookieValue = escape(value) + ((expDays == null) ? '' : '; expires=' + expDate.toUTCString());
				document.cookie = cookieName + '=' + cookieValue;
			},

			/**
			 * Obtiene una cookie.
			 * 
			 * @param  {String} cookieName Nombre de la cookie
			 * @return {mixed}
			 */
			get: function(cookieName) {
				return getCookie(cookieName);
			},

			/**
			 * Remueve una cookie.
			 * 
			 * @param  {String} cookieName Nombre de la cookie
			 * @return {void}
			 */
			unset: function(varName) {
				document.cookie = varName + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
			},

			/**
			 * Verifica si una cookie existe.
			 * 
			 * @param  {String} varName Nombre de la cookie
			 * @return {Boolean}
			 */
			exists: function(varName) {
				if ((getCookie(varName) == null) && (getCookie(varName) == undefined)) {
					return false;
				} else {
					return true;
				}
			}
		}
	}
});