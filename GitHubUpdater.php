<?php

/**
 * GitHub Updater script
 * Fetch changes from GitHub and pull them into a local database
 *
 * Copyright (c) The BBQTeam 2012
 * Version 3.0
 * This version require the Database to modified a bit.
 * We add a field, CommitNo which is an integer
 * Version 3.0 added the option to use with any rom, not only CM.
 */

if (php_sapi_name() != "cli") {
	die("Changelog updater must be run from CLI!");
}

// Configs
set_time_limit(0);
require_once("config.php");
require_once("Thread.php");

// To "bypass" GitHub API limitations, we take advantages of the two IPs we have on our
// server. One IP will be used for SHA branch fetch, and one for fetching the commits.
$opts = array(
    'socket' => array(
        'bindto' => '176.9.149.130:0',
    )
);
$opts_2 = array(
    'socket' => array(
        'bindto' => '176.9.149.152:0',
    )
);

// create the context... for SHA fetch
$context[0] = stream_context_create($opts);
// ... for commits
$context[1] = stream_context_create($opts_2);

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

$repoCounter = 0;
// Get all the information from all XML file.

$files = scandir("./projects/");
$repoID = array();
$repoOwner = array();
$repoName = array();
$repoBranch = array();
$versionNo = array();

foreach($files as $project)
{
	if(substr($project, -4) == ".xml")
	{
		$xml = simplexml_load_file("./projects/".$project);
		$git = $xml->xpath("//git");
		$owner = (string)$xml->owner;
		foreach($git as $value)
		{
			$att = 'name';
			$reponame = (string)$value->attributes()->$att;
			foreach($value->children() as $branch)
			{
				$branchname = (string)$branch->attributes()->$att;
				$versionNo[$repoCounter] = $branch;
				$repoID[$repoCounter] = $repoCounter;
				$repoOwner[$repoCounter] = $owner;
				$repoName[$repoCounter] = $reponame;
				$repoBranch[$repoCounter] = $branchname;
				$repoCounter = $repoCounter + 1;
			}
		}
	}
}

// We will verify here if we have redundant repo and remove them by modifying the
// Owner name to "nothing". I believe it's a dirty hack, but it work. To be reworked more
// clearly.
$repoCounter = 0;

foreach($repoID as $repoNo)
{
	foreach($repoID as $repoNo2)
	{
		if(($repoNo2 != $repoNo) and ($repoOwner[$repoNo] == $repoOwner[$repoNo2]) and ($repoName[$repoNo] == $repoName[$repoNo2]) and ($repoBranch[$repoNo] == $repoBranch[$repoNo2]) and ($repoOwner[$repoNo] != "nothing"))
		{
			//echo "\nThe repo ".$repoName[$repoNo]." was removed. No : ".$repoNo;
			$repoOwner[$repoNo2] = "nothing";
			$repoCounter++;
		}
	}
}
// Flush after every echo
ob_implicit_flush(TRUE);

$nbGitHubRequests = 0;

// Connect to MySQL
mysql_connect($CFG['SQL']['Host'], $CFG['SQL']['User'], $CFG['SQL']['Pass'], TRUE) or die(mysql_error());
mysql_select_db($CFG['SQL']['DB']) or die(mysql_error());

mysql_query("START TRANSACTION;") or die(mysql_error());

$startTime = microtime(true);
$lastCommitDBSHA = array();

function fetchLastCommitSHA($pRepoId, $pRepoOwner, $pRepoName, $pRepoBranch) {
	global $lastCommitDBSHA;
	global $distinctRepo;
        global $nbGitHubRequests;
	global $context;

	// Get
	$i = 0;
	while (!@$branches_github = file_get_contents("https://api.github.com/repos/".$pRepoOwner."/".$pRepoName."/branches", false, $context[0])) {
		$i++;
		if ($i > 5) {
			echo "Error loading branches for ".$pRepoOwner."/".$pRepoName."\n";
			posix_kill(getmypid(), 9);
		}
		sleep(3);
	}

	$nbGitHubRequests++;
	$branches_json = json_decode($branches_github, true);

	// Verify each branch on github if it has a match on DB. If it has a match on DB, store the latest commit SHA
	// in a array.
	$badFlag = true;
	foreach($branches_json as $gitBranch)
	{
		if ((string)$gitBranch['name'] == $pRepoBranch)// && ($lastCommitDB == "" || $lastCommitSHA != $lastCommitDB))
		{
				$shm_id = shmop_open($pRepoId+1, "c", 0777, 1000);
				// $lastCommitSHA[$pRepoId] = $gitBranch['commit']['sha'];
				shmop_write($shm_id, $gitBranch['commit']['sha'], 0);
				shmop_close($shm_id);

				if((strlen($lastCommitDBSHA[$pRepoId]) != 40) or ($lastCommitDBSHA[$pRepoId] != $gitBranch['commit']['sha']))
				{
					// That part is solely for debug/verify purpose. Usefull to output altought.
					echo "\n-------------------------------------------------------------------";
					echo "\nRepo ID :			".$pRepoId;
					echo "\nCurrent repo : 		".$pRepoName;
					echo "\nCurrent branch : 	".$gitBranch['name'];
					echo "\nFound Latest SHA :	".$gitBranch['commit']['sha'];
					echo "\nLastest SHA in db:	".$lastCommitDBSHA[$pRepoId];
					echo "\n-------------------------------------------------------------------";
				}
			$distinctRepo++;
			$badFlag = false;
			break;
		}
	}
	if((bool)$badFlag)
	{
		echo "\n-------------------------------------------------------------------";	
		echo "\nSomething went wrong while updating: ".$pRepoName;
		echo "\n-------------------------------------------------------------------";
	}
	posix_kill(getmypid(), 9);
}


