# readmail
Requires: yum install php73-php-imap 

PHP Script to read mail from an imap account and forward to techs at locations based on contents of mail
The script will also open a database and update a ticket status to OPEN or CLOSED depending on what is written in the email it reads.

You must edit the file /var/www/mysqlpassword.php to insert your servername, db user and db password.
You must add the imap server, email and password that you would like the script to check.
This file should be stored in /var/www/ or another location that is not accessible from the web.  Don't put it inside the /var/www/html folder!   

E.G.

/*  enter the ticket database credentials  */
$password="SuperSecureDBPasswordGoesHere'";
$username="MYSQL user";
$servername="localhost or mysql db server" ;
$dbname="wordpress or name of db where tickets are stored" ;

$imap_server="{example.com:143}";
$imap_user="call.log@example.com";
$imap_password="EmailPassword";
$tech_emails = "dan.vogel@example.com, support@example.com";

EXAMPLE RUNNING OF THE SCRIPT:  
# send a test message to the email account.  The script should read it and move it from INBOX to 'processed' folder
# This example, the script sees a single email with the subject:Test message for READMAIL
# Notice the output is HTML.  You can test this from the browser if the readmail.php file is stored in the /html folder.

php /var/www/html/esp/readmail.php
checking mail on {datostech.com:143} for account: call.log@datostech.com

Messages:1

Test message for READMAIL  (moved to processed mailbox.)<br><br>

