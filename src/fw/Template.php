<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use Fw\Template\FileSystem as FileSystem;
use Fw\Template\TokenParser\SetFrontVars as TokenParserSetFrontVars;
use Twig_Environment;
use Twig_Error_Syntax;
use Twig_Extension_Debug;
use Twig_SimpleFunction;
use Twig_SimpleFilter;

/**
 * Motor de render del framework.
 *
 * Maneja la instancia global de Twig y encapsula el método para renderizar
 */
class Template {

	/** @var Twig_Environment Instancia global de Twig */
	private static $_twig;

	/**
	 * Renderiza y devuelve el template.
	 *
	 * @param string $path Ruta del archivo del template
	 * @param array  $vars Variables a pasar al renderizar
	 *
	 * @return string Template renderizado
	 */
	public static function render($path, $vars = array()) {

		// Renderiza el template
		return self::getTwig()->render($path, $vars);
	}

	/**
	 * Devuelve la instancia global de Twig.
	 *
	 * @return Twig_Environment
	 */
	public static function getTwig() {

		// Devuelve la instancia global de Twig si ya fue creada
		if (isset(self::$_twig)) {
			return self::$_twig;
		}

		// Define el loader del framework para Twig
		$loader = new FileSystem();

		// Crea la instancia de Twig
		self::$_twig = new Twig_Environment($loader, array(
			'cache' => 'cache/twig',
			'debug' => Conf::getParam('debug')
		));

		// Agrega la extensión debug
		if (Conf::getParam('debug') == true) {
			self::$_twig->addExtension(new Twig_Extension_Debug());
		}

		// Agrega la extensión 'SetFrontVars'
		self::$_twig->addTokenParser(new TokenParserSetFrontVars());

		// Carga las configuraciones de Twig
		$conf = require_once 'conf/twig.php';

		// Zona horaria default para fechas
		self::$_twig->getExtension('Twig_Extension_Core')->setTimezone($conf['timezone']());

		// Formato default para fechas
		self::$_twig->getExtension('Twig_Extension_Core')->setDateFormat($conf['dateformat']());

		// Extiende las variables globales
		foreach ($conf['vars'] as $varName => $value) {
			self::$_twig->addGlobal($varName, $value);
		}

		// Extiende las funciones
		foreach ($conf['functions'] as $functionName => $callback) {
			self::$_twig->addFunction(new Twig_SimpleFunction($functionName, $callback));
		}

		// Extiende los filtros
		foreach ($conf['filters'] as $filterName => $callback) {
			self::$_twig->addFilter(new Twig_SimpleFilter($filterName, $callback));
		}
		return self::$_twig;
	}
}
?>
