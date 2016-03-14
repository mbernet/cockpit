<?php

define('OPENWEATHER_API_KEY', '91313229aedf333d6446e32571a7aab0');

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

    var_dump($_REQUEST);

    $query = array(
        'lat'   =>  '35',
        'lon'   =>  '139',
        'appid' => OPENWEATHER_API_KEY
    );

    $qString = http_build_query($query);
    $url = 'http://api.openweathermap.org/data/2.5/weather'.$qString;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $output = curl_exec($ch);

    curl_close($ch);

    echo $output;
    exit();
});



$app->on("before", function() {

    $routes = new \ArrayObject([]);

    /*
        $routes['{:resource}'] = string (classname) | callable
    */

    $this->trigger("cockpit.rest.init", [$routes])->bind("/weather/*", function($params) use($routes){

        $route = $this['route'];
        $token = $this->param("token", false);
        $path  = $params[":splat"][0];

        if (!$token || !$params[":splat"][0]) {
            return false;
        }

        $tokens = $this->db->getKey("cockpit/settings", "cockpit.api.tokens", []);

        if (!isset($tokens[$token])) {
            $this->response->status = 401;
            return ["error" => "access denied"];
        }


        // rules validation
        $rules = trim(preg_replace('/#(.+)/', '', $tokens[$token])); // trim and replace comments
        $pass  = false;

        if ($rules == '') {
            $pass = true;
        } else {

            $lines = explode("\n", $rules);

            // validate every rule
            foreach ($lines as $rule) {

                $rule = trim($rule);

                if (!$rule) continue;

                $ret  = $rule[0] == '!' ? false : true;

                if (!$ret) {
                    $rule = substr($rule, 1);
                }

                if (preg_match("#{$rule}#", $route)) {
                    $pass = $ret;
                    break;
                }
            }
        }

        // deny access
        if (!$pass) {
            $this->response->status = 401;
            return ["error" => "access denied"];
        }

        $parts      = explode('/', $params[":splat"][0], 2);
        $resource   = $parts[0];
        $params     = isset($parts[1]) ? explode('/', $parts[1]) : [];

        /*

        if (isset($routes[$resource])) {

            // invoke class
            if (is_string($routes[$resource])) {

                $action = count($params) ? array_shift($params):'index';

                return $this->invoke($routes[$resource], $action, $params);
            }

            if (is_callable($routes[$resource])) {
                return call_user_func_array($routes[$resource], $params);
            }
        }
        */
        echo "OK";

        return false;
    });

});