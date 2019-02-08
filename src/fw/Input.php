<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use \Exception;

/**
 * Clase para la validación y depuración de datos provenientes del frontend.
 *
 * Esta clase abstrae el uso de $_GET, $_POST, $_COOKIE y $_FILES.
 * Dispone de métodos seguros para la validación y depuración de cualquier dato proveniente del frontend.
 */
class Input {

	/**
	 * Devuelve una variable depurada, validada y recibida por el método HTTP $_POST.
	 *
	 * @param string       $var     Nombre de la variable recibida por $_POST
	 * @param string|array $type    Tipo de variable a recibir o un conjunto de reglas para validar la variable a recibir
	 * @param mixed        $default Valor default cuando la variable no se pudo validar o recibir
	 *
	 * @return mixed Resultado obtenido para la variable
	 */
	public static function post($var, $type = 'string', $default = null) {
		return self::getBy('post', $var, $type, $default);
	}

	/**
	 * Devuelve una variable depurada, validada y recibida por el método HTTP $_GET.
	 *
	 * @param string       $var     Nombre de la variable recibida por $_GET
	 * @param string|array $type    Tipo de variable a recibir o un conjunto de reglas para validar la variable a recibir
	 * @param mixed        $default Valor default cuando la variable no se pudo validar o recibir
	 *
	 * @return mixed Resultado obtenido para la variable
	 */
	public static function get($var, $type = 'string', $default = null) {
		return self::getBy('get', $var, $type, $default);
	}

	/**
	 * Devuelve una variable depurada, validada y recibida por el método HTTP $_COOKIE.
	 *
	 * @param string       $var     Nombre de la variable recibida por $_COOKIE
	 * @param string|array $type    Tipo de variable a recibir o un conjunto de reglas para validar la variable a recibir
	 * @param mixed        $default Valor default cuando la variable no se pudo validar o recibir
	 *
	 * @return mixed Resultado obtenido para la variable
	 */
	public static function cookie($var, $type = 'string', $default = null) {
		return self::getBy('cookie', $var, $type, $default);
	}

	/**
	 * Devuelve una variable validada y recibida por el método HTTP $_FILES.
	 *
	 * @param string     $var      Nombre de la variable recibida por $_FILES
	 * @param array|null $rules    Conjunto de reglas para validar la variable a recibir
	 * @param string|null $default Valor default cuando la variable no se pudo validar o recibir
	 *
	 * @return mixed Resultado obtenido para la variable
	 */
	public static function file($var, $rules = null) {

		// Si se definieron las reglas
		if (is_array($rules)) {

			// Define el metodo y el valor por default
			$rules['method'] = 'file';
			$rules['default'] = null;

		} else {

			// Define las reglas
			$rules = array(
				'method' 	=> 'file',
				'default' 	=> null
			);
		}

		// Se valida el dato
		$data = self::validate('file', array(
			$var => $rules
		));

		// Devuelve el dato obtenido o el default
		if ($data == false) {
			return null;
		} else {
			return $data->$var;
		}
	}

	/**
	 * Devuelve un dato validado y recibido por diversos métodos HTTP.
	 *
	 * @param string       $methods Nombre de uno o varios métodos HTTP por el cual se buscará la variable. Ej: 'post|get'
	 * @param string       $var     Nombre de la variable a obtener
	 * @param string|array $rules   Tipo de variable a obtener o un conjunto de reglas para validar la variable
	 * @param mixed        $default Valor default cuando la variable no se pudo validar o recibir
	 *
	 * @return mixed Resultado obtenido para la variable
	 */
	public static function getBy($methods, $var, $rules = 'string', $default = null) {

		// El parametro $rules puede cumplir dos funciones diferentes, 1: Definir un tipo de dato, 2: ser un array con un set de reglas
		if (is_array($rules)) {

			// Agrega la regla método y el valor default
			$rules['method'] = $methods;

		} else {

			// Define las reglas
			$rules = array(
				'method' 	=> $methods,
				'type'  	=> $rules,
				'default' 	=> $default
			);
		}

		// Se valida el dato
		$data = self::validate($methods, array(
			$var => $rules
		));

		// Devuelve el dato obtenido o el default
		if ($data == false) {
			return $default;
		} else {
			return $data->$var;
		}
	}

