
<?php

require_once './lib/bitcoin.php';

$scheme = 'http';
$username = 'YOUR_USERNAME';
$password = 'YOUR_PASSWORD';
$address = "localhost"; // Local
$port = 18332; // Testnet

$bitcoin_client = new BitcoinClient($scheme, $username, $password, $address, $port);

if($bitcoin_client->can_connect() !== TRUE){
	echo 'The Bitcoin server is presently unavailable. Please contact the site administrator.';
	exit;
}

?>