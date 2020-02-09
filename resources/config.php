<?php
$localPath = "";
if($_SERVER["HTTP_HOST"] == "localhost")
  $localPath = "/blockchainwall";

$config = array(
  "db" => array(
    "db_host" => "",
    "db_user" => "",
    "db_password" => "",
    "db_name" => ""
  ),
  "pat