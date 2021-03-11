<?php
namespace  currency_api\rest_countries;
require_once __DIR__ . '/config.php';

//use currency_api;
use \Exception as Exception;
/**
 * Sends a query to the REST Countries API with a get request and returns the result as a json formatted string
 *
 * @author Robert Fry
 *
 * @param string $iso4217_code contains a iso4217 compliant currency code
 * @return string containing json response
 * @throws Exception cURL error
 */
function request_countries_using_currency($iso4217_code)
{
    $query_url = RESTCOUNTRIES_URL . "/currency/" . urlencode($iso4217_code);

    $request = curl_init($query_url);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($request);

    if ($response == false)
    {
        $error_message = 'REST Countries error: ' . curl_error($request);
        throw new Exception($error_message);
    }

    return $response;
}

/**Requests countries using currency and parses data into an array stripping out
 * unnecessary information and ascertaining the correct currency name by looping though
 * those entries until it finds an entry with the input currency code and uses the
 * corresponding value as the currency name.
 * @param string $iso4217_code contains a iso4217 compliant currency code
 * @return array containing parsed json data
 * @throws Exception
 */
function get_currency_info($iso4217_code)
{
    $json = request_countries_using_currency($iso4217_code);
    $json = json_decode($json);

    if(isset($json->status) && $json->status == 404)
    {
        throw new Exception('404: currency info could not be found,');
    }
    //var_dump($json);
    $countries = array();
    $currency_name = '';

    foreach ($json as $object)
    {
        $countries[] = $object->name;

        if($currency_name == '')
        {
            foreach ($object->currencies as $currency_name_info) {
                if(strcasecmp($currency_name_info->code, $iso4217_code) == 0)
                {
                    $currency_name = $currency_name_info->name;
                }
            }
        }
    }

    return [
        'currency_name' => $currency_name,
        'countries'=> $countries];
}
