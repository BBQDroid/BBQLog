<?php

// Initial config
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once("config.php");
mysql_connect($CFG['SQL']['Host'], $CFG['SQL']['User'], $CFG['SQL']['Pass']);
mysql_select_db($CFG['SQL']['DB']);

// Prepare the CommitDate WHERE clause
if (!empty($_GET['sortCode']))
	$commitDateClause = " commits.CommitDate < '" . date("Y-m-d H:i:s", $_GET['sortCode']) . "' AND ";
else if (!empty($_GET['startDate']) && !empty($_GET['endDate']))
	$commitDateClause = " commits.CommitDate < '" . date("Y-m-d H:i:s", $_GET['startDate']) ."' AND commits.CommitDate > '".date("Y-m-d H:i:s", $_GET['endDate'])."' AND ";
else if (!empty($_GET['endDate']))
	$commitDateClause = " commits.CommitDate > '" . date("Y-m-d H:i:s", $_GET['endDate'])."' AND ";
else
	$commitDateClause = "";

// Fetch the Rom/Version ID couple from the DB based on the values passed
$Project = strtolower(mysql_real_escape_string($_GET['Project']));
$Version = strtolower(mysql_real_escape_string($_GET['Version']));
$Amount = intval($_GET['amount']);

//if ($Amount <= 0 || $Amount > 150)
	$Amount = 150;

$commits = mysql_query("
    SELECT * FROM commits
    WHERE
    $commitDateClause
    GitUsername='$Project' AND
    VersionNo='$Version'
    ORDER BY CommitDate DESC
    LIMIT $Amount
") or die(mysql_error());

$jsonData = array();

// Output commits with a structure similar to Gerrit's one
while ($commit = mysql_fetch_assoc($commits)) {
	$message = $commit['Message'];

	// cut the commit message at the first newline
	$newLinePos = strpos($message, "\n");
	if ($newLinePos != 0)
		$message = substr($message, 0, $newLinePos);

	$jsonData["result"]["changes"][] = array("id"=>0, "project"=>array("key"=>array("name"=>$commit['Repository'])), "lastUpdatedOn"=>$commit['CommitDate'], "subject"=>$message, "sortKey"=>strtotime($commit['CommitDate']), "repository"=>$commit['Repository'], "sha"=>$commit['SHA'], "gituser"=>$commit['GitUsername']);
}


echo json_encode($jsonData);
mysql_close();

?>
