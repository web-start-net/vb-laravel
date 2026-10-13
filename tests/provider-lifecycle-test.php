<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use VbLaravel\Runtime;

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
if (Runtime::state() !== Runtime::UNLOADED) {
    echo "✗ FAILED: Expected state unloaded\n";
    exit(1);
}

// 1. Register provider before boot
Runtime::registerProvider(TestDummyProvider::class);

// State must still be UNLOADED!
if (Runtime::state() !== Runtime::UNLOADED) {
    echo "✗ FAILED: registerProvider should not boot runtime\n";
    exit(1);
}

if (TestDummyProvider::$registered || TestDummyProvider::$booted) {
    echo "✗ FAILED: Provider should not be registered/booted before Runtime::boot()\n";
    exit(1);
}

// 2. Boot runtime
Runtime::boot();

if (Runtime::state() !== Runtime::BOOTED) {
    echo "✗ FAILED: Expected state booted\n";
    exit(1);
}

if (!TestDummyProvider::$registered || !TestDummyProvider::$booted) {
    echo "✗ FAILED: Provider register/boot were not called on Runtime::boot()\n";
    exit(1);
}

echo "✓ Provider was lazily booted upon Runtime::boot()!\n";
echo "✓ Provider Lifecycle Test PASSED!\n";
exit(0);
