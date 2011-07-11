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

class pool {

	var $pool;

	function pool() {
		$this->pool = array();
	}

	function add($value) {
		if (! in_array($value, $this->pool)) {
			return array_push($this->pool, $value);
		} else {
			return 2;
		}
	}

	function remove($rem_value) {
		if (in_array($rem_value, $this->pool)) {
			$new_pool = array();
			foreach ($this->pool as $value) {
				if ($value == $rem_value) continue;
				$new_pool[] = $value;
			}

			$this->pool = $new_pool;

			return true;
		} else {
			return false;
		}
	}

	function exists($value) {
		return in_array($value, $this->pool);
	}
}

?>