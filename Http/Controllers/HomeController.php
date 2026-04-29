<?php
	namespace App\Http\Controllers;
	use Illuminate\Http\Request;
	use App\Models\SiteSettings;
	use App\Models\MetaInfo;
	use App\Models\HomeImage;
	use App\Models\HomepageHtmlProducts;
	use App\Models\Manufacture;
	use App\Http\Controllers\Traits\CommonTrait;
	use App\Http\Controllers\Traits\VendorTrait;
	use App\Http\Controllers\Traits\CartTrait;
	use DB;
	use Session;
	use Cache;
	class HomeController extends Controller
	{
		use CommonTrait;
		use VendorTrait;
		use CartTrait;
		public $PageData;
		
		public function __construct()
		{
			$this->PageData['CSSFILES'] = ['slick.css','home.css'];	
			$this->PageData['JSFILES'] = ['slick.js','moment.min.js','home.js'];	
			$GTMDATA = ['page' => 'home', 'pagetype' => 'home'];
			$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
			$this->PageData['TEST'] = '123';
		}
		public function index(Request $request)
		{
            $visitor = "No";
            if(isset($request->visitor))
            {
                $visitor = $request->visitor;
            }
			$CategoryID = '';
			if (!Cache::has('HomeBanners')) {
				$HomeBanners = HomeImage::where('status','=','1')
							->whereIn('section',['HOME MAIN','HOME BOTTOM','MIDDLE BOTTOM BANNER','HOMEPAGE DEAL PRODUCT','HOME TOP THREE','HOME FEATURED BRANDS'])
							->orderBy('section')
							->orderBy('position')->get();
				Cache::put('HomeBanners', $HomeBanners);
			}else{
				$HomeBanners = Cache::get('HomeBanners');
			}	
			$Banners = [];
			$Section = "";
			$DefaultImage = [
				"HOME_MAIN" => "main-banner.jpg",
				"HOMEPAGE_DEAL_PRODUCT" => "weekly_sale_banner.jpg",
				"HOME_BOTTOM" => ['pick-bnr.jpg','earn-bnr.jpg',"affil-bnr.jpg"],
				"MIDDLE_BOTTOM_BANNER" => ['wholesalers-bnr.jpg','blog-bnr.jpg'],
                "HOME_TOP_THREE" => "weekly_sale_banner.jpg",
                "HOME_FEATURED_BRANDS" => "weekly_sale_banner.jpg"
			];
			$DefaultImageMobile = [
				"HOME_MAIN" => "main-banner-mobile.jpg",
				"HOMEPAGE_DEAL_PRODUCT" => "weekly_sale_banner_mob.jpg",
				"HOME_BOTTOM" => ['pick-bnr.jpg','earn-bnr.jpg',"affil-bnr.jpg"],
				"MIDDLE_BOTTOM_BANNER" => ['wholesalers-bnr.jpg','blog-bnr.jpg'],
                "HOME_TOP_THREE" => "weekly_sale_banner.jpg",
                "HOME_FEATURED_BRANDS" => "weekly_sale_banner.jpg"
			];
			$MiddleSection = 0;
			$HomeBottomCnt = 0;
			foreach($HomeBanners as $HBanner)
			{
				$Section = str_replace(" ","_",$HBanner->section);
				$BannerImg = $HBanner->home_image;
				$DefaultImg = $DefaultImage[$Section];
				$DefaultImgMob = $DefaultImageMobile[$Section];
				if($Section == 'MIDDLE_BOTTOM_BANNER')
				{
					$DefaultImg = $DefaultImage[$Section][$MiddleSection];
					$DefaultImgMob = $DefaultImageMobile[$Section][$MiddleSection];
					$MiddleSection++;
				}
				if($Section == 'HOME_BOTTOM')
				{
					$DefaultImg = $DefaultImage[$Section][$HomeBottomCnt];
					$DefaultImgMob = $DefaultImageMobile[$Section][$HomeBottomCnt];
					$HomeBottomCnt++;
				}
				if($Section == 'HOMEPAGE_DEAL_PRODUCT')
				{
					$DefaultImg = $DefaultImage[$Section];
					$DefaultImgMob = $DefaultImageMobile[$Section];
				}
				if(!empty($BannerImg) && file_exists(config('global.HOME_IMAGE_PATH').$BannerImg)){ 
					$newimageVal = config('global.HOME_IMAGE_PATH')  . stripslashes($BannerImg);
					$verP = filemtime($newimageVal);
					$BannerImg = config('global.HOME_IMAGE_URL') . $BannerImg . "?ver=" . $verP;
				} else {
					$BannerImg = config('global.SITE_IMAGES').$DefaultImg; 
				}
				$HBanner->home_image = $BannerImg;
				
				$BannerImgMobile = $HBanner->mobile_image;
				if(!empty($BannerImgMobile) && file_exists(config('global.HOME_IMAGE_PATH').$BannerImgMobile)){ 
					$BannerImgMobile = config('global.HOME_IMAGE_URL').$BannerImgMobile; 
				} else {
					$BannerImgMobile = config('global.SITE_IMAGES').$DefaultImgMob;
				}					
				$HBanner->mobile_image = $BannerImgMobile;
				
				$BannerLink = $HBanner->link;
				$HBanner->link = str_replace('{$Site_URL}',config('global.SITE_URL'),$BannerLink);
				$Banners[$Section][] = $HBanner;	
			}				
			//PrintObj($Banners['HOME_BOTTOM']);
            //dd($Banners);
			$this->PageData['HomeMainBanners'] = isset($Banners['HOME_MAIN'])?$Banners['HOME_MAIN']:[];
			$this->PageData['HomeWeeklyBanners'] = isset($Banners['HOMEPAGE_DEAL_PRODUCT'])?$Banners['HOMEPAGE_DEAL_PRODUCT']:[];
			$this->PageData['HomeBottomBanners'] = isset($Banners['HOME_BOTTOM'])?$Banners['HOME_BOTTOM']:[];
			$this->PageData['HomeMiddleBottomBanners'] = isset($Banners['MIDDLE_BOTTOM_BANNER'])?$Banners['MIDDLE_BOTTOM_BANNER']:[];
            $this->PageData['HomeTopThree'] = isset($Banners['HOME_TOP_THREE'])?$Banners['HOME_TOP_THREE']:[];
            $this->PageData['HomeFeatureBrands'] = isset($Banners['HOME_FEATURED_BRANDS'])?$Banners['HOME_FEATURED_BRANDS']:[];
			if (!Cache::has('HomeCategoryBanners')) {
				$HomeCategoryBanners = HomepageHtmlProducts::where('title','!=','')
				->where('banner_image','!=','')
				->where('status','=','1')
				->orderBy('position')->get();
				Cache::put('HomeCategoryBanners', $HomeCategoryBanners);
			}else{
				$HomeCategoryBanners = Cache::get('HomeCategoryBanners');
			}

			$CatBannersList = [];						
			if($HomeCategoryBanners && $HomeCategoryBanners->count() > 0)
			{   //dd(config('global.SITE_URL'));
				foreach($HomeCategoryBanners as $CatBanners)
				{
					$CatBanners->main_link = str_replace('{$Site_URL}',config('global.SITE_URL'),$CatBanners->main_link);
					if(file_exists(config('global.HOME_IMAGE_PATH').$CatBanners->banner_image)){ 
						$newimageVal = config('global.HOME_IMAGE_PATH')  . stripslashes($CatBanners->banner_image);
						$verP = filemtime($newimageVal);
						$CatBanners->banner_image = config('global.HOME_IMAGE_URL') . $CatBanners->banner_image . "?ver=" . $verP;
					}
					$CatBannersList[]=$CatBanners;
				}
			}				
			$this->PageData['HomeCategoryBanners'] = $CatBannersList;
			$NewArrivals = $this->ProductSlider('NEW ARRIVALS','Home',$CategoryID);
			$ProductsDetails = $this->GetProducts('ProductListPage','',12,['special' => ['na']]);
            $TotalProducts = $ProductsDetails['TotalProducts'];
            $this->PageData['NewArrivals'] = $NewArrivals[0]['products'];
			$this->PageData['NewArrivalsAttr'] = [
				'Title' => 'New Arrivals',
				'Slider' => 'home-new-sl',
				'SeeMore' => config('global.SITE_URL').'new-arrivals/p4u/special-na/view',
                'TotalProd' => $TotalProducts
			];
			
			$BestSellers = $this->ProductSlider('TOP SELLERS','Home',$CategoryID);

			$this->PageData['BestSellers'] = $BestSellers[0]['products'];
			$this->PageData['BestSellersAttr'] = [
				'Title' => 'Bestsellers',
				'Slider' => 'home-new-sl',
				'SeeMore' => config('global.SITE_URL').'top-sellers/p4u/special-ts/view',
			];
			
			$PageType = 'HO';
			if (!Cache::has('HOMEMETAINFO')) {
				$MetaInfo = MetaInfo::where('type','=',$PageType)->get();
				Cache::put('HOMEMETAINFO', $MetaInfo);
			}else{
				$MetaInfo = Cache::get('HOMEMETAINFO');
			}
			
			if($MetaInfo->count() > 0 )
			{
				$this->PageData['meta_title'] = $MetaInfo[0]->meta_title;
				$this->PageData['meta_description'] = $MetaInfo[0]->meta_description;
				$this->PageData['meta_keywords'] = $MetaInfo[0]->meta_keywords;
			}

			$this->PageData['Deal'] = $this->HomeDealOfWeek();
			$this->PageData['visitor'] =$visitor;
			return view('home')->with($this->PageData);		
		}
		
		public function HomeDealOfWeek()
		{
			$DealOfWeekQry = DB::table('pu_dealofweek as dw')
							->join('pu_dealofweektitle as dwt','dw.did','=','dwt.did')
							->join('pu_products as p','dw.product_sku','=','p.sku')
							->join('pu_products_category as pc','p.products_id','=','pc.products_id')
							->join('pu_manufacture as m','p.imanufactureid','=','m.imanufactureid')
							->where('p.status','=','1')->where('dw.status','=','1')
							->where('dw.start_date','<=',date('Y-m-d'))
							->where('dw.end_date','>=',date('Y-m-d'))
							->where('dw.display_on_home','=','Yes');
			if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
				$DealOfWeekQry->whereIn('p.product_type',['wholesaler']);
			else
				$DealOfWeekQry->whereIn('p.product_type',['both','retailer']);				
			$DealOfWeekQry->orderBy('dw.end_date');
			$DealOfWeekQry->orderBy('display_rank');
			$DealOfWeek = $DealOfWeekQry->limit(1)->get();
			if($DealOfWeek && $DealOfWeek->count() > 0)
			{
				$Deal = $this->SetProduct($DealOfWeek[0]);
				$product_price = $Deal->product_price;
				$Deal->product_url = SetProductURL($Deal->products_id, $Deal->product_name, $Deal->category_id);
				if($Deal->deal_price < $product_price)
					$Deal->product_price = $Deal->deal_price;
				
				if($Deal->deal_price !='' && $Deal->deal_price < $product_price)
				{ 				
					$yousave = ($Deal->retail_price - $Deal->deal_price)/$Deal->retail_price;
					$yousave = $yousave*100;
					$yousave = number_format($yousave,0);
					$Deal->yousave = $yousave;	
					$yousaveprice = $Deal->retail_price - $Deal->deal_price;
					$Deal->yousaveprice = $yousaveprice;
				}else{
					$retail_price = $Deal->retail_price;
					if($retail_price!='' && $retail_price!='0.00')
						$yousave=($retail_price-$product_price)/$retail_price;
					$yousave = $yousave*100;
					$yousave = number_format($yousave,0);
					$yousaveprice = $retail_price-$product_price;
					$Deal->yousaveprice = $yousaveprice;
					$Deal->yousave = $yousave;	
				}
				$Deal->formatted_end_date = date('d',strtotime($Deal->end_date));
				$Deal->formatted_end_month = date('m',strtotime($Deal->end_date));
				$Deal->formatted_end_year = date('Y',strtotime($Deal->end_date));	
				$Deal->deal_end_date = date('d-m-Y 23:59:00',strtotime($Deal->end_date));
				return $Deal;
			}				
		}
	}
