<?php

class Io_MogileFs_DomainNS extends Io_MogileFs {
	protected $realcwd = '/';

	public function ls($dir) {
		if ($this->realcwd === '/') {
			foreach ($this->mogileDomains as $name => $flag) {
				$list[] = array ( 'name' => $name,
						'size' => 0,
						'owner' => 'mogilefs',
						'group' => 'domain',
						'time' => 'Jul 12 12:00',
						'perms' => 'drwxrwxrwx');
			}
			return $list;
		}
		return parent::ls($dir);

	}

	public function cwd($dir) {
		if ($dir === '/') {
			$this->realcwd = '/';
		}
		$path = explode('/', $dir, 2);
		$this->msg("dir=".$path[0]."\n");
		if ($this->switchDomain($path[0])) {
			$this->realcwd .= $path[0];
			if (!isset($path[1])) return true;
			$dir = $path[1];
		}
		return parent::cwd($dir);
	}

	protected function switchDomain($domain) {
		if ($domain === $this->cfg->mogilefs->domain) return false;

		if (isset($this->mogileDomains[$domain])) {
			$this->oldDomain = $this->cfg->mogilefs->domain;
			$this->cfg->mogilefs->domain = $domain;
			unset($this->store);
			try {
				$this->init();
				return true;
			} catch (Exception $e) {
				$this->cfg->mogilefs->domain = $this->oldDomain;
				$this->init();
				return false; // XXX: reset domain
			}
		} 
		return false;
	}

	public function pwd() {
		if ($this->realcwd !== '/')
			return $this->realcwd.$this->cwd;
		return $this->realcwd;
	}

	public function getFilename($path) {	
		return parent::getFilename(strstr($path, $this->realcwd));
	}
}

?>
