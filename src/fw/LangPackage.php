<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use Fw\Utils\ArrayTools;
use Fw\Utils\PlainObjectModel;

/**
 * Administrador de paquetes de idioma.
 *
 * Carga, procesa y devuelve captions de los paquetes de idioma
 */
class LangPackage {

	/** @var string Directorio absoluto del archivo JSON del lenguaje cargado para el paquete */
	private $_sourceDir;

	/** @var PlainObjectModel Modelo de datos del paquete de idioma de la instancia */
	private $_package = null;

	/** @var array Almacena todos los modelos de datos de paquetes cargados por todas las instancias */
	private static $_packages = array();

	/**
	 * Construye la instancia del paquete de idiomas.
	 *
	 * @param string $packageName Identificador único del paquete de idioma a cargar
	 * @param string $lang        Define si debe cargar el paquete de un idioma en especifico
	 */
	public function __construct($packageName, $lang = null) {

		// Verifica si el paquete ya está cargado
		if (isset(self::$_packages[$packageName])) {
			$this->_package = self::$_packages[$packageName];
		} else {
			$this->_loadPackage($packageName, $lang);
		}
	}

	/**
	 * Devuelve un caption del paquete de idioma.
	 *
	 * @param string $path   Ruta del caption
	 * @param array  $params Parámetros a reemplazar en el caption
	 *
	 * @return mixed
	 */
	public function get($path, $params = array()) {

		// Obtiene el caption desde el modelo de datos de los captions
		$caption = $this->_package->getEndpoint($path);

		// Si se obtuvo, se procesa el caption
		if ($caption !== null) {
			return $this->_resolveCaption($caption, $params);
		} else {
			return '';
		}
	}

	/**
	 * Exporta en JSON de forma parcial o completa el paquete cargado.
	 *
	 * - El paquete a retornar es un objeto plano de dos dimensiones con las rutas y captions en su definición original
	 *
	 * @param string|null $filterPath Filtro de rutas a incluir
	 *
	 * @return string Definición del paquete codificado en JSON
	 */
	public function export($filterPath = '') {

		// Define el identificador del recurso almacenado en cache
		$cacheKey = $this->_sourceDir . '?' . $filterPath;

		// Última fecha de modificación de la fuente para validar el recurso en cache
		$lastModTime = filemtime($this->_sourceDir);

		// Carga el recurso desde el cache
		try {

			$package = CacheSystem::load('lang-export', $cacheKey, $lastModTime);

		} catch (FwException_CacheSystem $e) {

			// Carga y decodifica el paquete en JSON
			$package = json_decode(file_get_contents($this->_sourceDir), true);

			// Filtra únicamente las rutas que deben incluirse
			if (!empty($filterPath)) {
				foreach ($package as $path => $caption) {
					if (preg_match('/^' . $filterPath . '/', $path) == false) {
						unset($package[$path]);
					}
				}
			}

			// Codifica el paquete en JSON
			$package = json_encode($package);

			// Almacena en cache el paquete de idioma
			CacheSystem::save('fw/lang-export', $cacheKey, $package);
		}

		return $package;
	}

	/**
	 * Remueve los paquetes de idioma cargados.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$_packages = array();
	}

	/**
	 * Resuelve un caption del paquete.
	 *
	 * @param string|array $caption Definición del caption
	 * @param array|null   $params  Variables a reemplazar/usar para el procesamiento del caption
	 *
	 * @return string Texto resultante del caption
	 */
	private function _resolveCaption($caption, $params) {

		// Si la definición del caption es un string, se devuelve sin procesar
		if (is_string($caption)) {
			return $caption;
		}

		// String resultante del caption
		$string = '';

		// Recorre todos los chain del caption
		foreach ($caption as $chain) {

			// Agrega el chain al string
			if (is_string($chain)) {
				$string .= $chain;
			}

			if (is_array($chain)) {

				// Si el change es una expresión amount, la resuelve y la agrega al string
				if ($chain['type'] == 'amount') {
					if ((isset($params[$chain['var']])) && (is_int($params[$chain['var']])) && ($params[$chain['var']] >= 0)) {
						$string .= $this->_resolveAmount($params[$chain['var']], $chain['conditions'], $params);
					} else {
						$string .= '$' . $chain['var'];
					}
				}

				// Si el change es un replace, reemplaza la variable y la agrega al string
				if ($chain['type'] == 'replace') {
					if (isset($params[$chain['var']])) {
						$string .= $params[$chain['var']];
					} else {
						$string .= '$' . $chain['var'];
					}
				}
			}
		}

		// Devuelve el string resultante del caption
		return $string;
	}

