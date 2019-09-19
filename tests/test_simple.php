<?php

use XDApp\ServiceReg\Service;

if (is_dir(__DIR__ .'/../vendor/')) {
    $dir = __DIR__ .'/../vendor/';
}
else if (is_dir(__DIR__ .'/../../vendor/')) {
    $dir = __DIR__ .'/../../vendor/';

}
include $dir .'autoload.php';

$service = Service::factory('demo', 'test', '123456');

$service->addWebFunction(function($name = ''){
    return "hi: $name";
}, 'hello');

echo "All functions: ", implode(', ', $service->getNames()), "\n";

$service->connectToLocalDev('127.0.0.1', 8861);