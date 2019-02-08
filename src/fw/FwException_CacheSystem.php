<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

/**
 * Exception basada en FwException.
 */
class FwException_CacheSystem extends FwException {
	
	/**
	 * Retorna el listado de errores prestablecidos de la excepción.
	 * 
	 * @return array
	 */
	public function getErrorList() {
		return array(
			'resource-not-found' => 'The resource it is not in cache',
			'resource-expired'   => 'The resource is expired',
			'json-not-found'     => 'The JSON file was not found in "$path"',
			'json-invalid'       => 'The JSON file located at "$path" is invalid'
		);
	}
}
?>