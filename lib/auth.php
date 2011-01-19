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

/* a library which handles authentication */

class libauth {
	var $uid;
	var $gid;

	function auth($u, $g) {
		$this->uid = $u;
		$this->gid = $g;
		if ($this->uid == 0 || $this->gid == 0) {
			return false;
		}
		return true;
	}

	function can_read($f) {
		$fowner = $this->get_owner($f);
		$fgroup = $this->get_group($f);
		$uname  = $this->uid;
		$ugroup = $this->gid;
		$fperms = explode(":", $this->get_perms($f));
		$this->clean();
		if ($fowner == $uname && $fperms[0] == "r") {
			return true;
		} elseif ($fgroup == $ugroup && $fperms[3] == "r") {
			return true;
		} elseif ($fperms[6] == "r") {
			return true;
		} else {
			return false;
		}
	}

	function can_write($f) {
		$fowner = $this->get_owner($f);
		$fgroup = $this->get_group($f);
		$uname  = $this->uid;
		$ugroup = $this->gid;
		$fperms = explode(":", $this->get_perms($f));
		$this->clean();
		if ($fowner == $uname && $fperms[1] == "w") {
			return true;
		} elseif ($fgroup == $ugroup && $fperms[4] == "w") {
			return true;
		} elseif ($fperms[7] == "w") {
			return true;
		} else {
			return false;
		}
	}

	function can_execute($f) {
		$fowner = $this->get_owner($f);
		$fgroup = $this->get_group($f);
		$uname  = $this->uid;
		$ugroup = $this->gid;
		$fperms = explode(":", $this->get_perms($f));
		$this->clean();
		if ($fowner == $uname && $fperms[2] == "x") {
			return true;
		} elseif ($fgroup == $ugroup && $fperms[5] == "x") {
			return true;
		} elseif ($fperms[8] == "x") {
			return true;
		} else {
			return false;
		}
	}

	function get_owner($f) {
		return fileowner($f);
		$this->clean();
	}

	function get_group($f) {
		return filegroup($f);
		$this->clean();
	}

	function get_perms($f) {
		$mode = fileperms($f);
		/* Determine permissions */
		$owner['read']    = ($mode & 00400) ? 'r' : '-';
		$owner['write']   = ($mode & 00200) ? 'w' : '-';
		$owner['execute'] = ($mode & 00100) ? 'x' : '-';
		$group['read']    = ($mode & 00040) ? 'r' : '-';
		$group['write']   = ($mode & 00020) ? 'w' : '-';
		$group['execute'] = ($mode & 00010) ? 'x' : '-';
		$world['read']    = ($mode & 00004) ? 'r' : '-';
		$world['write']   = ($mode & 00002) ? 'w' : '-';
		$world['execute'] = ($mode & 00001) ? 'x' : '-';
		$permstr = $owner['read'].":".$owner['write'].":".$owner['execute'].":".$group['read'].":".$group['write'].":".$group['execute'].":".$world['read'].":".$world['write'].":".$world['execute'];
		$this->clean();
		return $permstr;
	}

	function clean() {
		// not required imho 
		//clearstatcache();
	}
}

?>
