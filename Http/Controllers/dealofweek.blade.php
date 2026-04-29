@extends('layouts.app')
@section('content')
<main class="clearfix">
	<section class="deal-time">
		<div class="container">
	  		<div class="row">
	  			<div class="col-12 text-center">
	  				<h1>1,000 One-Time Deals</h1>
				  	<div class="deal-countdown">
				  		<p>Deals End</p>
						<input type="hidden" id="deal_start_date" value="{{date('d-m-Y h:i:s')}}"/>
						<input type="hidden" id="deal_end_date" value="{{$Deal->deal_end_date or ''}}"/>
						<ul id="counter_1">
							<li><span id="days"></span><br/>day</li>
						    <li><span id="hrs"></span><br/>Hours</li>
						    <li><span id="min"></span><br/>Mins</li>
						    <li><span id="sec"></span><br/>Secs</li>
						</ul>
				  	</div>	
	  			</div>
	  		</div>
	  	</div>
	</section>
	<section class="deal-listing">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<div class="deal-filter d-sm-flex justify-content-between align-items-center">
						<div class="deal-select">
							<div class="select-auto">
								<select class="select" id="brands">
									<option>Brands</option>
									@foreach($BrandList as $key => $Brand)
									<option value="{{$key}}" @if($SelBrand == $key) selected @endif >{{$Brand}}</option>
									@endforeach
								</select>
								<input type="hidden" id="selbrands" value=""/>
							</div>
							<div class="select-auto">
								<select class="select" id="size">
									<option>Size</option>
									@foreach($SizeList as $key => $Size)
									<option value="{{$Size}}" @if($SelSize == $key) selected @endif >{{$Size}}</option>
									@endforeach
								</select>
								<input type="hidden" id="selsize" value=""/>
							</div>
							<div class="select-auto">
								<select class="select" id="dealsort">
									<option>Sort By</option>
									<option value="priceLH">Price Low to High</option>
									<option value="priceHL">Price High to Low</option>
									<option value="priceAZ">A-Z</option>
									<option value="priceZA">Z-A</option>
								</select>
								<input type="hidden" id="sortopt" value=""/>
							</div>
						</div>
						<div class="deal-src-sec">
							<div class="select-auto mr-2 price-rang-del">
								<div class="filter_acrd filter_acrd_act">
								<div class="filter_acrd_hd">
									<span class="title"></span> 
								</div>
								<div class="filter_acrd_con">
									<div class="inner">
										<div class="scroll">
											<input type="hidden" id="pricechange" value="0"/>
											<input type="hidden" id="minprice" value="{{$MinPrice}}"/>
											<input type="hidden" id="maxprice" value="{{$MaxPrice}}"/>
											<input type="hidden" id="chgminprice" value="{{$MinPrice}}"/>
											<input type="hidden" id="chgmaxprice" value="{{$MaxPrice}}"/>
											<div class="range_slider_controller"><div id="slider-range"></div></div>
											<input type="text" id="amount" readonly class="renge-price">
										</div>
									</div>
								</div>
							</div>
							</div>
							<div class="deal-src-f">
								<input type="text" id="searchkey" name="searchkey" value="{{$SelKey}}" placeholder="Search By UPC" class="form-control">
								<x-button link="javascript:void(0);" btntext="Search" classname="btn btn-primary" bid="btnsearch"/>	
							</div>
						</div>
						<div class="hide-sold">
							<a href="#" class="reset_link" id="reset_filters">Reset Filters</a>
							<label class="checkbox-label">
								<div class="chebox">
									<input type="checkbox" id="chksold" {{$SelStock}} >
									<span class="checkmark"></span>
								</div>Hide sold out
							</label>
						</div>	
					</div>
				</div>
			</div>
			<div id="cover-spin"></div>
			<div class="row pt-3 pt-md-5" id="dealofweek">
				@foreach($DealProds as $key => $Product)
					<div class="col-md-4 col-6">
						<x-productbox :prodData="$Product"/>
					</div>
				@endforeach
			</div>
			<div class="pb-2" id="loadmore" style="@if($TotalProducts <= 12) display:none @endif">
				<a class="list-more d-block" data-page='1'> Load More... </a>
			</div>
			<div class="pb-2" id="noprod" style="@if($TotalProducts > 0) display:none @endif">No Record Found</div>
		</div>
	</section>
</main>
@endsection