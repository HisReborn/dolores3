
<?php
  require_once("../../resources/init.php");
  require_once(LIBRARY_PATH . "/emoji.php");
  require_once(LIBRARY_PATH . "/OP_RETURN.php");
  require_once(LIBRARY_PATH . "/functions.php");

  $pageTitle = "Blockchain Wall";
  require_once(TEMPLATES_PATH . "/header.php");
?>
<?php
  // set variables to ""
  $message = "";

  if(isset($_POST['message'])){
    $message = $_POST['message'];
    $result = @OP_RETURN_store($message, $config["testnet"]);

    if(isset($result["error"]))
      printError($result["error"]);
    else {
      printSuccess("Your message have been successfully sent to the blockchain.");
      // $timestamp = time();
      // set variables ...
    }
  }else if(isset($_GET['id'])){
    // Retrieve message from DB/Blockchain
    // set variables ...
  }else{
    printWarning("No message selected.");
  }
?>
<div class="container">
  <div class="card">
    <div class="card-header">
      Message info
    </div>
    <div class="card-block">
      <span>
        <?php echo printMessage($message); ?>
      </span>
      <p class="card-text">
        <?php
          echo '<pre>';
          if(isset($result))
            var_dump($result);
          echo '</pre>';
        ?>
      </p>
      <a href="#" class="card-link">Share</a>
      <a href="#" class="card-link">Other</a>
    </div>
  </div>
</div>
<!-- Comments? -->
<?php
  require_once(TEMPLATES_PATH . "/footer.php");
?>