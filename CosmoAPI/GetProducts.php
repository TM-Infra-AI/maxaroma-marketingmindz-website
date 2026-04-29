<?php
set_time_limit(0);
include_once("/home/maxaroma/public_html/lib/config_setting.php");
$vemail = "tempchecknew@gmail.com";
$sendmessage = "Cosmo Product Csv File Start In Maxaroma on ".date('m-d-Y');	
$test 	= @mail($vemail,"Cosmo Product Csv File Start In Maxaroma",$sendmessage);

ini_set('display_errors',1);
$start_limit 		= 0;
$end_limit			= 50;

$total_batch		= 0;
$total_record_batch	= 0;
$export_file_name = EXPORT_VENDOR_FILE_PATH."cosmo_maxaroma.csv";
if(file_exists($export_file_name))
{
	@unlink($export_file_name);
}


$sql = "SELECT DISTINCT(cosmo_sku) FROM `".TABLE_PREFIX."products` WHERE cosmo_sku!='' Group By cosmo_sku ";
$ProductRes = $obj->select($sql);
$total_prod			 = count($ProductRes);

$total_record_batch  = ceil($total_prod/$end_limit);
if($total_prod	> 0)
{

$fp = fopen ($export_file_name, "a+");

$header_row = "itmref,retail,net,avail\n";

fwrite($fp,$header_row);
fclose($fp);

// Adroll

// Insert the header
$file_content = '';
$skuStr = '';
//####### TOTAL BATCH START HERE#############################
for($b=0; $b < $total_record_batch; $b++ )
{
     
     $sel_pro = "SELECT  cosmo_sku FROM `".TABLE_PREFIX."products` WHERE cosmo_sku!=''";
	 $sel_pro .=" Group By cosmo_sku"; 
     $sel_pro .=" LIMIT $start_limit,$end_limit";
	 $result = $obj->select($sel_pro);
	 $tot_rows = count($result);
	 
	
	 $file_content = '';	
	
	for( $i = 0; $i < $tot_rows; $i++ )	
	{	
		$curl_responseArr = array();
		
		$REQUEST_URL = 'https://api.cosmopolitanusa.com/v1/products/'.$result[$i]["cosmo_sku"];

		$header = array(
					'authorization:CosmoToken UEVSNFVTOkFkQG1zNDQ4NCE=',
					'Content-Type: application/json'
				);

		$curl = curl_init($REQUEST_URL);
		curl_setopt($curl,CURLOPT_HEADER, false);
		curl_setopt($curl,CURLOPT_HTTPHEADER,$header); 
		curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
		$curl_response = curl_exec($curl);
		$curl_responseArr = json_decode($curl_response, true);
		curl_close($curl);
		
		if($curl_responseArr["Status"]!='')
		{
			$skuStr .= $result[$i]["cosmo_sku"].",";
			continue;
		}
		
		if($b==0 && $i==0)
		{
			$vemail = "naresh.qualdev@gmail.com";
			$sendmessage = "Cosmo File Process  In Maxaroma on ".date('m-d-Y')." And Sku(s): ".json_encode($curl_response);	
			$test 	= @mail($vemail,"Important : Cosmo File Process",$sendmessage);	
			$test 	= @mail("ravi.qualdev@gmail.com","Important : Cosmo File Process",$sendmessage);	
		}
		
		$SKU 	= $curl_responseArr["Item"];
		
		$retail	= $curl_responseArr["Retail"];
		
		$net	= $curl_responseArr["Net"];
		
		$avail	= $curl_responseArr["Available"];
		
		$file_content .= '"'.str_replace('"','""',$SKU).'","'.str_replace('"','""',$retail).'","'.str_replace('"','""',$net);

        $file_content .= '","'.str_replace('"','""',$avail).'"';          
       
		$file_content .= "\n";
		
	   	
	}
	
	$fp = fopen ($export_file_name, "a+");
	fwrite($fp,$file_content);
	fclose($fp);
	
	$file_content = '';		
			
	$start_limit = $start_limit+$end_limit;
	
	$total_batch = $total_batch+1;
	
	
}
 $destination = EXPORT_VENDOR_FILE_PATH."cosmo_maxaroma_copy.csv";
 copy( $export_file_name,$destination);
}





if($skuStr!='')
{
$vemail = "naresh.qualdev@gmail.com";
$sendmessage = "Cosmo Product Csv File Error  In Maxaroma on ".date('m-d-Y')." And Sku(s): ".$skuStr;	
$test 	= @mail($vemail,"Cosmo Product Csv File Error In Maxaroma",$sendmessage);	
}
$vemail = "tempchecknew@gmail.com";
$sendmessage = "Cosmo Product Csv File End In Maxaroma on ".date('m-d-Y');	
$test 	= @mail($vemail,"Cosmo Product Csv File End In Maxaroma",$sendmessage);
exit;
?>
