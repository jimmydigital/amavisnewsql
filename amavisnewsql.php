<?php
/**
 * The main target file for most plugin operations. Not much more than a
 * branch point.
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Jared Watkins 2003,2012 https://github.com/jimmydigital/amavisnewsql
 * @package amavisnewsql
 * $Id: amavisnewsql.php, v
*/
error_reporting(E_ALL);

if (file_exists('../../include/init.php'))  
{
    include_once('../../include/init.php');

    // include/validate.php contains the SquirelMail startup code from
    // SquirrelMail 1.4.0 up to SquirrelMail 1.5.1
    //
} elseif (file_exists('../../include/validate.php'))  
{
    define('SM_PATH', '../../');
    include_once(SM_PATH . 'include/validate.php');
}

global $plugins;
if (!in_array('amavisnewsql', $plugins)) exit;

###########################


include_once(SM_PATH.'include/load_prefs.php');
include_once(SM_PATH.'functions/i18n.php');

include_once(SM_PATH.'plugins/amavisnewsql/config.php');
include_once(SM_PATH.'plugins/amavisnewsql/functions.php');
include_once(SM_PATH.'plugins/amavisnewsql/amavisnewsql.class.php');


/* Set up locale, for the error messages. */
$prev = bindtextdomain ('amavisnewsql', 'SM_PATH' . 'plugins/amavisnewsql/locale');
sq_change_text_domain('amavisnewsql');
#textdomain ('amavisnewsql');



sqgetGlobalVar('username',  $username, SQ_SESSION);

// Depending on how people login.. some virtual domain setup pass in wrongly formatted usernames
// like user@domain.com@domain.com  This checks for it.. and removes the second.
    
$pos = strpos($username, "@");
$pos2 = strrpos($username, "@");
    
if ($pos != $pos2) {
    if ($pos2 !== FALSE) $username = substr($username, 0, $pos2);
}


if(isset($_REQUEST)) {
   sqgetGlobalVar('action', $action, 'SQ_REQUEST');
   sqgetGlobalVar('row', $row, 'SQ_REQUEST');
}

#print "User: $username, Action $action, $row<br>\n";

//Connect to the DB
$dbfp = new AmavisNewSQL($CONFIG);
if (!$dbfp->connect()) {
   amavisnewsql_ErrorOut($dbfp->error);
   exit;
}

/*  Moved to setup.php
#include('SM_PATH'.'plugins/amavisnewsql/acbjcustom.php');
#amavisnewsql_UserExists();


   $err = $dbfp->UserExists($username, __FILE__, __LINE__);

   if (is_bool($err) && $err == FALSE) {
       amavisnewsql_ErrorOut($dbfp->error, TRUE);
   } else if ($err == null) {
       if(!$dbfp->CreateUser($username, $data_dir)) {
           amavisnewsql_ErrorOut($dbfp->error, TRUE);
       }
   }

*/


   switch ($action) {

      case "whitelist_abook":
            include "../../functions/addressbook.php";
            $abook = addressbook_init(true, true);
            $alist = $abook->list_addr();
            usort($alist,'alistcmp');

            if (!$dbfp->Whitelist_Addressbook($username, $alist, __FILE__, __LINE__)) {
                 amavisnewsql_ErrorOut($dbfp->error, TRUE);
            }
            amavisnewsql_DisplayOptions(NULL, NULL, __FILE__, __LINE__);

      break;
      case "set_quarantine_settings":
            sqgetGlobalVar('frequency', $freq, 'SQ_REQUEST');
            sqgetGlobalVar('retention', $retention, 'SQ_REQUEST');
            sqgetGlobalVar('quarantineonoff', $qonoff, 'SQ_REQUEST');
            if (!$dbfp->SetQuarantineSettings($username, $freq, $retention, $qonoff, __FILE__, __LINE__)) {
                 amavisnewsql_ErrorOut($dbfp->error, TRUE);
            }
            amavisnewsql_DisplayOptions(NULL, NULL, __FILE__, __LINE__);
      break;


      case "display_global_list":
            amavisnewsql_DisplayGlobalList(NULL, NULL, __FILE__, __LINE__);
      break;


      case "prep_edit_wb_address":
            amavisnewsql_DisplayOptions($action, $row, __FILE__, __LINE__);
      break;


      case "add_edit_wb_address":
            sqgetGlobalVar('WorB', $wb, 'SQ_REQUEST');
            sqgetGlobalVar('priority', $priority, 'SQ_REQUEST');
            sqgetGlobalVar('address', $address, 'SQ_REQUEST');

            if(!$dbfp->AddEditWBList($username, $wb, $priority, $address, $row, __FILE__, __LINE__)) {
               amavisnewsql_ErrorOut($dbfp->error, TRUE);
            }

            amavisnewsql_DisplayOptions(NULL, NULL, __FILE__, __LINE__);
      break;


      case "delete_wb_address":
            sqgetGlobalVar('row', $delrow, 'SQ_REQUEST');
            if(!$dbfp->DeleteWBAddress($username, $delrow, __FILE__, __LINE__)) {
                amavisnewsql_ErrorOut($dbfp->error, TRUE);
            }
            amavisnewsql_DisplayOptions(NULL, NULL, __FILE__, __LINE__);
      break;


      case "set_policy":
            sqgetGlobalVar('id', $target_id, 'SQ_REQUEST');
            if(!$dbfp->SetPolicy($username, $target_id, __FILE__, __LINE__)) {
                amavisnewsql_ErrorOut($dbfp->error, TRUE);
            }
            amavisnewsql_DisplayOptions(NULL, NULL, __FILE__, __LINE__);
      break;


      case "set_custom_policy":
            sqgetGlobalVar('tag2_level', $tag2_level, 'SQ_REQUEST');
            sqgetGlobalVar('kill_level', $kill_level, 'SQ_REQUEST');
            if(!$dbfp->SetCustomPolicy($username, $tag2_level, $kill_level, __FILE__, __LINE__)) {
                amavisnewsql_ErrorOut($dbfp->error, TRUE);
            }
            amavisnewsql_DisplayOptions(NULL, NULL, __FILE__, __LINE__);
      break;

      default:
            amavisnewsql_DisplayOptions(NULL, NULL, __FILE__, __LINE__);

   }

sq_change_text_domain('squirrelmail');
