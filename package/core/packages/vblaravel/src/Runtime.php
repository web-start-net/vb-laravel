<?php

declare(strict_types=1);

namespace VbLaravel;

use Illuminate\Container\Container;
use RuntimeException;
use Throwable;

final class Runtime
{
    public const VERSION = '0.1.0';
    public const UNLOADED = 'unloaded';
    public const BOOTING = 'booting';
    public const BOOTED = 'booted';
    public const FAILED = 'failed';

    private static string $state = self::UNLOADED;
    private static ?Container $container = null;
    private static ?Throwable $failure = null;

    public static function state(): string
    {
        return self::$state;
    }

    public static function isBooted(): bool
    {
        return self::$state === self::BOOTED;
    }

    public static function boot(?string $vendorPath = null): Container
    {
        if (self::$state === self::BOOTED) {
            return self::$container;
        }

        if (self::$state === self::BOOTING) {
            throw new RuntimeException('VB Laravel Runtime boot is recursive.');
        }

        if (self::$state === self::FAILED) {
            throw new RuntimeException('VB Laravel Runtime boot previously failed.', 0, self::$failure);
        }

        self::$state = self::BOOTING;

        try {
            $vendorPath ??= __DIR__ . '/..';
            $autoload = rtrim($vendorPath, '/\\') . '/vendor/autoload.php';

            if (!is_file($autoload)) {
                throw new RuntimeException("Runtime Composer autoloader not found: {$autoload}");
            }

            require_once $autoload;
            self::$container = new Container();
            self::$state = self::BOOTED;

            return self::$container;
        } catch (Throwable $exception) {
            self::$failure = $exception;
            self::$state = self::FAILED;
            throw new RuntimeException('VB Laravel Runtime boot failed.', 0, $exception);
        }
    }

    public static function container(): Container
    {
        if (self::$state !== self::BOOTED || self::$container === null) {
            throw new RuntimeException('Call VbLaravel\\Runtime::boot() before requesting the container.');
        }

        return self::$container;
    }
}
