<?php
/**
 * This is where I put stuff that is specific to this plugin.. but
 * does not belong in the class.  Most of the output screens are done
 * here. This is also where you can customize how errors are handled.
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Jared Watkins 2003 <jared@watkins.net>
 * @package amavisnewsql
 * $Id: functions.php, v
*/

/**
 * Handles all error output
 */
function amavisnewsql_ErrorOut($err, $fatal=false) {
   global $color;
   if (is_array($err)) {
       print_r($err);
   } else {
       $prev = bindtextdomain ('squirrelmail', SM_PATH . 'locale');
       textdomain ('squirrelmail');
       displayPageHeader($color, 'None');
       echo "<h3><font color=red>Error: $err</font></h3>";
       $prev = bindtextdomain ('amavisnewsql', SM_PATH . 'plugins/amavisnewsql/locale');
       textdomain ('amavisnewsql');
   }
   if($fatal) die();
}

/**
 * This prints the top section for adding / editing your W/B list
 */
function amavisnewsql_PrintAddEditWB($action, $row = '', $calledfrom=NULL, $line=NULL) {
   global $color, $dbfp, $username;

   if($action == 'prep_edit') {
      $title = "Edit Address";
      $action = "add_edit_wb_address";

      $wbList = $dbfp->ReadWBList($username, $calledfrom, $line);
      if (!$wbList && $wbList != null) {
         amavisnewsql_ErrorOut($dbfp->error, TRUE);
      }


      for($i=0; $i < count($wbList); $i++) {
          if($wbList[$i]["row"] == $row) {
             $address = $wbList[$i]["address"];
             $priority = $wbList[$i]["priority"];
             $wb = $wbList[$i]["wb"];
          }
      }
      if($wb == 'W') $Wselect = "selected";
      else if($wb == 'B') $Bselect = "selected";

   } else if($action == 'add') {
      $title = _("Add New Address");
      $action = _("add_edit_wb_address");
   }

   echo "
            <form action=\"$_SERVER[PHP_SELF]\" method=\"post\">
            <input type=\"hidden\" name=\"action\" value=\"$action\">
            <input type=\"hidden\" name=\"row\" value=\"$row\">
            <table width=\"100%\" border=\"0\">
             <tr><td align=center bgcolor=\"$color[5]\" colspan=\"4\"><b>$title</b></td></tr>
                <tr>
                  <td><b>" . _("Address:") . "</b></td>
                  <td><input name=\"address\" value=\"$address\" type=\"text\" size=\"40\" maxlength=\"100\"></td>
                  <td width=\"50%\" valign=\"top\" rowspan=\"4\"><center><i><b>" . _("Examples") . "</b></i></center><pre>
friend@aol.com  whitelist  7 (" . _("complete address") . ")" . _(" OR") . "
friend          whitelist  7 (" . _("complete part before domain") . ")
@aol.com        blacklist  6 (" . _("domain only including the ") . "@)
                  </pre>
                  <a href=\"amavisnewsql.php?action=whitelist_abook\">" . _("Whitelist Everyone In Your Address Book") ."</a>
                  </td>
                </tr>
                <tr>
                  <td><b>" . _("Type:") . "</b></td>
                  <td>
                     <select name=\"WorB\">
                        <option $Wselect value=\"W\">" . _("Whitelist") . "</option>
                        <option $Bselect value=\"B\">" . _("Blacklist") . "</option>
                     </select>
                  </td>
                </tr>
                <tr>
                  <td><b>" . _("Priority:") . "</b></td>
                  <td>\n
                     <select name=\"priority\">";

                     for($i=6; $i <= 9; $i++) {
                        if($i == $priority) $selected = 'selected';
                        echo "<option $selected value=\"$i\">$i</option>";
                        $selected = "";
                     }

   echo "
                     </select>
                  </td>
                </tr>
                <tr>
                  <td></td><td><input type=\"submit\" value=\"$title\" name=\"submit\"><br><br>
                  </td>

                </tr>
            </table>
            </form>
     </td>
    </tr>
   </table>
   ";
}

/**
 * Displays the little popup window of global W/B lists
 */
