<?php 
 include("lib/configuration.php");
 ini_set('error_reporting', E_WARNING);
 $username = $_REQUEST['SS-UserName'];
 $password = $_REQUEST['SS-Password'];
 $orders_id = $_REQUEST['orders_id'];
 
 define("TABLE_PREFIX","pu_");
 
 $fp1 = fopen('shipstation.txt', 'a+');
 fwrite($fp1, serialize($_REQUEST)."\n\r");
 fclose($fp1);
 
  ini_set('display_errors',0);
  DEFINE(__ENCODE_RESPONSE, true);

  DEFINE(__DEBUG, 1);
  DEFINE(__LANGUAGE_ID, 1);
  DEFINE(__DELIVERED_STATUS_ID, 3);
  DEFINE(__DELIVERED_STATUS_NAME, 'Delivered');
  
  DEFINE(__PROCESSING_STATUS_ID, 2);
  DEFINE(__PROCESSING_STATUS_NAME, 'Processing');
 
  $db_host = 'localhost';
  $db_username = 'maxaromacpanel_maxaroma';
  $db_password = 'P4p!{9P0S)di';
  $db_name = 'maxaromacpanel_maxaroma';

  $PHP_SELF = (isset($HTTP_SERVER_VARS['PHP_SELF']) ? $HTTP_SERVER_VARS['PHP_SELF'] : $HTTP_SERVER_VARS['SCRIPT_NAME']);
  $conn =  mysqli_connect($db_host, $db_username, $db_password) or die('Unable to connect to database server!');
  mysqli_select_db($conn,$db_name) or die('Unable to select database!');
  
  $request = trim(stripcslashes($request));
  
  if (__DEBUG) _log($request, __line__);
  
  $xmlRequest = new xml_doc($request);
  if (__DEBUG){
    ob_start();
    print("(xml parse error):");
    $xmlRequest->parse();
    _log(ob_get_contents(), __line__);
    @ob_end_clean();
  }
  
  $xmlRequest->getTag(0, $_tagName, $_tagAttributes, $_tagContents, $_tagTags);
  
  $xmlResponse = new xml_doc('<?xml version="1.0" encoding="UTF-8"?>');
  $root = '';
 
 $extra_sql = '';
 $order_by = '';
 if(isset($_REQUEST['start_date']) && $_REQUEST['start_date']!= "" && isset($_REQUEST['end_date']) && $_REQUEST['end_date']!= ""){	
		$start_date = str_replace("/","-",urldecode($_REQUEST['start_date']));
		 $end_date = str_replace("/","-",urldecode($_REQUEST['end_date']));
		 
		 $fdate = DateTime::createFromFormat('m-d-Y H:i',$start_date);
		 $fromdate = $fdate->format('Y-m-d');
		 
		 $tdate = DateTime::createFromFormat('m-d-Y H:i',$end_date);
		 $todate = $tdate->format('Y-m-d');
		 
		 $extra_sql .= " AND ((DATE_FORMAT(order_datetime,'%Y-%m-%d')>='$fromdate' AND DATE_FORMAT(order_datetime,'%Y-%m-%d')<='$todate') OR (phoneorder_paymentdate!='0000-00-00 00:00:00' AND DATE_FORMAT(phoneorder_paymentdate,'%Y-%m-%d')>='$fromdate' AND DATE_FORMAT(phoneorder_paymentdate,'%Y-%m-%d')<='$todate')) ";		
		 $order_by .= "order by orders_id DESC";
 }
 
if($orders_id > 0 && $orders_id!='')
{
	$extra_sql .= " AND orders_id='".$orders_id."' ";
	$order_by .= "order by orders_id DESC";
}
 
