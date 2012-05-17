<?php

$amount = 100;

$data_string = json_encode(array("jsonrpc" => "2.0", "method" => "allQueryNext", "params" => array($_GET['params'], "z", $amount), "id" => 1));

//die('http://review.cyanogenmod.com' . '/gerrit' . $_GET['url']);

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