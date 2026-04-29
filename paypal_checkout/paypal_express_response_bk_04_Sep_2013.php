<?php
require_once("../lib/configuration.php");

## Check to see if the Request object contains a variable named 'token'	
$token = "";
if (isset($_REQUEST['token']) and !empty($_REQUEST['token']))	
{	
	$_SESSION['token'] = '';
	unset($_SESSION['token']);
	
	$token = $_REQUEST['token'];
	$_SESSION['token'] = $token;
}
## token check end here 

## if token not set then redired on shoppin cart
if($_SESSION['token'] =="" or empty($_SESSION['token']))
{
	$ErrorLongMsg = "Error in Processing Request, Please try again.";
	$commonobj->setDisplayMessage($ErrorLongMsg);
	header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	exit;
}

## For the Confirm Payment of the Paypal Express Checkout 
## Redirect from the order process
$return_flag = $_GET['return_flag']; // confirm_payment;

require_once("paypal_express_functions.php");
	
if($return_flag!='confirm_payment') 
{
	$resArray = GetShippingDetails($token);

	$ack = strtoupper($resArray["ACK"]);
	//echo "<pre>"; print_r($ack); exit;
	if($ack == "SUCCESS" or $ack="SUCCESSWITHWARNING" )	
	{
		## Billing Address Start Here
		
		//Code for spilitting name 
		$name=explode(" ",trim($resArray['SHIPTONAME']));
		$first_name=$name[0];
		$last_name='';
		
		if(count($name) > 1 )
		{
			$last_name=$name[count($name)-1];
		}
		//End code for spillitting name		
		
		$Cart->post['bl_fname'] 	  	= $first_name;
		$Cart->post['bl_lname'] 	  	= $last_name;
		$Cart->post['bl_company'] 		= '';
		$Cart->post['bl_Addr1'] 	  	= $resArray['SHIPTOSTREET'];
		$Cart->post['bl_Addr2'] 	  	= $resArray['SHIPTOSTREET2'];
		$Cart->post['bl_city'] 	  		= $resArray['SHIPTOCITY'];
		$Cart->post['bl_country'] 		= $resArray['SHIPTOCOUNTRYCODE'];
		$Cart->post['bl_state'] 	  	= $resArray['SHIPTOSTATE'];
		$Cart->post['bl_otherstate'] 	= $resArray['SHIPTOSTATE'];
		$Cart->post['bl_zip'] 			= $resArray['SHIPTOZIP'];
		$Cart->post['bl_phone']	  		= '';
		$Cart->post['bl_email'] 	  	= $resArray['EMAIL'];

		if($resArray['SHIPTOCOUNTRYCODE']!='US') 
		{
			$Cart->post['bl_state'] 	  = '';
			$Cart->post['bl_otherstate']  = $resArray['SHIPTOSTATE'];
		} 
		else 
		{
			$Cart->post['bl_state'] 	 = $resArray['SHIPTOSTATE'];
			$Cart->post['bl_otherstate'] = '';
		}
		
		$Cart->setBillingAddress();
		## Billing Address End Here		
		
		$Cart->setBillingAsShipping('Yes');
		
		## Shipping Address Start Here
		$Cart->setShippingAddress();
		## Shipping Address End Here
		
		header("location:".$SECURED_PATH."index.php?file=paypal_billing");		
		exit;
	}	
	else	
	{
		##Display a user friendly Error on the page using any of the following error information returned by PayPal
		$ErrorCode 			= $resArray["L_ERRORCODE0"];
		$ErrorShortMsg		= $resArray["L_SHORTMESSAGE0"];
		$ErrorLongMsg 		= $resArray["L_LONGMESSAGE0"];
		$ErrorSeverityCode  = $resArray["L_SEVERITYCODE0"];
		
		$commonobj->setDisplayMessage($ErrorLongMsg);
		
		header("location:".$SECURED_PATH."index.php?file=shoppingcart");
		exit;
	}
}



/*
'------------------------------------
' The paymentAmount is the total value of 
' the shopping cart, that was set 
' earlier in a session variable 
' by the shopping cart page
'------------------------------------
*/
$finalPaymentAmount =  $Cart->getNetTotal();
/*
'------------------------------------
' Calls the DoExpressCheckoutPayment API call
' The ConfirmPayment function is defined in the file PayPalFunctions.jsp,
' that is included at the top of this file.
'-------------------------------------------------
*/		
$resArray = ConfirmPayment ();

