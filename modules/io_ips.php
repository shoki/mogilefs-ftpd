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

class io_ips {

	var $parameter;
	var $tree;
	var $path;
	var $pointer;
	var $cwd;
	var $callback;
	var $mode;
	var $update_record;
	var $fp;

	function io_ips() {

		$this->tree = array(
			array(
				"name" => "views"
				,"type" => "dir"
				,"data" => array(
					array(
						"name" => "all"
						,"type" => "dir"
						,"data" => array(
							array(
								"type" => "records"
								,"callback" => "get_all_views"
							)
						)
					)
					,array(
						"name" => "checked in"
						,"type" => "dir"
						,"data" => array(
							array(
								"type" => "records"
								,"callback" => "get_checkedin_views"
							)
						)
					)
				)
			)
			,array(
				"name" => "layouts"
				,"type" => "dir"
				,"data" =>  array(
					array(
						"name" => "all"
						,"type" => "dir"
						,"data" => array(
							array(
								"type" => "records"
								,"callback" => "get_all_layouts"
							)
						)
					)
					,array(
						"name" => "checked in"
						,"type" => "dir"
						,"data" => array(
							array(
								"type" => "records"
								,"callback" => "get_checkedin_layouts"
							)
						)
					)
				)
			)
		);

		$this->path = array();
		$this->pointer = &$this->tree;
		$this->cwd = "/";
	}

	function cwd() {

		$dir = trim($this->parameter);
		$new_cwd = "";

		switch (TRUE) {
			case (! strlen($dir)):
				return $this->cwd;

			case ($dir == ".."):
				array_pop($this->path);
				$this->create_pointer();
				$this->create_cwd();

				return true;
				break;

			case (substr($dir, 0, 1) == "/"):
				if (strlen($dir) == 1) {
					$this->path = array();
					$this->create_pointer();
					$this->create_cwd();
				} else {
					$dirs = explode("/", trim($dir, "/"));

					$new_path = array();
					$temp_pointer = &$this->tree;

					foreach($dirs as $dir) {
						$found = false;

						foreach($temp_pointer as $key => $node) {
							if ($node["name"] == $dir) {
								$found = true;
								break;
							}
						}

						if ($found) {
							array_push($new_path, $key);
							if (! $this->is_path($new_path)) {
								return false;
							}

							$temp_pointer = &$node["data"];
						} else {
							return false;
						}
					}

					$this->path = $new_path;

					$this->create_pointer();
					$this->create_cwd();
				}

				return true;
				break;

			default:
				$dir = trim($dir, "/");

				$found = false;
				foreach($this->pointer as $key => $node) {
					if ($node["name"] == $dir) {
						$found = true;
						break;
					}
				}

				if ($found) {
					$new_path = $this->path;
					array_push($new_path, $key);

					if ($this->is_path($new_path)) {
						$this->path = $new_path;
					} else {
						return false;
					}

					$this->create_pointer();
					$this->create_cwd();
					return true;
				} else {
					return false;
				}
				break;
		}

		echo $new_cwd;

		return $this->cwd;
	}

	function pwd() {
		return $this->cwd;
	}
	function create_pointer() {
		$this->pointer = &$this->tree;

		foreach($this->path as $node) {
			$this->pointer = &$this->pointer[$node]["data"];
		}
	}

	function create_cwd() {
		$cwd = "/";
		$p = &$this->tree;

		foreach($this->path as $node) {
			$cwd .= $p[$node]["name"] . "/";
			$p = &$p[$node]["data"];
		}

		$this->cwd = $cwd;
	}

	function is_path($path) {
		$pointer = &$this->tree;

		foreach($path as $node) {
			if (array_key_exists($node, $pointer)) {
				$pointer = &$pointer[$node]["data"];
			} else {
				return false;
			}
		}

		return true;
	}

	function ls() {
		$list = array();

		foreach($this->pointer as $node) {
			if ($node["type"] == "dir") {
				$size = 0;

				$info = array(
					"name" => $node["name"]
					,"type" => "dir"
					,"size" => $size
				);

				$list[] = $info;
			} elseif ($node["type"] == "records") {
				$callback = $node["callback"];

				$list = array_merge($list, $this->$callback("ls"));
			}
		}

		return $list;
	}

	function rm() {
		return true;
	}

