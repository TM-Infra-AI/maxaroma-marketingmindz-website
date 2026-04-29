<?php

namespace App\Imports;

use App\Models\DropshipperOrder;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Session;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

use Illuminate\Support\Facades\Auth;

use App\Models\Customer;
use App\Models\DropshipperOrderDetail;
use App\Models\Products;
use App\Models\Dealofweek;
use App\Models\Dealofweektitle;

use DB;

class DropshipperOrderImport implements ToCollection, WithBatchInserts
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */

    public function collection(Collection $rows)
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
        $gen_csv_fields_arr = GenCSVFieldsArr();
        $logged_in_user_id = Auth::user()->customer_id;
        $CSVFieldsDropshipperOrder = CSVFieldsDropshipperOrder();
        $CSVFieldsDropshipperOrderDetail = CSVFieldsDropshipperOrderDetail();
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
		DB::enableQueryLog();
		$fp = fopen(config('global.PHYSICAL_PATH').'dropshipper_queries.txt', 'w');  
		fwrite($fp, 'Date : '.date('Y-m-d h:i:s').PHP_EOL); 
		fwrite($fp, '--------------------------------------'.PHP_EOL);
		
        $i = 0;
        $OrderNoArr = array();
        foreach ($rows as $row) 
        {
            if($i > 0) {
                $subTotal=0;
                $list_outofstock = '';
                foreach(Session::get('sess_first_header_row_arr') as $key => $value) {
                    $import_field_name = $gen_csv_fields_arr[$value]['import_field'];
                    $temp_session_full[$import_field_name] = $row[$key];
                }
                foreach(Session::get('sess_main_table_first_header_rows_arr') as $key => $value) {
                    $import_field_name = $CSVFieldsDropshipperOrder[$value['col_header_name']]['import_field'];
                    $temp_dropshipper_order[$import_field_name] = $row[$value['csv_key']];
                }
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
                
				$TotalOrder = DropshipperOrder::where('orders_no', '=', $temp_dropshipper_order['orders_no'])->count();
				
				$QryLog = DB::getQueryLog();
				fwrite($fp, $this->getEloquentSqlWithBindings($QryLog[$qry]['query'],'other',$QryLog[$qry]['bindings']).PHP_EOL);  
                fwrite($fp, 'TotalOrder : '.$TotalOrder.PHP_EOL); 
				fwrite($fp,'-------------------------------------------'.PHP_EOL);
				$qry++;
				
				if($TotalOrder > 0) {
                    $delete_order = DropshipperOrder::where('orders_no', '=', $temp_dropshipper_order['orders_no'])
                                                ->where('customer_id', '=', Auth::user()->customer_id)
                                                ->delete();
					$QryLog = DB::getQueryLog();
					fwrite($fp, $this->getEloquentSqlWithBindings($QryLog[$qry]['query'],'other',$QryLog[$qry]['bindings']).PHP_EOL);  
					fwrite($fp, 'DeleteOrder : '.$delete_order.PHP_EOL); 
					fwrite($fp,'-------------------------------------------'.PHP_EOL);
					$qry++;							
                }
                $dropshipper_order = DropshipperOrder::create($temp_dropshipper_order);
				
				$QryLog = DB::getQueryLog();
				fwrite($fp, $this->getEloquentSqlWithBindings($QryLog[$qry]['query'],'other',$QryLog[$qry]['bindings']).PHP_EOL);  
                fwrite($fp, 'DropshipperOrder : '.json_encode($dropshipper_order).PHP_EOL); 
				fwrite($fp,'-------------------------------------------'.PHP_EOL);
				$qry++;
				
				if($dropshipper_order) {
                    $TotalQuantity  = 0;
                    $delete_dropshipper_order_detail = DropshipperOrderDetail::where('orders_no', '=', $dropshipper_order['orders_no'])
                                                ->where('customer_id', '=', Auth::user()->customer_id)
                                                ->delete();
					$QryLog = DB::getQueryLog();
					fwrite($fp, $this->getEloquentSqlWithBindings($QryLog[$qry]['query'],'other',$QryLog[$qry]['bindings']).PHP_EOL);
                    fwrite($fp,'Delete Order Details : '.$delete_dropshipper_order_detail.PHP_EOL);
					fwrite($fp,'-------------------------------------------'.PHP_EOL);
					$qry++;
					
					foreach(Session::get('sess_secondary_table_first_header_rows_arr') as $key => $value) {
                        $import_field_name = $CSVFieldsDropshipperOrderDetail[$value['col_header_name']]['import_field'];
                        $temp_dropshipper_order_detail[$import_field_name] = $row[$value['csv_key']];
                    }
					$temp_dropshipper_order_detail['orders_no'] = (string)$temp_dropshipper_order_detail['orders_no'];
                    $temp_dropshipper_order_detail['orders_id'] = $dropshipper_order->orders_id;
					/*$ProductRs = Products::select('pu_products.products_id','pu_products.sku','pu_products.is_atomizer','pu_products.vtype','pu_products.product_type','pu_products.product_name','pu_products.gender','pu_products.wholesale_price as product_price','pu_products.image','pu_products.current_stock','pu_products.brand_id','pu_products.imanufactureid','pu_products_category.category_id','pu_manufacture.vmanufacture', 'pu_products.minimum_stock')
                                            ->join('pu_products_category', 'pu_products_category.products_id', '=', 'pu_products.products_id')
                                            ->join('pu_category', 'pu_category.category_id', '=', 'pu_products_category.category_id')
                                            ->join('pu_manufacture', 'pu_manufacture.imanufactureid', '=', 'pu_products.imanufactureid')
                                            ->where('pu_products.sku', '=', $temp_dropshipper_order_detail['sku'])
                                            ->whereIn('pu_products.product_type', ['wholesaler','both'])
                                            ->having('product_price', '>', 0)
                                            ->first();*/
					
					$ProductRs = DB::table('pu_products as po')
						->select('po.products_id','po.sku','po.is_atomizer','po.vtype','po.product_type','po.product_name','po.gender','po.wholesale_price as product_price','po.image','po.current_stock as Stock','po.brand_id','po.imanufactureid', 'po.minimum_stock')                                            
						->where('po.sku', '=', $temp_dropshipper_order_detail['sku'])
						->whereIn('po.product_type', ['wholesaler','both'])
						->get();	
				
					$QryLog = DB::getQueryLog();
					fwrite($fp, $this->getEloquentSqlWithBindings($QryLog[$qry]['query'],'other',$QryLog[$qry]['bindings']).PHP_EOL);
					fwrite($fp,'Product Count : '.$ProductRs->count().PHP_EOL);
					$qry++;
					
                    if($ProductRs && $ProductRs->count() > 0) 
					{
						fwrite($fp,'--------- Product Details ---------------- '.PHP_EOL);
						fwrite($fp,json_encode($ProductRs[0]).PHP_EOL);
						fwrite($fp,'-------------------------------------------'.PHP_EOL);
						$Entry = 0;
						$ProductRs = $ProductRs[0];
						//dd($ProductRs);
						
						$IsCosmo = "No";
						$IsNandansons = "No";
						$IsPerfumePW  = "No";
						$IsPCA  = "No";
						$VendorSKU = "";
						$TotalPrice = 6.5;
						$temp_dropshipper_order_detail['products_id'] = 12345;
						$temp_dropshipper_order_detail['product_name'] = "TEST";
						$temp_dropshipper_order_detail['quantity'] = 1;
						$temp_dropshipper_order_detail['price'] = 6.5;
						$temp_dropshipper_order_detail['total'] = 6.5;
						$temp_dropshipper_order_detail['status'] = '1';
						$temp_dropshipper_order_detail['item_price'] = 6.5;
						$temp_dropshipper_order_detail['VendorSKU'] = $VendorSKU;
						$temp_dropshipper_order_detail['IsCosmo'] = $IsCosmo;
						$temp_dropshipper_order_detail['IsNandansons'] = $IsNandansons;
						$temp_dropshipper_order_detail['IsPerfumePW'] = $IsPerfumePW;
						$temp_dropshipper_order_detail['IsPCA'] = $IsPCA;
						$temp_dropshipper_order_detail['customer_id'] = Auth::user()->customer_id;
						
						$db_sql = DropshipperOrderDetail::create($temp_dropshipper_order_detail);
						$QryLog = DB::getQueryLog();
						fwrite($fp, $this->getEloquentSqlWithBindings($QryLog[$qry]['query'],'other',$QryLog[$qry]['bindings']).PHP_EOL);
						fwrite($fp, 'Dropshipper Order Details : '.json_encode($db_sql).PHP_EOL); 
						fwrite($fp,'-------------------------------------------'.PHP_EOL);
						$qry++;
					}	
				}
            }
            $i++;
        }
		/*$QryLogs = DB::getQueryLog();
		foreach($QryLogs as $QryLog)
		{
			fwrite($fp, $this->getEloquentSqlWithBindings($QryLog['query'],$QryLog['bindings']).PHP_EOL);
		}*/
    }

    public function batchSize(): int
    {
        return 1000;
    }
	
	public static function getEloquentSqlWithBindings($query,$qryflag='',$qrybindings=[])
	{
		/*return vsprintf(str_replace('?', '%s', $query), collect($qrybindings)->map(function ($binding) {
				$binding = addslashes($binding);
				return is_numeric($binding) ? $binding : "'{$binding}'";
			})->toArray());*/
			
		if($qryflag == 'other')
		{
			return vsprintf(str_replace('?', '%s', $query), collect($qrybindings)->map(function ($binding) {
				$binding = addslashes($binding);
				return is_numeric($binding) ? $binding : "'{$binding}'";
			})->toArray());
		} else {
			return vsprintf(str_replace('?', '%s', $query->toSql()), collect($query->getBindings())->map(function ($binding) {
				$binding = addslashes($binding);
				return is_numeric($binding) ? $binding : "'{$binding}'";
			})->toArray());
		}
	}
}
