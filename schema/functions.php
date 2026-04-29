<?php 
function getCategoryNavigationSchema($category_id) {
		global $obj,$generalobj,$Site_URL;
		unset($generalobj->child_cat_arr);		
		$generalobj->getCategoryTree($category_id);
		//getCategoryTreeSchema($category_id);
		
		$cat_navigation = array();
		$cnt_child_cat_arr = count($generalobj->child_cat_arr);
		 
		 $position= 2;
		 array_push($cat_navigation ,array(
				"@type"=> "ListItem",
			   "position"=> "1",
				   "item"=>array
				   (
					"@id"=> Site_URL,
					"name"=> "Home"
					)
				));
		 for($i = $cnt_child_cat_arr-1; $i >= 0; $i--) {	
				$sql = "SELECT category_id,category_name 
							   FROM ".TABLE_PREFIX."category 
							   WHERE category_id = '".$generalobj->child_cat_arr[$i]."'";
				$catRes = $obj->select($sql);
				
				array_push($cat_navigation ,array(
				"@type"=> "ListItem",
			   "position"=> $position,
				   "item"=>array
				   (
					"@id"=> $generalobj->getCategoryRewriteURL($catRes[0]["category_id"]),
					"name"=> "{$catRes[0]["category_name"]}"
					)
				));
				$position++;
		 }
		 return $cat_navigation;
}
function getProductlistNavigationSchema($Navigation)
{
	$pro_navigation = array();
	$totitem = count($Navigation);
	//echo "<pre>";print_r($Navigation);exit;
	$position= 2;
	 array_push($pro_navigation ,array(
			"@type"=> "ListItem",
			"position"=> "1",
			   "item"=>array
			   (
				"@id"=> Site_URL,
				"name"=> "Home"
				)
			));
	for($i=0 ; $i<$totitem ; $i++)
	{
		array_push($pro_navigation ,array(
			"@type"=> "ListItem",
			"position"=> $position,
			   "item"=>array
			   (
				"@id"=> $Navigation[$i]['url'],
				"name"=> $Navigation[$i]['name']
				)
			));
			$position++;
	}
	return $pro_navigation;
}
function getReviewSchema()
{
	global $obj;
	$sql = "SELECT COUNT(*) as count, AVG( `star_rate` ) as average FROM ".TABLE_PREFIX."products_review WHERE approved = 'Yes'";
	$reviewRes = $obj->select($sql);
	
	$ratingValue = number_format($reviewRes[0]['average'], 2);
	
	return $reviewRes[0]['count']."##".$ratingValue;
}

function getProductDetailSchema()
{
	global $productDetail,$arr_product_review,$avg_rate;	
	$totreview = count($arr_product_review);	
	if($totreview>0)
	{
		$reviewlist = '';
		for($i=0; $i<$totreview; $i++)
		{			
			$reviewlist .= '{
					  "@type": "Review",
					  "author": "'.$arr_product_review[$i]['first_name'].'",
					  "datePublished": "'.$arr_product_review[$i]['date'].'",
					  "description": "'.$arr_product_review[$i]['user_review'].'",
					  "name": "'.$arr_product_review[$i]['first_name'].'",
					  "reviewRating": {
						"@type": "Rating",
						"bestRating": "5",
						"ratingValue": "'.$arr_product_review[$i]['star_rate'].'",
						"worstRating": "1"
					  }
					}';
			
			if($i+1!=$totreview)
			{
				$reviewlist .= ',';
			}
		}
	}
	
	//echo count($arr_product_review);exit;
	
	if($totreview>0)
	{
		$ProductDetailSchema = '"aggregateRating": {
				"@type": "AggregateRating",
				"ratingValue": "'.$avg_rate.'",
				"reviewCount": "'.count($arr_product_review).'"
			  },';
	}  
	$ProductDetailSchema .= '"description": "'.$productDetail[0]['product_description'].'",
			  "name": "'.strip_tags($productDetail[0]['product_name'])." ".$productDetail[0]['size'].'",
			  "offers": {
				"@type": "Offer",
				"availability": "'.($productDetail[0]['stock']=='In' ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut').'",
				"itemCondition": "http://schema.org/NewCondition",
				"price": "'.$productDetail[0]['product_price'].'",
				"priceCurrency": "'.$_SESSION['currency_code'].'"
			  }';
	
	if($totreview>0)
	{
		$ProductDetailSchema .= ',"review": [
				'.$reviewlist.'
			  ]';
	}
	
	return $ProductDetailSchema;	
}

