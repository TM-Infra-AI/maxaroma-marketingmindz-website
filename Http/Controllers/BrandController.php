<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Traits\CommonTrait;
use Session;
use App\Models\MetaInfo;
use App\Models\Manufacture;
use App\Models\Submanufacture;
use App\Models\Promotional;
use App\Models\SiteOffers;
use App\Models\PromotionalBanner;
use App\Models\BrandLandling;
use App\Models\MainbrandLanding;
use DB;
use Cache;
use Illuminate\Support\Facades\File;

class BrandController extends Controller
{
	use CommonTrait;
	public $PageData;
	public function __construct()
    {
        /*
		$PageType = 'NR';
		$MetaInfo = MetaInfo::where('type','=',$PageType)->get(); 
		if($MetaInfo->count() > 0 )
		{
			$this->PageData['meta_description'] = $MetaInfo[0]->meta_description;
			$this->PageData['meta_keywords'] = $MetaInfo[0]->meta_keywords;
		}
        */
	}
	public function BrandPage(Request $request)
	{
        if(Cache::has('DefaultMetaInfo'))
        {
            $MetaInfo = Cache::get('DefaultMetaInfo');
            $this->PageData['meta_title'] = $MetaInfo->meta_title;
            $this->PageData['meta_description'] = $MetaInfo->meta_description;
            $this->PageData['meta_keywords'] = $MetaInfo->meta_keywords;
        }
        
		$imanufactureid = $request->brand_id;
		$category_id = 0;
		$BrandDetail = Manufacture::where('imanufactureid', '=', $imanufactureid)
			->where('status', '=', '1')->get();
		if ($BrandDetail && $BrandDetail->count() > 0) {
			$this->PageData['BrandDetail'] = $BrandDetail;
			$Submanufacture = Submanufacture::where('imanufactureid', '=', $imanufactureid)->where('status', '=', '1')->limit(6)->get();
			if ($category_id > 0) {
				$BrandDetail[0]->vlink = config('global.SITE_URL') . stripcslashes(remove_special_chars($BrandDetail[0]->vmanufacture)) . "/p4u/cid-" . $category_id . "/mid-" . $BrandDetail[0]->imanufactureid . "/view";
				if ($BrandDetail[0]->showhide == "No" || $BrandDetail[0]->header_image == "")
					return redirect(remove_special_chars($BrandDetail[0]->vlink));
			} else {
				$BrandDetail[0]->vlink = config('global.SITE_URL') . stripcslashes(remove_special_chars($BrandDetail[0]->vmanufacture)) . "/p4u/mid-" . $BrandDetail[0]->imanufactureid . "/view";
				if ($BrandDetail[0]->showhide == "No" || $BrandDetail[0]->header_image == "")
					return redirect($BrandDetail[0]->vlink);
			}
			$this->PageData['Submanufacture'] = $Submanufacture;
			$Meta = MetaInfo::where('type', '=', 'DP')->get();
            
			$this->PageData['meta_title'] = $Meta[0]->meta_title;
			$this->PageData['meta_keywords'] = $Meta[0]->meta_keywords;
			$this->PageData['meta_description'] = $Meta[0]->meta_description;
			if ($BrandDetail[0]->meta_title != '')
				$this->PageData['meta_title'] = $BrandDetail[0]->meta_title;
			if ($BrandDetail[0]->vmeta_keyword != '')
				$this->PageData['meta_keywords'] = $BrandDetail[0]->vmeta_keyword;
			if ($BrandDetail[0]->vmeta_description != '')
				$this->PageData['meta_description'] = $BrandDetail[0]->vmeta_description;

			$BrandDetail[0]->header_image = $this->setImageName($BrandDetail[0]->header_image, config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'), "brand-hbnr.jpg", config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'));

			$BrandDetail[0]->overlap_brand_logo = $this->setImageName($BrandDetail[0]->overlap_brand_logo, config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'), "brand-hbnr-logo.png", config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'));

