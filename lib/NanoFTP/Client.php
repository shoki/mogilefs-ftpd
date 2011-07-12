<?php

class NanoFTP_Client {

	public $CFG;

	protected $id;
	protected $connection;
	protected $buffer;
	protected $transfertype;
	protected $loggedin;
	protected $user;
	
	protected $addr;
	protected $port;
	protected $pasv;

	protected $data_addr;
	protected $data_port;
	// passive ftp data socket and connection
	protected $data_socket;
	protected $data_conn;
	// active ftp data socket pointer
	protected $data_fsp;

	public $command;
	public $parameter;
	public $return;

	protected $io;
	public $auth;
	
	protected $logintime;
	protected $scheduler;
	protected $timers;

	public function __construct($CFG, $connection, $id) {
		
		$this->id = $id;
		$this->connection = $connection;
		$this->CFG = $CFG;
		
		socket_getpeername($this->connection, $addr, $port);
		$this->addr = $addr;
		$this->port = $port;
		$this->pasv = false;
		$this->loggedin	= false;

		$this->logintime = microtime(true);

		$this->log = $this->CFG->log;
		$this->pasv_pool = new NanoFTP_Pool();

		$this->log->setPrefix("[".getmypid()."]");

		$this->scheduler = APA_Timerscheduler::get();

		/* handle sigpipe */
		pcntl_signal(SIGPIPE, SIG_IGN);
		pcntl_signal(SIGTERM, array($this, 'signalHandler'));
	}

	public function __destruct() {
		/* kill all timers */
		foreach ($this->timers as $timer) {
			$this->scheduler->stopTimer($timer);
		}
	}
	
	public function init() {
		$this->command		= "";
		$this->parameter	= "";
		$this->transfertype = "A";

		/* start idle timer */
		$this->timers['idle_time'] = $this->scheduler->startTimer($this->CFG->idle_time, $this, 'disconnect', array("421 client disconnected because of idle timeout (".$this->CFG->idle_time." seconds)"));

		$this->send("220 " . $this->CFG->server_name);

		if (! is_resource($this->connection)) die;
	}

	public function signalHandler($signo) {
		switch ($signo) {
			case SIGTERM:
				$this->disconnect("421 server died. Connection closed.");
				break;
			default:
				$this->log->msg("unknown signal: ".$signo."\n");
				break;
		}
	}

	public function getUser() {
		return $this->user;
	}

	public function appendBuffer($buffer) {
		$this->buffer .= $buffer;
	}

	public function resetBuffer() {
		$this->buffer = "";
	}

	public function interact() {
		$this->return = true;

		/* something happend, so restart the idle * timer */
		$this->scheduler->restartTimer($this->timers['idle_time'], $this->CFG->idle_time);

		if (strlen($this->buffer)) {

			$this->command		= trim(strtoupper(substr(trim($this->buffer), 0, 4)));
			$this->parameter 	= trim(substr(trim($this->buffer), 4));

			$command = $this->command;

			switch ($command) {
				case "QUIT":
					$this->log->write("client: " . trim($this->buffer) . "\n");
					$this->cmd_quit();
					return $this->return;
				case "USER":
					$this->log->write("client: " . trim($this->buffer) . "\n");
					$this->cmd_user();
					return $this->return;
				case "PASS":
					$this->log->write("client: PASS xxxx\n");
					$this->cmd_pass();
					return $this->return;
			}	

			$this->io->parameter = $this->parameter;
			
			$this->log->write($this->user . ": ".trim($this->buffer)."\n");

			if (! $this->loggedin) {
				$this->send("530 Not logged in.");
			} else {
				switch ($command) {
					case "LIST":
					case "NLST":
						$this->cmd_list();
						break;
					case "PASV":
						$this->cmd_pasv();
						break;
					case "PORT":
						$this->cmd_port();
						break;
					case "SYST":
						$this->cmd_syst();
						break;
					case "PWD":
						$this->cmd_pwd();
						break;
					case "CWD":
						$this->cmd_cwd();
						break;
					case "CDUP":
						$this->cmd_cwd();
						break;
					case "TYPE":
						$this->cmd_type();
						break;
					case "NOOP":
						$this->cmd_noop();
						break;
					case "RETR":
						$this->cmd_retr();
						break;
					case "SIZE":
						$this->cmd_size();
						break;
					case "STOR":
						$this->cmd_stor();
						break;
					case "DELE":
						$this->cmd_dele();
						break;
					case "HELP":
						$this->cmd_help();
						break;
					case "SITE":
						$this->cmd_site();
						break;
					case "APPE":
						$this->cmd_appe();
						break;
					case "MKD":
						$this->cmd_mkd();
						break;
					case "RMD":
						$this->cmd_rmd();
						break;
					case "RNFR":
						$this->cmd_rnfr();
						break;
					case "RNTO":
						$this->cmd_rnto();
						break;
					case "MDTM":
						$this->cmd_mdtm();
						break;
					default:
						$this->send("502 Command not implemented.");
						break;
				}
			}


			return $this->return;
		}
	}

