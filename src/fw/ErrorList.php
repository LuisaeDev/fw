<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

/**
 * Provee una instancia en la que se puede definir y seleccionar un error del listado de errores agregados en la misma.
 */
class ErrorList {

	/** @var string Código del error seleccionado */
	private $_code = null;

	/** @var array Definición de todos los errores manejados por la instancia */
	private $_list = array();

	/** @var array Variables a sustituir en el mensaje del error seleccionado */
	private $_vars = array();

	/** @var function Función callback al seleccionar un error */
	private $_callback = null;

	/**
	 * Constructor.
	 *
	 * @param array|null    $list     Listado de errores definidos para la instancia
	 * @param function|null $callback Función callback al seleccionar un error
	 */
	public function __construct($list = null, $callback = null) {
		if (is_array($list)) {
			$this->_list = $list;
		}
		if (isset($callback)) {
			$this->_callback = $callback;
		}
	}

	/**
	 * Método __clone.
	 */
	public function __clone() {
		$this->release();
	}

	/**
	 * Agrega uno o varios errores al listado.
	 *
	 * @param string $code    Código del error
	 * @param string $message Mensaje del error
	 *
	 * @return void
	 */
	public function add($code, $message) {
		if (is_array($code)) {
			$this->_list = array_merge($this->_list, $code);
		} else {
			$this->_list[$code] = $message;
		}
	}

	/**
	 * Selecciona un error del listado de errores.
	 *
	 * @param string     $code Código del error
	 * @param array|null $vars Variables para sustituir en el mensaje del error seleccionado
	 *
	 * @return void
	 */
	public function select($code, $vars = array()) {
		$this->_code = $code;
		$this->_vars = $vars;

		// Ejecuta la función callback si fue definida
		if ((isset($this->_callback)) && (is_callable($this->_callback))) {
			$callback = $this->_callback;
			$callback($this->getCode(), $this->getMessage());
		}
	}

	/**
	 * Confirma si hay un error seleccionado.
	 *
	 * @return bool
	 */
	public function exists() {
		return isset($this->_code);
	}

	/**
	 * Devuelve el código del error seleccionado.
	 *
	 * @return string|null Código del error
	 */
	public function getCode() {
		return $this->_code;
	}

	/**
	 * Devuelve el mensaje correspodiente al error seleccionado.
	 *
	 * @return string|null Mensaje del error
	 */
	public function getMessage() {
		if (($this->_code) && isset($this->_list[$this->_code])) {

			// Obtiene el mensaje correspondiente al error especificado
			$message = $this->_list[$this->_code];

			// Si se especificó variables para reemplazar en el mensaje
			if ((is_array($this->_vars)) && (count($this->_vars) > 0)) {

				// Se identifican y sustituyen las variables de tipo "$var"
				preg_match_all('/\$(\w+[\-\w+]*)/', $message, $matches, PREG_OFFSET_CAPTURE);
				foreach (array_reverse($matches[1]) as $match) {
					$index = $match[1];

					// Verifica si la variable está especificada en $this->_vars
					if (isset($this->_vars[$match[0]])) {
						$message = substr($message, 0, $index - 1) . $this->_vars[$match[0]] . substr($message, $index + strlen($match[0]));
					} else {
						$message = substr($message, 0, $index - 1) . '$' . $match[0] . substr($message, $index + strlen($match[0]));
					}
				}
			}
			return $message;
		} else {
			return null;
		}
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
	 * Libera el error seleccionado.
	 *
	 * @return void
	 */
	public function release() {
		$this->_code = null;
		$this->_vars = null;
	}
}
?>
