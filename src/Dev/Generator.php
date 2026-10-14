<?php

declare(strict_types=1);

namespace VbLaravel\Dev;

use RuntimeException;
use ZipArchive;

final class Generator
{
    /**
     * Convert an identifier to StudlyCase / PascalCase.
     */
    public static function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        // If it starts with ws followed by letters, e.g. wsratings -> WsRatings
        if (preg_match('/^ws([a-z0-9]+)$/i', $value, $m)) {
            return 'Ws' . ucfirst($m[1]);
        }
        return str_replace(' ', '', ucwords($value));
    }

    /**
     * Convert a string to snake_case.
     */
    public static function snake(string $value): string
    {
        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        return strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $value) ?? $value);
    }

    /**
     * Locate the root directory of an add-on containing vb-addon.json.
     */
    public static function findAddonDir(?string $path = null): string
    {
        $dir = $path ? realpath($path) : getcwd();
        if (!$dir || !is_dir($dir)) {
            throw new RuntimeException("Directory not found: " . ($path ?? 'current directory'));
        }

        // Check if $dir contains vb-addon.json
        if (is_file($dir . '/vb-addon.json')) {
            return $dir;
        }

        // Walk up directory tree
        $current = $dir;
        while ($current && dirname($current) !== $current) {
            if (is_file($current . '/vb-addon.json')) {
                return $current;
            }
            $current = dirname($current);
        }

        throw new RuntimeException("Could not locate 'vb-addon.json' in '{$dir}' or any parent directory.");
    }

    /**
     * Read the addon manifest from a directory.
     */
    public static function readManifest(string $addonDir): array
    {
        $file = $addonDir . '/vb-addon.json';
        if (!is_file($file)) {
            throw new RuntimeException("Missing 'vb-addon.json' in '{$addonDir}'.");
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON in '{$file}'.");
        }
        return $data;
    }

    /**
     * Scaffold a complete working add-on.
     *
     * @return string Absolute path to created add-on directory.
     */
    public static function makeAddon(string $id, string $title, ?string $parentDir = null, ?string $customFolder = null): string
    {
        $id = strtolower(trim($id));
        if (!preg_match('/^[a-z][a-z0-9]{2,24}$/', $id)) {
            throw new RuntimeException("Invalid add-on ID '{$id}'. Must match pattern ^[a-z][a-z0-9]{2,24}$ (3-25 lowercase alphanumeric characters, starting with a letter).");
        }

        $title = trim($title);
        if ($title === '') {
            throw new RuntimeException("Add-on title cannot be empty.");
        }

        $baseDir = $parentDir ? rtrim($parentDir, '/\\') : getcwd();
        $targetDir = $baseDir . '/' . ($customFolder ? trim($customFolder, '/\\') : $id);

        if (is_dir($targetDir)) {
            throw new RuntimeException("Target directory '{$targetDir}' already exists.");
        }

        $studly = self::studly($id);
        $providerClass = "{$studly}\\{$studly}ServiceProvider";
        $tableName = "{$id}_items";
        $timestamp = date('Y_m_d_His');

        // Create directory structure
        $dirs = [
            $targetDir,
            $targetDir . '/src',
            $targetDir . '/src/Api',
            $targetDir . '/src/Hooks',
            $targetDir . '/src/Models',
            $targetDir . '/database/migrations',
            $targetDir . '/config',
            $targetDir . '/lang',
            $targetDir . '/templates/hooks',
        ];

        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                mkdir($d, 0777, true);
            }
        }

        // 1. vb-addon.json
        $manifest = [
            'id' => $id,
            'title' => $title,
            'version' => '1.0.0',
            'runtime' => '^0.3',
            'provider' => $providerClass,
            'autoload' => [
                'psr-4' => [
                    "{$studly}\\" => 'src/',
                ],
            ],
        ];
        file_put_contents(
            $targetDir . '/vb-addon.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        // 2. Service Provider
        $providerCode = <<<PHP
<?php

declare(strict_types=1);

namespace {$studly};

use Illuminate\Support\ServiceProvider;

class {$studly}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind add-on services or dependencies into the Illuminate container
    }

    public function boot(): void
    {
        // Boot services when Illuminate is lazily initialized
    }
}

