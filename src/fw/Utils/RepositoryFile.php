<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Utils;

use Fw\Http;
use Fw\Conf;
use Fw\ErrorList;
use Fw\FwException;
use Fw\Utils\Image;

/**
 * Clase para manejar archivos del repositorio.
 *
 * Permite conectarse a un archivo del respositorio.
 * - Permite descargar o mostrar los archivos.
 * - Para archivos de tipo imagen se puede generar vistas previas redimensionadas o recortadas.
 *
 * @property-read int|null 	  $id        Id del registro del archivo
 * @property-read string|null $uid       Identificador único del archivo
 * @property-read string|null $path      Directorio del fichero del archivo
 * @property-read string|null $filename  Nombre del fichero del archivo
 * @property-read string|null $extension Extensión del archivo
 * @property-read int|null    $bytes     Tamaño en bytes del archivo
 * @property-read string|null $mimeType  MIME type correspondiente al archivo
 */
class RepositoryFile {

	/** @var ErrorList Manejador de errores de la clase */
	public $error = null;

	/** @var array Referencia al registro del archivo almacenado en el array self::$_buckets */
	private $_fileRecord = null;

	/** @var string UID del archivo del repositorio */
	private $_fileUid;

	/** @var string UID del bucket correspondiente al archivo */
	private $_bucketUid;

	/** @var array Almacena todos los registros obtenidos de los archivos del repositorio, el objetivo es evitar hacer múltiples request al mismo registro */
	static private $_buckets = array();

