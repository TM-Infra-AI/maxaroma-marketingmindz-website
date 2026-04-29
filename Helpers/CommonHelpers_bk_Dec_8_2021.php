<?php
//use DB;
//use Mail;
//use Cache;
//use URL;

use function PHPUnit\Framework\isEmpty;
use Request;

function checkLogin(){	
	if(Auth::user()){
			return redirect('/myaccount.html');
	}
}
function PrintObj($Obj)
{
	if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
	{
		dd($Obj);
	}		
}
function setURLValue($url)
{   
	$SpecialCharacters = array("(",")","'","#","%","]","[",":",".","/");		
	$url = str_replace(" ","-",$url);
	$url = str_replace($SpecialCharacters, "", $url);
	return strtolower($url);
}
	
function Price($Amount)
{
	$Amount = ($Amount == ''?0.00:(float)$Amount);
	$CurrencySymbol = '$';
	if(Session::has('currency_symbol') && Session::get('currency_symbol') != '')
		$CurrencySymbol = Session::get('currency_symbol');
    $conversion_rate = Session::get('currency_rate');    
    $Amount = $Amount * $conversion_rate;
	$FormatAmount = $CurrencySymbol.NumberFormat($Amount);
	return $FormatAmount;
}
	
function GetCountries()
{
	$countryArr = [];
	$Countries = \App\Models\Countries::orderBy('countries_name', 'asc')->get();
	foreach ( $Countries as $Country ) {			
		$countryArr[$Country['countries_iso_code_2']] = $Country['countries_iso_code_2'].' '.$Country['countries_name'];
	}		
	return $countryArr;
}

function GetStates()
{
	$stateArr = [];
	$states = App\Models\State::orderBy('name', 'asc')->get();
	foreach ( $states as $state ) {			
		$stateArr[$state['code']] = $state ['code'].' '.$state ['name'];
	}		
	return $stateArr;
}
function generalsetting($variable="",$section=1) {
	if($variable=="") {
		$Setting = \App\Models\SiteSettings::where('section','=',$section)->orderBy('display_order')->get();
		return $Setting;
	}else {
		$Setting = \App\Models\SiteSettings::where('section','=',$section)
					->where('var_name','=',$variable)
					->orderBy('site_settings_id')->get();
		return $Setting[0]->setting;
	}
}

function GetCustomerAttribute($Attr='')
{
	if($Attr!='')
	{
		$CustomerAttributes = \App\Models\CustomerAttribute::where('attributename','=',$Attr)->get();
		if($CustomerAttributes->count() > 0)
			return $CustomerAttributes; 
	}
}

function isMobile() {
	// return false;
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", Request::header('user-agent'));
}

function GetBottomHtml()
{
	$BottomHtmlText = Cache::remember('BottomHtmlText', 3600, function() {
		$BottomHtml = \App\Models\BottomHtml::all();
		$shipping_policy = '<a href="'.config('global.SITE_URL').'shipping-policy.html">Shipping Policy</a>';
		$BottomHtml = stripcslashes($BottomHtml[0]->html_text);	
		$BottomHtmlText = str_replace('{$Site_URL}',config('global.SITE_URL'),$BottomHtml);
		$BottomHtmlText = str_replace('{$shipping_policy}',$shipping_policy,$BottomHtmlText);
		return $BottomHtmlText;		
	});
}

function BrandsList($BrandChar='')
{
	$BrandList = [];
	if($BrandChar != '')
	{
		$BrandQry = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.status','=','1');
		if($BrandChar == '#')
			$BrandQry->where('m.vmanufacture','regexp','^[0-9]+');
		else
			$BrandQry->where('m.vmanufacture','like',$BrandChar.'%');
		$Brands = $BrandQry->groupBy('m.imanufactureid')->orderBy('m.vmanufacture')->get();			
		
		if($Brands && $Brands->count() > 0)
		{
			foreach($Brands as $Brand)
			{
				$Name = remove_special_chars($Brand->vmanufacture);
				$BrandList[]=[
					'Name' => $Brand->vmanufacture,
					'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid, 
				];
			}
		}
		return $BrandList;
	}
	
	foreach (range('A', 'Z') as $char){
		$Brands = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.status','=','1')
					->where('m.vmanufacture','like',$char.'%')
					->groupBy('m.imanufactureid')
					->orderBy('m.vmanufacture')
					->get();			
		
		if($Brands && $Brands->count() > 0)
		{
			foreach($Brands as $Brand)
			{
				$Name = remove_special_chars($Brand->vmanufacture);
				$BrandList[$char][]=[
					'Name' => $Brand->vmanufacture,
					'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid, 
				];
			}
		}
	}
	
	$Brands = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.status','=','1')
					->where('m.vmanufacture','regexp','^[0-9]+')
					->groupBy('m.imanufactureid')
					->orderBy('m.vmanufacture')
					->get();
	foreach($Brands as $Brand)
	{
		$Name = remove_special_chars($Brand->vmanufacture);
		$BrandList['#'][]=[
			'Name' => $Brand->vmanufacture,
			'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid, 
		];
	}
	return $BrandList;	
}

function remove_special_chars($str) {
	$str = preg_replace("/[,^!<>@\/()\"&#$*~`{}'?:;.?%]*/", "", trim($str));
	$str = str_replace("  ", " ", strtolower($str));
	$str = str_replace(" ", "-", strtolower($str));
	$str = str_replace("--", "-", strtolower($str));
	$str = str_replace("--", "-", strtolower($str));
	return $str;
}
	
