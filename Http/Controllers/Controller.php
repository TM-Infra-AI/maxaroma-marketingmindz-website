<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    
	public function ProductDetails_test(Request $request)
	{
		$this->PageData['CSSFILES'] = ['slick.css', 'detail.css'];
		$this->PageData['JSFILES'] = ['slick.js', 'easyResponsiveTabs.js', 'productdetail.js'];

		$products_id = $request->products_id;

		if ($products_id == '' || !is_numeric($products_id) || empty($products_id)) {
			return redirect('/');
		}
		$category_id = $request->category_id;
		$code = $request->code;

		

		$Static_info = getstaticpages('shipping_information','return_exchange_policy');
		$this->PageData['Shipping_info_Content'] =  '';
		if ($Static_info && $Static_info->count() > 0 && $Static_info[0]->content!='') {
			$Shipping_info_Content = str_replace("<h1>", "", $Static_info[0]->content);
			$Shipping_info_Content = str_replace("</h1>", "", $Shipping_info_Content);
			$this->PageData['Shipping_info_Content'] = $Shipping_info_Content;
		}

		$this->PageData['Return_info_Content'] = '';
		if ($Static_info && $Static_info->count() > 0 && $Static_info[1]->content!='') {
			$Return_info[0]->content = str_replace('{$titles}', '', $Static_info[1]->content);
			$Return_info_Content = str_replace("<h1>", "", $Static_info[1]->content);
			$Return_info_Content = str_replace("</h1>", "", $Return_info_Content);

			$this->PageData['Return_info_Content'] = $Return_info_Content;
		}

		$preview = isset($request->preview) ? $request->preview : 0;
		$resize = $request->resize;
		$productDetail = $this->getProductsDetail($products_id, $preview, $code, $resize);

		// dd($productDetail->referenced_products);

		$this->PageData['OutOfStockCount'] = 0;
		if (isset($productDetail->referenced_products['OutOfStock'])) {
			$this->PageData['OutOfStockCount'] = count($productDetail->referenced_products['OutOfStock']);
		}

		$this->PageData['productDetails'] = $productDetail;


		$breadcrumbs = $this->getProductNavigation($category_id);

		// dd($breadcrumbs);
		$this->PageData['breadcrumbs'] = $breadcrumbs;


		/*$this->PageData['ListFrom'] = 'Category'; 
			$ProdCat = $request->category_id;
			$this->PageData['SelCat'] = $request->category_id;
			$this->PageData['SelectedCat'] = [$request->category_id];
			*/

		########################### For Day Daily Deal Start ###########################
		$daydealofdayRS = array();
		if ($productDetail->sku != "") {
			$daydealofdayRS = GetDealOfWeek($productDetail->sku);
		}

		if (isset($daydealofdayRS[$productDetail->sku])) {
			$daydealofdayRS = $daydealofdayRS[$productDetail->sku];
		}


		$DealIcon = '';
		if (count($daydealofdayRS) > 0) {
			if (isset($daydealofdayRS)) {
				if ($daydealofdayRS['deal_price'] != '' && $daydealofdayRS['deal_price'] < $productDetail->product_price) {
					$dealprice = $daydealofdayRS['deal_price'];
					/*if($daydealofdayRS['description!='') {
				$productDetail->short_description = $daydealofdayRS['description;
			}*/
					$yousave = ($productDetail->retail_price - $daydealofdayRS['deal_price']) / $productDetail->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$productDetail->yousave   = $yousave;
					$productDetail->dealprice = $dealprice;
					$yousaveprice = $productDetail->retail_price - $daydealofdayRS['deal_price'];
					$productDetail->yousaveprice = $yousaveprice;
					//$productDetail->product_price = $dealprice;
				}
				if ($productDetail->dealprice == "") {
					$productDetail->dealprice = $productDetail->product_price;
				}
				$this->PageData['DateDiff'] = (isset($daydealofdayRS['datediff']) ? $daydealofdayRS['datediff'] : "");
				$daydealofdayRS['formatted_end_date']	= date('d', strtotime($daydealofdayRS['end_date']));
				$daydealofdayRS['formatted_end_month']	= date('m', strtotime($daydealofdayRS['end_date']));
				$daydealofdayRS['formatted_end_year']	= date('Y', strtotime($daydealofdayRS['end_date']));
				$DealStartDate = date("d-m-Y H:i:s");
				$DealEndDate = $daydealofdayRS['formatted_end_date'] . "-" . $daydealofdayRS['formatted_end_month'] . "-" . $daydealofdayRS['formatted_end_year'] . " 23:59:59";

				$this->PageData['DealEndDate'] = $DealEndDate;
				$this->PageData['DealStartDate'] = $DealStartDate;

				$DealIcon = $this->getIconsSaleDeal("Yes", "No", "No");

				if ($daydealofdayRS['deal_type'] == trim("Daily")) {
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
				if ($dealofdayRS['deal_price'] != '' && $dealofdayRS['deal_price'] < $productDetail->product_price) {
					$dealprice = $dealofdayRS['deal_price'];
					if ($dealofdayRS['description'] != '') {
						//$productDetail->short_description = $dealofdayRS['description;
					}
					$yousave = ($productDetail->retail_price - $dealofdayRS['deal_price']) / $productDetail->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$productDetail->yousave   = $yousave;
					$productDetail->dealprice = $dealprice;
					$yousaveprice = $productDetail->retail_price - $dealofdayRS['deal_price'];
					$productDetail->yousaveprice = $yousaveprice;
					//$productDetail->product_price = $dealprice;
				}
				if ($productDetail->dealprice == "") {
					$productDetail->dealprice = $productDetail->product_price;
				}
				$this->PageData['DateDiff'] = (isset($dealofdayRS['datediff']) ? $dealofdayRS['datediff'] : "");

				$dealofdayRS['formatted_end_date']	= date('d', strtotime($dealofdayRS['end_date']));
				$dealofdayRS['formatted_end_month']	= date('m', strtotime($dealofdayRS['end_date']));
				$dealofdayRS['formatted_end_year']	= date('Y', strtotime($dealofdayRS['end_date']));
				$DealStartDate = date("d-m-Y H:i:s");
				$DealEndDate = $dealofdayRS['formatted_end_date'] . "-" . $dealofdayRS['formatted_end_month'] . "-" . $dealofdayRS['formatted_end_year'] . " 23:59:59";

				$this->PageData['DealEndDate'] = $DealEndDate;
				$this->PageData['DealStartDate'] = $DealStartDate;

				$DealIcon = $this->getIconsSaleDeal("Yes", "No", "No");


				if ($dealofdayRS['deal_type'] == trim("Weekly")) {
					$this->PageData['deal_type'] = "Weekly";
				}
			}
		}
		$this->PageData['DealIcon'] = $DealIcon;
		$this->PageData['dealofdayRS'] = $dealofdayRS;
		## For Weeekly Deal End ###########################


		//Get youtube thumb image Start
		$ytthumb = "";

		if ($productDetail->youtubelink != "") {
			$youtubearr = explode("embed/", trim($productDetail->youtubelink));

			if ($youtubearr[1] != "") {
				$ytthumb = "https://img.youtube.com/vi/" . $youtubearr[1] . "/0.jpg";
			}
		}
		// dd($productDetail);
		$this->PageData['ytthumb'] = $ytthumb;
		//Get youtube thumb image End

		//================================= Reward Point Text Start Here ================================
		if (isset($productDetail->dealprice) && $productDetail->dealprice != '') {
			$reward_points_text = number_format($productDetail->dealprice, 0);
		} else {
			$reward_points_text = number_format($productDetail->product_price, 0);
		}

		if (strtolower(Session::get('eusertype')) == 'wholesaler') {
			$new_reward_points_text = "";
		} else {

			$reward_points_text = $reward_points_text * $productDetail->point_multiplier;

			$new_reward_points_text = " Earn <strong>" . $reward_points_text . " Points</strong>.";
		}

		$this->PageData['reward_points_text'] = $new_reward_points_text;
		//================================= Reward Point Text End Here ================================


		$avg_rate = $this->getProductAverageRating($productDetail->sku);

		$arr_product_review = $this->getProductReview($productDetail->sku);
		if (count($arr_product_review) <= 0) {
			for ($j = 0; $j < count($productDetail->referenced_products_keys); $j++) {

				for ($i = 0; $i < count($productDetail->referenced_products[$productDetail->referenced_products_keys[$j]]); $i++) {

					$arr_product_review = $this->getProductReview($productDetail->referenced_products[$productDetail->referenced_products_keys[$j]][$i]['sku']);
					$review = "No";

					if (count($arr_product_review) > 0) {
						$avg_rate = $this->getProductAverageRating($productDetail->referenced_products[$productDetail->referenced_products_keys[$j]][$i]['sku']);
						$review = "Yes";
						break;
					}
					if ($review == "Yes") {
						break;
					}
				}
				if ($review == "Yes") {
					break;
				}
			}
		}

		//   dd($arr_product_review);

		$this->PageData['avg_rate'] = $avg_rate;
		$this->PageData['arr_product_review'] = $arr_product_review;

		// dd($this->PageData['arr_product_review']);

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


		$manuf_prods_wom = $this->list_manufacturer_gender($productDetail->products_id, $productDetail->brand_id, $productDetail->imanufactureid, 'W');
		$manuf_prods_man = $this->list_manufacturer_gender($productDetail->products_id, $productDetail->brand_id, $productDetail->imanufactureid, 'M');
		$manuf_prods_uni = $this->list_manufacturer_gender($productDetail->products_id, $productDetail->brand_id, $productDetail->imanufactureid, 'U');

		// dd($manuf_prods_man);

		$this->PageData['manuf_prods_man'] = ($manuf_prods_man ? $manuf_prods_man : array());
		$this->PageData['manuf_prods_wom'] = ($manuf_prods_wom ? $manuf_prods_wom : array());
		$this->PageData['manuf_prods_uni'] = ($manuf_prods_uni ? $manuf_prods_uni : array());


		/*
		* Recent viewed item Logic start
		*/
		// $arr_recent_item = array();
		if (Session::has('RECENT_VIEWED_ITEMS') && Session::get('RECENT_VIEWED_ITEMS') && count(Session::get('RECENT_VIEWED_ITEMS')) > 0) {
			$arr_recent_item = $this->getRecent_ViewedItems($products_id);
		}

		$this->PageData['arr_recent_item'] = (isset($arr_recent_item) ? $arr_recent_item : array());


		if (!Session::has('RECENT_VIEWED_ITEMS')) {
			$productArry = array();
			Session::put('RECENT_VIEWED_ITEMS', $productArry);
			Session::save();
		}

		if (Session::has('RECENT_VIEWED_ITEMS') && !in_array((int)$products_id, Session::get('RECENT_VIEWED_ITEMS'))) {
			$productArry = Session::get('RECENT_VIEWED_ITEMS');
			array_push($productArry, (int)$products_id);
			Session::put('RECENT_VIEWED_ITEMS', $productArry);
			Session::save();
		}
		/*
		* Recent viewed item Logic End
		*/


		// Max2Day Start 
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
		// Max2Day End

		########################## CODE FOR REFEREAL URL START ##########################################
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


		################################# Afterpay Checkout Display Setting #######################################
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
		################################ Afterpay Checkout Display Setting End #################################################

		$logo_img = $this->GetBogoProducts($productDetail->sku, $productDetail->category_id, $productDetail->imanufactureid, $dealofdayRS, $daydealofdayRS);
		// dd($logo_img);
		$this->PageData['logo_img_bogo'] = $logo_img;

		return view('product.details')->with($this->PageData);
	}
	
}