	/**
	 * Constructor.
	 *
	 * @param string $bucketUid UID del bucket donde se aloja el archivo
	 * @param string $fileUid 	UID del registro del archivo
	 */
	public function __construct($bucketUid, $fileUid) {

		// Manejador de errores de la clase
		$this->error = new ErrorList(array(
			'file-no-registered'   => 'El archivo no está registrado',
			'no-valid-image'       => 'La extensión de la imagen es inválida',
			'load-image-error'     => 'Se produjo un error al cargar la imagen',
			'save-image-error'     => 'Se produjo un error al guardar la imagen',
			'invalid-preview-name' => 'El nombre del preview es inválido',
			'file-no-exists'       => 'El archivo no existe o no puede ser leído',
			'invalid-extension'    => 'La extensión del archivo no es válida por el Framework'
		));

		// Almacena el UID del archivo y del bucket
		$this->_bucketUid = $bucketUid;
		$this->_fileUid = $fileUid;

		// Verifica si el bucket aún no ha sido definido en self::$_buckets
		if (!isset(self::$_buckets[$this->_bucketUid])) {
			self::$_buckets[$this->_bucketUid] = array();
		}

		// Verifica si el registro del archivo aún no ha sido definido en self::$_buckets[$bucketUid]
		if (!isset(self::$_buckets[$this->_bucketUid][$this->_fileUid])) {
			self::$_buckets[$this->_bucketUid][$this->_fileUid] = null;
		}

		// Guarda por referencia el registro del archivo
		$this->_fileRecord = &self::$_buckets[$this->_bucketUid][$this->_fileUid];

		// Obtiene el registro si no había sido obtenido previamente
		if ($this->_fileRecord == null) {

			// Conexión a la base de datos
			$db = Conf::getDbConnection('fw');

			// Obtiene el registro del archivo
			$record = $db->get('view_fw_repo_file', array(
				'bucket_uid' => $this->_bucketUid,
				'file_uid' 	 => $this->_fileUid
			));

			// Almacena el registro del archivo en la propiedad estática self::$_buckets[$bucketUid]
			if ($record) {
				self::$_buckets[$this->_bucketUid][$this->_fileUid] = (array)$record;
			}
		}
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
	 * Confirma si el archivo existe.
	 *
	 * @return bool
	 */
	public function exists() {
		if (isset($this->_fileRecord)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Crea un preview de la imagen (Solo para archivos tipo imagen).
	 *
	 * @param string $preview Nombre del preview de la imagen
	 * @param string $action  Tipo de edición a realizar, valores permitidos {'resize', 'thumb'}
	 * @param int    $width   Ancho de imagen
	 * @param int    $height  Alto de imagen
	 * @param array  $crop    Coordenadas para realizar un recorte en la imagen, solo cuando $action es 'thumb'
	 *
	 * @return bool
	 */
	public function makePreview($preview, $action, $width, $height, $crop = null) {

		// Reinicia un error previo seleccionado
		$this->error->release();

		try {

			// Verifica que el archivo exista
			if (!$this->exists()) {
				throw new FwException('file-no-registered', $this->error);
			}

			// Confirma que la extensión de la imagen sea válida
			if (!in_array($this->extension, [ 'jpg', 'jpeg', 'png', 'gif' ])) {
				throw new FwException('no-valid-image', $this->error);
			}

			// Carga la imagen
			$img = new Image($this->path);
			if ($img->error->exists()) {
				throw new FwException('load-image-error', $this->error);
			}

			// Edita la imagen de acuerdo al tipo de acción
			switch ($action) {

				// Redimensiona la imagen
				case 'resize':
					$img->resize($width, $height);
					break;

				case 'thumb':

					// Realiza el recorte cuando es especificado
					if ($crop) {
						$img->crop($crop[0], $crop[1], $crop[2], $crop[3]);
					}

					// Redimensiona y recorta la imagen
					$img->thumb($width, $height);
					break;
			}

			// Directorio de destino del preview de la imagen
			$path = Conf::getParam('repo_path') . '/' . $this->_fileRecord('bucket_path') . '/previews/' . $this->_fileRecord('file_path') . '_' . $preview;

			// Verifica si el fichero ya existe para obtener su tamaño antes de ser eliminado
			if (file_exists($path)) {
				$prevBytes = filesize($path);
				unlink($path);
			} else {
				$prevBytes = 0;
			}

			// Guarda el preview de la imagen
			if ($img->saveAs($path) == false) {
				throw new FwException('save-image-error', $this->error);
			}

			// Libera la imagen de la memoria
			$img->destroy();

			// Actualiza el espacio registrado por el archivo en el bucket, suma el tamaño del preview y resta el del preview previo (Si existía)
			$this->_updateSpace(filesize($path) - $prevBytes);

			return true;

		} catch (FwException $e) {
			return false;
		}
	}

	/**
	 * Define una respuesta HTTP para descargar o mostrar el archivo en el navegador.
	 *
	 * @param string $action  Tipo de respuesta, opciones posibles {'display', 'download'}
	 * @param array  $options Array asociativo de parámetros opcionales para la respuesta HTTP
	 * - $options['cache'] Tiempo en segundos o intervalo de tiempo, el intervalo puede especificarse como es utilizado por DateInterval, ej: PT15M.
	 * - $options['preview'] Nombre del preview del archivo a mostrar (Solo cuando el tipo de archivo es imagen)
	 *
	 * @return bool
	 */
	public function response($action, array $options = array()) {

		// Reinicia un error previo seleccionado
		$this->error->release();

		// Obtiene el response HTTP
		$res = Http::getResponse();

		try {

			// Verifica que el archivo exista
			if (!$this->exists()) {
				throw new FwException('file-no-registered', $this->error);
			}

			// Si el archivo no dispone de información mime el archivo será descargado
			if (($action == 'display') && ($this->mimeType === null)) {
				$action = 'download';
			}

			// Define el directorio del archivo o el del preview si fue solicitado
			if (isset($options['preview'])) {

				// Evalua que el nombre del preview solicitado sea válido
				if (filter_var($data['preview'], FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^(?:[a-z0-9\-\_])+$/i'))) === false) {
					throw new FwException('invalid-preview-name', $this->error);
				}

				// Define el directorio del preview del archivo
				$path = Conf::getParam('repo_path') . '/' . $this->_fileRecord('bucket_path') . '/previews/' . $this->_fileRecord('file_path') . '_' . $options['preview'];

			} else {
				$path = $this->path;
			}

			// Verifica la existencia y disponibilidad del archivo
			if ((!file_exists($path)) || (!is_readable($path))) {
				throw new FwException('file-no-exists', $this->error);
			}

			// Verifica que la extensión esté en la lista de extensiones permitidas
			$validExt = Conf::getParam('valid_extensions');
			if (isset($validExt[$this->extension])) {
				throw new FwException('invalid-extension', $this->error);
			}

			// Respuesta Http para mostrar el archivo
			if ($action == 'display') {
				$res->file($path, $this->mimeType);
			}

			// Respuesta Http para descargar el archivo
			if ($action == 'download') {
				$res->file($path, 'application/octet-stream', $this->filename . '.' . $this->extension);
			}

			// Cache para la respuesta HTTP
			if (isset($options['cache'])) {
				$res->cache = $options['cache'];
			}

			// Emite el response
			$res->emit();

		} catch (FwException $e) {
			return false;
		}
	}

	/**
	 * Actualiza el espacio utilizado correspondiente al bucket.
	 *
	 * @param int $bytes Cantidad de bytes a sumar o restar
	 *
	 * @return void
	 */
	private function _updateSpace($bytes) {

		// Conexión a la base de datos
		$db = Conf::getDbConnection('fw');

		// Actualiza el espacio del bucket
		$db->update('fw_repo_bucket', array(
			'bytes' => [ 'bytes + :bytes' ]
		));
		$db->where('id = :id', array(
			':id' 	 => ['int', $this->_fileRecord('bucket_id')],
			':bytes' => ['int', $bytes]
		));
		$db->execute();
	}

	/**
	 * Devuelve un atributo del registro del archivo.
	 *
	 * @param string $colName Nombre de la columna o atributo
	 *
	 * @return mixed Retorna el valor del atributo solicitado
	 */
	private function _fileRecord($colName) {
		if ((isset($this->_fileRecord)) && (isset($this->_fileRecord[$colName]))) {
			return $this->_fileRecord[$colName];
		} else {
			return null;
		}
	}

	/**
	 * Propiedad $id.
	 *
	 * @return int|null ID del registro del archivo
	 */
	private function _get_id() {
		return $this->_fileRecord('file_id');
	}

	/**
	 * Propiedad $uid.
	 *
	 * @return string|null Identificador único del archivo
	 */
	private function _get_uid() {
		return $this->_fileRecord('file_uid');
	}

	/**
	 * Propiedad $path.
	 *
	 * @return string|null Directorio absoluto del fichero del archivo
	 */
	private function _get_path() {
		if ($this->_fileRecord('file_path')) {
			return Conf::getParam('repo_path') . '/' . $this->_fileRecord('bucket_path') . '/' . $this->_fileRecord('file_path');
		} else {
			return null;
		}
	}

	/**
	 * Propiedad $filename.
	 *
	 * @return string|null Nombre del fichero del archivo
	 */
	private function _get_filename() {
		return $this->_fileRecord('file_filename');
	}

	/**
	 * Propiedad $extension.
	 *
	 * @return string|null Extensión del archivo
	 */
	private function _get_extension() {
		return $this->_fileRecord('file_extension');
	}

	/**
	 * Propiedad $bytes.
	 *
	 * @return int|null Tamaño en bytes del archivo
	 */
	private function _get_bytes() {
		return $this->_fileRecord('file_bytes');
	}

	/**
	 * Propiedad $mimeType.
	 *
	 * @return string|null MIME TYPE correspondiente al archivo
	 */
	private function _get_mimeType() {
		$ext = $this->_fileRecord('file_extension');
		$validExt = Conf::getParam('valid_extensions');
		if ((isset($validExt[$ext])) && (!empty($validExt[$ext]))) {
			return $validExt[$ext];
		} else {
			return null;
		}
	}
}
?>
