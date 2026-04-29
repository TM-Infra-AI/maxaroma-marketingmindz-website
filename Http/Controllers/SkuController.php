<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SkuController extends Controller
{
    public function additem(Request $request)
    {
$client = new \GuzzleHttp\Client();

$response = $client->request('POST', 'https://staging.skuvault.com/api/inventory/addItem', [
  'body' => '{
    "Sku":"TEstH",
    "WarehouseId": 2,
    "LocationCode":"TEST",
    "Quantity": 10,
    "Reason":"Add",
    "TenantToken": "x/FjCe1aq8MEsd2k5KtHW+5tAWWtacrGDb5lRriKFks=",
    "UserToken": "cTkTP6sPPBckYvUwcB57JLeu3xdfW+BXXvDDe/saRUA="
}',
  'headers' => [
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
  ],
]);

echo $response->getBody();
    }
    
    public function create_brand()
    {
        $client = new \GuzzleHttp\Client();

$response = $client->request('POST', 'https://staging.skuvault.com/api/products/createBrands', [
  'body' => '{"Brands":[{"Name":"Max Aroma"}],"TenantToken":"x/FjCe1aq8MEsd2k5KtHW+5tAWWtacrGDb5lRriKFks=","UserToken":"cTkTP6sPPBckYvUwcB57JLeu3xdfW+BXXvDDe/saRUA="}',
  'headers' => [
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
  ],
]);

echo $response->getBody();
    }
    
     public function gettoken()
    {
        $client = new \GuzzleHttp\Client();

$response = $client->request('POST', 'https://app.skuvault.com/api/gettokens', [
  'body' => '{
     "Email": "harish_singh@technologymindz.com",
     "Password": "HGhy6(^uyghj786879)"
}',
  'headers' => [
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
  ],
]);

echo $response->getBody();
    }
    
    public function create_product()
    {
        $client = new \GuzzleHttp\Client();
$description='<div>\r\n<p class=\"wetext\" data-swiftype-name=\"Long Description\" data-swiftype-type=\"text\" data-swiftype-index=\"true\"><span style=\"text-decoration: underline;\"><strong><a href=\"https://www.maxaroma.com/paco-rabanne/p4u/mid-57/view\" target=\"_blank\" title=\"Paco Rabanne\">Paco Rabanne </a></strong></span>One Milion This sensual and popular fragrance is a blend of Sparkling Grapefruit, Red Orange, Mint, Rose, Cinnamon, Spices, Blond Leather, Blond Wood, Patchouli, and Amber. 1 Million by Paco Rabanne</p>\r\n</div>\r\n<p><strong>Fragrance Notes:</strong></p>\r\n<ol>\r\n<li>Sparkling Grapefruit, Red Orange, Mint, Rose, Cinnamon, Spices, Blond Leather, Blond Wood, Patchouli, and Amber.</li>\r\n<li>Hello</li>\r\n<li></li>\r\n</ol>\r\n<p><span></span></p>\r\n<p><span></span></p>\r\n<p><span></span></p>';
$response = $client->request('POST', 'https://app.skuvault.com/api/products/createProduct', [
  'body' => '{"Sku":"UP334966600792","Description":"Test","ShortDescription":"Test","LongDescription":"Test","Classification":"Test","Supplier":"Test","Brand":"Test","Code":"7598","Client":"String","PartNumber":"String","Cost":79.00,"SalePrice":0.00,"RetailPrice":79.00,"Weight":3.4 Oz,"WeightUnit":"Oz","VariationParentSku":"String","ReorderPoint":0,"MinimumOrderQuantity":10,"MinimumOrderQuantityInfo":"String","Note":"String","Pictures":["https://maxaroma.marketingmindz.com/productimages/large/UP3770001925448.jpg"],"Attributes":{"Test":"Test"},"AllowCreateAp":false,"SupplierInfo":[{"SupplierName":"Test","SupplierPartNumber":"10","Cost":"100","LeadTime":"255","IsActive":"true","IsPrimary":"true"}],"IsSerialized":false,"TenantToken":"x/FjCe1aq8MEsd2k5KtHW+5tAWWtacrGDb5lRriKFks=","UserToken":"cTkTP6sPPBckYvUwcB57JLeu3xdfW+BXXvDDe/saRUA="}',
  'headers' => [
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
  ],
]);

echo $response->getBody();
    }
}
