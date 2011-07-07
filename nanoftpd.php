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
include($CFG->libdir."/server.php");
include($CFG->libdir."/client.php");
include($CFG->libdir."/daemon.php");

class NanoFTP_Daemon extends Daemon {
	protected $server;

	public function __construct() {
		global $CFG;

		$this->pidFileLocation = $CFG->pidfile;
		$this->userID = $CFG->userid;
		$this->groupID = $CFG->groupid;

		parent::__construct();
	}

	public function _doTask() {
		global $CFG;

		declare(ticks = 1);
		pcntl_signal(SIGCHLD, array($this, 'sigHandler'));
		pcntl_signal(SIGTERM, array($this, 'sigHandler'));

		$this->server = new server($CFG);
		$this->server->run();

	}

	public function sigHandler($signo) {
		switch ($signo) {
			case SIGTERM:
				// cleanup childs and exit
				$this->_logMessage("exiting on SIGTERM");
				/* father will kill its childs */
				if (!$this->server->isChild) {
					$this->server->broadcast_signal($signo);
					// XXX should wait here until all childs are gone
					$this->releaseDaemon();
				}
				exit(0);
				break;
			case SIGCHLD:
				$this->server->reaper();
				break;
			default:
				$this->_logMessage("unknown signal: ".$signo);
				break;
		}
	}

	public function _logMessage($msg, $level = DLOG_NOTICE) {
		if (is_object($this->server) && is_object($this->server->log)) $this->server->log->write($msg."\n");
		else error_log($msg);
	}
}

if (!extension_loaded('sockets')) dl('sockets.so');
if (!extension_loaded('pcntl')) dl('pcntl.so');

/* make sure we are not handling all strings as UTF8. binary files are
 * not utf8 encoded and will be broken when using strlen() to get file
 * size */
if (ini_get('mbstring.func_overload') & 2) {
	mb_internal_encoding('latin1');
}

if ($CFG->daemonize) {
	$daemon = new NanoFTP_Daemon();
	$daemon->start();
} else {
	$server = new server($CFG);
	$server->run();
}

?>
