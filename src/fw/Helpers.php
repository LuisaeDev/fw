<?php

use Fw\Fw;
use Fw\Conf;
use Fw\Router;
use Fw\MainController;
use Fw\Http;
use Fw\Template;
use Fw\EventsHandler;


/**
 * Suscripción de uno o varios eventos.
 *
 * @param string   $eventName Nombre del evento a suscribir o varios eventos separados por comas
 * @param function $callback  Función $callback a llamar al emitir el evento
 *
 * @return void
 */
function onEvent($eventName, $callback) {
	EventsHandler::on($eventName, $callback);
}

/**
 * Emite un evento y llama a todas las funciones suscritas.
 *
 * @param string $eventName Nombre del evento
 *
 * @return void
 */
function triggerEvent($eventName) {
	call_user_func_array('EventsHandler::trigger', func_get_args());
}

/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

/**
 * Registra una ruta.
 *
 * @param string $route    Ruta a registrar
 * @param string $endpoint Definición del endpoint
 * @param string $tags     Definición de los tags del endpoint
 *
 * @return void
 */
function route($route, $endpoint, $tags = null) {
	Router::setRoute($route, $endpoint, $tags);
}

/**
 * Registra una seria de rutas.
 *
 * @param array  $routes Array de rutas a registrar { '{route}' => '{endpoint}', ... }
 * @param string $tags   Definición de los tags a definir en cada uno de los endpoints
 *
 * @return void
 */
function routes($routes, $tags = null) {
	Router::setRoutes($routes, $tags);
}

/**
 * Extiende un manejador de validación para el enrutador.
 *
 * Los manejadores de validación son especificados en los tags de los endpoints como un función validate( ... )
 * De estar especificado un validador en un tag, este será llamado desde MainController al tratar de validar la ejecución de un endpoint
 *
 * @param string   $name     Nombre del handler
 * @param function $callback Callback del handler
 *
 * @return void
 */
function extendValidation($name, $callback) {
	Router::extendValidation($name, $callback);
}

/**
 * Define un handler para un endpoint.
 *
 * @param string   $name     Nombre del endpoint
 * @param function $callback Callback del endpoint
 *
 * @return void
 */
function setEndpoint($name, $callback) {
	Router::setEndpointHandler($name, $callback);
}

/**
 * Procesa una nueva ruta.
 *
 * @param string $route Ruta a cargar
 *
 * @return void
 */
function reload($route) {
	MainController::reload($route);
}

/**
 * Redirige a una nueva ruta.
 *
 * @param string $route Ruta a redirigir
 *
 * @return void
 */
function redirect($route) {
	MainController::redirect($route);
}

/**
 * Registra un manejador de error HTTP.
 *
 * @param int             $errorCode Código del error HTTP
 * @param function|string $endpoint  Endpoint o callback del error
 *
 * @return void
 */
function onHttpError($errorCode, $endpoint) {
	Http::setErrorHandler($errorCode, $endpoint);
}

/**
 * Lanza un error HTTP.
 *
 * @param int  $statusCode Código HTTP de error
 * @param bool $useHandler Define si debe utilizar el handler correspondiente al error
 *
 * @return void
 */
function httpError($statusCode, $useHandler = true) {
	Http::throwError($statusCode, $useHandler);
}

/**
 * Carga una vista.
 *
 * @param string $path Path de la vista
 * @param array  $vars Variables a pasar al template a renderizar
 *
 * @return void
 */
function loadView($path, array $vars = array()) {
	MainController::loadView($path, $vars);
}

/**
 * Carga un controlador.
 *
 * @param string $path Path del controlador
 *
 * @return void
 */
function loadController($path) {
	MainController::loadController($path);
}

/**
 * Devuelve un path.
 *
 * @param string $pathName Nombre del directorio a solicitar
 *
 * @return string
 */
function path($pathName) {
	return Conf::getPath($pathName);
}

/**
 * Define una serie de paths.
 *
 * @param array $paths Definición de una serie de paths
 *
 * @return void
 */
function paths($paths) {
	Conf::setPaths($paths);
}


/**
 * Define una serie de assets.
 *
 * @param array $assets Definición de una serie de assets
 *
 * @return void
 */
function assets($assets) {
	Conf::setAssets($assets);
}

/**
 * Parser de URL's.
 *
 * Identifica si la URL es un asset '#...' o si contiene un path al inicio '@path:'
 *
 * @param string $url     Url a procesar
 * @param array  $version Variable de versión a agregar como parámetro de la URL
 *
 * @return string Url procesada
 */
function url($url, $version = false) {
	return Fw::url($url, $version);
}

/**
 * Agrega el path @baseUrl y pasa la URL por el parser.
 *
 * @param string $url     Url a procesar
 * @param array  $version Variable de versión a agregar como parámetro de la URL
 *
 * @return string Url procesada
 */
function baseUrl($url = null, $version = false) {
	if ($url) {
		return Fw::url('@baseUrl/' . $url, $version);
	} else {
		return path('baseUrl');
	}
}

/**
 * Define o devuelve una conexión a una base de datos.
 *
 * @param string   $name 	 Nombre de la conexión
 * @param function $callback Función callback que devuelve la conexión a la base de datos
 *
 * @return mixed Devuelve los datos de una conexión cuando no fue especificado el argumento $callback
 */
function dbConnection($name, $callback = null) {
	if ($callback === null) {
		return Conf::getDbConnection($name);
	} else {
		Conf::setDbConnection($name, $callback);
	}
}

/**
 * Define o devuelve una instancia.
 *
 * @param string   $name 	 Nombre de la instancia
 * @param function $callback Función callback que construye y devuelve la instancia
 *
 * @return mixed Devuelve una instancia cuando no fue especificado el argumento $callback
 */
function instance($name, $callback = null) {
	if ($callback === null) {
		return Conf::getInstance($name);
	} else {
		Conf::setInstance($name, $callback);
	}
}

/**
 * Despliega el valor de una variable en modo depuración.
 *
 * @param mixed $value Variable a desplegar
 *
 * @return void
 */
function dump($value) {
	if (Conf::getParam('debug') == true) {

		// Reinicia el framework
		Fw::restart();

		// Obtiene el response
		$res = Http::getResponse();

		// Obtiene el archivo y línea de donde fue llamado dump
		$trace = debug_backtrace();
		if (isset($trace[0]['file'])) {
			$file = $trace[0]['file'];
		} else {
			$file = '(not specified)';
		}
		if (isset($trace[0]['line'])) {
			$line = $trace[0]['line'];
		} else {
			$line = '(not specified)';
		}

		// Renderiza el template dump
		try {

			$res->body(Template::render('resources/fw/dump', [
				'value' => var_export($value, true),
				'type'  => gettype($value),
				'file'  => $file,
				'line'  => $line
			]), 'text/html');

		} catch (FwException_Template $e) {
			$res->body(var_export($value, true), 'text/txt');
			exit;
		}

		// Emite el response
		$res->emit();
	}
}
?>
