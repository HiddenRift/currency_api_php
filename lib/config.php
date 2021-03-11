<?php
namespace currency_api;

@date_default_timezone_set("GMT");

define('CURRENCYLAYER_API_KEY', '');
define('CURRENCYLAYER_URL', 'http://apilayer.net/api/live');
define('RESTCOUNTRIES_URL', 'https://restcountries.eu/rest/v2');
define('XML_STORAGE_FILE', __DIR__ . '/currency_data.xml');
define('DEFAULT_CURRENCIES', 'AUD,BRL,CAD,CHF,CNY,DKK,EUR,GBP,HKD,HUF,INR,JPY,MXN,MYR,NOK,NZD,PHP,RUB,SEK,SGD,THB,TRY,USD,ZAR');
// Hours between rates updates
define('RATES_UPDATE_INTERVAL', 11);

/*http://apilayer.net/api/live?access_key=597943e325e5a02a2c5c50f7a212b355&currencies=AUD,EUR,GBP,PLN */


/** @var array $ERROR_CODES Used in other files to determine error messages*/
$ERROR_CODES = array(
    1000 => 'Required parameter is missing',
    1100 => 'Parameter not recognized',
    1200 => 'Currency type not recognized',
    1300 => 'Currency amount must be a decimal number',
    1400 => 'Format must be xml or json',
    1500 => 'Error in service',
    2000 => 'Method not recognized or is missing',
    2100 => 'Rate in wrong format or is missing',
    2200 => 'Currency code in wrong format or is missing',
    2300 => 'Country name in wrong format or is missing',
    2400 => 'Currency code not found for update',
    2500 => 'Error in service'
);

/**
 * @param string $format used to set the headers for response in line with guideline to default to xml output
 */
function set_header($format)
{
    if(strcasecmp($format, 'json') == 0)
    {
        //return json
        header('Content-type: text/json');
    }else{
        //return xml
        header('Content-type: text/xml');
    }
}
