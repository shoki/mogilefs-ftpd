<?php

class NanoFTP_Daemon extends APA_Daemon {
	protected $server;

	public function __construct($CFG) {
		$this->CFG = $CFG;
		$this->pidFileLocation = $CFG->pidfile;
		$this->userID = $CFG->userid;
		$this->groupID = $CFG->groupid;

		parent::__construct();
	}

	public function _doTask() {
		declare(ticks = 1);
		pcntl_signal(SIGCHLD, array($this, 'sigHandler'));
		pcntl_signal(SIGTERM, array($this, 'sigHandler'));

		$this->server = new NanoFTP_Server($this->CFG);
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

?>
