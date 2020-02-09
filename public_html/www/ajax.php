
<?php
require_once("../../resources/init.php");
require_once(LIBRARY_PATH . "/emoji.php");
require_once(LIBRARY_PATH . "/functions.php");

if(isset($_POST['command']) && !empty($_POST['command'])){
  $command = $_POST['command'];

  if(isset($_POST['code'])){
    $code = $_POST['code'];
  }

  switch($command){
    case 'emoji_unified_to_html':
      echo printMessage($code);
      break;
    default:
      echo "Wrong command.";
      break;
  }
}
?>