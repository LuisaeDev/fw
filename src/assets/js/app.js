define('app', [ 'fw', 'fw/storage', 'mods/auth/handler', 'fw/helpers' ], function(fw, storage, authHandler, helpers) {

	// Se establecen las URL's para el handler de autenticación
	authHandler.loginURL = fw.baseUrl('/login');
	authHandler.logoutURL = fw.baseUrl('/logout');

	// Evento al realizar un login
	authHandler.on('login-response', function(resp) {
		if (resp && resp.success) {

			// Almacena en local los datos del usuario en sesión
			storage.local.set('logged-user', resp.user);
		}
	});

	// Evento al realizar un logout
	authHandler.on('logout-response', function(resp) {
		if (resp && resp.success) {

			// Remueve los datos del usuario en sesión
			storage.local.unset('logged-user');

			// Redirige a la URL base
			window.location.replace(fw.baseUrl());
		}
	});
});
