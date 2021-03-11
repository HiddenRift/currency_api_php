<?php
/**
 * Created by PhpStorm.
 * User: student
 * Date: 10/30/18
 * Time: 6:36 PM
 */

try {
    require_once __DIR__ . '/lib/xml_handler.php';
    require_once __DIR__ . '/lib/config.php';
    require_once __DIR__ . '/lib/error.php';
}catch(Exception $ex)
{
    http_response_code(500);
    die(1);
}

use currency_api\xml_interface as xml_interface;
use currency_api\error_handler as error_handler;

/*
$_GET['to'] = 'GBP';
$_GET['from'] = 'EUR';
$_GET['amnt'] = '10.35';
$_GET['format'] = 'xml';
*/

// get format data (this is separate as necessary to output error messages throughout script)
$format = 'xml';
if(isset($_GET['format']))
{
    if ($_GET['format'] == 'json' || $_GET['format'] == 'xml' || $_GET['format'] == '') {
        $format = ($_GET['format'] != '')? $_GET['format'] : 'xml';
    }else{
        send_error(1400, $format, 0);
    }
}
$format = strtolower($format);

//get other parameters, format pre specified to prevent false error
$parameters = array(
    'from' => '',
    'to' => '',
    'amnt' => '',
    'format' => $format
);

// check for unexpected parameters
foreach ($_GET as $var => $value) {
    if(isset($parameters[$var]))
    {
        $parameters[$var] = $value;
    } else {
        //unexpected parameter
        send_error(1100, $format, 0);
    }
}

//check for missing parameters
foreach ($parameters as $key => $parameter) {
    if(!isset($_GET[$key]) && $key !=  'format')
    {
        //parameter missing
        send_error(1000, $format, 0);
    }
}

//validate to from and amt variables
if(!preg_match('/^([0-9])+\.([0-9])([0-9])$/', $parameters['amnt']))
{
    //amount not decimal number
    send_error(1300, $format, 0);
}

if(!preg_match('/^[a-z][a-z][a-z]$/i', $parameters['from']))
{
    send_error(1200, $format, 0);
}

if(!preg_match('/^[a-z][a-z][a-z]$/i', $parameters['to']))
{
    send_error(1200, $format, 0);
}

//retrieve from file
try {
    $from_country = xml_interface\get_currency_info(strtoupper($parameters['from']));
    $to_country = xml_interface\get_currency_info(strtoupper($parameters['to']));
}catch(Exception $exception)
{
    send_error(1500, $format, 2);
}


if($from_country == false || $to_country == false)
{
    //currency not recognised
    send_error(1200, $format, 0);
}

// if code reaches here the parameters are valid
// calculate conversion rate and then convert input amount
// account for 0 exchange rate by leaving at 0 if from counties currency worthless
$conversion_rate = 0.0;
if((float)$from_country['rate'] != 0)
{
    $conversion_rate = (float)$to_country['rate'] / ((float)$from_country['rate'] * 1);
}
$converted_amt = (float)$parameters['amnt']*(float)$conversion_rate;

// round to 2 d.p and format to show string wih 2 d.p
//$conversion_rate = number_format(round($conversion_rate, 2), 2, '.', '');
//$converted_amt = number_format(round($converted_amt, 2), 2, '.', '');

$conversion_rate = round($conversion_rate, 6);
$converted_amt = round($converted_amt, 2);

$request_time = time();
if($request_time == false)
{
    //internal error occured
    send_error(1500, $format, 1);
}

$request_time = date('d M Y H:i',$request_time);


if($format == 'json')
{
    $from_response = array(
        'code' => $from_country['code'],
        'curr' => $from_country['curr'],
        'loc' => $from_country['loc'],
        'amnt' => (float)$parameters['amnt']);

    $to_response = array(
        'code' => $to_country['code'],
        'curr' => $to_country['curr'],
        'loc' => $to_country['loc'],
        'amnt' => $converted_amt);

    $response = array(
        'conv' => array(
        'at' => $request_time,
        'rate' => $conversion_rate,
        'from' => $from_response,
        'to' => $to_response));


    $response = json_encode($response,JSON_FORCE_OBJECT);
    header('Content-type: text/json');
    echo $response;
    die(0);
} else {
    //xml here
    $response = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<conv>
    <at>{$request_time}</at>
    <rate>{$conversion_rate}</rate>
    <from>
        <code>{$from_country['code']}</code>
        <curr>{$from_country['curr']}</curr>
        <loc>{$from_country['loc']}</loc>
        <amnt>{$parameters['amnt']}</amnt>
    </from>
    <to>
        <code>{$to_country['code']}</code>
        <curr>{$to_country['curr']}</curr>
        <loc>{$to_country['loc']}</loc>
        <amnt>{$converted_amt}</amnt> 
    </to>
</conv>
EOT;

    header('Content-type: text/xml');
    echo $response;


}

/**Generates an error message of appropriate type and terminates the script.
 * If the format specified is not json will always return xml
 * @param string $err_code code of error to be output to client
 * @param string $format format of the error message to be produced
 * @param int $exit_status exit code of script
 */
function send_error($err_code, $format, $exit_status)
{
    if($format == 'json')
    {
        // send json
        $err_msg = error_handler\get_error_msg($err_code, $format);
        header('Content-type: text/json');
        echo $err_msg;
        die($exit_status);
    }else{
        // anything else send xml error
        $err_msg = error_handler\get_error_msg($err_code, 'xml');
        header('Content-type: text/xml');
        echo $err_msg;
        die($exit_status);
    }
}
