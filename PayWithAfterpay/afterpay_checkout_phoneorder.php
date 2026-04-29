<?php
	//https://docs.afterpay.com/online-api-v2-b857508478e7.html#create-checkout
	//https://developer.afterpay.io/api
	
	//error_reporting(E_ALL);
	//ini_set("display_errors",1);
	
	require_once("../lib/configuration.php");
	require_once("../classes/shoppingcart.cls.php");
	require_once("../classes/general.cls.php");
	require_once("afterpay_functions.php");
	
	$generalobj  	= new General($obj,$smarty);
	//echo "<pre>";print_r($generalobj);exit;

	//require_once("paypal_express_functions.php");
	$order_id = $_SESSION["phoneorder_detail"]["order_id"];
	$order_total = $_SESSION["phoneorder_detail"]["order_amt"];
	$customer_id = $_SESSION["phoneorder_detail"]["customer_id"];
	
	if( $order_id <= 0 || $order_total <= 0) 
	{
		header("location:".$SECURED_PATH);
		exit();
	}
	
	$payload = array();
	$payload['amount']['amount'] = $order_total;
	$payload['amount']['currency'] = "USD";
	
	if(isset($customer_id)){
		$sql = "select first_name,last_name,email from ".TABLE_PREFIX."customer where customer_id='".$customer_id."'";
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
	}
	
	$payload['merchant']['redirectConfirmUrl'] = $SECURED_PATH."PayWithAfterpay/afterpay_payment_auth_phoneorder.php";
	$payload['merchant']['redirectCancelUrl'] = $SECURED_PATH."PayWithAfterpay/afterpay_payment_auth_phoneorder.php";
	
	
	############################Extra details start##########################################
		$sql = "select o.ship_first_name,o.ship_last_name,o.ship_company,o.ship_email,o.ship_address1,o.ship_address2,o.ship_city,o.ship_zip,o.ship_state,o.ship_country,o.ship_phone,o.bill_first_name,o.bill_last_name,o.bill_company,o.bill_email,o.bill_address1,o.bill_address2,o.bill_city,o.bill_zip,o.bill_state,o.bill_country,o.bill_phone from ".TABLE_PREFIX."orders o where o.orders_id='".$order_id."'";
		$Data_orders = $obj->select($sql);
		
		//shipping details
		$payload['shipping']['name'] = $Data_orders[0]['ship_first_name']." ".$Data_orders[0]['ship_last_name'];
		$payload['shipping']['line1'] = $Data_orders[0]['ship_address1'];
		$payload['shipping']['area1'] = $Data_orders[0]['ship_city'];
		$payload['shipping']['region'] = $Data_orders[0]['ship_state'];
		$payload['shipping']['postcode'] = $Data_orders[0]['ship_zip'];
		$payload['shipping']['countryCode'] = $Data_orders[0]['ship_country'];
		$payload['shipping']['phoneNumber'] = $Data_orders[0]['ship_phone'];
		//shipping details
		
		//Billing details
		$payload['billing']['name'] = $Data_orders[0]['bill_first_name']." ".$Data_orders[0]['bill_last_name'];
		$payload['billing']['line1'] = $Data_orders[0]['bill_address1'];
		$payload['billing']['area1'] = $Data_orders[0]['bill_city'];
		$payload['billing']['region'] = $Data_orders[0]['bill_state'];
		$payload['billing']['postcode'] = $Data_orders[0]['bill_zip'];
		$payload['billing']['countryCode'] = $Data_orders[0]['bill_country'];
		$payload['billing']['phoneNumber'] = $Data_orders[0]['bill_phone'];
		//Billing details
		
		//Items details
		$sql = "select od.* from ".TABLE_PREFIX."order_detail od where od.orders_id='".$order_id."'";
		$Data_order_detail = $obj->select($sql);
		
		for($i=0;$i < count($Data_order_detail); $i++){
			$prd_res = $obj->select("SELECT p.products_id, p.product_name ,p.sku, p.sale_price, p.current_stock,p.short_description,p.private_code,p.is_private,p.status FROM ".TABLE_PREFIX."products p WHERE p.sku = '".mysqli_real_escape_string($obj->CONN,stripslashes($Data_order_detail[$i]['sku']))."' AND p.status='1'");
			
		    $prd_sku = $prd_res[0]["sku"];	
		    $iprod_id = $prd_res[0]["products_id"];
		    $product_name = $prd_res[0]["product_name"];
		    $short_description = $prd_res[0]["short_description"];	
		   
			$CodeVal = "";
			if($prd_res[0]["private_code"]!='' && $prd_res[0]["is_private"]=='Yes' && $prd_res[0]["status"]=='2')
			{
				$CodeVal = $prd_res[0]["private_code"];
			}
			
			$p_link = $generalobj->getProductRewriteURL($prd_res[0]['products_id'], $prd_res[0]['product_name']);
			if($CodeVal!='')
			{
				$p_link = $generalobj->getProductRewriteURL($prd_res[0]['products_id'], $prd_res[0]['product_name'])."/".$CodeVal;
			}
			
			$payload['items'][$i]['name'] = $Data_order_detail[$i]['product_name'];
			$payload['items'][$i]['sku'] = $Data_order_detail[$i]['sku'];
			$payload['items'][$i]['quantity'] = $Data_order_detail[$i]['quantity'];
			
			$payload['items'][$i]['pageUrl'] = $Data_order_detail[$i]['Prod_URL'];
		
			$payload['items'][$i]['price']['amount'] = $Data_order_detail[$i]['total'];
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
							'status' 	   			=> 'Sent To AfterPay',
							'payment_type' 			=> 'PAYMENT_PAYWITHAFTERPAY',
							'payment_method' 		=> 'Pay With Afterpay'
						  );
						  
		$where_cond = " orders_id='".$order_id."' ";				  
		$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);
		
	}else{
		$err_msg = "Something went wrong, Please try again.";
		$commonobj->setDisplayMessage($err_msg);

		header("location:".$Site_URL."payment/".base64_encode($order_id)."/".base64_encode("0"));
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
