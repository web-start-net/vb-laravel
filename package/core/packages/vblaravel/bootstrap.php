<?php

declare(strict_types=1);

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Bridge.php';
require_once __DIR__ . '/src/Runtime.php';
require_once __DIR__ . '/src/AddonLoader.php';
require_once __DIR__ . '/src/Attributes/Hook.php';
require_once __DIR__ . '/src/Attributes/Api.php';
require_once __DIR__ . '/src/Attributes/Can.php';

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'VbLaravel\\Models\\')) {
        $file = __DIR__ . '/src/Models/' . substr($class, strlen('VbLaravel\\Models\\')) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }

    if (
        str_starts_with($class, 'Illuminate\\')
        || str_starts_with($class, 'Carbon\\')
        || $class === 'VbLaravel\\Migrator'
    ) {
        if (!\VbLaravel\Runtime::isBooted()) {
            \VbLaravel\Runtime::boot(__DIR__, $class);
        }
        if ($class === 'VbLaravel\\Migrator') {
            $file = __DIR__ . '/src/Migrator.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    }
});
