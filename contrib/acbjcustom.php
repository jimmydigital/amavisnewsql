<?
function amavisnewsql_UserExists() {
   global $CONFIG, $username, $dbfp, $data_dir;

   $q = "select id
         from $dbfp->users_table
         where username = '$username'";

   if(!$res = $dbfp->sqlRead($q, __FILE__, __LINE__)) amavisnewsql_ErrorOut($dbfp->error, TRUE);

   #$res = $dbfp->db->query($q);
   #       if (DB::isError($res)) {
   #             $dberr = $dbfp->db->getMessage();
   #             amavisnewsql_ErrorOut("amavisnewsql_UserExists Searching for User Database Error: $dberr", TRUE);
   #             return "";
   #       }

   $num = $res->numRows();
   if ($num == 1) {

       $arr = ldap_get_single($username);
       $email = $arr["mail"]["0"];

       $q = "update $dbfp->users_table set email = '$email' where username = '$username'";
       if(!$res = $dbfp->sqlWrite($q, __FILE__, __LINE__)) amavisnewsql_ErrorOut($dbfp->error, TRUE);

       #$res = $dbfp->db->query($q);
       #if (DB::isError($res)) {
       #    $dberr = $dbfp->db->getMessage();
       #    amavisnewsql_ErrorOut("amavisnewsql_UserExists Searching for User Database Error: $dberr");
       #    return FALSE;
       #}
       return TRUE;

   } else if ($num == 0) {
     $nextid = $dbfp->db->nextID("users_id");
     $full_name = getPref($data_dir, $username, 'full_name');

     $arr = ldap_get_single($username);
     $email = $arr["mail"]["0"];
     #$email  = getPref($data_dir, $username, 'email_address');

     if(($email == null) || ($full_name == null)) {
        displayPageHeader($color, 'None');
        die("<h3>Please fill out your Personal Information including Name and Email Address under Options</h3>");
     }

     $q = "insert into $dbfp->users_table
           (id, username, email, fullname, priority, policy_id, digest)
           values ('$nextid', '$username', '$email', '$full_name',
                        default, default, default)";
      $res = $dbfp->db->query($q);
   } else if($num > 1) {
         amavisnewsql_ErrorOut("amavisnewsql_UserExists Duplicate UserID Found Database Error: $num");
         exit();
   }

}







function ldap_get_single($uid) {
  global $CONFIG;
  $search_filter = "(uid=$uid)";

  $ldap = ldap_connect("ldap.amcity.com", 389) or
     die("Error connecting to ldap server");

  $sr = ldap_search($ldap, "o=amcity.com", "$search_filter");
  if (ldap_count_entries($ldap, $sr) == FALSE) {
      ldap_close($ldap);
      return FALSE;
  } else {
          $results = my_ldap_get_attributes($ldap, ldap_first_entry($ldap, $sr));

          ldap_close($ldap);
#          echo "result example :".$results[0]["cn"][0].":<br>\n";
          return $results;
        }

}

function my_ldap_get_attributes($ldap, $entry) {
  // Lowercases all attribute names

  $attribs_mixed = ldap_get_attributes($ldap, $entry);
  $attribs_lower = array();
  $attribs_lower["count"] = $attribs_mixed["count"];

  for($i=0; $i < $attribs_mixed["count"]; $i++) {
     $attrib = $attribs_mixed[$i];
     $attrib_lower = strtolower($attrib);
     $attribs_lower[$attrib_lower]["count"] = $attribs_mixed[$attrib]["count"];

     for($j=0; $j < $attribs_mixed[$attrib]["count"]; $j++) {
        $attribs_lower[$attrib_lower][] = $attribs_mixed[$attrib][$j];

     }
  }

  return($attribs_lower);
}



?>