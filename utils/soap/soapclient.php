<?php
/**
 * This is an example soap client to show how easy it is to allow other
 * systems you already have (intranet) to interact with this for user account
 * management. Account removal is the only function I need at the moment.. but I
 * expect this will grow over time.
 *
 * Still waiting to hear some feedback how well this soap class interacts with other
 * languages. So far I know there are issues with .NET.
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Jared Watkins 2003,2012 https://github.com/jimmydigital/amavisnewsql
 * @package amavisnewsql 
 * $Id: soapclient.php, v
*/

require_once ("nusoap.php");

$soapclient = new soapclient('http://localhost/squirrel/plugins/amavisnewsql/soap/server.php');
$soapclient->setCredentials('example', 'password');

$parms = array("test");
$res = $soapclient->call('remove_user', $parms);


if($res) echo "Success";
else print_r($res);

?>
