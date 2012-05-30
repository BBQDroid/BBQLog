<?php
$url = "http://get.cm/rss?device=" . $_GET['device'];
$cache_file = "rss_cache/" . md5($url);
$cache_exists = file_exists($cache_file);
if ($cache_exists && filemtime($cache_file) + (60 * 15) > time()) {
	echo file_get_contents($cache_file);
	exit();
}
if (@$ret = file_get_contents($url)) {
	@file_put_contents($cache_file, $ret);
	echo $ret;
	exit();
} else if ($cache_exists) {
	echo file_get_contents($cache);
	exit();
} else {
	echo "error";
}

?>
