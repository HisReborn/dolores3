<?php
require_once(dirname(__FILE__) . "/config.php");

if($config["debug"]){
  error_reporting(E_ALL);
  ini_set("display_errors", 1);
}

session_start();
if(!empty($_SESSION['visited_pages'])) {
  $_SESSION['visited_pages']['prev'] = $_SESSION['visi