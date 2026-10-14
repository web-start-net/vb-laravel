<?php

declare(strict_types=1);

namespace VbLaravel;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use RuntimeException;
use Throwable;

final class Runtime
{
    public const VERSION = '0.3.0';
    public const UNLOADED = 'unloaded';
    public const BOOTING = 'booting';
    public const BOOTED = 'booted';
    public const FAILED = 'failed';

    private static string $state = self::UNLOADED;
    private static ?Container $container = null;
    private static ?Manager $database = null;
    private static ?Throwable $failure = null;

    /** @var array<string> */
    private static array $providers = [];
    /** @var array<string, object> */
    private static array $bootedProviders = [];

    public static function state(): string
    {
        return self::$state;
    }

    public static function isBooted(): bool
    {
        return self::$state === self::BOOTED;
    }

    public static function registerProvider(string $providerClass): void
    {
        if (!in_array($providerClass, self::$providers, true)) {
            self::$providers[] = $providerClass;
        }

        if (self::$state === self::BOOTED) {
            self::bootProvider($providerClass);
        }
    }

    private static function bootProvider(string $providerClass): void
    {
        if (isset(self::$bootedProviders[$providerClass])) {
            return;
        }

        if (!class_exists($providerClass)) {
            return;
        }

        $provider = new $providerClass(self::$container);
        if (method_exists($provider, 'register')) {
            $provider->register();
        }
        if (method_exists($provider, 'boot')) {
            $provider->boot();
        }

        self::$bootedProviders[$providerClass] = $provider;
    }

    public static function getBootedProviders(): array
    {
        return self::$bootedProviders;
    }

    public static function reset(): void
    {
        self::$state = self::UNLOADED;
        self::$container = null;
        self::$database = null;
        self::$failure = null;
        self::$providers = [];
        self::$bootedProviders = [];
    }

    public static function boot(?string $vendorPath = null, ?string $triggerClass = null): Container
    {
        if (PHP_VERSION_ID < 80200) {
            throw new RuntimeException('VB Laravel Runtime requires PHP 8.2 or greater.');
        }

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
            $basePath = $vendorPath ?? dirname(__DIR__);
            if (is_file(rtrim($basePath, '/\\') . '/vendor/autoload.php')) {
                $autoload = rtrim($basePath, '/\\') . '/vendor/autoload.php';
            } elseif (is_file(rtrim($basePath, '/\\') . '/autoload.php')) {
                $autoload = rtrim($basePath, '/\\') . '/autoload.php';
            } elseif (is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
                $autoload = dirname(__DIR__) . '/vendor/autoload.php';
            } elseif (is_file(dirname(__DIR__, 2) . '/vendor/autoload.php')) {
                $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            } else {
                $autoload = dirname(__DIR__) . '/vendor/autoload.php';
            }

            if (!is_file($autoload)) {
                throw new RuntimeException("Runtime Composer autoloader not found: {$autoload}");
            }

            $composerLoader = require $autoload;
            if ($triggerClass !== null) {
                if (is_object($composerLoader) && method_exists($composerLoader, 'loadClass')) {
                    $composerLoader->loadClass($triggerClass);
                } elseif (class_exists(\Composer\Autoload\ClassLoader::class)) {
                    foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
                        $loader->loadClass($triggerClass);
                    }
                }
            }

            self::$container = new Container();
            self::$state = self::BOOTED;

            if (class_exists('vB', false)) {
                self::bootDatabase();
            }

            foreach (self::$providers as $providerClass) {
                self::bootProvider($providerClass);
            }

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

    /**
     * Boots Eloquent ORM via a dedicated lazy PDO connection.
     *
     * ARCHITECTURAL NOTE ON DATABASE CONNECTION & TRANSACTIONS:
     * Native vBulletin 6 uses a \mysqli handle (vB_Db_Mysql_Connection::$connection_master).
     * Eloquent strictly requires a PDO connection. Consequently, Eloquent runs on a separate PDO handle.
     * Transactions executed via Capsule/Eloquent (e.g. Capsule::connection()->transaction())
     * DO NOT encompass or protect native vBulletin queries executed via \vB::getDbAssertor().
     * Conversely, native vBulletin transaction rollbacks will not roll back Eloquent database writes.
     */
    public static function bootDatabase(?array $customConfig = null): Manager
    {
        if (self::$database !== null) {
            return self::$database;
        }

        if (self::$state !== self::BOOTED) {
            self::boot();
        }

        $config = $customConfig ?? self::extractVbDatabaseConfig();

        $capsule = new Manager(self::$container);
        $capsule->addConnection([
            'driver' => 'mysql',
            'database' => $config['database'] ?? '',
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 3306,
            'username' => $config['username'] ?? '',
            'password' => $config['password'] ?? '',
            'charset' => $config['charset'] ?? 'utf8mb4',
            'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
            'prefix' => $config['prefix'] ?? '',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$container->instance(Manager::class, $capsule);
        self::$container->singleton('db', fn() => $capsule->getDatabaseManager());
        self::$database = $capsule;

        return $capsule;
    }

    public static function schema()
    {
        return self::bootDatabase()->schema();
    }

    public static function extractVbDatabaseConfig(): array
    {
        $database = [];
        $master = [];
        $prefix = defined('TABLE_PREFIX') ? (string) TABLE_PREFIX : '';

        if (class_exists('vB', false) && method_exists('vB', 'getDbAssertor')) {
            try {
                $assertor = \vB::getDbAssertor();
                $ref = new \ReflectionProperty($assertor, 'dbconfig');
                $ref->setAccessible(true);
                $cfg = (array) $ref->getValue($assertor);
                $master = (array) ($cfg['MasterServer'] ?? []);
                $database = (array) ($cfg['Database'] ?? []);
                if (empty($prefix) && isset($database['tableprefix'])) {
                    $prefix = (string) $database['tableprefix'];
                }
            } catch (Throwable) {
            }
        }

        if (empty($master['username']) && defined('DIR') && is_file(DIR . '/includes/config.php')) {
            try {
                $config = [];
                require DIR . '/includes/config.php';
                if (!empty($config['MasterServer'])) {
                    $master = (array) $config['MasterServer'];
                }
                if (!empty($config['Database'])) {
                    $database = (array) $config['Database'];
                    if (empty($prefix) && isset($database['tableprefix'])) {
                        $prefix = (string) $database['tableprefix'];
                    }
                }
            } catch (Throwable) {
            }
        }

        if (empty($master['username']) && class_exists('vB', false) && method_exists('vB', 'getConfig')) {
            $vbConfig = (array) \vB::getConfig();
            $master = array_merge((array) ($vbConfig['MasterServer'] ?? []), $master);
            $database = array_merge((array) ($vbConfig['Database'] ?? []), $database);
            if (empty($prefix) && isset($database['tableprefix'])) {
                $prefix = (string) $database['tableprefix'];
            }
        }

        return [
            'database' => (string) ($database['dbname'] ?? ''),
            'host' => (string) ($master['servername'] ?? '127.0.0.1'),
            'port' => (int) ($master['port'] ?? 3306),
            'username' => (string) ($master['username'] ?? ''),
            'password' => (string) ($master['password'] ?? ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => $prefix,
        ];
    }
}
