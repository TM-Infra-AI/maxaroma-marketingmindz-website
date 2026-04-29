<?php
/* This Cron is used to create products in wish api */ 
set_time_limit(0);
//ini_set('memory_limit',"1024M");
//ini_set('memory_limit',"96M");

include_once("/home/maxaroma/public_html/lib/config_setting.php");
require_once 'vendor/autoload.php';
use Wish\UsedToken;
use Wish\WishClient;
use Wish\Exception\ServiceResponseException;

$myFile = '/home/maxaroma/public_html/Logs/WishDisableProducts.txt';
if(filesize($myFile)==10000000)
{
	rename($myFile,"/home/maxaroma/public_html/Logs/WishDisableProducts_bk.txt");
	unlink($myFile);
}

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');

	$stringData .= date("m/d/Y H:i:s")." : WishDisableProducts.php :  Wish Disable Products  : Wish Disable Products Start\n";
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



$sql = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku) ";
$sql .= " FROM `" . TABLE_PREFIX . "products`,`" . TABLE_PREFIX . "products_one`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
$sql .= " WHERE 1 ";
$sql .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
$sql .= " AND `".TABLE_PREFIX."brand`.status='1' ";
$sql .= " AND m.status = '1' ";
$sql .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
$sql .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
$sql .="  AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
$sql .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
$sql .="  AND `" . TABLE_PREFIX . "products_one`.is_wish_disable='Yes'";
$sql .="  AND `" . TABLE_PREFIX . "products_one`.is_sent_wish_disable='No'";
$sql .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_one`.products_id ";
$sql .="  Group By `" . TABLE_PREFIX . "products`.sku ";	

$res_total = $obj->select($sql);
//echo "<pre>"; print_r($res_total); exit;
	
$total_prod			 = count($res_total);

$total_record_batch  = ceil($total_prod/$end_limit);

$skustring = "";

if($total_prod > 0)
{
	
	
	for($b=0; $b < $total_record_batch; $b++ )
	{
		 $sel_pro = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku),`" . TABLE_PREFIX . "category`.parent_id,`" . TABLE_PREFIX . "products`.products_id";
		 $sel_pro .= " FROM `" . TABLE_PREFIX . "products`,`" . TABLE_PREFIX . "products_one`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
		 $sel_pro .= " WHERE 1 ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
		 $sel_pro .= " AND `".TABLE_PREFIX."brand`.status='1' ";
		 $sel_pro .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
		 $sel_pro .= " AND m.status = '1' ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
		 $sel_pro .="  AND `" . TABLE_PREFIX . "products_one`.is_wish_disable='Yes'";
		 $sel_pro .="  AND `" . TABLE_PREFIX . "products_one`.is_sent_wish_disable='No'";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_one`.products_id ";
		 $sel_pro .= " Group By `" . TABLE_PREFIX . "products`.sku"; 
		 $sel_pro .= " LIMIT $start_limit,$end_limit";
		
		 $prodRes = $obj->select($sel_pro);
		
		 
		$TotalProducts = count($prodRes);
		

		for($i=0;$i<$TotalProducts;$i++)
		{
			
			$coderror = $client->disableProductById($prodRes[$i]['sku']);
			if($coderror > 0)
			{
				$skulist.= $prodRes[$i]["sku"].",";
			}
			else
			{
				$sql = "Update `" . TABLE_PREFIX . "products_one` SET is_sent_wish_disable='Yes' WHERE products_id='".$prodRes[$i]["products_id"]."'";
				$result = $obj->sql_query($sql);
			}
			
		}
		 $start_limit = $start_limit+$end_limit;
		 $total_batch = $total_batch+1;
		 
  }
}

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');
	$stringData = "";
	$stringData .= date("m/d/Y H:i:s")." : WishDisableProducts.php :  Wish Disable Products  : Wish Disable Products End\n";
	fwrite($fh, $stringData);
	fclose($fh);
}


if($skulist!='')
{
	$subject = "Wish Express Disable Failed In Wish API On Maxaroma";
	$headers = "From: cron@maxaroma.com <cron@maxaroma.com>\n" . "MIME-Version: 1.0\n" . "Content-type: text/html; charset=iso-8859-1";
	$sendmessage = "Wish Express Disable Failed In Wish API On Maxaroma At ".date('m-d-Y') . " And The Product SKU Is ".$skulist;
	$onesendstat = mail("naresh.qualdev@gmail.com", $subject, $sendmessage, $headers);
	
}

unset($obj);
exit;

function removeSpCharacters ($data)
{
	
	$data = str_replace("<br />","",trim($data));
	$data = str_replace("<br>","",trim($data));
	$data = str_replace("\n","",trim($data));
	$data = str_replace("\r","",trim($data));
	$data = str_replace("\t","",trim($data));
	$data = str_replace('"',"",trim($data));
	$data = str_replace(',',"",trim($data));
	$data = str_replace("&amp;","and",trim($data));
	$data = str_replace("+","and",trim($data));
	$data = str_replace("&","and",trim($data));
	$data = str_replace("&eacute;","e",trim($data));
	$data = str_replace("andeacute;","e",trim($data));
	$data = str_replace("é","e",trim($data));
	$data = str_replace("andEacute;","E",trim($data));
	$data = str_replace("&Eacute;","E",trim($data));
	$data = str_replace("É","E",trim($data));
	$data = str_replace("!","",trim($data));		
	return $data;
}
?>