PHP;
        file_put_contents($targetDir . "/src/{$studly}ServiceProvider.php", $providerCode);

        // 3. Model: Item
        $modelCode = <<<PHP
<?php

declare(strict_types=1);

namespace {$studly}\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected \$table = '{$tableName}';
    protected \$dateFormat = 'U';
    protected \$guarded = [];
}

PHP;
        file_put_contents($targetDir . '/src/Models/Item.php', $modelCode);

        // 4. Migration: create items table
        $migrationFile = "{$timestamp}_create_{$id}_items_table.php";
        $migrationCode = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        if (!Capsule::schema()->hasTable('{$tableName}')) {
            Capsule::schema()->create('{$tableName}', static function (Blueprint \$table): void {
                \$table->increments('id');
                \$table->string('title');
                \$table->text('description')->nullable();
                \$table->boolean('enabled')->default(true);
                \$table->unsignedInteger('created_at')->default(0);
                \$table->unsignedInteger('updated_at')->default(0);
            });
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('{$tableName}');
    }
};

PHP;
        file_put_contents($targetDir . "/database/migrations/{$migrationFile}", $migrationCode);

        // 5. Hook listener
        $hookCode = <<<PHP
<?php

declare(strict_types=1);

namespace {$studly}\Hooks;

use VbLaravel\Attributes\Hook;

class ContentListener
{
    #[Hook('hookFrontendContentAfterAdd', order: 10)]
    public function onContentAfterAdd(array &\$params): void
    {
        if ((int) vb()->setting('{$id}_enabled', 1) !== 1) {
            return;
        }

        // Hook execution logic
    }
}

PHP;
        file_put_contents($targetDir . '/src/Hooks/ContentListener.php', $hookCode);

        // 6. API Controller: ItemController
        $apiCode = <<<PHP
<?php

declare(strict_types=1);

namespace {$studly}\Api;

use VbLaravel\Attributes\Api;
use VbLaravel\Attributes\Can;
use {$studly}\Models\Item;

class ItemController
{
    #[Api('{$id}:item', 'get')]
    #[Can('canview', 'genericpermissions')]
    public function get(int \$id): array
    {
        \$item = Item::find(\$id);
        if (!\$item) {
            throw new \\vB_Exception_Api('item_not_found');
        }

        return \$item->toArray();
    }

    #[Api('{$id}:item', 'save', write: true)]
    #[Can('canview', 'genericpermissions')]
    public function save(string \$title, string \$description = ''): array
    {
        \$item = Item::create([
            'title' => \$title,
            'description' => \$description,
            'enabled' => true,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        return [
            'success' => true,
            'id' => \$item->id,
        ];
    }
}

PHP;
        file_put_contents($targetDir . '/src/Api/ItemController.php', $apiCode);

        // 7. Settings
        $settingsCode = <<<PHP
<?php

declare(strict_types=1);

return [
    'group_title' => '{$title} Settings',
    'displayorder' => 1000,
    'settings' => [
        '{$id}_enabled' => [
            'title' => 'Enable {$title}',
            'description' => 'Enable or disable {$title} functionality.',
            'type' => 'boolean',
            'default' => 1,
            'displayorder' => 10,
        ],
    ],
];

PHP;
        file_put_contents($targetDir . '/config/settings.php', $settingsCode);

        // 8. Phrases (lang/en.php)
        $langCode = <<<PHP
<?php

declare(strict_types=1);

return [
    '{$id}_title' => '{$title}',
    '{$id}_welcome' => 'Welcome to {$title}',
];

PHP;
        file_put_contents($targetDir . '/lang/en.php', $langCode);

        // 9. Template hook (templates/hooks/header_head.html)
        $templateHookHtml = "<!-- {$id}:header_head -->\n<meta name=\"{$id}-addon\" content=\"enabled\" />\n";
        file_put_contents($targetDir . '/templates/hooks/header_head.html', $templateHookHtml);

        return $targetDir;
    }

