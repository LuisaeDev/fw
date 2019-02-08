/**
 * Clase para construir controladores de eventos.
 */
define('fw/EventsHandler', function() {
	
	/**
	 * Constructor de la clase.
	 * 
	 * @param {Object|undefined} context Contexto a aplicar al emitir los eventos
	 */
	function EventsHandler(context) {

		// Inicializa el array de eventos
		this._eventsList = [];

		// Define el contexto general de la instancia
		if (context) {
			this.context = context;
		} else {
			this.context = window;
		}
	}

	/**
	 * Propiedades del prototipo.
	 * 
	 * @type {Object}
	 */
	EventsHandler.prototype = {

		// Contexto general de la instancia
		context: window,

		// Array de eventos suscritos
		_eventsList: []
	};

	/**
	 * Suscripción de uno o varios eventos.
	 * 
	 * @param  {String}   		  eventName Nombre del evento a suscribir o varios eventos separados por comas
	 * @param  {Function} 		  callback  Función callback a llamar al emitir el evento
	 * @param  {Object|undefined} context   Contexto a aplicar a la función callback
	 * @return {void}
	 */
	EventsHandler.prototype.on = function(eventName, callback, context) {

		// Separa el string por si se especificaron múltiples eventos
		eventName = eventName.split(',');
		for (var i in eventName) {
			eventName[i] = eventName[i].trim();

			// Inicializa el array del evento si aún no había sido registrado
			if (eventName[i] in this._eventsList == false) {
				this._eventsList[eventName[i]] = [];
			}

			// Registra una función callback y contexto del evento
			this._eventsList[eventName[i]].push({
				callback: callback,
				context: context
			});
		}
	}

	/**
	 * Emite un evento y llama a todas las funciones suscritas.
	 * 
	 * @param  {String} eventName Nombre del evento
	 * @return {void}
	 */
	EventsHandler.prototype.trigger = function(eventName) {

		// Verifica si el evento está registrado
		if (eventName in this._eventsList == false) {
			return false;
		}

		// Obtiene los argumentos a pasar
		var params = Array.prototype.slice.call(arguments, 1);

		// Llama a cada función callback registrada para el evento
		for (var i in this._eventsList[eventName]) {
			var context = this._eventsList[eventName][i].context || this.context || window;
			this._eventsList[eventName][i].callback.apply(context, params);
		}
	}

	/**
	 * Devuelve la clase.
	 */
	return EventsHandler;
});