<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\VendorTrait;
use App\Models\MetaInfo;
use App\Models\Category;
use App\Models\Products;
use App\Models\ProductsCategory;
use DateTime;
use Illuminate\Contracts\Session\Session as SessionSession;
use Illuminate\Support\Facades\DB;
use Session;
use Illuminate\Support\Facades\Blade;

class MainCategoryController extends Controller
{
	use CommonTrait;
	use VendorTrait;
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
		if(isset($request->ver) && $request->ver=="new")
		{
		$this->PageData['CSSFILES'] = ['slick.css','listing.css','listpage.css','custom.css'];	
		$this->PageData['JSFILES'] = ['slick.js','maincategorylanding.js','jquery.mCustomScrollbar.concat.min.js']; //'jquery-1.11.3.js',	
		}
		else
		{
		$this->PageData['CSSFILES'] = ['listing.css','listpage.css','custom.css'];	
		$this->PageData['JSFILES'] = ['maincategorylanding.js','jquery.mCustomScrollbar.concat.min.js']; //'jquery-1.11.3.js',
	    }
		$this->PageData['ListFrom'] = 'MainCategory'; 
		$mainCategory = $request->category_id;
		//dd($mainCategory);
		$CategoryDetails = Category::where('status','=','1')->where('category_id','=',$mainCategory)->get();
		if(!$CategoryDetails || $CategoryDetails->count() == 0)
		{
			return redirect('/404');
		}
		$CatParent = '';	
		if($CategoryDetails && $CategoryDetails->count() > 0)
		{
			$PageType = 'CT';
			$MetaInfo = MetaInfo::where('type', '=', $PageType)->get();

			if(file_exists(config('global.CAT_IMAGE_PATH').$CategoryDetails[0]->banner_image) and !empty($CategoryDetails[0]->banner_image))
			{
				$newimageVal = config('global.CAT_IMAGE_PATH').$CategoryDetails[0]->banner_image;
				$verP =filemtime($newimageVal);
				$banner_image = config('global.CAT_IMAGE_URL').$CategoryDetails[0]->banner_image."?ver=".$verP;
				$this->PageData['category_banner_image'] = $banner_image;
			}else{
				$this->PageData['category_banner_image'] = config('global.SITE_IMAGES').'cat_banner_new.jpg';
			}

			if(file_exists(config('global.CAT_IMAGE_PATH').$CategoryDetails[0]->mob_banner_image) and !empty($CategoryDetails[0]->mob_banner_image))
			{
				$newimageVal = config('global.CAT_IMAGE_PATH').$CategoryDetails[0]->mob_banner_image;
				$verP =filemtime($newimageVal);
				$mob_banner_image = config('global.CAT_IMAGE_URL').$CategoryDetails[0]->mob_banner_image."?ver=".$verP;
				$this->PageData['category_mob_banner_image'] = $mob_banner_image;
			}else{
				$this->PageData['category_mob_banner_image'] = "";
			}

			if (!empty($CategoryDetails[0]->meta_title)) {
				$this->PageData['meta_title'] =  stripslashes($CategoryDetails[0]->meta_title);
			} elseif ($MetaInfo->count() > 0 && !empty($MetaInfo[0]->meta_title)) {
				$meta_title = str_replace('{$category_name}', $CategoryDetails[0]->category_name, $MetaInfo[0]->meta_title);
				$this->PageData['meta_title'] = stripslashes($meta_title);
			}

			if (!empty($CategoryDetails[0]->meta_keywords)) {
				$this->PageData['meta_keywords'] =  stripslashes($CategoryDetails[0]->meta_keywords);
			} elseif ($MetaInfo->count() > 0 && !empty($MetaInfo[0]->meta_keywords)) {
				$meta_keywords = str_replace('{$category_description}', $CategoryDetails[0]->description, $MetaInfo[0]->meta_keywords);
				$this->PageData['meta_keywords'] = stripslashes($meta_keywords);
			}
			if (!empty($CategoryDetails[0]->meta_description)) {
				$this->PageData['meta_description'] =  stripslashes($CategoryDetails[0]->meta_description);
			} elseif ($MetaInfo->count() > 0 && !empty($MetaInfo[0]->meta_description)) {
				$meta_description = str_replace('{$category_description}', $CategoryDetails[0]->description, $MetaInfo[0]->meta_description);
				$this->PageData['meta_description'] = stripslashes($meta_description);
			}
			
			if($CategoryDetails[0]->parent_id != 0)
			{
				$Parent = $CategoryDetails[0]->parent;
				$CatParent = remove_special_chars(strtolower($Parent->category_name));
			} 
			$sucat_name = remove_special_chars(strtolower($CategoryDetails[0]->category_name));
			$catprod_link = config('global.SITE_URL').$CatParent.$sucat_name."/p4u/cid-".$CategoryDetails[0]->category_id."/view";
			if($CategoryDetails[0]->Template_list == "Product List")
			{
				return redirect($catprod_link);
			}
        }
		$CategoryTree = GetMainCatsTree([$request->category_id]);
		$CategoryList = $CategoryTree['CatList'];
		$Cats = array_column($CategoryList,'category_id');
		$setFilters = [];
		if(isset($request->page) && $request->page != '') {
			$setFilters['page'] = $request->page;
		}
		if(isset($request->filters) && $request->filters != '') {
			if(strpos($request->filters, 'size') !== false){
			    $explodeFilter = explode("-",$request->filters);
				$setFilters['size'] = [$explodeFilter[1]];
			}
			if(strpos($request->filters, 'fragrance_family') !== false){
			    $explodeFilter = explode("-",$request->filters);
				$setFilters['fragrance_family'] = [$explodeFilter[1]];
			}
			if(strpos($request->filters, 'special') !== false){
			    $explodeFilter = explode("-",$request->filters);
				$setFilters['special'] = [$explodeFilter[1]];
			}
		}
		if(isset($request->sortby) && $request->sortby != 'Sort By') {
			$setFilters['sortby'] = $request->sortby;
		}
		$this->PageData['Categories'] = $CategoryList;
		if($CategoryDetails[0]->parent_id != 0 ){
			$setFilters['categories'] = [$request->category_id];
			$Details = $this->GetProducts('','',12, $setFilters);
		} else {
			if($request->filters == 'test')
			{
				$Details = $this->GetProductsNew('',$request->category_id,12, $setFilters);
			} else {
				//$Details = $this->GetProducts('MainCategory',$request->category_id,12, $setFilters);
                $Details = $this->GetProductsForMainCategory('MainCategory',$request->category_id,12, $setFilters);
			}
		}
		
