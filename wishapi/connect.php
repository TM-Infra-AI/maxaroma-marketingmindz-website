<?php
require("vendor/autoload.php");
include_once("/home/maxaroma/public_html/lib/config_setting.php");
use Wish\WishAuth;
$auth = new WishAuth('5a27cb05518c1c2af43a4413','ab62005c1fa74f3e88698d7201ebc49d','prod');

$response = $auth->getToken('d50416e70d8e40c2911b0e346a2150be','https://www.maxaroma.com/'); 

 
$token = $response->getData()->access_token;

$refresh_token = $response->getData()->refresh_token;

echo "refresh_token :".$refresh_token."<br/>"; exit; 

//$client = new WishClient($token,'prod');


unset($mail);

?>
