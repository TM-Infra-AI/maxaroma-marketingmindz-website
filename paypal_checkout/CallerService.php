<?php

/**
USE_PROXY: Set this variable to TRUE to route all the API requests through proxy.
like define('USE_PROXY',TRUE);
*/
define('USE_PROXY',FALSE);
/**
PROXY_HOST: Set the host name or the IP address of proxy server.
PROXY_PORT: Set proxy port.

PROXY_HOST and PROXY_PORT will be read only if USE_PROXY is set to TRUE
*/
define('PROXY_HOST', '127.0.0.1');
define('PROXY_PORT', '808');


/**
# Version: this is the API version in the request.
# It is a mandatory parameter for each API request.
# The only supported value at this time is 2.3
*/

define('VERSION', '53.0');
$version=VERSION;

/****************************************************
CallerService.php

This file uses the constants.php to get parameters needed 
to make an API call and calls the server.if you want use your
own credentials, you have to change the constants.php

Called by TransactionDetails.php, ReviewOrder.php, 
DoDirectPaymentReceipt.php and DoExpressCheckoutPayment.php.

****************************************************/

require_once(CLASS_PATH."encrypt.cls.php");


$sql = "SELECT pm_group_name, pm_gateway_name,pm_details FROM `".TABLE_PREFIX."payment_methods` WHERE pm_status = 'Active' AND pm_group_name = 'PAYMENT_PAYPALCC' LIMIT 0,1";
$db_res = $obj->select($sql);	

$arrPEVar = array();
if( count($db_res) > 0) 
{
	
	$arrPEVar		= unserialize($db_res[0]['pm_details']);
	
	$API_UserName   = $arrPEVar['paypalec_Username'];
	$API_Password   = $arrPEVar['paypalec_Password'];
	$API_Signature  = $arrPEVar['paypalec_Signature'];
	
	#############################
	$eobj = new Encrypt();
	$API_UserName   = $eobj->decrypt($API_UserName);
	$API_Password   = $eobj->decrypt($API_Password);
	$API_Signature  = $eobj->decrypt($API_Signature);
	//echo $API_UserName."<br>".$API_Password."<br>".$API_Signature."<br>";exit;
	unset($eobj);
	#############################
	
	if( strtoupper(trim($arrPEVar['paypalec_Transaction_Mode'])) == 'SANDBOX')	
		$PAYPAL_TRANSACTION_MODE = 'sandbox';
	else 
		$PAYPAL_TRANSACTION_MODE = '';
	
	if(trim($arrPEVar['paypalec_Currency_Code'])!='')
		$currencyCodeType = trim($arrPEVar['paypalec_Currency_Code']);
	else
		$currencyCodeType = 'USD';
}	
else 
{
	$msg = "Pay by credit card service is temporarily unavailable at this time, Please contact administrator.";
	$commonobj->setDisplayMessage($msg);
	
	header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	exit;
}

if( $API_UserName == '' or empty($API_UserName)) 
{
	$msg = "Pay by credit card service is temporarily unavailable at this time, Please contact administrator.";
	$commonobj->setDisplayMessage($msg);
	
	header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	exit;
}	

	/*if($_SERVER["REMOTE_ADDR"] == "122.170.103.198")
	{
		$PAYPAL_TRANSACTION_MODE = 'sandbox';
	}*/
## SANDBOX ENVIRONMENT
if(strtolower($PAYPAL_TRANSACTION_MODE)=='sandbox') 
{
	$API_Endpoint 	= "https://api-3t.sandbox.paypal.com/nvp";
	$PAYPAL_URL 	= "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=";
	
	############### UNCOMMENT BELOW CODE  FOR DEVELOPER TESTING #####################
	$API_UserName   = "rajan.qualdev.cc_api1.gmail.com";
	$API_Password   = "1364621199";
	$API_Signature  = "AMXbybImtGAWRlTNiIpIBUqPCh6xAxXg9HApAEhEMyfWiCcmVkDrg1tf";
	$PAYPAL_TRANSACTION_MODE = 'sandbox';
	##################################################################################
}	
else	## LIVE
{
	$API_Endpoint 	= "https://api-3t.paypal.com/nvp";
	$PAYPAL_URL 	= "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
}


############## PAPAL LIVE DETAIL #####################
	/*
	$API_UserName   = "";
	$API_Password   = "";
	$API_Signature  = "";
	$PAYPAL_TRANSACTION_MODE = '';
	$API_Endpoint 	= "https://api-3t.paypal.com/nvp";
	$PAYPAL_URL 	= "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
	*/
##########################################################


if (session_id() == "") 
	session_start();

/**
  * hash_call: Function to perform the API call to PayPal using API signature
  * @methodName is name of API  method.
  * @nvpStr is nvp string.
  * returns an associtive array containing the response from the server.
*/


