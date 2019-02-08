/**
 * Define para el Framework las cookies relacionadas a la zona horaria.
 */
define('fw/tz', [ 'fw/storage' ], function(storage) { return {

	// Define el offset de la zona horaria del cliente
	setCookieTzOffset: function() {
		
		// Almacena el tiempo offset en segundos
		var d = new Date();
		var offset = -1*d.getTimezoneOffset();
		storage.cookie.set('fw_tz_offset', (offset * 60), 1);

		// Define si se está usando horario de verano
		var milliSeconds = 0; 
		var offset1 = new Date(2012, 01, 25, 24, 00, 00, milliSeconds).getTimezoneOffset(); 
		var offset2 = new Date(2012, 01, 25, 24, 00, 00, milliSeconds - 1).getTimezoneOffset();
		if (offset1 != offset2) {
			var dst = 1;
		} else {
			var dst = 0;
		}
		storage.cookie.set('fw_tz_offset_dst', dst, 1);
	},

	// Se obtiene la zona horaria por ip
	setCookieTz: function() {
		$.getJSON('http://ip-api.com/json')
		.done(function(res) {
			if (res.status == 'success') {
				storage.session.set('location', res);
				storage.cookie.set('fw_tz', res.timezone, 1);
			}
		});
	}
}});
