<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Utils;

use Fw\Conf;
use Fw\FwDateTime;
use Fw\Utils\DataTools;
use DateInterval;

/**
 * Clase para la creación y validación de tokens.
 */
class Token {

	/**
	 * Crea un nuevo token.
	 *
	 * @param string $expire Intervalo de tiempo de duración del token
	 * @param int    $length Largo del key del token, el largo máximo debe ser 100
	 *
	 * @return object Datos del token
	 */
	public static function create($expire = 'PT6H', $length = 32) {

		// Conexión a la base de datos de redis
		$redis = dbConnection('fw:redis');

		// Genera el key único del token
		while (true) {
			$key = DataTools::createToken($length);
			if (!$redis->exists(Conf::getParam('redis_namespace') . ':tk:' . $key)) {
				break;
			}
		}

		// Estima la fecha de expiración
		$dtExpire = new FwDateTime('now');
		$dtExpire->add($expire);

		// Guarda el token
		$redis->set(Conf::getParam('redis_namespace') . ':tk:' . $key, $dtExpire->getTimestamp());

		// Define la expiración del token
		$redis->expireat(Conf::getParam('redis_namespace') . ':tk:' . $key, $dtExpire->getTimestamp());

		// Devuelve el token
		return (object)[
			'key'    => $key,
			'expire' => $dtExpire->getTimestamp()
		];
	}

	/**
	 * Verifica si un token es válido.
	 *
	 * @param string $key Key del token
	 *
	 * @return bool
	 */
	public static function isValid($key) {

		// Conexión a la base de datos de redis
		$redis = dbConnection('fw:redis');

		// Verifica si el token existe, de existir valida si ha expirado
		if ($redis->exists(Conf::getParam('redis_namespace') . ':tk:' . $key)) {
			$expire = $redis->get(Conf::getParam('redis_namespace') . ':tk:' . $key);
			if (time() >= (int)$expire) {

				// Elimina el token de redis
				$redis->del(Conf::getParam('redis_namespace') . ':tk:' . $key);
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	/**
	 * Compara la igualdad de dos tokens.
	 *
	 * @param string $tk1 Key del token 1
	 * @param string $tk2 Key del token 2
	 *
	 * @return bool
	 */
	public static function areEqual($tk1, $tk2) {
		return hash_equals($tk1, $tk2);
	}

	/**
	 * Destruye un token.
	 *
	 * @param string $key Key del token
	 *
	 * @return void
	 */
	public static function destroy($key) {

		// Conexión a la base de datos de redis
		$redis = dbConnection('fw:redis');

		// Destruye el token
		$redis->del(Conf::getParam('redis_namespace') . ':tk:' . $key);
	}
}
?>
