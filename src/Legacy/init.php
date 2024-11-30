<?php
if (defined('IN_SITE'))
    return;

define('IN_SITE', true);

chdir (dirname(__DIR__) . '/..');
set_include_path ( dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' );

ini_set('display_errors', true);

set_error_handler(function($severity, $message, $file, $line, $vars=null) {
    if (error_reporting() & $severity)
        throw new ErrorException($message, 0, $severity, $file, $line);
});

if (isset($_SERVER['HTTP_HOST']) && preg_match('/^(www\.)?svcover\.nl$/', $_SERVER['HTTP_HOST']))
    error_reporting(E_ALL ^ E_NOTICE ^ E_USER_NOTICE ^ E_DEPRECATED ^ E_STRICT);
else
    error_reporting(E_ALL);

require_once 'src/Legacy/proxies.php';
require_once 'src/Legacy/constants.php';
require_once 'src/Legacy/member.php';
require_once 'src/Legacy/functions.php';
require_once 'src/Legacy/auth.php';
require_once 'src/Legacy/i18n.php';
require_once 'src/Legacy/markup.php';
require_once 'src/Legacy/email.php';

/* Initialize session */
// TODO SFY can we do without starting the session?
// https://symfony.com/doc/current/session.html#integrating-with-legacy-applications
// session_start();

init_i18n();
