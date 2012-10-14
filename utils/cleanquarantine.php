#!/usr/bin/php -q
<?php
/**
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Jared Watkins 2003 <jared@watkins.net>
 * @package amavisnewsql
 * $Id: cleanquarantine.php, v
 *
 * This script should be called once a day from cron.  It will scan through
 * your quarantine database and remove anything that is old enough to be
 * aged out. These ages are set by each user.
 *
 * It will also (as of 0.8) remove any messages in quarantine that do not belong to anyone. 
 *
 * TODO: Make it do something more useful than just delete known spam
 *       maybe feed them into a shared bayes database or DCC?
 *
 * Change this to point to the amavisnewsql plugin directory
 * or whereever else you are storing this config file
 *
 * Be sure to include a trailing slash
 *
*/

#DEFINE ("BASEINCLUDE", "/var/www/squirrel/plugins/amavisnewsql/");




// You should not have to change anything below this line
// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
require('../config.php');
require('../amavisnewsql.class.php');
require('Log.php');

$conf  = array();
$log = &Log::singleton('syslog', LOG_MAIL, 'cleanquarantine.php', $conf);


$dbfp = new AmavisNewSQL($CONFIG);
$dbfp->connect();

   $now = time();
   $secondsperday = 86400;

   $q = "select $dbfp->msg_table.id, $dbfp->msg_table.storetime, $dbfp->users_table.retention, $dbfp->users_table.username
         from $dbfp->msg_table, $dbfp->users_table, $dbfp->msgowner_table
         where ('$now' - $dbfp->msg_table.storetime) > ($dbfp->users_table.retention * $secondsperday)
         and $dbfp->msgowner_table.rid = $dbfp->users_table.id
         and $dbfp->msgowner_table.msgid = $dbfp->msg_table.id";


   if (!$res = $dbfp->sqlRead($q, __FILE__, __LINE__)) {
       die ("cleanQuarantine Searching for Old Messages Error: $dberr");
   }
   
   $rowcount = $res->numRows();

   for($i=0; $i < $res->numRows(); $i++) {
       $row = $res->fetchRow(DB_FETCHMODE_ASSOC);

       $q1 = "delete from $dbfp->msg_table where id = '$row[id]'";
       $res1 = $dbfp->sqlWrite($q1, __FILE__, __LINE__);

       $q2 = "delete from $dbfp->msgowner_table where msgid = '$row[id]'";
       $res2 = $dbfp->sqlWrite($q2, __FILE__, __LINE__);

       if ($res1 == FALSE || $res2 == FALSE) {
           $dbfp->sqlWrite("rollback", __FILE__, __LINE__);
           $dbfp->disconnect();
           die("cleanQuarantine Deleting Old Messages Error: $dberr");
       }

         else $dbfp->sqlWrite("commit", __FILE__, __LINE__);
   }
   
   $log->log("Cleaned $rowcount messages from the amavisnewsql database", LOG_INFO);

/*  This next section is new as of 0.8  It will clean out your messages table
 *  of all email that is not owned by someone in the users table. 
 *
 *  This sort of situation shouldn't happen if everything is configured correctly
 *  but I know it does for some of you... 
 */
    

    
    $q = "select id from $dbfp->users_table";
    $users = $dbfp->db->getall($q);
    if (!$users) die ("Error getting user list: $dbfp->error");
    
    $owned=array();
    
    foreach ($users as $u) {
             $result = null;

             $q = "select $dbfp->msg_table.id
                   from $dbfp->users_table, $dbfp->msg_table, $dbfp->msgowner_table
                   where $dbfp->msgowner_table.rid = $dbfp->users_table.id
                   and $dbfp->users_table.id = $u[0]
                   and $dbfp->msg_table.id = $dbfp->msgowner_table.msgid
                   and $dbfp->users_table.id = $dbfp->msgowner_table.rid
                   order by $dbfp->msg_table.score, $dbfp->msg_table.storetime desc
                  ";

             $result = $dbfp->db->getAll($q);
             if (!$result && !is_array($result)) die ("Error getting messages for user number $u[0]");
             foreach ($result as $msgid) {
                      $owned[] = $msgid[0];
             
             }
             
    
    }
    
    
    $q = "select id from $dbfp->msg_table";
    $result = $dbfp->db->getAll($q);
    foreach ($result as $msgid) {
             $allmsg[] = $msgid[0];
    }
    
    $unowned = array_diff($allmsg, $owned);

    #print_r($owned);
    #print_r($allmsg);
    #echo "Differences...\n";
    #print_r($unowned);
    
    if(!$dbfp->sqlWrite("begin", __FILE__, __LINE__)) die("Error beginning transaction: $dbfp->error");
    
    $rowcount  = count($unowned);
    
    foreach ($unowned as $msgid) {
    
             $q = "delete from $dbfp->msg_table where id = $msgid";
             #echo "$q\n";
             if (!$dbfp->sqlwrite($q, __FILE__, __LINE__)) {
                 $err = $dbfp->error;
                 $dbfp->sqlwrite("rollback");
                 die ("There was an error removing message $msgid. The error is :$err: All messages removed for this run have been restored.");
             
             }
    }
    
    if (!$dbfp->sqlWrite("commit", __FILE__, __LINE__)) die("Error committing changes after unowned message removal: $dbfp->error");

    $log->log("Cleaned $rowcount orphaned messages from the msg table.", LOG_INFO);



    /* Now do something similar for the msgowner table.
     *
     */
    
    $q = "select id from $dbfp->msg_table";
    $result = $dbfp->db->getAll($q);
    foreach ($result as $msgid) {
             $allmsg[] = $msgid[0];
    }
    
    $q = "select msgid from $dbfp->msgowner_table";
    $result = $dbfp->db->getAll($q);
    foreach ($result as $msgid) {
             $ownedmsg[] = $msgid[0];
    }
    
    #echo "All messages\n";
    #print_r($allmsg);
    
    #echo "Owned messages\n";
    #print_r($ownedmsg);
    
    $unowned = array_diff($ownedmsg, $allmsg);
    #echo "Unowned entries\n";
    #print_r($unowned);
     

    if(!$dbfp->sqlWrite("begin", __FILE__, __LINE__)) die("Error beginning transaction: $dbfp->error");

    $rowcount = count($unowned);

    foreach ($unowned as $msgid) {
    
             $q = "delete from $dbfp->msgowner_table where msgid = $msgid";
             #echo "$q\n";
             if (!$dbfp->sqlwrite($q, __FILE__, __LINE__)) {
                 $err = $dbfp->error;
                 $dbfp->sqlwrite("rollback");
                 die ("There was an error removing entry $msgid from the msgowner table. The error is :$err: All entries removed for this run have been restored.");
             
             }
    }
    
    if (!$dbfp->sqlWrite("commit", __FILE__, __LINE__)) die("Error committing changes after unowned message removal: $dbfp->error");
    
    $log->log("Cleaned $rowcount orphaned entries from the msgowner table.", LOG_INFO);

?>
