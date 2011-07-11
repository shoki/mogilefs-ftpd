#!/usr/bin/env php
<?php

/*
****************************************************
* nanoFTPd - an FTP daemon written in PHP          *
****************************************************
* this file is licensed under the terms of GPL, v2 *
****************************************************
* developers:                                      *
*  - Arjen <arjenjb@wanadoo.nl>                    *
*  - Phanatic <linux@psoftwares.hu>                *
*  - Andre Pascha <andre.pascha@kwick.de>		   *
****************************************************
* http://sourceforge.net/projects/nanoftpd/        *
****************************************************
*/

include(dirname(__FILE__)."/config.php");

if (!extension_loaded('sockets')) dl('sockets.so');
if (!extension_loaded('pcntl')) dl('pcntl.so');

/* make sure we are not handling all strings as UTF8. binary files are
 * not utf8 encoded and will be broken when using strlen() to get file
 * size */
if (extension_loaded('mbstring') && ini_get('mbstring.func_overload') & 2) {
	mb_internal_encoding('latin1');
}

error_reporting(E_ALL);
set_time_limit(0);

function __autoload($class_name) {
	$name = str_replace("_", "/", $class_name);
    require_once $name . '.php';
}

if ($CFG->daemonize) {
	$daemon = new NanoFTP_Daemon($CFG);
	$daemon->start();
} else {
	$server = new NanoFTP_Server($CFG);
	$server->run();
}

?>
