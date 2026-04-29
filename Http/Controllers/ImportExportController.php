<?php 
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;

use Hash;
use Session;
use App\Models\Customer;
use App\Models\Products;
use App\Models\ShippingMode;
use App\Models\ShippingRule;
use App\Models\ShippingRate;
use App\Models\Stockalert;
use App\Models\Order;
use App\Models\AdminFundLog;
use App\Models\Admin;
use App\Models\PaypalIpnLog;
use App\Models\AuthorizeFundLog;
use App\Models\AmazonFundLog;

use App\Models\DropshipperOrder;
use App\Models\DropshipperOrderDetail;
use App\Models\Dealofweek;
use App\Models\Dealofweektitle;
use App\Models\ImportDropshiporder;

use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DropshipperOrderImport;

use DB;
use Mail;

// use File;
use Illuminate\Support\Facades\File;

use App\Exports\ExportFundHistory;
use App\Exports\ExportOrders;

class ImportExportController extends Controller
{
	public $PageData;
	public function __construct()
    {
    	//
	}
	
	public function ImportOrderCSV(Request $request)
	{
		if(!Auth::user())
			return redirect('/login.html');

		if($request['action'] == 'submit') {

	        $validatedData = $request->validate([
								'fileupload' => 'required|mimes:csv,txt'
					        ], [
					            'fileupload.required' => config('message.ImportOrder.CSV'),
					            'fileupload.mimes' => config('message.ImportOrder.AcceptCSV')
					        ]);
	        $fileselect = $request['fileupload'];
			$tablename = "pu_import_dropshiporder";
			$import_status = $this->ImportCSVInsertWithValidation($fileselect, $tablename);
			if($import_status['result'] == 'false') {
				Session::flash('error', $import_status['message']);
				return redirect()->back();
			}

			if(is_file($fileselect) and !empty($fileselect)) {
				
				$show_error_report = 0;

			    ## Generated error report during processing products start
			    $error_report_file_path = config('global.USER_ORDER_IMPORT_ORDER_PATH') . "Error_Report.csv";
				if(File::exists($error_report_file_path)) {
					File::delete($error_report_file_path);
				}

			    $err_fp = fopen($error_report_file_path, "a+");
			    $csv_fields_arr = Session::get('sess_first_header_row_arr');
			    $tot_csv_fields = count($csv_fields_arr);
			    
				$gen_csv_fields_arr = GenCSVFieldsArr();
			    if ($tot_csv_fields > 0) {
			        $products_header_str = '"Error Type","Error Message",';

			        for ($hd = 0; $hd < $tot_csv_fields; $hd++) {
			            $products_header_str .= '"' . str_replace('"', '""', $gen_csv_fields_arr[$csv_fields_arr[$hd]]['import_header_val']) . '",';
			        }

			        if (trim($products_header_str) != '') {
			            $products_header_str = substr($products_header_str, 0, -1);
			            $products_header_str .= "\n";
			            fwrite($err_fp, $products_header_str);
			           // fclose($err_fp);
			        }
			    }
				
				$err_msg  = "Order Imported Successfully";
				Session::flash('success', $err_msg);
				return redirect()->back();
				
			} else {
				$err_msg = "Failed to upload products, Please try again.";	
				Session::flash('error', $err_msg);
				return redirect()->back();	
			}
		}
	}

