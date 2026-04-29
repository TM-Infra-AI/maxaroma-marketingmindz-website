<?php
exit;
/* This Cron is used to generate new token and update in wisk token table after 20 days.*/
set_time_limit(0);
//ini_set('memory_limit',"500M");
//ini_set('memory_limit',"96M");
require("vendor/autoload.php");
include_once("/home/maxaroma/public_html/lib/config_setting.php");

use Wish\WishAuth;
$auth = new WishAuth('5a27cb05518c1c2af43a4413','ab62005c1fa74f3e88698d7201ebc49d','prod');

$response = $auth->refreshToken('4d657ea51112433a827a2b08c5cb1b1f');


$token = $response->getData()->access_token;
//echo $token; exit;
if($token!='')
{
	$sql = "Update `" . TABLE_PREFIX . "wish_token` SET token='".trim($token)."' WHERE id=1";
	$result = $obj->sql_query($sql);
	
}

$vemail = "naresh.qualdev@gmail.com";
$sendmessage = "In Maxaroma Refresh Token Cron End on ".date('Y-m-d H:i:s');	
$test 	= @mail($vemail,"In Maxaroma Refresh Token Cron End",$sendmessage);
unset($obj);
exit;
?>
