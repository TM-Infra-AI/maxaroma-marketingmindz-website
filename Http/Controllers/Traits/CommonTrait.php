<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

use App\Http\Controllers\Traits\VendorTrait;
use Illuminate\Support\Facades\Log;
use App\Models\HomepageProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductsCategory;
use App\Models\ProductsReview;
use App\Models\Listingmenu;
use App\Models\Manufacture;
use App\Models\Products;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\GiftCertificate;
use App\Models\Customer;
use App\Models\RewardRule;
use App\Models\MailBanner;
use App\Models\RewardPoint;
use App\Models\Dealofweek;
use App\Models\ReferFriend;

use DB;
use Session;
use Cache;

trait CommonTrait
{	
	use VendorTrait;
	
	public function Make_Price($text, $currency_symbol=false, $round=false) { 
		$curr_rate = Session::get('currency_rate');
		$text1	   = $text*$curr_rate;

		if(preg_match("/".config('global.CONTROL_PANEL_NAME')."/i",$_SERVER['REQUEST_URI'])) {
			$text1 = $text;
			return number_format($text1, 2, '.', '');
		}
		if($currency_symbol == true) {
			if($round==true) {
				return Session::get('currency_symbol').number_format(round($text1), 0, '', ',');
			}else {
				return Session::get('currency_symbol').number_format($text1, 2, '.', ',');
			}
		}else {
			return number_format($text1, 2, '.', '');
		}
	}
	
	public function ProductSlider($Flag='',$FileFlag, $CategoryID='')
	{
		$HomeProducts = HomepageProduct::where('chk_flag','=',$Flag)->orderBy('position')->get();
		$SliderProducts = [];
		if($Flag == 'TOP SELLERS')
			$Flag = "BEST SELLERS";
		if($HomeProducts->count() > 0)
		{
			foreach($HomeProducts as $key => $HomeProduct)
			{	
				$SliderProducts[$key]['ihomepageproductid'] = $HomeProduct->ihomepageproductid;	
				$SliderProducts[$key]['title'] = $HomeProduct->home_flag;	
				$SliderProducts[$key]['product_link'] = $HomeProduct->product_link;	
				$SliderProducts[$key]['numb'] = $key;
				if($HomeProduct->products != '')
				{
					
					$SliderProducts[$key]['products'] = $this->GetSliderProducts($HomeProduct->products,$Flag,$FileFlag,$CategoryID);
					if(count($SliderProducts[$key]['products']) < 12)
					{
						$getPrdLimit = 12 - count($SliderProducts[$key]['products']);
						$ExtraProds = $this->fetchhomeprodDev($Flag,$FileFlag,$getPrdLimit,$CategoryID);
						if(count($ExtraProds) > 0){
							$SliderProducts[$key]['products'] = array_merge($SliderProducts[$key]['products'],$ExtraProds);
                        } 
					}
				}				
			}
		}
		//dd($SliderProducts);
		return $SliderProducts;
	}
	
	public function PrepareProduct($Product,$key=0)
	{
	    if(!empty($Product))
	    {
	       // dd($Product);
		$product_link = config('global.SITE_URL');
		$product_name = remove_special_chars($Product->product_name);
		$Product->product_url = SetProductURL($Product->products_id,$Product->product_name,$Product->category_id);
		
		if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
			$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($Product->image);
			$verP = filemtime($newimageVal);
			$Product->prod_image  = config('global.PRD_THUMB_IMG_URL') . $Product->image . "?ver=" . $verP;
		} else {
			$Product->prod_image = config('global.NO_IMAGE_THUMB');
		}	
		
		if ($Product->gender == 'M'){
			$Product->gender = "sv-men";
			$Product->gendernames = "Men";
			$for_gender = ' for Men';
		} elseif ($Product->gender == 'W'){
			$Product->gender = "sv-women";
			$Product->gendernames = "Women";
			$for_gender = ' for Women';
		} elseif ($Product->gender == 'K'){
			$Product->gender = "sv-kids";
			$Product->gendernames = "Kids";
			$for_gender = ' for Kids';
		} elseif ($Product->gender == 'U'){
			$Product->gender = "sv-unisex";
			$Product->gendernames = "Unisex";
			$for_gender = ' Unisex';
		} else{
			$Product->gender = "";
			$Product->gendernames = "";
			$for_gender = '';
		}
		
		if($Product->vmanufacture != ''){
			$m_name = strtolower($Product->vmanufacture);
			$m_name = str_replace("#", "", $m_name);
			$m_name = str_replace("&", "", $m_name);
			$m_name = str_replace("  ", " ", trim($m_name));
			$m_name = str_replace("  ", " ", trim($m_name));
			$m_name = str_replace(" ", "-", $m_name);
			$Product->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$Product->imanufactureid;
		}
		
		if($Product->brand_name != '' && $Product->vmanufacture != ''){
			$m_name = strtolower($Product->vmanufacture);
			$m_name = str_replace("#", "", $m_name);
			$m_name = str_replace("&", "", $m_name);
			$m_name = str_replace("  ", " ", trim($m_name));
			$m_name = str_replace("  ", " ", trim($m_name));
			$m_name = str_replace(" ", "-", $m_name);
			$Product->referencedName = '<a href="' . $Product->product_url . '"><strong><u>' . $Product->brand_name . '</u></strong></a> by <a href='.$Product->vmanufacture_link.'><strong><u><br>'.$Product->vmanufacture.'</strong></u></a><br>'.$for_gender;
		}
		
		if(strlen($Product->product_name) > 45){
			$Product->product_name = substr($Product->product_name, 0, (45 - strlen($Product->product_name))). "..";
		} else {
			$Product->product_name = $Product->product_name;
		}

		if($Product->vmanufacture == '' || $Product->brand_name == ''){
			$Product->referencedName = '<a href="' . $Product->product_url . '"><u>' . $Product->product_name . '</u></a>';
		}
		
		if($Product->retail_price != '' && $Product->retail_price != '0.00' && isset($Product->product_price)){
			$yousave = ($Product->retail_price - $Product->product_price) / $Product->retail_price;
			$yousave = $yousave * 100;
			$yousave = number_format($yousave, 0);
			$yousaveprice = $Product->retail_price - $Product->product_price;
		}else{
			$yousave = 0;
			$yousaveprice = 0;
		}
		
		$Product->yousave = $yousave;
		$Product->maxyousave = number_format($Product->yousave, 0);
		$Product->yousaveprice = $yousaveprice;
		$Product->autoid = $key;
		
		$Product->sale_item = '0';
		if($Product->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
		{
			$Product->sale_item = '1';
		}
		
		$DealData = config('DealDetails');
		if(isset($DealData[$Product->sku]))
		{
			$Product->deal_price = $DealData[$Product->sku]['deal_price'];
			$Product->yousave = $DealData[$Product->sku]['yousave'];
			$Product->yousaveprice = $DealData[$Product->sku]['yousaveprice'];
		}
		$Product->short_description = strip_tags($Product->short_description);
		$Product->avg_rate = 0;
		$total_review = $Product->TotalReview;
		if($total_review > 0)
			$Product->avg_rate = GetProductAverageRating($Product->TotalReview,$Product->TotalRate);
	    }
	    else
	    {
	       $Product=[];
	    }
		return $Product;
	}
	
	public function GetSliderProducts($ProductString='',$Flag='',$FileFlag='',$CategoryID='',$ArrayFilters=[])
	{
		$SldProducts = [];
		$VariationIDs = [];
		$CatArrVal = [];
		if($ProductString != "")
		{			
			if (strstr($ProductString, ','))
			{
				$ProductString = str_replace("  ", "", $ProductString);	
				$ProductString = str_replace(" ", "", $ProductString);
				$ProductString = str_replace(",", "#", $ProductString);	
			}
			$ProductString = trim($ProductString);
			$ProductString = substr($ProductString,0,strlen($ProductString)-1);
			$ProductString = explode("#", trim($ProductString));
	
			if($CategoryID=='68' || $CategoryID=='70' || $CategoryID=='71' || $CategoryID=='69')
				$CatArrVal = [$CategoryID];

			$ProdQry = DB::table('pu_products as po')
						->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
									'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
									'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
									'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
									'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.category_name','c.parent_id')			
						->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','po.sku')])			
						->join('pu_products_category as pc','po.products_id','=','pc.products_id')
						->join('pu_category as c','pc.category_id','=','c.category_id')
						->join('pu_brand as b','b.brand_id','=','po.brand_id')
						->join('pu_manufacture as m',function($join){
							$join->on('po.imanufactureid','=','m.imanufactureid');
							$join->on('b.imanufactureid','=','m.imanufactureid');
						})
						->whereIn('po.sku',$ProductString)
						->where('po.status','=','1')
						->where('c.status','=','1');
						/*
						->whereIn('po.variation_id',function($query) use ($ProductString){
							$query->select('variation_id')->from('pu_products_one')
								->whereIn('sku',$ProductString);
						})*/
			
			if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
				$ProdQry->whereIn('po.product_type',['both','retailer','wholesaler']);
			else
				$ProdQry->whereIn('po.product_type',['both','retailer']);
			
			if($Flag == 'NEW ARRIVALS'){
				if($FileFlag == 'Home')
					$ProdQry->whereNotIn('pc.category_id',['198','199','200','201']);
				else 
					$ProdQry->whereNotIn('pc.category_id',['68','69','70','71','198','199','200','201']);
			}
			if($Flag == 'BEST SELLERS')
			{
				$ProdQry->where(function($query){
					$query->orWhere('po.current_stock','>',0);
					$query->OrWhere(function($qry){
						$qry->where('po.cosmo_current_stock','>',0)->where('po.cosmo_sku','!=','');	
					});
					$query->OrWhere(function($qry){
						$qry->where('po.pca_current_stock','>',0)->where('po.pca_sku','!=','');	
					});
					$query->OrWhere(function($qry){
						$qry->where('po.nandansons_current_stock','>',0)->where('po.nandansons_sku','!=','');	
					});
				});
			}
			$ProdQry->groupBy('po.products_id','po.variation_id');
			$Prods = $ProdQry->get();
			
			$SkipVariationID = [];
			$TotalProds = 0;
			$ProdIds=[];
			if($Prods->count() > 0)
			{
				//$SliderCategory = $this->GetCategories($Prods);	
				
				foreach($Prods as $key => $Product) 
				{
					if(!in_array($Product->sku,$ProductString))
						continue;
					$Product = $this->SetProduct($Product);
					/*
					if(isset($ArrayFilters['stock']) && $ArrayFilters['stock'] != '' && $Product->stock == '0')
						continue;
					
					if(isset($ArrayFilters['minprice']) && $ArrayFilters['minprice'] !='' && isset($ArrayFilters['maxprice']) && $ArrayFilters['maxprice'] != '')
					{	
						if($Product->product_price < $ArrayFilters['minprice'] || $Product->product_price > $ArrayFilters['maxprice'] )
							continue;
					}*/
					
					if($Product->product_price <=0 && in_array($Product->sku,$ProductString))
					{
						$SkipVariationID[]=$Product->variation_id;
						continue;
					}
					
					
					if(in_array($Product->variation_id,$SkipVariationID))
						continue;
					
					$Product->size_cnt = 0;
					if($Product->is_atomizer == "Yes" || $Product->stock == "Out")
					{
						$SizeCountArr = $this->getReferencedProducts_Counter_ListingDev($Product->products_id,$Product->variation_id,$CategoryID,$CatArrVal,$Prods);
						if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'No' && $SizeCountArr[0]->is_atomizer != '')
							$Product = $SizeCountArr[0];
						else if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'Yes' && $SizeCountArr[0]->stock =='In')
							$Product = $SizeCountArr[0];
						/*else
							$Product->size_cnt = $SizeCountArr;*/
					}/* else {
						
						$Product->size_cnt = $this->getReferencedProducts_CounterDev($Product->products_id,$Product->variation_id,$Prods);	
					}*/
					
					
					if($CategoryID == '2')
						$Product->category_id = $CategoryID;
					
					//Make Product Link Start
					$product_link = config('global.SITE_URL');
					
					
					$product_name = remove_special_chars($Product->product_name);
					//$product_link.= $CatInfo[$Product->category_id].$product_name."/pid/".$Product->products_id."/".$Product->category_id;
					$Product->product_url = SetProductURL($Product->products_id,$Product->product_name,$Product->category_id);
					//Make Product Link End
					
					if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
						$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($Product->image);
						$verP = filemtime($newimageVal);
						$Product->prod_image = config('global.PRD_THUMB_IMG_URL') . $Product->image . "?ver=" . $verP;
					} else {
						$Product->prod_image = config('global.NO_IMAGE_THUMB');
					}	
					
					if ($Product->gender == 'M'){
						$Product->gender = "sv-men";
						$Product->gendernames = "Men";
						$for_gender = ' for Men';
					} elseif ($Product->gender == 'W'){
						$Product->gender = "sv-women";
						$Product->gendernames = "Women";
						$for_gender = ' for Women';
					} elseif ($Product->gender == 'K'){
						$Product->gender = "sv-kids";
						$Product->gendernames = "Kids";
						$for_gender = ' for Kids';
					} elseif ($Product->gender == 'U'){
						$Product->gender = "sv-unisex";
						$Product->gendernames = "Unisex";
						$for_gender = ' Unisex';
					} else{
						$Product->gender = "";
						$Product->gendernames = "";
						$for_gender = '';
					}
					
					if($Product->vmanufacture != ''){
						$m_name = strtolower($Product->vmanufacture);
						$m_name = str_replace("#", "", $m_name);
						$m_name = str_replace("&", "", $m_name);
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace(" ", "-", $m_name);
						$Product->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$Product->imanufactureid;
					}
					
					if($Product->brand_name != '' && $Product->vmanufacture != ''){
						$m_name = strtolower($Product->vmanufacture);
						$m_name = str_replace("#", "", $m_name);
						$m_name = str_replace("&", "", $m_name);
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace(" ", "-", $m_name);
						$Product->referencedName = '<a href="' . $Product->product_url . '"><strong><u>' . $Product->brand_name . '</u></strong></a> by <a href='.$Product->vmanufacture_link.'><strong><u><br>'.$Product->vmanufacture.'</strong></u></a><br>'.$for_gender;
					}
					
					if(strlen($Product->product_name) > 45){
						$Product->product_name = substr($Product->product_name, 0, (45 - strlen($Product->product_name))). "..";
					} else {
						$Product->product_name = $Product->product_name;
					}

					if($Product->vmanufacture == '' || $Product->brand_name == ''){
						$Product->referencedName = '<a href="' . $Product->product_url . '"><u>' . $Product->product_name . '</u></a>';
					}
					
					if($Product->retail_price != '' && $Product->retail_price != '0.00' && isset($Product->product_price)){
						$yousave = ($Product->retail_price - $Product->product_price) / $Product->retail_price;
						$yousave = $yousave * 100;
						$yousave = number_format($yousave, 0);
						$yousaveprice = $Product->retail_price - $Product->product_price;
					}else{
						$yousave = 0;
						$yousaveprice = 0;
					}
					
					$Product->yousave = $yousave;
					$Product->maxyousave = 0;
					if($yousave > 0)
					{
					$Product->maxyousave = number_format($Product->yousave, 0);
					}
					$Product->yousaveprice = $yousaveprice;
					$Product->autoid = $key;
					
