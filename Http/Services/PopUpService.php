<?php

namespace App\Http\Services;

use App\Models\SiteOffers;
use App\Models\WishlistCategory;
use App\Models\Products;
use App\Models\Customer;
use App\Http\Controllers\Traits\VendorTrait;
use Session;
use Cookie;
use Illuminate\Support\Facades\Auth;

class PopUpService implements PopUpServiceContract
{
    use VendorTrait; 
    
    public function getSiteOffers($sectionId)
    {
        return SiteOffers::where('section_id', '=', $sectionId)
            ->where('status', '=', '1')->where('expiry_date', '>=', date('Y-m-d'))->get();
    }

    public function getWishlistCategory($customerId)
    {
        return WishlistCategory::select('*')
            ->where('customer_id', '=', $customerId)
            ->orderBy('wishlist_category_id', 'DESC')
            ->get();
    }

    public function getProductsById($productId)
    {
        return Products::select('products_id', 'sku', 'product_name')
            ->where('products_id', '=', $productId)
            ->get();
    }

    public function getProductsDetailsById($productId)
    {
        $Product = Products::where('products_id', '=', $productId)
            ->get();
        if($Product->count() > 0)
        {
            return $this->SetProduct($Product[0]);
        }
    }

    public function LoginpProcess($email, $password)
    {
        $Customer = Customer::where('email', $email)
            ->where('password', $password)
            ->where('status', '1')
            ->where('registration_type', 'M')
            ->first();

        if ($Customer && $Customer->count() > 0) {
            if ($Customer->eusertype == "Wholesaler Pending" || $Customer->eusertype == "Retailer") {
                
                return "inactive" ;
            }else{
                Auth::login($Customer, false);
            Session::put('sess_useremail', $Customer->email);
            Session::put('sess_username', $Customer->first_name);
            Session::put('sess_icustomerid', $Customer->customer_id);
            Session::put('eusertype', $Customer->eusertype);
            Session::put('is_dropshipper', $Customer->is_dropshipper);
			Session::put('SpecialCustomerFlag',$Customer->DownloadSpecialPricelist);
            Session::put('etype', 'M');
            Session::put('payment_amount', $Customer->payment_amount);
            //Cookie::queue(Cookie::make('omnisendContactID',$Customer->omnisend_accountid,time()+60*60*24*15));  
            if(Cookie::has('omnisendContactID'))
            {
                Cookie::forget('omnisendContactID');
            }
            $domain = "maxaroma.com";
            setcookie('omnisendContactID', $Customer->omnisend_accountid, time() + (86400 * 395), "/", $domain, true, false);
            return "active";
            }

            
        } else {
            return "wrong";
        }
    }

    public function sendInstantCouponMail($email)
    {
        $Template = GetMailTemplate("INSTANT_COUPON");

        $EmailBody = str_replace('{$SITE_NAME}', config('Settings.SITE_NAME'), $Template[0]->mail_body);
        $EmailBody = str_replace('{$Site_URL}', config('Settings.Site_URL'), $EmailBody);
        $EmailBody = str_replace('{$TOLL_FREE_NO}', config('Settings.TOLL_FREE_NO'), $EmailBody);
        $EmailBody = str_replace('{$COUPON_CODE_VALUE}', config('Settings.COUPON_CODE_VALUE'), $EmailBody);
        $EmailBody = str_replace('{$CONTACT_MAIL}', config('Settings.CONTACT_MAIL'), $EmailBody);

        $FreeShipping = "";
        if (config('Settings.FREESHIPPING_VALUE')) {
            $FreeShipping = '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $' . config('Settings.FREESHIPPING_VALUE') . ' or more Orders</span>';
        }
        $EmailBody = str_replace('{$freeshippinginfo}', $FreeShipping, $EmailBody);

        $To = $email;
        $Subject = str_replace('{$SITE_NAME}', config('Settings.SITE_NAME'), $Template[0]->subject);
        $From = config('Settings.CONTACT_MAIL');
        //SendMail($Subject, $EmailBody, $To, $From);
		/** OMANISEND **/
		OmanisendRequest('62011d76bf58ef001efc0fae',[],['toMail' => $To]);
		/** OMANISEND **/
    }

    public function sendForgotPasswordMail($email, $password)
    {
        $Template = GetMailTemplate("FORGOT_PASSWORD");

        $EmailBody = str_replace('{$SITE_NAME}', config('Settings.SITE_NAME'), $Template[0]->mail_body);
        $EmailBody = str_replace('{$vemail}', $email, $EmailBody);
        $EmailBody = str_replace('{$password}', $password, $EmailBody);
        $EmailBody = str_replace('{$TOLL_FREE_NO}', config('Settings.CONTACT_PHONE_NO'), $EmailBody);
        $EmailBody = str_replace('{$Site_URL}', config('Settings.SITE_URL'), $EmailBody);

        $FreeShipping = "";
        if (config('Settings.FREESHIPPING_VALUE')) {
            $FreeShipping = '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $' . config('Settings.FREESHIPPING_VALUE') . ' or more Orders</span>';
        }
        $EmailBody = str_replace('{$freeshippinginfo}', $FreeShipping, $EmailBody);

        $To = $email;
        $Subject = str_replace('{$SITE_NAME}', config('Settings.SITE_NAME'), $Template[0]->subject);
        $From = config('Settings.CONTACT_MAIL');
        SendMail($Subject, $EmailBody, $To, $From);
    }

    public function sendNicheFragranceMail($email, $coupon_code)
    {
        $Template = GetMailTemplate("NICHE_FRAGRANCES");
        $EmailBody = str_replace('{$Site_URL}', config('Settings.Site_URL'), $Template[0]->mail_body);
        $EmailBody = str_replace('{$SITE_NAME}', config('Settings.SITE_NAME'), $EmailBody);
        $EmailBody = str_replace('{$sender}', '$sender', $EmailBody);
        $EmailBody = str_replace('{$refer_comment}', '$refer_comment', $EmailBody);
        $EmailBody = str_replace('{$cid}', '$cust_id', $EmailBody);
        $EmailBody = str_replace('{$CONTACT_MAIL}', config('Settings.CONTACT_MAIL'), $EmailBody);
        $EmailBody = str_replace('{$coupon_code}', $coupon_code, $EmailBody);

        $FreeShipping = "";
        if (config('Settings.FREESHIPPING_VALUE')) {
            $FreeShipping = '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $' . config('Settings.FREESHIPPING_VALUE') . ' or more Orders</span>';
        }
        $EmailBody = str_replace('{$freeshippinginfo}', $FreeShipping, $EmailBody);
        $To = $email;
        $Subject = str_replace('{$SITE_NAME}', config('Settings.SITE_NAME'), $Template[0]->subject);
        $From = config('Settings.CONTACT_MAIL');
        //SendMail($Subject, $EmailBody, $To, $From);
		/** OMANISEND **/
		OmanisendRequest('620121738a8d4100249b3b18',['coupon_code' => $coupon_code],['toMail' => $To]);
		/** OMANISEND **/
    }
}
