<?php

/**
 * GitHub Updater script
 * Fetch changes from GitHub and pull them into a local database
 *
 * Copyright (c) The BBQTeam 2012
 *
 */
if (php_sapi_name() != "cli") {
	die("Changelog updater must be run from CLI!");
}

// Configs
set_time_limit(0);
require_once("config.php");


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


// Flush after every echo
ob_implicit_flush(TRUE);

$nbGitHubRequests = 0;

// Connect to MySQL
mysql_connect($CFG['SQL']['Host'], $CFG['SQL']['User'], $CFG['SQL']['Pass']) or die(mysql_error());
mysql_select_db($CFG['SQL']['DB']) or die(mysql_error());

mysql_query("START TRANSACTION;") or die(mysql_error());

$startTime = microtime(true);


// Start import
echo "Starting repositories processing...\n";

$params = getopt("c", array("repo::"));
if (!empty($params["repo"])) {
	echo "Processing ".$params["repo"]."\n";
	$repo = $params["repo"];
}

// If a specific repo is passed in GET, update only this repository
if (!empty($params["repo"])) {
	$repositories = mysql_query("SELECT * FROM repositories WHERE Repository='".esc($params["repo"])."' ");
} else {
	$repositories = mysql_query("SELECT DISTINCT * FROM repositories GROUP BY GitUsername,Repository;") or die(mysql_error());
}

while ($repo = mysql_fetch_assoc($repositories)) {

	echo "\n".str_repeat("-", 100)."\n";
	echo "Processing ".$repo['GitUsername']."/".$repo['Repository']."\n";

	// load last commit of the branch
	$i = 0;
	while (!@$branches_github = file_get_contents("https://api.github.com/repos/".$repo['GitUsername']."/".$repo['Repository']."/branches")) {
		$i++;
		if ($i > 5) {
			echo "Error loading branches for ".$repo['GitUsername']."/".$repo['Repository']."\n";
			continue 2;
		}
		sleep(3);
	}
	$nbGitHubRequests++;

	$branches_json = json_decode($branches_github, true);

	$branches_sql = mysql_query("SELECT Branch FROM repositories WHERE GitUsername='".esc($repo['GitUsername'])."' AND Repository='".esc($repo['Repository'])."'");

	while ($branch = mysql_fetch_assoc($branches_sql)) {
		// get the latest commit in db
		$query = mysql_query("SELECT SHA FROM commits WHERE GitUsername='".esc($repo['GitUsername'])."' AND Repository='".esc($repo['Repository'])."' AND Branch='".esc($branch['Branch'])."' ORDER BY CommitDate DESC LIMIT 1");
		$fetch = mysql_fetch_assoc($query);
		$lastCommitDB = $fetch['SHA'];


                // get the latest commit on GitHub
                $lastCommitSHA = "";
                foreach($branches_json as $gitBranch) {
                        if ($gitBranch['name'] == $branch['Branch'] && ($lastCommitDB == "" || $lastCommitSHA != $lastCommitDB)) {
                                $lastCommitSHA = $gitBranch['commit']['sha'];
                                echo "Found branch " . $branch['Branch'] . " (last commit: $lastCommitSHA)\n";
                                break;
                        }
                }

		if (empty($lastCommitSHA)) {
			// the last commit in DB is already the latest one of the branch, skipping
			continue;
		}

		echo "Fetching commits from ".$repo['GitUsername']."/".$repo['Repository']." from branch ".$branch['Branch']."\n";
		$commitSHA = $lastCommitSHA;
		$nbFetched = 0;
		$previousFetchedCommit = "";
		$inserts = 0;

		// we limit max 20 requests per branch (thats 2000 commits)
		while ($nbFetched < 20 && $previousFetchedCommit != $commitSHA) {
			$previousFetchedCommit = $commitSHA;

			$i = 0;
			while (!@$commits_github = file_get_contents("https://api.github.com/repos/".$repo['GitUsername']."/".$repo['Repository']."/commits?per_page=100&sha=$commitSHA")) {
				$i++;
				if ($i > 5) {
					echo "Error loading changes for ".$repo['GitUsername']."/".$repo['Repository'].":".$commitSHA;
					continue 4;
				}
				sleep(3);
			}

			$commits_json = json_decode($commits_github,true);
			$nbGitHubRequests++;
			$lastReached = false;

			foreach($commits_json as $commit) {
				if ($commit['sha'] == $lastCommitDB || strtotime(githubDate($commit['commit']['committer']['date'])) < time()-3600*24*31*4) { // max 4 months
					$lastReached = true;
					break;
				}

				// Merge commits
				//if ($commit['committer'] == null) {
				//	mysql_query("UPDATE commits SET CommitDate='".githubDate($commit['commit']['committer']['date'])."' WHERE SHA='".$commit['parents'][1]["sha"]."'");
				//} else {
					mysql_query("INSERT IGNORE INTO commits(SHA,GitUsername,Repository,Branch,Author,Message,CommitDate) VALUES('" . esc($commit['sha']) ."', '".esc($repo['GitUsername'])."' ,'".esc($repo['Repository'])."', '".esc($branch['Branch'])."', '".esc($commit['committer']['login'])."', '".esc($commit['commit']['message'])."', '".esc(githubDate($commit['commit']['committer']['date']))."');") or die(mysql_error());
				//}
				$inserts++;

				$commitSHA = $commit['sha'];
			}

			if ($lastReached) {
				break;
			}

			echo "Commits batch imported\n";
			$nbFetched++;
		}

		echo "Done fetching branch " . $branch['Branch'] . " of repo " . $repo['Repository'] . " ($inserts inserts)...\n";
	}
	echo "\n";
}

mysql_query("COMMIT;") or die(mysql_error());
echo mysql_num_rows($repositories)." repositories updated\n";

if (isset($params["c"])) {
	// We keep only the commits of the last 4 months
	echo "Cleaning 4+months old commits\n";
	mysql_query("DELETE FROM commits WHERE CommitDate < '".date("Y-m-d H:i:s", time()-3600*24*31*4). "'") or die(mysql_error());
	echo "Cleaned " . mysql_affected_rows() . " commits\n";
}

echo "GITHUB REQUESTS: " . $nbGitHubRequests . "\n";
echo "\n";
$duration = floatval(microtime(true) - $startTime);
echo $duration."s elapsed\n";
?>
