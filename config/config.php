<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'JLD Apartment Management');
define('APP_VERSION', '1.0.0');
define('CURRENCY_SYMBOL', '₱');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';

$projectDir = str_replace('\\', '/', dirname(__DIR__));
$docRoot = str_replace('\\', '/', isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');

$subDir = '';
if (!empty($docRoot) && strpos($projectDir, $docRoot) === 0) {
    $subDir = substr($projectDir, strlen($docRoot));
}
$subDir = trim($subDir, '/');
$baseUrl = $protocol . $host . ($subDir ? '/' . $subDir : '') . '/';

define('BASE_URL', $baseUrl);

define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('UPLOADS_PATH', ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/helpers.php';
