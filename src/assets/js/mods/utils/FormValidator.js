define([ 'fw/XHR', 'fw/helpers' ], function(fwXHR, helpers) {

	/**
	 * Selecciona el elemento del formulario.
	 *
	 * @param  {String|Object} selector Selector del formulario, puede ser especificado por su id ('#formId') o por su atributo name ('formName')
	 * @return {Object}                 Elemento DOM del formulario
	 */
	function selectFormElement(selector) {
		if (Object.prototype.toString.call(selector) == '[object HTMLFormElement]') {
			return selector;
		} else if (typeof selector == 'string') {
			if (selector.substr(0, 1) == '#') {
				return document.getElementById(selector.substr(1));
			} else {
				return document.forms[selector];
			}
		}
	}

	/**
	 * Conjunto de expresiones regulares.
	 *
	 * @type {Object}
	 */
	var regex = {
		email:       /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/,
		float:       /^\-?[0-9]*\.?[0-9]+$/,
		integer:     /^\-?[0-9]+$/,
		ip:          /^((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){3}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})$/i,
		letters:     /^[a-zA-Z]+$/i,
		lettersDash: /^[a-zA-Z\-]+$/i,
		numbers:     /^[0-9]+$/,
		numbersDash: /^[0-9\-]+$/,
		url:         /^(http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(:[0-9]{1,5})?(\/.*)?$/
	}

	/**
	 * Constructor de la clase.
	 *
	 * @param {Object} options Conjunto de propiedades a definir
	 */
	var FormValidator = function(options) {

		// Define las propiedades iniciales
		this._errors = Array();
		this._fields = Array();
		this._customRules = {};
		this._customTypes = {};
		this._customMessages = {};

		// Define todas las propiedades especificadas
		for (option in options) {
			this[option] = options[option];
		}

		// Obtiene el elemento del formulario
		this.form = selectFormElement(options.form)

		// Define el contexto (cuando no fue especificado) a aplicar a las funciones callback
		if (!this.context) {
			this.context = this;
		}

		// Registra todos los campos especificados
		if (options.fields) {
			this.addFields(options.fields);
		}

		// Sustituye el evento submit del formulario
		var _this = this;
		this.form.onsubmit = function(evt) {

			// Valida el formulario
			_this.validate(undefined, evt);

			// Detiene la acción de envío del formulario cuando ha ocurrido un error o cuando se especificó la propiedad preventDefault
			if ((_this._errors.length > 0) || (_this.preventDefault == true)) {
				if (evt && evt.preventDefault) {
					evt.preventDefault();
				} else if (event) {

					// IE
					event.returnValue = false;
				}
				return false;
			} else {
				return true;
			}
		}
	};

	/**
	 * Propiedades del prototipo.
	 *
	 * @type {Object}
	 */
	FormValidator.prototype = {

		// Elemento del formulario
		form: undefined,

		// Indicador si debe detener la acción de envío del formulario en caso de éxito
		preventDefault: false,

		// Contexto a aplicar a las funciones callback
		context: undefined,

		// Array de errores producidos al enviar el formulario
		_errors: undefined,

		// Callback al cambiar el valor de un campo del formulario
		_change: undefined,

		// Callback al intentar enviarse el formulario
		_always: undefined,

		// Callback al enviarse el formulario exitosamente
		_success: undefined,

		// Callback de error cuando se envía el formulario y falla uno o más campos
		_fail: undefined,

		// Campos definidos del formulario
		_fields: undefined,

		// Funciones validadoras personalizadas para esta instancia
		_customRules: undefined,

		// Funciones validadoras para los tipos de la regla 'type'
		_customTypes: undefined,

		// Mensajes default para esta instancia
		_customMessages: undefined
	};

	/**
	 * @private
	 *
	 * Mensajes default para las reglas.
	 *
	 * @type {Object}
	 */
	FormValidator.prototype._messages = {
		required:        'El campo "%label" es requerido',
		type:            'El valor del campo "%label" es inválido',
		match:           'El campo "%label" debe ser igual al campo "%param"',
		'min-length':    'El campo "%label" debe contener al menos %param caracteres de largo',
		'max-length':    'El campo "%label" no debe exceder los %param caracteres de largo',
		length:          'El campo "%label" debe tener exactamente %param caracteres de largo',
		'min-range':     'El campo "%label" debe ser igual o mayor a %param',
		'max-range':     'El campo "%label" no debe ser mayor a %param',
		'regex':         'El valor del campo "%label" es inválido',
		'file-ext':      'La extensión del archivo seleccionado en el campo "%label" es inválida',
		'file-mime':     'El formato MIME del archivo seleccionado en el campo "%label" es inválido',
		'file-min-size': 'El tamaño mínimo del archivo para el campo "%label" debe ser de %param MB',
		'file-max-size': 'El tamaño máximo del archivo para el campo "%label" debe ser de %param MB',
		validate:        'El campo "%label" es inválido'
	};

	/**
	 * @private
	 *
	 * Funciones de validación para las reglas.
	 *
	 * @type {Object}
	 */
	FormValidator.prototype._rules = {

		// Verifica que el campo sea requerido
		required: function(value, param, field) {
			if (param === true) {
				if ((field.value !== null) && (field.value !== '')) {
					return true;
				} else {
					return false;
				}
			} else {
				return true;
			}
		},

		// Verifica que el campo sea del tipo especificado
		type: function(value, type, field) {

			// Obtiene la función validadora del tipo
			var typeFunction = this._customTypes[type] || this._types[type];

			// Verifica si la función validadora está definida
			if (helpers.isFunction(typeFunction)) {
				return typeFunction.apply(this, [ value, field ]);
			} else {
				return false;
			}
		},

		// Verifica que el campo coincida con otro
		match: function(value, field2Name, field) {

			// Obtiene el valor del campo a comparar
			var value2 = this.getValue(field2Name);
			if (value2) {
				return (value === value2);
			} else {
				return false;
			}
		},

		// Valida el largo mínimo especificado
		'min-length': function(value, length, field) {
			return (value.length >= parseInt(length, 10));
		},

		// Valida el largo máximo especificado
		'max-length': function(value, length, field) {
			return (value.length <= parseInt(length, 10));
		},

		// Valida que el largo sea igual al especificado
		length: function(value, length, field) {
			return (value.length === parseInt(length, 10));
		},

		// Valida el rango mínimo especificado
		'min-range': function(value, minRange, field) {
			return (parseFloat(value) >= parseFloat(minRange));
		},

		// Valida el rango máximo especificado
		'max-range': function(value, maxRange, field) {
			return (parseFloat(value) <= parseFloat(maxRange));
		},

		// Valida una expresión regular
		regex: function(value, regex, field) {
			return (regex.test(value));
		},

		// Valida el formato MIME de un archivo
		'file-ext': function(value, allowExt, field) {
			if ((field.type == 'file') && (field.rules.type == 'file')) {

				// Obtiene la extensión del archivo
				if (helpers.isFile(value)) {
					var valueExt = value.name;
				} else {
					var valueExt = value;
				}
				valueExt = valueExt.substr((valueExt.lastIndexOf('.') + 1)).toLowerCase();

				// Verifica si la extensión del archivo coincide con el especificado en la regla
				if (helpers.isArray(allowExt)) {

					// Pasa a minúsculas todos las extensiones especificadas para la regla
					for (var i in allowExt) {
						allowExt[i] = allowExt[i].toLowerCase();
					}

					return helpers.inArray(allowExt, valueExt);

				} else {
					return (valueExt == allowExt.toLowerCase());
				}

			} else {
				return false;
			}
		},

		// Valida el formato MIME de un archivo
		'file-mime': function(value, allowMime, field) {
			if ((field.type == 'file') && (field.rules.type == 'file')) {

				// Obtiene el formato MIME del archivo
				if (helpers.isFile(value)) {
					var valueMime = value.type.toLowerCase();
				} else {
					return true;
				}

				// Verifica si el formato MIME del archivo coincide con el especificado en la regla
				if (helpers.isArray(allowMime)) {

					// Pasa a minúsculas todos los formatos MIME especificados para la regla
					for (var i in allowMime) {
						allowMime[i] = allowMime[i].toLowerCase();
					}

					return helpers.inArray(allowMime, valueMime);

				} else {
					return (valueMime == allowMime.toLowerCase());
				}

			} else {
				return false;
			}
		},

		// Valida el tamaño mínimo de un archivo
		'file-min-size': function(value, minSize, field) {
			if ((field.type == 'file') && (field.rules.type == 'file')) {

				// Obtiene el tamaño del archivo y lo convierte en MB
				if (helpers.isFile(value)) {
					var valueSize = Number(value.size) / 1024 / 1024;
				} else {
					return true;
				}

				// Verifica si tamaño es mayor o igual al mínimo especificado en la regla
				return (valueSize >= minSize);

			} else {
				return false;
			}
		},

		// Valida el tamaño máximo de un archivo
		'file-max-size': function(value, maxSize, field) {
			if ((field.type == 'file') && (field.rules.type == 'file')) {

				// Obtiene el tamaño del archivo y lo convierte en MB
				if (helpers.isFile(value)) {
					var valueSize = Number(value.size) / 1024 / 1024;
				} else {
					return true;
				}

				// Verifica si tamaño es menor o igual al máximo especificado en la regla
				return (valueSize <= maxSize);

			} else {
				return false;
			}
		},

		// Regla que llama a una función validadora
		validate: function(value, callback, field) {
			if (helpers.isFunction(callback)) {
				if (callback.apply(this.context, [ value, field ]) === true) {
					return true;
				}
			}
			return false;
		}
	};

	/**
	 * @private
	 *
	 * Funciones validadoras para la regla type.
	 *
	 * @type {Object}
	 */
	FormValidator.prototype._types = {

		// Verifica que el valor sea de tipo email
		email: function(value, field) {
			return regex.email.test(value);
		},

		// Verifica que el valor contenga múltiples valores de tipo email
		emails: function(value, field) {
			var emails = value.split(/\s*,\s*/g);
			for (i in emails) {
				if (!regex.email.test(emails[i])) {
					return false;
				}
			}
			return true;
		},

		// Valida que el valor contenga solo caracteres de [a-z]
		letters: function(value, field) {
			return (regex.letters.test(value));
		},

		// Valida que el valor contenga solo caracteres de [a-z] y [-]
		'letters-dash': function(value, field) {
			return (regex.lettersDash.test(value));
		},

		// Valida que el valor contenga solo caracteres [0-9]
		numbers: function(value, field) {
			return (regex.numbers.test(value));
		},

		// Valida que el valor contenga solo caracteres de [0-9] y [-]
		'numbers-dash': function(value, field) {
			return (regex.numbersDash.test(value));
		},

		// Valida que el valor contenga números enteros positivos y negativos
		integer: function(value, field) {
			return (regex.integer.test(value));
		},

		// Valida que el valor contenga números decimales positivos y negativos
		float: function(value, field) {
			return (regex.float.test(value));
		},

		// Valida que el valor sea una url
		url: function(value, field) {
			return (regex.url.test(value));
		},

		// Valida que el valor contenga sea una ip
		ip: function(value, field) {
			return (regex.ip.test(value));
		},

		// Valida que el valor sea de tipo file
		file: function(value, field) {
			if ((field.type == 'file') && ((helpers.isFile(value)) || ((typeof value == 'string')))) {
				return true;
			} else {
				return false;
			}
		}
	};

	/**
	 * Registra una función callback que será llamada al cambiar el valor de cada uno de los campos del formulario.
	 *
	 * @return {this} Retorna la instancia para encadenar (chain)
	 */
	FormValidator.prototype.change = function(callback) {
		this._change = callback;
		return this;
	};

	/**
	 * Registra una función callback que será llamada al intentar enviar el formulario.
	 *
	 * @return {this} Retorna la instancia para encadenar (chain)
	 */
	FormValidator.prototype.always = function(callback) {
		this._always = callback;
		return this;
	};

	/**
	 * Registra una función callback que será llamada al enviar exitosamente el formulario.
	 *
	 * @return {this} Retorna la instancia para encadenar (chain)
	 */
	FormValidator.prototype.success = function(callback) {
		this._success = callback;
		return this;
	};

	/**
	 * Registra una función callback que será llamada cuando se envía el formulario y falla uno o más campos
	 *
	 * @return {this} Retorna la instancia para encadenar (chain)
	 */
	FormValidator.prototype.fail = function(callback) {
		this._fail = callback;
		return this;
	};

	/**
	 * Registra un conjunto de campos del formulario.
	 *
	 * @param  {Object} fields Conjunto de múltiples campos
	 * @return {this}   	   Retorna la instancia para encadenar (chain)
	 */
	FormValidator.prototype.addFields = function(fields) {

		// Agrega todos los campos
		for (fieldName in fields) {

			// Obtiene el campo
			var field = fields[fieldName];

			// Verifica si el nombre del campo representa a múltiples campos separados por coma
			var fieldName = fieldName.split(',');
			for (var i in fieldName) {
				this.addField(fieldName[i].trim(), field, false);
			}
		}

		// Sincroniza los campos con source
		for (fieldName in fields) {
			var field = fields[fieldName];
			if (field.source) {
				field.source.sync();
			}
		}

		return this;
	};

	/**
	 * Registra un campo del formulario.
	 *
	 * @param  {String}  fieldName    Nombre del campo
	 * @param  {Object}  fieldOptions Propiedades del objeto
	 * @param  {Boolean} sync         Indica si debe sincronizar luego de agregar el campo (Solo para campos con source)
	 * @return {this}   		          Retorna la instancia para encadenar (chain)
	 */
	FormValidator.prototype.addField = function(fieldName, fieldOptions, sync)  {
		var _this = this;

		// Obtiene el elemento
		var el = this.form.elements[fieldName];

		// Si el elemento existe lo registra
		if (el) {

			// Obtiene el tipo de campo
			if ((el.nodeName == undefined) && (el.length > 1)) {
				var type = el[0].getAttribute('type') || (el[0].nodeName).toLowerCase();
			} else {
				var type = el.getAttribute('type') || (el.nodeName).toLowerCase();
			}

			// Define el objeto del campo
			var newField = {
				el:         el,
				$el:		   	$(el),
				name:       fieldName,
				label:      fieldOptions.label || fieldName,
				rules:      fieldOptions.rules || {},
				messages:   fieldOptions.messages || {},
				source:   	fieldOptions.source || false,
				before:   	fieldOptions.before || undefined,
				modify:   	fieldOptions.modify || undefined,
				change:   	fieldOptions.change || undefined,
				type:       type,
				value:      null
			}

			// Evento change del campo
			newField._change = function(evt) {

				// Valida el campo
				var result = _this.checkField(newField.name);

				// Llama la función callback 'change'
				if (result == true) {
					if (helpers.isFunction(newField.change)) {
						newField.change.apply(_this.context, [ newField, undefined, evt ]);
					}
					if (helpers.isFunction(_this._change)) {
						_this._change.apply(_this.context, [ newField, undefined, evt ]);
					}
				} else {
					if (helpers.isFunction(newField.change)) {
						newField.change.apply(_this.context, [ newField, result, evt ]);
					}
					if (helpers.isFunction(_this._change)) {
						_this._change.apply(_this.context, [ newField, result, evt ]);
					}
				}
			}

			// Vincula el evento 'change' del campo
			if ((el.nodeName == undefined) && (el.length > 1)) {
				var elements = el;
			} else {
				var elements = [ el ];
			}
			for (var i = 0; i < elements.length; i++) {
				var el = elements[i];
				el.addEventListener('change', newField._change);
			};

			// Si el elemento tiene una fuente de datos para sincronizar y es de tipo select
			if ((newField.source) && (type == 'select')) {

				// Define el estado de sincronización
				newField.source.synced = false;

				// Extiende el método reset para el campo
				newField.reset = function() {
					newField.$el.html('');
					if ('empty' in newField.source) {
						newField.$el.append('<option value="" selected>' + newField.source.empty + '</option>');
					}
				}

				// Extiende el método sync para sincronizar el campo
				newField.source.sync = function() {

					// Aborta cualquier request XHR en curso
					newField.source.xhr.abort();

					// Verifica los campos vinculados
					var data = {};
					for (j in newField.source.bind) {

						// Obtiene el campo vinculado
						var bind = newField.source.bind[j];

						// Si el campo vinculado no está sincronizado, finaliza la sincronización del presente campo
						if (bind.field.source && bind.field.source.synced == false) {
							return;
						}

						// Obtiene el valor del campo vinculado
						data[bind.alias] = _this.getValue(bind.field.name);
					}

					// Ejecuta el XHR y pasa los valores de los campos vinculados
					newField.source.xhr.perform(data);
				}

				// Crea la instancia XHR del campo
				newField.source.xhr = new fwXHR({
					url: newField.source.url,
					context: this
				});

				// Evento start del XHR
				newField.source.xhr.on('start', function() {
					newField.source.synced = false;
					newField.reset();
				});

				// Evento 'done' del XHR
				newField.source.xhr.on('done', function(items) {

					// Agrega todos los items
					for (i in items) {
						newField.$el.append('<option value="' + items[i].value + '">' + items[i].caption + '</option>');
					}

					// Verifica si hay un valor default para seleccionar al iniciar
					if (newField.$el.data('default')) {
						newField.$el.val(newField.$el.data('default'));
						newField.$el.removeAttr('data-default').removeData('default');
					}

					// Actualiza el estado de sincronización
					newField.source.synced = true;

					// Dispara el evento change del campo
					newField.$el.change();
				});

				// Determina si la sincronización debe dispararse cuando cambien otros campos
				if ('bind' in newField.source) {

					// Si el campo vinculado es string
					if (helpers.isString(newField.source.bind)) {
						newField.source.bind = Array(newField.source.bind);
					}

					// Recorre todos los campos a vincular
					var binds = Array();
					for (i in newField.source.bind) {

   					// Defermina el campo a vincular y el alias que se le asignará al realizar el request
						var bind = {};
						if (helpers.isObject(newField.source.bind)) {
							bind.alias = newField.source.bind[i];
							bind.field = this.getField(i)
						} else {
							bind.alias = newField.source.bind[i];
							bind.field = this.getField(newField.source.bind[i]);
						}

						// Verifica que el campo a vincular esté definido en el validador y que sea de tipo select
						if ((bind.field) && (bind.field.type == 'select')) {

							// Captura el evento 'change' del campo vinculado
							bind.field.$el.on('change', function() {
								newField.source.sync();
							});

							// Agrega el campo a vincular
							binds.push(bind);
						}
					}

					// Agrega todos los campos a vincular
					newField.source.bind = binds;

				} else {
					newField.source.bind = false;
				}
			} else {
				newField.source = false;
			}

			// Agrega el campo al array de campos
			this._fields.push(newField);

			// Determina si debe sincronizar el campo
			if ((newField.source) && (sync !== false)) {
				newField.source.sync();
			}
		}
		return this;
	};

	/**
	 * Remueve todos los campos del formulario.
	 *
	 * @return {this} Retorna la instancia para encadenar (chain)
	 */
	FormValidator.prototype.removeFields = function() {
		for (i in this._fields) {

			// Campo a remover
			var field = this._fields[i];

			// Verifica si el campo corresponde a múltiples elementos en el DOM
			if ((field.el.nodeName == undefined) && (field.el.length > 1)) {
				var elements = field.el;
			} else {
				var elements = [ field.el ];
			}

			// Desvincula el evento change de cada elemento
			for (var j = 0; j < elements.length; j++) {
				var el = elements[j];
				el.removeEventListener('change', field._change);
			}

			// Si el campo tiene una fuente de datos, remueve el XHR y eventos change asociado
			if (field.source && field.source.onBindedChange) {
				field.source.xhr.abort();
				delete field.source.xhr;
				if (field.source.bind) {
					for (j in field.source.bind) {
						field.source.bind[j].field.$el.off('change');
					}
				}
			}
		}

		// Reinicia la propiedad _fields de la instancia
		delete this._fields;
		this._fields = Array();

		return this;
	};

	/**
	 * Valida un campo del formulario.
	 *
	 * @param  {String}      fieldName Nombre de un campo registrado del formulario
	 * @return {Object|true} 		   Devuelve true en caso de ser válido o devuelve un Object con los datos del error cuando falló por alguna regla
	 */
	FormValidator.prototype.checkField = function(fieldName) {

		// Obtiene el campo a validar
		var field = this.getField(fieldName);
		if (!field) {
			return undefined;
		}

		// Obtiene el valor actual del campo
		this.getValue(field.name);

		// Valida el campo
		var result = this._checkField(field);

		// Devuelve el resultado de la validación del campo
		return result;
	};

	/**
	 * Valida un campo del formulario.
	 *
	 * @param  {String}          fieldName Nombre de un campo registrado del formulario
	 * @return {mixed|undefined}           Devuelve un boolean indicando si es válido o undefined al no encontrar el campo especificado
	 */
	FormValidator.prototype.isValid = function(fieldName) {

		// Obtiene el campo a validar
		var field = this.getField(fieldName);
		if (!field) {
			return undefined;
		}

		// Obtiene el valor actual del campo
		this.getValue(field.name);

		// Valida el campo
		if (this._checkField(field) === true) {
			return true;
		} else {
			return false;
		}
	};

	/**
	 * Obtiene el valor actual de un campo registrado del formulario.
	 *
	 * @param  {String}          fieldName Nombre de un campo registrado del formulario
	 * @return {mixed|undefined}           Devuelve el valor del campo o undefined al no encontrar el campo especificado
	 */
	FormValidator.prototype.getValue = function(fieldName) {

		// Obtiene el campo
		var field = this.getField(fieldName);
		if (!field) {
			return undefined;
		}

		// Inicialmente asigna valor null al campo
		field.value = null;

		// Verifica si extiste el callback para modificar previo el valor del campo
		if (helpers.isFunction(field.before)) {
			field.value = field.before.apply(this.context, [ field.value, field ]);
		}

		// Obtiene el valor del campo en base a su tipo
		switch(field.type) {
			case 'file':

				// Obtiene el archivo o los archivos seleccionados
				var values = Array();
				if (('files' in field.el) && (field.el.files.length > 0)) {

					// Agrega todos los valores de los archivos
					for (var i = 0; i < field.el.files.length; i++) {
						values.push(field.el.files[i]);
					}
					field.value = values;

				} else {
					field.value = field.el.value;
				}
				break;

			case 'radio':
				if (field.el.length > 1) {
					for (var i = 0; i < field.el.length; i++) {
						if (field.el[i].checked) {
							field.value = field.el[i].value;
							break;
						}
					};
				} else {
					if (field.el.checked) {
						field.value = field.el.value;
					}
				}
				break;

			case 'checkbox':

				// Obtiene el valor o los valores
				var values = Array();
				if (field.el.length > 1) {
					for (var i = 0; i < field.el.length; i++) {
						if (field.el[i].checked) {
							values.push(field.el[i].value);
						}
					};
				} else {
					if (field.el.checked) {
						values.push(field.el.value);
					}
				}
				if (values.length > 0) {
					field.value = values;
				}
				break;

			case 'select':

				// Obtiene el valor o los valores
				if (field.el.hasAttribute('multiple')) {
					var values = Array();
					if (field.el.length > 1) {
						for (var i = 0; i < field.el.length; i++) {
							if (field.el[i].selected) {
								values.push(field.el[i].value);
							}
						};
					} else {
						values.push(field.el.value);
					}
					if (values.length > 0) {
						field.value = values;
					}
				} else {
					field.value = field.el.value;
				}
				break;

			default:

				// Obtiene el valor del campo
				field.value = field.el.value;
		}

		// Verifica si extiste el callback para modificar el valor del campo
		if (helpers.isFunction(field.modify)) {
			field.value = field.modify.apply(this.context, [ field.value, field ]);
		}
		return field.value;
	};

	/**
	 * Devuelve el primer error producido después de validar el formulario.
	 *
	 * @return {Object|false} Objeto con propiedades sobre el error o false en el caso de no existir error
	 */
	FormValidator.prototype.getError = function() {
		if (this._errors.length > 0) {
			return this._errors[0];
		} else {
			return false;
		}
	}

	/**
	 * Devuelve todos los errores producidos después de validar el formulario.
	 *
	 * @return {Array} Objeto con propiedades sobre el error
	 */
	FormValidator.prototype.getErrors = function() {
		return this._errors;
	}

	/**
	 * Método para enviar el formulario.
	 *
	 * @return {void}
	 */
	FormValidator.prototype.submit = function() {
		this.form.submit();

	}
	/**
	 * Método para validar el formulario.
	 *
	 * @param  {Function} callback Función callback llamada al finalizar la validación
	 * @param  {Object}   evt      Evento de javascript pasado al enviar el formulario
	 * @return {Boolean}           Indica si el formulario se validó exitosamente
	 */
	FormValidator.prototype.validate = function(callback, evt) {

		try {

			// Remueve los errores previos
			this._errors = Array();

			// Obtiene el valor de todos los campos
			for (var i in this._fields) {

				// Obtiene el valor del campo
				this.getValue(this._fields[i].name);
			}

			// Valida las reglas de cada campo
			for (var i in this._fields) {

				// Obtiene el campo a validar
				var field = this._fields[i];

				// Verifica que el elemento no esté deshabilitado
				if (!field.el.disabled) {

					// Valida el campo
					var result = this._checkField(field);

					// Agrega el error en caso de haberse producido uno al validar el campo
					if (result != true) {
						this._errors.push(result);
					}
				}
			}

			// Construye un objeto a pasar con todos los valores de los campos del formulario
			var values = new Object();
			for (var i in this._fields) {
				if (!this._fields[i].el.disabled) {
					values[this._fields[i].name] = this._fields[i].value;
				}
			}

			// Llama la función callback 'always'
			if (helpers.isFunction(this._always)) {
				this._always.apply(this.context, [ values, this.getErrors(), evt ]);
			}

			// Llama las funciones callback 'success' o 'fail'
			if (this._errors.length == 0) {

				if (helpers.isFunction(this._success)) {
					this._success.apply(this.context, [ values, evt ]);
				}

			} else {
				if (helpers.isFunction(this._fail)) {
					this._fail.apply(this.context, [ this.getErrors(), evt ]);
				}
			}

			// Llama a la función callback pasada al llamar este método
			if (helpers.isFunction(callback)) {
				callback.apply(this.context, [ values, this.getErrors(), evt ]);
			}

			if (this._errors.length > 0) {
				return false;
			} else {
				return true;
			}

		} catch(e) {
			return false;
		}
	};

	/**
	 * Reemplaza en el prototipo o en esta instancia un mensaje default para una regla existente.
	 *
	 * @param  {String}   ruleName    Nombre de la nueva regla
	 * @param  {String}   ruleMessage Mensaje de default de la regla
	 * @return {this}                 Retorna la instancia para encadenar (chain)
	 */
	FormValidator.prototype.replaceMessage = function(ruleName, ruleMessage) {

		// Verifica si debe registrar el mensaje en la instancia o en el prototipo
		if (this._customMessages === undefined) {
			var messages = FormValidator.prototype._messages;
		} else {
			var messages = this._customMessages;
		}

		// Registra el mensaje
		messages[ruleName] = ruleMessage;
		return this;
	}

	/**
	 * Registra en el prototipo o en esta instancia una nueva regla.
	 *
	 * @param  {String}   ruleName      Nombre de la nueva regla
	 * @param  {String}   ruleMessage   Mensaje de default de la regla
	 * @param  {Function} ruleValidator Función validadora de la regla
	 * @return {this}                   Retorna la instancia para encadenar (chain)
	 */
	FormValidator.prototype.extendRule = function(ruleName, ruleMessage, ruleValidator) {

		// Verifica si debe registrar la regla y el mensaje en la instancia o en el prototipo
		if (this._customRules === undefined) {
			var rules = FormValidator.prototype._rules;
			var messages = FormValidator.prototype._messages;
		} else {
			var rules = this._customRules;
			var messages = this._customMessages;
		}

		// Registra la regla y el mensaje
		rules[ruleName] = ruleValidator;
		messages[ruleName] = ruleMessage;
		return this;
	}

	/**
	 * Registra en el prototipo o en esta instancia un nuevo tipo para la regla type.
	 *
	 * @param  {String}   typeName      Nombre del tipo a agregar
	 * @param  {Function} typeValidator Función validadora del tipo
	 * @return {this}   		  	    Retorna la instancia para encadenar (chain)
	 */
	FormValidator.prototype.extendType = function(typeName, typeValidator) {

		// Verifica si debe registrar la regla tipo en la instancia o en el prototipo
		if (this._customRules === undefined) {
			FormValidator.prototype._types[typeName] = typeValidator;
		} else {
			this._customTypes[typeName] = typeValidator;
		}
		return this;
	}

	/**
	 * Devuelve los datos de un campo registrado.
	 *
	 * @param  {String}           fieldName Nombre del campo
	 * @return {Object|undefined} Objeto con los datos del campo, undefined en caso de no estar registrado
	 */
	FormValidator.prototype.getField = function(fieldName) {
		for (var i in this._fields) {
			if (fieldName == this._fields[i].name) {

				// Actualiza el elemento del campo (Necesario por si sufrió alguna modificación dinámicamente)
				this._fields[i].el = this.form.elements[fieldName];

				// Devuelve el campo
				return this._fields[i];
			}
		}
		return undefined;
	}

	/**
	 * @private
	 *
	 * Valida todas las reglas especificadas para un campo.
	 *
	 * @param  {Object}      field Objeto con todas las propiedades del campo
	 * @return {Object|true} 	   Devuelve true en caso de ser válido o devuelve un Object con los datos del error cuando falló por alguna regla
	 */
	FormValidator.prototype._checkField = function(field) {

		// Error producido
		var error = undefined;

		// Identifica si el campo está vacío
		var isEmpty = (field.value === '') || (field.value === null);

		// Valida cada una de las reglas del campo en el orden en que fueron especificadas
		for (ruleName in field.rules) {

			// Indicador si la regla ha fallado
			var failed = false;

			// Salta las validaciones si el campo está vacío y no es requerido
			if ((isEmpty) && (field.rules.required !== true)) {
				continue;
			}

			// Obtiene la función de validación de la regla
			var ruleValidator = this._customRules[ruleName] || this._rules[ruleName];

			// Verifica si la función validadora está definida
			if (typeof ruleValidator === 'function') {

				// Obtiene el parámetro a pasar al validador de la regla
				var param = field.rules[ruleName];

				// Valida la regla pasando el valor, campo y su parámetro
				try {

					// Si el valor del campo es un array, llama al validador de la regla por cada uno de sus valores
					if (helpers.isArray(field.value)) {
						for (var j in field.value) {
							if (ruleValidator.apply(this, [ field.value[j], param, field ]) !== true) {
								failed = true;
							}
						}

					} else {
						if (ruleValidator.apply(this, [ field.value, param, field ]) !== true) {
							failed = true;
						}
					}

				} catch(e) {
					failed = true;
				}
			}

			// Si la regla ha fallado, agrega el error correspondiente a la regla en el array de errores
			if (failed) {

				// Se obtiene el mensaje de error de la regla que ha fallado
				var errorMessage = field.messages[ruleName] || this._customMessages[ruleName] || this._messages[ruleName] || ('Ha ocurrido un error con el campo ' + (field.label || field.name));

				// Reemplaza ciertas variables en el mensaje de error de la regla
				errorMessage = errorMessage.replace('%label', (field.label || field.name));
				errorMessage = errorMessage.replace('%param', param);

				// Registra el error, en caso de ya haberse producido un error previo solo se agrega el nuevo mensaje de error
				if (error) {
					error.messages.push(errorMessage);
				} else {
					error = {
						field:    field,
						rule:     ruleName,
						message:  errorMessage,
						messages: [ errorMessage ]
					};
				}
			}
		}

		// Retorna true en caso de ser válido o devuelve el error en el caso contrario
		if (!error) {
			return true;
		} else {
			return error;
		}
	};

	return FormValidator;
});
