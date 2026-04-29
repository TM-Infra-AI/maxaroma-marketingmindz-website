<?php
/* This Cron is used to create products in wish api */ 
set_time_limit(0);
ini_set('memory_limit',"1024M");
include_once("/home/peraroma/public_html/lib/config_setting.php");
require_once 'vendor/autoload.php';
use Wish\UsedToken;
use Wish\WishClient;
use Wish\Exception\ServiceResponseException;

/*$sql ="Update pu_products set is_wish_created='No'";
$result = $obj->sql_query($sql);
exit;
*/
$start_limit 		= 0;
$end_limit			= 50;

$total_batch		= 0;
$total_record_batch	= 0;
$TokenObj = new UsedToken();
$Token  = $TokenObj->getTokens();
$client = new WishClient($Token,'prod');

$fetch = $generalobj->getSystemProductPrice("`" . TABLE_PREFIX . "products`");
$retail_price_check =  $generalobj->getSystemProductRetailPrice("`" . TABLE_PREFIX . "products`");
$current_stock_check = $generalobj->getSystemStockCheck("`" . TABLE_PREFIX . "products`");
$website_stock		 = $generalobj->getSystemWebsiteCurrentStock("`" . TABLE_PREFIX . "products`");


$sql = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku) ,".$fetch." ";
$sql .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
$sql .= " WHERE 1 ";
$sql .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
$sql .= " AND `".TABLE_PREFIX."brand`.status='1' ";
$sql .= " AND m.status = '1' ";
$sql .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
$sql .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
$sql .="  AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
$sql .="  AND `" . TABLE_PREFIX . "products`.status = '1' AND `" . TABLE_PREFIX . "products`.is_wish_created='No' ";
$sql .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
$sql .="  Group By `" . TABLE_PREFIX . "products`.sku ";	
$sql .="  having  product_price > 0";	

$res_total = $obj->select($sql);
	
