<?php

require 'io_mogilefs.php';

class io_mogilefs_userpic extends io_mogilefs {
	protected $mogilefs_tracker = 'vmtrack1';
	protected $mogilefs_tracker_port = 7001;
	protected $size2class = array ( '50' => 'u1',
									'100' => 'u2',
									'200' => 'u3',
									'400' => 'u4',
									'50_backup' => 'u1b',
									'100_backup' => 'u2b',
									'200_backup' => 'u3b',
									'400_backup' => 'u4b',
									'userpics_small' => 'fa1',	/* ForumAvatar */
									);
								

	public function getFilename($lpath) {
		/* profile pics */
		if (preg_match("~u(\d+)/\d+/(\d+)_(.*).jpg$~", $lpath, $match)) {
			return $this->getMediaPath($match[2], $match[3], $match[1]);
		/* backup of locked pictures */
		} elseif (preg_match("~u(\d+_backup)/\d+/(\d+)_(.*).jpg$~", $lpath, $match)) {
			return $this->getMediaPath($match[2], $match[3], $match[1]);
		/* forum avatars */
		} elseif (preg_match("~userpics_small/\d+/(\d+\.jpg)$~", $lpath, $match)) {
			return $this->size2class['userpics_small'].'/'.$match[1];
		} else {	
			return $lpath;
		}
	}

	protected function getMediaPath($userid, $contentid, $size) {
		return $this->size2class[$size].'/'.$userid.'_'.$contentid.'.jpg';
	}

	public function md($dir) {
		return true;  /* all good */
	}
	
	public function cwd() {
		return true; /* all good */
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
		$result = file_get_contents("http://".$this->mogilefs_frontend."/invalidate?key=mg:".$this->mogilefs_domain.":/$fileId", false, $context);
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


	public function close() {
		/* need to invalidate cache when a file is overwritten */
		if ($this->saveonclose) {
			$ret = $this->invalidateFrontendCache($this->filename);
			$this->msg("saveonclose invalidate cache for ".$this->filename." returned: ".$ret."\n");
		}
		parent::close();
	}

}

?>
