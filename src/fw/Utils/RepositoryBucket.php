<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Utils;

use Fw\Auth;
use Fw\Conf;
use Fw\ErrorList;
use Fw\FwException;
use Fw\Utils\RepositoryFile;
use Fw\Utils\DataTools;

/**
 * Clase para manejar los bukets del repositorio.
 *
 * A través de esta clase se puede agregar y eliminar archivos del bucket.
 *
 * @property-read int    $id   ID del registro del bucket
 * @property-read string $uid  Identificador único del bucket
 * @property-read string $path Directorio del bucket
 */
class RepositoryBucket {

	/** @var ErrorList Manejador de errores de la clase */
	public $error = null;

	/** @var string UID del bucket */
	private $_uid;

	/** @var array Referencia al registro del bucket almacenado en el array self::$_buckets */
	private $_bucketRecord = null;

	/** @var array Almacena todos los registros obtenidos de los buckets del repositorio, el objetivo es evitar hacer múltiples request al mismo registro */
	static private $_buckets = array();

	/**
	 * Constructor.
	 *
	 * @param string $uid Identificador único del bucket
	 */
	public function __construct($uid) {

		// Almacena el UID del bucket
		$this->_uid = $uid;

		// Manejador de errores de la clase
		$this->error = new ErrorList(array(
			'without-session'     => 'Se requiere una sesión de usuario',
			'creating-folder'     => 'No fue posible crear el folder para el bucket "$uid"',
			'upload-error'        => 'Se produjo el error "$error" al subir el archivo',
			'invalid-upload'      => 'El archivo no existe o no fue subido mediante HTTP POST',
			'file-no-exists'      => 'El archivo no existe o no puede ser leído',
			'undefined-extension' => 'La extensión del archivo no ha sido definida',
			'invalid-extension'   => 'La extensión del archivo no es válida por el Framework',
			'max-file-size'       => 'El archivo es de mayor tamaño que el permitido por el framework',
			'save-file-error'     => 'Ocurrió un error al guardar el archivo a su destino en el repositorio',
			'copy-file-error'     => 'Ocurrió un error al copiar el archivo a su destino en el repositorio',
			'file-no-registered'  => 'El archivo no está registrado'
		));

		// Verifica si el registro del bucket aún no ha sido definido en self::$_buckets
		if (!isset(self::$_buckets[$this->_uid])) {
			self::$_buckets[$this->_uid] = null;
		}

		// Guarda por referencia el registro del bucket
		$this->_bucketRecord = &self::$_buckets[$this->_uid];

		// Obtiene el registro si no había sido obtenido previamente
		if ($this->_bucketRecord == null) {

			// Conexión a la base de datos
			$db = Conf::getDbConnection('fw');

			// Obtiene el registro del bucket
			$record = $db->get('fw_repo_bucket', [ 'uid', $this->_uid ], 'id, uid, path');

			// Almacena el registro del bucket en la propiedad estática self::$_buckets
			if ($record) {
				self::$_buckets[$this->_uid] = (array)$record;

			// Si el registro no existe, crea el bucket
			} else {
				$this->_create();
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
	 * Devuelve una instancia RepoFile correspondiente a un archivo de este bucket.
	 *
	 * @param string $fileUid Identificador único del archivo
	 *
	 * @return RepositoryFile Instancia del archivo
	 */
	public function get($fileUid) {
		return new RepositoryFile($this->_uid, $fileUid);
	}

	/**
	 * Agrega un archivo al bucket.
	 *
	 * @param array|string $file   Array con la información del archivo subido a través del método $_FILES o un directorio del fichero
	 * @param array        $params Array asociativo de parámetros opcionales para agregar en el registro del archivo
	 *
	 * @return RepositoryFile|false Instancia del archivo o false cuando ocurrió un error
	 */
	public function put($file, array $params = array()) {

		// Reinicia un error previo seleccionado
		$this->error->release();

		try {

			// Confirma que exista una sesión
			if (!Auth::isLogged()) {
				throw new FwException('without-session', $this->error);
			}

			// Conexión a la base de datos
			$db = Conf::getDbConnection('fw');

			// Array con información del nuevo archivo
			$fileInfo = array();

			// Si $file es un array, la procesa como una variable de un arcihvo pasada a través $_FILES[]
			if (is_array($file)) {

				// Verifica si ocurrió algún error durante la subida
				if ($file['error'] !== UPLOAD_ERR_OK) {
					throw new FwException('upload-error', $this->error, [ 'error' => $file['error'] ]);
				}

				// Confirma que el archivo exista, que pueda ser leído y que haya sido subido mediante HTTP POST
				if ((!file_exists($file['tmp_name'])) || (!is_readable($file['tmp_name'])) || (!is_uploaded_file($file['tmp_name']))) {
					throw new FwException('invalid-upload', $this->error);
				}

				// Directorio de origen del archivo
				$fileInfo['sourcePath'] = $file['tmp_name'];

				// Obtiene el tamaño en bytes del archivo
				$fileInfo['bytes'] = filesize($fileInfo['sourcePath']);

				// Obtiene la extensión
				$fileInfo['extension'] = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

				// Define el nombre del archivo:
				// - Ya sea que fue especificado en el parámetro $param['filename'],
				// - Obtenido a través del nombre del archivo subido
				// - Asignado cuando los métodos anteriores fallan
				if ((isset($params['filename'])) && (!empty($params['filename']))) {
					$params['filename'] = $params['filename'];
				} else if (strlen(pathinfo($file['name'])['filename']) > 0) {
					$params['filename'] = pathinfo($file['name'])['filename'];
				} else {
					$params['filename'] = 'file';
				}

			// Si $file es un recurso de tipo 'stream'
			} else if ((is_resource($file)) && (get_resource_type($file) == 'stream')) {

				// Obtiene estadísticas del archivo
				$data = fstat($file);

				// Obtiene el tamaño en bytes del archivo
				$fileInfo['bytes'] = $data['size'];

				// Verifica que la extensión del archivo haya sido definida por $params
				if ((isset($params['extension'])) && (!empty($params['extension']))) {
					$fileInfo['extension'] = $params['extension'];
				} else {
					throw new FwException('undefined-extension', $this->error);
				}

				// Obtiene el nombre del archivo definido por $params
				if ((isset($params['filename'])) && (!empty($params['filename']))) {
					$params['filename'] = $params['filename'];
				} else {
					$params['filename'] = 'file';
				}

			// Si $file es un path a un archivo
			} else {

				// Confirma que el archivo exista y que pueda ser leído
				if ((!file_exists($file)) || (!is_readable($file))) {
					throw new FwException('file-no-exists', $this->error);
				}

				// Directorio de origen del archivo
				$fileInfo['sourcePath'] = $file;

				// Obtiene el tamaño en bytes del archivo
				$fileInfo['bytes'] = filesize($fileInfo['sourcePath']);

				// Obtiene la extensión
				$fileInfo['extension'] = strtolower(pathinfo($file, PATHINFO_EXTENSION));

				// Define el nombre del archivo:
				// - Ya sea que fue especificado en el parámetro $param['filename'],
				// - Obtenido a través del nombre del archivo subido
				// - Asignado cuando los métodos anteriores fallan
				if ((isset($params['filename'])) && (!empty($params['filename']))) {
					$params['filename'] = $params['filename'];
				} else if (!empty(pathinfo($file)['filename'])) {
					$params['filename'] = pathinfo($file)['filename'];
				} else {
					$params['filename'] = 'file';
				}
			}

			// Verifica si el largo del nombre del archivo es mayor que 250 caracteres
			if (strlen($params['filename']) > 250) {
				$params['filename'] = substr($params['filename'], 0, 250);
			}

			// Verifica que la extensión sea válida por el Framework
			$validExt = Conf::getParam('valid_extensions');
			if (!isset($validExt[$fileInfo['extension']])) {
				throw new FwException('invalid-extension', $this->error);
			}

			// Verifica que el tamaño del archivo no sea mayor que el permitido por el Framework
			$fwMaxFilesize = Conf::getParam('input_max_file_size') * 1024 * 1024;
			$uploadMaxFilesize = ((int)ini_get('upload_max_filesize')) * 1024 * 1024;
			$postMaxFilesize = ((int)ini_get('post_max_size')) * 1024 * 1024;
			if (($fileInfo['bytes'] > $fwMaxFilesize) || ($fileInfo['bytes'] > $uploadMaxFilesize) || ($fileInfo['bytes'] > $postMaxFilesize)) {
				throw new FwException('max-file-size', $this->error);
			}

			// Si se definió un UID para el archivo, se verifica si en el bucket existe otro archivo con ese UID
			if ((isset($params['uid'])) && (!empty($params['uid']))) {

				// Verifica si el largo del UID especificado es mayor que 50 caracteres
				if (strlen($params['uid']) > 50) {
					$params['uid'] = substr($params['uid'], 0, 50);
				}

				// Busca en el bucket el registro del archivo por su UID
				$record = $db->get('fw_repo_file', [ 'bucket_id' => $this->_bucketRecord('id'), 'uid' => $params['uid'] ], 'id, path, bytes');

				// Confirma si el registro del archivo existe
				if ($record) {

					// Acción a realizar
					$action = 'update';

					// Obtiene el directorio previo del archivo
					$fileInfo['path'] = $record->path;

					// Peso en bytes del archivo anterior
					$prevBytes = $record->bytes;

				} else {

					// Acción a realizar
					$action = 'add';
				}

			} else {

				// Define un nuevo UID para el archivo
				while (true) {
					$params['uid'] = DataTools::createUID(16);
					if ($db->count('*', 'fw_repo_file', [ 'bucket_id' => $this->_bucketRecord('id'), 'uid' => $params['uid'] ]) == 0) {
						break;
					}
				}

				// Acción a realizar
				$action = 'add';
			}

			// Si la acción es agregar
			if ($action == 'add') {

				// Define el directorio del nuevo archivo
				while (true) {
					$fileInfo['path'] = strtolower(DataTools::createUID(16));
					if ($db->count('*', 'fw_repo_file', [ 'bucket_id' => $this->_bucketRecord('id'), 'path' => $fileInfo['path'] ]) == 0) {
						break;
					}
				}

				// Es valor de $prevBytes es diferente a 0 cuando la acción es 'update', se refiere al peso del archivo que será sustituido
				$prevBytes = 0;
			}

			// Define el directorio de destino del archivo en el bucket del repositorio
			$fileInfo['destPath'] = $this->path . '/' . $fileInfo['path'];

			// Guarda o copia el archivo de origen en su destino en el bucket del repositorio
			if (is_resource($file)) {
				if (!file_put_contents($fileInfo['destPath'], $file)) {
					throw new FwException('save-file-error', $this->error);
				}
			} else {
				if (!copy($fileInfo['sourcePath'], $fileInfo['destPath'])) {
					throw new FwException('copy-file-error', $this->error);
				}
			}

			// Actualiza el espacio del bucket, suma el tamaño del nuevo archivo y resta el tamaño del archivo previo (Si la acción era 'update')
			$this->_updateSpace($fileInfo['bytes'] - $prevBytes);

			// Se guarda el registro del archivo
			switch ($action) {
				case 'add':
					$db->autoInsert('fw_repo_file', array(
						'bucket_id'         => $this->_bucketRecord('id'),
						'uid'               => $params['uid'],
						'path'              => $fileInfo['path'],
						'filename'          => $params['filename'],
						'extension'         => $fileInfo['extension'],
						'bytes'             => $fileInfo['bytes'],
						'creation_user'	    => Auth::getCurrentUser()->id,
						'creation_time'     => time(),
						'modification_time' => time()
					));
					break;

				case 'update':

					// Se remueven los previews asociados al archivo
					$this->_removePreviews($fileInfo['path']);

					// Actualiza el registro
					$db->autoUpdate('fw_repo_file', $record->id, array(
						'filename' 	        => $params['filename'],
						'extension'         => $fileInfo['extension'],
						'bytes'             => $fileInfo['bytes'],
						'modification_time' => time()
					));
					break;
			}

			// Retorna la instancia del archivo
			return $this->get($params['uid']);

		} catch (FwException $e) {
			return false;
		}
	}

	/**
	 * Elimina un archivo de este bucket.
	 *
	 * @param string $uid Identificador único del archivo
	 *
	 * @return bool Confirmación de eliminación
	 */
	public function delete($uid) {

		// Conexión a la base de datos
		$db = Conf::getDbConnection('fw');

		try {

			// Reinicia un error previo seleccionado
			$this->error->release();

			// Confirma que exista una sesión
			if (!Auth::isLogged()) {
				throw new FwException('without-session', $this->error);
			}

			// Obtiene el registro del archivo
			$record = $db->get('fw_repo_file', [ 'bucket_id' => $this->_bucketRecord('id'), 'uid' => $uid ], 'id, path, bytes');
			if (!$record) {
				throw new FwException('file-no-registered', $this->error);
			}

			// Se remueven los previews asociados al archivo
			$this->_removePreviews($record->path);

			// Define el directorio del fichero del archivo
			$path = $this->path . '/' . $record->path;

			// Verifica que el fichero exista para eliminarlo
			if (file_exists($path)) {

				// Elimina el fichero
				if (unlink($path)) {

					// Actualiza el espacio del bucket, resta el tamaño del archivo
					$this->_updateSpace(-1 * $record->bytes);
				}
			}

			// Elimina el registro del archivo
			$db->autoDelete('fw_repo_file', $record->id);

			return true;

		} catch (FwException $e) {
			return false;
		}
	}

	/**
	 * Construye y registra un nuevo bucket.
	 *
	 * @return void
	 */
	private function _create() {

		// Conexión a la base de datos
		$db = Conf::getDbConnection('fw');

		try {

			// Genera el directorio del bucket
			while (true) {
				$path = strtolower(DataTools::createUID(16));
				if ($db->count('*', 'fw_repo_bucket', [ 'path', $path ]) == 0) {
					break;
				}
			}

			// Guarda el registro del bucket
			$db->autoInsert('fw_repo_bucket', array(
				'uid'   => $this->_uid,
				'path'  => $path,
				'bytes'	=> 0
			));

			// Almacena la información del bucket en self::$_buckets
			self::$_buckets[$this->_uid] = array(
				'id'   => $db->getLastInsertId(),
				'uid'  => $this->_uid,
				'path' => $path
			);

			// Verifica que el folder del bucket haya sido creado en el repositorio
			if (!is_dir($this->path)) {
				if (mkdir($this->path) == false) {
					throw new FwException('creating-folder', $this->error, [ 'uid' => $this->_uid ]);
				}
			}
			if (!is_dir($this->path . '/previews')) {
				if (mkdir($this->path . '/previews') == false) {
					throw new FwException('creating-folder', $this->error, [ 'uid' => $this->_uid ]);
				}
			}

		} catch (FwException $e) {
			Http::throwError($e);
		}
	}

	/**
	 * Remueve los previews de un archivo tipo imagen
	 *
	 * @param string $path Directorio relativo del archivo al cual se le removerán los previews
	 */
	private function _removePreviews($path) {

		// Busca todos los previews del archivo en el bucket
		$files = $this->path . '/previews/' . $path . '_*';
		$bytes = 0;
		foreach (glob($files) as $file) {

			// Verifica que el fichero del preview exista para eliminarlo
			if (file_exists($file)) {

				// Obtiene el tamaño del fichero
				$prevBytes = filesize($file);

				// Elimina el fichero
				if (unlink($file)) {
					$bytes = $bytes + $prevBytes;
				}
			}
		}

		// Actualiza el espacio del bucket, suma / resta los bytes de los previews
		$this->_updateSpace(-1 * $bytes);
	}

	/**
	 * Actualiza el espacio correspondiente al bucket.
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
			':id'    => ['int', $this->_bucketRecord('id')],
			':bytes' => ['int', $bytes]
		));
		$db->execute();
	}

	/**
	 * Devuelve un atributo del registro del bucket.
	 *
	 * @param string $colName Nombre de la columna o atributo
	 *
	 * @return mixed Retorna el valor del atributo solicitado
	 */
	private function _bucketRecord($colName) {
		if ((isset($this->_bucketRecord)) && (isset($this->_bucketRecord[$colName]))) {
			return $this->_bucketRecord[$colName];
		} else {
			return null;
		}
	}

	/**
	 * Propiedad $id.
	 *
	 * @return int ID del registro del bucket
	 */
	private function _get_id() {
		return $this->_bucketRecord('id');
	}

	/**
	 * Propiedad $uid.
	 *
	 * @return string|null Identificador único del bucket
	 */
	private function _get_uid() {
		return $this->_bucketRecord('uid');
	}

	/**
	 * Propiedad $path.
	 *
	 * @return string|null Directorio del bucket
	 */
	private function _get_path() {
		return Conf::getParam('repo_path') . '/' . $this->_bucketRecord('path');
	}
}
?>
