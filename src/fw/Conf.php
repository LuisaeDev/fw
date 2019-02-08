<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use DateTimeZone;

/**
 * Configuraciones globales del framework
 */
class Conf {

	/** @var array Almacena los parámetros de configuración del Framework */
	private static $_params = array();

	/** @var array Almacena las variables globales de la aplicación */
	private static $_globals = array();

	/** @var array Almacena todos las rutas */
	private static $_paths = array();

	/** @var array Almacena todos los assets */
	private static $_assets = array();

	/** @var array Almacena todos las conexiones a bases de datos */
	private static $_dbConnections = array();

	/** @var array Almacena las instancias */
	private static $_instances = array();

	/** @var array Variables a almacenar en la cookie del Framework */
	private static $_frontVars = array();

	/**
	 * Carga las configuraciones iniciales del Framework.
	 *
	 * @return void
	 */
	public static function load() {
		self::$_params = require_once './conf/params.php';
		self::$_globals = require_once './conf/globals.php';
		require_once './conf/paths.php';
	}

	/**
	 * Define un parámetro de configuración del Framework.
	 *
	 * @param string $name  Nombre del parámetro a definir
	 * @param mixed  $value Valor del parámetro a definir
	 *
	 * @return void
	 */
	public static function setParam($name, $value) {
		self::$_params[$name] = $value;
	}

	/**
	 * Devuelve un parámetro de configuración del Framework.
	 *
	 * @param string $name Nombre del parámetro a solicitar
	 *
	 * @return mixed
	 */
	public static function getParam($name) {
		if (isset(self::$_params[$name])) {
			if ((!is_array(self::$_params[$name])) && (is_callable(self::$_params[$name]))) {

				// Obtiene los argumentos a pasar
				$args = func_get_args();

				// Remueve el primer argumento recibido
				array_shift($args);

				// Llama a la función y pasa los argumentos
				return call_user_func_array(self::$_params[$name], $args);

			} else {
				return self::$_params[$name];
			}
		} else {
			return null;
		}
	}

	/**
	 * Define un parámetro de configuración del Framework.
	 *
	 * @param string $name  Nombre de la variable a definir
	 * @param mixed  $value Valor de la variable a definir
	 *
	 * @return void
	 */
	public static function setGlobal($name, $value) {
		self::$_globals[$name] = $value;
	}

	/**
	 * Devuelve una variable de configuración de la aplicación.
	 *
	 * @param string $name Nombre de la variable global a solicitar
	 *
	 * @return mixed
	 */
	public static function getGlobal($name) {
		if (isset(self::$_globals[$name])) {
			if ((!is_array(self::$_globals[$name])) && (is_callable(self::$_globals[$name]))) {

				// Obtiene los argumentos a pasar
				$args = func_get_args();

				// Remueve el primer argumento recibido
				array_shift($args);

				// Llama a la función y pasa los argumentos
				return call_user_func_array(self::$_globals[$name], $args);

			} else {
				return self::$_globals[$name];
			}
		} else {
			return null;
		}
	}

	/**
	 * Define una serie de paths.
	 *
	 * @param array $paths Definición de una serie de paths
	 *
	 * @return void
	 */
	public static function setPaths($paths) {
		self::$_paths = array_merge(self::$_paths, $paths);
	}

	/**
	 * Devuelve un path.
	 *
	 * @param string $pathName Nombre del path a solicitar
	 *
	 * @return string|null
	 */
	public static function getPath($pathName) {
		if (isset(self::$_paths[$pathName])) {
			if ((!is_array(self::$_paths[$pathName])) && (is_callable(self::$_paths[$pathName]))) {
				return call_user_func(self::$_paths[$pathName]);
			} else {
				return self::$_paths[$pathName];
			}
		} else {
			return null;
		}
	}

	/**
	 * Define una serie de assets.
	 *
	 * @param array $assets Definición de una serie de assets
	 *
	 * @return void
	 */
	public static function setAssets($assets) {
		self::$_assets = array_merge(self::$_assets, $assets);
	}

