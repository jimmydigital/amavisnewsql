<?php
/**
 * The server side of my example soap calls.
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Jared Watkins 2003 <jared@watkins.net>
 * @package amavisnewsql 
 * $Id: server.php, v
*/

require_once ("../nusoap.php");
require_once("../amavisnewsql.class.php");
require_once ("../config.php");

$s = new soap_server;
$s->register('remove_user');


function remove_user($username) {
   // Connect to the DB
    $dbfp = new AmavisNewSQL();
    if (!$dbfp->connect()) {
        return new soap_fault('Server', '', $dbfp->error);
    }

    if ($username == null || !is_string($username)) {
        return new soap_fault('Client', '', 'Supplied username is invalid: Must be a non-null string');
    }

    if (!$dbfp->UserExists($username) == TRUE) {
        return TRUE;  // Return true since all we want to know is that they are not in the database
    }

    if (!$dbfp->RemoveUser($username)) {
        return new soap_fault('Client', '', __FUNCTION__.": Error Removing User DB:$dbfp->error:");
    }

    return TRUE;
}

$s->service($HTTP_RAW_POST_DATA);

?>