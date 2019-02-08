<?php
/**
 * Voyager Framework
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

/**
 * Datos y conexiones a las múltiples bases de datos utilizadas por la aplicación web.
 */

namespace Fw;

/**
 * Datos de conexión a la base de datos mySQL del Framework.
 */
QueryBuilder::setDataConnection('fw', array(
	'dbname' => 'fw',
	'user'   => 'root',
	'pass'   => '',
	'engine' => 'mysql',
	'host'   => '127.0.0.1',
	'port'   => 3306
));

/**
 * Conexión a la base de datos usada por el Framework.
 */
dbConnection('fw', function() {
	return new QueryBuilder('fw');
});

/**
 * Conexión a la base de datos Redis del Framework.
 */
dbConnection('fw:redis', function() {
	$redis = new \Redis();
	$redis->connect('127.0.0.1', 6379);
	return $redis;
});
?>
