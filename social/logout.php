<?php 
/* -----------------------------------------------------------------------------------------
   IdiotMinds - http://idiotminds.com
   -----------------------------------------------------------------------------------------
*/
require_once 'config.php';

if($_GET['google']){
	require_once 'lib/google/Google_Client.php';	
	$client = new Google_Client();
	
	unset($_SESSION['token']);
	$client->revokeToken();	
}

if(isset($_SESSION['User']) && !empty($_SESSION['User'])){
session_destroy();
}
header('Location: '.BASE_URL);
?>