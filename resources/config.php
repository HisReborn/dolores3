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
  "paths" => array(
    "resources" => $_SERVER["DOCUMENT_ROOT"] . $localPath . "/resources",
    "images" => $_SERVER["DOCUMENT_ROOT"] . $localPath . "/public_html/img"
  ),
  "title" => "Blockchain Wall",
  "version" => "1",
  "debug" => "1"
);

define("LIBRARY_PATH", realpath(dirname(__FILE__)) . '/lib');
define("TEMPLATES_PATH", realpath(dirname(__FILE__)) . '/templates');
define("IMG_PATH", $localPath . '/public_html/img');
define("CSS_PATH", $localPath . '/public_html/css');
?>
