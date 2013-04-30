<?php

/**
 * GitHub Updater script
 * Fetch changes from GitHub and pull them into a local database
 *
 * Copyright (c) The BBQTeam 2012
 * Version 5.0
 * This new version changed completely the logic behind the updater.
 * We now fetch the SHA from GitHub, verify the latest we have in DB
 * Then fetch commit until we get the latest SHA in DB.
 * All info are still loaded from DB into an array before starting to
 * fetch on Github. Reverted to use MySQL again.
 */

// In the newer php version, we need to set up the time zone.
date_default_timezone_set("Europe/Paris");

if (php_sapi_name() != "cli") {
	die("Changelog updater must be run from CLI!");
}

// Configs
set_time_limit(0);
require_once("/var/www/changelog/config.php");
require_once("/var/www/changelog/Thread.php");

/**
 * Escape the string to be given to MySQL
 */
function esc($txt) {
	return mysql_real_escape_string($txt);
}

/**
 * Parses a GitHub date
 */
function githubDate($date) {
	$a = new DateTime($date);
	$a->setTimezone(new DateTimeZone('UTC'));
	return $a->format("Y-m-d H:i:s");
}

// We output everything to a file, get it easy to debug the log.
function ob_file_callback($buffer)
{
  global $ob_file;
  fwrite($ob_file,$buffer);
}

$ob_file = fopen('log.txt', 'w');
ob_start('ob_file_callback');

// Connect to MySQL
mysql_connect($CFG['SQL']['Host'], $CFG['SQL']['User'], $CFG['SQL']['Pass'], TRUE) or die(mysql_error());
mysql_select_db($CFG['SQL']['DB']) or die(mysql_error());

mysql_query("START TRANSACTION;") or die(mysql_error());

$startTime = microtime(true);


$repoCounter = 0;
// Get all the information from all XML file.
// Improved version, we also fetch the latest SHA from DB here. This avois us to 
// use a thread to do it cause thread doesn't handle mysql connection well.
$files = scandir("/var/www/changelog/projects/");
$repoID = array();
$repoOwner = array();
$repoName = array();
$repoBranch = array();
$versionNo = array();
$lastCommitDBSHA = array();
$att = 'name';
$att2 = 'version';

$xml = simplexml_load_file("/var/www/changelog/projects/cm.xml");//.$project);
$reposgeneric = $xml->xpath("//genericlist");
$reposspecific = $xml->xpath("//specificlist");
$owner = (string)$xml->owner;
foreach($reposgeneric as $value)
{
	foreach($value->children() as $git)
	{
		$reponame = (string)$git->attributes()->$att;
		foreach($git->children() as $branch)
		{
			$branchname = (string)$branch->attributes()->$att;
                        $versionNo[$repoCounter] = $branch->attributes()->$att2;
                        $repoID[$repoCounter] = $repoCounter;
                        $repoOwner[$repoCounter] = $owner;
                        $repoName[$repoCounter] = $reponame;
                        $repoBranch[$repoCounter] = $branchname;
                        $lastCommitDB = mysql_query("SELECT SHA FROM commits WHERE Repository = '".$repoName[$repoCounter]."' AND GitUsername = '".$repoOwner[$repoCounter]."' AND Branch = '".$repoBranch[$repoCounter]."' ORDER BY CommitDate DESC LIMIT 1") or die(mysql_error());
                        $fetch = mysql_fetch_assoc($lastCommitDB);
                        $lastCommitDBSHA[$repoCounter] = $fetch['SHA'];
                        $repoCounter++;
		}
	}
}
foreach($reposspecific as $value)
{
	foreach($value->children() as $git)
        {
        	$reponame = (string)$git->attributes()->$att;
                foreach($git->children() as $branch)
                {
                	$branchname = (string)$branch->attributes()->$att;
                        $versionNo[$repoCounter] = $branch->attributes()->$att2;
                        $repoID[$repoCounter] = $repoCounter;
                        $repoOwner[$repoCounter] = $owner;
                        $repoName[$repoCounter] = $reponame;
                        $repoBranch[$repoCounter] = $branchname;
			$lastCommitDB = mysql_query("SELECT SHA FROM commits WHERE Repository = '".$repoName[$repoCounter]."' AND GitUsername = '".$repoOwner[$repoCounter]."' AND Branch = '".$repoBranch[$repoCounter]."' ORDER BY CommitDate DESC LIMIT 1") or die(mysql_error());
			$fetch = mysql_fetch_assoc($lastCommitDB);
                        $lastCommitDBSHA[$repoCounter] = $fetch['SHA'];
                        $repoCounter++;
		}
	}
}