	/**
	 * Valida y depura un set de variables recibidas por diversos métodos.
	 *
	 * @param string      $defMethods Método(s) HTTP default por el cual se buscarán las variables que no tienen definido el método HTTP
	 * @param array       $dataSet    Conjunto de variables a recibir y sus respectivas reglas
	 * @param object|null $callback   Object que contiene las funciones callback 'success' o 'error'
	 *
	 * @return array|false Datos obtenidos para cada variable o false cuando una de las variables falló en la validación
	 */
	public static function validate($defMethod, $dataSet, $callback = null) {

		// Listado de errores
		$error = new ErrorList(array(
			'var-post-no-exists'     => 'Variable "$var": Can not obtain by method HTTP POST',
			'var-get-no-exists'      => 'Variable "$var": Can not obtain by method HTTP GET',
			'var-cookie-no-exists'   => 'Variable "$var": Can not obtain by method HTTP COOKIE',
			'var-file-no-exists'     => 'Variable "$var": Can not obtain by method HTTP FILES',
			'var-no-exists'          => 'Variable "$var": Can not obtain by any method HTTP',
			'no-value'               => 'Variable "$var": Variable has not any value',
			'invalid-post-req'       => 'Variable "$var": It requires that method HTTP must to be POST',
			'invalid-multiple'       => 'Variable "$var": Can not allow multiple values',
			'validation-fail'        => 'Variable "$var": Validation by the type specified failed',
			'custom-validation-fail' => 'Variable "$var": Custom validation callback failed',
			'null'                   => 'Variable "$var": Can not admit null values',
			'regex'                  => 'Variable "$var": Regex specified has failed',
			'empty'                  => 'Variable "$var": Can not admit empty values',
			'length'                 => 'Variable "$var": Value length is different than the specified',
			'min-length'             => 'Variable "$var": Value is less than the "min-length" specified',
			'max-length'             => 'Variable "$var": Value is greater than the "max-length" specified',
			'min-range'              => 'Variable "$var": Value is less than the "min-range" specified',
			'max-range'              => 'Variable "$var": Value is greater than the "max-range" specified',
			'min-datetime'           => 'Variable "$var": Value is less than the "min-datetime" specified',
			'max-datetime'           => 'Variable "$var": Value is greater than the "max-datetime" specified',
			'case'                   => 'Variable "$var": Value is not admitted by case rule',
			'upload-error'           => 'Variable "$var": Error ($code) was produced during upload process',
			'invalid-upload'         => 'Variable "$var": File not exists or was not uploading by HTTP POST',
			'fw-invalid-extension'   => 'Variable "$var": File extension must tu be defined by framework param "valid_extensions"',
			'fw-max-file-size'       => 'Variable "$var": File size is greater than the allowed by framework param "input_max_file_size"',
			'invalid-extension'      => 'Variable "$var": Extension is not valid',
			'min-size'               => 'Variable "$var": File size must to be greater than or equal to ($mb) Mb',
			'max-size'               => 'Variable "$var": File size must to be less than or equal to ($mb) Mb',
			'invalid-image'          => 'Variable "$var": Image format is not valid',
			'damaged-image'          => 'Variable "$var": Image could be damaged',
			'res'                    => 'Variable "$var": Image resolution must to be "$w" px width and "$h" px height',
			'min-res'                => 'Variable "$var": Minimum image resolution muest to be "$w" width and "$h" px height',
			'max-res'                => 'Variable "$var": Maximum image resolution muest to be "$w" width and "$h" px height',
			'min-mp'                 => 'Variable "$var": Minimum image resolution in megapixels must to be "$mp" mp',
			'mp'                     => 'Variable "$var": Image resolution in megapixels must to be "$mp" mp',
			'max-mp'                 => 'Variable "$var": Maximum image resolution in megapixels must to be "$mp" mp',
			'ratio'                  => 'Variable "$var": Aspect ratio must to be $ratio',
			'min-ratio'              => 'Variable "$var": Minimum aspect ratio must to be $ratio',
			'max-ratio'              => 'Variable "$var": Maximum aspect ratio must to be $ratio'
		));

		// Obtiene y procesa cada uno de las variables del set
		foreach ($dataSet as $var => $data) {

			try {

				// Define el nombre de la variable misma dentro de sus datos
				$data['name'] = $var;

				// Si no se definió el método y no se asignó un valor inicial, se utiliza el método default especificado
				if ((!isset($data['method'])) && (!array_key_exists('value', $data))) {
					$data['method'] = strtolower($defMethod);
				} else {
					$data['method'] = strtolower($data['method']);
				}

				// Obtiene el valor si se definió un método
				if (isset($data['method'])) {

					// Determina si se especificaron varios métodos
					$methods = explode('|', $data['method']);
					if (count($methods) > 1) {
						$data['method'] = null;
						foreach ($methods as $method) {
							if (($method == 'get') && (array_key_exists($var, $_GET))) {
								$data['method'] = 'get';
								break;

							} else if (($method == 'post') && (array_key_exists($var, $_POST))) {
								$data['method'] = 'post';
								break;

							} else if (($method == 'cookie') && (array_key_exists($var, $_COOKIE))) {
								$data['method'] = 'cookie';
								break;

							} else if (($method == 'file') && (array_key_exists($var, $_FILES))) {
								$data['method'] = 'file';
								break;
							}
						}
					}

					// Obtiene el valor por el método especificado
					switch ($data['method']) {
						case 'post':
							if (array_key_exists($var, $_POST)) {
								$data['value'] = $_POST[$var];
							} else {
								throw new FwException_Input('var-post-no-exists', $error, [ 'var' => $data['name'] ]);
							}
							break;

						case 'get':
							if (array_key_exists($var, $_GET)) {
								$data['value'] = $_GET[$var];
							} else {
								throw new FwException_Input('var-get-no-exists', $error, [ 'var' => $data['name'] ]);
							}
							break;

						case 'cookie':
							if (array_key_exists($var, $_COOKIE)) {
								$data['value'] = $_COOKIE[$var];
							} else {
								throw new FwException_Input('var-cookie-no-exists', $error, [ 'var' => $data['name'] ]);
							}
							break;

						case 'file':
							if (array_key_exists($var, $_FILES)) {
								$data['value'] = $_FILES[$var];
							} else {
								throw new FwException_Input('var-file-no-exists', $error, [ 'var' => $data['name'] ]);
							}
							break;

						default:
							throw new FwException_Input('var-no-exists', $error, [ 'var' => $data['name'] ]);
							break;
					}
				} else {
					$data['method'] = null;
				}

				// Error si no se obtuvo ningun valor
				if (!array_key_exists('value', $data)) {
					throw new FwException_Input('no-value', $error, [ 'var' => $data['name'] ]);
				}

				// Si el método es FILE, verifica que el método request sea por POST
				if ($data['method'] == 'file') {
					if (Http::getRequest()->method != 'post'){
						throw new FwException_Input('invalid-post-req', $error, [ 'var' => $data['name'] ]);
					}
				}

				/*
					Verifica si la variable recibió múltiples valores
					Si el valor del dato es un array de valores y el método no es FILE, o
					Si el método es FILE y el valor dispone de la información de múltiples variables
				*/
				if (((is_array($data['value']) && ($data['method'] != 'file'))) || (($data['method'] == 'file') && isset($data['value']['name']) && (is_array($data['value']['name'])))) {

					// Verifica que el dato admita múltiples valores
					if ((!isset($data['multiple'])) || ($data['multiple'] !== true)) {
						throw new FwException_Input('invalid-multiple', $error, [ 'var' => $data['name'] ]);
					}

					// Si el método es FILE, se modifica el array proveniente desde $_FILES a una estructura de datos ordenada por indice y no por atributos
					if ($data['method'] == 'file') {
						$dataFile = array();
						foreach ($data['value']['name'] as $i => $value) {
							$dataFile[$i] = array(
								'name' 		=> $data['value']['name'][$i],
								'type' 		=> $data['value']['type'][$i],
								'size' 		=> $data['value']['size'][$i],
								'tmp_name'	=> $data['value']['tmp_name'][$i],
								'error'		=> $data['value']['error'][$i]
							);
						}
						$data['value'] = $dataFile;
					}

					// Procesa un dato múltiple
					$data['value'] = self::_processMultiple($data['value'], $data, $error);

				} else {

					// Procesa el valor del dato
					$data = self::_process($data, $error);
				}

				// Guarda en el set de datos el valor del dato ya validado y depurado
				$dataSet[$var] = $data['value'];

			} catch (FwException_Input $e) {

				// Si hay un valor por default se utiliza y se remueve el error
				if (array_key_exists('default', $data)) {
					$error->release();
					$dataSet[$var] = $data['default'];

				} else {

					// Llama el callback de error
					if ((is_array($callback)) && (isset($callback['error'])) && (is_callable($callback['error']))) {
						call_user_func($callback['error'], $error->getResume(), $data);
					}

					// Finaliza la validación
					return false;
				}
			}
		}

		// Convierte el dataSet en un objeto
		$dataSet = (object)$dataSet;

		// Llama el callback de éxito
		if ((is_array($callback)) && (isset($callback['success'])) && (is_callable($callback['success']))) {
			$callback['success']($dataSet);
		} else if ((isset($callback)) && (is_callable($callback))) {
			$callback($dataSet);
		}

		// Devuelve el set de datos
		return $dataSet;
	}

