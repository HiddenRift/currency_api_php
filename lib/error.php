<?php
/**
 * Created by PhpStorm.
 * User: student
 * Date: 10/31/18
 * Time: 3:28 PM
 */
namespace currency_api\error_handler;

include_once __DIR__  . '/config.php';

function get_error_msg($err_code, $format, $method = 'GET')
{
    global $ERROR_CODES;

    if($method == 'GET') {
        $error = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<conv>
    <error>
        <code>{$err_code}</code>
        <msg>{$ERROR_CODES[$err_code]}</msg>
    </error>
</conv>
EOT;
        if (strcasecmp($format, 'json') == 0) {
            //$xml = simplexml_load_string($error);
            $json = json_encode(simplexml_load_string($error));
            //now append root node
            $json = '{"conv":' . $json . '}';
            return $json;
        } else {
            //return xml
            return $error;
        }
    } else
    {
        // errors where method specified always  xml

        $error = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<method type="{$method}">
    <error>
        <code>{$err_code}</code>
        <msg>{$ERROR_CODES[$err_code]}</msg>
    </error>
</method>
EOT;
        return $error;
    }
}