	public function ImportCSVInsertWithValidation($fileselect, $tablename)
	{
	    $file_path = config('global.USER_ORDER_IMPORT_ORDER_PATH') ."PU_CSV_".Auth::user()->customer_id. ".csv";
		if(File::exists($file_path)) {
			File::delete($file_path);
		}

		$success = File::copy($fileselect, $file_path);
		if($success == false) {
			return ['result' => 'false', 'message' => 'Failed to save file on the server. Please try again.'];
		} else {
			if(File::exists($file_path)) {

				$handle = fopen($file_path, "rb");
				$rec_counter = 1;
				$tot_success = 0; ## success to insert record counter
				$tot_failure = 0; ## fail to insert record counter
				$getfilesizevar = filesize($file_path);
				$gen_required_fields = GenRequiredFields();
				// $gen_csv_fields_arr = GenCSVFieldsArr();
				$dropshipper_order_fields = CSVFieldsDropshipperOrder();
				$dropshipper_order_details_fields = CSVFieldsDropshipperOrderDetail();
				$gen_csv_fields_arr = array_merge($dropshipper_order_fields,$dropshipper_order_details_fields);
				
				ImportDropshiporder::truncate();
				
				while($data = fgetcsv($handle, $getfilesizevar, ",")) 
				{
					## To process the CSV header rows Start 
					if( $rec_counter == 1 )
					{
						$csv_key = 0;
						foreach($data as $field_num => $field_value)
						{	
							$col_header_name = trim($field_value);
							$col_header_name = trim(str_replace(" ","_",str_replace("/","",strtolower($col_header_name))));
							
							if(!array_key_exists($col_header_name,$gen_csv_fields_arr))
							{
								
								$err_msg  = "<br>Invalid column name <b>'".$field_value."'</b> found.";
								$err_msg .= "Please follow the sample csv format."; 
								return ['result' => 'false', 'message' => $err_msg];
							}
							## stored the first header row column names in the array
							$first_header_rows_arr[] = $col_header_name;

							## stored the first header row column names and key of the filed in CSV in the array for pu_dropshipper_order table.
							if(array_key_exists($col_header_name,$dropshipper_order_fields))
							{
								$main_table_first_header_rows_arr[] = ['col_header_name' => $col_header_name, 'csv_key' => $csv_key];
							}

							## stored the first header row column names and key of the filed in CSV in the array for pu_dropshipper_order_detail table.
							if(array_key_exists($col_header_name,$dropshipper_order_details_fields))
							{
								$secondary_table_first_header_rows_arr[] = ['col_header_name' => $col_header_name, 'csv_key' => $csv_key];
							}
		
							$csv_key++;
						}
						## To check required columns in the products csv start
						foreach($gen_required_fields as $gen_required_fields_key => $gen_required_fields_value) {
							if(!in_array($gen_required_fields_value,$first_header_rows_arr))
							{
								
								$err_msg  = ucwords(str_replace("_"," ",$gen_required_fields_value));
								$err_msg .= " column(s) are required. "; 
								$err_msg .= "Please follow the sample csv format."; 
								return ['result' => 'false', 'message' => $err_msg];
							}
						}
					}
					
					## To process the CSV header rows end 
					Session::put('sess_main_table_first_header_rows_arr', $main_table_first_header_rows_arr);
					Session::put('sess_secondary_table_first_header_rows_arr', $secondary_table_first_header_rows_arr);
					Session::put('sess_first_header_row_arr', $first_header_rows_arr);
					$insert_imp_arr=[];
					for($tc=0;$tc<count($first_header_rows_arr);$tc++)
					{
						if(array_key_exists($first_header_rows_arr[$tc],$gen_csv_fields_arr))
						{
							$import_field_name = $gen_csv_fields_arr[$first_header_rows_arr[$tc]]['import_field'];
							$fieldvalue = trim($data[$tc]);
							
							if($import_field_name != '')
							 $insert_imp_arr[trim($import_field_name)] = $fieldvalue;		
						}
					}
					if(count($insert_imp_arr)>0)
						ImportDropshiporder::create($insert_imp_arr);
					
					$rec_counter = $rec_counter+1;
					
					
				} ## While End
				
		        $importCSV = Excel::import(new DropshipperOrderImport, $file_path);
	    		
				//$CSVRows = $this->csvToArray($file_path);
				//$this->ImportDropshipOrders($CSVRows);
				
				$this->SendStockMail(Auth::user()->customer_id);
				return ['result' => 'true', 'message' => 'Data imported successfully.'];
			}
		}
	}
	public function ImportDropshipOrders($rows)
	{
		config(['app.debug' => true]);
		if(Session::has('aOutOfStockItems')) {
            Session::forget('aOutOfStockItems');
        }
        if(Session::has('aOutOfStockItemsfull')) {
            Session::forget('aOutOfStockItemsfull');
        }
        if(Session::has('HalfaOutOfStockItems')) {
            Session::forget('HalfaOutOfStockItems');
        }
        //$gen_csv_fields_arr = GenCSVFieldsArr();
        $logged_in_user_id = Auth::user()->customer_id;
        //$CSVFieldsDropshipperOrder = CSVFieldsDropshipperOrder();
        //$CSVFieldsDropshipperOrderDetail = CSVFieldsDropshipperOrderDetail();
		
		$customer_res = Customer::select('first_name', 'last_name', 'company_name', 'email', 'address1', 'address2', 'city', 'zip', 'state', 'country', 'phone')
                                    ->where('customer_id','=',Auth::user()->customer_id)
                                    ->first();

        $w_user_type = Session::get('eusertype');
        $checkout_type = 'M';
                                    
        if(Session::has('currency_code') && Session::has('currency_symbol') && Session::has('currency_rate')) {
            $currency_info = Session::get('currency_code').'#'.Session::get('currency_symbol').'#'.Session::get('currency_rate');
        } else {
            $currency_info = '';
        }
		$qry = 0;
        $i = 0;
        $OrderNoArr = array();
		
		foreach ($rows as $row) 
        {
            $subTotal=0;
			$DelRecods = [];
			$list_outofstock = '';
			/*
			foreach(Session::get('sess_first_header_row_arr') as $key => $value) {
				$import_field_name = $gen_csv_fields_arr[$value]['import_field'];
				$import_field_value = $gen_csv_fields_arr[$value]['import_header_val'];
				$temp_session_full[$import_field_name] = $row[$import_field_value];
			}
			foreach(Session::get('sess_main_table_first_header_rows_arr') as $key => $value) {
				$import_field_name = $CSVFieldsDropshipperOrder[$value['col_header_name']]['import_field'];
				$import_field_value = $CSVFieldsDropshipperOrder[$value['col_header_name']]['import_header_val'];
				$temp_dropshipper_order[$import_field_name] = $row[$import_field_value];
			}*/
			
			$TotalOrder = DropshipperOrder::where('orders_no', '=', $row['Orders No'])->count();
					
			if($TotalOrder > 0) {
				$delete_order = DropshipperOrder::where('orders_no', '=', $row['Orders No'])
											->where('customer_id', '=', Auth::user()->customer_id)
											->delete();			
			}
			$temp_dropshipper_order=[];
			$temp_dropshipper_order['orders_no'] = $row['Orders No'];
			$temp_dropshipper_order['bill_first_name'] = $customer_res->first_name;
			$temp_dropshipper_order['bill_last_name'] = $customer_res->last_name;
			$temp_dropshipper_order['bill_company'] = $customer_res->company_name;
			$temp_dropshipper_order['bill_email'] = $customer_res->email;
			$temp_dropshipper_order['bill_address1'] = $customer_res->address1;
			$temp_dropshipper_order['bill_address2'] = $customer_res->address2;
			$temp_dropshipper_order['bill_city'] = $customer_res->city;
			$temp_dropshipper_order['bill_zip'] = $customer_res->zip;
			$temp_dropshipper_order['bill_state'] = $customer_res->state;
			$temp_dropshipper_order['bill_country'] = $customer_res->country;
			$temp_dropshipper_order['bill_phone'] = $customer_res->phone;
			$temp_dropshipper_order['customer_id'] = Auth::user()->customer_id;
			$temp_dropshipper_order['currency_info'] = $currency_info;
			$temp_dropshipper_order['checkout_type'] = $checkout_type;
			$temp_dropshipper_order['user_type'] = $w_user_type;
			$temp_dropshipper_order['status'] = 'Pending';
			$temp_dropshipper_order['customer_ip'] = $_SERVER['REMOTE_ADDR'];
			$temp_dropshipper_order['customer_browser'] = $_SERVER['HTTP_USER_AGENT'];
			$temp_dropshipper_order['is_dropship_order'] = 'Yes';
			$temp_dropshipper_order['Is_GiftCertificatPurchase'] = 0;
			$dropshipper_order = DropshipperOrder::create($temp_dropshipper_order);
			if($dropshipper_order) {
				$TotalQuantity  = 0;
				/*$DetailsRecods = DropshipperOrderDetail::where('orders_no', '=', $dropshipper_order['orders_no'])
											->where('customer_id', '=', Auth::user()->customer_id)
											->get();
				if($DetailsRecods->count() > 0)
				{
					foreach($DetailsRecods as $DetailsRecod)
					$DelRecods[]=$DetailsRecod->orders_detail_id;
				}*/					
				$delete_dropshipper_order_detail = DropshipperOrderDetail::where('orders_no', '=', $row['Orders No'])
											->where('customer_id', '=', Auth::user()->customer_id)
											->delete();
				
				/*foreach(Session::get('sess_secondary_table_first_header_rows_arr') as $key => $value) {
					$import_field_name = $CSVFieldsDropshipperOrderDetail[$value['col_header_name']]['import_field'];
					$import_field_value = $CSVFieldsDropshipperOrderDetail[$value['col_header_name']]['import_header_val'];
					$temp_dropshipper_order_detail[$import_field_name] = $row[$import_field_value];
				}*/
				$OrderProds = ImportDropshiporder::where('orders_no','=',$row['Orders No'])->get();
					
				
				if($OrderProds && $OrderProds->count() > 0) 
				{
					foreach($OrderProds as $OrdProd)
					{
						$ProductRs = DB::table('pu_products')
						->select('pu_products.products_id','pu_products.short_description','pu_products.sku','pu_products.is_atomizer','pu_products.vtype','pu_products.product_type','pu_products.product_name','pu_products.gender','pu_products.wholesale_price as product_price','pu_products.image','pu_products.current_stock','pu_products.brand_id','pu_products.imanufactureid', 'pu_products.minimum_stock')
						->where('pu_products.sku', '=', $OrdProd['sku'])
						->whereIn('pu_products.product_type', ['wholesaler','both'])
						->get();
						
						if($ProductRs && $ProductRs->count() > 0)
						{
							$prd_exit = 0;
							$ProductRs = $ProductRs[0];
							
							if($ProductRs->current_stock > 0) {
								$WebsiteStock = 'In';
							} else {
								$WebsiteStock = 'Out';
							}
							if($WebsiteStock == 'Out') {
								$OrderNoArr[] = $dropshipper_order["orders_no"];
								if(Session::has('aOutOfStockItems')) {
									Session::push('aOutOfStockItems', $row['SKU']);
								} else {
									Session::put('aOutOfStockItems', [$row['SKU']]);
								}
								/*
								if(Session::has('aOutOfStockItemsfull')) {
									if(!in_array($temp_session_full, Session::get('aOutOfStockItemsfull'))) {
										Session::push('aOutOfStockItemsfull', $temp_session_full);
									}
								} else {
									Session::put('aOutOfStockItemsfull', [$temp_session_full]);
								}*/
								continue;
							}
							if($WebsiteStock == 'In') {
								if($ProductRs->current_stock < $row['Qty']) {
									if(Session::has('HalfaOutOfStockItems') && !in_array($row['SKU'],Session::get('HalfaOutOfStockItems'))) {
											Session::push('HalfaOutOfStockItems', $row['SKU']);
									} else {
										Session::put('HalfaOutOfStockItems', [$row['SKU']]);
									}
									$prd_exit = $ProductRs->current_stock;
								}else{
									$prd_exit = $row['Qty'];
								}
							}
								
							if($prd_exit > 0)
							{
								$ProductRs->current_stock = $prd_exit;  
								$per = '';
								$val = '';
								if(config('Settings.WHOLESALE_MARKUP') == 'Yes') {
									$specialpricedtl = GetSpecialPricePercentandValue($row['Qty']);
									$perval = explode("#",$specialpricedtl);
									$per = ($perval[0]) ? $perval[0] : 0;
									$val = ($perval[1]) ? $perval[1] : 0;
								}
								$ProductRs->product_price = number_format($ProductRs->product_price,2,'.','');
								$ProductRs->sale_price = $ProductRs->product_price;
								
								
								
								if(config('Settings.WHOLESALE_MARKUP') == 'Yes') {
									$ProductRs->product_price = $ProductRs->product_price - $ProductRs->product_price* $per/100;
								}

								$ItemPrice = $ProductRs->product_price;
								$TotalPrice = number_format($ProductRs->product_price*$ProductRs->current_stock,2,'.','');
								$subTotal = $subTotal + $TotalPrice;
								
								$IsCosmo = "No";
								$IsNandansons = "No";
								$IsPerfumePW  = "No";
								$IsPCA  = "No";
								$VendorSKU = "";
								$temp_dropshipper_order_detail=[];
								$temp_dropshipper_order_detail['orders_no'] = $dropshipper_order->orders_no;
								$temp_dropshipper_order_detail['orders_id'] = $dropshipper_order->orders_id;
								$temp_dropshipper_order_detail['products_id'] = $ProductRs->products_id;
								$temp_dropshipper_order_detail['product_name'] = $ProductRs->product_name."<br/>".$ProductRs->short_description;
								$temp_dropshipper_order_detail['sku'] = $ProductRs->sku;
								$temp_dropshipper_order_detail['quantity'] = $ProductRs->current_stock;
								$temp_dropshipper_order_detail['price'] = $ProductRs->product_price;
								$temp_dropshipper_order_detail['total'] = $TotalPrice;
								$temp_dropshipper_order_detail['status'] = '1';
								$temp_dropshipper_order_detail['item_price'] = $ProductRs->product_price;
								$temp_dropshipper_order_detail['VendorSKU'] = $VendorSKU;
								$temp_dropshipper_order_detail['IsCosmo'] = $IsCosmo;
								$temp_dropshipper_order_detail['IsNandansons'] = $IsNandansons;
								$temp_dropshipper_order_detail['IsPerfumePW'] = $IsPerfumePW;
								$temp_dropshipper_order_detail['IsPCA'] = $IsPCA;
								$temp_dropshipper_order_detail['customer_id'] = Auth::user()->customer_id;
								
								$TotalQuantity = $TotalQuantity + $ProductRs->current_stock;
								
								$db_sql = DropshipperOrderDetail::create($temp_dropshipper_order_detail);
							}
						}
					}
				}
			}
		}		
	}
	public function SendStockMail($customer_id)
	{
	    if (Session::has('aOutOfStockItems') && count(Session::get('aOutOfStockItems')) > 0) {
	    	$stockalert = Stockalert::select('sku')
	    							->where('email', '=', $customer_id)
	    							->where('estatus', '=', 'No')
	    							->get()->toArray();

	        $mResults = array();
			$mResults = Products::select('pu_products.products_id','pu_products.sku')
									->whereIn('pu_products.sku', Session::get('aOutOfStockItems'))
									->whereNotIn('pu_products.sku', $stockalert)
									->get();

			if($mResults && $mResults->count() > 0) {
	            foreach ($mResults as $singleResult) {
	                $db_data = array();

	                $db_data['email'] = Session::get('sess_useremail');
	                $db_data['estatus'] = 'No';
	                $db_data['sku'] = $singleResult['sku'];
	                $db_data['prod_id'] = $singleResult['products_id'];
					$db_sql = Stockalert::create($db_data);
	            }
	        }
	    }
	}