function getProductNavigationSchema($category_id) {
	
		global $obj,$generalobj,$Site_URL,$productDetail,$products_id;
		unset($generalobj->child_cat_arr);
		
		$generalobj->getCategoryTree($category_id);
     	$cat_navigation = array();
		$cnt_child_cat_arr = count($generalobj->child_cat_arr);
		 
		 $position= 2;
		 array_push($cat_navigation ,array(
				"@type"=> "ListItem",
			   "position"=> "1",
				   "item"=>array
				   (
					"@id"=> Site_URL,
					"name"=> "Home"
					)
				));
		 for($i = $cnt_child_cat_arr-1; $i >= 0; $i--) {	
				$sql = "SELECT category_id,category_name 
							   FROM ".TABLE_PREFIX."category 
							   WHERE category_id = '".$generalobj->child_cat_arr[$i]."'";
				$catRes = $obj->select($sql);
				
				array_push($cat_navigation ,array(
				"@type"=> "ListItem",
			   "position"=> $position,
				   "item"=>array
				   (
					"@id"=> $generalobj->getCategoryRewriteURL($catRes[0]["category_id"]),
					"name"=> "{$catRes[0]["category_name"]}"
					)
				));
				$position++;
		 }
		 
		 array_push($cat_navigation ,array(
			"@type"=> "ListItem",
		   "position"=> $position,
			   "item"=>array
			   (
				"@id"=> $generalobj->getCategoryRewriteURL($products_id),
				"name"=> "{$productDetail[0]['product_name']}"
				)
			));
		 
		 
		 return $cat_navigation;
}

function getSchema($schema_type)
{
	global $smarty, $Site_URL;
	
	$schema = '';
	$schema .= '<script type="application/ld+json">{';
	
	if($schema_type=='category_list')
	{
		$schema_bread_crumb = get_schema_breadcrumb('category_list');
		$schema .= '"@context": "http://schema.org",
						"@type": "BreadcrumbList",
						"itemListElement": '.$schema_bread_crumb.'';
		
		
		
		//category Product List Start
		$schema .= getProductListSchema('category_list');
		//category Product List End
		
	}
	else if($schema_type == 'sub_category')
	{
		$schema_bread_crumb = get_schema_breadcrumb('sub_category');
		$schema .= '"@context": "http://schema.org",
						"@type": "BreadcrumbList",
						"itemListElement": '.$schema_bread_crumb.'';		
		
		//subcategory Product List Start
		$schema .= getProductListSchema('category_list');
		//subcategory Product List End
	}
	else if($schema_type == 'home')
	{		
		global $SliderManufacture;
		
		$schema_bread_crumb = get_schema_breadcrumb('home');
		$schema .= '"@context": "http://schema.org",
						"@type": "BreadcrumbList",
						"itemListElement": '.$schema_bread_crumb.'';
						
		//Separator Start
		$schema .= '} </script>';
		$schema .= '<script type="application/ld+json">{';
		//Separator End
		
		//Home Page General Information Start
		$ReviewSchema = getReviewSchema();
		$Reviews = explode("##",$ReviewSchema);	
		$schema .= '"@context": "http://schema.org",
		  "@type": "WebPage",
		  "url": "'.$Site_URL.'",';
		
		if($Reviews[0]>0 && $Reviews[1]>2.5)
		{
			$schema .= '"aggregateRating": {
			"@type": "AggregateRating",
			"ratingValue": "'.$Reviews[1].'",
			"reviewCount": "'.$Reviews[0].'"
			},';
		}
		  
		$schema .= '"name" : "'.SITE_TITLE.'"';
		//Home Page General Information End
		
		//Separator Start
		$schema .= '} </script>';
		$schema .= '<script type="application/ld+json">{';
		//Separator End
		
		//Brand List Start
		$Manufacturerlist = '';
		$totmanu = count($SliderManufacture);
		$i=1;
		foreach($SliderManufacture as $Manufacturer)
		{
			$Manufacturerlist .= '{"@type": "Brand","image": "'.$Manufacturer['manufacture_logo'].'","url": "'.$Manufacturer['manufacture_link'].'","name": "'.$Manufacturer['manufacture_name'].'"
			}';			
			if($i != $totmanu)
			{
				$Manufacturerlist .= ',';
			}				
		$i++;
		}
		
		$schema .= '"@context": "http://schema.org","@type": "ListItem","item": ['.$Manufacturerlist.']';
		//Brand List End
		
		
		//Separator Start
		$schema .= '} </script>';
		$schema .= '<script type="application/ld+json">{';
		//Separator End
		
		//Home Product List Start
		$prodiuctlistschema = getProductListSchema('home');
		$schema .= '"@context": "http://schema.org",
		  "@type": "ListItem",
		  "item": '.$prodiuctlistschema.'';
		 //Home Product List End
		  
	}
	else if($schema_type == 'productlist')
	{
		//Product
		$schema_bread_crumb = get_schema_breadcrumb('productlist');
		
		$schema .= '"@context": "http://schema.org",
						"@type": "BreadcrumbList",
						"itemListElement": '.$schema_bread_crumb.'';
		
		$schema .= '} </script>';
		$schema .= '<script type="application/ld+json">{';
		
		$schema .= getProductListSchema('productlist');
	}
	else if($schema_type == 'productdetail')
	{
		$schema_bread_crumb = get_schema_breadcrumb('productdetail');
		$schema .= '"@context": "http://schema.org",
						"@type": "BreadcrumbList",
						"itemListElement": '.$schema_bread_crumb.'';
		
		$schema .= '} </script>';
		$schema .= '<script type="application/ld+json">{';
		
		$prodiuctlistschema = getProductDetailSchema();
		
		$schema .= '"@context": "http://schema.org",
			  "@type": "Product",
			  '.$prodiuctlistschema.'';
			  
		//Product Detail ItemList Start
		$schema .= getProductListSchema('productdetail');
		//Product Detail ItemList End
	}
	else if($schema_type == 'contact_us')
	{
		global $page_content;
		$schema .= '"@context": "http://schema.org",
		  "@type": "Organization",
		  "address": {
			"@type": "PostalAddress",
			"streetAddress": "Peraoma LLC 1122 44th rd,suite 302,Long Island City ,New York ,11101"
		  },
		  "email": "'.ADMIN_MAIL.'",
		  "name": "'.SITE_TITLE.'",
		  "telephone": "'.CONTACT_PHONE_NO.'"';		  
	}
	
	$schema .= '} </script>';
	
	//echo $schema;exit;
	return $schema;
}