if($_REQUEST['action'] == 'export')
{	
	$start=0;
	$limit=10;
	
	if(isset($_REQUEST['page']))
	{
		$page=$_REQUEST['page'];
		$start=($page-1)*$limit;
	}
	$rows=mysqli_num_rows(mysqli_query($conn,"SELECT * FROM `".TABLE_PREFIX."orders` WHERE 1 AND status != 'Declined' AND status!='Sent To Stripe' AND status!='Pending - PhoneOrder' AND status!='Sent To AfterPay' AND (is_only_gc='0' || is_only_gc='') AND pay_status='Paid' $extra_sql $order_by"));
	
	$totalpages=ceil($rows/$limit);
	
	if($rows<=0){
		$xmlResponse->createTag("NoOrders", array(), 'No Orders Found', $root);
	}		
}

  switch ($_REQUEST['action']){
    
    case 'export' : {

      $ordersNode = $xmlResponse->createTag("Orders", array('pages'=>$totalpages), '', $root);
      $orders_query_raw = "SELECT *          
        FROM `".TABLE_PREFIX."orders`
        WHERE 1 AND
        status != 'Declined' 
		AND status!='Sent To Stripe'
		AND status!='Pending - PhoneOrder'
		AND status!='Sent To AfterPay'
        AND (is_only_gc='0' || is_only_gc='')
        AND pay_status='Paid'
        $extra_sql
        $order_by LIMIT $start, $limit";
	  
      $orders_query =mysqli_query($conn,$orders_query_raw);
      while ($orders = mysqli_fetch_array($orders_query)) {
		$orders["ship_address1"] = utf8_encode($orders["ship_address1"]);  
        $oInfo = parseSpecCharsA($orders);
			
			$dorderdate = date("m/d/Y h:i A",strtotime($oInfo->order_datetime));
			
			$dlastmodified = "00/00/0000 00:00 PM";
			if(trim($oInfo->order_upd_datetime)!="0000-00-00 00:00:00")
				$dlastmodified = date("m/d/Y h:i A",$oInfo->order_upd_datetime);			
				
		$orderNode  = $xmlResponse->createTag("Order",  array(), '', $ordersNode);
			
			$xmlResponse->createTag("OrderID",  array(), '<![CDATA['.$oInfo->orders_id.']]>',     $orderNode);
			$xmlResponse->createTag("OrderNumber",  array(), '<![CDATA['.$oInfo->orders_no.']]>',     $orderNode);
			$xmlResponse->createTag("OrderDate",  array(), $dorderdate,     $orderNode);
			$xmlResponse->createTag("OrderStatus",  array(), '<![CDATA['.$oInfo->status.']]>',     $orderNode);
			$xmlResponse->createTag("LastModified",  array(), $dlastmodified,     $orderNode);
			$xmlResponse->createTag("ShippingMethod",  array(), '<![CDATA['.$oInfo->shipinfo.']]>',     $orderNode);
			if($oInfo->customer_comment!='')
			{
				$xmlResponse->createTag("CustomerNotes",  array(), '<![CDATA['.$oInfo->customer_comment.']]>',     $orderNode);
			}
			$xmlResponse->createTag("PaymentMethod",  array(), '<![CDATA['.$oInfo->payment_method.']]>',     $orderNode);
			$xmlResponse->createTag("OrderTotal",  array(), $oInfo->order_total,     $orderNode);
			$xmlResponse->createTag("TaxAmount",  array(), $oInfo->tax,     $orderNode);
			$xmlResponse->createTag("ShippingAmount",  array(), $oInfo->shipping_amt,     $orderNode);
			
			if($oInfo->Is3DSecure=='Yes')
			{
				$xmlResponse->createTag("Source",  array(), '3D Secure - Stripe',     $orderNode);
			}
			else if($oInfo->payment_method=='Credit Card' && $oInfo->Is3DSecure=='No')
			{
				$xmlResponse->createTag("Source",  array(), 'Credit Card - Stripe',     $orderNode);
			}else if($oInfo->payment_method=='Paypal Express Checkout' || $oInfo->payment_method=='Pay With Amazon')
			{
				$xmlResponse->createTag("Source",  array(), 'Paypal / Amazon',     $orderNode);
			}
			else if($oInfo->payment_method=='Dropshipper Fund')
			{
				$xmlResponse->createTag("Source",  array(), 'Dropshipper orders',     $orderNode);
			}
			else if($oInfo->payment_method=='Pay With Afterpay')
			{
				$xmlResponse->createTag("Source",  array(), 'Afterpay orders',     $orderNode);
			}
			else if($oInfo->is_maxtwoday=='Yes')
			{
				$xmlResponse->createTag("Source",  array(), 'FEDEX 2nd day',     $orderNode);
			}
			$cust_query_raw = mysqli_query($conn,"select * from ".TABLE_PREFIX."customer where customer_id = ".$oInfo->customer_id);
			$cust = mysqli_fetch_array($cust_query_raw);
			$cInfo = parseSpecCharsA($cust);
			 
			$CustomerNode  = $xmlResponse->createTag("Customer",  array(), '', $orderNode);				
				
				$xmlResponse->createTag("CustomerCode",  array(), '<![CDATA['.$cInfo->email.']]>',     $CustomerNode);
				
			$BillNode = $xmlResponse->createTag("BillTo",  array(), '', $CustomerNode);				
				
				$xmlResponse->createTag("Name",  array(), '<![CDATA['.$oInfo->bill_first_name.' '.$oInfo->bill_last_name.']]>',     $BillNode);
				$xmlResponse->createTag("Company",  array(), '<![CDATA['.$oInfo->bill_company.']]>',     $BillNode);
				$xmlResponse->createTag("Phone",  array(), '<![CDATA['.$oInfo->bill_phone.']]>',     $BillNode);
				$xmlResponse->createTag("Email",  array(), '<![CDATA['.$oInfo->bill_email.']]>',     $BillNode);
			
			$ShipNode = $xmlResponse->createTag("ShipTo",  array(), '', $CustomerNode);
				
				if($oInfo->ship_country == ''){
					
					$shortname_state = array('AL','AK','AS','AZ','AR','CA','CO','CT','DE','DC','FL','GA','GU','HI','ID','IL','IN','IA','KS','KY','LA','ME','MH','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PW','PA','PR','RI','SC','SD','TN','TX','UT','VT','VI','VA','WA','WV','WI','WY');
					if (in_array($oInfo->ship_state, $shortname_state)){
						$oInfo->ship_country = "US";
					}else{
						$se1 = fopen('shipstation_eroor.txt', 'a+');
						fwrite($se1, date("l jS \of F Y h:i:s A")."\n\r========================\n\r".$oInfo->orders_id."\n\r");
						fclose($se1);
					}
				}
				
				$xmlResponse->createTag("Name",  array(), '<![CDATA['.$oInfo->ship_first_name.' '.$oInfo->ship_last_name.']]>',     $ShipNode);
				$xmlResponse->createTag("Company",  array(), '<![CDATA['.$oInfo->ship_company.']]>',     $ShipNode);
				$xmlResponse->createTag("Address1",  array(), '<![CDATA['.$oInfo->ship_address1.']]>',     $ShipNode);
				$xmlResponse->createTag("Address2",  array(), '<![CDATA['.$oInfo->ship_address2.']]>',     $ShipNode);
				$xmlResponse->createTag("City",  array(), '<![CDATA['.$oInfo->ship_city.']]>',     $ShipNode);
				$xmlResponse->createTag("State",  array(), '<![CDATA['.$oInfo->ship_state.']]>',     $ShipNode);
				$xmlResponse->createTag("PostalCode",  array(), '<![CDATA['.$oInfo->ship_zip.']]>',     $ShipNode);
				$xmlResponse->createTag("Country",  array(),  '<![CDATA['.$oInfo->ship_country.']]>',     $ShipNode);
				$xmlResponse->createTag("Phone",  array(), '<![CDATA['.$oInfo->ship_phone.']]>',     $ShipNode);
			
			$ItemsNode = $xmlResponse->createTag("Items",  array(), '', $orderNode);
				
				$db_dtl2 = mysqli_query($conn,"select * from ".TABLE_PREFIX."order_detail where orders_id =".$oInfo->orders_id);
				$giftSku = '';
				while($items = parseSpecCharsA(mysqli_fetch_array($db_dtl2))){
						
						$price_show=$items->price; 
						
						if($oInfo->orders_id > 13445)
						{
							$products = mysqli_query($conn,"select * from ".TABLE_PREFIX."products where 	products_id = ".$items->products_id);
						}
						else
						{	
							$products = mysqli_query($conn,"select * from ".TABLE_PREFIX."products where 	products_id = ".$items->products_id);
							
						}
						
						$rowproduct=parseSpecCharsA(mysqli_fetch_array($products));
						
						$ItemNode = $xmlResponse->createTag("Item",  array(), '',     $ItemsNode);
							
							$imgname = stripslashes($rowproduct->image);
							
							if(file_exists(PRD_MEDIUM_IMG_PATH.stripslashes($imgname)) and !empty($imgname))
									{$thumb_image = PRD_MEDIUM_IMG_URL.rawurlencode(stripslashes($imgname));}
							else
									{$thumb_image = NO_IMAGE_MEDIUM;}
							$prodimage = $thumb_image;
							
							$weight = 0;
						
							$newname = $rowproduct->product_name." ".$rowproduct->short_description;
							if(strlen($newname) > 200)
							{
								$newname =substr($newname,0,195)."...";
							}
							$newname = stripslashes(trim(utf8_encode($newname)));
							$newname = html_entity_decode(strip_tags($newname));
							
							
							if($items->is_gift_wrap == "Yes")
							{
								$giftSku.= $rowproduct->sku.", ";
							}														
							
							$xmlResponse->createTag("SKU",  array(), '<![CDATA['.$rowproduct->sku.']]>',     $ItemNode);
							$xmlResponse->createTag("Name",  array(), '<![CDATA['.$newname.']]>',     $ItemNode);
							$xmlResponse->createTag("ImageUrl",  array(), '<![CDATA['.$prodimage.']]>',     $ItemNode);
							$xmlResponse->createTag("Weight",  array(), '<![CDATA['.$weight.']]>',     $ItemNode);
							$xmlResponse->createTag("Quantity",  array(), $items->quantity,     $ItemNode);
							$xmlResponse->createTag("UnitPrice",  array(), $price_show,     $ItemNode);
				}
				$giftSku = substr(trim($giftSku),0,-1);
				
				if(trim($oInfo->auto_discount) != "0.00" && trim($oInfo->auto_discount) != "")
				{
					$ItemNode1 = $xmlResponse->createTag("Item",  array(), '',     $ItemsNode);				
						$xmlResponse->createTag("SKU",  array(), '<![CDATA[Auto Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Name",  array(), '<![CDATA[Auto Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Adjustment",  array(), '<![CDATA[true]]>',     $ItemNode1);
						$xmlResponse->createTag("Quantity",  array(), '<![CDATA[1]]>',     $ItemNode1);
						$xmlResponse->createTag("UnitPrice",  array(), '<![CDATA['.$oInfo->auto_discount.']]>',     $ItemNode1);
				}
				
				if(trim($oInfo->quantity_discount) != "0.00" && trim($oInfo->quantity_discount) != "")
				{
					$ItemNode1 = $xmlResponse->createTag("Item",  array(), '',     $ItemsNode);				
						$xmlResponse->createTag("SKU",  array(), '<![CDATA[Quantity Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Name",  array(), '<![CDATA[Quantity Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Adjustment",  array(), '<![CDATA[true]]>',     $ItemNode1);
						$xmlResponse->createTag("Quantity",  array(), '<![CDATA[1]]>',     $ItemNode1);
						$xmlResponse->createTag("UnitPrice",  array(), '<![CDATA['.$oInfo->quantity_discount.']]>',     $ItemNode1);
				}
				
				if(trim($oInfo->coupon_amount) != "0.00" && trim($oInfo->coupon_amount) != "")
				{
					$ItemNode1 = $xmlResponse->createTag("Item",  array(), '',     $ItemsNode);				
						$xmlResponse->createTag("SKU",  array(), '<![CDATA[Coupon Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Name",  array(), '<![CDATA[Coupon Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Adjustment",  array(), '<![CDATA[true]]>',     $ItemNode1);
						$xmlResponse->createTag("Quantity",  array(), '<![CDATA[1]]>',     $ItemNode1);
						$xmlResponse->createTag("UnitPrice",  array(), '<![CDATA['.$oInfo->coupon_amount.']]>',     $ItemNode1);
				}
				
				if(trim($oInfo->gc_amount) != "0.00" && trim($oInfo->gc_amount) != "")
				{
					$ItemNode1 = $xmlResponse->createTag("Item",  array(), '',     $ItemsNode);				
						$xmlResponse->createTag("SKU",  array(), '<![CDATA[Gift Certificate Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Name",  array(), '<![CDATA[Gift Certificate Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Adjustment",  array(), '<![CDATA[true]]>',     $ItemNode1);
						$xmlResponse->createTag("Quantity",  array(), '<![CDATA[1]]>',     $ItemNode1);
						$xmlResponse->createTag("UnitPrice",  array(), '<![CDATA['.$oInfo->gc_amount.']]>',     $ItemNode1);
				}
				if(trim($oInfo->bogo_discount) != "0.00" && trim($oInfo->bogo_discount) != "")
				{
					$ItemNode1 = $xmlResponse->createTag("Item",  array(), '',     $ItemsNode);				
						$xmlResponse->createTag("SKU",  array(), '<![CDATA[Bogo Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Name",  array(), '<![CDATA[Bogo Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Adjustment",  array(), '<![CDATA[true]]>',     $ItemNode1);
						$xmlResponse->createTag("Quantity",  array(), '<![CDATA[1]]>',     $ItemNode1);
						$xmlResponse->createTag("UnitPrice",  array(), '<![CDATA['.$oInfo->bogo_discount.']]>',     $ItemNode1);
				}
				
				if(trim($oInfo->reward_discount) != "0.00" && trim($oInfo->reward_discount) != "")
				{
					$ItemNode1 = $xmlResponse->createTag("Item",  array(), '',     $ItemsNode);				
						$xmlResponse->createTag("SKU",  array(), '<![CDATA[Reward Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Name",  array(), '<![CDATA[Reward Discount]]>',     $ItemNode1);
						$xmlResponse->createTag("Adjustment",  array(), '<![CDATA[true]]>',     $ItemNode1);
						$xmlResponse->createTag("Quantity",  array(), '<![CDATA[1]]>',     $ItemNode1);
						$xmlResponse->createTag("UnitPrice",  array(), '<![CDATA['.$oInfo->reward_discount.']]>',     $ItemNode1);
				}
				if(trim($oInfo->gift_charge) > 0 && trim($oInfo->gift_charge) != "")
				{
					    $ItemNode1 = $xmlResponse->createTag("Item",  array(), '',     $ItemsNode);				
						$xmlResponse->createTag("SKU",  array(), '<![CDATA[Gift Wrap - '.$giftSku.']]>',     $ItemNode1);
						$xmlResponse->createTag("Name",  array(), '<![CDATA[Gift Wrap]]>',     $ItemNode1);
						$xmlResponse->createTag("Quantity",  array(), '<![CDATA[1]]>',     $ItemNode1);
						$xmlResponse->createTag("UnitPrice",  array(), '<![CDATA['.$oInfo->gift_charge.']]>',     $ItemNode1);
				}
				if((trim($oInfo->shipping_signature) > 0 && trim($oInfo->shipping_signature) != "") || $oInfo->is_shipping_signature=="Yes")
				{
					    $ItemNode1 = $xmlResponse->createTag("Item",  array(), '',     $ItemsNode);				
						$xmlResponse->createTag("SKU",  array(), '<![CDATA[Shipping Signature]]>',     $ItemNode1);
						$xmlResponse->createTag("Name",  array(), '<![CDATA[Shipping Signature]]>',     $ItemNode1);
						$xmlResponse->createTag("Quantity",  array(), '<![CDATA[1]]>',     $ItemNode1);
						$xmlResponse->createTag("UnitPrice",  array(), '<![CDATA['.$oInfo->shipping_signature.']]>',     $ItemNode1);
				}
				if(trim($oInfo->route_shipping_insurance_charge) > 0 && trim($oInfo->route_shipping_insurance_charge) != "")
				{
					    $ItemNode1 = $xmlResponse->createTag("Item",  array(), '',     $ItemsNode);				
						$xmlResponse->createTag("SKU",  array(), '<![CDATA[Shipsurance Charge]]>',     $ItemNode1);
						$xmlResponse->createTag("Name",  array(), '<![CDATA[Shipsurance Charge]]>',     $ItemNode1);
						$xmlResponse->createTag("Quantity",  array(), '<![CDATA[1]]>',     $ItemNode1);
						$xmlResponse->createTag("UnitPrice",  array(), '<![CDATA['.$oInfo->route_shipping_insurance_charge.']]>',     $ItemNode1);
				}
			$sql = "UPDATE `".TABLE_PREFIX."orders` SET is_shipstaion_sent='Yes' WHERE orders_id='".$oInfo->orders_id."'";
			$updateQryVl =mysqli_query($conn,$sql);
      }
      
    } break;  
    
    case 'shipnotify' : {     
	 
	 $carrier = $_REQUEST['carrier'];
	 $trackingnumber = $_REQUEST['tracking_number'];	 
	 $ordernumber = $_REQUEST['order_number'];	 
	 	  
     $shipnotify_query_raw = "SELECT * FROM `".TABLE_PREFIX."orders` WHERE orders_no = '$ordernumber' AND (is_only_gc='0' || is_only_gc='')";
	  
      $shipnotify_query = mysqli_query($conn,$shipnotify_query_raw);
	  $shipnotify = parseSpecCharsA(mysqli_fetch_array($shipnotify_query));
      		
			$s_date = date("Y-m-d");
			
			 $fp = fopen('shipnotify.txt', 'a+');
			 $qry = "update ".TABLE_PREFIX."order_detail set dtl_ship_method = '$carrier',dtl_tracking_no = '$trackingnumber',dtl_ship_status='Shipped',dtl_ship_date = '$s_date',ostatus='Completed' where orders_id = ".$shipnotify->orders_id; 
			 fwrite($fp, $qry.' \n\r');
			 fwrite($fp, "REQUEST : ".serialize($_REQUEST)."\n\r");
			 fwrite($fp, "GET : ".serialize($_GET)."\n\r");
			 fclose($fp);
			 
			 mysqli_query($conn,"update ".TABLE_PREFIX."order_detail set dtl_ship_method = '".$carrier."',dtl_tracking_no = '$trackingnumber',dtl_ship_status='Shipped',dtl_ship_date = '$s_date',ostatus='Completed' where orders_id = ".$shipnotify->orders_id);
			 
			 mysqli_query($conn,"update ".TABLE_PREFIX."orders set status = 'Completed',tracking_no = '".$trackingnumber."',ship_status = 'Shipped',ship_method = '".$carrier."',ship_date = '".$s_date."' where orders_id = ".$shipnotify->orders_id);
			 if(OMNISEND_PROGRAM==1)
             {
                    $BaseFileName = __FILE__;
                    $orders_id = $shipnotify->orders_id;
                    $omnisend_event = 'update_order_status';
                    include(SITE_DIR."omnisend/omnisend_integration.php");
             }
            $custInfo = mysqli_query($conn,"select eusertype from ".TABLE_PREFIX."customer where customer_id = ".$shipnotify->customer_id);
			$OrderCustomer = parseSpecCharsA(mysqli_fetch_array($custInfo));
        
            if(YOTPO_PROGRAM == 1 && $OrderCustomer->eusertype == 'Retailer')
            {
                $sql_chk = "SELECT * FROM ".TABLE_PREFIX."orders WHERE orders_id ='".$shipnotify->orders_id."'";
                $order_res_chk	= $obj->select($sql_chk);
		
                $sql = "SELECT * FROM ".TABLE_PREFIX."order_detail WHERE orders_id='".$shipnotify->orders_id."'";
                $allOrderItems 	= $obj->select($sql);
                $yotpo_items = array();
                for($p=0;$p<count($allOrderItems);$p++)
                {
                    $orderItem = $allOrderItems[$p];
                    $yotpo_items[$p] = array(
                        'id' => $orderItem["products_id"],
                        'name' => (string) utf8_encode(strip_tags($orderItem["product_name"])),
                        'quantity' => $orderItem['quantity'],
                        'price_cents' => ($orderItem['price'] * 100),
                    );

                }
                $yotpo_event = "completeOrder";
                include(SITE_DIR."yotpo/yotpo_integration.php");
            }
        
			 $action = $_REQUEST['action'];
			 $order_number = $_REQUEST['order_number'];
			 $carrier = $_REQUEST['carrier'];
			 $service = $_REQUEST['service'];
			 $tracking_number = $_REQUEST['tracking_number'];
			 $date = date("Y-m-d");
			 mysqli_query($conn,'insert into '.TABLE_PREFIX.'shipstation_notifications(action,order_number,carrier,service,tracking_number,date) values("'.$action.'","'.$order_number.'","'.$carrier.'","'.$service.'","'.$tracking_number.'","'.$date.'")');
			
  		$ShipNoticeNode = $xmlResponse->createTag("ShipNotice", array(), '', $root);
			
			$client_id = $shipnotify->customer_id;
			$cust_code = mysqli_query($conn,"select email from ".TABLE_PREFIX."customer where customer_id = $client_id");
			$row_cust = parseSpecCharsA(mysqli_fetch_array($cust_code));
			
			$ship_dlastmodified = "00-00-0000";
			if(trim($shipnotify->order_upd_datetime)!="0000-00-00 00:00:00")
				$ship_dlastmodified = date("m-d-Y",$shipnotify->order_upd_datetime);
			
			$order_id = $shipnotify->orders_id;
			
			$xmlResponse->createTag("OrderNumber", array(), $shipnotify->orders_no, $ShipNoticeNode);
			$xmlResponse->createTag("OrderId", array(), $shipnotify->orders_id, $ShipNoticeNode);
			$xmlResponse->createTag("CustomerCode", array(), $row_cust->email, $ShipNoticeNode);
			$xmlResponse->createTag("LabelCreatedDate", array(), $ship_dlastmodified, $ShipNoticeNode);
			$xmlResponse->createTag("ShipDate", array(), $shipnotify->ship_date, $ShipNoticeNode);
			$xmlResponse->createTag("Carrier", array(), $shipnotify->ship_method, $ShipNoticeNode);
			$xmlResponse->createTag("Service", array(), $_REQUEST['service'], $ShipNoticeNode);
			$xmlResponse->createTag("TrackingNumber", array(), $shipnotify->tracking_no, $ShipNoticeNode);
			$xmlResponse->createTag("ShippingCost", array(), $shipnotify->shipping_amt, $ShipNoticeNode);
			
			$RecipientNode = $xmlResponse->createTag("Recipient", array(), '', $ShipNoticeNode);
				
				$xmlResponse->createTag("Name", array(), $shipnotify->ship_first_name, $RecipientNode);
				$xmlResponse->createTag("Company", array(), $shipnotify->ship_company, $RecipientNode);
				$xmlResponse->createTag("Address1", array(), $shipnotify->ship_address1, $RecipientNode);
				$xmlResponse->createTag("Address2", array(), $shipnotify->ship_address2, $RecipientNode);
				$xmlResponse->createTag("City", array(), $shipnotify->ship_city, $RecipientNode);
				$xmlResponse->createTag("State", array(), $shipnotify->ship_state, $RecipientNode);
				$xmlResponse->createTag("PostalCode", array(), $shipnotify->ship_zip, $RecipientNode);
				$xmlResponse->createTag("Country", array(), $shipnotify->ship_country, $RecipientNode);
			
			$ItemsNode = $xmlResponse->createTag("Items", array(), '', $ShipNoticeNode);
				
				$ship_db_dtl = mysqli_query($conn,"select products_id,quantity,is_free_gift_products,sku from ".TABLE_PREFIX."order_detail where orders_id =".$shipnotify->orders_id);					

				while($ship_items = parseSpecCharsA(mysqli_fetch_array($ship_db_dtl))){
						
					if($shipnotify->orders_id > 13445)
					{
						$ship_products = mysqli_query($conn,"select sku,product_name,short_description from ".TABLE_PREFIX."products where products_id = ".$ship_items->products_id);
					}
					else
					{						
						$ship_products = mysqli_query($conn,"select sku,product_name,short_description from ".TABLE_PREFIX."products where products_id = ".$ship_items->products_id);
					}
					$ship_rowproduct=parseSpecCharsA(mysqli_fetch_array($ship_products));
					
					$ItemNode = $xmlResponse->createTag("Item", array(), '', $ItemsNode);
					$newname = $rowproduct->product_name." ".$rowproduct->short_description;
						$xmlResponse->createTag("SKU", array(), $ship_rowproduct->sku, $ItemNode);
						$xmlResponse->createTag("Name", array(), $newname, $ItemNode);
						$xmlResponse->createTag("Quantity", array(), $ship_items->quantity, $ItemNode);
						
				}
      
    } break;  
    
  } 


  @header("Content-type: application/xml");
  print($xmlResponse->generate());

function xmlErrorResponse($command, $code, $message, $provider, $request_id='') {
  header("Content-type: application/xml");
  $xmlResponse = new xml_doc('<?xml version="1.0" encoding="UTF-8"?>');
  $root = $xmlResponse->createTag("RESPONSE", array('Version'=>'1.0'));
  $envelope = $xmlResponse->createTag("Envelope", array(), '', $root);
  $xmlResponse->createTag("Command", array(), $command, $envelope);
  $xmlResponse->createTag("StatusCode", array(), $code, $envelope);
  $xmlResponse->createTag("StatusMessage", array(), $message, $envelope);
  return $xmlResponse->generate();
}

function parseTagName($str) {
  return preg_replace("/[-=+\s!@#\$\^\&%*\(\)\{\}\[\]':`~\.]/is", "", $str);
}

function parseSpecChars($obj) {
  foreach($obj as $k=>$v){
    $obj->$k = htmlspecialchars($v, ENT_NOQUOTES); 
  }
  return $obj;
}


function parseSpecCharsA($arr){
	if(is_array($arr)){
	  foreach($arr as $k=>$v){
	    $obj->$k = htmlspecialchars($v, ENT_NOQUOTES); 
	  }
	  return $obj;
	}  
}

function _log($text, $line) {
}

class xml_doc {
	var $parser;			
	var $xml;				
	var $version;			
	var $encoding;			
	var $dtd;				
	var $entities;			
	var $xml_index;			
	var $xml_reference;		
	var $document;			
	var $stack;			

	function xml_doc($xml='') {
	
		$this->xml = $xml;

		$this->version = '1.0';
		$this->encoding = "utf-8";
		$this->dtd = '';
		$this->entities = array();
		$this->xml_index = array();
		$this->xml_reference = 0;
		$this->stack = array();
	}

	function parse() {

		$this->parser = xml_parser_create($this->encoding);
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, "startElement", "endElement");
		xml_set_character_data_handler($this->parser, "characterData");
		xml_set_default_handler($this->parser, "defaultHandler");

		if (!xml_parse($this->parser, $this->xml)) {

			$err_code = xml_get_error_code($this->parser);
			$err_string = xml_error_string($this->parser);
			$err_line = xml_get_current_line_number($this->parser);
			$err_col = xml_get_current_column_number($this->parser);
			$err_byte = xml_get_current_byte_index($this->parser);
			print "<p><b>Error Code:</b> $err_code<br>$err_string<br><b>Line:</b> $err_line <b>Column:</b> $err_col</p>";
		}

		xml_parser_free($this->parser);
	}

	function generate() {
		
		if ($this->version == '' and $this->encoding == '') {
			$out_header = '';
		} elseif ($this->version != '' and $this->encoding == '') {
			$out_header = '<' . "?xml version=\"{$this->version}\"?" . ">\n";
		} else {
			$out_header = '<' . "?xml version=\"{$this->version}\" encoding=\"{$this->encoding}\"?" . ">\n";
		}

		if ($this->dtd != '') {
			$out_header .= "<!DOCTYPE " . $this->dtd . ">\n";
		}

		$_root =& $this->xml_index[0];
		$this->xml = $this->createXML(0);

		return $out_header . $this->xml;
	}

	function stack_location() {
		
		return $this->stack[(count($this->stack) - 1)];
	}

	function startElement($parser, $name, $attrs=array()) {
		
		if (count($this->stack) == 0) {

			$this->document = new xml_tag($this,$name,$attrs);
			$this->document->refID = 0;

			$this->xml_index[0] =& $this->document;
			$this->xml_reference = 1;

			$this->stack[0] = 0;

		} else {
			$parent_index = $this->stack_location();

			$parent =& $this->xml_index[$parent_index];

			$parent->addChild($this,$name,$attrs);

			array_push($this->stack,($this->xml_reference - 1));
		}

	}

	function endElement($parser, $name) {
		array_pop($this->stack);
	}

	function characterData($parser, $data) {
		$cur_index = $this->stack_location();
		$tag =& $this->xml_index[$cur_index];
		$tag->contents .= $data;
	}

	function defaultHandler($parser, $data) {

	}

	function createTag($name, $attrs=array(), $contents='', $parentID = '', $encode = false) {
		

		if ($encode) {
			$contents = base64_encode(trim($contents));
			foreach ($attrs as $k=>$v){
				$attrs[$k] = base64_encode($v);
			}
			$attrs['encoding'] = "yes";
		}
		if ($parentID === '') {
			$this->document = new xml_tag($this,$name,$attrs,$contents);
			$this->document->refID = 0;
			$this->xml_index[0] =& $this->document;
			$this->xml_reference = 1;
			return 0;
		} else {
			$parent =& $this->xml_index[$parentID];
			return $parent->addChild($this,$name,$attrs,$contents);
		}
	}


	function createTag1($name, $attrs=array(), $contents='', $parentID = '') {
		
		if ($parentID === '') {
			$this->document = new xml_tag($this,$name,$attrs,$contents);
			$this->document->refID = 0;

			$this->xml_index[0] =& $this->document;
			$this->xml_reference = 1;

			return 0;
		} else {

			$parent =& $this->xml_index[$parentID];
			return $parent->addChild($this,$name,$attrs,$contents);
		}
	}


	function createXML($tagID,$parentXML='') {
		
		$final = '';

		$tag =& $this->xml_index[$tagID];

		$name = $tag->name;
		$contents = $tag->contents;
		$attr_count = count($tag->attributes);
		$child_count = count($tag->tags);
		$empty_tag = ($tag->contents == '') ? true : false;

		if ($attr_count == 0) {

			if ($empty_tag === true) {
					$final = "<$name />";
			} else {
					$final = "<$name>$contents</$name>";
			}
		} else {

			$attribs = '';
			foreach ($tag->attributes as $key => $value) {
				$attribs .= ' ' . $key . "=\"$value\"";
			}

			if ($empty_tag === true) {
				$final = "<$name$attribs />";
			} else {
				$final = "<$name$attribs>$contents</$name>";
			}
		}

		if ($child_count > 0) {
			foreach ($tag->tags as $childID) {
				$final = $this->createXML($childID,$final);
			}
		}

		if ($parentXML != '') {

			$stop1 = strrpos($parentXML,'</');
			$stop2 = strrpos($parentXML,' />');

			if ($stop1 > $stop2) {

				$begin_chunk = substr($parentXML,0,$stop1);
				$end_chunk = substr($parentXML,$stop1,(strlen($parentXML) - $stop1 + 1));

				$final = $begin_chunk . $final . $end_chunk;
			} elseif ($stop2 > $stop1) {

				$spc = strpos($parentXML,' ',0);

				$parent_name = substr($parentXML,1,$spc - 1);

				if ($spc != $stop2) {
					$parent_attribs = substr($parentXML,$spc,($stop2 - $spc));
				} else {
					$parent_attribs = '';
				}

				$final = "<$parent_name$parent_attribs>$final</$parent_name>";
			}
		}

		return $final;
	}


	function getTag($tagID,&$name,&$attributes,&$contents,&$tags) {

		$tag =& $this->xml_index[$tagID];

		$name = $tag->name;
		$attributes = $tag->attributes;
		$contents = $tag->contents;
		$tags = $tag->tags;
	}

	function getChildByName($parentID,$childName,$startIndex=0) {
	
		$parent =& $this->xml_index[$parentID];

		if ($startIndex > count($parent->tags)) return false;


		for ($i = $startIndex; $i < count($parent->tags); $i++) {
			$childID = $parent->tags[$i];		

			$child =& $this->xml_index[$childID];

			if ($child->name == $childName) {
				return $childID;
			}
		}
	}

}


class xml_tag {

	var $refID;			
	var $name;			
	var $attributes = array();	
	var $tags = array();		
	var $contents;			
	var $children = array();	


	function xml_tag(&$document,$tag_name,$tag_attrs=array(),$tag_contents='') {
		$this->name = $tag_name;
		$this->attributes = $tag_attrs;
		$this->contents = $tag_contents;

		$this->tags = array();			
		$this->children = array();
	}

	function addChild (&$document,$tag_name,$tag_attrs=array(),$tag_contents='') {
		
		$this->children[(count($this->children))] = new xml_tag($document,$tag_name,$tag_attrs,$tag_contents);

		$document->xml_index[$document->xml_reference] =& $this->children[(count($this->children) - 1)];

		$document->xml_index[$document->xml_reference]->refID = $document->xml_reference;

		array_push($this->tags,$document->xml_reference);

		$document->xml_reference++;

		return ($document->xml_reference - 1);
	}
}
?>