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

// This class is for logging actions
class NanoFTP_Log {
   
    // path to file
    private $logfile;
    
    // file pointer
    private $fp;
    
    // read or write mode
    private $file_mode;
    
    // log mode: no logging, to file, to console or both
    private $mode;

	// prefix for log entries
	private $prefix;

    public function __construct($CFG, $m = "log") {
		
		$this->logfile = $CFG->logging->file;

		// do the level trick, converts the decimal level number to binary
		// the first bit stands for file logging, the second for console
		$this->mode = strrev(str_pad(decbin($CFG->logging->mode), 8, "0", STR_PAD_LEFT));
		
		if ($this->mode[0] && ! file_exists($this->logfile)) {
	    	if (! @touch($this->logfile)) die("cannot create logfile ({$this->logfile})...");
		}
		
		switch ($m) {
		    case "log":
				$this->file_mode = "a";
				break;
				
		    case "read":
				$this->file_mode = "r";
				break;
		}
		$this->fp = fopen($this->logfile, $this->file_mode);
		if (! $this->fp) die("cannot open logfile (".$this->logfile." - mode: ".$this->file_mode.")...");
    }

	public function __destruct() {
		if (is_resource($this->fp)) fclose($this->fp);
	}

	public function setPrefix($prefix) {
		$this->prefix = $prefix;
	}

    public function write($msg) {

		$s = $this->datetime();
		if (!empty($this->prefix))
			$s .= " ".$this->prefix." ";
		$s .= ": ".$msg;
		
		// log to file
		if ($this->mode[0]) {
			if (is_resource($this->fp) && ! fwrite($this->fp, $s)) die("cannot write to logfile (".$this->logfile.")...");
		}
		
		// log to console
		if ($this->mode[1]) {
			echo $s;
		}
    }
    
    private function datetime() {
		$d = date("Ymd-His");
		return $d;
    }
}

?>