    /**
     * Create a new Eloquent model in an add-on.
     */
    public static function makeModel(string $name, ?string $table = null, ?string $addonPath = null): string
    {
        $addonDir = self::findAddonDir($addonPath);
        $manifest = self::readManifest($addonDir);
        $studly = self::studly($manifest['id']);

        $modelName = self::studly($name);
        $tableName = $table ?? ($manifest['id'] . '_' . self::snake($modelName) . 's');

        $modelsDir = $addonDir . '/src/Models';
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0777, true);
        }

        $filePath = $modelsDir . "/{$modelName}.php";
        if (is_file($filePath)) {
            throw new RuntimeException("Model '{$modelName}' already exists at '{$filePath}'.");
        }

        $code = <<<PHP
<?php

declare(strict_types=1);

namespace {$studly}\Models;

use Illuminate\Database\Eloquent\Model;

class {$modelName} extends Model
{
    protected \$table = '{$tableName}';
    protected \$dateFormat = 'U';
    protected \$guarded = [];
}

PHP;
        file_put_contents($filePath, $code);
        return $filePath;
    }

    /**
     * Create a new database migration in an add-on.
     */
    public static function makeMigration(string $name, ?string $table = null, ?string $addonPath = null): string
    {
        $addonDir = self::findAddonDir($addonPath);
        $manifest = self::readManifest($addonDir);
        $studly = self::studly($manifest['id']);

        $snakeName = self::snake($name);
        $className = self::studly($name);
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$snakeName}.php";

        $migrationsDir = $addonDir . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0777, true);
        }

        $filePath = $migrationsDir . "/{$fileName}";
        $targetTable = $table ?? ($manifest['id'] . '_' . str_replace('create_', '', $snakeName));

        $code = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        if (!Capsule::schema()->hasTable('{$targetTable}')) {
            Capsule::schema()->create('{$targetTable}', static function (Blueprint \$table): void {
                \$table->increments('id');
                \$table->unsignedInteger('created_at')->default(0);
                \$table->unsignedInteger('updated_at')->default(0);
            });
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('{$targetTable}');
    }
};

