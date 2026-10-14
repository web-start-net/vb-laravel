<?php

declare(strict_types=1);

ini_set('zend.assertions', '1');
ini_set('assert.active', '1');

require_once __DIR__ . '/../package/core/packages/vblaravel/bootstrap.php';

use function VbLaravel\app;
use function VbLaravel\collect;
use function VbLaravel\db;

function check(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new RuntimeException("Test assertion failed: " . ($message ?: 'Condition was false'));
    }
}

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
check(function_exists('vb'));
check(!function_exists('app'));
check(!function_exists('collect'));
check(!function_exists('db'));
check(function_exists('VbLaravel\app'));
check(function_exists('VbLaravel\collect'));
check(function_exists('VbLaravel\db'));

// Bridge tests
check(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);
check(vb()->userId() === 42);
check(vb()->isLoggedIn() === true);
check(vb()->inGroup(2) === true);
check(vb()->inGroup(6) === true);
check(vb()->inGroup([7, 99]) === true);
check(vb()->inGroup(99) === false);
check(vb()->can('any_permission') === false); // guarded when no user context
check(vb()->request()['method'] === 'getRequestInfo');
check(vb()->phrase('hello')['arguments'] === ['hello']);
check(vb()->node(7)['arguments'] === [7]);
check(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);

// Boot tests
$first = VbLaravel\Runtime::boot(dirname(__DIR__));
$second = VbLaravel\Runtime::boot(dirname(__DIR__));

check($first === $second);
check($first instanceof Illuminate\Container\Container);
check(VbLaravel\Runtime::state() === VbLaravel\Runtime::BOOTED);
check(VbLaravel\Runtime::VERSION === '0.3.0');

$col = collect([1, 2, 3])->map(fn($n) => $n * 2);
check($col->all() === [2, 4, 6]);

check(app() instanceof Illuminate\Container\Container);

// Core Read-Only Models Test
$user = new VbLaravel\Models\User();
try {
    $user->save();
    check(false, 'Should throw BadMethodCallException on save');
} catch (BadMethodCallException $e) {
    check(str_contains($e->getMessage(), 'read-only core vBulletin model'));
}

echo "VB Laravel PoC passed.\n";
