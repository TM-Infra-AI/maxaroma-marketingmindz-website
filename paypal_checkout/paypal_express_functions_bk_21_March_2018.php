<?php

require_once(CLASS_PATH."encrypt.cls.php");

/********************************************
PayPal API Module
Defines all the global variables and the wrapper functions 
********************************************/
$PROXY_HOST = '127.0.0.1';
$PROXY_PORT = '808';


$USE_PROXY = false;
$version="2.3";

//'------------------------------------
//' PayPal API Credentials 
//'------------------------------------
// Fetch Paypal Account Configuration for the Paypal Express

$API_UserName  = ''; 
$API_Password  = ''; 
$API_Signature = '';

$currencyCodeType = 'USD';
	
$sql = "SELECT pm_group_name, pm_gateway_name,pm_details FROM `".TABLE_PREFIX."payment_methods` WHERE pm_status = 'Active' 
		AND pm_group_name = 'PAYMENT_PAYPALEC' LIMIT 0,1";		

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
	$msg = "Pay by paypal service is temporarily unavailable at this time, Please contact administrator.";
	$commonobj->setDisplayMessage($msg);
	
	header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	exit;
}

if( $API_UserName == '' or empty($API_UserName)) 
{
	$msg = "Pay by paypal service is temporarily unavailable at this time, Please contact administrator.";
	$commonobj->setDisplayMessage($msg);
	
	header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	exit;
}	

