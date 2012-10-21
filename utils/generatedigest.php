#!/usr/bin/php -q
<?php
/**
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Jared Watkins 2003,2012 https://github.com/jimmydigital/amavisnewsql
 * @package amavisnewsql
 * $Id: generatedigest.php, v
 *
 * This script should be called once a day and will send out a sort of
 * digest mailing to everyone who wants one of what is in their personal
 * spam quarantine.
 *
 * Change the DEFINE to point to the amavisnewsql plugin directory
 * or whereever else you are storing this config file
 *
 * Be sure to include a trailing slash
 *
*/


#DEFINE ("BASEINCLUDE", "/var/www//squirrel/plugins/amavisnewsql/");



// You should not have to change anything below this line
// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

require('config.php');
require('amavisnewsql.class.php');
require('htmlMimeMail5/htmlMimeMail5.php');

$DEBUG = false;

$dbfp = new AmavisNewSQL($CONFIG);
$dbfp->connect(__FILE__, __LINE__);


$users = getUsers();

for ($i=0; $i < count($users); $i++) {
   #$messages = listQuarantineMessages($users[$i]["username"]);
   
   $messages = $dbfp->listQuarantineMessages($users[$i]["username"], __FILE__, __LINE__);
   
   if (! $messages && !is_array($messages)) die("Error getting messages for ".$users[$i]["username"] ." :$dbfp->error\n");
   
   $mail = new htmlMimeMail5();
   $bodyhtml = "
             <html><body>
             <p>The following are the top 100 lowest scoring messages being held in your Spam Quarantine</p>
             <p>To release a message for delivery.. login to the webmail system at
                <a href=\"$CONFIG[webmail_url]\">$CONFIG[webmail_url]</a> and
                access your 'Spam Quarantine' from the top menu. Select the message(s)
                you want to release and select 'Release' from the drop down menu.</p>

             <table width=\"100%\" >
             <tr><td><b>From</b></td><td><b>Date</b></td><td><b>Subject</b></td><td><b>Score</b></td></tr>
             ";

$bodytext = "
The following are the top 100 lowest scoring messages being held in your Spam Quarantine\n\n

To release a message for delivery.. login to the webmail system at
$CONFIG[webmail_url] and access your 'Spam Quarantine' from the
top menu. Select the message(s) you want to release and
select 'Release' from the drop down menu.\n\n
";

   if(count($messages) == 0) continue;
   for($j=0; $j < count($messages); $j++) {
      $bodyhtml .= "
                    <tr><td>".$messages[$j]["sender"].
                    "</td><td nowrap>".$messages[$j]["storetime"].
                    "</td><td>".$messages[$j]["subject"].
                    "</td><td>".$messages[$j]["score"]."</td></tr>\n";

      $bodytext .= "From:    ".$messages[$j]["sender"]."\n";
      $bodytext .= "Subject: ".$messages[$j]["subject"]."\n";
      $bodytext .= "Date:    ".$messages[$j]["storetime"]."\n";
      $bodytext .= "Score:   ".$messages[$j]["score"]."\n\n";
   }

   $bodyhtml .= "</table></body></html>\n";

   $mail->setHtml($bodyhtml, $bodytext);
   $mail->setFrom($CONFIG["digest_from"]);
   $mail->setSubject($CONFIG["digest_subject"]);
   $mail->setSMTPParams($CONFIG["smtp_host"],$CONFIG["smtp_port"]);
   $result = $mail->send(array($users[$i]["email"]), 'smtp');

   if($DEBUG) echo $result ? "Mail Sent\n" : "Failed to send mail\n";
}


// -------------------------------------------------------------------------------
// -------------------------------------------------------------------------------


/*
function listQuarantineMessages($username) {
   global $CONFIG, $dbfp;
   $C = $CONFIG;

   #to_char($dbfp->msg_table.storetime, 'Dy MM/DD HH24:MI') as storetime,
   #where $dbfp->msgowner_table.rid = (select id from $dbfp->users_table where username = '$username')

   $q = "select $dbfp->msg_table.id, $dbfp->msg_table.sender, $dbfp->msg_table.subject,
                $dbfp->msg_table.storetime as storetime,
                $dbfp->msg_table.score
         from $dbfp->msg_table, $dbfp->users_table, $dbfp->msgowner_table
         where $dbfp->msgowner_table.rid = $dbfp->users_table.id
         and $dbfp->users_table.username = '$username'
         and $dbfp->msg_table.id = $dbfp->msgowner_table.msgid
         order by $dbfp->msg_table.score
        ";

   #$res = $dbfp->db->query($q);

   if(!$res = $dbfp->sqlRead($q, __FILE__, __LINE__)) die($dbfp->error);

   $results = array();

   for($i=0; $i < $res->numRows(); $i++) {

      $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
      $results[$i]["sender"] = $row["sender"];
      $results[$i]["subject"] = substr($row["subject"], 0, 40);
      $results[$i]["id"] = $row["id"];
      $results[$i]["storetime"] = date("D m/d G:i" ,$row["storetime"]);
      $results[$i]["score"] = $row["score"];

   }

   return($results);

}

*/
// -------------------------------------------------------------------------------


function getUsers() {
   global $dbfp, $CONFIG;

   $day_of_month = date("d");
   $day_of_week = date("w");
   $weekdays = array(1,2,3,4,5);
   $users = array();


   if($day_of_month == 01) {   // First of the month.. do the Monthly people
      $q = "select username, email from $dbfp->users_table where digest = 'M'";

      if(!$res = $dbfp->sqlRead($q, __FILE__, __LINE__)) die ($dbfp->error);

      if($res->numRows() != 0) {
         for($i=0; $i < $res->numRows(); $i++) {
             $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
             array_push($users, array("username" => "$row[username]", "email" => "$row[email]"));
         }
      }
   } if($day_of_week == 5) {  // It's friday.. Take care of the weekly people
        $q = "select username, email from $dbfp->users_table where digest = 'W'";
        if(!$res = $dbfp->sqlRead($q, __FILE__, __LINE__)) die ($dbfp->error);

        if($res->numRows() != 0) {
          for($i=0; $i < $res->numRows(); $i++) {
              $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
              array_push($users, array("username" => "$row[username]", "email" => "$row[email]"));
          }
        }
   } if(in_array($day_of_week, $weekdays)) {  // It's a week day.. do the WD people
        $q = "select username, email from $dbfp->users_table where digest = 'WD'";
        if(!$res = $dbfp->sqlRead($q, __FILE__, __LINE__)) die ($dbfp->error);

        if($res->numRows() != 0) {
           for($i=0; $i < $res->numRows(); $i++) {
               $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
               array_push($users, array("username" => "$row[username]", "email" => "$row[email]"));
           }
        }
   }
        // Now for the daily people
        $q = "select username, email from $dbfp->users_table where digest = 'D'";
        if(!$res = $dbfp->sqlRead($q, __FILE__, __LINE__)) die ($dbfp->error);

        if($res->numRows() != 0) {
           for($i=0; $i < $res->numRows(); $i++) {
               $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
               array_push($users, array("username" => "$row[username]", "email" => "$row[email]"));
           }
        }

   return $users;

}


// -------------------------------------------------------------------------------


?>
