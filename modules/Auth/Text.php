<?php

class Auth_Text implements Auth_Interface {
	protected $cfg;
	protected $user;
	protected $user_uid;
	protected $user_gid;
	protected $io_module;

	public function __construct($cfg) {
		$this->cfg = $cfg;
	}

	public function authenticate($username, $password) {
		if (!function_exists($this->cfg->auth->crypt)) {
			// invalid hash function 
			return false;
		}

		$ret = false;
		$txtdb = new NanoFTP_DBText($this->cfg->auth->text->file, $this->cfg->auth->text->sep);

		$this->user = $username;

		if (!$txtdb->user_exist($this->user)) {
			$ret = false;
		} elseif ($txtdb->user_get_property($this->user, "password") == call_user_func($this->cfg->auth->crypt, $password)) {
			$this->user_uid = $txtdb->user_get_property($this->user, "uid");
			$this->user_gid = $txtdb->user_get_property($this->user, "gid");
			$this->io_module = "Io_" . $txtdb->user_get_property($this->user, "io_module");
			$ret = true;
		}
		return $ret;
	}

	public function getUserid($username) {
		return $this->user_uid;
	}

	public function getGroupid($username) {
		return $this->user_gid;
	}

	public function getIoModule() {
		return $this->io_module;
	}
}

?>