	/**
	 * Depura el valor de un dato según su tipo.
	 *
	 * Este método es utilizado exclusivamente por el método Input::_process()
	 *
	 * @param string $type   Tipo de dato a depurar
	 * @param mixed  $value  Valor de dato a depurar
	 * @param array  $params Reglas del dato que se usarán como parámetros para depurarlo
	 *
	 * @return mixed Valor depurado del dato
	 */
	public static function depure($type, $value, $params = array()) {

		// Si el valor es null se devuelve null
		if ($value === null) {
			return null;
		}

		// Evalua el tipo de dato y lo devuelve depurado
		switch ($type) {
			case 'raw':

				// Verifica si se deben filtrar etiquetas
				if (isset($params['allow-tags'])) {

					// Si se espeficaron las etiquetas en un array, las define en un solo string
					if (is_array($params['allow-tags'])) {
						$allowTags = '<' . implode('><', $params['allow-tags']) . '>';
					} else {
						$allowTags = $params['allow-tags'];
					}

					// Depura el string considerando las etiquetas permitidas
					return strip_tags($value, $allowTags);

				} else {
					return $value;
				}

			case 'string':
				return filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

			case 'int':
			case 'integer':
				return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);

			case 'float':
				return (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, array('flags' => FILTER_FLAG_ALLOW_FRACTION, FILTER_FLAG_ALLOW_THOUSAND, FILTER_FLAG_ALLOW_SCIENTIFIC));

			case 'double':
				return self::double($value);
				return (double)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, array('flags' => FILTER_FLAG_ALLOW_FRACTION, FILTER_FLAG_ALLOW_THOUSAND, FILTER_FLAG_ALLOW_SCIENTIFIC));

