<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

/**
 * Maneja el request del framework.
 *
 * @property      string $route     Ruta del request
 * @property      string $params    Parámetros especificados en la ruta del request
 * @property-read string $url       URL del request
 * @property-read string $method    Método del request
 * @property-read string $ip        Ip del cliente
 * @property-read string $userAgent Agente de usuario del cliente
 */
class HttpRequest {

	/** @var string Ruta del request */
	private $_route;

	/** @var string Parámetros especificados en la ruta del request */
	private $_params = array();

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Obtiene la ruta del request
		$this->route = Input::get('req', 'raw', '');
	}

	/**
	 * Método mágico __set.
	 */
	public function __set($property, $value) {
		if (is_callable(array($this, $method = '_set_' . $property))) {
			return $this->$method($value);
		}
	}

	/**
	 * Método mágico __get.
	 */
	public function __get($property) {
		if (is_callable(array($this, $method = '_get_' . $property))) {
			return $this->$method();
		} else {
			return null;
		}
	}

	/**
	 * Confirma si el request es de tipo XHR.
	 *
	 * @return bool
	 */
	public function isXHR() {
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Devuelve un header del request.
	 *
	 * @param string $var Nombre del header solicitado
	 *
	 * @return string|null
	 */
	public function getHeader($var) {

		// Obtiene los headers del request
		$headers = getallheaders();

		// Devuelve el valor solicitado
		if (isset($headers[$var])) {
			return $headers[$var];
		} else {
			return null;
		}
	}

	/**
	 * Setter de propiedad $params.
	 *
	 * @param array $value
	 */
	private function _set_params($value) {
		$this->_params = $value;
	}

	/**
	 * Getter de propiedad $params.
	 *
	 * @return array
	 */
	private function _get_params() {
		return $this->_params;
	}

	/**
	 * Setter de propiedad $route.
	 *
	 * @param string $value
	 */
	private function _set_route($value) {
		$this->_route = trim($value, '/');
	}

	/**
	 * Getter de propiedad $route.
	 *
	 * @return string
	 */
	private function _get_route() {
		return $this->_route;
	}

	/**
	 * Getter de propiedad $url.
	 *
	 * @return string
	 */
	private function _get_url() {
		return $_SERVER['REQUEST_URI'];
	}

	/**
	 * Getter de propiedad $method.
	 *
	 * @return string
	 */
	private function _get_method() {
		return strtolower($_SERVER['REQUEST_METHOD']);
	}

	/**
	 * Getter de propiedad $ip.
	 *
	 * @return string|null
	 */
	private function _get_ip() {
		foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
			if (array_key_exists($key, $_SERVER) === true) {
				foreach (explode(',', $_SERVER[$key]) as $ip){
					$ip = trim($ip);
					if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
						return $ip;
					}
				}
			}
		}
		return null;
	}

	/**
	 * Getter de propiedad $userAgent.
	 *
	 * @return string
	 */
	private function _get_userAgent() {
		return $_SERVER['HTTP_USER_AGENT'];
	}
}
?>
