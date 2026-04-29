<?php 
////////////////////////
/*
	Description 	:	function for the attachment send with mail...
	Author			:	Kishor kishor ranga
	Date 			:	23 Sep 2004
	Time 			:	2.47 PM , Thursday
*/
//////////////////////////
// FUCNTION DETAILS
////////////////////////
// we are using  some of the argument for this function which are described below
/*
	1	$fileatt= Used to locate the path and file name like e.g.  "zip/spinfo.htm"
	2	$fileName= file name  by which you want to send , display to receiver  "ShipbuilderInfo";
	3	$fileExt = file extention .. , file formate like .htm or .pdf etc
	4	$email_subject= subject of mail 
	5	$email_to= to whom you want to send like e.g. - "kishor.ranga@sunarctechnologies.com";
	6	$email_from = who is ssending or from email id , from whom the mail is sending like e.g. - "rajneesh.vyas@sunarctechnologies.com";
*/
function my_attachment($fileatt,$fileName, $fileExt, $email_subject, $email_to,$email_from, $email_message)
{
			
			$fileatt_type = "application/octet-stream"; // File Type 
			$headers = "From: ".$email_from; 
			$semi_rand = md5(time()); 
			$mime_boundary = "==Multipart_Boundary_x{$semi_rand}x"; 
			$headers .= "\nMIME-Version: 1.0\n" . 
						"Content-Type: multipart/mixed;\n" . 
						" boundary=\"{$mime_boundary}\""; 
			$email_message .= "This is a multi-part message in MIME format.\n\n" . 
							"--{$mime_boundary}\n" . 
							"Content-Type:text/html; charset=\"iso-8859-1\"\n" . 
						   "Content-Transfer-Encoding: 7bit\n\n" . 
			$email_message . "\n\n"; 
			$fileatt_name =  $fileName.".".$fileExt; // Filename that will be used for the file as the attachment 		
			$file = fopen($fileatt,'rb'); 
			$data = fread($file,filesize($fileatt)); 
			fclose($file); 
			$data = chunk_split(base64_encode($data)); 
			$email_message .= "--{$mime_boundary}\n" . 
							  "Content-Type: {$fileatt_type};\n" . 
							  " name=\"{$fileatt_name}\"\n" . 
							  "Content-Transfer-Encoding: base64\n\n" . 
							 $data . "\n\n" . 
							  "--{$mime_boundary}\n"; 
			unset($data); 
			unset($file); 
			unset($fileatt);
			unset($fileatt_type); 
			unset($fileatt_name); 

			$ok = @mail($email_to, $email_subject, $email_message, $headers); 
			if($ok) { 
				echo "<font face=verdana size=2></font>"; 
			} else { 
				die("Sorry but the email could not be sent. Please go back and try again!"); 
			} 
}

?>
