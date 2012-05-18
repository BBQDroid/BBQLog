<?php

error_reporting(E_ALL & ~E_NOTICE);

require_once("config.php");
mysql_connect($CFG['SQL']['Host'], $CFG['SQL']['User'], $CFG['SQL']['Pass']);
mysql_select_db($CFG['SQL']['DB']);

if (!empty($_GET['sortCode']))
	$commitDateClause = " commits.CommitDate < '" . date("Y-m-d H:i:s", $_GET['sortCode']) . "' AND ";
else
	$commitDateClause = "";

$RomName = mysql_real_escape_string($_GET['RomName']);
$RomVersion = mysql_real_escape_string($_GET['Version']);

$commits = mysql_query("
    SELECT * FROM (SELECT * FROM repositories WHERE IDRomVersion=
    (
        SELECT IDRomVersion FROM roms_versions WHERE VersionName='$RomVersion' AND IDRom=
        (
            SELECT IDRom FROM roms WHERE Name='$RomName' LIMIT 1
        ) LIMIT 1
    )) repos, commits
    WHERE
    $commitDateClause
    commits.GitUsername=repos.GitUsername AND
    commits.Repository=repos.Repository AND
    commits.Branch=repos.Branch
    ORDER BY CommitDate DESC
    LIMIT 30
");

$jsonData = array();

while ($commit = mysql_fetch_assoc($commits)) {
	$message = $commit['Message'];
	if (strpos($message, "\n") != 0)
		$message = substr($message, 0, strpos($message, "\n"));

	$jsonData["result"]["changes"][] = array("id"=>0, "project"=>array("key"=>array("name"=>$commit['Repository'])), "lastUpdatedOn"=>$commit['CommitDate'], "subject"=>$message, "sortKey"=>strtotime($commit['CommitDate']), "repository"=>$commit['Repository'], "sha"=>$commit['SHA'], "gituser"=>$commit['GitUsername']);
}

echo json_encode($jsonData);

?>
