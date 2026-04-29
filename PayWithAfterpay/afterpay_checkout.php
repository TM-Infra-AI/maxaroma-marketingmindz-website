<?php
	//https://docs.afterpay.com/online-api-v2-b857508478e7.html#create-checkout
	//https://developer.afterpay.io/api
	
	//error_reporting(E_ALL);
	//ini_set("display_errors",1);
	
	require_once("../lib/configuration.php");
	require_once("../classes/shoppingcart.cls.php");
	require_once("afterpay_functions.php");

	//echo "<pre>";print_r($_SESSION);exit;

	//require_once("paypal_express_functions.php");

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
	
	$PaymentArr  = getOrderTotalDetails(); // For Order Total
	$Payment_Amount  = $PaymentArr['Payment_Amount'];
	$Payment_Currency  = $PaymentArr['Payment_Currency'];
	
	//echo "<pre>";print_r($PaymentArr);exit;
	//echo $Payment_Amount;exit;
	
	$payload = array();
	$payload['amount']['amount'] = $Payment_Amount;
	$payload['amount']['currency'] = $Payment_Currency;
	$payload['merchantReference'] = "OR".$Cart->getOrderID();
	if(isset($_SESSION['sess_icustomerid'])){
		$sql = "select first_name,last_name,email from ".TABLE_PREFIX."customer where customer_id='".$_SESSION['sess_icustomerid']."'";
		$Data = $obj->select($sql);
		//echo "<pre>";print_r($Data);exit;	
		//$payload['consumer']['phoneNumber'] = "120000000";	//optional	
		$payload['consumer']['givenNames'] = $Data[0]['first_name'];		//optional
		$payload['consumer']['surname'] = $Data[0]['last_name'];		//optional
		$payload['consumer']['email'] = $Data[0]['email'];	//required
	}else{
		//$payload['consumer']['phoneNumber'] = "120000000";	//optional	
		//$payload['consumer']['givenNames'] = "Joe";		//optional
		//$payload['consumer']['surname'] = "Consumer";		//optional
		//$payload['consumer']['email'] = "test@afterpay.com";	//required
		
		$ErrorLongMsg = "Error in Processing Request, Please try again.";
		$commonobj->setDisplayMessage($ErrorLongMsg);
		header("location:".$SECURED_PATH."index.php?file=billing");
		exit;
	}
	
	$payload['merchant']['redirectConfirmUrl'] = $SECURED_PATH."PayWithAfterpay/afterpay_payment_auth.php";
	$payload['merchant']['redirectCancelUrl'] = $SECURED_PATH."PayWithAfterpay/afterpay_payment_auth.php";
	
	
	############################Extra details start##########################################
		$ShoppingCart = $_SESSION['ShoppingCart'];
		//shipping details
		$payload['shipping']['name'] = $ShoppingCart['ShippingAddress']['first_name']." ".$ShoppingCart['ShippingAddress']['last_name'];
		$payload['shipping']['line1'] = $ShoppingCart['ShippingAddress']['address1'];
		$payload['shipping']['area1'] = $ShoppingCart['ShippingAddress']['city'];
		$payload['shipping']['region'] = $ShoppingCart['ShippingAddress']['state'];
		$payload['shipping']['postcode'] = $ShoppingCart['ShippingAddress']['zip'];
		$payload['shipping']['countryCode'] = $ShoppingCart['ShippingAddress']['country'];
		$payload['shipping']['phoneNumber'] = $ShoppingCart['ShippingAddress']['phone'];
		//shipping details
		
		//Billing details
		$payload['billing']['name'] = $ShoppingCart['BillingAddress']['first_name']." ".$ShoppingCart['BillingAddress']['last_name'];
		$payload['billing']['line1'] = $ShoppingCart['BillingAddress']['address1'];
		$payload['billing']['area1'] = $ShoppingCart['BillingAddress']['city'];
		$payload['billing']['region'] = $ShoppingCart['BillingAddress']['state'];
		$payload['billing']['postcode'] = $ShoppingCart['BillingAddress']['zip'];
		$payload['billing']['countryCode'] = $ShoppingCart['BillingAddress']['country'];
		$payload['billing']['phoneNumber'] = $ShoppingCart['BillingAddress']['phone'];
		//Billing details
		
		//Items details
		$ItemArr = $ShoppingCart['Cart'];
		
		for($i=0;$i < count($ItemArr); $i++){
			$payload['items'][$i]['name'] = $ItemArr[$i]['ProductName'];
			$payload['items'][$i]['sku'] = $ItemArr[$i]['SKU'];
			$payload['items'][$i]['quantity'] = $ItemArr[$i]['Qty'];
			$payload['items'][$i]['pageUrl'] = $ItemArr[$i]['Prod_URL'];
		
			$payload['items'][$i]['price']['amount'] = $ItemArr[$i]['TotPrice'];
			$payload['items'][$i]['price']['currency'] = "USD";
			 
			//$payload['items'][$i]['imageUrl'] = $ItemArr[$i]['ProductName'];
		}
		
		//Items details
		
		//Shipping amount
			/* if($ShoppingCart['Shipping']['ShippingCharge'] > 0){
				$payload['shippingAmount']['amount'] = $ShoppingCart['Shipping']['ShippingCharge'];
				$payload['shippingAmount']['currency'] = "USD";
			} */
		//Shipping amount
	############################Extra details End##########################################
	
	
	//$payload['merchantReference']= "ord_".rand(111111,999999);
	
	$payloaddata = json_encode($payload);
	$getcheckout = GetAfterPayResult($payloaddata,"checkouts");	//initiate checkout and get token
	
	//echo "<pre>";print_r($getcheckout);exit;
	if(isset($getcheckout['token']) && $getcheckout['token'] != ""){
		$redirect = $getcheckout['redirectCheckoutUrl'];
		$token = $getcheckout['token'];
		$expires = $getcheckout['expires'];
		
		$updAray = array (
							'status' 	   				=> 'Sent To AfterPay'
						  );
						  
		$where_cond = " orders_id='".$Cart->getOrderID()."' ";				  
		$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);
		
	}else{
		$transaction_info = "This transaction has been Declined.";
		$Payment_response = json_encode($getcheckout);
		$updAray = array (
							'status' 	   				=> 'Declined',
							'transaction_info' 			=> $transaction_info,
							'payment_gateway_response' 	=> $Payment_response
						  );
						  
		$where_cond = " orders_id='".$Cart->getOrderID()."' ";	
						  
		$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);
		
		$ErrorLongMsg = "Error in Processing Request, Please try again.";
		$commonobj->setDisplayMessage($ErrorLongMsg);
		header("location:".$SECURED_PATH."index.php?file=billing");
		exit;
	}
?>
<script src="<?=$Token_JS_Url?>" async></script>
<script>
	//var Token_JS_Url = '<?=$Token_JS_Url?>';
	window.onload = function() {
		var tokenkey = '<?=$token?>';
		AfterPay.initialize({countryCode: "US"});
        AfterPay.redirect({token: tokenkey});
    };
</script>
