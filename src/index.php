<?php
/**
 * Voyager Framework
 * @version 1.0.0
 * @copyright 2019, Luis Aguilar
 * @author Luis Aguilar <hola@luisaguilar.me>
 */

/** Directorio de instalación del framework */
define('FW_ROOT', rtrim(str_replace('\\', '/', __DIR__), '/'));

/** Carga el autoloader de Composer */
require_once './vendor/autoload.php';

/** Carga e inicializa el framework */
require_once './fw/Fw.php';
Fw\Fw::initialize();
