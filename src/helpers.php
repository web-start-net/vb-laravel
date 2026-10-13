<?php

declare(strict_types=1);

namespace {
    use VbLaravel\Bridge;

    if (!function_exists('vb')) {
        function vb(): Bridge
        {
            static $bridge;

            return $bridge ??= new Bridge();
        }
    }
}

namespace VbLaravel {
    use Illuminate\Support\Collection;

    if (!function_exists('VbLaravel\app')) {
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

    if (!function_exists('VbLaravel\collect')) {
        function collect(mixed $value = null): Collection
        {
            return new Collection($value);
        }
    }

    if (!function_exists('VbLaravel\db')) {
        function db(?string $table = null): mixed
        {
            $capsule = Runtime::bootDatabase();

            if ($table !== null) {
                return $capsule->table($table);
            }

            return $capsule->getDatabaseManager();
        }
    }
}
