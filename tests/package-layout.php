<?php

declare(strict_types=1);

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
                return $group === 'genericpermissions' && $permission === 'can_use_bookmarks';
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
$manifest = json_decode((string) file_get_contents(dirname(__DIR__) . '/addon/vblaraveltest/vb-addon.json'), true, 512, JSON_THROW_ON_ERROR);
assert(array_keys($manifest) === ['id', 'title', 'version', 'runtime', 'provider']);
assert($manifest['runtime'] === '^0.1');
require_once $package . '/bootstrap.php';

assert(function_exists('vb'));
assert(class_exists(VbLaravel\AddonLoader::class, false));
assert(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);
assert(class_exists('Illuminate\\Container\\Container', false) === false);
assert(vb()->user()['method'] === 'fetchCurrentUserinfo');
assert(vb()->can('can_use_bookmarks') === true);
assert(vb()->url('vbbookmarks', ['nodeid' => 2]) === 'vbbookmarks:nodeid=2');
assert(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);
assert(class_exists('Illuminate\\Container\\Container', false) === false);

try {
    VbLaravel\Runtime::container();
    assert(false);
} catch (RuntimeException $exception) {
    assert(str_contains($exception->getMessage(), 'Runtime::boot()'));
}

assert(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);

$first = VbLaravel\Runtime::boot();
$second = VbLaravel\Runtime::boot();

assert($first === $second);
assert($first instanceof Illuminate\Container\Container);
assert(VbLaravel\Runtime::state() === VbLaravel\Runtime::BOOTED);
assert(VbLaravel\Runtime::container() === $first);

echo "Standalone package layout test passed.\n";