	function size($record) {
		$type = $this->pointer[0]["type"];
		if ($type == "dir") {
			return 0;
		} else {
			$callback = $this->pointer[0]["callback"];

			return $this->$callback("size", $record);
		}
	}

	function exists($record) {
		$callback = $this->pointer[0]["callback"];
		return $this->$callback("exists", $record);
	}

	function type($filename) {
		return "file";
	}

	function read($size) {
		static $data;
		if (! strlen($this->fp)) return false;

		$s = substr($this->fp, 0, $size);
		$this->fp = substr($this->fp, $size);

		return $s;
	}

	function write($str) {
		$this->fp .= $str;
	}

	function open($record, $create = false) {

		if (! $create) {
			$this->mode = "select";

			$callback = $this->pointer[0]["callback"];
			$this->fp = $this->$callback("select", $record);

			$this->fp = str_replace("\r", "", $this->fp);
		} else {
			$this->mode = "update";
			$this->update_record = $record;
			$this->fp = "";
		}
		return true;
	}

	function close() {
		if ($this->mode == "update") {
			$callback = $this->pointer[0]["callback"];
			$this->$callback("update", $this->update_record);
		}
		return true;
	}

	function get_all_views($mode, $record = false) {

		switch($mode) {
			case "ls":
				$qid = db_query("
					SELECT
						name
						,view
					FROM
						view
				");

				$list = array();
				while ($result = db_fetch_object($qid)) {
					$info = array();
					$info["type"] = "file";
					$info["name"] = $result->name;
					$info["size"] = strlen($result->view);

					$list[] = $info;
				}

				return $list;
				break;

			case "exists":
				$qid = db_query("
					SELECT
						id
					FROM
						view
					WHERE
						name = '$record'
				");

				return (db_num_rows($qid) != 0);
				break;

			case "size":
				$qid = db_query("
					SELECT
						view
					FROM
						view
					WHERE
						name = '$record'
				");

				return strlen(db_fetch_result($qid, "view"));
				break;

			case "select":
				$qid = db_query("
					SELECT
						view
					FROM
						view
					WHERE
						name = '$record'
				");

				return db_fetch_result($qid, "view");
				break;

			case "update":
				$qid = db_query("
					UPDATE
						view
					SET
						view = '$this->fp'
					WHERE
						name = '$record'
				");
				break;
		}
	}

	function get_checked_in_views($mode, $record = false) {

		switch($mode) {
			case "ls":
				$qid = db_query("
					SELECT
						name
						,view
					FROM
						view
				");

				$list = array();
				while ($result = db_fetch_object($qid)) {
					$info = array();
					$info["type"] = "file";
					$info["name"] = $result->name;
					$info["size"] = strlen($result->view);

					$list[] = $info;
				}

				return $list;
				break;

			case "exists":
				$qid = db_query("
					SELECT
						id
					FROM
						view
					WHERE
						name = '$record'
				");

				return (db_num_rows($qid) != 0);
				break;

			case "size":
				$qid = db_query("
					SELECT
						view
					FROM
						view
					WHERE
						name = '$record'
				");


				return strlen(db_fetch_result($qid, "view"));

		}
	}

	function get_all_layouts($mode, $record = false) {

		switch($mode) {
			case "ls":
				$qid = db_query("
					SELECT
						name
						,layout
					FROM
						layout
				");

				$list = array();
				while ($result = db_fetch_object($qid)) {
					$info = array();
					$info["type"] = "file";
					$info["name"] = $result->name;
					$info["size"] = strlen($result->layout);

					$list[] = $info;
				}

				return $list;
				break;

			case "exists":
				$qid = db_query("
					SELECT
						id
					FROM
						layout
					WHERE
						name = '$record'
				");

				return (db_num_rows($qid) != 0);
				break;

			case "size":
				$qid = db_query("
					SELECT
						layout
					FROM
						layout
					WHERE
						name = '$record'
				");

				return strlen(db_fetch_result($qid, "layout"));
				break;

			case "select":
				$qid = db_query("
					SELECT
						layout
					FROM
						layout
					WHERE
						name = '$record'
				");

				return db_fetch_result($qid, "layout");
				break;

			case "update":
				$qid = db_query("
					UPDATE
						layout
					SET
						layout = '$this->fp'
					WHERE
						name = '$record'
				");
				break;
		}
	}
}
?>