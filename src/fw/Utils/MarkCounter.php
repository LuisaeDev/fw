<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw\Utils;

use Fw\QueryBuilder;
use Fw\FwDateTime;
use DateInterval;

/**
 * Contador de marcas.
*/
class MarkCounter {

	/** @var string Listado de marcas manejado por la instancia */
	private $_listing;

    /** @var Fw\QueryBuilder Instancia de conexión a la base de datos */
	private $_db;

	/**
	 * Constructor.
	 *
	 * @param string $listing Listado de marcas manejado por la instancia
	 */
	public function __construct($listing) {

        // Define el listado de marcas manejado por la instancia
		$this->_listing = $listing;

		// Conexión a la base de datos
		$this->_db = new QueryBuilder('fw');
	}

	/**
	 * Agrega una marca.
	 *
	 * @param string $keyname Nombre clave de la marca
	 * @param mixed  $value   Valor de la marca
	 * @param mixed  $expire  Intérvalo de tiempo a expirar para la marca
	 *
	 * @return void
	 */
	public function add($keyname, $value, $expire = null) {

        // Determina el tiempo en expiración en UTC
        if ($expire) {
            $expire = time() + $this->_intervalToSeconds($expire);
        }

        // Almacena la marca
		$this->_db->autoInsert('fw_mark_counter', array(
            'listing' => $this->_listing,
			'keyname' => $keyname,
			'value'   => $value,
            'created' => time(),
            'expire'  => $expire
		));
	}

	/**
	 * Devuelve el último marcador registrado.
	 *
     * @param string $keyname Nombre clave de la marca
     *
	 * @return mixed|null
	 */
	public function getLast($keyname) {

        // Obtiene el registro del marcador
        $marker = $this->_db->get('fw_mark_counter', array(
            'listing' => $this->_listing,
            'keyname' => $keyname
        ), 'value, created', 'created DESC');

        // Retorna el marcador
		if ($marker === null) {
			return null;
		} else {
			return $marker;
		}
	}

	/**
	 * Devuelve todos los marcadores asociados a un nombre de marca.
	 *
     * @param string $keyname Nombre clave de la marca
	 *
	 * @return array
	 */
	public function getAll($keyname) {
        return $this->_db->autoSelect('fw_mark_counter', array(
            'listing' => $this->_listing,
            'keyname' => $keyname
        ), 'value, created', 'created DESC');
	}

	/**
	 * Cuenta la cantidad de marcadores asociados a un nombre de marca.
	 *
     * @param string      $keyname  Nombre clave de la marca
	 * @param string|null $interval Periodo de tiempo atras al momento actual de la consulta
	 *
	 * @return int
	 */
	public function count($keyname, $interval = null) {

        // Cuenta los marcadores dentro de el intervalo de tiempo especificado
        if (isset($interval)) {
			$dt = new FwDateTime('now');
			$dt->sub($interval);
            return $this->_db->count('*', 'fw_mark_counter', array(
                'listing' => $this->_listing,
                'keyname' => $keyname,
                'created' => [ '>=', $dt->getTimestamp() ]
            ));

        // Cuenta todos los marcadores vinculados al nombre de marca especificado
		} else {
            return $this->_db->count('*', 'fw_mark_counter', array(
                'listing' => $this->_listing,
                'keyname' => $keyname
            ));
		}
	}

	/**
	 * Destruye todos los marcadores asociados a un nombre de marca.
	 *
     * @param string $keyname Nombre clave de la marca
     *
	 * @return void
	 */
	public function delete($keyname) {
        $this->_db->autoDelete('fw_mark_counter', array(
            'listing' => $this->_listing,
            'keyname' => $keyname
        ));
	}

    /**
	 * Destruye todos los marcadores que hayan expirado y estén asociados a un nombre de marca.
	 *
     * @param string $keyname Nombre clave de la marca
     *
	 * @return void
	 */
    public function deleteExpired($keyname) {
        $this->_db->autoDelete('fw_mark_counter', array(
            'listing' => $this->_listing,
            'keyname' => $keyname,
            'expire'  => [ '<=', time() ]
        ));
	}

	/**
	 * Convierte un intervalo de tiempo a segundos.
	 *
	 * @param string $interval Intervalo de tiempo
	 *
	 * @return int Intervalo en segundos
	 */
	private static function _intervalToSeconds($interval) {
		$interval = new DateInterval($interval);
		$sec = 0;
		$sec += $interval->y * 365 * 24 * 60 * 60;
		$sec += $interval->m * 30 * 24 * 60 * 60;
		$sec += $interval->d * 24 * 60 * 60;
		$sec += $interval->h * 60 * 60;
		$sec += $interval->i * 60;
		$sec += $interval->s;
		return $sec;
	}
}
?>