function SetCatTree($CatArray=0)
{
	$ProdCats = [];
	//$ProdCatsData = Cache::remember('AllCategoriesInfo', 3600, function(){
		$Categories = \App\Models\Category::orderBy('category_id')->get();
		$AllCats = NewCatTree($Categories);
		$HomeLink = config('global.SITE_URL');
		$BredCrum = [];$SubCatsTree=[];$SubCats=[];
		$key = 0;
		foreach($AllCats as $MainCat)
		{	
			if($MainCat->category_id == $CatArray || $CatArray == 0)
			{
				$SubCatsTree[$key][]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'Level' => 0];
				$SubCatBredcrum = ucwords($MainCat->category_name);
				$BredCrum[0]['id'] = 0;
				$BredCrum[0]['title'] = 'Home';
				$BredCrum[0]['link'] = $HomeLink;
				$BredCrum[1]['id'] = $MainCat->category_id;
				$BredCrum[1]['title'] = ucwords($MainCat->category_name);
				$BredCrum[1]['link'] = $HomeLink.remove_special_chars(trim($MainCat->category_name)) . '/cid/' . $MainCat->category_id;
				
				$ProdCats[$MainCat->category_id] = [
					'slug' => remove_special_chars($MainCat->category_name).'/',
					'category_name' => $MainCat->category_name,
					'bredcrum' => $BredCrum,
					'subcatbredcrum' => $SubCatBredcrum,
					'parent_id' => 0,
					'root_parent_id' => 0,
				];
				if(isset($MainCat->childs) && count($MainCat->childs) > 0 ){
					//$SubCatsTree[$key][]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'Level' => 0];
					foreach($MainCat->childs as $SubLevel1){
						$SubAllCats = isset($SubLevel1->childs)?$SubLevel1->childs:[];
						$SubCatBredcrum1 = $SubCatBredcrum.' - '.ucwords($SubLevel1->category_name);
						$BredCrum[2]['id'] = $SubLevel1->category_id;
						$BredCrum[2]['title'] = ucwords($SubLevel1->category_name);
						$BredCrum[2]['link'] = $HomeLink.remove_special_chars(trim($SubLevel1->category_name)) . '/cid/' . $SubLevel1->category_id;
						$ProdCats[$SubLevel1->category_id] = [
							'slug' => remove_special_chars($MainCat->category_name).'/'.remove_special_chars($SubLevel1->category_name).'/',
							'category_name' => $SubLevel1->category_name,
							'bredcrum' => $BredCrum,
							'subcatbredcrum' => $SubCatBredcrum1,	
							'parent_id' => $SubLevel1->category_id,
							'root_parent_id' => $MainCat->category_id,
						];
						$SubCatsTree[$key][]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name,'hasChild' => ($SubAllCats != null && count($SubAllCats) > 0) ? 'Yes':'No', 'Level' => 1];		
						
						if($SubAllCats){
							foreach($SubAllCats as $SubLevel2){
								$SubCatBredcrum2= $SubCatBredcrum.' - '.ucwords($SubLevel1->category_name).' - '.ucwords($SubLevel2->category_name);
								$BredCrum[3]['id'] = $SubLevel2->category_id;	
								$BredCrum[3]['title'] = ucwords($SubLevel2->category_name);
								$BredCrum[3]['link'] = $HomeLink.remove_special_chars(trim($SubLevel2->category_name)) . '/cid/' . $SubLevel2->category_id;
								$ProdCats[$SubLevel2->category_id] = [
									'slug' => remove_special_chars($SubLevel1->category_name).'/'.remove_special_chars($SubLevel2->category_name).'/',
									'category_name' => $SubLevel2->category_name,
									'bredcrum' => $BredCrum,
									'subcatbredcrum' => $SubCatBredcrum2,
									'parent_id' => $SubLevel2->category_id,
									'root_parent_id' => $MainCat->category_id,
								];
								$SubCatsTree[$key][]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name, 'Level' => 2];
								$key++;
							}
						}
						$key++;
					}
				}
			}
		}
	return ['CatForProd' => $ProdCats, 'CatTree' => $SubCatsTree];
}

function GetMainCatsTree($CatArray)
{
	$Categories = \App\Models\Category::where('status','=','1')->orderBy('category_id')->get();
	$AllCats = NewCatTree($Categories);
	$SubCatsTree=[];$key=0;$SubCats=[];
	foreach($AllCats as $MainCat)
	{
		$SubCatsTree[$key][]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'link' => config('global.SITE_URL').remove_special_chars($MainCat->category_name).'/cid/'.$MainCat->category_id,'Level' => 0];
		if(in_array($MainCat->category_id,$CatArray) || $CatArray[0] == 0)
		{
			$SubCats[]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'link' => config('global.SITE_URL').remove_special_chars($MainCat->category_name).'/cid/'.$MainCat->category_id];
			if(isset($MainCat->childs) && count($MainCat->childs) > 0 ){
				$SubCatsTree[$key][]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'link' => config('global.SITE_URL').remove_special_chars($MainCat->category_name).'/cid/'.$MainCat->category_id, 'Level' => 0];
				
				foreach($MainCat->childs as $SubLevel1){
					$SubCats[]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name, 'link' => config('global.SITE_URL').remove_special_chars($SubLevel1->category_name).'/scid/'.$SubLevel1->category_id];
					$SubAllCats = isset($SubLevel1->childs)?$SubLevel1->childs:[];
					$SubCatsTree[$key][]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name,'hasChild' => ($SubAllCats != null && count($SubAllCats) > 0) ? 'Yes':'No', 'link' => config('global.SITE_URL').remove_special_chars($SubLevel1->category_name).'/scid/'.$SubLevel1->category_id,'Level' => 1];
					
					if($SubAllCats){
						foreach($SubAllCats as $SubLevel2){
							$SubCats[]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name, 'link' => config('global.SITE_URL').remove_special_chars($SubLevel2->category_name).'/scid/'.$SubLevel2->category_id];	
							$SubCatsTree[$key][]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name,  'link' => config('global.SITE_URL').remove_special_chars($SubLevel2->category_name).'/scid/'.$SubLevel2->category_id, 'Level' => 2];
							$key++;
						}
					}
					$key++;		
				}
			} 				
		}
	}
	return ['CatList' => $SubCats, 'CatTree' => $SubCatsTree];
}

function NewCatTree($Cats)
{
	$childs = array();
	foreach($Cats as $item){
		$childs[$item->parent_id][] = $item;
		unset($item);
	}
	foreach($Cats as $item){
		if (isset($childs[$item->category_id])){
			$item['childs'] = $childs[$item->category_id];
		}
	}
	return $childs[0];
}

function SetProductURL($ProdID,$ProdName,$CategoryID)
{
	$ProdLink = config('global.SITE_URL');
	$ProdName = remove_special_chars($ProdName);
	$AllCategoriesInfo = config('CATEGORY_INFO');
	$CatInfo = $AllCategoriesInfo['CatForProd'];
	if(!isset($CatInfo[$CategoryID]))
	{
		$CatId = DB::table('pu_products_category as pc')
			->select('c.category_id')
			->join('pu_category as c', 'pc.category_id', '=', 'c.category_id')
            		->where('pc.products_id', '=', $ProdID)
            		->where('c.status', '=', '1')
			->orderBy('c.display_position')
			->orderBy('c.category_name')
			->offset(0)->limit(1)->get();

		$CategoryID = $CatId[0]->category_id;

	}
	$CatLink = $CatInfo[$CategoryID]['slug'];
	$ProdLink.=$CatLink.$ProdName.'/pid/'.$ProdID.'/'.$CategoryID;
	return $ProdLink;
}	
function GetCategoryMenu()
{
	$Menu = [];
	$Menu = Cache::remember('Menu', 3600, function() {
		$MainBrands = \App\Models\MainbrandLanding::where('title','!=','')->where('is_show','=','Yes')->get();
		$i=0;
		if($MainBrands && $MainBrands->count() > 0){
			$MainBrandSubCats = \App\Models\BrandLandling::where('title','!=','')->where('sku','!=','')->where('status','=','1')->orderBy('position')->get();
			if($MainBrandSubCats && $MainBrandSubCats->count() > 0)
			{
				$MainBanner = '';
				if(file_exists(config('global.CAT_IMAGE_PATH').$MainBrands[0]->mega_menu_image) && $MainBrands[0]->mega_menu_image !='')
				{
					$MainBanner = config('global.CAT_IMAGE_URL').$MainBrands[0]->mega_menu_image;
				}
				$Menu[$i] = [
					'category_id' => '',
					'category_name' => $MainBrands[0]->title,
					'link' => 'javascript:void(0);',
					'banner' => $MainBanner,
				];
				foreach($MainBrandSubCats as $MainBrandSubCat)
				{
					$Menu[$i]['subcats'][] = [
						'category_id' => '',
						'category_name' => $MainBrandSubCat->title,
						'link' => config('global.SITE_URL').remove_special_chars($MainBrandSubCat->title).'/tpid/'.$MainBrandSubCat->id,
					]; 
				}
			}
			$i++;
		}
		$Categories = \App\Models\Category::where('status','=','1')->where('display_top','=','Yes')->orderBy('display_position')->limit(6)->get();
		foreach($Categories as $Key => $Category)
		{
			$CatBanner = '';				
			if(file_exists(config('global.CAT_IMAGE_PATH').$Category->mega_menu_image) && $Category->mega_menu_image !='')
			{
				$CatBanner = config('global.CAT_IMAGE_URL').$Category->mega_menu_image;
			}
			if($Category->category_id == '12')
				$CatBanner = config('global.SITE_IMAGES').'product_newa.jpg';
			$Menu[$i]=[
				'category_id' => $Category->category_id,
				'category_name' => $Category->category_name,
				'subcats' => GetSubCategories($Category->category_id,1),
				'link' => config('global.SITE_URL').remove_special_chars($Category->category_name).'/cid/'.$Category->category_id,
				'banner' => $CatBanner,
			];
			$i++;
		}
		$Menu[$i]=[
			'category_id' => '',
			'category_name' => 'Brands',
			'subcats' => [],
			'link' => config('global.SITE_URL').'brand-name-perfumes.html',
		];	
		return $Menu;
	});
}

