<?php

if (!defined('SM_PATH')) { define('SM_PATH' , '../../'); }


include_once('SM_PATH'.'include/validate.php');
include_once('SM_PATH'.'functions/page_header.php');
include_once('SM_PATH'.'include/load_prefs.php');
include_once('SM_PATH'.'functions/i18n.php');
include_once('Net/SMTP.php');

require('config.php');
include_once('functions.php');
include_once('amavisnewsql.class.php');



/* Set up locale, for the error messages. */
$prev = bindtextdomain ('amavisnewsql', 'SM_PATH' . 'plugins/amavisnewsql/locale');
textdomain ('amavisnewsql');


//error_reporting(E_ALL);


if(!$CONFIG["use_quarantine"]) die();


// Connect to the DB
$dbfp = new AmavisNewSQL($CONFIG);
$dbfp->connect();

global $username;

if(isset($_REQUEST)) {
   sqgetGlobalVar('action', $action, 'SQ_REQUEST');
}

#include('SM_PATH'.'plugins/amavisnewsql/acbjcustom.php');
#amavisnewsql_UserExists();


   $err = $dbfp->UserExists($username);
   if (is_bool($err) && $err == FALSE) {
       amavisnewsql_ErrorOut($dbfp->error);
   } else if ($err == null) {
       if(!$dbfp->CreateUser($username, $data_dir)) {
           amavisnewsql_ErrorOut($dbfp->error);
       }
   }



if(isset($_REQUEST)) {

   switch ($action) {
      case "DELETE":
            sqgetGlobalVar('msg', $msg, 'SQ_REQUEST');

            if (count($msg) == 0) amavisnewsql_ErrorOut("You Must Select At Least One Message To Delete", TRUE);

            if (!$dbfp->DeleteQuarantineMessages($msg, $username)) {
                    amavisnewsql_ErrorOut($dbfp->error);

            }

            DisplayHeader();
            DisplayQuarantineMessages();
      break;

      case "RELEASE":
            sqgetGlobalVar('msg', $msg, 'SQ_REQUEST');

            if (count($msg) == 0) amavisnewsql_ErrorOut("You Must Select At Least One Message To Release", TRUE);

            if (!$dbfp->ReleaseQuarantineMessages($msg, $username)) {
                    amavisnewsql_ErrorOut($dbfp->error);
            }

            DisplayHeader();
            DisplayQuarantineMessages();
      break;

      case "RELEASEADD":
            sqgetGlobalVar('msg', $msg, 'SQ_REQUEST');

            if (count($msg) == 0) amavisnewsql_ErrorOut("You Must Select At Least One Message To Release", TRUE);

            if (!$dbfp->WListMessages($msg, $username)) {
                amavisnewsql_ErrorOut($dbfp->error);
            }

            if (!$dbfp->ReleaseQuarantineMessages($msg, $username)) {
                    amavisnewsql_ErrorOut($dbfp->error);
            }

            DisplayHeader();
            DisplayQuarantineMessages();
      break;



      default:
            DisplayHeader();
            DisplayQuarantineMessages();

   }
}


function iseven($var) {
   return ($var % 2 == 0);
}


