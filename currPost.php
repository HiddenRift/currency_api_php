<?php
/**
 * Created by PhpStorm.
 * User: student
 * Date: 11/11/18
 * Time: 4:25 PM
 */

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/xml_handler.php';
require_once __DIR__ . '/lib/error.php';

use currency_api\xml_interface as xml_interface;
use currency_api\error_handler as error_handler;

/*
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['code'] = 'BRL';
$_POST['rate'] = '5.23432';
*/


if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'POST')
{
    send_error(2000, 0);
}

if(!isset($_POST['code']))
{
    //send_error(2200, 0);
    echo("error, code not recieved, got ");
    var_dump($_POST);
    die(0);
}
$iso4217_code = $_POST['code'];

if(!preg_match('/^[a-z][a-z][a-z]$/i', $iso4217_code))
{
    //error
    send_error(2200, 0);
}

if(!isset($_POST['rate']))
{
    send_error(2100, 0);
}
$exchange_rate = $_POST['rate'];

if(!preg_match('/^([0-9])+\.([0-9])+$/', $exchange_rate))
{
    //amount not decimal number
    send_error(2100, 0);
}

try
{

    $success = xml_interface\manual_update_rate(strtoupper($iso4217_code), $exchange_rate);
}
catch(Exception $error)
{
    send_error(2500,1);
}

if($success == false)
{
    // rate not found
    send_error(2400, 0);
} else{
    $current_time = date('d M Y H:i', time());
    // rate found
    $result = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<method type="post">
    <at>{$current_time}</at>
    <rate>{$success['rate']}</rate>
    <old_rate>{$success['old_rate']}</old_rate>
    <curr>
        <code>{$success['code']}</code>
        <name>{$success['curr']}</name>
        <loc>{$success['loc']}</loc>
    </curr>
</method>
EOT;
    header('Content-type: text/xml');
    echo $result;
    die(0);
}


/**Generates an error message and terminates the script
 * @param string $err_code code of error to be output to client
 * @param int $exit_status exit code of script
 */
function send_error($err_code, $exit_status)
{
    $err_msg = error_handler\get_error_msg($err_code, 'xml', 'post');
    header('Content-type: text/xml');
    echo $err_msg;
    die($exit_status);
}
