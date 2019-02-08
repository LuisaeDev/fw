<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

/**
 * Maneja el request, response y errores HTTP.
 */
class Http {

	/** @var HttpRequest Request del framework */
	private static $_request;

	/** @var HttpResponse Response del framework */
	private static $_response;

	/** @var array Almacena los manejadores de errores HTTP */
	private static $_errorHandler = array();

	// @var int Contador de errores http producidos de manera recursiva
	private static $_recursiveHttpError = 0;

	/**
	 * Retorna el request manejado por el framework.
	 *
	 * @return HttpRequest
	 */
	public static function getRequest() {
		if (self::$_request == null) {
			self::$_request = new HttpRequest();
		}
		return self::$_request;
	}

	/**
	 * Retorna el response manejado por el framework.
	 *
	 * @return HttpResponse
	 */
	public static function getResponse() {
		if (self::$_response == null) {
			self::$_response = new HttpResponse();
		}
		return self::$_response;
	}

	/**
	 * Registra un manejador de error HTTP.
	 *
	 * @param int             $errorCode Código del error HTTP
	 * @param function|string $endpoint  Endpoint o callback del error
	 *
	 * @return void
	 */
	public static function setErrorHandler($errorCode, $endpoint) {
		self::$_errorHandler[$errorCode] = $endpoint;
	}

	/**
	 * Lanza un error HTTP.
	 *
	 * @param int  $statusCode Código HTTP de error
	 * @param bool $useHandler Define si debe utilizar el handler correspondiente al error
	 *
	 * @return void
	 */
	public static function throwError($statusCode, $useHandler = true) {

		// Reinicia el framework
		Fw::restart();

		// Define el estado del error
		self::getResponse()->status = $statusCode;

		// Verifica si debe ejecutarse el handler correspondiente al error
		if (($useHandler == true) && (isset(self::$_errorHandler[$statusCode])) && (self::$_recursiveHttpError <= 5)) {

			// Aumenta el indicador de error http recursivos
			self::$_recursiveHttpError++;

			// Obtiene el handler del error
			$errorHandler = self::$_errorHandler[$statusCode];

			// Verifica si el handler es una función callback
			if (is_callable($errorHandler)) {
				call_user_func_array($errorHandler, [ self::getRequest(), self::getResponse() ]);

			// Verifica el endpoint del handler
			} else if (is_string($errorHandler)) {

				// Obtiene el tipo y el path del endpoint
				$errorHandler = explode(':', $errorHandler);
				if (count($errorHandler) == 1) {
					$type = 'controller';
					$path = trim($errorHandler[0]);
				} else {
					$type = trim($errorHandler[0]);
					$path = trim($errorHandler[1]);
				}

				// Carga el endpoint
				switch ($type) {
					case 'controller':
						MainController::loadController($path);
						break;

					case 'view':
						if (Http::getRequest()->isXHR() == false) {
							MainController::loadView($path);
						}
						break;

					case 'endpoint':
						MainController::loadEnpointHandler($path);
						break;
				}
			}
		}

		// Emite el response
		Http::getResponse()->emit();
	}

	/**
	 * Realiza un redireccionamiento.
	 *
	 * @param string $url Url a redireccionar
	 *
	 * @return void
	 */
	public static function location($url) {

		// Remueve cualquier salida previa
		ob_end_clean();

		// Finaliza el request
		Fw::end();

		// Realiza el redireccionamiento
		header('Location: ' . $url);
		exit;
	}
}
?>
