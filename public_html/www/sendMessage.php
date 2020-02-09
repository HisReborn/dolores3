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
      <form method="post" class="form-inline" action="message.php">
        <div class="input-group w-80">
          <input class="form-control" type="text" id="message" name="message" placeholder="Write your message here">
          <a href="#" class="input-group-addon w-20" id="emojis" data-toggle="popover"> <img src="<?php echo IMG_PATH . "/smiley.png" ?>" alt="Emojis" height="18" width="19"></a>
        </div>
        <button class="btn btn-secondary" type="submit">Send</button>
      </form>
      <span id="bytes">0</span> bytes (<span id="transactions">1</span> transactions)
      <!-- Emojis -->
      <div id="emojisTable" class="emojisContainer">
        <button type="button" class="close" aria-label="Close">
          <span aria-hidden="true">