#!/usr/bin/php
<?php
/**
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Nick Davis 2005 <nick@mrtizmo.com>
 * @author Jared Watkins 2005
 * @package amavisnewsql
 * $Id: addamavisuser.php, v
 *
 * This script should be called by the administrator if they wish to
 * manually add a user to the amavis.users table. By default, users are
 * added to that table when they activate their quarantine.
 *
 * @param string username
 * @param string full_name - must be in quotes "Josh Smith"
 * @param email address
 * @returns error message detailing problem or success.
 *
 * Change this to point to the amavisnewsql plugin directory
 * or whereever else you are storing this config file
 *
 * Be sure to include a trailing slash
 *
*/

DEFINE ("BASEINCLUDE", "/var/www/squirrel/plugins/amavisnewsql/");

// You should not have to change anything below this line
// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

include(BASEINCLUDE."config.php");
include(BASEINCLUDE."amavisnewsql.class.php");


if ($argc != 4) {
    echo "Usage: " . $argv[0] . " username \"FirstName LastName\" (in quotes) email (username@domain.com)\n\n";
    exit;
} else {
  $username = $argv[1];
  $full_name = $argv[2];
  $email = $argv[3];
}

/**
 * First check to see if the user is alredy in the amavis.users table.
 *
 * If the user is not in the amavis.users table, add them.
 * Values needed from admin:
 * username, full name
 *
 * UserExists returns: bool True on exists False on duplicate and Null on not found
 * CreateMordantUser returns: bool True on success False on failure Null on not found
*/

$dbfp = new AmavisNewSQL($CONFIG);
$dbfp->connect();
   $err = $dbfp->UserExists($username);
   if (is_bool($err) && $err == FALSE) {
        $dbfp->disconnect();
       die($dbfp->error ."\n");
   } else if ($err == NULL) {
       if(!$dbfp->CreateUserDirect($username, $email, $full_name)) {
           $dbfp->disconnect();
           die($dbfp->error ."\n");
       } else {
           $dbfp->disconnect();
           echo "OK: The user '$full_name' with username '$username' and email '$email' was added to the amavis database.\n";
       }
   } else if ($err == TRUE) {
           $dbfp->disconnect();
           die($dbfp->error ."\n");
   }

?>

