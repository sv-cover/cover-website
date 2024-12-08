<?php

namespace App\Legacy;

global $kernel;

function init($k): void
{
    global $kernel;
    $kernel = $k;

    require_once 'src/Legacy/init.php';
}
