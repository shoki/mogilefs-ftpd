<?php

class NanoFTP_Server {

	public $CFG;

	protected $socket;
	protected $clients;
	
	public $log;
	public $isClient = false;

	protected $scheduler = null;

	protected $pids = array();

	public function __construct($CFG) {

		$this->CFG = $CFG;
		$this->CFG->log = $this->log = new NanoFTP_Log($CFG);
		$this->log->setPrefix("[".getmypid()."]");
		$this->init();
	}

	public function init() {
		$this->isClient = false;

		$this->socket = false;
		$this->clients = Array();

		$this->scheduler = APA_TimerScheduler::get();
		$this->setProcTitle("nanoftpd [master]");

		pcntl_signal(SIGCHLD, array($this, 'reaper'));
	}

	protected function setup_socket() {
		// assign listening socket 
		if (! ($this->socket = @socket_create(AF_INET, SOCK_STREAM, 0)))
			$this->socket_error();

		// reuse listening socket address 
		if (! @socket_setopt($this->socket, SOL_SOCKET, SO_REUSEADDR, 1))
			$this->socket_error();

		// set socket to non-blocking 
		if (! @socket_set_nonblock($this->socket))
			$this->socket_error();

		// bind listening socket to specific address/port 
		if (! @socket_bind($this->socket, $this->CFG->listen_addr, $this->CFG->listen_port))
			$this->socket_error();

		// listen on listening socket
		if (! socket_listen($this->socket))
			$this->socket_error();

	}

	public function run() {
		$this->setup_socket();
		// set initial vars and loop until $abort is set to true
		$abort = false;
		$sleep = 1;	/* default select timeout */

		while (! $abort) {
			// sockets we want to pay attention to
			$set_array = array(0 => $this->socket);
			
			$set = $set_array;
			// avoid warnings about EINTR
			if (@socket_select($set, $set_w = NULL, $set_e = NULL, $sleep, 0) > 0) {
				
				// loop through sockets
				foreach($set as $sock) {
					$name = array_search ($sock, $set_array);

					if ($name === false) {
						/* skip unknown socket */
						continue;
					} elseif (!$this->isClient) {
						if (! ($conn = socket_accept($this->socket))) {
							$this->socket_error();
						} else {
							/* add new client connection or go on if this  fails */
							if (!$this->add_client($conn))	
								continue;
						}
					} else {
						// client socket has incoming data
						if (($read = @socket_read($sock, 1024)) === false || $read == '') {
							if ($read != '')
								$this->socket_error();

							// remove client from array
							$this->remove_client($sock);
						} else {
							$this->clients[$sock]->appendBuffer($read); /* when \n was received, start parsing the buffer */
							if (strstr($read, "\n") !== false && !$this->clients[$sock]->interact()) {
								$this->clients[$sock]->disconnect();
								$this->remove_client($sock);
							} else {
								$this->clients[$sock]->resetBuffer();
							}
						}
					}
				}
			}
			/* got some time to run the timers */
			$sleep = $this->scheduler->runTimers(400);
		}
	}

	protected function add_client($conn) {
		$pid = pcntl_fork() ;
		if ($pid > 0) {
			/* I AM YOUR FATHER */
			/* close the client socket, we don't need it */
			socket_close($conn);
			$this->pids[$pid] = $conn;
			return true;
		} elseif ($pid === -1) {
			$this->log->write("could not fork");
			socket_close($conn);
			return false;
		} else {
			// indicate this is not a server
			$this->isClient = true;

			/* close the listening socket */
			socket_close($this->socket);
			$this->socket = $conn;

			$this->setProcTitle("nanoftpd [worker]");

			// add socket to client list and announce connection
			$this->clients[$conn] = new NanoFTP_Client($this->CFG, $conn);

			// everything is ok, initialize client
			$this->clients[$conn]->init();
			return true;
		}
	}

	protected function reaper() {
		/* reap clients */
		do {
			$deadpid = pcntl_waitpid(-1, $cstat, WNOHANG);
			if ($deadpid > 0) {
				if (isset($this->pids[$deadpid])) {
					if (isset($this->clients[$this->pids[$deadpid]]))
						$this->remove_client($this->clients[$this->pids[$deadpid]]);
					unset($this->pids[$deadpid]);
				}
				$this->log->write("pid ".$deadpid." died with exit code: ".pcntl_wexitstatus($cstat)." clients running: ".count($this->pids)." memory usage: ".memory_get_usage()."\n");
			}
		} while ($deadpid > 0);
	}

	/* distribute signal to all childs */
	public function broadcast_signal($signal) {
		$this->log->write("broadcast signal $signal to all childs...\n");
		foreach ($this->pids as $pid => $bla) {
			posix_kill($pid, $signal);
		}
	}

	public function disconnect_client($sock, $msg = "421 administrative disconnect") {
		if (!isset($this->clients[$sock])) return false;
		$c = $this->clients[$sock];
		$c->disconnect($msg);
		$this->remove_client($sock);
	}

	protected function remove_client($sock) {
		if (isset($this->clients[$sock])) {
			$this->clients[$sock]->disconnect();
			unset($this->clients[$sock]);
		}
	}

	protected function socket_error() {
	    $this->log->write("socket: error: ".socket_strerror(socket_last_error($this->socket))."\n");
	    if (is_resource($this->socket)) socket_close($this->socket);
	    die;
	}
	
	protected function setProcTitle($str) {
		if (function_exists("setproctitle")) setproctitle($str);
	}

	public function shutdown($signo = SIGTERM) {
		$this->broadcast_signal($signo);
		$all_dead = false;
		do {
			// call reaper manually... SIGCHLD comes to late
			$this->reaper();
			if (count($this->pids) !== 0) {
				$this->log->write("waiting for ".count($this->pids)." childs to die\n");
				sleep(1);
			} else {
				$all_dead = true;
			}
		} while (!$all_dead);
		$this->log->write("shutdown finished\n");
	}
}


?>
