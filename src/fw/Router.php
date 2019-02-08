<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use Fw\Utils\PlainObjectModel;

/**
 * Enrutador del framework.
 */
class Router {

	/** @var PlainObjectModel Modelo de datos de rutas */
	private static $_routes;

	/** @var array Mapa de tags registradas */
	private static $_mappedTags = array();

	/** @var bool Define si las rutas fueron cargadas desde el cache  */
	private static $_fromCache = false;

	/** @var array Almacena todos los handlers de validación */
	private static $_validationHandlers = array();

	/** @var array Almacena todos los handlers de tipo endpoint */
	private static $_endpointsHandlers = array();

	/**
	 * Inicializa el enrutador del framework.
	 * 
	 * @return void
	 */
	public static function initialize() {

		// Construye el modelo de datos de las rutas
		self::$_routes = new PlainObjectModel('/', true);

		// Carga desde cache los datos
		try {

			$cached = CacheSystem::load('routes', './conf/routes.php');
			
			// Almacena las rutas y el mapa de tags
			self::$_routes->setNodes($cached['routes']);
			self::$_mappedTags = $cached['tags'];

			// Define que los datos fueron importados desde cache
			self::$_fromCache = true;

			// Siempre se importan el archivo de configuración de rutas para registrar únicamente los handlers
			require_once './conf/routes.php';

		} catch (FwException_CacheSystem $e) {

			// Se importan las rutas desde el archivo de configuración
			require_once './conf/routes.php';

			// Se almacena en cache los nodos de rutas y el mapa tags
			CacheSystem::save('fw/routes', './conf/routes.php', array(
				'routes' => self::$_routes->getNodes(),
				'tags' => self::$_mappedTags
			));
		}
	}

	/**
	 * Extiende un handler de validación.
	 * 
	 * Los manejadores de validación son especificados en los tags de los endpoints como un función validate( ... )
	 * De estar especificado un validador en un tag, este será llamado desde MainController al tratar de validar la ejecución de un endpoint
	 * 
	 * @param string   $name     Nombre del handler
	 * @param function $callback Callback del handler
	 * 
	 * @return void
	 */
	public static function extendValidation($name, $callback) {
		self::$_validationHandlers[$name] = $callback;
	}

	/**
	 * Devuelve un handler de validación.
	 * 
	 * @param string $name Nombre del handler
	 * 
	 * @return function|false Devuelve el handler o false si no está definido
	 */
	public static function getValidation($name) {
		if ((isset(self::$_validationHandlers[$name])) && (is_callable(self::$_validationHandlers[$name]))) {
			return self::$_validationHandlers[$name];
		} else {
			return false;
		}
	}

	/**
	 * Registra un handler para un endpoint de tipo función.
	 * 
	 * @param string   $name     Nombre del handler
	 * @param function $callback Callback del handler
	 * 
	 * @return void
	 */
	public static function setEndpointHandler($name, $callback) {
		self::$_endpointsHandlers[$name] = $callback;
	}

	/**
	 * Retorna un handler de un endpoint de tipo función.
	 * 
	 * @param string $name Nombre del handler
	 * 
	 * @return void
	 */
	public static function getEndpointHandler($name) {
		if (isset(self::$_endpointsHandlers[$name])) {
			return self::$_endpointsHandlers[$name];
		} else {
			return null;
		}
	}

	/**
	 * Registra una seria de rutas.
	 * 
	 * @param array  $routes Array de rutas a registrar { '{route}' => '{endpoint}', ... }
	 * @param string $tags   Definición de tags para asignar a cada uno de los endpoints
	 * 
	 * @return void
	 */
	public static function setRoutes($routes, $tags = null) {

		// No registra las rutas si fueron importadas desde el cache
		if (self::$_fromCache) {
			return;
		}

		// Registra cada una de las rutas
		foreach ($routes as $route => $endpoint) {
			self::setRoute($route, $endpoint, $tags);
		}
	}

	/**
	 * Registra una ruta.
	 * 
	 * @param string $route    Ruta a registrar
	 * @param string $endpoint Definición del endpoint
	 * @param string $tags     Definición de tags del endpoint
	 * 
	 * @return void
	 */
	public static function setRoute($route, $endpoint, $tags = null) {

		// No registra la ruta si fueron importadas desde el cache
		if (self::$_fromCache) {
			return;
		}

		// Registra la ruta y el endpoint en el modelo de datos
		self::$_routes->addEnpoint($route, $endpoint, function($endpoint) use ($tags) {

			// Se separa el endpoint y los tags
			$endpoint = self::_parseEndpoint($endpoint);

			// Si hay inline-tags definidos en el endpoint, se hace un parse y se registrar en el mapa de tags
			if (isset($endpoint['inline-tags'])) {
				self::$_mappedTags[$endpoint['inline-tags']] = self::_parseTags($endpoint['inline-tags']);
			}

			// Si se especificaron tags por el argumento $tags, se hace un parse y registran en el mapa de tags
			if (isset($tags)) {
				$tags = str_replace(' ', '', $tags);
				$endpoint['tags'] = $tags;
				self::$_mappedTags[$tags] = self::_parseTags($tags);
			} else {
				$endpoint['tags'] = null;
			}

			// Retorna la nueva definición del endpoint
			return $endpoint;
		});
	}

