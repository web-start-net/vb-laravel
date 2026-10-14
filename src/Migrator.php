<?php

declare(strict_types=1);

namespace VbLaravel;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;
use Throwable;

final class Migrator
{
    private const TABLE = 'vbl_migrations';

    public static function up(string $addonPath): int
    {
        Runtime::bootDatabase();
        $addonPath = realpath($addonPath) ?: rtrim($addonPath, '/\\');
        $addonId = self::resolveAddonId($addonPath);
        $migrationsDir = $addonPath . '/database/migrations';

        if (!is_dir($migrationsDir)) {
            return 0;
        }

        self::ensureTable();

        $applied = Capsule::table(self::TABLE)
            ->where('addon_id', $addonId)
            ->pluck('migration')
            ->all();

        $files = glob($migrationsDir . '/*.php') ?: [];
        sort($files);

        $pending = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $applied, true)) {
                $pending[$name] = $file;
            }
        }

        if (empty($pending)) {
            return 0;
        }

        $lastBatch = (int) Capsule::table(self::TABLE)
            ->where('addon_id', $addonId)
            ->max('batch');
        $batch = $lastBatch + 1;

        $count = 0;
        foreach ($pending as $name => $file) {
            $migration = self::resolveInstance($file);
            if ($migration && method_exists($migration, 'up')) {
                $migration->up();
            }

            Capsule::table(self::TABLE)->insert([
                'addon_id' => $addonId,
                'migration' => $name,
                'batch' => $batch,
            ]);
            $count++;
        }

        return $count;
    }

    public static function down(string $addonPath): int
    {
        Runtime::bootDatabase();
        $addonPath = realpath($addonPath) ?: rtrim($addonPath, '/\\');
        $addonId = self::resolveAddonId($addonPath);
        $migrationsDir = $addonPath . '/database/migrations';

        if (!Capsule::schema()->hasTable(self::TABLE)) {
            return 0;
        }

        $records = Capsule::table(self::TABLE)
            ->where('addon_id', $addonId)
            ->orderByDesc('batch')
            ->orderByDesc('id')
            ->get();

        if ($records->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($records as $record) {
            $file = $migrationsDir . '/' . $record->migration . '.php';
            if (is_file($file)) {
                $migration = self::resolveInstance($file);
                if ($migration && method_exists($migration, 'down')) {
                    $migration->down();
                }
            }

            Capsule::table(self::TABLE)
                ->where('id', $record->id)
                ->delete();
            $count++;
        }

        return $count;
    }

    private static function ensureTable(): void
    {
        if (!Capsule::schema()->hasTable(self::TABLE)) {
            Capsule::schema()->create(self::TABLE, static function (Blueprint $table): void {
                $table->increments('id');
                $table->string('addon_id', 64);
                $table->string('migration', 255);
                $table->unsignedInteger('batch');
                $table->timestamp('applied_at')->useCurrent();
                $table->index(['addon_id', 'batch']);
            });
        }
    }

    private static function resolveAddonId(string $addonPath): string
    {
        $manifest = $addonPath . '/vb-addon.json';
        if (is_file($manifest)) {
            $data = json_decode((string) file_get_contents($manifest), true);
            if (is_array($data) && !empty($data['id'])) {
                return (string) $data['id'];
            }
        }

        return basename($addonPath);
    }

    private static function resolveInstance(string $file): ?object
    {
        $res = require $file;
        if (is_object($res)) {
            return $res;
        }

        $classes = get_declared_classes();
        $last = end($classes);
        if ($last && (is_subclass_of($last, Migration::class) || method_exists($last, 'up'))) {
            return new $last();
        }

        return null;
    }
}
