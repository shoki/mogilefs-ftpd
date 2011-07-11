<?php

// XXX: cleanup
// XXX: add autoload__

include($CFG->libdir."/timer.php");
include($CFG->libdir."/timerscheduler.php");

class server {

	var $CFG;

	var $listen_addr;
	var $listen_port;

	var $socket;
	var $clients;
	
	var $log;
	var $clientID;

	var $isChild;

	var $timers = array();
	var $scheduler = array();

	var $pids = array();

	function server($CFG) {

		$this->CFG = $CFG;
		$this->log = &$CFG->log;
		$this->log->setPrefix("[".getmypid()."]");
		$this->isChild = false;
		
		$this->init();

		$allowed_directives = array(
			"listen_addr"
			,"listen_port"
		);

		foreach(get_object_vars($CFG) as $var => $value) {
			if (in_array($var, $allowed_directives)) $this->$var = $value;
		}
	}

	function init() {
		$this->listen_addr = 0;
		$this->listen_port = 21;
		$this->clientID = 'server';

		$this->socket = false;
		$this->clients = Array();

		$this->scheduler = new TimerScheduler();
		$this->setProcTitle("nanoftpd [master]");
	}

	private function setup_socket() {
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
		if (! @socket_bind($this->socket, $this->listen_addr, $this->listen_port))
			$this->socket_error();

		// listen on listening socket
		if (! socket_listen($this->socket))
			$this->socket_error();

	}

	function run() {
		$this->setup_socket();
		// set initial vars and loop until $abort is set to true
		$abort = false;

		while (! $abort) {
			/* reap manually when not running as a daemon */
			if ($this->clientID == 'server' && !$this->CFG->daemonize) {
				$this->reaper();
			}

			// sockets we want to pay attention to
			$set_array = array($this->clientID => $this->socket);
			
			$set = $set_array;
			//echo($this->clientID." select ".getmypid()."\n");
			// avoid warnings aboit EINTR
			if (@socket_select($set, $set_w = NULL, $set_e = NULL, 1, 0) > 0) {
				
				// loop through sockets
				foreach($set as $sock) {
					$name = array_search ($sock, $set_array);

					if ($name === false) {
						continue;
					} elseif ($name == "server") {
						if (! ($conn = socket_accept($this->socket))) {
							$this->socket_error();
						} else {
							/* add new client connection or go on if this  fails */
							if (!$this->add_client($conn))	
								continue;
						}
					} else {
						$clientID = $name;

						// client socket has incoming data
						if (($read = @socket_read($sock, 1024)) === false || $read == '') {
							if ($read != '')
								$this->socket_error();

							// remove client from array
							$this->remove_client($clientID);
						} else {
							// only want data with a newline
							if (strchr(strrev($read), "\n") === false) {
								$this->clients[$clientID]->buffer .= $read;
							} else {
								$this->clients[$clientID]->buffer .= str_replace("\n", "", $read);
								
								/* something happend, so restart the idle * timer */
								$this->scheduler->restartTimer($this->timers['idle_time'], $this->CFG->idle_time);

								if (! $this->clients[$clientID]->interact()) {
									$this->clients[$clientID]->disconnect();
									$this->remove_client($clientID);
								} else {
									$this->clients[$clientID]->buffer = "";
								}
							}
						}
					}
				}
			}
			/* got some time to run the timers */
			$this->scheduler->runTimers();
		}
	}

	private function add_client($conn) {
		$clientID = uniqid("client_");

		$pid = pcntl_fork() ;
		if ($pid > 0) {
			/* I AM YOUR FATHER */
			/* close the client socket, we don't need it */
			socket_close($conn);
			$this->pids[$pid] = true;
			return true;
		} elseif ($pid === -1) {
			$this->log->write("could not fork");
			socket_close($conn);
			return false;
		} else {
			// indicate that we are a child worker
			$this->isChild = true;

			// indicate this is not a server
			$this->clientID = $clientID;

			/* close the listening socket */
			socket_close($this->socket);
			$this->socket = $conn;

			$this->log->setPrefix("[".getmypid()."]");

			$this->setProcTitle("nanoftpd [worker]");

			// add socket to client list and announce connection
			$this->clients[$clientID] = new client($this->CFG, $conn, $clientID);

			/* start idle timer */
			$this->timers['idle_time'] = $this->scheduler->startTimer($this->CFG->idle_time, $this, 'disconnect_client', array($clientID, "421 client disconnected because of idle timeout (".$this->CFG->idle_time." seconds)"));

			// everything is ok, initialize client
			$this->clients[$clientID]->init();
			return true;
		}
	}

	public function reaper() {
		/* reap clients */
		do {
			$deadpid = pcntl_waitpid(-1, $cstat, WNOHANG);
			if ($deadpid > 0) {
				if (isset($this->pids[$deadpid])) unset($this->pids[$deadpid]);
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

	function get_client_connections() {
		$conn = array();

		foreach($this->clients as $clientID => $client) {
			$conn[$clientID] = $client->connection;
		}

		return $conn;
	}

	function disconnect_client($clientID, $msg = "421 administrative disconnect") {
		if (!isset($this->clients[$clientID])) return false;
		/* kill all timers */
		foreach ($this->timers as $timer) {
			$this->scheduler->stopTimer($timer);
		}
		$c = $this->clients[$clientID];
		$c->send($msg);
		$c->disconnect();
		$this->remove_client($clientID);
	}

	function remove_client($clientID) {
		if (isset($this->clients[$clientID])) unset($this->clients[$clientID]);
		/* child done */
		if ($this->clientID != 'server') exit(0);
	}

	function socket_error() {
	    $this->log->write("socket: error: ".socket_strerror(socket_last_error($this->socket))."\n");
	    if (is_resource($this->socket)) socket_close($this->socket);
	    die;
	}
	
	protected function setProcTitle($str) {
		if (function_exists("setproctitle")) setproctitle($str);
	}
}


?>
