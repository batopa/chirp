<?php

require_once 'vendor/autoload.php';

error_reporting(E_ALL);

if (!defined('OAUTH_ACCESS_TOKEN')) {
    define('OAUTH_ACCESS_TOKEN', '');
}
if (!defined('OAUTH_ACCESS_TOKEN_SECRET')) {
    define('OAUTH_ACCESS_TOKEN_SECRET', '');
}
if (!defined('CONSUMER_KEY')) {
    define('CONSUMER_KEY', '');
}
if (!defined('CONSUMER_SECRET')) {
    define('CONSUMER_SECRET', '');
}
