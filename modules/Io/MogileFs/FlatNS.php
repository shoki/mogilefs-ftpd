<?php

class Io_Mogilefs_Flat extends Io_MogileFs {
	public function ls($dir) {
		return $this->listMogileFiles($dir, $this->cfg->mogilefs->listlimit);
	}

	public function getFilename($path) {
		return $path;
	}

	protected function getMogileClass($filename) {
		if (parent::getMogileClass($this->cwd) === false) {
			return $this->cfg->mogilefs->defaultclass;
		}
	}


}

?>
