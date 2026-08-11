<?php

declare(strict_types=1);

require_once __DIR__ . '/../package/core/packages/vblaravel/bootstrap.php';

final class TestsApi
{
    public function callApi(string $controller, string $method, array $arguments): array
    {
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

assert(function_exists('vb'));
assert(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);
assert(vb()->user()['method'] === 'fetchCurrentUserinfo');
assert(vb()->request()['method'] === 'getRequestInfo');
assert(vb()->phrase('hello')['arguments'] === ['hello']);
assert(vb()->node(7)['arguments'] === [7]);
assert(VbLaravel\Runtime::state() === VbLaravel\Runtime::UNLOADED);

$first = VbLaravel\Runtime::boot(dirname(__DIR__));
$second = VbLaravel\Runtime::boot(dirname(__DIR__));

assert($first === $second);
assert($first instanceof Illuminate\Container\Container);
assert(VbLaravel\Runtime::state() === VbLaravel\Runtime::BOOTED);

echo "VB Laravel PoC passed.\n";
