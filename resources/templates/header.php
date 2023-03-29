<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo (isset($pageTitle)) ? $pageTitle : "" ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <link rel="icon" href="<?php echo IMG_PATH . '/favicon.ico' ?>">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.rawgit.com/twbs/bootstrap/v4-dev/dist/css/bootstrap.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo CSS_PATH . '/template.css' ?>">
    <!-- Emoji -->
    <link rel="stylesheet" href="<?php echo CSS_PATH . '/emoji.css' ?>">    
  </head>
  <body>
    <!-- Navbar -->
    <nav class="navbar navbar-light bg-faded navbar-fixed-top">
      <a class="navbar-brand" href="<?php echo $localPath . '/index.php' ?>">
        <img src="<?php echo IMG_PATH . '/logo.png' ?>" alt="<?php echo $config["title"]; ?>" class="navbar-brand" height="35px" width="35px">
      </a>
      <ul class="nav navbar-nav">
        <li class="nav-item active">
          <a class="nav-link" href="<?php echo $localPath . '/index.php' ?>">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo $localPath . '/sendMessage.php' ?>">Send message</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">Link 2</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">Link 3</a>
        </li>
      </ul>
      <!-- Search -->
      <div class="pull-right">
        <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
          <img src="<?php echo IMG_PATH . '/search.png' ?>" class="search-logo" alt="Search">
        </a>
        <div class="dropdown-menu search-box">
          <form class="form-inline search-box">
            <input class="form-control" type="text" placeholder="Search">
            <button class="btn btn-secondary" type="submit">Search</button>
          </form>
        </div>
      </div>
    </nav>
    <!-- Content -->
    <div class="container content">