function GetSubCategories($Parent=0,$Level=1)
{
	$Cats = [];
	$Categories = \App\Models\Category::where('status','=','1')
					->where('parent_id','=',$Parent)
					->orderBy('display_position')
					->orderBy('category_name')
					->limit(10)
					->get();
	foreach($Categories as $Key => $Category)
	{
		$Cats[] = [
			'category_id' => $Category->category_id,
			'category_name' => $Category->category_name,
			'subcats' => GetSubCategories($Category->category_id,2),
			'link' => config('global.SITE_URL').remove_special_chars($Category->category_name).'/scid/'.$Category->category_id,
		];
	}
	if($Level == 1)	
	{
		$Cats[] = [
			'category_id' => '',
			'category_name' => 'Sale',
			'subcats' => [],
			'link' => config('global.SITE_URL').'perfumesale/p4u/special-sl/view',
		];
		if(getDealofweekCount())
		{
			$Cats[] = [
				'category_id' => '',
				'category_name' => 'Weekly Deals',
				'subcats' => [],
				'link' => config('global.SITE_URL').'dealofweek.html',
			];
		}
		$Cats[] = [
			'category_id' => '',
			'category_name' => 'Coupons',
			'subcats' => [],
			'link' => config('global.SITE_URL').'coupons-promotional.html',
		];
		if($Parent == '1')
		{
			$UnboxCat = \App\Models\Category::find(198);
			if($UnboxCat && $UnboxCat->count() > 0)
			{
				$Cats[] = [
					'category_id' => $UnboxCat->category_id,
					'category_name' => 'Unboxed Items',
					'subcats' => [],
					'link' => config('global.SITE_URL').remove_special_chars($UnboxCat->category_name).'/cid/'.$UnboxCat->category_id,
				];
			}				
		}			
	}		
	return $Cats;
}	

function getDealofweekCount() {
	$Dealofweek  = \App\Models\Dealofweek::where('start_date','>=',date('Y-m-d'))->where('end_date','<=',date('Y-m-d'))->where('status','=','1')->where('deal_type','=','Weekly')->get();
	if($Dealofweek && $Dealofweek->count() > 0){
		return true;
	} else {
		return false;
	}
}

function GetReviewOfProducts()
{
	$ProductReviews = DB::table('pu_products_review as pr')
						->join('pu_products as p','pr.sku','=','p.sku')
						->join('pu_products_category as pc','p.products_id','=','pc.products_id')
						->join('pu_category as c','pc.category_id','=','c.category_id')
						->join('pu_manufacture as m','p.imanufactureid','=','m.imanufactureid')
						->select('pr.first_name','pr.city','pr.state','pr.country','pr.star_rate','pr.user_review','p.products_id','p.sku', 'p.product_name','p.brand_id','p.imanufactureid','m.vmanufacture','p.gender','pc.category_id')
						->where('pr.approved','=','Yes')
						->where('pr.star_rate','>','3')
						->where('p.status','=','1')
						->orderBy('pr.review_id','desc')
						->groupBy('p.products_id')
						->limit(10)
						->get();
	$AllReviewes = [];
	if($ProductReviews && $ProductReviews->count() > 0)
	{
		foreach($ProductReviews as $Review)
		{
			$Review->referencedName = $Review->product_name;
			$AllReviewes[]=$Review;				
		}
	}
	return $AllReviewes;
}

function GetMailTemplate($Template)
{
	if($Template != "")
	{
		$TemplateDetails = \App\Models\EmailTemplates::select('subject','mail_body')
							->where('template_var_name','=',$Template)
							->where('status','=','1')
							->get();
		if($TemplateDetails && $TemplateDetails->count() > 0)
			return $TemplateDetails;
		else 
			return false;	
	} else {
		return false;
	}
}
function SendMail($Subject,$EmailBody,$To,$From,$CC='',$BCC='')
{
	$SendMail = $MailSend = Mail::send(array(), array(), function ($message) use ($To,$Subject,$EmailBody,$From,$CC,$BCC) {
		//$message->from($From,"Maxaroma");
		$message->to($To)
			->subject($Subject)
			->setBody($EmailBody, 'text/html');
		if($CC != '')
			$message->cc($CC);
		if($BCC != '')
			$message->bcc($BCC);	
	});		
}	

function FreeGiftValue($subtotal) {
	$free_gift_array = array();
	if($subtotal >= config('Settings')->FREEGIFT_VALUE) {
		if(config('Settings')->BEAUTY_SAMPLE == "Yes") {
			$free_gift_array[] = "Beauty & Accessories Sample";
		}
		if(config('Settings')->PERFUME_SAMPLE == "Yes") {
			$free_gift_array[] = "Perfume Sample";
		}
	} else {
		return $free_gift_array;
	}
	return $free_gift_array;
}

function dateDiffInDays($date1, $date2)
{
    $diff = strtotime($date2) - strtotime($date1);
    return abs(round($diff / 86400));
}

/** DROPSHIPPER FUNCTIONS START **/