/*if($_SERVER['REMOTE_ADDR'] == "27.109.8.106")
{
	//$PAYPAL_TRANSACTION_MODE = 'sandbox';
}*/
## SANDBOX ENVIRONMENT
if(strtolower($PAYPAL_TRANSACTION_MODE)=='sandbox') 
{
	$API_Endpoint 	= "https://api-3t.sandbox.paypal.com/nvp";
	$PAYPAL_URL 	= "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=";
	
	############### UNCOMMENT BELOW CODE  FOR DEVELOPER TESTING #####################
	$API_UserName   = "hitumc_1237006051_biz_api1.gmail.com";
	$API_Password   = "JDB7QQSGZKY948JD";
	$API_Signature  = "AFcWxV21C7fd0v3bYYYRCpSSRl31AA23RjvP.Q5ZmiiyV66iZWYmDx-w";
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

/* 
   An express checkout transaction starts with a token, that
   identifies to PayPal your transaction
   In this example, when the script sees a token, the script
   knows that the buyer has already authorized payment through
   paypal.  If no token was found, the action is to send the buyer
   to PayPal to first authorize payment
*/

/*   
'---------------------------------------------------------------------------------------------------------
' Purpose: 	Prepares the parameters for the SetExpressCheckout API Call.
' Inputs:  
'		paymentAmount:  	Total value of the shopping cart
'		currencyCodeType: 	Currency code value the PayPal API
'		paymentType: 		paymentType has to be one of the following values: Sale or Order or Authorization
'		returnURL:			the page where buyers return to after they are done with the payment review on PayPal
'		cancelURL:			the page where buyers return to when they cancel the payment review on PayPal
'-------------------------------------------------------------------------------------------------------
*/
	
function CallShortcutExpressCheckout($paymentAmount,$returnURL,$cancelURL,$paymentType="Sale",$currencyCodeType="USD" ) 
{
	############################################################	
	## Construct the parameter string that describes the 
	## SetExpressCheckout API call in the shortcut implementation
	global $SECURED_PATH; 
	 
	$hrdimg = $SECURED_PATH."images/logo.png";
	$nvpstr = "&Amt=". $paymentAmount;
	$nvpstr = $nvpstr . "&PAYMENTACTION=".$paymentType;
	$nvpstr = $nvpstr . "&ReturnUrl=".$returnURL;
	$nvpstr = $nvpstr . "&CANCELURL=".$cancelURL;
	$nvpstr = $nvpstr . "&CURRENCYCODE=".$currencyCodeType;
	
	$nvpstr = $nvpstr . "&NOSHIPPING=0"; ## TO SHOW SHIPPING ADD ON PAYPAL SITE
	$nvpstr = $nvpstr . "&HDRIMG=".$hrdimg;
	$nvpstr = $nvpstr . "&VERSION=53.0"; 
	
	$nvpstr = $nvpstr . '&ITEMAMT='.$paymentAmount;

	##  Shows the Shopping Cart Contents on the Paypal Review Order Page
	$shopping_cart_contents = get_Shopping_Cart_Contents();
	if($shopping_cart_contents!='')
	{
		$nvpstr.=$shopping_cart_contents;
	}	
	
	
	
	/*echo $nvpstr;
	exit;*/

	//'-------------------------------------------------------------------------------------------------
	//' Make the API call to PayPal
	//' If the API call succeded, then redirect the buyer to PayPal to begin to authorize payment.  
	//' If an error occured, show the resulting errors
	//'-------------------------------------------------------------------------------------------------
	
	$resArray	=	hash_call("SetExpressCheckout", $nvpstr);
	$ack 		= 	strtoupper($resArray["ACK"]);
	
	if($ack=="SUCCESS")
	{
		$token = urldecode($resArray["TOKEN"]);
		$_SESSION['TOKEN']=$token;
	}
	   
	return $resArray;
}

/*   
'----------------------------------------------------------------------------------------------------
' Purpose: 	Prepares the parameters for the SetExpressCheckout API Call.
' Inputs:  
'		paymentAmount:  	Total value of the shopping cart
'		currencyCodeType: 	Currency code value the PayPal API
'		paymentType: 		paymentType has to be one of the following values: Sale or Order or Authorization
'		returnURL:			the page where buyers return to after they are done with the payment review on PayPal
'		cancelURL:			the page where buyers return to when they cancel the payment review on PayPal
'		shipToName:			the Ship to name entered on the merchant's site
'		shipToStreet:		the Ship to Street entered on the merchant's site
'		shipToCity:			the Ship to City entered on the merchant's site
'		shipToState:		the Ship to State entered on the merchant's site
'		shipToCountryCode:	the Code for Ship to Country entered on the merchant's site
'		shipToZip:			the Ship to ZipCode entered on the merchant's site
'		shipToStreet2:		the Ship to Street2 entered on the merchant's site
'		phoneNum:			the phoneNum  entered on the merchant's site
'---------------------------------------------------------------------------------------------------------
*/
function CallMarkExpressCheckout( $paymentAmount, $currencyCodeType, $paymentType, $returnURL, 
								  $cancelURL, $shipToName, $shipToStreet, $shipToCity, $shipToState,
								  $shipToCountryCode, $shipToZip, $shipToStreet2, $phoneNum
								) 
{
	
	############################################################	
	## Construct the parameter string that describes the 
	## SetExpressCheckout API call in the shortcut implementation
	
	global $SECURED_PATH; 
	 
	$hrdimg = $SECURED_PATH."images/logo.jpg";
	
	$nvpstr	= "&Amt=". $paymentAmount;
	$nvpstr = $nvpstr . "&PAYMENTACTION=".$paymentType;
	$nvpstr = $nvpstr . "&ReturnUrl=".$returnURL;
	$nvpstr = $nvpstr . "&CANCELURL=".$cancelURL;
	$nvpstr = $nvpstr . "&HDRIMG=".$hrdimg;
	$nvpstr = $nvpstr . "&CURRENCYCODE=".$currencyCodeType;
	$nvpstr = $nvpstr . "&ADDROVERRIDE=1";
	$nvpstr = $nvpstr . "&SHIPTONAME=".$shipToName;
	$nvpstr = $nvpstr . "&SHIPTOSTREET=".$shipToStreet;
	$nvpstr = $nvpstr . "&SHIPTOSTREET2=".$shipToStreet2;
	$nvpstr = $nvpstr . "&SHIPTOCITY=".$shipToCity;
	$nvpstr = $nvpstr . "&SHIPTOSTATE=".$shipToState;
	$nvpstr = $nvpstr . "&SHIPTOCOUNTRYCODE=".$shipToCountryCode;
	$nvpstr = $nvpstr . "&SHIPTOZIP=".$shipToZip;
	$nvpstr = $nvpstr . "&PHONENUM=".$phoneNum;
    //echo $nvpstr; exit;
	//'----------------------------------------------------------------------------------------------------
	//' Make the API call to PayPal
	//' If the API call succeded, then redirect the buyer to PayPal to begin to authorize payment.  
	//' If an error occured, show the resulting errors
	//'---------------------------------------------------------------------------------------------------
	$resArray=hash_call("SetExpressCheckout", $nvpstr);
	$ack = strtoupper($resArray["ACK"]);
	if($ack=="SUCCESS")
	{
		$token = urldecode($resArray["TOKEN"]);
		$_SESSION['TOKEN']=$token;
	}
	   
	return $resArray;
}

/*
'-------------------------------------------------------------------------------------------
' Purpose: Prepares the parameters for the GetExpressCheckoutDetails API Call.
' Inputs: None  
' Returns: The NVP Collection object of the GetExpressCheckoutDetails Call Response. 
'-------------------------------------------------------------------------------------------
*/
	
function GetShippingDetails( $token )
{
	//'--------------------------------------------------------------
	//' At this point, the buyer has completed authorizing the payment
	//' at PayPal.  The function will call PayPal to obtain the details
	//' of the authorization, incuding any shipping information of the
	//' buyer.  Remember, the authorization is not a completed transaction
	//' at this state - the buyer still needs an additional step to finalize
	//' the transaction
	//'--------------------------------------------------------------
   
	//'---------------------------------------------------------------------------
	//' Build a second API request to PayPal, using the token as the
	//'  ID to get the details on the payment authorization
	//'---------------------------------------------------------------------------
	$nvpstr="&TOKEN=".$token;

	//'---------------------------------------------------------------------------
	//' Make the API call and store the results in an array.  
	//'	If the call was a success, show the authorization details, and provide
	//' 	an action to complete the payment.  
	//'	If failed, show the error
	//'---------------------------------------------------------------------------
	
	$resArray = hash_call("GetExpressCheckoutDetails",$nvpstr);
	$ack = strtoupper($resArray["ACK"]);
	
	if($ack == "SUCCESS")
	{	
		$_SESSION['PAYPAL_PAYER_ID'] =	$resArray['PAYERID'];
	} 
	
	return $resArray;
}
	
/*
'----------------------------------------------------------------------------------------------------------------------------------
' Purpose: 	Prepares the parameters for the GetExpressCheckoutDetails API Call.
'
' Inputs:  
'		sBNCode:	The BN code used by PayPal to track the transactions from a given shopping cart.
' Returns: 
'		The NVP Collection object of the GetExpressCheckoutDetails Call Response.
'--------------------------------------------------------------------------------------------------------------------------------
*/

function ConfirmPayment()
{
	global $Cart;
	
	## Gather the information to make the final call to finalize the PayPal payment.  The variable nvpstr holds the name value pairs

	$OrderTotal 	= $Cart->getNetTotal();
	$ShippingAmt 	= $Cart->getShippingCharge();
	$TaxAmt 		= $Cart->getTaxValue();
	$HandlingAmt 	= $Cart->getShippingSignature();
	$GiftWrapping 	= $Cart->getGiftWrapping();

	## For the All Type Of Discounts Start Here
	$GiftCouponInfo = $Cart->getGiftCouponInfo();	// GC Discount Info
	$NetDiscount 	= $Cart->getNetDiscount(); 		// Coupon + Auto + Qty
	$TotalDiscount 	= $Cart->NumberFormat($NetDiscount + $GiftCouponInfo['Value']);
	## For the all types of discounts end here

	## Substract Discounts from order sub totals;
	$OrderSubTotal 		= $Cart->getSubTotal();
	$OrderSubTotal 		= $Cart->NumberFormat($OrderSubTotal - $TotalDiscount);
	
	## Shipping Address Start Here 
	$tempShippingAdd 	= $Cart->getShippingAddress();
	$shipToName 		= urlencode($tempShippingAdd['first_name']." ".$tempShippingAdd['last_name']);
	$shipToStreet 		= urlencode($tempShippingAdd['address1']);
	$shipToStreet2 		= urlencode($tempShippingAdd['address2']);
	$shipToCity 		= urlencode($tempShippingAdd['city']);
	$shipToState 		= urlencode($tempShippingAdd['state']);
	$shipToCountryCode 	= urlencode($tempShippingAdd['country']);
	$shipToZip 			= urlencode($tempShippingAdd['zip']);
	## Shipping Address End Here 	
	
	## Format the other parameters that were stored in the session from the previous calls	
	$token 				= urlencode($_SESSION['TOKEN']);
	$paymentType 		= urlencode("Sale");
	$currencyCodeType 	= urlencode("USD");
	$payerID 			= urlencode($_SESSION['PAYPAL_PAYER_ID']);
	$serverName 		= urlencode($_SERVER['SERVER_NAME']);
	
	//=============Added Code Date 17/03/2015 Start Here ===============//
	$HandlingAmt	    = $Cart->NumberFormat($HandlingAmt+$GiftWrapping['Charge']);
	
			
	$nvpstr  = '&TOKEN='.$token.'&PAYERID='.$payerID.'&PAYMENTACTION='.$paymentType.'&AMT='.$OrderTotal;
	$nvpstr .='&ITEMAMT='.$OrderSubTotal.'&SHIPPINGAMT='.$ShippingAmt.'&TAXAMT='.$TaxAmt.'&HANDLINGAMT='.$HandlingAmt;
	
	$nvpstr.='&SHIPTONAME='.$shipToName.'&SHIPTOSTREET='.$shipToStreet.'&SHIPTOCITY='.$shipToCity.'&SHIPTOCOUNTRYCODE='.$shipToCountryCode.'&SHIPTOSTATE='.$shipToState.'&SHIPTOZIP='.$shipToZip;

	$nvpstr.= '&CURRENCYCODE=' . $currencyCodeType . '&IPADDRESS=' . $serverName; 
	
	## Shows the Shopping Cart Contents on the Paypal Review Order Page
	$shopping_cart_contents = get_Shopping_Cart_Contents();
	
	if($shopping_cart_contents!='')
	{
		$nvpstr.=$shopping_cart_contents;
	}

	## Make the call to PayPal to finalize paymentIf an error occured, show the resulting errors

	$resArray=hash_call("DoExpressCheckoutPayment",$nvpstr);


	 /* 
		Display the API response back to the browser.
	    If the response from PayPal was a success, display the response parameters'
	    If the response was an error, display the errors received using APIError.php.
	 */
	$ack = strtoupper($resArray["ACK"]);

	return $resArray;
}

/*
'----------------------------------------------------------------------------------------------------------------------------------
* hash_call: Function to perform the API call to PayPal using API signature
* @methodName is name of API  method.
* @nvpStr is nvp string.
* returns an associtive array containing the response from the server.
'--------------------------------------------------------------------------------------------------------------------------------
*/

function hash_call($methodName,$nvpStr)
{
	## declaring of global variables
	global $API_Endpoint, $version, $API_UserName, $API_Password, $API_Signature;
	global $USE_PROXY, $PROXY_HOST, $PROXY_PORT;
	global $gv_ApiErrorURL;

	## setting the curl parameters.
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$API_Endpoint);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	## turning off the server and peer verification(TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_POST, 1);
	
	##if USE_PROXY constant set to TRUE in Constants.php, then only proxy will be enabled.
    ## Set proxy name to PROXY_HOST and port number to PROXY_PORT in constants.php 
	if($USE_PROXY)
	{
		curl_setopt ($ch, CURLOPT_PROXY, $PROXY_HOST. ":" . $PROXY_PORT); 
	}
	
	## NVPRequest for submitting to server
	$nvpreq="METHOD=".urlencode($methodName)."&VERSION=".urlencode($version)."&PWD=".urlencode($API_Password). "&USER=".urlencode($API_UserName)."&SIGNATURE=".urlencode($API_Signature).$nvpStr;
	## setting the nvpreq as POST FIELD to curl
	curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);


	## getting response from server
	$response = curl_exec($ch);

	## convrting NVPResponse to an Associative Array
	$nvpResArray = deformatNVP($response);
	$nvpReqArray = deformatNVP($nvpreq);
	$_SESSION['nvpReqArray'] = $nvpReqArray;
	/*echo "<pre>";
	print_r($nvpReqArray); exit;*/
	if (curl_errno($ch)) 
	{
		  ## moving to display page to display curl errors
		  //$_SESSION['curl_error_no']	= curl_errno($ch) ;
		  //$_SESSION['curl_error_msg']	= curl_error($ch);
		  
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

/*
'----------------------------------------------------------------------------------
' Purpose: Redirects to PayPal.com site.
' Inputs:  NVP string.
' Returns: 
'----------------------------------------------------------------------------------
*/

function RedirectToPayPal ( $token )
{
	global $PAYPAL_URL;
	## Redirect to paypal.com here
	$payPalURL = $PAYPAL_URL . $token;
	header("Location: ".$payPalURL);
	exit;
}

	
/*
'----------------------------------------------------------------------------------
* This function will take NVPString and convert it to an Associative Array and it will decode the response.
* It is usefull to search for a particular key and displaying arrays.
* @nvpstr is NVPString.
* @nvpArray is Associative Array.
'----------------------------------------------------------------------------------
*/
function deformatNVP($nvpstr)
{
	$intial=0;
	$nvpArray = array();

	while(strlen($nvpstr))
	{
		## postion of Key
		$keypos= strpos($nvpstr,'=');
		
		## position of value
		$valuepos = strpos($nvpstr,'&') ? strpos($nvpstr,'&'): strlen($nvpstr);

		## getting the Key and Value values and storing in a Associative Array
		$keyval = substr($nvpstr,$intial,$keypos);
		$valval = substr($nvpstr,$keypos+1,$valuepos-$keypos-1);
		
		##decoding the respose
		$nvpArray[urldecode($keyval)] =urldecode( $valval);
		$nvpstr = substr($nvpstr,$valuepos+1,strlen($nvpstr));
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
			
			$Item_QTY  = ''; 
			$Item_UNIT_PRICE  = ''; 
			
			$Item_SKU  = $tempCart[$tc]['SKU'];
			$Item_NAME = $tempCart[$tc]['ProductName'];
			
			$Item_QTY  			= $tempCart[$tc]['Qty'];
			$Item_UNIT_PRICE  	= $tempCart[$tc]['Price'];
			
		
			$items_description.= "&$L_NUMBER=".urlencode(stripslashes($Item_SKU))."&$L_NAME=".urlencode(str_replace('"','',stripslashes($Item_NAME)))."&$L_QTY=".$Item_QTY."&$L_AMT=".$Item_UNIT_PRICE."&$L_DESC=".urlencode(str_replace('"','',stripslashes($Item_DESC)));
	
		}	
		
		## For the All Type Of Discounts add item with negative amount Start Here
		$GiftCouponInfo 	= $Cart->getGiftCouponInfo();
		$NetDiscount 		= $Cart->getNetDiscount(); 		// Coupon + Auto + Qty 
		$TotalDiscount 		= $Cart->NumberFormat($NetDiscount  + $GiftCouponInfo['Value']);
		
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
