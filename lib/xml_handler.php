<?php
namespace currency_api\xml_interface;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api_rest_country.php';
require_once __DIR__ . '/api_currency_layer.php';


use currency_api\currency_layer as currency_layer;
use currency_api\rest_countries as rest_countries;

/**Function used to generate xml file in case one is not found or is empty
 * Does this by accessing the currency info for each of the currencies specified in
 * DEFAULT_CURRENCIES. Loops through returned rates and adds a currency node to
 * the xml tree for the data. While looping will access the RestCountries API to
 * retrieve data about the currency
 * On completion attempts to save generated xml to disk, on failure of this raises exception
 *
 * @throws \Exception in case of api being inaccessible or
 * being unable to save the xml to disk. WARNING: this means that
 * running the api call will attempt to build the xml file anew each time if
 * and only if it is unable to save
 */
function setup_xml_file()
{
    $xml_base = <<<EOF
<?xml version="1.0"?>
<currencies></currencies>
EOF;

    $xml_dom = simplexml_load_string($xml_base);
    $currency_rates = currency_layer\get_currency_info(DEFAULT_CURRENCIES);

    //print_r(get_countries_using_currency_arr("SGD"));

    foreach ($currency_rates as $iso_code => $value) {
        $currency_info = rest_countries\get_currency_info($iso_code);

        $currNode = $xml_dom->addChild('currency');
        $currNode->addAttribute('iso4217_code', $iso_code);
        $currNode->addAttribute('deleted', 'false');
        $currNode->addChild('name', $currency_info['currency_name']);
        $currNode->addChild('rate', $value);
        $countries_str = '';
        foreach ($currency_info['countries'] as $country) {
            $countries_str .= "$country, ";
        }
        $countries_str = substr($countries_str, 0, -2);
        $currNode->addChild('countries', $countries_str);

    }

    save_xml_to_file($xml_dom);
}

/**Updates the rates stored in the xml file
 * Loads the xml file and builds a string of currencies stored by the system to update
 * whilst ignoring any with the deleted attribute set to true.
 * Passes this string to currency layer api if it contains any codes.
 * uses the returned rates to update the required nodes.
 *
 * @throws \Exception on failure to write new rates to file
 * WARNING: this means that running the api call will attempt to update
 * the xml file each time if it is unable to save, updating the file modified time
 */
function update_rates()
{
    $xml_dom = open_xml_file();
    $currencies_to_update = '';
    // build a csv string of currencies to update
    foreach ($xml_dom as $currency)
    {
        if($currency['deleted'] == 'false')
        {
            //get currency code and append
            $iso_code = $currency['iso4217_code'];
            $currencies_to_update .= "$iso_code,";
        }
    }
    if($currencies_to_update == '')
    {
        return;
    }

    $currency_rates = currency_layer\get_currency_info($currencies_to_update);

    foreach ($currency_rates as $iso_code => $currency_rate)
    {
        $currency = $xml_dom->xpath("currency[@iso4217_code = '$iso_code']");
        $currency[0]->rate = $currency_rate;
    }
    save_xml_to_file($xml_dom);
}

/**Checks the last time the file was updated and updates the rates if required
 * The update is deemed to be required if the interval between the current time and
 * the time the file was last updated exceeds the number of hours stored in RATES_UPDATE_INTERVAL
 * @throws \Exception in function update_rates() on critical failure
 */
function update_rates_if_required()
{
    $file_last_updated = filemtime(XML_STORAGE_FILE);
    // if file does not exist attempt creation
    if($file_last_updated === false)
    {
        setup_xml_file();
    }
    $current_time = time();

    //subtract update interval in hours
    $current_time = $current_time - ((60*60)*RATES_UPDATE_INTERVAL);

    if($current_time>$file_last_updated)
    {
        update_rates();
    }
}

/**Removes currency stored in the XML_STORAGE_FILE if it exists
 * This is only a soft delete as the node still exists but with its
 * deleted attribute set to true, making it inaccessible
 * @param string $iso4217_code contains a iso4217 compliant currency code
 * @return bool true on success and false if currency not on file
 * @throws \Exception
 */
function delete_currency($iso4217_code)
{
    $xml_dom = open_xml_file();
    $currency = $xml_dom->xpath("currency[@iso4217_code = '$iso4217_code' and @deleted = 'false']");
    if(isset($currency[0]))
    {
        if($currency[0]['deleted'] == 'false'){
            $currency[0]['deleted'] = 'true';
            save_xml_to_file($xml_dom);
            return true;
        }
    }
    return false;
}

/**Adds currency specified to the xml file so it is accessible by the api
 * First checks if the currency currently exists in the file, if so it will
 * make it accessible and update the value. Due to the api making no distinction
 * between updating one value versus many, all values are updated.
 * If the currency is not already in the file, checks with the rest countries api on
 * whether it exists returning an error if it does not. Otherwise creates the node and runs
 * an update to fetch the rate value.
 * @param string $iso4217_code contains a iso4217 compliant currency code
 * @return array|bool an array containing new currency info or bool false if currency not recognised.
 * @throws \Exception
 */