	public function disconnect($reason = null ) {
		if ($reason !== null)
			$this->send($reason);

		if (is_resource($this->connection)) socket_close($this->connection);

		if ($this->pasv) {
			if (is_resource($this->data_conn)) socket_close($this->data_conn);
			if (is_resource($this->data_socket)) socket_close($this->data_socket);
		}
		exit(0);
	}

	/*
	NAME: help
	SYNTAX: help
	DESCRIPTION: shows the list of available commands...
	NOTE: -
	*/
	protected function cmd_help() {
		$this->send($this->io->help());
	}

	/*
	NAME: quit
	SYNTAX: quit
	DESCRIPTION: closes the connection to the server...
	NOTE: -
	*/
	protected function cmd_quit() {
		$this->send("221 Disconnected. Connection duration: ".round((float)microtime(true) - (float)$this->logintime, 4)." seconds.");
		$this->disconnect();

		$this->return = false;
	}

	/*
	NAME: user
	SYNTAX: user <username>
	DESCRIPTION: logs <username> in...
	NOTE: -
	*/
	protected function cmd_user() {

		$this->loggedin = false;
		$this->user = $this->parameter;

		$this->send("331 Password required for " . $this->user . ".");
	}

	/*
	NAME: pass
	SYNTAX: pass <password>
	DESCRIPTION: checks <password>, whether it's correct...
	NOTE: added authentication library support by Phanatic (26/12/2002)
	*/
	protected function cmd_pass() {

	    if (! $this->user) {
			$this->user = "";
			$this->loggedin = false;
			$this->send("530 Not logged in.");
			return;
	    }

		try {
			$module = "Auth_" . $this->CFG->auth->module;
			//require_once($this->CFG->moddir . "/" . $module . '.php');
			if (!class_exists($module))
				throw new Exception("failed to load authentication module: ".$module);

			$this->auth = new $module($this->CFG);
			if (!$this->auth->authenticate($this->user, $this->parameter))
				throw new Exception("authentication failed");

			$io_module = $this->auth->getIoModule();
			//require_once($this->CFG->moddir . "/" . $io_module . '.php');
			if (!class_exists($io_module))
				throw new Exception("failed to load io module: ".$io_module);

			$this->io = new $io_module($this);
			$this->io->init();
			$this->loggedin = true;
			$this->send("230 User ".$this->user." logged in from ".$this->addr.".");
		} catch (Exception $e) {
			$this->send("530 Not logged in. (".$e->getMessage().")");
			$this->loggedin = false;
		}
	}

	/*
	NAME: syst
	SYNTAX: syst
	DESCRIPTION: returns system type...
	NOTE: -
	*/
	protected function cmd_syst() {
		$this->send("215 UNIX Type: L8");
	}

	/*
	NAME: cwd / cdup
	SYNTAX: cwd <directory> / cdup
	DESCRIPTION: changes current directory to <directory> / changes current directory to parent directory...
	NOTE: -
	*/
	protected function cmd_cwd() {
		if ($this->command  == "CDUP") {
			$this->parameter = "..";
		}

		if ($this->io->cwd($this->parameter) !== false) {
			$this->send("250 CWD command succesful.");
		} else {
			$this->send("450 Requested file action not taken.");
		}
	}

	/*
	NAME: pwd
	SYNTAX: pwd
	DESCRIPTION: returns current directory...
	NOTE: -
	*/
	protected function cmd_pwd() {
		$dir = $this->io->pwd();
		$this->send("257 \"" . $dir . "\" is current directory.");
	}

	/*
	NAME: list
	SYNTAX: list
	DESCRIPTION: returns the filelist of the current directory...
	NOTE: should implement the <directory> parameter to be RFC-compilant...
	*/
	protected function cmd_list() {

		$ret = $this->data_open();

		if (! $ret) {
			$this->send("425 Can't open data connection.");
			return;
		}

		$this->send("150 Opening  " . $this->transfer_text() . " data connection.");

		foreach($this->io->ls($this->parameter) as $info) {
			// formatted list output added by Phanatic 

			$formatted_list = sprintf("%-11s%-2s%-15s%-15s%-10s%-13s".$info['name'], $info['perms'], "1", $info['owner'], $info['group'], $info['size'], $info['time']);

			
			$this->data_send($formatted_list);
			$this->data_eol();
		}

		$this->data_close();

		$this->send("226 Transfer complete.");
	}

