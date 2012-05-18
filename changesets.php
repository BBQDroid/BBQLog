<?php

error_reporting(E_ALL & ~E_NOTICE);

require_once("config.php");
mysql_connect($CFG['SQL']['Host'], $CFG['SQL']['User'], $CFG['SQL']['Pass']);
mysql_select_db($CFG['SQL']['DB']);

if (!empty($_GET['sortCode']))
	$commitDateClause = " AND CommitDate < '" . date("Y-m-d H:i:s", $_GET['sortCode']) . "' ";
else
	$commitDateClause = "";

$commits = mysql_query("SELECT * FROM commits $commitDateClause AND GitUsername IN () ORDER BY CommitDate DESC LIMIT 30");

$jsonData = array();

while ($commit = mysql_fetch_assoc($commits)) {
	$message = $commit['Message'];
	if (strpos($message, "\n") != 0)
		$message = substr($message, 0, strpos($message, "\n"));

	$jsonData["result"]["changes"][] = array("id"=>0, "project"=>array("key"=>array("name"=>$commit['Repository'])), "lastUpdatedOn"=>$commit['CommitDate'], "subject"=>$message, "sortKey"=>strtotime($commit['CommitDate']));
}

echo json_encode($jsonData);

?>