function fetchLastCommitSHA($pRepoId, $pRepoOwner, $pRepoName, $pRepoBranch, $pCFG, $pContext)
{
	global $lastCommitDBSHA;
	global $distinctRepo;
	global $context;
	global $versionNo;
	$branches_github = "";
	$i = 0;
	$exit = false;
	do
	{
		$branches_github = file_get_contents("https://api.github.com/repos/".$pRepoOwner."/".$pRepoName."/branches/".$pRepoBranch, false, $pContext );
		if(empty($branches_github))
		{
			$i++;
			sleep(2);
			echo "\nSomething went wrong with: ".$pRepoName.". Will try again in 2 sec.";
		}
		else
		{
			$exit = true;
		}
	}while(($i < 6) and (!$exit));
	if($i == 5)
	{
		echo "Error loading branches for ".$pRepoOwner."/".$pRepoName."\n";
		echo "\nThe thread was killed for your safety\n";
		posix_kill(getmypid(), 9);
	}
	else
	{
		$branches_json = json_decode($branches_github, true);
		if(strlen($lastCommitDBSHA[$pRepoId]) != 40)
		{
			$branches_github = file_get_contents("https://api.github.com/repos/".$pRepoOwner."/".$pRepoName."/commits?per_page=100&sha=".$branches_json['commit']['sha'], false, $pContext);
			$branches_json = json_decode($branches_github, true);
			$nbCommits = 0;
			foreach($branches_json as $gitCommits)
			{
				mysql_query("INSERT INTO commits(SHA,GitUsername,Repository,Branch,Author,Message,CommitDate,VersionNo) VALUES('" . esc($gitCommits['sha']) ."', '".esc($pRepoOwner)."' ,'".esc($pRepoName)."', '".esc($pRepoBranch)."', '".esc($gitCommits['commit']['committer']['name'])."', '".esc($gitCommits['commit']['message'])."', '".esc(githubDate($gitCommits['commit']['committer']['date']))."','".$versionNo[$pRepoId]."');") or die(mysql_error());
				$nbCommits++;
			}
			echo "\n----------------------------------------------------------------------------------------------------";
			echo "\nRepo ".$pRepoName. " at branch ".$pRepoBranch." is fresh and added ".$nbCommits." commits.\n";
			echo "\n----------------------------------------------------------------------------------------------------";
			posix_kill(getmypid(), 9);
		}
		elseif($branches_json['commit']['sha'] != $lastCommitDBSHA[$pRepoId] && $branches_json['commit']['sha'] != "")
		{
			$branches_github = file_get_contents("https://api.github.com/repos/".$pRepoOwner."/".$pRepoName."/commits?per_page=100&sha=".$branches_json['commit']['sha'], false, $pContext);
			$branches_json = json_decode($branches_github, true);
			$doneFlag = false;
			$nbCommits = 0;
			foreach($branches_json as $gitCommits)
			{
				if(($lastCommitDBSHA[$pRepoId] != $gitCommits['sha']) and !$doneFlag)
				{
                   			mysql_query("INSERT INTO commits(SHA,GitUsername,Repository,Branch,Author,Message,CommitDate,VersionNo) VALUES('" . esc($gitCommits['sha']) ."', '".esc($pRepoOwner)."' ,'".esc($pRepoName)."', '".esc($pRepoBranch)."', '".esc($gitCommits['commit']['committer']['name'])."', '".esc($gitCommits['commit']['message'])."', '".esc(githubDate($gitCommits['commit']['committer']['date']))."','".$versionNo[$pRepoId]."');") or die(mysql_error());
                    			$nbCommits++;
				}
				else
				{
					$doneFlag = true;
				}
			}
			echo "\n----------------------------------------------------------------------------------------------------";
			echo "\nAdded ".$nbCommits." commits into ".$pRepoName."\n";
			echo "\n----------------------------------------------------------------------------------------------------";
			posix_kill(getmypid(), 9);
		}
		else
		{
			//This is used to know if the Repo were up to date. Create too much overhead
			// in the output so it's disabled.
			//echo "\n".$branches_json['commit']['sha']."   ".$lastCommitDBSHA[$pRepoId];
			echo "\n----------------------------------------------------------------------------------------------------";
			echo "Repo ".$pRepoName. " at branch ".$pRepoBranch." was up to date.\n";
			echo "\n----------------------------------------------------------------------------------------------------";
			posix_kill(getmypid(), 9);
		}
	}
}