function CheckAvailableShippingMethod($shipping_mode_id = NULL, $ship_country,$ship_state,$ship_zip,$subTotal,$TotalQuantity)
{
	$shipping_mode_id = (int)$shipping_mode_id;

	$ShippingMethodRS = \App\Models\ShippingMode::where('shipping_mode_id','=',$shipping_mode_id)
						->where('status','=','1')
						->get();

	/*if ($ship_country != "")
	{
		## this condition is for Z + S + C
		$sql = "SELECT * FROM `".TABLE_PREFIX."shipping_rule` WHERE shipping_mode_id = '".$shipping_mode_id."'
				AND zipcode_to >= '".$ship_zip."' AND zipcode_from <= '".$ship_zip."'
				AND state like '%".$ship_state."%' AND country like '%".$ship_country."%'";
		$rid = $obj->select($sql);

		## this condition is for Z + C
		if (count($rid) <= 0)
		{
			$sql = "SELECT * FROM `".TABLE_PREFIX."shipping_rule`
					WHERE shipping_mode_id = '".$shipping_mode_id."'
					AND zipcode_to >='".$ship_zip."' AND zipcode_from <= '".$ship_zip."'
					AND country LIKE '%".$ship_country."%'";
			$rid = $obj->select($sql);

			## this condition is for S + C
			if (count($rid) <= 0)
			{
				$sql = "SELECT * FROM `".TABLE_PREFIX."shipping_rule`
						WHERE shipping_mode_id = '".$shipping_mode_id."' AND state LIKE '%".$ship_state."%'
						AND country LIKE '%".$ship_country."%'";
				$rid = $obj->select($sql);

				## this condition is for only C
				if (count($rid) <= 0)
				{
					$sql = "SELECT * FROM `".TABLE_PREFIX."shipping_rule`
							WHERE shipping_mode_id = '".$shipping_mode_id."'
							AND country like '%".$ship_country."%'
							AND state = '' AND zipcode_to = '' AND zipcode_from = ''";
					$rid = $obj->select($sql);

				}
			}
		}

		if (count($rid) > 0 )
		{
             return (int)$ShippingMethodRS[0]['shipping_mode_id'];

		}
		else
		{
			return false;
		}
	}
	else
	{
		return false;
	}*/
}
function GetBanner($bannertype,$imanufactureid='',$sku='')
{
	/*	
	$bannertype = explode(",",$bannertype);
	$condition = '';
			$bannercondition = '';
			$bannerShow = "No";
			for($m=0;$m<count($bannertype);$m++) { 
					$condition.=" (((start_date <= '".date('Y-m-d')."' AND end_date >= '".date('Y-m-d')."') OR (start_date = '0000-00-00' AND end_date >= '0000-00-00')) AND section = ".$bannertype[$m].") OR "; 
					if($bannertype[$m]=="'PRODUCT BANNER'")
					{
						$bannercondition = 'PRODUCT BANNER';
					}	
				}	
			$condition = substr($condition,0,-3);
			
			$BrandDetail = HomeImage::where('imanufactureid','=',$imanufactureid)
									->where('start_date','<=',date("Y-m-d")
									->where('end_date','<=',date("Y-m-d")
									->where('start_date','<=',date("Y-m-d")
									->where('start_date','<=',date("Y-m-d"))->get();
	*/
}

/** DROPSHIPPER FUNCTIONS END **/

function GetOrderStatusClass($status) {
	if($status == 'Pending') {
		$status_class = 'text_red';
	} elseif($status == 'Completed') {
		$status_class = 'text_green';
	} elseif($status == 'Canceled') {
		$status_class = 'text_red';
	} elseif($status == 'Declined') {
		$status_class = 'text_red';
	} elseif($status == 'Pending - PhoneOrder') {
		$status_class = 'text_red';
	} else {
		$status_class = '';
	}
	return $status_class;
}

function GetProductAverageRating($TotalReview,$TotalRate)
{
	$average_bottom = (int)@($TotalRate/$TotalReview);
	$average_real = @($TotalRate/$TotalReview);
	if(($average_real-$average_bottom)>=0.5)
		$average_rate = ceil($TotalRate/$TotalReview);
	else
		$average_rate =  $average_bottom;
	if($average_rate>5)
		$average_rate=5;
	return $average_rate;	
}

function GetDealOfWeek($SKU='',$DealType='Weekly',$ForPage='',$ismultipleSku = array())
{
	$DealDetails =[];
	$DealQuery = DB::table('pu_dealofweek as dw')
					->select('dw.dealofweek_id','dw.description','dw.deal_type','dw.discount_coupon_flag','dw.product_sku','dw.start_date','dw.end_date','dw.deal_price','p.retail_price','p.product_name','p.image','p.imanufactureid','p.short_description')
					->join('pu_products as p','dw.product_sku','=','p.sku')
					->where('dw.status','=','1')
					->where('dw.start_date','<=',date('Y-m-d'))->where('dw.end_date','>=',date('Y-m-d'))
					->where('dw.deal_type','=',$DealType);
	if($SKU != '' && count($ismultipleSku) == 0)
			$DealQuery->where('dw.product_sku','=',$SKU);
	
	if($SKU == '' && count($ismultipleSku) > 0)
		$DealQuery->whereIn('dw.product_sku',$ismultipleSku);
	
	if($ForPage == 'Cart')
	{
		$DealQuery->join('pu_products_one as po','p.products_id','=','po.products_id');
		$DealQuery->where(function($query){
			$query->orWhere('p.status','=','1');
			$query->OrWhere(function($qry){
				$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');	
			});
		});
		$DealQuery->orderBy('dw.dealofweek_id','desc')->limit(1);
	} else {
		$DealQuery->where('p.status','=','1');
	}		
	$Dealofweek = $DealQuery->get();
	
	if($Dealofweek && $Dealofweek->count() > 0) 
	{
		foreach($Dealofweek as $Deal)
		{
			$DealDetails[$Deal->product_sku]['deal_price'] = $Deal->deal_price;
			$DealDetails[$Deal->product_sku]['start_date'] = $Deal->start_date;
			$DealDetails[$Deal->product_sku]['end_date'] = $Deal->end_date;
			$DealDetails[$Deal->product_sku]['deal_type'] = $DealType;
			$YouSave=0;
			$YouSavePrice = 0;
			if($Deal->retail_price > 0 && $Deal->retail_price > $Deal->deal_price)
			{
				$YouSave = ($Deal->retail_price - $Deal->deal_price) / $Deal->retail_price;
				$YouSave = $YouSave * 100;
				$YouSave = number_format($YouSave, 0);
				$YouSavePrice = $Deal->retail_price - $Deal->deal_price;
			}
			$DealDetails[$Deal->product_sku]['yousave'] = $YouSave;
			$DealDetails[$Deal->product_sku]['yousaveprice'] = $YouSavePrice;
			
			$imgname = stripslashes($Deal->image);
			if(file_exists(config('global.PRD_THUMB_IMG_PATH').$imgname) and !empty($imgname))
			{
				$newimageVal = config('global.PRD_THUMB_IMG_PATH').$imgname;
				$verP =filemtime($newimageVal);
				$thumb_image = config('global.PRD_THUMB_IMG_URL').$imgname."?ver=".$verP;
			}else{
				$thumb_image = config('global.NO_IMAGE_THUMB');
			}
			$Deal->image = $thumb_image;
			$DealDetails[$Deal->product_sku]['description'] = $Deal->description;
			$DealDetails[$Deal->product_sku]['discount_coupon_flag'] = $Deal->discount_coupon_flag;
		}
	}
	return $DealDetails;	
}


function GetCancelReasons() {
	$CancelReason = array("Item no longer needed","Better price elsewhere","Purchased item by mistake","Changed my mind","Other");
	return $CancelReason;
}

function GetReturnReasons() {
	$ReturnReason = array("Item no longer needed","Quality not as expected","Not as described/pictured","Item Damaged","Wrong item received","Better price elsewhere","Purchased item by mistake","Changed my mind","Other");
	return $ReturnReason;
}

function GetRefundReasons() {
	$RefundReason = array("Customer order item by mistake","Item did not come back in its original condition","customer changed mind or no longer needed","Other");
	return $RefundReason;
}

