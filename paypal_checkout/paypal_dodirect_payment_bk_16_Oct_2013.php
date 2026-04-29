<?php 

require_once("../lib/configuration.php");
require_once("CallerService.php");

$paymentType 	  =  urlencode("Sale");
$currencyCodeType =  "USD"; 

$tempBillingAdd  = $Cart->getBillingAddress();
$tempShippingAdd = $Cart->getShippingAddress();


## Shipping Address
$firstName		= urlencode($tempShippingAdd['first_name']);
$lastName 		= urlencode($tempShippingAdd['last_name']);
$address1 		= urlencode($tempShippingAdd['address1']);
$address2 		= urlencode($tempShippingAdd['address2']);
$city 			= urlencode($tempShippingAdd['city']);
$state 			= urlencode($tempShippingAdd['state']);
$zip 			= urlencode($tempShippingAdd['zip']);
$country 		= urlencode($tempShippingAdd['country']);

## Billing Address
$bill_firstname	= urlencode($tempBillingAdd['first_name']);
$bill_lastname	= urlencode($tempBillingAdd['last_name']);
$bill_vaddress1	= urlencode($tempBillingAdd['address1']);
$bill_vcity		= urlencode($tempBillingAdd['city']);
$bill_vstate	= urlencode($tempBillingAdd['state']);
$bill_izip		= urlencode($tempBillingAdd['zip']);
$bill_country	= urlencode($tempBillingAdd['country']);

$OrderTotal 	= urlencode($Cart->getNetTotal());
$ShippingAmt 	= urlencode($Cart->getShippingCharge());
$TaxAmt 		= urlencode($Cart->getTaxValue());
$HandlingAmt 	= urlencode($Cart->NumberFormat(0));

## For the All Type Of Discounts Start Here
$GiftCouponInfo  = $Cart->getGiftCouponInfo();	// GC Discount Info
$NetDiscount 	 = $Cart->getNetDiscount(); 		// Coupon + Auto + Qty
$TotalDiscount 	 = $Cart->NumberFormat($NetDiscount + $GiftCouponInfo['Value']);
## For the all types of discounts end here

$OrderSubTotal 	 = $Cart->getSubTotal();
$OrderSubTotal 	 = $Cart->NumberFormat($OrderSubTotal - $TotalDiscount);

$tempCC 		  = $Cart->getPaymentDetail();
$creditCardType	  = urlencode($tempCC['CCType']);
$creditCardNumber = urlencode($tempCC['CCNumber']);
$cvv2Number 	  = urlencode($tempCC['CSC']);
$expDateMonth 	  = str_pad($tempCC['CCMonth'], 2, '0', STR_PAD_LEFT); 
$expDateYear 	  = str_pad($tempCC['CCYear'], 4, '20', STR_PAD_LEFT);

$INV_rand_number 	= "PU-".$Cart->getOrderID();


## For the Shopping Cart Code Start Here
$items_description = get_Shopping_Cart_Contents();

		
/* 
   Construct the request string that will be sent to PayPal.
   The variable $nvpstr contains all the variables and is a
   name value pair string with & as a delimiter 
*/

$nvpstr="&PAYMENTACTION=".$paymentType."&AMT=".$OrderTotal."&ITEMAMT=".$OrderSubTotal."&SHIPPINGAMT=".$ShippingAmt."&TAXAMT=".$TaxAmt."&HANDLINGAMT=".$HandlingAmt."&CREDITCARDTYPE=".$creditCardType."&ACCT=".$creditCardNumber."&EXPDATE=".$expDateMonth.$expDateYear."&CVV2=".$cvv2Number."&FIRSTNAME=".$bill_firstname."&LASTNAME=".$bill_lastname."&STREET=".$bill_vaddress1."&CITY=".$bill_vcity."&STATE=".$bill_vstate."&ZIP=".$bill_izip."&COUNTRYCODE=".$bill_country."&SHIPTONAME=".$firstName."&SHIPTOSTREET=".$address1."&SHIPTOCITY=".$city."&SHIPTOSTATE=".$state."&SHIPTOZIP=".$zip."&SHIPTOCOUNTRYCODE=".$country."&CURRENCYCODE=".$currencyCodeType."&INVNUM=".$INV_rand_number.$items_description;


/* 
Make the API call to PayPal, using API signature.
The API response is stored in an associative array called $resArray 
*/
 
$resArray = hash_call("doDirectPayment",$nvpstr);


/* 
   Display the API response back to the browser.
   If the response from PayPal was a success, display the response parameters'
   If the response was an error, display the errors received using APIError.php.
*/
   
$ack = strtoupper($resArray["ACK"]);

if($ack=="SUCCESS" or $ack=="SUCCESSWITHWARNING")
{	
	$transaction_info = "This transaction has been approved.";
	
	$card_code_response="ACK:".$resArray["ACK"].",AVSCODE:".$resArray["AVSCODE"].",CVV2MATCH:".$resArray["CVV2MATCH"].",TransactionID:".$resArray["TRANSACTIONID"].",Timestamp:".$resArray["TIMESTAMP"].",CorrelationID:".$resArray["CORRELATIONID"];
	
	$updAray = array (
						'pay_status' 	  			=> 'Paid',
						'transaction_info' 			=> $transaction_info,
						'payment_gateway_response' 	=> $card_code_response
					  );
	
	$where_cond = " orders_id='".$Cart->getOrderID()."' ";	
	$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);

	header("location:".$Site_URL."index.php?file=order_receipt");			
	exit;
	

}	
else	
{	
	## Display a user friendly Error on the page using any of the following error information returned by PayPal
	$count=0;
	$errormsg = '';
	while (isset($resArray["L_SHORTMESSAGE".$count])) 	
	{		
		  $errormsg.="Error Code:".$resArray["L_ERRORCODE".$count]." ### ";
		  $errormsg.="Short Error Msg:".$resArray["L_SHORTMESSAGE".$count]. " ### ";
		  $errormsg.="Long Error Msg:".$resArray["L_LONGMESSAGE".$count]." ### "; 
		  $count=$count+1;
	}
	
	$transaction_info = "This transaction has been Declined.";  
	
	$card_code_response="ACK:".$resArray["ACK"].",AVSCODE:".$resArray["AVSCODE"].",CVV2MATCH:".$resArray["CVV2MATCH"].",TransactionID:".$resArray["TRANSACTIONID"].",Timestamp:".$resArray["TIMESTAMP"].",CorrelationID:".$resArray["CORRELATIONID"]." -- ".$errormsg;;
	
	$updAray = array (
						'status' 	   				=> 'Declined',
						'transaction_info' 			=> $transaction_info,
						'payment_gateway_response' 	=> $card_code_response
					  );
	
	$where_cond = " orders_id='".$Cart->getOrderID()."' ";	
	$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);
	

	$msg= "Sorry, your payment has been DECLINED, please make sure to verify the accuracy of your payment details and try again." .$resArray["L_LONGMESSAGE0"];

	$commonobj->setDisplayMessage($msg);
	
	header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	exit;
}
?>