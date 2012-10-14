<?php
/**
 * AmavisNewSQL - AmavisNew+SQL+SpamAssassin+Quarantine plugin for SquirrelMail
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Jared Watkins 2003 <jared@watkins.net>
 * @package amavisnewsql
 * $Id: setup.php, v
*/

/**
 * Required for SM versioning
 */
function amavisnewsql_version()
{
  return '0.9.0';
}

//error_reporting(E_ALL);


#if (defined('SM_PATH')) echo "Path :".SM_PATH;
#if (!defined('SM_PATH')) { define('SM_PATH' , '../../'); }

#include_once('SM_PATH'.'functions/page_header.php');
#include_once('SM_PATH'.'functions/i18n.php');




// This code block was moved from amavisnewsql.php
// Here we check to see if they exist in the amavis database.. thus 'turning on'
// their account to take advantage of our quarantine system and more
// agressive policy settings.
// Before.. they would only get added to the system when they first looked
// at the options page for the plugin.. which might take a while if they are
// not the curious type.

#include('SM_PATH'.'plugins/amavisnewsql/acbjcustom.php');
#amavisnewsql_UserExists();



// Check to see if they are in the DB.. Add them if not
// we only want to do this once per session

function amavisnew_right_main_bottom () {

    global $data_dir;
    if (!sqsession_is_registered('inamavis')) {
    include_once('SM_PATH' . 'plugins/amavisnewsql/config.php');
    #require('config.php');
    sqgetGlobalVar('username',  $username, 'SQ_SESSION');
    
    // Depending on how people login.. some virtual domain setup pass in wrongly formatted usernames
    // like user@domain.com@domain.com  This checks for it.. and removes the second.
    
    $pos = strpos($username, "@");
    $pos2 = strrpos($username, "@");
    
    if ($pos != $pos2) {
      if ($pos2 !== FALSE) $username = substr($username, 0, $pos2);
    }


    $email  = getPref($data_dir, $username, 'email_address');

    if ($email == null) {

        if (strpos($username, "@")) {
            $email = $username;
        } else {
            $email = "$username@".$CONFIG['default_domain'];
        }

        setpref($data_dir, $username, 'email_address', $email);
    }

        include_once('SM_PATH'.'plugins/amavisnewsql/functions.php');
        include_once('SM_PATH'.'plugins/amavisnewsql/amavisnewsql.class.php');
        include_once('SM_PATH'.'include/validate.php');
        include_once('SM_PATH'.'include/load_prefs.php');
#        global $data_dir;


        // Connect to the DB
        $dbfp = new AmavisNewSQL($CONFIG);
        if ( ! $dbfp->connect()) {
            amavisnewsql_ErrorOut($dbfp->error);
            exit;
        }


        $err = $dbfp->UserExists($username, __FILE__, __LINE__);

        if (is_bool($err) && $err == FALSE) {

            amavisnewsql_ErrorOut($dbfp->error, TRUE);

        } else if ($err == null) {
            print_r($data_dir);

            if (!$dbfp->CreateUser($username, $data_dir)) {

                amavisnewsql_ErrorOut($dbfp->error, TRUE);

            } else { // success

               $inamavis = 't';
               sqsession_register($inamavis, 'inamavis');
            }

        } else if ($err == TRUE) {  // they are already in there

               $inamavis = 't';
               sqsession_register($inamavis, 'inamavis');
        }
    }
}




function squirrelmail_plugin_init_amavisnewsql () {
  global $squirrelmail_plugin_hooks;

  $squirrelmail_plugin_hooks['optpage_register_block']['amavisnewsql'] = 'amavisnewsql_optpage_register_block';

  $squirrelmail_plugin_hooks['read_body_header_right']['amavisnewsql'] = 'amavisnewsql_address_add';

  $squirrelmail_plugin_hooks['right_main_bottom']['amavisnewsql'] = 'amavisnew_right_main_bottom';

  $squirrelmail_plugin_hooks['menuline']['amavisnewsql'] = 'amavisnewsql_spam_quarantine';
}


function amavisnewsql_address_add() {  // Borrowed from address_add plugin
    global $message;
    if (!$message || !isset($message)) return;

    $header = $message->rfc822_header;
    $decodedfrom = $header->getAddr_s('from');

    $IP_RegExp_Match = '\\[?[0-9]{1,3}(\\.[0-9]{1,3}){3}\\]?';
    $Host_RegExp_Match = '(' . $IP_RegExp_Match . '|[0-9a-z]([-.]?[0-9a-z])*\\.[a-z][a-z]+)';
    $Email_RegExp_Match = '[0-9a-z]([-_.+|]?[_0-9a-z|])*(%' . $Host_RegExp_Match . ')?@' . $Host_RegExp_Match;
    $regs = array();
    while (eregi($Email_RegExp_Match, $decodedfrom, $regs)) {
       $decodedfrom = substr(strstr($decodedfrom, $regs[0]), strlen($regs[0]));
       $fromaddress = urlencode($regs[0]);
    }

    echo " | ";
    displayInternalLink ("plugins/amavisnewsql/amavisnewsql.php?action=add_edit_wb_address&WorB=W&priority=7&address=$fromaddress", _("Whitelist Sender"), 'right');


}


function amavisnewsql_optpage_register_block () {
    global $optpage_blocks;
    sq_change_text_domain('amavisnewsql');
    $optpage_blocks[] =
      array (
             'name' => _("Spam Protection"),
             'url'  => sqm_baseuri() .'plugins/amavisnewsql/amavisnewsql.php',
             'desc' => _("Define your own white/black lists and customize your spam scoring rules."),
             'js'   => FALSE
            );
    sq_change_text_domain('squirrelmail');
}


function amavisnewsql_spam_quarantine ()
{
    error_reporting(E_ALL);
    require(SM_PATH . '/plugins/amavisnewsql/config.php');

    if ($CONFIG["use_quarantine"])
    {
        displayInternalLink ('plugins/amavisnewsql/quarantine.php', _("[Quarantine] "), 'right');
    }
}

?>
