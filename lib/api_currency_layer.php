<?php
/**
 * Created by PhpStorm.
 * User: student
 * Date: 10/25/18
 * Time: 4:08 PM
 */

namespace currency_api\currency_layer;

require_once __DIR__ . '/config.php';


use currency_api;

/**
 * Sends a query to the Currency Layer API with a get request and returns the result as a json formatted string
 *
 * @author Robert Fry
 *
 * @param string $iso4217_codes csv string of all currencies to lookup, not urlencoded
 * @return string containing json data
 * @throws \Exception cURL error
 */
function request_exchange_rates_for_currencies($iso4217_codes)
{
    $query_url = CURRENCYLAYER_URL . '?access_key=' . CURRENCYLAYER_API_KEY . '&currencies=' . $iso4217_codes;

    $request = curl_init($query_url);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($request);

    if ($response == false)
    {
        $error_message = 'REST currency error: ' . curl_error($request);
        throw new \Exception($error_message);
    }

    return $response;
}

/**Requests the currency info for the provided currencies and parses the data into an array to be returned.
 * @param string $iso4217_codes csv string of all currencies to lookup, not urlencoded
 * @return array containing data parsed from the json
 * @throws \Exception
 */
function get_currency_info($iso4217_codes)
{
    $data = json_decode(request_exchange_rates_for_currencies($iso4217_codes));

    // debug line to mock response
    //$data = json_decode("{\"success\":true,\"terms\":\"https:\/\/currencylayer.com\/terms\",\"privacy\":\"https:\/\/currencylayer.com\/privacy\",\"timestamp\":1540809546,\"source\":\"USD\",\"quotes\":{\"USDGBP\":0.778978,\"USDEUR\":0.876445}}");


    if($data->success == false)
    {
        throw new \Exception('CurrencyAPI failed to return data due to bad query');
    }
    $currency_data = array();
    foreach ($data->quotes as $quote => $value)
    {
        $currency_data += [substr($quote, 3) => $value];
    }
    return $currency_data;
}