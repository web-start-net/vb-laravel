<?php

declare(strict_types=1);

ini_set('zend.assertions', '1');
ini_set('assert.active', '1');

function check(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new RuntimeException("Test assertion failed: " . ($message ?: 'Condition was false'));
    }
}

final class PackageTestsApi
{
    public function callApi(string $controller, string $method, array $arguments): array
    {
        return compact('controller', 'method', 'arguments');
    }
}

abstract class Api_InterfaceAbstract
{
    public static function instance(): PackageTestsApi
    {
        static $api;
        return $api ??= new PackageTestsApi();
    }
}

abstract class vB_Api
{
    public static function instance(string $controller): array
    {
        return ['controller' => $controller];
    }
}

abstract class vB
{
    public static function getUserContext(): object
    {
        return new class {
            public function hasPermission(string $group, string $permission): bool
            {
                return $group === 'genericpermissions' && $permission === 'can_use_runtime';
            }
        };
    }
}

abstract class vB5_Route
{
    public static function buildUrl(string $route, array $data = [], array $extra = []): string
    {
        return $route . ':' . http_build_query($data + $extra);
    }
}

$package = dirname(__DIR__) . '/package/core/packages/vblaravel';
$manifest = [
    'id' => 'Vendor/AddOn',
    'title' => 'Example Add-on',
    'version' => '1.0.0',
    'runtime' => '^0.1',
    'provider' => 'Vendor\\AddOn\\Provider',
];
check(array_keys($manifest) === ['id', 'title', 'version', 'runtime', 'provider']);
check($manifest['runtime'] === '^0.1');
require_once $package . '/bootstrap.php';

check(function_exists('vb'));
check(class_exists(VbLaravel\AddonLoader::class, false));
check(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);
check(class_exists('Illuminate\\Container\\Container', false) === false);
check(vb()->user()['method'] === 'fetchCurrentUserinfo');
check(vb()->can('can_use_runtime') === true);
check(vb()->url('runtime_example', ['nodeid' => 2]) === 'runtime_example:nodeid=2');
check(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);
check(class_exists('Illuminate\\Container\\Container', false) === false);

try {
    VbLaravel\Runtime::container();
    check(false);
} catch (RuntimeException $exception) {
    check(str_contains($exception->getMessage(), 'Runtime::boot()'));
}

check(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);

$first = VbLaravel\Runtime::boot();
$second = VbLaravel\Runtime::boot();

check($first === $second);
check($first instanceof Illuminate\Container\Container);
check(VbLaravel\Runtime::state() === VbLaravel\Runtime::BOOTED);
check(VbLaravel\Runtime::container() === $first);

echo "Standalone package layout test passed.\n";