function DisplayHeader() {
   global $CONFIG, $username, $color, $javascript_on;

   $prev = bindtextdomain ('amavisnewsql', 'SM_PATH' . 'plugins/amavisnewsql/locale');
   textdomain ('amavisnewsql');
   displayPageHeader($color, 'Message Quarantine');
   $prev = bindtextdomain ('squirrelmail', 'SM_PATH' . 'plugins/squirrelmail/locale');
   textdomain ('squirrelmail');
   $plugindir = substr($_SERVER["PHP_SELF"], 0, strrpos($_SERVER["PHP_SELF"], '/'));

           if ($javascript_on) {
               echo "<script language=\"JavaScript\" type=\"text/javascript\">
                        <!--
                        function CheckAllINBOX() {
                           for (var i = 0; i <document.FormMessages.elements.length; i++) {
                             if(document.FormMessages.elements[i].type == 'checkbox') {
                                document.FormMessages.elements[i].checked = !(document.FormMessages.elements[i].checked);
                             }
                           }
                        }
                        -->
                        </script>
               ";
           }


           echo "
                <body>

                 <center>
                 <form method=\"post\" name=\"FormMessages\" action=\"$_SERVER[PHP_SELF]\">
                 <table bgcolor=\"$color[9]\" border=\"0\" width=\"100%\" cellpadding=\"1\" cellspacing=\"0\">
                 <tr><td><table bgcolor=\"$color[0]\" border=\"0\" width=\"100%\" cellpadding=\"1\" cellspacing=\"0\">
                 <tr><td height=20></td></tr>
                 </tr>
                 <td align=\"left\" valign=\"middle\" nowrap><tt>
                     <select name=\"action\">
                        <option value=\"\">" . _("Select Action") . "</option>
                        <option value=\"DELETE\">" . _("DELETE") . "</option>
                        <option value=\"RELEASE\">" . _("Release") . "</option>
                        <option value=\"RELEASEADD\">" . _("Release + Add To Whitelist") . "</option>
                     </select></tt>
                     <input type=\"submit\" value=\"" . _("Submit") . "\"><td></td>
                 </td>
                 <td>
                      " . _("Messages hilighted in RED likely contain viruses!") . "
                 </td>
                 </tr>
                     <td colspan=2>
           ";


                     if($javascript_on)
                       echo "<a href=\"javascript:void(0)\" onclick=\"CheckAllINBOX();\">Toggle All</a>
                             &nbsp;&nbsp;&nbsp;
                       ";


           echo "

                       <a href=\"$plugindir/amavisnewsql.php\">" . _("Spam Assassin Settings") . "</a>
                     </td>
                 </tr></table>
                 <tr><td colspan=\"5\" HEIGHT=\"5\" BGCOLOR=\"$color[4]\"></td></tr>
                 </table>
           ";

}


// --------------------------------------------------------------------