	/*public function CheckShippingCharge($ship_zip, $ship_state, $ship_country, $subTotal, $TotalQuantity)
	{
		$ShippingModeRS = ShippingMode::where('status', '=', '1')
										->where('eusertype', '=', 'Dropshipper')
										->orderBy('display_position', 'ASC')
										->get();
	    $tempCharge = 0;
		if(count($ShippingModeRS) > 0) {
			foreach ($ShippingModeRS as $shipping_mode_key => $shipping_mode_value) {
	        	$shipping_mode_id = $this->CheckAvailableShippingMethod($shipping_mode_value['shipping_mode_id'], $ship_country, $ship_state, $ship_zip);
	        	$shipname = $shipping_mode_value["type"];
		        if (is_int($shipping_mode_id) == true and $shipping_mode_id > 0) {
		            $tempCharge = $this->CalculateAvailableShippingCharge($ship_zip, $ship_state, $ship_country, $shipping_mode_id, $subTotal, $TotalQuantity);
		            return $tempCharge . "#" . $shipname;
		        } else {
		            continue;
		        }
			}
		}
	    return $tempCharge;
	}

	public function CheckAvailableShippingMethod($shipping_mode_id = NULL, $ship_country, $ship_state, $ship_zip)
	{
	    $shipping_mode_id = (int)$shipping_mode_id;

		$ShippingMethodRS = ShippingMode::select('shipping_mode_id')->where('shipping_mode_id', '=', $shipping_mode_id)
										->where('eusertype', '=', 'Dropshipper')
										->orderBy('shipping_mode_id', 'DESC')
										->first();
	    if ($ship_country != "") {
	        ## this condition is for Z + S + C
	    	$rid = ShippingRule::select('*')
	    						->where('shipping_mode_id', '=', $shipping_mode_id)
						    	->where('zipcode_to', '>=', $ship_zip)
						    	->where('zipcode_from', '<=', $ship_zip)
						    	->where('state', 'like', '%'.$ship_state.'%')
						    	->where('country', 'like', '%'.$ship_country.'%')
						    	->get();
	        ## this condition is for Z + C
	        if (count($rid) <= 0) {
		    	$rid = ShippingRule::select('*')
		    						->where('shipping_mode_id', '=', $shipping_mode_id)
							    	->where('zipcode_to', '>=', $ship_zip)
							    	->where('zipcode_from', '<=', $ship_zip)
							    	->where('country', 'like', '%'.$ship_country.'%')
							    	->get();
	            ## this condition is for S + C
	            if (count($rid) <= 0) {
			    	$rid = ShippingRule::select('*')
			    						->where('shipping_mode_id', '=', $shipping_mode_id)
						    			->where('state', 'like', '%'.$ship_state.'%')
								    	->where('country', 'like', '%'.$ship_country.'%')
								    	->get();
	                ## this condition is for only C
	                if (count($rid) <= 0) {
				    	$rid = ShippingRule::select('*')
				    						->where('shipping_mode_id', '=', $shipping_mode_id)
									    	->where('zipcode_to', '=', '')
									    	->where('zipcode_from', '=', '')
									    	->where('state', '=', '')
									    	->where('country', 'like', '%'.$ship_country.'%')
									    	->get();
	                }
	            }
	        }

	        if (count($rid) > 0) {
	            return (int)$ShippingMethodRS->shipping_mode_id;
	        } else {
	            return false;
	        }
	    } else {
	        return false;
	    }
	}

	public function CalculateAvailableShippingCharge($ship_zip, $ship_state, $ship_country, $shipping_mode_id, $subTotal, $TotalQuantity)
	{
	    $ship_country = substr($ship_country, 0, 2);
	    $shipping_mode_id = (int)$shipping_mode_id;
	    $totalitem = $TotalQuantity;
	    if ($ship_country != "") {
	        ## this condition is for Z + S + C
	    	$rid = ShippingRule::select('shipping_rule_id', 'rule_type', 'is_free_ship', 'free_ship_amt', 'prop_item', 'prop_charge')
	    						->where('shipping_mode_id', '=', $shipping_mode_id)
						    	->where('zipcode_to', '>=', $ship_zip)
						    	->where('zipcode_from', '<=', $ship_zip)
						    	->where('state', 'like', '%'.$ship_state.'%')
						    	->where('country', 'like', '%'.$ship_country.'%')
						    	->get();

	        ## this condition is for Z + C
	        if (count($rid) <= 0) {
		    	$rid = ShippingRule::select('shipping_rule_id', 'rule_type', 'is_free_ship', 'free_ship_amt', 'prop_item', 'prop_charge')
		    						->where('shipping_mode_id', '=', $shipping_mode_id)
							    	->where('zipcode_to', '>=', $ship_zip)
							    	->where('zipcode_from', '<=', $ship_zip)
							    	->where('country', 'like', '%'.$ship_country.'%')
							    	->get();

	            ## this condition is for S + C
	            if (count($rid) <= 0) {
			    	$rid = ShippingRule::select('shipping_rule_id', 'rule_type', 'is_free_ship', 'free_ship_amt', 'prop_item', 'prop_charge')
			    						->where('shipping_mode_id', '=', $shipping_mode_id)
						    			->where('state', 'like', '%'.$ship_state.'%')
								    	->where('country', 'like', '%'.$ship_country.'%')
								    	->get();

	                ## this condition is for only C
	                if (count($rid) <= 0) {
				    	$rid = ShippingRule::select('shipping_rule_id', 'rule_type', 'is_free_ship', 'free_ship_amt', 'prop_item', 'prop_charge')
				    						->where('shipping_mode_id', '=', $shipping_mode_id)
									    	->where('zipcode_to', '=', '')
									    	->where('zipcode_from', '=', '')
									    	->where('state', '=', '')
									    	->where('country', 'like', '%'.$ship_country.'%')
									    	->get();
	                }
	            }
	        }
	    }

	    $shipping_rule_id = $rid[0]["shipping_rule_id"];
	    $rule_type = $rid[0]["rule_type"];

	    if ($shipping_rule_id != "" && $rule_type == 1) {
	    	$rowrate = ShippingRate::select('charge')
	    						->where('shipping_rule_id', '=', $shipping_rule_id)
						    	->where('order_amount', '<=', $subTotal)
						    	->orderBy('order_amount', 'DESC')
						    	->first();
	    } else if ($shipping_rule_id != "" && $rule_type == 0) {
	    	$rowrate = ShippingRate::select('charge')
	    						->where('shipping_rule_id', '=', $shipping_rule_id)
						    	->where('order_amount', '<=', $totalitem)
						    	->orderBy('order_amount', 'DESC')
						    	->first();
	        ############ FOR FREE SHIPPING FOR ITEM COUNT ##########
	        if ($rid[0]["is_free_ship"] == "Yes") {
	            if ($rid[0]["free_ship_amt"] <= $subTotal) {
	                $temp_ShippingCharge = 0;
	                return $temp_ShippingCharge;
	            }
	        }
	        ############## FOR FREE SHIPPING FOR ITEM COUNT ##############
	    }
	    // if(!empty($rowrate)) {
		if($rowrate && $rowrate->count() > 0) {
	    	$charge = $rowrate->charge;
	    } else {
	    	$charge = 0;
	    }

	    if ($charge > 0) {
	    	$temp_ShippingCharge = $charge;
	    } else {
	    	$temp_ShippingCharge = 0;
	    }

	    ########### START CODE FOR CALCULATE PROP SHIP CHARGE###########
	    if ($rid[0]["prop_item"] > 0) {
	        if ($rid[0]["prop_charge"] > 0) {
	            if ($totalitem >= $rid[0]["prop_item"]) {
	                $extraitem = ($totalitem - $rid[0]["prop_item"]) + 1;
	                $propshippingcharge = ($rid[0]["prop_charge"] * $extraitem);
	                $temp_ShippingCharge = $temp_ShippingCharge + $propshippingcharge;
	            }
	        }
	    }

	    return $temp_ShippingCharge;
	}*/

