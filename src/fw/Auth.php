<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use Fw\Auth\CurrentUser;
use Fw\Utils\DataTools;

/**
 * Clase para el manejo de sesiones de usuario.
 */
class Auth {

	/** @var integer Tiempo de expiración en segundos para las sesiones */
	private static $_expire;

	/** @var CurrentUser Instancia del usuario en sesión */
	private static $_currentUser = null;

	/** @var bool Indica si hay un sesión de usuario */
	private static $_isLogged = false;

	/** @var string|null Token generado para la sesión de usuario */
	private static $_token = null;

	/**
	 * Inicializa la clase Auth.
	 *
	 * @return void
	 */
	public static function initialize() {

		// Obtiene el tiempo de expiración en segundos para las sesiones
		self::$_expire = Conf::getParam('auth_expire');

		// Construye la instancia para acceder a los datos del usurio en sesión
		self::$_currentUser = new CurrentUser();

		// Valida si existe una sesión de usuario
		self::_validate();
	}

	/**
	 * Realiza un inicio de sesión de usuario.
	 *
	 * @param string        $attrName         Define la columna por el cual se buscará el registro del usuario
	 * @param int           $attrValue        Valor con el cual se buscará el registro del usuario
	 * @param string|null   $pass             Password de la cuenta del usuario, al no especificarse no verificará el password de la cuenta
	 * @param function|null $validateCallback Función de validación del usuario
	 *
	 * @return void
	 *
	 * @throws FwException_Auth
	 */
	public static function login($attrName, $attrValue, $pass = null, $validateCallback = null) {

		// Conexión a la base de datos
		$db = Conf::getDbConnection('fw');

		// Define la instancia de Redis
		$redis = Conf::getDbConnection('fw:redis');

		// Verifica si ya existe una sesión de usuario
		if (self::isLogged()) {
			throw new FwException_Auth('session-exists');
		}

		// Verifica si la ip del cliente es inválida
		if (Http::getRequest()->ip === null) {
			throw new FwException_Auth('invalid-ip');
		}

		// Columnas predeterminadas de la tabla de usuarios
		$cols = [ 'id', 'pass', 'type', 'locale', 'timezone' ];

		// Obtiene las columnas especificadas en las configuraciones del Framework
		if (Conf::getParam('auth_users_table_cols') !== null) {
			$cols = array_unique(array_merge($cols, Conf::getParam('auth_users_table_cols')));
		}

		// Obtiene el registro de usuario
		$db->select($cols, Conf::getParam('auth_users_table'));

		// Determina por que columna identificará al usuario
		$db->quickWhere([ $attrName, $attrValue]);

		// Ejecuta la consulta
		$db->limit(1);
		$db->execute();

		// Evalua que el registro exista
		$user = $db->fetch();
		if (!$user) {
			throw new FwException_Auth('no-registered');
		}

		// El inicio de sesión puede realizarse sin password, sin embargo si este fue especificado entonces se tratará de validarse
		if ($pass) {

			// Evalua que el registro disponga de un password para acceder
			if ($user['pass'] == null) {
				throw new FwException_Auth('without-pass');
			}

			// Evalua que el password coincida
			if (!password_verify($pass, $user['pass'])) {
				throw new FwException_Auth('incorrect-pass');
			}
		}

		// Remueve el atributo pass de la variable $user
		unset($user['pass']);

		// Determina la cantidad de sesiones activas simultáneas que puede tener el usuario
		if (Conf::getParam('auth_concurrent') > 0) {
			$count = $redis->hLen(Conf::getParam('redis_namespace') . ':auth:' . $user['id']);
			if (($count) && ($count >= Conf::getParam('auth_concurrent'))) {
				throw new FwException_Auth('concurrent-sessions');
			}
		}

		// Llama al callback de validación si fue especificado
		if ((isset($validateCallback)) && (is_callable($validateCallback))) {
			if ($validateCallback((object)$user) !== true) {
				throw new FwException_Auth('invalid-login');
			}
		}

		// Inicia la sesión de php
		Session::start();

		// Se genera un nuevo id de sesión de php
		Session::regenerate();

		// Genera el id de autenticación de usuario
		$authId = DataTools::createUID();

		// Genera el token de la sesión de usuario
		$token = DataTools::createToken(32);

		// Agrega los datos de autenticación en las variables de sesión
		Session::set('fw_auth', array(
			'auth_id' => $authId,
			'user_id' => $user['id']
		));

		// Guarda en Redis un nuevo registro de autenticación de usuario
		$redis->hSet(Conf::getParam('redis_namespace') . ':auth:' . $user['id'], $authId, json_encode(array(
			'token'         => $token,
			'ip'            => Http::getRequest()->ip,
			'user_agent'    => Http::getRequest()->user_agent,
			'login_time'    => time(),
			'updating_time' => time()
		)));
		if (self::$_expire > 0) {
			$redis->expire(Conf::getParam('redis_namespace') . ':auth:' . $user['id'], self::$_expire);
		}

		// Actualiza la propiedad que define que existe una sesión de usuario válida
		self::$_isLogged = true;

		// Token de la sesión del usurio
		self::$_token = $token;
	}

