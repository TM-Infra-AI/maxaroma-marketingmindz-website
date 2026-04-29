<?php
	//https://docs.afterpay.com/online-api-v2-b857508478e7.html#capture-without-auth
	//$url = "https://api.us-sandbox.afterpay.com/v2/payments/auth";
	
	require_once("../lib/configuration.php");
	require_once("afterpay_functions.php");
	
	require_once("../classes/general.cls.php");
	$generalobj  	= new General($obj,$smarty);
	
	$order_id = $_SESSION["phoneorder_detail"]["order_id"];
	if(isset($_REQUEST) && $_REQUEST['return_flag'] == "confirm_payment"){
		$trans_id = $_SESSION["phoneorder_detail"]["Afterpay"]['AP_Auth_ID'];
		$trans_token = $_SESSION["phoneorder_detail"]["Afterpay"]['AP_Auth_Token'];
		//$auth_amt = $_SESSION["phoneorder_detail"]["Afterpay"]['AP_Auth_Amt'];
		//$auth_currency = $_SESSION["phoneorder_detail"]["Afterpay"]['AP_Auth_Currency'];
				
		$payload = array();
		
		$order_total = $_SESSION["phoneorder_detail"]["order_amt"];

		$payload['amount']['amount'] = $order_total;
		$payload['amount']['currency'] = "USD";
		
		$payloaddata = json_encode($payload);
		//echo "<pre>";print_r($payload);exit;
		
		$url = "payments/".$trans_id."/capture";
		$capture_arr = GetAfterPayResult($payloaddata,$url);
		$payment_gateway_response = json_encode($capture_arr);
		
		if($capture_arr['status'] == "APPROVED" && ($capture_arr['paymentState'] == "PARTIALLY_CAPTURED" || $capture_arr['paymentState'] == "CAPTURED")){
			$transaction_info = "This transaction has been approved.";
			
			$sql = "SELECT payment_gateway_response FROM `".TABLE_PREFIX."orders` WHERE orders_id='".$order_id."'";
			$db_res = $obj->select($sql);
			
			if(!empty($db_res)){
				$payment_gateway_response = $db_res[0]['payment_gateway_response']."\n\n==============\n\nCapture Response::".$payment_gateway_response;
			}
			
			$updAray = array (
								'pay_status' 	   			=> 'Paid',
								'status' 	   				=> 'Pending',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'phoneorder_paymentdate' => date("Y-m-d H:i:s")
							  );
			$where_cond = " orders_id='".$order_id."' ";				  
			$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);
			
			
			############################# Complete Other Related processes Start #########################################
				if($uporderres){
					$sql = "SELECT * FROM `".TABLE_PREFIX."orders` WHERE orders_id ='".$order_id."'";
					$OrderRS = $obj->select($sql);
					
					$sql = "SELECT * FROM `".TABLE_PREFIX."order_detail` WHERE orders_id ='". $order_id."' ORDER BY orders_detail_id " ;
					$OrderDetailRs = $obj->select($sql);
		
					if($OrderRS[0]['gc_code']!="")
					{
						$sql = "SELECT * FROM `".TABLE_PREFIX."gift_certificate` WHERE remaining_value > 0 AND status ='1' AND gc_code ='".$OrderRS[0]['gc_code']."'";
						$CouponRS = $obj->select($sql);
					}  
					
					if(count($CouponRS) > 0) 
					{
						$gc_remaining_value = 0;
						$new_total = $OrderRS[0]['order_total'] + $OrderRS[0]['gc_amount'];
						
						if($new_total <= $CouponRS[0]['remaining_value'])
						{
							$gc_remaining_value = $CouponRS[0]['remaining_value']-$new_total;
						}
						
						if($CouponRS[0]['gc_code'] != '' && $CouponRS[0]['remaining_value'] > 0 ) 
						{
							$upgGif = array (
							'remaining_value'	=>	$gc_remaining_value,
							'last_used_date'	=>	date('Y-m-d H:i:s')
							);
							
							$udpGift = $obj->update(TABLE_PREFIX.'gift_certificate', $upgGif, " gc_code = '".$CouponRS[0]['gc_code']."' ");
						}
						
						if($CouponRS[0]['gc_code'] != '' && $new_total <= $CouponRS[0]['remaining_value'])
						{
							$str_info  = 'Gift Certificate dicount value is greater than order total amount. \n\n';
							$str_info .= 'So net $'.$new_total.' is deduct from gift certifiacte value. \n\n';
							$str_info .= 'Used Gift Certificate code is ('.$CouponRS[0]['gc_code'].')';
							
							$updAray = array (
							'pay_status' 	   => 'Paid',
							'transaction_info' => $str_info,
							);
							$updOrder = $obj->update(TABLE_PREFIX.'orders', $updAray, " orders_id='".$order_id."' ");
						}
					}
					
					
					if(strtolower($OrderRS[0]['user_type'])=='retailer') {
						$rewardarray_use = array();
						$reward_discount =  $OrderRS[0]['reward_discount'];
						
						if($reward_discount > 0) {
							$sql_client = "SELECT customer_id, iRewardpoint FROM `".TABLE_PREFIX."customer` WHERE `customer_id` = '".$OrderRS[0]['customer_id']."'  AND `status`='1'  LIMIT 0,1";
							$res_client = $obj->select($sql_client);
							
							$FinalReaminRewardpoint = 0;
							
							//////////////////////
							$Redeem_Reward = $obj->select("SELECT `forderamount`,`fcharge` FROM `".TABLE_PREFIX."rewardrule` WHERE `erewardrule`='redeem'");
							$Max_Reward = $obj->select("SELECT `fcharge` FROM `".TABLE_PREFIX."rewardrule` WHERE `erewardrule`='max'");	
							
							if($res_client[0]['iRewardpoint'] >  $Max_Reward[0]["fcharge"])
							{
								$refer_amount = ($res_client[0]['iRewardpoint']/$Redeem_Reward[0]["fcharge"]);
		
								
								if($reward_discount < $OrderRS[0]['sub_total'] )
								{
									$remain_count = $Redeem_Reward[0]["fcharge"] * (int)$refer_amount;
									$reward_remaining = $res_client[0]['iRewardpoint'] - $remain_count;
									$Total_Reward_Point = $res_client[0]['iRewardpoint'];
									$AppliedRewardPoint = $res_client[0]['iRewardpoint'];
								}
							}
							
							
							
							if((int)$reward_remaining > 0  && $reward_discount>0) {
								 $FinalReaminRewardpoint = (int)$reward_remaining; 
							}
							else{
								 $FinalReaminRewardpoint = $res_client[0]['iRewardpoint'];
							}
							
							
							
							//echo "<pre>".$FinalReaminRewardpoint;print_r($_SESSION);exit;
							$upgCustomer = array (
							'iRewardpoint' => $FinalReaminRewardpoint
							);									 
							$udpRefer = $obj->update(TABLE_PREFIX.'customer', $upgCustomer, " `customer_id` = '".$OrderRS[0]['customer_id']. "'");
							
							if($remain_count  > 0)
							{
								$InsertCustomer = array (
								'customer_id' 	=> $OrderRS[0]['customer_id'],
								'note'		  	=> "Deduct Reward Point By Phone Order",
								'iRewardpoint'	=> $remain_count,
								'Order_No'		=> $OrderRS[0]["orders_no"]
								);
								
								$obj->insert(TABLE_PREFIX.'reward_point', $InsertCustomer);
							}
						}
					}
					
					$Rewardchk_arr = array();
					if(count($OrderDetailRs)>0) {
						$DealTotalprice = 0;
						for($dl=0; $dl<count($OrderDetailRs); $dl++) {
							$sqldeal  = " SELECT dealofweek_id, product_sku FROM ".TABLE_PREFIX."dealofweek WHERE status='1' AND start_date <= '".date('Y-m-d')."' AND end_date >= '".date('Y-m-d')."' and  product_sku = '".$OrderDetailRs[$dl]['sku']."' limit 1";
							$dealofdayRS= $obj->select($sqldeal);
							
							if($dealofdayRS && count($dealofdayRS)>0) {
								$DealTotalprice = $DealTotalprice+$OrderDetailRs[$dl]['total'];
							}
							else {
								$Rewardchk_arr[] = $OrderDetailRs[$dl]['sku'];
							}
						}
					}
					
					if(strtolower($OrderRS[0]['user_type'])=='retailer') {
						$rewardsql = $obj->sql_query("select * from `".TABLE_PREFIX."rewardrule` where erewardrule = 'reward'");
						
						if($rewardsql && count($rewardsql)>0) {
							//Deal product's reward point count :: Start
							$Rewardtotal = $OrderRS[0]['order_total'];
							$RewardtotalNext = $OrderRS[0]['order_total'];
							$DealRewardpoint = 0;
							
							if($DealTotalprice>0) {
								$valuedeal = ( $DealTotalprice * 2)/$rewardsql[0]['forderamount'];
								$DealRewardpoint = number_format($valuedeal, 0, '.', '');
								
								if($Rewardtotal>$DealTotalprice){
									$Rewardtotal = $Rewardtotal-$DealTotalprice;
								}
							}
							
							//Deal product's reward point count :: End			
							$value = ($Rewardtotal * $rewardsql[0]['fcharge'])/$rewardsql[0]['forderamount'];
							$Rewardpoint = number_format($value, 0, '.', '');
							
							if($RewardtotalNext>$DealTotalprice && !empty($Rewardchk_arr))
							$Rewardpoint = $Rewardpoint+$DealRewardpoint; 
							else
							$Rewardpoint = $DealRewardpoint; 
							
							if($Rewardpoint>0) {
								$sql_client = "SELECT iRewardpoint FROM `".TABLE_PREFIX."customer` WHERE `customer_id` = '".$OrderRS[0]['customer_id']."' LIMIT 0,1";
								$res_client = $obj->select($sql_client);
								
								$FinalRewardpoint = $Rewardpoint + $res_client[0]['iRewardpoint'];
								
								$upgCustomer = array (
								'iRewardpoint' => $FinalRewardpoint
								);
								$udpRefer = $obj->update(TABLE_PREFIX.'customer', $upgCustomer, " `customer_id` = '".$OrderRS[0]['customer_id']. "' ");									
								
								$InsertCustomer = array (
								'customer_id' 	=> $OrderRS[0]['customer_id'],
								'note'		  	=> "Reward Point Added By Phone Order",
								'iRewardpoint'	=> $Rewardpoint,
								'Order_No'		=> $OrderRS[0]["orders_no"]
								);
								
								$obj->insert(TABLE_PREFIX.'reward_point', $InsertCustomer);
							}
						}
					}
					
					$cust_query = "SELECT referenced_by,email FROM `".TABLE_PREFIX."customer` WHERE `customer_id`='".$OrderRS[0]['customer_id']."' AND registration_type='M' AND status='1'";
					$cust_res 	= $obj->select($cust_query);
					
					//$Remail = $cust_res[0]['email'];
					$referenced_by = "";
					if(count($cust_res)>0 )
					{ 
						$referenced_by = $cust_res[0]['referenced_by']; 
						$new_str_arr = explode('#', $referenced_by);
						$id = $new_str_arr[0];
						$Remail =  $new_str_arr[1];		
					}
					
					if($referenced_by!='')
					{	
						$qry = "SELECT sender,is_sender_notified,receiver FROM `".TABLE_PREFIX."referfriend` WHERE `customer_id`='".$OrderRS[0]['customer_id']."' AND receiver = '".$Remail."' limit 0,1";
						$referralRes = $obj->select($qry);
						
						$datetime = date('Y-m-d H:i:s');
						
						if(count($referralRes)>0) 
						{
							//Condition For Adding Referral Point First Time When Refferal Client Clicks in Link and Updating Referrel Customer Status//
							if($referralRes[0]['is_sender_notified']=='N') 
							{
								/*$saveData['customer_id'] 		= $cust_id;
									$saveData['sender'] 		 	= $sender_email;       
								$saveData['receiver'] 		 	= $email;*/
								$saveData['is_sender_notified'] = 'Y';
								$saveData['refer_datetime']	 	= $datetime;       
								
								$where = "customer_id= '".$id."' AND receiver = '".$Remail."'";
								$referredId = $obj->update(TABLE_PREFIX.'referfriend', $saveData, $where);
								
								// Query For Updating Reward Point in Customer Table //
								$cust_qry = "SELECT iRewardpoint FROM `".TABLE_PREFIX."customer` WHERE `customer_id`='".$OrderRS[0]['customer_id']."'";
								$cust_res = $obj->select($cust_qry);
								
								$reward_point = $cust_res[0]['iRewardpoint']+100;
								$custdata['iRewardpoint'] = $reward_point;
								
								$where = "customer_id= '".$OrderRS[0]['customer_id']."'";
								$custId = $obj->update(TABLE_PREFIX.'customer', $custdata, $where);
								
								$InsertCustomer = array (
								'customer_id' 	=> $OrderRS[0]['customer_id'],
								'note'		  	=> "Reward Point For Adding Referral Point First Time",
								'iRewardpoint'	=> 100,
								'Order_No'		=> $OrderRS[0]["orders_no"]   // Change Order No 
								);
								
								$obj->insert(TABLE_PREFIX.'reward_point', $InsertCustomer);
							}
						}
					}
					
					
					#### Deduct product stock Start #####
					// if($OrderRS[0]['pay_status'] == 'Paid'){	
					if(!empty($OrderDetailRs)){
						$tot_pro = count($OrderDetailRs);
						
						for($i=0; $i < $tot_pro; $i++){
							$sql = "SELECT current_stock,cosmo_current_stock,cosmo_sku,nandansons_sku,nandansons_current_stock,perfumeworldwide_sku,pca_sku,perfumeworldwide_currentstock,pca_current_stock FROM ".TABLE_PREFIX."products WHERE status = '1' AND sku = '".$OrderDetailRs[$i]['sku']."'" ;
							$ProductSt = $obj->select($sql);
							
							if(count($ProductSt) <= 0 )
							{
								return NULL; // Not Found  Product
							}
							
							$new_stock=0;
							
							if($OrderDetailRs[$i]['IsCosmo']=="Yes" && $OrderDetailRs[$i]['VendorSKU']==$ProductSt[0]["cosmo_sku"])
							{
								if($ProductSt[0]["cosmo_current_stock"]>$OrderDetailRs[$i]['quantity'])
								{
									$new_stock = $ProductSt[0]["cosmo_current_stock"]-$OrderDetailRs[$i]['quantity'];
								}
								else if($OrderDetailRs[$i]['quantity']>$ProductSt[0]["cosmo_current_stock"])
								{
									$new_stock = $OrderDetailRs[$i]['quantity']-$ProductSt[0]["cosmo_current_stock"];
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}
								
								$UpdateStock = array (
								'cosmo_current_stock' => $new_stock
								);
							}
							else if($OrderDetailRs[$i]['IsNandansons']=="Yes" &&  $OrderDetailRs[$i]['VendorSKU']==$ProductSt[0]["nandansons_sku"])
							{
								if($ProductSt[0]["nandansons_current_stock"]>$OrderDetailRs[$i]['quantity'])
								{
									$new_stock = $ProductSt[0]["nandansons_current_stock"]-$OrderDetailRs[$i]['quantity'];
								}
								else if($OrderDetailRs[$i]['quantity']>$ProductSt[0]["nandansons_current_stock"])
								{
									$new_stock = $OrderDetailRs[$i]['quantity']-$ProductSt[0]["nandansons_current_stock"];
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}
								
								$UpdateStock = array (
								'nandansons_current_stock' => $new_stock
								);
							}
							else if($OrderDetailRs[$i]['IsPerfumePW']=="Yes" && $OrderDetailRs[$i]['VendorSKU']==$ProductSt[0]["perfumeworldwide_sku"])
							{
								if($ProductSt[0]["perfumeworldwide_currentstock"]>$OrderDetailRs[$i]['quantity'])
								{
									$new_stock = $ProductSt[0]["perfumeworldwide_currentstock"]-$OrderDetailRs[$i]['quantity'];
								}
								else if($OrderDetailRs[$i]['quantity']>$ProductSt[0]["perfumeworldwide_currentstock"])
								{
									$new_stock = $OrderDetailRs[$i]['quantity']-$ProductSt[0]["perfumeworldwide_currentstock"];
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}
								
								$UpdateStock = array (
								'perfumeworldwide_currentstock' => $new_stock
								);
							}
							else if($OrderDetailRs[$i]['IsPCA']=="Yes" && $OrderDetailRs[$i]['VendorSKU']==$ProductSt[0]["pca_sku"])
							{
								if($ProductSt[0]["pca_current_stock"]>$OrderDetailRs[$i]['quantity'])
								{
									$new_stock = $ProductSt[0]["pca_current_stock"]-$OrderDetailRs[$i]['quantity'];
								}
								else if($OrderDetailRs[$i]['quantity']>$ProductSt[0]["pca_current_stock"])
								{
									$new_stock = $OrderDetailRs[$i]['quantity']-$ProductSt[0]["pca_current_stock"];
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}
								
								$UpdateStock = array (
								'pca_current_stock' => $new_stock
								);
							}
							else
							{
								if($ProductSt[0]["current_stock"]>$OrderDetailRs[$i]['quantity'])
								{
									$new_stock = $ProductSt[0]["current_stock"]-$OrderDetailRs[$i]['quantity'];
								}
								else if($OrderDetailRs[$i]['quantity']>$ProductSt[0]["current_stock"])
								{
									$new_stock = $OrderDetailRs[$i]['quantity']-$ProductSt[0]["current_stock"];
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}
								
								$UpdateStock = array (
								'current_stock' => $new_stock
								);
							}
							
							$result = $obj->update(TABLE_PREFIX.'products', $UpdateStock, "sku ='".$OrderDetailRs[$i]['sku']."'") ;
						}
					}
					
					#### Deduct product stock End #####
					
					$STR_EMAIL_ITEM = '';
					$topmenubar = '<table cellpadding="0" cellspacing="0" width="100%" border="0" style="background-color:#2d2d2d;">
					<tr align="center">
					<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'fragrances/cid/1" style="color:#fff; text-decoration:none; padding:8px 0px; display:block; text-transform:uppercase;">Fragrances</a></td>
					<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'skincare/cid/18" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Skincare</a></td>
					<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'makeup/cid/30" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Makeup</a></td>
					<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'bath-body/cid/12" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Bath &amp; Body</a></td>
					<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'at-home/cid/15" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">At Home</a></td>
					<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'sunglasses/cid/68" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Sunglasses</a></td>
					<td><a href="'.$Site_URL.'perfumesale/p4u/special-sl/view" style="color:#fff; text-decoration:none; padding:5px; display:block;text-transform:uppercase;">Sale</a></td>
					</tr>
					</table>';
					
					//new
					$STR_EMAIL_ITEM .= '<table cellpadding="0" cellspacing="0" width="100%" border="0">
					<tr align="center" valign="top">
					<td style="background-color:#e5e5e5; padding:5px;"><strong>Gift Wrap</strong></td>
					<td style="background-color:#e5e5e5; padding:5px;"><strong>Images</strong></td>
					<td style="background-color:#e5e5e5; padding:5px;" align="left"><strong>Your Order Summary</strong></td>
					<td style="background-color:#e5e5e5; padding:5px;"><strong>Quantity</strong></td>
					<td style="background-color:#e5e5e5; padding:5px;" align="right"><strong>Price</strong></td>
					</tr>';
					
					for($n=0;$n<count($OrderDetailRs);$n++)
					{
						$thumb_image = $generalobj->getItemThumb($OrderDetailRs[$n]['sku']);
						
						$checked = '';
						if($OrderDetailRs[$n]['is_gift_wrap']=='Yes')
						{ $checked = 'checked="checked" '; }
						
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td valign="middle" style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><input type="checkbox"  disabled="disabled" '.$checked.' /></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;">'.$thumb_image.'</a></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="left"><p style="color:#cc3399; margin:0px;"><strong>'.$OrderDetailRs[$n]['product_name'].'</strong></p><p>SKU:'.$OrderDetailRs[$n]['sku'].'</p>';
						
						
						$STR_EMAIL_ITEM .= '</td>';
						$STR_EMAIL_ITEM .= '<td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><strong>'.$OrderDetailRs[$n]['quantity'].'</strong></td>
						<td style="padding:10px 5px; border-bottom:1px solid #e8e8e8;" align="right"><strong>$'.$OrderDetailRs[$n]['price'].'</strong></td>
						</tr>';		
						
						$TotalProducts = (int)$TotalProducts + (int)$OrderDetailRs[$n]['quantity'];
					}
					
					if($OrderDetailRs[$n]['is_gift_wrap']=='Yes')
					{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Gift Wrap:</strong></td><td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">Yes</td></tr>';
					}
					
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong> Total item purchased:</strong></td>
					<td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">'.$TotalProducts.'</td>
					</tr>';
					
					
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Subtotal:</td>
					<td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['sub_total'].'</td>
					</tr>';
					
					
					if($OrderRS[0]["shipping_amt"]>0)
					{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Charge:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['shipping_amt'].'</td></tr>';
					}
					
					if($OrderRS[0]["tax"]>0)
					{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Sales Tax:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['tax'].'</td></tr>';
					}
					
					
					if($OrderRS[0]["gift_charge"]>0)
					{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Wrap Charge :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['gift_charge'].'</td></tr>';
					}
					
					if($OrderRS[0]["auto_discount"]>0)
					{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Auto Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['auto_discount'].'</td></tr>';
					}
					
					if($OrderRS[0]["quantity_discount"]>0)
					{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Quantity Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['quantity_discount'].'</td></tr>';
					}
					
					if($OrderRS[0]["coupon_amount"]>0)
					{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Coupon Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['coupon_amount'].'</td></tr>';
					}
					
					if($OrderRS[0]["gc_amount"]>0)
					{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Certificate Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['gc_amount'].'</td></tr>';
					}
					
					if($OrderRS[0]["reward_discount"]>0)
					{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Reward Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['reward_discount'].'</td></tr>';
					}
					
					if($OrderRS[0]["refer_amount"]>0)
					{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">'.$AUTO_REFER_DISCOUNT.' :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['refer_amount'].'</td></tr>';
					}
					
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Order Total:</strong></td>
					<td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right"><strong>$'.$OrderRS[0]['order_total'].'</strong></td>
					</tr>';
					$STR_EMAIL_ITEM .= '</table>';
					
					$mres = $generalobj->Get_Mail_Template("ORDER_RECEIPT");
					$mail_content = stripslashes($mres[0]["mail_body"]);
					
					//new
					$freeshippinginfo = '';
					if(FREESHIPPING_VALUE!="")
					{
						$freeshippinginfo .= '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.FREESHIPPING_VALUE.' or more Orders</span>';
					}
					
					$mail_content = str_replace('{$freeshippinginfo}', $freeshippinginfo, $mail_content);
					$mail_content = str_replace('{$topmenubar}', $topmenubar, $mail_content);
					$mail_content = str_replace('{$ordereddate}', date("d F, Y",strtotime($OrderRS[0]['order_datetime'])), $mail_content);
					$mail_content = str_replace('{$ordertotal}', $OrderRS[0]['order_total'], $mail_content);
					$mail_content = str_replace('{$shipinfo}', $OrderRS[0]['shipinfo'], $mail_content);
					$mail_content = str_replace('{$CONTACT_MAIL}', CONTACT_MAIL, $mail_content);
					
					$sql_banner_na = "SELECT * FROM ".TABLE_PREFIX."mail_banner where mail_banner_id = 1 and status = '1'";
					$row_banner_na = $obj->select($sql_banner_na);
					$banner_img_new_arrival = MAIL_BANNERS_URL.$row_banner_na[0]['mail_banner_image'].".jpg";
					$banner_new_arrival_link = $row_banner_na[0]['mail_banner_link'];
					
					$sql_banner_da = "SELECT * FROM ".TABLE_PREFIX."mail_banner where mail_banner_id = 2 and status = '1'";
					$row_banner_da = $obj->select($sql_banner_da);
					$banner_img_dailyaroma = MAIL_BANNERS_URL.$row_banner_da[0]['mail_banner_image'].".jpg";	
					$banner_dailyaroma_link = $row_banner_da[0]['mail_banner_link'];
					
					$Addblock = '';
					if(count($row_banner_na)>0 || count($row_banner_da)>0)
					{
						$Addblock .= '<td width="3%">&nbsp;</td><td width="27%" valign="top"><table cellpadding="0" cellspacing="0" width="100%" border="0">';
						
						if(count($row_banner_na)>0)
						{
							$Addblock .= '<tr><td align="center" style="padding:5px;border:1px solid #e8e8e8;"><a href="'.$banner_new_arrival_link.'"><img src="'.$banner_img_new_arrival.'" alt="" /></a></td></tr>';
						}
						
						if(count($row_banner_da)>0)
						{
							$Addblock .= '<tr><td align="center" style="padding:5px;border:1px solid #e8e8e8; border-top:0px;"><a href="'.$banner_dailyaroma_link.'"><img src="'.$banner_img_dailyaroma.'" alt="" /></a></td></tr>';
						}
						
						$Addblock .= '</table></td>';
					}
					
					$mail_content = str_replace('{$Addblock}', $Addblock, $mail_content);
					$mail_content = str_replace('{$OrderRs.orders_no}', $OrderRS[0]['orders_no'], $mail_content);		
					$mail_content = str_replace('{$OrderRs.order_datetime|date_format}', date("d F, Y",strtotime($OrderRS[0]['order_datetime'])), $mail_content);	
					$mail_content = str_replace('{$OrderRs.order_total|price}', $OrderRS[0]['order_total'], $mail_content);		
					$mail_content = str_replace('{if $OrderRs.bill_address2!=""}', "", $mail_content);
					$mail_content = str_replace('{if $OrderRs.ship_address2!=""}', "", $mail_content);
					$mail_content = str_replace(', {/if}', "", $mail_content);
					//new
					
					$mail_content = str_replace('{$OrderRs.orders_id}', $OrderRS[0]['orders_id'], $mail_content);
					$mail_content = str_replace('{$OrderRs.bill_first_name}', $OrderRS[0]['bill_first_name'], $mail_content);		
					$mail_content = str_replace('{$OrderRs.bill_last_name}',  $OrderRS[0]['bill_last_name'], $mail_content);	
					$mail_content = str_replace('{$OrderRs.bill_last_name}',  $OrderRS[0]['orders_id'], $mail_content);	
					$mail_content = str_replace('{$OrderRs.status}',  $OrderRS[0]['status'], $mail_content);	
					$mail_content = str_replace('{if $OrderRs.is_only_gc == 0}', '', $mail_content);
					$mail_content = str_replace('{/if}', '', $mail_content);
					
					$mail_content = str_replace('{$OrderRs.bill_address1}',  $OrderRS[0]['bill_address1'], $mail_content);
					$mail_content = str_replace('{$OrderRs.bill_address2}',  $OrderRS[0]['bill_address2'], $mail_content);
					$mail_content = str_replace('{$OrderRs.bill_city}',  $OrderRS[0]['bill_city'], $mail_content);
					$mail_content = str_replace('{$OrderRs.bill_state}',  $OrderRS[0]['bill_state'], $mail_content);
					$mail_content = str_replace('{$OrderRs.bill_zip}',  $OrderRS[0]['bill_zip'], $mail_content);	
					$mail_content = str_replace('{$OrderRs.bill_country}',  $OrderRS[0]['bill_country'], $mail_content);
					$mail_content = str_replace('{$OrderRs.bill_phone}',  $OrderRS[0]['bill_phone'], $mail_content);
					$mail_content = str_replace('{$OrderRs.bill_email}',  $OrderRS[0]['bill_email'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_first_name}',  $OrderRS[0]['ship_first_name'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_last_name}',  $OrderRS[0]['ship_last_name'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_address1}',  $OrderRS[0]['ship_address1'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_address2}',  $OrderRS[0]['ship_address2'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_city}',  $OrderRS[0]['ship_city'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_state}',  $OrderRS[0]['ship_state'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_zip}',  $OrderRS[0]['ship_zip'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_country}',  $OrderRS[0]['ship_country'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_phone}',  $OrderRS[0]['ship_phone'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_email}',  $OrderRS[0]['ship_email'], $mail_content);
					$mail_content = str_replace('{$OrderRs.ship_email}',  $OrderRS[0]['ship_email'], $mail_content);
					$mail_content = str_replace('{$STR_EMAIL_ITEM}',  $STR_EMAIL_ITEM, $mail_content);
					$mail_content = str_replace('{$TOLL_FREE_NO}', CONTACT_PHONE_NO, $mail_content);
					$mail_content = str_replace('{$Site_URL}', $Site_URL, $mail_content);
					$mail_content = str_replace('{$SITE_NAME}', SITE_TITLE, $mail_content);
					
					$mail_subject = str_replace('{$SITE_NAME}', SITE_TITLE, $mres[0]['subject']);
					$mail_subject = str_replace('{$OrderRs.orders_no}', $OrderRS[0]['orders_no'], $mail_subject);
					
					//$onesendstat = $generalobj->SMTP_Mail_Send($OrderRS[0]['bill_email'],$mail_subject, $mail_content, CONTACT_MAIL);
					
					//$OrderRS[0]['bill_email']  = "qqualdev@gmail.com";
					$onesendstat = SmartyEmail($mail_subject, $mail_content, $OrderRS[0]['bill_email'], CONTACT_MAIL);
				}
				############################# Complete Other Related processes End #########################################
				


			$_SESSION["phoneorder_detail"]["Afterpay"] = array();
			unset($_SESSION["phoneorder_detail"]["Afterpay"]);
			
			$err_msg = "Thank you for your payment. Your order will be processed as soon as possible. An Order Receipt E-mail has been sent to you.";
			$commonobj->setDisplayMessage($err_msg);
				
			header("location:".SITE_URL."payment/".base64_encode($order_id)."/".base64_encode("1"));			
			exit;
		}else{
			$Message = "Error in Processing Request, Please try again.";
			if(isset($capture_arr['errorId'])){
				$Message = $capture_arr['message'];
			}
			$commonobj->setDisplayMessage($Message);
			
			$_SESSION["phoneorder_detail"]["Afterpay"] = array();
			unset($_SESSION["phoneorder_detail"]["Afterpay"]);
			
			header("location:".$Site_URL."payment/".base64_encode($order_id)."/".base64_encode("0"));
			exit;
		}
	}else{
		//order order not confirmed by customer
		//status >> CANCELLED
		
		$err_msg = "Error in Processing Request, Please try again.";
		$commonobj->setDisplayMessage($err_msg);

		header("location:".$Site_URL."payment/".base64_encode($order_id)."/".base64_encode("0"));
		exit;
	}
?>

