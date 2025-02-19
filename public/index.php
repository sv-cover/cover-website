<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Cover >>>>>>>>>>>>>>>
date_default_timezone_set('Europe/Amsterdam');
// <<<<<<<<<<<<<<< Cover

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