function add_currency($iso4217_code)
{
    $xml_dom = open_xml_file();
    // search for code in document

    $currency = $xml_dom->xpath("currency[@iso4217_code = '$iso4217_code']");
    if(isset($currency[0]))
    {
        if($currency[0]['deleted'] == 'true')
        {
            //currency logically deleted, reenable by setting to false and update;
            $currency[0]['deleted'] = 'false';
            save_xml_to_file($xml_dom);
            update_rates();
        }
    }else{
        // currency does not exist in file
        // check if it exists
        try {
            $currency_info = rest_countries\get_currency_info($iso4217_code);
        }catch(\Exception $ex)
        {
            return false;
        }
        //currency exists, append to file
        $currNode = $xml_dom->addChild('currency');
        $currNode->addAttribute('iso4217_code', $iso4217_code);
        $currNode->addAttribute('deleted', 'false');
        $currNode->addChild('name', $currency_info['currency_name']);
        // create default rate to be overridden by update
        $currNode->addChild('rate', 1);
        $countries_str = '';
        foreach ($currency_info['countries'] as $country) {
            $countries_str .= "$country, ";
        }
        $countries_str = substr($countries_str, 0, -2);
        $currNode->addChild('countries', $countries_str);

        save_xml_to_file($xml_dom);
        //save then update rates to fetch new value
        update_rates();
    }


    //reopen file and access new rates
    $xml_dom = simplexml_load_file(XML_STORAGE_FILE);
    $currency = $xml_dom->xpath("currency[@iso4217_code = '$iso4217_code']");
    $results = array(
        'rate' => (string)$currency[0]->rate,
        'code' => $iso4217_code,
        'name' => (string)$currency[0]->name,
        'loc' => (string)$currency[0]->countries
    );
    return $results;
}


/**Updates the given iso4217 codes entry in the xml file to a new rate passed to the function
 *
 * @param string $iso4217_code contains a iso4217 compliant currency code
 * @param string $rate contains a validated string containing a decimal number
 * @return array|bool an array containing the currency info including the old rate or bool false on failure
 * due to invalid iso code
 * @throws \Exception on being unable to save xml to file
 */
function manual_update_rate($iso4217_code, $rate)
{
    $xml_dom = open_xml_file();

    $currency = $xml_dom->xpath("currency[@iso4217_code = '$iso4217_code' and @deleted = 'false']");
    if(isset($currency[0]))
    {
        $results = array(
            'rate' => $rate,
            'old_rate' => "{$currency[0]->rate}",
            'code'=>$iso4217_code,
            'curr'=>(string)$currency[0]->name,
            'loc'=>(string)$currency[0]->countries,
        );
        $currency[0]->rate = $rate;
        save_xml_to_file($xml_dom);
        return $results;
    }
    return false;
}


/**Retrieves the currency data pertaining to the code if it exists in the xml file
 * updates the rates if required.
 * Executes an xPath query on the xml file, if it returns a result will return the first result in the
 * array. There should only be at maximum one instance of each currency in the array therefore this is not an issue.
 * @param string $iso4217_code contains a iso4217 compliant currency code
 * @return array|bool on success function returns an array with requested currency data
 * on fail returns false
 * @throws \Exception if updating rates is required and fails
 */
function get_currency_info($iso4217_code)
{
    update_rates_if_required();
    // file is checked in update rates if required
    $xml_dom = simplexml_load_file(XML_STORAGE_FILE);
    $currency = $xml_dom->xpath("currency[@iso4217_code = '$iso4217_code' and @deleted = 'false']");
    if(isset($currency[0]))
    {
        return [
            'code'=>$iso4217_code,
            'curr'=>(string)$currency[0]->name,
            'loc'=>(string)$currency[0]->countries,
            'rate'=>(string)$currency[0]->rate
        ];
    }
    return false;
}

/**Opens or if necessary creates the xml file fr the application
 * @return \SimpleXMLElement containing the loaded xml file
 * @throws \Exception on being unable to successfuly open or create the xml file
 */
function open_xml_file()
{
    $xml = @simplexml_load_file(XML_STORAGE_FILE);
    if($xml === false)
    {
        // ascertain why opening failed
        if(@file_exists(XML_STORAGE_FILE))
        {
            //file exists, check contents
            $size = @filesize(XML_STORAGE_FILE);
            if ($size === 0)
            {
                //file is empty test write
                $fh = @fopen(XML_STORAGE_FILE, 'w');
                if($fh == false)
                {
                    throw new \Exception("Error: XML_STORAGE_FILE could not be written to, please verify permissions");
                } else {
                    //
                    fclose($fh);
                    setup_xml_file();
                }

            }else{
                //file is corrupt / cannot be accessed
                throw new \Exception("Error: XML_STORAGE_FILE could not be accessed or may be corrupt");
            }
        }else{
            //file doesn't exist attempt creation
            $fh = fopen(XML_STORAGE_FILE, 'w');
            if($fh == false)
            {
                throw new \Exception("Error: XML_STORAGE_FILE could not be created");
            } else {
                // file able to be created close and generate
                fclose($fh);
                setup_xml_file();
            }
        }
        // xml should now be created so reload
        $xml = @simplexml_load_file(XML_STORAGE_FILE);
    }
    return $xml;
}

/**Checks whether xml written to file and raises exception in case it cannot be
 * @param $xml \SimpleXMLElement
 * @throws \Exception
 * @return void
 */
function save_xml_to_file($xml)
{
    $saved = @$xml->asXML(XML_STORAGE_FILE);
    if($saved == false)
    {
        throw new \Exception("Error: XML file could not be saved, check permissions");
    }
}
