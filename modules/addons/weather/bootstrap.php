<?php
$this->module("restservice")->extend([

    'js_lib' => function($token = null) use($app) {

        return $app->script($app->routeUrl("/rest/api-js?token={$token}"));
    }
]);