function get_schema_breadcrumb($type)
{
	if($type=='category_list')
	{
		global $category_id;
		return json_encode(getCategoryNavigationSchema($category_id));
	}
	else if($type=='home')
	{
		global $obj,$generalobj;
		$cat_sql = "SELECT category_id,parent_id,category_name,display_top FROM " . TABLE_PREFIX . "category WHERE status = '1' AND display_top = 'Yes' ORDER BY display_position LIMIT 0,7";
        $cat_res = $obj->select($cat_sql);
		$totcat = count($cat_res);
		
		$cat_navigation = array();
		 
		 $position= 1;
		 for($i = 0; $i<$totcat; $i++) {
				
				array_push($cat_navigation ,array(
				"@type"=> "ListItem",
			   "position"=> $position,
				   "item"=>array
				   (
					"@id"=> $generalobj->getCategoryRewriteURL($cat_res[$i]['category_id']),
					"name"=> $cat_res[$i]['category_name']
					)
				));
				$position++;
		 }
		 
		 array_push($cat_navigation ,array(
				"@type"=> "ListItem",
			   "position"=> $position,
				   "item"=>array
				   (
					"@id"=> $Site_URL."perfumesale/p4u/special-sl/view",
					"name"=> 'Sale'
					)
				));
				
				array_push($cat_navigation ,array(
				"@type"=> "ListItem",
			   "position"=> $position,
				   "item"=>array
				   (
					"@id"=> $Site_URL."brand-name-perfumes.html",
					"name"=> 'Brands'
					)
				));
		
		return json_encode($cat_navigation);
		
	}
	else if($type=='sub_category')
	{
		global $category_id;
		return json_encode(getCategoryNavigationSchema($category_id));
	}
	else if($type=='productlist')
	{
		global $navigationArrayFinal2;
		return json_encode(getProductlistNavigationSchema($navigationArrayFinal2));
	}
	else if($type=='productdetail')
	{
		global $category_id;
		return json_encode(getProductNavigationSchema($category_id));
	}
}
function getProductListSchema($type)
{
	if($type=='productlist')
	{
		global $aProducts,$CanonicalURL,$arrDealItem_Left;
		
		// ProductList
		$totpro = count($aProducts);
		$productlist2 = '';
		$productlist2 .= '[';
		for($i=0; $i<$totpro; $i++)
		{			
			$productlist2 .= '{
			  "@type": "Product",
			  "image": "'.$aProducts[$i]['image'].'",
			  "url": "'.$aProducts[$i]['product_url'].'",
			  "name": "'.$aProducts[$i]['product_name'].'",
			  "offers": {
				  "price": "'.$aProducts[$i]['product_price'].'",
				  "priceCurrency": "'.$_SESSION['currency_code'].'"
			  }
			}';
			
			if($i+1!=$totpro)
			{
				$productlist2 .= ',';
			}
		}
		$productlist2 .= ']';		
		
		$productlist .= '"@context": "http://schema.org",
		  "@type": "ListItem",
		  "url": "'.$CanonicalURL.'",
		  "item": '.$productlist2.'';
		
		//$productlist .= getDealItems();
		
	}
	else if($type=='home')
	{
		global $HomeProducts;
		
		$tottab = count($HomeProducts);
		$productlist = '';
		$productlist .= '[';
		for($i=0; $i<$tottab; $i++)
		{
			$totpro = count($HomeProducts[$i]['products']);
			for($j=0; $j<$totpro; $j++)
			{
				$productlist .= '{
				  "@type": "Product",
				  "image": "'.$HomeProducts[$i]['products'][$j]['image'].'",
				  "url": "'.$HomeProducts[$i]['products'][$j]['product_url'].'",
				  "name": "'.$HomeProducts[$i]['products'][$j]['product_name'].'",
				  "offers": {
					  "price": "'.$HomeProducts[$i]['products'][$j]['product_price'].'",
					  "priceCurrency": "'.$_SESSION['currency_code'].'"
				  }
				}';
				
				if($j+1!=$totpro)
				{
					$productlist .= ',';
				}
			}
			if($i+1!=$tottab)
			{
				$productlist .= ',';
			}
		}
		$productlist .= ']';
	}
	else if($type=='category_list')
	{
		global $featuredProducts,$newarrivalProducts,$topsellerProducts,$clearanceProducts,$arrDealItem_Left,$arrCategoryDetail;
		$productlist = '';
		
		if(count($arrCategoryDetail['subcategory_banners'])>0)
		{
			//Sub Category
			$totsubcategory = count($arrCategoryDetail['subcategory_banners']);
			
			$productlist .= '} </script>';
			$productlist .= '<script type="application/ld+json">{';
			$subcategorylist = '';
			$subcategorylist .= '[';
			
			for($i=0; $i<$totsubcategory; $i++)
			{
				$subcategorylist .= '{
					"@type": "ListItem",
					"image": "'.$arrCategoryDetail['subcategory_banners'][$i]['banner'].'",
					"url": "'.$arrCategoryDetail['subcategory_banners'][$i]['banner_url'].'",
					"name": "'.$arrCategoryDetail['subcategory_banners'][$i]['banner_title'].'"
				}';
					
				if($i+1!=$totsubcategory)
				{
					$subcategorylist .= ',';
				}
			}
			$subcategorylist .= ']';
			
			$productlist .= '"@context": "http://schema.org",
			  "@type": "ListItem",
			  "name": "Sub Category",
			  "item": '.$subcategorylist.'';
			  
			//$productlist .= '} </script>';
			
		}
		else
		{
			//Featured
			$totfeatured = count($featuredProducts);
			if($totfeatured>0)
			{
				$productlist .= '} </script>';
				$productlist .= '<script type="application/ld+json">{';
				$featuredlist = '';
				$featuredlist .= '[';
				
				for($i=0; $i<$totfeatured; $i++)
				{
					$featuredlist .= '{
						"@type": "Product",
						"image": "'.$featuredProducts[$i]['image'].'",
						"url": "'.$featuredProducts[$i]['product_url'].'",
						"name": "'.$featuredProducts[$i]['product_name'].'",
						"offers": {
							"price": "'.($featuredProducts[$i]['deal_price']!="" && $featuredProducts[$i]['deal_price'] != 0 ?$featuredProducts[$i]['deal_price'] : $featuredProducts[$i]['product_price']).'",
							"priceCurrency": "'.$_SESSION['currency_code'].'"
						}			  
					}';
						
					if($i+1!=$totfeatured)
					{
						$featuredlist .= ',';
					}
				}
				$featuredlist .= ']';
				
				$productlist .= '"@context": "http://schema.org",
				  "@type": "ListItem",
				  "name": "Featured",
				  "item": '.$featuredlist.'';
				  
				//$productlist .= '} </script>';
			}
			
			//New Arrival
			$totnewarrival = count($newarrivalProducts);
			if($totnewarrival>0)
			{
				$productlist .= '} </script>';
				$productlist .= '<script type="application/ld+json">{';
				$newarrivallist = '';
				$newarrivallist .= '[';
				
				for($i=0; $i<$totnewarrival; $i++)
				{
					$newarrivallist .= '{
						"@type": "Product",
						"image": "'.$newarrivalProducts[$i]['image'].'",
						"url": "'.$newarrivalProducts[$i]['product_url'].'",
						"name": "'.$newarrivalProducts[$i]['product_name'].'",
						"offers": {
							"price": "'.($newarrivalProducts[$i]['deal_price']!="" && $newarrivalProducts[$i]['deal_price'] != 0 ?$newarrivalProducts[$i]['deal_price'] : $newarrivalProducts[$i]['product_price']).'",
							"priceCurrency": "'.$_SESSION['currency_code'].'"
						}			  
					}';
						
					if($i+1!=$totnewarrival)
					{
						$newarrivallist .= ',';
					}
				}
				$newarrivallist .= ']';
				
				$productlist .= '"@context": "http://schema.org",
				  "@type": "ListItem",
				  "name": "New Arrival",
				  "item": '.$newarrivallist.'';
			}
			
			//Best Seller
			$tottopseller = count($topsellerProducts);
			if($tottopseller>0)
			{
				$productlist .= '} </script>';
				$productlist .= '<script type="application/ld+json">{';
				$topsellerlist = '';
				$topsellerlist .= '[';
				
				for($i=0; $i<$tottopseller; $i++)
				{
					$topsellerlist .= '{
						"@type": "Product",
						"image": "'.$topsellerProducts[$i]['image'].'",
						"url": "'.$topsellerProducts[$i]['product_url'].'",
						"name": "'.$topsellerProducts[$i]['product_name'].'",
						"offers": {
							"price": "'.($topsellerProducts[$i]['deal_price']!="" && $topsellerProducts[$i]['deal_price'] != 0 ?$topsellerProducts[$i]['deal_price'] : $topsellerProducts[$i]['product_price']).'",
							"priceCurrency": "'.$_SESSION['currency_code'].'"
						}			  
					}';
						
					if($i+1!=$tottopseller)
					{
						$topsellerlist .= ',';
					}
				}
				$topsellerlist .= ']';
				
				$productlist .= '"@context": "http://schema.org",
				  "@type": "ListItem",
				  "name": "New Arrival",
				  "item": '.$topsellerlist.'';
			}
			
			//Clearence
			$totclearance = count($clearanceProducts);
			if($totclearance>0)
			{
				$productlist .= '} </script>';
				$productlist .= '<script type="application/ld+json">{';
				$clearancelist = '';
				$clearancelist .= '[';
				
				for($i=0; $i<$totclearance; $i++)
				{
					$clearancelist .= '{
						"@type": "Product",
						"image": "'.$clearanceProducts[$i]['image'].'",
						"url": "'.$clearanceProducts[$i]['product_url'].'",
						"name": "'.$clearanceProducts[$i]['product_name'].'",
						"offers": {
							"price": "'.($clearanceProducts[$i]['deal_price']!="" && $clearanceProducts[$i]['deal_price'] != 0 ?     
							$clearanceProducts[$i]['deal_price'] : $clearanceProducts[$i]['product_price']).'",
							"priceCurrency": "'.$_SESSION['currency_code'].'"
						}			  
					}';
						
					if($i+1!=$totclearance)
					{
						$clearancelist .= ',';
					}
				}
				$clearancelist .= ']';
				
				$productlist .= '"@context": "http://schema.org",
				  "@type": "ListItem",
				  "name": "Clearance",
				  "item": '.$clearancelist.'';
			}
		}
		
		$productlist .= getDealItems();
		
	}
	else if($type == 'productdetail')
	{
		global $productDetail,$arrDealItem_Left;
		$totreference = count($productDetail[0]['referenced_products']);
		
		if($totreference>0)
		{
			
			
			for($i=0; $i<$totreference; $i++)
			{
				$productlist .= '} </script>';
			    $productlist .= '<script type="application/ld+json">{';
				$tmpname = $productDetail[0]['referenced_products_keys'][$i];
				$totrefpro = count($productDetail[0]['referenced_products'][$tmpname]);
				
				$refprolist = '';
				$refprolist .= '[';
				
				for($j=0 ; $j<$totrefpro ; $j++)
				{
					$refprolist .= '{
						"@type": "Product",
						"image": "'.$productDetail[0]['referenced_products'][$tmpname][$j]['image'].'",
						"url": "'.$productDetail[0]['referenced_products'][$tmpname][$j]['product_url'].'",
						"name": "'.$productDetail[0]['referenced_products'][$tmpname][$j]['product_name'].'",
						"offers": {
							"price": "'.($productDetail[0]['referenced_products'][$tmpname][$j]['deal_price']!="" && $productDetail[0]['referenced_products'][$tmpname][$j]['deal_price'] != 0 ? $productDetail[0]['referenced_products'][$tmpname][$j]['deal_price'] : $productDetail[0]['referenced_products'][$tmpname][$j]['product_price']).'",
							"priceCurrency": "'.$_SESSION['currency_code'].'"
						}			  
					}';
						
					if($j+1!=$totrefpro)
					{
						$refprolist .= ',';
					}
				}
				$refprolist .= ']';
				
				$productlist .= '"@context": "http://schema.org",
				  "@type": "ListItem",
				  "name": "'.$tmpname.'",
				  "item": '.$refprolist.'';
			   
			}		
		}
		
		//Related Items
		$totrelatedItem = count($productDetail[0]['relatedItem']);
		if($totrelatedItem>0)
		{
			$productlist .= '} </script>';
			$productlist .= '<script type="application/ld+json">{';
			$relatedItemlist = '';
			$relatedItemlist .= '[';
			
			for($i=0; $i<$totrelatedItem; $i++)
			{
				$relatedItemlist .= '{
					"@type": "Product",
					"image": "'.$productDetail[0]['relatedItem'][$i]['image'].'",
					"url": "'.$productDetail[0]['relatedItem'][$i]['product_url'].'",
					"name": "'.$productDetail[0]['relatedItem'][$i]['product_name'].'",
					"offers": {
						"price": "'.($productDetail[0]['relatedItem'][$i]['deal_price']!="" && $productDetail[0]['relatedItem'][$i]['deal_price'] != 0 ? $productDetail[0]['relatedItem'][$i]['deal_price'] : $productDetail[0]['relatedItem'][$i]['product_price']).'",
						"priceCurrency": "'.$_SESSION['currency_code'].'"
					}			  
				}';
					
				if($i+1!=$totrelatedItem)
				{
					$relatedItemlist .= ',';
				}
			}
			$relatedItemlist .= ']';
			
			$productlist .= '"@context": "http://schema.org",
				  "@type": "ListItem",
				  "name": "Related Items",
				  "item": '.$relatedItemlist.'';
			
			
		}
		
		//DealItemlist
		//$productlist .= getDealItems();		
	}
	//echo $productlist;exit;
	return $productlist;
}

