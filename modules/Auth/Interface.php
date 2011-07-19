<?php

interface Auth_Interface {
	public function __construct($cfg);
	public function authenticate($username, $password);
	public function getUserid($username);
	public function getGroupid($username);
	public function getIoModule($username);
}

?>
