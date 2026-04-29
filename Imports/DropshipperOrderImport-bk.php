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
                if($TotalOrder > 0) {
                    $delete_order = DropshipperOrder::where('orders_no', '=', $temp_dropshipper_order['orders_no'])
                                                ->where('customer_id', '=', Auth::user()->customer_id)
                                                ->delete();
                }
                $dropshipper_order = DropshipperOrder::create($temp_dropshipper_order);
                if($dropshipper_order) {
                    $TotalQuantity  = 0;
                    $delete_dropshipper_order_detail = DropshipperOrderDetail::where('orders_no', '=', $dropshipper_order['orders_no'])
                                                ->where('customer_id', '=', Auth::user()->customer_id)
                                                ->delete();
                    foreach(Session::get('sess_secondary_table_first_header_rows_arr') as $key => $value) {
                        $import_field_name = $CSVFieldsDropshipperOrderDetail[$value['col_header_name']]['import_field'];
                        $temp_dropshipper_order_detail[$import_field_name] = $row[$value['csv_key']];
                    }
                    $temp_dropshipper_order_detail['orders_id'] = $dropshipper_order->orders_id;

                    $ProductRs = Products::select('pu_products.products_id','pu_products.sku','pu_products.is_atomizer','pu_products.vtype','pu_products.product_type','pu_products.product_name','pu_products.gender','pu_products.wholesale_price as product_price','pu_products.image','pu_products.current_stock','pu_products.brand_id','pu_products.imanufactureid','pu_products_category.category_id','pu_manufacture.vmanufacture', 'pu_products.minimum_stock')
                                            ->join('pu_products_category', 'pu_products_category.products_id', '=', 'pu_products.products_id')
                                            ->join('pu_category', 'pu_category.category_id', '=', 'pu_products_category.category_id')
                                            ->join('pu_manufacture', 'pu_manufacture.imanufactureid', '=', 'pu_products.imanufactureid')
                                            ->where('pu_products.sku', '=', $temp_dropshipper_order_detail['sku'])
                                            ->whereIn('pu_products.product_type', ['wholesaler','both'])
                                            ->having('product_price', '>', 0)
                                            ->first();
                    if($ProductRs && $ProductRs->count() > 0) {
                        $prd_exit = 0;
                        /*if($ProductRs->minimum_stock > $ProductRs->current_stock || $ProductRs->current_stock <= 0) {
                            $WebsiteStock = 'Out';
                        } else {
                            $WebsiteStock = 'In';
                        }*/
                        if($ProductRs->current_stock > 0) {
                            $WebsiteStock = 'In';
                        } else {
                            $WebsiteStock = 'Out';
                        }
                        if($WebsiteStock == 'Out') {
                            $OrderNoArr[] = $dropshipper_order["orders_no"];
                            if(Session::has('aOutOfStockItems')) {
                                Session::push('aOutOfStockItems', $temp_dropshipper_order_detail['sku']);
                            } else {
                                Session::put('aOutOfStockItems', [$temp_dropshipper_order_detail['sku']]);
                            }
                            if(Session::has('aOutOfStockItemsfull')) {
                                if(!in_array($temp_session_full, Session::get('aOutOfStockItemsfull'))) {
                                    Session::push('aOutOfStockItemsfull', $temp_session_full);
                                }
                            } else {
                                Session::put('aOutOfStockItemsfull', [$temp_session_full]);
                            }
                            continue;
                        }
                        if($WebsiteStock == 'In') {
                            if($ProductRs->current_stock < $temp_dropshipper_order_detail['quantity']) {
                                if(Session::has('HalfaOutOfStockItems') && !in_array($temp_dropshipper_order_detail['sku'],Session::get('HalfaOutOfStockItems'))) {
                                        Session::push('HalfaOutOfStockItems', $temp_dropshipper_order_detail['sku']);
                                } else {
                                    Session::put('HalfaOutOfStockItems', [$temp_dropshipper_order_detail['sku']]);
                                }
                                $prd_exit = $ProductRs->current_stock;
                            }else{
                                $prd_exit = $temp_dropshipper_order_detail['quantity'];
                            }
                        }

                        if($prd_exit  > 0) {
                            $ProductRs->current_stock = $prd_exit;  
                            $per = '';
                            $val = '';
                            if(config('Settings.WHOLESALE_MARKUP') == 'Yes') {
                                $specialpricedtl = GetSpecialPricePercentandValue($temp_dropshipper_order_detail['quantity']);
                                $perval = explode("#",$specialpricedtl);
                                $per = ($perval[0]) ? $perval[0] : 0;
                                $val = ($perval[1]) ? $perval[1] : 0;
                            }
                            $ProductRs->product_price = number_format($ProductRs->product_price,2,'.','');
                            $ProductRs->sale_price = $ProductRs->product_price;

                            ########################### Code For Change Price of Weekly Deal Product Start ###########################// 

                            $dealofdayRS = Dealofweek::select('pu_dealofweek.*','pu_dealofweektitle.*')
                                                    ->join('pu_dealofweektitle', 'pu_dealofweektitle.did', '=', 'pu_dealofweek.did')
                                                    ->join('pu_products', 'pu_products.sku', '=', 'pu_dealofweek.product_sku')
                                                    ->where('pu_dealofweek.product_sku', '=', $ProductRs->sku)
                                                    ->where('pu_products.status', '=', '1')
                                                    ->where('pu_dealofweek.status', '=', '1')
                                                    ->where('pu_dealofweek.start_date', '<=', date('Y-m-d'))
                                                    ->where('pu_dealofweek.end_date', '>=', date('Y-m-d'))
                                                    ->where('pu_dealofweek.deal_type', '=', 'Weekly')
                                                    ->orderBy('pu_dealofweek.dealofweek_id', 'DESC')
                                                    ->first();
                            $ProductRs->IsDealProducts = 'No';
                            $ProductRs->DealDiscountFlag = 'No';
                            if($dealofdayRS && $dealofdayRS->count() > 0) {
                                if(trim($dealofdayRS->product_sku)==trim($ProductRs->sku)) {
                                    if($dealofdayRS->deal_price!='' && $dealofdayRS->deal_price < $ProductRs->sale_price) {
                                        $dealprice =  number_format($dealofdayRS->deal_price,2,'.','');
                                        $ProductRs->product_price = $dealprice;
                                        if($dealofdayRS->description!='') {
                                            $ProductRs->short_description = $dealofdayRS->description;
                                        }
                                    }
                                    $ProductRs->DealDiscountFlag = $dealofdayRS->discount_coupon_flag;  
                                    $ProductRs->IsDealProducts = 'Yes';     
                                }   
                            }

                            ########################### Code For Change Price of Weekly Deal Product End ###########################// 

                            ########################### Code For Change Price of Daily Deal Product Start Here###########################// 
                            $date = date('Y-m-d');
                             
                            $dealofdayRS = Dealofweek::select('pu_dealofweek.*')
                                                    ->join('pu_products', 'pu_products.sku', '=', 'pu_dealofweek.product_sku')
                                                    ->where('pu_dealofweek.product_sku', '=', $ProductRs->sku)
                                                    ->where('pu_products.status', '=', '1')
                                                    ->where('pu_dealofweek.status', '=', '1')
                                                    ->where('pu_dealofweek.start_date', '<=', date('Y-m-d'))
                                                    ->where('pu_dealofweek.end_date', '>=', date('Y-m-d'))
                                                    ->where('pu_dealofweek.deal_type', '=', 'Daily')
                                                    ->orderBy('pu_dealofweek.dealofweek_id', 'DESC')
                                                    ->first();
                           
                            // if(!empty($dealofdayRS)) {
                            if($dealofdayRS && $dealofdayRS->count() > 0) {
                                if(trim($dealofdayRS->product_sku)==trim($ProductRs->sku)) {
                                    if($dealofdayRS->deal_price!='' && $dealofdayRS->deal_price < $ProductRs->sale_price) {
                                        $dealprice = number_format($dealofdayRS->deal_price,2,'.','');
                                        $ProductRs->product_price = $dealprice;
                                        if($dealofdayRS->description!='') {
                                            $ProductRs->short_description = $dealofdayRS->description;
                                        }
                                        $ProductRs->IsDealProducts = "Yes"; 
                                        $ProductRs->DealDiscountFlag = $dealofdayRS->discount_coupon_flag;  
                                    }   
                                }   
                            }
                            ########################### Code For Change Price of Daily Deal Product End ###########################// 

                            $IsCosmo = "No";
                            $IsNandansons = "No";
                            $IsPerfumePW  = "No";
                            $IsPCA  = "No";
                            $VendorSKU = "";
                            
                            $cosmo_our_price = $ProductRs->cosmo_our_price;
                            $nandansons_our_price = $ProductRs->nandansons_our_price;
                            $perfumeworldwide_our_price = $ProductRs->perfumeworldwide_our_price;
                            $pca_our_price = $ProductRs->pca_our_price;

                            $SpecialPriceDetails = '';

                            if(config('Settings.WHOLESALE_MARKUP') == 'Yes') {
                                $ProductRs->product_price = $ProductRs->product_price - $ProductRs->product_price* $per/100;
                            }

                            $ItemPrice = $ProductRs->product_price;
                            $TotalPrice = number_format($ProductRs->product_price*$ProductRs->current_stock,2,'.','');
                            $subTotal = $subTotal + $TotalPrice;

                            $temp_dropshipper_order_detail['products_id'] = $ProductRs->products_id;
                            $temp_dropshipper_order_detail['product_name'] = $ProductRs->product_name."<br/>".$ProductRs->short_description;
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
                        } else {
                            if(Session::has('aOutOfStockItems')) {
                                Session::push('aOutOfStockItems', $temp_dropshipper_order_detail['sku']);
                            } else {
                                Session::put('aOutOfStockItems', [$temp_dropshipper_order_detail['sku']]);
                            }
                            if(Session::has('aOutOfStockItemsfull')) {
                                if(!in_array($temp_session_full, Session::get('aOutOfStockItemsfull'))) {
                                    Session::push('aOutOfStockItemsfull', $temp_session_full);
                                }
                            } else {
                                Session::put('aOutOfStockItemsfull', [$temp_session_full]);
                            }
                            $list_outofstock.= $temp_dropshipper_order_detail['sku'].",";
                            $OrderNoArr[] = $dropshipper_order["orders_no"];
                        }
                    } else {
                        if(Session::has('aOutOfStockItems')) {
                            Session::push('aOutOfStockItems', $temp_dropshipper_order_detail['sku']);
                        } else {
                            Session::put('aOutOfStockItems', [$temp_dropshipper_order_detail['sku']]);
                        }
                        if(Session::has('aOutOfStockItemsfull')) {
                            if(!in_array($temp_session_full, Session::get('aOutOfStockItemsfull'))) {
                                Session::push('aOutOfStockItemsfull', $temp_session_full);
                            }
                        } else {
                            Session::put('aOutOfStockItemsfull', [$temp_session_full]);
                        }
                        $list_outofstock.= $temp_dropshipper_order_detail['sku'].",";
                        $OrderNoArr[] = $dropshipper_order["orders_no"];
                    }

                    /*if ($list_outofstock != '')
                    {
                        $updateStockInfo = array(
                        'is_outofstock' => 'Yes',
                        'list_outofstock' => $list_outofstock
                        );
                        $updms = DropshipperOrder::find($dropshipper_order->orders_id);
                        $updms->update($updateStockInfo); 
                    }*/
                }


                $TotalFoundOrderItem = DropshipperOrderDetail::select('orders_detail_id')
                                            ->where('orders_id', '=', $dropshipper_order->orders_id)
                                            ->where('customer_id', '=', Auth::user()->customer_id)
                                            ->count();
                if ($TotalFoundOrderItem > 0) {
                    $orderTotal = 0;
                    $subTotal = number_format($subTotal, 2, '.', '');
                    $orderTotal = $subTotal;
                    $orderTotal = number_format($orderTotal, 2, '.', '');
                    $updateStockInfo = array(
                        'sub_total' => $subTotal,
                        'order_total' => $orderTotal,
                    );
                    $updms = DropshipperOrder::find($dropshipper_order->orders_id);
                    $updms->update($updateStockInfo); 
                } else {
                    DropshipperOrder::where('orders_id', '=', $dropshipper_order->orders_id)
                                    ->where('customer_id', '=', Auth::user()->customer_id)
                                    ->delete();
                }

            }
            $i++;
        }

        if(Session::has('aOutOfStockItems')) {
            $aOutOfStockItems = array_unique(Session::get('aOutOfStockItems'));
            Session::forget('aOutOfStockItems');
            Session::put('aOutOfStockItems', $aOutOfStockItems);
        }

        if(Session::has('aOutOfStockItemsfull')) {
            $aOutOfStockItemsfull = Session::get('aOutOfStockItemsfull');
            Session::forget('aOutOfStockItemsfull');
            Session::put('aOutOfStockItemsfull', $aOutOfStockItemsfull);
        }
        
    }
    
    public function batchSize(): int
    {
        return 1000;
    }


}
