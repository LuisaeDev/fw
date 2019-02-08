<?php
/**
 * Voyager Micro Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use Exception;

/**
 * Tipo de excepción utilizada por el framework.
 */
class FwException extends Exception {

	/** @var array Almacena los parámetros asociados a la excepción */
	protected $params = array();

	/**
	 * Constructor.
	 *
	 * @param string                 $code    Codigo de la excepción
	 * @param string|array|ErrorList $message Mensaje de la excepción, acepta instancias ErrorList para seleccionar un error y obtener el mensaje correspondiente
	 * @param array                  $params  Parámetros de la excepción
	 */
	public function __construct($code, $message = null, $params = array()) {
		$this->code = $code;

		// Si el mensaje es de tipo string
		if (is_string($message)) {
			$this->message = $message;
			$this->params = $params;
			return;
		}

		// Si el argumento $message contiene una instancia ErrorList
		if ((is_object($message)) && ($message instanceof \Fw\ErrorList)) {
			$errorList = $message;

		// Si el argumento $message es un array de errores, crea una instancia de ErrorList
		} else if (is_array($message)) {
			$errorList = new ErrorList($message);

		// Si no se definió el mensaje intenta recuperarlos del método getErrorList()
		} else if ($message === null) {
			$errorList = new ErrorList($this->getErrorList());

		} else {
			return;
		}

		// Selecciona el error producido
		$errorList->select($code, $params);

		// Recupera el mensaje desde la instancia ErrorList
		$this->message = $errorList->getMessage();
		$this->params = $params;
	}

	/**
	 * Devuelve los parámetros asociados a la excepción.
	 */
	public function getParams() {
		return $this->params;
	}

	/**
	 * Devuelve un array resumen del error seleccionado.
	 *
	 * @return array|null
	 */
	public function getResume() {
		return array(
			'code'    => $this->getCode(),
			'message' => $this->getMessage()
		);
	}

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
