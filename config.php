<?php
declare(strict_types=1);

const DB_HOST = 'sql207.infinityfree.com';
const DB_NAME = 'if0_41876569_adote_certo';
const DB_USER = 'if0_41876569';
const DB_PASS = '3llen2010';

date_default_timezone_set('UTC');

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
