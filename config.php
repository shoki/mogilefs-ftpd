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
* https://github.com/shoki/mogilefs-ftpd 		   *
* http://sourceforge.net/projects/nanoftpd/        *
****************************************************
*/

$CFG = new stdClass();
$CFG->rootdir = dirname(__FILE__);			// nanoFTPd root directory
$CFG->libdir = "$CFG->rootdir/lib";			// nanoFTPd lib/ directory
$CFG->moddir = "$CFG->rootdir/modules";		// nanoFTPd modules/ directory
$CFG->tmpdir = "$CFG->rootdir/tmp";			// temporary files direcotry
$CFG->logdir = "$CFG->rootdir/log";			// log and pid files
ini_set('include_path', get_include_path().":".dirname(__FILE__).":".$CFG->libdir.":".$CFG->moddir);

$CFG->daemonize	= false;						// wether to daemonize or not
$CFG->pidfile = "$CFG->logdir/nanoftpd.pid";	// pidfile store
ini_set('error_log', $CFG->logdir."/nanoftpd.err");	// PHP error log
$CFG->userid = 0;							// userid of daemon user
$CFG->groupid = 0;							// groupid of daemon user
$CFG->listen_addr = "0.0.0.0";				// IP address where nanoFTPd should listen
$CFG->listen_port = 2121;						// port where nanoFTPd should listen
$CFG->low_port = 15000;						// lowest PASV port
$CFG->high_port = 25000;					// highest PASV port
$CFG->idle_time	= 300;						// close conn after this amount of seconds when inactive
$CFG->server_name = "nanoFTPd server [OMFG]";		// nanoFTPd server name

// Authentication module configuration
$CFG->auth = new stdClass();
$CFG->auth->module = 'Text';
$CFG->auth->crypt = 'md5';

// Text file based authentication settings
$CFG->auth->text = new stdClass();
$CFG->auth->text->file = $CFG->rootdir.'/users';
$CFG->auth->text->sep = ':';

// mogilefs tracker/domain settings 
$CFG->mogilefs = new stdClass();
$CFG->mogilefs->tracker = 'vmtrack1';
$CFG->mogilefs->port = 7001;
$CFG->mogilefs->domain = 'blockoland';			// default domain for tracker connection
$CFG->mogilefs->timeout = 3;					// connect timeout for tracker
$CFG->mogilefs->defaultclass = 'default';		// default class to use in MogileFs_FlatNS uploads
$CFG->mogilefs->listlimit = 1000;				// LIST entries limit
$CFG->mogilefs->extendedlist = false;			// when on always lookup metadata for listed files (eg. size)
$CFG->mogilefs->searchlist = false;				// enable searching in mogilefs via listKeys() when passing args to LIST 
$CFG->mogilefs->canrename = true;				// enable rename commands
$CFG->mogilefs->canmkdir = true;				// create mogile class on mkdir
$CFG->mogilefs->canrmdir = true;				// delete mogile class on rmdir
$CFG->mogilefs->mindevcount = 2;				// mindevcount for create class

// logging configuration
$CFG->logging = new stdClass();
$CFG->logging->mode	= 2;					// 0 = no logging, 1 = to file (see directive below), 2 = to console, 3 = both
if ($CFG->daemonize) $CFG->logging->mode = 1;	// in daemon mode always log to file only
$CFG->logging->file	= "$CFG->rootdir/log/nanoftpd.log";	// the file where nanoFTPd should log the accesses & errors

?>
