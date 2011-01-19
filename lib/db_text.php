<?php

/*
****************************************************
* nanoFTPd - an FTP daemon written in PHP          *
****************************************************
* this file is licensed under the terms of GPL, v2 *
****************************************************
* developers:                                      *
*  - Arjen <arjenjb@wanadoo.nl>                    *
*  - Phanatic <linux@psoftwares.hu>                *
****************************************************
* http://sourceforge.net/projects/nanoftpd/        *
****************************************************
*/

class database {
	var $file;
	var $sep;

	function database($f, $s) {
		if (!file_exists($f)) {
			die("authentication file doesn't exist, quitting immediately...\n($f)\n");
		}
		$this->file = $f;
		$this->sep = $s;
	}

	function user_exist($u) {
		$fp = fopen($this->file, "r");
		flock($fp, 1);
		while (!feof($fp)) {
			$l = fgets($fp, 4096);
			$user = explode($this->sep, $l);
			$id = array_search($u, $user);
			if (strlen($id) != 0) {
				fclose($fp);
				return true;
			}
		}
		flock($fp, 3);
		fclose($fp);
		return false;
	}

	function user_get_property($u, $p) {
		if (gettype($p) == "string") {
			switch ($p) {
				case "username":
					$p = 0;
				break;
				case "password":
					$p = 1;
				break;
				case "uid":
					$p = 2;
				break;
				case "gid":
					$p = 3;
				break;
				case "io_module":
					$p = 4;
				break;
			}
		}

		$fp = fopen($this->file, "r");
		flock($fp, 1);
		while (!feof($fp)) {
			$l = fgets($fp, 4096);
			$user = explode($this->sep, $l);
			if ($user[0] == $u) {
				fclose($fp);
				return rtrim($user[$p]);
			}
		}
		flock($fp, 3);
		fclose($fp);
		return false;
	}
}

?>
