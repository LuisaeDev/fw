/**
 * Conjunto de métodos de utilería para el manejo de datos.
 */
define('fw/helpers', function() {

	/**
	 * String para métodos de base64.
	 * 
	 * @type {String}
	 */
	var strBase64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";

	/**
	 * API del módulo.
	 */
	return {
		
		/**
		 * Escapa caracteres especiales de HTML.
		 * 
		 * @param  {String} value Cadena a escapar
		 * @return {String}
		 */
		htmlEntities: function(text) {
			return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
		},
	
		/**
		 * Codifica un string en utf8.
		 * 
		 * @param  {String} value Cadena a codificar
		 * @return {String}
		 */
		utf8Encode: function(string) {
			string = string.replace(/\r\n/g,"\n");
			var utftext = "";
			for (var n = 0; n < string.length; n++) {
				var c = string.charCodeAt(n);
				if (c < 128) {
					utftext += String.fromCharCode(c);
				}
				else if((c > 127) && (c < 2048)) {
					utftext += String.fromCharCode((c >> 6) | 192);
					utftext += String.fromCharCode((c & 63) | 128);
				}
				else {
					utftext += String.fromCharCode((c >> 12) | 224);
					utftext += String.fromCharCode(((c >> 6) & 63) | 128);
					utftext += String.fromCharCode((c & 63) | 128);
				}

			}
			return utftext;
		},
		
		/**
		 * Decodifica un string codificado en utf8.
		 * 
		 * @param  {String} value Cadena codificada
		 * @return {String}
		 */
		utf8Decode: function(utftext) {
			var string = "";
			var i = 0;
			var c = c1 = c2 = 0;
			while ( i < utftext.length ) {

				c = utftext.charCodeAt(i);

				if (c < 128) {
					string += String.fromCharCode(c);
					i++;
				}
				else if((c > 191) && (c < 224)) {
					c2 = utftext.charCodeAt(i+1);
					string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
					i += 2;
				}
				else {
					c2 = utftext.charCodeAt(i+1);
					c3 = utftext.charCodeAt(i+2);
					string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
					i += 3;
				}
			}
			return string;
		},

		/**
		 * Codifica un string en base64.
		 * 
		 * @param  {String} value Cadena a codificar
		 * @return {String}
		 */
		base64Encode: function(value) {
			var output = "";
			var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
			var i = 0;
			value = this.utf8Encode(value);
			while (i < value.length) {
				chr1 = value.charCodeAt(i++);
				chr2 = value.charCodeAt(i++);
				chr3 = value.charCodeAt(i++);

				enc1 = chr1 >> 2;
				enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
				enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
				enc4 = chr3 & 63;

				if (isNaN(chr2)) {
					enc3 = enc4 = 64;
				} else if (isNaN(chr3)) {
					enc4 = 64;
				}
				output = output +
				strBase64.charAt(enc1) + strBase64.charAt(enc2) +
				strBase64.charAt(enc3) + strBase64.charAt(enc4);
			}
			return output;
		},
		
		/**
		 * Decodifica un string codificado en base64.
		 * 
		 * @param  {String} value Cadena codificada
		 * @return {String}
		 */
		base64Decode: function(value) {
			var output = "";
			var chr1, chr2, chr3;
			var enc1, enc2, enc3, enc4;
			var i = 0;
			value = value.replace(/[^A-Za-z0-9\+\/\=]/g, "");
			while (i < value.length) {
				enc1 = strBase64.indexOf(value.charAt(i++));
				enc2 = strBase64.indexOf(value.charAt(i++));
				enc3 = strBase64.indexOf(value.charAt(i++));
				enc4 = strBase64.indexOf(value.charAt(i++));

				chr1 = (enc1 << 2) | (enc2 >> 4);
				chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
				chr3 = ((enc3 & 3) << 6) | enc4;

				output = output + String.fromCharCode(chr1);

				if (enc3 != 64) {
					output = output + String.fromCharCode(chr2);
				}
				if (enc4 != 64) {
					output = output + String.fromCharCode(chr3);
				}
			}
			output = this.utf8Decode(output);
			return output;
		},

		/**
		 * Codifica un objeto de javascript en un string JSON.
		 * 
		 * @param  {Object} value Objeto a codificar
		 * @return {String}
		 */
		jsonEncode: function(value) {
			return JSON.stringify(value);
		},

		/**
		 * Decodifica un string JSON a un objeto de javascript.
		 * 
		 * @param  {String} value Valor en formato JSON
		 * @return {Object}
		 */
		jsonDecode: function(value) {
			return JSON.parse(value);
		},

		/**
		 * Verifica si un valor está definido en un array.
		 * 
		 * @param  {Array}   arrayValue  Array en el cual se realizará la búsqueda
		 * @param  {mixed}   searchValue Valor a buscar
		 * @return {Boolean}
		 */
		inArray: function(arrayValue, searchValue) {
			if (arrayValue.indexOf(searchValue) == -1) {
				return false;
			} else {
				return true;
			}
		},

		/**
		 * Verifica si un valor es de tipo JSON.
		 * 
		 * @param  {mixed}   value Valor a verificar
		 * @return {Boolean}
		 */
		isJSON: function(value) {
			try {

				JSON.parse(value);
				return true;

			} catch(e) {
				return false;
			}
		},

		/**
		 * Verifica si un valor es de tipo string.
		 * 
		 * @param  {mixed}   value Valor a verificar
		 * @return {Boolean}
		 */
		isString: function(value) {
			if (Object.prototype.toString.call(value) == '[object String]') {
				return true;
			} else {
				return false;
			}
		},

		/**
		 * Verifica si un valor es de tipo array.
		 * 
		 * @param  {mixed}   value Valor a verificar
		 * @return {Boolean}
		 */
		isArray: function(value) {
			if (Object.prototype.toString.call(value) == '[object Array]') {
				return true;
			} else {
				return false;
			}
		},

		/**
		 * Verifica si un valor es de tipo objeto.
		 * 
		 * @param  {mixed}   value Valor a verificar
		 * @return {Boolean}
		 */
		isObject: function(value) {
			if (Object.prototype.toString.call(value) == '[object Object]') {
				return true;
			} else {
				return false;
			}
		},

		/**
		 * Verifica si un valor es de tipo función.
		 * 
		 * @param  {mixed}   value Valor a verificar
		 * @return {Boolean}
		 */
		isFunction: function(value) {
			if (Object.prototype.toString.call(value) == '[object Function]') {
				return true;
			} else {
				return false;
			}
		},

		/**
		 * Verifica si un valor es un objeto de tipo File.
		 * 
		 * @param  {mixed}   value Valor a verificar
		 * @return {Boolean}
		 */
		isFile: function(value) {
			if (Object.prototype.toString.call(value) == '[object File]') {
				return true;
			} else {
				return false;
			}
		},

		/**
		 * Devuelve una variable especificada en la URL.
		 * 
		 * @param  {String}          varName Nombre de la variable
		 * @return {mixed|undefined} Valor de la variable o undefined si no está especificada
		 */
		getURLVar: function(varName) {
			varName = varName.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
			var regex = new RegExp("[\\?&]" + varName + "=([^&#]*)"),
			results = regex.exec(location.search);
			if (!results) {
				return undefined;
			} else {
				return decodeURIComponent(results[1].replace(/\+/g, " "));
			}
		},

		/**
		 * Ejecuta una serie de funciones secuencialmente.
		 * 
		 * @param  {Array} Array de funciones
		 * @return {void}
		 */
		callSecuence: function(functions) {
			var i = 0;
			
			// Callback pasado en cada función para llamar la función siguiente
			var next = function() {
				if (i < functions.length - 1) {
					i++;
					functions[i](next);
				}
			}

			// Ejecuta la primer función
			functions[i](next);
		}
	}
});