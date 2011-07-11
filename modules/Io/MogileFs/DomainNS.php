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

	/* try to hide first path level which represents the mogilefs domain */
	public function cwd($dir) {
		$path = explode('/', $dir, 3);
		if ($dir === '/' || ($this->cwd === '/' && $dir === '..')) {
			$this->realcwd = '/';
		}
		// full path enterred 
		if ($dir[0] === '/' && isset($path[1])) {
			$domain = $path[1];
			if (isset($path[2]))
				$moredir = $path[2];
		} else {
			$domain = $path[0];
			if (isset($path[1]))
				$moredir = $path[1];
		}
		if ($this->switchDomain($domain)) {
			$this->realcwd = '/'.$domain;
			if (!isset($moredir)) return true;
			$dir = $moredir;
		}
		return parent::cwd($dir);
	}

	protected function switchDomain($domain) {
		if ($domain === $this->cfg->mogilefs->domain) return true;

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

}

?>
