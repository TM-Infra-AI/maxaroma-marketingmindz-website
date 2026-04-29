<?php
require_once("../lib/configuration.php");
require_once("paypal_express_functions.php");


if( $Cart->getTotalItemInCart() <= 0 ) 
{
	$a = $Cart->destroyCart();
	header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	exit();
}

## Here Check Wholesaler minimum order amount
if($Cart->Is_WholeSaler_Allow() == false)
{
	header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	exit();
}


$tempBillingAdd  = $Cart->getBillingAddress();
$tempShippingAdd = $Cart->getShippingAddress();

$OrderSubTotal 	 =  $Cart->getSubTotal();

$GiftCouponInfo  = $Cart->getGiftCouponInfo(); 	// GC Discount Info
$NetDiscount 	 = $Cart->getNetDiscount(); 	    // Coupon + Auto + Qty
$TotalDiscount 	 = $Cart->NumberFormat($NetDiscount + $GiftCouponInfo['Value']);

$OrderSubTotal 	 = $Cart->NumberFormat($OrderSubTotal - $TotalDiscount);
$Payment_Amount  = $OrderSubTotal ; // For Order Total

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
$returnURL = urlencode($SECURED_PATH."paypal_checkout/paypal_express_response.php");

//'------------------------------------
//' The cancelURL is the location buyers are sent to when they hit the
//' cancel button during authorization of payment during the PayPal flow
//' This is set to the value entered on the Integration Assistant 
//'------------------------------------
$cancelURL = urlencode($SECURED_PATH."index.php?file=shoppingcart");
	
	//'------------------------------------
	//' Calls the SetExpressCheckout API call
	//'
	//' The CallMarkExpressCheckout function is defined in the file PayPalFunctions.php,
	//' it is included at the top of this file.
	//'-------------------------------------------------

$resArray 	= CallShortcutExpressCheckout($Payment_Amount,$returnURL,$cancelURL);
$ack 		= strtoupper($resArray["ACK"]);
	
if($ack=="SUCCESS" or $ack=="SUCCESSWITHWARNING")
{
	RedirectToPayPal ( $resArray["TOKEN"] );
} 
else  
{
	## Display a user friendly Error on the page using any of the following error information returned by PayPal
	$ErrorCode 			= $resArray["L_ERRORCODE0"];
	$ErrorShortMsg 		= $resArray["L_SHORTMESSAGE0"];
	$ErrorLongMsg 		= $resArray["L_LONGMESSAGE0"];
	$ErrorSeverityCode 	= $resArray["L_SEVERITYCODE0"];
	
	$commonobj->setDisplayMessage($ErrorLongMsg);
	
	header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	exit;
}
?>