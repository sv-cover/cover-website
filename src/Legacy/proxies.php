<?php

use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

function get_auth() {
    global $kernel;

    if (!isset($kernel))
        return null;

    return $kernel->getContainer()->get('App\Service\Authentication')->getAuth();
}

function get_identity() {
    global $kernel;

    if (!isset($kernel))
        return null;

    return $kernel->getContainer()->get('App\Service\Authentication')->getIdentity();
}

function get_model(string $name) {
    global $kernel;

    if (!isset($kernel))
        return null;

    return $kernel->getContainer()->get('App\Service\Database')->getModel($name);
}

function get_secretary() {
    global $kernel;

    if (!isset($kernel))
        return null;

    return $kernel->getContainer()->get('App\Service\Secretary');
}

function get_policy($name) {
    global $kernel;

    if (!isset($kernel))
        return null;

    return $kernel->getContainer()->get('App\Service\Policy')->get($name);
}

function get_config_value(string $name, mixed $default = null) {
    global $kernel;

    try {
        return $kernel->getContainer()->getParameterBag()->get($name);
    } catch (ParameterNotFoundException $e) {
        return $default;
    }
}
