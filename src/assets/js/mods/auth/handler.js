/**
 * Controlador de sesiones de usuario.
 */
define([ 'fw', 'fw/EventsHandler', 'fw/storage', 'fw/helpers' ], function(fw, EventsHandler, storage, helpers) {

	/**
	 * Instancia controladora de eventos.
	 *
	 * @type {EventsHandler}
	 */
	var eventsHandler = new EventsHandler();

	/**
	 * XHR de jquery.
	 *
	 * @type {Object}
	 */
	var $xhr = undefined;

	/**
	 * API del módulo.
	 */
	return {

		/**
		 * URL para el request de login
		 *
		 * @type {String}
		 */
		loginURL: undefined,

		/**
		 * URL para el request de logout
		 *
		 * @type {String}
		 */
		logoutURL: undefined,

		/**
		 * Suscripción a un evento del módulo.
		 *
		 * @return {void}
		 */
		on: function() {
			eventsHandler.on.apply(eventsHandler, arguments);
		},

		/**
		 * Confirma si hay un request en curso.
		 *
		 * @return {Boolean}
		 */
		isBusy: function() {
			if ($xhr) {
				return true;
			} else {
				return false;
			}
		},

		/**
		 * Realiza el request para inicio de sesión.
		 *
		 * @param  {Object} params Parámetros a pasar al request
		 * @return {void}
		 */
		login: function(params) {

			// Verifica si existe un request en curso
			if ($xhr) {
				return;
			}

			// Emite el evento de cargando
			eventsHandler.trigger('loading', true);

			// Codifica en base64 el nombre de usuario, correo y password
			if ('username' in params) {
				params.username = helpers.base64Encode(params.username);
			}
			if ('email' in params) {
				params.email = helpers.base64Encode(params.email);
			}
			if ('pass' in params) {
				params.pass = helpers.base64Encode(params.pass);
			}

			// Realiza la solicitud ajax
			$xhr = $.ajax({
				url:      this.loginURL,
				type:     'POST',
				dataType: 'json',
				context:  this,
				data:     params

			}).always(function() {

				// Remueve el objeto XHR de jquery
				$xhr = undefined;

				// Emite el evento de cargando
				eventsHandler.trigger('loading', false);

			}).done(function(resp) {

				// Almacena el token de la sesión
				storage.local.set('fw_tk', resp.tk);

				// Emite el evento del response
				eventsHandler.trigger('login-response', resp);

			}).fail(function(jqXHR, textStatus, errorThrown) {
				eventsHandler.trigger('login-response');
			});
		},

		/**
		 * Realiza el request de cierre de sesión.
		 *
		 * @return {void}
		 */
		logout: function() {

			// Verifica si existe un request en curso
			if ($xhr) {
				return;
			}

			// Emite el evento de cargando
			eventsHandler.trigger('loading', true);

			// Realiza la solicitud ajax
			$xhr = $.ajax({
				url: 		this.logoutURL,
				type: 		'POST',
				dataType: 	'json',
				context: 	this,
				data: {
					tk: storage.local.get('fw_tk')
				}

			}).always(function() {

				// Remueve el objeto XHR de jquery
				$xhr = undefined;

				// Emite el evento de cargando
				eventsHandler.trigger('loading', false);

			}).done(function(resp) {

				// Emite el evento del response
				eventsHandler.trigger('logout-response', resp);

			}).fail(function(jqXHR, textStatus, errorThrown) {
				eventsHandler.trigger('logout-response');
			});
		}
	};
});
