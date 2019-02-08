<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

/**
 * Inicializa el Framework y ofrece métodos de uso general.
 */
class Fw {

	/**
	 * Inicializa el Framework.
	 *
	 * @return void
	 */
	public static function initialize() {

		// Registra el autoloader de clases del Framework
		spl_autoload_register('self::_autoload');

		// Carga los helpers
		require_once './fw/Helpers.php';

		// Carga las configuraciones iniciales del framework
		Conf::load();

		// Define la zona horaria del servidor
		date_default_timezone_set(Conf::getParam('default_timezone'));

		// Oculta los errores mostrados por php
		ini_set('display_errors', '0');

		// Derine que tipo errores deben ser manejados
		error_reporting(Conf::getParam('error_reporting'));

		// Habilita el historial de errores
		if (Conf::getParam('error_log') == true) {
			ini_set('log_errors', '1');
			ini_set('log_errors_max_len', (string)Conf::getParam('error_log_max_length'));
			ini_set('error_log', './' . Conf::getParam('error_log_path'));
		} else {
			ini_set('log_errors', '0');
		}

		// Define el manejador de errores notificados por PHP
		set_error_handler(function($errno, $errstr, $errfile, $errline) {

			// Envío el error al manejador de errores del Framework
			Fw::throwError(array(
				'code'    => $errno,
				'message' => $errstr,
				'file'    => $errfile,
				'line'    => $errline,
				'catched' => 'set_error_handler',
				'trace'	  => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
			));
		}, Conf::getParam('error_reporting'));

		// Define el manejador de excepciones y errores
		set_exception_handler(function($e) {
			Fw::throwError($e);
		});

		// Callback llamado al finalizar el script, verifica si ocurrió un error fatal
		register_shutdown_function(function() {
			$error = error_get_last();
			if ($error !== NULL) {
				Fw::throwError(array(
					'code'    => $error['type'],
					'message' => $error['message'],
					'file'    => $error['file'],
					'line'    => $error['line'],
					'catched' => 'register_shutdown_function',
					'trace'   => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
				));
			}
		});

		// Inicializa el enrutador
		Router::initialize();

		// Inicializa la clase de sesión
		Session::initialize();

		// Activa el almacenamiento en búfer de la salida
		ob_start();

		// Carga el script inicial de la aplicación
		require_once './conf/main.php';

		// Evento 'start', previo a la validación de autenticación de usuario
		EventsHandler::trigger('fw-start');

		// Inicializa la clase de autenticación de usuario
		Auth::initialize();

		// Establece el idioma para fechas y tiempo
		setlocale(LC_TIME, Conf::getLocale());

		// Evento 'logged', indica el estado y el usuario en sesión
		EventsHandler::trigger('fw-logged', Auth::isLogged(), Auth::getCurrentUser());

		// Solicita al controlador principal que procese el request
		MainController::processRequest();
	}

