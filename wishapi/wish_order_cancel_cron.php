<?php
set_time_limit(0);
//ini_set('memory_limit',"500M");
include_once("/home/maxaroma/public_html/lib/config_setting.php");
require_once 'vendor/autoload.php';

use Wish\UsedToken;
use Wish\WishClient;
use Wish\Model\WishReason;


$myFile = '/home/maxaroma/public_html/Logs/wish_order_cancel.txt';
if(filesize($myFile)==10000000)
{
	rename($myFile,"/home/maxaroma/public_html/Logs/wish_order_cancel_bk.txt");
	unlink($myFile);
}

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');

	$stringData .= date("m/d/Y H:i:s")." : wish_order_cancel.php :  Wish Order Cancel  : Wish  Order Cancel Update Start\n";
	fwrite($fh, $stringData);
	fclose($fh);
}

$TokenObj = new UsedToken();
$Token  = $TokenObj->getTokens();
$client = new WishClient($Token,'prod');

$sql = "SELECT o.orders_id,o.order_id,reason_code,reason_note,our_order_id FROM `" . TABLE_PREFIX . "wish_orders`  AS o WHERE o.status='Canceled'AND o.is_wish_canceled='No'";
$order_res = $obj->select($sql);

$TotalOrders  = count($order_res);
if($TotalOrders > 0)
{
	for($i=0;$i<$TotalOrders;$i++)
	{
	  $res = $client->refundOrderById($order_res[$i]["order_id"],$order_res[$i]["reason_code"],$order_res[$i]["reason_note"]);
	  
	
	  if($res!='success')
	  {
		 
		$vemail = "naresh.qualdev@gmail.com";
		$sendmessage = 'In Maxaroma Wish Order Canceled Error .. on '.date('Y-m-d H:i:s').' The Order Number IS '.$order_res[$i]['our_order_id'];	
		$test 	= @mail($vemail,"In Maxaroma Wish Order Canceled Error",$MSG); 
	}
	else
	{
		$updateOrder = array (
								"is_wish_canceled"	 => "Yes"
		  					 );									 
	    $udpRefer = $obj->update(TABLE_PREFIX."wish_orders", $updateOrder, " `orders_id` = '".$order_res[$i]["orders_id"]."'");
	
	}
  }
}

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');
	$stringData = "";
	$stringData .= date("m/d/Y H:i:s")." : wish_order_cancel.php :  Wish Order Cancel  : Wish  Order Cancel Update End\n";
	fwrite($fh, $stringData);
	fclose($fh);
}

unset($mail);
exit;
?>
