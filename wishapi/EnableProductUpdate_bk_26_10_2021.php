<?
set_time_limit(0);
//ini_set('memory_limit',"1024M");
include_once("/home/maxaroma/public_html/lib/config_setting.php");
require_once 'vendor/autoload.php';
use Wish\UsedToken;
use Wish\WishClient;
use Wish\Exception\ServiceResponseException;

$myFile = '/home/maxaroma/public_html/Logs/EnableProductUpdate.txt';
if(filesize($myFile)==10000000)
{
	rename($myFile,"/home/maxaroma/public_html/Logs/EnableProductUpdate_bk.txt");
	unlink($myFile);
}

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');

	$stringData .= date("m/d/Y H:i:s")." : EnableProductUpdate.php :  Wish Enable Product Update  : Wish Enable Product Update Start\n";
	fwrite($fh, $stringData);
	fclose($fh);
}


$start_limit 		= 0;
$end_limit			= 20;


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
$sql .="  AND `" . TABLE_PREFIX . "products`.current_stock > 0 ";
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
		$sel_pro .="  AND `" . TABLE_PREFIX . "products`.current_stock > 0 ";
		$sel_pro .= " Group By `" . TABLE_PREFIX . "products`.sku"; 
		$sel_pro .= " HAVING product_price > 0";
		$sel_pro .= " LIMIT $start_limit,$end_limit";
	
		$prodRes = $obj->select($sel_pro);
		
		
		//echo "<pre>"; print_r($prodRes); exit;
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
			
			
			if($prodRes[$i]["current_stock"]<=0)
			{
				$UpdateArr[]  = array("sku" => $sku,"enabled" =>false);
			}
			else
			{
				$UpdateArr[]  = array("sku" => $sku,"inventory" =>$prodRes[$i]["current_stock"],"enabled" =>true);
			}
					 
		 
		 	
			
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

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');
	$stringData = "";
	$stringData .= date("m/d/Y H:i:s")." : EnableProductUpdate.php :  Wish Enable Product Update  : Wish Enable Product Update End\n";
	fwrite($fh, $stringData);
	fclose($fh);
}
exit;

?>
