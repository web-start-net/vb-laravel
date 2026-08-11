<?php

declare(strict_types=1);

namespace VB\LaravelTest;

require_once __DIR__ . '/Note.php';

use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\ServiceProvider;
use PDO;
use ReflectionProperty;
use RuntimeException;

final class Provider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        global $vbulletin;

        $assertor = \vB::getDbAssertor();
        $property = new ReflectionProperty($assertor, 'dbconfig');
        $config = $property->getValue($assertor);
        $master = $config['MasterServer'] ?? [];
        $database = $config['Database'] ?? [];

        $pdo = new PDO(
            'mysql:host=' . ($master['servername'] ?? '127.0.0.1')
                . ';port=' . ($master['port'] ?? 3306)
                . ';dbname=' . ($database['dbname'] ?? '') . ';charset=utf8mb4',
            $master['username'] ?? '',
            $master['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $pdo->exec('CREATE TABLE IF NOT EXISTS vblaravel_test_note ('
            . 'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,'
            . 'userid INT UNSIGNED NOT NULL DEFAULT 0,'
            . 'message VARCHAR(255) NOT NULL,'
            . 'created_at TIMESTAMP NULL DEFAULT NULL,'
            . 'updated_at TIMESTAMP NULL DEFAULT NULL'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $capsule = new Manager($this->app);
        $capsule->addConnection(['driver' => 'mysql', 'database' => $database['dbname'], 'host' => $master['servername'], 'port' => $master['port'] ?? 3306, 'username' => $master['username'], 'password' => $master['password'], 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->app->instance(Manager::class, $capsule);

        if (!isset($vbulletin)) {
            throw new RuntimeException('vBulletin registry was not available to the test provider.');
        }
    }
}
