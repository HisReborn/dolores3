<?php
/*
TODO:
  Hacer lista de emoticonos válidos y llamar desde aquí a funcion que los postee.
  Controlar que el emoticono se inserte donde el cursor y no solo al final (?)
*/
  require_once("../../resources/init.php");
  require_once(LIBRARY_PATH . "/emoji.php");

  $pageTitle = "Blockchain Wall";
  require_once(TEMPLATES_PATH . "/header.php");
?>
<div class="container">
  <div class="card">
    <div class="card-header">
      Send Message
    </div>
    <div class="card-block">
      <div id="prev" class="m-b-15"></div>
      <form method="post" class="form-inline" act