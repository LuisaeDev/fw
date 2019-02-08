<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use PDO;
use PDOException;

/**
 * Establece una conexión a una base de datos a través de PDO.
 *
 * Esta clase sirve de intermediaria entre la clase QueryBuilder para comunicarse directamente con la base de datos a través de PDO.
 *
 * @property-read string $dbname Nombre de la base de datos de la conexión
 * @property-read string $engine Motor de base de datos
 * @property-read string $host Host de la conexión
 * @property-read int $port Puerto de la conexión
 */
class PDOConnection {

	/** @var PDO Instancia PDO */
	private $_pdo;

	/** @var array Almacena los datos de la conexión */
	private $_connection = array(
		'dbname' 	=> null,
		'engine' 	=> null,
		'host' 		=> null,
		'port' 		=> null
	);

	/** @var bool Establece si hay una transacción en curso */
	private $_transaction = false;

	/**
	 * Constructor.
	 *
	 * @param array $params Parámetros para establecer la conexión
	 *
	 * @throws FwException_PDOCOnnection
	 */
	public function __construct($params) {

		// Verifica los parámetros pasados
		if (!isset($params['dbname'])) {
			throw new FwException_PDOCOnnection('no-dbname');
		}
		if (!isset($params['user'])) {
			$params['user'] = '';
		}
		if (!isset($params['pass'])) {
			$params['pass'] = '';
		}
		if (!isset($params['engine'])) {
			$params['engine'] = 'mysql';
		}
		if (!isset($params['host'])) {
			$params['host'] = 'localhost';
		}
		if (!isset($params['port'])) {
			$params['port'] = '3306';
		}

		try {

			// Establece la conexión PDO con la base de datos
			switch ($params['engine']) {
				case 'mysql':
					$this->_pdo = new PDO('mysql:host=' . $params['host'] . ';port=' . $params['port'] . ';dbname=' . $params['dbname'] . ';charset=utf8', $params['user'], $params['pass']);
					break;

				default:
					throw new FwException_PDOCOnnection('engine-no-supported', null, [ 'engine' => $params['engine'] ]);
					break;
			}

			// Habilita el reporte de errores y excepciones para PDO
			$this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			// Guarda las propiedades de la instancia
			$this->_connection['dbname'] = $params['dbname'];
			$this->_connection['engine'] = $params['engine'];
			$this->_connection['host']   = $params['host'];
			$this->_connection['port']   = $params['port'];

		} catch (PDOException $e) {
			throw new FwException_PDOCOnnection('pdo-exception', null, [ 'code' => $e->getCode(), 'message' => $e->getMessage() ]);
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
	 * Inicia una transacción.
	 *
	 * Desactiva el modo 'autocommit'
	 *
	 * @return void
	 */
	public function beginTransaction() {
		if ($this->_transaction == false) {
			$this->_transaction = true;
			$this->_pdo->beginTransaction();
		}
	}

	/**
	 * Confirma una transacción.
	 *
	 * Activa el modo 'autocommit'
	 *
	 * @return void
	 */
	public function commit() {
		if ($this->_transaction == true) {
			$this->_transaction = false;
			$this->_pdo->commit();
		}
	}

	/**
	 * Revierte una transacción.
	 *
	 * Revierte la transacción y activa el modo 'autocommit'
	 *
	 * @return void
	 */
	public function rollback() {
		if ($this->_transaction == true) {
			$this->_transaction = false;
			$this->_pdo->rollBack();
		}
	}

	/**
	 * Indica si hay una transacción en curso.
	 *
	 * @return bool
	 */
	public function inTransaction() {
		return $this->_transaction;
	}

	/**
	 * Prepara y devuelve una sentencia (PDOStatement).
	 *
	 * @return PDOStatement
	 *
	 * @throws FwException_PDOCOnnection
	 */
	public function prepare($sql, $params = array()) {

		try {

			// Obtiene el objeto PDOStatement
			$pdoStm = $this->_pdo->prepare($sql);

			// Agrega los parámetros
			foreach ($params as $i => $param) {

				// Si el parámetro es DateTime se establece el formato correcto de almacenamiento
				if ($param['value'] instanceof DateTime) {
					$param['value'] = $param['value']->format('Y-m-d H:i:s');
					$param['type'] = 'str';
				}

				// Identifica el tipo de parámetro
				switch ($param['type']) {
					case 'null':
						$param['type'] = PDO::PARAM_NULL;
						break;

					case 'bool':
						$param['type'] = PDO::PARAM_BOOL;
						break;

					case 'int':
						$param['type'] = PDO::PARAM_INT;
						break;

					case 'str':
						$param['type'] = PDO::PARAM_STR;
						break;
				}

				// Agrega el parámetro
				$pdoStm->bindParam($i, $param['value'], $param['type']);
			}

			// Devuelve el objeto PDOStatement
			return $pdoStm;

		} catch (PDOException $e) {
			throw new FwException_PDOCOnnection('pdo-exception', null, [ 'code' => $e->getCode(), 'message' => $e->getMessage() ]);
		}
	}

	/**
	 * Devuelve el valor de la última llave primaria insertada.
	 *
	 * @return mixed
	 */
	public function getLastInsertId() {
		if (method_exists($this->_pdo, 'lastInsertId')) {
			return $this->_pdo->lastInsertId();
		} else {
			return null;
		}
	}

	/**
	 * Devuelve el último error producido en la instancia PDO.
	 *
	 * @return array|null
	 */
	public function getLastError() {
		if (method_exists($this->_pdo, 'errorInfo')) {

			// Obtiene el error de PDO
			$errorInfo = $this->_pdo->errorInfo();
			if (isset($errorInfo[1])) {
				return array(
					'code'    => $errorInfo[1],
					'message' => $errorInfo[2]
				);
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

	/**
	 * Propiedad $dbname.
	 *
	 * @return string Nombre de la base de datos de la conexión
	 */
	private function _get_dbname() {
		return $this->_connection['dbname'];
	}

	/**
	 * Propiedad $engine.
	 *
	 * @return string Motor de base de datos
	 */
	private function _get_engine() {
		return $this->_connection['engine'];
	}

	/**
	 * Propiedad $host.
	 *
	 * @return string Host de la conexión
	 */
	private function _get_host() {
		return $this->_connection['host'];
	}

	/**
	 * Propiedad $port.
	 *
	 * @return int Puerto de la conexión
	 */
	private function _get_port() {
		return $this->_connection['port'];
	}
}
?>
