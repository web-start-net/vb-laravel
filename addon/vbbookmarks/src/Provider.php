<?php

declare(strict_types=1);

namespace VB\Bookmarks;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\ServiceProvider;
use ReflectionProperty;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $assertor = \vB::getDbAssertor();
        $property = new ReflectionProperty($assertor, 'dbconfig');
        $config = $property->getValue($assertor);
        $master = $config['MasterServer'] ?? [];
        $database = $config['Database'] ?? [];

        $capsule = new Manager($this->app);
        $capsule->addConnection([
            'driver' => 'mysql',
            'database' => $database['dbname'] ?? '',
            'host' => $master['servername'] ?? '127.0.0.1',
            'port' => $master['port'] ?? 3306,
            'username' => $master['username'] ?? '',
            'password' => $master['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => defined('TABLE_PREFIX') ? TABLE_PREFIX : '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->app->instance(Manager::class, $capsule);
    }
}
