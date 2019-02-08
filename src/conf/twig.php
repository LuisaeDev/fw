<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

/**
 * Configuraciones generales de twig.
 */

namespace Fw;

return array(

	// Zona horaria default para twig
	'timezone' => function() {
		return Conf::getTimezone();
	},

	// Formato de fechas utilizado por twig
	'dateformat' => function() {
		return 'd/m/Y';
	},

	// Variables extendidas
	'vars' => array(),

	// Funciones extendidas
	'functions' => array(

		// Configuraciones del Framework pasadas al frontend
		'frontConf' => function() {
			return json_encode(array(
				'vars'    => Conf::getFrontVars(),
				'logged'  => Auth::isLogged(),
				'locale'  => Conf::getLocale(),
				'baseUrl' => Conf::getPath('baseUrl')
			));
		},

		// Devuelve un atributo (columna) del usuario en sesión
		'user' => function($attr) {
			return Auth::getCurrentUser()->{$attr};
		},

		// Devuelve una variable dinámica
		'get' => function($var) {
			switch ($var) {
				case 'auth_token':     return Auth::getToken();
				case 'locale':         return Conf::getLocale();
				case 'request_route':  return Http::getRequest()->route;
				case 'request_url':    return Http::getRequest()->url;
				case 'request_params': return Http::getRequest()->params;
				default: return null;
			}
		},

		// Devuelve una instancia
		'instance' => function($name) {
			return Conf::getInstance($name);
		},

		// Confirma si hay una sesión de usuario
		'isLogged' => function() {
			return Auth::isLogged();
		},

		// Parser de URL's
		'url' => function($url, $version = false) {
			return Fw::url($url, $version);
		},

		// Parser de URL's y la devuelve codificada
		'urlencode' => function($url, $version = false) {
			return urlencode(Fw::url($url, $version));
		},

		// Parser de URL's con path @baseUrl
		'baseUrl' => function($url = null, $version = false) {
			return baseUrl($url, $version);
		},

		// Devuelve un paquete de idioma
		'langPackage' => function($path, $params = null) {
			return new LangPackage($path, $params);
		},

		// Codifica un valor en base64
		'base64' => function($str) {
			return base64_encode($str);
		}
	),

	// Filtros extendidos
	'filters' => array(

		// Parser de URL's
		'url' => function($url) {
			return Fw::url($url);
		},

		// URL's con path @baseUrl
		'baseUrl' => function($url) {
			return baseUrl($url);
		},

		// Codifica un valor en base64
		'base64' => function($str) {
			return base64_encode($str);
		}
	)
);
?>
