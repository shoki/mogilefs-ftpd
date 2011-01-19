<?php

/* 
 FTP to MogileFS translation layer
*/

class io_mogilefs {
	public $parameter;
	public $root;
	public $cwd = '/';
	protected $store;
	protected $saveonclose = false;
	protected $lastError;
	protected $metaCache = array();
	protected $mogileClass;
	protected $mogileClasses;
	protected $fp;
	protected $mogilefs_domain = 'default';
	protected $mogilefs_tracker = 'vmtrack1';
	protected $mogilefs_tracker_port = 7001;
	protected $mogilefs_tracker_timeout = 3;

	public function __construct($client) {
		$this->client = $client;
		$this->cfg = $client->CFG;
		$this->log = &$this->cfg->log;
		$this->root = "";
		$this->MogileFSConnect();
	}

	public function __destruct() {
		if (is_object($this->store)) unset($this->store);
	}

	protected function MogileFSConnect() {
		try {
			if (!$this->mogilefs_domain || !$this->mogilefs_tracker || !$this->mogilefs_tracker_port ) throw new Exception("no mogilefs config set");

			$this->store = new MogileFs();
			$this->store->connect($this->mogilefs_tracker, $this->mogilefs_tracker_port, $this->mogilefs_domain, $this->mogilefs_tracker_timeout);
		} catch (Exception $e) {
			$this->msg("MogileFS Connect Error: ".$e->getMessage()."\n");
			throw $e;
		}
		$this->getMogileClasses();
	}

	public function cwd() {
		return false;
	}

	public function pwd() {
		return $this->cwd;
	}

	/* return mogile class when listing */
	public function ls() {
		return array();
	}

	protected function getMogileClasses() {
		$ret = array();
		try {
			$domains = $this->store->getDomains(); 
		} catch (Exception $e) {}
		for ($x = 1; $x <= $domains['domains']; $x++) {
			if ($domains['domain'.$x] == $this->mogilefs_domain) {
				for ($y = 1; $y <= $domains['domain'.$x.'classes']; $y++) {
					$this->mogileClasses[$domains['domain'.$x.'class'.$y.'name']] = true;
					$info = array ('name' => $domains['domain'.$x.'class'.$y.'name'],
								'size' => 0,
								'owner' => 'root',
								'group' => 'root',
								'time' => 'Jul 12 12:00',
								'perms' => 'drwxrwxrwx');
					$ret[] = $info;
				}
			}
		}
		return $ret;
	}

	public function rm($filename) {
		$filename = $this->getFilename($filename);
		try {
			$this->delete($filename);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	public function size($filename) {
		$filename = $this->getFilename($filename);
		$meta = $this->getMeta($filename);
		$size = strlen($meta['content']);
		return $size; 
	}

	public function exists($filename) {
		$filename = $this->getFilename($filename);
		if ($this->getMeta($filename) !== false) 
			return true; 
		return false;
	}

	public function mdtm($filename) {
		// fake last mod time for mogilefs
		return "20100708133238";
	}

	public function type($filename) {
		return "file"; // hmmm... have no dirs in mogilefs...
	}
	
	public function md($dir) {
		return false; 
	}
	
	public function rd($dir) {
		return false; 
	}

	public function rn($from, $to) {
		return false;
	}

	public function read($size) {
		if (!is_resource($this->fp)) return false;
		return fread($this->fp, $size);
	}

	public function write($str) {
		if (!is_resource($this->fp)) return false;
		fwrite($this->fp, $str);
	}

	protected function getMogileClass($filename) {
		$path = explode("/", $filename, 2);
		/* when path doesn't seem to have any class in it, try to get it from
		 * cwd */
		if (count($path) > 1) {
			/* check if class is valid */
			if (isset($this->mogileClasses[$path[0]]))
				return $path[0];
			else 
				return false;
		} else 
			return $this->cwd;
	}

	public function open($filename, $create = false, $append = false) {
		if ($append) {
			return false; /* not supported */
		}
		$this->tmpfile = $this->cfg->tmpdir."/mogileftp_".getmypid().'_'.uniqid();
		$filename = $this->getFilename($filename);

		if ($create) {
			$mode = "w";
			$this->saveonclose = true;
			$this->filename = $filename;
		} else {
			$mode = "r";
			try {
				$meta = $this->getMeta($filename);
				file_put_contents($this->tmpfile, $meta['content']);
			} catch (Exception $e) {
				$this->lastError = $e->getMessage();
				return false;
			}
		}
		return ($this->fp = fopen($this->tmpfile, $mode));
	}

	protected function getMeta($filename) {
		$this->msg("get meta for: ".$filename."\n");
		/* get and cache metadata */
		if (isset($this->metaCache[$filename])) return $this->metaCache[$filename];
		$this->msg("get meta for: ".$filename." not cached"."\n");
		try {
			$this->metaCache[$filename]['path'] = $meta = $this->store->get($filename);
			$this->msg("get file from mogile: ".$meta["path1"]."\n");
			$content = file_get_contents($meta['path1']);
			if (empty($content))
				throw new Exception("could not retrieve file");
			$this->metaCache[$filename]['content'] = $content;
			return $this->metaCache[$filename];
		} catch (Exception $e) {
			$this->msg("get meta failed: ".$e->getMessage()."\n");
			$this->lastError = $e->getMessage();
			return false;
		}
	}

	public function getLastError() {
		return $this->lastError;
	}

	protected function msg($msg) {
		$this->log->write(__CLASS__.": ".$msg);
	}

	public function close() {
		if (is_resource($this->fp)) fclose($this->fp);
		if ($this->saveonclose) {
			$this->msg("put $this->tmpfile into mogile: ".$this->filename." class=".$this->getMogileClass($this->filename)."\n");
			try {
				$this->store->put($this->tmpfile, $this->filename, $this->getMogileClass($this->filename));
			} catch (Exception $e) {
				$this->msg("failed to put: ".$e->getMessage()."\n");
				return false;
			}
		}
		if (file_exists($this->tmpfile)) unlink($this->tmpfile);
	}

	public function validate_filename($filename) {
		return true;
	}

	/* don't check permissions */
	public function check_can_write($filename) {
		return false;
	}
	public function check_can_read($filename) {
		return false;
	}

	protected function delete($filename) {
		try {
			$this->store->delete($filename);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}
	
	public function getFilename($path) {
		return $path;
	}

	public function getUserId($path) {
		return $path;
	}
}


?>
