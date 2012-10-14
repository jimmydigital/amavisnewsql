#!/usr/bin/php -q
<?php
/**
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Jared Watkins 2003 <jared@watkins.net>
 * @package amavisnewsql
 * $Id: process_bsmtp.php, v
 *
 * This script picks up messages that are quarantined by amavis-new
 * and places them into your database. It should be called on a regular
 * basis from cron.  This script was rewritten in php based on a
 * perl script of the same name written by Peter Collinson.
 *
 * Change the DEFINE to point to the amavisnewsql plugin directory
 * or whereever else you are storing this config file
 *
 * Be sure to include a trailing slash
*/

DEFINE ("QUARANTINEDIR", "/var/virusmails");

// You should not have to change anything below this line
// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
require("../config.php");
require("../amavisnewsql.class.php");
require "Log.php";


$dbfp = new AmavisNewSQL($CONFIG);
$dbfp->connect();

$conf  = array();
$log = &Log::singleton('syslog', LOG_MAIL, 'process_bsmtp.php', $conf);

# LOG_INFO
# LOG_ERR
# LOG_ALERT
#$log->log("Testing", LOG_INFO);

$d = dir(QUARANTINEDIR) or die ("Unable to open QUARANTINEDIR");

$filelist = array();

while ($file = $d->read()) {
   if ($file == "." || $file == ".." || $file == ".notstored") continue;
   array_push($filelist, $file);
}
$d->close();

if (count($filelist) > 0) {
   for ($i=0; $i < count($filelist); $i++) {
        process_file($dbfp, $filelist[$i]);
   }
}

function process_file($dbfp, $file) {
    global $log;

    $store = array();
       $store["stype"] = "";
       $store["sender"] = "";
       $store["subject"] = "";
       $store["body"] = "";
       $store["storetime"] = time();
       $store["score"] = "";

    if (strstr($file, "spam")) $store["stype"] = "spam";
    else if (strstr($file, "virus")) $store["stype"] = "virus";

    $fp = fopen(QUARANTINEDIR."/".$file, "r");

    $rcpt = array();
    $scanstate = "header";
    while (!feof($fp)) {
        $line = fgets($fp, 4096);
        if ($scanstate == "header") {

            if (preg_match("/^MAIL FROM:.*<([^>]*)>.*/", $line, $match)) {
                $store["sender"] = $match[1];
                continue;
            } else if (preg_match("/RCPT TO:<([^>]*).*/", $line, $match)) {
                array_push ($rcpt, $match[1]);
                continue;
            } else if (preg_match("/^DATA/", $line)) {
                $scanstate = "body";
                continue;
            }
        }

        if ($scanstate == "body") {

            if (preg_match("/^\.\n/s", $line)) {
                $scanstate = "ignore";
            } else {
                if (preg_match("/^X-Envelope-To:/i", $line)) {
                    $scanstate = "skiptext";
                } else if (preg_match("/^Subject: (.*)/", $line, $match)) {
                    $store["subject"] = trim($match[1]);
#                } else if (preg_match("/^X-Spam-Status:.*hits=([0-9]{1,2}\.[0-9])/", $line, $match)) {
                } else if (preg_match("/^X-Spam-Status:.*score=([0-9]+\.[0-9]+)/", $line, $match)) {
                    $store["score"] = trim($match[1]);
                }
                $store["body"] .= $line;
            }
        }

        if ($scanstate == "skiptext") {
            if (!preg_match("/^\s/", $line)) {
                $scanstate = "body";
                $store["body"] .= $line;
            }
        }
    } //while

    if (count($rcpt) == 0) {
        $log->log("ERROR: $file - no recipients", LOG_ERR);
        rename(QUARANTINEDIR."/$file", QUARANTINEDIR."/.notstored/$file");
        return;
    }

    foreach ($store as $key => $value) {
         #$store[$key] = $dbfp->db->quoteSmart($value);
         $store[$key] = addslashes($value);
    }

    $nextid  = $dbfp->db->nextID("msg_id");

    // Now lookup recipient names using amavis style lookups
/*
    foreach ($rcpt as $receiver) {
        $userid = $dbfp->uid($receiver);

        if (!$userid) {
            $log->log("ERROR: Recipient $receiver in $file does not exist in the database. Message NOT stored.", LOG_ERR);
            rename(QUARANTINEDIR."/$file", QUARANTINEDIR."/.notstored/$file");
            return;
        }

        if ($userid) {
            $userlist[$nextid] = $userid;
        }
    }
*/

    foreach ($rcpt as $receiver) {
        $userid = $dbfp->uid($receiver);

        if (!$userid) {

            continue;
        
        } else {
        
            $userlist[$nextid] = $userid;
        
        }
    }
    
    
    if (count($userlist) == 0) {
        $log->log("ERROR: No Recipients in $file exist in the database. Message NOT stored.", LOG_ERR);
        rename(QUARANTINEDIR."/$file", QUARANTINEDIR."/.notstored/$file");
        return;
    }



    # Since I'm using the smartquote call instead of the old one.. I no longer need to quote
    # those insert vars.. right? Wrong.. smartquote is not a substuite for addslashes
    #
    
    $q = "insert into $dbfp->msg_table (id, stype, sender, subject, body, storetime, score)
                      values ($nextid, '$store[stype]', '$store[sender]', '$store[subject]',
                              '$store[body]', '$store[storetime]', '$store[score]')";

#                      values ($nextid, $store[stype], $store[sender], $store[subject],
#                              $store[body], $store[storetime], $store[score])";


    if (!$dbfp->sqlWrite($q, __FUNCTION__, __LINE__)) {
        $log->log("$file - Failed to insert message into database: $dbfp->error. Message moved to notstored directory.", LOG_ERR);
        rename(QUARANTINEDIR."/$file", QUARANTINEDIR."/.notstored/$file");
        return;
    }


    foreach ($userlist as $key => $value) {
        $q = "insert into $dbfp->msgowner_table (msgid, rid) values ($key, $value)";
        if (!$dbfp->sqlWrite($q, __FUNCTION__, __LINE__)) {
            $log->log("$file - Failed to update message owner database: $dbfp->error", LOG_ERR);
            return;
        }
    }

    unlink(QUARANTINEDIR."/$file") or $log->log("Cannot delete: $file");
    $log->log("$file stored as $nextid", LOG_INFO);




} // function


?>