					$Product->sale_item = '0';
					if($Product->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
					{
						$Product->sale_item = '1';
					}
					
					$DealData = config('DealDetails');
					if(isset($DealData[$Product->sku]))
					{
						$Product->deal_price = $DealData[$Product->sku]['deal_price'];
						$Product->yousave = $DealData[$Product->sku]['yousave'];
						$Product->yousaveprice = $DealData[$Product->sku]['yousaveprice'];
					}
					$Product->short_description = strip_tags($Product->short_description);
					$Product->avg_rate = 0;
					$total_review = $Product->TotalReview;
					if($total_review > 0)
						$Product->avg_rate = GetProductAverageRating($Product->TotalReview,$Product->TotalRate);
					
					$VariationIDs[] = $Product->variation_id;
					$SldProducts[] = $Product;
				}	
			}
		}
		$SldProducts = $this->CountOptions($VariationIDs,$SldProducts,$CatArrVal);
		return $SldProducts;
	}
	
	
	
	public function CountOptionsNew($VariationIDs=[],$DisplayProducts=[],$CatArrVal=[],$Flag='')
	{
		$VariationIDs = array_unique($VariationIDs);
		$VariationProductQry = DB::table('pu_products as po')
							->join('pu_products_category as pc','po.products_id','=','pc.products_id')
							->join('pu_category as c','pc.category_id','=','c.category_id')
							->join('pu_brand as b','b.brand_id','=','po.brand_id')
							->join('pu_manufacture as m',function($join){
								$join->on('po.imanufactureid','=','m.imanufactureid');
								$join->on('b.imanufactureid','=','m.imanufactureid');
							})
							->select('po.products_id','po.product_name','po.image','po.is_atomizer','po.sku','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.vtype','po.variation_id','c.category_id','m.vmanufacture','m.imanufactureid','b.brand_name','po.short_description')
							->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')]);
							
		if($Flag == "TOP_SELLERS" || $Flag == "NEW_ARRIVALS")
		{
			if(count($CatArrVal) > 0)
				$VariationProductQry->whereIn('pc.category_id',$CatArrVal);
		}else{	
			$VariationProductQry->whereIn('po.variation_id',$VariationIDs);		
		}
			$VariationProductQry->where('po.status','=','1')->where('c.status','=','1')->where('b.status','=','1')->where('m.status','=','1');
		if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $VariationProductQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $VariationProductQry->whereIn('po.product_type',['both','retailer']);					
		
		$VariationProductQry->orderBy('po.current_stock');
		$VariationProductQry->orderBy('po.nandansons_current_stock');
		$VariationProductQry->orderBy('po.cosmo_current_stock');
		$VariationProductQry->orderBy('po.pca_current_stock');
		$VariationProducts = $VariationProductQry->groupBy('po.products_id')->get()->toArray();
				
		$TotalVariations=[];
		$Variation='';
		$vcount = 0;
		
		$TotalVariations = array_count_values(array_column($VariationProducts, 'variation_id'));
		$ProdCnt = [];
		$Price = [];
		foreach($VariationProducts as $Product)
		{
			$Product = $this->SetProduct($Product);
			$Price[$Product->variation_id][] = (float)$Product->product_price;
		}
		$NewProduct = [];
		
		foreach($DisplayProducts as $ProductNew)
		{
			if(isset($TotalVariations[$ProductNew->variation_id]))
				$ProductNew->size_cnt = $TotalVariations[$ProductNew->variation_id];
			else
				$ProductNew->size_cnt = 0;
			
			if(isset($Price[$ProductNew->variation_id]) && count($Price[$ProductNew->variation_id]) > 0)
			{
				$ProductNew->minPrice = min($Price[$ProductNew->variation_id]);
				$ProductNew->maxPrice = max($Price[$ProductNew->variation_id]);
			} else {
				$ProductNew->minPrice = 0;
				$ProductNew->maxPrice = 0;
			}
			$NewProduct[] = $ProductNew;
		}
		
		for($i=0;$i<count($NewProduct);$i++)
		{
			if($NewProduct[$i]->is_atomizer == "Yes" || $NewProduct[$i]->stock == "Out")
			{
				foreach($VariationProducts as $Product)
				{
					if ($Product->products_id == $NewProduct[$i]->products_id)
					continue;
					if ($Product->variation_id != $NewProduct[$i]->variation_id)
						continue;

					$Product = $this->SetProduct($Product);

					if ($Product->is_atomizer == "Yes" && $Product->stock == "Out")
						continue;
					if ($Product->stock == "Out" && $Product->is_atomizer == "No")
						continue;

					if ($Product->is_atomizer == "No") 
					{
						if ($Product->stock == "In" && !empty($Product->category_id) && $Product->category_id == $NewProduct[$i]->category_id) 
						{
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;
							
							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}
							
							if (count($CatArrVal) > 0) {
								$isAtom1 = 'No';
								for ($j = 0; $j < count($CatArrVal); $j++) {
									if ($CatArrVal[$j] != 68 && $CatArrVal[$j] != 70 &&  $CatArrVal[$j] != 71 &&  $CatArrVal[$j] != 69) {
										$isAtom1 = 'Yes';
									}
								}
								if ($isAtom1 == 'Yes') {
									break;
								}
							} else {
								break;
							}
						} else if ($Product->stock == "In") {
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;
							
							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}

						} else {
							if ($NewProduct[$i]->is_atomizer != 'Yes' && $NewProduct[$i]->stock != 'In') {
								$NewProduct[$i] = $this->PrepareProduct($Product,$i);
								if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
									$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
								else
									$NewProduct[$i]->size_cnt = 0;
								
								if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
								{
									$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
									$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
								} else {
									$NewProduct[$i]->minPrice = 0;
									$NewProduct[$i]->maxPrice = 0;
								}
							}
						}
					} else {
						if ($Product->stock == "In" && ($NewProduct[$i]->stock != 'In' || in_array(68, $CatArrVal) || in_array(70, $CatArrVal) || in_array(71, $CatArrVal) || in_array(69, $CatArrVal))) {
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;
							
							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}
							$isAtom = 'No';
							for ($j = 0; $j < count($CatArrVal); $j++) {
								if ($CatArrVal[$j] == 68 || $CatArrVal[$j] == 70 ||  $CatArrVal[$j] == 71 ||  $CatArrVal[$j] == 69) {
									$isAtom = 'Yes';
								}
							}
							if ($isAtom == 'Yes')
								break;
						}
					}
				}	
			}
		}
		//dd($NewProduct);
		return $NewProduct;
	}
	
	
	public function CountOptions($VariationIDs=[],$DisplayProducts=[],$CatArrVal=[],$Flag='')
	{
		$VariationIDs = array_unique($VariationIDs);
		$VariationProductQry = DB::table('pu_products as po')
							->join('pu_products_category as pc','po.products_id','=','pc.products_id')
							->join('pu_category as c','pc.category_id','=','c.category_id')
							->join('pu_brand as b','b.brand_id','=','po.brand_id')
							->join('pu_manufacture as m',function($join){
								$join->on('po.imanufactureid','=','m.imanufactureid');
								$join->on('b.imanufactureid','=','m.imanufactureid');
							})
							->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.parent_id')
							->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')]);			
							//->whereIn('po.variation_id',$VariationIDs)
							
		if($Flag == "TOP_SELLERS" || $Flag == "NEW_ARRIVALS")
		{
			if(count($CatArrVal) > 0)
				$VariationProductQry->whereIn('pc.category_id',$CatArrVal);
		}else{	
			$VariationProductQry->whereIn('po.variation_id',$VariationIDs);		
		}
			$VariationProductQry->where('po.status','=','1')->where('c.status','=','1')->where('b.status','=','1')->where('m.status','=','1');
		if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $VariationProductQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $VariationProductQry->whereIn('po.product_type',['both','retailer']);					
		
		$VariationProductQry->orderBy('po.current_stock');
		$VariationProductQry->orderBy('po.nandansons_current_stock');
		$VariationProductQry->orderBy('po.cosmo_current_stock');
		$VariationProductQry->orderBy('po.pca_current_stock');
		$VariationProducts = $VariationProductQry->groupBy('po.products_id')->get()->toArray();
				
		$TotalVariations=[];
		$Variation='';
		$vcount = 0;
		
		$TotalVariations = array_count_values(array_column($VariationProducts, 'variation_id'));
		$ProdCnt = [];
		$Price = [];
		foreach($VariationProducts as $Product)
		{
			$Product = $this->SetProduct($Product);
			$Price[$Product->variation_id][] = (float)$Product->product_price;
		}
		$NewProduct = [];
		
		foreach($DisplayProducts as $ProductNew)
		{
			if(isset($TotalVariations[$ProductNew->variation_id]))
				$ProductNew->size_cnt = $TotalVariations[$ProductNew->variation_id];
			else
				$ProductNew->size_cnt = 0;
			
			if(isset($Price[$ProductNew->variation_id]) && count($Price[$ProductNew->variation_id]) > 0)
			{
				$ProductNew->minPrice = min($Price[$ProductNew->variation_id]);
				$ProductNew->maxPrice = max($Price[$ProductNew->variation_id]);
			} else {
				$ProductNew->minPrice = 0;
				$ProductNew->maxPrice = 0;
			}
			$NewProduct[] = $ProductNew;
		}
		
		for($i=0;$i<count($NewProduct);$i++)
		{
			if($NewProduct[$i]->is_atomizer == "Yes" || $NewProduct[$i]->stock == "Out")
			{
				foreach($VariationProducts as $Product)
				{
					if ($Product->products_id == $NewProduct[$i]->products_id)
					continue;
					if ($Product->variation_id != $NewProduct[$i]->variation_id)
						continue;

					$Product = $this->SetProduct($Product);

					if ($Product->is_atomizer == "Yes" && $Product->stock == "Out")
						continue;
					if ($Product->stock == "Out" && $Product->is_atomizer == "No")
						continue;

					if ($Product->is_atomizer == "No") 
					{
						if ($Product->stock == "In" && $Product->category_id == $NewProduct[$i]->category_id) 
						{
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;
							
							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}
							
							if (count($CatArrVal) > 0) {
								$isAtom1 = 'No';
								for ($j = 0; $j < count($CatArrVal); $j++) {
									if ($CatArrVal[$j] != 68 && $CatArrVal[$j] != 70 &&  $CatArrVal[$j] != 71 &&  $CatArrVal[$j] != 69) {
										$isAtom1 = 'Yes';
									}
								}
								if ($isAtom1 == 'Yes') {
									break;
								}
							} else {
								break;
							}
						} else if ($Product->stock == "In") {
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;
							
							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}

						} else {
							if ($NewProduct[$i]->is_atomizer != 'Yes' && $NewProduct[$i]->stock != 'In') {
								$NewProduct[$i] = $this->PrepareProduct($Product,$i);
								if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
									$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
								else
									$NewProduct[$i]->size_cnt = 0;
								
								if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
								{
									$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
									$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
								} else {
									$NewProduct[$i]->minPrice = 0;
									$NewProduct[$i]->maxPrice = 0;
								}
							}
						}
					} else {
						if ($Product->stock == "In" && ($NewProduct[$i]->stock != 'In' || in_array(68, $CatArrVal) || in_array(70, $CatArrVal) || in_array(71, $CatArrVal) || in_array(69, $CatArrVal))) {
							$NewProduct[$i] = $this->PrepareProduct($Product,$i);
							if(isset($TotalVariations[$NewProduct[$i]->variation_id]))
								$NewProduct[$i]->size_cnt = $TotalVariations[$NewProduct[$i]->variation_id];
							else
								$NewProduct[$i]->size_cnt = 0;
							
							if(isset($Price[$NewProduct[$i]->variation_id]) && count($Price[$NewProduct[$i]->variation_id]) > 0)
							{
								$NewProduct[$i]->minPrice = min($Price[$NewProduct[$i]->variation_id]);
								$NewProduct[$i]->maxPrice = max($Price[$NewProduct[$i]->variation_id]);
							} else {
								$NewProduct[$i]->minPrice = 0;
								$NewProduct[$i]->maxPrice = 0;
							}
							$isAtom = 'No';
							for ($j = 0; $j < count($CatArrVal); $j++) {
								if ($CatArrVal[$j] == 68 || $CatArrVal[$j] == 70 ||  $CatArrVal[$j] == 71 ||  $CatArrVal[$j] == 69) {
									$isAtom = 'Yes';
								}
							}
							if ($isAtom == 'Yes')
								break;
						}
					}
				}	
			}
		}
		//dd($NewProduct);
		return $NewProduct;
	}
	
	public function fetchhomeprodDev($Flag,$FileFlag='',$getPrdLimit='',$CategoryID='')
	{
		$SldProducts = [];
		$VariationIDs = [];
		$ProdIds=[];
		$ProdQry = DB::table('pu_products as po')
					->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.parent_id')
					->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')])			
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_brand as b','b.brand_id','=','po.brand_id')
					->join('pu_manufacture as m',function($join){
						$join->on('po.imanufactureid','=','m.imanufactureid');
						$join->on('b.imanufactureid','=','m.imanufactureid');
					})
					->where('po.status','=','1');
			
		if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
			$ProdQry->whereIn('po.product_type',['both','retailer','wholesaler']);
		else
			$ProdQry->whereIn('po.product_type',['both','retailer']);
		
		if($Flag == 'NEW ARRIVALS'){
			if($FileFlag != 'Home')
				$ProdQry->whereNotIn('pc.category_id',['198','199','200','201']);
			else 
				$ProdQry->whereNotIn('pc.category_id',['68','69','70','71','198','199','200','201']);
			
			$ProdQry->where(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>',DB::raw("DATE_SUB(CURDATE(),INTERVAL 90 DAY)"))
					->where('po.cosmo_sku','=','')->where('po.pca_sku','=','')->where('po.nandansons_sku','=','')
					->where('po.current_stock','>',0)
					->orderBy('po.add_datetime','desc');
		}
		if($Flag == 'BEST SELLERS')
		{
			$ProdQry->where('pc.category_id','=','2');
			$ProdQry->where('po.is_sold_quantity','>','0');
			$ProdQry->where(function($query){
				$query->orWhere('po.current_stock','>',0);
				$query->OrWhere(function($qry){
					$qry->where('po.cosmo_current_stock','>',0)->where('po.cosmo_sku','!=','');	
				});
				$query->OrWhere(function($qry){
					$qry->where('po.pca_current_stock','>',0)->where('po.pca_sku','!=','');	
				});
				$query->OrWhere(function($qry){
					$qry->where('po.nandansons_current_stock','>',0)->where('po.nandansons_sku','!=','');	
				});
			});
		}
		$ProdQry->groupBy('po.variation_id');
        if($getPrdLimit != '')
		  $ProdQry->limit($getPrdLimit);
		$Prods = $ProdQry->get();
		
		$SkipVariationID = [];
		if($Prods->count() > 0)
		{
			//$SliderCategory = $this->GetCategories($Prods);	
			foreach($Prods as $key => $Product) 
			{
				$Product = $this->SetProduct($Product);
				
				if($Product->product_price <=0 && in_array($Product->sku,$ProductString))
				{
					$SkipVariationID[]=$Product->variation_id;
					continue;
				}
				
				if(in_array($Product->variation_id,$SkipVariationID))
					continue;
				
				$VariationIDs[] = $Product->variation_id;
				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
					$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($Product->image);
					$verP = filemtime($newimageVal);
					$Product->prod_image = config('global.PRD_THUMB_IMG_URL') . $Product->image . "?ver=" . $verP;
				} else {
					$Product->prod_image = config('global.NO_IMAGE_THUMB');
				}
				/*
				if($Product->is_atomizer == "Yes" || $Product->stock == "Out")
				{
					$SizeCountArr = $this->getReferencedProducts_Counter_ListingDev($Product->products_id,$Product->variation_id,$CategoryID,[],$Prods);
					if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'No' && $SizeCountArr[0]->is_atomizer != '')
						$Product = $SizeCountArr[0];
					else if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'Yes' && $SizeCountArr[0]->stock =='In')
						$Product = $SizeCountArr[0];
					
				} else {
					//$Product->size_cnt = $this->getReferencedProducts_CounterDev($Product->products_id,$Product->variation_id,$Prods);	
				}
				*/
				$PriceRange = $this->setPriceRange($Product->variation_id,$Prods);
				$Product->minPrice = $PriceRange['MinPrice'];
				$Product->maxPrice = $PriceRange['MaxPrice'];
				$Product->yousave = $PriceRange['YouSave'];
				
				//Make Product Link Start
				/*$product_link = config('global.SITE_URL');
				if($Product->parent_id != 0){
					$ProdCat = $Product->parent_id;
					$ProdCatDetails = $SliderCategory[$ProdCat];
					$category_url = remove_special_chars($ProdCatDetails->category_name);
					$product_link.=$category_url;
				}
				$ProdCat = $Product->category_id;
				$ProdCatDetails = $SliderCategory[$ProdCat];
				$category_url = remove_special_chars($ProdCatDetails->category_name);
				if($Product->parent_id != 0)
					$product_link.='/'.$category_url;
				else
					$product_link.=$category_url;
				
				$product_name = remove_special_chars($Product->product_name);
				$product_link.='/'.$product_name."/pid/".$Product->products_id."/".$Product->category_id;
				*/
				$Product->product_url = SetProductURL($Product->products_id,$Product->product_name,$Product->category_id);
				//Make Product Link End
				
				if ($Product->gender == 'M'){
					$Product->gender = "sv-men";
					$Product->gendernames = "Men";
					$for_gender = ' for Men';
				} elseif ($Product->gender == 'W'){
					$Product->gender = "sv-women";
					$Product->gendernames = "Women";
					$for_gender = ' for Women';
				} elseif ($Product->gender == 'K'){
					$Product->gender = "sv-kids";
					$Product->gendernames = "Kids";
					$for_gender = ' for Kids';
				} elseif ($Product->gender == 'U'){
					$Product->gender = "sv-unisex";
					$Product->gendernames = "Unisex";
					$for_gender = ' Unisex';
				} else{
					$Product->gender = "";
					$Product->gendernames = "";
					$for_gender = '';
				}
				
				if($Product->vmanufacture != ''){
					$m_name = strtolower($Product->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$Product->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$Product->imanufactureid;
				}
				
				if($Product->brand_name != '' && $Product->vmanufacture != ''){
					$m_name = strtolower($Product->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$Product->referencedName = '<a href="' . $Product->product_url . '"><strong><u>' . $Product->brand_name . '</u></strong></a> by <a href='.$Product->vmanufacture_link.'><strong><u><br>'.$Product->vmanufacture.'</strong></u></a><br>'.$for_gender;
				}
				
				if(strlen($Product->product_name) > 45){
					$Product->product_name = substr($Product->product_name, 0, (45 - strlen($Product->product_name))). "..";
				} else {
					$Product->product_name = $Product->product_name;
				}

				if($Product->vmanufacture == '' || $Product->brand_name == ''){
					$Product->referencedName = '<a href="' . $Product->product_url . '"><u>' . $Product->product_name . '</u></a>';
				}
				
				if($Product->retail_price != '' && $Product->retail_price != '0.00' && isset($Product->product_price)){
					$yousave = ($Product->retail_price - $Product->product_price) / $Product->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$yousaveprice = $Product->retail_price - $Product->product_price;
				}else{
					$yousave = 0;
					$yousaveprice = 0;
				}
				
				$Product->yousave = $yousave;
				$Product->maxyousave = number_format($Product->yousave, 0);
				$Product->yousaveprice = $yousaveprice;
				$Product->autoid = $key;
				
				$Product->sale_item = '0';
				if($Product->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
				{
					$Product->sale_item = '1';
				}
				
				$DealData = config('DealDetails');
				if(isset($DealData[$Product->sku]))
				{
					$Product->deal_price = $DealData[$Product->sku]['deal_price'];
					$Product->yousave = $DealData[$Product->sku]['yousave'];
					$Product->yousaveprice = $DealData[$Product->sku]['yousaveprice'];
				}
				$Product->short_description = strip_tags($Product->short_description);
				$Product->avg_rate = 0;
				$total_review = $Product->TotalReview;
				if($total_review > 0)
					$Product->avg_rate = GetProductAverageRating($Product->TotalReview,$Product->TotalRate);
				
				$SldProducts[] = $Product;
			}
		}
		$SldProducts = $this->CountOptions($VariationIDs,$SldProducts,[$CategoryID]);		
		return $SldProducts;
	}
	public function GetCategories($Products)
	{
		$Categoies = [];
		$SliderCats = [];
		foreach($Products as $Product)
		{
			if($Product->parent_id != 0)
				$Categoies[] = $Product->parent_id;
			$Categoies[] = $Product->category_id;
		}
		if(count($Categoies) > 0)
		{
			$Categoies = array_unique($Categoies);
			$ProdCats = Category::whereIn('category_id',$Categoies)->where('status','=','1')->orderBy('category_name')->get();
			foreach($ProdCats as $Cat)
				$SliderCats[$Cat->category_id] = $Cat;
		}
		return $SliderCats;
	}
	public function getProductRewriteURL($products_id, $product_name = '', $category_id = '', $vmanufacture = '')
	{
		$product_name = remove_special_chars($product_name);
		if ($vmanufacture != '')
            $vmanufacture = remove_special_chars($vmanufacture) . "/";
		if ($category_id == '')
        {
			$CatDetails = DB::table('pu_products_category as pcr')
							->join('pu_category as c','pcr.category_id','=','c.category_id')
							->where('pcr.products_id','=',$products_id)
							->where('c.status','=','1')
							->orderBy('c.display_position')->orderBy('c.category_name')
							->limit(1)->get();		
            $category_id = $CatDetails[0]->category_id;
        }
		$category_url = $this->getParentCategoryRewriteURL($category_id) . "/";
        return config('global.SITE_URL').$category_url.$product_name."/pid/".$products_id."/".$category_id;
	}
	
	public function getParentCategoryRewriteURL($category_id) {
		
        $new_vcat_name = '';
		$CatDetails = Category::where('category_id','=',$category_id)
						->where('status','=','1')->orderBy('category_name')->get();
        if($CatDetails->count() > 0)
        {
            $new_iparent_id = $CatDetails[0]->parent_id;
            $new_icat_id = $CatDetails[0]->category_id;
            $new_vcat_name = remove_special_chars(trim($CatDetails[0]->category_name));
            while($new_iparent_id != 0)
            {
				$ParentCatDetails = Category::where('category_id','=',$new_iparent_id)
						->where('status','=','1')->orderBy('category_name')->get();
                $new_iparent_id = $ParentCatDetails[0]->parent_id;
                $new_icat_id = $ParentCatDetails[0]->category_id;
                $new_vcat_name = remove_special_chars(trim($ParentCatDetails[0]->category_name)) . "/" . $new_vcat_name;
            }
        } 
		return $new_vcat_name;
    }
	
	public function SetFilters($Params)
	{
		$ExpFilters = explode("/",$Params->filters);
		if(isset($Params->mid) && $Params->mid != '')
			$ExpFilters[]='mid-'.$Params->mid;
		
		$AllFilters = [];
		$ParamString = ['cid' => 'categories', 'mid' => 'brands','family' => 'fragrance_family', 'type' => 'vtype', 
				'formulation' => 'formulation', 'stock' => 'stock', 'size' => 'size', 
				'special' => 'special', 'coverage' => 'coverage', 'finish' => 'finish', 
				'skin' => 'skin_type', 'features' => 'features'];		
		foreach($ExpFilters as $AllParam)
		{
			$ExpParam = explode("-",$AllParam);
			if(count($ExpParam)>0 && array_key_exists($ExpParam[0],$ParamString))
			{
				$Key = $ParamString[$ExpParam[0]];
				$AllFilters[$Key] = explode(',',$ExpParam[1]);
			} else if(count($ExpParam)>0 && $ExpParam[0] == 'key'){	
				$AllFilters['key'] = $ExpParam[1];
			} else if(count($ExpParam)>0 && $ExpParam[0] == 'price'){
				$AllFilters['minprice'] = $ExpParam[1];
				$AllFilters['maxprice'] = $ExpParam[2];					
			}					
		}
		return $AllFilters;
	}
		
	public function GetProducts($Flag,$CategoryID,$limit=12,$Filters=[])
	{
		$FilterCategories = [];
		$Offset = 0;
		$SortBy = "";
		$CatProdsQry = [];
		$ChildCatArr = [];
		if(count($Filters) > 0){
			foreach($Filters as $fkey => $Filter)
			{
				if($fkey == 'categories' && count($Filters) > 0){
					$ChildCatArr = $Filters['categories'];
				} 
			}
		} 
		if(count($ChildCatArr) == 0 && $CategoryID != '') {
			//$ChildCats = $this->GetChildCategories($CategoryID);
			$ChildCats = GetMainCatsTree([$CategoryID]);
			if(count($ChildCats['CatList']) > 0)
				$ChildCatArr = array_column($ChildCats['CatList'],'category_id');
			else
				$ChildCatArr = [$CategoryID];
		}
		
		if(isset($Filters['page']) && $Filters['page'] > 1){
				$Offset = ($Filters['page']-1) * $limit;
		}
		
		$SortBy = isset($Filters['sortby'])?$Filters['sortby']:'';
	
		$CatProdsQry = DB::table('pu_products as po')
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_brand as b','b.brand_id','=','po.brand_id')
					->join('pu_manufacture as m',function($join){
						$join->on('po.imanufactureid','=','m.imanufactureid');
						$join->on('b.imanufactureid','=','m.imanufactureid');
					})
					->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','m.is_popular','pc.category_id','c.parent_id')
					->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')])			
					->where('po.status','=','1')
					->where('c.status','=','1');
					
					//->where('po.sku','=','11111EMPTY');
					
        
        if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $CatProdsQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $CatProdsQry->whereIn('po.product_type',['both','retailer']);
		
		if(count($ChildCatArr) > 0)			
			$CatProdsQry->whereIn('pc.category_id',$ChildCatArr);
			
			
			
			
			//$CatProdsQry->groupBy(['po.brand_id','po.gender','po.imanufactureid']);
		$CatProdsQry->groupBy(['po.variation_id']);
		
		$FilterStock = '';
		$FilterMinPrice = '';
		$FilterMaxPrice = '';
		$FilterKey = '';
		$BrandInSearch = 0;
		
		foreach($Filters as $fkey => $Filter)
		{
			if(is_array($Filter) && count($Filter) > 0)
			{
				if($fkey == 'categories'){
					$CatProdsQry->whereIn('pc.category_id',$Filter);
				}else if($fkey == 'brands'){
					$CatProdsQry->whereIn('po.imanufactureid',$Filter);
					$BrandInSearch=1;
				}else if($fkey == 'features'){
					$CatProdsQry->whereIn('po.refine_feature',$Filter);
				}else if($fkey == 'special'){
					foreach($Filter as $Special)
					{
						if($Special == 'top_seller' || $Special == 'ts')
						{
							$Flag = "TOP_SELLERS";
							/*if(!$CategoryID)
								$Flag = "TOP_SELLERS";
							else
								$CatProdsQry->where('po.top_seller','=','Yes');
							*/
						}
						if($Special == 'new_arrival' || $Special == 'na')
						{
							$Flag = "NEW_ARRIVALS";
							/*if(!$CategoryID)
								$CatProdsQry->where('po.new_arrival','=','Yes');
							else
								$Flag = "NEW_ARRIVALS";
							*/
						}
						if($Special == 'featured' || $Special == 'fe')
							$CatProdsQry->where('po.featured','=','Yes');
						if($Special == 'clearance' || $Special == 'cl')
							$CatProdsQry->where('po.clearance','=','Yes');
						if($Special == 'celebrity' || $Special == 'cp')
							$CatProdsQry->where('po.celebrity','=','Yes');
						if($Special == 'sale_price' || $Special == 'sl')
							$CatProdsQry->where('po.sale_price','>',0);
					}
				} else if($fkey == 'stock'){ 
					$FilterStock = $Filter[0];
				} else if($fkey == 'ProductSKUs'){
					$CatProdsQry->whereIn('po.sku',$Filter);		
				} else if($fkey == 'NotProductSKUs'){
					$CatProdsQry->whereNotIn('po.sku',$Filter);		
				} else {
					$CatProdsQry->whereIn('po.'.$fkey,$Filter);
				}
			} else if($fkey == 'stock'){
				$FilterStock = $Filter;
			}else if($fkey == 'minprice'){
				$FilterMinPrice = $Filter;
			}else if($fkey == 'maxprice'){
				$FilterMaxPrice = $Filter;
			}else if($fkey == 'key'){
				$FilterKey = $Filter;
			}
		}
		
		if($Flag == "TOP_SELLERS")
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere('po.is_sold_quantity','>',0);
			});
		}
		if($Flag == "NEW_ARRIVALS")
		{
			$CatProdsQry->whereNotIn('pc.category_id',['198','199','200','201']);
			
			$CatProdsQry->where(function($query){
				$query->where('po.new_arrival','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});
		}
		
		if($Flag == 'CategoryPage')
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}else if($Flag == 'DealofweekPage'){
			$CatProdsQry->join('pu_dealofweek as dw','dw.product_sku','=','po.sku');
			$CatProdsQry->join('pu_dealofweektitle as dwt','dw.did','=','dwt.did');
			$CatProdsQry->where('dw.deal_type','=','Weekly');
			$CatProdsQry->where('dw.status','=','1');
			$CatProdsQry->where('dw.start_date','<=',date('Y-m-d'))->where('dw.end_date','>=',date('Y-m-d'));
			if($FilterKey != '')
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			$CatProdsQry->orderBy('dwt.deal_rank');
			$CatProdsQry->orderBy('dw.end_date');
			$CatProdsQry->orderBy('dw.display_rank');
		}else if($Flag == 'Maxtwoday'){
			$CatProdsQry->where('po.maxtwodaydelivery','=','Yes');
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		else if($Flag == 'Promotional'){
			if($FilterKey != ''){
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			}
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}else if($Flag == 'ProductListPage' || $Flag == 'BrandPage'){
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('b.brand_name');
			$CatProdsQry->orderBy('po.cosmo_sku');
			$CatProdsQry->orderBy('po.nandansons_sku');
			$CatProdsQry->orderBy('po.pca_sku');
			$CatProdsQry->orderBy('po.display_position');
		} else if($Flag == 'ShoppingCart'){
			$CatProdsQry->join('pu_products_viewed as pv','po.sku','=','pv.sku');
			$CatProdsQry->where('pv.customer_ip','!=',$_SERVER['REMOTE_ADDR']);
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		} else if($Flag == "TOP_SELLERS"){
			$CatProdsQry->orderBy('po.is_sold_quantity','desc');
		} else if($Flag == "NEW_ARRIVALS"){
			$CatProdsQry->orderBy('po.add_datetime','desc');	
		}else{
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		$CatProdsWithoutLimit = $CatProdsQry->get();
		
		//echo "<pre>"; print_r($CatProdsWithoutLimit); exit;
		//$CatProdsWithLimit = $CatProdsQry->offset($Offset)->limit($limit)->get();
		$ArrayFilters = ['sortby' => $SortBy, 'offset' => $Offset, 'limit' => $limit];
		$SKUs = '' ;
		$CatProducts = [];
		$TotalProds = 0;
		$VariationIDs=[];
		$ProdIds=[];
		$DealData = GetDealOfWeek('',"Weekly");
		$CatProdsQry->offset($Offset)->chunk(1000, function($MyCatProdsWithoutLimit)use(&$CatProducts,&$VariationIDs,&$ProdIds,&$TotalProds,$CategoryID,$DealData,$FilterStock,$FilterMinPrice,$FilterMaxPrice,$BrandInSearch)
		{
			//$SliderCategory = $this->GetCategories($CatProdsWithoutLimit);	
			foreach($MyCatProdsWithoutLimit as $key => $CatProd)
			{
				$CatProd = $this->SetProduct($CatProd);
				
				if(is_array($FilterStock) && count($FilterStock) > 0 && $CatProd->stock == 'Out')
					continue;
				
				if($FilterMaxPrice !='')
				{	
					$FilterMaxPrice = (float)$FilterMaxPrice;
					$FilterMinPrice = (float)$FilterMinPrice;
					if((float)$CatProd->product_price < $FilterMinPrice || (float)$CatProd->product_price > $FilterMaxPrice )
						continue;
				}
				
				if($CatProd->product_price <= 0)
					continue;
				
				$TotalProds++;
				/*$SKUs.= $CatProd->sku."#";	
				$TotalProds++;*/
				$VariationIDs[]=$CatProd->variation_id;
				
				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
					$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($CatProd->image);
					$verP = filemtime($newimageVal);
					$CatProd->prod_image  = config('global.PRD_THUMB_IMG_URL') . $CatProd->image . "?ver=" . $verP;
				} else {
					$CatProd->prod_image = config('global.NO_IMAGE_THUMB');
				}
				/*if($CatProd->is_atomizer == "Yes" || $CatProd->stock == "Out")
				{
					$SizeCountArr = $this->getReferencedProducts_Counter_ListingDev($CatProd->products_id,$CatProd->variation_id,$CategoryID,[],$CatProdsWithoutLimit);
					if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'No' && $SizeCountArr[0]->is_atomizer != ''){
						$Product = $SizeCountArr[0];
					}else if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'Yes' && $SizeCountArr[0]->stock =='In'){
						$Product = $SizeCountArr[0];
					}else{
						//$CatProd->size_cnt = $SizeCountArr;
					}
				} else {
					//$CatProd->size_cnt = $this->getReferencedProducts_CounterDev($CatProd->products_id,$CatProd->variation_id,$CatProdsWithoutLimit);	
				}*/
				
				$CatProd->BrandInSearch = $BrandInSearch;
				
				$PriceRange = $this->setPriceRange($CatProd->variation_id,$MyCatProdsWithoutLimit);
				$CatProd->minPrice = $PriceRange['MinPrice'];
				$CatProd->maxPrice = $PriceRange['MaxPrice'];
				$CatProd->yousave = $PriceRange['YouSave'];
				
				if($CategoryID == '2')
					$CatProd->category_id = $CategoryID;
				
				if($CatProd->parent_id != 0)
					$ProdCat = $CatProd->parent_id;
				else
					$ProdCat = $CatProd->category_id;
				/*
				$ProdCatDetails = $SliderCategory[$ProdCat];
				$category_url = remove_special_chars($ProdCatDetails->category_name).'/';
				$product_name = remove_special_chars($CatProd->product_name);
				$CatProd->product_url = config('global.SITE_URL').$ProdURL.$product_name."/pid/".$CatProd->products_id."/".$ProdCat;
				*/
				$CatProd->product_url = SetProductURL($CatProd->products_id,$CatProd->product_name,$CatProd->category_id);
				
				if ($CatProd->gender == 'M'){
					$CatProd->gender = "sv-men";
					$CatProd->gendernames = "Men";
					$for_gender = ' for Men';
				} elseif ($CatProd->gender == 'W'){
					$CatProd->gender = "sv-women";
					$CatProd->gendernames = "Women";
					$for_gender = ' for Women';
				} elseif ($CatProd->gender == 'K'){
					$CatProd->gender = "sv-kids";
					$CatProd->gendernames = "Kids";
					$for_gender = ' for Kids';
				} elseif ($CatProd->gender == 'U'){
					$CatProd->gender = "sv-unisex";
					$CatProd->gendernames = "Unisex";
					$for_gender = ' Unisex';
				} else{
					$CatProd->gender = "";
					$CatProd->gendernames = "";
					$for_gender = '';
				}
				
				if($CatProd->vmanufacture != ''){
					$m_name = strtolower($CatProd->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$CatProd->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$CatProd->imanufactureid;
				}
				
				if($CatProd->brand_name != '' && $CatProd->vmanufacture != ''){
					$m_name = strtolower($CatProd->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$CatProd->referencedName = '<a href="' . $CatProd->product_url . '"><strong>' . $CatProd->brand_name . '</strong></a> by <a href='.$CatProd->vmanufacture_link.'><strong><br>'.$CatProd->vmanufacture.'</strong></a>'.$for_gender;
				}
				
				if(strlen($CatProd->product_name) > 45){
					$CatProd->product_name = substr($CatProd->product_name, 0, (45 - strlen($CatProd->product_name))). "..";
				} else {
					$CatProd->product_name = $CatProd->product_name;
				}

				if($CatProd->vmanufacture == '' || $CatProd->brand_name == ''){
					$CatProd->referencedName = '<a href="' . $CatProd->product_url . '">' . $CatProd->product_name . '</a>';
				}
				
				if($CatProd->retail_price != '' && $CatProd->retail_price != '0.00' && isset($CatProd->product_price)){
					$yousave = ($CatProd->retail_price - $CatProd->product_price) / $CatProd->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$yousaveprice = $CatProd->retail_price - $CatProd->product_price;
				}else{
					$yousave = 0;
					$yousaveprice = 0;
				}
				
				$CatProd->yousave = $yousave;
				$CatProd->maxyousave = (($CatProd->yousave>0)?number_format($CatProd->yousave, 0):0);
				$CatProd->yousaveprice = $yousaveprice;
				$CatProd->autoid = $key;
				
				$CatProd->sale_item = '0';
				if($CatProd->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
				{
					$CatProd->sale_item = '1';
				}
				
				if(isset($DealData[$CatProd->sku]))
				{
					//echo "<pre>"; print_r($DealData); exit;
					$CatProd->deal_price = $DealData[$CatProd->sku]['deal_price'];
					$CatProd->yousave = $DealData[$CatProd->sku]['yousave'];
					$CatProd->yousaveprice = $DealData[$CatProd->sku]['yousaveprice'];
				}
				$CatProd->short_description = strip_tags($CatProd->short_description);
				$CatProd->avg_rate = 0;
				$total_review = $CatProd->TotalReview;
				if($total_review > 0)
					$CatProd->avg_rate = GetProductAverageRating($CatProd->TotalReview,$CatProd->TotalRate);
				
				$ProdIds[]=$CatProd->products_id;
				
				$CatProducts[] = $CatProd;
				
				
			}
			//$Products = $this->GetSliderProducts($SKUs,'','Category',$CategoryID,$ArrayFilters);		
		});
		$TotalProducts = $TotalProds;
		$AllFilters = $this->GetFilters($CatProducts,$Filters,$Flag);
		if(count($CatProducts)>0 && isset($ArrayFilters['limit']) && $ArrayFilters['limit'] != '')
		{
			if(count($CatProducts) > $ArrayFilters['offset'])
				$CatProducts = array_slice($CatProducts,$ArrayFilters['offset'],$ArrayFilters['limit']);
		}
	
		if(count($CatProducts)>0 && isset($ArrayFilters['sortby']) && $ArrayFilters['sortby'] != '')
		{
			if($ArrayFilters['sortby'] == 'priceHL'){
				usort($CatProducts,function($first,$second){
					return $first->product_price < $second->product_price ? 1 : -1; 
				});
			}
			if($ArrayFilters['sortby'] == 'priceLH'){
				usort($CatProducts,function($first,$second){
					return $first->product_price > $second->product_price ? 1 : -1; 
				});
			}
			if($ArrayFilters['sortby'] == 'priceAZ'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($first->brand_name),strtolower($second->brand_name)); 
				});
			}
			if($ArrayFilters['sortby'] == 'priceZA'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($second->brand_name),strtolower($first->brand_name)); 
				});
			}
		} else {
			//usort($CatProducts,function($first,$second){
				//return $first->WebsiteStock > $second->WebsiteStock ? 1 : -1; 
			//});
		}
		
		$CatProducts = $this->CountOptions($VariationIDs,$CatProducts,$ChildCatArr,$Flag);
		$ProductsDetails = ['Products' => $CatProducts,'TotalProducts' => $TotalProducts, 'LeftFilters' => $AllFilters];
		
		
		return $ProductsDetails;
	}
	
    public function GetProductsForMainCategory($Flag,$CategoryID,$limit=12,$Filters=[])
	{
		$FilterCategories = [];
		$Offset = 0;
		$SortBy = "";
		$CatProdsQry = [];
		$ChildCatArr = [];
		if(count($Filters) > 0){
			foreach($Filters as $fkey => $Filter)
			{
				if($fkey == 'categories' && count($Filters) > 0){
					$ChildCatArr = $Filters['categories'];
				} 
			}
		} 
		if(count($ChildCatArr) == 0 && $CategoryID != '') {
			//$ChildCats = $this->GetChildCategories($CategoryID);
			$ChildCats = GetMainCatsTree([$CategoryID]);
			if(count($ChildCats['CatList']) > 0)
				$ChildCatArr = array_column($ChildCats['CatList'],'category_id');
			else
				$ChildCatArr = [$CategoryID];
		}
		
		if(isset($Filters['page']) && $Filters['page'] > 1){
				$Offset = ($Filters['page']-1) * $limit;
		}
		
		$SortBy = isset($Filters['sortby'])?$Filters['sortby']:'';
	
		$CatProdsQry = DB::table('pu_products as po')
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_brand as b','b.brand_id','=','po.brand_id')
					->join('pu_manufacture as m',function($join){
						$join->on('po.imanufactureid','=','m.imanufactureid');
						$join->on('b.imanufactureid','=','m.imanufactureid');
					})
					->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','m.is_popular','pc.category_id','c.parent_id')
					->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','=','po.sku')])			
					->where('po.status','=','1')
					->where('c.status','=','1');
					
					//->where('po.sku','=','11111EMPTY');
					
        
        if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $CatProdsQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $CatProdsQry->whereIn('po.product_type',['both','retailer']);
		
		if(count($ChildCatArr) > 0)			
			$CatProdsQry->whereIn('pc.category_id',$ChildCatArr);
			
		$CatProdsQry->groupBy(['po.variation_id']);
		
		$FilterStock = '';
		$FilterMinPrice = '';
		$FilterMaxPrice = '';
		$FilterKey = '';
		$BrandInSearch = 0;
		
		foreach($Filters as $fkey => $Filter)
		{
			if(is_array($Filter) && count($Filter) > 0)
			{
				if($fkey == 'categories'){
					$CatProdsQry->whereIn('pc.category_id',$Filter);
				}else if($fkey == 'brands'){
					$CatProdsQry->whereIn('po.imanufactureid',$Filter);
					$BrandInSearch=1;
				}else if($fkey == 'features'){
					$CatProdsQry->whereIn('po.refine_feature',$Filter);
				}else if($fkey == 'special'){
					foreach($Filter as $Special)
					{
						if($Special == 'top_seller' || $Special == 'ts')
						{
							$Flag = "TOP_SELLERS";
						}
						if($Special == 'new_arrival' || $Special == 'na')
						{
							$Flag = "NEW_ARRIVALS";
						}
						if($Special == 'featured' || $Special == 'fe')
							$CatProdsQry->where('po.featured','=','Yes');
						if($Special == 'clearance' || $Special == 'cl')
							$CatProdsQry->where('po.clearance','=','Yes');
						if($Special == 'celebrity' || $Special == 'cp')
							$CatProdsQry->where('po.celebrity','=','Yes');
						if($Special == 'sale_price' || $Special == 'sl')
							$CatProdsQry->where('po.sale_price','>',0);
					}
				} else if($fkey == 'stock'){ 
					$FilterStock = $Filter[0];
				} else if($fkey == 'ProductSKUs'){
					$CatProdsQry->whereIn('po.sku',$Filter);		
				} else if($fkey == 'NotProductSKUs'){
					$CatProdsQry->whereNotIn('po.sku',$Filter);		
				} else {
					$CatProdsQry->whereIn('po.'.$fkey,$Filter);
				}
			} else if($fkey == 'stock'){
				$FilterStock = $Filter;
			}else if($fkey == 'minprice'){
				$FilterMinPrice = $Filter;
			}else if($fkey == 'maxprice'){
				$FilterMaxPrice = $Filter;
			}else if($fkey == 'key'){
				$FilterKey = $Filter;
			}
		}
		
		if($Flag == "TOP_SELLERS")
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere('po.is_sold_quantity','>',0);
			});
		}
		if($Flag == "NEW_ARRIVALS")
		{
			$CatProdsQry->whereNotIn('pc.category_id',['198','199','200','201']);
			
			$CatProdsQry->where(function($query){
				$query->where('po.new_arrival','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});
		}
		
		if($Flag == 'CategoryPage')
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}else if($Flag == 'DealofweekPage'){
			$CatProdsQry->join('pu_dealofweek as dw','dw.product_sku','=','po.sku');
			$CatProdsQry->join('pu_dealofweektitle as dwt','dw.did','=','dwt.did');
			$CatProdsQry->where('dw.deal_type','=','Weekly');
			$CatProdsQry->where('dw.status','=','1');
			$CatProdsQry->where('dw.start_date','<=',date('Y-m-d'))->where('dw.end_date','>=',date('Y-m-d'));
			if($FilterKey != '')
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			$CatProdsQry->orderBy('dwt.deal_rank');
			$CatProdsQry->orderBy('dw.end_date');
			$CatProdsQry->orderBy('dw.display_rank');
		}else if($Flag == 'Maxtwoday'){
			$CatProdsQry->where('po.maxtwodaydelivery','=','Yes');
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		else if($Flag == 'Promotional'){
			if($FilterKey != ''){
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			}
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}else if($Flag == 'ProductListPage' || $Flag == 'BrandPage'){
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('b.brand_name');
			$CatProdsQry->orderBy('po.cosmo_sku');
			$CatProdsQry->orderBy('po.nandansons_sku');
			$CatProdsQry->orderBy('po.pca_sku');
			$CatProdsQry->orderBy('po.display_position');
		} else if($Flag == 'ShoppingCart'){
			$CatProdsQry->join('pu_products_viewed as pv','po.sku','=','pv.sku');
			$CatProdsQry->where('pv.customer_ip','!=',$_SERVER['REMOTE_ADDR']);
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		} else if($Flag == "TOP_SELLERS"){
			$CatProdsQry->orderBy('po.is_sold_quantity','desc');
		} else if($Flag == "NEW_ARRIVALS"){
			$CatProdsQry->orderBy('po.add_datetime','desc');	
		}else{
			$CatProdsQry->orderBy('po.current_stock','desc');
			$CatProdsQry->orderBy('po.cosmo_current_stock','desc');
			$CatProdsQry->orderBy('po.pca_current_stock','desc');
			$CatProdsQry->orderBy('po.nandansons_current_stock','desc');
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		
		//echo "<pre>"; print_r($CatProdsWithoutLimit); exit;
		//$CatProdsWithLimit = $CatProdsQry->offset($Offset)->limit($limit)->get();
		$ArrayFilters = ['sortby' => $SortBy, 'offset' => $Offset, 'limit' => $limit];
		$SKUs = '' ;
		$CatProducts = [];
		$TotalProds = 0;
		$VariationIDs=[];
		$ProdIds=[];
		$DealData = GetDealOfWeek('',"Weekly");
        
        $MyCatProdWithoutLimit = $CatProdsQry->get();
        
        $MyCatProdWithLimit = $CatProdsQry->offset($Offset)->limit(12)->get();
        
        foreach($MyCatProdWithLimit as $key => $CatProd)
        {
            $CatProd = $this->SetProduct($CatProd);
            if(is_array($FilterStock) && count($FilterStock) > 0 && $CatProd->stock == 'Out')
                continue;

            if($FilterMaxPrice !='')
            {	
                $FilterMaxPrice = (float)$FilterMaxPrice;
                $FilterMinPrice = (float)$FilterMinPrice;
                if((float)$CatProd->product_price < $FilterMinPrice || (float)$CatProd->product_price > $FilterMaxPrice )
                    continue;
            }

            if($CatProd->product_price <= 0)
                continue;

            $TotalProds++;
            /*$SKUs.= $CatProd->sku."#";	
            $TotalProds++;*/
            $VariationIDs[]=$CatProd->variation_id;

            if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
                $newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($CatProd->image);
                $verP = filemtime($newimageVal);
                $CatProd->prod_image  = config('global.PRD_THUMB_IMG_URL') . $CatProd->image . "?ver=" . $verP;
            } else {
                $CatProd->prod_image = config('global.NO_IMAGE_THUMB');
            }
            
            $CatProd->BrandInSearch = $BrandInSearch;

            $PriceRange = $this->setPriceRange($CatProd->variation_id,$MyCatProdWithLimit);
            $CatProd->minPrice = $PriceRange['MinPrice'];
            $CatProd->maxPrice = $PriceRange['MaxPrice'];
            $CatProd->yousave = $PriceRange['YouSave'];

            if($CategoryID == '2')
                $CatProd->category_id = $CategoryID;

            if($CatProd->parent_id != 0)
                $ProdCat = $CatProd->parent_id;
            else
                $ProdCat = $CatProd->category_id;
            
            $CatProd->product_url = SetProductURL($CatProd->products_id,$CatProd->product_name,$CatProd->category_id);

            if ($CatProd->gender == 'M'){
                $CatProd->gender = "sv-men";
                $CatProd->gendernames = "Men";
                $for_gender = ' for Men';
            } elseif ($CatProd->gender == 'W'){
                $CatProd->gender = "sv-women";
                $CatProd->gendernames = "Women";
                $for_gender = ' for Women';
            } elseif ($CatProd->gender == 'K'){
                $CatProd->gender = "sv-kids";
                $CatProd->gendernames = "Kids";
                $for_gender = ' for Kids';
            } elseif ($CatProd->gender == 'U'){
                $CatProd->gender = "sv-unisex";
                $CatProd->gendernames = "Unisex";
                $for_gender = ' Unisex';
            } else{
                $CatProd->gender = "";
                $CatProd->gendernames = "";
                $for_gender = '';
            }

            if($CatProd->vmanufacture != ''){
                $m_name = strtolower($CatProd->vmanufacture);
                $m_name = str_replace("#", "", $m_name);
                $m_name = str_replace("&", "", $m_name);
                $m_name = str_replace("  ", " ", trim($m_name));
                $m_name = str_replace("  ", " ", trim($m_name));
                $m_name = str_replace(" ", "-", $m_name);
                $CatProd->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$CatProd->imanufactureid;
            }

            if($CatProd->brand_name != '' && $CatProd->vmanufacture != ''){
                $m_name = strtolower($CatProd->vmanufacture);
                $m_name = str_replace("#", "", $m_name);
                $m_name = str_replace("&", "", $m_name);
                $m_name = str_replace("  ", " ", trim($m_name));
                $m_name = str_replace("  ", " ", trim($m_name));
                $m_name = str_replace(" ", "-", $m_name);
                $CatProd->referencedName = '<a href="' . $CatProd->product_url . '"><strong>' . $CatProd->brand_name . '</strong></a> by <a href='.$CatProd->vmanufacture_link.'><strong><br>'.$CatProd->vmanufacture.'</strong></a>'.$for_gender;
            }

            if(strlen($CatProd->product_name) > 45){
                $CatProd->product_name = substr($CatProd->product_name, 0, (45 - strlen($CatProd->product_name))). "..";
            } else {
                $CatProd->product_name = $CatProd->product_name;
            }

            if($CatProd->vmanufacture == '' || $CatProd->brand_name == ''){
                $CatProd->referencedName = '<a href="' . $CatProd->product_url . '">' . $CatProd->product_name . '</a>';
            }

            if($CatProd->retail_price != '' && $CatProd->retail_price != '0.00' && isset($CatProd->product_price)){
                $yousave = ($CatProd->retail_price - $CatProd->product_price) / $CatProd->retail_price;
                $yousave = $yousave * 100;
                $yousave = number_format($yousave, 0);
                $yousaveprice = $CatProd->retail_price - $CatProd->product_price;
            }else{
                $yousave = 0;
                $yousaveprice = 0;
            }

            $CatProd->yousave = $yousave;
            $CatProd->maxyousave = (($CatProd->yousave>0)?number_format($CatProd->yousave, 0):0);
            $CatProd->yousaveprice = $yousaveprice;
            $CatProd->autoid = $key;

            $CatProd->sale_item = '0';
            if($CatProd->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
            {
                $CatProd->sale_item = '1';
            }

            if(isset($DealData[$CatProd->sku]))
            {
                $CatProd->deal_price = $DealData[$CatProd->sku]['deal_price'];
                $CatProd->yousave = $DealData[$CatProd->sku]['yousave'];
                $CatProd->yousaveprice = $DealData[$CatProd->sku]['yousaveprice'];
            }
            $CatProd->short_description = strip_tags($CatProd->short_description);
            $CatProd->avg_rate = 0;
            $total_review = $CatProd->TotalReview;
            if($total_review > 0)
                $CatProd->avg_rate = GetProductAverageRating($CatProd->TotalReview,$CatProd->TotalRate);

            $ProdIds[]=$CatProd->products_id;

            $CatProducts[] = $CatProd;
        }
			//$Products = $this->GetSliderProducts($SKUs,'','Category',$CategoryID,$ArrayFilters);		
		
		//$TotalProducts = $TotalProds;
        $TotalProducts = $MyCatProdWithoutLimit->count();
		$AllFilters = $this->GetFilters($CatProducts,$Filters,$Flag);
		if(count($CatProducts)>0 && isset($ArrayFilters['limit']) && $ArrayFilters['limit'] != '')
		{
			if(count($CatProducts) > $ArrayFilters['offset'])
				$CatProducts = array_slice($CatProducts,$ArrayFilters['offset'],$ArrayFilters['limit']);
		}
	
		if(count($CatProducts)>0 && isset($ArrayFilters['sortby']) && $ArrayFilters['sortby'] != '')
		{
			if($ArrayFilters['sortby'] == 'priceHL'){
				usort($CatProducts,function($first,$second){
					return $first->product_price < $second->product_price ? 1 : -1; 
				});
			}
			if($ArrayFilters['sortby'] == 'priceLH'){
				usort($CatProducts,function($first,$second){
					return $first->product_price > $second->product_price ? 1 : -1; 
				});
			}
			if($ArrayFilters['sortby'] == 'priceAZ'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($first->brand_name),strtolower($second->brand_name)); 
				});
			}
			if($ArrayFilters['sortby'] == 'priceZA'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($second->brand_name),strtolower($first->brand_name)); 
				});
			}
		} else {
			//usort($CatProducts,function($first,$second){
				//return $first->WebsiteStock > $second->WebsiteStock ? 1 : -1; 
			//});
		}
		
		$CatProducts = $this->CountOptionsNew($VariationIDs,$CatProducts,$ChildCatArr,$Flag);
		$ProductsDetails = ['Products' => $CatProducts,'TotalProducts' => $TotalProducts, 'LeftFilters' => $AllFilters];
		
		
		return $ProductsDetails;
	}
	public function GetFilters($Products,$SetFilters=[],$Flag='')
	{
		$Filters=[];
		$ListingMenu = $this->ListingMenu();
		$f=0;
		/*$CategoryList = $this->UniqueKey($Products,'category_id','category_name');
		if(count($CategoryList) > 0 )
		{
			$Filters[$f]['Categories']['Attr'] = ['title' => 'Category', 'id' => 'categories', 'filterval' => 'key'];
			$Filters[$f]['Categories']['Data'] = $CategoryList;
			$Filters[$f]['Categories']['Selected'] = isset($SetFilters['categories'])?$SetFilters['categories']:[];
			$Filters[$f]['Categories']['Order'] = 0;
		}
		$f++;*/
		
		$BrandList = $this->UniqueKey($Products,'imanufactureid','vmanufacture',$Flag);
		asort($BrandList);
		
		if(count($BrandList) > 0 )
		{
			$Filters[$f]['Brands']['Attr'] = ['title' => 'Brands', 'id' => 'brands', 'filterval' => 'key'];
			$Filters[$f]['Brands']['Data'] = $BrandList;
			$Filters[$f]['Brands']['Selected'] = isset($SetFilters['brands'])?$SetFilters['brands']:[];
			$Filters[$f]['Brands']['Order'] = $f;
		}
		$f++;
		
		if(count($ListingMenu) > 0)
		{
			foreach($ListingMenu as $Menu)
			{
				$List = $this->UniqueKey($Products,$Menu->table_fieldName,$Menu->table_fieldName);
				if(count($List) > 0 )
				{
					if($Menu->table_fieldName == 'size')
					{
						$SizeType = [];
						$NewSizes = [];
						foreach($List as $skey => $size)
						{
							$ExpSize = explode(" ",$size);
							if(strstr(strtolower($size),'oz') ||  isset($ExpSize[1]) && (strtolower($ExpSize[1]) == 'oz' || strtolower($ExpSize[1]) == 'oz.'))
							{
								$SizeType['oz'][$skey] = $size;
							}else if(strstr(strtolower($size),'ml') ||  isset($ExpSize[1]) && (strtolower($ExpSize[1]) == 'ml' || strtolower($ExpSize[1]) == 'ml.'))
							{
								$SizeType['ml'][$skey] = $size;
							}else if(strstr(strtolower($size),'mini'))
							{
								$SizeType['mini'][$skey] = $size;
							}else if(strstr(strtolower($size),'set'))
							{
								$SizeType['set'][$skey] = $size;
							}else {
								$SizeType['oth'][$skey] = $size;
							}
						}
						if(isset($SizeType['mini']))
						{
							$NewSizes = array_merge($NewSizes,$SizeType['mini']);
						}
						if(isset($SizeType['set']))
						{
							$NewSizes = array_merge($NewSizes,$SizeType['set']);
						}
						
						if(isset($SizeType['ml']))
						{
							$NewSizes = array_merge($NewSizes,$this->SetArray($SizeType['ml'],'ml'));
						}
						if(isset($SizeType['oz']))
						{
							$NewSizes = array_merge($NewSizes,$this->SetArray($SizeType['oz'],'oz'));
						}
						if(isset($SizeType['oth']))
						{
							$SortedSize = $this->SetArray($SizeType['oth'],'oth');
							$NewSizes = $NewSizes + $SortedSize;
							//$NewSizes = array_merge($NewSizes,$this->SetArray($SizeType['oth'],'oth'));
						}
						$List = $NewSizes;
						//asort($List);
					}else{	
						asort($List);
					}
					$MenuName = str_replace(" ","",$Menu->menuname);
					$Filters[$f][$MenuName]['Attr'] = ['title' => $Menu->menuname, 'id' => $Menu->table_fieldName];
					$Filters[$f][$MenuName]['Data'] = $List;
					$Filters[$f][$MenuName]['Selected'] = isset($SetFilters[$Menu->table_fieldName])?$SetFilters[$Menu->table_fieldName]:[];
					$f++;
				}	
			}
		}
		
		$FeatureList = $this->UniqueKey($Products,'refine_feature','refine_feature');
		asort($FeatureList);

		if(count($FeatureList) > 0 )
		{
			$Filters[$f]['Features']['Attr'] = ['title' => 'Features', 'id' => 'features', 'filterval' => 'key'];
			$Filters[$f]['Features']['Data'] = $FeatureList;
			$Filters[$f]['Features']['Selected'] = isset($SetFilters['features'])?$SetFilters['features']:[];
			$Filters[$f]['Features']['Order'] = $f;
		}
		$f++;
		
		$Filters[$f]['Avability']['Attr'] = ['title' => 'By Avability', 'id' => 'stock', 'filterval' => 'key' ];
		$Filters[$f]['Avability']['Data'] = ['In' => 'In Stock'];
		$Filters[$f]['Avability']['Selected'] = isset($SetFilters['stock'])?$SetFilters['stock']:[];
		
		$f++;
		$SpecialFilter = [];
		if(isset($SetFilters['special']))
		{
			foreach($SetFilters['special'] as $SFilter)
			{
				if($SFilter == 'ts')
					$SpecialFilter[] = 'top_seller';
				if($SFilter == 'na')
					$SpecialFilter[] = 'new_arrival';
				if($SFilter == 'fe')
					$SpecialFilter[] = 'featured';
				if($SFilter == 'cl')
					$SpecialFilter[] = 'clearance';
				if($SFilter == 'cp')
					$SpecialFilter[] = 'celebrity';
				if($SFilter == 'sl')
					$SpecialFilter[] = 'sale_price';
			}
		}
		$Filters[$f]['Special']['Attr'] = ['title' => 'By Special', 'id' => 'special', 'filterval' => 'key' ];
		$Filters[$f]['Special']['Data'] = [
							'top_seller' => 'Top Seller', 
							'new_arrival' => 'New Arrival', 
							'featured' => 'Featured', 
							'clearance' => 'Clearance', 
							'celebrity' => 'Celebrity Perfume', 
							'sale_price' => 'Sale'];
		$Filters[$f]['Special']['Selected'] = $SpecialFilter;
		return $Filters;
	}
	
	public function SetArray($SizeArray=[],$sizekey)
	{
		$NewSizeArray = [];
		$SizeSortArray = [];
		foreach($SizeArray as $skey => $svalue)
		{
			$ExpSize = explode($sizekey,strtolower($svalue));
			array_push($NewSizeArray,['key' => $svalue, 'val' => trim($ExpSize[0])]);
		}
		
		if(count($NewSizeArray) > 0)
		{
			usort($NewSizeArray, function($a, $b) {
				return $a['val'] > $b['val'];
			});
			foreach($NewSizeArray as $nkey => $nval)
			{
				$SizeSortArray[(string)$nval['key']] = $nval['key'];
			}
		}	
		//dd($NewSizeArray);
		return $SizeSortArray;
	}
	
	public function UniqueKey($Array, $key, $column,$flag='') {
		$ItemsData = [];
		foreach ($Array as $item) {
			if(isset($item->$column) && $item->$column != ''){
				if($key == 'imanufactureid')
				{
					
					if(isset($flag) && isset($item->is_popular) && $item->is_popular == 'Yes' && $flag=='ProductListPage')
					{
						
						$ItemsData[ucwords($item->$key)] = ucwords($item->$column);
					}
					else if($flag!='ProductListPage')
					{
						$ItemsData[ucwords($item->$key)] = ucwords($item->$column);
					}
				} else { 
					$ItemsData[ucwords($item->$key)] = ucwords($item->$column);
				}
			}
		}
		$ItemsData = array_unique($ItemsData);
		return $ItemsData;
	}
	
	public function getChildCatIdStr($category_id, $string_catID='',$type='') 
	{	
		$FindCat = Category::where('parent_id','=',$category_id)->where('status','=','1')->get();
		if($FindCat && $FindCat->count() > 0)
		{
			foreach($FindCat as $FindCatNew){
				$temp_id = $FindCatNew->category_id;
				if($type == ''){
					$string_catID.=$temp_id.",";
					$string_catID = $this->GetChildCats($temp_id,$string_catID,$type);
				} else {
					$string_catID[]= ['category_id' => $FindCatNew->category_id,'category_name' => $FindCatNew->category_name];
					$string_catID=$this->GetChildCats($temp_id,$string_catID,$type);
				}
			}
		} 
		return $string_catID;
	}
	
	public function GetChildCats($ParentID,$string_catID,$type)
	{
		$ChildCat = Category::where('parent_id','=',$ParentID)->where('status','=','1')->get();
		if($ChildCat && $ChildCat->count() > 0)
		{	
			foreach($ChildCat as $Child)
			{
				if($type == ''){
					$string_catID.= $Child->category_id.",";
					$this->GetChildCats($Child->category_id,$string_catID,$type);
				}else{
					$string_catID[]= ['category_id' => $Child->category_id,'category_name' => $Child->category_name];
					$this->GetChildCats($Child->category_id,$string_catID,$type);
				}
			}
		}
		return $string_catID;
	}
	public function ParentChild($CategoryID,$Bredcrum=[])
	{
		if($CategoryID != 0)
		{
			$CatDetails = Category::find($CategoryID);
			$Bredcrum[]=[
				'url' => config('global.SITE_URL').remove_special_chars(trim($CatDetails->category_name)).'/cid/'.$CatDetails->category_id,
				'name' => $CatDetails->category_name,
			];
			$this->ParentChild($CatDetails->parent_id,$Bredcrum);
		}		
		return $Bredcrum;
	}
	public function GetCatTree($CatArray)
	{
		//$Categories = Category::where('parent_id','=','0')->where('status','=','1')->with('children')->get();
		$Categories = Category::select('category_id','category_name','parent_id')->where('status','=','1')->orderBy('display_position')->get();
		$SubCatsTree=[];$key=0;
		$AllCats = $this->MyCatTree($Categories);
		foreach($AllCats as $MainCat)
		{
			if(in_array($MainCat->category_id,$CatArray) || $CatArray[0] == 0)
			{
				$SubCatsTree[$key][]=['category_id' => $MainCat->category_id, 'category_name' => $MainCat->category_name, 'Level' => 0];
			
				if(isset($MainCat->childs) && count($MainCat->childs) > 0 ){
					foreach($MainCat->childs as $SubLevel1){
						$SubAllCats = isset($SubLevel1->childs)?$SubLevel1->childs:[];
						$SubCatsTree[$key][]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name,'hasChild' => ($SubAllCats != null && count($SubAllCats) > 0) ? 'Yes':'No', 'Level' => 1];
						$SubCats[]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name];
						if($SubAllCats){
							foreach($SubAllCats as $SubLevel2){
								$SubCatsTree[$key][]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name, 'Level' => 2];
								$SubCats[]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name];
								$key++;
							}
						}
						$key++;
					}
				}
				$key++;
			}
		}
		return $SubCatsTree;
	}
	
	public function MyCatTree($Cats)
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
	
	public function GetChildCategories($Category)
	{
		$SubCats = [];
		$SubCatsTree=[];
		if(!is_object($Category))
		{
			$Category = Category::find($Category);
		}
		$key=0;
		$SubCatsTree[$key][]=['category_id' => $Category->category_id, 'category_name' => $Category->category_name];
		$SubCats[]=['category_id' => $Category->category_id, 'category_name' => $Category->category_name];
		if($Category->children){
			foreach($Category->children as $SubLevel1){
				$SubAllCats = $SubLevel1->children;
				$SubCatsTree[$key][]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name,'hasChild' => ($SubAllCats != null && count($SubAllCats) > 0) ? 'Yes':'No'];
				$SubCats[]=['category_id' => $SubLevel1->category_id, 'category_name' => $SubLevel1->category_name];
				if($SubAllCats){
					foreach($SubLevel1->children as $SubLevel2){
						$SubCatsTree[$key][]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name];
						$SubCats[]=['category_id' => $SubLevel2->category_id, 'category_name' => $SubLevel2->category_name];
						$key++;
					}
				}
				$key++;
			}
		}
		return ['CatList' => $SubCats, 'CatTree' => $SubCatsTree];
	} 
	public function SetParentID($Category)
	{
		$ParentID = "";
		if($Category->parent != null && $Category->parent->parent != null){
			$ParentID =  $Category->parent->parent->category_id;
		}elseif($Category->parent != null){
			$ParentID =  $Category->parent->category_id;
		}else{
			$ParentID =  $Category->category_id;
		}
		return $ParentID;
	}
	public function Bredcrum($RequestParams)
	{
		$i=0;
		$ExpMyCat = [];
		if($RequestParams->category_id && $RequestParams->category_id != '')
		{
			$ExpMyCat = explode(',',$RequestParams->category_id);
		}
		if($RequestParams->category_id && $RequestParams->category_id != '' && count($ExpMyCat) == 1)
		{			
			$CatDetails = config('CATEGORY_INFO');
			$BredcrumInfo = $CatDetails['CatForProd'][$RequestParams->category_id]['bredcrum'];
			foreach($BredcrumInfo as $Binfo)
			{
				$Bredcrum[]=$Binfo;
				if($Binfo['id'] == $RequestParams->category_id)
					break;
			}	
		} else if(isset($RequestParams->keyword) && $RequestParams->keyword != ''){
			$Bredcrum[$i]['title'] = 'Home';
			$Bredcrum[$i]['link'] = config('global.SITE_URL');
			$i++;
			$Bredcrum[$i]['title'] = ucwords($RequestParams->keyword);
			$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/key-'.$RequestParams->keyword.'/view';
		} else {
			$Bredcrum[$i]['title'] = 'Home';
			$Bredcrum[$i]['link'] = config('global.SITE_URL');
		}
		$i=count($Bredcrum)-1;
		/*
		$Bredcrum[$i]['title'] = 'Home';
		$Bredcrum[$i]['link'] = config('global.SITE_URL');
		
		if($RequestParams->category_id && $RequestParams->category_id != '')
		{
			$CatDetails = Category::find($RequestParams->category_id);
			if($CatDetails && $CatDetails->count() > 0)
			{
				$CatString = '';
				$CatLink = '';
				if($CatDetails->parent != null && $CatDetails->parent->parent != null)
				{
					$MainCat = $CatDetails->parent->parent;
					$CatLink = config('global.SITE_URL').remove_special_chars(trim($MainCat->category_name)).'/cid/'.$CatDetails->category_id;
					$CatString.= ucwords($MainCat->category_name);
				}
				if($CatDetails->parent != null)
				{
					$SubCat = $CatDetails->parent;
					$CatLink = config('global.SITE_URL').remove_special_chars(trim($SubCat->category_name)).'/cid/'.$CatDetails->category_id;
					if($CatString !='')
						$CatString.=' - '.ucwords($SubCat->category_name);
					else
						$CatString.=ucwords($SubCat->category_name);
				}
				if($CatString != '')
					$CatString.=' - '.$CatDetails->category_name;
				$i++;
				$Bredcrum[$i]['title'] = $CatString;
				$Bredcrum[$i]['link'] = $CatLink;
			}
		}*/
		
		$OtherParams = ["new-arrivals"];
		$OtherCat = "";
		/*if(isset($RequestParams->category_name) && in_array($RequestParams->category_name,$OtherParams))
		{
			$i++;
			$OtherCat = str_replace("-"," ",$RequestParams->category_name);
			$Bredcrum[$i]['title'] = ucwords($OtherCat);
			$Bredcrum[$i]['link'] = '';
			$OtherCat = ucwords($OtherCat);
		}*/
	
		$NewParams = [];
		if($RequestParams->filters != '')
		{
			$Params = explode("/",$RequestParams->filters);
			foreach($Params as $pkey => $Param)
			{
				$ExpParam = explode('-',$Param);
				if(count($ExpParam)>1)
					$NewParams[$ExpParam[0]] = $ExpParam[1];
			}
		}
		
		foreach($NewParams as $fkey => $FParam)
		{
			if($fkey == 'cid')
			{
				$ExpCats = explode(",",$FParam);
				for($k=0;$k<count($ExpCats);$k++)
				{
					if(isset($RequestParams->category_id) && $ExpCats[$k] == $RequestParams->category_id)
						continue;
					$i++;
					$CatDetails = Category::find($ExpCats[$k]);
					if($CatDetails && $CatDetails->count() > 0)
					{
						$CatString = '';
						$CatLink = '';
						if($CatDetails->parent != null && $CatDetails->parent->parent != null)
						{
							$MainCat = $CatDetails->parent->parent;
							$CatLink = config('global.SITE_URL').remove_special_chars(trim($MainCat->category_name)).'/cid/'.$CatDetails->category_id;
							$CatString.= ucwords($MainCat->category_name);
						}
						if($CatDetails->parent != null)
						{
							$SubCat = $CatDetails->parent;
							$CatLink = config('global.SITE_URL').remove_special_chars(trim($SubCat->category_name)).'/cid/'.$CatDetails->category_id;
							if($CatString !='')
								$CatString.=' - '.ucwords($SubCat->category_name);
							else
								$CatString.=ucwords($SubCat->category_name);
						}
						if($CatString != '')
							$CatString.=' - '.$CatDetails->category_name;
						$Bredcrum[$i]['title'] = $CatString;
						$Bredcrum[$i]['link'] = $CatLink;
					}
				}
			}else if($fkey == 'mid')
			{
				$ExpBrands = explode(",",$FParam);
				if(count($ExpBrands) > 0)
				{
					$Manufactures = Manufacture::whereIn('imanufactureid',$ExpBrands)->get();
					if($Manufactures && $Manufactures->count() > 0)
					{
						foreach($Manufactures as $Manufacture)
						{
							$i++;
							$Bredcrum[$i]['title'] = ucwords($Manufacture->vmanufacture);
							$CatLink = config('global.SITE_URL').'p4u/';
							if(isset($RequestParams->category_id) && $RequestParams->category_id != '')
								$CatLink.='cid-'.$RequestParams->category_id.'/';
							$CatLink.='mid-'.$Manufacture->imanufactureid.'/view';
							$Bredcrum[$i]['link'] = $CatLink;
						}
					}
				}
			} else {
				$ExpParams = explode(",",$FParam);
				for($e=0;$e<count($ExpParams);$e++)
				{
					$BLink = '';
					$Title = '';
					if($RequestParams->category_id && $RequestParams->category_id != '')
						$BLink.='cid-'.$RequestParams->category_id.'/';
					if(isset($NewParams['mid']) && $NewParams['mid'] != '')
						$BLink.='mid-'.$NewParams['mid'].'/';
					if($fkey == 'special')
					{
						$ShowLink = 0;
						if($ExpParams[$e] == 'ts' || $ExpParams[$e] == 'top_seller'){
							$Title = 'Top Seller';
							$ShowLink = 1;
						}
						if($ExpParams[$e] == 'na' || $ExpParams[$e] == 'new_arrival'){
							$Title = 'New Arrival';
							$ShowLink = 1;
						}
						if($ExpParams[$e] == 'fe' || $ExpParams[$e] == 'featured'){
							$Title = 'Featured';
							$ShowLink = 1;
						}
						if($ExpParams[$e] == 'cl' || $ExpParams[$e] == 'clearance'){
							$Title = 'Clearance';
							$ShowLink = 1;
						}
						if($ExpParams[$e] == 'cp' || $ExpParams[$e] == 'celebrity'){
							$Title = 'Celebrity';
							$ShowLink = 1;
						}
						if($ExpParams[$e] == 'sl' || $ExpParams[$e] == 'sale_price'){
							$Title = 'Sale';
							$ShowLink = 1;
						}
						if($ShowLink == 1)
							$BLink.= "special-".$ExpParams[$e].'/view';	
						
						if($BLink != ''){
							$i++;
							$Bredcrum[$i]['title'] = ucwords($Title);
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					} else {
						$BLink.= $fkey."-".$ExpParams[$e].'/view';	
						if($BLink != ''){
							$i++;
							$Bredcrum[$i]['title'] = ucwords($ExpParams[$e]);
							$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/'.$BLink;
						}
					}
				}
			}
		}
		$BredLink = '';
		foreach($Bredcrum as $key => $BHead)
		{
			if((count($Bredcrum)-1) == $key )
			{
				$BredLink.="<span class='active'>".$BHead['title']."</span>";
			} else {
				$BredLink.="<a href='".$BHead['link']."'>".$BHead['title']."</a>";
			}
		}
		$BredData = ['BredLink' => $BredLink, 'PageTitle' => $Bredcrum[count($Bredcrum)-1]['title']];
		return $BredData;
	}		
	
	public function BredcrumAjax($RequestParams)
	{
		$i=0;
		$CurrFilter = $RequestParams->currFilter;
		$Rank=0;
		$ExpMyCat = [];
		if($RequestParams->category_id && $RequestParams->category_id != '')
		{
			$ExpMyCat = explode(',',$RequestParams->category_id);
		}
		if($RequestParams->category_id && $RequestParams->category_id != '' && count($ExpMyCat) == 1)
		{
			$CatDetails = config('CATEGORY_INFO');
			$BredcrumInfo = $CatDetails['CatForProd'][$RequestParams->category_id]['bredcrum'];
			foreach($BredcrumInfo as $Binfo)
			{
				$Binfo['rank'] = 0;
				if($CurrFilter==$Binfo['title'])
					$Binfo['rank'] = 1;
				$Bredcrum[]=$Binfo;
				if($Binfo['id'] == $RequestParams->category_id)
					break;
			}
		}
		else if(isset($RequestParams->keyword) && $RequestParams->keyword != ''){
			$Bredcrum[$i]['title'] = 'Home';
			$Bredcrum[$i]['link'] = config('global.SITE_URL');
			$Bredcrum[$i]['rank'] = 0;
			$Rank++;
			$i++;
			$Bredcrum[$i]['title'] = ucwords($RequestParams->keyword);
			$Bredcrum[$i]['link'] = config('global.SITE_URL').'p4u/key-'.$RequestParams->keyword.'/view';
			$Bredcrum[$i]['rank'] = 1;
			$Rank++;
			$i++;
		}
		else {
			$Bredcrum[$i]['title'] = 'Home';
			$Bredcrum[$i]['link'] = config('global.SITE_URL');
			$Bredcrum[$i]['rank'] = 0;
			$i++;
			/*$Bredcrum[$i]['title'] = 'Search';
			$Bredcrum[$i]['link'] = '';
			$Bredcrum[$i]['rank'] = 1;*/
		}
		
		$i=count($Bredcrum)-1;
		/*$OtherParams = ["new-arrivals"];
		if(isset($RequestParams['category_name']) && in_array($RequestParams['category_name'],$OtherParams))
		{
			$i++;
			$OtherCat = str_replace("-"," ",$RequestParams['category_name']);
			$Bredcrum[$i]['title'] = ucwords($OtherCat);
			$Bredcrum[$i]['link'] = '';
		}*/
		
		if(isset($RequestParams->keyword) && $RequestParams->keyword != ''){
			if(!empty($RequestParams->setArrBreadCrumb)){
				$NewParams = array_reverse(array_unique($RequestParams->setArrBreadCrumb));
			}else{
				$NewParams = array();
			}
		}else{
			$NewParams = json_decode($RequestParams->filters);
		}
		//dd($NewParams);
		
		if(isset($RequestParams->keyword) && $RequestParams->keyword != ''){
			$ExpParams = array_unique($NewParams);
			
			if(is_array($ExpParams) && count($ExpParams) > 0)
			{
				foreach($ExpParams as $extraFilter)
				{
					//dd($ExpParams);
					$i++;
					//$Bredcrum[$i]['rank'] = 2;
					
					$Bredcrum[$i]['rank'] = 1;
					if($CurrFilter == ucwords($extraFilter)){
						$Bredcrum[$i]['rank'] = $Rank;
					}
					
					$Rank++;
					
					$CatLink = '';
					
					$extraFilter1 = str_replace("doubledot",":",$extraFilter);
					$extraFilter1 = str_replace("dot",".",$extraFilter1);
					$extraFilter1 = str_replace("dash","-",$extraFilter1);
					$extraFilter1 = str_replace("andd","&",$extraFilter1);
					$extraFilter1 = str_replace("singlecomma","'",$extraFilter1);
					$extraFilter1 = str_replace("_"," ",$extraFilter1);
					
					$Bredcrum[$i]['title'] = ucwords($extraFilter1);
					$CatLink .= config('global.SITE_URL').'p4u/key-'.rawurlencode(str_replace("-"," ",$extraFilter1)).'/view';
					
					$Bredcrum[$i]['link'] = $CatLink;
				}
			}
		}
		
		foreach($NewParams as $fkey => $FParam)
		{
			if(!isset($RequestParams->keyword) && $RequestParams->keyword == ''){
			if($fkey == 'categories')
			{
				$ExpCats = $FParam;
				
				if(!isset($RequestParams->keyword) && $RequestParams->keyword == ''){
					for($k=0;$k<count($ExpCats);$k++)
					{
						if(isset($RequestParams->category_id) && $ExpCats[$k] == $RequestParams->category_id)
							continue;
						$i++;
						$CatDetails = Category::find($ExpCats[$k]);
						if($CatDetails && $CatDetails->count() > 0)
						{
							$CatString = '';
							$CatLink = '';
							$SelCat='';
							if($CatDetails->parent != null && $CatDetails->parent->parent != null)
							{
								$MainCat = $CatDetails->parent->parent;
								$CatLink = config('global.SITE_URL').remove_special_chars(trim($MainCat->category_name)).'/cid/'.$CatDetails->category_id;
								$CatString.= ucwords($MainCat->category_name);
								$SelCat = ucwords($MainCat->category_name);
							}
							if($CatDetails->parent != null)
							{
								$SubCat = $CatDetails->parent;
								$CatLink = config('global.SITE_URL').remove_special_chars(trim($SubCat->category_name)).'/cid/'.$CatDetails->category_id;
								if($CatString !='')
									$CatString.=' - '.ucwords($SubCat->category_name);
								else
									$CatString.=ucwords($SubCat->category_name);
								$SelCat = ucwords($SubCat->category_name);
							}
							if($CatString != '')
								$CatString.=' - '.$CatDetails->category_name;
							$Bredcrum[$i]['title'] = $CatString;
							$Bredcrum[$i]['link'] = $CatLink;
							$Bredcrum[$i]['rank'] = 0;
							if($CurrFilter==$SelCat)
								$Bredcrum[$i]['rank'] = 1;
						}
					}
				}
			}else if($fkey == 'brands')
			{
				$ExpBrands = $FParam;
				
				if(!isset($RequestParams->keyword) && $RequestParams->keyword == ''){
					if(count($ExpBrands) > 0)
					{
						$Manufactures = Manufacture::whereIn('imanufactureid',$ExpBrands)->get();
						if($Manufactures && $Manufactures->count() > 0)
						{
							foreach($Manufactures as $Manufacture)
							{
								$i++;
								$Bredcrum[$i]['rank'] = 0;
								if($CurrFilter == ucwords($Manufacture->vmanufacture))
									$Bredcrum[$i]['rank'] = 1;
								$Bredcrum[$i]['title'] = ucwords($Manufacture->vmanufacture);
								$CatLink = config('global.SITE_URL').'p4u/';
								if(isset($RequestParams->category_id) && $RequestParams->category_id != '')
									$CatLink.='cid-'.$RequestParams->category_id.'/';
								$CatLink.='mid-'.$Manufacture->imanufactureid.'/view';
								$Bredcrum[$i]['link'] = $CatLink;
							}
						}
					}
				}
			} else {
				$ExpParams = $FParam;
				
				if(!isset($RequestParams->keyword) && $RequestParams->keyword == ''){
					if(is_array($ExpParams) && count($ExpParams) > 0)
					{
						for($e=0;$e<count($ExpParams);$e++)
						{
							$i++;
							$Bredcrum[$i]['title'] = ucwords($ExpParams[$e]);
							$BLink = config('global.SITE_URL').'p4u/';
							if($RequestParams->category_id && $RequestParams->category_id != '')
								$BLink.='cid-'.$RequestParams->category_id.'/';
							/*if(isset($NewParams['mid']) && $NewParams['mid'] != '')
								$BLink.='mid-'.$NewParams['mid'].'/';*/
							if($fkey == 'special')
							{
								$SpecialVal = "";
								if($ExpParams[$e] == 'ts' || $ExpParams[$e] == 'top_seller'){
									$Bredcrum[$i]['title'] = 'Top Seller';
									$SpecialVal = 'ts';
								}
								if($ExpParams[$e] == 'na' || $ExpParams[$e] == 'new_arrival'){
									$Bredcrum[$i]['title'] = 'New Arrival';
									$SpecialVal = 'na';
								}
								if($ExpParams[$e] == 'fe' || $ExpParams[$e] == 'featured'){
									$Bredcrum[$i]['title'] = 'Featured';
									$SpecialVal = 'fe';
								}
								if($ExpParams[$e] == 'cl' || $ExpParams[$e] == 'clearance'){
									$Bredcrum[$i]['title'] = 'Clearance';
									$SpecialVal = 'cl';
								}
								if($ExpParams[$e] == 'cp' || $ExpParams[$e] == 'celebrity' ){
									$Bredcrum[$i]['title'] = 'Celebrity';
									$SpecialVal = 'cp';
								}
								if($ExpParams[$e] == 'sl' || $ExpParams[$e] == 'sale_price'){
									$Bredcrum[$i]['title'] = 'Sale';
									$SpecialVal = 'ts';
								}
								$BLink.= $fkey."-".$SpecialVal.'/view';	
							} else {
								$BLink.= $fkey."-".$ExpParams[$e].'/view';	
							}
							$Bredcrum[$i]['link'] = $BLink;
							$Bredcrum[$i]['rank'] = 0;
							if($CurrFilter == $Bredcrum[$i]['title'])
								$Bredcrum[$i]['rank'] = 1;
						}
					}	
				}
			}
			}
		}
		
		usort($Bredcrum, function($a, $b) {
			return $a['rank'] <=> $b['rank'];
		});
		
		$BredLink = '';
		foreach($Bredcrum as $key => $BHead)
		{
			if((count($Bredcrum)-1) == $key )
			{
				$BredLink.="<span class='active'>".$BHead['title']."</span>";
			} else {
				$BredLink.="<a href='".$BHead['link']."'>".$BHead['title']."</a>";
			}
		}
		$BredData = ['BredLink' => $BredLink, 'PageTitle' => $Bredcrum[count($Bredcrum)-1]['title']];
		return $BredData;
	}
	
	public function GetBredcrum($Category,$NoneCategory='')
	{
		$Bredcrum = '<a href="'.config('global.SITE_URL').'">Home</a>';
		if($Category != '')
		{
			if($Category->parent != null && $Category->parent->parent != null){
				$MainCat = $Category->parent->parent;
				$MainCatLink = config('global.SITE_URL').remove_special_chars(trim($MainCat->category_name)).'/cid/'.$MainCat->category_id;
				$Bredcrum.='<a href="'.$MainCatLink.'">'.$MainCat->category_name.'</a>';
			}
			if($Category->parent != null){
				$SubCat = $Category->parent;
				$SubCatLink = config('global.SITE_URL').remove_special_chars(trim($SubCat->category_name)).'/cid/'.$SubCat->category_id;
				$Bredcrum.='<a href="'.$SubCatLink.'">'.$SubCat->category_name.'</a>';
			}
			$Bredcrum.='<span class="active">'.$Category->category_name.'</span>';
		} 
		if($NoneCategory != '') {
			$Bredcrum.='<span class="active">'.$NoneCategory.'</span>';
		}			
		return $Bredcrum;
	}
	
	public function insertGiftCertificate(Request $request,$cookieegift='No')
	{
		//echo "sasa".$request['GiftImage'];exit;
		$temp_ary = array();
		 if(trim($cookieegift)=='Yes')
        {
			$giftcertificateflag = $this->checkexistgiftcertificate($request["GiftImage"]);
			if($giftcertificateflag==false)
			  return NULL;
		}
		$temp_ary['ProductID']   	= 0;

		if($request["GiftImage"] == "GiftCard1.png")
		{
			$temp_ary['SKU']         	= config('global.GIFT_CERTIFICATE_SKU');
		}
		elseif($request["GiftImage"] == "GiftCard2.png")
		{
			$temp_ary['SKU']         	= config('global.GIFT_CERTIFICATE_SKU1');
		}
		elseif($request["GiftImage"] == "GiftCard3.png")
		{
			$temp_ary['SKU']         	= config('global.GIFT_CERTIFICATE_SKU2');
		}

		$temp_ary['ProductName'] 	= 'E-Gift Card';
		$temp_ary['ProductName_description']= 'E-Gift Card';
		$temp_ary['short_description'] = '';

		if($request['dateflag'] == 'FutureDate')
		{
			$request['deliverydate'] = $request['d_start_date'];
		}
		else
		{
			$request['deliverydate'] = date("m/d/Y");

		}

		$temp_ary['ItemPrice']     	= number_format($request['gc_value'],2);

		$temp_ary['Price']       	= number_format($request['gc_value'],2);
		$temp_ary['Qty'] 		 	= 1;
		$temp_ary['TotPrice']    	= number_format($request['gc_value'],2);


		if($request["GiftImage"] == "GiftCard1.png")
		{
			$temp_ary['Image']			= "<img src='".config('global.GC_IMAGE_URL')."' border='0' width='125' />";
			$temp_ary['image_forpopup'] = "<img src='".config('global.GC_IMAGE_URL')."' border='0' width='75' />";
			$temp_ary['Billing_Image']  = "<img src='".config('global.GC_IMAGE_URL')."' border='0' width='195'/>";
		}
		elseif($request["GiftImage"] == "GiftCard2.png")
		{
			$temp_ary['Image']			= "<img src='".config('global.GC_IMAGE_URL1')."' border='0' width='125' />";
			$temp_ary['image_forpopup'] = "<img src='".config('global.GC_IMAGE_URL1')."' border='0' width='75' />";
			$temp_ary['Billing_Image']  = "<img src='".config('global.GC_IMAGE_URL1')."' border='0' width='195'/>";
		}
		elseif($request["GiftImage"] == "GiftCard3.png")
		{
			$temp_ary['Image']			= "<img src='".config('global.GC_IMAGE_URL2')."' border='0' width='125' />";
			$temp_ary['image_forpopup'] = "<img src='".config('global.GC_IMAGE_URL2')."' border='0' width='75' />";
			$temp_ary['Billing_Image']  = "<img src='".config('global.GC_IMAGE_URL2')."' border='0' width='195'/>";
		} else {
			$temp_ary['Image']			= "<img src='".config('global.NO_IMAGE_THUMB')."' border='0' width='125' />";
			$temp_ary['image_forpopup'] = "<img src='".config('global.NO_IMAGE_THUMB')."' border='0' width='75' />";
			$temp_ary['Billing_Image']  = "<img src='".config('global.NO_IMAGE_THUMB')."' border='0' width='195'/>";
		}

		$temp_ary['Prod_URL']       = "#";

		$temp_ary['RecipientName']	= $request['recname'];
		$temp_ary['RecipientEmail']	= $request['recemail'];
		$temp_ary['YourName']		= $request['yourname'];
		$temp_ary['YourEmail']		= $request['youremail'];
		$temp_ary['Subject']		= $request['subject'];
		$temp_ary['Message']		= $request['permassage'];
		//$temp_ary['Signature']		= $request['signature'];
		$temp_ary['DeliveryDate']	= $request['deliverydate'];
		$temp_ary['GiftImage']		= $request['GiftImage'];
		$temp_ary['IsDealProducts'] = 'No';
		$temp_ary['DealDiscountFlag'] = 'No';
		$temp_ary['ImanufactureID']  = '99999';
		$temp_ary['IS_Free_Gift']	 = '';
		$temp_ary['VendorSKU']	 = '';
		$temp_ary['IsCosmo']	 = 'No';
		$temp_ary['IsNandansons']	= 'No';
		$temp_ary['IsPerfumePW']	= 'No';
		$temp_ary['IsPCA']	 = 'No';
		$temp_ary['ItemWiseCouponDiscount']	 = '';
		$temp_ary['handling_time_str']	 = '';

		if($temp_ary['Price'] <= 0)
			return false;

		$this->setGiftCertiTotal($temp_ary['TotPrice']);

		//$this->session['ShoppingCart']['Cart'][] = $temp_ary;
		$cart_arr = array();
		if(Session::has('ShoppingCart.Cart')){
			$cart_arr = Session::get('ShoppingCart.Cart');
		}
		array_push($cart_arr,$temp_ary);
		Session::put('ShoppingCart.Cart',$cart_arr);
		
		$a = $this->CalculateSubTotal();
		return true;
	}

	public function setGiftCertiTotal($val)
	{
		$GiftCertiTotal = Session::get('ShoppingCart.GiftCertiTotal') + $val;
		Session::put('ShoppingCart.GiftCertiTotal',$GiftCertiTotal);
		
		$GiftCertiCount = Session::get('ShoppingCart.GiftCertiCount') + 1;
		Session::put('ShoppingCart.GiftCertiCount',$GiftCertiCount);
		return NULL;
	}
	
	public function checkexistgiftcertificate($GiftImage)
    {
		$GiftSkuVal = '';
		if($GiftImage == "GiftCard1.png")
		{
			$GiftSkuVal =  config('global.GIFT_CERTIFICATE_SKU');
		}
		else if($GiftImage == "GiftCard2.png")
		{
			$GiftSkuVal =  config('global.GIFT_CERTIFICATE_SKU1');
		}
		else if($GiftImage == "GiftCard3.png")
		{
			$GiftSkuVal = config('global.GIFT_CERTIFICATE_SKU2');
		}
		
		$shoppingcart = Session::get('ShoppingCart.Cart');
		$count = count($shoppingcart);

        for ($a = 0; $a < $count; $a++) {
            if ($GiftSkuVal == $shoppingcart[$a]['SKU']) {
               return false;
            }
        }
        return true;
	}
	
	public function CalculateSubTotal()
	{
		if(Session::has('ShoppingCart.Cart'))
		{	
			$shoppingcart = Session::get('ShoppingCart.Cart');
			$count = count ( $shoppingcart );
			$SubTotal = 0;
			$TotalItemInCart = 0 ;

			for($a=0; $a<$count; $a++)
			{
				$SubTotal += $shoppingcart[$a]['TotPrice'];
				$TotalItemInCart += $shoppingcart[$a]['Qty'];
			}	
			Session::put('ShoppingCart.SubTotal',NumberFormat($SubTotal));
			Session::put('ShoppingCart.TotalItemInCart',$TotalItemInCart);
		}
	}
	
	public function ListingMenu()
	{
		$ListingMenus = Listingmenu::select('menuname', 'table_fieldName', 'fieldFor', 'display')->where('display','=','Yes')->orderBy('id')->get();
		$Listing = [];
		if($ListingMenus && $ListingMenus->count() > 0)
		{
			foreach($ListingMenus as $ListingMenu)
				$Listing[$ListingMenu->table_fieldName] = $ListingMenu;
		}
		return $Listing;
	}
	
	//Wholesale Special Price Functions
	function GetSpecialPriceWholesaler(Request $request,$markuparr) {
		$var_extra='';
		$var_extra1='';

		$sql_part='';
		$prodRes=array();

		$add_extra_sql='';
		$add_extra_select='';
		$add_extra_ordBy='';

		$brand = $request['brand'];
		$flg = $request['flg'];
		$search_keyword = $request['search_keyword'];
		
		$page = $request['page'];
		if($page == "" || $page <= 1){
			$page = 1;
		}
		$reclimit = 6;
		$start_from = ($page - 1) * $reclimit;
		
		//$brand = "Frapin Parfums";
		//$search_keyword = "t";
		
		$prodCntSQL= Products::select('products_id')
								->where('status','=','1')
								->where('wholesale_price','!=','0.00')
								->whereIn('product_type',['both','wholesaler']);
								
		$prodSQL = Products::where('status','=','1')
								->where('wholesale_price','!=','0.00')
								->whereIn('product_type',['both','wholesaler']);
		if($brand!="")
		{
			//$brandSQL= "SELECT * FROM `".TABLE_PREFIX."manufacture` WHERE REPLACE(REPLACE(REPLACE(vmanufacture,\"\\\'\",''),\"&\",'and'),\"\\'\",'') = '".str_replace("\'","",str_replace("&","and",str_replace("\\'","",trim($brand))))."' and status = '1'";
			
			//~ $brand_name = str_replace("\'","",str_replace("&","and",str_replace("\\'","",trim($brand)));
			//~ $brandCntRes= DB::table('pu_manufacture')->select('imanufactureid')
							//~ ->where('status', '=', '1')
							//~ ->whereRaw('REPLACE(REPLACE(REPLACE(vmanufacture,\"\\\'\",''),\"&\",\"and\"),\"\\\'\","") = ?',[$brand_name])
							//~ ->get();
			 
			$brandCntRes= Manufacture::where('vmanufacture', '=', $brand)
							->where('status', '=', '1')
							->get();
								
			$prodCntSQL->where('imanufactureid','=',$brandCntRes[0]->imanufactureid);
			$prodSQL->where('imanufactureid','=',$brandCntRes[0]->imanufactureid);
		}
		//echo "<pre>";print_r($brandCntRes);exit;
		
		if($flg!="")
		{
			if($flg=="na")
			{
				$prodCntSQL->where('new_arrival','=','Yes');
				$prodSQL->where('new_arrival','=','Yes');
			}
			else if($flg=="fe")
			{
				$prodCntSQL->where('featured','=','Yes');
				$prodSQL->where('featured','=','Yes');
			}
			else if($flg=="cl")
			{
				$prodCntSQL->where('clearance','=','Yes');
				$prodSQL->where('clearance','=','Yes');
			}
			else if($flg=="ts")
			{
				$prodCntSQL->where('top_seller','=','Yes');
				$prodSQL->where('top_seller','=','Yes');
			}
			else if($flg=="cp")
			{
				$prodCntSQL->where('celebrity','=','Yes');
				$prodSQL->where('celebrity','=','Yes');
			}
		}
		
		if($search_keyword!=""){
			//$search_keyword = str_replace("\'","",str_replace("&","and",str_replace("\\'","",trim($search_keyword))));
			
			//$add_extra_sql .= " and (REPLACE(REPLACE(REPLACE(product_name,\"\\\'\",''),\"&\",'and'),\"\\'\",'') LIKE \"%$search_keyword%\" || sku='".$search_keyword."' || UPC='".$search_keyword."')";
			
			$prodCntSQL->where(function($query) use ($search_keyword){
							$query->orWhere('product_name','LIKE','%'.$search_keyword.'%')
								  ->orWhere('sku','=',$search_keyword)
								  ->orWhere('UPC','=',$search_keyword);
						});
			
			$prodSQL->where(function($query) use ($search_keyword){
							$query->orWhere('product_name','LIKE','%'.$search_keyword.'%')
								  ->orWhere('sku','=',$search_keyword)
								  ->orWhere('UPC','=',$search_keyword);
						});
		}
		
		$prodCntRes = $prodCntSQL->get()->count();
		
		//echo $prodCntRes;exit;
		//$prodRes = $prodSQL->offset($start_from)->limit($reclimit)->get();
		if(Session::get('sess_useremail') == 'qqualdev@gmail.com')
		{
			ini_set('memory_limit', '512M');
			$prodRes = $prodSQL->get();
		} else {
			$prodRes = $prodSQL->limit(1000)->get();
		}
		//echo "<pre>"; print_r($prodRes); exit;
		$SpecialProducts = [];
		for($i=0; $i < count($prodRes); $i++) {
			
			$prodRes[$i] = $this->SetProduct($prodRes[$i]);
			if(!isset($request->all_items))
			{
				if($prodRes[$i]->stock == 'Out')
					continue;
			}
			$imgname = stripslashes($prodRes[$i]['image']);
			if(file_exists(config('global.PRD_THUMB_IMG_PATH').stripslashes($prodRes[$i]['image'])) and !empty($imgname))
					{$thumb_image = config('global.PRD_THUMB_IMG_URL').rawurlencode($prodRes[$i]['image']);}
			else
					{$thumb_image = config('global.NO_IMAGE_THUMB');}
			$prodRes[$i]['image'] = $thumb_image;
			
			//wholesale markup prices
			if($markuparr->count() > 0){
				$markuparr_cnt = $markuparr->count();
				for($d=0;$d< $markuparr_cnt;$d++)
				{
					if($markuparr[$d]->markup_value != "")
					{
						$per = $markuparr[$d]->markup_percent;
						$prodRes[$i]['wholesale_price_'.$d] = $prodRes[$i]['wholesale_price'] - ($prodRes[$i]['wholesale_price']*$per/100);
					}
					
				}
			}
			$SpecialProducts[]=$prodRes[$i];
			//wholesale markup prices
		}
		$start = 0;
		$end = $reclimit;
		if($page > 1)
		{
			$start = ($page-1) * $reclimit;
			$end = $start + $reclimit;
		}
		$NewSpecialProducts = array_slice($SpecialProducts,$start_from,$reclimit);
		//echo "<pre>";
		//print_r($prodSQL);
		//exit;

		$response_arr['TotalProducts'] = count($SpecialProducts);
		$response_arr['PerPage'] = $reclimit;
		$response_arr['DataArr'] = $NewSpecialProducts;
		return $response_arr;
	}
	
	public function GetProductsByQuery($Flag,$CategoryID,$limit=12,$Filters=[])
	{
		$FilterCategories = [];
		$Offset = 0;
		$SortBy = "";
		$CatProdsQry = [];
		$ChildCatArr = [];
		if(count($Filters) > 0){
			foreach($Filters as $fkey => $Filter)
			{
				if($fkey == 'categories' && count($Filters) > 0){
					$ChildCatArr = $Filters['categories'];
				} 
			}
		} 
		if(count($ChildCatArr) == 0 && $CategoryID != '') {
			$ChildCats = $this->GetChildCategories($CategoryID);
			$ChildCatArr = array_column($ChildCats['CatList'],'category_id');
		}
		
		if(isset($Filters['page']) && $Filters['page'] > 1){
			/*if($Flag=='BrandPage')
			{*/
				$Offset = ($Filters['page']-1) * $limit;
			/*}else{
				$Offset = ($Filters['page']-1) * $limit;
			}*/
		}
		
		$SortBy = isset($Filters['sortby'])?$Filters['sortby']:'';
	
		$CatProdsQry = DB::table('pu_products as po')
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_brand as b','b.brand_id','=','po.brand_id')
					->join('pu_manufacture as m',function($join){
						$join->on('po.imanufactureid','=','m.imanufactureid');
						$join->on('b.imanufactureid','=','m.imanufactureid');
					})
					->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
								'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
								'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
								'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
								'po.vtype','po.variation_id','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.parent_id')
					->where('po.status','=','1')
					->where('c.status','=','1');
        
        if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $CatProdsQry->whereIn('po.product_type',['both','retailer','wholesaler']);
        else
            $CatProdsQry->whereIn('po.product_type',['both','retailer']);
		
		if(count($ChildCatArr) > 0)			
			$CatProdsQry->whereIn('pc.category_id',$ChildCatArr);
			
			//$CatProdsQry->groupBy(['po.brand_id','po.gender','po.imanufactureid']);
		$CatProdsQry->groupBy(['po.variation_id']);
		
		$FilterStock = '';
		$FilterMinPrice = '';
		$FilterMaxPrice = '';
		$FilterKey = '';
		foreach($Filters as $fkey => $Filter)
		{
			if(is_array($Filter) && count($Filter) > 0)
			{
				if($fkey == 'categories'){
					$CatProdsQry->whereIn('pc.category_id',$Filter);
				}else if($fkey == 'brands'){
					$CatProdsQry->whereIn('po.imanufactureid',$Filter);
				}else if($fkey == 'special'){
					foreach($Filter as $Special)
					{
						if($Special == 'top_seller' || $Special == 'ts')
							$CatProdsQry->where('po.top_seller','=','Yes');
						if($Special == 'new_arrival' || $Special == 'na')
							$CatProdsQry->where('po.new_arrival','=','Yes');
						if($Special == 'featured' || $Special == 'fe')
							$CatProdsQry->where('po.featured','=','Yes');
						if($Special == 'clearance' || $Special == 'cl')
							$CatProdsQry->where('po.clearance','=','Yes');
						if($Special == 'celebrity' || $Special == 'cp')
							$CatProdsQry->where('po.celebrity','=','Yes');
						if($Special == 'sale_price' || $Special == 'sl')
							$CatProdsQry->where('po.sale_price','>',0);
					}
				} else if($fkey == 'stock'){ 
					$FilterStock = $Filter[0];
				} else if($fkey == 'ProductSKUs'){
					$CatProdsQry->whereIn('po.sku',$Filter);		
				} else if($fkey == 'NotProductSKUs'){
					$CatProdsQry->whereNotIn('po.sku',$Filter);		
				} else {
					$CatProdsQry->whereIn('po.'.$fkey,$Filter);
				}
			} else if($fkey == 'stock'){
				$FilterStock = $Filter;
			}else if($fkey == 'minprice'){
				$FilterMinPrice = $Filter;
			}else if($fkey == 'maxprice'){
				$FilterMaxPrice = $Filter;
			}else if($fkey == 'key'){
				$FilterKey = $Filter;
			}
		}
		
		if($Flag == 'CategoryPage')
		{
			$CatProdsQry->where(function($query){
				$query->where('po.top_seller','=','Yes');
				$query->orWhere(DB::raw("DATE_FORMAT(po.add_datetime,'%Y-%m-%d')"),'>=',DB::raw("DATE_SUB(CURDATE(),INTERVAL 30 DAY)"));
			});
			$CatProdsQry->orderBy('po.add_datetime','desc');
		}else if($Flag == 'DealofweekPage'){
			$CatProdsQry->join('pu_dealofweek as dw','dw.product_sku','=','po.sku');
			$CatProdsQry->join('pu_dealofweektitle as dwt','dw.did','=','dwt.did');
			$CatProdsQry->where('dw.deal_type','=','Weekly');
			$CatProdsQry->where('dw.status','=','1');
			$CatProdsQry->where('dw.start_date','<=',date('Y-m-d'))->where('dw.end_date','>=',date('Y-m-d'));
			if($FilterKey != '')
				$CatProdsQry->where('po.UPC','=',$FilterKey);
			$CatProdsQry->orderBy('dwt.deal_rank');
			$CatProdsQry->orderBy('dw.end_date');
			$CatProdsQry->orderBy('dw.display_rank');
		}else if($Flag == 'ProductListPage'){
			$CatProdsQry->orderBy('b.brand_name');
			$CatProdsQry->orderBy('po.cosmo_sku');
			$CatProdsQry->orderBy('po.nandansons_sku');
			$CatProdsQry->orderBy('po.pca_sku');
			$CatProdsQry->orderBy('po.display_position');
		} else if($Flag == 'ShoppingCart'){
			$CatProdsQry->join('pu_products_viewed as pv','po.sku','=','pv.sku');
			$CatProdsQry->where('pv.customer_ip','!=',$_SERVER['REMOTE_ADDR']);
		}else{
			$CatProdsQry->orderBy('po.display_position');
			$CatProdsQry->orderBy('po.product_name');
		}
		$CatProdsWithoutLimit = $CatProdsQry->get();
		
		//$CatProdsWithLimit = $CatProdsQry->offset($Offset)->limit($limit)->get();
		$ArrayFilters = ['sortby' => $SortBy, 'offset' => $Offset, 'limit' => $limit];
		$SKUs = '' ;
		$CatProducts = [];
		$TotalProds = 0;
		
		if($CatProdsWithoutLimit && $CatProdsWithoutLimit->count() > 0)
		{
			$SliderCategory = $this->GetCategories($CatProdsWithoutLimit);	
			foreach($CatProdsWithoutLimit as $key => $CatProd)
			{
				$CatProd = $this->SetProduct($CatProd);					
				if($FilterStock != '' && $CatProd->stock == 'Out')
					continue;
				
				if($FilterMaxPrice !='')
				{	
					if($CatProd->product_price < $FilterMinPrice || $CatProd->product_price > $FilterMaxPrice )
						continue;
				}
				/*$SKUs.= $CatProd->sku."#";	
				$TotalProds++;*/
				
				if($CatProd->is_atomizer == "Yes" || $CatProd->stock == "Out")
				{
					$SizeCountArr = $this->getReferencedProducts_Counter_ListingDev($CatProd->products_id,$CatProd->variation_id,$CategoryID,[],$CatProdsWithoutLimit);
					if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'No' && $SizeCountArr[0]->is_atomizer != '')
						$Product = $SizeCountArr[0];
					else if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'Yes' && $SizeCountArr[0]->stock =='In')
						$Product = $SizeCountArr[0];
					else
						$CatProd->size_cnt = $SizeCountArr;
				} else {
					$CatProd->size_cnt = $this->getReferencedProducts_CounterDev($CatProd->products_id,$CatProd->variation_id,$CatProdsWithoutLimit);	
				}
				
				$PriceRange = $this->setPriceRange($CatProd->variation_id,$CatProdsWithoutLimit);
				$CatProd->minPrice = $PriceRange['MinPrice'];
				$CatProd->maxPrice = $PriceRange['MaxPrice'];
				$CatProd->yousave = $PriceRange['YouSave'];
				
				if($CategoryID == '2')
					$CatProd->category_id = $CategoryID;
				
				if($CatProd->parent_id != 0)
					$ProdCat = $CatProd->parent_id;
				else
					$ProdCat = $CatProd->category_id;
				
				$ProdCatDetails = $SliderCategory[$ProdCat];
				$category_url = remove_special_chars($ProdCatDetails->category_name).'/';
				$product_name = remove_special_chars($CatProd->product_name);
				$CatProd->product_url = config('global.SITE_URL').$category_url.$product_name."/pid/".$CatProd->products_id."/".$ProdCat;
				
				if ($CatProd->gender == 'M'){
					$CatProd->gender = "sp sp-strip-boy-icon";
					$CatProd->gendernames = "Men";
					$for_gender = ' for Men';
				} elseif ($CatProd->gender == 'W'){
					$CatProd->gender = "sp sp-strip-girl-icon";
					$CatProd->gendernames = "Women";
					$for_gender = ' for Women';
				} elseif ($CatProd->gender == 'K'){
					$CatProd->gender = "sp sp-strip-children-icon";
					$CatProd->gendernames = "Kids";
					$for_gender = ' for Kids';
				} elseif ($CatProd->gender == 'U'){
					$CatProd->gender = "sp sp-strip-uni-icon";
					$CatProd->gendernames = "Unisex";
					$for_gender = ' Unisex';
				} else{
					$CatProd->gender = "";
					$CatProd->gendernames = "";
					$for_gender = '';
				}
				
				if($CatProd->vmanufacture != ''){
					$m_name = strtolower($CatProd->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$CatProd->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$CatProd->imanufactureid;
				}
				
				if($CatProd->brand_name != '' && $CatProd->vmanufacture != ''){
					$m_name = strtolower($CatProd->vmanufacture);
					$m_name = str_replace("#", "", $m_name);
					$m_name = str_replace("&", "", $m_name);
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace("  ", " ", trim($m_name));
					$m_name = str_replace(" ", "-", $m_name);
					$CatProd->referencedName = '<a href="' . $CatProd->product_url . '"><strong><u>' . $CatProd->brand_name . '</u></strong></a> by <a href='.$CatProd->vmanufacture_link.'><strong><u>'.$CatProd->vmanufacture.'</strong></u></a>'.$for_gender;
				}
				
				if(strlen($CatProd->product_name) > 45){
					$CatProd->product_name = substr($CatProd->product_name, 0, (45 - strlen($CatProd->product_name))). "..";
				} else {
					$CatProd->product_name = $CatProd->product_name;
				}

				if($CatProd->vmanufacture == '' || $CatProd->brand_name == ''){
					$CatProd->referencedName = '<a href="' . $CatProd->product_url . '"><u>' . $CatProd->product_name . '</u></a>';
				}
				
				if($CatProd->retail_price != '' && $CatProd->retail_price != '0.00' && isset($CatProd->product_price)){
					$yousave = ($CatProd->retail_price - $CatProd->product_price) / $CatProd->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$yousaveprice = $CatProd->retail_price - $CatProd->product_price;
				}else{
					$yousave = 0;
					$yousaveprice = 0;
				}
				
				$CatProd->yousave = $yousave;
				$CatProd->maxyousave = number_format($CatProd->yousave, 0);
				$CatProd->yousaveprice = $yousaveprice;
				$CatProd->autoid = $key;
				
				$CatProd->sale_item = '0';
				if($CatProd->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
				{
					$CatProd->sale_item = '1';
				}
				
				$DealData = config('DealDetails');
				if(isset($DealData[$CatProd->sku]))
				{
					$CatProd->deal_price = $DealData[$CatProd->sku]['deal_price'];
					$CatProd->yousave = $DealData[$CatProd->sku]['yousave'];
					$CatProd->yousaveprice = $DealData[$CatProd->sku]['yousaveprice'];
				}
				$CatProd->short_description = strip_tags($CatProd->short_description);
				/*$CatProd->avg_rate = 0;
				$total_review = $CatProd->TotalReview;
				if($total_review > 0)
					$CatProd->avg_rate = GetProductAverageRating($CatProd->TotalReview,$CatProd->TotalRate);
				*/

				$CatProducts[] = $CatProd;
			}
			//$Products = $this->GetSliderProducts($SKUs,'','Category',$CategoryID,$ArrayFilters);		
		}

		$AllFilters = $this->GetFilters($CatProducts,$Filters);
		if(count($CatProducts)>0 && isset($ArrayFilters['limit']) && $ArrayFilters['limit'] != '')
		{
			$CatProducts = array_slice($CatProducts,$ArrayFilters['offset'],$ArrayFilters['limit']);
		}
		if(count($CatProducts)>0 && isset($ArrayFilters['sortby']) && $ArrayFilters['sortby'] != '')
		{
			if($ArrayFilters['sortby'] == 'priceHL'){
				usort($CatProducts,function($first,$second){
					return $first->product_price < $second->product_price ? 1 : -1; 
				});
			}
			if($ArrayFilters['sortby'] == 'priceLH'){
				usort($CatProducts,function($first,$second){
					return $first->product_price > $second->product_price ? 1 : -1; 
				});
			}
			if($ArrayFilters['sortby'] == 'priceAZ'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($first->brand_name),strtolower($second->brand_name)); 
				});
			}
			if($ArrayFilters['sortby'] == 'priceZA'){
				usort($CatProducts,function($first,$second){
					return strcmp(strtolower($second->brand_name),strtolower($first->brand_name)); 
				});
			}
		}
		$ProductsDetails = ['Products' => $CatProducts,'TotalProducts' => $CatProdsWithoutLimit->count(), 'LeftFilters' => $AllFilters];
		return $ProductsDetails;
	}

	public function PhoneorderPaymentSuccess($payment_mode)
    {	//payment_mode = Stripe,Afterpay
		$OrderID = Session::get('phoneorder_detail.order_id');
		
		// if($success ==1){
			
			$OrderRS = Order::where('orders_id', '=', $OrderID)
						->get();
			// echo "<pre>";print_r($OrderRS);exit;
			
			if($OrderRS->count() <= 0) 
			{
				$err_msg = "Something went wrong, payment failed.";
				$res_arr['success'] = 0;
				$res_arr['err_msg'] = $err_msg;
				return $res_arr;
			}
			
			$OrderDetailRs = OrderDetail::where('orders_id', '=', $OrderID)
						->get();
			
			if($OrderRS[0]->gc_code != "")
			{
				$GiftCardRes = GiftCertificate::where('remaining_value','>','0')
												->where('status','=',"1")
												->where('gc_code','=',$OrderRS[0]->gc_code)
												->get();
												
				// echo "<pre>";print_r($GiftCardRes);exit;
				if($GiftCardRes->count() > 0) 
				{
					$gc_remaining_value = 0;
					$applied_amount = $OrderRS[0]->gc_amount;	//applied gc amount
					
					if($applied_amount <= $GiftCardRes[0]->remaining_value)
					{
						$gc_remaining_value = $GiftCardRes[0]->remaining_value-$applied_amount;
					}
					
					if($GiftCardRes[0]->gc_code != '' && $GiftCardRes[0]->remaining_value > 0 ) 
					{
						$upgGif = array (
									'remaining_value'	=>	$gc_remaining_value,
									'last_used_date'	=>	date('Y-m-d H:i:s')
										);
										
						$uporderresgift = GiftCertificate::where('gc_code','=',$GiftCardRes[0]->gc_code)->update($upgGif);
					}		
				}
			}  
			
			$updAray = array (
							'phoneorder_paymentdate' => date("Y-m-d H:i:s")
						 );
			$updOrder = Order::where('orders_id','=',$OrderID)->update($updAray);
		
			$res_client = Customer::select('customer_id','iRewardpoint','referenced_by','email','registration_type','status')
								->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->where('status', '=', '1')
								->limit(1)->get();
			
			$NewReaminRewardpoint = $res_client[0]->iRewardpoint;	
			if(strtolower($OrderRS[0]->user_type)=='retailer') {
				$rewardarray_use = array();
				$reward_discount =  $OrderRS[0]->reward_discount;
				
				if($reward_discount > 0) {
					// $res_client = Customer::select('customer_id','iRewardpoint')
								// ->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->where('status', '=', '1')
								// ->limit(1)->get();
								
					
					//////////////////////
					$Redeem_Reward = RewardRule::select('forderamount','fcharge')
								->where('erewardrule', '=', 'redeem')
								->get();
								
					$Max_Reward = RewardRule::select('fcharge')
								->where('erewardrule', '=', 'max')
								->get();
					
					$reward_point_deducted = ($reward_discount * $Redeem_Reward[0]->fcharge)/ $Redeem_Reward[0]->forderamount;
								
					/* if($res_client[0]->iRewardpoint >  $Max_Reward[0]->fcharge)
					{
						$refer_amount = ($res_client[0]->iRewardpoint/$Redeem_Reward[0]->fcharge);

						if($reward_discount < $OrderRS[0]->sub_total )
						{
							$remain_count = $Redeem_Reward[0]->fcharge * (int)$refer_amount;
							$reward_remaining = $res_client[0]->iRewardpoint - $remain_count;
							$Total_Reward_Point = $res_client[0]->iRewardpoint;
							$AppliedRewardPoint = $res_client[0]->iRewardpoint;
						}
					} 
					
					if((int)$reward_remaining > 0  && $reward_discount>0) {
						 $FinalReaminRewardpoint = (int)$reward_remaining; 
					}
					else{
						 $FinalReaminRewardpoint = $res_client[0]->iRewardpoint;
					}
					*/
					$FinalReaminRewardpoint = $res_client[0]->iRewardpoint;	
					if($reward_point_deducted <= $res_client[0]->iRewardpoint){
						$FinalReaminRewardpoint = $res_client[0]->iRewardpoint - $reward_point_deducted;
					}
					$NewReaminRewardpoint = $FinalReaminRewardpoint;
					//echo "<pre>".$FinalReaminRewardpoint;print_r($_SESSION);exit;
					$upgCustomer = array (
											'iRewardpoint' => $FinalReaminRewardpoint
								   );	
					$udpRefer = Customer::where('customer_id','=',$OrderRS[0]->customer_id)->update($upgCustomer);
				
					if($reward_point_deducted  > 0){
						$InsertCustomer = array (
													'customer_id' 	=> $OrderRS[0]->customer_id,
													'note'		  	=> "Deduct Reward Point By Phone Order",
													'iRewardpoint'	=> $reward_point_deducted,
													'Order_No'		=> $OrderRS[0]->orders_no
										   );
						RewardPoint::create($InsertCustomer);						 
					}
				}
			}
			
			$Rewardchk_arr = array();
			if($OrderDetailRs->count() > 0) {
				$DealTotalprice = 0;
				for($dl=0; $dl < $OrderDetailRs->count(); $dl++) {
					$dealofdayRS = Dealofweek::select('dealofweek_id','product_sku')
								->where('status', '=', '1')
								->where('start_date', '<=', date('Y-m-d'))
								->where('end_date', '>=', date('Y-m-d'))
								->where('product_sku', '=', $OrderDetailRs[$dl]->sku)
								->limit(1)->get();
					
					if($dealofdayRS->count() > 0) {
							$DealTotalprice = $DealTotalprice+$OrderDetailRs[$dl]->total;
					}
					else {
							$Rewardchk_arr[] = $OrderDetailRs[$dl]->sku;
					}
				}
			}
			
			if(strtolower($OrderRS[0]['user_type'])=='retailer') {
				$rewardsql = RewardRule::where('erewardrule', '=', 'reward')
								->get();

				if($rewardsql->count() > 0) {
					//Deal product's reward point count :: Start
					$Rewardtotal = $OrderRS[0]->order_total;
					$RewardtotalNext = $OrderRS[0]->order_total;
					$DealRewardpoint = 0;
					
					if($DealTotalprice>0) {
						$valuedeal = ( $DealTotalprice * 2)/$rewardsql[0]->forderamount;
						$DealRewardpoint = number_format($valuedeal, 0, '.', '');
						
						if($Rewardtotal>$DealTotalprice){
							$Rewardtotal = $Rewardtotal-$DealTotalprice;
						}
					}
			
					//Deal product's reward point count :: End			
					$value = ($Rewardtotal * $rewardsql[0]->fcharge)/$rewardsql[0]->forderamount;
					$Rewardpoint = number_format($value, 0, '.', '');
						
					if($RewardtotalNext>$DealTotalprice && !empty($Rewardchk_arr))
						$Rewardpoint = $Rewardpoint+$DealRewardpoint; 
					else
						$Rewardpoint = $DealRewardpoint; 
				
					
					if($Rewardpoint>0) {
						// $res_client = Customer::select('iRewardpoint')
								// ->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->limit(1)->get();
							
						$FinalRewardpoint = $Rewardpoint + $NewReaminRewardpoint;
						$NewReaminRewardpoint = $FinalRewardpoint;
						
						$upgCustomer = array (
												'iRewardpoint' => $FinalRewardpoint
									   );
						$udpRefer = Customer::where('customer_id','=',$OrderRS[0]->customer_id)->update($upgCustomer);			   
						  // echo $Rewardpoint;exit;
						$InsertCustomer = array (
												'customer_id' 	=> $OrderRS[0]['customer_id'],
												'note'		  	=> "Reward Point Added By Phone Order",
												'iRewardpoint'	=> $Rewardpoint,
												'Order_No'		=> $OrderRS[0]["orders_no"]
									   );
						RewardPoint::create($InsertCustomer);
					}
				}
			}
			
			// $cust_res = Customer::select('referenced_by','email')
								// ->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->where('registration_type', '=', 'M')
								// ->where('status', '=', '1')
								// ->get();
			
			//$Remail = $cust_res[0]['email'];
			$referenced_by = "";
			if($res_client->count()>0 && $res_client[0]->registration_type == 'M' && $res_client[0]->status == '1' )
			{ 
				$referenced_by = $res_client[0]->referenced_by; 
				
				if($referenced_by != ""){
					$new_str_arr = explode('#', $referenced_by);
					
					if(!empty($new_str_arr)){
						$id = $new_str_arr[0];
						$Remail =  $new_str_arr[1];		
					}
				}
			}
			
			if($referenced_by!='' )
			{	
				$referralRes = ReferFriend::select('sender','is_sender_notified','receiver')
								->where('customer_id', '=', $OrderRS[0]->customer_id)
								->where('receiver', '=', $Remail)
								->limit(1)->get();
				
				$datetime = date('Y-m-d H:i:s');
					
				if($referralRes->count()>0) 
				{
					//Condition For Adding Referral Point First Time When Refferal Client Clicks in Link and Updating Referrel Customer Status//
					if($referralRes[0]->is_sender_notified == 'N') 
					{
						/*$saveData['customer_id'] 		= $cust_id;
						$saveData['sender'] 		 	= $sender_email;       
						$saveData['receiver'] 		 	= $email;*/
						$saveData['is_sender_notified'] = 'Y';
						$saveData['refer_datetime']	 	= $datetime;       
						
						$referredId = ReferFriend::where('customer_id','=',$id)->where('receiver','=',$Remail)->update($saveData);
						
						// $cust_res = Customer::select('iRewardpoint')
								// ->where('customer_id', '=', $OrderRS[0]->customer_id)
								// ->get();
						// Query For Updating Reward Point in Customer Table //
						
						$reward_point = $NewReaminRewardpoint+100;
						$custdata['iRewardpoint'] = $reward_point;
						$custId = Customer::where('customer_id','=',$OrderRS[0]->customer_id)->update($custdata);
						
						$InsertCustomer = array (
													'customer_id' 	=> $OrderRS[0]->customer_id,
													'note'		  	=> "Reward Point For Adding Referral Point First Time",
													'iRewardpoint'	=> 100,
													'Order_No'		=> $OrderRS[0]->orders_no   // Change Order No 
												);
						RewardPoint::create($InsertCustomer);
					}
				}
			}
			
			#### Deduct product stock Start #####
			if($payment_mode != "Stripe" || ($payment_mode == "Stripe" && $OrderRS[0]->pay_status == 'Paid')){
				if($OrderDetailRs->count() > 0){
					$tot_pro = $OrderDetailRs->count();
					
					for($i=0; $i < $tot_pro; $i++){
						$ProductSt = Products::select('current_stock','cosmo_current_stock','cosmo_sku','nandansons_sku','nandansons_current_stock','perfumeworldwide_sku','pca_sku','perfumeworldwide_currentstock','pca_current_stock')
								->where('status', '=', '1')
								->where('sku', '=', $OrderDetailRs[$i]->sku)
								->get();
						
						if($ProductSt->count() > 0 )
						{
							$new_stock=0;
							if($OrderDetailRs[$i]->IsCosmo =="Yes" && $OrderDetailRs[$i]->VendorSKU == $ProductSt[0]->cosmo_sku)
							{
								if($ProductSt[0]->cosmo_current_stock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->cosmo_current_stock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->cosmo_current_stock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity -$ProductSt[0]->cosmo_current_stock;
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}

								$UpdateStock = array (
												'cosmo_current_stock' => $new_stock
											 );
							}
							else if($OrderDetailRs[$i]->IsNandansons == "Yes" &&  $OrderDetailRs[$i]->VendorSKU ==$ProductSt[0]->nandansons_sku)
							{
								if($ProductSt[0]->nandansons_current_stock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->nandansons_current_stock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->nandansons_current_stock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity - $ProductSt[0]->nandansons_current_stock;
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}

								$UpdateStock = array (
												'nandansons_current_stock' => $new_stock
											 );
							}
							else if($OrderDetailRs[$i]->IsPerfumePW =="Yes" && $OrderDetailRs[$i]->VendorSKU == $ProductSt[0]->perfumeworldwide_sku)
							{
								if($ProductSt[0]->perfumeworldwide_currentstock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->perfumeworldwide_currentstock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->perfumeworldwide_currentstock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity - $ProductSt[0]->perfumeworldwide_currentstock;
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}

								$UpdateStock = array (
												'perfumeworldwide_currentstock' => $new_stock
											 );
							}
							else if($OrderDetailRs[$i]->IsPCA == "Yes" && $OrderDetailRs[$i]->VendorSKU ==$ProductSt[0]->pca_sku)
							{
								if($ProductSt[0]->pca_current_stock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->pca_current_stock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->pca_current_stock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity - $ProductSt[0]->pca_current_stock;
								}
								if($new_stock<=0)
								{
									$new_stock=0;
								}

								$UpdateStock = array (
												'pca_current_stock' => $new_stock
											 );
							}
							else
							{
								if($ProductSt[0]->current_stock > $OrderDetailRs[$i]->quantity)
								{
									$new_stock = $ProductSt[0]->current_stock - $OrderDetailRs[$i]->quantity;
								}
								else if($OrderDetailRs[$i]->quantity > $ProductSt[0]->current_stock)
								{
									$new_stock = $OrderDetailRs[$i]->quantity - $ProductSt[0]->current_stock;
								}
								if($new_stock<=0)
								{
										$new_stock=0;
								}

								$UpdateStock = array (
													'current_stock' => $new_stock
												 );
							}
							$result = Products::where('sku','=',$OrderDetailRs[$i]->sku)->update($UpdateStock);
							
							//Umesh added
                        		$CreateQuantityArr = array(
                        					"Sku"  					=>$OrderDetailRs[$i]->sku,
                        					"WarehouseId"			=> 2,
                        					"LocationCode"			=> "United States of America",
                        			// 		"Reason"=> "Add",
                        					"Quantity"				=> (int)$new_stock,
                        					"TenantToken"			=> "x/FjCe1aq8MEsd2k5KtHW+5tAWWtacrGDb5lRriKFks=",
                        					"UserToken"				=> "cTkTP6sPPBckYvUwcB57JLeu3xdfW+BXXvDDe/saRUA="
                        
                        			  );
                        		
                        		$request = 'https://app.skuvault.com/api/inventory/setItemQuantity';
                        
                        	$param = json_encode(
                        					 $CreateQuantityArr
                        				);
                        
                        
                        	$initialreg = curl_init($request);
                        	curl_setopt ($initialreg, CURLOPT_POST, 1);
                        	curl_setopt ($initialreg, CURLOPT_POSTFIELDS, $param);
                        	curl_setopt($initialreg, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ;
                        	curl_setopt($initialreg, CURLOPT_HEADER, False);
                        	curl_setopt($initialreg, CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
                        	curl_setopt($initialreg, CURLOPT_RETURNTRANSFER, true);
                        	$response = curl_exec($initialreg);
                        	$error_rep = json_decode($response, true);
                        
                        	curl_close($initialreg);
        	
        	
        	//Umesh end
						}
					}
				}
			}	
		
			#### Deduct product stock End #####
			$Site_URL = config('global.SITE_URL');
			$STR_EMAIL_ITEM = '';
			$topmenubar = '<table cellpadding="0" cellspacing="0" width="100%" border="0" style="background-color:#2d2d2d;">
											<tr align="center">
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'fragrances/cid/1" style="color:#fff; text-decoration:none; padding:8px 0px; display:block; text-transform:uppercase;">Fragrances</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'skincare/cid/18" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Skincare</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'pocket-perfume/cid/68" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Pocket Perfume</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'bath-body/cid/12" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Bath &amp; Body</a></td>
												<td style="border-right:1px solid #e8e8e8;"><a href="'.$Site_URL.'candles/cid/208" style="color:#fff; text-decoration:none; padding:8px 0px; display:block;text-transform:uppercase;">Candles</a></td>
												<td><a href="'.$Site_URL.'offers.html" style="color:#ff0000; text-decoration:none; padding:5px; display:block;text-transform:uppercase;">SALES & OFFERS</a></td>
											</tr>
										</table>';
				
				//new
				$STR_EMAIL_ITEM .= '<table cellpadding="0" cellspacing="0" width="100%" border="0">
							<tr align="center" valign="top">
								<td style="background-color:#e5e5e5; padding:5px;"><strong>Gift Wrap</strong></td>
								<td style="background-color:#e5e5e5; padding:5px;"><strong>Images</strong></td>
								<td style="background-color:#e5e5e5; padding:5px;" align="left"><strong>Your Order Summary</strong></td>
								<td style="background-color:#e5e5e5; padding:5px;"><strong>Quantity</strong></td>
								<td style="background-color:#e5e5e5; padding:5px;" align="right"><strong>Price</strong></td>
							</tr>';
				
				$TotalProducts = 0;
				$is_gift_wrap = "No";
				for($n=0;$n < $OrderDetailRs->count(); $n++)
				{
						// $thumb_image = getItemThumb($OrderDetailRs[$n]['sku']);
						if($OrderDetailRs[$n]['sku']== config('global.GIFT_CERTIFICATE_SKU'))
						{
							$thumb_image	='<img src="'.config('global.GC_IMAGE_URL').'" width="125" border="0" class="img-resp-75" />';
						}
						else if($OrderDetailRs[$n]['sku'] == config('global.GIFT_CERTIFICATE_SKU1'))
						{
							$thumb_image	='<img src="'.config('global.GC_IMAGE_URL1').'" width="125" border="0" class="img-resp-75" />';
						}
						else if($OrderDetailRs[$n]['sku'] == config('global.GIFT_CERTIFICATE_SKU2'))
						{
							$thumb_image	='<img src="'.config('global.GC_IMAGE_URL2').'" width="125" border="0" class="img-resp-75" />';
						}else{
							$prod_res = Products::select('image')
								->where('sku', '=', $OrderDetailRs[$n]['sku'])
								->limit(1)->get();
								
							$image_name= $prod_res[0]['image'];

							if(file_exists(config('global.PRD_THUMB_IMG_PATH').$image_name) and !empty($image_name))
								$prod_image = config('global.PRD_THUMB_IMG_URL').$image_name;
							else
								$prod_image = config('global.NO_IMAGE_THUMB');

							$thumb_image	='<img src="'.$prod_image.'" width="125" border="0" class="img-resp-75" />';
						}

						
						$checked = '';
						if($OrderDetailRs[$n]['is_gift_wrap']=='Yes')
						{ $checked = 'checked="checked" ';$is_gift_wrap = "Yes";}
				
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td valign="middle" style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><input type="checkbox"  disabled="disabled" '.$checked.' /></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;">'.$thumb_image.'</a></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="left"><p margin:0px;"><strong>'.$OrderDetailRs[$n]['product_name'].'</strong></p><p>SKU:'.$OrderDetailRs[$n]['sku'].'</p>';
						
						
						$STR_EMAIL_ITEM .= '</td>';
						$STR_EMAIL_ITEM .= '<td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><strong>'.$OrderDetailRs[$n]['quantity'].'</strong></td>
						<td style="padding:10px 5px; border-bottom:1px solid #e8e8e8;" align="right"><strong>$'.$OrderDetailRs[$n]['price'].'</strong></td>
						</tr>';		
							
						$TotalProducts = (int)$TotalProducts + (int)$OrderDetailRs[$n]['quantity'];
				}
				
				if($is_gift_wrap == 'Yes')
				{
						$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Gift Wrap:</strong></td><td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">Yes</td></tr>';
				}
				
				$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong> Total item purchased:</strong></td>
					<td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">'.$TotalProducts.'</td>
				</tr>';
			
			
				$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Subtotal:</td>
					<td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['sub_total'].'</td>
				</tr>';
				
				
				if($OrderRS[0]["shipping_amt"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Charge:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['shipping_amt'].'</td></tr>';
				}
				
				if($OrderRS[0]["tax"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Sales Tax:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['tax'].'</td></tr>';
				}
				
				
				if($OrderRS[0]["gift_charge"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Wrap Charge :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['gift_charge'].'</td></tr>';
				}
				
				if($OrderRS[0]["auto_discount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Auto Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['auto_discount'].'</td></tr>';
				}
				
				if($OrderRS[0]["quantity_discount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Quantity Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['quantity_discount'].'</td></tr>';
				}
				
				if($OrderRS[0]["coupon_amount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Coupon Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['coupon_amount'].'</td></tr>';
				}
				
				if($OrderRS[0]["gc_amount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Certificate Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['gc_amount'].'</td></tr>';
				}
				
				if($OrderRS[0]["reward_discount"]>0)
				{
					$STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Reward Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['reward_discount'].'</td></tr>';
				}
				
				// if($OrderRS[0]["refer_amount"]>0)
				// {
					// $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">'.$AUTO_REFER_DISCOUNT.' :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">$'.$OrderRS[0]['refer_amount'].'</td></tr>';
				// }
				
				$STR_EMAIL_ITEM .= '<tr align="center" valign="top">
					<td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Order Total:</strong></td>
					<td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right"><strong>$'.$OrderRS[0]['order_total'].'</strong></td>
				</tr>';
				$STR_EMAIL_ITEM .= '</table>';
				
				$mres = GetMailTemplate("ORDER_RECEIPT_NEW");	
				$mail_content = stripslashes($mres[0]["mail_body"]);
				
				$freeshippinginfo = '';
				if(config('Settings.FREESHIPPING_VALUE')!="")
				{
					$freeshippinginfo .= '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
				}
				
				$mail_content = str_replace('{$freeshippinginfo}', $freeshippinginfo, $mail_content);
				$mail_content = str_replace('{$topmenubar}', $topmenubar, $mail_content);
				$mail_content = str_replace('{$ordereddate}', date("d F, Y",$OrderRS[0]['order_datetime']), $mail_content);
				$mail_content = str_replace('{$ordertotal}', $OrderRS[0]['order_total'], $mail_content);
				$mail_content = str_replace('{$shipinfo}', $OrderRS[0]['shipinfo'], $mail_content);
				$mail_content = str_replace('{$CONTACT_MAIL}', config('Settings.CONTACT_MAIL'), $mail_content);
				
				$MailBanners = MailBanner::where('status','=','1')->get();
				
				$Addblock = '';
				if($MailBanners && $MailBanners->count() > 0)
				{
					$Addblock .= ' <td class="flex" valign="top" width="27%"><table width="100%" border="0" cellpadding="0" cellspacing="0">
							  <tbody>';
					foreach($MailBanners as $MailBanner)
					{
						$banner_img = config('global.MAIL_BANNERS_URL').$MailBanner->mail_banner_image.".jpg";
						$banner_link = $MailBanner->mail_banner_link;
						$Addblock .= ' <tr class="halftd"> 
											<td style="padding:5px;border:1px solid #e8e8e8" align="center"><a href="'.$banner_link.'"  target="_blank"><img src="'.$banner_img.'" alt="" class="img-responsive"/></a>
										</td></tr>';
					}
					$Addblock .= '</tbody></table></td>';				
				}
				
				$mail_content = str_replace('{$Addblock}', $Addblock, $mail_content);
				$mail_content = str_replace('{$orders_no}', $OrderRS[0]['orders_no'], $mail_content);		
				$mail_content = str_replace('{$order_datetime}', date("d F, Y",$OrderRS[0]['order_datetime']), $mail_content);	
				$mail_content = str_replace('{$order_total}', $OrderRS[0]['order_total'], $mail_content);		
				$mail_content = str_replace('{$shipinfo}', $OrderRS[0]['shipinfo'], $mail_content);		
				//new
				
				$mail_content = str_replace('{$orders_id}', $OrderRS[0]['orders_id'], $mail_content);
				$BillAddress = $OrderRS[0]['bill_first_name'].' '.$OrderRS[0]['bill_last_name']."<br>";
				if($OrderRS[0]['bill_address2'] != '')
					$BillAddress.= $OrderRS[0]['bill_address1'].', '.$OrderRS[0]['bill_address2']."<br>";
				else 
					$BillAddress.= $OrderRS[0]['bill_address1'].',<br>';
				$BillAddress.=$OrderRS[0]['bill_city'].', '.$OrderRS[0]['bill_state']."<br>";
				$BillAddress.=$OrderRS[0]['bill_zip'].' - '.$OrderRS[0]['bill_country'];
					
				$mail_content = str_replace('{$bill_address}',$BillAddress,$mail_content);
				
				$ShipAddress = $OrderRS[0]['ship_first_name'].' '.$OrderRS[0]['ship_last_name']."<br>";
				if($OrderRS[0]['ship_address2'] != '')
					$ShipAddress.= $OrderRS[0]['ship_address1'].', '.$OrderRS[0]['ship_address2']."<br>";
				else 
					$ShipAddress.= $OrderRS[0]['ship_address1'].',<br>';
				$ShipAddress.=$OrderRS[0]['ship_city'].', '.$OrderRS[0]['ship_state']."<br>";
				$ShipAddress.=$OrderRS[0]['ship_zip'].' - '.$OrderRS[0]['ship_country'];
					
				$mail_content = str_replace('{$ship_address}',$ShipAddress,$mail_content);
				
				$mail_content = str_replace('{$STR_EMAIL_ITEM}',  $STR_EMAIL_ITEM, $mail_content);
				$mail_content = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$mail_content);
				$mail_content = str_replace('{$TOLL_FREE_NO}', config('global.CONTACT_PHONE_NO'), $mail_content);
				$mail_content = str_replace('{$Site_URL}', $Site_URL, $mail_content);
				$mail_content = str_replace('{$SITE_NAME}', config('global.SITE_TITLE'), $mail_content);
				
				$mail_subject = str_replace('{$SITE_NAME}', config('Settings.SITE_TITLE'), $mres[0]['subject']);
				$mail_subject = str_replace('{$OrderRs.orders_no}', $OrderRS[0]['orders_no'], $mail_subject);
				//$onesendstat = $generalobj->SMTP_Mail_Send($OrderRS[0]['bill_email'],$mail_subject, $mail_content, CONTACT_MAIL);
				
				$file = fopen("tmp.html","a+");
				fwrite($file,"AFTER".$mail_subject);
				fwrite($file,"\n\n\n\n".$mail_content);
				fclose($file);
				
				//$OrderRS[0]['bill_email']  = "qqualdev@gmail.com";
				//SendMail($mail_subject,  $mail_content, $OrderRS[0]['bill_email'], config('Settings.ADMIN_MAIL'));
				$OtherData = ['toMail' => $OrderRS[0]['bill_email'], 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM];
			    OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRS[0],$OtherData);
				$err_msg = "Thank you for your payment. Your order will be processed as soon as possible. An Order Receipt E-mail has been sent to you.";
				
				// Session::flash('success',$err_msg);
				$res_arr['success'] = 1;
				$res_arr['err_msg'] = $err_msg;
				return $res_arr;
		
		/* }else{
			$err_msg = "Something went wrong, payment failed.";
			$res_arr['success'] = 0;
			$res_arr['err_msg'] = $err_msg;
			return $res_arr;
		} */
	}
	
	public function GetProductsNew($Flag,$CategoryID,$limit=12,$Filters=[])
	{
		$FilterCategories = [];
		$Offset = 0;
		$SortBy = "";
		$CatProdsQry = [];
		$ChildCatArr = [];
		if(count($Filters) > 0){
			foreach($Filters as $fkey => $Filter)
			{
				if($fkey == 'categories' && count($Filters) > 0){
					$ChildCatArr = $Filters['categories'];
				} 
			}
		} 
		if(count($ChildCatArr) == 0 && $CategoryID != '') {
			//$ChildCats = $this->GetChildCategories($CategoryID);
			$ChildCats = GetMainCatsTree([$CategoryID]);
			if(count($ChildCats['CatList']) > 0)
				$ChildCatArr = array_column($ChildCats['CatList'],'category_id');
			else
				$ChildCatArr = [$CategoryID];
		}
		if(isset($Filters['page']) && $Filters['page'] > 1){
				$Offset = ($Filters['page']-1) * $limit;
		}
		
		$SortBy = isset($Filters['sortby'])?$Filters['sortby']:'';
		
		
		$CatProdsQry = Products::select('products_id','sku','is_gift_wrap','short_description','maxtwodaydelivery','fragrance_family','formulation','size','coverage','finish','skin_type','product_name','vtype','imanufactureid','brand_id','is_atomizer',
								'fragrance_seasons','fragrance_occasion','fragrance_personality','image','current_stock','retail_price','cosmo_retail_price','pca_retail_price','minimum_stock','gender','new_arrival','featured','clearance','top_seller',
								'product_type','cosmo_sku','cosmo_current_stock','cosmo_wholesale_price','cosmo_our_price','pca_sku','pca_current_stock','pca_wholesale_price','pca_our_price',
								'nandansons_sku','nandansons_current_stock','nandansons_wholesale_price','nandansons_our_price','nandansons_retail_price','wholesale_price','our_price','sale_price',
								'vtype','variation_id','refine_feature','product_type');
		if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
            $CatProdsQry->whereIn('product_type',['both','retailer','wholesaler']);
        else
            $CatProdsQry->whereIn('product_type',['both','retailer']);						
		
		if(count($ChildCatArr) > 0){			
			$CatProdsQry->with(['prodCategory.Category' => function($q) use ($ChildCatArr){
				$q->whereIn('category_id',$ChildCatArr);
			}]);
		}				
						
		/*$CatProducts = $CatProdsQry->limit(10)->get()->filter(function($prod){
							return $prod->stock == 'In';
						});*/
		$Sizes = $CatProdsQry->select('size')->where('size','!=','')->distinct('size')->orderBy('size')->get();
		$Fragrances = $CatProdsQry->select('fragrance_family')->where('fragrance_family','!=','')->distinct('fragrance_family')->orderBy('fragrance_family')->get();						
		foreach($Fragrances as $Fragrance)
		{
			echo $Fragrance->fragrance_family."<br>";
		}
		
		$CatProducts = $CatProdsQry->limit(12)->groupBy(['variation_id'])->get();				
		
		$Products = [];
		foreach($CatProducts as $CatProd)
		{
			if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $CatProd->image) && trim($CatProd->image) != '') {
				$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($CatProd->image);
				$verP = filemtime($newimageVal);
				$CatProd->prod_image  = config('global.PRD_THUMB_IMG_URL') . $CatProd->image . "?ver=" . $verP;
			} else {
				$CatProd->prod_image = config('global.NO_IMAGE_THUMB');
			}
			$Products[] = $CatProd;
			//dd($Prod->current_stock_vendor);	
			//dd($Prod->retail_price_vendor);
			//dd($Prod->product_price);
			//dd($Prod->WebsiteStock);
			//dd($Prod->prodbrand->manufacturer);
			//dd($Prod->ratings->where('approved','=','Yes')->where('star_rate','!=','0')->sum('star_rate'));
			//dd($Prod->ratings->where('approved','=','Yes')->where('star_rate','!=','0')->count());
		}
		dd($Products);
	}
	
	public function GetProductsWithParms($ProductString='',$ManufactureID='',$CategoryID='',$ExcludeProductString='',$Flag='',$limit=10,$Filters=[]){
		$SldProducts = [];
		$VariationIDs = [];
		$CatArrVal = [];
		
		//if($ProductString != ""){
		if($ProductString != ""){
			if (strstr($ProductString, ','))
			{
				$ProductString = str_replace("  ", "", $ProductString);	
				$ProductString = str_replace(" ", "", $ProductString);
				$ProductString = str_replace(",", "#", $ProductString);	
			}
			$ProductString = trim($ProductString);
			//$ProductString = substr($ProductString,0,strlen($ProductString)-1);
			$ProductString = rtrim($ProductString,"#");
			$ProductString = explode("#", trim($ProductString));
		}
			
		if($ExcludeProductString != ""){
			if (strstr($ExcludeProductString, ','))
			{
				$ExcludeProductString = str_replace("  ", "", $ExcludeProductString);	
				$ExcludeProductString = str_replace(" ", "", $ExcludeProductString);
				$ExcludeProductString = str_replace(",", "#", $ExcludeProductString);	
			}
			$ExcludeProductString = trim($ExcludeProductString);
			//$ExcludeProductString = substr($ExcludeProductString,0,strlen($ExcludeProductString)-1);
			$ExcludeProductString = rtrim($ExcludeProductString,"#");
			$ExcludeProductString = explode("#", trim($ExcludeProductString));
		}
			
			
			if($CategoryID=='68' || $CategoryID=='70' || $CategoryID=='71' || $CategoryID=='69'){
				$CatArrVal = [$CategoryID];
			}
			
			if(isset($Filters['limit']) && $Filters['limit'] > 1){
				$limit = $Filters['limit'];
			}else{
				$limit = 10;
			}
			
			
			if(isset($Filters['page']) && $Filters['page'] > 1){
				$Offset = ($Filters['page']-1) * $limit;
			}else{
				$Offset = 0;
			}
			
			
			$ProdQry = DB::table('pu_products as po')
						->select('po.products_id','po.sku','po.is_gift_wrap','po.short_description','po.maxtwodaydelivery','po.fragrance_family','po.formulation','po.size','po.coverage','po.finish','po.skin_type','po.product_name','po.vtype','po.imanufactureid','po.brand_id','po.is_atomizer',
									'po.fragrance_seasons','po.fragrance_occasion','po.fragrance_personality','po.image','po.current_stock','po.retail_price','po.cosmo_retail_price','po.pca_retail_price','po.minimum_stock','po.gender','po.new_arrival','po.featured','po.clearance','po.top_seller',
									'po.product_type','po.cosmo_sku','po.cosmo_current_stock','po.cosmo_wholesale_price','po.cosmo_our_price','po.pca_sku','po.pca_current_stock','po.pca_wholesale_price','po.pca_our_price',
									'po.nandansons_sku','po.nandansons_current_stock','po.nandansons_wholesale_price','po.nandansons_our_price','po.nandansons_retail_price','po.wholesale_price','po.our_price','po.sale_price',
									'po.vtype','po.variation_id','po.refine_feature','m.vmanufacture','po.product_type','b.brand_name','pc.category_id','c.category_name','c.parent_id')			
						->addSelect(['TotalRate' => ProductsReview::select(DB::raw('SUM(star_rate)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','po.sku')
									,'TotalReview' => ProductsReview::select(DB::raw('COUNT(review_id)'))
										->where('approved','=','Yes')->where('star_rate','!=','0')->whereColumn('sku','po.sku')])			
						->join('pu_products_category as pc','po.products_id','=','pc.products_id')
						->join('pu_category as c','pc.category_id','=','c.category_id')
						->join('pu_brand as b','b.brand_id','=','po.brand_id')
						->join('pu_manufacture as m',function($join){
							$join->on('po.imanufactureid','=','m.imanufactureid');
							$join->on('b.imanufactureid','=','m.imanufactureid');
						})
						->where('po.status','=','1')
						->where('c.status','=','1');
						
			if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler'){
				$ProdQry->whereIn('po.product_type',['both','retailer','wholesaler']);
			}
			else{
				$ProdQry->whereIn('po.product_type',['both','retailer']);
			}
			
			if($ProductString != '' && count($ProductString) > 0){
				$ProdQry->whereIn('po.sku',$ProductString);
			}
			
			if($ExcludeProductString != '' && count($ExcludeProductString) > 0){
				$ProdQry->whereNotIn('po.sku',$ExcludeProductString);
			}
			
			if($ManufactureID != '' && $ManufactureID > 0){
				$ProdQry->where('m.imanufactureid','=',$ManufactureID);
			}
			if($CategoryID != '' && $CategoryID > 0){
				$ProdQry->where('c.category_id','=',$CategoryID);
			}
			
			$ProdQry->groupBy('po.products_id','po.variation_id');
			

			$Prods = $ProdQry->get();
			
			
			$SkipVariationID = [];
			$TotalProds = 0;
			$ProdIds=[];
			
			$TotalProducts = $Prods->count();
			
			if($Prods->count() > 0)
			{
				//$SliderCategory = $this->GetCategories($Prods);	
				
				foreach($Prods as $key => $Product) 
				{
					
					if($ProductString != '' && count($ProductString) > 0){
						if(!in_array($Product->sku,$ProductString)){
							continue;
						}
					}
					
					$Product = $this->SetProduct($Product);
					
					if($ProductString != '' && count($ProductString) > 0){
						if($Product->product_price <=0 && in_array($Product->sku,$ProductString))
						{
							$SkipVariationID[]=$Product->variation_id;
							continue;
						}
					}else{
						if($Product->product_price <=0)
						{
							$SkipVariationID[]=$Product->variation_id;
							continue;
						}
					}
					
					
					if(in_array($Product->variation_id,$SkipVariationID))
						continue;
					
					$Product->size_cnt = 0;
					if($Product->is_atomizer == "Yes" || $Product->stock == "Out")
					{
						$SizeCountArr = $this->getReferencedProducts_Counter_ListingDev($Product->products_id,$Product->variation_id,$CategoryID,$CatArrVal,$Prods);
						if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'No' && $SizeCountArr[0]->is_atomizer != '')
							$Product = $SizeCountArr[0];
						else if(is_array($SizeCountArr) && $SizeCountArr[0]->is_atomizer == 'Yes' && $SizeCountArr[0]->stock =='In')
							$Product = $SizeCountArr[0];
						
					}
					
					
					if($CategoryID == '2')
						$Product->category_id = $CategoryID;
					
					//Make Product Link Start
					$product_link = config('global.SITE_URL');
					
					
					$product_name = remove_special_chars($Product->product_name);
					$Product->product_url = SetProductURL($Product->products_id,$Product->product_name,$Product->category_id);
					//Make Product Link End
					
					if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $Product->image) && trim($Product->image) != '') {
						$newimageVal = config('global.PRD_THUMB_IMG_PATH')  . stripslashes($Product->image);
						$verP = filemtime($newimageVal);
						$Product->prod_image = config('global.PRD_THUMB_IMG_URL') . $Product->image . "?ver=" . $verP;
					} else {
						$Product->prod_image = config('global.NO_IMAGE_THUMB');
					}	
					
					if ($Product->gender == 'M'){
						$Product->gender = "sv-men";
						$Product->gendernames = "Men";
						$for_gender = ' for Men';
					} elseif ($Product->gender == 'W'){
						$Product->gender = "sv-women";
						$Product->gendernames = "Women";
						$for_gender = ' for Women';
					} elseif ($Product->gender == 'K'){
						$Product->gender = "sv-kids";
						$Product->gendernames = "Kids";
						$for_gender = ' for Kids';
					} elseif ($Product->gender == 'U'){
						$Product->gender = "sv-unisex";
						$Product->gendernames = "Unisex";
						$for_gender = ' Unisex';
					} else{
						$Product->gender = "";
						$Product->gendernames = "";
						$for_gender = '';
					}
					
					if($Product->vmanufacture != ''){
						$m_name = strtolower($Product->vmanufacture);
						$m_name = str_replace("#", "", $m_name);
						$m_name = str_replace("&", "", $m_name);
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace(" ", "-", $m_name);
						$Product->vmanufacture_link = config('global.SITE_URL').$m_name."/smid-".$Product->imanufactureid;
					}
					
					if($Product->brand_name != '' && $Product->vmanufacture != ''){
						$m_name = strtolower($Product->vmanufacture);
						$m_name = str_replace("#", "", $m_name);
						$m_name = str_replace("&", "", $m_name);
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace("  ", " ", trim($m_name));
						$m_name = str_replace(" ", "-", $m_name);
						$Product->referencedName = '<a href="' . $Product->product_url . '"><strong><u>' . $Product->brand_name . '</u></strong></a> by <a href='.$Product->vmanufacture_link.'><strong><u><br>'.$Product->vmanufacture.'</strong></u></a><br>'.$for_gender;
					}
					
					if(strlen($Product->product_name) > 45){
						$Product->product_name = substr($Product->product_name, 0, (45 - strlen($Product->product_name))). "..";
					} else {
						$Product->product_name = $Product->product_name;
					}

					if($Product->vmanufacture == '' || $Product->brand_name == ''){
						$Product->referencedName = '<a href="' . $Product->product_url . '"><u>' . $Product->product_name . '</u></a>';
					}
					
					if($Product->retail_price != '' && $Product->retail_price != '0.00' && isset($Product->product_price)){
						$yousave = ($Product->retail_price - $Product->product_price) / $Product->retail_price;
						$yousave = $yousave * 100;
						$yousave = number_format($yousave, 0);
						$yousaveprice = $Product->retail_price - $Product->product_price;
					}else{
						$yousave = 0;
						$yousaveprice = 0;
					}
					
					$Product->yousave = $yousave;
					$Product->maxyousave = number_format($Product->yousave, 0);
					$Product->yousaveprice = $yousaveprice;
					$Product->autoid = $key;
					
					$Product->sale_item = '0';
					if($Product->sale_price > 0 && strtolower(Session::get('eusertype'))!='wholesaler')
					{
						$Product->sale_item = '1';
					}
					
					$DealData = config('DealDetails');
					if(isset($DealData[$Product->sku]))
					{
						$Product->deal_price = $DealData[$Product->sku]['deal_price'];
						$Product->yousave = $DealData[$Product->sku]['yousave'];
						$Product->yousaveprice = $DealData[$Product->sku]['yousaveprice'];
					}
					$Product->short_description = strip_tags($Product->short_description);
					$Product->avg_rate = 0;
					$total_review = $Product->TotalReview;
					if($total_review > 0)
						$Product->avg_rate = GetProductAverageRating($Product->TotalReview,$Product->TotalRate);
					
					$VariationIDs[] = $Product->variation_id;
					$SldProducts[] = $Product;
				}	
			}
		//}
		$SldProducts = $this->CountOptions($VariationIDs,$SldProducts,$CatArrVal);
		
		if(count($SldProducts) > $Offset){
			$SldProducts = array_slice($SldProducts,$Offset,$limit);
		}
		
		$ProductsDetails = ['Products' => $SldProducts,'TotalProducts' => $TotalProducts];
		return $ProductsDetails;
	}
}
