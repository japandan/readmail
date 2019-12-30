<!-
  The following script is used to check a gmail account and process the emails.
  I use this script to read emails comming from ServiceNow helpdesk ticket system.
  The script will read each new email and look for the "location:" field.
  It will then check a MySQL table to see what tech email addresses are used for the
  location.  It will forward the new email to these techs.  

  This script can be executed via the web or via a cron job.
  I currently have a cron.daily job that executes the script every hour.    

This is what is done when the script runs:
	1.  Checks the email account for headers from new email.  
	2.  Looks for an NEW INCIDENT# in the email. 
             If found, it realizes the email is for a new Ticket.
	3.  If the ticket is new, a record is inserted into the CALL_LOG database 
             and status set to OPEN.
	4.  The email is parsed for the LOCATION city.
	5.  The LOCATION is looked up in a simple MySQL table that has 
            LOCATION:EMAIL@example.com for each tech.
	6.  The email is forwarded to each tech email.
	7.  The email is moved from the INBOX to a folder called "Processed".
    (disabled for testing)

-->

<?php
/* update this file with the database and imap login credentials */
require('/var/www/mysqlpassword.php');

// exit if imap is not installed in PHP
echo "imap module: ", extension_loaded('imap') ? 'OK' : 'MISSING', "\n<br>";
echo "";
if ( ! extension_loaded('imap') ) { echo "install php_imap\n"; exit(); }


/*  Change the date from 21/12/2017 05:00:00 GMT  to YYYY-MM-DD  */
function fixdate($date_time) {
        if (preg_match('@(?P<DD>\d\d)/(?P<MM>\d\d)/(?P<YYYY>\d+)\b@',$date_time,$matches)){
                $fixed = "$matches[YYYY]-$matches[MM]-$matches[DD]";
                return $fixed;
        }
}




/* pull a string out of text    */
function grep( $search, $text ) {
        $pattern ="/($search\s+)(.+)/";
/*      echo "searching RegEx: $pattern <br>\n"; */
        if (preg_match($pattern, $text, $matches)){
                $found=$matches[2];
                echo "$search  => $found <br>\n";
        }
        else {
                echo "$search <no match>\n";
                $found="";

        }
        return $found;
}


//  retrieve the list of tech emails from the SITES-TECHS table
function send_emails( $location, $ticket, $msg ) {
        global $dbname, $username, $password, $servername, $tech_emails, $imap_user, $sites_techs;

        $db = new mysqli($servername, $username, $password, $dbname);
        if ($db->connect_error)
                die("DB Connection failed: ".$db->connect_error);

        $sql="SELECT * FROM $sites_techs WHERE site = '$location'";
        //$sql="UPDATE esp_sites_techs SET tech_email = CONCAT(tech,'@datostech.com') ";
        $headers = "From: $imap_user";
        $email_group = $db->query( $sql );
        if ($email_group->num_rows > 0)  {
                while ($row = $email_group->fetch_assoc()) {
                        $tech_emails = $tech_emails.",".$row["tech_email"];
                }
                mail($tech_emails, "NEW ESP Ticket $ticket for '$location'", $msg, $headers);
                echo "\nSending emails to techs found for location '$location'";
                echo $tech_emails;
        } else {
                mail($tech_emails, "NEW ESP Ticket $ticket  UNKNOWN LOCATION '$location'", "PLEASE FORWARD TO CORRECT TECHS!!\n".$msg, $headers);
                echo "\nSending emails to managers.  No techs emails found for location '$location'";
        }

}

/*  update table esp_incidents columns tasknumber, status
*/
function dbupdate( $tasknumber, $status ){

        global $dbname, $username, $password, $servername, $incidents;
/* open the database  */
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error)
                die("DB Connection failed: ".$conn->connect_error);

        $sql ="UPDATE $incidents SET status='$status' WHERE tasknumber = '$tasknumber'";
        if ($conn->query($sql) === TRUE) {
                echo "Ticket $tasknumber changed to status=$status \n\n"; }
        else {
                echo "Error:$conn->error<br>\n\n";
        }
        $conn->close();

}





/*  insert the table esp_incidents columns tasknumber, status, contract
*/
function dbinsert( $tasknumber, $status, $contract, $receivedon, $text ){
        global $dbname, $username, $password, $servername, $incidents;

/*  variables pulled from text  */
        $location = rtrim( grep("City:", $text) );
        $address = grep("Address:", $text);
        $caller = grep("Caller Name :",$text);
        $contactphone = grep("Caller Phone Number :", $text);
        $alternate_contact = grep("Alternate Onsite Contact Number :",$text);
        $summary = grep("PROBLEM:", $text);
        $respondby = fixdate( grep("Respond By:", $text));
        $restoreby= fixdate( grep("Restore By:", $text));

// send an email to the techs
//      echo nl2br($text);     // optionally show the message on the screen
        $msg =wordwrap($text, 50);
        send_emails( $location, $tasknumber, $msg );

/* open the database  */
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error)
                die("DB Connection failed: ".$conn->connect_error);


        $sql ="INSERT INTO $incidents (tasknumber, status, contract,
         location, summary, caller, contactphone, receivedon, respondby,
         restoreby,address, alternate_contact ) VALUES
        ( '$tasknumber','$status','$contract','$location','$summary','$caller',
        '$contactphone','$receivedon','$respondby','$restoreby', '$address',
        '$alternate_contact')";

        if ($conn->query($sql) === TRUE) {
                echo "New db record added for $location Ticket $ticket\n\n"; }
        else {
                echo "Error:$conn->error<br>\n\n";
        }
        $conn->close();
}


