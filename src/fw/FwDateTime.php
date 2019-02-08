<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

use Exception;
use DateTime;
use DateTimeZone;
use DateInterval;

/**
 * Clase que extiende la clase DateTime, utiliza los mismo métodos, sin embargo se agregaron características que optimicen su uso.
 * Se mejoró el contructor, para poder definir fechas provenientes con un formato en particular y/o una zona horaria.
 * Se sobre-escribieron algunos métodos que requieren de argumentos con instancias de tipo DateInterval, DateTime, DateTimeZone de manera que tales argumentos puedan especificarse como string para que internamente se construyan las instancias respectivas.
 */
class FwDateTime extends DateTime {

	/**
	 * Constructor.
	 *
	 * @param string|int $time Fecha / tiempo para la instancia, puede ser una marca Timestamp o una fecha string, ej: "now", "Y-m-d H:i:s". Utlizar los formatos: http://php.net/manual/en/datetime.formats.date.php
	 * @param array $params Array asociativo de parámetros para la creación de la fecha
	 * 	1 La posición ['format'] representa el formato en el que se definió una fecha cuando esta es string
	 * 	2 La posición ['timezone'] representa la zona horaria proveniente de la fecha, puede ser un string o una instancia DateTimeZone
	 */
	public function __construct($time = 'now', array $params = null) {

		// Se obtiene la zona horaria especificada o la del framework
		if (isset($params['timezone'])) {
			if (is_string($params['timezone'])) {
				$timezone = new DateTimeZone($params['timezone']);
			} else {
				$timezone = $params['timezone'];
			}
		} else {
			$timezone = new DateTimeZone(Conf::getParam('default_timezone'));
		}

		// Verifica si $time es de tipo timestamp
		if (self::isTimestamp($time)) {

			// Crea la instancia DateTime y define su timestamp
			parent::__construct('now', $timezone);
			parent::setTimestamp($time);

		} else {

			// Crea la instancia DateTime, verifica si se especificó un formato para su creación
			if (isset($params['format'])) {
				$dt = DateTime::createFromFormat($params['format'], $time, $timezone);
			} else {
				$dt = false;
			}
			if ($dt) {
				parent::__construct($dt->format('Y-m-d H:i:s'), $timezone);
			} else {
				parent::__construct($time, $timezone);
			}
		}
	}

	/**
	 * Aumenta un intervalo de tiempo a la instancia.
	 *
	 * @param string|DateInterval Intervalo de tiempo a aumentar
	 *
	 * @return void
	 *
	 * @see https://en.wikipedia.org/wiki/ISO_8601#Durations La definición de intervalos en formato string obedece este formato
	 */
	public function add($interval) {
		if ($interval instanceof DateInterval) {
			parent::add($interval);
		} else {
			parent::add(new DateInterval($interval));
		}
	}

	/**
	 * Reduce un intervalo de tiempo a la instancia.
	 *
	 * @param string|DateInterval Intervalo de tiempo a reducir
	 *
	 * @return void
	 *
	 * @see https://en.wikipedia.org/wiki/ISO_8601#Durations La definición de intervalos en formato string obedece este formato
	 */
	public function sub($interval) {
		if ($interval instanceof DateInterval) {
			parent::sub($interval);
		} else {
			parent::sub(new DateInterval($interval));
		}
	}

	/**
	 * Obtiene el intervalo de tiempo entre la instancia y la fecha especificada.
	 *
	 * @param FwDateTime|DateTime|string|int $date     Fecha con la cual se calculará el intervalo de tiempo
	 * @param bool|null                      $absolute ¿Debería el intervalo ser forzado para ser positivo?
	 *
	 * @return DateInterval
	 */
	public function diff($date, $absolute = NULL) {
		if (($date instanceof self) || ($date instanceof DateTime)) {
			return parent::diff($date, $absolute);
		} else {
			$dt = new self($date);
			return parent::diff($dt, $absolute);
		}
	}

	/**
	 * Cambia la zona horaria de la instancia.
	 *
	 * @param string|DateTimeZone $timezone Nueva zona horaria
	 *
	 * @return void
	 */
	public function setTimezone($timezone) {
		if ($timezone instanceof DateTimeZone) {
			parent::setTimezone($timezone);
		} else {
			parent::setTimezone(new DateTimeZone($timezone));
		}
	}

	/**
	 * Devuelve la fecha formateada según el formato dado.
	 *
	 * @param string                   $format Formato de salida para la fecha
	 * @param string|DateTimeZone|null $timezone Zona horaria a aplicar a la fecha para su salida
	 *
	 * @return string Devuelve la fecha formateada según el formato dado
	 */
	public function format($format, $timezone = null) {

		// Instancia temporal
		$dt = clone $this;

		// Se define la zona horaria
		if (isset($timezone)) {
			$dt->setTimezone($timezone);
		}

		return date_format($dt, $format);
	}

	/**
	 * Formatea una fecha/hora local según una configuración local.
	 *
	 * @param string                   $format   Formato de salida para la fecha
	 * @param string|DateTimeZone|null $timezone Zona horaria a aplicar a la fecha para su salida
	 *
	 * @return string Devuelve una cadena formateada según format empleando, los nombres del mes y del día de la semana y otras cadenas dependientes del lenguaje respetan el localismo establecido con setlocale().
	 */
	public function strftime($format, $timezone = null) {

		// Instancia temporal
		$dt = clone $this;

		// Se define la zona horaria
		if (isset($timezone)) {
			$dt->setTimezone($timezone);
		}

		return strftime($format, $dt->getTimestamp());
	}

	/**
	 * Verifica si un valor es de tipo timestamp.
	 *
	 * @param string|int $unixTimestamp Marca temporal de Unix que representa la fecha
	 *
	 * @return bool
	 */
	public static function isTimestamp($unixTimestamp) {
		return ((string)(int) $unixTimestamp === (string)$unixTimestamp) && ($unixTimestamp <= PHP_INT_MAX) && ($unixTimestamp >= ~PHP_INT_MAX);
	}

	/**
	 * Devuelve un timestamp a partir de la definición de una fecha.
	 *
	 * @param string|int $time   Fecha, puede ser una marca Timestamp o una fecha string, ej: "now", "Y-m-d H:i:s".
	 * @param array      $params Array asociativo de parámetros para la creación de la fecha
	 *
	 * @return int|null Marca timestamp o null cuando la fecha falló
	 */
	public static function getTimestampFrom($time, array $params = null) {

		try {

			$dt = new self($time, $params);
			return $dt->getTimestamp();

		} catch (Exception $e) {
			return null;
		}
	}

	/**
	 * Devuelve una fecha con formato a partir de la definición de una fecha.
	 *
	 * @param string|int|DateTime|FwDateTime $dt     Fecha, puede ser una marca Timestamp o una fecha string, ej: "now", "Y-m-d H:i:s".
	 * @param string|null                    $format Formato de salida para la fecha
	 * @param array                          $params Array asociativo de parámetros para la creación de la fecha
	 *
	 * @return string|null Fecha con formato
	 */
	public static function getFormatFrom($dt, $format = null, $timezone = null) {

		try {
			if (($dt instanceof self == false) && ($dt instanceof DateTime == false)) {
				$dt = new self($dt);
			}
			return $dt->format($format, $timezone);

		} catch (Exception $e) {
			return null;
		}
	}
}
?>