	/*
	NAME: dele
	SYNTAX: dele <filename>
	DESCRIPTION: delete <filename>...
	NOTE: authentication check added by Phanatic (26/12/2002)
	*/
	protected function cmd_dele() {
		if (strpos(trim($this->parameter), "..") !== false) {
			$this->send("550 Permission denied.");
			return;
		}

		if (substr($this->parameter, 0, 1) == "/") {
			$file = $this->io->root.$this->parameter;
		} else {
			$file = $this->io->root.$this->io->cwd.$this->parameter;
		}
		if (!$this->io->validate_filename($file)) { 
			$this->send("550 Resource is not a file.");
		} else {	
			if (!$this->io->canWrite($file)) { 
				$this->send("550 Permission denied.");
			} else {
				if (!$this->io->rm($this->parameter)) {
					$this->send("550 Couldn't delete file.");
				} else {
					$this->send("250 Delete command successful.");
				}
			}
		}
	}

	/*
	NAME: mkd
	SYNTAX: mkd <directory>
	DESCRIPTION: creates the specified directory...
	NOTE: -
	*/
	protected function cmd_mkd() {
	    $dir = trim($this->parameter);
	    
	    if (strpos($dir, "..") !== false) {
		$this->send("550 Permission denied.");
		return;
	    }
	    
    	    $io = $this->io;
	    
	    if (!$io->md($dir)) {
		$this->send("553 Requested action not taken.");
	    } else {
		$this->send("250 MKD command successful.");
	    }
	}
	
	/*
	NAME: rmd
	SYNTAX: rmd <directory>
	DESCRIPTION: removes the specified directory (must be empty)...
	NOTE: -
	*/
	protected function cmd_rmd() {
	    $dir = trim($this->parameter);
	    
	    if (strpos($dir, "..") !== false) {
		$this->send("550 Permission denied.");
		return;
	    }
	    
	    $io = $this->io;
	    
	    if (!$io->rd($dir)) {
		$this->send("553 Requested action not taken.");
	    } else {
		$this->send("250 RMD command successful.");
	    }
	}
	
	/*
	NAME: rnfr
	SYNTAX: rnfr <file>
	DESCRIPTION: sets the specified file for renaming...
	NOTE: -
	*/
	protected function cmd_rnfr() {
	    $file = trim($this->parameter);
	    
	    if (strpos($file, "..") !== false) {
		$this->send("550 Permission denied.");
		return;
	    }
	    
	    $io = $this->io;
	    
	    if (!$io->exists($file)) {
		$this->send("553 Requested action not taken.");
		return;
	    }
	    
	    $this->rnfr = $file;
	    $this->send("350 RNFR command successful.");
	}
	
	/*
	NAME: rnto
	SYNTAX: rnto <file>
	DESCRIPTION: sets the target of the renaming...
	NOTE: -
	*/
	protected function cmd_rnto() {
	    $file = trim($this->parameter);
	    
	    if (!isset($this->rnfr) || strlen($this->rnfr) == 0) {
		$this->send("550 Requested file action not taken (need an RNFR command).");
		return;
	    }
	    
	    if (strpos($file, "..") !== false) {
		$this->send("550 Permission denied.");
		return;
	    }
	    
	    $io = $this->io;
	    
	    if ($io->rn($this->rnfr, $file)) {
		$this->send("250 RNTO command successful.");
	    } else {
		$this->send("553 Requested action not taken.");
	    }
	}

	protected function cmd_mdtm() {
	    $file = trim($this->parameter);

		if (method_exists($this->io, 'mdtm')) {
			$time = $this->io->mdtm($file);
			$this->send ("213 ".$time);
		} else {
			$this->send("502 Command not implemented.");
		}
		return;    
	}

