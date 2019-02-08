/**
 * Constructor de aplicaciones Backbone.
 */
define('fw/appBuilder', [ 'fw/EventsHandler' ], function(EventsHandler) {

	/**
	 * Almacena todas las aplicaciones creadas a través del método get().
	 *
	 * @type {Object}
	 */
	var globalApps = {};

	/**
	 * Constructor de la clase.
	 */
	function App() {

		// Estado de inicio de la Aplicación
		this._initialized = false;

		// Inicializa la instancia de eventos
		this._eventsHandler = new EventsHandler(this);

		// Definiciones previas de las clases de Backbone
		this._Model = [];
		this._Collection = [];
		this._View = [];
		this._Router = [];

		// Definiciones de clases de Backbone
		this.Model = {};
		this.Collection = {};
		this.View = {};
		this.Router = {};

		// Instancias de clases de Backbone
		this._model = {};
		this._collection = {};
		this._view = {};
		this._router = {};

		// Filtros
		this._filters = {};
	}

	/**
	 * Prototipo de la clase.
	 *
	 * @type {Object}
	 */
	App.prototype = {

		// Estado que define si la aplicación ya fue iniciada
		_initialized: false,

		// Instancia de eventos
		_eventsHandler: undefined,

		// Definición de clases previos a su construcción con Backbone
		_Model: [],
		_Collection: [],
		_View: [],
		_Router: [],

		// Clases Backbone
		Model: {},
		Collection: {},
		View: {},
		Router: {},

		// Instancias Backbone
		_model: {},
		_collection: {},
		_view: {},
		_router: {},

		// Filtros
		_filters: {}
	};

	/**
	 * Registra un evento.
	 *
	 * @return {void}
	 */
	App.prototype.on = function() {
		this._eventsHandler.on.apply(this._eventsHandler, arguments);
	};

	/**
	 * Emite un evento.
	 *
	 * @return {void}
	 */
	App.prototype.trigger = function() {
		this._eventsHandler.trigger.apply(this._eventsHandler, arguments);
	};

	/**
	 * Agrega un filtro.
	 *
	 * @param  {String}   filterName Nombre del filtro
	 * @param  {Function} callback   Callback al aplicar el filtro
	 * @return {void}
	 */
	App.prototype.setFilter = function(filterName, callback) {
		this._filters[filterName] = callback;
	};

	/**
	 * Aplica un filtro.
	 *
	 * @param  {String} filterName Nombre del filtro
	 * @param  {mixed}  value      Valor a pasar al filtro
	 * @return {mixed}
	 */
	App.prototype.applyFilter = function(filterName, value) {
		if ((filterName in this._filters) && (typeof this._filters[filterName] == 'function')) {
			return this._filters[filterName](value);
		} else {
			return value;
		}
	};

	/**
	 * Define / devuelve una clase Model de la aplicación.
	 *
	 * @param  {String} 		 className Nombre de la clase del modelo
	 * @param  {Object|Function} classObj  Objeto o función callback para la definición del modelo
	 * @return {Backbone.Model}            Clase Model de backbone
	 */
	App.prototype.classModel = function(className, classObj) {

		// Devuelve la clase
		if (classObj == undefined) {
			if (this.Model[className]) {
				return this.Model[className];
			}

		// Define la clase
		} else {
			if (this._initialized == false) {
				this._Model.push({
					className: className,
					classObj: classObj
				});
			} else {
				if ($.isFunction(classObj)) {
					this.Model[className] = Backbone.Model.extend(classObj.apply(this));
				} else {
					this.Model[className] = Backbone.Model.extend(classObj);
				}
			}
		}
	};

	/**
	 * Define / devuelve una clase Collection de la aplicación.
	 *
	 * @param  {String} 		 className Nombre de la clase de la colección
	 * @param  {Object|Function} classObj  Objeto o función callback para la definición de la colección
	 * @return {Backbone.Collection}       Clase Collection de backbone
	 */
	App.prototype.classCollection = function(className, classObj) {

		// Devuelve la clase
		if (classObj == undefined) {
			if (this.Collection[className]) {
				return this.Collection[className];
			}

		// Define la clase
		} else {
			if (this._initialized == false) {
				this._Collection.push({
					className: className,
					classObj: classObj
				});
			} else {
				if ($.isFunction(classObj)) {
					this.Collection[className] = Backbone.Collection.extend(classObj.apply(this));
				} else {
					this.Collection[className] = Backbone.Collection.extend(classObj);
				}
			}
		}
	};

	/**
	 * Define / devuelve una clase View de la aplicación.
	 *
	 * @param  {String} 		 className Nombre de la clase de la vista
	 * @param  {Object|Function} classObj  Objeto o función callback para la definición de la vista
	 * @return {Backbone.View}       	   Clase View de backbone
	 */
	App.prototype.classView = function(className, classObj) {

		// Devuelve la clase
		if (classObj == undefined) {
			if (this.View[className]) {
				return this.View[className];
			}

		// Define la clase
		} else {
			if (this._initialized == false) {
				this._View.push({
					className: className,
					classObj: classObj
				});
			} else {
				if ($.isFunction(classObj)) {
					this.View[className] = Backbone.View.extend(classObj.apply(this));
				} else {
					this.View[className] = Backbone.View.extend(classObj);
				}
			}
		}
	};

	/**
	 * Define / devuelve una clase Router de la aplicación.
	 *
	 * @param  {String} 		 className Nombre de la clase del router
	 * @param  {Object|Function} classObj  Objeto o función callback para la definición del router
	 * @return {Backbone.Router}       	   Clase Router de backbone
	 */
	App.prototype.classRouter = function(className, classObj) {

		// Devuelve la clase
		if (classObj == undefined) {
			if (this.Router[className]) {
				return this.Router[className];
			}

		// Define la clase
		} else {
			if (this._initialized == false) {
				this._Router.push({
					className: className,
					classObj: classObj
				});
			} else {
				if ($.isFunction(classObj)) {
					this.Router[className] = Backbone.Router.extend(classObj.apply(this));
				} else {
					this.Router[className] = Backbone.Router.extend(classObj);
				}
			}
		}
	};

	/**
	 * Construye y devuelve un nuevo modelo.
	 *
	 * @param {String} instanceName Nombre de la instancia a declarar
	 * @param {String} className    Nombre de la clase del modelo
	 * @param {Object} classObj     Objeto de inicialización
	 *
	 * @return {Backbone.Model}
	 */
	App.prototype.newModel = function(instanceName, className, classObj) {
		if (this.Model[className]) {
			return this._model[instanceName] = new this.Model[className](classObj);
		}
	};

	/**
	 * Construye y devuelve una nueva colección.
	 *
	 * @param {String} instanceName Nombre de la instancia a declarar
	 * @param {String} className    Nombre de la clase del modelo
	 * @param {Object} classObj     Objeto de inicialización
	 *
	 * @return {Backbone.Collection}
	 */
	App.prototype.newCollection = function(instanceName, className, classObj) {
		if (this.Collection[className]) {
			return this._collection[instanceName] = new this.Collection[className](classObj);
		}
	};

	/**
	 * Construye y devuelve una nueva vista.
	 *
	 * @param {String} instanceName Nombre de la instancia a declarar
	 * @param {String} className    Nombre de la clase del modelo
	 * @param {Object} classObj     Objeto de inicialización
	 *
	 * @return {Backbone.View}
	 */
	App.prototype.newView = function(instanceName, className, classObj) {
		if (this.View[className]) {
			return this._view[instanceName] = new this.View[className](classObj);
		}
	};

	/**
	 * Construye y devuelve un nuevo router.
	 *
	 * @param {String} instanceName Nombre de la instancia a declarar
	 * @param {String} className    Nombre de la clase del modelo
	 * @param {Object} classObj     Objeto de inicialización
	 *
	 * @return {Backbone.Router}
	 */
	App.prototype.newRouter = function(instanceName, className, classObj) {
		if (this.Router[className]) {
			return this._router[instanceName] = new this.Router[className](classObj);
		}
	};

	/**
	 * Devuelve una instancia de un modelo.
	 *
	 * @param {String} instanceName Nombre de la instancia a obtener
	 *
	 * @return {Backbone.Model}
	 */
	App.prototype.getModel = function(instanceName) {
		if (this._model[instanceName]) {
			return this._model[instanceName];
		}
	};

	/**
	 * Devuelve una instancia de una colección.
	 *
	 * @param {String} instanceName Nombre de la instancia a obtener
	 *
	 * @return {Backbone.Collection}
	 */
	App.prototype.getCollection = function(instanceName) {
		if (this._collection[instanceName]) {
			return this._collection[instanceName];
		}
	};

	/**
	 * Devuelve una instancia de una vista.
	 *
	 * @param {String} instanceName Nombre de la instancia a obtener
	 *
	 * @return {Backbone.View}
	 */
	App.prototype.getView = function(instanceName) {
		if (this._view[instanceName]) {
			return this._view[instanceName];
		}
	};

	/**
	 * Devuelve una instancia de un router.
	 *
	 * @param {String} instanceName Nombre de la instancia a obtener
	 *
	 * @return {Backbone.Router}
	 */
	App.prototype.getRouter = function(instanceName) {
		if (this._router[instanceName]) {
			return this._router[instanceName];
		}
	};

	/**
	 * Inicializa la aplicación.
	 *
	 * Construye todas las instancias de los modelos, colecciones, vistas y routers definidos previamente a la inicialización
	 *
	 * @param  {Function} callback Función callback al inicializar la aplicación
	 * @return {void}
	 */
	App.prototype.initialize = function(callback) {
		if (this._initialized == false) {

			// Define el estado de la aplicación como true
			this._initialized = true;

			// Construye las clases Model de la aplicación
			for (i in this._Model) {
				var model = this._Model[i];
				if ($.isFunction(model.classObj)) {
					this.Model[model.className] = Backbone.Model.extend(model.classObj.apply(this));
				} else {
					this.Model[model.className] = Backbone.Model.extend(model.classObj);
				}
			}

			// Construye las clases Collection de la aplicación
			for (i in this._Collection) {
				var collection = this._Collection[i];
				if ($.isFunction(collection.classObj)) {
					this.Collection[collection.className] = Backbone.Collection.extend(collection.classObj.apply(this));
				} else {
					this.Collection[collection.className] = Backbone.Collection.extend(collection.classObj);
				}
			}

			// Construye las clases View de la aplicación
			for (i in this._View) {
				var view = this._View[i];
				if ($.isFunction(view.classObj)) {
					this.View[view.className] = Backbone.View.extend(view.classObj.apply(this));
				} else {
					this.View[view.className] = Backbone.View.extend(view.classObj);
				}
			}

			// Construye las clases Router de la aplicación
			for (i in this._Router) {
				var router = this._Router[i];
				if ($.isFunction(router.classObj)) {
					this.Router[router.className] = Backbone.Router.extend(router.classObj.apply(this));
				} else {
					this.Router[router.className] = Backbone.Router.extend(router.classObj);
				}
			}

			// Llama a la función callback para iniciar la aplicación
			if (callback) {
				callback.apply(this);
			}

			// Emite el evento 'initialize'
			this.trigger('initialize');
		}
	};

	/**
	 * API del módulo.
	 */
	return {

		/**
		 * Construye una instancia de aplicación.
		 *
		 * @param  {String} appName Nombre de la aplicación
		 * @return {App}
		 */
		new: function(appName) {
			if (appName == undefined) {
				return new App();
			} else if (appName in globalApps) {
				return;
			} else {
				return globalApps[appName] = new App();
			}
		},

		/**
		 * Devuelve una aplicación registrada globalmente.
		 *
		 * Si la aplicación solicitada no está registrada, la contruye
		 *
		 * @param  {String} appName Nombre de la aplicación
		 * @return {App}         	Aplicación registrada
		 */
		get: function(appName) {
			if (globalApps[appName] == undefined) {
				globalApps[appName] = new App();
			}
			return globalApps[appName];
		}
	};
});
