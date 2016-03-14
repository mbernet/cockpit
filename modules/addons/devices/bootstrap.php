<?php

$app->bind("/devices/iphone", function($params) use ($routes) {
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

});

$app->bind("/devices/android", function($params) use ($routes) {
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
});

if (COCKPIT_ADMIN && !COCKPIT_REST) include_once(__DIR__.'/admin.php');
