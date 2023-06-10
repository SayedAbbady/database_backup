<?php
// include DB connection
require ("../includes/dbconnection.php");
// include email variables
include("../includes/email_details.php");

// Get all table names
$tables = array();
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// Create a SQL file
$sqlFile = 'database_export.sql';

// Export each table as SQL statements
foreach ($tables as $table) {
    // Get table structure
    $sql = "SHOW CREATE TABLE $table";
    $result = $conn->query($sql);
    $row = $result->fetch_row();
    $tableStructure = $row[1];

    // Write table structure to SQL file
    file_put_contents($sqlFile, "-- Table structure for table `$table`" . PHP_EOL, FILE_APPEND);
    file_put_contents($sqlFile, $tableStructure . ";" . PHP_EOL, FILE_APPEND);

    // Get table data
    $sql = "SELECT * FROM $table";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Write table data to SQL file
        file_put_contents($sqlFile, "-- Data for table `$table`" . PHP_EOL, FILE_APPEND);
        while ($row = $result->fetch_assoc()) {
            $values = implode("', '", array_map('addslashes', $row));
            file_put_contents($sqlFile, "INSERT INTO `$table` VALUES ('$values');" . PHP_EOL, FILE_APPEND);
        }
    }

    file_put_contents($sqlFile, PHP_EOL, FILE_APPEND);
}

// Compress the SQL file into a .gz file
$compressedFile = 'database_export.sql.gz';
$fileContent = file_get_contents($sqlFile);
file_put_contents($compressedFile, gzencode($fileContent, 9));

// Email details
require 'PHPMailer-master/PHPMailerAutoload.php';

$mail = new PHPMailer;

$mail->isSMTP();
$mail->Host = $email_server_accounts;
$mail->SMTPAuth = true;
$mail->SMTPKeepAlive = true; // SMTP connection will not close after each email sent, reduces SMTP overhead
$mail->SMTPSecure = 'tls';
$mail->Port = $port;
$mail->Username = $email_accounts;
$mail->Password = $email_pass_accounts;
$mail->setFrom($email_accounts, $company_name_accounts);
$mail->addReplyTo($email_accounts, $company_name_accounts);
$subject = 'Database Export';
$mail->Subject = $subject;
//Same body for all messages, so set this before the sending loop
//If you generate a different body for each recipient (e.g. you're using a templating system),
//set it inside the loop
$to = 'example@gmail.com';
//msgHTML also sets AltBody, but if you want a custom one, set it afterwards
$mail->AltBody = 'To view the message, please use an HTML compatible email viewer!';
$mail->addAddress($to, "Backup");
$body = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head> 
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="initial-scale=1.0" />
    <meta name="format-detection" content="telephone=no" />
    <title><!-- #{Subject} --></title>
    <style type="text/css">  
    #outlook a {
      padding: 0;
    }
    body {
      width: 100% !important;
      -webkit-text-size-adjust: 100%;
      -ms-text-size-adjust: 100%;
      margin: 0;
      padding: 0;
    }
    
    #body-layout {
      margin: 0;
      padding: 0;
      width: 100% !important;
      line-height: 100% !important;
    }
   
    </style>
  </head>
  <body id="body-layout" style="">
    <h1>DB Backup of '.date("Y-m-d",time()).'</h1>
  </body>
</html>';
$attachment = $compressedFile;
$mail->addAttachment($attachment);

$mail->msgHTML($body);
$mail->send();
        

// $body .= chunk_split(base64_encode(file_get_contents($attachment))) . "\r\n";



// Delete the SQL and compressed files
unlink($sqlFile);
unlink($compressedFile);

// Close the database connection
$conn->close();
?>
