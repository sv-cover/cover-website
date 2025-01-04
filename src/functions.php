<?php

// namespace App; No namespace, make available globally

use App\Legacy\I18n;

if (!\function_exists(__::class)) {
    function __($message)
    {
        return I18n::translate($message);
    }
}

if (!\function_exists(__N::class)) {
    function __N($singular, $plural, $number)
    {
        return I18n::translatePluralize($singular, $plural, $number);
    }
}