PHP;
        file_put_contents($filePath, $code);
        return $filePath;
    }

    /**
     * Create a new hook listener in an add-on.
     */
    public static function makeHook(string $name, string $hookName = 'hookFrontendContentAfterAdd', int $order = 10, ?string $addonPath = null): string
    {
        $addonDir = self::findAddonDir($addonPath);
        $manifest = self::readManifest($addonDir);
        $studly = self::studly($manifest['id']);

        $className = self::studly($name);
        $hooksDir = $addonDir . '/src/Hooks';
        if (!is_dir($hooksDir)) {
            mkdir($hooksDir, 0777, true);
        }

        $filePath = $hooksDir . "/{$className}.php";
        if (is_file($filePath)) {
            throw new RuntimeException("Hook listener '{$className}' already exists at '{$filePath}'.");
        }

        $methodName = 'on' . ucfirst($hookName);

        $code = <<<PHP
<?php

declare(strict_types=1);

namespace {$studly}\Hooks;

use VbLaravel\Attributes\Hook;

class {$className}
{
    #[Hook('{$hookName}', order: {$order})]
    public function {$methodName}(array &\$params): void
    {
        // Custom hook logic
    }
}

PHP;
        file_put_contents($filePath, $code);
        return $filePath;
    }

    /**
     * Create a new API controller in an add-on.
     */
    public static function makeApi(string $name, ?string $controller = null, ?string $action = null, bool $write = false, ?string $addonPath = null, ?string $node = null): string
    {
        $addonDir = self::findAddonDir($addonPath);
        $manifest = self::readManifest($addonDir);
        $studly = self::studly($manifest['id']);

        $ctrlBase = self::studly(str_replace('Controller', '', $name));
        $className = $ctrlBase . 'Controller';

        $apiDir = $addonDir . '/src/Api';
        if (!is_dir($apiDir)) {
            mkdir($apiDir, 0777, true);
        }

        $filePath = $apiDir . "/{$className}.php";
        if (is_file($filePath)) {
            throw new RuntimeException("API Controller '{$className}' already exists at '{$filePath}'.");
        }

        $cIdent = $controller ?? ($manifest['id'] . ':' . strtolower($ctrlBase));
        $actName = $action ?? ($write ? 'save' : 'get');
        $writeAttr = $write ? ', write: true' : '';
        $nodeAttr = $node ? ", node: '{$node}'" : '';
        $paramSig = $node ? "int \${$node}" : '';

        $code = <<<PHP
<?php

declare(strict_types=1);

namespace {$studly}\Api;

use VbLaravel\Attributes\Api;
use VbLaravel\Attributes\Can;

class {$className}
{
    #[Api('{$cIdent}', '{$actName}'{$writeAttr}{$nodeAttr})]
    #[Can('canview', 'genericpermissions')]
    public function {$actName}({$paramSig}): array
    {
        return [
            'success' => true,
        ];
    }
}

PHP;
        file_put_contents($filePath, $code);
        return $filePath;
    }

    /**
     * Create a template or template hook file.
     */
    public static function makeTemplate(string $name, bool $isHook = false, ?string $addonPath = null): string
    {
        $addonDir = self::findAddonDir($addonPath);
        $manifest = self::readManifest($addonDir);
        $id = $manifest['id'];

        $cleanName = basename(strtolower(trim($name)), '.html');

        if ($isHook) {
            $dir = $addonDir . '/templates/hooks';
        } else {
            $dir = $addonDir . '/templates';
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filePath = $dir . "/{$cleanName}.html";
        if (is_file($filePath)) {
            throw new RuntimeException("Template file already exists at '{$filePath}'.");
        }

        if ($isHook) {
            $content = "<!-- {$id}:hook_{$cleanName} -->\n<div class=\"{$id}-hook-container\">\n    <!-- Template hook content -->\n</div>\n";
        } else {
            $content = "<div class=\"{$id}-template-container\">\n    <h3>{$cleanName}</h3>\n</div>\n";
        }

        file_put_contents($filePath, $content);
        return $filePath;
    }

    /**
     * Create a distribution zip package for forum administrators.
     */
    public static function packageAddon(string $addonPath, ?string $outputDir = null): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException("PHP Zip extension (ZipArchive) is required for packaging.");
        }

        $addonDir = self::findAddonDir($addonPath);
        $manifest = self::readManifest($addonDir);
        $id = $manifest['id'];
        $version = $manifest['version'] ?? '1.0.0';

        $distDir = $outputDir ? rtrim($outputDir, '/\\') : $addonDir . '/dist';
        if (!is_dir($distDir)) {
            mkdir($distDir, 0777, true);
        }

        // Build the addon into temporary output directory
        $tempPkgDir = sys_get_temp_dir() . '/vbl_pkg_' . $id . '_' . uniqid();
        Compiler::build($addonDir, $tempPkgDir);

        $zipFile = $distDir . "/{$id}-{$version}.zip";
        if (is_file($zipFile)) {
            unlink($zipFile);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create zip archive: {$zipFile}");
        }

        // Add all files from $tempPkgDir into core/packages/<id>/
        $prefix = "core/packages/{$id}/";
        $files = self::rglob($tempPkgDir);
        foreach ($files as $file) {
            if (is_file($file)) {
                $relative = substr($file, strlen($tempPkgDir) + 1);
                $zipPath = $prefix . str_replace('\\', '/', $relative);
                $zip->addFile($file, $zipPath);
            }
        }

        $zip->close();

        // Cleanup temporary directory
        self::deleteDirectory($tempPkgDir);

        return $zipFile;
    }

    /**
     * Synchronize single source of truth: root src/ into package/core/packages/vblaravel/src/.
     */
    public static function syncVblaravelPackage(?string $rootDir = null, ?string $newVersion = null): array
    {
        $root = $rootDir ? realpath($rootDir) : dirname(__DIR__, 2);
        if (!$root || !is_dir($root . '/src')) {
            throw new RuntimeException("Could not find root repository at '{$root}'.");
        }

        $srcDir = $root . '/src';
        $pkgDir = $root . '/package/core/packages/vblaravel';
        $pkgSrc = $pkgDir . '/src';

        if (!is_dir($pkgDir)) {
            throw new RuntimeException("Package directory not found: {$pkgDir}");
        }

        // 1. If new version provided, update Runtime.php and product_vblaravel.xml
        if ($newVersion !== null) {
            $newVersion = trim($newVersion);
            if (!preg_match('/^\d+\.\d+\.\d+$/', $newVersion)) {
                throw new RuntimeException("Invalid version format '{$newVersion}'. Must be semver (e.g. 0.3.0).");
            }

            // Update src/Runtime.php
            $runtimeFile = $srcDir . '/Runtime.php';
            if (is_file($runtimeFile)) {
                $content = (string) file_get_contents($runtimeFile);
                $content = preg_replace(
                    "/public const VERSION = '[^']+';/",
                    "public const VERSION = '{$newVersion}';",
                    $content
                );
                file_put_contents($runtimeFile, $content);
            }

            // Update package/core/packages/vblaravel/xml/product_vblaravel.xml
            $xmlFile = $pkgDir . '/xml/product_vblaravel.xml';
            if (is_file($xmlFile)) {
                $xml = (string) file_get_contents($xmlFile);
                $xml = preg_replace('/<version>[^<]+<\/version>/', "<version>{$newVersion}</version>", $xml);
                if (!str_contains($xml, "<code version=\"{$newVersion}\">")) {
                    $codeBlock = "        <code version=\"{$newVersion}\">\n            <installcode><![CDATA[if (version_compare(PHP_VERSION, '8.2.0', '<')) {\n    trigger_error('VB Laravel requires PHP 8.2 or higher. Current PHP version: ' . PHP_VERSION, E_USER_ERROR);\n}]]></installcode>\n            <uninstallcode><![CDATA[]]></uninstallcode>\n        </code>\n    </codes>";
                    $xml = str_replace('    </codes>', $codeBlock, $xml);
                }
                file_put_contents($xmlFile, $xml);
            }
        }

        // 2. Clear package/core/packages/vblaravel/src/ and copy fresh from root src/ (excluding Dev/)
        if (is_dir($pkgSrc)) {
            self::deleteDirectory($pkgSrc);
        }
        mkdir($pkgSrc, 0777, true);

        $copiedFiles = [];
        $srcFiles = self::rglob($srcDir);
        foreach ($srcFiles as $file) {
            if (is_file($file)) {
                $rel = substr($file, strlen($srcDir) + 1);
                $relNorm = str_replace('\\', '/', $rel);

                // Exclude Dev/ directory (dev-only tooling)
                if (str_starts_with($relNorm, 'Dev/')) {
                    continue;
                }

                $dest = $pkgSrc . '/' . $rel;
                $destDir = dirname($dest);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }
                copy($file, $dest);
                $copiedFiles[] = $relNorm;
            }
        }

        return [
            'copied' => $copiedFiles,
            'version' => $newVersion,
            'target' => $pkgSrc,
        ];
    }

    private static function rglob(string $dir): array
    {
        $files = [];
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $files = array_merge($files, self::rglob($path));
            } else {
                $files[] = $path;
            }
        }
        return $files;
    }

    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Build, deploy package, and re-import product XML in one command.
     */
    public static function devDeploy(string $addonPath, ?string $targetCoreDir = null): array
    {
        $addonDir = self::findAddonDir($addonPath);
        $manifest = self::readManifest($addonDir);
        $id = $manifest['id'];

        // Determine vBulletin core directory
        $coreDir = $targetCoreDir ? realpath($targetCoreDir) : null;
        if (!$coreDir) {
            $envTesting = dirname(__DIR__, 2) . '/.env.testing';
            if (is_file($envTesting)) {
                $lines = file($envTesting, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'VBL_TEST_DIR=')) {
                        $coreDir = trim(trim(substr($line, strlen('VBL_TEST_DIR='))), '"\'');
                        break;
                    }
                }
            }
        }
        if (!$coreDir) {
            $coreDir = getenv('VBL_TEST_DIR') ?: null;
        }
        if (!$coreDir && is_dir('C:/xampp/htdocs/vbtest/core')) {
            $coreDir = 'C:/xampp/htdocs/vbtest/core';
        }
        if (!$coreDir || !is_dir($coreDir)) {
            throw new RuntimeException("vBulletin core directory not found. Specify --target=/path/to/vb/core or set VBL_TEST_DIR in .env.testing.");
        }

        $coreDirNorm = str_replace('\\', '/', $coreDir);

        // 1. Build the add-on package into temporary directory
        $tempPkg = sys_get_temp_dir() . '/vbl_dev_' . $id . '_' . uniqid();
        $xmlFile = Compiler::build($addonDir, $tempPkg);

        // 2. Target package destination: <coreDir>/packages/<id>/
        $targetPkgDir = rtrim($coreDirNorm, '/') . '/packages/' . $id;
        if (!is_dir($targetPkgDir)) {
            mkdir($targetPkgDir, 0777, true);
        }

        // Copy files from $tempPkg to $targetPkgDir
        self::copyDirectory($tempPkg, $targetPkgDir);
        self::deleteDirectory($tempPkg);

        // 3. Re-import XML using vBulletin's native install_product in a separate PHP process
        $targetXml = $targetPkgDir . '/xml/product_' . $id . '.xml';
        if (!is_file($targetXml)) {
            throw new RuntimeException("Generated product XML not found at: {$targetXml}");
        }

        $importScript = sys_get_temp_dir() . '/vbl_import_' . uniqid() . '.php';
        $scriptContent = <<<PHP
