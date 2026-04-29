<?php
set_time_limit(0);
//ini_set('memory_limit',"500M");
//ini_set('memory_limit',"96M");
include_once("/home/maxaroma/public_html/lib/config_setting.php");
require_once 'vendor/autoload.php';

use Wish\UsedToken;
use Wish\WishClient;
use Wish\Model\WishTracker;
 


$myFile = '/home/maxaroma/public_html/Logs/tracking_txt_cron.txt';
if(filesize($myFile)==10000000)
{
	rename($myFile,"/home/maxaroma/public_html/Logs/tracking_txt_cron_bk.txt");
	unlink($myFile);
}

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');

	$stringData .= date("m/d/Y H:i:s")." : tracking_txt_cron.php :  Wish Tracking Number : Wish Tracking Number Start\n";
	fwrite($fh, $stringData);
	fclose($fh);
}


$TokenObj = new UsedToken();
$Token  = $TokenObj->getTokens();

$client = new WishClient($Token,'prod');
$sql = "SELECT o.orders_id,o.order_id,o.ship_method,o.our_order_id,o.tracking_no,o.ship_date FROM `" . TABLE_PREFIX . "wish_orders`  AS o WHERE o.tracking_no!='' AND o.ship_method!='' AND o.ship_status='Shipped' AND o.ship_date!='' AND o.ship_date!='0000-00-00' AND o.is_wish='No' AND o.status='Completed'";
$order_res = $obj->select($sql);



$TotalOrders  = count($order_res);
if($TotalOrders > 0)
{
	for($i=0;$i<$TotalOrders;$i++)
	{
	  $tracker = new WishTracker($order_res[$i]['ship_method'],$order_res[$i]['tracking_no'],'',"US");
	   $res = $client->fulfillOrderById($order_res[$i]['order_id'],$tracker);
	 

	  
	if($res > 0)
	{
		
		$vemail = "naresh.qualdev@gmail.com";
		$sendmessage = " In Maxaroma Wish Order Tracking Number Error In Updation on ".date('m-d-Y H:i:s');
		$test 	= @mail($vemail,"Important : In Maxaroma Wish Order1 Tracking Number Error In Updation",$sendmessage);
		$test11 = @mail("ravi.qualdev@gmail.com","Important : In Maxaroma Wish Order1 Tracking Number Error In Updation",$sendmessage);
		
		
		
	}
	else
	{
		$updateOrder = array (
								"is_wish"	 => "Yes"
		  					 );									 
	    $udpRefer = $obj->update(TABLE_PREFIX."wish_orders", $updateOrder, " `orders_id` = '".$order_res[$i]["orders_id"]."'");
	
	}
  }
}

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');
	$stringData = "";
	$stringData .= date("m/d/Y H:i:s")." : tracking_txt_cron.php :  Wish Tracking Number : Wish Tracking Number End\n";
	fwrite($fh, $stringData);
	fclose($fh);
}


unset($obj);
exit;
?>
