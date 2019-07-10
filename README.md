# readmail
PHP Script to read mail from an imap account and forward to techs at locations based on contents of mail
The script will also open a database and update a ticket status to OPEN or CLOSED depending on what is written in the email it reads.

You must edit the file mysqlpassword.php to insert your servername, db user and db password.
You must add the imap server, email and password that you would like the script to check.
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
