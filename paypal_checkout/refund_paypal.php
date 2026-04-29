<?php
require_once(CLASS_PATH."encrypt.cls.php");


###### Here fetch Paypal info start ############

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

//echo "<pre>"; print_r($db_res); exit;
$arrPEVar = array();
if( count($db_res) > 0)
{
	$is_gateway_availabel = 'Yes';

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
	$is_gateway_availabel = 'No';
}


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

function process_Refund_Payment_Paypal($orders_id = NULL,$refund_amount=0,$refund_comment = '',$orders_no='',$refund_shipping_cost=0,$refund_restocking_fee=0)
{
	global $obj,$is_gateway_availabel;

	if((int)$orders_id==0)
	{
		return NULL;
	}

	if($is_gateway_availabel == 'No')
	{
		$p_msg = "Paypal service is temporarily unavailable at this time. <br>Your Order can not be refund.";

		return $p_msg;
	}

	## Basic Param For Refund
	$x_currency    =  "USD";
	$x_refundType  =  "Partial"; ## Partial or Full
	$x_trans_id    =  get_TrasactionId($orders_id);

	## Must required if Partial Refund
	$x_amount      =  number_format($refund_amount,2,'.','');
	$x_memo 	   =  $orders_no;

	if($x_amount <=0)
	{
		$p_msg = "Your order can not been refund.<br>For refund order, Refund Amount must be greater than zero.";
		return $p_msg;
	}

	## Make NVP string Start
	$nvpstr = "";

	$nvpstr .= "&TRANSACTIONID=".urlencode($x_trans_id);
	$nvpstr .= "&REFUNDTYPE=".urlencode($x_refundType);
	$nvpstr .= "&CURRENCYCODE=".urlencode($x_currency);

	## Must required if Partial Refund
	$nvpstr .= "&AMT=".$x_amount;
	$nvpstr .= "&NOTE=".urlencode("Refund Transaction at fragrancedepot.com - Order # ".$x_memo);


	$arrReturn = hash_call("RefundTransaction",$nvpstr);

	if($arrReturn['Is_Curl_Error'] == 'Yes')
	{
		$p_msg = "Error in Processing Request Please try again.";
		return $p_msg;
	}


	## Update Order Table Start #############
	$slq = "SELECT total_refund_amount,refund_transaction_response, refund_comment,order_total,customer_id,apply_credit,orders_no FROM `".TABLE_PREFIX."orders` WHERE orders_id = '".(int)$orders_id."' LIMIT 0,1";
	$order_res = $obj->select($slq);


	## Make Gate Response String From Array
	$gateway_response = '';

	foreach ($arrReturn as $key => $value)
	{
		$gateway_response .= $key."=".$value." | ";
	}


	$refund_transaction_response   = $order_res[0]['refund_transaction_response'];
	$refund_transaction_response  .= $gateway_response."<br><br> ";
	$refund_transaction_response  .= "Response recieved on date ".date('m/d/Y H:i:s')."<br><br> ";

	$new_refund_comment  = $order_res[0]['refund_comment'];
	$new_refund_comment  .= ."<br>".$refund_comment."<br><br> ";
	$new_refund_comment  .= "Refund processed by(original payment) : <b>".$_SESSION['sess_admin_email']."</b> on date ".date('m/d/Y H:i:s')."<br><br>Original Shipping Cost:".$refund_shipping_cost."<br>Restocking Fee:".$refund_restocking_fee."<br><br>" ;
	/*$arrUp = array(
					'refund_transaction_response' 	=> $refund_transaction_response,
					'refund_comment' 				=> $new_refund_comment,
					'order_upd_datetime'			=> date("Y-m-d H:i:s")
				  );
	*/
	$update_order = "UPDATE `".TABLE_PREFIX."orders` SET `refund_transaction_response` = '".$refund_transaction_response."', ";
	$update_order .= " `refund_comment` = '".$new_refund_comment."', ";
	$update_order .= " `order_upd_datetime` = '".date("Y-m-d H:i:s")."' ";

	if(strtoupper($arrReturn["ACK"]) == "SUCCESS"  or strtoupper($arrReturn["ACK"]) == "SUCCESSWITHWARNING")
	{
		$total_refund_amount  			= $order_res[0]['total_refund_amount'] + $refund_amount;
		$total_refund_amount			= number_format($total_refund_amount,2,'.','');
		//$arrUp["total_refund_amount"]   = $total_refund_amount;
		$update_order .= " ,`refund_comment` = '".$new_refund_comment."', ";
		$update_order .= " `refund_shipping_cost` = '".$refund_shipping_cost."', `refund_restocking_fee` = '".$refund_restocking_fee."'";
		$update_order .= " ,`total_refund_amount` = '".$total_refund_amount."' ";
	}


	$cntstr = '';
		if($total_refund_amount==$order_res[0]['order_total']) {
				$update_order.= " , status='Refund' ";
				//CreditAmountRefund($order_res[0]['customer_id'],$order_res[0]['apply_credit']);
		}


	$update_order .= " WHERE orders_id = '".$orders_id."'";
	$obj->sql_query($update_order);

	## Update Order Table End #############


	## Return Message
	$p_msg  = '';
	if(strtoupper($arrReturn["ACK"]) == "SUCCESS"  or strtoupper($arrReturn["ACK"]) == "SUCCESSWITHWARNING")
	{
		if($total_refund_amount==$order_res[0]['order_total']) {
			RefundRewardPoint($orders_no,$order_res[0]['order_total']);
		}
		RefundAmountEmail($refund_amount,$total_refund_amount,$orders_id,$refund_shipping_cost,$refund_restocking_fee);

		$p_msg = "Refund processed successfully.<br>This transaction has been approved.";
	}
	else
	{
		$p_msg = "Your order has not been refund.<br>".str_replace(":","-",$arrReturn["L_LONGMESSAGE0"]);
	}

	return $p_msg;
}

function get_TrasactionId($orders_id)
{
	 global $obj;

	 $slq = "SELECT payment_gateway_response FROM `".TABLE_PREFIX."orders`
	 		WHERE orders_id = '".(int)$orders_id."' LIMIT 0,1";

	 $order_res = $obj->select($slq);

	 if(trim($order_res[0]['payment_gateway_response']) == '')
	 {
	 	return NULL;
	 }

	 $gateway_response = explode(" -- ",$order_res[0]['payment_gateway_response']);

	 $arr_response = array();
	 for($p=0; $p<count($gateway_response); $p++)
	 {
	 	$arrTemp = explode("=",trim($gateway_response[$p]));

		if(trim($arrTemp[0]) !='')
		{
			$arr_response[trim($arrTemp[0])] = trim($arrTemp[1]);
		}

	 }

	if(array_key_exists('TRANSACTION ID', $arr_response))
	{
		 $Trand_Id = trim($arr_response['TRANSACTION ID']);
	}
	else
	{
		$Trand_Id = '';
	}
	 return $Trand_Id;
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
	$nvpResArray	= deformatNVP($response);

	$nvpReqArray	= deformatNVP($nvpreq);

	if (curl_errno($ch))
	{
		## moving to display page to display curl errors
		$ErrorLongMsg = "Error in Processing Request Please try again.";

		$nvpResArray['Is_Curl_Error'] = "Yes";

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



?>
