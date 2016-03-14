<?php

define('OPENWEATHER_API_KEY', '91313229aedf333d6446e32571a7aab0');
define('OPENWEATHER_IBIZA_ID', '2516479');

/*
 * Considerar ús de CACHE
*/

include_once(__DIR__.'/apc.caching.php');

$app->bind("/weather", function($params) use ($routes) {
    $token = $this->param("token", false);
    //echo $token;
    if (!$token) {
        return false;
    }

    $tokens = $this->db->getKey("cockpit/settings", "cockpit.api.tokens", []);

    if (!isset($tokens[$token])) {
        $this->response->status = 401;
        return ["error" => "access denied"];
    }

    $oCache = new CacheAPC();

    if($result = $oCache->getData('ibiza_weather')) {
        die('entra');
        return $result;
    } else {
        $query = array(
            'id'    => OPENWEATHER_IBIZA_ID,
            'appid' => OPENWEATHER_API_KEY
        );

        $qString = http_build_query($query);
        $url = 'http://api.openweathermap.org/data/2.5/weather?'.$qString;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);

        curl_close($ch);
        $oCache->setData('ibiza_weather', $output);
        return $output;
    }


});