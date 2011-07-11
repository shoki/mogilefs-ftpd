<?php

/*
****************************************************
* nanoFTPd - an FTP daemon written in PHP          *
****************************************************
* this file is licensed under the terms of GPL, v2 *
****************************************************
* developers:                                      *
*  - Andre Pascha <bender@duese.org>			   *
*  - Arjen <arjenjb@wanadoo.nl>                    *
*  - Phanatic <linux@psoftwares.hu>                *
****************************************************
* http://sourceforge.net/projects/nanoftpd/        *
****************************************************
*/

$CFG = new stdClass();
$CFG->rootdir 			= dirname(__FILE__);		// nanoFTPd root directory
$CFG->libdir 			= "$CFG->rootdir/lib";			// nanoFTPd lib/ directory
$CFG->moddir 			= "$CFG->rootdir/modules";		// nanoFTPd modules/ directory
$CFG->tmpdir			= "$CFG->rootdir/tmp";		// temporary files direcotry
$CFG->logdir			= "$CFG->rootdir/log";		// temporary files direcotry
ini_set('include_path', get_include_path().":".dirname(__FILE__).":".$CFG->libdir.":".$CFG->moddir);

$CFG->daemonize		= false;					// wether to daemonize or not
$CFG->pidfile		= "$CFG->logdir/nanoftpd.pid";	// pidfile store
ini_set('error_log', $CFG->logdir."/nanoftpd.err");	// PHP error log
$CFG->userid		= 0;
$CFG->groupid		= 0;
$CFG->listen_addr 		= "0.0.0.0";			// IP address where nanoFTPd should listen
$CFG->listen_port 		= 21;					// port where nanoFTPd should listen
$CFG->low_port			= 15000;
$CFG->high_port			= 25000;
$CFG->idle_time		= 300;					// close conn after this amount of seconds when inactive
$CFG->io			= "MogileFs";				// io module (default: file) -- note: ips doesn't work
$CFG->server_name 		= "nanoFTPd server [OMFG]";		// nanoFTPd server name

$CFG->auth = new stdClass();
$CFG->auth->module = 'Text';
$CFG->auth->crypt = 'md5';

$CFG->auth->text = new stdClass();
$CFG->auth->text->file = $CFG->rootdir.'/users';
$CFG->auth->text->sep = ':';

/* mogilefs tracker/domain settings */
$CFG->mogilefs = new stdClass();
$CFG->mogilefs->tracker = 'vmtrack1';
$CFG->mogilefs->port = 7001;
$CFG->mogilefs->domain = 'blockoland';
$CFG->mogilefs->defaultclass = 'default';
$CFG->mogilefs->timeout = 3;
$CFG->mogilefs->listlimit = 1000;				// LIST entries limit
$CFG->mogilefs->extendedlist = false;			// when on always lookup metadata for listed files (eg. size)
$CFG->mogilefs->searchlist = false;				// enable searching in mogilefs via listKeys() when passing args to LIST 
$CFG->mogilefs->canrename = true;				// enable rename commands
$CFG->mogilefs->canmkdir = true;				// create mogile class on mkdir
$CFG->mogilefs->canrmdir = true;				// delete mogile class on rmdir
$CFG->mogilefs->mindevcount = 2;				// mindevcount for create class

$CFG->logging = new stdClass();
$CFG->logging->mode		= 2;					// 0 = no logging, 1 = to file (see directive below), 2 = to console, 3 = both
$CFG->logging->file		= "$CFG->rootdir/log/nanoftpd.log";	// the file where nanoFTPd should log the accesses & errors

?>
