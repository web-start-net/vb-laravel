<?php

declare(strict_types=1);

ini_set('zend.assertions', '1');
ini_set('assert.active', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use VbLaravel\Runtime;

function check(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new RuntimeException("Test assertion failed: " . ($message ?: 'Condition was false'));
    }
}

class TestDummyProvider
{
    public static bool $registered = false;
    public static bool $booted = false;

    public function __construct(private $container)
    {
    }

    public function register(): void
    {
        self::$registered = true;
    }

    public function boot(): void
    {
        self::$booted = true;
    }
}

echo "--- Testing Provider Lifecycle ---\n";

Runtime::reset();
echo "State after reset: " . Runtime::state() . "\n";
check(Runtime::state() === Runtime::UNLOADED, "Expected state unloaded");

// 1. Register provider before boot
Runtime::registerProvider(TestDummyProvider::class);

// State must still be UNLOADED!
check(Runtime::state() === Runtime::UNLOADED, "registerProvider should not boot runtime");
check(!TestDummyProvider::$registered && !TestDummyProvider::$booted, "Provider should not be registered/booted before Runtime::boot()");

// 2. Boot runtime
Runtime::boot();

check(Runtime::state() === Runtime::BOOTED, "Expected state booted");
check(TestDummyProvider::$registered && TestDummyProvider::$booted, "Provider register/boot were not called on Runtime::boot()");

echo "✓ Provider was lazily booted upon Runtime::boot()!\n";
echo "✓ Provider Lifecycle Test PASSED!\n";
exit(0);
