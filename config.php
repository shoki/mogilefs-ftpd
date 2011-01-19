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
****************************************************
* http://sourceforge.net/projects/nanoftpd/        *
****************************************************
*/

error_reporting(E_ALL);
set_time_limit(0);

ini_set('include_path', get_include_path().":".dirname(__FILE__).":/www/kwick/lib");

class object{}
$CFG = new object();

$CFG->dbuser 			= "nanoftpd";				// user connecting to the database
$CFG->dbpass 			= "nanoftpd";				// password of that user
$CFG->dbhost 			= "localhost";				// host to connect to
$CFG->dbname 			= "nanoftpd";				// name of the database

$CFG->dbtype			= "text";				// database type - currently available: mysql, pgsql (postgresql) and text

$CFG->table	= array();
$CFG->table['name']		= "users";				// name of the table which holds user data
$CFG->table['username']		= "username";				// name of username field
$CFG->table['password']		= "password";				// name of password field
$CFG->table['uid']		= "uid";				// name of uid field
$CFG->table['gid']		= "gid";				// name of gid field


$CFG->crypt			= "md5";				// password encryption method ("plain" or "md5")

$CFG->rootdir 			= dirname(__FILE__);		// nanoFTPd root directory
$CFG->libdir 			= "$CFG->rootdir/lib";			// nanoFTPd lib/ directory
$CFG->moddir 			= "$CFG->rootdir/modules";		// nanoFTPd modules/ directory
$CFG->tmpdir			= "$CFG->rootdir/tmp";		// temporary files direcotry
$CFG->logdir			= "$CFG->rootdir/log";		// temporary files direcotry

$CFG->daemonize		= true;					// wether to daemonize or not
$CFG->pidfile		= "$CFG->logdir/nanoftpd.pid";	// pidfile store
ini_set('error_log', $CFG->logdir."/nanoftpd.err");	// PHP error log
$CFG->userid		= 0;
$CFG->groupid		= 0;

$CFG->text			= array();				// textfile-based user authentication -- see docs/README.text
$CFG->text['file']		= $CFG->rootdir."/users";		// path to file which holds user data
$CFG->text['sep']		= ":";					// the character which separates the columns


$CFG->listen_addr 		= "62.146.70.152";			// IP address where nanoFTPd should listen
$CFG->listen_port 		= 2121;					// port where nanoFTPd should listen
$CFG->low_port			= 15000;
$CFG->high_port			= 25000;
$CFG->max_conn			= 10;					// max number of connections allowed
$CFG->max_conn_per_ip	= 3;						// max number of connections per ip allowed
$CFG->idle_time		= 60;					// close conn after this amount of seconds when inactive
$CFG->io			= "mogilefs";				// io module (default: file) -- note: ips doesn't work
$CFG->server_name 		= "nanoFTPd server [APA mogilified]";		// nanoFTPd server name

$CFG->dynip			= array();				// dynamic ip support -- see docs/REAME.dynip
$CFG->dynip['on']		= 0;					// 0 = off (use listen_addr directive) 1 = on (override listen_addr directive)
$CFG->dynip['iface']	= "ppp0";					// interface connecting to the internet

$CFG->logging = new object;
$CFG->logging->mode		= 1;					// 0 = no logging, 1 = to file (see directive below), 2 = to console, 3 = both
$CFG->logging->file		= "$CFG->rootdir/log/nanoftpd.log";	// the file where nanoFTPd should log the accesses & errors


require($CFG->libdir."/db_".$CFG->dbtype.".php");
require("$CFG->libdir/pool.php");
require("$CFG->libdir/auth.php");
require("$CFG->libdir/log.php");

$CFG->pasv_pool = new pool();
$CFG->log 		= new log($CFG);
if ($CFG->dbtype != "text") $CFG->dblink = db_connect($CFG->dbhost, $CFG->dbname, $CFG->dbuser, $CFG->dbpass);


?>