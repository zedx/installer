<?php
/*
 * Set timezone
 */
date_default_timezone_set('Europe/Paris');

/*
 * Check PHP version
 */
if (version_compare(PHP_VERSION, '5.5.9', '<')) {
    exit('You need at least PHP 5.5.9 to install ZEDx CMS.');
}

/*
 * PHP headers
 */
if (isset($_REQUEST['handler'])) {
    header('Content-Type: text/event-stream');
}
header('Expires: Mon, 29 Dec 1985 17:55:00 GMT');
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

/*
 * Debug mode
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

/*
 * Constants
 */
define('PATH_INSTALL', str_replace('\\', '/', realpath(dirname(__FILE__).'/../../')));
define('ZEDx_GATEWAY', 'https://api.zedx.io');

/*
 * Address timeout limits
 */
@set_time_limit(3600);

/*
 * Prevent PCRE engine from crashing
 */
ini_set('pcre.recursion_limit', '524'); // 256KB stack. Win32 Apache

/*
 * Handle fatal errors with AJAX
 */
register_shutdown_function('installerShutdown');
function installerShutdown()
{
    global $installer;
    $error = error_get_last();
    if ($error['type'] == 1) {
        $errorMsg = htmlspecialchars_decode(strip_tags($error['message']));
        echo $errorMsg;
        if (isset($installer)) {
            $installer->log('Fatal error: %s on line %s in file %s', $errorMsg, $error['line'], $error['file']);
        }
        header($_SERVER['SERVER_PROTOCOL'].' 520 Internal Server Error', true, 520);
        exit;
    }
}

require_once 'helpers.php';
require_once 'InstallerException.php';
require_once 'Installer.php';

try {
    $installer = new Installer();
    $installer->cleanLog();
    //$installer->log('Host: %s', php_uname());
    $installer->log('PHP version: %s', PHP_VERSION);
    $installer->log('Server software: %s', isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown');
    $installer->log('Operating system: %s', PHP_OS);
    $installer->log('Memory limit: %s', ini_get('memory_limit'));
    $installer->log('Max execution time: %s', ini_get('max_execution_time'));
} catch (Exception $ex) {
    $fatalError = $ex->getMessage();
}
