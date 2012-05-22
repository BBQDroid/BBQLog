<?php

if (!isset($_GET['params']) || !isset($_GET['url'])) {
	return;
}

$params = array($_GET['params']);
if (!empty($_GET['sortCode'])) {
	array_push($params, $_GET['sortCode']);
} else {
	array_push($params, "z");
}
array_push($params, $_GET['amount']);

$data_string = json_encode(array("jsonrpc" => "2.0", "method" => "allQueryNext", "params" => $params, "id" => 1));

$ch = curl_init('http://review.cyanogenmod.com' . '/gerrit' . $_GET['url']);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen($data_string))
);

$result = curl_exec($ch);

echo $result;

?>
