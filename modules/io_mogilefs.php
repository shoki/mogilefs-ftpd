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

	public function cwd() {
		/* go to root */
		if ($this->parameter === '/' || $this->parameter === '..') {
			$this->cwd = '/';
			return true;
		}

		$class = ltrim($this->parameter, '/') ;
		if ($this->parameter[0] === '/' || $this->cwd === '/' && isset($this->mogileClasses[$class])) {
			$this->cwd = '/'.$class.'/';
			return true;
		}
		return false;
	}

	public function pwd() {
		return $this->cwd;
	}

	/* return mogile class when listing */
	public function ls() {
		if ($this->cwd === '/') 
			return $this->listMogileClasses();
		else 
			return $this->listMogileFiles($this->parameter, $this->cfg->mogilefs->listlimit);
	}

	public function rm($filename) {
		$filename = $this->getFilename($filename);
		try {
			$this->store->delete($filename);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	public function size($filename) {
		$filename = $this->getFilename($filename);
		$meta = $this->getMeta($filename);
		return $meta['info']['length']; 
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
		if (!$this->cfg->mogilefs->canmkdir) return false;

		try {
			$this->store->createClass($this->cfg->mogilefs->domain, $this->getFilename($dir), $this->cfg->mogilefs->mindevcount);
			return true;
		} catch (Exception $e) {
			$this->msg("createClass failed: ".$e->getMessage()."\n");
			return false;
		}
	}
	
	public function rd($dir) {
		if (!$this->cfg->mogilefs->canrmdir) return false;

		try {
			$this->store->deleteClass($this->cfg->mogilefs->domain, $this->getFilename($dir));
			return true;
		} catch (Exception $e) {
			$this->msg("deleteClass failed: ".$e->getMessage()."\n");
			return false;
		}
	}

	public function rn($from, $to) {
		if (!$this->cfg->mogilefs->canrename) return false;

		try {
			$this->store->rename($this->getFilename($from), $this->getFilename($to));
			return true;
		} catch (Exception $e) {
			$this->msg("rename failed: ".$e->getMessage()."\n");
			return false;
		}
	}

	public function site($params) {
		// have no SITE support now
		return "502 Command not implemented.";
	}

	public function read($size) {
		if (!is_resource($this->fp)) return false;
		return fread($this->fp, $size);
	}

	public function write($str) {
		if (!is_resource($this->fp)) return false;
		fwrite($this->fp, $str);
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
				$content = file_get_contents($meta['path']['path1']);
				if (empty($content))
					throw new Exception("could not retrieve file");

				file_put_contents($this->tmpfile, $content);
			} catch (Exception $e) {
				$this->lastError = $e->getMessage();
				return false;
			}
		}
		return ($this->fp = fopen($this->tmpfile, $mode));
	}

	public function close() {
		$ret = false;
		if (is_resource($this->fp)) fclose($this->fp);
		if ($this->saveonclose) {
			$this->msg("put $this->tmpfile into mogile: ".$this->filename." class=".$this->getMogileClass($this->filename)."\n");
			try {
				$this->store->put($this->tmpfile, $this->filename, $this->getMogileClass($this->filename));
				$this->filename = '';
				$this->saveonclose = false;
				$ret = true;
			} catch (Exception $e) {
				$this->msg("failed to put: ".$e->getMessage()."\n");
			}
		}
		if (file_exists($this->tmpfile)) unlink($this->tmpfile);
		return $ret;
	}

	protected function MogileFSConnect() {
		try {
			if (!$this->cfg->mogilefs->domain || !$this->cfg->mogilefs->tracker || !$this->cfg->mogilefs->port ) 
				throw new Exception("no mogilefs config set");

			$this->store = new MogileFs();
			$this->store->connect($this->cfg->mogilefs->tracker, $this->cfg->mogilefs->port, $this->cfg->mogilefs->domain, $this->cfg->mogilefs->timeout);
		} catch (Exception $e) {
			$this->msg("MogileFS Connect Error: ".$e->getMessage()."\n");
			throw $e;
		}
		$this->listMogileClasses();
	}

	protected function listMogileClasses() {
		$ret = array();
		try {
			$domains = $this->store->getDomains(); 
		} catch (Exception $e) {
			$this->msg("MogileFS getDomains Error: ".$e->getMessage()."\n");
			return $ret;
		}
		for ($x = 1; $x <= $domains['domains']; $x++) {
			if ($domains['domain'.$x] == $this->cfg->mogilefs->domain) {
				for ($y = 1; $y <= $domains['domain'.$x.'classes']; $y++) {
					$this->mogileClasses[$domains['domain'.$x.'class'.$y.'name']] = true;
					$info = array ('name' => $domains['domain'.$x.'class'.$y.'name'],
								'size' => 0,
								'owner' => 'mogilefs',
								'group' => 'class',
								'time' => 'Jul 12 12:00',
								'perms' => 'drwxrwxrwx');
					$ret[] = $info;
				}
			}
		}
		return $ret;
	}

	protected function listMogileFiles($prefix = '', $view_limit = 1000) {
		$files = array();
		$next_after = null;
		$lastKey = null;
		$list_limit = 1000; 	/* internal mogilefs listKeys limit */
		$loops = 0;
		$maxloops = 10;
		do {
			++$loops;
			try {
				$path = ltrim($this->cwd, '/');
				if ($this->cfg->mogilefs->searchlist)
					$key = $path.$prefix;
				else
					$key = $path;

				$list = $this->store->listKeys($key, $next_after, $list_limit);

				for ($x = 1; $x <= $list['key_count']; $x++) {
					if (!isset($list['key_'.$x]) || empty($list['key_'.$x])) { 
						continue;
					}

					$size = 0;
					if ($this->cfg->mogilefs->extendedlist) {
						$meta = $this->getMeta($list['key_'.$x]);
						$size = $meta['info']['length'];
					}

					$files[] = array ( 'name' => str_replace($path, '', $list['key_'.$x]),
								'size' => $size,
								'owner' => 'mogilefs',
								'group' => 'file',
								'time' => 'Jul 12 12:00',
								'perms' => '-rwxrwxrwx');

				}
				/* if there is more to read, fire up another listKeys() */
				$count = count($list) - 2;
				if ($count == $list_limit && $count < $view_limit) {
					$next_after = $list['next_after'];
					$this->msg("read more after $next_after\n");
				} else {
					$next_after = null;
					$this->msg("read finished\n");
				}

			} catch (Exception $e) {
				$this->msg("listkeys Exception: ".$e->getMessage()."\n");
			}
		} while ($next_after && $loops < $maxloops);
		return $files;
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

	protected function getMeta($filename) {
		//$this->msg("get meta for: ".$filename."\n");
		/* get and cache metadata */
		if (isset($this->metaCache[$filename])) return $this->metaCache[$filename];
		try {
			$this->metaCache[$filename]['path'] = $meta = $this->store->get($filename);
			$this->metaCache[$filename]['info'] = $this->store->fileInfo($filename);
			return $this->metaCache[$filename];
		} catch (Exception $e) {
			$this->msg("get meta failed: ".$e->getMessage()."\n");
			$this->lastError = $e->getMessage();
			return false;
		}
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

	public function getFilename($path) {
		if ($this->cwd != '/' && $path[0] !== '/') {
			/* client navigated into mogilefs class by CWD */
			return ltrim($this->cwd, '/').ltrim($path, '/');

		} 
		return ltrim($path, '/');
	}

	public function getLastError() {
		return $this->lastError;
	}

	protected function msg($msg) {
		$this->log->write(__CLASS__.": domain=".$this->cfg->mogilefs->domain." ".$msg);
	}


}


?>
