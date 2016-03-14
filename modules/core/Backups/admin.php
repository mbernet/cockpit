<?php

//var_dump($app);

$app->bind("/weather", function() {
    return "Hello World!";
});
