<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use DateInterval;

/**
 * Maneja el response del framework.
 *
 * @property int    $status       Códito de status del response
 * @property string $ETag         ETag para el response
 * @property string $lastModified Última modificación del response
 * @property string $cache        Cache en segundos a establecer en el response
 */
class HttpResponse {

	/** @var string|null Define el tipo de response a emitir */
	private $_type = null;

	/** @var int Código de status */
	private $_status = 200;

	/** @var array Header a especificar */
	private $_headers = array();

	/** @var string|null Cuerpo del response */
	private $_body = null;

	/** @var string|null Directorio para un response de tipo 'file' */
	private $_filePath = null;

	/** @var string|null Datos para un response de tipo 'file-data' */
	private $_fileData = null;

	/** @var int Define el cache en segundos para el response */
	private $_cache = null;

	/** @var string|null Fecha de última modificación del response */
	private $_lastModified = null;

	/** @var string|null ETag del response */
	private $_ETag = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
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
	 * Define el cuerpo del response.
	 *
	 * @param string      $data        Contenido del response
	 * @param string|null $contentType Tipo de contenido del response
	 *
	 * @return void
	 */
	public function body($data, $contentType = null) {

		// Define el tipo de response
		$this->_type = 'body';

		// Define el cuerpo del response
		$this->_body = $data;

		// Define el Content-Type
		if ($contentType) {
			$this->setHeader('Content-Type', $contentType, true);
		}
	}

	/**
	 * Define un response de tipo JSON.
	 *
	 * @param array $data Array a codificar en formato JSON
	 *
	 * @return void
	 */
	public function json($data) {

		// Define el tipo de response
		$this->_type = 'body';

		// Define el cuerpo del response
		$this->_body = json_encode($data);

		// Define el Content-Type
		$this->setHeader('Content-Type', 'application/json');
	}

	/**
	 * Define un archivo como response.
	 *
	 * @param string      $path        Directorio de un archivo
	 * @param string      $contentType Tipo de contenido del response
	 * @param string|null $fileName    Se puede especificar el nombre del archivo, al especificarlo se le avisa al browser que lo debe descargar
	 *
	 * @return void
	 */
	public function file($path, $contentType, $filename = null) {

		// Define el tipo de response
		$this->_type = 'file';

		// Almacena el directorio del archivo
		$this->_filePath = $path;

		// Define el Content-Type
		$this->setHeader('Content-Type', $contentType);

		// Si se definió el nombre del archivo, se define el header Content-Disposition para descargar el archivo
		if (isset($filename)) {
			$this->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
		}
	}

	/**
	 * Define un archivo como response.
	 *
	 * @param string      $data        Buffer de datos del archivo
	 * @param string      $contentType Tipo de contenido del response
	 * @param string|null $fileName    Se puede especificar el nombre del archivo, al especificarlo se le avisa al browser que lo debe descargar
	 *
	 * @return void
	 */
	public function fileData($data, $contentType, $filename = null) {

		// Define el tipo de response
		$this->_type = 'file-data';

		// Almacena el buffer de datos del archivo
		$this->_fileData = $data;

		// Define el Content-Type
		$this->setHeader('Content-Type', $contentType);

		// Si se definió el nombre del archivo, se define el header Content-Disposition para descargar el archivo
		if (isset($filename)) {
			$this->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
		}
	}

