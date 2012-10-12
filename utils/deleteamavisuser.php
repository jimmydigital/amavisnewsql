#!/usr/local/bin/php
<?php
/**
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Nick Davis 2005 <nick@mrtizmo.com>
 * @author Jared Watkins (small changes)
 * @package amavisnewsql
 * $Id: rmamavisuser.php, v
 *
 * This script should be called by the administrator if they wish to
 * manually remove a user from the amavis.users table.
 *
 * @param string username
 * @returns message detailing problem or success.
 *
 * Change this to point to the amavisnewsql plugin directory
 * or whereever else you are storing this config file
 *
 * Be sure to include a trailing slash
 *
*/

DEFINE ("BASEINCLUDE", "/htdocs/squirrel/plugins/amavisnewsql/");

// You should not have to change anything below this line
// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

include(BASEINCLUDE."config.php");
include(BASEINCLUDE."amavisnewsql.class.php");


if ($argc != 2) {

    echo "Usage: " . $argv[0] . " username\n\n";
    exit;

} else {

    $username = $argv[1];

}

/**
 * First check to see if the user is alredy in the amavis.users table.
 * If the user is not in the amavis.users table return an error message,
 * else remove them and all associated quarantine messages.
 *
 * UserExists returns: bool True on exists False on duplicate and Null on not found
 * RemoveUser returns: bool True on success False on failure Null on not found
*/

$dbfp = new AmavisNewSQL($CONFIG);
$dbfp->connect();
   $err = $dbfp->UserExists($username);
   if (is_bool($err) && $err == FALSE) { // duplicates of this user in amavis.users
       $dbfp->disconnect();
       die($dbfp->error ." You will have to remove the duplicate manually.\n");
   } else if ($err == NULL) { // this user cannot be found
           $dbfp->disconnect();
           die($dbfp->error ."\n");
   } else if ($err == TRUE) { // this user does exist
       if(!$dbfp->RemoveUser($username)) {
           $dbfp->disconnect();
           die($dbfp->error ."\n");
       } else {
           $dbfp->disconnect();
           echo "NOTICE: The user '$username' and all associated data was removed from the amavis database.\n\n";
       }
   }


?>