function amavisnewsql_DisplayGlobalList() {
   global $dbfp, $color;


   $q = "select $dbfp->wblist_table.email, $dbfp->wblist_table.wb,
                $dbfp->wblist_table.priority
         from $dbfp->wblist_table, $dbfp->users_table
         where $dbfp->wblist_table.rid = $dbfp->users_table.id
         and $dbfp->wblist_table.priority < 6
         and $dbfp->users_table.id = '$dbfp->global_user_id'
         order by $dbfp->wblist_table.priority";


   echo"<table border=\"0\" width=\"100%\" align=\"center\" cellpadding=\"2\" cellspacing=\"0\">
             <tr>
                <td colspan=\"3\" align=\"center\" bgcolor=\"$color[3]\"><b>Global White/Black Lists</b>
                </td>
             </tr>
             <tr bgcolor=\"$color[5]\">
                 <td width=\"60%\"><i>" . _("Address") . "</i></td>
                 <td><i>" . _("Type") . "</i></td>
                 <td><i>" . _("Priority") . "</i></td>
             </tr>
       ";



   if (!$res = $dbfp->sqlRead($q, __FILE__, __LINE__)) {
       amavisnewsql_ErrorOut("amavisnewsql_DisplayGlobalList Running Global Query Database Error: $dbfp->error");

   } else {

    for($i=0; $i < $res->numRows(); $i++) {
       $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
       if($row["wb"] == "W") $type = "Whitelist";
         else $type = "Blacklist";
       echo "<tr><td>$row[email]</td><td>$type</td><td>$row[priority]</td></tr>";

    }

   echo "</table>";




   }

}

/**
 * Prints the form that allows you to set your SA options
 */
