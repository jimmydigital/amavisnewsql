<?
/**
 * Class for dealing with a database for amavisnewsql or other tools
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Jared Watkins 2003 <jared@watkins.net>
 */

/**
 * Load our config files
 */
include_once('DB.php');

/**
  * @function void AmavisNewSQL()
  * @function fp     connect()
  * @function bool   disconnect()
  * @function mixed  listQuarantineMessages(username)
  * @function bool   RemoveUser(username)
  * @function bool   UserExists(username)
  * @function bool   CreateUser(username, data_dir)
  * @function bool   CreateUserDirect(username, email, full_name) (For command line tools)
  * @function bool   AddEditWBList(username, wb, priority, address, row=null)
  * @function bool   SetCustomPolicy(username, tag2level, kill_level)
  * @function bool   SetPolicy(username, target_id)
  * @function bool   _ValidatePolicyRanges(tag2, kill)
  * @function bool   SetQuarantineSettings(username, freq, retention)
  * @function mixed  ReadQuarantineSettings(username)
  * @function bool   DeleteWBAddress(username, delrow)
  * @function mixed  ReadWBList(username)
  * @function string uid(username)
  * @function mixed  ReadPolicyList(username = '%')
  * @function bool   AddressSanityCheck($address)
  * @function mixed  _HowManyOwners(messageid)
  * @function bool   DeleteQuarantineMessages(mixed msgid, string username)
  * @function bool   _RemoveMessage(msgid, username, method)
  * @function bool   ReleaseQuarantineMessages(array messages, string username)
  * @function bool   WListMessages(array messages, string username)
  * @function bool   Whitelist_Addressbook(array alist)
  * @function bool   _IsCustomPolicyDefined(string username)
  */
class AmavisNewSQL {

      var $dsn;
      var $global_user_id;
      var $wblist_table;
      var $users_table;
      var $policy_table;
      var $msgowner_table;
      var $msg_table;
      var $smtp_host;
      var $smtp_port;
      var $db;
      var $error;


       function AmavisNewSQL($CONFIG) {

         $this->dsn = $CONFIG["dsn"];
         $this->global_user_id = $CONFIG["global_user_id"];
         $this->wblist_table = $CONFIG["wblist_table"];
         $this->users_table = $CONFIG["users_table"];
         $this->policy_table = $CONFIG["policy_table"];
         $this->msgowner_table = $CONFIG["msgowner_table"];
         $this->msg_table = $CONFIG["msg_table"];
         $this->smtp_host = $CONFIG["smtp_host"];
         $this->smtp_port = $CONFIG["smtp_port"];
         $this->db = null;
         $this->error = null;
      }

    /**
      * Return an array containing details of the quarantine messages for a given username.. 
      * This was moved over from generatedigest.php as of 0.8
      * @param string username
      * @returns mixed Array on success Fale on failure
      */

     function listQuarantineMessages($username, $calledfrom=null, $line=null) {
          $this->error = null;

          $q = "select $this->msg_table.id, $this->msg_table.sender, $this->msg_table.subject,
                       $this->msg_table.storetime as storetime,
                       $this->msg_table.score
                from $this->msg_table, $this->users_table, $this->msgowner_table
                where $this->msgowner_table.rid = $this->users_table.id
                and $this->users_table.username = '$username'
                and $this->msg_table.id = $this->msgowner_table.msgid
                order by $this->msg_table.score
                limit 100
               ";

          if(!$res = $this->sqlRead($q, $calledfrom, $line)) return FALSE;

          $results = array();

          for ($i=0; $i < $res->numRows(); $i++) {

               $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
               $results[$i]["sender"] = $row["sender"];
               $results[$i]["subject"] = substr($row["subject"], 0, 40);
               $results[$i]["id"] = $row["id"];
               $results[$i]["storetime"] = date("D m/d G:i" ,$row["storetime"]);
               $results[$i]["score"] = $row["score"];

          }

          return($results);

      }