/*  check the subject for the "New Incident Task" and add ITSK# to MySQL as a new ticket

    If the subject task reads ITSK0279712 (SV1801100123) Work Log Updated, then look for
    "Offsite (Resolution):" and if found, CLOSE the ticket in the call_log

*/
function addtodb( $subjectline, $newdate, $text ) {
        $contract = "ESP";
        if ( preg_match('/^New Incident Task/',$subjectline, $matches,0) or preg_match('/^NEW ESP/',$subjectline, $matches,0) ){
                preg_match('/ITSK\d+\b/',$subjectline, $matches);
                $ticket = $matches[0];
                if(preg_match('/\bDoDEA\b/',$subjectline, $matches))
                        $contract = $matches[0];
                echo "<br>$ticket will be added to database.<br>\n\n";
                dbinsert($ticket,"OPEN",$contract,$newdate, $text);
        }
        elseif  (preg_match('/ITSK\d+\b/',$subjectline, $matches,0) ) {
                $ticket = $matches[0];
                echo "Ticket:".$ticket." was found but is not new.<br>\n";
                if (preg_match('/Resolution/', $text, $matches,0)){
                        dbupdate( $ticket, "CLOSED");
                }

        }

}



/*

This is the sample email that we need to parse for the fields...

Description:
PROBLEM: Laptop case is separating or hinge is broken
PARTS SHIPPED: T430 barebone
RESOLUTION STEPS: Verify problem exists, replace barebone notebook (swap over HDD, battery, ODD Bezel, Keyboard).? Defective notebook needs to return to NCS Technologies using provided prepaid return label.
SHIPPED TO: Darrick Haynes

---Formatted Descr---
Product: LENOV-2347-7CD
Serial Number : MJXBWXK
Customer / Site ID: EA88474600190
Address: YOKOSUKA NAVAL BASE
City: YOKOSUKA


FOR CLOSURE the following email is received...
Your Incident Task (ITSK0279360) has been updated with the following entry:

Offsite (Resolution):
Date Attended: 1/17/2018       { the rest of the information comes in different formats }
Attended and Closed
Started Time: 1:45
End Time: 1:55
Location: Yokota High School
Work Summary: Replaced keyboard with missing key.
Second Visit: No, a second visit is not required
Serial : MJO1QZKO
Model : T440P
Number: ITSK0279360


------------------------------------------------ end of email ----------
Database to store the headers..
table: esp_incidents
        tasknumber  <-- message header
        status   <-- open, closed, logged
        contract <-- DoDEA, etc
        assignedtech <-- firstname.lastname
        location <-- from message body..city?
        summary  <- from message body
        caller  <-- from message body
        closedon <-- from CALL_LOG when tech closes or Import from ESP portal
        contactphone  <-- from message body
        receivedon:date <-- date email comes in
        respondby:date <-- from message body
        restoreby:date <-- from message body
*/


/*  open the mailbox  */
        echo "checking mail on $imap_server for account: $imap_user \n\n";
        $imap = imap_open( $imap_server, "$imap_user", "$imap_password" );
/*        $imap = imap_open("{datostech.com:143}", "call.log@datostech.com", "imap-password");*/

        $message_count = imap_num_msg($imap);
        echo "Messages:".$message_count."\n\n";
/* set the timezone  */
        date_default_timezone_set('America/New_York');

/*  MAIN LOOP  */
        for ($i = 1; $i <= $message_count; ++$i) {
                $header = imap_header($imap, $i);
                $body = trim(substr(imap_body($imap, $i), 0, 8000));
                $prettydate = date("Y-m-d", $header->udate);

                if (isset($header->from[0]->personal)) {
                        $personal = $header->from[0]->personal;
                } else {
                        $personal = $header->from[0]->mailbox;
                }
                $email = "$personal <{$header->from[0]->mailbox}@{$header->from[0]->host}>";

                $subject = $header->subject;
                $message_text = imap_fetchbody($imap, $i, "1.1");
/* read subject and body text and insert into db */
                addtodb( $subject, $prettydate, $message_text );
/* move email to the "Processed Folder"  */
//                imap_mail_move($imap,$i,'processed');
//                echo "$subject  (moved to processed mailbox.)";
                echo "<br><br>\n\n";
        }

//      send_emails( "FUSSA","ITSK0123456", "This is a test. please ignore"  );
        imap_expunge($imap);
        imap_close($imap);
?>