	/*
	NAME: stor
	SYNTAX: stor <file>
	DESCRIPTION: stores a local file on the server...
	NOTE: -
	*/
	protected function cmd_stor() {
		$file = trim($this->parameter);

		$io = $this->io;

		$this->send("150 File status okay; openening " . $this->transfer_text() . " connection.");
		$this->data_open();
		$io->open($file, true);

		if ($this->pasv) {
			while(($buf = socket_read($this->data_conn, 512)) !== false) {
				if (! strlen($buf)) break;
				$io->write($buf);
			}
		} else {
			while (!feof($this->data_fsp)) {
				$buf = fgets($this->data_fsp, 16384);
				$io->write($buf);
			}
		}
		$io->close();
		$this->data_close();
		$this->send("226 transfer complete.");

	}

	/*
	NAME: appe
	SYNTAX: appe <file>
	DESCRIPTION: if <file> exists, the recieved data should be appended to that file...
	NOTE: -
	*/
	protected function cmd_appe() {
	    $file = trim($this->parameter);
	    
	    if (strpos($file, "..") !== false) {
		$this->send("550 Permission denied.");
		return;
	    }
	    
	    $io = $this->io;
	    
	    $this->send("150 File status okay; openening " . $this->transfer_text() . " connection.");
	    $this->data_open();
	    
	    if ($io->exists($file)) {
		if ($io->type($file) == "dir") {
		    $this->send("553 Requested action not taken.");
		    return;
		} else {
		    $io->open($file, false, true);
		}
	    } else {
		$io->open($file, true);
	    }	    
	    
	    if ($this->pasv) {
		while(($buf = socket_read($this->data_conn, 512)) !== false) {
		    if (! strlen($buf)) break;
		    $io->write($buf);
		}
	    } else {
		while (!feof($this->data_fsp)) {
		    $buf = fgets($this->data_fsp, 16384);
		    $io->write($buf);
		}
	    }
	    $io->close();
	    $this->data_close();
	    $this->send("226 transfer complete.");
	}

	/*
	NAME: retr
	SYNTAX: retr <file>
	DESCRIPTION: retrieve a file from the server...
	NOTE: authentication check added by Phanatic (26/12/2002)
	*/
	protected function cmd_retr() {
		$file = trim($this->parameter);

		if (strpos($file, "..") !== false) {
			$this->send("550 Permission denied.");
			return;
		}

		$io = $this->io;
		$filename = $this->io->root.$this->io->cwd.$file;

		if (!$this->io->validate_filename($filename)) {
			$this->send("550 Resource is not a file. $filename");
			return;
		} else {
			if (!$this->io->exists($file)) {
				$this->send("553 Requested action not taken.");
				return;
			}

			if (!$this->io->canRead($filename)) {
				$this->send("550 Permission denied.");
				return;
			} else {
				$size = $io->size($file);

				if (!$io->open($file)) {
					$this->send("550 could not read file.  (".$io->getLastError().")");
					return;
				}
				$this->data_open();
				$this->send("150 " . $this->transfer_text() . " connection for " . $file . " (" . $size . " bytes).");

				if ($this->transfertype == "A") {
					$file = str_replace("\n", "\r", $io->read($size));
					$this->data_send($file);
				} else {
					while ($data = $io->read(1024)) {
						$this->data_send($data);
					}
				}

				$this->send("226 transfer complete.");
				$this->data_close();
				$io->close();
			}
		}
	}

	protected function cmd_pasv() {

		$pool = $this->pasv_pool;

		socket_getsockname($this->connection, $local_addr);

		if ($this->pasv) {
			if (is_resource($this->data_conn)) socket_close($this->data_conn);
			if (is_resource($this->data_socket)) socket_close($this->data_socket);

			$this->data_conn = false;
			$this->data_socket = false;

			if ($this->data_port) $pool->remove($this->data_port);

		}

		$this->pasv = true;

		$low_port = $this->CFG->low_port;
		$high_port = $this->CFG->high_port;

		if (($socket = socket_create(AF_INET, SOCK_STREAM, 0)) < 0) {
			$this->send("425 Can't open data connection.");
			return;
		}

		// reuse listening socket address 
		if (! @socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
			$this->send("425 Can't open data connection.");
			return;
		}

		/* try to get a unused port slot */
		// XXX: record already tested ports and fail if exhausted
		for ($try = 0; $try < 1000; $try++) { 
			/* get random port */
			$port = mt_rand($low_port, $high_port);
			if (! $pool->exists($port)) {
				$pool->add($port);

				$c = @socket_bind($socket, $local_addr, $port);
				if (!$c)
					$this->log->write("socket_bind error: ".socket_strerror(socket_last_error($socket))."\n");
				else {
					if (!socket_listen($socket)) {
						$this->log->write("socket_listen error: ".socket_strerror(socket_last_error($socket))."\n");
					} else {
						break;
					}
				}
			}
		}


		if (! isset($c)) {
			$this->send("452 Can't open data connection.");
			return;
		}


		$this->data_socket = $socket;
		$this->data_port = $port;

		$p1 = $port >>  8;
		$p2 = $port & 0xff;

		$tmp = str_replace(".", ",", $local_addr);
		$this->send("227 Entering Passive Mode ({$tmp},{$p1},{$p2}).");
	}