// Here we start the import for the commits
$distinctRepo = 0;

$Threads = array();
for ($i = 0; $i < 12; $i++) {
	$Threads[$i] = new Thread("fetchLastCommitSHA");
}

foreach($repoID as $repoNo)
{
	if($repoOwner[$repoNo] != "nothing")
	{
		// Get the latest commit SHA for the current REPO in Database.
		$lastCommitDB = mysql_query("SELECT SHA FROM commits WHERE Repository = '".$repoName[$repoNo]."' AND GitUsername = '".$repoOwner[$repoNo]."' AND Branch = '".$repoBranch[$repoNo]."' ORDER BY CommitDate DESC LIMIT 0,1") or die(mysql_error());
		$fetch = mysql_fetch_assoc($lastCommitDB);
		$lastCommitDBSHA[$repoNo] = $fetch['SHA'];

                $threadFound = false;
		while (!$threadFound) {
			sleep(0.1);
	                foreach($Threads as $thread) {
				if (!$thread->isAlive()) {
					$threadFound = true;
			                $thread->start($repoNo, $repoOwner[$repoNo], $repoName[$repoNo], $repoBranch[$repoNo]);
					break;
				}
			}
		}
	}
}

$threadsDone = true;
$overtime = 0;
do
{
	$threadsDone = true;
	foreach ($Threads as $thread)
	{
		if ($thread->isAlive())
		{
			$threadsDone = false;
			break;
		}
	}
	sleep(1);
	$overtime++;

	if ($overtime >= 5)
		break;
} while ($threadsDone == false);

unset($Threads);

