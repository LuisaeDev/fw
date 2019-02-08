<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use PDO;
use PDOException;
use Fw\Utils\ArrayTools;

/**
 * ORM para realizar mútiples operaciones a una base de datos.
 *
 * Esta clase abstrae la escritura de SQL, provee métodos y propiedades para realizar diversos tipos de consultas.
 * Para la ejecución de las consultas se auxilia de la clase PDOConnection para que sea esta la que establezca la conexión a la base de datos.
 *
 * @property-read string $dbname Nombre de la base de datos de la conexión
 * @property-read string $engine Motor de base de datos
 * @property-read string $host   Host de la conexión
 * @property-read int    $port   Puerto de la conexión
 * @property-read string $sql    Consulta SQL en proceso
 * @property-read array  $cols   Columnas agregadas a la consulta
 * @property-read array  $params Parámetros agregados a la consulta
 */
class QueryBuilder {

	/** @var PDOConnection Conexión a la base de datos */
	private $_PDOConnection = null;

	/** @var PDOSentence Sentencia PDO obtenida al preparar la consulta previo a su ejecución */
	private $_pdoStm = null;

	/** @var string Operación SQL a realizar, ej: SELECT, INSERT, UPDATE, DELETE ...  */
	private $_operation = null;

	/** @var string Tabla definida para la consulta */
	private $_table	= null;

	/** @var array Columnas definidas para la consulta */
	private $_cols = array();

	/** @var array Parámetros definidos para la consulta */
	private $_params = array();

	/** @var array Cláusulas FROM de la consulta */
	private $_from = array();

	/** @var array Grupos de condiciones where de la consulta */
	private $_where = array();

	/** @var string Statements GROUP BY de la consulta */
	private $_group = array();

	/** @var string Cláusula HAVING de la consulta */
	private $_having = '';

	/** @var array Keyword ORDER BY de la consulta */
	private $_order = array();

	/** @var array Cláusula LIMIT de la consulta */
	private $_limit = array();

	/** @var string Consulta a ejecutar cuando la instrucción es QUERY */
	private $_query	= '';

	/** @var array Filas obtenidas desde la instancia PDOStatement */
	private $_fetchAll = null;

	/** @var array|bool Fila obtenida (array asociativo) desde la instancia PDOStatement o false al no encontrarla */
	private $_fetch = null;

	/** @var array|bool Fila obtenida (object) desde la instancia PDOStatement o false al no encontrarla */
	private $_fetchObject = null;

	/** @var mixed Última llave primaria insertada después de ejecutar una consulta */
	private $_lastInsertId = null;

	/** @var array Almacena las estructuras obtenidas de tablas o vistas de diversas bases de datos */
	private static $_structures = array();

	/** @var array Almacena datos de conexiones a bases de datos */
	private static $_dataConnections = array();

