<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

/**
 * Controlador principal del Framework.
 *
 * Procesa el request obtenido por la clase HTTP.
 */
class MainController {

	/**
	 * Procesa el request del Framework.
	 *
	 * Verifica el request, obtiene el endpoint y ejecuta el controlador, vista o endpoint handler
	 *
	 * @return void
	 *
	 * @throws FwError
	 */
	public static function processRequest() {

		// Obtiene los datos del endpoint correspondiente a la ruta del request actual
		$endpoint = Router::getEndpoint(Http::getRequest()->route);

		try {

			// Si no se obtuvo el endpoint
			if ($endpoint === null) {
				throw new FwException(404);
			}

			// Valida cada tag del endpoint
			foreach ($endpoint->tags as $tag) {
				switch ($tag['name']) {

					// Valida la autenticación y tipo de usuario
					case 'logged':
						if ($tag['value'] === true) {
							if (!Auth::isLogged()) {
								throw new FwException(401);
							}
						} else if ($tag['value'] === false) {
							if (Auth::isLogged()) {
								throw new FwException(404);
							}
						} else {
							if (!in_array(Auth::getCurrentUser()->type, $tag['value'])) {
								throw new FwException(401);
							}
						}
						break;

					// Valida el método del request
					case 'method':
						if (!in_array(Http::getRequest()->method, $tag['value'])) {
							throw new FwException(403);
						}
						break;

					// Valida si el request es o no XHR
					case 'xhr':
						if ($tag['value'] !== Http::getRequest()->isXHR()) {
							throw new FwException(403);
						}
						break;

					// Valida si debe estar en modo distribución
					case 'dist':
						if ($tag['value'] !== Conf::getParam('dist')) {
							throw new FwException(403);
						}
						break;

					// Valida si debe estar en modo depuración
					case 'debug':
						if ($tag['value'] !== Conf::getParam('debug')) {
							throw new FwException(403);
						}
						break;

					// Valida el acceso para ciertos rangos de ip
					case 'ip':
						$match = false;
						foreach ($tag['value'] as $ip) {
							if (self::_matchIpv4(Http::getRequest()->ip, $ip) == true) {
								$match = true;
								break;
							}
						}
						if (!$match) {
							throw new FwException(403);
						}
						break;

					// Verifica si el tag corresponde a una validación extendida
					default:
						$validation = Router::getValidation($tag['name']);
						if ($validation !== false) {

							// Verifica los parámetros a pasar al manejador de validación
							if ($tag['value'] === true) {
								$tag['value'] = [ $endpoint ];
							} else {
								$tag['value'] = array_merge([ $endpoint ], $tag['value']);
							}

							// Ejecuta la validación
							$result = call_user_func_array($validation, $tag['value']);

							// Si la respuesta es 200 o true, es válida
							if (($result == 200) or ($result == true)) {
								continue;

							// Si es false, se emitirá un error 403
							} else if ($result == false) {
								throw new FwException(403);

							// Si no, se emite el error especificado en la respuesta
							} else {
								throw new FwException($result);
							}
						}
						break;
				}
			}

		} catch (FwException $e) {
			Http::throwError($e->getCode());
		}

		// Agrega los parámetros capturados por el endpoint
		Http::getRequest()->params = $endpoint->params;

		// Evento 'ready', indica que el Framework está listo para cargar el endpoint
		EventsHandler::trigger('fw-ready', Http::getRequest());

		// Carga el endpoint según su tipo
		switch ($endpoint->type) {
			case 'controller':
				self::loadController($endpoint->path);
				break;

			case 'view':
				self::loadView($endpoint->path);
				break;

			case 'endpoint':
				self::loadEnpointHandler($endpoint->path);
				break;

			default:
				throw new FwError('invalid-endpoint-type', null, [ 'type' => $endpoint->type, 'route' => Http::getRequest()->route ] );
				break;
		}
	}

	/**
	 * Carga un controlador.
	 *
	 * @param string $path Path del controlador
	 *
	 * @return void
	 *
	 * @throws FwError
	 */
	public static function loadController($path) {

		// Agrega al path 'controllers'
		$path = './controllers/' . $path;

		// Verifica si no se ha especificado la extensión
		if (substr($path, -4) != '.php') {
			$path = $path . '.php';
		}

		// Carga el controlador
		if (file_exists($path)) {
			$controller = require_once $path;
		} else {
			throw new FwError('controller-not-found', null, [ 'path' => $path, 'route' => Http::getRequest()->route ] );
		}

		// Ejecuta la función del controlador y pasa el request y response
		if (is_callable($controller)) {
			call_user_func_array($controller, [ Http::getRequest(), Http::getResponse() ]);
		}
	}