function CanonicalURL()
{
	$CurrentURL = URL::current();
	$CanonicalURL = '';
	
	//Home Page 
	$CheckHomeURLForSlash = substr(config('global.SITE_URL'),strlen(config('global.SITE_URL'))-1,strlen(config('global.SITE_URL')));
	if($CheckHomeURLForSlash == '/')
	{
		$HomeURL = substr(config('global.SITE_URL'),0,strlen(config('global.SITE_URL'))-1);
		if($CurrentURL == $HomeURL)
			$CanonicalURL = $HomeURL;
	}
	//Sub Category Pages
	if(strstr($CurrentURL,'scid'))
	{
		$URLV = explode("/",$CurrentURL);
		$CanonicalURL = $CurrentURL;
		if($URLV[count($URLV)-1] == '2')
		{
			$CanonicalURL = config('global.SITE_URL')."fragrances/niche-perfumes/p4u/cid-2/page-all/view";
		}
	}
	
	//Product Listing Pages
	if(strstr($CurrentURL,'p4u'))
	{
		$URLV 	  = explode("/p4u/",$CurrentURL);
		$urlValue = str_replace("/peraromares/","",$URLV[0]);
		$urlValue = str_replace("/peraromares","",$urlValue);
		
		$NewCanURL = str_replace("/peraromares/","",$CurrentURL);
		$NewCanURL = str_replace("/peraromares","",$CurrentURL);
		$NewCanURL = str_replace("pp-all/","",$CurrentURL."/");
		$NewCanURL = str_replace("/view/","",$CurrentURL."/");
		$NewCanURL = str_replace("/view","",$CurrentURL."/");
		$NewCanURL = $NewCanURL."pp-all/view";
		
		$CanonicalURL = $NewCanURL;
		
		if($URLV[1] == 'cid-1/view' || $URLV[1] == 'cid-1/pp-all/view' || $URLV[1] == 'cid-1/page-all/view')
		{
			$CanonicalURL = config('global.SITE_URL')."fragrances/p4u/cid-1/page-all/view";
		}
		else if($URLV[1] == 'cid-3/special-fe/view' || $URLV[1] == 'cid-3/special-na/view' || $URLV[1] == 'cid-3/special-ts/view' || $URLV[1] == 'cid-3/special-cl/view' || $URLV[1] == 'cid-3/view' || $URLV[1] == 'cid-3/page-all/view')
		{
			$CanonicalURL = config('global.SITE_URL')."fragrances-for-men/p4u/cid-3/page-all/view";
		}
		else if($URLV[1] == 'cid-5/view' || $URLV[1] == 'cid-5/pp-all/view' || $URLV[1] == 'cid-5/page-all/view' || $URLV[1] == 'cid-5/page-all/pp-all/view')
		{
			$CanonicalURL = config('global.SITE_URL')."fragrances-for-women/p4u/cid-5/page-all/view";
		}
		else if($URLV[1] == 'cid-2/special-fe/view' || $URLV[1] == 'cid-2/special-na/view' || $URLV[1] == 'cid-2/special-ts/view' || $URLV[1] == 'cid-2/special-cl/view' || $URLV[1] == 'cid-2/view' || $URLV[1] == 'cid-2/page-all/view' || $URLV[1] == 'cid-2/page-all/pp-all/view')
		{
			$CanonicalURL = config('global.SITE_URL')."fragrances/niche-perfumes/p4u/cid-2/page-all/view";
		}
		else if($URLV[1] == 'cid-1/special-cp/view' || $URLV[1] == 'cid-1/special-cp/pp-all/view' || $URLV[1] == 'cid-1/special-cp/page-all/view' || $URLV[1] == 'cid-1/special-cp/page-all/pp-all/view')
		{
			$CanonicalURL = config('global.SITE_URL')."fragrances/celebrity-perfumes/p4u/cid-1/special-cp/page-all/view";
		}
		else if($URLV[1] == 'cid-4/special-fe/view' || $URLV[1] == 'cid-4/special-na/view' || $URLV[1] == 'cid-4/special-ts/view' || $URLV[1] == 'cid-4/special-cl/view' || $URLV[1] == 'cid-4/view' || $URLV[1] == 'cid-4/pp-all/view' || $URLV[1] == 'cid-4/page-all/view' || $URLV[1] == 'cid-4/page-all/pp-all/view')
		{
			$CanonicalURL = config('global.SITE_URL')."fragrances/unisex-perfumes/p4u/cid-4/page-all/view";
		}
		else if($URLV[1] == 'mid-43/view' || $URLV[1] == 'mid-43/pp-all/view')
		{
			$CanonicalURL = config('global.SITE_URL')."tom-ford-fragrances/p4u/mid-43/pp-all/view";
		}
		else if($URLV[1] == 'mid-14/view' || $URLV[1] == 'mid-14/pp-all/view')
		{
			$CanonicalURL = config('global.SITE_URL')."creed-fragrances/p4u/mid-14/pp-all/view";
		}
		else if($URLV[1] == 'mid-312/view' || $URLV[1] == 'mid-312/pp-all/view')
		{
			$CanonicalURL = config('global.SITE_URL')."amouage-fragrances/p4u/mid-312/pp-all/view";
		}
		else if($URLV[1] == 'mid-282/view' || $URLV[1] == 'mid-282/pp-all/view')
		{
			$CanonicalURL = config('global.SITE_URL')."parfums-de-marly-fragrances/p4u/mid-282/pp-all/view";
		}
	}
	return $CanonicalURL;
}
function getstaticpages($flag)
{
	if(is_array($flag))
	{
	$static_res  =$static_res = \App\Models\StaticPages::whereIn('name',$flag)->get();
	}
	else
	{
	$static_res  =$static_res = \App\Models\StaticPages::where('name','=',$flag)->get();	
	}
    return $static_res;
}


function GenRequiredFields()
{
	return array(
			    'orders_no',
			    'sku',
			    'qty',
			    'first_name',
			    'last_name',
			    'address1',
			    'city',
			    'state',
			    'zip',
			    'country',
			    'email'
			);
}

function GenCSVFieldsArr()
{
	return array(
			    'orders_no' => array(
			        'import_field' => 'orders_no',
			        'export_field' => 'orders_no',
			        'import_header_val' => 'Orders No',
			        'export_header_val' => 'Orders No'
			    ),
			    'sku' => array(
			        'import_field' => 'sku',
			        'export_field' => 'sku',
			        'import_header_val' => 'SKU',
			        'export_header_val' => 'SKU'
			    ),
			    'qty' => array(
			        'import_field' => 'quantity',
			        'export_field' => 'quantity',
			        'import_header_val' => 'Qty',
			        'export_header_val' => 'Qty'
			    ),
			    'first_name' => array(
			        'import_field' => 'ship_first_name',
			        'export_field' => 'ship_first_name',
			        'import_header_val' => 'First Name',
			        'export_header_val' => 'First Name'
			    ),
			    'last_name' => array(
			        'import_field' => 'ship_last_name',
			        'export_field' => 'ship_last_name',
			        'product_field' => 'ship_last_name',
			        'import_header_val' => 'Last Name',
			        'export_header_val' => 'Last Name'
			    ),
			    'address1' => array(
			        'import_field' => 'ship_address1',
			        'export_field' => 'ship_address1',
			        'product_field' => 'ship_address1',
			        'import_header_val' => 'Address1',
			        'export_header_val' => 'Address1'
			    ),
			    'address2' => array(
			        'import_field' => 'ship_address2',
			        'export_field' => 'ship_address2',
			        'product_field' => 'ship_address2',
			        'import_header_val' => 'Address2',
			        'export_header_val' => 'Address2'
			    ),
			    'city' => array(
			        'import_field' => 'ship_city',
			        'export_field' => 'ship_city',
			        'product_field' => 'ship_city',
			        'import_header_val' => 'City',
			        'export_header_val' => 'City'
			    ),
			    'state' => array(
			        'import_field' => 'ship_state',
			        'export_field' => 'ship_state',
			        'product_field' => 'ship_state',
			        'import_header_val' => 'State',
			        'export_header_val' => 'State'
			    ),
			    'country' => array(
			        'import_field' => 'ship_country',
			        'export_field' => 'ship_country',
			        'product_field' => 'ship_country',
			        'import_header_val' => 'Country',
			        'export_header_val' => 'Country'
			    ),
			    'zip' => array(
			        'import_field' => 'ship_zip',
			        'export_field' => 'ship_zip',
			        'product_field' => 'ship_zip',
			        'import_header_val' => 'Zip',
			        'export_header_val' => 'Zip'
			    ),
			    'phone' => array(
			        'import_field' => 'ship_phone',
			        'export_field' => 'ship_phone',
			        'product_field' => 'ship_phone',
			        'import_header_val' => 'Phone',
			        'export_header_val' => 'Phone'
			    ),
			    'email' => array(
			        'import_field' => 'ship_email',
			        'export_field' => 'ship_email',
			        'product_field' => 'ship_email',
			        'import_header_val' => 'Email',
			        'export_header_val' => 'Email'
			    )
					   													
			);
}


