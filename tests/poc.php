<?php

declare(strict_types=1);

require_once __DIR__ . '/../package/core/packages/vblaravel/bootstrap.php';

use function VbLaravel\app;
use function VbLaravel\collect;
use function VbLaravel\db;

final class TestsApi
{
    public function callApi(string $controller, string $method, array $arguments): array
    {
        if ($controller === 'user' && $method === 'fetchCurrentUserinfo') {
            return [
                'userid' => 42,
                'username' => 'TestUser',
                'usergroupid' => 2,
                'membergroupids' => '6,7',
            ];
        }
        return compact('controller', 'method', 'arguments');
    }
}

abstract class Api_InterfaceAbstract
{
    public static function instance(): TestsApi
    {
        static $api;
        return $api ??= new TestsApi();
    }
}

abstract class vB_Api
{
    public static function instance(string $controller): array
    {
        return ['controller' => $controller];
    }
}

// Global vs Namespaced function checks
assert(function_exists('vb'));
assert(!function_exists('app'));
assert(!function_exists('collect'));
assert(!function_exists('db'));
assert(function_exists('VbLaravel\app'));
assert(function_exists('VbLaravel\collect'));
assert(function_exists('VbLaravel\db'));

// Bridge tests
assert(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);
assert(vb()->userId() === 42);
assert(vb()->isLoggedIn() === true);
assert(vb()->inGroup(2) === true);
assert(vb()->inGroup(6) === true);
assert(vb()->inGroup([7, 99]) === true);
assert(vb()->inGroup(99) === false);
assert(vb()->can('any_permission') === false); // guarded when no user context
assert(vb()->request()['method'] === 'getRequestInfo');
assert(vb()->phrase('hello')['arguments'] === ['hello']);
assert(vb()->node(7)['arguments'] === [7]);
assert(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);

// Boot tests
$first = VbLaravel\Runtime::boot(dirname(__DIR__));
$second = VbLaravel\Runtime::boot(dirname(__DIR__));

assert($first === $second);
assert($first instanceof Illuminate\Container\Container);
assert(VbLaravel\Runtime::state() === VbLaravel\Runtime::BOOTED);
assert(VbLaravel\Runtime::VERSION === '0.2.0');

$col = collect([1, 2, 3])->map(fn($n) => $n * 2);
assert($col->all() === [2, 4, 6]);

assert(app() instanceof Illuminate\Container\Container);

// Core Read-Only Models Test
$user = new VbLaravel\Models\User();
try {
    $user->save();
    assert(false, 'Should throw BadMethodCallException on save');
} catch (BadMethodCallException $e) {
    assert(str_contains($e->getMessage(), 'read-only core vBulletin model'));
}

echo "VB Laravel 0.2.0 PoC passed.\n";