	/**
	 * Carga una vista.
	 *
	 * @param string $path Path de la vista
	 * @param array  $vars Variables a pasar al template a renderizar
	 *
	 * @return void
	 *
	 * @throws FwError
	 */
	public static function loadView($path, array $vars = array()) {

		// Agrega al path 'views'
		$path = './views/' . $path;

		// Verifica si no se ha especificado la extensión
		if (substr($path, -5) != '.twig') {
			$absPath = $path . '.twig';
		}

		// Renderiza la vista
		if (file_exists($absPath)) {
			Http::getResponse()->body(Template::render($path, $vars), 'text/html');
		} else {
			throw new FwError('view-not-found', null, [ 'path' => $path, 'route' => Http::getRequest()->route ] );
		}

		// Emite el response
		Http::getResponse()->emit();
	}

	/**
	 * Carga un endpoint handler definido por la clase Router.
	 *
	 * @param string $name Nombre del endpoint handler
	 *
	 * @return void
	 *
	 * @throws FwError
	 */
	public static function loadEnpointHandler($name) {

		// Obtiene el callback del endpoint
		$callback = Router::getEndpointHandler($name);

		// Verifica si el endpoint está registrado y si puede ser llamado
		if ((isset($callback)) && (is_callable($callback))) {
			call_user_func_array($callback, [ Http::getRequest(), Http::getResponse() ]);
		} else {
			throw new FwError('endpoint-handler-not-found', null, [ 'name' => $name, 'route' => Http::getRequest()->route ]);
		}
	}

	/**
	 * Procesa una nueva ruta.
	 *
	 * @param string $route Ruta a cargar
	 *
	 * @return void
	 */
	public static function reload($route) {

		// Reinicia el framework
		Fw::restart();

		// Define la nueva ruta del request
		Http::getRequest()->route = $route;

		// Remueve los parámetros obtenidos
		Http::getRequest()->params = array();

		// Procesa nuevamente request
		self::processRequest();
	}

	/**
	 * Redirige a una nueva ruta.
	 *
	 * @param string $route Ruta a redirigir
	 *
	 * @return void
	 */
	public static function redirect($route) {
		Http::location(Fw::url('@baseUrl/' . ltrim($route, '/')));
	}

	/**
	 * Verifica si una ip de tipo ipv4 coincide con el filtro especificado
	 *
	 * @param string $ip       Ip a verificar
	 * @param string $filterIp Filtro de ip
	 *
	 * @return bool
	 */
	private static function _matchIpv4($ip, $filterIp) {

		// Wildcards
		if (strpos($filterIp, '*')) {
			$type = 'wildcards';

		// Mask, CIDR
		} else if (strpos($filterIp, '/')) {
			$tmp = explode('/', $filterIp);
			if (strpos($tmp[1], '.')) {
				$type = 'mask';
			} else {
				$type = 'cidr';
			}

		// Section
		} else if (strpos($filterIp, '-')) {
			$type = 'section';

		// Single
		} else if (ip2long($filterIp)) {
			$type = 'single';

		// localhost
		} else if ($filterIp == 'localhost') {
			$type = 'localhost';
		}

		// Verifica si la ip coincide de acuerdo al tipo
		switch ($type) {
			case 'wildcards':
				$filterIp = explode('.', $filterIp);
				$ip = explode('.', $ip);
				for ($i = 0; $i < count($filterIp); $i++) {
					if ($filterIp[$i] !== '*') {
						if ($filterIp[$i] !== $ip[$i]) {
							return false;
						}
					} else {
						return true;
					}
				}

			case 'mask':
				$allowedMask = explode('/', $filterIp)[1];
				$filterIp = explode('/', $filterIp)[0];
				$begin = (ip2long($filterIp) & ip2long($allowedMask)) + 1;
				$end = (ip2long($filterIp) | (~ ip2long($allowedMask))) + 1;
				$ip = ip2long($ip);
				return ($ip >= $begin && $ip <= $end);

			case 'cidr':
				$filterIp = explode('/', $filterIp);
				$net = $filterIp[0];
				$mask = $filterIp[1];
				return ( ip2long($ip) & ~((1 << (32 - $mask)) - 1) ) == ip2long($net);

			case 'section':
				$begin = ip2long(explode('-', $filterIp)[0]);
				$end = ip2long(explode('-', $filterIp)[1]);
				$ip = ip2long($ip);
				return ($ip >= $begin && $ip <= $end);

			case 'single':
				return (ip2long($filterIp) == ip2long($ip));

			case 'localhost':
				return ($ip == '::1') || ($ip == 'localhost');
		}
		return false;
	}
}
?>
