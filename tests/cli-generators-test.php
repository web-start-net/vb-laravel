<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Dev/Validator.php';
require_once __DIR__ . '/../src/Dev/Compiler.php';
require_once __DIR__ . '/../src/Dev/Generator.php';

use VbLaravel\Dev\Compiler;
use VbLaravel\Dev\Generator;
use VbLaravel\Dev\Validator;

echo "--- Testing CLI Generators, Packaging, and Release ---\n";

$tempDir = sys_get_temp_dir() . '/vbl_gen_test_' . uniqid();
mkdir($tempDir, 0777, true);

register_shutdown_function(static function () use ($tempDir): void {
    if (is_dir($tempDir)) {
        $delete = static function (string $dir, $self) use (&$delete): void {
            $items = scandir($dir) ?: [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . '/' . $item;
                if (is_dir($path)) {
                    $delete($path, $delete);
                } else {
                    @unlink($path);
                }
            }
            @rmdir($dir);
        };
        $delete($tempDir, $delete);
    }
});

// 1. Test makeAddon
$addonId = 'wstestaddon';
$addonTitle = 'Test Addon';
$addonDir = Generator::makeAddon($addonId, $addonTitle, $tempDir);

assert(is_dir($addonDir), "Add-on directory should exist");
assert(is_file($addonDir . '/vb-addon.json'), "vb-addon.json should exist");
assert(is_file($addonDir . '/src/WstestaddonServiceProvider.php'), "ServiceProvider should exist");
assert(is_file($addonDir . '/src/Models/Item.php'), "Item model should exist");
assert(is_file($addonDir . '/src/Hooks/ContentListener.php'), "ContentListener should exist");
assert(is_file($addonDir . '/src/Api/ItemController.php'), "ItemController should exist");
assert(is_file($addonDir . '/config/settings.php'), "config/settings.php should exist");
assert(is_file($addonDir . '/lang/en.php'), "lang/en.php should exist");
assert(is_file($addonDir . '/templates/hooks/header_head.html'), "templates/hooks/header_head.html should exist");

// Validate scaffolded addon immediately with 0 edits
$errors = Validator::validate($addonDir);
assert(empty($errors), "Scaffolded add-on must pass validate with 0 errors: " . implode(', ', $errors));

// Build scaffolded addon immediately with 0 edits
$buildDir = $tempDir . '/build_initial';
$xmlFile = Compiler::build($addonDir, $buildDir);
assert(is_file($xmlFile), "Scaffolded add-on must build XML with 0 edits");
$xmlContent = (string) file_get_contents($xmlFile);
assert(str_contains($xmlContent, 'productid="wstestaddon"'), "XML must contain productid");
assert(str_contains($xmlContent, '<title>Test Addon</title>'), "XML must contain title");
assert(str_contains($xmlContent, 'varname="wstestaddon_enabled"'), "XML must contain setting");
assert(str_contains($xmlContent, 'wstestaddon_title'), "XML must contain phrases");
echo "✓ make:addon generated a 100% valid, buildable add-on with 0 edits!\n";

// 2. Test granular generators
$modelFile = Generator::makeModel('PostReview', 'wstestaddon_reviews', $addonDir);
assert(is_file($modelFile), "Model file must be created");
$modelContent = (string) file_get_contents($modelFile);
assert(str_contains($modelContent, 'class PostReview extends Model'), "Model class must be PostReview");
assert(str_contains($modelContent, "\$table = 'wstestaddon_reviews'"), "Model table must be wstestaddon_reviews");
assert(str_contains($modelContent, "\$dateFormat = 'U'"), "Model dateFormat must be U");

$migFile = Generator::makeMigration('create_reviews_table', 'wstestaddon_reviews', $addonDir);
assert(is_file($migFile), "Migration file must be created");
$migContent = (string) file_get_contents($migFile);
assert(str_contains($migContent, 'return new class extends Migration'), "Migration must return anonymous Migration class");
assert(str_contains($migContent, "'wstestaddon_reviews'"), "Migration must reference target table");

$hookFile = Generator::makeHook('ReviewHook', 'hookFrontendContentAfterAdd', 15, $addonDir);
assert(is_file($hookFile), "Hook file must be created");
$hookContent = (string) file_get_contents($hookFile);
assert(str_contains($hookContent, "#[Hook('hookFrontendContentAfterAdd', order: 15)]"), "Hook attribute must match");
assert(str_contains($hookContent, "public function onHookFrontendContentAfterAdd(array &\$params): void"), "Hook signature must take array &\$params");

