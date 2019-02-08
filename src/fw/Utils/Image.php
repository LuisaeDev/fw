<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Utils;

use Fw\ErrorList;
use Fw\FwException;

/**
 * Clase para la edición de archivos tipo imagen *.jpg, *.jpeg, *.png, *.gif
 *
 * @property-read GD  $gd     Instancia de la imagen en memoria
 * @property-read int $width  Ancho actual de la imagen en edición
 * @property-read int $height Alto actual de la imagen en edición
 * @property-read int $mp     Resolución en megapixeles actual de la imagen en edición
*/
class Image {

	/** @var ErrorList Manejador de errores de la clase */
	public $error = null;

	/** @var string Directorio del fichero de imagen en edición */
	private $_path;

	/** @var GD Instancia de la imagen en memoria */
	private $_gd;

	/** @var string Formato de la imagen en edición */
	private $_format;

	/** @var string Formato MIME de la imagen en edición */
	private $_mime;

	/**
	 * Constructor.
	 *
	 * Carga la imagen a editar
	 *
	 * @param string $path Directorio del fichero de imagen a
	 */
	public function __construct($path) {

		// Manejador de errores de la clase
		$this->error = new ErrorList(array(
			'file-no-readable' => 'El archivo no existe o no puede ser leído',
			'damaged-image'    => 'El archivo de imagen posiblemente esté dañado',
			'invalid-image'    => 'El formato de la imagen no es válido'
		));

		// Carga la imagen
		return $this->load($path);
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
	 * Carga la imagen a editar.
	 *
	 * @param string $path Directorio del fichero de imagen a editar
	 *
	 * @return bool Confirmación de carga del archivo de imagen
	 */
	public function load($path) {

		// Reinicia un error previo seleccionado
		$this->error->release();

		// Destruye una imagen previa
		$this->destroy();

		try {

			// Verifica que el archivo exista y pueda ser leido
			if ((!file_exists($path)) || (!is_readable($path))) {
				throw new FwException('file-no-readable', $this->error);
			}

			// Obtiene la información de la imagen y verifica que sea válida
			$metadata = getimagesize($path);
			if (($metadata == false) || (!is_array($metadata)) || ($metadata[0] == 0) || ($metadata[1] == 0)) {
				throw new FwException('damaged-image', $this->error);
			}

			// Guarda el formato MIME
			$this->_mime = $metadata['mime'];

			// Crea la imagen
			switch ($metadata[2]) {
				case IMAGETYPE_JPEG:
					$this->_format = 'jpg';
					$this->_gd = imagecreatefromjpeg($path);
					break;

				case IMAGETYPE_PNG:
					$this->_format = 'png';
					$this->_gd = imagecreatefrompng($path);
					break;

				case IMAGETYPE_GIF:
					$this->_format = 'gif';
					$this->_gd = imagecreatefromgif($path);
					break;

				default:
					throw new FwException('invalid-image', $this->error);
					break;
			}

			// Verifica que la imagen haya sido cargada
			if ($this->_gd == false) {
				throw new FwException('damaged-image', $this->error);
			}

			// Guarda el directorio de la imagen
			$this->_path = $path;

			return true;

		} catch (FwException $e) {

			// Reinicia las propiedades
			$this->_path = null;
			$this->_gd = null;
			$this->_format = null;
			$this->_mime = null;

			return false;
		}
	}

	/**
	 * Redimensiona una imagen.
	 *
	 * La imagen puede ser redimensionada a un tamaño mayor o menor,
	 * pero sin importar las nuevas dimensiones siempre se mantendrá la proporción original,
	 * es decir que si las dimensiones especificadas son diferentes a la proporción de la imagen,
	 * la redimensión se calculará de manea que mantenga la proporción original de la imagen
	 * pero sin rebasar las dimensiones especificadas.
	 *
	 * @param int  $w      Ancho en pixeles para redimensionar
	 * @param int  $h      Alto en pixeles para redimensionar
	 * @param bool $expand Determina si la imagen puede o no expandirse más que su tamaño original
	 *
	 * @return bool Confirmación de éxito
	 */
	public function resize($w, $h, $expand = true) {

		// Verifica que la imagen haya sido cargada
		if ($this->_gd == null) {
			return false;
		}

		// Encaja las nuevas dimensiones en las dimensiones solicitadas
		$coords = $this->fit([ $this->width, $this->height ], [ $w, $h ]);

		// Redondea las coordenadas
		$coords['x'] = round($coords['x']);
		$coords['y'] = round($coords['y']);
		$coords['w'] = round($coords['w']);
		$coords['h'] = round($coords['h']);

		// Finaliza la función si la imagen no puede expandirse más que su tamaño original
		if (($expand == false) && (($coords['w'] >= $this->width) || ($coords['h'] >= $this->height))) {
			return false;
		}

		// Crea una copia temporal de la imagen y la redimensiona
		$imageTemp = imagecreatetruecolor($coords['w'], $coords['h']);
		if ($this->_format == 'png') {
			$this->_alpha($imageTemp, $coords['w'], $coords['h']);
		}
		imagecopyresampled($imageTemp, $this->_gd, 0, 0, 0, 0, $coords['w'], $coords['h'], $this->width, $this->height);

		// Destruye la imagen actual y crea una nueva a partir de la imagen temporal
		imagedestroy($this->_gd);
		$this->_gd = imagecreatetruecolor($coords['w'], $coords['h']);
		if ($this->_format == 'png') {
			$this->_alpha($this->_gd, $coords['w'], $coords['h']);
		}
		imagecopy($this->_gd, $imageTemp, 0, 0, 0, 0, $coords['w'], $coords['h']);

		// Destruye la imagen temporal
		imagedestroy($imageTemp);

		return true;
	}

	/**
	 * Realiza un recorte en la imagen.
	 *
	 * @param int $x Coordenada x en pixeles del recorte
	 * @param int $y Coordenada y en pixeles del recorte
	 * @param int $w Ancho en pixeles del recorte
	 * @param int $h Alto en pixeles del recorte
	 *
	 * @return bool Confirmación de éxito
	 */
	public function crop($x, $y, $w, $h) {

		// Verifica que la imagen haya sido cargada
		if ($this->_gd == null) {
			return false;
		}

		// Redondea las coordenadas
		$x = round($x);
		$y = round($y);
		$w = round($w);
		$h = round($h);

		// Corrige las coordenadas que están fuera de las proporciones de la imagen
		if (($x + $w) > $this->width) {
			$w = $this->width - $x;
		}
		if (($y + $h) > $this->height) {
			$h = $this->height - $y;
		}
		if ($x < 0) {
			$x = 0;
		}
		if ($y < 0) {
			$y = 0;
		}

		// Crea una copia temporal de la imagen recortando las coordenadas especificadas
		$imageTemp = imagecreatetruecolor($w, $h);
		if ($this->_format == 'png') {
			$this->_alpha($imageTemp, $w, $h);
		}
		imagecopyresampled($imageTemp, $this->_gd, 0, 0, $x, $y, $w, $h, $w, $h);

		// Destruye la imagen actual y crea una nueva a partir de la imagen temporal
		imagedestroy($this->_gd);
		$this->_gd = imagecreatetruecolor($w, $h);
		if ($this->_format == 'png') {
			$this->_alpha($this->_gd, $w, $h);
		}
		imagecopy($this->_gd, $imageTemp, 0, 0, 0, 0, $w, $h);

		// Destruye la imagen temporal
		imagedestroy($imageTemp);

		return true;
	}

	/**
	 * Redimensiona y recorta la imagen.
	 *
	 * Se redimensiona la imagen en una resolución especifica.
	 * Si la proporción de la nueva resolución no coincide con la proporción de la imagen,
	 * se recorta la imagen respetando la proporción de la nueva resolución.
	 *
	 * @param int $w Ancho en pixeles para redimensionar
	 * @param int $h Alto en pixeles para redimensionar
	 */
	public function thumb($w, $h) {

		// Encaja las dimensiones de la nueva resolución en la resolución actual de la imagen
		$coords = $this->fit([ $w, $h ], [ $this->width, $this->height ]);

		// Realiza un recorte en las coordenadas especificadas
		$this->crop($coords['x'], $coords['y'], $coords['w'], $coords['h']);

		// Redimensiona la imagen a la nueva resolución
		$this->resize($w, $h);
	}

	/**
	 * Redimensiona la imagen en base a megapixeles.
	 *
	 * @param float $mp Cantidad de megapixeles
	 */
	public function mpResize($mp) {

		// Obtiene la proporción ratio de la imagen
		$ratio = round($this->width / $this->height, 3);

		// En base a la resolución en megapixeles sugerida ($mp), determina las nuevas dimensiones de la imagen conservando su proporción original
		$h = sqrt($mp * 1000000 / $ratio);
		$w = $h * $ratio;

		// Redimensiona la imagen
		$this->resize($w, $h);
	}

	/*
	// Realiza un rotación de la imagen
	public function rotar($grados) {

		// Verifica que la imagen haya sido cargada
		if ($this->_gd == null) {
			return false;
		}

		// Realiza la rotación de la imagen
		$cache = imagerotate($cache, $grados, 0);

		// Actualiza la variable imagen
		imagedestroy($this->_gd);
		$this->_gd = imagecreatetruecolor($w, $h);
		imagecopy($this->_gd, $cache, 0, 0, 0, 0, $w, $h);
		imagedestroy($cache);

		return true;
	}
	*/

	/**
	 * Guarda en el fichero de la imagen actual los cambios realizados en la imagen.
	 *
	 * @param string|null $format  Formato de la imagen a guardar
	 * @param int         $quality Calidad de la imagen, solo para JPG
	 *
	 * @return bool Confirmación de éxito
	 */
	public function save($format = null, $quality = 75) {
		return $this->saveAs($this->_path, $format, $quality);
	}

	/**
	 * Guarda en un fichero específico los cambios realizados.
	 *
	 * @param string      $path    Directorio del fichero a guardar
	 * @param string|null $format  Formato de la imagen a guardar
	 * @param int         $quality Calidad de la imagen, solo para JPG
	 *
	 * @return bool Confirmación de éxito
	 */
	public function saveAs($path, $format = null, $quality = 75) {

		// Verifica que la imagen haya sido cargada
		if ($this->_gd == null) {
			return false;
		}

		// Si no se definió un nuevo formato
		if ($format == null) {
			$format = $this->_format;
		}

		// Guarda la imagen en el directorio y formato especificado
		switch ($format) {
			case 'jpg':
			case 'jpeg':
				$success = imagejpeg($this->_gd, $path, $quality);
				break;

			case 'png':
				$success = imagepng($this->_gd, $path);
				break;

			case 'gif':
				$success = imagegif($this->_gd, $path);
				break;

			default:
				$success = false;
				break;
		}

		if ($success) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Destruye la imagen en memoria para liberar espacio.
	 *
	 * @return void
	 */
	public function destroy() {

		// Reinicia un error previo seleccionado
		$this->error->release();

		// Libera la imagen de la memoria
		if ($this->_gd !== null) {
			imagedestroy($this->_gd);
		}

		// Reinicia las propiedades
		$this->_path = null;
		$this->_gd = null;
		$this->_format = null;
		$this->_mime = null;
	}

	/**
	 * Función herramienta para encajar un área 'a' dentro de un área 'b'.
	 *
	 * Encaja las dimensiones ancho y alto de un área 'a' dentro de un área 'b' respetando las proporciones del área 'a',
	 * y respetando el tamaño del área 'b'.
	 *
	 * @param array $a Ancho y alto del área 'a'
	 * @param array $b Ancho y alto del área 'b'
	 *
	 * @return array Coordenadas x, y, w, h para encajar el área 'a' dentro de 'b'
	 */
	public function fit($a, $b) {
		$a['ratio'] = $a[0] / $a[1];
		$b['ratio'] = $b[0] / $b[1];

		if ($a['ratio'] <= $b['ratio']) {
			$c['w'] = $a[0] / ($a[1] / $b[1]);
			$c['h'] = $b[1];
		} else {
			$c['h'] = $a[1] / ($a[0] / $b[0]);
			$c['w'] = $b[0];
		}

		$c['x'] = ($b[0] - $c['w'])/2;
		$c['y'] = ($b[1] - $c['h'])/2;
		return $c;
	}

	/**
	 * Propiedad $gd.
	 *
	 * @return GD Devuelve la instancia de la imagen en memoria
	 */
	private function _get_gd() {
		return $this->_gd;
	}

	/**
	 * Propiedad $width.
	 *
	 * @return int Devuelve el ancho actual de la imagen en edición
	 */
	private function _get_width() {
		if ($this->_gd != null) {
			return imagesx($this->_gd);
		} else {
			return null;
		}
	}

	/**
	 * Propiedad $height.
	 *
	 * @return int Devuelve el alto actual de la imagen en edición
	 */
	private function _get_height() {
		if ($this->_gd != null) {
			return imagesy($this->_gd);
		} else {
			return null;
		}
	}

	/**
	 * Propiedad $mp.
	 *
	 * @return int Devuelve la resolución en megapixeles actual de la imagen en edición
	 */
	private function _get_mp() {
		if ($this->_gd != null) {
			return ((imagesy($this->_gd) * imagesx($this->_gd)) / 1000000);
		} else {
			return null;
		}
	}

	/**
	 * Aplica el canal alpha para imágenes en formato 'png'.
	 *
	 * @param $img Obtiene por referencia la instancia de imagen
	 *
	 * @return void
	 */
	private function _alpha(&$img, $w, $h) {
		imagealphablending($img, false);
		imagesavealpha($img,true);
		$alpha = imagecolorallocatealpha($img, 255, 255, 255, 127);
		imagefilledrectangle($img, 0, 0, $w, $h, $alpha);
	}
}
?>
