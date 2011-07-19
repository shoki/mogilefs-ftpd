<?php

class Auth_Text implements Auth_Interface {
	protected $cfg;
	protected $users = array();
	protected $user;
	protected $user_uid;
	protected $user_gid;
	protected $io_module;

	public function __construct($cfg) {
		$this->cfg = $cfg;
	}

	public function authenticate($username, $password) {
		$this->loadUsers();

		if (!function_exists($this->cfg->auth->crypt)) {
			// invalid hash function 
			return false;
		}

		if (isset($this->users[$username]) && $this->users[$username]['password'] == call_user_func($this->cfg->auth->crypt, $password)) {
			$this->user = $username;
			$this->user_uid = $this->users[$username]['uid'];
			$this->user_gid = $this->users[$username]['gid'];
			$this->io_module = "Io_" . $this->users[$username]['io_module'];
			return true;
		}
		return false;
	}

	protected function loadUsers() {
		$entries = explode("\n", file_get_contents($this->cfg->auth->text->file));
		foreach ($entries as $entry) {
			$f = explode($this->cfg->auth->text->sep, $entry);
			if (count($f) != 5) continue;
			$this->users[$f[0]] = array ( 
					'password' => $f[1],
					'uid'	   => $f[2],
					'gid'	   => $f[3],
					'io_module'=> $f[4],
					);
		}
	}

	public function getUserid($username) {
		return (isset($this->users[$username]) ? $this->users[$username]['uid'] : false);
	}

	public function getGroupid($username) {
		return (isset($this->users[$username]) ? $this->users[$username]['gid'] : false);
	}

	public function getIoModule($username) {
		return (isset($this->users[$username]) ? $this->users[$username]['io_module'] : false);
	}

}

?>