	/**
	 * Resuelve una expresión de tipo amount especificada en un caption.
	 *
	 * Determina en base a una variable que texto debe mostrar.
	 *
	 * @param integer $value    Valor a evaluar
	 * @param array   $captions Opciones de captions
	 * @param array   $params   Parámetros pasados para resolver el caption
	 *
	 * @return string Chain resuelto
	 */
	private function _resolveAmount($value, $captions, $params) {
		$result = '';

		// En base a la variable, decide la condición a optar
		if (($value > 1) && (isset($captions[0]))) {
			$result = $captions[0];
		} else if (($value == 1) && (isset($captions[1]))) {
			$result = $captions[1];
		} else if (($value <= 0) && (isset($captions[2]))) {
			$result = $captions[2];
		}

		// Si el resultado de la condición es un array, lo resuelve otro caption
		if (is_array($result)) {
			$result = $this->_resolveCaption($result, $params);
		}

		// Retorna el resultado del chain
		return $result;
	}

	/**
	 * Procesa la definición de un caption.
	 *
	 * Si el caption posee expresiones y variables lo transforma en una estructura particular de datos array legible al momento de solicitar un caption
	 *
	 * @param string $caption Definición en formato string de un caption
	 *
	 * @return string|array Estructura de datos el caption
	 */
	private function _parseCaption($caption) {

		// Array de elementos a reemplazar
		$replace = [];

		// Contador de elementos a reemplazar
		$replIndex = 0;

		// Se identifican expresiones de tipo "{ $var, a|b|c }"
		preg_match_all('/\{[\ ]*\$(\w+[\-\w+]*)[\ ]*\,([^\}]*)\}/', $caption, $matches, PREG_OFFSET_CAPTURE);

		// Verifica si se encontraron expresiones
		if (count($matches[0]) > 0) {

			// Recorre todas las expresiones encontradas de forma inversa
			for ($i	= count($matches[0]) - 1; $i >= 0; $i--) {

				// Posición en el string donde está ubicada la expresión
				$pos1 = $matches[0][$i][1];
				$pos2 = $pos1 + strlen($matches[0][$i][0]);

				// Obtiene el nombre de variable definido en la expresión
				$varName = $matches[1][$i][0];

				// Separa la definición de condiciones especificadas en la expresión
				$conditions = explode('|', trim($matches[2][$i][0]));

				// Remueve los espacios en blanco al inicio y final de cada argumento y procesa cada condición como un caption
				ArrayTools::walk($conditions, function($key, $value) {
					return $this->_parseCaption(trim($value));
				});

				// Remueve la expresión del caption y coloca un comodín en su lugar
				$caption = substr($caption, 0, $pos1) . '{' . $replIndex . '}' . substr($caption, $pos2);

				// Define el elemento a reemplazar
				$replace[$replIndex] = [ 'type' => 'amount', 'var' => $varName, 'conditions' => $conditions ];

				// Aumenta una unidad al contador de elementos a reemplazar
				$replIndex++;
			}
		}

		// Se identifican variables de tipo "$var"
		preg_match_all('/\$(\w+[\-\w+]*)/', $caption, $matches, PREG_OFFSET_CAPTURE);

		// Verifica si se encontraron variables
		if (count($matches[0]) > 0) {

			// Recorre todas las variables encontradas de forma inversa
			for ($i	= count($matches[1]) - 1; $i >= 0; $i--) {

				// Posición en el string donde está ubicada la variable
				$pos1 = $matches[1][$i][1] - 1;
				$pos2 = $pos1 + strlen($matches[0][$i][0]);

				// Obtiene el nombre de variable definido en la expresión
				$varName = $matches[1][$i][0];

				// Remueve el nombre de la variable en el caption y coloca un comodín en su lugar
				$caption = substr($caption, 0, $pos1) . '{' . $replIndex . '}' . substr($caption, $pos2);

				// Define el elemento a reemplazar
				$replace[$replIndex] = [ 'type' => 'replace', 'var' => $varName ];

				// Aumenta una unidad al contador de elementos a reemplazar
				$replIndex++;
			}
		}

		// Verifica si se definieron elementos para reemplazar
		if ($replIndex > 0) {

			// Se identifican todos los comodines insertados en el caption y se capturan los índices de los elementos a reemplazar
			preg_match_all('/\{([0-9])\}*/', $caption, $matches, PREG_OFFSET_CAPTURE);

			// Variable temporal para reordenar los elementos a reemplazar
			$_replace = [];

			// Recorre de forma inversa todos los comodines encontrados
			if (count($matches[0]) > 0) {
				for ($i	= count($matches[1]) - 1; $i >= 0; $i--) {

					// Posición en el string donde está ubicado el comodín
					$pos1 = $matches[1][$i][1] - 1;
					$pos2 = $pos1 + strlen($matches[0][$i][0]);

					// Obtiene el índice del comodín
					$varName = $matches[1][$i][0];

					// Remueve el comodín con índice en el caption y coloca un comodín '{?}' en su lugar
					$caption = substr($caption, 0, $pos1) . '{?}' . substr($caption, $pos2);

					// Agrega el elmento a reemplazar
					$_replace[] = $replace[$matches[1][$i][0]];
				}
			}

			// Ordena secuencialmente todos los elementos a reemplazar
			$replace = array_reverse($_replace);

			// Separa los comodines en el caption por un array de strings
			$strings = explode('{?}', $caption);

			// Se fusionan de manera secuencial los segmentos de tipo string con los resultados de las expresiones
			$caption = [];
			for ($i = 0; $i < count($strings); $i++) {
				$caption[] = $strings[$i];
				if ($i < count($strings) - 1) {
					$caption[] = $replace[$i];
				}
			}
		}

		return $caption;
	}

