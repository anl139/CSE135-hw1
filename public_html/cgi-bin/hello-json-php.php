<?php
header("Cache-Control: no-cache");
header("Content-Type: application/json");

$date = date("r");
$ip = $_SERVER['REMOTE_ADDR'];

$message = [
    "title"   => "Hello, Andrew Lam!",
    "heading" => "Hello, Andrew Lam!",
    "message" => "This page was generated with the PHP programming language",
    "time"    => $date,
    "IP"      => $ip
];

echo json_encode($message, JSON_PRETTY_PRINT);
