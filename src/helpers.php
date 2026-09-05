<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use VbLaravel\Bridge;
use VbLaravel\Runtime;

if (!function_exists('vb')) {
    function vb(): Bridge
    {
        static $bridge;

        return $bridge ??= new Bridge();
    }
}

if (!function_exists('app')) {
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        if (!Runtime::isBooted()) {
            Runtime::boot();
        }

        $container = Runtime::container();

        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract, $parameters);
    }
}

if (!function_exists('collect')) {
    function collect(mixed $value = null): Collection
    {
        return new Collection($value);
    }
}

if (!function_exists('db')) {
    function db(?string $table = null): mixed
    {
        $capsule = Runtime::bootDatabase();

        if ($table !== null) {
            return $capsule->table($table);
        }

        return $capsule->getDatabaseManager();
    }
}
