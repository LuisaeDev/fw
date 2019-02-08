<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Template;

use Fw\Fw;
use Fw\Template;
use Fw\FwException_Template;
use Twig_LoaderInterface;
use Twig_ExistsLoaderInterface;
use Twig_SourceContextLoaderInterface;
use Twig_Source;

/**
 * Loader de templates extendido para el framework y pasado a Twig.
 *
 * @tutorial https://twig.symfony.com/doc/2.x/api.html#create-your-own-loader
 */
class FileSystem implements Twig_LoaderInterface, Twig_ExistsLoaderInterface, Twig_SourceContextLoaderInterface {

	public function __construct() {
	}

	/**
	 * Returns the source context for a given template logical name.
	 *
	 * @param string $name The template logical name
	 *
	 * @return Twig_Source
	 *
	 * @throws Twig_Error_Loader When $name is not found
	 */
	public function getSourceContext($name) {
		$path = $this->findTemplate($name);
		return new Twig_Source(file_get_contents($path), $name, $path);
	}

	/**
	 * Gets the cache key to use for the cache for a given template name.
	 *
	 * @param string $name The name of the template to load
	 *
	 * @return string The cache key
	 *
	 * @throws Twig_Error_Loader When $name is not found
	 */
	public function getCacheKey($name) {
		return $name;
	}

	/**
	 * Returns true if the template is still fresh.
	 *
	 * @param string $name The template name
	 * @param int    $time Timestamp of the last modification time of the cached template
	 *
	 * @return bool true if the template is fresh, false otherwise
	 *
	 * @throws Twig_Error_Loader When $name is not found
	 */
	public function isFresh($name, $time) {
		return filemtime($this->findTemplate($name)) <= $time;
	}

	/**
	 * Check if we have the source code of a template, given its name.
	 *
	 * @param string $name The name of the template to check if we can load
	 *
	 * @return bool If the template source code is handled by this loader or not
	 */
	public function exists($name) {
		return false !== $this->findTemplate($name, false);
	}

	/**
	 * Devuelve la ruta absoluta de un template. Emite una excepción si $throw o false si el template no existe.
	 *
	 * @param string  $path  Ruta relativa del template
	 * @param boolean $throw Determina si debe emitirse una excepción si el template no existe
	 *
	 * @return string|false Devuelve la ruta o false si no existe el template
	 *
	 * @throws FwException_Template
	 */
	protected function findTemplate($path, $throw = true) {

		// Verifica si no se ha especificado la extensión
		if (substr($path, -5) != '.twig') {
			$path = $path . '.twig';
		}

		// Verifica si el template existe
		if (file_exists($path)) {
			return $path;
		} else {
			if ($throw) {
				throw new FwException_Template('template-not-found', null, [ 'path' => $path ]);
			} else {
				return false;
			}
		}
	}
}
?>