			$BrandDetail[0]->authorized_dealer_logo = $this->setImageName($BrandDetail[0]->authorized_dealer_logo, config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'), "auth-logo.png", config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'));

			if ($BrandDetail[0]->is_new_design == 'Yes' && $BrandDetail[0]->status == '1' && $BrandDetail[0]->showhide == 'Yes') {

				if ($BrandDetail[0]->history_images != '') {
					$historyImgs = explode("#", $BrandDetail[0]->history_images);
					for ($k = 0; $k < count($historyImgs); $k++) {
						$imgindx = $k + 1;
						$historyImgs[$k] = $this->setImageName($historyImgs[$k], config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'), "brand-h" . $imgindx . ".jpg", config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'));
					}
				} else {
					$historyImgs = array();
				}
				$this->PageData['historyImgs'] = $historyImgs;

				$SetFilters = array();
				if ($BrandDetail[0]->designerid > 0) {
					if ($BrandDetail[0]->exclude_sku != '') {
						$SetFilters['NotProductSKUs'] = explode("#", $BrandDetail[0]->exclude_sku);
					}
					$SetFilters['brands'] = array($BrandDetail[0]->designerid);
				} else {
					if ($BrandDetail[0]->sku != '') {
						$SetFilters['ProductSKUs'] = explode("#", $BrandDetail[0]->sku);
					}
				}
				$ProductsDetails = $this->GetProducts('BrandPage', '', 10, $SetFilters);
				$Products = $ProductsDetails['Products'];
				$TotalProducts = $ProductsDetails['TotalProducts'];
				$this->PageData['Products'] = $Products;
				$this->PageData['TotalProducts'] = $TotalProducts;

				$this->PageData['CSSFILES'] = ['brand-history.css'];
				$this->PageData['JSFILES'] = ['brand_history.js'];
				return view('brand.brand_land')->with($this->PageData);
			} else {
				$this->PageData['CSSFILES'] = ['brand-lending.css'];
				return view('brand.brand')->with($this->PageData);
			}
		}
	}

	public function GetBrandProducts(Request $request)
	{
		$imanufactureid = $request->brand_id;
		$BrandDetail = Manufacture::where('imanufactureid', '=', $imanufactureid)
			->where('status', '=', '1')->get();
		if ($BrandDetail && $BrandDetail->count() > 0) {
			if ($BrandDetail[0]->designerid > 0) {
				if ($BrandDetail[0]->exclude_sku != '') {
					$SetFilters['NotProductSKUs'] = explode("#", $BrandDetail[0]->exclude_sku);
				}
				$SetFilters['brands'] = array($BrandDetail[0]->designerid);
			} else {
				if ($BrandDetail[0]->sku != '') {
					$SetFilters['ProductSKUs'] = explode("#", $BrandDetail[0]->sku);
				}
			}
			$SetFilters['page'] = $request->page;
			$ProductsDetails = $this->GetProducts('BrandPage', '', 10, $SetFilters);
			$Products = $ProductsDetails['Products'];
			$TotalProducts = $ProductsDetails['TotalProducts'];
			$this->PageData['Products'] = $Products;
			$this->PageData['TotalProducts'] = $TotalProducts;

			$ProductHTML = view('brand.brandproductlist')->with($this->PageData)->render();
			return response()->json(array('TotalProducts' => $TotalProducts, 'ProductHTML' => $ProductHTML));
		}
	}

	public function Promotional(Request $request)
	{
		$this->PageData['CSSFILES'] = ['jquery-ui-slider.css', 'deal-list.css', 'custom.css'];
		$this->PageData['JSFILES'] = ['moment.min.js', 'jquery-ui-slider.min.js', 'promotional.js'];

		$PromotionalData = Promotional::all();
		//$PromotionalBannerData = PromotionalBanner::all();
		$PromotionalBannerData = PromotionalBanner::where('start_date','<=',date('Y-m-d'))->where('end_date','>=',date('Y-m-d'))->get();
		//dd($PromotionalBannerData);

		$SetFilters = $this->SetFilters($request);
		if ($PromotionalData && $PromotionalData->count() > 0) {
			$SetFilters['ProductSKUs'] = explode(",", $PromotionalData[0]->sku);
		}
		$this->PageData['SelBrand'] = '';
		if (isset($SetFilters['brands']) && count($SetFilters['brands']) > 0)
			$this->PageData['SelBrand'] = implode(",", $SetFilters['brands']);

		$this->PageData['SelSize'] = '';
		if (isset($SetFilters['size']) && count($SetFilters['size']) > 0)
			$this->PageData['SelSize'] = implode(",", $SetFilters['size']);

		$this->PageData['SelKey'] = '';
		if (isset($SetFilters['key']) && $SetFilters['key'] != '')
			$this->PageData['SelKey'] = $SetFilters['key'];

		$this->PageData['SelStock'] = '';
		if (isset($SetFilters['stock']) && in_array('In', $SetFilters['stock']))
			$this->PageData['SelStock'] = 'checked';

		$ProductsDetails = $this->GetProducts('Promotional', '', 12, $SetFilters);
		$Products = $ProductsDetails['Products'];
		$TotalProducts = $ProductsDetails['TotalProducts'];
		$this->PageData['PromotionalProds'] = $Products;
		$this->PageData['TotalProducts'] = $TotalProducts;
		$BrandList = [];
		$SizeList = [];
		if (count($ProductsDetails['LeftFilters']) > 0) {
			foreach ($ProductsDetails['LeftFilters'] as $DealFilter) {
				if (array_key_exists('Brands', $DealFilter)) {
					$BrandList = $DealFilter['Brands']['Data'];
				}
				if (array_key_exists('Size', $DealFilter)) {
					$SizeList = $DealFilter['Size']['Data'];
				}
			}
		}
		$this->PageData['PromotionalData'] = $PromotionalData;
		$this->PageData['PromotionalBannerData'] = $PromotionalBannerData;
		//dd($PromotionalData);
		
		$this->PageData['BrandList'] = $BrandList;
		$this->PageData['SizeList'] = $SizeList;
		$this->PageData['MinPrice'] = count($Products) > 0 ? min(array_column($Products, 'product_price')) : 0;
		$this->PageData['MaxPrice'] = count($Products) > 0 ? max(array_column($Products, 'product_price')) : 0;
		return view('product.promotional')->with($this->PageData);
	}
	public function GetPromotional(Request $request)
	{
		$Filters = json_decode($request->filters, true);
		
		$PromotionalData = Promotional::all();
		$PromotionalBannerData = PromotionalBanner::where('start_date','<=',date('Y-m-d'))->where('end_date','>=',date('Y-m-d'))->get();
		
		if ($PromotionalData && $PromotionalData->count() > 0) {
			$Filters['ProductSKUs'] = explode(",", $PromotionalData[0]->sku);
		}
		$Filters['page'] = $request->page;
		$ProductsDetails = $this->GetProducts('Promotional', '', 12, $Filters);
		$Products = $ProductsDetails['Products'];
		$TotalProducts = $ProductsDetails['TotalProducts'];
		$this->PageData['Products'] = $Products;
		$this->PageData['TotalProducts'] = $TotalProducts;
		
		$this->PageData['PromotionalData'] = $PromotionalData;
		$this->PageData['PromotionalBannerData'] = $PromotionalBannerData;
		
		$BrandList = [];
		$SizeList = [];
		if (count($ProductsDetails['LeftFilters']) > 0) {
			foreach ($ProductsDetails['LeftFilters'] as $PromotionalFilter) {
				if (array_key_exists('Brands', $PromotionalFilter)) {
					$BrandList = $PromotionalFilter['Brands']['Data'];
				}
				if (array_key_exists('Size', $PromotionalFilter)) {
					$SizeList = $PromotionalFilter['Size']['Data'];
				}
			}
		}
		$ProductHTML = view('product.otherlist')->with($this->PageData)->render();
		return response()->json(array('TotalProducts' => $TotalProducts, 'ProductHTML' => $ProductHTML, 'BrandList' => $BrandList, 'SizeList' => $SizeList));
	}

	public function setImageName($image, $imgpath, $imgURL, $defaultImg, $defaultPath, $defaultURL)
	{
		$imageName="";
		if (!empty($image) && file_exists($imgpath . stripslashes($image))) {
			$ver = filemtime($imgpath . stripslashes($image));
			$imageName = $imgURL . $image . '?ver=' . $ver;
		} /*else {
			$ver = @filemtime($defaultPath . $defaultImg);
			$imageName = $defaultURL . $defaultImg . '?ver=' . $ver;
		}*/
		return $imageName;
	}
	public function BrandHistory(Request $request)
	{
		$id	= $request['brand_id'];
		$res = BrandLandling::select('title', 'sku', 'position', 'banner_image', 'mobile_banner_image', 'bundle_banner_image','banner_link','youtube_link', 'video_show', 'status')
			->where('id', '=', (int) $id)
			->where('status', '=', '1')
			->first();
		if ($res && $res->count() > 0) {
			$res->banner_image = $this->setImageName($res->banner_image, config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'), "brand-hbnr.jpg", config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'));
			$res->mob_image = $this->setImageName($res->mobile_banner_image, config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'), "brand-hbnr.jpg", config('global.MANUFACTUR_IMAGE_PATH'), config('global.MANUFACTUR_IMAGE_URL'));

			$relatedItems = $res->sku;
			if ($relatedItems && $relatedItems != '') {
				if (strstr($relatedItems, ',')) {
					$relatedItems = str_replace(",", "#", $relatedItems);
				}
				
				$relatedItems = rtrim($relatedItems,"#");
				
				$relatedItemValues = explode("#", $relatedItems);
				$SetFilters['ProductSKUs'] = $relatedItemValues;
				$ProductsDetails = $this->GetProducts('', '', 15, $SetFilters);
				$Products = $ProductsDetails['Products'];
				$TotalProducts = $ProductsDetails['TotalProducts'];
				$this->PageData['Products'] = $Products;
				$this->PageData['TotalProducts'] = $TotalProducts;
				// $ProductsDetails = $this->GetSliderProducts($res->sku, '', '');
				// dd($ProductsDetails);
			} else {
				$ProductsDetails = [];
			}
			$this->PageData['brandpro_arr'] = $ProductsDetails;
			$this->PageData['meta_title'] = config('Settings.SITE_TITLE') . " :: " . $res->title;
			// } else {
			// }

			$res->is_brand_show = 'Yes';

			if ($res->youtube_link != '') {
				$youtubeLinks = explode("#", $res->youtube_link);
			} else {
				$youtubeLinks = array();
			}
			$this->PageData['cntYoutubeLink'] = count($youtubeLinks);
			$this->PageData['youtubeLinks'] = $youtubeLinks;
			$this->PageData['res'] = $res;
			// dd($res);

			$this->PageData['CSSFILES'] = ['brand-history.css'];
			$this->PageData['JSFILES'] = ['brandhistorybundle.js'];
			return view('brand.brandhistory')->with($this->PageData);
		} else {
			return view('errors.404');
		}
	}
	public function GetBrandHistoryBundleProducts(Request $request)
	{
		$brand_id = $request->brand_id;
		$page = $request->page;
		$res = BrandLandling::select('*')
			->where('id', '=', (int) $brand_id)
			->where('status', '=', '1')
			->first();
		if ($res && $res->count() > 0) {
			if ($res->sku != '') {
				$res->sku = rtrim($res->sku,"#");
				$SetFilters['ProductSKUs'] = explode("#", $res->sku);
			}
			$relatedItems = $res->sku;
			if ($relatedItems && $relatedItems != '') {
				if (strstr($relatedItems, ',')) {
					$relatedItems = str_replace(",", "#", $relatedItems);
				}
				
				$relatedItems = rtrim($relatedItems,"#");
				
				$relatedItemValues = explode("#", $relatedItems);
				$SetFilters['ProductSKUs'] = $relatedItemValues;
				$SetFilters['page'] = $request->page;
				$ProductsDetails = $this->GetProducts('BrandHistory', '', 15, $SetFilters);
				// dd($ProductsDetails);
				$Products = $ProductsDetails['Products'];
				//$TotalProducts = count($ProductsDetails['Products']);
				$TotalProducts = $ProductsDetails['TotalProducts'];
				$this->PageData['Products'] = $Products;
				$this->PageData['TotalProducts'] = $TotalProducts;
			}
			$ProductHTML = view('brand.brandhistorygrid')->with($this->PageData)->render();
			return response()->json(array('TotalProducts' => $TotalProducts, 'ProductHTML' => $ProductHTML));
		}
	}
	public function MaxaromaBundles(Request $request)
	{
		$mainBrandLanding = MainbrandLanding::get();
		$this->PageData['mainBrandLanding']  = "";
		if($mainBrandLanding){

			if (file_exists(config('global.CAT_IMAGE_PATH') . $mainBrandLanding[0]['mega_menu_image']) && $mainBrandLanding[0]['mega_menu_image'] != '') {
				$newimageVal = config('global.CAT_IMAGE_PATH')  . stripslashes($mainBrandLanding[0]['mega_menu_image']);
				$verP = filemtime($newimageVal);
				$mainBrandLanding[0]['mega_menu_image'] = config('global.CAT_IMAGE_URL') . $mainBrandLanding[0]['mega_menu_image'] . "?ver=" . $verP;
			}else{
				$mainBrandLanding[0]['mega_menu_image'] = config('global.CAT_IMAGE_URL').$mainBrandLanding[0]['mega_menu_image'];
			}

			$mainBrandLanding[0]['title'] = strtolower($mainBrandLanding[0]['title']);
			$this->PageData['mainBrandLanding'] = $mainBrandLanding;

		}
		$result = BrandLandling::select('id', 'bundle_banner_image','banner_link','title')
			->where('status', '=', '1')
			->orderBy('position')
			->get();
		$brandlanding = array();
		if ($result && $result->count() > 0) {
			$i = 0;
			foreach ($result as $value) {
				$brandlanding[$i]['id'] = $value['id'];
				$brandlanding[$i]['banner_link'] = $value['banner_link'];
				$brandlanding[$i]['title'] = $value['title'];

				if (file_exists(config('global.MANUFACTUR_IMAGE_PATH') . $value['bundle_banner_image']) && $value['bundle_banner_image'] != '') {
					$newimageVal = config('global.MANUFACTUR_IMAGE_PATH')  . stripslashes($value['bundle_banner_image']);
					$verP = filemtime($newimageVal);
					$brandlanding[$i]['banner_image'] = config('global.MANUFACTUR_IMAGE_URL') . $value['bundle_banner_image'] . "?ver=" . $verP;
				}else{
					$brandlanding[$i]['banner_image'] = config('global.MANUFACTUR_IMAGE_URL') . $value['bundle_banner_image'];
				}

				$i++;
			}
			$this->PageData['brandlanding'] = $brandlanding;
		}

		$this->PageData['CSSFILES'] = ['influencer-bundel.css'];
		$this->PageData['meta_title'] = config('global.META_TITLE').' :: Discovery Bundles';
		return view('brand.maxaroma_bundles')->with($this->PageData);
	}

	public function Offers(Request $request)
	{
		$this->PageData['CSSFILES'] = ['jquery-ui-slider.css','custom.css'];
		$this->PageData['JSFILES'] = ['moment.min.js', 'jquery-ui-slider.min.js'];

		$siteOffer = SiteOffers::where('status', '=', '1')->where('expiry_date', '>=', date('Y-m-d'))->get();
		foreach($siteOffer as $key => $offerbanner){
			if(!empty($offerbanner->offer_banner) && file_exists(config('global.SITE_OFFER_PATH').$offerbanner->offer_banner)){
				$newimageVal = config('global.SITE_OFFER_PATH')  . stripslashes($offerbanner->offer_banner);
				$verP = filemtime($newimageVal);
				$siteOffer[$key]->offer_banner = config('global.SITE_OFFER_URL') . $offerbanner->offer_banner . "?ver=" . $verP;
			} else {
				$siteOffer[$key]->offer_banner = 'https://via.placeholder.com/400x600/eee'; 
			}
		}
		
		$this->PageData['siteOffer'] = $siteOffer;

		return view('product.offers')->with($this->PageData);
	}
}