// Here we start the import for the commits
$distinctRepo = 0;

$Threads = array();
for ($i = 0; $i < 12; $i++) {
	$Threads[$i] = new Thread("fetchLastCommitSHA");
}

foreach($repoID as $repoNo)
{
    $threadFound = false;
	while (!$threadFound)
	{
		usleep(50);
		$i=1;
		foreach($Threads as $thread)
		{
			if (!$thread->isAlive())
			{
				if($i%2)
				{
					$threadFound = true;
					$thread->start($repoNo, $repoOwner[$repoNo], $repoName[$repoNo], $repoBranch[$repoNo], $CFG, $CFG['context'][0]);
					break;
				}
				else
				{
					$threadFound = true;
					$thread->start($repoNo, $repoOwner[$repoNo], $repoName[$repoNo], $repoBranch[$repoNo], $CFG, $CFG['context'][1]);
					break;
				}
			}
		$i++;
		}
	}
}

echo "\n=================================";
echo "\nOperations done! Waiting on threads to finish...";
echo "\n=================================";

$threadsDone = true;
$selfkill = false;
$overtime = 0;
do
{
	$threadsDone = true;
	foreach ($Threads as $index => $thread)
	{
		if ($thread->isAlive())
		{
			echo "\nWaiting thread $index...";
			$threadsDone = false;
			break;
		}
	}
	sleep(1);
	$overtime++;

	if ($overtime >= 10) {
		echo "\nWarning: Timed out waiting on threads to finish. I'll kill them ALL WITH FIRE!";
		foreach($Threads as $thread)
		{
			$thread->kill();
		}
		break;
	}
} while ($threadsDone == false);

mysql_query("COMMIT;") or die(mysql_error());

if (isset($params["c"])) {
	// We keep only the commits of the last 4 months
	echo "Cleaning 4+months old commits\n";
	mysql_error("DELETE FROM commits WHERE CommitDate < '".date("Y-m-d H:i:s", time()-3600*24*31*4). "'") or die(mysql_error());
	echo "Cleaned " . mysql_affected_rows() . " commits\n";
}

//echo "\nGITHUB REQUESTS: " . $nbGitHubRequests . "\n";
//echo "\nFor ".$distinctRepo." distincts repos.";
//echo "\n";
$duration = floatval(microtime(true) - $startTime);
echo "\n\nTook ".$duration." seconds to verify and update ALL THE REPOS\n";
$nbrequest = file_get_contents("https://api.github.com/rate_limit", false, $CFG['context'][1]);
$nbrequest_decoded = json_decode($nbrequest,true);
echo "\nWe have : ".$nbrequest_decoded['rate']['remaining']." GitHub requests left \n";
ob_end_flush();
?>
