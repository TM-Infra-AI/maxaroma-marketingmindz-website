<?php
set_time_limit(0);
ini_set('memory_limit',"500M");

//error_reporting(E_ALL);
//ini_set('display_errors',1);

$result = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<processedSalesOrderMessage xmlns="api.supplieroasis.com">
		<processedSalesOrder>
		<salesChannelOrderNumber>OSTK-012</salesChannelOrderNumber>
		<salesChannelName>OSTK</salesChannelName>
		<orderDate>2013-06-19T16:54:34.000-06:00</orderDate>
		<warehouseName><code>IML TEST</code></warehouseName>
		<shipToAddress>
			<contactName>Ship Address for Sales Order</contactName>
			<address1>2, Boston Street</address1>
			<city>Salt Lake City</city>
			<stateOrProvince>UT</stateOrProvince>
			<postalCode>84121</postalCode>
			<countryCode>US</countryCode>
			<phone>801-555-1212</phone>
		</shipToAddress>
		<returnAddress>
			<contactName>Mark\'s Ski Hut</contactName>
			<address1>123, ABC Street</address1>
			<city>Salt Lake City</city>
			<stateOrProvince>UT</stateOrProvince>
			<postalCode>84101</postalCode>
			<countryCode>US</countryCode>
			<phone>801-555-1212</phone>
		</returnAddress>
		<shippingSpecifications>
			<isThirdPartyBilling>false</isThirdPartyBilling>
			<isSignatureRequired>true</isSignatureRequired>
			<isDeclaredValueRequired>false</isDeclaredValueRequired>
			<smallParcelShipment>
			<shippingServiceLevel><code>GROUND</code></shippingServiceLevel>
			<shippingAccountNumber>392582735</shippingAccountNumber>
			<carrier><code>FEDEX</code></carrier>
			</smallParcelShipment>
			<isExport>false</isExport>
		</shippingSpecifications>
		<branding/>
		<orderId>2123</orderId>
		<status>COMPLETE</status>
		
		<processedSalesOrderLine>
			<salesChannelLineId>1</salesChannelLineId>
			<partnerSKU>232218BRN</partnerSKU>
			<barcode>XYZ281729</barcode>
			<salesChannelSKU>232218BRN</salesChannelSKU>
			<quantity>1</quantity>
			<specialHandling><code>IN #Inbound Inspection#</code></specialHandling>
			<lineId>2256</lineId>
			<itemId>127</itemId>
			<itemName>Christopher Knight Home Nottingham Brown</itemName>
			<lineStatus>SHIPPED</lineStatus>
			<unitCost>1.00</unitCost>
			<shipConfirmationDetail>
				<quantityShipped>1</quantityShipped>
				<packageDetail>
					<packageID>123</packageID>
					<packageType><code>BOX</code></packageType>
					<packageNumber>12345</packageNumber>
					<packageWeight>11</packageWeight>
					<trackingNumber>Z123123123123</trackingNumber>
				</packageDetail>
				<shipmentDetail>
					<shipmentID>805</shipmentID>
					<shipmentCarrier>FDEG</shipmentCarrier>
					<billingAccountNumber>null</billingAccountNumber>
					<dateShipped>2013-06-24T00:00:00.000-06:00</dateShipped>
					<dateConfirmed>2013-06-24T00:00:00.000-06:00</dateConfirmed>
				</shipmentDetail>
			</shipConfirmationDetail>
		</processedSalesOrderLine>
		
		<processedSalesOrderLine>
			<salesChannelLineId>2</salesChannelLineId>
			<partnerSKU>281729</partnerSKU>
			<barcode>ABC281729</barcode>
			<salesChannelSKU>281729</salesChannelSKU>
			<quantity>1</quantity>
			<specialHandling><code>AS #Assembly</code></specialHandling>
			<lineId>2257</lineId>
			<itemId>129</itemId>
			<itemName>Christopher Knight Home Milano Ivory</itemName>
			<lineStatus>SHIPPED</lineStatus>
			<unitCost>1.00</unitCost>
			<shipConfirmationDetail>
				<quantityShipped>1</quantityShipped>
				<packageDetail>
					<packageID>854</packageID>
					<packageType><code>BOX</code></packageType>
					<packageNumber>2</packageNumber>
					<packageWeight>1.25</packageWeight>
					<trackingNumber>038055710854873</trackingNumber>
				</packageDetail>
				<shipmentDetail>
					<shipmentID>805</shipmentID>
					<shipmentCarrier>FDEG</shipmentCarrier>
					<billingAccountNumber>null</billingAccountNumber>
					<dateShipped>2013-06-24T00:00:00.000-06:00</dateShipped>
					<dateConfirmed>2013-06-24T00:00:00.000-06:00</dateConfirmed>
				</shipmentDetail>
			</shipConfirmationDetail>
		</processedSalesOrderLine>
	</processedSalesOrder>
	
	<processedSalesOrder>
		<salesChannelOrderNumber>OSTK-012</salesChannelOrderNumber>
		<salesChannelName>OSTK</salesChannelName>
		<orderDate>2013-06-19T16:54:34.000-06:00</orderDate>
		<warehouseName><code>IML TEST</code></warehouseName>
		<shipToAddress>
			<contactName>Ship Address for Sales Order</contactName>
			<address1>2, Boston Street</address1>
			<city>Salt Lake City</city>
			<stateOrProvince>UT</stateOrProvince>
			<postalCode>84121</postalCode>
			<countryCode>US</countryCode>
			<phone>801-555-1212</phone>
		</shipToAddress>
		<returnAddress>
			<contactName>Mark\'s Ski Hut</contactName>
			<address1>123, ABC Street</address1>
			<city>Salt Lake City</city>
			<stateOrProvince>UT</stateOrProvince>
			<postalCode>84101</postalCode>
			<countryCode>US</countryCode>
			<phone>801-555-1212</phone>
		</returnAddress>
		<shippingSpecifications>
			<isThirdPartyBilling>false</isThirdPartyBilling>
			<isSignatureRequired>true</isSignatureRequired>
			<isDeclaredValueRequired>false</isDeclaredValueRequired>
			<smallParcelShipment>
			<shippingServiceLevel><code>GROUND</code></shippingServiceLevel>
			<shippingAccountNumber>392582735</shippingAccountNumber>
			<carrier><code>FEDEX</code></carrier>
			</smallParcelShipment>
			<isExport>false</isExport>
		</shippingSpecifications>
		<branding/>
		<orderId>2123</orderId>
		<status>COMPLETE</status>
		
		<processedSalesOrderLine>
			<salesChannelLineId>1</salesChannelLineId>
			<partnerSKU>232218BRN</partnerSKU>
			<barcode>XYZ281729</barcode>
			<salesChannelSKU>232218BRN</salesChannelSKU>
			<quantity>1</quantity>
			<specialHandling><code>IN #Inbound Inspection#</code></specialHandling>
			<lineId>2256</lineId>
			<itemId>127</itemId>
			<itemName>Christopher Knight Home Nottingham Brown</itemName>
			<lineStatus>SHIPPED</lineStatus>
			<unitCost>1.00</unitCost>
			<shipConfirmationDetail>
				<quantityShipped>1</quantityShipped>
				<packageDetail>
					<packageID>123</packageID>
					<packageType><code>BOX</code></packageType>
					<packageNumber>12345</packageNumber>
					<packageWeight>11</packageWeight>
					<trackingNumber>Z123123123123</trackingNumber>
				</packageDetail>
				<shipmentDetail>
					<shipmentID>805</shipmentID>
					<shipmentCarrier>FDEG</shipmentCarrier>
					<billingAccountNumber>null</billingAccountNumber>
					<dateShipped>2013-06-24T00:00:00.000-06:00</dateShipped>
					<dateConfirmed>2013-06-24T00:00:00.000-06:00</dateConfirmed>
				</shipmentDetail>
			</shipConfirmationDetail>
		</processedSalesOrderLine>
		
		<processedSalesOrderLine>
			<salesChannelLineId>2</salesChannelLineId>
			<partnerSKU>281729</partnerSKU>
			<barcode>ABC281729</barcode>
			<salesChannelSKU>281729</salesChannelSKU>
			<quantity>1</quantity>
			<specialHandling><code>AS #Assembly</code></specialHandling>
			<lineId>2257</lineId>
			<itemId>129</itemId>
			<itemName>Christopher Knight Home Milano Ivory</itemName>
			<lineStatus>SHIPPED</lineStatus>
			<unitCost>1.00</unitCost>
			<shipConfirmationDetail>
				<quantityShipped>1</quantityShipped>
				<packageDetail>
					<packageID>854</packageID>
					<packageType><code>BOX</code></packageType>
					<packageNumber>2</packageNumber>
					<packageWeight>1.25</packageWeight>
					<trackingNumber>038055710854873</trackingNumber>
				</packageDetail>
				<shipmentDetail>
					<shipmentID>805</shipmentID>
					<shipmentCarrier>FDEG</shipmentCarrier>
					<billingAccountNumber>null</billingAccountNumber>
					<dateShipped>2013-06-24T00:00:00.000-06:00</dateShipped>
					<dateConfirmed>2013-06-24T00:00:00.000-06:00</dateConfirmed>
				</shipmentDetail>
			</shipConfirmationDetail>
		</processedSalesOrderLine>
	</processedSalesOrder>