function DisplayQuarantineMessages() {
   global $CONFIG, $dbfp, $username, $color, $javascript_on, $data_dir;
   sqgetGlobalVar('sort', $sort, 'SQ_REQUEST');
   sqgetGlobalVar('field', $field, 'SQ_REQUEST');
   sqgetGlobalVar('offset', $offset, 'SQ_REQUEST');
   $plugindir = substr($_SERVER["PHP_SELF"], 0, strrpos($_SERVER["PHP_SELF"], '/'));

   if ($offset == null) $offset = 0;

   $show_num  = getPref($data_dir, $username, 'show_num');
   if (!is_numeric($show_num)) $show_num = 15;

   $q = "select $dbfp->msg_table.score, $dbfp->msg_table.subject, $dbfp->msg_table.sender,
                $dbfp->msg_table.storetime,
                $dbfp->msg_table.id, $dbfp->msg_table.stype
         from $dbfp->users_table, $dbfp->msg_table, $dbfp->msgowner_table
         where $dbfp->msgowner_table.rid = $dbfp->users_table.id
         and $dbfp->users_table.username = '$username'
         and $dbfp->msg_table.id = $dbfp->msgowner_table.msgid
         and $dbfp->users_table.id = $dbfp->msgowner_table.rid
         order by $dbfp->msg_table.score, $dbfp->msg_table.storetime desc
        ";


   
   if (!sqsession_is_registered('quarantineresult') || $offset == 0) {

       $dbfp->db->setFetchMode(DB_FETCHMODE_ASSOC);
       if (!$res = $dbfp->db->getAll($q)) return FALSE;
       sqsession_register($res, 'quarantineresult');
       
    } else {
          
       sqgetGlobalVar('quarantineresult',  $res, SQ_SESSION);
    
    }

   #if (!$res = $dbfp->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;
   #$num = $res->numRows();
   
   $num = count($res);
   
   $prev = bindtextdomain ('amavisnewsql', 'SM_PATH' . 'plugins/amavisnewsql/locale');
   textdomain ('amavisnewsql');

   if($num == 0) echo "<font color=\"$color[2]\">" . _("No Messages in Quarantine") . "</font><br>";
   else {

           echo "
                 <table bgcolor=\"$color[9]\" cellpadding=\"1\" cellspacing=\"0\" border=\"0\" width=\"100%\">
                 <tr><td>
                 <table border=\"0\" cellpadding=\"1\" cellspacing=\"0\" width=\"100%\">
                 <tr bgcolor=\"$color[5]\" align=center>
                 <td colspan=2><b>" . _("From") . "</b></td>
                 <td align=center><b>" . _("Date") . "</b></td>
                 <td><b>" . _("Subject") . "</b></td>
                 <td><b>" . _("Score") . "</b></td></tr>
                 <tr><td colspan=\"5\" HEIGHT=\"5\" BGCOLOR=\"$color[4]\"></td></tr>";

           $currow=1;
           for($i=$offset; (($i < $num) && ($currow <= $show_num)); $i++) {
              #$row = $res->fetchRow(DB_FETCHMODE_ASSOC);
              $currow++;

              if(trim($res[$i]["stype"]) == "virus") {
                 $rowcolor = "FF0000";
                 $textcolor = "ffffff";
              } else {
                    if(iseven($i)) {
                     $rowcolor = $color[4];
                     $textcolor = $color[8];
                    }  else {
                             $rowcolor = $color[12];
                             $textcolor = $color[8];
                      }

              }

              echo "

                    <tr bgcolor=\"$rowcolor\">
                       <td width=1%><input type=\"checkbox\" name=\"msg[]\" value=\"".$res[$i]["id"]."\"></td>
                       <td><font color=\"$textcolor\">".$res[$i]["sender"]."</font></td>
                       <td nowrap width=\"140\" align=\"left\"><font color=\"$textcolor\">".date("D m/d G:i" ,$res[$i]["storetime"])."</font></td>
                       <td><font color=\"$textcolor\">".substr($res[$i]["subject"],0,40)."</font></td>
                       <td width=\"50\" align=\"right\"><font color=\"$textcolor\">".$res[$i]["score"]."</font></td>
                    </tr>\n
                    <tr><td colspan=\"5\" bgcolor=\"$color[0]\" height=\"1\"></td></tr>
                    ";

           }

           if ($offset == 0) {
               if ($num <= $show_num) {
                   $next_link = _("Next");
                   $previous_link = _("Previous");

               } else {
               
                   $next_link = "<a href=\"$plugindir/quarantine.php?offset=".($offset+$show_num) . "\">"._("Next") . "</a>";
                   $previous_link = _("Previous");
               
               }
           } else {
                
                $previous_link = "<a href=\"$plugindir/quarantine.php?offset=".($offset-$show_num)."\">" . _("Previous"). "</a>";
                
                if ($offset + $show_num < $num) {
                
                    $next_link = "<a href=\"$plugindir/quarantine.php?offset=".($offset+$show_num)."\">" . _("Next") . "</a>";
                
                } else {
                    
                    $next_link = _("Next");
                
                }
           
           
           }

           echo "</table></table><table border=\"0\" cellpadding=\"5\" cellspacing=\"0\" width=\"100%\">\n";
           echo "<tr bgcolor=\"$color[4]\" height=\"8\"><td colspan=\"2\"></td></tr>\n";
           echo "<tr height=\"30\" bgcolor=\"$color[0]\"><td align=\"left\">$previous_link</td><td align=\"right\">$next_link</td></tr>


                ";

           echo "</table></table></form>";

   }

   $dbfp->disconnect();
}


function ShowSortButton($sort, $field) {
    global $PHP_SELF;
    /* Figure out which image we want to use. */
    if ($sort != 'asc' && $sort != 'dsc') {
        $img = 'sort_none.png';
        $which = 'asc';
    } elseif ($sort == 'asc') {
        $img = 'up_pointer.png';
        $which = 'dsc';
    } else {
        $img = 'down_pointer.png';
        $which = 'none';
    }

    if (preg_match('/^(.+)\?.+$/',$PHP_SELF,$regs)) {
        $source_url = $regs[1];
    } else {
        $source_url = $PHP_SELF;
    }


    /* Now that we have everything figured out, show the actual button. */
    echo ' <a href="' . $source_url .'?newsort=' . $which
         . '&amp;field=' . $field
         . '"><IMG SRC="../../images/' . $img
         . '" BORDER=0 WIDTH=12 HEIGHT=10 ALT="sort"></a>';
}


// ---------------------------------------------------------------


?>