    /**
      * Read in the users local addressbook and update the whitelist with any entrys you find
      * ignoring any that are already in there.
      * @param string username
      * @param array alist
      * @returns bool True on success Fale on failure
      */
      function Whitelist_Addressbook($username, $alist) {
          $this->error = null;
          if (!is_array($alist)) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          if (!$wblist = $this->ReadWBList($username)) return FALSE;
          if ($wblist == -1) $wblist = 0; // Due to the stupid way empty arrays wont pass is_array

          $addressestoadd = array();
          $add = true;

          // Compare the two lists and keep track of any abook emails not in the whitelist
          for ($i=0; $i < count($alist); $i++) {
              for ($j=0; $j < count($wblist); $j++) {
                  if (strtolower($alist[$i]["email"]) == strtolower($wblist[$j]["address"])) {
                      $add = false;
                      continue;
                  }
              }

              if ($add) {

                  array_push ($addressestoadd, $alist[$i]["email"]);

              } else $add = true;
          }

          if (count($addressestoadd) == 0) {  // Nothing to add
             return TRUE;
          }

          for ($i=0; $i < count($addressestoadd); $i++) {
            if (!$this->AddEditWBList($username, "W", 7, $addressestoadd[$i])) {
                return FALSE;
            }
          }

          return TRUE;
      }

    /**
      * Used to extract a sender address from a message in quarantine
      * and whitelist it.
      * @param array messageids
      * @param string username
      * @returns bool True on success Fale on failure
      */
      function WListMessages($msg, $username) {
          $this->error = null;
          if (!is_array($msg) || !is_string($username)) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          for ($i=0; $i < count($msg); $i++) {
              $q = "SELECT sender from $this->msg_table, $this->users_table, $this->msgowner_table
                    where username = '$username'
                    and $this->users_table.id = $this->msgowner_table.rid
                    and $this->msgowner_table.msgid = $this->msg_table.id
                    and $this->msgowner_table.msgid = $msg[$i]
                    ";

              if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

              $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
              $sender_email = $row["sender"];

              if (!$this->AddEditWBList($username, "W", 7, $sender_email)) {
                 return FALSE;
              }
          }

          return TRUE;
      }

    /**
      * Release selected message ids from the quarantine and remove them
      * if not owned by anyone else.
      *
      * @param array messageids
      * @param string username
      * @returns bool True on success Fale on failure
      */
      function ReleaseQuarantineMessages($msg, $username) {
          $this->error = null;
          if (!is_array($msg) || !is_string($username)) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          // First.. fetch the primary email address to send the released messages to

          $q = "select email from $this->users_table where username = '$username'";

          if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

          $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
          $email_address = stripslashes($row["email"]);

          if(is_null($email_address)) {
             $this->error = "Please define a personal email address under 'Personal Information' on the Options page";
             return FALSE;
          }

          // If multiple.. format the array of ids
          for ($i=0; $i < count($msg); $i++) {
              if($i != 0) $msg_to_rel = $msg_to_rel.",";
              $msg_to_rel = $msg_to_rel."'".$msg[$i]."'";
          }

          $q  = "select $this->users_table.email, $this->msg_table.body,
                        $this->msg_table.id as messageid,
                        $this->msg_table.sender
                 from $this->msg_table, $this->users_table, $this->msgowner_table
                 where $this->msg_table.id in ($msg_to_rel)
                 and $this->msg_table.id = $this->msgowner_table.msgid
                 and $this->msgowner_table.rid = $this->users_table.id
                 and $this->users_table.username = '$username'
                 ";

          if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

          $smtp = new Net_SMTP($this->smtp_host, $this->smtp_port);

          for ($i=0; $i < $res->numRows(); $i++) {
              $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
              $body = substr($row["body"],5);  // Remove DATA from the first line since the class does that

              if (!$smtp->connect()) {
                  $this->error = "Unable to connect to smtp server";
                  return FALSE;
              }

              $smtp->helo("localhost");
              $smtp->mailFrom(stripslashes("$row[sender]"));

              if (!$smtp->rcptTo($email_address)) {
                  $this->error = "Error: Problem with Recipient Address :$email_address:";
                  return FALSE;
              }

              if (!$smtp->data(stripslashes($body))) {
                  $this->error = "Error Sending Message";
                  return FALSE;
              }

              $smtp->disconnect();
              $this->DeleteQuarantineMessages($row["messageid"], $username);

          }
          return TRUE;
      }


