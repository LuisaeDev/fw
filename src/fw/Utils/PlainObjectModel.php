<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Utils;

use Fw\Input;

/**
 * Manejador de objetos planos (Datos tipo array de dos dimensiones).
 */
class PlainObjectModel {

	/** @var array Objeto anidado */
	private $_nodes = array();

	/** @var string Caracter delimitador de las rutas */
	private $_delimiter;

	/** @var string Determina si deben capturarse parámetros a través de expresiones regulares especificadas en las rutas */
	private $_captureParams;

	/** @var array Parámetros capturados */
	private $_params;

	/**
	 * Contructor.
	 *
	 * @param string $delimiter     Define el delimitador a utilizar por la instancia
	 * @param bool   $captureParams Determina si deben capturarse parámetros a través de expresiones regulares especificadas en las rutas
	 */
	public function __construct($delimiter, $captureParams) {
		$this->_nodes = array();
		$this->_delimiter = $delimiter;
		$this->_captureParams = $captureParams;
	}

	/**
	 * Obtiene un endpoint a través de una ruta.
	 *
	 * @param string $path Ruta hacia el endpoint
	 *
	 * @return mixed|null Definición del endpoint o null si pudo obtenerse
	 */
	public function getEndpoint($path) {

		// Remueve espacios en blanco y el delimitador que estén al inicio y final de la ruta
		$path = trim($path);
		$path = ltrim($path, $this->_delimiter);
		$path = rtrim($path, $this->_delimiter);

		// Divide la ruta en segmentos
		$paths = explode($this->_delimiter, $path);

		// Remueve parámetros previamente capturados
		$this->_params = array();

		// Recorre los nodos para obtener el endpoint
		return $this->_findEndpoint($this->_nodes, $paths);
	}

	/**
	 * Agrega una serie de endpoints en el objeto.
	 *
	 * @param array    $paths Rutas hacia los endpoints
	 * @param callable $cb    Callback llamado para alterar cada endpoint previo a ser agregado en el objeto
	 *
	 * @return void
	 */
	public function addEndpoints($paths, $cb = null) {
		if ((is_array($paths)) && (ArrayTools::isAssociative($paths))) {
			foreach ($paths as $path => $endpoint) {
				$this->addEnpoint($path, $endpoint, $cb);
			}
		}
	}

	/**
	 * Agrega una endpoint en el objeto.
	 *
	 * @param string   $path     Ruta hacia el endpoint
	 * @param mixed    $endpoint Definición del endpoint
	 * @param callable $cb       Callback llamado para alterar un endpoint previo a ser agregado en el objeto
	 *
	 * @return void
	 */
	public function addEnpoint($path, $endpoint, $cb = null){

		// Remueve espacios en blanco y el caracter delimitador que estén al inicio o final de la ruta
		$path = trim($path);
		$path = ltrim($path, $this->_delimiter);
		$path = rtrim($path, $this->_delimiter);

		// Divide la ruta en segmentos
		$paths = explode($this->_delimiter, $path);

		// Registra la ruta de forma anidada hasta llegar al endpoint.
		$this->_nodes = $this->_registerEndpoint($this->_nodes, $paths, $endpoint, $cb);
	}

	/**
	 * Devuelve los parámetros capturares luego de una llamada al método getEndpoint()
	 *
	 * @return array parámetros capturados
	 */
	public function getCapturedParams() {

		// Se re-ordenan los parámetros ya que fueron registrados de forma inversa al obtener el endpoint
		return array_reverse($this->_params);
	}

	/**
	 * Retorna todos los nodos del objeto.
	 *
	 * @return array
	 */
	public function getNodes() {
		return $this->_nodes;
	}

	/**
	 * Redefine todos los nodos del objeto.
	 *
	 * @param array $nodes Nodos anidados
	 *
	 * @return void
	 */
	public function setNodes($nodes) {
		if ((is_array($nodes)) && (ArrayTools::isAssociative($nodes))) {
			$this->_nodes = $nodes;
		}
	}

