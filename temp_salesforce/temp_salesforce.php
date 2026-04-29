<?php
error_reporting(0);
ini_set("display_errors",0);

set_time_limit(0);
ini_set('memory_limit',"1000M");
//include_once("/home/peraroma/staging/lib/config_setting.php");
include_once("/home/peraroma/public_html/lib/config_setting.php");
require_once(CLASS_PATH."general.cls.php");
require_once(CLASS_PATH."product.cls.php");

$generalobj  = new General($obj,$smarty);


		$file_path = __DIR__ ."/MaptoCustomerv2.csv";
		
		if(file_exists($file_path))	
		{	
			$handle = fopen($file_path, "rb");
			$rec_counter = 1;
			$tot_success = 0; ## success to insert record counter
			$tot_failure = 0; ## fail to insert record counter
			$getfilesizevar = filesize($file_path);
			
			//$db_sql = $obj->sql_query("TRUNCATE TABLE temp_salesforce");
			
			while($data = fgetcsv($handle, $getfilesizevar, ",")) 
			{
				if($rec_counter != "1"){
					$Account_ID = $data[0];
					$Contact_ID = $data[1];
					$Customer_ID = $data[2];
					$Email = $data[3];
					$Data_Import_Source = $data[4];
					
					
					//echo "<pre>";print_r($data);//exit;
					
					$cust_sql = "SELECT is_deleted,registration_type FROM ".TABLE_PREFIX."customer WHERE customer_id='".$Customer_ID."'";
					$check_cust_email = $obj->select($cust_sql);
					//echo "<pre>";print_r($check_cust_email);exit;
					
					$Type = ($check_cust_email[0]['registration_type'] == "M") ? "Member" : "Guest";
					$Action = ($check_cust_email[0]['is_deleted'] == "No") ? "Keep" : "Delete";
					
					$Data_insert['Account_ID'] = $Account_ID;
					$Data_insert['Contact_ID'] = $Contact_ID;
					$Data_insert['Customer_ID'] = $Customer_ID;
					$Data_insert['Email'] = $Email;
					$Data_insert['Data_Import_Source'] = $Data_Import_Source;
					$Data_insert['Type'] = $Type;
					$Data_insert['is_deleted'] = $check_cust_email[0]['is_deleted'];
					$Data_insert['Action'] = $Action;
					
					$id = $obj->insert('temp_salesforce', $Data_insert);
					//echo "<pre>";print_r(error_get_last());
				}
				
				//if($rec_counter == 5){exit;}
				
				$rec_counter = $rec_counter + 1;
			}
			
			echo "Completed";
		} 
?>
