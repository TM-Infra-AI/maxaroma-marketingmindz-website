<?php

namespace App\Http\Services;

interface PopUpServiceContract
{
    public function getSiteOffers($sectionId);

    public function getWishlistCategory($customerId);

    public function getProductsById($productId);

    public function getProductsDetailsById($productId);

    public function LoginpProcess($email, $password);

    public function sendInstantCouponMail($email);

    public function sendForgotPasswordMail($email, $password);

    public function sendNicheFragranceMail($email, $coupon_code);

}
