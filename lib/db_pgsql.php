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

/*
this is the postgresql interface for nanoftpd (based on the mysql interface)
---
see docs/README.pgsql for details...
*/

if (! isset($DB_DIE_ON_FAIL)) $DB_DIE_ON_FAIL = true;
if (! isset($DB_DEBUG)) $DB_DEBUG = false;

function db_connect($dbhost, $dbname, $dbuser, $dbpass) {

	global $DB_DEBUG, $DB_DIE_ON_FAIL;

	if (! $dbh = pg_connect("host=$dbhost user=$dbuser password=$dbpass dbname=$dbname")) {
	    if ($DB_DEBUG) {
		echo "Can't connect to $dbhost as $dbuser (database: $dbname)\n";
		echo "PostgreSQL error: ".pg_last_error($dbh)."\n";
	    } else {
		echo "Database error encountered.\n";
	    }
	    
	    if ($DB_DIE_ON_FAIL) {
		echo "This script cannot continue, terminating.\n";
		die();
	    }
	}

	return $dbh;
}

function db_disconnect($link = "") {
	global $CFG;

	if (! $link) $link = $CFG->dblink;
	pg_close($link);
}

function db_query($query, $link = "", $debug=false, $die_on_debug=true, $silent=false) {
 
	global $DB_DIE_ON_FAIL, $DB_DEBUG, $CFG, $DB_QUERY_COUNT;
	static $query_count;
	
	$query_count++;
	$DB_QUERY_COUNT = $query_count;
	if (! $link) $link = $CFG->dblink;
	
	if ($debug) {
		echo "$query";

		if ($die_on_debug) die;
	}

	$qid = pg_query($link, $query);

	if (! $qid && ! $silent) {
		if ($DB_DEBUG) {
			echo "Can't execute query: $query\n";
			echo "PostgreSQL Error: ".pg_last_error()."\n";
		} else {
			echo "Database error encountered.\n";
		}

		if ($DB_DIE_ON_FAIL) {
			echo "This script cannot continue, terminating.\n";
			die();
		}
	}

	return $qid;
}

function db_fetch_array($qid) {

	return pg_fetch_array($qid);
}

function db_fetch_row($qid) {

	return pg_fetch_row($qid);
}

function db_fetch_object($qid) {

	return pg_fetch_object($qid);
}

function db_num_rows($qid) {

	return pg_num_rows($qid);
}

function db_affected_rows($link = "") {
	global $CFG;
	
	if (! $link) $link = $CFG->dblink;
	return pg_affected_rows($link);
}

function db_free_result($qid) {

	pg_free_result($qid);
}

function db_num_fields($qid) {

	return pg_num_fields($qid);
}

function db_field_name($qid, $fieldno) {

	return pg_field_name($qid, $fieldno);
}

function db_fetch_field($qid, $field) {

	$result = db_fetch_object($qid);
	return $result->$field;
}

?>
