<?php

error_reporting(E_ALL);

class GitHubAPI {
  public function __construct() {
  
  }


  public function getRepoCommits($user, $repo, $startSha = "") {
	return json_decode(file_get_contents("https://api.github.com/repos/" . $user . "/" . $repo . "/commits"));
  }


}

?>