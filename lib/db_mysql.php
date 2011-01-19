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

if (! isset($DB_DIE_ON_FAIL)) $DB_DIE_ON_FAIL = true;
if (! isset($DB_DEBUG)) $DB_DEBUG = true;

function db_connect($dbhost, $dbname, $dbuser, $dbpass) {

	global $CFG, $DB_DIE_ON_FAIL, $DB_DEBUG;

	if (! $dbh = @mysql_connect($dbhost, $dbuser, $dbpass)) {
		if ($DB_DEBUG) {
			$CFG->log->write("MySQL: Can't connect to $dbhost as $dbuser\n");
			$CFG->log->write("MySQL: Error: ", mysql_error() . "\n");
		} else {
			$CFG->log->write("MySQL: Database error encountered\n");
		}

		if ($DB_DIE_ON_FAIL) {
			$CFG->log->write("MySQL: This script cannot continue, terminating.\n");
			die();
		}
	}

	$selection = mysql_select_db($dbname, $dbh);

	if (! $selection) {
		if ($DB_DEBUG) {
			echo "Can't select database $dbname";
			echo "MySQL Error: ", mysql_error();
		} else {
			echo "Database error encountered";
		}

		if ($DB_DIE_ON_FAIL) {
			echo "This script cannot continue, terminating.";
			die();
		}
	}

	return $dbh;
}

function db_disconnect($link = "") {
	global $CFG;

	if (! $link) $link = $CFG->dblink;
	mysql_close($link);
}

function db_query($query, $link = "", $debug=false, $die_on_debug=true, $silent=false) {
 
	global $DB_DIE_ON_FAIL, $DB_DEBUG, $CFG;
	
	// echo $DB_QUERY_COUNT;
	if (! $link) $link = $CFG->dblink;
	
	if ($debug) {
		echo "$query\n";

		if ($die_on_debug) die;
	}

	$qid = mysql_query($query, $link);

	if (! $qid && ! $silent) {
		if ($DB_DEBUG) {
			$CFG->log->write("MySQL: Can't execute query: $query\n");
			$CFG->log->write("MySQL: Error (" . mysql_errno() . "): " . mysql_error(). "\n");
		} else {
			$CFG->log->write("MySQL: Database error encountered.\n");
		}

		if ($DB_DIE_ON_FAIL) {
			$CFG->log->write("MySQL: This script cannot continue, terminating.\n");
			die();
		}
	}

	return $qid;
}

function db_fetch_array($qid) {
	
	return mysql_fetch_array($qid);
}

function db_fetch_row($qid) {

	return mysql_fetch_row($qid);
}

function db_fetch_object($qid) {

	return mysql_fetch_object($qid);
}

function db_num_rows($qid) {

	return mysql_num_rows($qid);
}

function db_affected_rows($link = "") {
	global $CFG;
	
	if (! $link) $link = $CFG->dblink;
	return mysql_affected_rows($link);
}

function db_insert_id($link = "") {
	global $CFG;
	
	if (! $link) $link = $CFG->dblink;
	return mysql_insert_id($link);
}

function db_free_result($qid) {

	mysql_free_result($qid);
}

function db_num_fields($qid) {

	return mysql_num_fields($qid);
}

function db_field_name($qid, $fieldno) {

	return mysql_field_name($qid, $fieldno);
}

function db_data_seek($qid, $row) {

	if (db_num_rows($qid)) { return mysql_data_seek($qid, $row); }
}

function db_fetch_field($qid, $field) {

	$result = db_fetch_object($qid);
	return $result->$field;
}

function db_list_fields($db, $table, $link = "") {
	global $CFG;
	
	if (! $link) $link = $CFG->dblink;
	return mysql_list_fields($db, $table, $link);
}

function db_fetch_result($qid, $field) {

	$result = db_fetch_object($qid);
	return $result->$field;
}

?>
