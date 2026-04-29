<?
set_time_limit(0);
ini_set('memory_limit',"500M");
include_once("/home/maxaroma/public_html/lib/config_setting.php");

$vemail = "tempchecknew@gmail.com";
$sendmessage = "Pepperjam Correct Order Started In Maxaroma on ".date('m-d-Y');	
$test 	= @mail($vemail,"Pepperjam Correct Order Started In Maxaroma",$sendmessage);

$start_limit 		= 0;
$end_limit			= 50;

$total_batch		= 0;
$total_record_batch	= 0;

$filename = '8716_transactions_corrected_'.strftime('%Y%m%d%H%M%S').'.csv';

$export_file_name = PEPPERJAM_CORRECT_ORDER_PATH.$filename;

if(file_exists($export_file_name))
{
	@unlink($export_file_name);
}	


//$sql = "SELECT o.orders_no,o.customer_id,o.pepperjam_reason_code,o.coupon_code,od.sku,od.price,od.quantity FROM `" . TABLE_PREFIX . "orders` as o,`". TABLE_PREFIX . "order_detail`, as od WHERE o.is_pepperjam='Yes' AND o.pepperjam_sent='No' AND (o.status='Canceled' OR o.status='Declined') AND o.orders_id=od.orders_id";
$sql = "SELECT o.orders_id FROM `" . TABLE_PREFIX . "orders` as o,`". TABLE_PREFIX . "order_detail` as od 
		WHERE o.is_pepperjam='Yes' AND o.pepperjam_sent='No' AND (o.status='Canceled' OR o.status='Declined') 
		AND o.orders_id=od.orders_id ";
	   	
$res_total = $obj->select($sql);	

	
$total_Orders	= count($res_total);
$total_record_batch  = ceil($total_Orders/$end_limit);		

if($total_Orders> 0)
{
	$fp = fopen ($export_file_name, "a+");

	$header_row = "PROGRAM_ID,ORDER_ID,ITEM_ID,ITEM_PRICE,QUANTITY,CATEGORY,NEW_TO_FILE,COUPON,REASON\n";

	fwrite($fp,$header_row);
	fclose($fp);


// Insert the header
$file_content = '';

//####### TOTAL BATCH START HERE#############################
for($b=0; $b < $total_record_batch; $b++ )
{     
    $sel_pro = "SELECT o.orders_id,o.orders_no,o.customer_id,o.pepperjam_reason_code,o.coupon_code,od.sku,od.price,od.quantity FROM `" . TABLE_PREFIX . "orders` as o,`". TABLE_PREFIX . "order_detail` as od 
				WHERE o.is_pepperjam='Yes' AND o.pepperjam_sent='No' AND (o.status='Canceled' OR o.status='Declined') 
				AND o.orders_id=od.orders_id  LIMIT $start_limit,$end_limit";
	$result = $obj->select($sel_pro);
	$tot_rows = count($result);
	$file_content = '';	
	
	for( $i = 0; $i < $tot_rows; $i++ )	
	{	
		
		$PROGRAM_ID = "8716";
		$ORDER_ID 	= $result[$i]["orders_no"];
		$ITEM_ID	= $result[$i]["sku"];
		$ITEM_PRICE = 0;
		$QUANTITY 	= 0;
		$CATEGORY 	= "";
		
		$sql = "SELECT * FROM `".TABLE_PREFIX."orders` 
		WHERE customer_id ='".$result[$i]["customer_id"]."'"  ;
		$customArr = $obj->select($sql);
		
		$NEW_TO_FILE = 1;
		if(count($customArr) > 1)
		{
		  $NEW_TO_FILE = 0;
		}
		
		$COUPON = $result[$i]["coupon_code"];
		$REASON = $result[$i]["pepperjam_reason_code"];
		
		$file_content .= '"'.str_replace('"','""',$PROGRAM_ID).'","'.str_replace('"','""',$ORDER_ID).'","'.str_replace('"','""',$ITEM_ID);
	    
	    $file_content .= '","'.str_replace('"','""',$ITEM_PRICE).'","'.str_replace('"','""',$QUANTITY).'","'.str_replace('"','""',$CATEGORY);
	     
	    $file_content .= '","'.str_replace('"','""',$NEW_TO_FILE).'","'.str_replace('"','""',$COUPON).'","'.str_replace('"','""',$REASON).'"';
	 
		$file_content .= "\n";	
		
		$UpdateDetails = array(
								"pepperjam_sent" => "Yes"
							  );
		$resultUpdates = $obj->update(TABLE_PREFIX.'orders', $UpdateDetails, "orders_id ='".$result[$i]['orders_id']."'  AND pepperjam_sent='No'") ;	 						

		  		
	}
	
	$fp = fopen ($export_file_name, "a+");
	fwrite($fp,$file_content);
	fclose($fp);
	
	$file_content = '';		
			
	$start_limit = $start_limit+$end_limit;
	$total_batch = $total_batch+1;
	
	
}
}
$vemail = "tempchecknew@gmail.com";
$sendmessage = "Pepperjam Correct Order Ended In Maxaroma on ".date('m-d-Y');	
$test 	= @mail($vemail,"Pepperjam Correct Order Ended In Maxaroma",$sendmessage);
unset($obj);
exit;
?>