<?php
define('DIR', '{$coreDirNorm}');
define('CWD', DIR);
define('VB_AREA', 'AdminCP');
define('THIS_SCRIPT', 'vbl_dev_import');
require_once DIR . '/vb/vb.php';
\\vB::init();
\$req = new \\vB_Request_Cli();
\\vB::setRequest(\$req);
\$req->createSessionForUser(1);
require_once DIR . '/includes/adminfunctions.php';
require_once DIR . '/includes/adminfunctions_product.php';
\$xml = file_get_contents('{$targetXml}');
install_product(\$xml, true, false, false);
\\vB::getDatastore()->build_options();
\\vB_Api::instanceInternal('Hook')->buildHookDatastore();
echo 'SUCCESS';
PHP;
        file_put_contents($importScript, $scriptContent);

        $output = shell_exec('php ' . escapeshellarg($importScript));
        @unlink($importScript);

        $imported = str_contains((string) $output, 'SUCCESS');

        return [
            'id' => $id,
            'version' => $manifest['version'] ?? '1.0.0',
            'coreDir' => $coreDirNorm,
            'packageDir' => $targetPkgDir,
            'xmlFile' => $targetXml,
            'imported' => $imported,
        ];
    }

    public static function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }
        $items = scandir($source) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcPath = $source . '/' . $item;
            $dstPath = $destination . '/' . $item;
            if (is_dir($srcPath)) {
                self::copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }
}