	/**
	 * Obtiene un endpoint.
	 * 
	 * @param string $route Ruta del endpoint a obtener
	 * 
	 * @return array|null Datos del endpoint o null cuando no pudo obtenerse
	 */
	public static function getEndpoint($route) {

		// Obtiene el endpoint desde el modelo de datos de las rutas
		$endpoint = self::$_routes->getEndpoint($route);

		// Si se obtuvo, se altera la definición del endpoint
		if ($endpoint) {

			// Recupera del mapa de tags los inline-tags del endpoint
			if ((isset($endpoint['inline-tags'])) && (isset(self::$_mappedTags[$endpoint['inline-tags']]))) {
				$endpoint['inline-tags'] = self::$_mappedTags[$endpoint['inline-tags']];
			} else {
				$endpoint['inline-tags'] = [];
			}

			// Recupera del mapa de tags los tags del endpoint
			if ((isset($endpoint['tags'])) && (isset(self::$_mappedTags[$endpoint['tags']]))) {
				$endpoint['tags'] = self::$_mappedTags[$endpoint['tags']];
			} else {
				$endpoint['tags'] = [];
			}

			// Fusiona los tags e inline-tags
			$endpoint['tags'] = array_merge($endpoint['tags'], $endpoint['inline-tags']);

			// Remueve inline-tags del endpoint
			unset($endpoint['inline-tags']);

			// Recorre todos los tags del endpoint y exluye los tags params()
			// Obtiene los keys para los parámetros del endpoint
			$tags = [];
			$paramsKeys = [];
			foreach ($endpoint['tags'] as $i => $tag) {
				if ($tag['name'] == 'params') {
					$paramsKeys = $tag['value'];
				} else {
					$tags[] = $tag;
				}
			}

			// Define los tags del endpoint
			$endpoint['tags'] = $tags;

			// Obtiene los parámetros capturados desde el modelo de datos
			$endpoint['params'] = self::$_routes->getCapturedParams();

			// Si fueron especificados los keys para los parámetros se agregan con sus valores en el mismo orden en que fueron capturados
			if (count($paramsKeys) > 0) {
				foreach ($paramsKeys as $i => $key) {
					if (isset($endpoint['params'][$i])) {
						$endpoint['params'][$key] = $endpoint['params'][$i];
					}
				}
			}

			$endpoint = (object)$endpoint;
		}
		return $endpoint;
	}

	/**
	 * Identifica la estructura de datos de un endpoint a partir de un string.
	 * 
	 * @param string $strEndpoint Definición del endpoint
	 * 
	 * @return array Estructura de datos del endpoint
	 */
	private static function _parseEndpoint($strEndpoint) {
		$brakets = '{}';

		// Remueve los espacios en blanco al inicio y el final
		$strEndpoint = str_replace(' ', '', $strEndpoint);

		// Identifica la ruta y las etiquetas
		$endpoint = explode($brakets[0], $strEndpoint);
		if (count($endpoint) == 1) {
			$endpoint = array(
				'path' => trim($endpoint[0]),
				'inline-tags' => null
			);
		} else {
			if (substr($endpoint[1], -1) == $brakets[1]) {
				$endpoint = array(
					'path' => trim($endpoint[0]),
					'inline-tags' => trim(substr($endpoint[1], 0, -1))
				);
			} else {
				$endpoint = array(
					'path' => trim($endpoint[0]),
					'inline-tags' => null
				);
			}
		}

		// Determina el tipo y path del endpoint
		$path = explode(':', $endpoint['path']);
		if (count($path) == 1) {
			$endpoint['type'] = 'controller';
			$endpoint['path'] = trim($path[0]);

		} else {
			$endpoint['type'] = trim($path[0]);
			$endpoint['path'] = trim($path[1]);
		}

		// Devuelve el endpoint
		return $endpoint;
	}

	/**
	 * Identifica la estructura de tags a partir de un string.
	 * 
	 * @param string $strTags String de tags
	 * 
	 * @return array Estrcutura de datos de las tags
	 */
	private static function _parseTags($strTags) {

		// Remueve los espacios en blanco
		$strTags = str_replace(' ', '', $strTags);

		// Dentro de las funciones ( ... ) reemplaza los caracteres ',' por '|' para separar los argumentos
		if (strpos($strTags, '(')) {
			$strTags = explode('(', $strTags);
			foreach ($strTags as $i => $tag) {
				if ($i > 0) {
					$params = explode(')', $tag);
					$params[0] = str_replace(',', '*', $params[0]);
					$strTags[$i] = implode(')', $params);
				}
			}
			$strTags = implode('(', $strTags);
		}

		// Separa las etiquetas por ','
		$strTags = explode(',', $strTags);

		// Verifica cada etiqueta si es una variable o función
		$tags = array();
		foreach ($strTags as $tag) {
			preg_match_all('/^([\!]?[a-z\_]+)(?:\(([a-z0-9\_\-\|\@\/\.]+)*\))*$/i', $tag, $matches, PREG_SET_ORDER);
			if ($matches) {

				// Define el nombre de la etiqueta
				$tagName = $matches[0][1];

				// Define la etiqueta como función y almacena los parámetros encontrados
				if (isset($matches[0][2])) {
					$tagName = ltrim($tagName, '!');
					$tags[] = [ 'name' => $tagName, 'value' => explode('*', $matches[0][2]) ];
				} else {

					// Si el tag comienza con '!', su valor será false, caso contrario es true
					if (substr($tagName, 0, 1) == '!') {
						$tags[] = [ 'name' => ltrim($tagName, '!'), 'value' => false ];
					} else {
						$tags[] = [ 'name' => $tagName, 'value' => true ];
					}
				}
			}
		}
		return $tags;
	}
}
?>