<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

/**
 * Definición de paths y assets.
 */

namespace Fw;

// Paths
paths(array(

	// Ruta base donde reside el Framework
	'baseUrl' => function() {
		if (Conf::getParam('dist')) {
			return '/';
		} else {
			return '/fw/dev';
		}
	}
));

// Assets
assets(array(
	'components.js' => '@baseUrl/components/components.js',
	'fw.js'         => '@baseUrl/assets/js/fw.js',
	'styles.css'    => '@baseUrl/assets/css/styles.css'
));
?>