			case 'bool':
			case 'boolean':
				return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

			case 'datetime':

				// Construye una instancia FwDateTime para validar la integridad de la fecha
				try {

					// Verifica si se especificó un formato y zona horaria para la construcción de la fecha
					$params = array();
					if (isset($params['dateformat'])) {
						$params['format'] = $params['dateformat'];
					} else {
						$params['format'] = Conf::getParam('input_dateformat');
					}
					if (isset($params['timezone'])) {
						$params['timezone'] = $params['timezone'];
					} else {
						$params['timezone'] = Conf::getTimezone();
					}
					return new FwDateTime($value, $params);

				} catch (Exception $e) {
					return null;
				}
				break;

			case 'email':
				return filter_var($value, FILTER_SANITIZE_EMAIL);

			case 'url':
				return filter_var($value, FILTER_SANITIZE_URL, FILTER_FLAG_HOST_REQUIRED);

			case 'urlencode':
				return filter_var($value, FILTER_SANITIZE_ENCODED);

			default:
				return filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		}
	}

	/**
	 * Procesa el valor de un dato múltiple.
	 *
	 * Este método es utilizado exclusivamente por el método Input::validate()
	 *
	 * @param mixed     $data  Valor a depurar
	 * @param array     $data  Dato con sus respectivas reglas y valor
	 * @param ErrorList $error Variable de error pasada por referencia
	 *
	 * @return mixed Valor validado, depurado y transformado
	 *
	 * @uses self::_process()
	 */
	private static function _processMultiple($value, $data, &$error) {

		// Verifica si el valor a depurar es múltiple
		if (is_array($value)) {

			// Itera y procesa todos los valores
			foreach ($value as $i => $val) {
				$value[$i] = self::_processMultiple($val, $data, $error);
			}

			// Retorna el valor procesado
			return $value;

		} else {

			// Almacena el dato en una variable temporal
			$dataTmp = $data;
			$dataTmp['value'] = $value;

			// Intenta procesar el dato
			try {

				$dataTmp = self::_process($dataTmp, $error);

			} catch (FwException_Input $e) {

				// Verifica si hay un valor por default para reemplazar
				if (array_key_exists('default*', $dataTmp)) {
					$error->release();
					$dataTmp['value'] = $dataTmp['default*'];
				} else {
					$dataTmp['value'] = null;
				}
			}

			// Devuelve el valor procesado
			return $dataTmp['value'];
		}
	}

	/**
	 * Procesa un dato para su validación, depuracion, transformacion y condición.
	 *
	 * Este método es utilizado exclusivamente por el método Input::validate()
	 *
	 * @param array     $data  Dato con sus respectivas reglas y valor
	 * @param ErrorList $error Variable de error pasada por referencia
	 *
	 * @return array Un dato con sus reglas respectivas y su valor validado, depurado y transformado
	 *
	 * @uses self::_validateData()
	 * @uses self::depure()
	 * @uses self::_checkRules()
	 */
	private static function _process($data, &$error) {

		// Si el dato proviene de un método distinto a FILE se hacen las siguientes verificaciones
		if ($data['method'] == 'file') {

			// Si produjo un error 4 al subir el archivo es porque no se ha subido ningún archivo
			if ($data['value']['error'] == 4) {
				$data['value'] = null;
			}

		} else {

			// Define el tipo 'raw' si no había sido definido ningún tipo
			if (!array_key_exists('type', $data)) {
				$data['type'] = 'raw';
			}

			// (base64) Decodifica el valor recibido en base64
			if ((isset($data['base64'])) && ($data['base64'] == true)) {
				$data['value'] = base64_decode($data['value']);
			}

			// (urlencode) Decodifica el valor codificado en urlencode
			if ((isset($data['urlencode'])) && ($data['urlencode'] == true)) {
				$data['value'] = urldecode($data['value']);
			}

			// (trim) Remueve los espacios en blanco al inicio y al final
			if ((!isset($data['trim'])) || ($data['trim'] == true)) {
				$data['value'] = trim($data['value']);
			}

			// (multiline) Codifica o remueve los saltos de línea
			if (($data['type'] == 'raw') || ($data['type'] == 'string')) {
				if ((isset($data['multiline'])) && ($data['multiline'] == true)) {
					$data['value'] = preg_replace('/\n{3,}/', "\n\n", $data['value']);
					$data['value'] = preg_replace('/(\r\n){3,}/', "\r\n\r\n", $data['value']);
				} else {
					$data['value'] = preg_replace('!\s+!', ' ', $data['value']);
				}
			}

			// (whitespace) Remueve los espacios en blanco o tabulaciones consecutivamente repetidos
			if ((!isset($data['whitespace'])) || ($data['whitespace'] == false)) {
				$data['value'] = preg_replace('/\ \ +/', ' ', $data['value']);
				$data['value'] = preg_replace('/\t\t+/', ' ', $data['value']);
			}

			// Si el dato recibido es una cadena vacía y el parametro para admitir 'empty' no es true o no existe, el valor se convierte en nulo
			if ($data['value'] === '') {
				if ((!isset($data['empty'])) || ($data['empty'] !== true)) {
					$data['value'] = null;
				}
			}

			// Valida el dato de acuerdo a su tipo
			if ($data['value'] !== null) {
				if (self::_validateData($data) === false) {
					throw new FwException_Input('validation-fail', $error, [ 'var' => $data['name'] ]);
				}
			}

			// Depuración personalizada
			if ($data['value'] !== null) {
				if ((isset($data['debug'])) && (is_callable($data['debug']))) {
					$data['value'] = $data['debug']($data['value']);
				}
			}

			// Se depura el dato
			$data['value'] = self::depure($data['type'], $data['value'], $data);
		}

		// Se verifican las reglas especificadas para el dato
		$data = self::_checkRules($data, $error);

		// Validación personalizada
		if ((isset($data['validate'])) && (is_callable($data['validate']))) {
			if ($data['validate']($data['value']) !== true) {
				throw new FwException_Input('custom-validation-fail', $error, [ 'var' => $data['name'] ]);
			}
		}

		return $data;
	}

	/**
	 * Valida un dato de acuerdo a su tipo.
	 *
	 * Este método es utilizado exclusivamente por el método Input::_process()
	 *
	 * @param array $data Dato con sus respectivas reglas y valor
	 *
	 * @return bool Confirmación de validación del dato
	 */
	private static function _validateData($data) {
		$filter = null;
		$flag = null;

		// Si el valor es null la validación es false
		if ($data['value'] === null) {
			return false;
		}

		// Determina los filtros, banderas y expresiones regulares para validar el dato
		switch ($data['type']) {
			case 'raw':
				return true;
				break;

			case 'string':
				$filter = FILTER_VALIDATE_REGEXP;
				$regexp = '/^[\d\D]{0,}\z/i'; // Admite todo tipo de caracteres
				break;

			case 'int':
			case 'integer':
				$filter = FILTER_VALIDATE_INT;
				break;

			case 'float':
			case 'double':
				$filter = FILTER_VALIDATE_FLOAT;
				$flag = FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND | FILTER_FLAG_ALLOW_SCIENTIFIC;
				break;

				case 'bool':
				case 'boolean':
				$filter = FILTER_VALIDATE_BOOLEAN;
				$flag = FILTER_NULL_ON_FAILURE;
				break;

			case 'datetime':

				// Construye una instancia FwDateTime para validar la integridad de la fecha
				try {

					// Verifica si se especificó un formato y zona horaria para la construcción de la fecha
					$params = array();
					if (isset($data['dateformat'])) {
						$params['format'] = $data['dateformat'];
					} else {
						$params['format'] = Conf::getParam('input_dateformat');
					}
					if (isset($data['timezone'])) {
						$params['timezone'] = $data['timezone'];
					} else {
						$params['timezone'] = Conf::getTimezone();
					}
					$dt = new FwDateTime($data['value'], $params);
					return true;

				} catch (Exception $e) {
					return false;
				}
				break;

			case 'email':
				if (strlen($data['value']) > 254) {
					return false;
				}
				$filter = FILTER_VALIDATE_EMAIL;
				break;

			case 'url':
				$filter = FILTER_VALIDATE_URL;
				break;

			case 'ip':
				$filter = FILTER_VALIDATE_IP;
				break;

			case 'hexa':
				$filter = FILTER_VALIDATE_REGEXP;
				$regexp = '/^((0x)|(#)|())(([a-fA-F0-9]{3}$)|([a-fA-F0-9]{6}$))/';
				break;

			default:
				return false;
		}

		// Aplica los filtros, banderas y expresiones regulares para determinar la validacion del valor
		switch ($filter) {

			// Si no se obtuvo ningun filtro se genera un error
			case null:
				return false;
				break;

			// Si el filtro es un booleano
			case FILTER_VALIDATE_BOOLEAN:
				if (filter_var($data['value'], $filter, $flag) !== null) {
					return true;
				} else {
					return false;
				}
				break;

			// Si el filtro es una expresión regular
			case FILTER_VALIDATE_REGEXP:
				if (filter_var($data['value'], FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $regexp))) !== false) {
					return true;
				} else {
					return false;
				}
				break;

			// Opcion por default
			default:
				if (filter_var($data['value'], $filter, $flag) !== false) {
					return true;
				} else {
					return false;
				}
				break;
		}
	}

	/**
	 * Verifica las reglas luego de que el dato haya sido validado y depurado.
	 *
	 * Este método es utilizado exclusivamente por el método Input::_process()
	 *
	 * @param array     $data  Dato con sus respectivas reglas y valor
	 * @param ErrorList $error Variable de error pasada por referencia
	 *
	 * @return array El dato con sus reglas respectivas y su valor transformado
	 */
	private static function _checkRules($data, &$error) {

		// null: Si el valor es nulo y el dato no admite nulos
		if ($data['value'] === null) {
			if ((!isset($data['null'])) || ($data['null'] !== true)) {
				throw new FwException_Input('null', $error, [ 'var' => $data['name'] ]);
			} else {
				return;
			}
		}

		// Transformaciones del dato
		if ($data['method'] != 'file') {

			// (round) Redondea el valor
			if ((isset($data['round'])) && (($data['round'] === true) || ($data['round'] >= 0))) {
				if ((gettype($data['value']) == 'float') || (gettype($data['value']) == 'double')) {
					if ($data['round'] === true) {
						$data['value'] = round($data['value']);
					} else {
						$data['value'] = round($data['value'], $data['round']);
					}
				}
			}

			// (ceil) Redondea hacia arriba el valor
			if ((isset($data['ceil'])) && ($data['ceil'] == true)) {
				if ((gettype($data['value']) == 'float') || (gettype($data['value']) == 'double')) {
					$data['value'] = ceil($data['value']);
				}
			}

			// (floor) Redondea hacia abajo el valor
			if ((isset($data['floor'])) && ($data['floor'] == true)) {
				if ((gettype($data['value']) == 'float') || (gettype($data['value']) == 'double')) {
					$data['value'] = floor($data['value']);
				}
			}

			// (lowercase) Convierte un string a minusculas
			if ((isset($data['lowercase'])) && ($data['lowercase'] == true)) {
				if (gettype($data['value']) == 'string') {
					$data['value'] = strtolower($data['value'], 'UTF-8');
				}
			}

			// (uppercase) Convierte un string a mayusculas
			if ((isset($data['uppercase'])) && ($data['uppercase'] == true)) {
				if (gettype($data['value']) == 'string') {
					$data['value'] = mb_strtoupper($data['value'], 'UTF-8');
				}
			}
		}

		// Verifica condiciones si el dato no proviene del método HTTP $_FILES
		if ($data['method'] != 'file') {

			// (regex) Evalua una expresion regular
			if (gettype($data['value']) == 'string') {
				if (isset($data['regex'])) {
					if (filter_var($data['value'], FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $data['regex']))) === false) {
						throw new FwException_Input('regex', $error, [ 'var' => $data['name'] ]);
					}
				}
			}

			// (empty) Si no se permiten cadenas de texto vacias
			if ((gettype($data['value']) == 'string') && (empty($data['value']))) {
				if ((!isset($data['empty'])) || ($data['empty'] != true)) {
					throw new FwException_Input('empty', $error, [ 'var' => $data['name'] ]);
				}
			}

			// (length) Largo exacto para un dato string
			if (isset($data['length'])) {
				if ((gettype($data['value']) == 'string') && (mb_strlen($data['value'], 'UTF-8') != $data['length'])) {
					throw new FwException_Input('length', $error, [ 'var' => $data['name'] ]);
				}
			}

			// (min-length) Largo minimo para un dato string
			if (isset($data['min-length'])) {
				if ((gettype($data['value']) == 'string') && (mb_strlen($data['value'], 'UTF-8') < $data['min-length'])) {
					throw new FwException_Input('min-length', $error, [ 'var' => $data['name'] ]);
				}
			}

			// (max-length) Largo máximo para un dato string
			if (isset($data['max-length'])) {
				if ((gettype($data['value']) == 'string') && (mb_strlen($data['value'], 'UTF-8') > $data['max-length'])) {
					throw new FwException_Input('max-length', $error, [ 'var' => $data['name'] ]);
				}
			}

			// (min-range) Rango mínimo para un dato numerico o float
			if (isset($data['min-range'])) {
				if (((gettype($data['value']) == 'int') || (gettype($data['value']) == 'integer') || (gettype($data['value']) == 'float') || (gettype($data['value']) == 'double')) && ($data['value'] < $data['min-range'])) {
					throw new FwException_Input('min-range', $error, [ 'var' => $data['name'] ]);
				}
			}

			// (max-range) Rango máximo para un dato numerico o float
			if (isset($data['max-range'])) {
				if (((gettype($data['value']) == 'int') || (gettype($data['value']) == 'integer') || (gettype($data['value']) == 'float') || (gettype($data['value']) == 'double')) && ($data['value'] > $data['max-range'])) {
					throw new FwException_Input('max-range', $error, [ 'var' => $data['name'] ]);
				}
			}

			// (min-datetime) Rango mínimo para una fecha
			if ((isset($data['min-datetime'])) && ($data['type'] == 'datetime')) {

				// Si es string, construye la instancia FwDateTime para la fecha mínima
				if (is_string($data['min-datetime'])) {
					$data['min-datetime'] = new FwDateTime($data['min-datetime'], [ 'timezone' => $data['value']->getTimezone() ]);
				}

				// Compara las fechas
				if ($data['value'] < $data['min-datetime']) {
					throw new FwException_Input('min-datetime', $error, [ 'var' => $data['name'] ]);
				}
			}

			// (max-datetime) Rango máximo para una fecha
			if ((isset($data['max-datetime'])) && ($data['type'] == 'datetime')) {

				// Si es string, construye la instancia FwDateTime para la fecha máxima
				if (is_string($data['max-datetime'])) {
					$data['max-datetime'] = new FwDateTime($data['max-datetime'], [ 'timezone' => $data['value']->getTimezone() ]);
				}

				// Compara las fechas
				if ($data['value'] > $data['max-datetime']) {
					throw new FwException_Input('max-datetime', $error, [ 'var' => $data['name'] ]);
				}
			}

			// (case) Condición case de valores
			if ((isset($data['case'])) && (is_array($data['case']))) {
				if (!in_array($data['value'], $data['case'])) {
					throw new FwException_Input('case', $error, [ 'var' => $data['name'] ]);
				}
			}
		}

		// Verifica condiciones si el dato proviene del método HTTP $_FILES
		if (($data['method'] == 'file') && ($data['value'] !== null)) {

			// Si hay algun error en el proceso de upload
			if ($data['value']['error'] !== UPLOAD_ERR_OK) {
				throw new FwException_Input('upload-error', $error, [ 'var' => $data['name'], 'code' => $data['value']['error'] ]);
			}

			// Confirma la existencia del archivo y que haya sido subido mediante HTTP POST
			if ((!file_exists($data['value']['tmp_name'])) || (!is_readable($data['value']['tmp_name'])) || (!is_uploaded_file($data['value']['tmp_name']))) {
				throw new FwException_Input('invalid-upload', $error, [ 'var' => $data['name'] ]);
			}

			// Obtiene la extensión
			$ext = strtolower(pathinfo($data['value']['name'], PATHINFO_EXTENSION));

			/*
				Verificación de restricciones del framework
				---
			*/

			// Verifica que la extensión esté en la lista blanca del framework
			$validExt = array_map('strtolower', Conf::getParam('valid_extensions'));
			if (!in_array($ext, $validExt)) {
				throw new FwException_Input('fw-invalid-extension', $error, [ 'var' => $data['name'] ]);
			}

			// Verifica que el tamaño del archivo no sea mayor que el permitido
			$maxSize = Conf::getParam('input_max_file_size') * 1024 * 1024;
			$upload_max_filesize = ((int)ini_get('upload_max_filesize')) * 1024 * 1024;
			$post_max_size = ((int)ini_get('post_max_size')) * 1024 * 1024;
			if (($data['value']['size'] > $maxSize) || ($data['value']['size'] > $upload_max_filesize) || ($data['value']['size'] > $post_max_size)) {
				throw new FwException_Input('fw-max-file-size', $error, [ 'var' => $data['name'] ]);
			}

			/*
				---
			*/

			// (ext) Verifica la extensión
			if (isset($data['ext'])) {
				if (is_string($data['ext'])) {
					if ($ext !== $data['ext']) {
						throw new FwException_Input('invalid-extension', $error, [ 'var' => $data['name'] ]);
					}
				} else {
					if (!in_array($ext, $data['ext'])) {
						throw new FwException_Input('invalid-extension', $error, [ 'var' => $data['name'] ]);
					}
				}
			}

			// (min-size) Tamaño mínimo para un archivo
			if (isset($data['min-size'])) {
				$bytes = $data['min-size'] * 1024 * 1024;
				if (($data['value']['size'] < $bytes) || (($bytes == 0) && ($data['value']['size'] == 0))) {
					throw new FwException_Input('min-size', $error, [ 'var' => $data['name'], 'mb' => $data['min-size'] ]);
				}
			}

			// (max-size) Tamaño máximo para un archivo
			if (isset($data['max-size'])) {
				$bytes = $data['max-size'] * 1024 * 1024;
				if ($data['value']['size'] > $bytes) {
					throw new FwException_Input('max-size', $error, [ 'var' => $data['name'], 'mb' => $data['max-size'] ]);
				}
			}

			// (image) Valida que el archivo sea de tipo imagen (jpg, jpeg, png, gif)
			if ((isset($data['image'])) && (($data['image'] === true) || (is_array($data['image']))))  {

				// Verifica la extensión del archivo
				if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
					throw new FwException_Input('invalid-image', $error, [ 'var' => $data['name'] ]);
				}

				// Carga la imagen
				$metaImg = getimagesize($data['value']['tmp_name']);
				if (($metaImg == false) || (!is_array($metaImg)) || ($metaImg[0] == 0) || ($metaImg[1] == 0)) {
					throw new FwException_Input('damaged-image', $error, [ 'var' => $data['name'] ]);
				}

				// Verifica el tipo de imagen
				if (!in_array($metaImg[2], [ IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF ])) {
					throw new FwException_Input('invalid-image', $error, [ 'var' => $data['name'] ]);
				}

				// Verifica si se establecieron algunas condiciones para la imagen
				if (is_array($data['image'])) {

					// (res) Resolución para la imagen
					if (isset($data['image']['res'])) {
						if (($metaImg[0] != $data['image']['res'][0]) || ($metaImg[1] != $data['image']['res'][1])) {
							throw new FwException_Input('res', $error, [
								'var' => $data['name'],
								'w'   => $data['image']['res'][0],
								'h'   => $data['image']['res'][1]
							]);
						}
					}

					// (min-res) Resolución mínima para la imagen
					if (isset($data['image']['min-res'])) {
						if (($metaImg[0] < $data['image']['min-res'][0]) || ($metaImg[1] < $data['image']['min-res'][1])) {
							throw new FwException_Input('min-res', $error, [
								'var' => $data['name'],
								'w'   => $data['image']['min-res'][0],
								'h'   => $data['image']['min-res'][1]
							]);
						}
					}

					// (max-res) Resolución máxima para la imagen
					if (isset($data['image']['max-res'])) {
						if (($metaImg[0] > $data['image']['max-res'][0]) || ($metaImg[1] > $data['image']['max-res'][1])) {
							throw new FwException_Input('max-res', $error, [
								'var' => $data['name'],
								'w'   => $data['image']['max-res'][0],
								'h'   => $data['image']['max-res'][1]
							]);
						}
					}

					// (mp) Resolución en megapixeles
					$mp = $metaImg[0]*$metaImg[1]/1000000;
					if (isset($data['image']['mp'])) {
						if ($mp != $data['image']['mp']) {
							throw new FwException_Input('mp', $error, [ 'var' => $data['name'], 'mp' => $data['image']['mp'] ]);
						}
					}

					// (min-mp) Resolución mínima en megapixeles
					if (isset($data['image']['min-mp'])) {
						if ($mp < $data['image']['min-mp']) {
							throw new FwException_Input('min-mp', $error, [ 'var' => $data['name'], 'mp' => $data['image']['min-mp'] ]);
						}
					}

					// (max-mp) Resolución máxima en megapixeles
					if (isset($data['image']['max-mp'])) {
						if ($mp > $data['image']['max-mp']) {
							throw new FwException_Input('max-mp', $error, [ 'var' => $data['name'], 'mp' => $data['image']['max-mp'] ]);
						}
					}

					// (ratio) Relación ratio (ancho / alto)
					$ratio = $metaImg[0]/$metaImg[1];
					if (isset($data['image']['ratio'])) {
						if ($ratio != $data['image']['ratio']) {
							throw new FwException_Input('ratio', $error, [ 'var' => $data['name'], 'ratio' => $data['image']['ratio'] ]);
						}
					}

					// (min-ratio) Relación ratio (ancho / alto) mínima
					$ratio = $metaImg[0]/$metaImg[1];
					if (isset($data['image']['min-ratio'])) {
						if ($ratio < $data['image']['min-ratio']) {
							throw new FwException_Input('min-ratio', $error, [ 'var' => $data['name'], 'ratio' => $data['image']['min-ratio'] ]);
						}
					}

					// (max-ratio) Relación ratio (ancho/alto) máxima
					if (isset($data['image']['max-ratio'])) {
						if ($ratio > $data['image']['max-ratio']) {
							throw new FwException_Input('max-ratio', $error, [ 'var' => $data['name'], 'ratio' => $data['image']['min-ratio'] ]);
						}
					}
				}
			}
		}

		// Devuelve el dato
		return $data;
	}
}
?>