function fetchGitHubCommit($pRepoId, $pRepoOwner, $pRepoName, $pRepoBranch, $pVersionNo) {
        global $lastCommitDBSHA;
	global $nbGitHubRequests;
	global $context;

	$shm_id = shmop_open($pRepoId+1, "a", 0777, 1000);
	$lastCommitSHA_Value = trim(shmop_read($shm_id, 0, 1000));
	shmop_close($shm_id);

	// If the repo has no valid change, we import the 100 latest change from it.
	if(strlen($lastCommitDBSHA[$pRepoId]) != 40)
	{
		$i = 0;
		$url = "https://api.github.com/repos/".$pRepoOwner."/".$pRepoName."/commits?per_page=100&sha=".$lastCommitSHA_Value;
		while (!$commits_github = file_get_contents($url, false, $context[1]))
		{
			$i++;
			if ($i > 5)
			{
				echo "Error loading commits for ".$pRepoOwner."/".$pRepoName." at " . $url . " !\n";
				posix_kill(getmypid(), 9);
			}
			sleep(2);
		}
		$nbGitHubRequests++;
		$commits_json = json_decode($commits_github, true);
		$commitsCounter = 0;
		foreach($commits_json as $gitCommits)
		{
			//echo "\n-------------------------------------------------------------------";
			//echo "\n"."SHA			:".$gitCommits['sha'];
			//echo "\n"."GitUsername	:".$repoOwner[$repoNo];
			//echo "\n"."Repository	:".$repoName[$repoNo];
			//echo "\n"."Branch		:".$repoBranch[$repoNo];
			//echo "\n"."Author		:".$gitCommits['commit']['author']['name'];
			//echo "\n"."Message		:".$gitCommits['commit']['message'];
			//echo "\n"."CommitDate	:".$gitCommits['commit']['author']['date'];
			//echo "\n-------------------------------------------------------------------";
			$commitsCounter++;
			mysql_query("INSERT INTO commits(SHA,GitUsername,Repository,Branch,Author,Message,CommitDate,VersionNo) VALUES('" . esc($gitCommits['sha']) ."', '".esc($pRepoOwner)."' ,'".esc($pRepoName)."', '".esc($pRepoBranch)."', '".esc($gitCommits['commit']['committer']['name'])."', '".esc($gitCommits['commit']['message'])."', '".esc(githubDate($gitCommits['commit']['committer']['date']))."','".$pVersionNo."');") or die(mysql_error());
		}
		echo "\n-------------------------------------------------------------------";
		echo "\n"."Freshly added ".$commitsCounter." commits to repo ".$pRepoName." for branch ".$pRepoBranch;
		echo "\n-------------------------------------------------------------------";
		mysql_query("COMMIT;") or die(mysql_error());
	}
	// If the repo has a valid SHA, then we verify if the Latest GitHub change and the Latest DB change are the same. If yes
	// We skip, if No, we fetch until they are the same.
	elseif($lastCommitDBSHA[$pRepoId] != $lastCommitSHA_Value)
	{
		$i = 0;
		while (!@$commits_github = file_get_contents("https://api.github.com/repos/".$pRepoOwner."/".$pRepoName."/commits?per_page=100&sha=".$lastCommitSHA_Value, false, $context[1]))
		{
			$i++;
			if ($i > 5)
			{
				echo "Error loading commits for ".$pRepoOwner."/".$pRepoName."\n";
				continue 2;
			}
			sleep(3);
		}
		$nbGitHubRequests++;
		$commits_json = json_decode($commits_github, true);
		$commitsCounter = 0;
		foreach($commits_json as $gitCommits)
		{
			if($gitCommits['sha'] != $lastCommitDBSHA[$pRepoId])
			{
				//echo "\n-------------------------------------------------------------------";
				//echo "\n"."SHA			:".$gitCommits['sha'];
				//echo "\n"."GitUsername	:".$repoOwner[$repoNo];
				//echo "\n"."Repository	:".$repoName[$repoNo];
				//echo "\n"."Branch		:".$repoBranch[$repoNo];
				//echo "\n"."Author		:".$gitCommits['commit']['author']['name'];
				//echo "\n"."Message		:".$gitCommits['commit']['message'];
				//echo "\n"."CommitDate	:".$gitCommits['commit']['author']['date'];
				//echo "\nRepo Updated succesfully";
				//echo "\n-------------------------------------------------------------------";
				$commitsCounter++;
				mysql_query("INSERT INTO commits(SHA,GitUsername,Repository,Branch,Author,Message,CommitDate,VersionNo) VALUES('" . esc($gitCommits['sha']) ."', '".esc($pRepoOwner)."' ,'".esc($pRepoName)."', '".esc($pRepoBranch)."', '".esc($gitCommits['commit']['committer']['name'])."', '".esc($gitCommits['commit']['message'])."', '".esc(githubDate($gitCommits['commit']['committer']['date']))."','".$pVersionNo."');") or die(mysql_error());
			}
			else
			{
				mysql_query("COMMIT;") or die(mysql_error());
				break;
			}
		}
		echo "\n-------------------------------------------------------------------";
		echo "\n".$commitsCounter." commits has been added to repository : ".$pRepoName." for branch : ".$pRepoBranch;
		echo "\n-------------------------------------------------------------------";
	}
	//else
	//{
	//	echo "\n-------------------------------------------------------------------";
	//	echo "\n"."Repository ".$repoName[$repoNo]." Is up to date";
	//	echo "\n-------------------------------------------------------------------";
	//}
	posix_kill(getmypid(), 9);
}

$Threads = array();
for ($i = 0; $i < 12; $i++) {
	$Threads[$i] = new Thread("fetchGitHubCommit");
}

// We Start the import of the latest commits for all the repo.
foreach($repoID as $repoNo)
{
	// We remove the repo marked redundant
	if($repoOwner[$repoNo] != "nothing")
	{
                $threadFound = false;
		while (!$threadFound) {
			usleep(100000); // 100ms
	                foreach($Threads as $thread) {
				if (!$thread->isAlive()) {
					$threadFound = true;
			                $thread->start($repoNo, $repoOwner[$repoNo], $repoName[$repoNo], $repoBranch[$repoNo], $versionNo[$repoNo]);
					break;
				}
			}
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
		echo "\nWarning: Timed out waiting on threads to finish. I'll kill myself to avoid zombie!";
		$selfkill = true;
		break;
	}
} while ($threadsDone == false);

mysql_query("COMMIT;") or die(mysql_error());

if (isset($params["c"])) {
	// We keep only the commits of the last 4 months
	echo "Cleaning 4+months old commits\n";
	mysql_query("DELETE FROM commits WHERE CommitDate < '".date("Y-m-d H:i:s", time()-3600*24*31*4). "'") or die(mysql_error());
	echo "Cleaned " . mysql_affected_rows() . " commits\n";
}

echo "\nGITHUB REQUESTS: " . $nbGitHubRequests . "\n";
echo "\nFor ".$distinctRepo." distincts repos.";
echo "\n";
$duration = floatval(microtime(true) - $startTime);
echo $duration."s elapsed\n";

if ($selfkill) {
	posix_kill(getmypid(),9);
}

?>
