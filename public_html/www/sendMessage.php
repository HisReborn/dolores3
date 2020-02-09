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
          <span aria-hidden="true">&times;</span>
          <span class="sr-only">Close</span>
        </button>
        <div class="p-tr-25">
<?php
  foreach ($GLOBALS['emoji_maps']['names'] as $code => $text) {
    echo emoji_unified_to_html($code);
  }
?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
  require_once(TEMPLATES_PATH . "/footer.php");
?>
<script>
// Update the number of bytes when writting a new char
$('#message').keyup(function(){
  var bytes = getByteLen($('#message').val());

  $('#bytes').text(bytes);
  $('#transactions').text(parseInt((bytes-1)/80)+1);
  ajax();
});

// Update the preview message with emojis
function ajax(){
  $.ajax({ url: "ajax.php",
           data: {command: 'emoji_unified_to_html', code: $('#message').val()},
           type: 'POST',
           success:
            function (response){
              $("#prev").html(response);
            }
  });
}

// Show/hide the list of emojis
$('#emojis, .close').click(function(e){
    $('#emojisTable').toggle();
});

// Writes the selected emoji in the input field
$('.emoji').click(function(e) {
  var code = $(this).attr('class').split(' ')[1].slice(5);
  var emoji = htmlDecode("&#x" + code + ";");

  $('#message').val($('#message').val() + emoji);
  $('#message').trigger("keyup");
});
</script>