function CSVFieldsDropshipperOrder()
{
	return array(
			    'orders_no' => array(
			        'import_field' => 'orders_no',
			        'export_field' => 'orders_no',
			        'import_header_val' => 'Orders No',
			        'export_header_val' => 'Orders No'
			    ),
			    'first_name' => array(
			        'import_field' => 'ship_first_name',
			        'export_field' => 'ship_first_name',
			        'import_header_val' => 'First Name',
			        'export_header_val' => 'First Name'
			    ),
			    'last_name' => array(
			        'import_field' => 'ship_last_name',
			        'export_field' => 'ship_last_name',
			        'product_field' => 'ship_last_name',
			        'import_header_val' => 'Last Name',
			        'export_header_val' => 'Last Name'
			    ),
			    'address1' => array(
			        'import_field' => 'ship_address1',
			        'export_field' => 'ship_address1',
			        'product_field' => 'ship_address1',
			        'import_header_val' => 'Address1',
			        'export_header_val' => 'Address1'
			    ),
			    'address2' => array(
			        'import_field' => 'ship_address2',
			        'export_field' => 'ship_address2',
			        'product_field' => 'ship_address2',
			        'import_header_val' => 'Address2',
			        'export_header_val' => 'Address2'
			    ),
			    'city' => array(
			        'import_field' => 'ship_city',
			        'export_field' => 'ship_city',
			        'product_field' => 'ship_city',
			        'import_header_val' => 'City',
			        'export_header_val' => 'City'
			    ),
			    'state' => array(
			        'import_field' => 'ship_state',
			        'export_field' => 'ship_state',
			        'product_field' => 'ship_state',
			        'import_header_val' => 'State',
			        'export_header_val' => 'State'
			    ),
			    'country' => array(
			        'import_field' => 'ship_country',
			        'export_field' => 'ship_country',
			        'product_field' => 'ship_country',
			        'import_header_val' => 'Country',
			        'export_header_val' => 'Country'
			    ),
			    'zip' => array(
			        'import_field' => 'ship_zip',
			        'export_field' => 'ship_zip',
			        'product_field' => 'ship_zip',
			        'import_header_val' => 'Zip',
			        'export_header_val' => 'Zip'
			    ),
			    'phone' => array(
			        'import_field' => 'ship_phone',
			        'export_field' => 'ship_phone',
			        'product_field' => 'ship_phone',
			        'import_header_val' => 'Phone',
			        'export_header_val' => 'Phone'
			    ),
			    'email' => array(
			        'import_field' => 'ship_email',
			        'export_field' => 'ship_email',
			        'product_field' => 'ship_email',
			        'import_header_val' => 'Email',
			        'export_header_val' => 'Email'
			    )
					   													
			);
}


function CSVFieldsDropshipperOrderDetail()
{
	return array(
			    'orders_no' => array(
			        'import_field' => 'orders_no',
			        'export_field' => 'orders_no',
			        'import_header_val' => 'Orders No',
			        'export_header_val' => 'Orders No'
			    ),
			    'sku' => array(
			        'import_field' => 'sku',
			        'export_field' => 'sku',
			        'import_header_val' => 'SKU',
			        'export_header_val' => 'SKU'
			    ),
			    'qty' => array(
			        'import_field' => 'quantity',
			        'export_field' => 'quantity',
			        'import_header_val' => 'Qty',
			        'export_header_val' => 'Qty'
			    ),
					   													
			);
}


function GetSpecialPricePercentandValue($qty)
{
	$per = 0;
	$val = 0;
	$db_recs = \App\Models\MarkupPrices::all();
	// if(!empty($db_recs)) {
	if($db_recs && $db_recs->count() > 0) {
		foreach ($db_recs as $markup_price_key => $markup_price_value) {
			if($markup_price_value->markup_value !="" && $markup_price_value->markup_value != "0" && $markup_price_value->markup_percent !="" && $markup_price_value->markup_percent !="0") {
				$mvalu = explode("-",$markup_price_value->markup_value);
				$mvalcount = count($mvalu);
				if($mvalcount>1) {
					if($qty >= $mvalu[0] && $qty <= $mvalu[1]) {
						$per = $markup_price_value->markup_percent;
						$val = $markup_price_value->markup_value;
					}
				} else {
					if($qty > $mvalu[0]) {
						$per = $markup_price_value->markup_percent;
						$val = $markup_price_value->markup_value;
					}
				}
			}
			if($per != '') {
				break;
			}
		}
	}
	return $per."#".$val;
}

function getWholesalerSpecialPricesDetails($product_price){
	
	if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
	{
		$is_special_price_enable = 1;
	}
	else
	{
		$is_special_price_enable = 0;
	}
	$quty_detail = '';
	$dis_detail = '';
	$SpecialPriceDetails = "";
	if($is_special_price_enable == 1)
	{
		$per = '';
		$db_recs = \App\Models\MarkupPrices::all();
		if($db_recs && $db_recs->count() > 0) {
			$quty_detail .= '<table> <tr><th class="fsbold">Quantity</th>
						 <th class="fsbold">Discount Offer Price</th></tr>';
			$quty_detail .= '<tr>';
			foreach ($db_recs as $markup_price_key => $markup) {
				if($markup->markup_lable != "" && $markup->markup_lable != '0' && $markup->markup_percent != "" && $markup->markup_percent != '0'){
					$NewPrice = $product_price - ($product_price*$markup->markup_percent)/100;
					$quty_detail .= ' <tr><td>'.$markup->markup_lable.'</td><td>'.NumberFormat($NewPrice).'</td></tr>';
				}
			}
			$quty_detail.= '</tr></table>';
		}
		$SpecialPriceDetails = $quty_detail;
	}
	return $SpecialPriceDetails;
}
function NumberFormat($val)
{
	if($val == '')
		$val = 0;
	$val = (float)$val; 
	return number_format( $val , 2, '.','');
}

