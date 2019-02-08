<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

/**
 * Clase para el manejo de $_SESSION.
 */
class Session {

	/**
	 * Inicializa la clase Session.
	 *
	 * @return void
	 */
	public static function initialize() {

		// Configuraciones de php
		ini_set('session.use_cookies', '1');
		ini_set('session.use_only_cookies', '1');
		ini_set('session.gc_maxlifetime', Conf::getParam('php_session_expire') * 60);

		// Define el id de sesión de php
		session_name(Conf::getParam('php_session_name'));

		// Establece los parámetros para la cookie de sesión
		$params = session_get_cookie_params();
		session_set_cookie_params($params['lifetime'], Conf::getParam('php_session_cookie_path'), $params['domain'], Conf::getParam('php_session_https'), true);

		// Habilita a Redis como manejador de sesiones
		if (Conf::getParam('php_session_redis_handler') != false) {
			ini_set('session.save_handler', 'redis');
			ini_set('session.save_path', Conf::getParam('php_session_redis_handler'));
		}

		// Verifica si existe una sesión de php a iniciar
		self::auto();
	}

	/**
	 * Verifica si la cookie de sesión de php existe e inicia la sesión de php automáticamente.
	 *
	 * @return void
	 */
	public static function auto() {

		// Verifica si la cookie existe e inicia la sesión
		if (Input::cookie(Conf::getParam('php_session_name'), [ 'type' => 'raw', 'length' => 26 ])) {
			self::start();
		}
	}

	/**
	 * Devuelve por referencia una variable de sesión.
	 *
	 * @param string $var Nombre de la variable a obtener
	 *
	 * @return mixed
	 */
	public static function &get($varName) {

		// Variable para pasar por referencia en caso de que no exista
		$null = null;

		// Verifica que exista una sesión activa
		if (session_status() != PHP_SESSION_ACTIVE) {
			Session::start();
		}

		// Devuelve la variable solicitada o todo el array de variables
		if (array_key_exists($varName, $_SESSION)) {
			return $_SESSION[$varName];
		} else {
			return $null;
		}
	}

	/**
	 * Define una variable de sesión.
	 *
	 * @param string $varName Nombre de la variable a definir
	 * @param mixed  $value   Valor de la variable a definir
	 *
	 * @return void
	 */
	public static function set($varName, $value) {

		// Verifica que exista una sesión activa
		if (session_status() != PHP_SESSION_ACTIVE) {
			Session::start();
		}

		// Crea / actualiza la variable
		$_SESSION[$varName] = $value;
	}

	/**
	 * Verifica si existe una variable de sesión.
	 *
	 * @param string $varName Nombre de la variable
	 *
	 * @return bool
	 */
	public static function exists($varName) {

		// Verifica que exista una sesión activa
		if (session_status() != PHP_SESSION_ACTIVE) {
			Session::start();
		}

		if (isset($_SESSION[$varName])) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Elimina una variable de sesión.
	 *
	 * @param string $varName Nombre de la variable
	 *
	 * @return bool
	 */
	public static function remove($varName) {

		// Verifica que exista una sesión activa
		if (session_status() != PHP_SESSION_ACTIVE) {
			Session::start();
		}

		if (array_key_exists($varName, $_SESSION)) {
			unset($_SESSION[$varName]);
		}
	}

	/**
	 * Verifica si la sesión de PHP está activa.
	 *
	 * @return bool
	 */
	public static function isActive() {
		if (session_status() == PHP_SESSION_ACTIVE) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Inicia la sesión de PHP.
	 *
	 * @return void
	 */
	public static function start() {
		switch (session_status()) {
			case PHP_SESSION_DISABLED:
				throw new FwError('php-session-disabled');
				break;

			case PHP_SESSION_NONE:

				// Inicia la sesión de php
				session_start();
				break;

			case PHP_SESSION_ACTIVE:
				break;
		}
	}

	/**
	 * Regenera el id de sesión de PHP.
	 *
	 * @return string id de la sesión de php
	 */
	public static function regenerate() {
		if (session_status() == PHP_SESSION_ACTIVE) {
			session_regenerate_id(true);
			return session_id();
		}
	}

	/**
	 * Cierra la sesión de php.
	 *
	 * @return void
	 */
	public static function close() {
		if (session_status() == PHP_SESSION_ACTIVE) {
			session_write_close();
		}
	}

	/**
	 * Destruye la cookie de sesión de php.
	 *
	 * @return void
	 */
	public static function destroyCookie() {
		if (Input::cookie(session_name())) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, Conf::getParam('php_session_cookie_path'), $params['domain'], Conf::getParam('php_session_https'), true);
		}
	}

	/**
	 * Destruye la cookie y la sesión de php.
	 *
	 * @return void
	 */
	public static function destroy() {
		if (session_status() == PHP_SESSION_ACTIVE) {
			unset($_SESSION);
			self::destroyCookie();
			session_destroy();
		}
	}
}
?>