	/**
	 * Lanza un error faltal (500) del Framework.
	 *
	 * @param string|array|Exception $e Información del error
	 *
	 * @return void
	 */
	public static function throwError($e) {

		// Agrega el request y timestamp al error
		$error = array(
			'request.route'  => Http::getRequest()->url,
			'request.method' => Http::getRequest()->method,
			'timestamp' 	 => time()
		);

		// Si el error es una instancia extendida de Exception
		if (is_a($e, 'Error') || is_subclass_of($e, 'Error')) {
			$error['code']      = $e->getCode();
			$error['message']   = $e->getMessage();
			$error['file']      = $e->getFile();
			$error['line']      = $e->getLine();
			$error['triggered'] = get_class($e);
			$error['catched']   = 'set_exception_handler';
			$error['trace']     = $e->getTraceAsString();

		} else if (is_a($e, 'Exception') || is_subclass_of($e, 'Exception')) {
			$error['code']    = $e->getCode();
			$error['message'] = $e->getMessage();
			$error['file']    = $e->getFile();
			$error['line']    = $e->getLine();
			$error['triggered'] = get_class($e);
			$error['catched'] = 'set_exception_handler';
			$error['trace']   = $e->getTraceAsString();

		// Si el error es un string
		} else if (is_string($e)) {
			$error['message'] = $e;

		// Si el error es un array
		} else if (is_array($e)) {
			$error = array_merge($error, $e);

		} else {
			return;
		}

		// Agrega los valores no asignados
		if (!isset($error['trace'])) {
			$error['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		}
		if (!isset($error['code'])) {
			$error['code'] = '(not specified)';
		}
		if (!isset($error['message'])) {
			$error['message'] = '(not specified)';
		}
		if (!isset($error['file'])) {
			if (isset($error['trace'][0]['file'])) {
				$error['file'] = $error['trace'][0]['file'];
			} else {
				$error['file'] = '(not specified)';
			}
		}
		if (!isset($error['line'])) {
			if (isset($error['trace'][0]['file'])) {
				$error['line'] = $error['trace'][0]['line'];
			} else {
				$error['line'] = '(not specified)';
			}
		}
		if (!isset($error['triggered'])) {
			$error['triggered'] = '(not specified)';
		}
		if (!isset($error['catched'])) {
			$error['catched'] = '(not specified)';
		}

		// En modo depuración, se muestra el error
		if (Conf::getParam('debug') == true) {

			// Reinicia el framework
			Fw::restart();

			// Obtiene el response
			$res = Http::getResponse();

			// Carga la vista de detalles del error
			if (Conf::getParam('error_template_display') != false) {

				// Captura el trace en formato string
				$error['trace'] = var_export($error['trace'], true);

				// Renderiza el template del error y lo agrega al body del response
				try {

					$res->body(Template::render(Conf::getParam('error_template_display'), [ 'error' => $error ]), 'text/html');

				} catch (FwException_Template $e) {
					$res->body(var_export($error, true), 'text/txt');
				}

			// Muestra el error como texto plano
			} else {
				$res->body(var_export($error, true), 'text/txt');
			}
		}

		// Almacena el error
		if (Conf::getParam('error_log') == true) {

			// Se remueve el trace cuando no es requerido
			if (Conf::getParam('error_log_trace') == false) {
				unset($error['trace']);
			}
			error_log(var_export($error, true), 0);
		}

		// Emite el response o emite el error 500
		if (Conf::getParam('debug') == true) {
			$res->emit(500);
		} else {
			Http::throwError(500);
		}
	}

	/**
	 * Reinicia el framework.
	 *
	 * @return void
	 */
	public static function restart() {

		// Reinicia el response
		Http::getResponse()->clear();

		// Remueve los paquetes de idioma cargados
		LangPackage::reset();
	}

	/**
	 * Tarea al para finalizar luego de emitir un HTTP response o al realizar un Http::location()
	 *
	 * @return void
	 */
	public static function end() {

		// Cierra la sesión de php
		Session::close();
	}

	/**
	 * Parser de URL's.
	 *
	 * Identifica si la URL es un asset '#...' o si contiene un path al inicio '@path:'
	 *
	 * @param string $url     Url a procesar
	 * @param array  $version Variable de versión a agregar como parámetro de la URL
	 *
	 * @return string Url procesada
	 */
	public static function url($url, $version = false) {

		// Si la URL comienza con '#'
		$firstChar = $url[0];
		if ($firstChar == '#') {

			// Se obtiene la URL del asset
			$asset = Conf::getAsset(substr($url, 1));

			// Si el asset no está definido, se retorna la definición del asset
			if ($asset === null) {
				return $url;
			} else {
				$url = $asset;
			}
		}

		// Si la URL comienza con '@'
		$firstChar = $url[0];
		if ($firstChar == '@') {

			// Se divide la URL por el caracter '/'
			$url = explode('/', $url);

			// Obtiene el path
			$path = Conf::getPath(substr($url[0], 1));

			// Sustituye el valor del path
			if ($path !== null) {
				$url[0] = rtrim($path, '/');
			}

			// Construye nuevamente la URL
			$url = implode('/', $url);
		}

		// Si se definió agregar una versión a la URL
		if ($version != false) {

			// Si se definió como true, se obtiene la versión definida en las configuraciones del Framework
			if ($version === true) {
				$version = Conf::getParam('url_version');
			}

			// Verifica si es el primer parámetro de la URL
			if (strpos($url, '?')) {
				$url .= '&v=' . $version;
			} else {
				$url .= '?v=' . $version;
			}
		}

		// Retorna la URL
		return $url;
	}

	/**
	 * Autoloader de clases del Framework.
	 *
	 * @return void
	 *
	 * @throws FwError
	 */
	private static function _autoload($className) {

		// Reemplaza las '\' por '/'
		$className = str_replace('\\', '/', $className);

		// Verifica si el namespace corresponde al framework
		if (substr($className, 0, 3) == 'Fw/') {

			// Define el directorio de la clase
			$classDir = './fw/' . substr($className, 3) . '.php';

		// Verifica si el namespace corresponde a los definidos para la aplicación web
		} else {

			// Verifica si el namespace está registrado para la aplicación
			$path = explode('/', $className);
			if ((count($path) == 1) || (!in_array($path[0], Conf::getParam('namespaces')))) {
				return;
			}

			// Define el directorio de la clase
			$classDir = './namespaces/' . $className . '.php';
		}

		// Evalua la existencia del archivo php de la clase
		if (!file_exists($classDir)) {
			throw new FwError('class-not-found', null, [ 'className' => $className, 'classDir' => $classDir ]);
		}

		// Carga la clase
		require_once $classDir;

		// Reemplaza las '\' por '/'
		$className = str_replace('/', '\\', $className);

		// Verifica que la clase haya sido definida
		if (!class_exists($className, false)) {
			throw new FwError('class-not-defined', null, [ 'className' => $className, 'classDir' => $classDir ]);
		}
	}
}
?>
