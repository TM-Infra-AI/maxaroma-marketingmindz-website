<?php
require_once("../lib/configuration.php");
require_once("paypal_express_functions.php");

$id_order = $_REQUEST["orderid"];
$OrderID = ($id_order != "") ? base64_decode($id_order) : "";
if($OrderID == '' or empty($OrderID)) 
{
	header("location:".$Site_URL."index.php");
	exit();
}

//$OrderID = "10010";

$_SESSION["phoneorder"] = '';
unset($_SESSION["phoneorder"]);
	
$_SESSION["phoneorder"]["porder_id"] = $OrderID;

$sql = "SELECT o.bill_email,o.orders_id,o.orders_no,o.order_total,o.sub_total,o.auto_discount,o.quantity_discount,o.reward_discount,o.coupon_amount,o.gc_amount,o.refer_amount,o.apply_credit,o.shipping_amt,o.shipping_signature,o.tax,o.gift_charge,ord.price,ord.quantity,ord.sku,ord.product_name,ord.total FROM `".TABLE_PREFIX."orders` o left join `".TABLE_PREFIX."order_detail` ord on ord.orders_id=o.orders_id WHERE o.orders_id ='".$OrderID."'";
$OrderRs = $obj->select($sql);

$orders_id = $OrderRs[0]['orders_id'];
$order_total = $OrderRs[0]['order_total'];

$AllDiscounts = $OrderRs[0]['auto_discount'] + $OrderRs[0]['quantity_discount']+$OrderRs[0]['reward_discount']+$OrderRs[0]['coupon_amount']+$OrderRs[0]['gc_amount']+$OrderRs[0]['refer_amount']+$OrderRs[0]['apply_credit'];

$Payment_Amount = $order_total;	

$tot_count = count($OrderRs);
for($i=0;$i < $tot_count; $i++){
	$Data_Order[$i]["SKU"] = $OrderRs[$i]['sku'];
	$Data_Order[$i]["ProductName"] = $OrderRs[$i]['product_name'];
	$Data_Order[$i]["Qty"] = $OrderRs[$i]['quantity'];
	$Data_Order[$i]["Price"] = $OrderRs[$i]['price'];
	
	if($i == 0){
		//$Data_Order[$i]["Discounts"]["Coupon_Amount"] = $OrderRs[$i]['coupon_amount'];
		$Data_Order[$i]["AllDiscounts"] = $AllDiscounts;
		$Data_Order[$i]["OrderTotal"] = $OrderRs[$i]['order_total'];
		$Data_Order[$i]["SubTotal"] = $OrderRs[$i]['sub_total'];
		$Data_Order[$i]["tax"] = $OrderRs[$i]['tax'];
		$Data_Order[$i]["shipping_amt"] = $OrderRs[$i]['shipping_amt'];
	}
}

//echo "<pre>";print_r($Data_Order);exit;

//'------------------------------------------------------------------------------
//' Are set to the selections made on the Integration Assistant 
//  Pass below var in CallShortcutExpressCheckout functions if needed
//'------------------------------------------------------------------------------
$paymentType 	  = "Sale";
$currencyCodeType = "USD";

//'------------------------------------
//' The returnURL is the location where buyers return to when a
//' payment has been succesfully authorized.
//'
//' This is set to the value entered on the Integration Assistant 
//'------------------------------------
$returnURL = urlencode($SECURED_PATH."paypal_checkout/paypal_express_response_phoneorder.php");

//'------------------------------------
//' The cancelURL is the location buyers are sent to when they hit the
//' cancel button during authorization of payment during the PayPal flow
//' This is set to the value entered on the Integration Assistant 
//'------------------------------------
$cancelURL = urlencode($SECURED_PATH."payment/".$id_order."/".base64_encode("0"));
	
	//'------------------------------------
	//' Calls the SetExpressCheckout API call
	//'
	//' The CallMarkExpressCheckout function is defined in the file PayPalFunctions.php,
	//' it is included at the top of this file.
	//'-------------------------------------------------

$resArray 	= CallShortcutExpressCheckout($Payment_Amount,$returnURL,$cancelURL,$paymentType,$currencyCodeType,"Yes",$Data_Order);
$ack 		= strtoupper($resArray["ACK"]);
	
if($ack=="SUCCESS" or $ack=="SUCCESSWITHWARNING")
{
	 echo('Success#####'.$resArray["TOKEN"]);
   	 return;
	//RedirectToPayPal ( $resArray["TOKEN"] );
} 
else  
{
	## Display a user friendly Error on the page using any of the following error information returned by PayPal
	$ErrorCode 			= $resArray["L_ERRORCODE0"];
	$ErrorShortMsg 		= $resArray["L_SHORTMESSAGE0"];
	$ErrorLongMsg 		= $resArray["L_LONGMESSAGE0"];
	$ErrorSeverityCode 	= $resArray["L_SEVERITYCODE0"];
	
	//$commonobj->setDisplayMessage($ErrorLongMsg);
	
	//header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	//exit;
	echo('Error#####'.$ErrorLongMsg.'#####'.$ErrorShortMsg.'#####'.$ErrorCode.'#####'.$ErrorSeverityCode);
   	return;
}
?>
