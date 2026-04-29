<?php
	namespace App\Http\Controllers;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Route;
	use App\Http\Controllers\Traits\CommonTrait;
	use App\Http\Controllers\Traits\CartTrait;
	use App\Models\MetaInfo;
	use App\Models\Category;
	use App\Models\Manufacture;
	use App\Models\HomeImage;
	use DB;
	use Session;
	use Cache;
use stdClass;
use Illuminate\Support\Facades\Http;
class ProductController extends Controller
	{
		use CommonTrait;
		use CartTrait;
		public $PageData;
		
		public function __construct()
		{
			$PageType = 'NR';
			$MetaInfo = MetaInfo::where('type','=',$PageType)->get(); 
			if($MetaInfo->count() > 0 )
			{
				$this->PageData['meta_title'] = $MetaInfo[0]->meta_title;
				$this->PageData['meta_description'] = $MetaInfo[0]->meta_description;
				$this->PageData['meta_keywords'] = $MetaInfo[0]->meta_keywords;
			}
		}
		public function ProductList(Request $request)
		{
			
			
			$this->PageData['CSSFILES'] = ['listing.css','jquery-ui-slider.css','listpage.css','custom.css'];	
			$this->PageData['JSFILES'] = ['jquery.mCustomScrollbar.concat.min.js','jquery-ui-slider.min.js','listing_page.js'];
			
			$GTMDATA = ['page' => 'productlist', 'pagetype' => 'searchresults'];
			$GTMDATA['search_query'] = isset($request->keyword)?$request->keyword:'';
			$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
			
			
			//$request->keyword = '';
			
			if($request->keyword != ''){
				return $this->SearchByThirdParty($request);
			}
			else{
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
				
				$ProductsDetails = $this->GetProducts('ProductListPage',$ProdCat,24,$SetFilters);
				$Products = $ProductsDetails['Products'];
				$TotalProducts = $ProductsDetails['TotalProducts'];
				$this->PageData['Products'] = $Products;
				
				$criteoskutop3skus= "";
				if(count($Products) >= 3)
				{
					foreach($Products as $p => $Prod)
					{
						if($p < 3)
							$criteoskutop3skus.="'".$Prod->sku."',";
					}
				}
				if($criteoskutop3skus!="")
				{
					$criteoskutop3skus = substr($criteoskutop3skus,0,-1);
				}
				$this->PageData['criteoskutop3skus'] = $criteoskutop3skus;
				
				$this->PageData['TotalProducts'] = $TotalProducts;
				
				$SelCatDetails = config('CATEGORY_INFO');

				if(isset($request->category_id) && $request->category_id != '')
				{
					$CatDetails = Category::find($request->category_id);

					if(file_exists(config('global.CAT_IMAGE_PATH').$CatDetails->banner_image) and !empty($CatDetails->banner_image))
					{
						$newimageVal = config('global.CAT_IMAGE_PATH').$CatDetails->banner_image;
						$verP =filemtime($newimageVal);
						$banner_image = config('global.CAT_IMAGE_URL').$CatDetails->banner_image."?ver=".$verP;
						$this->PageData['bannerImage']  = $banner_image;
					}else{
						$this->PageData['bannerImage']  = config('global.SITE_IMAGES').'cat_banner_new.jpg';
					}

					if(file_exists(config('global.CAT_IMAGE_PATH').$CatDetails->mob_banner_image) and !empty($CatDetails->mob_banner_image))
					{
						$newimageVal = config('global.CAT_IMAGE_PATH').$CatDetails->mob_banner_image;
						$verP =filemtime($newimageVal);
						$mob_banner_image = config('global.CAT_IMAGE_URL').$CatDetails->mob_banner_image."?ver=".$verP;
						$this->PageData['mobile_banner_image']  = $mob_banner_image;
					}else{
						$this->PageData['mobile_banner_image']  = config('global.SITE_IMAGES').'cat_banner_new.jpg';
					}
				} else{
					$this->PageData['bannerImage'] = config('global.SITE_IMAGES').'cat_banner_new.jpg';
					$this->PageData['mobile_banner_image'] = config('global.SITE_IMAGES').'cat_banner_new.jpg';
				}

				$ExpCat = [];
				if(isset($request->category_id) && $request->category_id != '')
				{
					$ExpCat = explode(',',$request->category_id);
				}					
				if(isset($request->category_id) && $request->category_id != '' && count($ExpCat) == 1)
				{
					$CatInfo = $SelCatDetails['CatForProd'][$request->category_id];
					$CatDetails = Category::find($request->category_id);
					$this->PageData['PageTitle'] = ucwords(remove_special_chars($CatDetails->category_name));
					//dd($this->Bredcrum($request));
					//$Bredcrum = $this->GetBredcrum($CatDetails);
					$ParentID = ($CatInfo['root_parent_id'] != '0'?$CatInfo['root_parent_id']:$request->category_id);
					$this->PageData['Category'] = $CatDetails;
					if(file_exists(config('global.CAT_IMAGE_PATH').$CatDetails->banner_image) && !empty($CatDetails->banner_image)) {
						$newimageVal = config('global.CAT_IMAGE_PATH').$CatDetails->banner_image;
						$verP =filemtime($newimageVal);
						$banner_image = config('global.CAT_IMAGE_URL').$CatDetails->banner_image."?ver=".$verP;
						$this->PageData['bannerImage']  = $banner_image;
					}
					if(file_exists(config('global.CAT_IMAGE_PATH').$CatDetails->mob_banner_image) && !empty($CatDetails->mob_banner_image)) {
						$newimageVal = config('global.CAT_IMAGE_PATH').$CatDetails->mob_banner_image;
						$verP =filemtime($newimageVal);
						$mob_banner_image = config('global.CAT_IMAGE_URL').$CatDetails->mob_banner_image."?ver=".$verP;
						$this->PageData['mobile_banner_image']  = $mob_banner_image;
					}
										
				} else { 
					$ParentID = 0;
				}
				$Bredcrum = $this->Bredcrum($request);
				$this->PageData['Bredcrum'] = $Bredcrum['BredLink'];
				$this->PageData['PageTitle'] = $Bredcrum['PageTitle'];
				
				$this->PageData['MinPrice'] = count($Products)>0?min( array_column( $Products, 'product_price' )):0;
				$this->PageData['MaxPrice'] = count($Products)>0?max( array_column( $Products, 'product_price' )):0;
				
				$this->PageData['PageName'] = $request->category_name;
				$CatDetails = SetCatTree($ParentID);
				$CategoryList= $CatDetails['CatTree'];
				$this->PageData['Categories'] = $CategoryList;
				$this->PageData['Filters'] = $ProductsDetails['LeftFilters'];
				
				
			//	print_r($this->PageData);
				return view('product.listing')->with($this->PageData);
			}
		}
		
		public function SearchByThirdParty($request)
		{
			if(isset($request->keyword)){
				$searchKeyword = $request->keyword;
				$searchKeyword = str_replace("andd","&",$searchKeyword);
				$searchKeyword = str_replace("backslash","/",$searchKeyword);
				$searchKeyword = str_replace(" ","-",$searchKeyword);
				
				$perPage = 24;
				$begin = 1;
				
				$extraSearchQuery = $productTypeQry = '';
				
				
				if(Session::get('sess_icustomerid') != ''){
					if(strtolower(Session::get('eusertype')) == 'wholesaler'){
						$productTypeQry = '&filter.product_type=wholesaler';
					}
					if(strtolower(Session::get('eusertype')) == 'retailer'){
						$productTypeQry = '&filter.product_type=retailer';
						
					}
				}
				
			
				$curl = new \GuzzleHttp\Client();
				$initialRequest = "https://2mjz9y.a.searchspring.io/api/suggest/query?disableSpellCorrect=true&lang=en&pubId=2mjz9y&query=".$searchKeyword;
				$response = $curl->request('GET', $initialRequest);
				$Value = $response->getBody()->getContents();
				$jsonArrayResponse = json_decode($Value);
				
				
				if(!empty($jsonArrayResponse->suggested->text)){
					$suggestedQry = $jsonArrayResponse->suggested->text;
				}else{
					$suggestedQry = $searchKeyword;
				}

				$suggestedQry1 = rawurlencode($searchKeyword);
				$suggestedQry1 = str_replace("-","+",$suggestedQry1);
				
				
				$curl1 = new \GuzzleHttp\Client();
				//$initialRequest1 = "https://api.searchspring.net/api/search/search?siteId=2mjz9y&resultsFormat=native&resultsPerPage=".$perPage."&page=".$begin."&q=".$suggestedQry1.$extraSearchQuery.$productTypeQry;
				$initialRequest1 = "https://api.searchspring.net/api/search/search?siteId=2mjz9y&resultsFormat=native&resultsPerPage=".$perPage."&page=".$begin."&q=".$suggestedQry1;
				$response1 = $curl->request('GET', $initialRequest1);
				$Value1 = $response1->getBody()->getContents();
				$jsonArrayResponse1 = json_decode($Value1);
				
			 	$merchandising = json_decode(json_encode($jsonArrayResponse1->merchandising,JSON_UNESCAPED_SLASHES));
				$merchandising_content = (json_encode($merchandising->content)); 
				$Products = json_decode(json_encode($jsonArrayResponse1->results));
				$TotalProducts = $jsonArrayResponse1->pagination->totalResults;
				
				 $this->PageData['inlineBanner'] = isset($merchandising_content->inline->value) ? $merchandising_content->inline->value : ''; 
			
				$this->PageData['Products'] = $Products;
				$this->PageData['TotalProducts'] = $TotalProducts;
				$this->PageData['PageTitle'] = $request->keyword;
				$filters = json_decode(json_encode($jsonArrayResponse1->facets), true);
				
				$allFilters = array();
				
				$this->PageData['MinPrice'] = $this->PageData['MaxPrice'] = 0;
				
				for($i=0;$i<count($filters);$i++){
					
					$filters[$i]['Attr'] =  $filters[$i]['Selected'] =  $filters[$i]['Data'] = array();
					
					if($filters[$i]['field'] == 'price'){
						//$this->PageData['MinPrice'] = $filters[$i]['range'][0];
					//	$this->PageData['MaxPrice'] = $filters[$i]['range'][1];
					}
					else{
						$filters[$i]['Attr']['title'] = $filters[$i]['label'];
						$filters[$i]['Attr']['id'] = $filters[$i]['field'];
						$filters[$i]['Attr']['filterval'] = 'key';
						//$filters[$i]['Attr']['status'] = $filters[$i]['active'];
						
						if($filters[$i]['field'] == 'brand'){
							$filters[$i]['Attr']['name'] = 'mid';
						}else if($filters[$i]['field'] == 'category'){
							$filters[$i]['Attr']['name'] = 'cid';
						}else if($filters[$i]['field'] == 'size'){
							$filters[$i]['Attr']['name'] = 'size';
						}else if($filters[$i]['field'] == 'badges'){
							$filters[$i]['Attr']['name'] = 'special';
						}else{
							$filters[$i]['Attr']['name'] = '';
						}
						
						$valuesArr = $filters[$i]['values'];
						$ItemsData = [];
						foreach ($valuesArr as $item) {
							
							if(isset($item['value']) && $item['value'] != ''){
								
								$item['value'] = str_replace(":","doubledot",$item['value']);
								$item['value'] = str_replace(".","dot",$item['value']);
								$item['value'] = str_replace("-","dash",$item['value']);
								$item['value'] = str_replace("&","andd",$item['value']);
								$item['value'] = str_replace("'","singlecomma",$item['value']);
								$item['value'] = str_replace(" ","_",$item['value']);
								
								$ItemsData[$item['value']] = $item['label'];
							}
						}
						$filters[$i]['Data'] = $ItemsData;
						
						$allFilters[][$filters[$i]['label']] = $filters[$i];
					}
				}
				
				$this->PageData['Filters'] = $allFilters;
			
				$Bredcrum = $this->Bredcrum($request);
				$this->PageData['Bredcrum'] = $Bredcrum['BredLink'];
				return view('product.listing_search')->with($this->PageData);
			}
		}
		
		public function BrandProductList(Request $request)
		{
			$this->PageData['CSSFILES'] = ['listing.css','jquery-ui-slider.css','listpage.css','custom.css'];	
			$this->PageData['JSFILES'] = ['jquery.mCustomScrollbar.concat.min.js','jquery-ui-slider.min.js','listing_page.js'];
			$this->PageData['ListFrom'] = 'Brand'; 
			$BrandID = $request->mid;
			
			$BrandData = Manufacture::where('status','=','1')->where('imanufactureid','=',$BrandID)->get();
			if(!$BrandData || $BrandData->count() == 0)
				return redirect('/');
			
			$this->PageData['PageTitle'] = ucwords(stripcslashes(($BrandData[0]->vmanufacture))); 
			$this->PageData['SelImanufacturer'] = $BrandID;
			$BrandCats = DB::table('pu_products as po')
					->join('pu_products_category as pc','po.products_id','=','pc.products_id')
					->join('pu_category as c','pc.category_id','=','c.category_id')
					->join('pu_manufacture as m','po.imanufactureid','=','m.imanufactureid')
					->select('pc.category_id','c.parent_id','c.category_name')
					->where('po.status','=','1')
					->where('c.status','=','1')
					->where('c.parent_id','=','0')
					->groupBy('c.category_id')->get();
			$CatArray=[];
			$Cats = [];		
			if($BrandCats && $BrandCats->count() > 0)
			{
				$CatArray=[];
				foreach($BrandCats as $BrandCat)
				{
					if($BrandCat->parent_id != 0){
						$CParent = $BrandCat->parent_id;
						$BCat = Category::find($BrandCat->parent_id);
						if($BCat && $BCat->count() > 0 && $BCat->parent_id != 0)
							$CParent = $BCat->category_id;
					}else{
						$CParent = $BrandCat->category_id;
					}
					$Cats[] = $CParent;
				}
			}
			$CatArray = $this->GetCatTree($Cats);
			
			$SetFilters = $this->SetFilters($request);
			$this->PageData['SelCat'] = '';
			$this->PageData['SelectedCat'] = [];
			$ProdCat='';
			
			if(isset($SetFilters['categories']) && count($SetFilters['categories']) > 0){
				$ProdCat = $SetFilters['categories'];
				$this->PageData['SelectedCat'] = $ProdCat;
				$this->PageData['SelCat'] = implode(',',$ProdCat);
			}
			
			$ProductsDetails = $this->GetProducts('ProductListPage',$ProdCat,24,$SetFilters);
			$Products = $ProductsDetails['Products'];
			$TotalProducts = $ProductsDetails['TotalProducts'];
			$this->PageData['Products'] = $Products;
			$this->PageData['TotalProducts'] = $TotalProducts;
			$Bredcrum = '';
			if($request->brand_name == 'new-arrivals')
			{
				$PageTitle = ucwords(str_replace('-',' ',$request->brand_name));
				$this->PageData['PageTitle'] = $PageTitle;
				$Bredcrum = $this->GetBredcrum('',$PageTitle);
			}
			$this->PageData['bannerImage'] = config('global.SITE_IMAGES').'cat_banner_new.jpg';
			$this->PageData['mobile_banner_image'] = config('global.SITE_IMAGES').'cat_banner_new.jpg';
			$this->PageData['PageName'] = $request->brand_name;
			
			$this->PageData['Categories'] = $CatArray;
			$this->PageData['Bredcrum'] = $Bredcrum;
			
			$this->PageData['MinPrice'] = count($Products)>0?min( array_column( $Products, 'product_price' )):0;
			$this->PageData['MaxPrice'] = count($Products)>0?max( array_column( $Products, 'product_price' )):0;
			
			$this->PageData['Filters'] = $this->GetFilters($Products,$SetFilters);	
			
			return view('product.listing')->with($this->PageData);
		}
		
		/*public function SetFilters($Params)
		{
			$ExpFilters = explode("/",$Params->filters);
			if(isset($Params->mid) && $Params->mid != '')
				$ExpFilters[]='mid-'.$Params->mid;
			
			$AllFilters = [];
			$ParamString = ['cid' => 'categories', 'mid' => 'brands','family' => 'fragrance_family', 'type' => 'vtype', 
					'formulation' => 'formulation', 'stock' => 'stock', 'size' => 'size', 
					'special' => 'special', 'coverage' => 'coverage', 'finish' => 'finish', 
					'skin' => 'skin_type'];		
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
		}*/
		
		public function ProductListPage(Request $request)
		{
			
			$Filters = json_decode($request->filters,true);
			$Filters['page'] = $request->page;
			
			if($request->keyword != ''){
				return $this->ThirdPartyListPage($request);
			}
			else{
				$ProductsDetails = $this->GetProducts('ProductListPage',$request->category_id,24,$Filters);
				$Products = $ProductsDetails['Products'];
				$TotalProducts = $ProductsDetails['TotalProducts'];
				$BredcrumHTML = $this->BredcrumAjax($request);
				$this->PageData['Products'] = $Products;
				$this->PageData['TotalProducts'] = $TotalProducts;
				$ProductHTML = view('product.list')->with($this->PageData)->render();
				//return view('product.list')->with($this->PageData);
				return response()->json(array('TotalProducts' => $TotalProducts, 'ProductHTML'=>$ProductHTML, 'BredcrumHTML' => $BredcrumHTML));
			}
		}
		
		public function ThirdPartyListPage(Request $request)
		{
			
			$Filters = json_decode($request->filters,true);
			$Filters['page'] = $request->page;
			
			//if($request->keyword != ''){
				
				$searchKeyword = $request->keyword;
				$searchKeyword = str_replace("andd","&",$searchKeyword);
				$searchKeyword = str_replace("backslash","/",$searchKeyword);
				$searchKeyword = str_replace(" ","-",$searchKeyword);
				
				$perPage = 24;
				$begin = $Filters['page'];
				
				$extraSearchQuery = $productTypeQry = '';
				
				if(Session::get('sess_icustomerid') != ''){
					if(strtolower(Session::get('eusertype')) == 'wholesaler'){
						$productTypeQry = '&filter.product_type=wholesaler';
					}
					if(strtolower(Session::get('eusertype')) == 'retailer'){
						$productTypeQry = '&filter.product_type=retailer';
					}
				}
				
				if($Filters['sortby'] != ''){
					if($Filters['sortby'] == 'priceLH'){
						$extraSearchQuery .= '&sort.price=asc';
					}
					else if($Filters['sortby'] == 'priceHL'){
						$extraSearchQuery .= '&sort.price=desc';
					}
					else if($Filters['sortby'] == 'ATZ'){
						$extraSearchQuery .= '&sort.name=asc';
					}
					else if($Filters['sortby'] == 'ZTA'){
						$extraSearchQuery .= '&sort.name=desc';
					}
				}
				
				if(!empty($Filters['brands'])){
					for($b=0;$b<count($Filters['brands']);$b++){
						$selBrands = $Filters['brands'][$b];
						$selBrands = str_replace("doubledot",":",$selBrands);
						$selBrands = str_replace("dot",".",$selBrands);
						$selBrands = str_replace("andd","&",$selBrands);
						$selBrands = str_replace("dash","-",$selBrands);
						$selBrands = str_replace("singlecomma","'",$selBrands);
						$selBrands = str_replace("_"," ",$selBrands);
						
						$extraSearchQuery .= '&filter.brand='.rawurlencode($selBrands);
					}
				}
				
				if(!empty($Filters['categories'])){
					for($c=0;$c<count($Filters['categories']);$c++){
						$selCategories = $Filters['categories'][$c];
						$selCategories = str_replace("doubledot",":",$selCategories);
						$selCategories = str_replace("dot",".",$selCategories);
						$selCategories = str_replace("andd","&",$selCategories);
						$selCategories = str_replace("dash","-",$selCategories);
						$selCategories = str_replace("singlecomma","'",$selCategories);
						$selCategories = str_replace("_"," ",$selCategories);
						
						$extraSearchQuery .= '&filter.category='.rawurlencode($selCategories);
					}
				}
				
				if(!empty($Filters['size'])){
					for($s=0;$s<count($Filters['size']);$s++){
						$selSizes = $Filters['size'][$s];
						$selSizes = str_replace("doubledot",":",$selSizes);
						$selSizes = str_replace("dot",".",$selSizes);
						$selSizes = str_replace("andd","&",$selSizes);
						$selSizes = str_replace("dash","-",$selSizes);
						$selSizes = str_replace("singlecomma","'",$selSizes);
						$selSizes = str_replace("_"," ",$selSizes);
						
						$extraSearchQuery .= '&filter.size='.rawurlencode($selSizes);
					}
				}
				
				if(!empty($Filters['special'])){
					for($sp=0;$sp<count($Filters['special']);$sp++){
						$selSpecials = $Filters['special'][$sp];
						$selSpecials = str_replace("doubledot",":",$selSpecials);
						$selSpecials = str_replace("dot",".",$selSpecials);
						$selSpecials = str_replace("andd","&",$selSpecials);
						$selSpecials = str_replace("dash","-",$selSpecials);
						$selSpecials = str_replace("singlecomma","'",$selSpecials);
						$selSpecials = str_replace("_"," ",$selSpecials);
						
						$extraSearchQuery .= '&filter.badges='.rawurlencode($selSpecials);
					}
				}
				
				if(!empty($Filters['formulation'])){
					for($f=0;$f<count($Filters['formulation']);$f++){
						$selFormulations = $Filters['formulation'][$f];
						$selFormulations = str_replace("doubledot",":",$selFormulations);
						$selFormulations = str_replace("dot",".",$selFormulations);
						$selFormulations = str_replace("andd","&",$selFormulations);
						$selFormulations = str_replace("dash","-",$selFormulations);
						$selFormulations = str_replace("singlecomma","'",$selFormulations);
						$selFormulations = str_replace("_"," ",$selFormulations);
						
						$extraSearchQuery .= '&filter.formulation='.rawurlencode($selFormulations);
					}
				}
				
				if(!empty($Filters['features'])){
					for($f=0;$f<count($Filters['features']);$f++){
						$selFeatures = $Filters['features'][$f];
						$selFeatures = str_replace("doubledot",":",$selFeatures);
						$selFeatures = str_replace("dot",".",$selFeatures);
						$selFeatures = str_replace("andd","&",$selFeatures);
						$selFeatures = str_replace("dash","-",$selFeatures);
						$selFeatures = str_replace("singlecomma","'",$selFeatures);
						$selFeatures = str_replace("_"," ",$selFeatures);
						
						$extraSearchQuery .= '&filter.by_feature='.rawurlencode($selFeatures);
					}
				}
				
				if(!empty($Filters['fragrance_family'])){
					for($f=0;$f<count($Filters['fragrance_family']);$f++){
						$selFragrances = $Filters['fragrance_family'][$f];
						$selFragrances = str_replace("doubledot",":",$selFragrances);
						$selFragrances = str_replace("dot",".",$selFragrances);
						$selFragrances = str_replace("andd","&",$selFragrances);
						$selFragrances = str_replace("dash","-",$selFragrances);
						$selFragrances = str_replace("singlecomma","'",$selFragrances);
						$selFragrances = str_replace("_"," ",$selFragrances);
						
						$extraSearchQuery .= '&filter.fragrance_family='.rawurlencode($selFragrances);
					}
				}
				
				if(!empty($Filters['vtype'])){
					for($f=0;$f<count($Filters['vtype']);$f++){
						$selTypes = $Filters['vtype'][$f];
						$selTypes = str_replace("doubledot",":",$selTypes);
						$selTypes = str_replace("dot",".",$selTypes);
						$selTypes = str_replace("andd","&",$selTypes);
						$selTypes = str_replace("dash","-",$selTypes);
						$selTypes = str_replace("singlecomma","'",$selTypes);
						$selTypes = str_replace("_"," ",$selTypes);
						
						$extraSearchQuery .= '&filter.type='.rawurlencode($selTypes);
					}
				}
				
				if(!empty($Filters['coverage'])){
					for($f=0;$f<count($Filters['coverage']);$f++){
						$selCoverage = $Filters['coverage'][$f];
						$selCoverage = str_replace("doubledot",":",$selCoverage);
						$selCoverage = str_replace("dot",".",$selCoverage);
						$selCoverage = str_replace("andd","&",$selCoverage);
						$selCoverage = str_replace("dash","-",$selCoverage);
						$selCoverage = str_replace("singlecomma","'",$selCoverage);
						$selCoverage = str_replace("_"," ",$selCoverage);
						
						$extraSearchQuery .= '&filter.coverage='.rawurlencode($selCoverage);
					}
				}
				
				if(!empty($Filters['finish'])){
					for($f=0;$f<count($Filters['finish']);$f++){
						$selFinish = $Filters['finish'][$f];
						$selFinish = str_replace("doubledot",":",$selFinish);
						$selFinish = str_replace("dot",".",$selFinish);
						$selFinish = str_replace("andd","&",$selFinish);
						$selFinish = str_replace("dash","-",$selFinish);
						$selFinish = str_replace("singlecomma","'",$selFinish);
						$selFinish = str_replace("_"," ",$selFinish);
						
						$extraSearchQuery .= '&filter.finish='.rawurlencode($selFinish);
					}
				}
				
				if(!empty($Filters['skin_type'])){
					for($f=0;$f<count($Filters['skin_type']);$f++){
						$selSkinTypes = $Filters['skin_type'][$f];
						$selSkinTypes = str_replace("doubledot",":",$selSkinTypes);
						$selSkinTypes = str_replace("dot",".",$selSkinTypes);
						$selSkinTypes = str_replace("andd","&",$selSkinTypes);
						$selSkinTypes = str_replace("dash","-",$selSkinTypes);
						$selSkinTypes = str_replace("singlecomma","'",$selSkinTypes);
						$selSkinTypes = str_replace("_"," ",$selSkinTypes);
						
						$extraSearchQuery .= '&filter.skin_type='.rawurlencode($selSkinTypes);
					}
				}
				
				if(!empty($Filters['fragrance_seasons'])){
					for($f=0;$f<count($Filters['fragrance_seasons']);$f++){
						$selSeasons = $Filters['fragrance_seasons'][$f];
						$selSeasons = str_replace("doubledot",":",$selSeasons);
						$selSeasons = str_replace("dot",".",$selSeasons);
						$selSeasons = str_replace("andd","&",$selSeasons);
						$selSeasons = str_replace("dash","-",$selSeasons);
						$selSeasons = str_replace("singlecomma","'",$selSeasons);
						$selSeasons = str_replace("_"," ",$selSeasons);
						
						$extraSearchQuery .= '&filter.seasons='.rawurlencode($selSeasons);
					}
				}
				
				if(!empty($Filters['fragrance_occasion'])){
					for($f=0;$f<count($Filters['fragrance_occasion']);$f++){
						$selOccasion = $Filters['fragrance_occasion'][$f];
						$selOccasion = str_replace("doubledot",":",$selOccasion);
						$selOccasion = str_replace("dot",".",$selOccasion);
						$selOccasion = str_replace("andd","&",$selOccasion);
						$selOccasion = str_replace("dash","-",$selOccasion);
						$selOccasion = str_replace("singlecomma","'",$selOccasion);
						$selOccasion = str_replace("_"," ",$selOccasion);
						
						$extraSearchQuery .= '&filter.occasion='.rawurlencode($selOccasion);
					}
				}
				
				if(!empty($Filters['fragrance_personality'])){
					for($f=0;$f<count($Filters['fragrance_personality']);$f++){
						$selPersonality = $Filters['fragrance_personality'][$f];
						$selPersonality = str_replace("doubledot",":",$selPersonality);
						$selPersonality = str_replace("dot",".",$selPersonality);
						$selPersonality = str_replace("andd","&",$selPersonality);
						$selPersonality = str_replace("dash","-",$selPersonality);
						$selPersonality = str_replace("singlecomma","'",$selPersonality);
						$selPersonality = str_replace("_"," ",$selPersonality);
						
						$extraSearchQuery .= '&filter.personality='.rawurlencode($selPersonality);
					}
				}
				
				if($Filters['ochangeprice'] == '1' && $Filters['minprice'] != '' && $Filters['maxprice'] != ''){
					$extraSearchQuery .= '&filter.price.low='.rawurlencode($Filters['minprice']).'&filter.price.high='.rawurlencode($Filters['maxprice']);
				}
				
				$curl = new \GuzzleHttp\Client();
				$initialRequest = "https://faltym.a.searchspring.io/api/suggest/query?disableSpellCorrect=true&lang=en&pubId=faltym&query=".$searchKeyword;
				$response = $curl->request('GET', $initialRequest);
				$Value = $response->getBody()->getContents();
				$jsonArrayResponse = json_decode($Value);
				
				
				if(!empty($jsonArrayResponse->suggested->text)){
					$suggestedQry = $jsonArrayResponse->suggested->text;
				}else{
					$suggestedQry = $searchKeyword;
				}
				
				$suggestedQry1 = rawurlencode($searchKeyword);
				$suggestedQry1 = str_replace("-","+",$suggestedQry1);
				
				
				$curl1 = new \GuzzleHttp\Client();
				//$initialRequest1 = "https://api.searchspring.net/api/search/search?siteId=faltym&resultsFormat=native&resultsPerPage=".$perPage."&page=".$begin."&q=".$suggestedQry1.$extraSearchQuery;
				$initialRequest1 = "https://api.searchspring.net/api/search/search?siteId=faltym&resultsFormat=native&resultsPerPage=".$perPage."&page=".$begin."&q=".$searchKeyword.$extraSearchQuery.$productTypeQry;
				$response1 = $curl->request('GET', $initialRequest1);
				$Value1 = $response1->getBody()->getContents();
				$jsonArrayResponse1 = json_decode($Value1);
				
				
				$Products = json_decode(json_encode($jsonArrayResponse1->results));
				$TotalProducts = $jsonArrayResponse1->pagination->totalResults;
				
				
				$filtersAll = json_decode(json_encode($jsonArrayResponse1->facets), true);
				
				$TotalProducts = $jsonArrayResponse1->pagination->totalResults;
				
				$this->PageData['Products'] = $Products;
				$this->PageData['TotalProducts'] = $TotalProducts;
				
				$MinPrice = $MaxPrice = 0;
				
				$allFilters = array();
				
				$setMinPrice = $setMaxPrice = '';
				
				for($i=0;$i<count($filtersAll);$i++){
					
					$filtersAll[$i]['Attr'] =  $filtersAll[$i]['Selected'] =  $filtersAll[$i]['Data'] = array();
					
					if($filtersAll[$i]['field'] == 'price'){
						/*$this->PageData['MinPrice'] = $filtersAll[$i]['range'][0];
						$this->PageData['MaxPrice'] = $filtersAll[$i]['range'][1];*/
						$setMinPrice = $filtersAll[$i]['range'][0];
						$setMaxPrice = $filtersAll[$i]['range'][1];
					}
					else{
						$filtersAll[$i]['Attr']['title'] = $filtersAll[$i]['label'];
						$filtersAll[$i]['Attr']['id'] = $filtersAll[$i]['field'];
						$filtersAll[$i]['Attr']['filterval'] = 'key';
						//$filtersAll[$i]['Attr']['status'] = $filtersAll[$i]['active'];
						
						if($filtersAll[$i]['field'] == 'brand'){
							$filtersAll[$i]['Attr']['name'] = 'mid';
							$filtersAll[$i]['Selected'] = $Filters['brands'];
						}else if($filtersAll[$i]['field'] == 'category'){
							$filtersAll[$i]['Attr']['name'] = 'cid';
							$filtersAll[$i]['Selected'] = $Filters['categories'];
						}else if($filtersAll[$i]['field'] == 'size'){
							$filtersAll[$i]['Attr']['name'] = 'size';
							$filtersAll[$i]['Selected'] = $Filters['size'];
						}else if($filtersAll[$i]['field'] == 'badges'){
							$filtersAll[$i]['Attr']['name'] = 'special';
							$filtersAll[$i]['Selected'] = $Filters['special'];
						}else if($filtersAll[$i]['field'] == 'formulation'){
							$filtersAll[$i]['Attr']['name'] = '';
							$filtersAll[$i]['Selected'] = $Filters['formulation'];
						}else if($filtersAll[$i]['field'] == 'by_feature'){
							$filtersAll[$i]['Attr']['name'] = '';
							$filtersAll[$i]['Selected'] = $Filters['features'];
						}else if($filtersAll[$i]['field'] == 'fragrance_family'){
							$filtersAll[$i]['Attr']['name'] = '';
							$filtersAll[$i]['Selected'] = $Filters['fragrance_family'];
						}else if($filtersAll[$i]['field'] == 'type'){
							$filtersAll[$i]['Attr']['name'] = '';
							$filtersAll[$i]['Selected'] = $Filters['vtype'];
						}else if($filtersAll[$i]['field'] == 'coverage'){
							$filtersAll[$i]['Attr']['name'] = '';
							$filtersAll[$i]['Selected'] = $Filters['coverage'];
						}else if($filtersAll[$i]['field'] == 'finish'){
							$filtersAll[$i]['Attr']['name'] = '';
							$filtersAll[$i]['Selected'] = $Filters['finish'];
						}else if($filtersAll[$i]['field'] == 'skin_type'){
							$filtersAll[$i]['Attr']['name'] = '';
							$filtersAll[$i]['Selected'] = $Filters['skin_type'];
						}else if($filtersAll[$i]['field'] == 'seasons'){
							$filtersAll[$i]['Attr']['name'] = '';
							$filtersAll[$i]['Selected'] = $Filters['fragrance_seasons'];
						}else if($filtersAll[$i]['field'] == 'occasion'){
							$filtersAll[$i]['Attr']['name'] = '';
							$filtersAll[$i]['Selected'] = $Filters['fragrance_occasion'];
						}else if($filtersAll[$i]['field'] == 'personality'){
							$filtersAll[$i]['Attr']['name'] = '';
							$filtersAll[$i]['Selected'] = $Filters['fragrance_personality'];
						}
						
						$valuesArr = $filtersAll[$i]['values'];
						
						$ItemsData = [];
						foreach ($valuesArr as $item) {
							
							if(isset($item['value']) && $item['value'] != ''){
								
								$item['value'] = str_replace(":","doubledot",$item['value']);
								$item['value'] = str_replace(".","dot",$item['value']);
								$item['value'] = str_replace("-","dash",$item['value']);
								$item['value'] = str_replace("&","andd",$item['value']);
								$item['value'] = str_replace("'","singlecomma",$item['value']);
								$item['value'] = str_replace(" ","_",$item['value']);
								
								$ItemsData[$item['value']] = $item['label'];
							}
						}
						$filtersAll[$i]['Data'] = $ItemsData;
						
						$allFilters[][$filtersAll[$i]['label']] = $filtersAll[$i];
					}
				}
				
				$this->PageData['Filters'] = $allFilters;
				//$this->PageData['OMinPrice'] = $MinPrice;
				//$this->PageData['OMaxPrice'] = $MaxPrice;
				
				$MinPrice = $Filters['minprice'];
				$MaxPrice = $Filters['maxprice'];
				$this->PageData['MinPrice'] = $MinPrice;
				$this->PageData['MaxPrice'] = $MaxPrice;
				
				$this->PageData['OMinPrice'] = $Filters['ominprice'];
				$this->PageData['OMaxPrice'] = $Filters['omaxprice'];
				$this->PageData['OChangePrice'] = $Filters['ochangeprice'];
				
				if($Filters['ochangeprice'] == ''){
					$this->PageData['OChangePrice'] = 0;
				}
				
				
				if($MinPrice == ''){
					$MinPrice = $setMinPrice;
					$this->PageData['MinPrice'] = $MinPrice;
					
					$Filters['ominprice'] = $setMinPrice;
					$this->PageData['OMinPrice'] = $setMinPrice;
				}
				
				if($MinPrice == '' && $setMinPrice == ''){
					$MinPrice = $Filters['ominprice'];
					$this->PageData['MinPrice'] = $MinPrice;
				}
				
				if($MaxPrice == ''){
					$MaxPrice = $setMaxPrice;
					$this->PageData['MaxPrice'] = $MaxPrice;
					
					$Filters['omaxprice'] = $setMaxPrice;
					$this->PageData['OMaxPrice'] = $setMaxPrice;
				}
				
				if($MaxPrice == '' && $setMaxPrice == ''){
					$MaxPrice = $Filters['omaxprice'];
					$this->PageData['MaxPrice'] = $MaxPrice;
				}
				
				$BredcrumHTML = $this->BredcrumAjax($request);
				$ProductHTML = view('product.list_search')->with($this->PageData)->render();
				$allFilters = view('product.filter_search')->with($this->PageData)->render();
				
				
				//return response()->json(array('TotalProducts' => $TotalProducts, 'ProductHTML'=>$ProductHTML, 'Filters'=>$allFilters, 'OMinPrice'=>$MinPrice, 'OMaxPrice'=>$MaxPrice, 'MinPrice'=>$Filters['minprice'], 'MaxPrice'=>$Filters['maxprice'], 'BredcrumHTML' => $BredcrumHTML));
				return response()->json(array('TotalProducts' => $TotalProducts, 'ProductHTML'=>$ProductHTML, 'Filters'=>$allFilters, 'OMinPrice'=>$Filters['ominprice'], 'OMaxPrice'=>$Filters['omaxprice'], 'MinPrice'=>$MinPrice, 'MaxPrice'=>$MaxPrice, 'BredcrumHTML' => $BredcrumHTML));
			//}
		}
		
		public function Dealofweek(Request $request)
		{
			$this->PageData['CSSFILES'] = ['jquery-ui-slider.css','deal-list.css','custom.css'];	
			$this->PageData['JSFILES'] = ['moment.min.js','jquery-ui-slider.min.js','dealofweek.js'];
			
			$DealDetails = HomeImage::where('status','=','1')
							->where('start_date','<=',date('Y-m-d'))->where('end_date','>=',date('Y-m-d'))
							->where('section','=','DEAL OF THE WEEK')->get();
					
			$this->PageData['dealOfTheWeekBanner'] = '';
			$this->PageData['dealOfTheWeekBannerMobile'] = '';
			if($DealDetails && $DealDetails->count() > 0)
			{
				$banner_url = $DealDetails[0]->link;
				$this->PageData['banner_url'] = str_replace('{$Site_URL}',config('global.SITE_URL'),$banner_url);
				
				$banner_img = $DealDetails[0]->banner;
				$mobile_banner_img = $DealDetails[0]->mobile_banner_img;
				if(!empty($banner_img) && file_exists(config('global.HOME_IMAGE_PATH').$banner_img)) 
				{ 
					$this->PageData['dealOfTheWeekBanner'] = config('global.HOME_IMAGE_PATH').$banner_img; 
				}
				if(!empty($mobile_banner_img) && file_exists(config('global.HOME_IMAGE_PATH').$mobile_banner_img)) 
				{ 
					$this->PageData['dealOfTheWeekBannerMobile'] = config('global.HOME_IMAGE_PATH').$mobile_banner_img; 
				}
			}
			
			$SetFilters = $this->SetFilters($request);
			$this->PageData['SelBrand'] = '';
			if(isset($SetFilters['brands']) && count($SetFilters['brands']) > 0)
				$this->PageData['SelBrand'] = $SetFilters['brands'][0];	
				//$this->PageData['SelBrand'] = implode(",",$SetFilters['brands']);
			
			$this->PageData['SelSize'] = '';
			if(isset($SetFilters['size']) && count($SetFilters['size']) > 0)
				$this->PageData['SelSize'] = implode(",",$SetFilters['size']);
			
			$this->PageData['SelKey'] = '';
			if(isset($SetFilters['key']) && $SetFilters['key'] != '')
				$this->PageData['SelKey'] = $SetFilters['key'];
			
			$this->PageData['SelStock'] = '';
			if(isset($SetFilters['stock']) && in_array('In',$SetFilters['stock']))
				$this->PageData['SelStock'] = 'checked';
			
			
			$ProductsDetails = $this->GetProducts('DealofweekPage','',12,$SetFilters);
			
			$Products = $ProductsDetails['Products'];
			$TotalProducts = $ProductsDetails['TotalProducts'];
			$this->PageData['DealProds'] = $Products;
			$this->PageData['TotalProducts'] = $TotalProducts;
			$BrandList = [];
			$SizeList = [];
			if(count($ProductsDetails['LeftFilters']) > 0)
			{
				foreach($ProductsDetails['LeftFilters'] as $DealFilter)
				{
					if(array_key_exists('Brands',$DealFilter)){
						$BrandList = $DealFilter['Brands']['Data'];
					}
					if(array_key_exists('Size',$DealFilter)){
						$SizeList = $DealFilter['Size']['Data'];
					}
				}
			}
			
			if((isset($SetFilters['brands']) && count($SetFilters['brands']) > 0) || (isset($SetFilters['size']) && count($SetFilters['size']) > 0))
			{
				$this->PageData['BrandList'] = Cache::get('DealBrands');
				$this->PageData['SizeList'] = Cache::get('DealSizes');
			} else {
				Cache::put('DealBrands', $BrandList);
				$this->PageData['BrandList'] = Cache::get('DealBrands');
				Cache::put('DealSizes', $SizeList);
				$this->PageData['SizeList'] = Cache::get('DealSizes');
			}	

			//$this->PageData['SizeList'] = $SizeList;
			$this->PageData['MinPrice'] = count($Products)>0?min( array_column( $Products, 'deal_price' )):0;
			$this->PageData['MaxPrice'] = count($Products)>0?max( array_column( $Products, 'deal_price' )):0;
			$this->PageData['Deal'] = $this->HomeDealOfWeek();
			return view('product.dealofweek')->with($this->PageData);
		}
		
		
	    public function Maxtwoday(Request $request)
		{
			$this->PageData['CSSFILES'] = ['jquery-ui-slider.css','deal-list.css','custom.css'];	
			$this->PageData['JSFILES'] = ['moment.min.js','jquery-ui-slider.min.js','maxtwoday.js'];
			
			$MaxtwoDetails = HomeImage::where('status','=','1')
							->where('start_date','<=',date('Y-m-d'))->where('end_date','>=',date('Y-m-d'))->where('title','=','max-2-day')
							->where('section','=','HOME FEATURED BRANDS')->get();
					
			
			$this->PageData['Maxtwodaybanner'] = '';
			$this->PageData['MaxtwodaybannerMobile'] = '';
			if($MaxtwoDetails && $MaxtwoDetails->count() > 0)
			{
				//dd($MaxtwoDetails); 
				$banner_url = $MaxtwoDetails[0]->link;
				$this->PageData['banner_url'] = str_replace('{$Site_URL}',config('global.SITE_URL'),$banner_url);
				$this->PageData['MaxtwodayTitle'] = $MaxtwoDetails[0]->title;
				$banner_img = $MaxtwoDetails[0]->home_image;
				$mobile_banner_img = $MaxtwoDetails[0]->mobile_image;
				if(!empty($banner_img) && file_exists(config('global.HOME_IMAGE_PATH').$banner_img)) 
				{ 
					$this->PageData['Maxtwodaybanner'] = config('global.HOME_IMAGE_URL').$banner_img; 
				}
				if(!empty($mobile_banner_img) && file_exists(config('global.HOME_IMAGE_PATH').$mobile_banner_img)) 
				{ 
					$this->PageData['MaxtwodaybannerMobile'] = config('global.HOME_IMAGE_URL').$mobile_banner_img; 
				}
			}
			
			$SetFilters = $this->SetFilters($request);
			$this->PageData['SelBrand'] = '';
			if(isset($SetFilters['brands']) && count($SetFilters['brands']) > 0)
				$this->PageData['SelBrand'] = $SetFilters['brands'][0];	
				//$this->PageData['SelBrand'] = implode(",",$SetFilters['brands']);
			
			$this->PageData['SelSize'] = '';
			if(isset($SetFilters['size']) && count($SetFilters['size']) > 0)
				$this->PageData['SelSize'] = implode(",",$SetFilters['size']);
			
			$this->PageData['SelKey'] = '';
			if(isset($SetFilters['key']) && $SetFilters['key'] != '')
				$this->PageData['SelKey'] = $SetFilters['key'];
			
			$this->PageData['SelStock'] = '';
			if(isset($SetFilters['stock']) && in_array('In',$SetFilters['stock']))
				$this->PageData['SelStock'] = 'checked';
			
			
			$ProductsDetails = $this->GetProducts('Maxtwoday','',12,$SetFilters);
			
			$Products = $ProductsDetails['Products'];
			
			//dd($Products);
			$TotalProducts = $ProductsDetails['TotalProducts'];
			$this->PageData['MaxtwoProds'] = $Products;
			$this->PageData['TotalProducts'] = $TotalProducts;
			$BrandList = [];
			$SizeList = [];
			if(count($ProductsDetails['LeftFilters']) > 0)
			{
				foreach($ProductsDetails['LeftFilters'] as $MaxFilter)
				{
					if(array_key_exists('Brands',$MaxFilter)){
						$BrandList = $MaxFilter['Brands']['Data'];
					}
					if(array_key_exists('Size',$MaxFilter)){
						$SizeList = $MaxFilter['Size']['Data'];
					}
				}
			}
			
			if((isset($SetFilters['brands']) && count($SetFilters['brands']) > 0) || (isset($SetFilters['size']) && count($SetFilters['size']) > 0))
			{
				$this->PageData['BrandList'] = Cache::get('MaxBrands');
				$this->PageData['SizeList'] = Cache::get('MaxSizes');
			} else {
				Cache::put('MaxBrands', $BrandList);
				$this->PageData['BrandList'] = Cache::get('MaxBrands');
				Cache::put('MaxSizes', $SizeList);
				$this->PageData['SizeList'] = Cache::get('MaxSizes');
			}	

			//$this->PageData['SizeList'] = $SizeList;
			$this->PageData['MinPrice'] = count($Products)>0?min( array_column( $Products, 'product_price' )):0;
			$this->PageData['MaxPrice'] = count($Products)>0?max( array_column( $Products, 'product_price' )):0;
			
			//dd($this->PageData);
			return view('product.maxtwoday')->with($this->PageData);
		}
		
		public function GetMaxtwoday(Request $request)
		{
			$Filters = json_decode($request->filters,true);
			$Filters['page'] = $request->page;
			$ProductsDetails = $this->GetProducts('Maxtwoday','',12,$Filters);
			$Products = $ProductsDetails['Products'];
			$TotalProducts = $ProductsDetails['TotalProducts'];
			$this->PageData['Products'] = $Products;
			$this->PageData['TotalProducts'] = $TotalProducts;
			$BrandList = [];
			$SizeList = [];
			if(count($ProductsDetails['LeftFilters']) > 0)
			{
				foreach($ProductsDetails['LeftFilters'] as $DealFilter)
				{
					if(array_key_exists('Brands',$DealFilter)){
						$BrandList = $DealFilter['Brands']['Data'];
					}
					if(array_key_exists('Size',$DealFilter)){
						$SizeList = $DealFilter['Size']['Data'];
					}
				}
			}
			$MinPrice = count($Products)>0?min( array_column( $Products, 'product_price' )):0;
			$MaxPrice = count($Products)>0?max( array_column( $Products, 'product_price' )):0;
			$ProductHTML = view('product.otherlist')->with($this->PageData)->render();
			//return view('product.list')->with($this->PageData);
			return response()->json(array('TotalProducts' => $TotalProducts, 'ProductHTML'=>$ProductHTML, 'BrandList' => $BrandList, 'SizeList' => $SizeList,'MinPrice' => $MinPrice, 'MaxPrice' => $MaxPrice));
		}
		
		public function GetDealOfWeek(Request $request)
		{
			$Filters = json_decode($request->filters,true);
			$Filters['page'] = $request->page;
			$ProductsDetails = $this->GetProducts('DealofweekPage','',12,$Filters);
			$Products = $ProductsDetails['Products'];
			$TotalProducts = $ProductsDetails['TotalProducts'];
			$this->PageData['Products'] = $Products;
			$this->PageData['TotalProducts'] = $TotalProducts;
			$BrandList = [];
			$SizeList = [];
			if(count($ProductsDetails['LeftFilters']) > 0)
			{
				foreach($ProductsDetails['LeftFilters'] as $DealFilter)
				{
					if(array_key_exists('Brands',$DealFilter)){
						$BrandList = $DealFilter['Brands']['Data'];
					}
					if(array_key_exists('Size',$DealFilter)){
						$SizeList = $DealFilter['Size']['Data'];
					}
				}
			}
			$MinPrice = count($Products)>0?min( array_column( $Products, 'deal_price' )):0;
			$MaxPrice = count($Products)>0?max( array_column( $Products, 'deal_price' )):0;
			$ProductHTML = view('product.otherlist')->with($this->PageData)->render();
			//return view('product.list')->with($this->PageData);
			return response()->json(array('TotalProducts' => $TotalProducts, 'ProductHTML'=>$ProductHTML, 'BrandList' => $BrandList, 'SizeList' => $SizeList,'MinPrice' => $MinPrice, 'MaxPrice' => $MaxPrice));
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
				$DealOfWeekQry->whereIn('p.product_type',['both','retailer','wholesaler']);
			else
				$DealOfWeekQry->whereIn('p.product_type',['both','retailer']);				
			$DealOfWeekQry->orderBy('dw.end_date');
			$DealOfWeekQry->orderBy('display_rank');
			$DealOfWeek = $DealOfWeekQry->limit(1)->get();
			
			$Deal = new stdClass();
			if($DealOfWeek && $DealOfWeek->count() > 0)
			{
				$Deal = $this->SetProduct($DealOfWeek[0]);
				$Deal->formatted_end_date = date('d',strtotime($Deal->end_date));
				$Deal->formatted_end_month = date('m',strtotime($Deal->end_date));
				$Deal->formatted_end_year = date('Y',strtotime($Deal->end_date));	
				$Deal->deal_end_date = date('d-m-Y 23:59:00',strtotime($Deal->end_date));
			} else {
				$DealOfWeekQry = DB::table('pu_dealofweek as dw')
								->join('pu_dealofweektitle as dwt','dw.did','=','dwt.did')
								->join('pu_products as p','dw.product_sku','=','p.sku')
								->join('pu_products_category as pc','p.products_id','=','pc.products_id')
								->join('pu_manufacture as m','p.imanufactureid','=','m.imanufactureid')
								->where('p.status','=','1')->where('dw.status','=','1')
								->where('dw.start_date','<=',date('Y-m-d'))
								->where('dw.end_date','>=',date('Y-m-d'))
								->where('dw.display_on_home','=','No');
				if(Session::get('eusertype') && strtolower(Session::get('eusertype')) == 'wholesaler')
					$DealOfWeekQry->whereIn('p.product_type',['both','retailer','wholesaler']);
				else
					$DealOfWeekQry->whereIn('p.product_type',['both','retailer']);				
				$DealOfWeekQry->orderBy('dw.end_date','desc');
				$DealOfWeek = $DealOfWeekQry->limit(1)->get();
				if($DealOfWeek && $DealOfWeek->count() > 0)
				{
					$Deal = $this->SetProduct($DealOfWeek[0]);
					$Deal->formatted_end_date = date('d',strtotime($Deal->end_date));
					$Deal->formatted_end_month = date('m',strtotime($Deal->end_date));
					$Deal->formatted_end_year = date('Y',strtotime($Deal->end_date));	
					$Deal->deal_end_date = date('d-m-Y h:i:s',strtotime($Deal->end_date));
				}
			}					
			return $Deal;
		}
		
		public function SearchSpringAutocomplete(Request $request)
		{
		   // dd($request);
		   // die;
			
			if($request->ajax()){
			
				$searchKeyword = $request->keyword;
				$searchKeyword = str_replace("andd","&",$searchKeyword);
				$searchKeyword = str_replace("backslash","/",$searchKeyword);
				$searchKeyword = str_replace(" ","-",$searchKeyword);
				
				$type = $request->type;
				
				$extraSearchQuery = $extraSearchQueryName = $addExtraQry = $productTypeQry = '';
				
				// if(Session::get('sess_icustomerid') != ''){
				// 	if(strtolower(Session::get('eusertype')) == 'wholesaler'){
				// 		$productTypeQry = '&filter.product_type=wholesaler';
				// 	}
				// 	if(strtolower(Session::get('eusertype')) == 'retailer'){
				// 		$productTypeQry = '&filter.product_type=retailer';
				// 	}
				// }
				
				if($request->extraSearchQuery != ''){
					$extraSearchQuery = $request->extraSearchQuery;
					$extraSearchQueryName = $request->extraSearchQueryName;
					
					$extraSearchQuery = str_replace("doubledot",":",$extraSearchQuery);
					$extraSearchQuery = str_replace("dot",".",$extraSearchQuery);
					$extraSearchQuery = str_replace("dash","-",$extraSearchQuery);
					$extraSearchQuery = str_replace("andd","&",$extraSearchQuery);
					$extraSearchQuery = str_replace("singlecomma","'",$extraSearchQuery);
					$extraSearchQuery = str_replace("_"," ",$extraSearchQuery);
					
					if($extraSearchQueryName == 'cid'){
						$addExtraQry = '&filter.category='.rawurlencode($extraSearchQuery);
					}else if($extraSearchQueryName == 'mid'){
						$addExtraQry = '&filter.brand='.rawurlencode($extraSearchQuery);
					}else if($extraSearchQueryName == 'size'){
						$addExtraQry = '&filter.size='.rawurlencode($extraSearchQuery);
					}else if($extraSearchQueryName == 'special'){
						$addExtraQry = '&filter.badges='.rawurlencode($extraSearchQuery);
					}
				}
				
				
				$curl = new \GuzzleHttp\Client();
				$initialRequest = "https://2mjz9y.a.searchspring.io/api/suggest/query?disableSpellCorrect=true&lang=en&pubId=2mjz9y&query=".rawurlencode(str_replace("-"," ",$searchKeyword));
			
				$response = $curl->request('GET', $initialRequest);
				$Value = $response->getBody()->getContents();
				$jsonArrayResponse = json_decode($Value);
				
				
			
				if(!empty($jsonArrayResponse->suggested->text) && $type != 'changeTabData'){
					$suggestedQry = $jsonArrayResponse->suggested->text;
				}else{
					$suggestedQry = $searchKeyword;
				}
				
				$curl1 = new \GuzzleHttp\Client();
				$initialRequest1 = "https://2mjz9y.a.searchspring.io/api/search/autocomplete.json?q=".rawurlencode(str_replace("-"," ",$suggestedQry))."&resultsFormat=native&resultsPerPage=12&siteId=2mjz9y".$addExtraQry.$productTypeQry;
				$response1 = $curl->request('GET', $initialRequest1);
				$Value1 = $response1->getBody()->getContents();
				$jsonArrayResponse1 = json_decode($Value1);
				
	
				$aProducts = json_decode(json_encode($jsonArrayResponse1->results), true);
				$cntaProducts = count($aProducts);
				$filters = json_decode(json_encode($jsonArrayResponse1->facets), true);

				$cntSetLinkOnTop = 0;
				if(!empty($jsonArrayResponse1->facets) && $jsonArrayResponse1->facets[0]->field == 'brand' && count($jsonArrayResponse1->facets[0]->values) == 1 && strtolower($jsonArrayResponse1->facets[0]->values[0]->value) == strtolower($suggestedQry)){
					$cntSetLinkOnTop++;
				}

				$data = $fltrsData = $data1 = $data2 = $allData = '';
				
				$data .= '<div class="serach-dropdown" id="serach-dropdownauto">';

				if($cntaProducts <= 0){
				$data .= '<div class="src-sec" style="text-align:center;display: block;"><strong>Sorry, No records found.</strong></div>';
				}else{
				if($cntSetLinkOnTop > 0){
				$data .= '<div class="src-sec headingLink" style="text-align:center;display: block;"><a href="'.config('global.SITE_URL').'p4u/key-'.$suggestedQry.'/view" ><strong>'.$suggestedQry.'</strong></a></div><br/>';
				}
				$data .= '<div class="src-sec">';
				if(!empty($jsonArrayResponse->alternatives)){
				
				if(empty($jsonArrayResponse->suggested->text)){
				$setTabKeyword = str_replace("andd","&",$searchKeyword);
				$setTabKeyword = str_replace("/","backslash",$setTabKeyword);
				$setTabKeyword = str_replace(" ","-",$setTabKeyword);

				$data .= '<div class="serch-ltab">
				<ul><li class="changeTab"><a href="javascript:void(0);" class="serch-ltab-active" >'.$searchKeyword.'</a></li>';
				}else{
				$setTabKeyword = str_replace("andd","&",$jsonArrayResponse->suggested->text);
				$setTabKeyword = str_replace("/","backslash",$setTabKeyword);
				$setTabKeyword = str_replace(" ","-",$setTabKeyword);

				$data .= '<div class="serch-ltab">
				<ul><li class="changeTab"><a href="javascript:void(0);" class="serch-ltab-active" >'.$jsonArrayResponse->suggested->text.'</a></li>';
				}
				for($a=0;$a<count($jsonArrayResponse->alternatives);$a++){
				$setTabKeyword1 = str_replace("andd","&",$jsonArrayResponse->alternatives[$a]->text);
				$setTabKeyword1 = str_replace("/","backslash",$jsonArrayResponse->alternatives[$a]->text);
				$setTabKeyword1 = str_replace(" ","-",$setTabKeyword1);
				//$product_available = $this->productCount($jsonArrayResponse->alternatives[$a]->text, $addExtraQry, $productTypeQry);
				//if($product_available){
					$data .= '<li class="changeTab"><a href="javascript:void(0);">'.$jsonArrayResponse->alternatives[$a]->text.'</a></li>';
				//}
				}
				$data .= '</ul>
				</div>';
				}else{
				if(empty($jsonArrayResponse->suggested->text)){
				$setTabKeyword = str_replace("andd","&",$searchKeyword);
				$setTabKeyword = str_replace("/","backslash",$setTabKeyword);
				$setTabKeyword = str_replace(" ","-",$setTabKeyword);

				$data .= '<div class="serch-ltab">
				<ul><li class="changeTab"><a href="javascript:void(0);" class="serch-ltab-active">'.$searchKeyword.'</a></li>';
				}else{
				$setTabKeyword = str_replace("andd","&",$jsonArrayResponse->suggested->text);
				$setTabKeyword = str_replace("/","backslash",$setTabKeyword);
				$setTabKeyword = str_replace(" ","-",$setTabKeyword);
				
				$data .= '<div class="serch-ltab">
				<ul><li class="changeTab"><a href="javascript:void(0);" class="serch-ltab-active">'.$jsonArrayResponse->suggested->text.'</a></li>';
				}$data .= '</ul></div>';
				}
				$data .= '<div class="list-toggle visible-xs">
				<h2><span><svg class="filter_icon" aria-hidden="true" role="img" width="20" height="20">
      <use href="#filter_icon" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#filter_icon"></use>
</svg> <strong>Refine</strong> Search</span>
				<span id="closeSearchAutoComplete" style="display: none;">Close</span>
				</h2>
				</div>	';

				if($type != 'changeTabData'){$fltrsData .= '<div class="src-left-filter">';}
				for($f=0;$f<count($filters);$f++){
				if($filters[$f]['field'] != 'fragrance_family' && $filters[$f]['field'] != 'type' && $filters[$f]['field'] != 'formulation' && $filters[$f]['field'] != 'coverage' && $filters[$f]['field'] != 'finish' && $filters[$f]['field'] != 'skin_type' && $filters[$f]['field'] != 'seasons' && $filters[$f]['field'] != 'occasion' && $filters[$f]['field'] != 'personality' && $filters[$f]['field'] != 'by_feature'){
				if($filters[$f]['field'] != 'price'){
				$catArr = $filters[$f]['values'];

				$fltrsData .= '<div class="src-fbox">
				<h3>'.$filters[$f]['label'].'</h3>
				<ul class="filter_checkbox">';
				for($j=0;$j<count($catArr);$j++){
				if($j < 6){

				$sentData = '';

				if($catArr[$j]['active'] == '1'){
				//$sentData = 'checked';
				$sentData = ' class="active"';
				}

				if($filters[$f]['field'] == 'badges'){
				if(strpos($catArr[$j]['value'], ' ') !== false){
				$getInitials = $this->initialsFN($catArr[$j]['value']);
				}else{
				$getInitials = strtolower(substr($catArr[$j]['value'], 0, 2)); 
				}
				}

				$idsss = str_replace(":","doubledot",$catArr[$j]['value']);
				$idsss = str_replace("&","andd",$idsss);
				$idsss = str_replace(".", "dot",$idsss);
				$idsss = str_replace("-", "dash",$idsss);
				$idsss = str_replace("'", "singlecomma",$idsss);
				$idsss = str_replace(" ","_",$idsss);

				if($filters[$f]['field'] == 'category'){	
				$name = 'cid';
				}else if($filters[$f]['field'] == 'brand'){
				$name = 'mid';
				}else if($filters[$f]['field'] == 'size'){
				$name = 'size';
				}else if($filters[$f]['field'] == 'badges'){
				$name = 'special';
				}

				$searchKeyword1 = str_replace("/","backslash",$searchKeyword);
				$setURL = config('global.SITE_URL')."p4u/key-".$searchKeyword1."/view";

				if($filters[$f]['field'] == 'badges'){
				$fltrsData .= '<li id="li'.$name.$idsss.'" class="setChk"><a href="javascript:void(0);" name="chk'.$name.'" id="'.$name.$idsss.'" value="'.$getInitials.'" data-url="'.$setURL.'" data-object-val="txt'.$name.'" data-name="'.$name.'" '.$sentData.'>'.$catArr[$j]['label'].'</a></li>';
				}else{
				$fltrsData .= '<li id="li'.$name.$idsss.'" class="setChk"><a href="javascript:void(0);" name="chk'.$name.'" id="'.$name.$idsss.'" value="'.$idsss.'" data-url="'.$setURL.'" data-object-val="txt'.$name.'" data-name="'.$name.'" '.$sentData.'>'.$catArr[$j]['label'].'</a></li>';
				}

				}}
				$fltrsData .= '</ul></div>';
				}}}
				if($type != 'changeTabData'){$fltrsData .= '</div>';}
				if($type != 'changeTabData'){$data1 .= '<div class="src-plist">';}

				if($cntaProducts > 0){
				$data1 .= '<div class="src-name"><span>Search result for <strong>"'.$suggestedQry.'"</strong></span><span style="float:right;"><a href="javascript:void(0);" onClick="setFilterValue();"><strong>View All Products</strong></a></span></div><div class="src-prdlist">';

				for($i=0;$i<$cntaProducts;$i++){
				//Problem code start
				$data1 .= '<div class="product">
				<div class="thumb lazythumb">
				<a href="'.$aProducts[$i]['url'].'" onmousedown="return intellisuggestTrackClick(this, \'' . $aProducts[$i]['intellisuggestData'] . '\',\''. $aProducts[$i]['intellisuggestSignature'] .'\')"><img class="owl-lazy" src="'.$aProducts[$i]['imageUrl'].'" style="opacity: 1;"><span class="cornar">';

				 if((isset($aProducts[$i]['website_stock']) && $aProducts[$i]['website_stock'] > 0) && (isset($aProducts[$i]['max_two_day_delivery']) && $aProducts[$i]['max_two_day_delivery'] == "Yes")){
				 	$data1 .= '<img src="'.config('global.SITE_IMAGES').'2day.png" alt="" width="57" height="26" />';
				 }
				
				$data1 .= '</span>';
				
			//	$aProducts[$i]['sale_item'] = '0';
				// if(isset($aProducts[$i]['sale_price']) && $aProducts[$i]['sale_price'] > 0 && Session::get('sess_icustomerid') != '' && Session::get('eusertype') == 'Wholesaler')
				// {
				// 	$aProducts[$i]['sale_item'] = '1';
				// }
				
				// if(isset($aProducts[$i]['sale_item']) && $aProducts[$i]['sale_item'] == 1){
				// 	$data1 .= '<svg class="sv-sale" aria-hidden="true" role="img" width="100" height="20">
				// 		<use href="#sv-sale" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-sale"></use>
				// 	</svg>';
				// }
				
				$data1 .= '</a>
				</div>
				<!-- Product Icon -->
				<!--<div class="icon">-->
				<ul class="product_icon">';
				/*$data1 .= '<!-- Gender Icon -->';
				if(isset($aProducts[$i]['gender'])){
				
				if ($aProducts[$i]['gender'] == 'M'){
					$aProducts[$i]['gender'] = "sv-men";
					$aProducts[$i]['gendernames'] = "Men";
					$for_gender = ' for Men';
				} elseif ($aProducts[$i]['gender'] == 'W'){
					$aProducts[$i]['gender'] = "sv-women";
					$aProducts[$i]['gendernames'] = "Women";
					$for_gender = ' for Women';
				} elseif ($aProducts[$i]['gender'] == 'K'){
					$aProducts[$i]['gender'] = "sv-kids";
					$aProducts[$i]['gendernames'] = "Kids";
					$for_gender = ' for Kids';
				} elseif ($aProducts[$i]['gender'] == 'U'){
					$aProducts[$i]['gender'] = "sv-unisex";
					$aProducts[$i]['gendernames'] = "Unisex";
					$for_gender = ' Unisex';
				} else{
					$aProducts[$i]['gender'] = "";
					$aProducts[$i]['gendernames'] = "";
					$for_gender = '';
				}
				
				$data1 .= '<li>
				<!--<a href="javascript:void(0);" title="'.$aProducts[$i]['gendernames'].' Item"><svg class="'.$aProducts[$i]['gender'].'" aria-hidden="true" role="img" width="8" height="14"><use href="#'.$aProducts[$i]['gender'].'" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#'.$aProducts[$i]['gender'].'"></use></svg></a>-->
				<a href="javascript:void(0);" title="'.$aProducts[$i]['gendernames'].' Item">
					<svg class="'.$aProducts[$i]['gender'].' sv-bor" aria-hidden="true" role="img" width="15" height="15"><use href="#'.$aProducts[$i]['gender'].'" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#'.$aProducts[$i]['gender'].'"></use></svg>
				</a>
				</li>';
				}
				$data1 .= '<!-- Gender Icon -->';*/
				
			
				
				$data1 .= '<!-- Add To Wislist Icon -->
				<li>
					<!--<a href="javascript:void(0);" class="svg-h" title="Add to wishlist" onclick="DisplayPopupBoxWishlist(\''.trim($aProducts[$i]['uid']).'\',\'yes\');"><svg class="sv-heart" aria-hidden="true" role="img" width="14" height="13"><use href="#sv-heart" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-heart"></use></svg></a>-->
					<a href="javascript:void(0);" class="svg-h" title="Add to wishlist" onclick="DisplayPopupBoxWishlist(\''.trim($aProducts[$i]['uid']).'\',\'yes\');">
						<svg class="sv-heart sv-bor" aria-hidden="true" role="img" width="14" height="14">
							<use href="#sv-heart" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-heart"></use>
						</svg>
						<svg class="sv-heart-fill sv-fill" aria-hidden="true" role="img" width="14" height="14">
							<use href="#sv-heart-fill" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-heart-fill"></use>
						</svg>
					</a>
				</li>
				<!-- Add To Wislist Icon -->

				<!-- Add To Cart Icon -->';
				
				//  if(isset($aProducts[$i]['inventory_count']) && $aProducts[$i]['inventory_count'] <= 0){
				//  $data1 .= '<li><a class="label-cart-outstock" title="Out of stock" href="javascript:void(0);" rel="nofollow" onclick="DisplayPopupBoxAlertMe(\''.trim($aProducts[$i]['uid']).'\',\''.trim($aProducts[$i]['sku']).'\');" onmousedown="return intellisuggestTrackClick(this, \'' . $aProducts[$i]['intellisuggestData'] . '\',\''. $aProducts[$i]['intellisuggestSignature'] .'\')"><svg class="sv-cart-outstock" aria-hidden="true" role="img" width="14" height="13"><use href="#sv-cart-outstock" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-cart-outstock"></use></svg></a></li>';
				//  }else{
				// if(Session::get('sess_icustomerid') != '' && Session::get('eusertype') == 'Wholesaler' && isset($aProducts[$i]['product_type']) && $aProducts[$i]['product_type']== 'retailer'){
				//  }else{
				// $data1 .= '<li><a href="javascript:void(0);" title="Add to cart" class="prodaddcart" data-product="'.trim($aProducts[$i]['uid']).'" onmousedown="return intellisuggestTrackClick(this, \'' . $aProducts[$i]['intellisuggestData'] . '\',\''. $aProducts[$i]['intellisuggestSignature'] .'\')"><svg class="sv-cartnw vam" aria-hidden="true" role="img" width="15" height="15"><use href="#sv-cartnw" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-cartnw"></use></svg></a></li>';
				//  }
				//  }
				
			
				$data1 .= '<!-- Add To Cart Icon -->
				</ul>
				<!--</div>-->
				<!-- Product Icon -->

				<div class="product-name"><a href="'.$aProducts[$i]['url'].'" onmousedown="return intellisuggestTrackClick(this, \'' . $aProducts[$i]['intellisuggestData'] . '\',\''. $aProducts[$i]['intellisuggestSignature'] .'\')"><u><strong>'.$aProducts[$i]['name'].'</strong></u></a></div>

				<!--<div class="minheight">-->';
				
			
				/*if(isset($aProducts[$i]['rating']) && $aProducts[$i]['rating'] > 0){
					$data1 .= '<div class="rating pb-2 d-flex align-items-center justify-content-center"><span class="d-inline-block">('.(int)$aProducts[$i]['rating'].')</span>';
					$setRatingAvg = (int)$aProducts[$i]['rating_avg'] + 1;
					for($s=$setRatingAvg;$s<=5;$s++){
					$data1 .= '<span class="star"></span>';
					}
					for($r=0;$r<$aProducts[$i]['rating_avg'];$r++){
					$data1 .= '<span class="star active"></span>';
					}
					$data1 .= '</div>';
				}*/

				$data1 .= '<!--</div>-->
				</div>';
				}}
					//Problem code ends

				if($type != 'changeTabData'){$data1 .= '</div></div>';}
				$data2 .= '</div>';
				}
				$data2 .= '</div>';
				
			

				$allData = $data.$fltrsData.$data1.$data2;
				
			
					

				if($type == 'changeTabData'){
					//echo $suggestedQry.'@@@'.$fltrsData.'@@@'.$data1;
					return $suggestedQry.'@@@'.$fltrsData.'@@@'.$data1;
				}else{
				//	
					
					return $allData;
				}

				/*echo json_encode($resultArr);
				exit;
				return json_encode($resultArr);*/
			}
		}
		
		public function initialsFN($str) {
		    $ret = '';
		    foreach (explode(' ', $str) as $word){
		        $ret .= strtolower($word[0]);
			}
		    return $ret;
		}
		public function SetWholesalePrice(Request $request)
		{
			$ProductsID = $request->products_id;
			$ProdQty = $request->quantity;
			return $this->GetWholesalePrice($ProductsID,$ProdQty);
		}
		
		private function productCount($query_string, $extra_query,$product_query){
			$response = Http::get("https://faltym.a.searchspring.io/api/search/autocomplete.json?q=".rawurlencode(str_replace("-"," ",$query_string))."&resultsFormat=native&resultsPerPage=12&siteId=faltym".$extra_query.$product_query);
			$response_object = $response->object();
			return isset($response_object->results) && count($response_object->results) > 0;
		}
		
		
		
		
			public function allproducts(Request $request)
		{
            $json=array();
            $products=DB::table('pu_products')->limit(10000)->get();
            
            $productlist=[];
                if(!empty($products))
                {
                    foreach($products as $key => $val)
                    {
                         
                        $category=DB::table('pu_category')->join('pu_products_category','pu_category.category_id','pu_products_category.category_id')->where('pu_products_category.products_id',$val->products_id)->select('pu_category.*','pu_products_category.*')->first();
                        if(!empty($category))
                        {
                        
                        $parentcat=DB::table('pu_category')->where('category_id',$category->parent_id)->first();
                        $rating=DB::table('pu_products_review')->where('pu_products_review.products_id',$val->products_id)->max('star_rate');
                        $inventory=DB::table('pu_skuvault_inventory')->where('sku',$val->sku)->count();
                        $totalrating=DB::table('pu_products_review')->where('pu_products_review.products_id',$val->products_id)->count();
                        $image=route('home').'/productimages/thumb/'.$val->image;
                        $productdata['Product ID']=$val->products_id;
                        $productdata['SKU']=$val->sku ?? '';
                        $productdata['Name']=$val->product_name;
                        if(!empty($parentcat->category_name) && $category->category_name)
                        {
                            
                            $prourl=config('global.SITE_URL').$parentcat->category_name.'/'.$category->category_name.'/'.str_replace(' ','-',$val->product_name).'/'.'pid/'.$val->products_id.'/'.$category->category_id ?? '';
                           
                            $productdata['Product URL']=$prourl;
                            
                        }else
                        {
                            $productdata['Product URL']='';
                        }
                        $productdata['Price']=str_replace('"','',$val->our_price) ?? '';
                        $productdata['Retail Price']=str_replace('"','',$val->retail_price) ?? '';
                        $productdata['Thumbnail URL']=$image ?? '';
                        $productdata['Search Keywords']=$val->product_name ?? '';
                        $description=strip_tags(str_replace('r\n','',$val->product_description));
                        $finaldes=str_replace('\\','',$description);
                        $productdata['Description']= $finaldes ?? '';
                        if(!empty($parentcat->category_name) && $category->category_name)
                        {
                        $productdata['Category']="Home>".$parentcat->category_name.">".$category->category_name.">".$val->product_name;
                        $productdata['Category ID']=$val->products_id.'|'.$category->category_id ?? '';
                        }else
                        {
                            $productdata['Category']='';
                        $productdata['Category ID']='';
                        }
                        $productdata['Brand']=$val->brand->brand_name ?? '';
                        $productdata['Child SKU']=$val->pca_sku ?? '';
                        $productdata['Child Price']=$val->pca_price ?? '';
                        $productdata['Color']=$val->skin_type ?? '';
                        $productdata['Color Family']=$val->fragrance_family ?? '';
                        $productdata['Color Swatches']=$val->coverage ?? '';
                        $productdata['Size']=$val->size ?? '';
                         $productdata['fragrance_family']=$val->fragrance_family ?? '';
                          $productdata['formulation']=$val->formulation ?? '';
                          $productdata['fragrance_occasion']=$val->fragrance_occasion ?? '';
                          $productdata['fragrance_personality']=$val->fragrance_personality ?? '';
                        // $productdata['Pants Size']=$val->size_old ?? '';
                        // $productdata['Occassion']=$val->fragrance_occasion ?? '';
                        // $productdata['Season']=$val->fragrance_seasons ?? '';
                        
                        if($rating==1)
                        {
                            $badge='*';
                        }
                        if($rating==2)
                        {
                            $badge='**';
                        }
                        if($rating==3)
                        {
                            $badge='***';
                        }if($rating==4)
                        {
                            $badge='****';
                        }if($rating==5)
                        {
                            $badge='*****';
                        }
                        $productdata['Badges']=$badge ?? '';
                        $productdata['Rating Avg']=$rating ?? '';
                        $productdata['Rating Count']=$totalrating ?? '';
                        
                        // $productdata['short_description']=strip_tags($val->short_description) ?? '';
                        // $productdata['gender']=$val->gender ?? '';
                        // $productdata['manufactureid']=$val->imanufactureid ?? '';
                        
                        $productdata['Inventory Count']=$inventory ?? '';
                        $productdata['Date Created']=$val->add_datetime ?? '';
                        $productlist[]=json_encode($productdata,JSON_UNESCAPED_SLASHES);
                        }
                    }
                    return response(implode("\r\n",$productlist));
                    // ->withHeaders(['Content-Type' => 'application/x-ndjson' ]);
                    //   return ;
                        // $Json['status']=200;
                      // return $Json['Products']=$productlist;
                        //  $out='';
                        // foreach($productlist as $i=$product){
                        //   $out=$out.json_encode($product)."\r\n";
                        // }
                        // return $out;
                        // $json=str_replace('\/','/',json_encode($productlist));
                        // $json1= str_replace("["," ",$json);
                        // $json1 = str_replace("]"," ",$json1);
                        // $json1 = str_replace("},{","}{",$json1);
                        // return $json1;
                        die; 
                    
                      
                }else
                {
                        $Json['status']=400;
                        $Json['Products']='No Product Available';
                        return response()->json($Json);
                        die;
                }
           
       
		}
		
	

	}