	/**
	 * Emite el HTTP response.
	 *
	 * @param int $statusCode Código de status de la respuesta
	 *
	 * @return void
	 */
	public function emit($statusCode = null) {

		// Limpia el buffer de salida
		if (ob_get_contents()) {
			ob_end_clean();
		}

		// Si la respuesta es de tipo 'file', verifica que el archivo exista
		if ($this->_type == 'file') {
			if (!file_exists($this->_filePath)) {
				Http::throwError(404, false);
			}
		}

		// Define los headers para el cache
		if ($this->_cache !== null) {
			self::_setCacheHeaders();
		}

		// Agrega el header ETag si fue especificado a través de la propiedad $ETag
		if ($this->ETag !== null) {
			$this->setHeader('ETag', $this->ETag);
		}

		// Agrega el header Last-Modified y If-Modified-Since si fue especificado a través de la propiedad $lastModified
		if ($this->lastModified !== null) {
			$this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $this->lastModified) . ' GMT');
		}

		// Define el status del response recibido por parámetro en este método
		if ($statusCode !== null) {
			$this->_status = $statusCode;
		}

		// Verifica si el response a emitir no ha sido modificado con respecto al request
		if (Conf::getParam('response_emit_304') == true) {
			if ($this->ifNotModified() == true) {
				$this->status = 304;
			}
		}

		// Define el status del response
		if ($this->_getStatusInfo($this->_status) !== null) {
			header($this->_getStatusInfo($this->_status), true, $this->_status);
		}

		// Emite los headers
		foreach ($this->_headers as $header) {
			header($header['content'] . ': ' . $header['value']);
		}

		// Finaliza si el status es 304
		if ($this->_status == 304) {
			return;
		}

		// Contenido del response
		switch ($this->_type) {
			case 'body':
				echo $this->_body;
				break;

			case 'file':
				header('Content-Length: ' . filesize($this->_filePath));
				readfile($this->_filePath);
				break;

			case 'file-data':
				header('Content-Length: ' . mb_strlen($this->_fileData, '8bit'));
				echo $this->_fileData;
				break;
		}

		// Finaliza el request
		Fw::end();
		exit;
	}

	/**
	 * Reinicia el response.
	 *
	 * @return void
	 */
	public function clear() {

		// Limpia el buffer de salida
		if (ob_get_contents()) {
			ob_end_clean();
		}

		// Reinicia todas las propiedades del response
		$this->_type = null;
		$this->_status = 200;
		$this->_headers = array();
		$this->_body = null;
		$this->_filePath = null;
		$this->_fileData = null;
		$this->_cache = null;
		$this->_lastModified = null;
		$this->_ETag = null;
	}

	/**
	 * Agrega un header al response.
	 *
	 * @param string $content Contenido de la respuesta
	 * @param string $value   Valor del contenido de la respuesta
	 * @param bool   $replace Define si debe reemplazarse los encabezados del mismo tipo de contenido
	 *
	 * @return void
	 */
	public function setHeader($content, $value, $replace = true) {

		// Reemplaza los headers previos del mismo tipo
		if ($replace) {
			foreach ($this->_headers as $i => $header) {
				if ($header['content'] == $content) {
					unset($this->_headers[$i]);
				}
			}
		}

		// Agrega el header
		array_push($this->_headers, array(
			'content' 	=> $content,
			'value' 	=> $value
		));
	}

	/**
	 * Verifica si el response a emitir no ha sido modificado con respecto al request.
	 *
	 * Compara las propiedades 'ETag' y 'lastModified' especificados en este response o en el archivo a responder, con los datos especificados en los headers del request.
	 *
	 * @param bool $emit Define si debe emitir el response
	 *
	 * @return bool Devuelve true cuando el response no ha sido modificado con respecto al request
	 */
	public function ifNotModified($emit = false) {
		$success = false;

		// Obtiene el request del framework
		$request = Http::getRequest();

		// Verifica si el ETag del request coincide
		if (($this->ETag !== null) && ($this->ETag === $request->getHeader('If-None-Match'))) {
			$success = true;
		}

		// Verifica si el If-Modified-Since del request coincide
		if (($this->lastModified !== null) && ($request->getHeader('If-Modified-Since') != null) && ($this->lastModified === (int)FwDateTime::getTimestampFrom($request->getHeader('If-Modified-Since')))) {
			$success = true;
		}

		// Determina si debe emitir el response 304
		if ($success && $emit) {
			$this->emit(304);
		} else {
			return $success;
		}
	}

	/**
	 * Define los headers para el cache del response.
	 *
	 * @return void
	 */
	private function _setCacheHeaders() {

		// Si el cache fue especificado como string
		if (is_string($this->_cache)) {
			$this->setHeader('Cache-Control', $this->_cache);

		// Si el cache fue especificado como entero
		} else if ($this->_cache == 0) {
			$this->setHeader('Cache-Control', 'no-cache');
		} else if ($this->_cache > 0) {
			$this->setHeader('Cache-Control', 'public, max-age=' . $this->_cache);
		}
	}

	/**
	 * Devuelve la cadena de información relacionado a un código de estado.
	 *
	 * @param int $code Código de status
	 *
	 * @return string|null
	 */
	private function _getStatusInfo($code) {
		$list = array(
			100 => 'HTTP/1.1 100 Continue',
			101 => 'HTTP/1.1 101 Switching Protocols',
			200 => 'HTTP/1.1 200 OK',
			201 => 'HTTP/1.1 201 Created',
			202 => 'HTTP/1.1 202 Accepted',
			203 => 'HTTP/1.1 203 Non-Authoritative Information',
			204 => 'HTTP/1.1 204 No Content',
			205 => 'HTTP/1.1 205 Reset Content',
			206 => 'HTTP/1.1 206 Partial Content',
			300 => 'HTTP/1.1 300 Multiple Choices',
			301 => 'HTTP/1.1 301 Moved Permanently',
			302 => 'HTTP/1.1 302 Found',
			303 => 'HTTP/1.1 303 See Other',
			304 => 'HTTP/1.1 304 Not Modified',
			305 => 'HTTP/1.1 305 Use Proxy',
			307 => 'HTTP/1.1 307 Temporary Redirect',
			400 => 'HTTP/1.1 400 Bad Request',
			401 => 'HTTP/1.1 401 Unauthorized',
			402 => 'HTTP/1.1 402 Payment Required',
			403 => 'HTTP/1.1 403 Forbidden',
			404 => 'HTTP/1.1 404 Not Found',
			405 => 'HTTP/1.1 405 Method Not Allowed',
			406 => 'HTTP/1.1 406 Not Acceptable',
			407 => 'HTTP/1.1 407 Proxy Authentication Required',
			408 => 'HTTP/1.1 408 Request Time-out',
			409 => 'HTTP/1.1 409 Conflict',
			410 => 'HTTP/1.1 410 Gone',
			411 => 'HTTP/1.1 411 Length Required',
			412 => 'HTTP/1.1 412 Precondition Failed',
			413 => 'HTTP/1.1 413 Request Entity Too Large',
			414 => 'HTTP/1.1 414 Request-URI Too Large',
			415 => 'HTTP/1.1 415 Unsupported Media Type',
			416 => 'HTTP/1.1 416 Requested Range Not Satisfiable',
			417 => 'HTTP/1.1 417 Expectation Failed',
			500 => 'HTTP/1.1 500 Internal Server Error',
			501 => 'HTTP/1.1 501 Not Implemented',
			502 => 'HTTP/1.1 502 Bad Gateway',
			503 => 'HTTP/1.1 503 Service Unavailable',
			504 => 'HTTP/1.1 504 Gateway Time-out',
			505 => 'HTTP/1.1 505 HTTP Version Not Supported',
		);
		if (isset($list[$code])) {
			return $list[$code];
		}
	}

	/**
	 * Setter de propiedad $status.
	 *
	 * @param int $value
	 */
	private function _set_status($value) {
		$this->_status = $value;
	}

	/**
	 * Getter de propiedad $status.
	 *
	 * @return int
	 */
	private function _get_status() {
		return $this->_status;
	}

	/**
	 * Setter de propiedad $ETag.
	 *
	 * @param string|null $value
	 */
	private function _set_ETag($value) {
		$this->_ETag = $value;
	}

	/**
	 * Getter de propiedad $ETag.
	 *
	 * @return string|null
	 */
	private function _get_ETag() {

		// Devuelve el ETag especificado
		if (isset($this->_ETag)) {
			return $this->_ETag;
		}

		// Devuelve el ETag correspondiente al archivo
		if ($this->_type == 'file') {
			if (file_exists($this->_filePath)) {
				return md5_file($this->_filePath);
			}
		}

		return null;
	}

	/**
	 * Setter de propiedad $lastModified.
	 *
	 * @param int|string|null $value
	 */
	private function _set_lastModified($value) {
		$this->_lastModified = $value;
	}

	/**
	 * Getter de propiedad $lastModified.
	 *
	 * @return int|null
	 */
	private function _get_lastModified() {

		// Devuelve la fecha de modificación especificado para el response
		if (isset($this->_lastModified)) {
			return FwDateTime::getTimestampFrom($this->_lastModified);
		}

		// Devuelve la fecha de modificación correspondiente al archivo
		if ($this->_type == 'file') {
			if (file_exists($this->_filePath)) {
				return FwDateTime::getTimestampFrom(filemtime($this->_filePath));
			}
		}

		return null;
	}

	/**
	 * Setter de propiedad $cache.
	 *
	 * @param int|string $value Cantidad de segundos o valor para el header 'Cache-Control'
	 *
	 * @return void
	 */
	public function _set_cache($value) {
		$this->_cache = $value;
	}

	/**
	 * Getter de propiedad $cache.
	 *
	 * @return int
	 */
	private function _get_cache() {
		return $this->_cache;
	}
}
?>