    /**
      * Removes all data for a given username from all tables in the database
      * while making sure none of their quarantined messages are owned by anyone
      * anyone else. Assumes you have already made sure they are in the database.
      *
      * @param string username
      * @returns bool True on success False on failure Null on not found
      */
      function RemoveUser($username) {
          $this->error = null;
          if ($username == null || !is_string($username)) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          // First fetch their list of quarantine mails
          // Make sure to only delete policy ids > 10!  =]

          $q = "select $this->msgowner_table.msgid,
                       $this->msgowner_table.rid,
                       $this->users_table.id as userid,
                       $this->users_table.policy_id
                from $this->msg_table, $this->users_table, $this->msgowner_table
                where $this->msgowner_table.rid = $this->users_table.id
                and $this->msgowner_table.msgid = $this->msg_table.id
                and $this->users_table.username = '$username'
                ";

          if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

          // Delete each message
          $numrows = $res->numRows();
          if ($numrows > 0) {
              for ($i=0; $i < $numrows; $i++) {
                   $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
                   $this->DeleteQuarantineMessage($row["msgid"], $username);
              }
          }

          // Now it's time to remove the userdata from other tables
          // First lets remove the policy line if they had a custom one

          $uid = $this->uid($username);

          $q = "select policy_id from $this->users_table where id = $uid";

          if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

          $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
          $policyid = $row["policy_id"];

          if ($policyid > 10) {  // 10 and below are system policies.. don't delete those =]

              $q = "delete from $this->policy_table where id = $policyid";
              if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) return FALSE;

          }

          // Remove all W/B list entries