	/**
	 * Constructor.
	 *
	 * @param array|PDOConnection $con Datos de conexión o una instancia PDOConnection
	 */
	public function __construct($con) {

		// Establece la conexión a la base de datos
		$this->connect($con);
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
	 * Método __clone.
	 */
	public function __clone() {
		$this->_reset();
		$this->_pdoStm       = null;
		$this->_fetchAll     = null;
		$this->_fetchObject  = null;
		$this->_fetch        = null;
		$this->_lastInsertId = null;
	}

	/**
	 * Establece la conexión de la instancia QueryBuilder.
	 *
	 * @param string|array|PDOConnection $con Instancia PDOConnection, array con datos de una conexión o string de una conexión especificada por el método self::setDataConnection()
	 *
	 * @return void
	 *
	 * @throws FwError
	 */
	public function connect($con) {

		// Reinicia la consulta
		$this->_reset();

		// Si el argumento $con es una instancia PDOConnection
		if ($con instanceof \Fw\PDOConnection) {

			// Establece la conexión
			$this->_PDOConnection = $con;

		} else {

			// Si la conexión se especificó como un string, verifica que los datos de conexión hayan sido definidos
			if (is_string($con)) {
				if (isset(self::$_dataConnections[$con])) {
					$con = self::$_dataConnections[$con];
				} else {
					throw new FwError('data-connection-no-defined', null, [ 'con' => $con ] );
				}
			}

			// Crea una nueva instancia PDOConnection
			$this->_PDOConnection = new PDOConnection($con);
		}
	}

	/**
	 * Inicia una transacción y desactiva el modo 'autocommit'.
	 *
	 * @return void
	 */
	public function beginTransaction() {
		$this->_PDOConnection->beginTransaction();
	}

	/**
	 * Confirma una transacción y activa el modo 'autocommit'.
	 *
	 * @return void
	 */
	public function commit() {
		$this->_PDOConnection->commit();
	}

	/**
	 * Revierte una transacción y activa el modo 'autocommit.
	 *
	 * @return void
	 */
	public function rollback() {
		$this->_PDOConnection->rollback();
	}

	/**
	 * Indica si hay una transacción en curso.
	 *
	 * @return bool
	 */
	public function inTransaction() {
		return $this->_PDOConnection->inTransaction();
	}

	/**
	 * Ejecuta una consulta automática de tipo SELECT para obtener un registro en particular.
	 *
	 * @param string       $table      Nombre de la tabla
	 * @param mixed        $quickWhere Condición rápida WHERE
	 * @param string|array $cols       Array de columnas a obtener
	 * @param string       $order      Keyword ORDER BY
	 *
	 * @return array|false Objeto o false cuando el registro no fue encontrado
	 */
	public function get($table, $quickWhere, $cols = '*', $order = null) {

		// Inicia una consulta de tipo SELECT
		$this->select($cols, $table);

		// Agrega una condición rápida WHERE
		$this->quickWhere($quickWhere);

		// Agrega el keyword ORDER BY
		if (isset($order)) {
			$this->order($order);
		}

		// Ejecuta la consulta y devuelve el objeto
		$this->limit(1);
		$this->execute();
		return $this->fetchObject();
	}

	/**
	 * Verifica si un registro existe.
	 *
	 * @param string $table      Nombre de la tabla o vista
	 * @param mixed  $quickWhere Condición rápida WHERE
	 *
	 * @return bool
	 */
	public function exists($table, $quickWhere) {
		$count = $this->count('*', $table, $quickWhere);
		if ($count > 0) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Ejecuta una consulta para llamar a la función count().
	 *
	 * @param string $col        Nombre de la columna
	 * @param string $table      Nombre de la tabla
	 * @param mixed  $quickWhere Condición rápida WHERE
	 *
	 * @return int Cantidad de registros contados
	 */
	public function count($col, $table, $quickWhere = null) {

		// Construye la consulta
		$this->select('count(' . $col. ')', $table);

		// Agrega una condición rápida WHERE
		if (isset($quickWhere)) {
			$this->quickWhere($quickWhere);
		}

		// Ejecuta la consulta y devuelve la cantidad de registros contados
		$this->execute();
		if ($this->fetch()) {
			return (int)$this->fetch('count(' . $col. ')');
		} else {
			return 0;
		}
	}

	/**
	 * Ejecuta una consulta automática de tipo SELECT.
	 *
	 * @param string       $table      Nombre de la tabla
	 * @param mixed        $quickWhere Condición rápida WHERE
	 * @param string|array $cols       Array de columnas a obtener
	 * @param string       $order      Keyword ORDER BY
	 * @param int|string   $limit      Inicio de la cláusula limit, puede ser un número o un string para especificar toda la cláusula, ej: '1 OFFSET 10'
	 *
	 * @return array Conjunto de filas obtenidas
	 */
	public function autoSelect($table, $quickWhere = null, $cols = '*', $order = null, $limit = null) {

		// Inicia una consulta SELECT
		$this->select($cols, $table);

		// Agrega una condición rápida WHERE
		if (isset($quickWhere)) {
			$this->quickWhere($quickWhere);
		}

		// Agrega el keyword ORDER BY
		if (isset($order)) {
			$this->order($order);
		}

		// Agrega la cláusula LIMIT
		if (isset($limit)) {
			$this->limit($limit);
		}

		// Ejecuta la consulta y devuelve el conjunto de filas
		$this->execute();
		return $this->fetchAll();
	}

	/**
	 * Ejecuta una consulta automática de tipo INSERT.
	 *
	 * @param string $table Nombre de la tabla
	 * @param array  $cols  Array de columnas
	 *
	 * @return void
	 */
	public function autoInsert($table, $cols) {

		// Inicia una consulta de tipo INSERT
		$this->insert($table, $cols);

		// Ejecuta la consulta
		$this->execute();
	}

	/**
	 * Ejecuta una consulta automática de tipo UPDATE.
	 *
	 * @param string $table     Nombre de la tabla
	 * @param mixed $quickWhere Condición rápida WHERE
	 * @param array $cols       Array de columnas
	 *
	 * @return void
	 */
	public function autoUpdate($table, $quickWhere, $cols) {

		// Inicia una consulta de tipo UPDATE
		$this->update($table, $cols);

		// Agrega una condición rápida WHERE
		if (isset($quickWhere)) {
			$this->quickWhere($quickWhere);
		}

		// Ejecuta la consulta
		$this->execute();
	}

	/**
	 * Ejecuta una consulta automática de tipo DELETE.
	 *
	 * @param string $table      Nombre de la tabla
	 * @param mixed  $quickWhere Condición rápida WHERE
	 *
	 * @return void
	 */
	public function autoDelete($table, $quickWhere = null) {

		// Inicia una consulta de tipo DELETE
		$this->delete($table);

		// Agrega una condición rápida WHERE
		if (isset($quickWhere)) {
			$this->quickWhere($quickWhere);
		}

		// Ejecuta la consulta
		$this->execute();
	}

	/**
	 * Inicia una consulta de tipo SELECT.
	 *
	 * @param string|array $cols  Array de columnas a obtener
	 * @param string|null  $table Nombre de la tabla
	 *
	 * @return void
	 */
	public function select($cols, $table = null) {

		// Reinicia la consulta
		$this->_reset();

		// Define la instrucción de la consulta
		$this->_operation = 'SELECT';

		// Almacena las columnas para la consulta
		if (is_array($cols)) {

			// Evalua cada posición del array, si una posición es asociativa, el valor de la posición se asignará como alias de la columna
			foreach ($cols as $i => $col) {
				if (is_string($i)) {
					$this->_cols[] = $i . ' AS ' . $col;
				} else {
					$this->_cols[] = $col;
				}
			}

		} else {

			$cols = explode(',', $cols);
			foreach ($cols as $value) {
				$this->_cols[] = trim($value);
			}
		}

		// Almacena la tabla y la cláusula from para la consulta
		if (is_string($table)) {
			$this->_table = $table;
			array_push($this->_from, array(
				'table' => $table,
				'join'  => null
			));
		}
	}

	/**
	 * Inicia una consulta de tipo SELECT DISTINCT.
	 *
	 * @param string|array $cols  Array de columnas a obtener
	 * @param string|null  $table Nombre de la tabla
	 *
	 * @return void
	 */
	public function selectDistinc($cols, $table = null) {

		// Realiza un select
		$this->select($cols, $table);

		// Define la instrucción de la consulta
		$this->_operation = 'SELECT DISTINCT';
	}

	/**
	 * Inicia una consulta de tipo INSERT.
	 *
	 * @param string $table Nombre de la tabla
	 * @param array  $cols  Array de columnas
	 *
	 * @return void
	 */
	public function insert($table, $cols = array()) {

		// Reinicia la consulta
		$this->_reset();

		// Define la instrucción de la consulta
		$this->_operation = 'INSERT';

		// Almacena la tabla para la consulta
		$this->_table = $table;

		// Almacena las columnas para la consulta
		foreach ($cols as $colName => $colValue) {
			$this->col($colName, $colValue);
		}
	}

	/**
	 * Inicia una consulta de tipo INSERT IGNORE.
	 *
	 * @param string $table Nombre de la tabla
	 * @param array  $cols  Array de columnas
	 *
	 * @return void
	 */
	public function insertIgnore($table, $cols = array()) {

		// Reinicia la consulta
		$this->_reset();

		// Define la instrucción de la consulta
		$this->_operation = 'INSERT IGNORE';

		// Almacena la tabla para la consulta
		$this->_table = $table;

		// Almacena las columnas para la consulta
		foreach ($cols as $colName => $colValue) {
			$this->col($colName, $colValue);
		}
	}

	/**
	 * Inicia una consulta de tipo REPLACE.
	 *
	 * @param string $table Nombre de la tabla
	 * @param array  $cols  Array de columnas
	 *
	 * @return void
	 */
	public function replace($table, $cols = array()) {

		// Reinicia la consulta
		$this->_reset();

		// Define la instrucción de la consulta
		$this->_operation = 'REPLACE';

		// Almacena la tabla para la consulta
		$this->_table = $table;

		// Almacena las columnas para la consulta
		foreach ($cols as $colName => $colValue) {
			$this->col($colName, $colValue);
		}
	}

	/**
	 * Inicia una consulta de tipo UPDATE.
	 *
	 * @param string $table Nombre de la tabla
	 * @param array  $cols  Array de columnas
	 *
	 * @return void
	 */
	public function update($table, array $cols = array()) {

		// Reinicia la consulta
		$this->_reset();

		// Define la instrucción de la consulta
		$this->_operation = 'UPDATE';

		// Almacena la tabla para la consulta
		$this->_table = $table;

		// Almacena las columnas para la consulta
		foreach ($cols as $colName => $colValue) {
			$this->col($colName, $colValue);
		}
	}

	/**
	 * Inicia una consulta de tipo DELETE.
	 *
	 * @param string $table Nombre de la tabla
	 *
	 * @return void
	 */
	public function delete($table) {

		// Reinicia la consulta
		$this->_reset();

		// Define la instrucción de la consulta
		$this->_operation = 'DELETE';

		// Almacena la tabla para la consulta
		$this->_table = $table;
	}

	/**
	 * Realiza un query a la base de datos.
	 *
	 * @param string $query  Consulta SQL a ejecutar
	 * @param array  $params Array de parámetros agregados
	 *
	 * @return void
	 */
	public function query($query, $params = null) {

		// Define la instrucción de la consulta
		$this->_operation = 'QUERY';

		// Almacena la consulta
		$this->_query = $query;

		// Define los parámetros
		if (is_array($params)) {
			$this->params($params);
		}
	}

	/**
	 * Agrega la primer cláusula FROM y sus respectivos JOIN a la consulta.
	 *
	 * @param string            $table Nombre de la tabla o vista
	 * @param string|array|null $join  Una o varias cláusulas JOIN
	 *
	 * @return void
	 */
	public function from($table, $join = null) {

		// Reinicia el array de cláusulas FROM
		$this->_from = array();

		// Agrega un FROM
		$this->addFrom($table, $join);
	}

	/**
	 * Agrega una cláusula FROM y sus respectivos JOIN a la consulta.
	 *
	 * @param string            $table Nombre de la tabla o vista
	 * @param string|array|null $join  Una o varias cláusulas JOIN, las opciones para especificarlas son las siguientes:
	 * 1. Un string con la definición de una cláusula JOIN, ej: "INNER JOIN table2 ON table1.id = table2.table1_id"
	 * 2. Un array con la definición de muchas cláusula JOIN, cada una de las cláusulas puede espeficicarse de dos maneras, como un string o un array de 3 posiciones con el formato siguiente, ej: ['INNER JOIN', 'table2', 'table1.id = table2.table1_id']
	 *
	 * @return void
	 */
	public function addFrom($table, $join = null) {
		array_push($this->_from, array(
			'table' => 	$table,
			'join' =>	$join
		));
	}

	/**
	 * Agrega una columna a la consulta.
	 *
	 * La columna a agregar puede definir su valor en 3 modalidades:
	 * 1. Un array de una posición, en donde la única posición definida es el valor a insetar directamente en la consulta SQL sin ser agregado como parámetro
	 * 2. Un array de dos posiciones, este genera un parámetro para agregar en la consulta, en donde la primer posición es el tipo de parámetro y la segunda es el valor para el parámetro
	 * 3. Un valor cualquiera diferente a un array el cual se asignará como valor para la columna, este genera un parámetro para agregar a la consulta, sin embargo se analiza la estructura de la tabla y la columna para determinar el tipo de parámetro
	 *
	 * @param string $name  Nombre de la columna
	 * @param mixed  $value Valor para la columna
	 *
	 * @return void
	 */
	public function col($name, $value) {

		// Validaciones cuando el valor es un array
		if (is_array($value)) {

			// Si se especificó solo una posición en el array, ese valor será asignado directamente a la columna sin ser preparado como parámetro
			if (count($value) == 1) {
				$this->_cols[$name] = $value[0];

			} else {

				// Se utiliza el nombre de la columna para definir el nombre del parámetro
				$paramName = ':_' . str_replace('`', '', $name);

				// Agrega la columna y el nombre del parámetro como valor
				$this->_cols[$name] = $paramName;

				// Agrega el parametro definido
				$this->param($paramName, $value[0], $value[1]);
			}

		// Cuando el valor no es un array, se realiza un analisis de la tabla / vista para reconocer el tipo de parámetro para la columna
		} else {

			// Se utiliza el nombre de la columna para definir el nombre del parámetro
			$paramName = ':_' . str_replace('`', '', $name);

			// Agrega la columna y el nombre del parámetro como valor
			$this->_cols[$name] = $paramName;

			/*
				Se determina el tipo de columna para definir el tipo de parámetro a agregar
			*/

			// Verifica si el valor es null
			if ($value === null) {
				$paramType = PDO::PARAM_NULL;

			} else {

				// Obtiene la estructura de la tabla
				$tableStruc = $this->describe($this->_table);

				// Verifica si se obtuvo la estructura de la tabla y la información de la columna
				if (($tableStruc) && (isset($tableStruc['col'][$name]))) {

					// Obtiene el tipo de parámetro a utilizar para esta columna
					$paramType = $tableStruc['col'][$name]['param-type'];

					// Si el valor para la columna es una instancia DateTime o FwDateTime y el tipo de columna es 'date', 'datetime' o 'timestamp, se transforma para su almacenamiento
					if (($value instanceof DateTime) || ($value instanceof FwDateTime)) {

						// De acuerdo al tipo de columna, se realiza la transformación del valor a string
						switch ($tableStruc['col'][$name]['type']) {
							case 'date':
								$value = $value->format('Y-m-d');
								break;

							case 'datetime':
								$value = $value->format('Y-m-d H:i:s');
								break;

							case 'timestamp':
							default:
								$value->setTimezone('UTC');
								$value = $value->format('Y-m-d H:i:s');
								break;
						}
					}

				} else {
					$paramType = PDO::PARAM_STR;
				}
			}

			// Agrega el parámetro correspondiente
			$this->param($paramName, $paramType, $value);
		}
	}

	/**
	 * Agrega una serie de columnas para la consulta.
	 *
	 * @param array $cols Array de columnas
	 *
	 * @return void
	 */
	public function cols(array $cols) {
		foreach ($cols as $colName => $colValue) {
			$this->col($colName, $colValue);
		}
	}

	/**
	 * Inicia un grupo de condiciones WHERE.
	 *
	 * @param string|array $condition Condición o condiciones a agregar
	 * @param array        $params    Array de parámetros agregados
	 *
	 * @return void
	 */
	public function where($condition, array $params = null) {
		$this->_where = array();
		$this->_where[] = $condition;

		// Define los parámetros
		if (is_array($params)) {
			$this->params($params);
		}
	}

	/**
	 * Agrega un operador AND y un grupo de condiciones WHERE.
	 *
	 * @param string|array $condition Condición o condiciones a agregar
	 * @param array        $params    Array de parámetros agregados
	 *
	 * @return void
	 */
	public function andWhere($condition, array $params = null) {
		$this->_where[] = 'AND';
		$this->_where[] = $condition;

		// Define los parámetros
		if (is_array($params)) {
			$this->params($params);
		}
	}

	/**
	 * Agrega un operador OR y un grupo de condiciones WHERE.
	 *
	 * @param string|array $condition Condición o condiciones a agregar
	 * @param array        $params    Array de parámetros agregados
	 *
	 * @return void
	 */
	public function orWhere($condition, array $params = null) {
		$this->_where[] = 'OR';
		$this->_where[] = $condition;

		// Define los parámetros
		if (is_array($params)) {
			$this->params($params);
		}
	}

	/**
	 * Agrega un operador XOR y un grupo de condiciones WHERE.
	 *
	 * @param string|array $condition Condición o condiciones a agregar
	 * @param array        $params    Array de parámetros agregados
	 *
	 * @return void
	 */
	public function xorWhere($condition, array $params = null) {
		$this->_where[] = 'XOR';
		$this->_where[] = $condition;

		// Define los parámetros
		if (is_array($params)) {
			$this->params($params);
		}
	}

	/**
	 * Agrega una condición o una serie de condiciones rápidas WHERE.
	 *
	 * Este método permite agregar una condición o una serie de condiciones WHERE de una manera rápida y eficiente.
	 * Se requiere que ya haya sido especificada la tabla o vista a través de una instrucción: SELECT, INSERT, UPDATE, DELETE.
	 * Este método principalmente es utilizado por los métodos automáticos: get(), count(), autoSelect(), autoUpdate(), autoInsert(), autoDelete()
	 * El tipo de condiciones a especificar son exclusivamente para hacer comparaciones rápidas con columnas de la tabla.
	 * Al especificar mútiples condiciones no se debe especificar compuertas lógicas entre ellas, internamente siempre serán separadas por un AND.
	 * Este método no requiere de especificar los parámetros agregados, todos son generados automáticamente en base a la estructura de la tabla o vista.
	 *
	 * @param mixed $conditions Condición o condiciones a agregar, las modalidades posibles para definir son las siguientes:
	 * 1. Un valor cualquiera diferente a un array, se buscará ese valor automáticamente en la columna con la llave principal de la tabla o primer columna de una vista.
	 * 2. Un array de dos posiciones, la primer posición debe ser el nombre de una columna, el segundo el valor por el cual se buscará, ej: [ 'name', 'Luis' ].
	 * 3. Un array asociativo, en donde los índices son los nombres de las columnas y sus valores representan el valor por el cual se buscarán.
	 *
	 * @return void
	 */
	public function quickWhere($conditions) {

		// Verifica que se haya especificado la tabla o vista
		if (isset($this->_table)) {

			// Obtiene la estructura de la tabla o vista
			$tableStruc = $this->describe($this->_table);

			// Variables para almacenar las condiciones where y los parámetros identificados
			$where = array();
			$params = array();

			// Si el parámetro $conditions es un array
			if (is_array($conditions)) {

				// Si el array es en formato ['col', 'value'], busca el registro por la columna especificada
				if ((count($conditions) == 2) && (isset($conditions[0]))) {

					// Datos de la columna y el parámetro a agregar
					$colName = $conditions[0];
					$paramName = ':__' . str_replace('`', '', $colName);
					$paramValue = $conditions[1];

					// Obtiene el tipo de parámetro para la columna especificada
					if ((isset($tableStruc)) && (isset($tableStruc['col'][$colName]))) {
						$paramType = $tableStruc['col'][$colName]['param-type'];
					} else {
						$paramType = 'str';
					}

					// Agrega la condición y el parámetro
					if ($paramValue === null) {
						$where[] = $colName . ' IS NULL';
					} else {
						$where[] = $colName . ' = ' . $paramName;
						$params[$paramName] = [ $paramType, $paramValue ];
					}

				// Si son múltiples condiciones, se espera que el array sea asociativo
				} else if (ArrayTools::isAssociative($conditions)) {

					// Evalua cada condición especificada
					foreach ($conditions as $colName => $paramValue) {

						// Obtiene el tipo de parámetro para la columna especificada
						if ((isset($tableStruc)) && (isset($tableStruc['col'][$colName]))) {
							$paramType = $tableStruc['col'][$colName]['param-type'];
						} else {
							$paramType = 'str';
						}

						// Si el valor fue expresado como un array de dos posiciones, estás indican: ['operador', 'valor']
						if (is_array($paramValue)) {
							$operator = $paramValue[0];
							$paramValue = $paramValue[1];

						// Si no se especificó el operador lógico, la condición WHERE será "="
						} else {
							$operator = '=';
						}

						// Nombre del parámetro a agregar
						$paramName = ':__' . str_replace('`', '', $colName);

						// Agrega la condición cuando el valor es null
						if ($paramValue == null) {
							if (($operator == '<>') || ($operator == '!=') || ($operator == 'IS NOT')) {
								$where[] = $colName . ' IS NOT NULL';
							} else {
								$where[] = $colName . ' IS NULL';
							}

						// Agrega la condición y el parámetro
						} else {
							$where[] = $colName . ' ' . $operator . ' ' . $paramName;
							$params[$paramName] = [ $paramType, $paramValue ];
						}
					}
				}

			// Si como condición se agregó solo un valor, se búsca el registro por su llave primaria
			} else {

				// Datos de la columna y el parámetro a agregar
				$colName = $tableStruc['pk']['name'];
				$paramName = ':__' . str_replace('`', '', $tableStruc['pk']['name']);
				$paramType = $tableStruc['pk']['param-type'];
				$paramValue = $conditions;

				// Agrega la condición y el parámetro
				$where[] =  $colName . ' = ' . $paramName;
				$params[$paramName] = [ $paramType, $paramValue ];
			}

			// Agrega las condiciones where y los parámetros
			foreach ($where as $condition) {
				$this->andWhere($condition);
			}
			$this->params($params);
		}
	}

	/**
	 * Agrega un parámetro a la consulta.
	 *
	 * @param string     $name  Nombre del parámetro
	 * @param string|int $type  Tipo del parámetro, se permite valores {'str', 'null', 'bool', 'int'}
	 * @param mixed      $value Valor a asignar al parámetro
	 *
	 * @return void
	 */
	public function param($name, $type, $value) {
		$this->_params[$name] = array(
			'type' 	=> $type,
			'value' => $value
		);
	}

	/**
	 * Agrega una serie de parámetros para la consulta.
	 *
	 * @param array $params Array de parámetros agregados
	 *
	 * @return void
	 */
	public function params(array $params = null) {
		foreach ($params as $name => $value) {
			$this->param($name, $value[0], $value[1]);
		}
	}

	/**
	 * Agrega el primer statement GROUP BY
	 *
	 * @param string $stm Statement GROUP BY
	 *
	 * @return void
	 */
	public function group($stm) {
		$this->_group = array();
		array_push($this->_group, $stm);
	}

	/**
	 * Agrega un nuevo statement GROUP BY
	 *
	 * @param  string $stm Statement GROUP BY
	 *
	 * @return void
	 */
	public function addGroup($stm) {
		array_push($this->_group, $stm);
	}

	/**
	 * Agrega la cláusula HAVING.
	 *
	 * @param string $clause Cláusula HAVING
	 *
	 * @return void
	 */
	public function having($clause) {
		$this->_having = $clause;
	}

	/**
	 * Agrega el primer keyword ORDER BY.
	 *
	 * @param string $keyword Keyword ORDER BY
	 *
	 * @return void
	 */
	public function order($keyword) {
		$this->_order = array();
		array_push($this->_order, $keyword);
	}

	/**
	 * Agrega un nuevo keyword ORDER BY.
	 *
	 * @param string $keyword Keyword ORDER BY
	 *
	 * @return void
	 */
	public function addOrder($keyword) {
		array_push($this->_order, $keyword);
	}

	/**
	 * Agrega una cláusula LIMIT a la consulta.
	 *
	 * @param int|string $start Inicio de la cláusula limit, puede ser un número o un string para especificar toda la cláusula, ej: '1 OFFSET 10'
	 * @param int|string $end   Fin del limit
	 *
	 * @return void
	 */
	public function limit($start, $end = null) {
		$this->_limit['start'] = $start;
		if (isset($end)) {
			$this->_limit['end'] = $end;
		}
	}

	/**
	 * Prepara y ejecuta la consulta.
	 *
	 * @return void
	 *
	 * @throws FwException_QueryBuilder
	 */
	public function execute() {

		// Remueve los resultados previamente almacenados
		$this->_fetchAll 	 = null;
		$this->_fetchObject  = null;
		$this->_fetch 		 = null;
		$this->_lastInsertId = null;

		// Obtiene la consulta
		$sql = $this->_getSQL() . ';';

		// Obtiene la sentencia preparada
		$this->_pdoStm = $this->_PDOConnection->prepare($sql, $this->_params);

		// Ejecuta la sentencia
		$result = $this->_pdoStm->execute();

		// Verifica si se produjo un error
		$lastError = $this->_PDOConnection->getLastError();
		if (isset($lastError)) {
			throw new FwException_QueryBuilder('pdo-exception', null, [ 'code' => $lastError['code'], 'message' => $lastError['message'] ]);
		}

		// Obtiene el valor de la última llave primaria insertada
		$this->_lastInsertId = $this->_PDOConnection->getLastInsertId();
	}


	/**
	 * Obtiene una fila como objeto o especificamente el valor de una de sus columnas.
	 *
	 * @param string|null $col Nombre de una columna de la fila
	 *
	 * @return mixed Fila como objeto o false cuando no fue encontrada
	 */
	public function fetchObject($col = null) {

		// Verifica si la fila no ha sido almacenada
		if (!isset($this->_fetchObject)) {

			// Obtiene la fila desde la instancia PDOStatement
			if ((gettype($this->_pdoStm) == 'object') && (method_exists($this->_pdoStm, 'fetchObject'))) {
				$this->_fetchObject = $this->_pdoStm->fetchObject();
			} else {
				$this->_fetchObject = false;
			}
		}

		// Devuelve la fila o el valor de la columna solicitada
		if (!isset($col)) {
			return $this->_fetchObject;
		} else if ((isset($col)) && (is_array($this->_fetchObject)) && (isset($this->_fetchObject->{$col}))) {
			return $this->_fetchObject->{$col};
		} else {
			return null;
		}
	}

	/**
	 * Obtiene una fila como array asociativo o especificamente el valor de una de sus columnas.
	 *
	 * @param string|null $col Nombre de una columna de la fila
	 *
	 * @return mixed Fila como array asociativo o false cuando no fue encontrada
	 */
	public function fetch($col = null) {

		// Verifica si la fila no ha sido almacenada
		if (!isset($this->_fetch)) {

			// Obtiene la fila desde la instancia PDOStatement
			if ((gettype($this->_pdoStm) == 'object') && (method_exists($this->_pdoStm, 'fetch'))) {
				$this->_fetch = $this->_pdoStm->fetch(PDO::FETCH_ASSOC);
			} else {
				$this->_fetch = false;
			}
		}

		// Devuelve la fila o el valor de la columna solicitada
		if (!isset($col)) {
			return $this->_fetch;
		} else if ((isset($col)) && (is_array($this->_fetch)) && (isset($this->_fetch[$col]))) {
			return $this->_fetch[$col];
		} else {
			return null;
		}
	}

	/**
	 * Obtiene un array con todas las filas del conjunto de resultados.
	 *
	 * @param int|null    $i   Índice de la fila a obtener
	 * @param string|null $col Nombre de una columna de la fila
	 *
	 * @return mixed Conjunto de filas, una fila en especifico o una columna en particular
	 */
	public function fetchAll($i = null, $col = null) {

		// Verifica si las filas no han sido almacenadas
		if (!isset($this->_fetchAll)) {

			// Obtiene las filas desde la instancia PDOStatement
			if ((gettype($this->_pdoStm) == 'object') && (method_exists($this->_pdoStm, 'fetchAll'))) {
				$this->_fetchAll = $this->_pdoStm->fetchAll(PDO::FETCH_ASSOC);
			} else {
				$this->_fetchAll = array();
			}
		}

		// Devuelve las filas, una fila en particular o una columna solicitada
		if (isset($i)) {
			if (isset($this->_fetchAll[$i])) {
				if (!isset($col)) {
					return $this->_fetchAll[$i];
				} else if ((isset($col)) && (isset($this->_fetchAll[$i][$col]))) {
					return $this->_fetchAll[$i][$col];
				} else {
					return null;
				}
			} else {
				return null;
			}
		} else {
			return $this->_fetchAll;
		}
	}

	/**
	 * Devuelve el valor de la llave primaria del último registro insertado.
	 *
	 * @return int
	 */
	public function getLastInsertId() {
		return $this->_lastInsertId;
	}

	/**
	 * Devuelve la cantidad de filas afectadas.
	 *
	 * @return int
	 */
	public function rowCount() {
		if (method_exists($this->_pdoStm, 'rowCount')) {
			return $this->_pdoStm->rowCount();
		} else {
			return 0;
		}
	}

	/**
	 * Devuelve la estructura de una tabla o vista.
	 *
	 * @param string $table Nombre de la tabla o vista
	 *
	 * @return array|bool Array de la estructura o false cuando no puedo obtenerse
	 *
	 * @throws FwException_QueryBuilder
	 */
	public function describe($table) {

		// Cadena identificadora de la tabla
		$uid = md5($this->_PDOConnection->host . '|' . $this->_PDOConnection->port . '|' . $this->_PDOConnection->engine . '|' . $this->_PDOConnection->dbname . '|' . $table);

		// Devuelve la estructura de la tabla si ya fue obtenida previamente
		if (isset(self::$_structures[$uid])) {
			return self::$_structures[$uid];
		}

		// Obtiene la estructura de la tabla según el engine
		switch ($this->_PDOConnection->engine) {
			case 'mysql':
				$sql = 'describe ' . $table;
				break;
		}

		try {

			// Prepara y obtiene la sentencia preparada
			$statement = $this->_PDOConnection->prepare($sql);

			// Ejecuta la sentencia
			$statement->execute();

			// Obtiene la estructura de la tabla
			$structure = $statement->fetchAll(PDO::FETCH_ASSOC);

		} catch (PDOException $e) {
			throw new FwException_QueryBuilder('pdo-exception', null, [ 'code' => $e->getCode(), 'message' => $e->getMessage() ]);
		}

		// Devuelve false si no se pudo obtener la estructura
		if ((count($structure) == 0) || ($structure == null)) {
			return false;
		}

		// Array para almacenar la estructura de la tabla
		$_table = array(
			'pk'  => null,
			'col' => array()
		);

		// Identifica cada columna para definir la estructura de la tabla
		foreach ($structure as $col) {

			// Obtiene el nombre de la columna
			switch ($this->_PDOConnection->engine) {
				case 'sqlite':
					$colName = $col['name'];
					$colType = $col['type'];
					break;

				case 'mysql':
					$colName = $col['Field'];
					$colType = $col['Type'];
					break;
			}

			// Se adecuan los tipos de columnas obtenidos ya que su definición puede tener un formato diferente, ej: 'varchar(100)'
			preg_match_all('/^[a-zA-Z]{0,}/', $colType, $matches);
			$colType = $matches[0][0];

			// Define el tipo de parámetro para las sentencias preparadas correspondiente al tipo de columna
			switch (strtolower($colType)) {
				case 'boolean':
					$paramType = PDO::PARAM_BOOL;
					break;

				case 'int':
				case 'integer':
				case 'tinyint':
				case 'bigint':
				case 'smalint':
					$paramType = PDO::PARAM_INT;
					break;

				case 'numeric':
				case 'real':
				case 'decimal':
				case 'float':
				case 'double':
				case 'date':
				case 'datetime':
				case 'time':
				case 'timestamp':
					$paramType = PDO::PARAM_STR;
					break;

				case 'char':
				case 'nchar':
				case 'character':
				case 'varchar':
				case 'nvarchar':
				case 'test':

				default:
					$paramType = PDO::PARAM_STR;
					break;
			};

			// Define la columna
			$_table['col'][$colName] = array(
				'name'	 		=> $colName,
				'type'		 	=> $colType,
				'param-type' 	=> $paramType,
			);

			// Define si es la llave primaria
			switch ($this->_PDOConnection->engine) {
				case 'sqlite':
					if ($col['pk'] == 1) {
						$_table['pk'] = array(
							'name' 	 		=> $colName,
							'type'		 	=> $colType,
							'param-type' 	=> $paramType,
						);
					}
					break;

				case 'mysql':
					if ($col['Key'] == 'PRI') {
						$_table['pk'] = array(
							'name' 	 		=> $colName,
							'type'			=> $colType,
							'param-type' 	=> $paramType,
						);
					}
					break;
			}
		}

		// Si no se encontró ninguna llave primaria, se define la primer columna como llave primaria
		if (!isset($_table['pk'])) {
			$_table['pk'] = current($_table['col']);
		}

		// Almacena la estructura de la tabla en la variable estática $_structures
		self::$_structures[$uid] = $_table;

		// Devuelve la estructura de la tabla
		return $_table;
	}

	/**
	 * Devuelve el string SQL con las columnas especificadas.
	 *
	 * @return string
	 */
	private function _getCols() {

		// El formato del string a devolver depende del tipo de instrucción que se está procesando
		switch ($this->_operation) {
			case 'SELECT':
			case 'SELECT DISTINCT':
				return implode(', ', $this->_cols);
				break;

			case 'INSERT':
			case 'INSERT IGNORE':
			case 'REPLACE':
				$cols = array();
				$values = array();
				foreach ($this->_cols as $i => $value) {
					array_push($cols, $i);
					array_push($values, $value);
				}
				return '(' . implode(', ', $cols) . ') VALUES (' . implode(', ', $values) . ')';
				break;

			case 'UPDATE':
				$str = '';
				foreach ($this->_cols as $i => $value) {
					if (strlen($str) > 0) {
						$str .= ', ';
					}
					$str .= $i . ' = ' . $value;
				}
				return $str;
				break;
		}
	}

	/**
	 * Devuelve el string SQL de FROM y los JOIN especificados.
	 *
	 * @return string
	 */
	private function _getFrom() {
		$str = '';
		foreach ($this->_from as $from) {

			// Verifica si se está iniciando el statement FROM
			if (strlen($str) == 0) {
				$str .= ' FROM';
			} else {
				$str .= ',';
			}

			// Define la tabla
			$str .= ' ' . $from['table'];

			// Define si hay una o más cláusulas JOIN
			if (is_array($from['join'])) {
				foreach ($from['join'] as $join) {

					// Cada cláusula JOIN puede ser un solo string o un array de 3 posiciones, ej: ['INNER JOIN', 'table2', 'table1.id = table2.id']
					if (is_array($join)) {
						$str .= ' ' . $join[0] . ' ' . $join[1] . ' ON (' . $join[2] . ')';
					} else {
						$str .= ' ' . $join;
					}
				}
			} else if (isset($from['join'])) {
				$str .= ' ' . $from['join'];
			}
		}
		return $str;
	}

	/**
	 * Devuelve el string SQL de las condiciones where.
	 *
	 * @return string
	 */
	private function _getWhere() {
		if (count($this->_where) > 0) {
			return ' WHERE (' . $this->_getWhereCond($this->_where) . ')';
		} else {
			return '';
		}
	}

	/**
	 * Resuelve una condición o un nivel de condiciones where.
	 *
	 * Este método es recursivo cuando es un array anidado
	 *
	 * @param array|string $where Condición o condiciones SQL where
	 *
	 * @return string SQL con las condiciones where del nivel procesado
	 */
	private function _getWhereCond($where) {

		// Si la condición es única
		if (is_string($where)) {
			return '(' . $where . ')';

		// Si son múltiples condiciones
		} else if (is_array($where)) {

			// Resuelve cada condición del array recibido
			$count = 0;
			$str = '';
			foreach ($where as $condition) {

				// Si la condición es un string
				if (is_string($condition)) {

					// Verifica si es un operador o una condición
					switch (strtolower($condition)) {
						case 'and':
						case '&&':
						case 'or':
						case '||':
						case 'xor':

							// Agrega el operador si no es la primer condición del nivel
							if ($count > 0) {
								$str .= ' ' . $condition . ' ';
							}
							break;

						default:
							$str .= '(' . $condition . ')';
					}

				// Si la condición es otro nivel de condiciones
				} else if (is_array($condition)) {

					// Llama a esta misma función para resolver el nivel
					$str .= '(' . $this->_getWhereCond($condition) . ')';
				}
				$count++;
			}
			return $str;
		}
	}

	/**
	 * Devuelve el string SQL de los statements GROUP BY.
	 *
	 * @return string
	 */
	private function _getGroup() {
		if (count($this->_group) > 0) {
			return ' GROUP BY ' . implode(', ', $this->_group);
		} else {
			return '';
		}
	}

	/**
	 * Devuelve el string SQL de la cláusula HAVING.
	 *
	 * @return string
	 */
	private function _getHaving() {
		if (strlen($this->_having) > 0) {
			return ' HAVING ' . $this->_having;
		} else {
			return '';
		}
	}

	/**
	 * Devuelve el string SQL de los keywords ORDER BY.
	 *
	 * @return string
	 */
	private function _getOrder() {
		if (count($this->_order) > 0) {
			return ' ORDER BY ' . implode(', ', $this->_order);
		} else {
			return '';
		}
	}

	/**
	 * Devuelve el string SQL de la cláusula LIMIT.
	 *
	 * @return string
	 */
	private function _getLimit() {
		$str = '';
		if (isset($this->_limit['start'])) {
			$str .= ' LIMIT ' . $this->_limit['start'];
			if (isset($this->_limit['end'])) {
				$str .= ', ' . $this->_limit['end'];
			}
		}
		return $str;
	}

	/**
	 * Construye y devuelve la consulta SQL.
	 *
	 * @return string
	 */
	private function _getSQL() {
		$str = '';

		// Inicia la construccion de la consulta
		switch ($this->_operation) {
			case 'SELECT':
			case 'SELECT DISTINCT':
				$str = $this->_operation . ' ';
				$str .= $this->_getCols();
				$str .= $this->_getFrom();
				$str .= $this->_getWhere();
				$str .= $this->_getGroup();
				$str .= $this->_getHaving();
				$str .= $this->_getOrder();
				$str .= $this->_getLimit();
				break;

			case 'INSERT':
				$str = 'INSERT INTO ' . $this->_table . ' '. $this->_getCols();
				break;

			case 'INSERT IGNORE':
				$str = 'INSERT IGNORE INTO ' . $this->_table . ' '. $this->_getCols();
				break;

			case 'REPLACE':
				$str = 'REPLACE INTO ' . $this->_table . ' '. $this->_getCols();
				break;

			case 'UPDATE':
				$str = 'UPDATE ' . $this->_table . ' SET '. $this->_getCols();
				$str .= $this->_getWhere();
				break;

			case 'DELETE':
				$str = 'DELETE FROM ' . $this->_table;
				$str .= $this->_getWhere();
				break;

			case 'QUERY':
				$str = $this->_query;
				break;

			default:
				return '';
				break;
		}
		return $str;
	}

	/**
	 * Reinicia la consulta actual.
	 *
	 * @return void
	 */
	private function _reset() {
		$this->_operation = null;
		$this->_table 		= null;
		$this->_cols 		= array();
		$this->_params 		= array();
		$this->_from 		= array();
		$this->_where 		= array();
		$this->_group 		= array();
		$this->_having 		= '';
		$this->_order 		= array();
		$this->_limit 		= array();
		$this->_query 		= '';
	}

	/**
	 * Propiedad $dbname.
	 *
	 * @return string Nombre de la base de datos de la conexión
	 */
	private function _get_dbname() {
		return $this->_PDOConnection->dbname;
	}

	/**
	 * Propiedad $engine.
	 *
	 * @return string Motor de base de datos
	 */
	private function _get_engine() {
		return $this->_PDOConnection->engine;
	}

	/**
	 * Propiedad $host.
	 *
	 * @return string Host de la conexión
	 */
	private function _get_host() {
		return $this->_PDOConnection->host;
	}

	/**
	 * Propiedad $port.
	 *
	 * @return int Puerto de la conexión
	 */
	private function _get_port() {
		return $this->_PDOConnection->port;
	}

	/**
	 * Propiedad $sql.
	 *
	 * @return string Consulta SQL que está en proceso
	 */
	private function _get_sql() {
		return $this->_getSQL();
	}

	/**
	 * Propiedad $cols.
	 *
	 * @return array Conjunto de columnas agregadas a la consulta en proceso
	 */
	private function _get_cols() {
		return $this->_cols;
	}

	/**
	 * Propiedad $params.
	 *
	 * @return array Conjunto de parametros agregados a la consulta en proceso
	 */
	private function _get_params() {
		return $this->_params;
	}

	/**
	 * Registra los datos de conexión a una base de datos.
	 *
	 * @param string $name Nombre de los datos de conexión
	 * @param mixed  $data Datos de la conexión
	 *
	 * @return void
	 */
	public static function setDataConnection($name, $data) {
		self::$_dataConnections[$name] = $data;
	}
}
?>