		if($request->filters != 'test')
		{	$TotalCatProds =0;	
			$Products = $Details['Products'];
			if($Details['TotalProducts'] > 0)
              $TotalCatProds = $Details['TotalProducts'];
			
			$TotalProducts = $Details['TotalProducts'];
			$LeftFilters = $Details['LeftFilters'];
			$this->PageData['Products'] = $Products;
			$this->PageData['TotalProducts'] = $TotalProducts;
			$this->PageData['TotalCatProds'] = $TotalCatProds;
			$this->PageData['Filters'] = array_reverse($LeftFilters);
			$AllCatInfo = config('CATEGORY_INFO');
			$Bredcrum = $AllCatInfo['CatForProd'][$request->category_id]['subcatbredcrum'];
			$this->PageData['Bredcrum'] = $Bredcrum;
			$this->PageData['PageTitle'] = $Bredcrum;
			
			if($request->ajax()) {
				$ProductHTML = view('product.list')->with($this->PageData)->render();
				return response()->json(array('TotalProducts' => $TotalProducts, 'ProductHTML'=>$ProductHTML));
			} else {
				if($request->category_id == '1'){
					if(isset($request->ver) && $request->ver=="new")
					{
						return view('category.maincategorylanding_new')->with($this->PageData);
					}
					else
					{
						return view('category.maincategorylanding')->with($this->PageData);
					}
				}else{
					$this->PageData['CSSFILES'] = ['slick.css', 'category_page.css', 'listing.css'];
					$this->PageData['JSFILES'] = ['slick.js', 'category_page.js'];
					
					//$BestSellers = $this->ProductSlider('TOP SELLERS','Home',$request->category_id);
					$SliderFilters['special'] = ['top_seller'];
					$BestSellers = $this->GetProducts('TOP_SELLERS',$request->category_id,12);		
					
					if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
					{
						//dd($BestSellers['Products']);
					}
					
					$this->PageData['BestSellers'] = $BestSellers['Products'];
					$this->PageData['BestSellersAttr'] = [
						'Title' => 'Best Sellers',
						'Slider' => 'home-new-sl',
						'SeeMore' => config('global.SITE_URL').$sucat_name.'/p4u/cid-'.$request->category_id.'/special-ts/view',
					];
					
					$SliderFilters['special'] = ['new_arrival'];
					$NewArrivals = $this->GetProducts('NEW_ARRIVALS',$request->category_id,12);
					//$SliderProducts[0]['products'] = $NewArrivals['Products'];
					
					//$NewArrivals = $this->ProductSlider('NEW ARRIVALS','Home',$request->category_id);
					$this->PageData['NewArrivals'] = $NewArrivals['Products'];
					$this->PageData['NewArrivalsAttr'] = [
						'Title' => 'New Arrivals',
						'Slider' => 'home-new-sl',
						'SeeMore' => config('global.SITE_URL').$sucat_name.'/p4u/cid-'.$request->category_id.'/special-na/view',
					];
					
					/*
					$NewArrivals = $this->ProductSlider('NEW ARRIVALS','Home',$request->category_id);
					$this->PageData['NewArrivals'] = $NewArrivals[0]['products'];
					$this->PageData['NewArrivalsAttr'] = [
						'Title' => 'New Arrivals',
						'Slider' => 'home-new-sl',
						'SeeMore' => config('global.SITE_URL').'new-arrivals/p4u/special-na/view',
					];


					$BestSellers = $this->ProductSlider('TOP SELLERS','Home',$request->category_id);
					$this->PageData['BestSellers'] = $BestSellers[0]['products'];
					$this->PageData['BestSellersAttr'] = [
						'Title' => 'Best Sellers',
						'Slider' => 'home-new-sl',
						'SeeMore' => config('global.SITE_URL').'top-sellers/p4u/special-ts/view',
					];
					*/
					return view('category.index')->with($this->PageData);	
				}	
			}
		} else {
			return view('product.test')->with($this->PageData);	
		}			
	}

}
