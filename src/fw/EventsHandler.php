<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

namespace Fw;

/**
 * Clase que hereda los métodos on() y trigger() para el manejo de eventos.
 */
class EventsHandler {

	/** @var array Lista de errores con callbacks suscritos */
	private static $_eventsList = [];

	/**
	 * Suscripción de uno o varios eventos.
	 *
	 * @param string   $eventName Nombre del evento a suscribir o varios eventos separados por comas
	 * @param function $callback  Función $callback a llamar al emitir el evento
	 *
	 * @return void
	 */
	public static function on($eventName, $callback) {

		// Separa el string por si se especificaron múltiples eventos
		$eventName = explode(',', $eventName);
		foreach ($eventName as $event) {
			$event = trim($event);

			// Inicializa el array del evento si aún no había sido creado
			if (!isset(self::$_eventsList[$event])) {
				self::$_eventsList[$event] = [];
			}

			// Suscribe la función callback en el array del evento
			self::$_eventsList[$event][] = $callback;
		}
	}

	/**
	 * Emite un evento y llama a todas las funciones suscritas.
	 *
	 * @param string $eventName Nombre del evento
	 *
	 * @return void
	 */
	public static function trigger($eventName) {

		// Verifica si el evento está registrado
		if (!isset(self::$_eventsList[$eventName])) {
			return;
		}

		// Obtiene los argumentos a pasar
		$args = func_get_args();

		// Remueve el primer argumento recibido
		array_shift($args);

		// Llama a cada función callback suscrita para el evento
		foreach (self::$_eventsList[$eventName] as $callback) {
			call_user_func_array($callback, $args);
		}
	}
}
?>
