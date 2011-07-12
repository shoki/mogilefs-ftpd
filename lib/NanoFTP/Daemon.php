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
				break;
			default:
				$this->_logMessage("unknown signal: ".$signo);
				break;
		}
	}

	public function _logMessage($msg, $level = DLOG_NOTICE) {
		error_log($msg);
	}
}

?>
