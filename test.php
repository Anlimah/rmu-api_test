<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('bootstrap.php');

use Src\Gateway\CurlGatewayAccess;

$httpHeader = array("Authorization: Basic ZWlxamp4bnc6aXdjcHpudGY=", "Content-Type: application/json");
$gw = new CurlGatewayAccess("https://test.api.rmuictonline.com", $httpHeader, array("message" => "okay"));
$data = $gw->initiateProcess();
var_dump($data);
