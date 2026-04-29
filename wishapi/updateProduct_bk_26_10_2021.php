<?
set_time_limit(0);
//ini_set('memory_limit',"1024M");
//ini_set('memory_limit',"96M");
include_once("/home/maxaroma/public_html/lib/config_setting.php");
require_once 'vendor/autoload.php';
use Wish\UsedToken;
use Wish\WishClient;
use Wish\Exception\ServiceResponseException;
/*$vemail = "tempchecknew@gmail.com";
$sendmessage = "Wish Product Bulk Update start Cron In Wish API On Maxaroma At ".date('m-d-Y');	
$test 	= @mail($vemail,"Wish Product Bulk Update start Cron In Wish API On Maxaroma",$sendmessage);
*/


$myFile = '/home/maxaroma/public_html/Logs/updateProduct.txt';
if(filesize($myFile)==10000000)
{
	rename($myFile,"/home/maxaroma/public_html/Logs/updateProduct_bk.txt");
	unlink($myFile);
}

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');

	$stringData .= date("m/d/Y H:i:s")." : updateProduct.php :  Wish Product Start  : Wish Product Bulk Update Start\n";
	fwrite($fh, $stringData);
	fclose($fh);
}


$start_limit 		= 0;
$end_limit			= 50;


$total_batch		= 0;
$total_record_batch	= 0;
$TokenObj = new UsedToken();
$Token  = $TokenObj->getTokens();
$client = new WishClient($Token,'prod');


$fetch  = " IF(`" . TABLE_PREFIX . "products`.sale_price!=0 AND `" . TABLE_PREFIX . "products`.sale_price < `" . TABLE_PREFIX . "products`.our_price,`" . TABLE_PREFIX . "products`.sale_price,`" . TABLE_PREFIX . "products`.our_price) AS product_price "; 


$sql = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku) ,".$fetch." ";
$sql .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
$sql .= " WHERE 1 ";
$sql .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
$sql .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
$sql .="  AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
$sql .="  AND `" . TABLE_PREFIX . "products`.current_stock<=0 ";
$sql .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
$sql .="  Group By `" . TABLE_PREFIX . "products`.sku ";	
$sql .="  having  product_price > 0 ";	
$res_total = $obj->select($sql);
	
$total_prod			 = count($res_total);



$total_record_batch  = ceil($total_prod/$end_limit);

$skustring = "";
$skulist = '';

if($total_prod > 0)
{
	for($b=0; $b < $total_record_batch; $b++ )
	{	
		$sel_pro = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku),`" . TABLE_PREFIX . "category`.parent_id,";
		$sel_pro .= " `" . TABLE_PREFIX . "products_category`.category_id, ";
		$sel_pro .= " `" . TABLE_PREFIX . "products`.sku, ";
		$sel_pro .= " `" . TABLE_PREFIX . "products`.products_id, ";
		$sel_pro .= " `" . TABLE_PREFIX.  "products`.retail_price, "; 
		$sel_pro .= " `" . TABLE_PREFIX.  "products`.current_stock, "; 
		$sel_pro .= $fetch;
		$sel_pro .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
		$sel_pro .= " WHERE 1 ";
		$sel_pro .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
		$sel_pro .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
		$sel_pro .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
		$sel_pro .= " AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
		$sel_pro .="  AND `" . TABLE_PREFIX . "products`.current_stock<=0 ";
		$sel_pro .= " Group By `" . TABLE_PREFIX . "products`.sku"; 
		$sel_pro .= " HAVING product_price > 0";
		$sel_pro .= " LIMIT $start_limit,$end_limit";
	
		$prodRes = $obj->select($sel_pro);
		
		
		
		$TotalProducts = count($prodRes);
		$UpdateArr = array();
		for($i=0;$i<$TotalProducts;$i++)
		{
			
			$sku  	   = $prodRes[$i]["sku"];
			
			if($prodRes[$i]["status"] == '0')
			{
				$prodRes[$i]["current_stock"] = 0;
			}
			if($prodRes[$i]["current_stock"] < 3)
			{
				$prodRes[$i]["current_stock"] = 0;
			}
			
			
			
			$UpdateArr[]  = array("sku" => $sku,"enabled" =>false);
			
				 
		 
		 	
			
		}
		
		
		
		$uodatestr = json_encode($UpdateArr);
		
		$ch = curl_init('https://merchant.wish.com/api/v2/variant/bulk-sku-update?access_token='.$Token.'&format=json&updates='.$uodatestr.'');
        
		$header = array(
				'Content-Type: application/json'
				);
		

        curl_setopt($ch, CURLOPT_POST,1);
		curl_setopt($ch, CURLOPT_HEADER, $header);
       
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$output = curl_exec($ch);
		
		
		 $start_limit = $start_limit+$end_limit;
		 $total_batch = $total_batch+1;
		 
  }
 
  
  
}

$myFile = '/home/maxaroma/public_html/Logs/updateProduct.txt';
if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');
	$stringData = "";
	$stringData .= date("m/d/Y H:i:s")." : updateProduct.php : Wish Product Start  : Wish Product Bulk Update End\n";
	fwrite($fh, $stringData);
	fclose($fh);
}
exit;



//$updates11='[{"sku":"UP608940535370","inventory":0},{"sku":"UP309973112018","inventory":145}]"'; 


/*
$ch = curl_init('https://merchant.wish.com/api/v2/variant/bulk-sku-update?access_token='.$Token.'&format=json&updates=[{"sku":"UP608940535370","inventory":0},{"sku":"UP309973112018","inventory":145}]');
  
$header = array(
				'Content-Type: application/json'
				);
		
        // set url
        
        //curl_setopt($ch, CURLOPT_POSTFIELDS,  $updates11);
        curl_setopt($ch, CURLOPT_POST,1);
		curl_setopt($ch, CURLOPT_HEADER, $header);
       
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$output = curl_exec($ch);
		
		echo "<PRE>";
		print_r($output);
		exit;

*/

/*

$ch = curl_init('https://merchant.wish.com/api/v2/variant/get-bulk-update-job-status?job_id=5c0134790ed86e68640fe61d&access_token='.$Token); 
curl_setopt($ch, CURLOPT_POST,0);
curl_setopt($ch, CURLOPT_HEADER, $header);
       
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$output = curl_exec($ch);
echo "<pre>"; print_r($output); exit;

*/

?>