	/**
	 * Devuelve un asset.
	 *
	 * @param string $assetName Nombre del asset a solicitar
	 *
	 * @return string|null
	 */
	public static function getAsset($assetName) {
		if (isset(self::$_assets[$assetName])) {
			if ((!is_array(self::$_assets[$assetName])) && (is_callable(self::$_assets[$assetName]))) {
				return call_user_func(self::$_assets[$assetName]);
			} else {
				return self::$_assets[$assetName];
			}
		} else {
			return null;
		}
	}

	/**
	 * Registra una conexión a una base de datos.
	 *
	 * @param string   $name 	   Nombre de la conexión
	 * @param function $connection Conexión a la base de datos o función callback que devuelve la conexión a la base de datos
	 *
	 * @return void
	 */
	public static function setDbConnection($name, $connection) {
		self::$_dbConnections[$name] = $connection;
	}

	/**
	 * Devuelve una conexión a una base de datos.
	 *
	 * @param string $name Nombre de la conexión
	 *
	 * @return mixed Devuelve una conexión a una base de datos
	 */
	public static function getDbConnection($name) {
		if (isset(self::$_dbConnections[$name])) {

			// Verifica si la conexión a solicitar aun es una función anónima que devuelve la conexión
			if ((!is_array(self::$_dbConnections[$name])) && (is_callable(self::$_dbConnections[$name]))) {
				self::$_dbConnections[$name] = call_user_func(self::$_dbConnections[$name]);
			}
			return self::$_dbConnections[$name];
		} else {
			return null;
		}
	}

	/**
	 * Registra una instancia.
	 *
	 * @param string   $name     Nombre de la instancia
	 * @param function $callback Función constructora de la instancia
	 *
	 * @return void
	 */
	public static function setInstance($name, $callback) {
		self::$_instances[$name] = $callback;
	}

	/**
	 * Devuelve una instancia registrada.
	 *
	 * @param string $name Nombre de la instancia
	 *
	 * @return mixed Devuelve la instancia
	 */
	public static function getInstance($name) {
		if (isset(self::$_instances[$name])) {

			// Verifica si la conexión a solicitar aun es una función anónima que devuelve la conexión
			if ((!is_array(self::$_instances[$name])) && (is_callable(self::$_instances[$name]))) {
				self::$_instances[$name] = call_user_func(self::$_instances[$name]);
			}

			return self::$_instances[$name];
		} else {
			return null;
		}
	}

	/**
	 * Agrega un conjunto de variables para pasar al Frontend.
	 *
	 * @param array $vars Conjunto de variables a agregar
	 *
	 * @return void
	 */
	public static function setFrontVars($vars = array()) {
		self::$_frontVars = array_merge(self::$_frontVars, $vars);
	}

	/**
	 * Retorna el conjunto de variables a pasar al Frontend.
	 *
	 * @return array
	 */
	public static function getFrontVars() {
		return self::$_frontVars;
	}

	/**
	 * Identifica y retorna la zona horaria requerida.
	 *
	 * Al identificar la zona horaria requerida, la almacena en la constante FW_TIMEZONE
	 *
	 * @return string
	 */
	public static function getTimezone() {

		// Retorna la constante de zona horaria
		if (defined('FW_TIMEZONE')) {
			return FW_TIMEZONE;
		}

		// Obtiene la zona horaria en la información del usuario en sesión
		if ((Auth::isLogged()) && (Auth::getCurrentUser()->timezone != null) && in_array(Auth::getCurrentUser()->timezone, DateTimeZone::listIdentifiers())) {
			$tz = Auth::getCurrentUser()->timezone;
		} else {
			$tz = false;
		}

		// Verifica si existe la cookie "fw_tz" que especifica la zona horaria del cliente
		if ($tz == false) {
			$tz = Input::cookie('fw_tz', [
				'type'       => 'string',
				'max-length' => 255,
				'validate'   => function($tz) {
					return in_array($tz, DateTimeZone::listIdentifiers());
				}
			], false);
		}

		// Verifica si existe la cookie "fw_tz_offset" que especifica la zona horaria del cliente en segundos
		if ($tz == false) {
			$seconds = Input::cookie('fw_tz_offset', [
				'type'      => 'int',
				'min-range' => -43200,
				'max-range' => 43200,
			], false);
			if ($seconds !== false) {

				// Verifica si se especificó la cookie que determina si se está usando horario de verano
				$dst = Input::cookie('fw_tz_offset_dst', [
					'type' => 'int',
					'case' => [ 0, 1 ]
				], 0);

				// Define la zona horaria del cliente
				$tz = timezone_name_from_abbr(null, $seconds, $dst);
			}
		}

		// Define la constante de zona horaria
		if ($tz) {
			define('FW_TIMEZONE', $tz);

		// Define la constante de zona horaria basándose en la del Framework como última fuente
		} else {
			define('FW_TIMEZONE', self::getParam('default_timezone'));
		}

		return FW_TIMEZONE;
	}

