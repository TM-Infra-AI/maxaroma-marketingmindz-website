<?php 
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;

use Hash;
use Session;
use App\Models\MetaInfo;
use App\Models\Customer;
use App\Models\ProductsCategory;
use App\Models\Category;
use App\Models\Products;
use App\Models\FreeGiftProduct;
use App\Models\ProductsOne;
use App\Models\MarkupPrices;
use App\Models\Stockalert;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PaymentMethod;
use App\Models\Dealofweek;
use App\Models\RewardRule;
use App\Models\ReferFriend;
use App\Models\RewardPoint;
use App\Models\MailBanner;
use App\Models\GiftCertificate;

use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\VendorTrait;
use App\Http\Controllers\Traits\EncryptTrait;
use App\Http\Controllers\Traits\AfterpayTrait;
use App\Http\Controllers\Traits\CartTrait;
use DB;
use Mail;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportOrders;

class GeneralController extends Controller
{
	use CommonTrait;
	use VendorTrait;
	use EncryptTrait;
	use AfterpayTrait;
	use CartTrait;
		
	public $PageData;
	public function __construct()
    {
		$PageType = 'NR';
		$MetaInfo = MetaInfo::where('type','=',$PageType)->get(); 
		if($MetaInfo->count() > 0 )
		{
			$this->PageData['meta_description'] = $MetaInfo[0]->meta_description;
			$this->PageData['meta_keywords'] = $MetaInfo[0]->meta_keywords;
		}
	}
	
	public function WholeSaleProducts(Request $request)
	{
		
		if(!Auth::user())
			return redirect('/login.html');
		
		$markuparr = MarkupPrices::get();
		$search_keyword = $request['search_keyword'];
		$SearchAll = 'No';
		if(isset($request->all_items))
			$SearchAll = 'Yes';
		$this->PageData['SearchAll'] = $SearchAll;
		$product_arr = $this->GetSpecialPriceWholesaler($request,$markuparr);
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Special Product Price List';
		$this->PageData['JSFILES'] = ['jquery-ui1.12.1.js','wholesaleproducts.js'];	
		$this->PageData['CSSFILES'] = ['jquery-ui1.12.1.css','myaccount.css','custom.css'];	
		$this->PageData['ProductArr'] = $product_arr['DataArr'];	
		$this->PageData['MarkupArr'] = $markuparr;	
		$this->PageData['Search_Keyword'] = $search_keyword;	
		$this->PageData['TotalProducts'] = $product_arr['TotalProducts'];	
		$this->PageData['PerPage'] = $product_arr['PerPage'];	
		
		if($request->isMethod('post')){
			$ProductHTML = view('myaccount.wholesaleproduct')->with($this->PageData)->render();
			
			return response()->json(array('TotalProducts' => $product_arr['TotalProducts'], 'ProductHTML'=>$ProductHTML, 'PerPage'=>$product_arr['PerPage']));
		}else{
			return view('myaccount.wholesaleproducts')->with($this->PageData);
		}
	}
	
	public function SearchWholeSaleProducts(Request $request)
	{
		$letters = $request['search_keyword'];
		$letters = mb_convert_encoding($letters,"HTML-ENTITIES", "UTF-8" );
		$letters = preg_replace("/[^a-z0-9,&; \'\’]/si","",$letters);
		
		$prodData = DB::table('pu_products as po')
						->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
							'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
							'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
							'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
							'po.vtype','po.variation_id','po.refine_feature','po.product_type','c.category_id','po.UPC','m.vmanufacture')

							//->select('p.products_id','p.image','p.imanufactureid','c.category_id','m.vmanufacture','p.product_name','p.sku','p.UPC')		
							->join('pu_products_category as pc','po.products_id','=','pc.products_id')
							->join('pu_category as c','c.category_id','=','pc.category_id')
							->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
							//->where('p.product_name','REGEXP','[[:<:]]'.$letters)
							->where(function($query) use ($letters){
									$query->orWhere('po.product_name','LIKE','%'.$letters.'%')
										  ->orWhere('po.sku','=',$letters)
										  ->orWhere('po.UPC','=',$letters);
								})
							->where('po.status','=','1')->groupBy('po.products_id')->limit(100)->get();
		
		$search_detail_arr = array();
		if ( count($prodData) > 0 ) 
		{
			for($i=0; $i<count($prodData); $i++)
			{
				$prodData[$i] = $this->SetProduct($prodData[$i]);
				if($request['all_items'] == 'No')
				{
					if($prodData[$i]->stock == 'Out')
						continue;
				}
				
				if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prodData[$i]->image) && $prodData[$i]->image != '')
				{$thumb_image = config('global.PRD_THUMB_IMG_URL').$prodData[$i]->image; }
			    else
				{$thumb_image = config('global.NO_IMAGE_THUMB');}
				
				$product_name = $prodData[$i]->product_name;
				$pid 	= $prodData[$i]->products_id;				
				$product_url = $this->getProductRewriteURL($prodData[$i]->products_id,$prodData[$i]->product_name,$prodData[$i]->category_id,$prodData[$i]->vmanufacture);
				//$strtest = '<a href='.$db_recs[$i]['product_url'].'>'.$thumb_image.' '.ucwords($product_name).'</a>';
				$search_detail_arr[$i]['data']['thumb_image'] = $thumb_image;
				$search_detail_arr[$i]['data']['product_name'] = $product_name;
				$search_detail_arr[$i]['data']['pid'] = $pid;
				$search_detail_arr[$i]['data']['product_url'] = $product_url;
				$search_detail_arr[$i]['data']['sku'] = $prodData[$i]->sku;
				$search_detail_arr[$i]['data']['upc'] = $prodData[$i]->UPC;
				