function hash_call($methodName,$nvpStr)
{
	## declaring of global variables
	global $API_Endpoint,$version,$API_UserName,$API_Password,$API_Signature,$nvp_Header;

	## setting the curl parameters.
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$API_Endpoint);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	## turning off the server and peer verification(TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_POST, 1);
   
    ## if USE_PROXY constant set to TRUE in Constants.php, then only proxy will be enabled.
    ## Set proxy name to PROXY_HOST and port number to PROXY_PORT in constants.php 
	if(USE_PROXY)
	{
		curl_setopt ($ch, CURLOPT_PROXY, PROXY_HOST.":".PROXY_PORT); 
	}
	
	
	## NVPRequest for submitting to server
	$nvpreq="METHOD=".urlencode($methodName)."&VERSION=".urlencode($version)."&PWD=".urlencode($API_Password)."&USER=".urlencode($API_UserName)."&SIGNATURE=".urlencode($API_Signature).$nvpStr;
	
	## setting the nvpreq as POST FIELD to curl
	curl_setopt($ch,CURLOPT_POSTFIELDS,$nvpreq);

	## getting response from server
	$response = curl_exec($ch);

	## convrting NVPResponse to an Associative Array
	$nvpResArray	= deformatNVP($response);
	$nvpReqArray	= deformatNVP($nvpreq);
	$_SESSION['nvpReqArray'] = $nvpReqArray;
	
	/*echo "<prE>";
	print_r($nvpResArray);
exit;*/
	if (curl_errno($ch)) 
	{
		  ## moving to display page to display curl errors
		  /*	
		  $_SESSION['curl_error_no']	= curl_errno($ch) ;
		  $_SESSION['curl_error_msg']	= curl_error($ch);
		  */
		  
		  global $commonobj,$SECURED_PATH;
		  
		  $ErrorLongMsg = "Error in Processing Request Please try again.";
		  $commonobj->setDisplayMessage($ErrorLongMsg);
		  header("location:".$SECURED_PATH."index.php?file=shoppingcart");
		  exit;
	 } 
	 else 
	 {
		 ## closing the curl
		 curl_close($ch);
	 }

	return $nvpResArray;
}

/** This function will take NVPString and convert it to an Associative Array and it will decode the response.
  * It is usefull to search for a particular key and displaying arrays.
  * @nvpstr is NVPString.
  * @nvpArray is Associative Array.
  */

function deformatNVP($nvpstr)
{
	$intial=0;
 	$nvpArray = array();

	while(strlen($nvpstr))
	{
		## postion of Key
		$keypos= strpos($nvpstr,'=');
		
		##position of value
		$valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&'): strlen($nvpstr);

		## getting the Key and Value values and storing in a Associative Array
		$keyval	= substr($nvpstr,$intial,$keypos);
		$valval	= substr($nvpstr,$keypos+1,$valuepos-$keypos-1);
		
		## decoding the respose
		$nvpArray[urldecode($keyval)] = urldecode( $valval);
		$nvpstr	= substr($nvpstr,$valuepos+1,strlen($nvpstr));
     }
	return $nvpArray;
}


#########################################################################
## This function will take the shopping cart for the Paypal Start
#########################################################################
function get_Shopping_Cart_Contents() 
{
	global $Cart;
	
	## For the Shopping Cart Code Start Here
	$items_description = '';
	$tempCart = $Cart->getCart();

	if(count($tempCart) > 0 ) 
	{
		$tc =0;
		
		for($tc=0; $tc<count($tempCart); $tc++)
		{
			$L_NUMBER 	= "L_NUMBER".$tc;
			$L_NAME 	= "L_NAME".$tc;
			$L_DESC		= "L_DESC".$tc; // added new
			$L_AMT 		= "L_AMT".$tc;
			$L_QTY 		= "L_QTY".$tc;
			
			
			$Item_SKU  = '';
			$Item_NAME = '';
			$Item_DESC = '';
			
			$Item_QTY  		  = $tempCart[$tc]['Qty'];
			$Item_UNIT_PRICE  = $tempCart[$tc]['Price'];
			
			$Item_SKU  = $tempCart[$tc]['SKU'];
			$Item_NAME = $tempCart[$tc]['ProductName'];
		
			
			$items_description.= "&$L_NUMBER=".urlencode(stripslashes($Item_SKU))."&$L_NAME=".urlencode(str_replace('"','',stripslashes($Item_NAME)))."&$L_QTY=".$Item_QTY."&$L_AMT=".$Item_UNIT_PRICE."&$L_DESC=".urlencode(str_replace('"','',stripslashes($Item_DESC)));
	
		}	
		
		## For the All Type Of Discounts add item with negative amount Start Here
		$GiftCouponInfo  	= $Cart->getGiftCouponInfo();	// GC Discount Info
		$NetDiscount 		= $Cart->getNetDiscount(); 		// Coupon + Auto + Qty
		$TotalDiscount 		= $Cart->NumberFormat($NetDiscount + $GiftCouponInfo['Value']);
		
		if($TotalDiscount > 0)	
		{
			$L_NUMBER 	= "L_NUMBER".$tc;
			$L_NAME 	= "L_NAME".$tc;
			$L_DESC		= "L_DESC".$tc; // added new
			$L_AMT 		= "L_AMT".$tc;
			$L_QTY 		= "L_QTY".$tc;
			$items_description.= "&$L_NUMBER=Discount&$L_NAME=All Discount&$L_QTY=1&$L_AMT=-".$TotalDiscount."&$L_DESC=".urlencode("All Discounts");
			$tc++;
		}
		## For the All Type Of Discounts add item with negative amount End Here
	} 
	return 	$items_description;
} 
#########################################################################
## This function will take the shopping cart for the Paypal End
#########################################################################

?>