	protected function cmd_port() {
		$data = explode(",", $this->parameter);

		if (count($data) != 6) {
			$this->send("500 Wrong number of Parameters.");
			return;
		}

		$p2 = array_pop($data);
		$p1 = array_pop($data);

		$port = ($p1 << 8) + $p2;

		foreach($data as $ip_seg) {
			if (! is_numeric($ip_seg) || $ip_seg > 255 || $ip_seg < 0) {
				$this->send("500 Bad IP address " . implode(".", $data) . ".");
				return;
			}
		}

		$ip = implode(".", $data);

		if (! is_numeric($p1) || ! is_numeric($p2) || ! $port) {
			$this->send("500 Bad Port number.");
			return;
		}

		$this->data_addr = $ip;
		$this->data_port = $port;

		$this->log->write($this->user.": server: Client suggested: $ip:$port.\n");
		$this->send("200 PORT command successful.");
	}

	protected function cmd_type() {
		$type = trim(strtoupper($this->parameter));

		if (strlen($type) != 1) {
			$this->send("501 Syntax error in parameters or arguments.");
		} elseif ($type != "A" && $type != "I") {
			$this->send("501 Syntax error in parameters or arguments.");
		} else {
			$this->transfertype = $type;
			$this->send("200 type set.");
		}
	}

	protected function cmd_size() {
		$file = trim($this->parameter);
		
		if (strpos($file, "..") !== false) {
		    $this->send("550 Permission denied.");
		    return;
		}

		$io = $this->io;

		if (! $this->io->exists($file)) {
			$this->send("553 Requested action not taken.");
			return;
		}

		$size = $io->size($file);

		if ($size === false) {
			$this->send("553 Requested action not taken.");
			return;
		}

		$this->send("213 " . $size);
	}

	protected function cmd_noop() {
		$this->send("200 Nothing Done.");
	}
	
	/*
	NAME: site
	SYNTAX: site <command> <parameters>
	DESCRIPTION: server specific commands...
	NOTE: chmod feature built in by Phanatic (01/01/2003)
	*/
	protected function cmd_site() {
		$this->send($this->io->site($this->parameter));
	}

	protected function data_open() {

		if ($this->pasv) {
			
			if (! $conn = @socket_accept($this->data_socket)) {

				$this->log->write($this->user.": server: Client not connected\n");
				return false;
			}

			if (! socket_getpeername($conn, $peer_ip, $peer_port)) {
				$this->log->write($this->user.": server: Client not connected\n");
				$this->data_conn = false;
				return false;
			} else {
				$this->log->write($this->user.": server: Client connected ($peer_ip:$peer_port)\n");
			}

			$this->data_conn = $conn;

		} else {

			$fsp = fsockopen($this->data_addr, $this->data_port, $errno, $errstr, 30);

			if (! $fsp) {
				$this->log->write($this->user.": server: Could not connect to client\n");
				return false;
			}

			$this->data_fsp = $fsp;
		}

		return true;
	}

	protected function data_close() {
		if (! $this->pasv) {
			if (is_resource($this->data_fsp)) fclose($this->data_fsp);
			$this->data_fsp = false;
		} else {
			socket_close($this->data_conn);
			$this->data_conn = false;
		}
	}

	protected function data_send($str) {

		if ($this->pasv) {
			socket_write($this->data_conn, $str, strlen($str));
		} else {
			fputs($this->data_fsp, $str);
		}
	}

	protected function data_read() {
		if ($this->pasv) {
			return socket_read($this->data_conn, 1024);
		} else {
			return fread($this->data_fsp, 1024);
		}
	}

	protected function data_eol() {
		$eol = ($this->transfertype == "A") ? "\r\n" : "\n";
		$this->data_send($eol);
	}


	protected function send($str) {
		socket_write($this->connection, $str . "\n");
		if (! $this->loggedin) {
		    $this->log->write("server: $str\n");
		} else {
		    $this->log->write($this->user.": server: $str\n");
		}
	}

	protected function transfer_text() {
		return ($this->transfertype == "A") ? "ASCII mode" : "Binary mode";
	}
}

?>
