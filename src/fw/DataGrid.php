<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

/**
 * Clase para hacer consultas select con opciones de paginación y filtros a una tabla o vista.
 *
 * @property-read QueryBuilder $db   Instancia QueryBuilder para manejar internamente en esta instancia
 * @property-read string       $from Tabla/Vista con la que trabajará esta instancia
 */
class DataGrid {

	/** @var QueryBuilder Constructor SQL manejado inernamente por la instancia */
	private $_db;

	/** @var strng Tabla/Vista con la que está trabajando esta instancia */
	private $_from;

	/**
	 * Constructor de la clase.
	 *
	 * @param QueryBuilder $db   Instancia QueryBuilder para manejar internamente en esta instancia
	 * @param string       $from Tabla/Vista con la que trabajará esta instancia
	 */
	public function __construct(QueryBuilder $db, $from) {

		// Almacena por regerencia la instancia QueryBuilder
		$this->_db = $db;

		// Define la tabla/vista con la que trabajará esta instancia
		$this->_from = $from;
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
	 * Método mágico __set.
	 */
	public function __set($property, $value) {
		if (is_callable(array($this, $method = '_set_' . $property))) {
			return $this->$method($value);
		} else {
			return null;
		}
	}

	/**
	 * Obtiene un set de registros o un registro del DataGrid.
	 *
	 * @param array $query Set de opciones a definir para realizar el query
	 *
	 * @return array Devuelve los registros y paginación obtenida
	 */
	public function select($query = array()) {

		// Variables
		$records = array();
		$pagination = array();
		$totalRecords = 0;
		$offset = 0;

		// Clona la instancia QueryBuilder solo para usar durante la ejecución de este método
		$db = clone $this->_db;

		// Verifica que estén especificadas las columnas
		if (!isset($query['cols'])) {
			$query['cols'] = '*';
		}

		// Verifica el parámetro search sea un array [ 'col' => '', 'value' => '' ]
		if ((isset($query['search'])) && (is_array($query['search'])) && (strlen($query['search']['value']) == 0)) {
			$query['search'] = null;
		}

		// Verifica el parámetro where
		if ((isset($query['where'])) && (count($query['where']) == 0)) {
			$query['where'] = null;
		}

		// Verifica el parámetro params
		if ((isset($query['params'])) && (count($query['params']) == 0)) {
			$query['params'] = null;
		}

		// Si la solicitud requiere paginación, se obtienen las variables necesarias para obtener los registros de la página correspondiente y algunas variables para obtener la paginación misma
		if (isset($query['pagination'])) {

			// Inicia la consulta
			$db->select('count(*)', $this->_from);

			// Condiciones where
			if (isset($query['where'])) {
				$db->where($query['where']);
			}

			// Parámetros
			if (isset($query['params'])) {
				$db->params($query['params']);
			}

			// Agrega la condición de busqueda
			if (isset($query['search'])) {

				// Si solo hay una posición en el array de 'col', lo convierte a string
				if ((is_array($query['search']['col'])) && (count($query['search']['col']) == 1)) {
					$query['search']['col'] = $query['search']['col'][0];
				}

				// Si se especificaron múltiples columnas para search se concatenan por OR cada condición por columna
				if (is_array($query['search']['col'])) {
					foreach ($query['search']['col'] as $i => $col) {
						if ($i == 0) {
							$db->andWhere('(' . $col . ' LIKE :search');
						} else if ($i == (count($query['search']['col']) - 1)) {
							$db->orWhere($col . ' LIKE :search' . ')');
						} else {
							$db->orWhere($col . ' LIKE :search');
						}
					}
				} else {
					$db->andWhere($query['search']['col'] . ' LIKE :search');
				}

				// Se borran espacios en blanco al inicio y final y se agregan comodínes % entre espacios en blanco
				$query['search']['value'] = trim((string)$query['search']['value']);
				$query['search']['value'] = '%' . str_replace(' ', '%', $query['search']['value']) . '%';

				// Se agrega el parámetro para :search
				$db->param(':search', 'str', $query['search']['value']);
			}

			// Ejecuta la consulta
			$db->execute();

			// Obtiene el total de registros
			$totalRecords = (int)$db->fetch('count(*)');
			$query['pagination']['records'] = $totalRecords;

			// Calcula el total de páginas disponibles
			$query['pagination']['pages'] = (int)ceil($totalRecords / $query['pagination']['rows']);

			// Confirma que la página a solicitar este en el rango de registros disponibles
			if (($query['pagination']['page'] <= 0) || ($query['pagination']['pages'] == 0)) {
				$query['pagination']['page'] = 1;
			} else if ($query['pagination']['page'] > $query['pagination']['pages']) {
				$query['pagination']['page'] = $query['pagination']['pages'];
			}

			// Determina desde que registro se comenzará la consulta
			$offset = ($query['pagination']['page'] - 1) * $query['pagination']['rows'];
		}

		// Inicia la consulta
		$db->select($query['cols']);

		// Agrega el FROM de la tabla
		$db->from($this->_from);

		// Agrega las condiciones where
		if (isset($query['where'])) {
			$db->where($query['where']);
		}

		// Agrega los parámetros
		if (isset($query['params'])) {
			$db->params($query['params']);
		}

		// Agrega la condición search
		if (isset($query['search'])) {

			// Si solo hay una posición en el array de 'col', lo convierte a string
			if ((is_array($query['search']['col'])) && (count($query['search']['col']) == 1)) {
				$query['search']['col'] = $query['search']['col'][0];
			}

			// Si se especificaron múltiples columnas para search se concatenan por OR cada condición por columna
			if (is_array($query['search']['col'])) {
				foreach ($query['search']['col'] as $i => $col) {
					if ($i == 0) {
						$db->andWhere('(' . $col . ' LIKE :search');
					} else if ($i == (count($query['search']['col']) - 1)) {
						$db->orWhere($col . ' LIKE :search' . ')');
					} else {
						$db->orWhere($col . ' LIKE :search');
					}
				}
			} else {
				$db->andWhere($query['search']['col'] . ' LIKE :search');
			}

			// Se borran espacios en blanco al inicio y final y se agregan comodínes % entre espacios en blanco
			$query['search']['value'] = trim((string)$query['search']['value']);
			$query['search']['value'] = '%' . str_replace(' ', '%', $query['search']['value']) . '%';

			// Se agrega el parámetro para :search
			$db->param(':search', 'str', $query['search']['value']);
		}

		// Establece el orden
		if (isset($query['order'])) {
			$db->order($query['order']);
		}

		// Se definen los límites si hay paginación
		if (isset($query['pagination'])) {
			$db->limit(':start', ':end');
			$db->params(array(
				':start' => ['int', $offset],
				':end' 	 => ['int', $query['pagination']['rows']]
			));
		}

		// Se ejecuta la consulta
		$db->execute();

		// Se obtienen los registros
		$records = $db->fetchAll();

		// Si no se solicitó la paginación se determina la variable $totalRecords igual a la cantidad de registros obtenidos
		if (!isset($query['pagination'])) {
			$totalRecords = count($records);
		}

		// Agrega las columnas adicionales
		foreach ($records as $i => $valor) {

			// Agrega el índice
			$records[$i]['_index'] = $offset + $i + 1;
		}

		// Obtiene y define la paginación
		if (isset($query['pagination'])) {
			$pagination = $this->_getPaginationOf($query['pagination']);
		} else {
			$pagination = null;
		}

		// Devuelve el resultado
		return array(
			'records' => $records,
			'pagination' => $pagination
		);
	}

	/**
	 * Devuelve la propiedad $from.
	 *
	 * @return string Tabla/vista con la que está trabajando la instancia.
	 */
	private function _get_from() {
		return $this->_from;
	}

	/**
	 * Define la propiedad $from.
	 *
	 * @param string Tabla/vista con la que está trabajando la instancia.
	 *
	 * @return void
	 */
	private function _set_from($from) {
		if (isset($from)) {
			$this->_from = $from;
		} else {
			$this->_from = $this->_tabla;
		}
	}

	/**
	 * Devuelve la propiedad $db.
	 *
	 * @return QueryBuilder Devuelve la instancia QueryBuilder manejada por la instancia DataGrid.
	 */
	private function _get_db() {
		return $this->_db;
	}

	/**
	 * Define la propiedad $db.
	 *
	 * @param QueryBuilder Pasa por referencia una instancia de QueryBuilder.
	 *
	 * @return void
	 */
	private function _set_db(&$db) {
		$this->_db = $db;
	}

	/**
	 * Genera los datos corresponidentes para usarlos durante la construcción de una paginación.
	 *
	 * @param array $pagination Datos generales de la paginación
	 *
	 * @return array Datos para construir una paginación
	 */
	private function _getPaginationOf($pagination) {

		// Determina el estado de los botones 'first' y 'previous'
		if (($pagination['page'] == 1) || ($pagination['pages'] <= 1)) {
			$first = array(
				'index'		=> 1,
				'enabled'	=> false
			);
			$previous = array(
				'index'		=> ($pagination['page'] - 1),
				'enabled'	=> false
			);
		} else {
			$first = array(
				'index'		=> 1,
				'enabled'	=> true
			);
			$previous = array(
				'index'		=> ($pagination['page'] - 1),
				'enabled'	=> true
			);
		}

		// Determina el estado para los botones 'next' y 'last'
		if (($pagination['page'] == $pagination['pages']) || ($pagination['pages'] <= 1)){
			$next = array(
				'index'		=> ($pagination['page'] + 1),
				'enabled'	=> false
			);
			$last = array(
				'index'		=> $pagination['pages'],
				'enabled'	=> false
			);
		} else {
			$next = array(
				'index'		=> ($pagination['page'] + 1),
				'enabled'	=> true
				);
			$last = array(
				'index'		=> $pagination['pages'],
				'enabled'	=> true
			);
		}

		// Determina el rango de los botones de navegación
		if (!isset($pagination['buttons'])) {
			$pagination['buttons'] = 3;
		}
		if ($pagination['pages'] <= $pagination['buttons']) {
			$navLeft = 1;
			$navRight = $pagination['pages'];
		} else if ($pagination['page'] == 1) {
			$navLeft = 1;
			$navRight = $pagination['buttons'];
		} else if ($pagination['page'] == $pagination['pages']) {
			$navLeft = $pagination['pages'] - $pagination['buttons'] + 1;
			$navRight = $pagination['pages'];
		} else {
			if ($previous['index'] <= $pagination['page']) {
				$navLeft = $pagination['page'] - $pagination['buttons'] + 2;
				$navRight = $pagination['page'] + 1;
				if ($navLeft <= 0) {
					$navLeft = 1;
					$navRight = $pagination['buttons'];
				}
			} else {
				$navLeft = $pagination['page'] - 1;
				$navRight = $pagination['page'] + $pagination['buttons'] - 2;
				if ($navRight > $pagination['pages']) {
					$navRight = $pagination['pages'];
					$navLeft = $pagination['pages'] - $pagination['buttons'] + 1;
				}
			}
		}

		// Define los botones de navegación
		$nav = array();
		for ($i = $navLeft; $i <= $navRight; $i++) {
			if ($i == $pagination['page']) {
				array_push($nav, array(
					'index'		=> $i,
					'enabled'	=> false
				));
			} else {
				array_push($nav, array(
					'index'		=> $i,
					'enabled'	=> true
				));
			}
		}

		// Devuelve los datos de la paginación
		return array(
			'first'        => $first,
			'previous'     => $previous,
			'nav'          => $nav,
			'next'         => $next,
			'last'         => $last,
			'index'        => $pagination['page'],
			'totalPages'   => $pagination['pages'],
			'totalRecords' => $pagination['records']
		);
	}
}
?>
