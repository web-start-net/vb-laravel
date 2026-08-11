<?php

declare(strict_types=1);

namespace VbLaravel;

use InvalidArgumentException;
use RuntimeException;

final class AddonLoader
{
    private static array $loaded = [];

    public static function load(string $path): void
    {
        $path = realpath($path) ?: rtrim($path, '/\\');

        if (isset(self::$loaded[$path])) {
            return;
        }

        if (!is_dir($path)) {
            throw new InvalidArgumentException("Add-on directory not found: {$path}");
        }

        $manifestPath = $path . '/vb-addon.json';
        if (!is_file($manifestPath)) {
            throw new InvalidArgumentException("Add-on manifest not found: {$manifestPath}");
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            throw new InvalidArgumentException("Invalid add-on manifest: {$manifestPath}");
        }

        foreach (['id', 'title', 'version', 'runtime', 'provider'] as $field) {
            if (!isset($manifest[$field]) || !is_string($manifest[$field]) || $manifest[$field] === '') {
                throw new InvalidArgumentException("Missing add-on manifest field: {$field}");
            }
        }

        if (!self::runtimeMatches($manifest['runtime'])) {
            throw new RuntimeException("Add-on {$manifest['id']} is incompatible with runtime " . Runtime::VERSION);
        }

        $provider = $manifest['provider'];
        $providerFile = $path . '/src/' . basename(str_replace('\\', '/', $provider)) . '.php';
        if (!is_file($providerFile)) {
            throw new InvalidArgumentException("Add-on provider file not found: {$providerFile}");
        }

        Runtime::boot();
        require_once $providerFile;
        if (!class_exists($provider)) {
            throw new InvalidArgumentException("Add-on provider class not found: {$provider}");
        }
        $instance = new $provider(Runtime::container());
        if (!method_exists($instance, 'register') || !method_exists($instance, 'boot')) {
            throw new InvalidArgumentException("Add-on provider must define register() and boot(): {$provider}");
        }
        $instance->register();
        $instance->boot();
        self::$loaded[$path] = true;
    }

    private static function runtimeMatches(string $constraint): bool
    {
        if (!preg_match('/^\^(\d+)\.(\d+)$/', trim($constraint), $matches)) {
            return false;
        }

        [$major, $minor] = array_map('intval', explode('.', Runtime::VERSION, 2));
        return $major === (int) $matches[1] && $minor === (int) $matches[2];
    }
}