	/**
	 * Carga un paquete de idioma.
	 *
	 * @param string $name Identificador único del paquete (Ruta relativa del archivo)
	 * @param string $lang Define si debe cargar el paquete en un idioma en específico
	 *
	 * @return void
	 *
	 * @throws FwException_Lang
	 */
	private function _loadPackage($name, $lang = null) {

		// Si no se especificó el idioma del paquete a cargar, define el idioma especificado por el cliente
		if ($lang == null) {
			$lang = Conf::getLocale();
		}

		// Define el directorio hasta el paquete
		$path = './lang/' . $name;

		// Verifica si existe el paquete en el idioma solicitado
		if (file_exists($path . '/' . $lang . '.json')) {
			$this->_sourceDir = $path . '/' . $lang . '.json';

		// Verifica si existe el paquete en el idioma solicitado utilizando solo el prefijo del idioma, si está especificado de la forma "en-US"
		} else if (file_exists($path . '/' . explode('-', $lang)[0] . '.json')) {
			$this->_sourceDir = $path . '/' . explode('-', $lang)[0] . '.json';

		// Verifica si existe el paquete en el idioma default del framework
		} else if (file_exists($path . '/' . Conf::getParam('default_locale') . '.json')) {
			$this->_sourceDir = $path . '/' . Conf::getParam('default_locale') . '.json';

		} else {
			throw new FwException_Lang('package-not-found', null, [ 'name' => $name ]);
		}

		// Registra el nuevo modelo de datos para el paquete
		$this->_package = self::$_packages[$name] = new PlainObjectModel('.', false);

		// Carga el paquete de idioma desde el cache
		try {

			$this->_package->setNodes(CacheSystem::load('lang', $this->_sourceDir));

		} catch (FwException_CacheSystem $e) {

			// Carga y decodifica el paquete en formato JSON
			$captions = json_decode(file_get_contents($this->_sourceDir), true);

			// Verifica que el paquete de idioma haya cargado correctamente y sea válido
			if (($captions === null) || (!ArrayTools::isAssociative($captions))) {
				throw new FwException_Lang('invalid-package', null, [ 'name' => $name ]);
			}

			// Agrega todos los captions
			$this->_package->addEndpoints($captions, function($caption) {
				return $this->_parseCaption($caption);
			});

			// Almacena en cache el paquete de idioma
			CacheSystem::save('fw/lang', $this->_sourceDir, $this->_package->getNodes());
		}
	}
}
?>