$ack = strtoupper($resArray["ACK"]);

if($ack=="SUCCESS" or $ack=="SUCCESSWITHWARNING")
{
	
	$payerID 	   		= urlencode($_SESSION['PAYPAL_PAYER_ID']);
	
	$ACK 				= $resArray["ACK"];
	$CORRELATIONID 		= $resArray["CORRELATIONID"];
	$TIMESTAMP 			= $resArray["TIMESTAMP"];
	$ORDERTIME 			= $resArray["ORDERTIME"];
	$PAYMENTSTATUS 		= $resArray["PAYMENTSTATUS"];
	$PENDINGREASON 		= $resArray["PENDINGREASON"];
	$REASONCODE 		= $resArray["REASONCODE"];
	$transactionId		= $resArray["TRANSACTIONID"]; 
	$transactionType 	= $resArray["TRANSACTIONTYPE"]; 
	$paymentType		= $resArray["PAYMENTTYPE"];  
	$amt				= $resArray["AMT"];  
	$currencyCode		= $resArray["CURRENCYCODE"];  
	$feeAmt				= $resArray["FEEAMT"];  //' PayPal fee amount charged for the transaction
	$settleAmt			= $resArray["SETTLEAMT"];  //' Amount deposited in your PayPal account after a currency conversion.
	$taxAmt				= $resArray["TAXAMT"];  //' Tax charged on the transaction.
	$exchangeRate		= $resArray["EXCHANGERATE"];
	
	
	$transaction_info = "This transaction has been approved.";
	
	$payment_gateway_response ="ACK=".$ACK." -- "."PAYER ID=".$payerID." -- TIMESTAMP=".$TIMESTAMP." -- CORRELATIONID=".$CORRELATIONID." -- "."TRANSACTION ID=".$transactionId." -- "."TRANSACTION TYPE=".$transactionType." -- "."PAYMENT TYPE=".$paymentType." -- ORDERTIME=".$ORDERTIME." -- PAYMENTSTATUS=".$PAYMENTSTATUS." -- PENDINGREASON=".$PENDINGREASON." -- REASONCODE=".$REASONCODE;
	
	$updAray = array (
						'pay_status' 	   			=> 'Paid',
						'transaction_info' 			=> $transaction_info,
						'payment_gateway_response' 	=> $payment_gateway_response,
						'paypal_payer_id' 	   		=> $payerID,
						'paypal_transaction_id' 	=> $transactionId,
						'paypal_transaction_status' => $PAYMENTSTATUS,
						'paypal_transaction_date' 	=> $TIMESTAMP
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
	while (isset($resArray["L_ERRORCODE".$count])) 
	{		
		$errormsg.="Error Code:".$resArray["L_ERRORCODE".$count]." ### ";
		$errormsg.="Short Error Msg:".$resArray["L_SHORTMESSAGE".$count]. " ### ";
		$errormsg.="Long Error Msg:".$resArray["L_LONGMESSAGE".$count]." ### "; 
		$count=$count+1;
	}
			
	$transaction_info = "This transaction has been Declined.";
	
	$payment_gateway_response ="ACK=".urldecode($resArray["ACK"])."--"."CORRELATIONID=".urldecode($resArray["CORRELATIONID"])." -- "."TIMESTAMP =".urldecode($resArray["TIMESTAMP"])." -- ".$errormsg;

	$updAray = array (
						'pay_status' 	   			=> 'Declined',
						'transaction_info' 			=> $transaction_info,
						'payment_gateway_response' 	=> $payment_gateway_response,
						'paypal_payer_id' 	   		=> $payerID,
						'paypal_transaction_id' 	=> $transactionId,
						'paypal_transaction_status' => $PAYMENTSTATUS,
						'paypal_transaction_date' 	=> $TIMESTAMP
					  );
					  
	$where_cond = " orders_id='".$Cart->getOrderID()."' ";	
					  
	$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);

	$commonobj->setDisplayMessage($resArray["L_LONGMESSAGE0"]);
	
	header("location:".$SECURED_PATH."index.php?file=shoppingcart");
	exit;
}
?>