$apiFile = Generator::makeApi('Review', 'wstestaddon:review', 'submit', true, $addonDir);
assert(is_file($apiFile), "API file must be created");
$apiContent = (string) file_get_contents($apiFile);
assert(str_contains($apiContent, "#[Api('wstestaddon:review', 'submit', write: true)]"), "API attribute must match");
assert(str_contains($apiContent, "#[Can('canview', 'genericpermissions')]"), "API must include #[Can] guard");

$tplFile = Generator::makeTemplate('review_widget', false, $addonDir);
assert(is_file($tplFile), "Template file must be created");
$tplHookFile = Generator::makeTemplate('footer_bottom', true, $addonDir);
assert(is_file($tplHookFile), "Template hook file must be created");

echo "✓ Granular generators (make:model, make:migration, make:hook, make:api, make:template) PASSED!\n";

// 3. Re-validate and re-build with new components
$errorsAfter = Validator::validate($addonDir);
assert(empty($errorsAfter), "Add-on with newly generated components must validate with 0 errors: " . implode(', ', $errorsAfter));

$buildDir2 = $tempDir . '/build_updated';
$xmlFile2 = Compiler::build($addonDir, $buildDir2);
assert(is_file($xmlFile2), "Add-on with newly generated components must compile XML");
assert(is_file($buildDir2 . '/hooks.php'), "Add-on must compile package hooks.php");
$hooksContent = (string) file_get_contents($buildDir2 . '/hooks.php');
assert(str_contains($hooksContent, 'hookFrontendContentAfterAdd'), "hooks.php must contain PHP hook");

$xmlContent2 = (string) file_get_contents($xmlFile2);
assert(str_contains($xmlContent2, 'footer_bottom'), "XML must contain template hook footer_bottom");
assert(str_contains($xmlContent2, 'header_head'), "XML must contain template hook header_head");
echo "✓ Validation and Compilation with all generated components PASSED!\n";

// 4. Test packageAddon
$distDir = $tempDir . '/dist';
$zipPath = Generator::packageAddon($addonDir, $distDir);
assert(is_file($zipPath), "Package zip must exist");
assert(filesize($zipPath) > 500, "Package zip must have non-trivial size");

$zip = new ZipArchive();
assert($zip->open($zipPath) === true, "Must be a valid zip archive");
$expectedEntries = [
    'core/packages/wstestaddon/vb-addon.json',
    'core/packages/wstestaddon/product.php',
    'core/packages/wstestaddon/hooks.php',
    'core/packages/wstestaddon/xml/product_wstestaddon.xml',
    'core/packages/wstestaddon/src/Models/PostReview.php',
    'core/packages/wstestaddon/src/Api/ReviewController.php',
    'core/packages/wstestaddon/src/Hooks/ReviewHook.php',
    'core/packages/wstestaddon/database/migrations/' . basename($migFile),
];
foreach ($expectedEntries as $entry) {
    assert($zip->locateName($entry) !== false, "Zip archive must contain entry: {$entry}");
}
$zip->close();
echo "✓ package:addon created upload-ready zip with correct core/packages/<id>/ structure!\n";

// 5. Test syncVblaravelPackage (SSOT)
$syncResult = Generator::syncVblaravelPackage(dirname(__DIR__));
assert(!empty($syncResult['copied']), "Must copy runtime files");
assert(!in_array('Dev/Generator.php', $syncResult['copied'], true), "Dev/ tooling must NOT be copied into production package");
assert(file_exists($syncResult['target'] . '/Runtime.php'), "Runtime.php must exist in package target");
echo "✓ syncVblaravelPackage verified SSOT (src/ -> package/) without leaking Dev/ tooling!\n";

// 6. Test Generator error handling
try {
    Generator::makeAddon('invalid_id_with_underscores', 'Invalid', $tempDir);
    assert(false, "Should have thrown for invalid ID");
} catch (RuntimeException $e) {
    assert(str_contains($e->getMessage(), 'Invalid add-on ID'));
}

try {
    Generator::makeModel('PostReview', null, $addonDir);
    assert(false, "Should have thrown for duplicate model");
} catch (RuntimeException $e) {
    assert(str_contains($e->getMessage(), 'already exists'));
}

try {
    Generator::makeHook('ReviewHook', 'hookFrontendContentAfterAdd', 10, $addonDir);
    assert(false, "Should have thrown for duplicate hook");
} catch (RuntimeException $e) {
    assert(str_contains($e->getMessage(), 'already exists'));
}

echo "✓ Generator error handling tests PASSED!\n";
echo "✓ ALL CLI Generator and Packaging tests PASSED!\n";
