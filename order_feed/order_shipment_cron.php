<?
set_time_limit(0); 
//ini_set('memory_limit','500M');
ini_set('display_errors', 1);

//@mail("arjun.qualdev@gmail.com","Peraroma Shipped Order Crone","Peraroma Shipped Order Crone executed");

$physical_path= "/home/maxaroma/public_html/";
$Site_URL = "http://www.maxaroma.com/";

require_once($physical_path."general_cron/config_setting.php");

$action = 'Export';
$start_limit 		= 0;
$end_limit			= 50;
$total_batch		= 0;
$total_record_batch	= 0;

$dtTo = date('Y-m-d');
$dtFrom=date("Y-m-d", strtotime("-20day"));
$filterClause.= "(ord.order_datetime BETWEEN '".$dtFrom."' AND '".$dtTo."') ";
$filterClause.= " AND ord.ship_status='Shipped' AND ord.status !='Canceled' ";
 
//Testing purpose
//*$filterClause = "AND (ord.dorderdate BETWEEN '2012-08-6' AND '2012-08-8' AND od.estatus='Shipped') ";
//End 
if( $action == "Export" ) 
{	
	$export_file_name = $physical_path."order_feed/shipped_order_feed.txt";
	//echo $export_file_name; exit;
	
	
	if( $start_limit == 0 )
	{
		//$sql = mysql_query("SELECT * FROM `pu_orders` as ord, `pu_order_detail` as od WHERE ord.orders_id = od.orders_id $filterClause group by od.orders_detail_id order by ord.orders_id desc");
		
		$sql = mysql_query("SELECT * FROM `pu_orders` as ord WHERE $filterClause order by ord.orders_id desc");
		
		$res_total = array();
		$count=0;
	
		while ( $row = mysql_fetch_array($sql))
		{
			$res_total[$count] = $row;
			$count++;
		}//End//
		
				
		$total_prod  = count($res_total);
		$total_record_batch  = ceil($total_prod/$end_limit);
		
		if(file_exists($export_file_name))
		{
			unlink($export_file_name);
		}	
		
		$fp = fopen ($export_file_name, "a+");
		$header_row = "merchant order id\ttracking number\tcarrier code\tother carrier name\tship date\n";
		
		fwrite($fp,$header_row);
		fclose($fp);
		// Insert the header
	}
	
	for($b=0; $b < $total_record_batch; $b++ )
	{	
			
		//$sel_pro = mysql_query("SELECT *,ord.orders_no as ordno FROM `pu_orders` as ord, `pu_order_detail` as od WHERE ord.orders_id = od.orders_id $filterClause group by od.orders_detail_id order by ord.orders_id desc LIMIT $start_limit,$end_limit");
		
		$sel_pro = mysql_query("SELECT *,ord.orders_no as ordno FROM `pu_orders` as ord WHERE $filterClause order by ord.orders_id desc LIMIT $start_limit,$end_limit");
		
		if(mysql_num_rows($sel_pro) > 0)
		{
			//Counter code start//
			$data = array();
			$file_content = '';	
			while ( $rowrecord = mysql_fetch_array($sel_pro))
			{ 
			//echo '<pre>';print_r($rowrecord); exit;
				//$data[$count] = $rowrecord;<br />
				$order_id = $rowrecord["ordno"];
				if($shipmethods!='')
				{
					$shipmethods		=	$rowrecord["ship_method"];
					$other_shipmethod 	= '';
				}else
				{
					$shipmethods		= 'Other';
					$other_shipmethod 	= 'Other';
				}
				$tracking_no = $rowrecord["tracking_no"];
				$ship_date = $rowrecord["ship_date"];
				
				$file_content .= $order_id."\t".$tracking_no."\t".$shipmethods."\t".$other_shipmethod."\t".$ship_date."\n";
	
			}//End//
		}
	//	echo $file_content;
		
		$start_limit = $start_limit+$end_limit;
		$total_batch = $total_batch+1;
		
		$fp = fopen ($export_file_name, "a+");
		fwrite($fp,$file_content);
		fclose($fp);
		//echo "yes";
	//exit;	
		if($total_batch == $total_record_batch ) 
		{
			//Mail Code start here//////////////////
			$vemail = "qqualdev@gmail.com";
			//$vemail = "arjun.qualdev@gmail.com";
			$vfromemail = "gequaldev@gmail.com";
			$headers = "To: $vemail <$vemail>\n" . "From: $vfromemail <$vfromemail>\n" . "MIME-Version: 1.0\n" . "Content-type: text/html; charset=iso-8859-1";
			$MSG =  "Maxaroma Shipped Order exported successfully !";
			$test = @mail($vemail,"Maxaroma Shipped Order Crone",$MSG,$headers);
			//*echo "hello G8"; 
			exit;
			
			//Mail Code end here//////////////////	
		}
	}	
}
else
{
	//Mail Code start here//////////////////
	$vemail = "qqualdev@gmail.com";
	$vfromemail = "gequaldev@gmail.com";
	$headers = "To: $vemail <$vemail>\n" . "From: $vfromemail <$vfromemail>\n" . "MIME-Version: 1.0\n" . "Content-type: text/html; charset=iso-8859-1";
		$MSG =  "Maxaroma Shipped Order not exported!";
	$test = @mail($vemail,"Maxaroma Shipped Order Crone",$MSG,$headers);
	exit;
	//Mail Code end here//////////////////
}
	
?>
