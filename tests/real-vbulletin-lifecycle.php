<?php

declare(strict_types=1);

$vbulletinCore = 'C:/Users/MeisaM/Documents/vbulletin/source/core';

define('CWD', $vbulletinCore);
define('DIR', $vbulletinCore);
define('VB_AREA', 'FrontEnd');
define('THIS_SCRIPT', 'vb-laravel-lifecycle-test');
define('DISABLE_HOOKS', true);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['HTTPS'] = '';
$_SERVER['QUERY_STRING'] = '';

require_once $vbulletinCore . '/vb/vb.php';
vB::init();
vB::setConfigFile('../../../ChatGPT/vb-laravel/tests/vb-config.php');

require_once dirname($vbulletinCore) . '/includes/api/interfaceabstract.php';
require_once dirname($vbulletinCore) . '/includes/api/interface/collapsed.php';
require_once dirname($vbulletinCore) . '/includes/vb5/config.php';

vB::setRequest(new vB_Request_Web());

$package = dirname(__DIR__) . '/package/core/packages/vblaravel';
require_once $package . '/bootstrap.php';

if (!function_exists('vb')) {
    throw new RuntimeException('vb() was not registered by the package bootstrap.');
}

if (VbLaravel\Runtime::state() !== VbLaravel\Runtime::UNLOADED) {
    throw new RuntimeException('Runtime was not unloaded after package bootstrap.');
}

echo "Real vBulletin bootstrap and package bootstrap loaded.\n";
echo "Attempting native request API through vb(); this requires the reference database.\n";
$request = vb()->request();
if (!is_array($request) || !isset($request['timeNow'])) {
    throw new RuntimeException('Native vBulletin request API did not return request data.');
}

if (VbLaravel\Runtime::state() !== VbLaravel\Runtime::UNLOADED) {
    throw new RuntimeException('Native vBulletin API call booted Illuminate unexpectedly.');
}

$container = VbLaravel\Runtime::boot();
if (!$container instanceof Illuminate\Container\Container) {
    throw new RuntimeException('Private Illuminate Container was not loaded.');
}

$requestAfterBoot = vb()->request();
if (!is_array($requestAfterBoot) || !isset($requestAfterBoot['timeNow'])) {
    throw new RuntimeException('Native vBulletin request API failed after Illuminate boot.');
}

echo "Real vBulletin lifecycle test passed.\n";
echo 'Runtime before boot: unloaded' . PHP_EOL;
echo 'Runtime after boot: ' . VbLaravel\Runtime::state() . PHP_EOL;
echo "Native request API worked after Illuminate boot.\n";
