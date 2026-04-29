<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\ProductDetailTrait;
use App\Http\Controllers\Traits\VendorTrait;
use App\Http\Controllers\Traits\CartTrait;
use App\Models\MetaInfo;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMethod;
use DateTime;
use Illuminate\Contracts\Session\Session as SessionSession;
use Illuminate\Support\Facades\DB;
use Session;
use Illuminate\Support\Facades\Blade;
use Cache;

class CategoryController extends Controller
{
	use CommonTrait;
	use VendorTrait;
	use ProductDetailTrait;
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
	public function CategoryPage(Request $request)
	{
		$this->PageData['CSSFILES'] = ['slick.css', 'category_page.css', 'listing.css'];
		$this->PageData['JSFILES'] = ['jquery.mCustomScrollbar.concat.min.js','slick.js', 'category_page.js','listing_page.js'];
		
		$GTMDATA = ['page' => 'category', 'pagetype' => 'category'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		
		if (!isset($request->category_id))
			return redirect('/');
		$CategoryID = $request->category_id;
		$CatDetails = Category::where('category_id', '=', $CategoryID)->where('status', '=', '1')->get();
	
		if($CatDetails->count() == 0){
		    return redirect('/404');
		}
		if ($CatDetails[0]->parent_id == '0') {
			$CatLink = remove_special_chars($CatDetails[0]->category_name);
			$catprod_link = config('global.SITE_URL') . $CatLink . "/p4u/cid-" . $CatDetails[0]->category_id . "/view";
			return redirect($catprod_link);
		}

		if ($CatDetails->count() == 0) {
			return redirect('/');
		} else {
			$this->PageData['BannerImage'] = '';
			$this->PageData['BannerImageMobile'] = '';
			$this->PageData['category_banner_image'] = "";
			$this->PageData['category_mob_banner_image'] ="";
			if (file_exists(config('global.CAT_IMAGE_PATH') . $CatDetails[0]->banner_image) && !empty($CatDetails[0]->banner_image)){
				$this->PageData['BannerImage'] = config('global.CAT_IMAGE_URL') . $CatDetails[0]->banner_image;
				$newimageVal = config('global.CAT_IMAGE_PATH').stripslashes($CatDetails[0]->banner_image);
				$verP =filemtime($newimageVal);
				$banner_image = config('global.CAT_IMAGE_URL').$CatDetails[0]->banner_image."?ver=".$verP;
				$this->PageData['category_banner_image'] = $banner_image;
			}else{
				$this->PageData['category_banner_image'] = config('global.CAT_IMAGE_URL').'Banner_1.jpg';
			}
			if (file_exists(config('global.CAT_IMAGE_PATH') . $CatDetails[0]->mob_banner_image) && !empty($CatDetails[0]->mob_banner_image)){
				$this->PageData['BannerImageMobile'] = config('global.CAT_IMAGE_URL') . $CatDetails[0]->mob_banner_image;
				$newimageVal = config('global.CAT_IMAGE_PATH').stripslashes($CatDetails[0]->mob_banner_image);
				$verP =filemtime($newimageVal);
				$mob_banner_image = config('global.CAT_IMAGE_URL').$CatDetails[0]->mob_banner_image."?ver=".$verP;
				$this->PageData['category_mob_banner_image'] = $mob_banner_image;
			}else{
				$this->PageData['category_mob_banner_image'] = config('global.CAT_IMAGE_URL').'Banner_1.jpg';
			}

			$this->PageData['Category'] = $CatDetails[0];
			$CatInfo = config('CATEGORY_INFO');
			$breadcrumbs = $CatInfo['CatForProd'][$CatDetails[0]->category_id]['subcatbredcrum'];
			$this->PageData['Bredcrum'] = $breadcrumbs;
			$this->PageData['PageTitle'] = $breadcrumbs;

			$PageType = 'CT';
			$MetaInfo = MetaInfo::where('type', '=', $PageType)->get();

			if (!empty($CatDetails[0]->meta_title)) {
				$this->PageData['meta_title'] =  stripslashes($CatDetails[0]->meta_title);
			} elseif ($MetaInfo->count() > 0 && !empty($MetaInfo[0]->meta_title)) {
				$meta_title = str_replace('{$category_name}', $CatDetails[0]->category_name, $MetaInfo[0]->meta_title);
				$this->PageData['meta_title'] = stripslashes($meta_title);
			}
			if (!empty($CatDetails[0]->meta_keywords)) {
				$this->PageData['meta_keywords'] =  stripslashes($CatDetails[0]->meta_keywords);
			} elseif ($MetaInfo->count() > 0 && !empty($MetaInfo[0]->meta_keywords)) {
				$meta_keywords = str_replace('{$category_description}', $CatDetails[0]->description, $MetaInfo[0]->meta_keywords);
				$this->PageData['meta_keywords'] = stripslashes($meta_keywords);
			}
			if (!empty($CatDetails[0]->meta_description)) {
				$this->PageData['meta_description'] =  stripslashes($CatDetails[0]->meta_description);
			} elseif ($MetaInfo->count() > 0 && !empty($MetaInfo[0]->meta_keywords)) {
				$meta_description = str_replace('{$category_description}', $CatDetails[0]->description, $MetaInfo[0]->meta_description);
				$this->PageData['meta_description'] = stripslashes($meta_description);
			}
			$CatProductDetails = $this->GetProducts('CategoryPage', $CatDetails[0]->category_id);
			$CatProducts = $CatProductDetails['Products'];
			$TotalCatProds = 0;
            if($CatProductDetails['TotalProducts'] >0 )
                $TotalCatProds = $CatProductDetails['TotalProducts'];
			$this->PageData['TotalCatProds'] = $TotalCatProds;
            $CatParent = Category::find($CatDetails[0]->parent_id);
			$Parent = remove_special_chars($CatParent->category_name);
			$SubCat = remove_special_chars($CatDetails[0]->category_name);

			$this->PageData['CatProducts'] = $CatProducts;
			$ViewMoreLink = config('global.SITE_URL') . $Parent . $SubCat . "/p4u/cid-" . $CatDetails[0]->category_id . "/view";
			$this->PageData['ViewMoreLink'] = $ViewMoreLink;


			$NewArrivals = $this->ProductSlider('NEW ARRIVALS','Home',$CategoryID);
			$this->PageData['NewArrivals'] = $NewArrivals[0]['products'];
			$this->PageData['NewArrivalsAttr'] = [
				'Title' => 'New Arrivals',
				'Slider' => 'home-new-sl',
				'SeeMore' => config('global.SITE_URL').'new-arrivals/p4u/special-na/view',
			];


			$BestSellers = $this->ProductSlider('TOP SELLERS','Home',$CategoryID);
			$this->PageData['BestSellers'] = $BestSellers[0]['products'];
			$this->PageData['BestSellersAttr'] = [
				'Title' => 'Best Sellers',
				'Slider' => 'home-new-sl',
				'SeeMore' => config('global.SITE_URL').'top-sellers/p4u/special-ts/view',
			];

		}

		return view('category.index')->with($this->PageData);
	}

	public function ProductDetails(Request $request)
	{
		$this->PageData['CSSFILES'] = ['slick.css', 'detail.css','static.css','xzoom.css','magnific-popup.css'];
		//$this->PageData['JSFILES'] = ['afterpay.js','slick.js', 'easyResponsiveTabs.js', 'productdetail.js', 'jquery.zoom.js', 'moment.min.js','jquery.magnific-popup.min.js','xzoom.min.js'];
		$this->PageData['JSFILES'] = ['afterpay.js','slick.js', 'easyResponsiveTabs.js', 'productdetail.js', 'zoom.js', 'moment.min.js','jquery.magnific-popup.min.js'];

		$products_id = $request->products_id;

		if ($products_id == '' || !is_numeric($products_id) || empty($products_id)) {
			return redirect('/');
		}
		$category_id = $request->category_id;
		$code = $request->code;
		
		
	
		
		
		$StaticPageArr = array('shipping_information', 'return_exchange_policy');

		$Shipping_info = getstaticpages($StaticPageArr);


		//	$Return_info   = getstaticpages('return_exchange_policy');
		$this->PageData['Shipping_info_Content'] =  '';
		if ($Shipping_info[0]->content != '') {
			$Shipping_info_Content = str_replace("<h1>", "", $Shipping_info[1]->content);
			$Shipping_info_Content = str_replace("</h1>", "", $Shipping_info_Content);
			
			if (str_contains($Shipping_info_Content, '{$Site_URL}')) {
				$Shipping_info_Content = str_replace('{$Site_URL}', config('global.SITE_URL'), $Shipping_info_Content);
			}
			
			$this->PageData['Shipping_info_Content'] = $Shipping_info_Content;
		}

		$this->PageData['Return_info_Content'] = '';
		if ($Shipping_info[1]->content != '') {
			$Shipping_info[1]->content = str_replace('{$titles}', '', $Shipping_info[0]->content);
			$Return_info_Content = str_replace("<h1>", "", $Shipping_info[1]->content);
			$Return_info_Content = str_replace("</h1>", "", $Return_info_Content);
			
			if (str_contains($Return_info_Content, '{$Site_URL}')) {
				$Return_info_Content = str_replace('{$Site_URL}', config('global.SITE_URL'), $Return_info_Content);
			}

			$this->PageData['Return_info_Content'] = $Return_info_Content;
		}

		/*$preview = isset($request->preview) ? $request->preview : 0;
		$resize = $request->resize;*/
		
		$preview = 0;
		if(isset($request->private) && $request->private == 'preview')
			$preview = 1;
		
		$code = "";
		if(isset($request->code)) 
		{	
			if($request->code == 'preview')
				$preview = 1;
			else 
				$code = $request->code;
		}
		
		$resize = '';
		if(isset($request->resize)) 
		{
			if($request->resize == 'preview')
				$preview = 1;
			else 
				$resize = $request->resize;
		}

		$productDetail = $this->getProductsDetail($products_id, $preview, $code, $resize);
		
		if(empty($productDetail->product_price) || $productDetail->product_price == 0){
			return redirect('/');
		}
		
		if (empty($productDetail)) {
			return redirect('/');
		}

		if (isset($productDetail->product_type) && $productDetail->product_type == 'wholesaler' && Session::get('eusertype') == 'Retailer') {
			return redirect('/');
		}

		$this->PageData['OutOfStockCount'] = 0;
		if (isset($productDetail->referenced_products['OutOfStock'])) {
			$this->PageData['OutOfStockCount'] = count($productDetail->referenced_products['OutOfStock']);
		}

		$productDetail->product_story = $this->clean_new($productDetail->product_story);
		$this->PageData['productDetails'] = $productDetail;
		$CatInfo = config('CATEGORY_INFO');

		if(!isset($CatInfo['CatForProd'][$category_id]['bredcrum'])){
			$breadcrumbs = $this->getProductNavigation($productDetail->category_id);
		}else{
			$breadcrumbs = $CatInfo['CatForProd'][$category_id]['bredcrum'];
		}
		
		$this->PageData['breadcrumbs'] = $breadcrumbs;


		########################### For Weeekly Deal Start ###########################
		$dealofdayRS = array();
		$DealEndDate = "";
		$DealStartDate = "";
		$DealIcon = '';
		if ($productDetail->sku != "") {
			$dealofdayRS = GetDealOfWeek($productDetail->sku, 'Weekly');
		}
		if (isset($dealofdayRS[$productDetail->sku])) {
			$dealofdayRS = $dealofdayRS[$productDetail->sku];
		}


		if (count($dealofdayRS) > 0) {
			if (isset($dealofdayRS)) {
				if (isset($dealofdayRS['deal_price']) && $dealofdayRS['deal_price'] != '' && $dealofdayRS['deal_price'] < $productDetail->product_price) {
					$dealprice = $dealofdayRS['deal_price'];
					if (isset($dealofdayRS['description']) && $dealofdayRS['description'] != '') {
						$productDetail->short_description = $dealofdayRS['description'];
					}
					/*$yousave = ($productDetail->retail_price - $dealofdayRS['deal_price']) / $productDetail->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					*/
					$ChkZeroFraction = $productDetail->retail_price - $dealofdayRS['deal_price'];
					$yousave = 0;
					if($ChkZeroFraction > 0)
					{
						$yousave = ($productDetail->retail_price - $dealofdayRS['deal_price']) / $productDetail->retail_price;
						$yousave = $yousave * 100;
						$yousave = number_format($yousave, 0);
					}
					$productDetail->yousave   = $yousave;
					$productDetail->dealprice = $dealprice;
					$yousaveprice = $productDetail->retail_price - $dealofdayRS['deal_price'];
					$productDetail->yousaveprice = $yousaveprice;
					$productDetail->product_price = $dealprice;
				}
					
				if (!isset($productDetail->dealprice)) {
					$productDetail->dealprice = $productDetail->product_price;
				}

				if (isset($dealofdayRS['start_date'])) {
					$this->PageData['DateDiff'] =  date_diff(date_create($dealofdayRS['start_date']), date_create($dealofdayRS['end_date']));
				}

				$dealofdayRS['formatted_end_date']	= (isset($dealofdayRS['end_date']) ? date('d', strtotime($dealofdayRS['end_date'])) : '');
				$dealofdayRS['formatted_end_month']	= (isset($dealofdayRS['end_date']) ? date('m', strtotime($dealofdayRS['end_date'])) : '');
				$dealofdayRS['formatted_end_year']	= (isset($dealofdayRS['end_date']) ? date('Y', strtotime($dealofdayRS['end_date'])) : '');
				$DealStartDate = date("d-m-Y H:i:s");
				$DealEndDate = $dealofdayRS['formatted_end_date'] . "-" . $dealofdayRS['formatted_end_month'] . "-" . $dealofdayRS['formatted_end_year'] . " 23:59:59";

				$this->PageData['DealEndDate'] = $DealEndDate;
				$this->PageData['DealStartDate'] = $DealStartDate;

				$DealIcon = $this->getIconsSaleDeal("Yes", "No", "No");
				if (isset($dealofdayRS['deal_type']) && $dealofdayRS['deal_type'] == trim("Weekly")) {
					$this->PageData['deal_type'] = "Weekly";
				}
			}
		}
		$this->PageData['DealIcon'] = $DealIcon;
		$this->PageData['dealofdayRS'] = $dealofdayRS;
		## For Weeekly Deal End ###########################
		
		$SpecialPriceDetails = "";
		if (strtolower(Session::get('eusertype')) == 'wholesaler' && $productDetail->WebsiteStock == 'In' && ($productDetail->product_type == 'wholesaler' || $productDetail->product_type == 'both')) {
			$SpecialPriceDetails = $this->getWholesalerSpecialPricesDetails2($productDetail->product_price);
		}
		$this->PageData['SpecialPriceDetails'] = $SpecialPriceDetails;

		### Get youtube thumb image Start ###
		$ytthumb = "";

		if ($productDetail->youtubelink != ""  &&  preg_match("/embed/i", $productDetail->youtubelink)) {
			$youtubearr = explode("embed/", trim($productDetail->youtubelink));
			
						if ($youtubearr[1] != "") {
				$ytthumb = "https://img.youtube.com/vi/" . $youtubearr[1] . "/0.jpg";
			}
		}
		$this->PageData['ytthumb'] = $ytthumb;
		### Get youtube thumb image End ###

		

		############################# Reward Point Text Start Here ##################################
		if (isset($productDetail->dealprice) && $productDetail->dealprice != '') {
			$reward_points_text = (float)number_format($productDetail->dealprice, 0);
		} else {
			$reward_points_text = (float)number_format((float)$productDetail->product_price, 0);
		}
			
		if (strtolower(Session::get('eusertype')) == 'wholesaler') {
			$new_reward_points_text = "";
		} else {

			$reward_points_text = (float)$reward_points_text * (int)$productDetail->point_multiplier;
			$new_reward_points_text = " Earn <strong>" . $reward_points_text . " Points</strong>.";
		}

		$this->PageData['reward_points_text'] = $new_reward_points_text;
		############################## Reward Point Text End Here ##################################



		$arr_product_review = $this->getProductReview($productDetail->sku);
		$productreviewBystar = $this->getProductReviewByStar($productDetail->sku, count($arr_product_review));

		$this->PageData['productreviewBystar'] = $productreviewBystar;

		$avg_rate = $this->getProductAverageRating($productDetail->sku);

		$this->PageData['avg_rate'] = $avg_rate;
		$this->PageData['arr_product_review'] = $arr_product_review;

		if (Session::has('sess_icustomerid') && Session::get('sess_icustomerid') != '' && strtolower(Session::get('eusertype')) == 'retailer') {
			$res_customer = Customer::where('customer_id', '=', Session::get('sess_icustomerid'))->get();
			if (count($res_customer) > 0) {
				$this->PageData['customer_data'] = $res_customer[0];
			}
			$login_text = '<p>There are no reviews for the product. Be the first one to leave a review and earn 50 POINTS.</p>';
			if (count($arr_product_review) > 0) {
				$login_text = '<p>Leave a review for the item and earn 50 Points (After Approval) </p>';
			}
			$this->PageData['review_reward_point_text'] = $login_text;
		} else {
			if (Session::get('sess_icustomerid') != "" && strtolower(Session::get('eusertype')) == 'wholesaler') {
				$login_text = "";
			} else {
				//Note: Only for retailer
				$login_text = '<p>There are no reviews for the product. Be the first one to leave a review and earn 50 POINTS. Please <a data-pid="' . $products_id . '"  class="link-2 tdu openloginpopup" href="javascript:void(0)" id="review_login">login</a> </p>';
				if (count($arr_product_review) > 0) {
					$login_text = '<p>Leave a review for the item and earn 50 Points (After Approval). Please <a data-pid="' . $products_id . '"  class="link-2 tdu openloginpopup" href="javascript:void(0)" id="review_login">login</a></p>';
				}
			}
			$this->PageData['review_reward_point_text'] = $login_text;
		}

		$manuf_prods = $this->list_manufacturer_gender($productDetail->products_id, $productDetail->category_id, $productDetail->brand_id, $productDetail->imanufactureid, array('U', 'W', 'M'));


		$this->PageData['manuf_prods_wom'] = array();
		if (isset($manuf_prods['W'])) {
			$this->PageData['manuf_prods_wom'] = ($manuf_prods['W'] ? $manuf_prods['W'] : array());
		}

		$this->PageData['manuf_prods_man'] = array();
		if (isset($manuf_prods['M'])) {
			$this->PageData['manuf_prods_man'] = ($manuf_prods['M'] ? $manuf_prods['M'] : array());
		}

		$this->PageData['manuf_prods_uni'] =  array();
		if (isset($manuf_prods['U'])) {
			$this->PageData['manuf_prods_uni'] = ($manuf_prods['U'] ? $manuf_prods['U'] : array());
		}

		################ Recent viewed item Logic start #########################
		$arr_recent_item = array();
		if (Session::has('RECENT_VIEWED_ITEMS') && Session::get('RECENT_VIEWED_ITEMS') && count(Session::get('RECENT_VIEWED_ITEMS')) > 0) {
			$arr_recent_item = $this->getRecent_ViewedItems($productDetail->sku);
		}
		$this->PageData['arr_recent_item'] = (isset($arr_recent_item) ? $arr_recent_item : array());

		if (!Session::has('RECENT_VIEWED_ITEMS')) {
			$productArry = array();
			Session::put('RECENT_VIEWED_ITEMS', $productArry);
			Session::save();
		}
		if (Session::has('RECENT_VIEWED_ITEMS') && !in_array($productDetail->sku, Session::get('RECENT_VIEWED_ITEMS'))) {
			$productArry = Session::get('RECENT_VIEWED_ITEMS');
			array_push($productArry, $productDetail->sku);
			Session::put('RECENT_VIEWED_ITEMS', $productArry);
			Session::save();
		}
		################ Recent viewed item Logic End ##############################


		################ Max2Day Start ##################
		$this->PageData['Max2Day'] = Session::get('Max2Day');

		if ($productDetail->stock == "In" && $productDetail->WebsiteStock == "Out") {
			if (is_numeric(config('Settings.VENDOR_ARRIVES_FROM_DAYS')) && is_numeric(config('Settings.VENDOR_ARRIVES_TO_DAYS')) && is_numeric(config('Settings.VENDOR_EXPEDITED_DAYS'))) {
				$currentDate = date("M. d");
				$estimateDate = date('M. d', strtotime("+" . config('Settings.VENDOR_ARRIVES_FROM_DAYS') . " Weekday"));
				$estimateDate1 = date('M. d', strtotime("+" . config('Settings.VENDOR_ARRIVES_TO_DAYS') . " Weekday"));
				$totalEstimate = $estimateDate . ' - ' . $estimateDate1;
				$this->PageData['totalEstimate'] = $totalEstimate;
				$fastEstimateDate = date('l, F d', strtotime("+" . config('Settings.VENDOR_EXPEDITED_DAYS') . " Weekday"));
			}
		} else {
			if (is_numeric(config('Settings.ARRIVES_FROM_DAYS')) && is_numeric(config('Settings.ARRIVES_TO_DAYS')) && is_numeric(config('Settings.EXPEDITED_DAYS'))) {
				$currentDate = date("M. d");
				$estimateDate = date('M. d', strtotime("+" . config('Settings.ARRIVES_FROM_DAYS') . " Weekday"));
				$estimateDate1 = date('M. d', strtotime("+" . config('Settings.ARRIVES_TO_DAYS') . " Weekday"));
				$totalEstimate = $estimateDate . ' - ' . $estimateDate1;
				$this->PageData['totalEstimate'] = $totalEstimate;
				$fastEstimateDate = date('l, F d', strtotime("+" . config('Settings.EXPEDITED_DAYS') . " Weekday"));
			}
		}

		$this->PageData['fastEstimateDate'] = $fastEstimateDate;
		
		/*
		if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
		{
			$MyDate = "2021-12-17";
			$MyTime = "01:00:00";
			$FullDateTime = $MyDate.' '.$MyTime;	
			$DayVal = date("H@@a",strtotime($FullDateTime));
			$DayValArr = explode("@@", $DayVal);
			$startdateVal = date($FullDateTime);
			$enddateVal = date($MyDate." 14:00:00", strtotime("+2 Weekday"));
			$maxtwodaycontent =  date('l F d', strtotime(date($MyDate." 14:00:00")." +2 Weekday"));
			
			if ($DayValArr[1] == "pm") {
				if ($DayValArr[0] >= 14) {
					$maxtwodaycontent = date('l F d', strtotime(date($MyDate." 14:00:00")." +3 Weekday"));
					$enddateVal = date('Y-m-d H:i:s',strtotime(date($MyDate." 14:00:00")." +1 day"));
					$datetime1 = new DateTime($startdateVal);
					$datetime2 = new DateTime($enddateVal);
					$interval = $datetime1->diff($datetime2);
					$this->PageData['Maxhour'] = (int)$interval->format('%h');
					$this->PageData['Maxmin'] = (int)$interval->format('%i');
				} else {
					$enddateVal = date($MyDate." 14:00:00");
					$datetime1 = new DateTime($startdateVal);
					$datetime2 = new DateTime($enddateVal);
					$interval = $datetime1->diff($datetime2);
					$this->PageData['Maxhour'] = (int)$interval->format('%h');
					$this->PageData['Maxmin'] = (int)$interval->format('%i');
				}
			} else {
				$enddateVal = date($MyDate." 14:00:00");
				$datetime1 = new DateTime($startdateVal);
				$datetime2 = new DateTime($enddateVal);
				$interval = $datetime1->diff($datetime2);
				$this->PageData['Maxhour'] = (int)$interval->format('%h');
				$this->PageData['Maxmin'] = (int)$interval->format('%i');
			}
			
		} else {*/
			$DayVal = date("H@@a");
			$DayValArr = explode("@@", $DayVal);
			$startdateVal = date("Y-m-d H:i:s");
			$enddateVal = date("Y-m-d 14:00:00", strtotime("+2 Weekday"));
			$maxtwodaycontent =  date('l F d', strtotime("+2 Weekday"));
			
			/*if ($DayValArr[1] == "pm") {
				if ($DayValArr[0] >= 14) {
					$maxtwodaycontent = date('l F d', strtotime("+3 Weekday"));
					$enddateVal = date("Y-m-d 02:00:00", strtotime("+3 Weekday"));
				}
			}
			$datetime1 = new DateTime($startdateVal);
			$datetime2 = new DateTime($enddateVal);
			$interval = $datetime1->diff($datetime2);

			//$this->PageData['Maxhour'] = (int)($interval->format('%h') * $interval->format('%d'));
			if($interval->format('%d') > 0)
				$this->PageData['Maxhour'] = (int)($interval->format('%h') + (24 * $interval->format('%d')));
			else 
				$this->PageData['Maxhour'] = (int)($interval->format('%h'));
			$this->PageData['Maxmin'] = $interval->format('%i');*/
			
			if ($DayValArr[1] == "pm") {
				if ($DayValArr[0] >= 14) {
					$maxtwodaycontent = date('l F d', strtotime("+3 Weekday"));
					$enddateVal = date("Y-m-d 14:00:00", strtotime("+1 day"));
					$datetime1 = new DateTime($startdateVal);
					$datetime2 = new DateTime($enddateVal);
					$interval = $datetime1->diff($datetime2);
					$this->PageData['Maxhour'] = (int)$interval->format('%h');
					$this->PageData['Maxmin'] = (int)$interval->format('%i');
				} else {
					$enddateVal = date("Y-m-d 14:00:00");
					$datetime1 = new DateTime($startdateVal);
					$datetime2 = new DateTime($enddateVal);
					$interval = $datetime1->diff($datetime2);
					$this->PageData['Maxhour'] = (int)$interval->format('%h');
					$this->PageData['Maxmin'] = (int)$interval->format('%i');
				}
			} else {
				$enddateVal = date("Y-m-d 14:00:00");
				$datetime1 = new DateTime($startdateVal);
				$datetime2 = new DateTime($enddateVal);
				$interval = $datetime1->diff($datetime2);
				$this->PageData['Maxhour'] = (int)$interval->format('%h');
				$this->PageData['Maxmin'] = (int)$interval->format('%i');
			}
		//}
		
		
		
		$this->PageData['maxtwodaycontent'] = $maxtwodaycontent;
		############### Max2Day End #########################

		########################## CODE FOR REFEREAL URL START #######################################
		$this->PageData['usertype_cust'] = Session::get('eusertype');
		$this->PageData['FACE_BOOK_ADMINS_ID'] = "";
		$thisURL = $_SERVER['REQUEST_URI'];
		$thisURL = config('global.SITE_URL') . substr($thisURL, 1, strlen($thisURL));
		$this->PageData['referer_url'] = $thisURL; ////////////////// enhere //////////////////////////
		$fbName = $productDetail->product_name;
		$fbDesc = $productDetail->product_description;
		$fbImage = $productDetail->thumb_image;
		$fburl = $thisURL;
		$this->PageData['fbtitle'] = $fbName;
		$this->PageData['fburl'] = $fburl;
		$this->PageData['fburlForPass'] = urlencode($fburl);
		$this->PageData['fbimage'] = $fbImage;
		$this->PageData['fbdescription'] = strip_tags($fbDesc);
		$this->PageData['fbsite_name'] = config('global.SITE_URL');
		// $this->PageData['fbadmins'] = "100005334245374";//100004186244441
		$this->PageData['fbadmins'] = "";
		$this->PageData['fbtype'] = "website";
		//Settting for face book like button
		$this->PageData['CanonicalURL'] = config('global.SITE_URL') . str_replace("/" . config('global.SITE_BASE') . "/", '', $_SERVER['REQUEST_URI']);
		################################ CODE FOR REFEREAL URL END #####################################


		################################# Afterpay Checkout Display Setting ##############################
		$Afterpay_Checkout = 'No';
		Session::forget('AfterPay');

		$Afterpay_pay = PaymentMethod::select('pm_status')->where('pm_group_name', '=', 'PAYMENT_PAYWITHAFTERPAY')->where('pm_status', '=', 'Active')->offset(0)->limit(1)->get();
		if (!empty($Afterpay_pay)) {
			$Afterpay_Checkout = 'Yes';
		}
		if (Session::has('eusertype') && Session::get('eusertype') == "Wholesaler") {
			$Afterpay_Checkout = 'No';
		}

		if(!Session::has('Afterpay.Min_AP_AMT') && !Session::has('Afterpay.Max_AP_AMT'))
		{
			$this->AfterpayMinMax();
		}

		$show_AP = "No";
		if ($Afterpay_Checkout == "Yes" && $show_AP == "Yes") {
			// include_once(config('global.PHYSICAL_PATH') . "PayWithAfterpay/afterpay_functions.php");

			$payload = array();
			$getconfigs = GetAfterPayResult($payload, "configuration", "No");

			$Min_AP = $getconfigs['minimumAmount']['amount'];
			$Max_AP = $getconfigs['maximumAmount']['amount'];

			$Min_AP_AMT = round($Min_AP * 100);
			$Max_AP_AMT = round($Max_AP * 100);

			$min_ap_amt['AfterPay']['Min_AP_AMT'];
			$max_ap_amt['AfterPay']['Max_AP_AMT'];

			Session::put($min_ap_amt, $Min_AP_AMT);
			Session::put($max_ap_amt, $Max_AP_AMT);

			$this->PageData['Min_AP_AMT'] = $Min_AP_AMT;
			$this->PageData['Max_AP_AMT'] = $Max_AP_AMT;

			//$Min_AP_currency = $getconfigs['minimumAmount']['currency'];
			//echo "<pre>";print_r($getconfigs);exit;
		}
		$this->PageData['Afterpay_Checkout'] = $Afterpay_Checkout;
		$this->PageData['show_AP'] = $Afterpay_Checkout;
		################################ Afterpay Checkout Display Setting End #################################

		$isMobile = isMobile();
		$isIpad = preg_match("/(tablet|ipad)/i", $_SERVER["HTTP_USER_AGENT"]);

		$this->PageData['isMobile'] = $isMobile;
		$this->PageData['isIpad'] = $isIpad;

		$logo_img = $this->GetBogoProducts($productDetail->sku, $category_id, $productDetail->imanufactureid, $dealofdayRS);
		$this->PageData['logo_img_bogo'] = $logo_img;

		if (!empty($productDetail->meta_title)) {
			$this->PageData['meta_title'] =  stripslashes(htmlentities($productDetail->meta_title));
			
		} else{
			$this->PageData['meta_title'] = stripslashes($productDetail->product_name);
		}
		if (!empty($productDetail->meta_keyword)) {
			$this->PageData['meta_keywords'] =  stripslashes($productDetail->meta_keyword);
		} else{
			$this->PageData['meta_keywords'] = stripslashes($productDetail->short_description);
		}
		if (!empty($productDetail->meta_description)) {
			$this->PageData['meta_description'] =  stripslashes($productDetail->meta_description);
		} else{
			$this->PageData['meta_description'] = stripslashes($productDetail->short_description);
		}
		
		$daydealofdayRS = array();
		if ($productDetail->sku != "") {
			$daydealofdayRS = GetDealOfWeek($productDetail->sku);
		}
		
		$GTMDATA = ['page' => 'productdetail', 'pagetype' => 'product'];
		
		if(count($dealofdayRS) > 0 || count($daydealofdayRS)>0)
			$GTMDATA['RemarketingtotalValue'] = (isset($productDetail->dealprice)?round($productDetail->dealprice):0);
		else
			$GTMDATA['RemarketingtotalValue'] = round($productDetail->product_price);
		

		$GTMDATA['RemarketingprodID'] = $productDetail->sku;		
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		
		/** OMNI TAGS **/
		$ProdName = "";
		if(!empty($productDetail->vmanufacture))
		{
			$ProdName = $productDetail->vmanufacture.' '.$productDetail->referencedName_main;
		} else {
			$ProdName = $productDetail->brand_name.' '.$productDetail->referencedName_main;
		}			
		$ProductData = '
			{
				$productID : "'.$productDetail->products_id.'",
				$variantID	: "'.$productDetail->sku.'",
				$currency : "'.Session::get('currency_code').'",
				$price : '.(round($productDetail->product_price)*100).',
				$title	: "'.$ProdName.'",
				$imageUrl : "'.$productDetail->mainImage.'",
				$productUrl : "'.url()->current().'"
			}';
		$this->PageData['OmniProdData'] = $ProductData;
		/*
		$ProductOmaniData = [
				'productID' => $productDetail->products_id,
				'sku'	=> $productDetail->sku,
				'currency' => Session::get('currency_code'),
				'price' => $productDetail->product_price,
				'title'	=> $ProdName,
				'product_image' => $productDetail->mainImage,
				'product_url' => url()->current()
			];*/
		/** OMNI TAGS **/
		
		config(['ProdSKU' => $productDetail->sku]);
		return view('product.details')->with($this->PageData);
	}

	public function GetRefProduct(Request $request)
	{

		$products_id = $request->products_id;

		$code = $request->code;

		$productDetail = $this->getProductsDetails($products_id,  $preview = '', $code = '', $resize = '');


		$SpecialPriceDetails = "";

		if (strtolower(Session::get('eusertype')) == 'wholesaler' && $productDetail->WebsiteStock == 'In' && ($productDetail->product_type == 'wholesaler' || $productDetail->product_type == 'both')) {
			$SpecialPriceDetails = $this->getWholesalerSpecialPricesDetails2($productDetail->product_price);
		}

		$this->PageData['SpecialPriceDetails'] = $SpecialPriceDetails;

		$this->PageData['OutOfStockCount'] = 0;
		if (isset($productDetail->referenced_products['OutOfStock'])) {
			$this->PageData['OutOfStockCount'] = count($productDetail->referenced_products['OutOfStock']);
		}

		$productDetail->product_story = $this->clean_new($productDetail->product_story);
		$this->PageData['productDetails'] = $productDetail;

		$category_id = $request->category_id;

		if ($category_id == '' ||  empty($category_id)) {

			$res_category = DB::table('pu_products_category as pcr')
				->join('pu_category as c', 'pcr.category_id', '=', 'c.category_id')
				->select('c.category_id')
				->where('c.status', '=', '1')
				->where('pcr.products_id', '=', $products_id)
				->orderBy('c.display_position')->orderBy('c.category_name')->offset(0)->limit(1)->get();

			$category_id = $res_category[0]->category_id;
		}
		$CatInfo = config('CATEGORY_INFO');
		if(!isset($CatInfo['CatForProd'][$category_id]['bredcrum'])){
			$breadcrumbs = $this->getProductNavigation($productDetail->category_id);
		}else{
			$breadcrumbs = $CatInfo['CatForProd'][$category_id]['bredcrum'];
		}

		$this->PageData['breadcrumbs'] = $breadcrumbs;

		########################### For Day Daily Deal Start ###########################
		$daydealofdayRS = array();
		if ($productDetail->sku != "") {
			$daydealofdayRS = GetDealOfWeek($productDetail->sku);
		}

		if (isset($daydealofdayRS[$productDetail->sku])) {
			$daydealofdayRS = $daydealofdayRS[$productDetail->sku];
		}

		$DealIcon = '';
		$DealEndDate = "";
		$DealStartDate = "";
		if (count($daydealofdayRS) > 0) {
			if (isset($daydealofdayRS)) {
				if (isset($daydealofdayRS['deal_price']) && $daydealofdayRS['deal_price'] != '' && $daydealofdayRS['deal_price'] < $productDetail->product_price) {
					$dealprice = $daydealofdayRS['deal_price'];
					if (isset($daydealofdayRS['description']) && $daydealofdayRS['description'] != '') {
						$productDetail->short_description = $daydealofdayRS['description'];
					}
					$yousave = ($productDetail->retail_price - $daydealofdayRS['deal_price']) / $productDetail->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$productDetail->yousave   = $yousave;
					$productDetail->dealprice = $dealprice;
					$yousaveprice = $productDetail->retail_price - $daydealofdayRS['deal_price'];
					$productDetail->yousaveprice = $yousaveprice;
					$productDetail->product_price = $dealprice;
				}
				if (!isset($productDetail->dealprice)) {
					$productDetail->dealprice = $productDetail->product_price;
				}

				if(isset($daydealofdayRS['start_date']) && isset($daydealofdayRS['end_date'])) {
				$this->PageData['DateDiff'] = date_diff(date_create($daydealofdayRS['start_date']), date_create($daydealofdayRS['end_date']));
				$daydealofdayRS['formatted_end_date']	= date('d', strtotime($daydealofdayRS['end_date']));
				$daydealofdayRS['formatted_end_month']	= date('m', strtotime($daydealofdayRS['end_date']));
				$daydealofdayRS['formatted_end_year']	= date('Y', strtotime($daydealofdayRS['end_date']));
				$DealStartDate = date("d-m-Y H:i:s");
				$DealEndDate = $daydealofdayRS['formatted_end_date'] . "-" . $daydealofdayRS['formatted_end_month'] . "-" . $daydealofdayRS['formatted_end_year'] . " 23:59:59";

				$this->PageData['DealEndDate'] = $DealEndDate;
				$this->PageData['DealStartDate'] = $DealStartDate;
				}

				$DealIcon = $this->getIconsSaleDeal("Yes", "No", "No");
				if (isset($daydealofdayRS['deal_type']) && $daydealofdayRS['deal_type'] == trim("Daily")) {
					$this->PageData['deal_type'] = 'Daily';
				}
			}
		}
		$this->PageData['DealIcon'] = $DealIcon;
		$this->PageData['daydealofdayRS'] = $daydealofdayRS;
		########################### For Day Daily Deal End #########################

		########################### For Weeekly Deal Start ###########################
		$dealofdayRS = array();
		if ($productDetail->sku != "") {
			$dealofdayRS = GetDealOfWeek($productDetail->sku, 'Weekly');
		}
		if (isset($dealofdayRS[$productDetail->sku])) {
			$dealofdayRS = $dealofdayRS[$productDetail->sku];
		}
		if (count($dealofdayRS) > 0) {
			if (isset($dealofdayRS)) {
				if (isset($dealofdayRS['deal_price']) && $dealofdayRS['deal_price'] != '' && $dealofdayRS['deal_price'] < $productDetail->product_price) {
					$dealprice = $dealofdayRS['deal_price'];
					if (isset($dealofdayRS['description']) && $dealofdayRS['description'] != '') {
						$productDetail->short_description = $dealofdayRS['description'];
					}
					$yousave = ($productDetail->retail_price - $dealofdayRS['deal_price']) / $productDetail->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$productDetail->yousave   = $yousave;
					$productDetail->dealprice = $dealprice;
					$yousaveprice = $productDetail->retail_price - $dealofdayRS['deal_price'];
					$productDetail->yousaveprice = $yousaveprice;
					$productDetail->product_price = $dealprice;
				}
				if (isset($productDetail->dealprice) && $productDetail->dealprice == "") {
					$productDetail->dealprice = $productDetail->product_price;
				}

				if (isset($dealofdayRS['start_date'])) {
					$this->PageData['DateDiff'] =  date_diff(date_create($dealofdayRS['start_date']), date_create($dealofdayRS['end_date']));
				}

				$dealofdayRS['formatted_end_date']	=  (isset($dealofdayRS['end_date']) ? date('d', strtotime($dealofdayRS['end_date'])) : '');
				$dealofdayRS['formatted_end_month']	= (isset($dealofdayRS['end_date']) ? date('m', strtotime($dealofdayRS['end_date'])) : '');
				$dealofdayRS['formatted_end_year']	= (isset($dealofdayRS['end_date']) ? date('Y', strtotime($dealofdayRS['end_date'])) : '');
				$DealStartDate = date("d-m-Y H:i:s");
				$DealEndDate = $dealofdayRS['formatted_end_date'] . "-" . $dealofdayRS['formatted_end_month'] . "-" . $dealofdayRS['formatted_end_year'] . " 23:59:59";

				$this->PageData['DealEndDate'] = $DealEndDate;
				$this->PageData['DealStartDate'] = $DealStartDate;

				$DealIcon = $this->getIconsSaleDeal("Yes", "No", "No");
				if (isset($dealofdayRS['deal_type']) && $dealofdayRS['deal_type'] == trim("Weekly")) {
					$this->PageData['deal_type'] = "Weekly";
				}
			}
		}
		$this->PageData['DealIcon'] = $DealIcon;
		$this->PageData['dealofdayRS'] = $dealofdayRS;
		## For Weeekly Deal End ###########################


		### Get youtube thumb image Start ###
		$ytthumb = "";

		if ($productDetail->youtubelink != "") {
			$youtubearr = explode("embed/", trim($productDetail->youtubelink));

			if ($youtubearr[1] != "") {
				$ytthumb = "https://img.youtube.com/vi/" . $youtubearr[1] . "/0.jpg";
			}
		}
		$this->PageData['ytthumb'] = $ytthumb;
		### Get youtube thumb image End ###

		############################# Reward Point Text Start Here ##################################
		if (isset($productDetail->dealprice) && $productDetail->dealprice != '') {
			$reward_points_text = (float)number_format((float)$productDetail->dealprice, 0);
		} else {
			$reward_points_text = (float)number_format((float)$productDetail->product_price, 0);
		}

		if (strtolower(Session::get('eusertype')) == 'wholesaler') {
			$new_reward_points_text = "";
		} else {

			$reward_points_text = $reward_points_text * $productDetail->point_multiplier;
			$new_reward_points_text = " Earn <strong>" . $reward_points_text . " Points</strong>.";
		}
		
		if(!Session::has('Afterpay.Min_AP_AMT') && !Session::has('Afterpay.Max_AP_AMT'))
		{
			$this->AfterpayMinMax();
		}

		$this->PageData['reward_points_text'] = $new_reward_points_text;
		############################## Reward Point Text End Here ##################################

		$arr_product_review = $this->getProductReview($productDetail->sku);

		$productreviewBystar = $this->getProductReviewByStar($productDetail->sku, count($arr_product_review));

		// dd($productreviewBystar);

		$this->PageData['productreviewBystar'] = $productreviewBystar;

		$avg_rate = $this->getProductAverageRating($productDetail->sku);

		// if (count($arr_product_review) >= 0) {

		// 	for ($j = 0; $j < count($productDetail->referenced_products_keys); $j++) {
		// 		for ($i = 0; $i < count($productDetail->referenced_products[$productDetail->referenced_products_keys[$j]]); $i++) {
		// 			$arr_product_review = $this->getProductReview($productDetail->referenced_products[$productDetail->referenced_products_keys[$j]][$i]['sku']);
		// 			$review = "No";
		// 			if (count($arr_product_review) > 0) {
		// 				$avg_rate = $this->getProductAverageRating($productDetail->referenced_products[$productDetail->referenced_products_keys[$j]][$i]['sku']);
		// 				$review = "Yes";
		// 				break;
		// 			}
		// 			if ($review == "Yes") {
		// 				break;
		// 			}
		// 		}
		// 		if ($review == "Yes") {
		// 			break;
		// 		}
		// 	}
		// }



		$this->PageData['avg_rate'] = $avg_rate;
		$this->PageData['arr_product_review'] = $arr_product_review;

		if (Session::has('sess_icustomerid') && Session::get('sess_icustomerid') != '' && strtolower(Session::get('eusertype')) == 'retailer') {
			$res_customer = Customer::where('customer_id', '=', Session::get('sess_icustomerid'))->get();
			if (count($res_customer) > 0) {
				$this->PageData['customer_data'] = $res_customer[0];
			}
			$login_text = '<p>There are no reviews for the product. Be the first one to leave a review and earn 50 POINTS.</p>';
			if (count($arr_product_review) > 0) {
				$login_text = '<p>Leave a review for the item and earn 50 Points (After Approval) </p>';
			}
			$this->PageData['review_reward_point_text'] = $login_text;
		} else {
			if (Session::get('sess_icustomerid') != "" && strtolower(Session::get('eusertype')) == 'wholesaler') {
				$login_text = "";
			} else {
				//Note: Only for retailer
				$login_text = '<p>There are no reviews for the product. Be the first one to leave a review and earn 50 POINTS. Please <a data-pid="' . $products_id . '"  class="link-2 tdu openloginpopup" href="javascript:void(0)" id="review_login">login</a> </p>';
				if (count($arr_product_review) > 0) {
					$login_text = '<p>Leave a review for the item and earn 50 Points (After Approval). Please <a data-pid="' . $products_id . '"  class="link-2 tdu openloginpopup" href="javascript:void(0)" id="review_login">login</a></p>';
				}
			}
			$this->PageData['review_reward_point_text'] = $login_text;
		}

		$manuf_prods = $this->list_manufacturer_gender($productDetail->products_id, $productDetail->category_id, $productDetail->brand_id, $productDetail->imanufactureid, array('U', 'W', 'M'));

		$this->PageData['manuf_prods_wom'] = array();
		if (isset($manuf_prods['W'])) {
			$this->PageData['manuf_prods_wom'] = ($manuf_prods['W'] ? $manuf_prods['W'] : array());
		}

		$this->PageData['manuf_prods_man'] = array();
		if (isset($manuf_prods['M'])) {
			$this->PageData['manuf_prods_man'] = ($manuf_prods['M'] ? $manuf_prods['M'] : array());
		}

		$this->PageData['manuf_prods_uni'] =  array();
		if (isset($manuf_prods['U'])) {
			$this->PageData['manuf_prods_uni'] = ($manuf_prods['U'] ? $manuf_prods['U'] : array());
		}

		################ Recent viewed item Logic start #########################
		$arr_recent_item = array();
		if (Session::has('RECENT_VIEWED_ITEMS') && Session::get('RECENT_VIEWED_ITEMS') && count(Session::get('RECENT_VIEWED_ITEMS')) > 0) {
			$arr_recent_item = $this->getRecent_ViewedItems($productDetail->sku);
		}
		$this->PageData['arr_recent_item'] = (isset($arr_recent_item) ? $arr_recent_item : array());

		if (!Session::has('RECENT_VIEWED_ITEMS')) {
			$productArry = array();
			Session::put('RECENT_VIEWED_ITEMS', $productArry);
			Session::save();
		}
		if (Session::has('RECENT_VIEWED_ITEMS') && !in_array($productDetail->sku, Session::get('RECENT_VIEWED_ITEMS'))) {
			$productArry = Session::get('RECENT_VIEWED_ITEMS');
			array_push($productArry, $productDetail->sku);
			Session::put('RECENT_VIEWED_ITEMS', $productArry);
			Session::save();
		}
		################ Recent viewed item Logic End ##############################


		################ Max2Day Start ##################
		$this->PageData['Max2Day'] = Session::get('Max2Day');

		if ($productDetail->stock == "In" && $productDetail->WebsiteStock == "Out") {
			if (is_numeric(config('Settings.VENDOR_ARRIVES_FROM_DAYS')) && is_numeric(config('Settings.VENDOR_ARRIVES_TO_DAYS')) && is_numeric(config('Settings.VENDOR_EXPEDITED_DAYS'))) {
				$currentDate = date("M. d");
				$estimateDate = date('M. d', strtotime("+" . config('Settings.VENDOR_ARRIVES_FROM_DAYS') . " Weekday"));
				$estimateDate1 = date('M. d', strtotime("+" . config('Settings.VENDOR_ARRIVES_TO_DAYS') . " Weekday"));
				$totalEstimate = $estimateDate . ' - ' . $estimateDate1;
				$this->PageData['totalEstimate'] = $totalEstimate;
				$fastEstimateDate = date('l, F d', strtotime("+" . config('Settings.VENDOR_EXPEDITED_DAYS') . " Weekday"));
			}
		} else {
			if (is_numeric(config('Settings.ARRIVES_FROM_DAYS')) && is_numeric(config('Settings.ARRIVES_TO_DAYS')) && is_numeric(config('Settings.EXPEDITED_DAYS'))) {
				$currentDate = date("M. d");
				$estimateDate = date('M. d', strtotime("+" . config('Settings.ARRIVES_FROM_DAYS') . " Weekday"));
				$estimateDate1 = date('M. d', strtotime("+" . config('Settings.ARRIVES_TO_DAYS') . " Weekday"));
				$totalEstimate = $estimateDate . ' - ' . $estimateDate1;
				$this->PageData['totalEstimate'] = $totalEstimate;
				$fastEstimateDate = date('l, F d', strtotime("+" . config('Settings.EXPEDITED_DAYS') . " Weekday"));
			}
		}

		$this->PageData['fastEstimateDate'] = $fastEstimateDate;

		$DayVal = date("h@@a");
		$DayValArr = explode("@@", $DayVal);
		$startdateVal = date("Y-m-d H:i:s");
		$enddateVal = date("Y-m-d 02:00:00", strtotime("+2 Weekday"));
		$maxtwodaycontent =  date('l F d', strtotime("+2 Weekday"));
		if ($DayValArr[1] == "pm") {
			if ($DayValArr[0] >= 2) {
				$maxtwodaycontent = date('l F d', strtotime("+3 Weekday"));
				$enddateVal = date("Y-m-d 02:00:00", strtotime("+3 Weekday"));
			}
		}
		$datetime1 = new DateTime($startdateVal);
		$datetime2 = new DateTime($enddateVal);
		$interval = $datetime1->diff($datetime2);

		$this->PageData['Maxhour'] = (int)($interval->format('%h') * $interval->format('%d'));
		$this->PageData['Maxmin'] = $interval->format('%i');
		$this->PageData['maxtwodaycontent'] = $maxtwodaycontent;
		############### Max2Day End #########################

		########################## CODE FOR REFEREAL URL START #######################################
		$this->PageData['usertype_cust'] = Session::get('eusertype');
		$this->PageData['FACE_BOOK_ADMINS_ID'] = "";
		$thisURL = $_SERVER['REQUEST_URI'];
		$thisURL = config('global.SITE_URL') . substr($thisURL, 1, strlen($thisURL));
		$this->PageData['referer_url'] = $thisURL; ////////////////// enhere //////////////////////////
		$fbName = $productDetail->product_name;
		$fbDesc = $productDetail->product_description;
		$fbImage = $productDetail->thumb_image;
		$fburl = $thisURL;
		$this->PageData['fbtitle'] = $fbName;
		$this->PageData['fburl'] = $fburl;
		$this->PageData['fburlForPass'] = urlencode($fburl);
		$this->PageData['fbimage'] = $fbImage;
		$this->PageData['fbdescription'] = strip_tags($fbDesc);
		$this->PageData['fbsite_name'] = config('global.SITE_URL');
		// $this->PageData['fbadmins'] = "100005334245374";//100004186244441
		$this->PageData['fbadmins'] = "";
		$this->PageData['fbtype'] = "website";
		//Settting for face book like button
		$this->PageData['CanonicalURL'] = config('global.SITE_URL') . str_replace("/" . config('global.SITE_BASE') . "/", '', $_SERVER['REQUEST_URI']);
		################################ CODE FOR REFEREAL URL END #####################################


		################################# Afterpay Checkout Display Setting ##############################
		$Afterpay_Checkout = 'No';
		Session::forget('AfterPay');

		$Afterpay_pay = PaymentMethod::select('pm_status')->where('pm_group_name', '=', 'PAYMENT_PAYWITHAFTERPAY')->where('pm_status', '=', 'Active')->offset(0)->limit(1)->get();
		if (!empty($Afterpay_pay)) {
			$Afterpay_Checkout = 'Yes';
		}
		if (Session::has('eusertype') && Session::get('eusertype') == "Wholesaler") {
			$Afterpay_Checkout = 'No';
		}

		//Only Demo Afterpay
		//$show_AP = "No";
		/* if($_SESSION['sess_useremail'] != "gequaldev@gmail.com"){
			//$show_AP = "Yes"; 
			$Afterpay_Checkout ='No';
		} */
		//Only Demo Afterpay

		$show_AP = "No";
		if ($Afterpay_Checkout == "Yes" && $show_AP == "Yes") {
			// include_once(config('global.PHYSICAL_PATH') . "PayWithAfterpay/afterpay_functions.php");

			$payload = array();
			$getconfigs = GetAfterPayResult($payload, "configuration", "No");

			$Min_AP = $getconfigs['minimumAmount']['amount'];
			$Max_AP = $getconfigs['maximumAmount']['amount'];

			$Min_AP_AMT = round($Min_AP * 100);
			$Max_AP_AMT = round($Max_AP * 100);

			$min_ap_amt['AfterPay']['Min_AP_AMT'];
			$max_ap_amt['AfterPay']['Max_AP_AMT'];

			Session::put($min_ap_amt, $Min_AP_AMT);
			Session::put($max_ap_amt, $Max_AP_AMT);

			$this->PageData['Min_AP_AMT'] = $Min_AP_AMT;
			$this->PageData['Max_AP_AMT'] = $Max_AP_AMT;

			//$Min_AP_currency = $getconfigs['minimumAmount']['currency'];
			//echo "<pre>";print_r($getconfigs);exit;
		}
		$this->PageData['Afterpay_Checkout'] = $Afterpay_Checkout;
		$this->PageData['show_AP'] = $Afterpay_Checkout;
		################################ Afterpay Checkout Display Setting End #################################

		$logo_img = $this->GetBogoProducts($productDetail->sku, $category_id, $productDetail->imanufactureid, $dealofdayRS, $daydealofdayRS);
		$this->PageData['logo_img_bogo'] = $logo_img;
		$product_name = remove_special_chars($productDetail->product_name);
		//$product_url = $this->getProductRewriteURL($productDetail->products_id, $productDetail->product_name, $category_id, $productDetail->vmanufacture);
		$product_url = SetProductURL($productDetail->products_id, $productDetail->product_name, $productDetail->category_id);

		$this->PageData['product_url'] = $product_url;

		$product_descriptionpart = view('product.product_details.product_descriptionpart')->with($this->PageData)->render();
		$product_descriptionmobilepart = view('product.product_details.product_descriptionpart')->with($this->PageData)->render();
		$product_bottomsticky = view('product.product_details.product_bottomsticky')->with($this->PageData)->render();
		$addtocart_max2dayreward = view('product.product_details.addtocart_max2dayreward')->with($this->PageData)->render();
		$product_detail = view('product.product_details.product_detail')->with($this->PageData)->render();
		$product_review = view('product.product_details.product_review')->with($this->PageData)->render();
		$you_may_also_like = view('product.product_details.you_may_also_like')->with($this->PageData)->render();
		$SpecialPriceSort = view('product.product_details.specialprice')->with($this->PageData)->render();
		$weekly_deal_days_box = view('product.product_details.weekly_deal_days_box')->with($this->PageData)->render();
		$add_towishlist = view('product.product_details.add_towishlist')->with($this->PageData)->render();
		$product_story_notes = view('product.product_details.product_story_notes')->with($this->PageData)->render();

		$productimgicon_manufacture_detail = view('product.product_details.productimgicon_manufacture_detail')->with($this->PageData)->render();

		$array = array(
			'psort_descriptn' => $productDetail->short_description,
			'product_descriptionpart' => $product_descriptionpart,
			'product_descriptionmobilepart' => $product_descriptionmobilepart,
			'addtocart_max2dayreward' => $addtocart_max2dayreward,
			'product_detail' => $product_detail,
			'product_review' => $product_review,
			'you_may_also_like' => $you_may_also_like,
			'product_url' => $product_url,
			'SpecialPriceSort' => $SpecialPriceSort,
			'sort_remaining_days' => $weekly_deal_days_box,
			'psort_sku' => $productDetail->sku,
			'DealEndDate' => $DealEndDate,
			'DealStartDate' => $DealStartDate,
			'add_towishlist' => $add_towishlist,
			'product_story_notes' => $product_story_notes,
			'productimgicon_manufacture_detail' => $productimgicon_manufacture_detail,
			'product_bottomsticky' => $product_bottomsticky
		);

		return response()->json($array);
	}
}