// function added in global service provider
function GetDealCheckProduct()
{
	$deal_product =  DB::table('pu_dealofweek as dw')
		->join('pu_dealofweektitle as dwt', 'dw.did', '=', 'dwt.did')
		->join('pu_products as p', 'dw.product_sku', '=', 'p.sku')
		->select('dw.dealofweek_id', 'dw.product_sku', 'dw.start_date', 'dw.end_date', 'dw.deal_price')
		->where('p.status', '=', '1')
		->where('dw.status', '=', '1')
		->where('start_date', '<=', date('Y-m-d'))
		->where('end_date', '>=', date('Y-m-d'))
		->get();
	$dealcheck_array = array();
	$dealcompare_array = array();
	if (count($deal_product) > 0) {
		for ($i = 0; $i < count($deal_product); $i++) {
			$dealcheck_array[] .= trim($deal_product[$i]->product_sku);
			$deal_product[$i]->deal_end = date("Y-m-d");
			$dealcompare_array[trim($deal_product[$i]->product_sku)] = $deal_product[$i];
		}
	}

	return array('dealcheck_array'=> $dealcheck_array,'dealcompare_array' => $dealcompare_array);
}

// function added in global service provider
function GetDayDealCheckProduct()
{
	$ddeal_product =  DB::table('pu_dealofweek as dw')
			->join('pu_products as p', 'dw.product_sku', '=', 'p.sku')
			->select('dw.dealofweek_id', 'dw.product_sku', 'dw.start_date', 'dw.end_date', 'dw.deal_price', 'p.product_name', 'p.image', 'p.imanufactureid', 'p.short_description')
			->where('p.status', '=', '1')
			->where('dw.status', '=', '1')
			->where('start_date', '<=', date('Y-m-d'))
			->where('end_date', '>=', date('Y-m-d'))
			->where('deal_type', '=', 'Daily')
			->offset(0)->limit(1)->get();
		$ddealcheck_array = array();
		$ddealcompare_array = array();
		$aroma_popup_flg = 0;
		if (count($ddeal_product) > 0) {
			for ($i = 0; $i < count($ddeal_product); $i++) {
				$ddealcheck_array[] = trim($ddeal_product[$i]->product_sku);
				$ddeal_product[$i]->deal_end = date("Y-m-d");
				$ddealcompare_array[trim($ddeal_product[$i]->product_sku)] = $ddeal_product[$i];
				$ddealcheck_array['product_name'] = trim($ddeal_product[$i]->product_name);
				$ddealcheck_array['image'] = '$brnlogo';
			}
		}

	return array('ddealcheck_array'=> $ddealcheck_array,'ddealcompare_array' => $ddealcompare_array);
}

function GetFrontMegaMenu()
{
	$menu_array = Cache::remember('menu_array', 3600, function() {
		$parentCategories =  DB::table('pu_menu_front')
							->select('menu_title', 'menu_id', 'menu_link', 'rank', 'status','parent_id')
							->where('parent_id', '=', 0)
							->where('status', '=', '1')
							->orderBy('rank', 'ASC')
							->get()->toArray();
		// echo $_SERVER['REMOTE_ADDR'];
		// 49.34.169.128
		$mainArray = [];
		$level = 1;
		if(count($parentCategories) > 0) {
			foreach($parentCategories as $pcKey => $pcValue) {
				$mainArray[$pcKey]['menu_id'] = $pcValue->menu_id;
				$mainArray[$pcKey]['menu_title'] = $pcValue->menu_title;
				$mainArray[$pcKey]['menu_link'] = $pcValue->menu_link;
				$mainArray[$pcKey]['rank'] = $pcValue->rank;
				$mainArray[$pcKey]['status'] = $pcValue->status;
				$mainArray[$pcKey]['parent_id'] = $pcValue->parent_id;
				$parentCategories[$pcKey]->level = $level;
				$labels =  DB::table('pu_menu_front')
							->select('menu_title', 'menu_id', 'menu_link', 'rank', 'status','parent_id')
							->where('parent_id', '=', $pcValue->menu_id)
							->where('is_label', '=', '1')
							->where('status', '=', '1')
							->orderBy('rank', 'ASC')
							->get()->toArray();
				$cat_labels_count =  DB::table('pu_menu_front')
							->select('menu_title', 'menu_id', 'menu_link', 'rank', 'status')
							->where('parent_id', '=', $pcValue->menu_id)
							->where('is_label', '=', '1')
							->where('menu_title', '!=', 'Custom Tag Link - Banner Section')
							->where('status', '=', '1')
							->count();
				$parentCategories[$pcKey]->label_count = count($labels);
				$mainArray[$pcKey]['label_count'] = count($labels);
				$total_columns = 5;
				$display_banners_count = $total_columns - $cat_labels_count;
				$mainArray[$pcKey]['display_banners_count'] = $display_banners_count;
				$labelArray = [];
				if(count($labels) > 0) {
					foreach($labels as $labelKey => $labelVaue) {
						$labelArray[$labelKey]['menu_id'] = $labelVaue->menu_id;
						$labelArray[$labelKey]['menu_title'] = $labelVaue->menu_title;
						$labelArray[$labelKey]['menu_link'] = $labelVaue->menu_link;
						// $labelArray[$labelKey]['is_below'] = $labelVaue->is_below;
						$labelArray[$labelKey]['rank'] = $labelVaue->rank;
						$labelArray[$labelKey]['status'] = $labelVaue->status;
						$labelArray[$labelKey]['parent_id'] = $labelVaue->parent_id;
			        	$labelArray[$labelKey]['childs'] = array();
				        getSubCats($labelVaue->menu_id, $labelArray[$labelKey]['childs'],$level+1);
					}
				}
				$mainArray[$pcKey]['label'] = $labelArray;
			}
		}
		// dd($mainArray);
		$menu_array = $mainArray;
		return $menu_array;
	});
}

