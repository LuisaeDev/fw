<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

/**
 * Parámetros de configuración del Framework.
 */

namespace Fw;

return array(

	// Modo de distribución
	'dist' 						=> false,

	// Modo de depuración
	'debug' 					=> true,

	// Versión para las URL's generadas por el método Fw::url()
	'url_version' 				=> 1,

	// Zona horaria default a utilizar
	'default_timezone'			=> 'UTC',

	// Idioma default a utilizar
	'default_locale'			=> 'es',

	// Idiomas soportados
	'supported_locales' 		=> [ 'es' ],

	// Namespaces idendificados por la aplicación web
	'namespaces'				=> [ ],

	// Errores notificados por PHP
	'error_reporting'           => function() {
		if (Conf::getParam('debug')) {
			return E_ERROR | E_WARNING | E_PARSE | E_NOTICE;
		} else {
			return E_ERROR;
		}
	},

	// Template para mostrar errores, puede ser false
	'error_template_display'	=> './resources/fw/error.twig',

	// Historial de errores
	'error_log'					=> true,
	'error_log_path'			=> 'error.log',
	'error_log_trace'			=> false,
	'error_log_max_length'		=> 2048,

	// Tiempo de expiración para las sesiones de usuario (7 días)
	'auth_expire'				=> 604800,

	// Define la cantidad de sesiones concurrentes permitidas para una misma cuenta de usuario (0 es igual a ilimitado)
	'auth_concurrent' 			=> 0,

	// Tabla de usuarios
	'auth_users_table'			=> 'fw_user',

	// Columnas adicionales a requerir de la tabla de usuarios al inicio de sesión, no incluir las columnas ('id', 'pass', 'type', 'locale', 'timezone')
	'auth_users_table_cols'		=> [ 'email', 'name' ],

	// Namespace (prefijo) reservado para recursos almacenados en Redis por el Framework
	'redis_namespace'           => 'fw',

	// Formato de fecha predeterminado utilizado por la clase Input para la interpretación de fechas
	'input_dateformat'			=> 'Y-m-d',

	// Tamaño máximo permitido al validar archivos por la clase Input (Mb)
	'input_max_file_size'		=> 20,

	// Lista de extensiones permitidas en las validaciones de la clase Input y en Utils\RepositoryFile
	'valid_extensions' => function() {
		return CacheSystem::decodeJSON('resources/fw/valid-extensions.json');
	},

	// Ubicación del repositorio
	'repo_path'                 => '/repo',

	// Define si se debe enviar un response 304 automáticamente si no ha sido modificado con respecto al request
	'response_emit_304'  		=> true,

	// Parámetros para sesiones de php
	'php_session_redis_handler' => 'tcp://127.0.0.1:6379',
	'php_session_name' 			=> 'fw_ses',
	'php_session_expire' 		=> 604800,
	'php_session_https'			=> false,
	'php_session_cookie_path'	=> function() {
		return path('baseUrl');
	},
);
?>