          $q = "delete from $this->wblist_table where rid = $uid";
          if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) return FALSE;

          // Finally... remove the entry from the users table
          $q = "delete from $this->users_table where id = $uid";
          if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) return FALSE;

          return TRUE;
      }


    /**
      * DeleteQuarantineMessages - Moved from quarantine.php for reuse in the soap server
      *
      * @param mixed msg either an array or a string depending on the usage
      * @param string Username
      * @returns True on success False on failure
      */
      function DeleteQuarantineMessages($msg, $username) {
          $this->error = null;
          if ($username == null || $msg == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          if (is_array($msg)) { // array is used when the user selects a list of messages to delete
                                // from within the webmail app

              for ($i=0; $i < count($msg); $i++) {
                  $owners = $this->_HowManyOwners($msg[$i]);

                  if ($owners > 1) {
                      // More than one person has this in their quarantine so don't touch the msg table

                      $this->_RemoveMessage($msg[$i], $username, "partial");

                  } else {

                      $this->_RemoveMessage($msg[$i], $username, "complete");
                  }
              }

          } else if (is_numeric($msg)) {  // This one is mostly used from other methods of the class

              $owners = $this->_HowManyOwners($msg);

              if($owners > 1) $this->_RemoveMessage($msg, $username, "partial");
              else            $this->_RemoveMessage($msg, $username, "complete");

              return null;  // If $msg was an int stop here

          } else {

             $this->error = "Error in ".__FUNCTION__.": msg is neither number or array :$msg:";
             return FALSE;
          }

          return TRUE;
      }


    /**
      * _RemoveMessage - Moved from quarantine.php for reuse in the soap server
      *
      * @param int msgid
      * @param string username
      * @param string method
      * @returns True on success False on failure
      */
      function _RemoveMessage($msgid, $username, $method) {
          $this->error = null;
          if ($username == null || $msgid == null || $method == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          if(!$uid = $this->uid($username)) {
               return FALSE;
          }

          $this->sqlWrite("begin");

          $q = "delete from $this->msgowner_table
                where $this->msgowner_table.msgid = $msgid
                and rid = $uid";

          if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) {
               $this->sqlWrite("rollback");
               $this->disconnect();
               return FALSE;
          }

          if ($method == "complete") {

              $q = "delete from $this->msg_table
                    where $this->msg_table.id = $msgid";

              if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) {
                   $this->sqlWrite("rollback");
                   $this->disconnect();
                   return FALSE;
              }

          }

          $this->sqlWrite("commit");
      }


    /**
      * HowManyOwners - Moved from quarantine.php for reuse in the soap server
      * Returns the number of owners for a given msgid for an email in the
      * quarantine
      *
      * @param string messageid
      * @returns mixed Number on success False on Failure
      */
      function _HowManyOwners($msgid) {
          $this->error = null;
          if ($msgid == null || !is_numeric($msgid)) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          $q = "select rid from $this->msgowner_table where $this->msgowner_table.msgid = $msgid";
          if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

          return $res->numRows();
      }


    /**
      * Determines if a user is already in the users table..
      * This function is ripe for customizing for your own site.. like if
      * the user data is not in SM by default.. see the contrib directory
      *
      * @param string username
      * @returns bool True on exists False on duplicate and Null on not found
      */
      function UserExists($username, $calledfrom=null, $line=null) {
          $this->error = null;
          if ($username == null || !is_string($username)) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          $q = "select id
                from $this->users_table
                where username = '$username'";

          if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

          $num = $res->numRows();
          if($num == 1) {
              return TRUE;

          } else if($num == 0) {
              $this->error = "User '$username' does not exist in the database";
              return NULL;

          } else if($num > 1) {
              $this->error = "Duplicate UserID Found";
              return FALSE;
          }
      }

      function UpdateUserInfo($username, $data_dir, $email, $fullname=null) {
          $this->error = null;
          if ($username == null || $email == null || $data_dir == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }



      }

     /**
      * Create a user entry in the database
      *
      * @param string username
      * @param string data_dir from squirrelmail
      * @returns bool True on success False on failure
      */
      function CreateUser($username, $data_dir) {
          $this->error = null;
          if ($username == null || $data_dir == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

              $nextid = $this->db->nextID("users_id");
              $full_name = addslashes(getPref($data_dir, $username, 'full_name'));
              $email  = addslashes(getPref($data_dir, $username, 'email_address'));

              if ($email == null) {
                  $this->error = "Please fill out your Personal Information including Name and Email Address under Options";
                  return FALSE;
              }

              $q = "insert into $this->users_table
                    (id, username, email, fullname)
                     values ('$nextid', '$username', '$email', '$full_name')";

              if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) return FALSE;

              return TRUE;
      }

     /**
      * Create a user entry in the database Called from outside SQM
      *
      * @param string username
      * @param string email directly given email address
      * @param string FullName directly given full name
      * @returns bool True on success False on failure
      */
      function CreateUserDirect($username, $email, $full_name) {
          $this->error = null;
          if ($username == null || $email == null || $full_name == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }
          if (!strstr($email, '@')) {
              $this->error = "Invalid email address given to ".__FUNCTION__." function";
              return FALSE;
          }
          
          $full_name = addslashes($full_name);
          $email = addslashes($email);

              $nextid = $this->db->nextID("users_id");

              $q = "insert into $this->users_table
                    (id, username, email, fullname)
                     values ('$nextid', '$username', '$email', '$full_name')";

              if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) return FALSE;

              return TRUE;
      }


    /**
      * Check to see if the supplied address conforms to one of the allowed formats
      *
      * This is pretty basic... since the allowed input can be less than a complete
      * email address it's not going to be perfect.. but the worst that happens
      * is that amavis won't match it to checked mail. I know this can be improved.
      *
      * @param string address
      * @returns bool True on success False on failure
      */
      function AddressSanityCheck($address)
      {
          $this->error = null;
          if ($address == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          $badchars = array(' ', '(', ')', '<', '>', '[', '[', "\\");

          foreach ($badchars as $char) {
              if(strpos($address, $char) !== FALSE) return FALSE;
          }

          return TRUE;
      }


    /**
      * Add or Edit WB List for the given user
      *
      * @param string username
      * @param string wb W or B to indicate a white or black list entry
      * @param int Priority setting for entry
      * @param string Email address (full or partial) to be added/edited
      * @param int Row Defaults to null for add operations otherwise indicates the row number for that users list
      * @returns bool True on success False on failure
      */
      function AddEditWBList($username, $wb, $priority, $address, $row = null) {
          $this->error = null;
          if ($username == null || $wb == null || $priority == null || $address == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          $uid = $this->uid($username);
          $address = trim(strtolower($address));

          if (!$this->AddressSanityCheck($address)) {
             $this->error = "Invalid characters in email address: $address";
             return FALSE;
          }

          if($row == null) {   // It is an add operation

                $q = "select * from $this->wblist_table where email = '$address' and $this->wblist_table.rid = '$uid'";

                if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

                if($res->numRows() > 0) {
                   $this->error = "You Already Have That Address in Your List";
                   return FALSE;
                }

                $this->sqlWrite("begin");

                $q = "insert into $this->wblist_table
                      (rid, priority, email, wb)
                      values ('$uid', '$priority', '$address', '$wb')";

                 if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) {
                      $this->sqlWrite("rollback");
                      $this->disconnect();
                      return FALSE;
                 }

                $this->sqlWrite("commit");

          } else if(is_numeric($row)) {  // This is a change operation

               if (!$wbList = $this->ReadWBList($username)) return FALSE;
               if ($wbList == -1) $wbList = 0;

               for($i=0; $i <= count($wbList); $i++) {
                   if($i == $row) { // Make sure we are working on the right row from that users personal list

                      $this->sqlWrite("begin");

                      $q = "update $this->wblist_table
                            set priority = '$priority',
                            email = '$address',
                            wb = '$wb'
                            where rid = '".$wbList[$i]["rid"]."'
                            and email = '".$wbList[$i]["email"]."'";

                      if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) {
                           $this->sqlWrite("rollback");
                           $this->disconnect();
                           return FALSE;
                      }

                      $this->sqlWrite("commit");
                   }
               } //for
               $this->sqlWrite("commit");
          }  // if add or update
          return TRUE;
      }


    /**
      * Set custom policy values for a given username
      *
      * @param string username
      * @param float Tag2 level
      * @param float Kill / Quarantine Level
      * @returns bool True on success False on failure
      */
      function SetCustomPolicy($username, $tag2_level, $kill_level) {
          $this->error = null;
          if($username == null || $tag2_level == null || $kill_level == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          if(!$this->_ValidatePolicyRanges($tag2_level, $kill_level)) return FALSE;

          $q = "select id,policy_id from $this->users_table where username = '$username'";

          if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

          $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
          $uid = $row["id"];

          $this->sqlWrite("begin");

          if($row["policy_id"] > 10) {        // We are changing an existing custom policy
             $q = "update $this->policy_table
                   set spam_tag2_level = '$tag2_level',
                       spam_kill_level = '$kill_level'
                   where id = $row[policy_id]";

             if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) {
                 $this->sqlWrite("rollback");
                 $this->disconnect();
                 return FALSE;
             }

          } else {

             $nextid = $this->db->nextID("policy_id");
             $q = "insert into $this->policy_table
                  (id,policy_name,spam_tag2_level,spam_kill_level)
                   values ($nextid,'Custom','$tag2_level','$kill_level')";

             if (!$res1 = $this->sqlWrite($q, __FUNCTION__, __LINE__)) {
                 $this->sqlWrite("rollback");
                 $this->disconnect();
                 return FALSE;
             }

             $q = "update $this->users_table set policy_id = '$nextid' where id = '$uid'";

             if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) {
                 $this->sqlWrite("rollback");
                 $this->disconnect();
                 return FALSE;
             }
          }

          $this->sqlWrite("commit");
          return TRUE;
      }


    /**
      * Set a predefined policy for a given user
      *
      * @param string username
      * @param int Policy ID to set on user
      * @returns bool True on success False on failure
      */
      function SetPolicy($username, $target_id) {
          $this->error = null;
          if($username == null || $target_id == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          $q = "select id,policy_id from $this->users_table where username = '$username'";
          if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

          $row = $res->fetchRow(DB_FETCHMODE_ASSOC);
          $uid = $row["id"];

          $this->sqlWrite("begin");

          if($row["policy_id"] < 10) {
             $q = "update $this->users_table
                   set policy_id = '$target_id'
                   where id = '$uid'";

             if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) return FALSE;

          } else {
             // Since we are dumping a custom Policy Remove the entry from the policy table

            $del = "delete from $this->policy_table where id = '$row[policy_id]'";
            $q   = "update $this->users_table set policy_id = '$target_id' where id = '$uid'";

            if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) {
                $this->sqlWrite("rollback");
                $this->disconnect();
                return FALSE;
            }

            if (!$res = $this->sqlWrite($del, __FUNCTION__, __LINE__)) {
                $this->sqlWrite("rollback");
                $this->disconnect();
                return FALSE;
            }

          }
          $this->sqlWrite("commit");
          return TRUE;
      }


    /**
      * Test if the supplied values for tag2 and kill levels are sane
      *
      * @param float tag2 numerical score
      * @param float kill numerical score
      * @returns bool True on success False on failure
      */
      function _ValidatePolicyRanges($tag2, $kill) {

         $this->error = null;
         if($kill == "" || $tag2 == "") {
            $this->error = "Both Tag and Quarantine Levels Must be Set";
            return FALSE;
         }

         if($kill < $tag2) {
            $this->error = "Tag Level is Higher Than Quarantine Level";
            return FALSE;
         }
         return TRUE;
      }


    /**
      * Sets a given users quarantine settings
      *
      * @param string username
      * @param string $freq Frequency for digest mailings
      * @param int Days to retain messages in quarantine
      * @returns bool True on success False on failure
      */
      function SetQuarantineSettings($username, $freq, $retention, $qonoff, $calledfrom=null, $line=null) {
          $this->error = null;
          if ($username == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          $possible_freqs = array('N', 'WD', 'W', 'M', 'D');
          $possible_retentions = array(3, 5, 7, 10, 14, 20, 30);

          if($freq != "") {
             if(!in_array($freq, $possible_freqs)) {
                 $this->error = "Requested Report Frequency is Invalid";
                 return FALSE;
             } else {

                 $q = "update $this->users_table set digest = '$freq' where username = '$username'";

                 if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) return FALSE;
             }
          }

          if (is_numeric($retention)) {
              if (!in_array($retention, $possible_retentions)) {
                  $this->error = "Requested Retention Days is Invalid";
                  return FALSE;
              } else {

                  $q = "update $this->users_table set retention = '$retention' where username = '$username'";

                  if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) return FALSE;

             }
          }

          //
          // Code for switching quarantine on and off.. per user setting
          //

          if (is_numeric($qonoff)) {

              if ($ret = $this->_IsCustomPolicyDefined($username)) {
                  $polid = $ret["policy_id"];


              } else {  // If they don't already have a custom policy defined we need to make one for them.

                  // use setcustompolicy method...  pull their current settings from one of the predefined
                  // policys and create a custom one based on those levels.  This does not feel quite right.. since
                  // currently if they change from a custom to a predefined it will remove their custom policy entry
                  // and loose the quarantine settings... for now I'm ok with this.

                  $q = "select spam_tag2_level,spam_kill_level,policy_id from users,policy where username = '$username' and policy_id = policy.id";

                  if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;
                  $row = $res->fetchRow(DB_FETCHMODE_ASSOC);

                  if (!$this->setcustompolicy($username, $row["spam_tag2_level"], $row["spam_kill_level"])) return FALSE;

                  // Now that we have created them a custom policy based on the settings from their previous one.. get
                  // the new policy ID number.

                  $q = "select policy_id from $this->users_table where username = '$username'";
                  if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;
                  $row = $res->fetchRow(DB_FETCHMODE_ASSOC);

                  $polid = $row["policy_id"];
              }

              // Now that the policy numbers are figured out... set the quarantine option

              if ($qonoff == 1) {

                  $q = "update $this->policy_table set spam_quarantine_to = 'spam-quarantine' where id = '$polid'";

                  if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) return FALSE;

              } else {

                  $q = "update $this->policy_table set spam_quarantine_to = ' ' where id = '$polid'";

                  if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) return FALSE;

              }
          }


          return TRUE;
      }

    /**
      * Return Array or F if a user has a custom policy defined
      *
      * @param string username
      * @returns mixed int or False
      */
      function _IsCustomPolicyDefined($username) {
          $this->error = null;
          if($username == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          $q = "select id,policy_id from $this->users_table where username = '$username'";
          if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

          $row = $res->fetchRow(DB_FETCHMODE_ASSOC);

          if ($row["policy_id"] > 10) {
              return $row;
          } else return FALSE;

      }

    /**
      * Return a given users quarantine settings
      *
      * @param string username
      * @returns mixed Array on success and False on failure
      */
      function ReadQuarantineSettings($username) {
          $this->error = null;
          if($username == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          $q = "select digest, retention, spam_quarantine_to
                from $this->users_table, $this->policy_table
                where username = '$username'
                and $this->users_table.policy_id = $this->policy_table.id";

          if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

          $row = $res->fetchRow(DB_FETCHMODE_ASSOC);

          switch ($row["digest"]) {
             case "N":
                $ret["freq"] = _("Never");
             break;
             case "WD":
                $ret["freq"] = _("Week Days");
             break;
             case "W":
                $ret["freq"] = _("Weekly");
             break;
             case "M":
                $ret["freq"] = _("Monthly");
             break;
             case "D":
                $ret["freq"] = _("Daily");
             break;
          }

          $ret["retention"] = $row["retention"]. _(" Days");

          if ($row["spam_quarantine_to"] == "spam-quarantine") {
              $ret["onoff"] = _("Quarantine");
          } else $ret["onoff"] = _("Reject");

          return $ret;
      }


    /**
      * Delete a specified WB List address for a given username
      *
      * @param string username
      * @param int Matching row of WBList to delete
      * @returns bool True on success False on failure
      */
      function DeleteWBAddress($username, $delrow) {
          $this->error = null;
          if($username == null || $delrow == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

          if (!$wbList = $this->ReadWBList($username)) return FALSE;
          if ($wbList == -1) $wbList = array();

          $this->sqlWrite("begin");

          if (count($wbList) == 0) return TRUE;

          for($i=0; $i <= count($wbList); $i++) {
              if($i == $delrow) {
                 $q = "delete from $this->wblist_table
                       where rid = '".$wbList[$i]["rid"]."'
                       and email = '".$wbList[$i]["address"]."'";

                 if (!$res = $this->sqlWrite($q, __FUNCTION__, __LINE__)) {
                     $this->sqlWrite("rollback");
                     $this->disconnect();
                     return FALSE;
                 }
              }
          }
          $this->sqlWrite("commit");
          return TRUE;
      }


    /**
      * Read in the policy settings for a given user
      *
      * @param string username
      * @returns mixed Array of data on success or False on failure
      */
      function ReadPolicyList($username = '%') {
         $this->error = null;
          if($username == "%") {
            $q = "select *
                 from $this->policy_table
                 where $this->policy_table.id <= 10
                 order by spam_tag2_level";
         } else {
            $q = "select $this->policy_table.policy_name,
                     $this->policy_table.spam_tag2_level,
                     $this->policy_table.spam_kill_level,
                     $this->users_table.id,
                     $this->users_table.email
                  from $this->policy_table, $this->users_table
                  where $this->users_table.policy_id = $this->policy_table.id
                  and $this->users_table.username = '$username'
                  order by $this->policy_table.id";
         }

         if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

         $PolicyList = array();

         for($i=0; $i < $res->numRows(); $i++) {
             $row = $res->fetchRow(DB_FETCHMODE_ASSOC);

             $PolicyList[$i]["policy_name"] = $row["policy_name"];
             $PolicyList[$i]["id"] = $row["id"];
             $PolicyList[$i]["tag2_level"] = $row["spam_tag2_level"];
             $PolicyList[$i]["kill_level"] = $row["spam_kill_level"];
         }
         return $PolicyList;
      }


    /**
      * Read in the list of white/black list addresses for a given user
      *
      * @param string username
      * @returns mixed Array of data on success or False on failure -1 on 0 List entries found
      */
      function ReadWBList($username) {
          $this->error = null;
          if($username == null) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." function";
              return FALSE;
          }

         $q = "select $this->wblist_table.email, $this->wblist_table.priority,
              $this->wblist_table.wb, $this->users_table.username as user,
              $this->wblist_table.rid
              from $this->wblist_table, $this->users_table
              where $this->users_table.username = '$username'
              and $this->users_table.id = $this->wblist_table.rid
              order by $this->wblist_table.priority desc, $this->wblist_table.email";


         if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

         $wblist = array();
         for($i=0; $i < $res->numRows(); $i++) {
             $row = $res->fetchRow(DB_FETCHMODE_ASSOC);

             $wbList[$i]["address"] = $row["email"];
             $wbList[$i]["row"] = $i;
             $wbList[$i]["priority"] = $row["priority"];
             $wbList[$i]["wb"] = $row["wb"];
             $wbList[$i]["rid"] = $row["rid"];
             $wbList[$i]["email"] = $row["email"];
         }
         if (count($wbList) == 0) return -1;
         return $wbList;
      }


     /**
      * Gets useful error info when there is a db error
      * @param object Result set object
      * @returns string Error Message
      */
      function dbErrorMessage($res) {
          while (list($key, $value) = each ($res)) {
              if($key == "message" || $key == "userinfo") {
                  $message .= " = ".$value;
              }
          }
          return $message;
      }


    /**
      * Get a persons numerical uid from the given username or email address
      * @param string username or email address
      * @returns int on success False on failure
      */
      function uid($string) {
         $this->error = null;

         if(ereg("@", $string)) {
             $q = "select id from $this->users_table where email = '$string'";
         } else {
             $q = "select id from $this->users_table where username = '$string'";
         }


         if (!$res = $this->sqlRead($q, __FUNCTION__, __LINE__)) return FALSE;

         $row = $res->fetchRow(DB_FETCHMODE_ASSOC);

         return $row["id"];
      }


    /**
      * Wrapper method for customizing the database backend
      * @param string sql statement for write/update function
      * @param string Name of the calling function for use if there is an error (Optional)
      * @param int Line number of calling function again for error messages (Optional)
      * @returns mixed Result Set on success or False on failure
      */
      function sqlWrite($query, $calledfrom=null, $line=null) {
         $this->error = null;

         if (!is_string($query)) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." method from $calledfrom on line $line";
              return FALSE;
          }

         $res = $this->db->query($query);
         if(DB::isError($res)) {
            $this->error = "DB Error in ".__FUNCTION__." Message = '".$this->dbErrorMessage($res)."' called from $calledfrom on line $line";            return FALSE;
         }

         return $res;
      }

    /**
      * Wrapper method for customizing the database backend
      * @param string sql statement for read function
      * @param string Name of the calling function for use if there is an error (Optional)
      * @param int Line number of calling function again for error messages (Optional)
      * @returns mixed Result Set on success or False on failure
      */
      function sqlRead($query, $calledfrom=null, $line=null) {
         $this->error = null;

         if (!is_string($query)) {
              $this->error = "Improper arguments supplied for ".__FUNCTION__." method from $calledfrom on line $line";
              return FALSE;
          }

         $res = $this->db->query($query);
         if(DB::isError($res)) {
            $this->error = "DB Error in ".__FUNCTION__." Message = '".$this->dbErrorMessage($res)."' called from $calledfrom on line $line";
            return FALSE;
         }

         return $res;
      }

    /**
      * Connect to the database
      * @returns bool True on success or False on failure
      */
      function connect() {
         $this->error = null;
         $this->db = DB::connect($this->dsn, true);
         if (DB::isError($this->db) ) {
            $this->error = "DB Error in ".__FUNCTION__." Message = '".$this->db->getMessage($this->db)."'";
            return FALSE;
         }
         return TRUE;
      }


    /**
      * Disconnect from the database
      * @returns bool True on success False on failure
      */
      function disconnect() {
         $this->db->disconnect();
         if (DB::isError($this->db) ) {
            $this->error = $this->db->getMessage();
            return FALSE;
         }
         return TRUE;

      }


} // Class

?>