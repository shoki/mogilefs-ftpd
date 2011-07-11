<?php

interface io_interface {
	
	public function init() ;
	public function cwd($dir) ;
	public function pwd() ;
	public function ls($dir) ;
	public function rm($filename) ;
	public function size($filename) ;
	public function exists($filename) ;
	public function mdtm($filename) ;
	public function type($filename) ;
	public function md($dir) ;
	public function rd($dir) ;
	public function rn($from, $to) ;
	public function site($params) ;
	public function read($size) ;
	public function write($str) ;
	public function open($filename, $create = false, $append = false) ;
	public function close() ;
	public function validate_filename($filename) ;
	public function canWrite($filename) ;
	public function canRead($filename) ;
	public function getLastError() ;
	public function help() ;
}

?>
