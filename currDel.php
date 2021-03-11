<?php
/**
 * Created by PhpStorm.
 * User: student
 * Date: 11/11/18
 * Time: 4:26 PM
 */

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/xml_handler.php';
require_once __DIR__ . '/lib/error.php';

use currency_api\xml_interface as xml_interface;
use currency_api\error_handler as error_handler;

if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'DELETE')
{
    send_error(2000, 0);
}

$putdata = @fopen("php://input", "r");



$query_string = fread($putdata,10);

// if string over 10 characters error (to avoid possible memory overload) and because valid string always has 8 characters
if(fread($putdata,10)) {
    //error invalid input
    send_error(2500, 0);
}

parse_str($query_string, $parsed_data);
if(!isset($parsed_data['code']))
{
    send_error(2200, 0);
}

if(!preg_match('/^[a-z][a-z][a-z]$/i', $parsed_data['code']))
{
    send_error(2200, 0);
}



try
{
    $deleted = xml_interface\delete_currency(strtoupper($parsed_data['code']));
}
catch(Exception $error)
{
    send_error(2500,1);
}

if($deleted)
{
    $current_time = date('d M Y H:i', time());
    $result = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<method type="delete">
    <at>{$current_time}</at>
    <code>{$parsed_data['code']}</code>
</method>
EOT;
    header('Content-type: text/xml');
    echo $result;
    die(0);
}else{
    send_error(2200, 0);
}

/**Generates an error message and terminates the script
 * @param string $err_code code of error to be output to client
 * @param int $exit_status exit code of script
 */
function send_error($err_code, $exit_status)
{
    $err_msg = error_handler\get_error_msg($err_code, 'xml', 'DELETE');
    header('Content-type: text/xml');
    echo $err_msg;
    die($exit_status);
}