function getSubCats($parent_id = 0, &$categoriesArray = array(),$level=0) {
	$allSubCategories =  DB::table('pu_menu_front')
				->select('menu_title', 'menu_id', 'menu_link', 'rank', 'status', 'menu_image', 'menu_image1', 'menu_image2', 'menu_label', 'menu_label1', 'menu_label2', 'menu_custom_link', 'menu_custom_link1', 'menu_custom_link2')
				->where('parent_id', '=', (int)$parent_id)
				->where('is_label', '=', '0')
				->where('status', '=', '1')
				->orderBy('rank', 'ASC')
				->get()->toArray();

    foreach($allSubCategories as $k => $category) {
		$categoriesArray[$k]['menu_id'] = $category->menu_id;
		$categoriesArray[$k]['menu_title'] = $category->menu_title;
		$categoriesArray[$k]['menu_link'] = $category->menu_link;

		if (file_exists(config('global.FRONT_MENU_IMAGE_PATH') . $category->menu_image) && $category->menu_image != '') {
			$newimageVal = config('global.FRONT_MENU_IMAGE_PATH')  . stripslashes($category->menu_image);
			$verP = filemtime($newimageVal);
			$categoriesArray[$k]['menu_image'] = config('global.FRONT_MENU_IMAGE_URL') . $category->menu_image . "?ver=" . $verP;
		}else{
			$categoriesArray[$k]['menu_image'] = $category->menu_image;
		}

		if (file_exists(config('global.FRONT_MENU_IMAGE_PATH') . $category->menu_image1) && $category->menu_image1 != '') {
			$newimageVal = config('global.FRONT_MENU_IMAGE_PATH')  . stripslashes($category->menu_image1);
			$verP = filemtime($newimageVal);
			$categoriesArray[$k]['menu_image1'] = config('global.FRONT_MENU_IMAGE_URL') . $category->menu_image1 . "?ver=" . $verP;
		}else{
			$categoriesArray[$k]['menu_image1'] = $category->menu_image1;
		}

		if (file_exists(config('global.FRONT_MENU_IMAGE_PATH') . $category->menu_image2) && $category->menu_image2 != '') {
			$newimageVal = config('global.FRONT_MENU_IMAGE_PATH')  . stripslashes($category->menu_image2);
			$verP = filemtime($newimageVal);
			$categoriesArray[$k]['menu_image2']  = config('global.FRONT_MENU_IMAGE_URL') . $category->menu_image2 . "?ver=" . $verP;
		}else{
			$categoriesArray[$k]['menu_image2'] = $category->menu_image2;
		}
		
		$categoriesArray[$k]['menu_label'] = $category->menu_label;
		$categoriesArray[$k]['menu_label1'] = $category->menu_label1;
		$categoriesArray[$k]['menu_label2'] = $category->menu_label2;
		$categoriesArray[$k]['menu_custom_link'] = $category->menu_custom_link;
		$categoriesArray[$k]['menu_custom_link1'] = $category->menu_custom_link1;
		$categoriesArray[$k]['menu_custom_link2'] = $category->menu_custom_link2;
		
		$categoriesArray[$k]['rank'] = $category->rank;
		$categoriesArray[$k]['status'] = $category->status;
		$categoriesArray[$k]['level'] = $level;
    	$categoriesArray[$k]['childs'] = array();
    	getSubCats($category->menu_id,$categoriesArray[$k]['childs'],$level+1);
    }
}

 
function getCategoriesHTML($category) 
{
    $html = "";
    foreach ($category as $cat_id)
    {
        if (count($cat_id['childs']) > 0) {
			// $html .='<li><a href="'.$cat_id['menu_link'].'" class="mm-sub-link">'.$cat_id['menu_title'].'</a></li>';
          	$html .= getCategoriesHTML($cat_id['childs']);
        } else {
			if($cat_id['menu_title'] != 'Kids' || config('typevalofcriteo') == 'd'){
				$html .='<li><a href="'.$cat_id['menu_link'].'" class="">'.$cat_id['menu_title'].'</a></li>';
			}
        }
    }

    return $html;
}

function getPopularBrands()
{
	$popular_brands = Cache::remember('popular_brands', 3600, function() {
		$popularBrands = [];	
		
		$BrandList = DB::table('pu_products as po')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('po.products_id','po.product_name','m.imanufactureid','m.vmanufacture','m.vdetail','m.imglogo')
					->where('po.status','=','1')
					->where('m.is_popular','=','Yes')
					->where('m.status','=','1')
					->groupBy('m.imanufactureid')
					->orderBy('m.vmanufacture', 'ASC')
					->limit(25)
					->get();

		if($BrandList && $BrandList->count() > 0)
		{
			foreach($BrandList as $Brand)
			{
				$Name = remove_special_chars($Brand->vmanufacture);
				if(file_exists(config('global.MANUFACTUR_IMAGE_PATH').$Brand->imglogo) && $Brand->imglogo !='')
				{
					$imgLogo = config('global.MANUFACTUR_IMAGE_URL').$Brand->imglogo;
				} else {
					$imgLogo = config('global.MANUFACTUR_IMAGE_URL').'popular_brand_no_image.png';
				}
				$popularBrands[]=[
					'Name' => $Brand->vmanufacture,
					'ImageLogo' => $imgLogo,
					'Link' => config('global.SITE_URL').$Name.'/smid-'.$Brand->imanufactureid, 
				];
			}
		}
		$popular_brands = $popularBrands;
		return $popular_brands;
	
	});
}

function GCGenerateCode( $seed=false)
{
	$length = 25;
	$GIFT_CERT_CODE_CHARACTERS = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$newcode = "";
	if(false !== $seed) mt_srand($seed);
	for($i=0; $i<$length; $i++):
		$idx = mt_rand(1, strlen($GIFT_CERT_CODE_CHARACTERS)) - 1;
		$newcode .= $GIFT_CERT_CODE_CHARACTERS[$idx];
	endfor;
	return $newcode;
}

function GetPages()
{
	$Pages = [];
	Cache::remember('StaticPagesCache', 3600, function() {
		$StaticPages = \App\Models\StaticPages::where('status','=','1')->get();
		$DirectPages = ['faq','about_us','site_map','free_sample','coupons_promotional','reward_point_program','security_policy','privacy_policy','shipping_policy','return_exchange_policy','terms_and_conditions'];
		$PageWithUnderscore = ['returns_policy','shipping_service','authenticity_promise','redemption_policy','store_credit'];
		foreach($StaticPages as $key => $Page)
		{
			if($Page->name == 'contactus')
				continue;
			$Pages[$key]['slug'] = $Page->name;
			if($Page->name == 'FAQS')
			{
				$Page->name = 'faq';
			}
			$Page->name = strtolower($Page->name);
			
			if(!in_array($Page->name,$DirectPages)){
				if(in_array($Page->name,$PageWithUnderscore)){
					$StaticPage = '/site-page/'.$Page->name.'.html';
				} else {
					$StaticPage = str_replace('_','-',$Page->name);
					$StaticPage = '/site-page/'.$StaticPage.'.html';
				}
			}else{
				$StaticPage = str_replace('_','-',$Page->name);
				$StaticPage = '/'.$StaticPage.'.html';
			}
			$Pages[$key]['link'] = $StaticPage;
		}
		return $Pages;
	}); 
}
function AddAttentiveSubscriber($data){
	global $obj;
	$url = "https://api.attentivemobile.com/1/add-subscribers";
	$bearer_token = "SLaZI9AyM-aDRze0L4jggSD8qEsV3k9YMGyue2yRkzI";
	$ch = curl_init();
	curl_setopt_array($ch, array(
	  // Replace with your offline_event_set_id
	  CURLOPT_URL => $url, 
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "POST",
	  CURLOPT_POSTFIELDS =>  json_encode($data),
	  CURLOPT_HTTPHEADER => array(
		"cache-control: no-cache",
		"Content-Type: application/json",
		"Authorization: Bearer ".$bearer_token."",
		//"Authorization: Basic ".$basic_auth."",
		"Accept: application/json"),
	));
	$result = curl_exec($ch);
	
	$arrUpdate = array('attentive_response' => $result);
	\App\Models\NewsLetter::where('news_letter_id','=',$data["visitorId"])->update($arrUpdate);
	return true;		
}
function getImageWidth($imagpath){
	if(!file_exists($imagpath)){
		$imagpath = config('global.PHYSICAL_PATH').'images/noimage-th.jpg';
	}
	list($width, $height) =  @getimagesize($imagpath);
	return $width;
}

function getImageHeight($imagpath){
	if(!file_exists($imagpath)){
		$imagpath = config('global.PHYSICAL_PATH').'images/noimage-th.jpg';
	}
	list($width, $height) =  @getimagesize($imagpath);
	return $height;
}