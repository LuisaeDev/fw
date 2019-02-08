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
class FwException_PDOCOnnection extends FwException {

	/**
	 * Retorna el listado de errores prestablecidos de la excepción.
	 * 
	 * @return array
	 */
	public function getErrorList() {
		return array(
			'no-dbname'           => 'The DataBase\'s name must to be especified',
			'engine-no-supported' => 'The DataBase\' engine "$engine" it is not supported',
			'pdo-exception'       => 'Code: $code, Message: $message'
		);
	}
}
?>