<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Auth;

use Fw\Conf;
use Fw\Auth;
use Fw\Session;
use Fw\QueryBuilder;

/**
 * Proporciona un acceso y administración de los datos del usuario en sesión.
 */
class CurrentUser {

	/** @var array Datos del registro del usuario en sesión */
	private static $_data = null;

	/** @var Array Columnas predeterminadas a cargar del registro de usuario */
	private static $_defCols = [ 'id', 'type', 'locale', 'timezone' ];

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Método mágico __get.
	 *
	 * Devuelve un atributo del registro del usuario en sesión.
	 *
	 * @param string $attrName Nombre del atributo
	 *
	 * @return mixed|null
	 */
	public function __get($attrName) {

		// Retorna null en caso de no existir una sesión de usuario
		if (!Auth::isLogged()) {
			return null;
		}

		// Verifica si los datos ya fueron cargados desde Redis
		if (self::$_data == null) {
			self::_getDataFromRedis();
		}

		// Retorna el atributo solicitado
		if (isset(self::$_data[$attrName])) {
			return self::$_data[$attrName];
		} else {
			return null;
		}
	}

	/**
	 * Actualiza el registro del usuario en sesión.
	 *
	 * @param array $attrs Array asociativo de atributos a modificar en el registro.
	 *
	 * @return void
	 */
	public function update($attrs) {

		// Finaliza si no existe una sesión de usuario
		if (!Auth::isLogged()) {
			return;
		}

		// Se remueven atributos que no se permiten modificar por este método
		unset($attrs['id']);
		unset($attrs['pass']);

		// Crea una instancia de conexión a la base de datos
		$db = new QueryBuilder('fw');

		// Actualiza el registro del usuario en sesión
        $db->autoUpdate(Conf::getParam('auth_users_table'), Session::get('fw_auth')['user_id'], $attrs);

		// Carga nuevamente el registro del usuario en sesión
		self::_refresh();
	}

	/**
	 * Carga nuevamente los datos del registro del usuario en sesión.
	 *
	 * @return void
	 */
	public function refresh() {
		self::_refresh();
	}

	/**
	 * Obtiene los datos del usuario en sesión almacenados en Redis.
	 *
	 * @return void
	 */
	private static function _getDataFromRedis() {

		// Define la instancia de Redis
		$redis = Conf::getDbConnection('fw:redis');

		// Obtiene los datos del registro del usuario desde Redis
		$data = $redis->get(Conf::getParam('redis_namespace') . ':user:' . Session::get('fw_auth')['user_id']);

		// Si los datos en Redis ya expiraron, se vuelven a cargar desde la base de datos
		if (!$data) {
			self::_refresh();
			return;
		} else {
			$data = json_decode($data, true);
		}

		// Compara el array de datos cargado desde redis con la especificación de columnas a cargar del registro de usuario
		$cols = array_merge(self::$_defCols, Conf::getParam('auth_users_table_cols'));

		// Verifica que ambos array sean igual de largos
		if ((count($data)) != (count($cols))) {
			self::_refresh();
			return;
		}

		// Verifica que ambos array dispongan de las mismas columnas
		foreach ($cols as $i => $value) {
			if (!isset($data[$value])) {
				self::_refresh();
				return;
			}
		}

		// Almacena en la clase los datos del registro del usuario en sesión
		self::$_data = $data;
	}

	/**
	 * Carga nuevamente los datos del registro del usuario en sesión.
	 *
	 * @return void
	 */
	private static function _refresh() {

		// Finaliza si no existe una sesión de usuario
		if (!Auth::isLogged()) {
			return;
		}

		// Define la instancia de Redis
		$redis = Conf::getDbConnection('fw:redis');

		// Obtiene otras columnas definidas en las configuraciones del Framework
		if (Conf::getParam('auth_users_table_cols') !== null) {
			$cols = array_unique(array_merge(self::$_defCols, Conf::getParam('auth_users_table_cols')));
		} else {
			$cols = self::$_defCols;
		}

		// Se remueven atributos que no se permiten manejar por esta clase
		unset($cols['id']);
		unset($cols['pass']);

		// Crea una instancia de conexión a la base de datos
		$db = new QueryBuilder('fw');

		// Obtiene el registro de usuario
		$data = $db->get(Conf::getParam('auth_users_table'), Session::get('fw_auth')['user_id'], $cols);
		if (!$data) {
			return;
		}

		// Guarda en Redis los datos del registro del usuario en sesión
		$redis->set(Conf::getParam('redis_namespace') . ':user:' . Session::get('fw_auth')['user_id'], json_encode($data));

		// Define el tiempo de expiración (24 Horas) para la información del usuario almacenada en Redis
		$redis->expire(Conf::getParam('redis_namespace') . ':user:' . Session::get('fw_auth')['user_id'], 86400);

		// Almacena en la clase los datos del registro del usuario en sesión
		self::$_data = (array)$data;
	}
}
?>
