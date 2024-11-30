<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

//> Old Cover stuff

require_once dirname(__DIR__).'/src/Legacy/init.php';

//< Old Cover stuff

// Make kernel globally available for legacy compatibility
global $kernel;

return function (array $context) {
    global $kernel;
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $request = Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
    // return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
