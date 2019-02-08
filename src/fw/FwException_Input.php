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
class FwException_Input extends FwException {

	/**
	 * Retorna el listado de errores prestablecidos de la excepción.
	 *
	 * @return array
	 */
	public function getErrorList() {
		return array(
		);
	}
}
?>