	/**
	 * Método recursivo para agregar de forma anidada una ruta hacia un endpoint.
	 *
	 * @param array &$node    Nodo anidado pasado por referencia
	 * @param array $paths    Ruta segmentada
	 * @param mixed $endpoint Definición del endpoint
	 * @param callable $cb    Callback a llamar para alterar el endpoint previo a ser agregado en el nodo
	 *
	 * @return array Nodo anidado
	 */
	private function _registerEndpoint(&$node, $paths, $endpoint, $cb = null) {

		// Nombre del nuevo nodo a registrar (Segmento de la ruta)
		$path = $paths[0];

		// Remueve de $paths el segmento de la ruta que va a registrarse
		$paths = array_slice($paths, 1);

		// Se agrega el nodo si no había sido creado
		if (!isset($node[$path])) {
			$node[$path] = array();
		}

		// Si es el último segmento de la ruta se agrega el endpoint
		if (count($paths) == 0) {

			// Llama al callback para alterar el endpoint previo a almacenarse en el nodo
			if ((isset($cb)) && (is_callable($cb))) {
				$endpoint = call_user_func($cb, $endpoint);
			}

			// Registra el endpoint en el nodo
			$node[$path]['endpoint'] = $endpoint;

		// Si no es el último segmento de la ruta se agrega un nodo anidado
		} else {

			// Se agrega 'nested' en el nodo donde se registrarán el nodo anidado
			if (!isset($node[$path]['nested'])) {
				$node[$path]['nested'] = array();
			}

			// Llama recursivamente el método para agregar en el nodo actual el resto de los segmentos de la ruta hacia el endpoint
			$node[$path]['nested'] = $this->_registerEndpoint($node[$path]['nested'], $paths, $endpoint, $cb);
		}

		// Retorna el nodo
		return $node;
	}

	/**
	 * Método recursivo para obtener un endpoint en el objeto anidado.
	 *
	 * @param array $node Nodo anidado
	 * @param array $paths Ruta segmentada
	 *
	 * @return mixed|null Definición del endpoint o null si pudo obtenerse
	 */
	private function _findEndpoint($node, $paths) {

		// Segmento de ruta a buscar
		$path = $paths[0];

		// Remueve de $paths el segmento de la ruta a buscar
		$paths = array_slice($paths, 1);

		// Recorre en la iteración actual todos los nodos anidados
		foreach ($node as $nodeName => $node) {

			// Parámetro capturado a través un nombre de nodo definido como expresión regular
			$param = null;

			// Si se deben capturar parámetros en expresiones regulares
			// Verifica y captura si el nodo está definido como una expresión regular
			if (($this->_captureParams) && ((substr($nodeName, 0, 1) == '[') || (substr($nodeName, 0, 1) == '('))) {

				// Construye y valida la expresión regular. (Si la expresión inicia con '[' la encierra entre paréntesis)
				if (substr($nodeName, 0, 1) == '[') {
					$regex = '/^(' . $nodeName . ')$/';
				} else {
					$regex = '/^' . $nodeName . '$/';
				}
				$match = filter_var($path, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $regex)));

				// Si cumplió la expresión regular se almacena el segmento de ruta como un parámetro capturado
				if ($match !== false) {
					$param = Input::depure('string', $match);
					$match = true;
				}

			// Si el segmento de ruta es igual que el nombre del nodo
			} else if ($nodeName == $path) {
				$match = true;

			// Si no coincide el segmento de ruta con el nodo
			} else {
				$match = false;
			}

			// Si el segmento de ruta coincidió con el nodo
			if ($match) {

				// Si es el último segmento de ruta y el nodo tiene un endpoint
				if ((count($paths) == 0) && (isset($node['endpoint']))) {

					// Registra si hay un parámetro capturado
					if (isset($param)) {
						$this->_params[] = $param;
					}

					// Devuelve el endpoint correspondiente a la ruta
					return $node['endpoint'];

				// Si hay más segmentos de rutas y el endpoint tiene nodos anidados
				} else if ((count($paths) > 0) && (isset($node['nested']))) {

					// Llama recursivamente el método para buscar en el nodo actual el resto de los segmentos de la ruta hacia el endpoint
					$result = $this->_findEndpoint($node['nested'], $paths);

					// Si no se obtuvo el endpoint en los nodos anidados
					// Y si se está capturando parámetros por expresiones regulares
					// Se continua la búsqueda en el resto de nodos de la iteración actual
					if (($result === null) && ($this->_captureParams)) {
						continue;
					} else {

						// Registra si hay un parámetro capturado
						if (isset($param)) {
							$this->_params[] = $param;
						}

						// Devuelve el endpoint correspondiente a la ruta
						return $result;
					}
				}
			}
		}

		// No se encontró un endpoint para la ruta especificada
		return null;
	}
}
?>