	public function ExportFundHistory(Request $request)
	{
		$export_file_name = "fund_history.csv";
		$customer_id = Session::get('sess_icustomerid');
		
		$result1 = Order::select(DB::raw("CONCAT('Used in Order# ',pu_orders.orders_id) as orders_no,DATE_FORMAT(pu_orders.order_datetime,'%Y-%m-%d %h:%i:%s') as date_time,CONCAT('$-',pu_orders.order_total) as added_fund,'order' as oldnew,'--' as note"))
							->join('pu_customer', 'pu_customer.customer_id', '=', 'pu_orders.customer_id')
							->where('pu_orders.customer_id', '=', Auth::user()->customer_id)
							->where('pu_orders.payment_method', '=', 'Dropshipper Fund')
							->where('pu_customer.eusertype', '=', 'Wholesaler')
							->where('pu_customer.registration_type', '=', 'M');

		$result2 = AdminFundLog::select(DB::raw("'Added by Maxaroma' as orders_no, DATE_FORMAT(pu_admin_fund_log.date,'%Y-%m-%d %h:%i:%s') as date_time,CONCAT('$',pu_admin_fund_log.funded_amount) as added_fund, CONCAT('$',pu_admin_fund_log.old_fund_value,' | $',pu_admin_fund_log.new_fund_value) as oldnew,pu_admin_fund_log.note"))
							->join('pu_admin', 'pu_admin.admin_id', '=', 'pu_admin_fund_log.admin_id')
							->where('pu_admin_fund_log.customer_id', '=', Auth::user()->customer_id);

		$result3 = PaypalIpnLog::select(DB::raw("'Added by You' as orders_no, DATE_FORMAT(pu_paypal_ipn_log.order_date,'%Y-%m-%d %h:%i:%s') as date_time,CONCAT('$',pu_paypal_ipn_log.cust_requested_fund) as added_fund, '--' as oldnew,'--' as note"))
							->join('pu_customer', 'pu_customer.customer_id', '=', 'pu_paypal_ipn_log.customer_id')
							->where('pu_paypal_ipn_log.customer_id', '=', Auth::user()->customer_id);

		$result4 = AuthorizeFundLog::select(DB::raw("'Added by You' as orders_no, DATE_FORMAT(pu_authorize_fund_logs.order_date,'%Y-%m-%d %h:%i:%s') as date_time,CONCAT('$',pu_authorize_fund_logs.fund_amount) as added_fund,'--' as oldnew,'--' as note"))
							->join('pu_customer', 'pu_customer.customer_id', '=', 'pu_authorize_fund_logs.customer_id')
							->where('pu_authorize_fund_logs.customer_id', '=', Auth::user()->customer_id)
							->where('pu_authorize_fund_logs.status', '!=', 'Sent To Stripe');

		$DropShipper_Customer = AmazonFundLog::select(DB::raw("'Added by You' as orders_no, DATE_FORMAT(pu_amazon_fund_logs.order_date,'%Y-%m-%d %h:%i:%s') as date_time,CONCAT('$',pu_amazon_fund_logs.fund_amount) as added_fund,'--' as oldnew,'--' as note"))
							->join('pu_customer', 'pu_customer.customer_id', '=', 'pu_amazon_fund_logs.customer_id')
							->where('pu_amazon_fund_logs.customer_id', '=', Auth::user()->customer_id)
							->where('pu_amazon_fund_logs.status', '!=', 'Declined')
							->orderBy('date_time', 'DESC')
							->union($result1)
							->union($result2)
							->union($result3)
							->union($result4)
							->get();
		// dd($DropShipper_Customer);
		$total_records = count($DropShipper_Customer);
		$csv_data = [];
		if($total_records > 0) {
			for($i=0;$i<$total_records;$i++) {
				if($DropShipper_Customer[$i]['added_fund'] != '') {
					$oldamt = $newamt = $totamt = 0;
					if($DropShipper_Customer[$i]['oldnew']!='--' && $DropShipper_Customer[$i]['oldnew']!='order') {
						$a = explode("|", $DropShipper_Customer[$i-1]['oldnew']);
						$newamt = str_replace(" ","",$a[1]);
						$newamt = str_replace("$","",$a[1]);
						$newamt = abs($newamt);
					}
					if($newamt=='') {
						$newamt = 0;
						$newamt = abs($newamt);
					}
					$fund = str_replace("$","",$DropShipper_Customer[$i]['added_fund']);
					$fund = abs($fund);
					if($DropShipper_Customer[$i]['oldnew']=='--') {
						$totamt = abs($newamt + $fund);
						$valueToAssign = "$".str_replace(" ","",$newamt)." | $".$totamt;
						$valueToAssign1 = "$".$totamt;
						$DropShipper_Customer[$i]['oldnew'] = $valueToAssign;
						$DropShipper_Customer[$i]['oldnew1'] = $valueToAssign1;
					} elseif($DropShipper_Customer[$i]['oldnew']=='order') {
						$totamt = abs($newamt - $fund);
								if($totamt==$fund) {
									$valueToAssign = "$0 | $0";
									$valueToAssign1 = "$0";
								} else {
									$valueToAssign = "$".str_replace(" ","",$newamt)." | $".$totamt;
									$valueToAssign1 = "$".$totamt;
								}
						//$valueToAssign = "$".str_replace(" ","",$newamt)." | $".$totamt;
						$DropShipper_Customer[$i]['oldnew'] = $valueToAssign;
						$DropShipper_Customer[$i]['oldnew1'] = $valueToAssign1;
					} else {
						$a1 = explode("|", $DropShipper_Customer[$i]['oldnew']);
						$newamt11 = $a1[1];
						$valueToAssign1 = $newamt11;
								
						$DropShipper_Customer[$i]['oldnew1'] = $valueToAssign1;
					}
				}

				if($DropShipper_Customer[$i]['added_fund']!='') { 
					if($DropShipper_Customer[$i]["note"]=="--")
					{
						$DropShipper_Customer[$i]["note"] = "";
					}
					$csv_data[$i][] = trim($DropShipper_Customer[$i]["orders_no"]);
					$csv_data[$i][] = trim(date("m-d-Y H:i:s",  strtotime($DropShipper_Customer[$i]['date_time'])));
					$csv_data[$i][] = trim($DropShipper_Customer[$i]["added_fund"]);
					$csv_data[$i][] = trim($DropShipper_Customer[$i]["oldnew1"]);
					$csv_data[$i][] = trim($DropShipper_Customer[$i]["note"]);
				}
			}
		}
		// dd($csv_data);
		// $csv_data = [];
		if(count($csv_data) > 0)
		{
			$header_row = ["Order Number / Added By", "Date Time", "Used / Added Fund", "Balance", "Note"];
			return Excel::download(new ExportFundHistory($csv_data, $header_row), $export_file_name);
		} else {
			Session::flash('error', 'No Data Found!');
			return redirect()->back();
		}
	}

