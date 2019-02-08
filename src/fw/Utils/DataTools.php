<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Utils;

/**
 * Métodos relacionados a la generación de tipos datos.
 */
class DataTools {

	/**
	 * Genera y devuelve un token generado de manera segura.
	 *
	 * @param int $length Largo del valor a retornar
	 *
	 * @return string
	 */
	public static function createToken($length = 32) {
		return \bin2hex(\openssl_random_pseudo_bytes($length / 2));
	}

	/**
	 * Devuelve un identificador único prefijado basado en la hora actual en microsegundos.
	 *
	 * @param int $length Largo del valor a retornar, debe ser un entero entre 1 y 32
	 *
	 * @return string
	 */
	public static function createUID($length = 32) {
		$uniqid = md5(uniqid('', true));
		if (($length > 1) && ($length < 32)) {
			return substr($uniqid, 0, $length);
		} else {
			return $uniqid;
		}
	}

	/**
	 * Devuelve un código aleatorio de 32 caracteres.
	 *
	 * @param int $length Largo del valor a retornar
	 *
	 * @return string
	 */
	public static function createRandom($length = 32) {
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charsLength = strlen($chars);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $chars[rand(0, $charsLength - 1)];
		}
		return $randomString;
	}
}
?>