	/**
	 * Cierra la sesión de usuario.
	 *
	 * @return void
	 */
	public static function logout() {

		// Verifica que exista una sesión de php y que exista una sesión de usuario
		if ((Session::isActive() == true) && (self::isLogged() == true)) {

			// Define la instancia de redis
			$redis = Conf::getDbConnection('fw:redis');

			// Remueve el registro de autenticación de usuario
			$redis->hDel(Conf::getParam('redis_namespace') . ':auth:' . Session::get('fw_auth')['user_id'], Session::get('fw_auth')['auth_id']);

			// Se genera un nuevo id de sesión de php
			Session::regenerate();

			// Destruye la sesión de php y su cookie de sesión
			Session::destroy();

			// Reinicia las variables
			self::$_isLogged = false;
			self::$_token = null;
		}
	}

	/**
	 * Devuelve la instancia del usuario en sesión.
	 *
	 * @return CurrentUser
	 */
	public static function getCurrentUser() {
		return self::$_currentUser;
	}

	/**
	 * Indica si existe o no una sesión de usuario.
	 *
	 * @return bool
	 */
	public static function isLogged() {
		return self::$_isLogged;
	}

	/**
	 * Devuelve el token de la sesión de usuario.
	 *
	 * @return string|null
	 */
	public static function getToken() {
		if (self::$_isLogged) {
			return self::$_token;
		} else {
			return null;
		}
	}

	/**
	 * Verifica si un token corresponde al token de la sesión de usuario actual.
	 *
	 * @param string $token Token de verificar
	 *
	 * @return bool
	 */
	public static function validateToken($token) {
		if ((self::isLogged()) && (hash_equals($token, self::getToken()))) {
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Valida la existencia e integridad de una sesión de usuario.
	 *
	 * - Verifica si existe un sesión de usuario.
	 * - Validar que la sesión de PHP sea íntegra.
	 * - Validar que la sesión no haya excedido el tiempo máximo de cierre.
	 *
	 * @return void
	 */
	private static function _validate() {

		// Reinicia las variables
		self::$_isLogged = false;
		self::$_token = null;

		// Verifica si existe una sesión de php
		if (Session::isActive() == false) {
			return;
		}

		// Evalua que exista la sesión de usuario
		if (!Session::exists('fw_auth')) {
			return;
		}

		// Define la instancia de Redis
		$redis = Conf::getDbConnection('fw:redis');

		// Obtiene los datos del registro de autenticación de usuario
		$authData = $redis->hGet(Conf::getParam('redis_namespace') . ':auth:' . Session::get('fw_auth')['user_id'], Session::get('fw_auth')['auth_id']);

		// Verifica si se obtuvieron los datos de autenticación
		if (!$authData) {
			Session::remove('fw_auth');
			return;
		} else {
			$authData = json_decode($authData, false);
		}

		// Propiedad que define que existe una sesión de usuario válida
		self::$_isLogged = true;

		// Almacena el la clase el token de la sesión del usuario
		self::$_token = $authData->token;

		// Calcula los minutos transcurridos entre la última actualización y ahora
		$updatingDate = new FwDateTime($authData->updating_time);
		$interval = $updatingDate->diff('now', true);
		$seconds = ($interval->days * 24 * 60 * 60) + ($interval->h * 60 * 60) + $interval->i;

		// Cierra la sesión si ha excedido el tiempo de cierre
		if ((self::$_expire > 0) && ($seconds >= self::$_expire)) {
			self::logout();
			return;
		}

		// Guarda en Redis la nueva fecha de actualización correspondiente a la actual sesión de usuario
		$authData->updating_time = time();
		$redis->hSet(Conf::getParam('redis_namespace') . ':auth:' . Session::get('fw_auth')['user_id'], 'auth:' . Session::get('fw_auth')['auth_id'], json_encode($authData));
		if (self::$_expire > 0) {
			$redis->expire(Conf::getParam('redis_namespace') . ':auth:' . Session::get('fw_auth')['user_id'], self::$_expire);
		}
	}
}
?>
