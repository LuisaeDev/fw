<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Utils;

/**
 * Métodos relacionados a Arrays.
 */
class ArrayTools {

	/**
	 * Determina si un array es asociativo o secuencial.
	 *
	 * @return bool
	 */
	public static function isAssociative($value) {
		if (is_array($value)) {
			return array_keys($value) !== range(0, count($value) - 1);
		} else {
			return false;
		}
	}

	/**
	 * Convierte un array asociativo a un array secuencial.
	 *
	 * @param array $object Array asociativo a indexar
	 *
	 * @return array Array secuencial
	 */
	public static function objectToArray($object) {
		if (!is_object($object) && !is_array($object)) {
			return $object;
		} else {
			return array_map('self::objToArray', (array) $object);
		}
	}

	/**
	 * Recorre y altera cada elemento en un array de forma iterativa.
	 *
	 * Recorre cada elemento de un array.
	 * Se llama a una función callback en cada iteración.
	 * Al callback se le pasa el key y value del elemento.
	 * El valor retornado por el callback modifica el valor del elemento.
	 *
	 * @param array    &$array     Array a recorrer pasado por referencia
	 * @param function $cb         Función callback que es llamada en cada iteración
	 * @param boolean  $passArrays Define si debe llamarse al callback cuando el elemento es un array
	 *
	 * @return mixed Retorna el array alterado
	 */
	public static function walk(&$array, $cb, $passArrays = false) {
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				if ($passArrays == true) {
					$value = call_user_func($cb, $key, $value);
				}
				if (is_array($value)) {
					$array[$key] = self::walk($value, $cb);
				} else {
					$array[$key] = $value;
				}
			} else {
				$array[$key] = call_user_func($cb, $key, $value);
			}
		}
		return $array;
	}

	/**
	 * Recorre un array asociativo de forma iterativa.
	 *
	 * Recorre cada bloque asociativo dentro de un array.
	 * Se llama a una función callback en cada iteración.
	 * Al callback se le pasa el key y value del elemento.
	 * El valor retornado por el callback modifica el valor del elemento.
	 * Si se retorna null, el bloque de la iteración actual será eliminado.
	 *
	 * @param array    &$array Array a recorrer pasado por referencia
	 * @param function $cb     Función callback que es llamada en cada iteración
	 *
	 * @return mixed Retorna el array alterado
	 */
	public static function walkAssociative(&$array, $cb) {
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				if (self::isAssociative($value)) {
					$value = call_user_func($cb, $value);
					if (is_array($value)) {
						$array[$key] = self::walkAssociative($value, $cb);
					} else if ($value === null) {
						unset($array[$key]);
					} else {
						$array[$key] = $value;
					}
				} else {
					$array[$key] = self::walkAssociative($value, $cb);
				}
			}
		}
		return $array;
	}
}
?>