</processedSalesOrderMessage>';

$response1 		= simplexml_load_string($result);
$json_string 	= json_encode($response1);
$result_array	= json_decode($json_string, TRUE);

echo "<pre>";print_r($result_array);exit;


function removeSpCharacters ($data)
{
	
	$data = str_replace("<br />","",trim($data));
	$data = str_replace("<br>","",trim($data));
	$data = str_replace("\n","",trim($data));
	$data = str_replace("\r","",trim($data));
	$data = str_replace("\t","",trim($data));
	$data = str_replace('"',"",trim($data));
	$data = str_replace("&amp;","and",trim($data));
	$data = str_replace("+","and",trim($data));
	$data = str_replace("&","and",trim($data));
	$data = str_replace("&eacute;","e",trim($data));
	$data = str_replace("andeacute;","e",trim($data));
	$data = str_replace("é","e",trim($data));
	$data = str_replace("andEacute;","E",trim($data));
	$data = str_replace("&Eacute;","E",trim($data));
	$data = str_replace("É","E",trim($data));
	$data = str_replace("É","E",trim($data));
	$data = str_replace("","",trim($data));	
	
	return $data;
}
function normalizeString($str)
{
	$special_chars = array("~","`","!","^","&","*","®",""); 
	return str_replace($special_chars,"",$str);
}

function setParameter($param,$value)
{
	$param_tag = '';
	$param_open_tag = '';
	$param_close_tag = '';
	
	$param = trim($param);
    $value = trim($value);
    $value = normalizeString($value);
	
	$param_open_tag = "<".$param.">";
	$param_close_tag = "</".$param.">";
	
    $param_tag = $param_open_tag.$value.$param_close_tag;
	
	return $param_tag;
}
function xmlEscape($string)
{
	return str_replace(array('&'), array('&amp;'), $string);
}
?>
