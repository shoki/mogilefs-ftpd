<?php

require 'io_mogilefs.php';

class io_mogilefs_ug extends io_mogilefs {
	protected $mogilefs_tracker = 'vmtrack1';
	protected $mogilefs_tracker_port = 7001;
	protected $pruneUser = false;
	protected $UserId;

	public function getFilename($lpath) {
		/* if it is a compatibility path, translate it */
		/* userids < 10000, first path is all zero */
		if (preg_match("/ug\d+\/ug\/0000\/0*(\d+)\/(\d+)_full,r,(\d+)x(\d+).jpg/", $lpath, $match)) {
			return $this->getMediaPath($match[1], $match[2], $match[3]);
			/* userids > 10000 */
		} elseif (preg_match("/ug\d+\/ug\/0*(\d+)\/(\d+)\/(\d+)_full,r,(\d+)x(\d+).jpg/", $lpath, $match)) {
			return $this->getMediaPath($match[1].$match[2], $match[3], $match[4]);
		} else {	
			return $lpath;
		}
	}

	public function getUserId($path) {
		if (preg_match("/ug\d+\/ug\/0000\/0*(\d+)/", $path, $match)) {
			return $match[1];
		} elseif (preg_match("/ug\d+\/ug\/0*(\d+)\/(\d+)/", $path, $match)) {
			return $match[1].$match[2];
		} else {
			return $path;
		}
	}

	protected function getMediaPath($userid, $contentid, $size) {
		$size2class = array ( '120' => 'upt', '470' => 'upf');
		return $size2class[$size].'/'.$userid.'_'.$contentid.'.jpg';
	}


	/* return mogile class when listing */
	public function ls() {
		if ($this->pruneUser) return array();
		return $this->getMogileClasses();
	}

	public function cwd() {
		/* cd / means reset mogile class */
		if ($this->parameter == '/') {
			if (!$this->pruneUser) {
				$this->mogileClass = '';
				$this->cwd = '/';
			}
			return true;
		} elseif ($this->cwd == '/' && !empty($this->parameter)) {
			$this->cwd .= $this->parameter;
			/* first cwd registers mogile class */
			if (isset($this->mogileClasses[$this->parameter])) {
				$this->mogileClass = $this->parameter;
				return true;
			} else {
				/* this doesn't seem to be a valid mogile class. switch to
				 * delete user home mode */
				if ($this->getUserId($this->parameter) != $this->parameter) {
					$this->pruneUser = true;
					$this->UserId = $this->getUserId($this->parameter);
					return true;
				}
			}
		}

		return false;
	}

	public function rd($dir) {
		$classes = array('upt', 'upf');
		/* for kwick case this means, get all files of that user and delete them */
		if ($this->pruneUser) {
			foreach ($classes as $class) {
				$files = $this->getUserFiles($this->UserId, $class);
				foreach ($files as $file) {
					$this->msg("delete: ".$file." class=".$class."\n");
					try {
						$this->delete($file);
					} catch (Exception $e) 
					{
						$this->msg("delete failed: ".$e->getMessage()."\n");
					}
				}
			}
			$this->pruneUser = false;
			return true;
		}

		return false; 
	}	
	
	protected function getUserFiles($userid, $class) {
		$files = array();
		$next_after = null;
		$lastKey = null;
		$loops = 0;
		$maxloops = 10;
		do {
			++$loops;
			try {
				$list = $this->store->listKeys($class.'/'.$userid."_", $next_after, 1000);
				$this->msg("got ".count($list)." keys from listKeys for $userid class $class\n");

				for ($x = 1; $x <= $list['key_count']; $x++) {
					if (!isset($list['key_'.$x]) || empty($list['key_'.$x])) { 
						$this->msg("key_$x not found for $userid class $class\n");
						continue;
					}
					$files[] = $list['key_'.$x];
				}
				/* if there is more to read, fire up another listKeys() */
				if ((count($list) - 2) == 1000) {
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
		/* make sure that we are not trying to delete too much :) */
		if (count($files) > 4000) {
			$this->msg("listkey is too high (".count($files).") for $userid.\n");
			$files = array();
		}
		return $files;
	}

	protected function invalidateFrontendCache($fileId) {
		$opts = array('http' =>
			array(
				'timeout'  => "2.0",
			)
		);

		$context = stream_context_create($opts);

		// invalidate cache
		$errorReporting = error_reporting(0);
		$result = file_get_contents("http://i.kw.cx/invalidate?key=mg:kwick:/$fileId", false, $context);
		error_reporting($errorReporting);

		if (false !== $result) {
			return true;
		}

		return false;
	}

	protected function delete($filename) {
		$delrc = parent::delete($filename);
		$icrc = $this->invalidateFrontendCache($filename);
		$this->msg("delete $filename: ".($delrc ? 'done' : 'failed')." invalidate: ".($icrc ? 'done' : 'failed')."\n");
	}
	

	public function rm($filename) {
		$filename = $this->getFilename($filename);
		parent::rm($filename);
		return true; /* alway good */
	}

}


?>