function getDealItems()
{
	global $arrDealItem_Left;
	$productlist = '';
	//Deal Products
	$totDealItem = count($arrDealItem_Left);
	if($totDealItem>0)
	{
		$productlist .= '} </script>';
		$productlist .= '<script type="application/ld+json">{';
		$DealItemlist = '';
		$DealItemlist .= '[';
		
		for($i=0; $i<$totDealItem; $i++)
		{
			$DealItemlist .= '{
				"@type": "Product",
				"image": "'.$arrDealItem_Left[$i]['image'].'",
				"url": "'.$arrDealItem_Left[$i]['product_url'].'",
				"name": "'.$arrDealItem_Left[$i]['product_name'].'",
				"offers": {
					"price": "'.($arrDealItem_Left[$i]['deal_price']!="" && $arrDealItem_Left[$i]['deal_price'] != 0 ?$arrDealItem_Left[$i]['deal_price'] : $arrDealItem_Left[$i]['product_price']).'",
					"priceCurrency": "'.$_SESSION['currency_code'].'"
				}			  
			}';
				
			if($i+1!=$totDealItem)
			{
				$DealItemlist .= ',';
			}
		}
		$DealItemlist .= ']';
		
		$productlist .= '"@context": "http://schema.org",
		  "@type": "ItemList",
		  "name": "Deal Item List",
		  "numberOfItems": "'.$totDealItem.'",
		  "itemListElement": '.$DealItemlist.'';
	}
	return $productlist;
}
?>
