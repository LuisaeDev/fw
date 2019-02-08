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
class FwException_Auth extends FwException {
	
	/**
	 * Retorna el listado de errores prestablecidos de la excepción.
	 * 
	 * @return array
	 */
	public function getErrorList() {
		return array(
			'session-exists'      => 'A user session already exists',
			'invalid-ip'          => 'The clients\'s ip is invalid',
			'no-registered'       => 'The user account is not registered',
			'without-pass'        => 'The account does not have a password',
			'incorrect-pass'      => 'The password is incorrect',
			'concurrent-sessions' => 'You have reached the maximum of simultaneous sessions',
			'invalid-login'       => 'Login has been invalidated by the application'
		);
	}
}
?>