function amavisnewsql_DisplayOptions($action = '', $row = '', $calledfrom=NULL, $line=NULL) {
  global $CONFIG, $dbfp, $username, $color;

  if($CONFIG["use_quarantine"]) {
     $tag2_label = _("Quarantine Level");
  } else {
     $tag2_label = _("Kill Level");
  }

  $prev = bindtextdomain ('squirrelmail', SM_PATH . 'locale');
  textdomain ('squirrelmail');
  displayPageHeader($color, 'None');
  $prev = bindtextdomain ('amavisnewsql', SM_PATH . 'plugins/amavisnewsql/locale');
  textdomain ('amavisnewsql');


 echo "
      <table border=\"1\" width=\"95%\" align=\"center\" cellpadding=\"2\" cellspacing=\"2\">
      <tr>
         <td align=\"center\" bgcolor=\"$color[0]\">
         <b>" . _("Options") . " - " . _("SpamAssassin Configuration") . " </b>

         <!-- White/Blacklist -->\n


       <table width=\"100%\" border=\"0\" cellpadding=\"1\" cellspacing=\"1\">
       <tr>
           <td align=\"center\"><b><i><a href=\"javascript:void(0);\" onclick=\"window.open('$_SERVER[PHP_SELF]?action=display_global_list','GlobalList','width=400,height=300,scrollbars=yes');\">". _("Global Address List") . "</a></b></i><td>
       </tr>
        <tr>
           <td align=\"center\" bgcolor=\"$color[5]\"><b>" . _("Personal Allow / Deny Addresses List") ."</b>
           </td>
        </tr>
        <tr>
           <td bgcolor=\"$color[4]\">
             <table border=\"0\" width=\"100%\">
             <tr>
                <td><b>" . _("Address") . "</b></td>
                <td><b>" . _("Type") . "</b></td>
                <td><b>" . _("Priority") . "</b></td>
                <td><b>" . _("Action") . "</b></td>
                </tr>

             <!-- User's white/black list -->
   ";
      $wbList = $dbfp->ReadWBList($username, $calledfrom, $line);
      if (!$wbList && $wbList != null) {
         amavisnewsql_ErrorOut($dbfp->error, TRUE);
      }


     if(count($wbList) == 0) {
        echo "<tr><td><b><font color=red>" . _("No Personal Addresses Defined") . "</b>
              </td></tr>";
     } else {

        for ($j=0; $j < count($wbList); $j++) {
            echo "<tr>
                  <td>".$wbList[$j]["address"]."
                  </td>";

            echo "<td>";
               if ($wbList[$j]["wb"] == 'W') {
                     echo _("Whitelist");
               } elseif ($wbList[$j]["wb"] == 'B') {
                     echo _("Blacklist");
               }
            echo "</td>";

            echo "<td>".$wbList[$j]["priority"]."</td>";
            echo "<td>
                  <a href=$_SERVER[PHP_SELF]?action=prep_edit_wb_address&row=".$wbList[$j]["row"].">Edit</a>
                  &nbsp
                  <a href=$_SERVER[PHP_SELF]?action=delete_wb_address&row=".$wbList[$j]["row"].">Delete</a>
                  </td></tr>";
        }
     } //if else

echo "
       </table>\n
       <!-- End white/blacklist -->\n
       </td></tr>\n
       <tr>\n
          <td bgcolor=\"$color[4]\">\n";
           if($action == 'prep_edit_wb_address') {
                amavisnewsql_PrintAddEditWB("prep_edit", $row);
           } else {
                amavisnewsql_PrintAddEditWB("add");
           }
echo "
   <table width=\"100%\" border=\"0\" cellpadding=\"1\" cellspacing=\"1\">
     <tr>
       <td colspan=4 align=\"center\" bgcolor=\"$color[4]\"><b>" . _("Description") . "</b></td>
    </tr>
    <tr>
      <td colspan=\"0\" bgcolor=\"$color[4]\">
             <td bgcolor=\"$color[4]\"><b><center>" . _("Whitelist / Blacklist") . "</b></center><br>" .
                _("Here you may list the addresses you wish to protect and those you wish to block. Please keep in mind that spammers often have throw-away or fake addresses so blacklisting these is pointless. The listed addresses appear in the same order as they will be applied by the mail server. Please read the section on priority to understand why this is important.") . "
      </td>

      <td bgcolor=\"$color[4]\"><center><b>" . _("Priority") . "</b></center><br>" .
                _("The priority determines the order of the list with the search stopping at the first matching address.  Overlapping addresses should be listed from specific to general.  E.g. if you want to allow one person from aol to mail you and block everyone else you would use two rules. The higher priority one set to whitelist friend@aol.com and a lower priority one set to blacklist @aol.com. The server will match the specific higher priority address first and end the search.  Addresses that are whitelisted for the whole company appear above with the lowest priorities.. your settings can always override the global ones.") . "
        </td>
    </tr>
   </table>


 <!-- Policy Setting -->
";

if (!$policyList = $dbfp->ReadPolicyList("%", $calledfrom, $line)) {
   amavisnewsql_ErrorOut($dbfp->error, TRUE);
}

if (!$myPolicy = $dbfp->ReadPolicyList($username, $calledfrom, $line)) {
   amavisnewsql_ErrorOut($dbfp->error, TRUE);
}

if(count($policyList) == 0) {
        echo "<b><font color=red>" . _("No Policies Defined") ."</b>";
     } else {

         if($CONFIG["use_quarantine"]) {
             if (!$quarantine = $dbfp->ReadQuarantineSettings($username, $calledfrom, $line)) {
                 amavisnewsql_ErrorOut($dbfp->error, TRUE);
             }
            echo "
                 <form action=\"$_SERVER[PHP_SELF]\">
                 <input type=\"hidden\" name=\"action\" value=\"set_quarantine_settings\">
                 <table width=\"100%\" border=\"0\" cellpadding\"1\" cellspacing=\"1\">
                 <tr>
                    <td align=\"center\" bgcolor=\"$color[5]\"><b>" . _("Quarantine Settings") ."</b></td>
                 </tr>
                 <tr>
                    <td align=\"center\" bgcolor=\"$color[4]\">
                       <table border=\"0\" width=\"100%\" cellpadding=\"1\" cellspacing=\"1\">
                         <tr>
                              <td height=\"30\">" . _("How often should you receive quarantine reports?") ."</td>
                              <td><i>" . _("Currently:") ."</i> $quarantine[freq] <br>

                                  <select name=\"frequency\">
                                  <option selected value=\"\"></option>
                                  <option value=\"WD\">" . _("Week Days (Default)") ."</option>
                                  <option value=\"N\">" . _("Never") ."</option>
                                  <option value=\"D\">" . _("Daily") ."</option>
                                  <option value=\"W\">" . _("Weekly (Friday)") ."</option>
                                  <option value=\"M\">" . _("Monthly (1st)") ."
                                  </select>

                              </td>
                         </tr>
                         <tr><td height=\"10\"></td></tr>
                         <tr>
                              <td height=\"30\">" . _("How long should mail stay in the quarantine before being automatically removed?") . "</td>

                              <td>
                                  <i>" . _("Currently:") ."</i> $quarantine[retention]<br>
                                  <select name=\"retention\">
                                  <option selected value=\"\"></option>
                                  <option value=\"3\">" . _("3 Days") ."</option>
                                  <option value=\"5\">" . _("5 Days") ."</option>
                                  <option value=\"7\">" . _("7 Days") ."</option>
                                  <option value=\"10\">" . _("10 Days") ."</option>
                                  <option value=\"14\">" . _("14 Days") ."</option>
                                  <option value=\"20\">" . _("20 Days") ."</option>
                                  <option value=\"30\">" . _("30 Days") ."</option>
                                  </select>
                              </td>
                         </tr>
                         <tr><td height=\"10\"></td></tr>
                         <tr>
                              <td height=\"30\">" . _("Should messages be quarantined or rejected when reaching the higher Score Level?") ."</td>

                              <td>
                                   <i>" . _("Currently:") . "</i> $quarantine[onoff]<br>
                                   <select name=\"quarantineonoff\">
                                   <option selected value=\"\"></option>
                                   <option value=\"1\">" ._("Use Quarantine") . "</option>
                                   <option value=\"0\">" ._("Reject High Scoring Email") . "</option>
                                   </select>
                              </td>
                         </tr>
                         <tr>
                              <td> </td>
                              <td valign=\"bottom\" height=\"40\">
                                  <input type=\"submit\" value=\"" . _("Set Quarantine Policy") . "\">
                              </td>
                         </tr>
                       </table>
                    </td>
                 </tr>
                 </table>
                 </form>
            ";
         }

         echo "


              <table width=\"100%\" border=\"0\" cellpadding\"1\" cellspacing=\"1\">
              <tr>
                 <td align=\"center\" bgcolor=\"$color[5]\"><b>" . _("Message Scoring Policy") . "</b></td>
              </tr>
              <tr>

                 <td align=\"center\" bgcolor=\"$color[4]\">
                    <table border=\"0\" width=\"100%\" cellpadding=\"1\" cellspacing=\"1\">
                      <tr><td><b>" . _("Your Current Policy") . "</b></td><td><b>" . _("Tag Level") . "</b></td><td><b>$tag2_label</b></td></tr>\n
                      <tr><td>".$myPolicy[0]["policy_name"]."</td><td>".$myPolicy[0]["tag2_level"]."</td><td>".$myPolicy[0]["kill_level"]."</td></tr>\n
                      <tr><td colspan=4><hr></td></tr>\n
                      <tr><td><b>" . _("Predefined Policies") . "</b><br><i>(" . _("Click To Select") . ")</i></td><td><b>" . _("Tag Level") . "</b></td><td><b>$tag2_label</b></td></tr>\n
         ";

                    for($i=0; $i < count($policyList); $i++) {
                      $setlink = "<a href=$_SERVER[PHP_SELF]?action=set_policy&id=".$policyList[$i]["id"].">".$policyList[$i]["policy_name"]."</a>";

                      echo "<tr><td>$setlink</td>\n
                            <td>".$policyList[$i]["tag2_level"]."</td>\n
                            <td>".$policyList[$i]["kill_level"]."</td></tr>\n";
                    }

         echo "

                 <tr><td colspan=4 bgcolor=\"$color[5]\"><b>" . _("Define Custom Policy") . "</b></td></tr>
                 <tr>
                   <form action=\"$_SERVER[PHP_SELF]\">
                   <input type=\"hidden\" name=\"action\" value=\"set_custom_policy\">
                 </tr>

                 <tr>
                   <td>" . _("Custom Policy") . "</td>
                   <td><input name=\"tag2_level\" type=\"text\" maxlength=\"4\" size=\"3\"></td>
                   <td><input name=\"kill_level\" type=\"text\" maxlength=\"4\" size=\"3\"></td>
                   <td><input type=\"submit\" value=\"" . _("Set Custom Policy") . "\"</td>
                 </tr>
                 </form>
                 <tr><td bgcolor=\"$color[4]\" colspan=4>



                     <table width=\"100%\" border=\"0\" cellpadding=\"3\" cellspacing=\"1\">
                     <tr>
                         <td colspan=4 align=\"center\" bgcolor=\"$color[4]\"><b>" . _("Description") . "</b></td>
                     </tr>



                     <td bgcolor=\"$color[4]\" valign=\"top\"><center><b>" . _("Policies") . "</b></center><br>" .
           _("You may select from one of several predefined scoring policies or you may define your own. Each email is assigned a numerical score based on the characteristics it shares with those common to spam.") . "
                     </td>
                     <td bgcolor=\"$color[4]\" valign=\"top\"><b><center>" . _("Tag Level") . "</b></center><br>" .
                        _("The tag level is the numerical score required to identify a message as being spam.  This will cause *SPAM* to be added to the subject line and the X-Spam-Flag header to be set to YES.") . "
                     </td>
                     <td colspan=2 valign=\"top\" bgcolor=\"$color[4]\"><center><b>$tag2_label</b></center><br>" .
                        _("The ") . _("$tag2_label") . _(" is the numerical score required to prevent delivery of a message at the server.  This level should be higher or equal to Tag Level and high enough to avoid catching legitimate email. As your custom whitelist grows you should adjust these two levels to tag and block more spam.") . "
                     </td>
                 </tr>
          </table>
         ";
     }



$dbfp->disconnect();
echo "</body></html>";
} //function amavisnewsql_DisplayOptions
?>