				$search_detail_arr[$i]['value'] = $pid;
				$search_detail_arr[$i]['label'] = $product_name;
				
			}
		} else {
			$search_detail_arr[0]['value'] = "0";
			$search_detail_arr[0]['label'] = $letters;
			$search_detail_arr[0]['data']['thumb_image'] = "";
			$search_detail_arr[0]['data']['product_name'] = $letters;
			$search_detail_arr[0]['data']['pid'] = "0";
			$search_detail_arr[0]['data']['product_url'] = "";
			$search_detail_arr[0]['data']['sku'] = "";
			$search_detail_arr[0]['data']['upc'] = "";
		}		
		//array_unshift($search_detail_arr,$data_key);
		
		//echo "<pre>";print_r($search_detail_arr);exit;
		return response()->json($search_detail_arr);
	}
	
	public function SpecialWholeSaleProductList(Request $request)
	{
		if(!Auth::user())
			return redirect('/login.html');
		
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Download Special Wholesaler Product Pricelist';
		$this->PageData['JSFILES'] = ['jquery-ui1.12.1.js','wholesaleproducts.js'];	
		$this->PageData['CSSFILES'] = ['jquery-ui1.12.1.css','myaccount.css','custom.css'];	
		
		$cust_detail = Customer::select('DownloadSpecialPricelist')
								->where('customer_id', '=', Session::get('sess_icustomerid'))
								->get();
		
		/* $SpecialCustomerFlag = "No";
		if(isset($cust_detail[0]->DownloadSpecialPricelist)){
			$SpecialCustomerFlag = $cust_detail[0]->DownloadSpecialPricelist;
		}
		
		$this->PageData['SpecialCustomerFlag'] = $SpecialCustomerFlag; */
		
		if(Session::get('eusertype')=='Wholesaler' && Session::get('is_dropshipper')!='Yes' && Session::get('SpecialCustomerFlag') !="Yes" )
		{
			return redirect('/');
		}
		
		return view('myaccount.download_special_wholesaler_list')->with($this->PageData);
	}
	
	public function SpecialWholeSaleProductList_Download(Request $request)
	{
		if(!Auth::user())
			return redirect('/login.html');
		
		/* $cust_detail = Customer::select('DownloadSpecialPricelist')
								->where('customer_id', '=', Session::get('sess_icustomerid'))
								->get();
		
		$SpecialCustomerFlag = "No";
		if(isset($cust_detail[0]->DownloadSpecialPricelist)){
			$SpecialCustomerFlag = $cust_detail[0]->DownloadSpecialPricelist;
		} */
		if(Session::get('eusertype')!='Wholesaler' && Session::get('is_dropshipper')!='Yes' && Session::get('SpecialCustomerFlag') != "Yes" )
		{
			return redirect('/');
		}
		
		$cust_detail = Customer::select('warehouse')
								->where('customer_id', '=', Session::get('sess_icustomerid'))
								->where('warehouse', '!=', '')
								->get();
		
		if($cust_detail->count()<=0)
		{
			$err_msg = "File Not Found";
			Session::flash('error',$err_msg);
			return redirect(config('global.SITE_URL').'specialwholesaleproductpricelist');
		}
		
		$warehouse = $cust_detail[0]->warehouse;
		
		if($warehouse=="")
		{
			$err_msg = "File Not Found";
			Session::flash('error',$err_msg);
			return redirect(config('global.SITE_URL').'specialwholesaleproductpricelist');
		}
		
		$warehouseArr = explode("#",$warehouse);
		$fd1 = "";
		$fd2 = "";
		$fd3 = "";
		$fd4 = "";
		$fd5 = "";
		for($p=0; $p < count($warehouseArr); $p++)
		{
			if($warehouseArr[$p]=="Website")
			{
				 $fd1 = "Website";
			}
			if($warehouseArr[$p]=="Cosmo")
			{
				 $fd2 = "Cosmo";
			}
			if($warehouseArr[$p]=="Nandansons")
			{
				 $fd3 = "Nandansons";
			}
			if($warehouseArr[$p]=="Perfumeworldwide")
			{
				 $fd4 = "Perfumeworldwide";
			}
			if($warehouseArr[$p]=="PCA")
			{
				 $fd5 = "PCA";
			}
		}
		
		// define(EXPORT_LIB,''.$physical_path.'Excel/export_library/');
		// include_once(EXPORT_LIB . "Writer.php");
		
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Download_Special_Wholesale_Product_Pricelist".Session::get('sess_icustomerid').".xls"))
		{
			unlink(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Download_Special_Wholesale_Product_Pricelist".Session::get('sess_icustomerid').".xls");
		}
		
		$export_file_name = "Download_Special_Wholesale_Product_Pricelist".Session::get('sess_icustomerid').".xls";
		$export_file_path = config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH').$export_file_name;
		
		$start_limit   = 0;
		$end_limit	   = 100;
		$process_batch = 0;
		$total_batch   = 0;

		$StockCondition = '';
		$aliasname = "p";
		$casewhenprice1 = "";
		$casewhenend = "";
		
		/* if($fd1!='')
		{
			// $StockCondition = getFSpecialSystemWebsiteCurrentStock("p");
			$aliasname = "p";
			$StockCondition = " if(('".$aliasname.".minimum_stock' > '".$aliasname.".current_stock' || '".$aliasname.".current_stock' <=8) ,'Out','In') as WebsiteStock ,";
		}
	
		if($fd1!='')
		{
			$casewhenprice1= " CASE
				 WHEN '".$aliasname.".current_stock' > 8 THEN IF('".$aliasname.".wholesale_price' > 0 ,'".$aliasname.".wholesale_price', 0)
				 ELSE ";
			$casewhenend = " End";
		}
		if($fd2!='')
		{
			$casewhenprice1 .= " CASE
					WHEN '".$aliasname.".cosmo_sku' !='' AND '".$aliasname.".cosmo_current_stock' > 71 THEN IF('".$aliasname.".cosmo_price' > 0 ,'".$aliasname.".cosmo_price', 0)
					WHEN '".$aliasname.".cosmo_sku' !='' AND '".$aliasname.".cosmo_current_stock' > 36 AND 'c.category_id' IN('15','39','74','203','7','8','11','27') THEN IF('".$aliasname.".cosmo_price' > 0 ,'".$aliasname.".cosmo_price', 0)
					ELSE";
			$casewhenend .= " End";
		}
		if($fd3!='')
		{
			$casewhenprice1 .= " CASE
						WHEN '".$aliasname.".nandansons_sku' !='' AND '".$aliasname.".nandansons_current_stock' > 71 THEN IF('".$aliasname.".nandansons_price' > 0 ,'".$aliasname.".nandansons_price', 0)
						WHEN '".$aliasname.".nandansons_sku' != '' AND '".$aliasname.".nandansons_current_stock' > 36 AND 'c.category_id' IN('15','39','74','203','7','8','11','27') THEN IF('".$aliasname.".nandansons_price' > 0 ,'".$aliasname.".nandansons_price', 0)
						ELSE";
			$casewhenend .= " End";
		}
		if($fd4!='')
		{
			$casewhenprice1 .= " CASE
						  WHEN '".$aliasname.".perfumeworldwide_sku' !='' AND '".$aliasname.".perfumeworldwide_currentstock' > 71 THEN IF('".$aliasname.".perfumeworldwide_price' > 0 ,'".$aliasname.".perfumeworldwide_price', 0)
						   WHEN '".$aliasname.".perfumeworldwide_sku' !='' AND '".$aliasname.".perfumeworldwide_currentstock' > 36 AND 'c.category_id' IN('15','39','74','203','7','8','11','27') THEN IF('".$aliasname.".perfumeworldwide_price' > 0 ,'".$aliasname.".perfumeworldwide_price', 0)
						  ELSE";
			$casewhenend .= " End";
		}
		if($fd5!='')
		{
			$casewhenprice1 .= " CASE
						  WHEN '".$aliasname.".pca_sku' !='' AND '".$aliasname.".pca_current_stock' > 71 THEN IF('".$aliasname.".pca_price' > 0 ,'".$aliasname.".pca_price', 0)
						  WHEN '".$aliasname.".pca_sku' !='' AND '".$aliasname.".pca_current_stock' > 36 AND 'c.category_id' IN('15','39','74','203','7','8','11','27') THEN IF('".$aliasname.".pca_price' > 0 ,'".$aliasname.".pca_price', 0)
						  ELSE";
			$casewhenend .= " End";
		}
		$pricename = "AS product_price"; 		
		$EXTRA_SQL = $casewhenprice1." 0 ".$casewhenend. " ".$pricename;*/
		
		
	if(Session::get('sess_useremail') == 'qqualdev@gmail.com')
	{
		config(['app.debug' => true]);
	}		
	$res_total_prods = DB::table('pu_products as p')
			->select('p.products_id')
			->join('pu_brand as pb','pb.brand_id','=','p.brand_id')
			->join('pu_products_one as po','po.products_id','=','p.products_id')
			->join('pu_manufacture as pm','pm.imanufactureid','=','p.imanufactureid')
			->join('pu_products_category as pc','pc.products_id','=','p.products_id')
			->join('pu_category as c','c.category_id','=','pc.category_id')
			->where('p.status','=','1')
			->where('c.status','=','1')
			->whereNotIn('c.category_id',['68','69','70','71'])
			->whereIn('p.product_type',['both','wholesaler'])
			->groupBy('p.products_id')->get();
		$total_records = $res_total_prods->count();
		$total_batch   = ceil($total_records/$end_limit);
		$csv_data = array();
		// $total_batch = 2;
		
		if($total_records > 0)
		{
			$per = '';
			$db_recs = MarkupPrices::get();
			//echo "<pre>"; print_r($db_recs); exit;
			
			$tot_markup_rice = $db_recs->count();
			
			if($request['rc']=='')
			$rc=0;
			else
			$rc=$request['rc'];

			if(file_exists($export_file_path))
			{
			  unlink($export_file_path);
			}
			
			// $Heading =array('Item#','Product Name','Category','Brand Name','UPC','Price','Quantity','Image','Order Quantity','Warehouse');
		
			// $newSelectQuery = "LEAST(
			// IF(p.cosmo_sku != '' AND (p.cosmo_current_stock > 71 || (p.cosmo_current_stock > 36 && c.category_id IN('15','39','74','203','7','8','11','27')))and p.cosmo_price > 0,p.cosmo_price,9999999999),
			// IF(p.perfumeworldwide_sku != '' AND (p.perfumeworldwide_currentstock >71 || (p.perfumeworldwide_currentstock > 36 && c.category_id IN('15','39','74','203','7','8','11','27'))) AND p.perfumeworldwide_price >0, p.perfumeworldwide_price, 9999999999),
			// IF(p.pca_sku != '' AND (p.pca_current_stock >71 || (p.pca_current_stock > 36 && c.category_id IN('15','39','74','203','7','8','11','27'))) AND p.pca_price >0, p.pca_price, 9999999999),
			// IF(p.nandansons_sku != '' AND (p.nandansons_current_stock >71 || (p.nandansons_current_stock > 36 && c.category_id IN('15','39','74','203','7','8','11','27'))) AND p.nandansons_price >0, p.nandansons_price, 9999999999)) AS minimum_product_price";
			
			
			// 'p.minimum_stock','p.current_stock','p.wholesale_price','p.cosmo_sku','p.cosmo_current_stock','p.cosmo_price','p.nandansons_sku','p.nandansons_current_stock','p.nandansons_price','p.perfumeworldwide_sku','p.perfumeworldwide_currentstock','p.perfumeworldwide_price','p.pca_sku','p.pca_current_stock','p.pca_price'
			
			for($b=0; $b<$total_batch; $b++){
				$result = DB::table('pu_products as p')
						->select('p.products_id','p.minimum_stock','p.sku','p.product_name','p.display_position','p.product_description','p.short_description','p.cosmo_sku','p.cosmo_current_stock','p.nandansons_sku','p.gender','p.size','p.nandansons_current_stock','p.perfumeworldwide_sku','p.perfumeworldwide_currentstock','p.current_stock','p.cosmo_wholesale_price','p.nandansons_wholesale_price','p.perfumeworldwide_wholesale_price','p.pca_sku','p.pca_wholesale_price','p.pca_current_stock','p.cosmo_price','p.nandansons_price','p.perfumeworldwide_price','p.pca_price','p.w_our_cost','p.wholesale_price','pm.vmanufacture','pb.brand_name','p.retail_price','p.image','c.category_name','p.UPC','c.category_id','po.special_website_price')
						->join('pu_products_one as po','po.products_id','=','p.products_id')
						->join('pu_brand as pb','pb.brand_id','=','p.brand_id')
						->join('pu_manufacture as pm','pm.imanufactureid','=','p.imanufactureid')
						->join('pu_products_category as pc','pc.products_id','=','p.products_id')
						->join('pu_category as c','c.category_id','=','pc.category_id')
						->where('p.status','=','1')
						->where('c.status','=','1')
						// ->where('p.sku','=','UP085715163103')
						->whereNotIn('c.category_id',['68','69','70','71'])
						->whereIn('p.product_type',['both','wholesaler'])
						->groupBy('p.products_id')
						->orderBy('pm.vmanufacture')
						->offset($start_limit)
						->limit($end_limit)
						->get();
				
				$file_content = '';
				$cnt_tot_prd = $result->count();
				
				$check_categoryarray = ['15','39','74','203','7','8','11','27'];
				$fd1_check_stock = 8;
				$check_stock_1 = 71;
				$check_stock_2 = 36;
				
				for( $p=0; $p<$cnt_tot_prd; $p++) {
					//get product price
						$product_price = 0;
						$product_price_minimum = 0;
						$minimum_product_price_arr = [];
						
						$WebsiteStock = "In";
						if($result[$p]->current_stock <= $fd1_check_stock || $result[$p]->minimum_stock > $result[$p]->current_stock){
							$WebsiteStock = "Out";
						}
						
						$is_website = "No";
						$is_cosmo = "No";
						$is_nandanson = "No";
						$is_pww = "No";
						$is_pca = "No";
						
						if($fd1!='')
						{
							if($WebsiteStock == "In"){
								$is_website = "Yes";
								$product_price = ($result[$p]->wholesale_price > 0) ? $result[$p]->wholesale_price : 0;
							}
						}
						if($fd2!='')
						{
							$product_price_cosmo = "";
							
							if($result[$p]->cosmo_sku != "" && $result[$p]->cosmo_current_stock > $check_stock_1){
								$is_cosmo = "Yes";
								$product_price_cosmo = ($result[$p]->cosmo_price > 0) ? $result[$p]->cosmo_price : 0;
								
							}else if($result[$p]->cosmo_sku != "" && $result[$p]->cosmo_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$check_categoryarray)){
								$is_cosmo = "Yes";
								$product_price_cosmo = ($result[$p]->cosmo_price > 0) ? $result[$p]->cosmo_price : 0;
							}
							
							if(isset($product_price_cosmo) && $product_price_cosmo > 0){
								$minimum_product_price_arr[] = ($product_price_cosmo > 0) ? $product_price_cosmo : 9999999999;	
								if($is_cosmo == "Yes" && $is_website == "No"){
									$product_price = $product_price_cosmo;
								}
							}
						}
						
						if($fd3!='')
						{
							$product_price_nand = "";
							
							if($result[$p]->nandansons_sku != "" && $result[$p]->nandansons_current_stock > $check_stock_1){
								$is_nandanson = "Yes";
								$product_price_nand = ($result[$p]->nandansons_price > 0) ? $result[$p]->nandansons_price : 0;
							}else if($result[$p]->nandansons_sku != "" && $result[$p]->nandansons_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$check_categoryarray)){
								$is_nandanson = "Yes";
								$product_price_nand = ($result[$p]->nandansons_price > 0) ? $result[$p]->nandansons_price : 0;
							}
							
							if(isset($product_price_nand) && $product_price_nand > 0){
								$minimum_product_price_arr[] = ($product_price_nand > 0) ? $product_price_nand : 9999999999;	
								if($is_nandanson == "Yes" && $is_cosmo == "No" && $is_website == "No"){
									$product_price = $product_price_nand;
								}
							}
						}
						if($fd4!='')
						{
							$product_price_prfm = "";
							
							if($result[$p]->perfumeworldwide_sku != "" && $result[$p]->perfumeworldwide_currentstock > $check_stock_1){
								$is_pww = "Yes";
								$product_price_prfm = ($result[$p]->perfumeworldwide_price > 0) ? $result[$p]->perfumeworldwide_price : 0;
							}else if($result[$p]->perfumeworldwide_sku != "" && $result[$p]->perfumeworldwide_currentstock > $check_stock_2 && in_array($result[$p]->category_id,$check_categoryarray)){
								$is_pww = "Yes";
								$product_price_prfm = ($result[$p]->perfumeworldwide_price > 0) ? $result[$p]->perfumeworldwide_price : 0;
							}
							
							if(isset($product_price_prfm) && $product_price_prfm > 0){
								$minimum_product_price_arr[] = ($product_price_prfm > 0) ? $product_price_prfm : 9999999999;	
								if($is_pww == "Yes" && $is_nandanson == "No" && $is_cosmo == "No" && $is_website == "No"){
									$product_price = $product_price_prfm;
								}
							}
						}
						if($fd5!='')
						{
							$product_price_pca = "";
							
							if($result[$p]->pca_sku != "" && $result[$p]->pca_current_stock > $check_stock_1){
								$is_pca = "Yes";
								$product_price_pca = ($result[$p]->pca_price > 0) ? $result[$p]->pca_price : 0;
							}else if($result[$p]->pca_sku != "" && $result[$p]->pca_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$check_categoryarray)){
								$is_pca = "Yes";
								$product_price_pca = ($result[$p]->pca_price > 0) ? $result[$p]->pca_price : 0;
							}
							
							if(isset($product_price_pca) && $product_price_pca > 0){
								$minimum_product_price_arr[] = ($product_price_pca > 0) ? $product_price_pca : 9999999999;	
								if($is_pca == "Yes" && $is_pww == "No" && $is_nandanson == "No" && $is_cosmo == "No" && $is_website == "No"){
									$product_price = $product_price_pca;
								}
							}
						}
					
					if($product_price <= 0){
						continue;
					 }
					$result[$p]->product_price = $product_price;						
					$result[$p]->minimum_product_price = $product_price;
					if(isset($minimum_product_price_arr) && count($minimum_product_price_arr) > 0){
						$result[$p]->minimum_product_price = min($minimum_product_price_arr);							
					}
					//get product price
					
					
					if($fd1!='' && $WebsiteStock == "In")
				    {
						for($i=0; $i<$tot_markup_rice; $i++)
						{
							if($db_recs[$i]->markup_value !="")
							{
								$mvalu = explode("-",$db_recs[$i]->markup_value);
								$mvalcount = count($mvalu);

								if($mvalcount>1)
								{
									$per = $db_recs[$i]->markup_percent;
									$result[$p]->{'wholesale_price_'.$i} = number_format(($result[$p]->product_price - $result[$p]->product_price*$per/100),2,".","");
								}
								else
								{
									$per = $db_recs[$i]->markup_percent;
									$result[$p]->{'wholesale_price_'.$i} = number_format(($result[$p]->product_price - $result[$p]->product_price*$per/100),2,".","");
								}
							}
						}
					}
					
					// $data_arr = array_map(function($item){return (array) $item;}, $result[$p]);
					$data_arr = (array)$result[$p];
					extract($data_arr);

					$products_id = $result[$p]->products_id;
					$SKU = trim($result[$p]->sku);

					$UPC = trim($result[$p]->UPC);

					$Product_Name = trim($result[$p]->product_name);
					$Product_Name = str_replace('"','""',$Product_Name);
					
					$CatNewName = "";
					if(preg_match('/unboxed/i',strtolower($Product_Name)) || preg_match('/unbox/i',strtolower($Product_Name)) || preg_match('/unboxes/i',strtolower($Product_Name)))
					{
						$CatNewName = "Unboxed";
					}
					
					$Product_Name = str_replace("Gift Set","",trim($Product_Name));
					$Product_Name = str_replace("Gift set","",trim($Product_Name));
					$Product_Name = str_replace("gift Set","",trim($Product_Name));
					$Product_Name = str_replace("Gifts Set","",trim($Product_Name));
					$Product_Name = str_replace("Gifts Sets","",trim($Product_Name));
					$Product_Name = str_replace("gift set","",trim($Product_Name));
					$Product_Name = str_replace("gifts set","",trim($Product_Name));
					$Product_Name = str_replace("gifts sets","",trim($Product_Name));
					$Product_Name = str_replace("giftset","",trim($Product_Name));
					$Product_Name = str_replace("giftsets","",trim($Product_Name));
					$Product_Name = str_replace("Giftset","",trim($Product_Name));
					$Product_Name = str_replace("Giftsets","",trim($Product_Name));
					$Product_Name = str_replace("GiftSet","",trim($Product_Name));
					$Product_Name = str_replace("GiftSets","",trim($Product_Name));
					$Product_Name = str_replace("Sets","",trim($Product_Name));
					$Product_Name = str_replace("sets","",trim($Product_Name));
					$Product_Name = str_replace("Set","",trim($Product_Name));
					$Product_Name = str_replace("set","",trim($Product_Name));
					$Product_Name = str_replace("sets","",trim($Product_Name));
					$Product_Name = str_replace("Spray","",trim($Product_Name));
					$Product_Name = str_replace("spray","",trim($Product_Name));
					$Product_Name = str_replace("perfume","",trim($Product_Name));
					$Product_Name = str_replace("Perfume","",trim($Product_Name));
					$Product_Name = str_replace("Spray","",trim($Product_Name));
					$Product_Name = str_replace("spray","",trim($Product_Name));
					
					$Product_NameArr = preg_split("/\bfor\b/iu", $Product_Name);
					
					if(!isset($Product_NameArr[1]) || $Product_NameArr[1]=='')
					{
						$CatNewName='';
					}
					
					if($result[$p]->gender == "W")
					{
						$result[$p]->gender = "L";
					}

					if($CatNewName!='')
					{
						$Product_Name = trim($Product_NameArr[0]).' '.$CatNewName;	
					}
					else
					{
						$Product_Name = trim($Product_NameArr[0]);
					}
					
					$Product_Description = trim($result[$p]->short_description);
					$Product_Description = str_replace('"','""',$Product_Description);
					
					if($Product_Description!='')
					{
						//$Product_Name .=" ".$Product_Description;
					}
					
					$perfumesize = "";
					if(preg_match('/pieces set/i',strtolower($Product_Description)) || preg_match('/pieces/i',strtolower($Product_Description)) || preg_match('/set/i',strtolower($Product_Description)) || preg_match('/piece set/i',strtolower($Product_Description))  || preg_match('/piece/i',strtolower($Product_Description)) || preg_match('/gift set/i',strtolower($Product_Description))  || preg_match('/gift sets/i',strtolower($Product_Description)) || preg_match('/gifts set/i',strtolower($Product_Description)) || preg_match('/gifts sets/i',strtolower($Product_Description)) || preg_match('/giftsets/i',strtolower($Product_Description))  || preg_match('/giftset/i',strtolower($Product_Description)) || preg_match('/sets/i',strtolower($Product_Description)))
					{
						$perfumesize = "Gift Set";
					}elseif(preg_match('/eau de parfum/i',strtolower($Product_Description)))
					{
						$perfumesize = "EDP";
					}elseif(preg_match('/eau de toilette/i',strtolower($Product_Description)))
					{
						$perfumesize = "EDT";
					}elseif(preg_match('/eau de cologne/i',strtolower($Product_Description)))
					{
						$perfumesize = "EDC";
					}
					
					$perfumesizetester = "";
					if(preg_match('/tester/i',strtolower($Product_Description)) || preg_match('/testers/i',strtolower($Product_Description)) || preg_match('/(tester)/i',strtolower($Product_Description)) || preg_match('/(testers)/i',strtolower($Product_Description)))
					{
						$perfumesizetester = "Tester";
					}
					
					if($perfumesize=='Gift Set')
					{
						$Product_Name = trim($Product_Name)." ".trim($perfumesize);
						if($result[$p]->gender!='')
						{
							$Product_Name = $Product_Name." (".trim($result[$p]->gender).")";
						}

					}else if($perfumesizetester=="")
					{
						$Product_Name = trim($Product_Name);
						if($result[$p]->gender !='')
						{
							$Product_Name = $Product_Name." (".trim($result[$p]->gender).")";
						}
						if($perfumesize!='')
						{
							$Product_Name = $Product_Name." ".trim($perfumesize);
						}
						if($result[$p]->size!='')
						{
							$Product_Name = $Product_Name." ".trim($result[$p]->size);
						}

					}
					
					if($perfumesizetester == "Tester")
					{
						$Product_Name = trim($Product_Name);
					    if($result[$p]->gender!='')
					    {
							$Product_Name = $Product_Name." (".	trim($result[$p]->gender).")";
						}
						if($perfumesize!='')
						{
							$Product_Name = $Product_Name." ".trim($perfumesize);
						}
						if($result[$p]->size!='')
						{
							$Product_Name = $Product_Name." ".trim($result[$p]->size);
						}
						$Product_Name = $Product_Name." (".trim($perfumesizetester).")";
					}
					
					/*$Product_Name = str_replace("perfume","",trim($Product_Name));
					$Product_Name = str_replace("Perfume","",trim($Product_Name));
					$Product_Name = str_replace("Spray","",trim($Product_Name));
					$Product_Name = str_replace("spray","",trim($Product_Name));
					*/
					
					$product_category = $result[$p]->category_name;

					if($product_category=="Men")
					{
						$product_category = "M";
					}elseif($product_category=="Women"){
						$product_category = "W";
					}elseif($product_category=="Unisex"){
						$product_category = "U";
					}elseif($product_category=="Kids"){
						$product_category = "K";
					}elseif($product_category=="Women Testers"){
						$product_category = "W Testers";
					}elseif($product_category=="Women Gift Sets"){
						$product_category = "W Gift Sets";
					}elseif($product_category=="Men Gift Sets"){
						$product_category = "M Gift Sets";
					}elseif($product_category=="Unisex Testers"){
						$product_category = "U Testers";
					}elseif($product_category=="Unisex Gift Sets"){
						$product_category = "U Gift Sets";
					}elseif($product_category=="Men Testers"){
						$product_category = "M Testers";
					}
					$manufacturer = $result[$p]->vmanufacture;
					
					if(file_exists(config('global.PRD_LARGE_IMG_PATH') . $result[$p]->image) and ! empty($result[$p]->image)) {
						$mainImageUrl = config('global.PRD_LARGE_IMG_URL').$result[$p]->image;

					} else {
						$mainImageUrl = config('global.NO_IMAGE_LARGE');
					}
					
					$Wholesale_Price = number_format($result[$p]->product_price,2,".","");
					$retail_price = number_format($result[$p]->retail_price,2,".","");
					
					$warehouse = "FD1";
					$category_idArr = array(15,39,74,203,7,8,11,27);
					
					if($WebsiteStock == "Out" && $fd1!='')
					{
						
						$diffrence = 0;
						if(isset($result[$p]->minimum_product_price)){
							$diffrence = $result[$p]->product_price - $result[$p]->minimum_product_price;
						}
						// echo $product_price."==".$result[$p]->sku."==".$result[$p]->current_stock."==".$result[$p]->product_price."==".$result[$p]->minimum_product_price."<br>";
						if($diffrence > 0.50)
						{
							if($result[$p]->cosmo_sku!='' && ($result[$p]->cosmo_current_stock > $check_stock_1 || ($result[$p]->cosmo_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr)))  && $fd2!='' && $result[$p]->cosmo_wholesale_price > 0 && $result[$p]->cosmo_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->cosmo_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								 $warehouse = "FD2";
								 $result[$p]->product_price =  $result[$p]->minimum_product_price;
							}else if($result[$p]->nandansons_sku != '' && ($result[$p]->nandansons_current_stock > $check_stock_1 || ($result[$p]->nandansons_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd3!='' && $result[$p]->nandansons_wholesale_price > 0 && $result[$p]->nandansons_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->nandansons_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD3";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}else if($result[$p]->perfumeworldwide_sku!='' && ($result[$p]->perfumeworldwide_currentstock > $check_stock_1 || ($result[$p]->perfumeworldwide_currentstock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd4!='' && $result[$p]->perfumeworldwide_wholesale_price > 0 && $result[$p]->perfumeworldwide_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->perfumeworldwide_currentstock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD4";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}else if($result[$p]->pca_sku != '' && ($result[$p]->pca_current_stock > $check_stock_1 || ($result[$p]->pca_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd5!='' && $result[$p]->pca_wholesale_price > 0 && $result[$p]->pca_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->pca_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD5";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}
						}else{
							if($result[$p]->cosmo_sku !='' && ($result[$p]->cosmo_current_stock > $check_stock_1 || ($result[$p]->cosmo_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd2!='' && $result[$p]->cosmo_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->cosmo_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								 $warehouse = "FD2";
								 
							}else if($result[$p]->nandansons_sku != '' && ($result[$p]->nandansons_current_stock > $check_stock_1 || ($result[$p]->nandansons_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd3!='' && $result[$p]->nandansons_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->nandansons_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD3";
							}
							else if($result[$p]->perfumeworldwide_sku != '' && ($result[$p]->perfumeworldwide_currentstock > $check_stock_1 || ($result[$p]->perfumeworldwide_currentstock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd4!='' && $result[$p]->perfumeworldwide_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->perfumeworldwide_currentstock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD4";
							}
							else if($result[$p]->pca_sku != '' && ($result[$p]->pca_current_stock > $check_stock_1 || ($result[$p]->pca_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd5!='' && $result[$p]->pca_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->pca_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD5";
							}
							
						}
					}
					
					if($fd1=='')
					{
					    $diffrence = 0;
						if(isset($result[$p]->minimum_product_price)){
							$diffrence = $result[$p]->product_price - $result[$p]->minimum_product_price;
						}
						
						if($diffrence > 0.50)
						{
							if($result[$p]->cosmo_sku != '' && ($result[$p]->cosmo_current_stock > $check_stock_1 || ($result[$p]->cosmo_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd2!='' && $result[$p]->cosmo_wholesale_price > 0 && $result[$p]->cosmo_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->cosmo_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD2";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}else if($result[$p]->nandansons_sku!='' &&  ($result[$p]->nandansons_current_stock > $check_stock_1 || ($result[$p]->nandansons_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd3!='' && $result[$p]->nandansons_wholesale_price > 0 && $result[$p]->nandansons_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->nandansons_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD3";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}
							else if($result[$p]->perfumeworldwide_sku!='' &&  ($result[$p]->perfumeworldwide_currentstock > $check_stock_1 || ($result[$p]->perfumeworldwide_currentstock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd4!='' && $result[$p]->perfumeworldwide_wholesale_price > 0 && $result[$p]->perfumeworldwide_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->perfumeworldwide_currentstock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD4";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}
							else if($result[$p]->pca_sku!='' &&   ($result[$p]->pca_current_stock > $check_stock_1 || ($result[$p]->pca_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd5!='' && $result[$p]->pca_wholesale_price > 0 && $result[$p]->pca_price == $result[$p]->minimum_product_price)
							{
								$result[$p]->current_stock = $result[$p]->pca_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD5";
								$result[$p]->product_price =  $result[$p]->minimum_product_price;
							}
							
						}
						else
						{
							if($result[$p]->cosmo_sku != '' && ($result[$p]->cosmo_current_stock > $check_stock_1 || ($result[$p]->cosmo_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd2!='' && $result[$p]->cosmo_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->cosmo_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD2";
							}else if($result[$p]->nandansons_sku!='' &&  ($result[$p]->nandansons_current_stock > $check_stock_1 || ($result[$p]->nandansons_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd3!='' && $result[$p]->nandansons_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->nandansons_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD3";
							}
							else if($result[$p]->perfumeworldwide_sku!='' && ($result[$p]->perfumeworldwide_currentstock > $check_stock_1 || ($result[$p]->perfumeworldwide_currentstock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd4!='' && $result[$p]->perfumeworldwide_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->perfumeworldwide_currentstock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD4";
							}
							else if($result[$p]->pca_sku!='' &&  ($result[$p]->pca_current_stock > $check_stock_1 || ($result[$p]->pca_current_stock > $check_stock_2 && in_array($result[$p]->category_id,$category_idArr))) && $fd5!='' && $result[$p]->pca_wholesale_price > 0)
							{
								$result[$p]->current_stock = $result[$p]->pca_current_stock;
								if($result[$p]->current_stock > 300)
								{
									$result[$p]->current_stock = 300;
								}
								$warehouse = "FD5";
							}
							
						}
					}
					
					if($WebsiteStock == "In" && $fd1!='')
					{
						if(isset($result[$p]->wholesale_price_3)){
							$result[$p]->product_price = $result[$p]->wholesale_price_3;
						}
					}
					
					if($WebsiteStock == "Out" && $fd1!='')
					{
						if($result[$p]->product_price < 7)
						{
							$result[$p]->product_price = $result[$p]->product_price + 0.75;
						}
						else if($result[$p]->product_price >= 7 && $result[$p]->product_price <= 20)
						{
							$result[$p]->product_price = $result[$p]->product_price + 1.25;
						}
						else if($result[$p]->product_price > 20 && $result[$p]->product_price <= 35)
						{
							$result[$p]->product_price = $result[$p]->product_price + 1.75;
						}
						else if($result[$p]->product_price > 35 && $result[$p]->product_price <= 50)
						{
							$result[$p]->product_price = $result[$p]->product_price + 2.25;
						}
						else if($result[$p]->product_price > 50 && $result[$p]->product_price <= 65)
						{
							$result[$p]->product_price = $result[$p]->product_price + 3.25;
						}
						else if($result[$p]->product_price > 65 && $result[$p]->product_price <= 80)
						{
							$result[$p]->product_price = $result[$p]->product_price + 4;
						}
						else if($result[$p]->product_price > 80 && $result[$p]->product_price <= 95)
						{
							$result[$p]->product_price = $result[$p]->product_price + 5;
						}
						else if($result[$p]->product_price > 95 && $result[$p]->product_price <= 110)
						{
							$result[$p]->product_price = $result[$p]->product_price + 5.75;
						}
						else if($result[$p]->product_price > 110 && $result[$p]->product_price <= 125)
						{
							$result[$p]->product_price = $result[$p]->product_price + 6.25;
						}
						else if($result[$p]->product_price > 125)
						{
							$result[$p]->product_price = $result[$p]->product_price + 8;
						}
					}

					if($fd1=='')
					{
						if($result[$p]->product_price < 7)
						{
							$result[$p]->product_price = $result[$p]->product_price + 0.75;
						}
						else if($result[$p]->product_price >= 7 && $result[$p]->product_price <= 20)
						{
							$result[$p]->product_price = $result[$p]->product_price + 1.25;
						}
						else if($result[$p]->product_price > 20 && $result[$p]->product_price <= 35)
						{
							$result[$p]->product_price = $result[$p]->product_price + 1.75;
						}
						else if($result[$p]->product_price > 35 && $result[$p]->product_price <= 50)
						{
							$result[$p]->product_price = $result[$p]->product_price + 2.25;
						}
						else if($result[$p]->product_price > 50 && $result[$p]->product_price <= 65)
						{
							$result[$p]->product_price = $result[$p]->product_price + 3.25;
						}
						else if($result[$p]->product_price > 65 && $result[$p]->product_price <= 80)
						{
							$result[$p]->product_price = $result[$p]->product_price + 4;
						}
						else if($result[$p]->product_price > 80 && $result[$p]->product_price <= 95)
						{
							$result[$p]->product_price = $result[$p]->product_price + 5;
						}
						else if($result[$p]->product_price > 95 && $result[$p]->product_price <= 110)
						{
							$result[$p]->product_price = $result[$p]->product_price + 5.75;
						}
						else if($result[$p]->product_price >  110 && $result[$p]->product_price <= 125)
						{
							$result[$p]->product_price = $result[$p]->product_price + 6.25;
						}
						else if($result[$p]->product_price > 125)
						{
							$result[$p]->product_price = $result[$p]->product_price + 8;
						}
					}
					
					$result[$p]->product_price = number_format($result[$p]->product_price,2,".","");
					
					$product_price = 0;
					if($result[$p]->product_price > 0)
					{
						$product_price	= floor($result[$p]->product_price);

						$fraction = $result[$p]->product_price - $product_price;
						$fraction = number_format($fraction,2,".","");

						if($fraction >= 0.01  && $fraction <= 0.25)
						{
							$product_price = $product_price + 0.25;
						}
						else if($fraction > 0.25  && $fraction <= 0.50)
						{
							$product_price = $product_price + 0.50;
						}
						else if($fraction > 0.50  && $fraction <= 0.75)
						{
							$product_price = $product_price + 0.75;
						}
						else if($fraction > 0.75  && $fraction <= 0.99)
						{
							$product_price = $product_price + 1;
						}

					}
					
					$OrderQuantity ='';
					
					if($WebsiteStock == "In" && $fd1!='' &&  $result[$p]->special_website_price > 0)
					{
						//$product_price = $result[$p]['special_website_price'];
						$OCost = "";
						if($result[$p]->w_our_cost > 0){
							$OCost = $result[$p]->w_our_cost * 1.05;
						}
						if($result[$p]->special_website_price >0 && $result[$p]->current_stock > 8)
						{
							if($result[$p]->special_website_price < $OCost)
							{
								//echo "<font color='Red'>$".$result[$p]["special_website_price"]."</font>";
								$product_price = number_format($result[$p]->wholesale_price,2,".","");
							}else{
								$product_price = number_format($result[$p]->special_website_price,2,".","");
							}
						}else if($result[$p]->current_stock > 8 && $result[$p]->wholesale_price > 0){
							for($k=0; $k < $tot_markup_rice; $k++)
							{
								if($db_recs[$k]->markup_value != "")
								{
									$mvalu = explode("-",$db_recs[$k]->markup_value);
									$mvalcount = count($mvalu);

									if($mvalcount>1)
									{
										$per = $db_recs[$k]->markup_percent;
										$result[$p]->{'wholesale_price_'.$p} = number_format(($result[$p]->wholesale_price - $result[$p]->wholesale_price *$per/100),2,".","");
									}
									else
									{
										$per = $db_recs[$k]->markup_percent;
										$result[$p]->{'wholesale_price_'.$p} = number_format(($result[$p]->wholesale_price - $result[$p]->wholesale_price*$per/100),2,".","");
									}
								}
							}

							if(isset($result[$p]->wholesale_price_3) && $result[$p]->wholesale_price_3 > 0)
							{
								$wholesale_price_3	= floor($result[$p]->wholesale_price_3);

								$fraction = $result[$p]->wholesale_price_3 - $wholesale_price_3;

								$fraction = number_format($fraction,2,".","");

								if($fraction >= 0.01  && $fraction <= 0.25)
								{
									$wholesale_price_3 = $wholesale_price_3 + 0.25;
								}
								else if($fraction > 0.25  && $fraction <= 0.50)
								{
									$wholesale_price_3 = $wholesale_price_3 + 0.50;
								}
								else if($fraction > 0.50  && $fraction <= 0.75)
								{
									$wholesale_price_3 = $wholesale_price_3 + 0.75;
								}
								else if($fraction > 0.75  && $fraction <= 0.99)
								{
									$wholesale_price_3 = $wholesale_price_3 + 1;
								}
								$product_price = $wholesale_price_3;
							}
						}
					}
					
					$csv_data[$rc][] = $SKU;
					$csv_data[$rc][] = $Product_Name;
					$csv_data[$rc][] = $product_category;
					$csv_data[$rc][] = $manufacturer;
					$csv_data[$rc][] = $UPC;
					$csv_data[$rc][] = $product_price;
					$csv_data[$rc][] = $result[$p]->current_stock;
					$csv_data[$rc][] = $mainImageUrl;
					$csv_data[$rc][] = $OrderQuantity;
					$csv_data[$rc][] = $warehouse;
					
					$rc=$rc+1;
				}
				$start_limit = $start_limit+$end_limit;
				$process_batch 	= $process_batch + 1;

				// if($_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.9.87"){
					// echo "<pre>";print_r($csv_data);exit;
				// }
				if($process_batch == $total_batch){
					//$workbook->send($export_file_name);
					// $workbook->close();
				}
			}
		}
		
		// $header_row = ['Item#','Product Name','Category','Brand Name','UPC','Price','Quantity','Image','Order Quantity','Warehouse'];
		// array_unshift($csv_data,$header_row);
		// echo "<pre>";print_r($csv_data);exit;
		
		// if($_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.9.87"){
			// echo "<pre>";print_r($csv_data);exit;
		// }
		if(!empty($csv_data) && count($csv_data) > 0)
		{
			$header_row = ['Item#','Product Name','Category','Brand Name','UPC','Price','Quantity','Image','Order Quantity','Warehouse'];
			return Excel::download(new ExportOrders($csv_data, $header_row,'SpecialProducts'), $export_file_name);
		} else {
			Session::flash('error', 'No Data Found!');
			// return redirect()->back();
			return redirect(config('global.SITE_URL').'/specialwholesaleproductpricelist');
		}
		
	}
	
	public function GetProductDetailAlert(Request $request)
	{
		$productid = $request['iproductid'];
		$sku = $request['vsku'];
		$alert = $request['alert'];
		if($alert == "yes"){
			$validatedData = $request->validate([
								'alert_email' => 'required|email'
					        ], [
					            'alert_email.required' => config('message.Validate.Email'),
					            'alert_email.email' => config('message.Validate.ValidEmail'),
					        ]);
					        
			$alert_product = array(
							'email'    => $request['alert_email'],
							'estatus'  => 'No',
							'prod_id'  => $productid,
							'sku'      => $sku,
					);
			$alert_id = Stockalert::create($alert_product);
			$ProductHTML = "Thank you for your request. We will email you as soon as the item is in stock.";
			
		}else{
			$prodData = DB::table('pu_products as p')
									->select('p.products_id','p.image','p.short_description','p.product_name','p.sku','p.UPC','p.product_description')
									->where('p.sku','=',$sku)
									->get();
			if ( count($prodData) > 0 ) 
			{
				if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prodData[0]->image) && $prodData[0]->image != '')
				{$thumb_image = config('global.PRD_THUMB_IMG_PATH').$prodData[0]->image; }
				else
				{$thumb_image = config('global.NO_IMAGE_THUMB');}
				
				//~ $prod_detail['iproductid'] = $prodData[0]->products_id;
				//~ $prod_detail['vsku'] = $prodData[0]->sku;
				//~ $prod_detail['product_name'] = $prodData[0]->product_name;
				//~ $prod_detail['image'] = $thumb_image;
				//~ $prod_detail['short_description'] = $prodData[0]->short_description;
				//~ $prod_detail['product_description'] = $prodData[0]->product_description;
				
				$prodData[0]->image = $thumb_image;
				$this->PageData['prod_detail'] = $prodData;
			}
			
			//echo "<pre>";print_r($this->PageData);exit;
			$ProductHTML = view('myaccount.wproductsnotifypopup')->with($this->PageData)->render();
		}
			
		return response()->json(array('ProductHTML'=>$ProductHTML));
	}
	
	public function ChangeCurrency(Request $request){
		$currency_id = $request['currency'];
		$currency_arr = config('Currencies');
		
		$success = "0";
		//echo "<pre>";print_r($currency_arr);exit;
		if($currency_id != "" && $currency_id > 0){
			$currency_data = $currency_arr->firstWhere('currency_id',$currency_id);
		
			if(count($currency_data) > 0 and $currency_data['exchange_rate'] > 0){
				Session::put('currency_code',$currency_data['currency_code']);
				Session::put('currency_symbol',$currency_data['currency_symbol']);
				Session::put('currency_rate',$currency_data['exchange_rate']);		
				
				$success = "1";			
			}
		}
		
		//echo "<pre>";print_r($get_currency);exit;
		return response()->json(array('success'=>$success));
	}
	
	public function DownloadPPL(Request $request){
		if(!Auth::user())
			return redirect('/login.html');
			
		$complete_newarrival_export_file_name_pdf='';
		
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Complete_NewArrival_Wholesale_Price_List.pdf"))
		{
			$complete_newarrival_export_file_name_pdf = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Complete_NewArrival_Wholesale_Price_List.pdf";
		}
		$complete_tester_export_file_name_pdf = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Tester_Wholesale_Price_List.pdf"))
		{
			$complete_tester_export_file_name_pdf = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Tester_Wholesale_Price_List.pdf";
		}
		$complete_giftset_export_file_name_pdf = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Giftset_Wholesale_Price_List.pdf"))
		{
			$complete_giftset_export_file_name_pdf = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Giftset_Wholesale_Price_List.pdf";
		}
		$complete_wholesale_export_file_name_pdf = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Complete_Wholesale_Price_List.pdf"))
		{
			$complete_wholesale_export_file_name_pdf = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Complete_Wholesale_Price_List.pdf";
		}
		$sunglasses_wholesale_export_file_name_pdf = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Sunglasses_Wholesale_Price_List.pdf"))
		{
			$sunglasses_wholesale_export_file_name_pdf = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Sunglasses_Wholesale_Price_List.pdf";
		}
		
		$complete_tester_export_file_name_xls = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Tester_Wholesale_Price_List.xls"))
		{
			$complete_tester_export_file_name_xls = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Tester_Wholesale_Price_List.xls";
		}
		$complete_giftset_export_file_name_xls = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Giftset_Wholesale_Price_List.xls"))
		{
			$complete_giftset_export_file_name_xls = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Giftset_Wholesale_Price_List.xls";
		}
		$complete_wholesale_export_file_name_xls = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Complete_Wholesale_Price_List.xls"))
		{
			$complete_wholesale_export_file_name_xls = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Complete_Wholesale_Price_List.xls";
		}
		$complete_newarrival_export_file_name_xls = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Complete_NewArrival_Wholesale_Price_List.xls"))
		{
			$complete_newarrival_export_file_name_xls = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Complete_NewArrival_Wholesale_Price_List.xls";
		}
		$sunglasses_wholesale_export_file_name_xls = '';
		if(file_exists(config('global.EXPORT_WHOLESALE_PRICE_LIST_PATH')."Sunglasses_Wholesale_Price_List.xls"))
		{
			$sunglasses_wholesale_export_file_name_xls = config('global.EXPORT_WHOLESALE_PRICE_LIST_URL')."Sunglasses_Wholesale_Price_List.xls";
		}
		
		//echo "<pre>";print_r($get_currency);exit;
		
		$this->PageData['complete_newarrival_export_file_name_pdf'] = $complete_newarrival_export_file_name_pdf;
		$this->PageData['complete_tester_export_file_name_pdf'] = $complete_tester_export_file_name_pdf;
		$this->PageData['complete_giftset_export_file_name_pdf'] = $complete_giftset_export_file_name_pdf;
		$this->PageData['complete_wholesale_export_file_name_pdf'] = $complete_wholesale_export_file_name_pdf;
		$this->PageData['sunglasses_wholesale_export_file_name_pdf'] = $sunglasses_wholesale_export_file_name_pdf;
		
		$this->PageData['complete_tester_export_file_name_xls'] = $complete_tester_export_file_name_xls;	
		$this->PageData['complete_giftset_export_file_name_xls'] = $complete_giftset_export_file_name_xls;
		$this->PageData['complete_wholesale_export_file_name_xls'] = $complete_wholesale_export_file_name_xls;
		$this->PageData['complete_newarrival_export_file_name_xls'] = $complete_newarrival_export_file_name_xls;
		$this->PageData['sunglasses_wholesale_export_file_name_xls'] = $sunglasses_wholesale_export_file_name_xls;
		
		return view('myaccount.downloadppl')->with($this->PageData);
	}

	function PhoneorderPayReceipt(Request $request){
		$OrderID = base64_decode($request['id']);
		$this->PageData['meta_title']  = "Order Receipt Print :: Fragrance Depot";
		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css','myaccount.css'];	
		$this->PageData['JSFILES'] = ['phoneorder_payment_receipt.js'];	
		
		if($OrderID == "" || $OrderID <=0) 
		{
			return redirect('/');
		}
		
		
		$OrderRs = Order::select('orders_id', 'orders_no','customer_id', 'sub_total', 'order_total', 'refund_amount', 'pay_status', 'status', 'payment_type', 'use_credit_limit', 'payment_method','payment_gateway_response','order_datetime','phoneorder_paymentdate')
							->where('orders_id', '=', $OrderID)
							->get();
		
		if($OrderRs->count() <= 0) 
		{
			return redirect('/');
		}
		
		if($OrderRs[0]->order_total == "" || $OrderRs[0]->order_total < 0){
			$OrderRs[0]->order_total = 0;
		}
		$customer_id = $OrderRs[0]->customer_id;
		
		//echo "Encryption: ".$this->encrypt("sk_live_le79xVg0h7UgoRfE7NHhNquq00Bi11RGmn");echo "<br>Decryption: ".$this->decrypt("ASoA1f/Rxr7Lw/29tsfEwJXWrMa5wpDdvsiu75yW2tPA2Pr9y46LociLuKqeyM0=");exit;
		
		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->whereIn('pm_group_name', ['PAYMENT_STRIPE','PAYMENT_PAYWITHAFTERPAY','PAYMENT_PAYPALEC','PAYMENT_PAYWITHAMAZON'])
							->where('pm_status', '=', 'Active')
							->get();
				
		$IsStripeCheckout = "No";
		$arrAuthnetVar = array();		
		$IsPaypalExpressCheckout = "No";
		$PaypalActionMode = "";		
		$IsPayWithAmazonCheckout ='No';
		$Afterpay_Checkout ='No';
		
		$tot_records = $db_res->count();
		if( $tot_records > 0)
		{
			for($i=0; $i < $tot_records ; $i++){
				if($db_res[$i]->pm_group_name == "PAYMENT_STRIPE"){
					$IsStripeCheckout = "Yes";
					$arrAuthnetVar = unserialize($db_res[$i]->pm_details);

					$STRIPE_KEY  = $arrAuthnetVar['Secret_Key'];
					$PUBLISH_KEY = $arrAuthnetVar['Publishable_Key'];

					#############################
					
					$STRIPE_KEY   = $this->decrypt($STRIPE_KEY);
					$PUBLISH_KEY   = $this->decrypt($PUBLISH_KEY);
					#############################
				}else if($db_res[$i]->pm_group_name == "PAYMENT_PAYWITHAFTERPAY"){
					$Afterpay_Checkout ='Yes';
				}else if($db_res[$i]->pm_group_name == "PAYMENT_PAYPALEC"){
					$IsPaypalExpressCheckout ='Yes';
					$PaypalActionMode = "sandbox";
				}else if($db_res[$i]->pm_group_name == "PAYMENT_PAYWITHAMAZON"){
					$IsPayWithAmazonCheckout ='Yes';
				}
			}	
		}
		
		$cust_detail = Customer::select('eusertype')
								->where('customer_id', '=', $customer_id)
								->get();
		
		if($cust_detail->count() > 0 && $cust_detail[0]->eusertype == "Wholesaler"){
			$Afterpay_Checkout ='No';
		}
		// echo $Afterpay_Checkout;exit;
		
			if($_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.6.77"){
				 config(['app.debug' => true]);
			}
		if($Afterpay_Checkout == "Yes"){
			$payload = array();
			
			$getconfigs = $this->GetAfterPayResult($payload,"configuration","No");
			
			$Min_AP = $getconfigs['minimumAmount']['amount'];
			$Max_AP = $getconfigs['maximumAmount']['amount'];

			$Min_AP_AMT = round($Min_AP * 100);
			$Max_AP_AMT = round($Max_AP * 100);
			
			$this->PageData["Min_AP_AMT"] = $Min_AP_AMT;
			$this->PageData["Max_AP_AMT"] = $Max_AP_AMT;
			
			if($OrderRs[0]->order_total < $Min_AP || $OrderRs[0]->order_total > $Max_AP){
				$Afterpay_Checkout = "No";
			}
		}
		
		// echo $Afterpay_Checkout."<br>".$PUBLISH_KEY;exit;
		$Payment_Method_Message = "";
		if(($OrderRs[0]->payment_type == 'PAYMENT_AUTHORIZENETCC' || $OrderRs[0]->payment_type =='PAYMENT_PAYPALCC') and $OrderRs[0]->payment_gateway_response !='')
		{
			$arr_gateway_response = explode(",",$OrderRs[0]->payment_gateway_response);
			
			if ($arr_gateway_response[0] == 4)
			{
				$Payment_Method_Message = "<h3>Thank you! Your order will be processed pending a standard transaction review.</h3>
			<p>We hope you enjoyed shopping with us. Your order will be processed as soon as possible. We will contact you with updates. <br />Please allow 24hrs to process the payment. An E-mail Confirmation will be sent upon payment received.</p>";
			}
		}
		// echo $OrderRs[0]->phoneorder_paymentdate;exit;
		
		$OrderRs[0]['datetime_order'] = date("m/d/Y H:i:s",$OrderRs[0]->order_datetime);
		
		// if($OrderRs[0]->phoneorder_paymentdate != "0000-00-00 00:00:00"){
			// $OrderRs[0]->phoneorder_paymentdate = date("m/d/Y H:i:s",strtotime($OrderRs[0]->phoneorder_paymentdate));
		// }
		
		//$this->PageData['CSSFILES'] = ['myaccount.css'];	
		$this->PageData['Payment_Method_Message'] = $Payment_Method_Message;	
		
		$viewInvoiceUrl = config('global.SITE_URL').'invoice/'.base64_encode($OrderRs[0]->orders_no.'.pdf');
		$this->PageData['viewInvoiceUrl'] = $viewInvoiceUrl;	
		
		$payInvoiceUrl = config('global.SITE_URL').'stripe/phoneorder';
		$this->PageData['payInvoiceUrl'] = $payInvoiceUrl;	
		
		$this->PageData['OrderID'] = base64_encode($OrderID);	
		$this->PageData['OrderRs'] = $OrderRs[0];	
		
		$this->PageData["PaypalActionMode"] = $PaypalActionMode;
		$this->PageData["Afterpay_Checkout"] = $Afterpay_Checkout;
		$this->PageData["IsPayWithAmazonCheckout"] = $IsPayWithAmazonCheckout;
		$this->PageData["IsPaypalExpressCheckout"] = $IsPaypalExpressCheckout;
		$this->PageData["IsStripeCheckout"] = $IsStripeCheckout;
		
		Session::forget('phoneorder_detail');
		Session::put('phoneorder_detail.order_id',$OrderID);
		Session::put('phoneorder_detail.order_amt',$OrderRs[0]->order_total);
		Session::put('phoneorder_detail.customer_id',$OrderRs[0]->customer_id);
		
		if($Afterpay_Checkout == "Yes" && isset($Min_AP_AMT) && isset($Max_AP_AMT)){
			Session::put('phoneorder_detail.Afterpay.Min_AP_AMT',$Min_AP_AMT);
			Session::put('phoneorder_detail.Afterpay.Max_AP_AMT',$Max_AP_AMT);
		}
		
		$Checkout_Available = "Yes";
		if($IsStripeCheckout == "No" && $IsPaypalExpressCheckout == "No" && $IsPayWithAmazonCheckout == "No" && $Afterpay_Checkout == "No"){
			$Checkout_Available = "No";
		}
		$this->PageData["Checkout_Available"] = $Checkout_Available;

		$this->SetAmazonConfig('phoneorder_payment_receipt');
		
		// echo "<pre>";print_r($this->PageData);exit;
		return view('myaccount.phoneorderpayreceipt')->with($this->PageData);
	}
	
	public function PhoneorderPayReceiptResponse(Request $request){
		$Id_order = $request['id'];
		$OrderID = base64_decode($Id_order);
		$success = base64_decode($request['success']);
		
		if($success == 1){
			$response_arr = $this->PhoneorderPaymentSuccess('Stripe');
			if($response_arr['success'] == "1"){
				Session::flash('success',$response_arr['err_msg']);
			}else{
				Session::flash('error',$response_arr['err_msg']);
			}	
		}else{
			$err_msg = "Something went wrong, payment failed.";
			Session::flash('error',$err_msg);
		}
		
		return redirect(config('global.SITE_URL')."payment/".$Id_order);
		// echo "<pre>";print_r($this->PageData);exit;
		// return view('myaccount.phoneorderpayreceipt')->with($this->PageData);
	}
	
	public function ProductPage(Request $request)
	{
		$this->PageData['CSSFILES'] = ['listing.css','jquery-ui-slider.css','listpage.css','custom.css'];	
		$this->PageData['JSFILES'] = ['jquery-1.11.3.js','jquery.mCustomScrollbar.concat.min.js','jquery-ui-slider.min.js','listing_page.js'];
		$this->PageData['ListFrom'] = 'Category'; 
		$ProdCat = $request->category_id;
		$this->PageData['SelCat'] = $request->category_id;
		$this->PageData['SelectedCat'] = [$request->category_id];
		
		$SetFilters = $this->SetFilters($request);
		if(isset($SetFilters['categories']) && count($SetFilters['categories']) > 0){
			$ProdCat = $SetFilters['categories'];
			$this->PageData['SelectedCat'] = $ProdCat;
			$this->PageData['SelCat'] = implode(',',$ProdCat);
		}
				
		$ProductsDetails = $this->GetProductsNew('ProductListPage',$ProdCat,12,[]);
		$Products = $ProductsDetails['Products'];
		$TotalProducts = $ProductsDetails['TotalProducts'];
		$this->PageData['Products'] = $Products;
		$this->PageData['TotalProducts'] = $TotalProducts;
		//dd($ProductsDetails['LeftFilters']);
		$this->PageData['Filters'] = $ProductsDetails['LeftFilters'];
		
		if(isset($request->category_id) && $request->category_id != '')
		{
			$CatDetails = Category::find($request->category_id);
			$this->PageData['PageTitle'] = ucwords(remove_special_chars($CatDetails->category_name));
			$ParentID = $this->SetParentID($CatDetails);
			$this->PageData['Category'] = $CatDetails;
		} else { 
			$ParentID = 0;
		}
		$Bredcrum = $this->Bredcrum($request);
		$this->PageData['Bredcrum'] = $Bredcrum['BredLink'];
		$this->PageData['PageTitle'] = $Bredcrum['PageTitle'];
		
		$this->PageData['MinPrice'] = 0;
		$this->PageData['MaxPrice'] = 0;
		
		$this->PageData['PageName'] = $request->category_name;
		
		return view('product.test')->with($this->PageData);
	}

	public function AmazonPhoneOrderCheckout(Request $request)
	{
		$OrderID = Session::get('phoneorder_detail.order_id');
		
		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];	
		$this->PageData['JSFILES'] = ['phoneorder_payment_receipt.js'];		
		
		$this->PageData['meta_title'] = "Amazon Payment :: ".config('Settings.SITE_TITLE');
		
		$Id_order = base64_encode($OrderID);
		$this->PageData['back_url'] = config('global.SITE_URL')."payment/".$Id_order;
		$this->SetAmazonConfig('phoneorder_payment_receipt');
		
		$updAray = array (
							'status'			=> 'Pending - Phoneorder',
							'payment_type' 		=> 'PAYMENT_PAYWITHAMAZON',
							'payment_method' 	=> 'Pay With Amazon'
						 );
		$uporderres = Order::Where("orders_id","=",$OrderID)->update($updAray);	 
		return view('checkout.amazon-phoneordercheckout')->with($this->PageData);
	}
	
	public function PhoneorderDownloadInvoice(Request $request){
		$invoice_no = $request['invoice_no'];

		$pdfFile = 'phoneorder_invoice/Invoice-'.base64_decode($invoice_no);

		//echo $pdfFile;exit;
		$downloadName = 'Invoice.pdf';
		header('Content-Type:application/pdf');
		header('Content-Length:'.filesize($pdfFile));
		header('Content-Transfer-Encoding:Binary');
		header('Content-Disposition: attachment;filename='.$downloadName);
		readfile($pdfFile);
		exit;
	}
	
}
?>
