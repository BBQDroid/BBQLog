<?php
$url = "http://get.cm/rss?device=" . $_GET['device'];
$cache_file = "rss_cache/" . md5($url);
$cache_exists = file_exists($cache_file);

 $ctx=stream_context_create(array('http'=>
        array(
            'timeout' => 5
        )
    ));

if ($cache_exists && filemtime($cache_file) + (60 * 15) > time()) {
	echo file_get_contents($cache_file);
	exit();
}
if (@$ret = file_get_contents($url,false,$ctx)) {
	@file_put_contents($cache_file, $ret);
	echo $ret;
} else if ($cache_exists) {
	echo file_get_contents($cache_file);
} else {
	echo "error";
}

?>
