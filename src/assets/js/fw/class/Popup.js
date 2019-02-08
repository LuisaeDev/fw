define('fw/Popup', [ 'fw/nunjucks', 'fw/EventsHandler', 'fw/helpers' ], function(nunjucks, EventsHandler, helpers) {

	// Agrega la estructura HTML de los popups
	$('body').prepend(nunjucks.render('popup/layers'));

	// Atajos de jQuery
	var $structure = $('#popup-structure');
	var $back =      $('#popup-structure__back');
	var $front =     $('#popup-structure__front');
	var $overlay =   $('#popup-structure__overlay');
	var $loading =   $('#popup-structure__loading');

	// Datos generales de los popups
	var options = {
		queue: [],
		visible:   [],
		overlay:  false,
		isloading:  false
	};

	/**
	 * Muestra, oculta el overlay.
	 *
	 * @param  {Boolean} status Estado que indica si debe mostrar o ocultar el overlay
	 * @return {void}
	 */
	function overlay(status) {
		if ((status == true) && (options.overlay == false)) {

			// Muestra la capa de popups y el overlay
			$structure.addClass('popup-structure--show');
			$overlay.addClass('popup-structure__overlay--show');

			// Agrega la clase no-scroll al body para evitar hacer scroll
			$('body').addClass('popup--no-scroll');

			// Define el estado general del overlay
			options.overlay = true;

		} else if ((status == false) && (options.overlay == true)) {

			// Oculta la capa de popups, overlay y remueve la clase no-scroll, touch del body
			$structure.removeClass('popup-structure--show');
			$overlay.removeClass('popup-structure__overlay--show');
			$('body').removeClass('popup--no-scroll');

			// Define el estado general del overlay
			options.overlay = false;
		}
	}

	/**
	 * Muestra, oculta el elemento cargando.
	 *
	 * @param  {Boolean} status Estado que indica si debe mostrar o ocultar el elemento cargando
	 * @return {void}
	 */
	function loading(status) {
		if (status == false) {
			options.isLoading = false;
			$loading.removeClass('popup-structure__loading--show');
		} else {
			options.isLoading = true;
			$loading.addClass('popup-structure__loading--show');
		}
	}

	/**
	 * Cierra el popup frontal visible.
	 *
	 * @param  {String} method Método a utlizar para el cierre del popup, puede ser click afuera o por tecla 'esc'
	 * @return {void}
	 */
	function closeFront(method) {

		// Cierra el popup frontal si no se está cargando ningún popup
		if ((options.isLoading == false) && (options.visible.length > 0)) {

			// Selecciona el popup frontal
			var popup = options.visible[options.visible.length - 1];

			// Verifica que el popup permita el cierre al dar click afuera
			if ((method == 'out') && (popup.closeOut == true)) {
				popup.close();
			}

			// Verifica que el popup permita el cierre por la tecla esc
			if ((method == 'esc') && (popup.closeEsc == true)) {
				popup.close();
			}
		}
	}

	/**
	 * Centra uno o todos los options.
	 *
	 * @param  {Popup|undefined} popup Instancia del popup a centrar
	 * @return {void}
	 */
	function center(popup) {

		// Determina el o los popups a centrar
		if (popup) {
			var popups = [ popup ];
		} else {
			var popups = options.visible;
		}

		// Alinea verticalmente todos los popups definidos
		popups.forEach(function(popup) {
			if (popup.status.ready) {
				var top = ($front.height() - popup.$el.outerHeight())/2;
				if (top <= 0) {
					popup.$el.css({'top': '0px'});
				} else {
					popup.$el.css({'top': top + 'px'});
				}
			}
		});
	}

	/**
	 * Constructor de la clase.
	 *
	 * @param {String|Object|Function} controller Controlador del popup, al definir un string se cargará como módulo de RequireJS
	 * @param {Object|Function}   	   props     Propiedades iniciales del popup, o la función callback al ocultar o cerrar el popup cuando este argumento es una función
	 * @param {Function|undefined} 	   callback  Función callback al ocultar o cerrar oel popup
	 */
	function Popup(controller, props, callback) {

		// Obtiene la (URL, Objeto, Clase) del controlador a cargar
		this._controller = controller;

		// Define los estados del popup
		this.status = {
			loading: false,
			loaded:  false,
			render:  false,
			ready: 	 false
		};

		// Inicializa la instancia de eventos
		this._eventsHandler = new EventsHandler(this);

		// Inicializa el objeto params pasado al controlador
		this.params = {};

		// Identifica si el argumento 'props' es un objeto con las opciones iniciales
		if (helpers.isObject(props)) {
			for (prop in props) {
				this[prop] = props[prop];
			}
		} else if (props != undefined) {

			// Si el argumento 'props' no es un objeto y está definido, es considerado como el parámetro 'callback'
			callback = props;
		}

		// Define el id del elemento del popup
		if (this.id) {
			var id = this.id;
			this.id = '#' + this.id;
		} else {
			var id = 'popup_' + Math.random().toString(36).slice(2);
			this.id = '#' + id;
		}

		// El parámetro callback define si el popup debe mostrarse
		if (helpers.isFunction(callback)) {
			this.show(callback);
		}

		// Crea el wrapper del popup
		$back.children('.popup-structure__container').append(nunjucks.render('popup/wrapper', {
			id: id
		}));

		// Obtiene el elemento del popup
		this.el = document.getElementById(id);
		this.$el = $(this.el);
	}

	/**
	 * Propiedades del prototipo.
	 *
	 * @type {Object}
	 */
	Popup.prototype = {

		// Controlador del popup
		controller: undefined,

		// ID asignado al elemento del popup
		id: undefined,

		// Estados del popup
		status: {
			loading: false,
			loaded:  false,
			render:  false,
			ready: 	 false
		},

		// Parámetros a pasar al controlador del popup
		params: {},

		// Contexto pasado a la función callback
		context: window,

		// Función callback al cerrar o ocultar el popup
		callback: undefined,

		// Elemento DOM del popup
		el: undefined,

		// Elemento jQuery del popup
		$el: undefined,

		// Template de la ventana del popup
		window: undefined,

		// Template de contenido del popup
		content: undefined,

		// Clase tema del popup
		className: 'popup--default',

		// Título del popup
		title: undefined,

		// Clase o valor numérico que determina el ancho del popup
		width: 'popup--auto',

		// Acción de cerrar definida para closeOut y closeBtn ('remove', 'hide')
		closeAction: 'remove',

		// Activa / desactiva el botón de cierre
		closeBtn: true,

		// Activa / desactiva el cierre cuando se da click fuera del popup
		closeOut: true,

		// Activa / desactiva el cierre a través de la tecla escape
		closeEsc: true,

		// (URL, Objeto, Función) del controlador del popup
		_controller: undefined,

		// Instancia del controlador de eventos
		_eventsHandler: undefined,
	};

	/**
	 * Registra un evento.
	 *
	 * @return {this} Retorna la instancia para encadenar (chain)
	 */
	Popup.prototype.on = function() {
		this._eventsHandler.on.apply(this._eventsHandler, arguments);
		return this;
	};

	/**
	 * Emite un evento.
	 *
	 * @return {void}
	 */
	Popup.prototype.trigger = function() {
		this._eventsHandler.trigger.apply(this._eventsHandler, arguments);
	};

	/**
	 * Solicitud para mostrar el popup.
	 *
	 * @param  {mixed}    			params   Parámetros a pasar como primer argumento del método 'show' y 'render' del controlador
	 * @param  {Function|undefined} callback Función callback al cerrar u ocultar el popup
	 * @param  {Object|undefined}   context  Contexto a aplicar a la función callback
	 * @return {void}
	 */
	Popup.prototype.show = function(params, callback, context) {

		// Verifica si el controlador se está cargando o ya se está mostrando
		if (this.status.loading || this.status.ready) {
			return;
		}

		// Si otra instancia del popup está en proceso de carga, se pone en cola la solicitud actual
		if (options.isLoading) {
			options.queue.push({ popup: this, passArguments: arguments });
			return;
		}

		// Define el estado de cargando
		this.status.loading = true;

		// Muestra el overlay
		overlay(true);

		// Muestra el elemento de cargando
		loading(true);

		// En caso de que el argumento 'params' sea una función, será considerada como la función callback
		if (helpers.isFunction(params)) {

			// Si se definió la función callback en el argumento 'params' y se definió el argumento 'callback', este último será considerado como 'context'
			if (callback) {
				context = callback;
			}

			// Define la función callback
			callback = params;

			// Define la variable a pasar 'params' en undefined
			params = undefined;
		}

		// Fusiona los parámetros especificados
		if (params) {
			this.params = $.extend(this.params, params);
		}

		// Define la función callback
		if (helpers.isFunction(callback)) {
			this.callback = callback;
		}

		// Define el contexto a aplicar al llamar a la función callback
		if (context) {
			this.context = context;
		}

		// Carga el controlador del popup
		if (this.controller == undefined) {

			// Carga el controlador a través de requireJS
			if (helpers.isString(this._controller)) {
			 	var _this = this;
				require([this._controller], function(controller) {
					_this._initialize(controller);
				}, function() {
					_this.error();
				});

			} else {
			 	this._initialize(this._controller);
			}

		} else {

			// Llama al método render del controlador si no está renderizado
			if (this.status.render == false) {

				// Emite el evento render
				this.trigger('render', this.params);

				// Llama al método render del controlador y pasa las variables pasadas
				if (helpers.isFunction(this.controller.render)) {
					this.controller.render.apply(this.controller, [ this.params ]);
				}

				// Crea la estructura HTML del popup
				this._buildPopup();

				// Define el estado del render
				this.status.render = true;

				// Emite el evento rendered
				this.trigger('rendered');

				// Llama al método rendered del controlador y pasa sus parámetros
				if (helpers.isFunction(this.controller.rendered)) {
					this.controller.rendered.apply(this.controller);
				}
			}

			// Emite el evento show
			this.trigger('show', this.params);

			// Llama al método show del controlador y pasa las variables pasadas
			if (helpers.isFunction(this.controller.show)) {
				this.controller.show.apply(this.controller, [ this.params ]);
			}
		}
	};

	/**
	 * Método llamado desde el controlador para confirmar que el popup está listo para mostrarse.
	 *
	 * @return {void}
	 */
	Popup.prototype.ready = function() {
		if ((this.status.loading) && (this.status.ready == false)) {

			// Define el estado cargando
			this.status.loading = false;

			// Define el estado de ready
			this.status.ready = true;

			// Oculta el elemento cargando
			loading(false);

			// Si existe otro popup en el front, lo mueve al back
			if (options.visible.length > 0) {

				// Selecciona el popup frontal
				$popup = options.visible[options.visible.length - 1].$el;

				// Envía el popup a la capa $back
				$back.children('.popup-structure__container').append($popup);

				// Remueve el atributo tabindex del popup
				$popup.removeAttr('tabindex')
			}

			// Mueve el elemento del popup hacia front
			$front.children('.popup-structure__container').append(this.$el);

			// Agrega la instancia del popup al array de 'popups' visibles
			options.visible.push(this);

			// Agrega al popup la clase visible
			this.$el.addClass('popup--show');

			// Agrega el atributo tabindex y define el foco en el popup
			this.$el.attr('tabindex', 1000)
			this.$el.focus();

			// Centra el popup actual
			center(this);

			// Realiza el scroll top del contenedor frontal
			$front.scrollTop(0);

			// Emite el evento ready
			this.trigger('ready');

			// Verifica si existen popups por cargar en la cola, si no hay más popups en cola
			if (options.queue.length > 0) {
				options.queue[0].popup.show.apply(options.queue[0].popup, options.queue[0].passArguments);
				options.queue.splice(0, 1);
			}
		}
	};

	/**
	 * Método llamado cuando el controlador no pudo cargarse o no pudo mostrarse.
	 *
	 * @return {void}
	 */
	Popup.prototype.error = function() {
		if ((this.status.loading) && (this.status.ready == false)) {

			// Define el estado de cargando
			this.status.loading = false;

			// Oculta el elemento cargando
			loading(false);

			// Emite el evento error
			this.trigger('error');

			// Cierra el popup
			this.close();
		}
	};

	/**
	 * Cierra el popup.
	 *
	 * El popup es cerrado de acuerdo a su acción de cierre definida en la propiedad 'closeAction'
	 * Todos los argumentos definidos al llamar este método, son pasados a la función callback
	 *
	 * @return {void}
	 */
	Popup.prototype.close = function() {

		// Si el popup se está cargando no podrá cerrarse
		if (this.status.loading) {
			return;
		}

		// Valida que la acción sea 'remove' u 'hide'
		if ((this.closeAction != 'remove') && (this.closeAction != 'hide')) {
			return;
		}

		// Verifica si el controlador pudo ser cargado y si dispone del método de cerrar para que valide la acción
		if ((this.controller) && (helpers.isFunction(this.controller.close))) {
			var close = this.controller.close.apply(this.controller);
		} else {
			var close = true;
		}

		// Cierra el popup de acuerdo a su acción cerrar definida
		if (close !== false) {
			switch (this.closeAction) {
				case 'remove':

					// Cierra el popup
					this._close.apply(this, [ true, arguments ]);

					// Emite el evento 'remove'
					this.trigger('remove');
					break;

				case 'hide':

					// Cierra el popup
					this._close.apply(this, [ false, arguments ]);

					// Emite el evento hide
					this.trigger('hide');
					break;
			}
		}
	};

	/**
	 * Centra el popup verticalmente.
	 *
	 * @return {void}
	 */
	Popup.prototype.center = function() {
		center(this);
	};

	/**
	 * @private
	 *
	 * Inicializa el controlador del popup y previo a ser mostrado la primera vez
	 *
	 * @param  {Object} controller Objeto con los métodos requeridos para interactuar con el controlador
	 * @return {void}
	 */
	Popup.prototype._initialize = function(controller) {

		// Define el estado de cargado
		this.status.loaded = true;

		// Obtiene la instancia del controlador
		if (helpers.isObject(controller)) {
			this.controller = controller;
		}
		if (helpers.isFunction(controller)) {
			this.controller = new controller(this);
		}

		// Almacena los parámetros definidos que habían sido definidos hasta este momento para luego fusionarlos despúes de llamar al método 'initialize' del controlador
		// Nota: El objetivo de fusionar los parámetros es para darle prioridad a los parámetros establecidos al crear la instancia del Popup y no a los definidos en el método 'initialize' del controlador
		var params = $.extend(this.params, {});

		// Llama al método initialize() del controlador y pasa como contexto la instancia del popup
		if (helpers.isFunction(this.controller.initialize)) {
			this.controller.initialize.apply(this.controller, [ this ]);
		}

		// Emite el evento initialize
		this.trigger('initialize', [ this ]);

		// Fusiona los parámetros previos con los definidos en el método 'initialize' del controlador
		$.extend(this.params, params);

		// Emite el evento render
		this.trigger('render', this.params);

		// Llama al método render del controlador y pasa sus parámetros
		if (helpers.isFunction(this.controller.render)) {
			this.controller.render.apply(this.controller, [ this.params ]);
		}

		// Crea la estructura HTML del popup
		this._buildPopup();

		// Define el estado del render
		this.status.render = true;

		// Emite el evento rendered
		this.trigger('rendered');

		// Llama al método rendered del controlador y pasa sus parámetros
		if (helpers.isFunction(this.controller.rendered)) {
			this.controller.rendered.apply(this.controller);
		}

		// Emite el evento 'show'
		this.trigger('show', this.params);

		// Llama al método 'show' del controlador y pasa las variables pasadas en el método 'show'
		if (helpers.isFunction(this.controller.show)) {
			this.controller.show.apply(this.controller, [ this.params ]);
		}
	};

	/**
	 * @private
	 *
	 * Cierra el popup e intenta hacer la llamada al callback.
	 *
	 * @param  {Boolean} remove   	   Indica si el popup debe destruirse
	 * @param  {Array} 	 passArguments Argumentos a pasar al callback
	 * @return {void}
	 */
	Popup.prototype._close = function(remove, passArguments) {

		// Se remueve el popup del array de popups visibles
		for (var i in options.visible) {
			if (options.visible[i].id == this.id) {
				options.visible.splice(i, 1);
				break;
			}
		}

		// Remueve la clase visible del popup
		this.$el.removeClass('popup--show');

		// Mueve el elemento del popup al back
		$back.children('.popup-structure__container').prepend(this.$el);

		// Remueve el atributo tabindex del popup
		this.$el.removeAttr('tabindex')

		// Remueve el contenido del popup y define el estado de render
		if (remove == true) {

			// Emite el evento remove
			this.trigger('remove');

			// Verifica si el controlador fue cargado y si dispone del método de remove
			if ((this.controller) && (helpers.isFunction(this.controller.remove))) {
				this.controller.remove.apply(this.controller);
			}

			// Remueve el popup
			this._removePopup();

			// Define el estado de render
			this.status.render = false;

		} else {

			// Al ocultar el popup remueve su propiedad top, para cuando se muestre nuevamente se le calcule y asigne una nueva posición
			this.$el.css({'top': 'initial'});
		}

		// Mueve el último popup visible al front
		if (options.visible.length > 0) {
			$front.children('.popup-structure__container').append(options.visible[options.visible.length - 1].$el);
		} else {

			// Oculta el overlay
			overlay(false);
		}

		// Define el estado de ready
		this.status.ready = false;

		// Emite el evento close
		this.trigger('close');

		// Llama a la función callback y pasa los argumentos de respuesta
		if (helpers.isFunction(this.callback)) {
			this.callback.apply(this.context, passArguments);
		}

		// Emite el evento 'callback'
		if (passArguments) {
			passArguments = [].slice.call(passArguments);
			passArguments.unshift('callback');
			this.trigger.apply(this, passArguments);
		} else {
			this.trigger('callback');
		}
	};

	/**
	 * Construye el popup en el DOM.
	 *
	 * @return {void}
	 */
	Popup.prototype._buildPopup = function() {

		// Compila el template para la ventana del popup
		if (this.window == undefined) {

			// Obtiene el template default
			this.window = nunjucks.getTemplate('popup/default');

		} else if (helpers.isString(this.window)) {

			// Se compila si se definió un template
			this.window = nunjucks.compile(this.window);
		}

		// Compila el template para el contenido del popup
		if (this.content == undefined) {
			this.content = '';
		}

		// Renderiza la ventana del popup y su template de contenido
		this.$el.html(this.window.render({
			id:       this.id,
			content:  this.content,
			title:    this.title,
			closeBtn: this.closeBtn
		}));

		// Agrega la clase tema del popup
		this.$el.addClass(this.className);

		// Agrega la clase que determina el ancho del popup
		if (this.width) {
			if (isNaN(this.width)) {
				this.$el.addClass(this.width);
			} else {
				this.$el.css('max-width', this.width);
			}
		}

		// Vincula los eventos para el botón de cerrar del popup
		if (this.closeBtn == true) {
			var _this = this;
			this.$el.find('*.js-popup-close').click(function(e) {
				e.preventDefault();
				_this.close();
			});
		}
	};

	/**
	 * Destruye el popup del DOM.
	 *
	 * @return {void}
	 */
	Popup.prototype._removePopup = function() {

		// Desvincula el evento click del botón de cerrar
		this.$el.find('*.js-popup-close').off('click');

		// Remueve el contenido del popup
		this.$el.html('');
	};

	/**
	 * Evento que centra todos los popups al cambiar el tamaño del navegador.
	 */
	$(window).resize(function() {
		center();
	});

	/**
	 * Evento para cerrar el popup frontal con la tecla esc.
	 */
	$structure.keyup(function(e) {
		if (e.which == 27) {
			closeFront('esc');
		}
	});

	/**
	 * Se asignan los eventos al front para el cierre del popup frontal al dar click afuera de el.
	 *
	 * Nota: Se utiliza la variable bandera frontClick para saber con exactitud cuando se realiza el mousedown y mouseup en el elemento con la clase .js-popup-out
	 */
	var frontClick = false;
	$front.on('mousedown', function(e) {

		// Verifica que el click sea izquierdo y sobre el elemento con la clase .js-popup-out
		if ((e.which == 1) && ($(e.target).hasClass('js-popup-out'))) {
			frontClick = true;
		} else {
			frontClick = false;
		}
	});
	$front.on('click', function(e) {

		// Verifica que el mousedown se haya realizado sobre el elemento con la clase .js-popup-out
		if (($(e.target).hasClass('js-popup-out')) && (frontClick == true)) {
			closeFront('out');
		}
		frontClick = false;
	});

	/**
	 * Devuelve la clase.
	 */
	return Popup;
});