$total_prod			 = count($res_total);
$total_record_batch  = ceil($total_prod/$end_limit);
if($total_prod > 0)
{
	$sql = "SELECT * FROM `".TABLE_PREFIX."site_settings` WHERE status = '1' AND var_name='WISH_PER_VAR' ORDER BY site_settings_id";
	$setting_res_new	=	$obj->select($sql);
	$WISH_PER_VAR 		= 	$setting_res_new[0]["setting"];
	
	for($b=0; $b < $total_record_batch; $b++ )
	{
	 $sel_pro = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku),`" . TABLE_PREFIX . "category`.parent_id,";
     $sel_pro .= " `" . TABLE_PREFIX . "products_category`.category_id, ";
     $sel_pro .= " `" . TABLE_PREFIX . "products`.sku, ";
     $sel_pro .= " `" . TABLE_PREFIX . "products`.products_id, ";
     $sel_pro .= " `" . TABLE_PREFIX . "products`.product_name ,";
     $sel_pro .= " `" . TABLE_PREFIX . "products`.image, ";
     $sel_pro .= " `" . TABLE_PREFIX . "products`.UPC, ";
     $sel_pro .= " `" . TABLE_PREFIX . "products`.short_description, ";
     $sel_pro .= " `" . TABLE_PREFIX . "products`.product_description, ";
     $sel_pro .= " `" . TABLE_PREFIX.  "products`.gender, "; 
     $sel_pro .= " `" . TABLE_PREFIX.  "products`.size, "; 
     $sel_pro .= " `" . TABLE_PREFIX.  "products`.vtype, ";
     $sel_pro .= " `" . TABLE_PREFIX.  "products`.UPC, "; 
     $sel_pro .= " `" . TABLE_PREFIX.  "products`.extra_images, "; 
     $sel_pro .= " `".TABLE_PREFIX."brand`.brand_name, "; 
	 $sel_pro .= " m.vmanufacture, "; 	
     $sel_pro .= " `" . TABLE_PREFIX.  "products`.brand_id ,";
     $sel_pro .= $fetch.",".$retail_price_check.",".$current_stock_check.",".$website_stock;
     $sel_pro .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
     $sel_pro .= " WHERE 1 ";
     $sel_pro .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
     $sel_pro .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
     $sel_pro .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
	 $sel_pro .= " AND `".TABLE_PREFIX."brand`.status='1' ";
	 $sel_pro .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
	 $sel_pro .= " AND m.status = '1' ";
     $sel_pro .= " AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
	 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.status = '1' AND `" . TABLE_PREFIX . "products`.is_wish_created='No' ";
	 $sel_pro .= " Group By `" . TABLE_PREFIX . "products`.sku"; 
	 $sel_pro .= " HAVING product_price > 0";
     $sel_pro .= " LIMIT $start_limit,$end_limit";
      
	
	 $prodRes = $obj->select($sel_pro);
	
	 $TotalProducts = count($prodRes);
		for($i=0;$i<$TotalProducts;$i++)
		{
			$name 		 				= removeSpCharacters(trim(ucwords($prodRes[$i]['product_name'])));
			$product_description 		= removeSpCharacters(trim(ucwords($prodRes[$i]["product_description"])));
			if($product_description=='')
			{
				$product_description 	= removeSpCharacters(trim(ucwords($prodRes[$i]["short_description"])));
			}
			if($product_description=='')
			{
				continue;
			}
			$tags 	   = 10;
			$sku  	   = $prodRes[$i]["sku"];
			$inventory = $prodRes[$i]["current_stock"];
			$price	   = $prodRes[$i]["product_price"];
			
			
			
			if($price > 0)
			{
				$priceCal = ($prodRes[$i]['product_price'] * ($WISH_PER_VAR/100));
				$price = $price +  $priceCal;
			}	
			
			
			$msrp	   = $prodRes[$i]["retail_price"];
			$product_retail_price = $msrp;
			
			$shipping  = 0;
			
			$size      = $prodRes[$i]["size"];
			$upc	   = $prodRes[$i]["UPC"];
			if(strlen($upc)==12)
			{
				$upc = $prodRes[$i]["UPC"];
			}
			else
			{
				$upc = '';
			}
			
			$large_image = '';
			if(file_exists(PRD_LARGE_IMG_PATH . $prodRes[$i]['image']) && !empty($prodRes[$i]['image'])) {
                $large_image = PRD_LARGE_IMG_URL . $prodRes[$i]['image'];
            } 
            if($large_image=='')
            {
				continue;
			}
	        $Image1 = $large_image;
	    
	        $Image1 = str_replace('https://','https://',$Image1);
		    $Image1 = str_replace('httpss://','https://',$Image1);
			
			$extra_images = $prodRes[$i]['extra_images'];
			$ExtraImageArr = array();
			
			$extraimagelist = ''; 
			
			if($extra_images!='')
			{
				$ExtraImageArr = explode("#",$extra_images);
				
				if(count($ExtraImageArr) > 0)
				{
					if(file_exists(PRD_LARGE_IMG_PATH.$ExtraImageArr[0]) && !empty($ExtraImageArr[0]))
					{
						$extraimagelist1 .= PRD_LARGE_IMG_PATH.$ExtraImageArr[0];
					}
					if(file_exists(PRD_LARGE_IMG_PATH.$ExtraImageArr[1]) && !empty($ExtraImageArr[1]))
					{
						 if($extraimagelist1!='')
						 {
							 $extraimagelist .= '|'.PRD_LARGE_IMG_PATH.$ExtraImageArr[1];
						 }
						 else
						 {
							 $extraimagelist .= PRD_LARGE_IMG_PATH.$ExtraImageArr[1];
						 }
					}
				}
				
			}
			
			
			$brand = trim($prodRes[$i]["brand_name"]);
			$product = array();
			$product = array(
							 'name'			=> $name,
							 'description'	=> $product_description,
						     'tags'			=> $tags,
							 'sku'			=> $sku,
							 'inventory'	=> $inventory,
							 'price'		=> $price,
							 'shipping'		=> $shipping,
							 'msrp'			=> $msrp,
							 'main_image' 	=> $Image1,
							 'brand'		=> $brand,
							 'upc'			=> $upc,
							 'extra_images' => $extraimagelist
							);
					
						
		try {
			$sql = "Update `" . TABLE_PREFIX . "products` SET is_wish_created='Yes' WHERE products_id='".$prodRes[$i]["products_id"]."'";
			
			$result = $obj->sql_query($sql);
			$newResOF = $client->createProduct($product);
			
			
			}catch(ServiceResponsException $e){
			  $vemail = "naresh.qualdev@gmail.com";
			  $sendmessage = "Product Created Failed In Wish API On Maxaroma At ".date('m-d-Y') . " And The Product SKU Is ".$prodRes[$i]["sku"];	
			  $test 	= @mail($vemail,"Product Created Failed In Wish API On Maxaroma",$sendmessage);
			}
		}
		 $start_limit = $start_limit+$end_limit;
		 $total_batch = $total_batch+1;
  }
}
$vemail = "naresh.qualdev@gmail.com";
$sendmessage = "Product Created Cron End In Wish API On Maxaroma At ".date('m-d-Y');	
$test 	= @mail($vemail,"Product Created Cron End In Wish API On Maxaroma",$sendmessage);
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
