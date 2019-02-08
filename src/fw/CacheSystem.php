<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use Fw\Utils\ArrayTools;

/**
 * Clase para la carga y almacenamiento de recursos en cache.
 */
class CacheSystem {

	/**
	 * Carga un recurso desde el cache.
	 *
	 * @param string   $path        Directorio en cache de donde se cargará el recurso
	 * @param string   $name        Nombre único del recurso
	 * @param int|null $lastModTime Tiempo Unix de última modificación del recurso, si se omite se verifica si el $name es el directorio de un archivo y se carga el tiempo de su última modificación
	 *
	 * @return mixed Contenido del recurso cargado desde cache
	 *
	 * @throws FwException_CacheSystem
	 */
	public static function load($path, $name, $lastModTime = null) {

		// Define el directorio del archivo en cache
		$cachedFilePath = './cache/' . $path .'/' . self::_getKey($name) . '.php';

		// Verifica si el recurso existe en cache
		if (!file_exists($cachedFilePath)) {
			throw new FwException_CacheSystem('resource-not-found');
		}

		// Obtiene el time del recurso si no fue especificado
		if ($lastModTime === null) {
			if (file_exists($name)) {
				$lastModTime = filemtime($name);
			} else {
				$lastModTime = time();
			}
		}

		// Verifica si el recurso en cache está expirado
		if ($lastModTime > filemtime($cachedFilePath)) {
			unlink($cachedFilePath);
			throw new FwException_CacheSystem('resource-expired');
		}

		// Devuelve el recurso en cache
		return require_once $cachedFilePath;
	}

	/**
	 * Decodifica un archivo JSON y lo almacena en cache.
	 *
	 * @param string $path Directorio del archivo JSON
	 *
	 * @return array Datos obtenidos al decodificar el archivo JSON
	 *
	 * @throws FwException_CacheSystem
	 */
	public static function decodeJSON($path) {

		// Verifica que el archivo exista
		if (!file_exists($path)) {
			throw new FwException_CacheSystem('json-not-found', null, [ 'path' => $path]);
		}

		try {

			$result = self::load('fw/json', $path);

		} catch (FwException_CacheSystem $e) {

			// Carga y decodifica el recurso JSON
			$result = json_decode(file_get_contents($path), true);

			// Verifica si el recurso JSON cargó exitosamente
			if (($result === null) || (!ArrayTools::isAssociative($result))) {
				throw new FwException_CacheSystem('json-invalid', null, [ 'path' => $path ]);
			}

			// Almacena en cache el recurso JSON
			CacheSystem::save('fw/json', $path, $result);
		}

		return $result;
	}

	/**
	 * Almacena un recurso en cache.
	 *
	 * @param string $path    Directorio en donde se almacenará en el cache del framework
	 * @param strng  $name    Nombre único del recurso
	 * @param mixed  $content Contenido a almacenar en cache
	 *
	 * @return void
	 */
	public static function save($path, $name, $content) {

		// Directorio absoluto del path
		$dir = './cache/' . $path;

		// Crea el directorio si no existe
		if (!is_dir($dir)) {
			mkdir($dir, 0750, true);
		}

		// Define el contenido a almacenar en el cache
		$content = "<?php\n\nreturn " . var_export($content, true). ";\n\n?>";

		// Almacena el contenido en cache
		file_put_contents('./cache/' . $path . '/' . self::_getKey($name) . '.php', $content);
	}

	/**
	 * Devuelve un key o identificador generado por el nombre del recurso.
	 *
	 * @param string $name Nombre único del recurso
	 *
	 * @return string Key o identificador generado
	 */
	private static function _getKey($name) {
		return md5($name);
	}
}
?>