	/**
	 * Identifica y retorna el idioma requerido por el cliente.
	 *
	 * Al identificar el idioma requerido, lo almacena en la constante FW_LOCALE
	 *
	 * @return string
	 */
	public static function getLocale() {

		// Retorna la constante de idioma
		if (defined('FW_LOCALE')) {
			return FW_LOCALE;
		}

		// Obtiene los idiomas aceptados por el cliente
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			$locales = trim($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		} else {
			$locales = '';
		}

		// Define el array $qLocales de idiomas aceptados por el navegador del cliente, por orden de preferencia
		$qLocales = array();
		$locales = explode(',', $locales);
		foreach ($locales as $locale) {
			if (preg_match('/(\*|[a-zA-Z0-9]{1,8}(?:-[a-zA-Z0-9]{1,8})*)(?:\s*;\s*q\s*=\s*(0(?:\.\d{0,3})|1(?:\.0{0,3})))?/', trim($locale), $match)) {

				// Define la preferencia del lenguaje
				if (!isset($match[2])) {
					$match[2] = '1.0';
				} else {
					$match[2] = (string)floatval($match[2]);
				}

				// Agrega la posición de preferencia en el array de $qLocales
				if (!isset($qLocales[$match[2]])) {
					$qLocales[$match[2]] = array();
				}

				// Agrega el lenguaje en la posición de preferencia correspondiente
				$qLocales[$match[2]][] = strtolower($match[1]);
			}
		}

		// Ordena el array en orden inverso
		krsort($qLocales);

		// Obtiene ordenadamente cada idioma por su preferencia
		$locales = array();
		foreach ($qLocales as $q) {
			foreach ($q as $locale) {
				$locales[] = $locale;
			}
		}

		// Si existe la cookie "locale", agrega el idioma de la cookie en primera posición
		if (Input::cookie('fw_locale')) {
			$locales = array_merge([Input::cookie('fw_locale')], $locales);
		}

		// Si existe una sesión de usuario, agrega el idioma de la sesión en primera posición
		if ((Auth::isLogged()) && (Auth::getCurrentUser()->locale != null))  {
			$locales = array_merge([Auth::getCurrentUser()->locale], $locales);
		}

		// Determina a partir de todos los lenguajes identificados, cual idioma deberá utilizar
		foreach ($locales as $locale) {

			// Verifica si el lenguaje está en la lista de lenguajes permitidos
			if (in_array($locale, self::getParam('supported_locales'))) {

				// Define el idioma actual a utilizar
				define('FW_LOCALE', $locale);
				return;
			}

			// Verifica si el lenguaje está especificado de la forma "en-US", obteniendo solo la primer parte y verificando si está en la lista de lenguajes permitidos
			$locale = explode('-', $locale)[0];
			if (in_array($locale, self::getParam('supported_locales'))) {

				// Define el idioma actual a utilizar
				define('FW_LOCALE', $locale);
				return;
			}

			// Verifica si el lenguaje está especificado de la forma "en_US", obteniendo solo la primer parte y verificando si está en la lista de lenguajes permitidos
			$locale = explode('_', $locale)[0];
			if (in_array($locale, self::getParam('supported_locales'))) {

				// Define el idioma actual a utilizar
				define('FW_LOCALE', $locale);
				return;
			}
		}

		// Define como idioma actual el idioma predeterminado del Framework
		define('FW_LOCALE', self::getParam('default_locale'));
	}
}
?>
