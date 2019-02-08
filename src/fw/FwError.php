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
class FwError extends FwException {

	/**
	 * Retorna el listado de errores prestablecidos de la excepción.
	 *
	 * @return array
	 */
	public function getErrorList() {
		return array(
			'class-not-found'            => 'Error trying to load the class "$className" in path "$classDir"',
			'class-not-defined'          => 'The file located at "$classDir" has not the class definition or namespace to "$className"',
			'invalid-endpoint-type'      => 'An invalid endpoint type "$type" has been requested by the route "$route"',
			'controller-not-found'       => 'Error trying to load the controller in path "$path" requested by the route "$route"',
			'view-not-found'             => 'Error trying to load the view in path "$path" requested by the route "$route"',
			'endpoint-handler-not-found' => 'Error trying to call the endpoint handler "$name" requested by the route "$route"',
			'php-session-disabled'       => 'The session variables for PHP are disabled',
			'data-connection-no-defined' => 'Error trying to connect to the DataBase, data connection "$con" is not defined'
		);
	}
}
?>