	public function ExportOrderCSV(Request $request)
	{
		// dd($request);
        $validatedData = $request->validate([
							'd_start_date' => 'required|date_format:Y-m-d',
							'd_end_date'	=> 'required|date_format:Y-m-d'
				        ], [
				            'd_start_date.required' => config('message.ExportOrder.FromDate'),
				            'd_end_date.required' => config('message.ExportOrder.ToDate'),
				            'd_start_date.date_format' => config('message.ExportOrder.InvalidDateFormat'),
				            'd_end_date.date_format' => config('message.ExportOrder.InvalidDateFormat'),
				        ]);

		if($request['action'] != 'search') {
			return redirect()->back();
		}

		/*$total_prds = Order::join('pu_order_detail', 'pu_order_detail.orders_id', '=', 'pu_orders.orders_id')
						->where('pu_orders.customer_id', '=', Auth::user()->customer_id)
						->where('pu_orders.ship_status', '=', 'Shipped')
						->where('pu_orders.ship_method', '!=', '')
						->where('pu_orders.tracking_no', '!=', '')
						->where('pu_orders.ship_date', '>=', $request['d_start_date'])
						->where('pu_orders.ship_date', '<=', $request['d_end_date'])
						->count();*/

		$resOrderDtl = Order::select('pu_order_detail.sku', 'pu_order_detail.quantity', 'pu_order_detail.price', 'pu_order_detail.total', 'pu_orders.orders_no', 'pu_orders.ship_first_name', 'pu_orders.ship_last_name', 'pu_orders.ship_address1', 'pu_orders.ship_address2', 'pu_orders.ship_city', 'pu_orders.ship_state', 'pu_orders.ship_country', 'pu_orders.ship_zip', 'pu_orders.ship_phone', 'pu_orders.ship_email', 'pu_orders.tracking_no', 'pu_orders.ship_method')->addSelect(DB::raw('DATE_FORMAT(pu_orders.ship_date, "%m/%d/%Y" ) as ship_date'))
					->join('pu_order_detail', 'pu_order_detail.orders_id', '=', 'pu_orders.orders_id')
					->where('pu_orders.customer_id', '=', Auth::user()->customer_id)
					->where('pu_orders.ship_status', '=', 'Shipped')
					->where('pu_orders.ship_method', '!=', '')
					->where('pu_orders.tracking_no', '!=', '')
					->where('pu_orders.ship_date', '>=', $request['d_start_date'])
					->where('pu_orders.ship_date', '<=', $request['d_end_date'])
					->get();

		$csv_data = [];
		if(count($resOrderDtl) > 0) {
			foreach($resOrderDtl as $result_order_detail_key => $result_order_detail_value) {
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["orders_no"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["sku"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["quantity"]);

				$csv_data[$result_order_detail_key][] = number_format((float)trim($result_order_detail_value["price"]), 2, '.', '');
				$csv_data[$result_order_detail_key][] = number_format((float)trim($result_order_detail_value["total"]), 2, '.', '');

				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_first_name"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_last_name"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_address1"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_address2"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_city"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_state"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_country"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_zip"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_phone"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_email"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["tracking_no"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_method"]);
				$csv_data[$result_order_detail_key][] = trim($result_order_detail_value["ship_date"]);

			}
		}
		if(count($csv_data) > 0)
		{
			$header_row = ["Orders No", "SKU", "Qty", "Item Price", "Total Price", "First Name", "Last Name", "Address1", "Address2", "City", "State", "Country", "Zip", "Phone", "Email", "Tracking Number", "Shipping Method", "Ship Date"];

			$export_file_name = "Orders_".time().".csv";
			return Excel::download(new ExportFundHistory($csv_data, $header_row), $export_file_name);
		} else {
			Session::flash('error', 'No Data Found!');
			return redirect()->back();
		}
	}
	
	public function csvToArray($filename = '', $delimiter = ',')
	{
		if (!file_exists($filename) || !is_readable($filename))
			return false;

		$header = null;
		$data = array();
		if (($handle = fopen($filename, 'r')) !== false)
		{
			while (($row = fgetcsv($handle, 1000, $delimiter)) !== false)
			{
				if (!$header)
					$header = $row;
				else
					$data[] = array_combine($header, $row);
			}
			fclose($handle);
		}

		return $data;
	}

}
?>
