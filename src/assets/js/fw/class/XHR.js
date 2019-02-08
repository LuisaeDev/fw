define('fw/XHR', [ 'fw/EventsHandler' ], function(EventsHandler) {

	/**
	 * Constructor de la clase.
	 */
	function XHR(options) {

		// Array que alamacena todos los XHR de jQuery en curso
		this._jqXHRs = [],

		// Extiende y define las opciones de la instancia
		this.options = $.extend({
			url: undefined,
			method: 'post',
			data: undefined,
			context: window,
			concurrent: false
		}, options);

		// Inicializa la instancia de eventos
		this._eventsHandler = new EventsHandler(this.options.context);
	}

	/**
	 * Prototipo de la clase.
	 *
	 * @type {Object}
	 */
	XHR.prototype = {

		// Array que alamacena todos los XHR de jQuery en curso
		_jqXHRs: [],

		// Opciones de la instancia
		options: {}
	};

	/**
	 * Registra un evento.
	 *
	 * @return {void}
	 */
	XHR.prototype.on = function() {
		this._eventsHandler.on.apply(this._eventsHandler, arguments);
		return this;
	};

	/**
	 * Emite un evento.
	 *
	 * @return {void}
	 */
	XHR.prototype.trigger = function() {
		this._eventsHandler.trigger.apply(this._eventsHandler, arguments);
		return this;
	};

	/**
	 * Determina si se está ejecutando un request.
	 *
	 * @return {Boolean}
	 */
	XHR.prototype.isLoading = function() {
		if (this._jqXHRs.length > 0) {
			return true;
		} else {
			return false;
		}
	};

	/**
	 * Realiza el XHR request.
	 *
	 * @param {Object} Parámetros a pasar como data del XHR
	 *
	 * @return {void}
	 */
	XHR.prototype.perform = function(data) {
		if ((this.options.concurrent) || (this._jqXHRs.length == 0)) {

			// Emite el evento requesting
			this.trigger('start', true);

			// Emite el evento loading
			this.trigger('loading', true);

			// Realiza el request
			var i = this._jqXHRs.length;
			this._jqXHRs[i] = $.ajax({
				url:     this.options.url,
				method:  this.options.method,
				data:    data || this.options.data || {},
				context: this
			})
			.always(function() {
				this._jqXHRs.splice(i);
				this.trigger('loading', false);
				this.trigger('always');
			})
			.done(function(res) {
				this.trigger('done', res);
			})
			.fail(function(jqXHR, textStatus) {
				this.trigger('fail');
			});
		}
	};

	/**
	 * Aborta los XHR request en curso.
	 *
	 * @return {void}
	 */
	XHR.prototype.abort = function() {
		for (var i = this._jqXHRs.length - 1; i >= 0; i--) {
			if (this._jqXHRs[i]) {
				this._jqXHRs[i].abort();
			}
			this._jqXHRs.splice(i);
		}
	};

	/**
	 * Retorna el constructor.
	 */
	return XHR;
});
