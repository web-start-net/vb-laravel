<?php

declare(strict_types=1);

ini_set('zend.assertions', '1');
ini_set('assert.active', '1');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Dev/Validator.php';
require_once __DIR__ . '/../src/Dev/Compiler.php';
require_once __DIR__ . '/../src/Dev/Generator.php';

use VbLaravel\Dev\Compiler;
use VbLaravel\Dev\Generator;
use VbLaravel\Dev\Validator;

function check(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new RuntimeException("Test assertion failed: " . ($message ?: 'Condition was false'));
    }
}

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

check(is_dir($addonDir), "Add-on directory should exist");
check(is_file($addonDir . '/vb-addon.json'), "vb-addon.json should exist");
check(is_file($addonDir . '/src/WsTestaddonServiceProvider.php'), "ServiceProvider should exist with StudlyCase WsTestaddonServiceProvider.php");
check(is_file($addonDir . '/src/Models/Item.php'), "Item model should exist");
check(is_file($addonDir . '/src/Hooks/ContentListener.php'), "ContentListener should exist");
check(is_file($addonDir . '/src/Api/ItemController.php'), "ItemController should exist");
check(is_file($addonDir . '/config/settings.php'), "config/settings.php should exist");
check(is_file($addonDir . '/lang/en.php'), "lang/en.php should exist");
check(is_file($addonDir . '/templates/hooks/header_head.html'), "templates/hooks/header_head.html should exist");

// Validate scaffolded addon immediately with 0 edits
$errors = Validator::validate($addonDir);
check(empty($errors), "Scaffolded add-on must pass validate with 0 errors: " . implode(', ', $errors));

// Build scaffolded addon immediately with 0 edits
$buildDir = $tempDir . '/build_initial';
$xmlFile = Compiler::build($addonDir, $buildDir);
check(is_file($xmlFile), "Scaffolded add-on must build XML with 0 edits");
$xmlContent = (string) file_get_contents($xmlFile);
check(str_contains($xmlContent, 'productid="wstestaddon"'), "XML must contain productid");
check(str_contains($xmlContent, '<title>Test Addon</title>'), "XML must contain title");
check(str_contains($xmlContent, 'varname="wstestaddon_enabled"'), "XML must contain setting");
check(str_contains($xmlContent, 'wstestaddon_title'), "XML must contain phrases");
echo "✓ make:addon generated a 100% valid, buildable add-on with 0 edits!\n";

// 2. Test granular generators
$modelFile = Generator::makeModel('PostReview', 'wstestaddon_reviews', $addonDir);
check(is_file($modelFile), "Model file must be created");
$modelContent = (string) file_get_contents($modelFile);
check(str_contains($modelContent, 'class PostReview extends Model'), "Model class must be PostReview");
check(str_contains($modelContent, "\$table = 'wstestaddon_reviews'"), "Model table must be wstestaddon_reviews");
check(str_contains($modelContent, "\$dateFormat = 'U'"), "Model dateFormat must be U");

$migFile = Generator::makeMigration('create_reviews_table', 'wstestaddon_reviews', $addonDir);
check(is_file($migFile), "Migration file must be created");
$migContent = (string) file_get_contents($migFile);
check(str_contains($migContent, 'return new class extends Migration'), "Migration must return anonymous Migration class");
check(str_contains($migContent, "'wstestaddon_reviews'"), "Migration must reference target table");

$hookFile = Generator::makeHook('ReviewHook', 'hookFrontendContentAfterAdd', 15, $addonDir);
check(is_file($hookFile), "Hook file must be created");
$hookContent = (string) file_get_contents($hookFile);
check(str_contains($hookContent, "#[Hook('hookFrontendContentAfterAdd', order: 15)]"), "Hook attribute must match");
check(str_contains($hookContent, "public function onHookFrontendContentAfterAdd(array &\$params): void"), "Hook signature must take array &\$params");

$apiFile = Generator::makeApi('Review', 'wstestaddon:review', 'submit', true, $addonDir);
check(is_file($apiFile), "API file must be created");
$apiContent = (string) file_get_contents($apiFile);
check(str_contains($apiContent, "#[Api('wstestaddon:review', 'submit', write: true)]"), "API attribute must match");
check(str_contains($apiContent, "#[Can('canview', 'genericpermissions')]"), "API must include #[Can] guard");

$tplFile = Generator::makeTemplate('review_widget', false, $addonDir);
check(is_file($tplFile), "Template file must be created");
$tplHookFile = Generator::makeTemplate('footer_end', true, $addonDir);
check(is_file($tplHookFile), "Template hook file must be created");

echo "✓ Granular generators (make:model, make:migration, make:hook, make:api, make:template) PASSED!\n";

// 3. Re-validate and re-build with new components
$errorsAfter = Validator::validate($addonDir);
check(empty($errorsAfter), "Add-on with newly generated components must validate with 0 errors: " . implode(', ', $errorsAfter));

$buildDir2 = $tempDir . '/build_updated';
$xmlFile2 = Compiler::build($addonDir, $buildDir2);
check(is_file($xmlFile2), "Add-on with newly generated components must compile XML");
check(is_file($buildDir2 . '/hooks.php'), "Add-on must compile package hooks.php");
$hooksContent = (string) file_get_contents($buildDir2 . '/hooks.php');
check(str_contains($hooksContent, 'hookFrontendContentAfterAdd'), "hooks.php must contain PHP hook");

$xmlContent2 = (string) file_get_contents($xmlFile2);
check(str_contains($xmlContent2, 'footer_end'), "XML must contain template hook footer_end");
check(str_contains($xmlContent2, 'header_head'), "XML must contain template hook header_head");
echo "✓ Validation and Compilation with all generated components PASSED!\n";

// 4. Test packageAddon
$distDir = $tempDir . '/dist';
$zipPath = Generator::packageAddon($addonDir, $distDir);
check(is_file($zipPath), "Package zip must exist");
check(filesize($zipPath) > 500, "Package zip must have non-trivial size");

$zip = new ZipArchive();
check($zip->open($zipPath) === true, "Must be a valid zip archive");
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
    check($zip->locateName($entry) !== false, "Zip archive must contain entry: {$entry}");
}
$zip->close();
echo "✓ package:addon created upload-ready zip with correct core/packages/<id>/ structure!\n";

// 5. Test syncVblaravelPackage (SSOT)
$syncResult = Generator::syncVblaravelPackage(dirname(__DIR__));
check(!empty($syncResult['copied']), "Must copy runtime files");
check(!in_array('Dev/Generator.php', $syncResult['copied'], true), "Dev/ tooling must NOT be copied into production package");
check(file_exists($syncResult['target'] . '/Runtime.php'), "Runtime.php must exist in package target");
echo "✓ syncVblaravelPackage verified SSOT (src/ -> package/) without leaking Dev/ tooling!\n";

// 6. Test Generator error handling
try {
    Generator::makeAddon('invalid_id_with_underscores', 'Invalid', $tempDir);
    check(false, "Should have thrown for invalid ID");
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'Invalid add-on ID'));
}

try {
    Generator::makeModel('PostReview', null, $addonDir);
    check(false, "Should have thrown for duplicate model");
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'already exists'));
}

try {
    Generator::makeHook('ReviewHook', 'hookFrontendContentAfterAdd', 10, $addonDir);
    check(false, "Should have thrown for duplicate hook");
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'already exists'));
}

// 7. Test Validator detection of unknown PHP hooks, unknown template hooks, and missing byRef
$badHookFile = $addonDir . '/src/Hooks/BadHook.php';
file_put_contents($badHookFile, <<<PHP
<?php
namespace WsTestaddon\Hooks;
use VbLaravel\Attributes\Hook;
class BadHook {
    #[Hook('hookFrontendContentAfterAd')] // Typo: missing trailing 'd'
    public function onTypo(array \$params): void {} // Missing by-reference '&'
}
PHP
);

$badTplHook = $addonDir . '/templates/hooks/footer_bottom.html';
file_put_contents($badTplHook, '<!-- invalid template hook -->');

$valErrors = Validator::validate($addonDir);
unlink($badHookFile);
unlink($badTplHook);

$hasPhpSug = false;
$hasByRefErr = false;
$hasTplSug = false;
foreach ($valErrors as $ve) {
    if (str_contains($ve, "Unknown PHP hook 'hookFrontendContentAfterAd'") && str_contains($ve, "Did you mean 'hookFrontendContentAfterAdd'?")) {
        $hasPhpSug = true;
    }
    if (str_contains($ve, "must accept an array by reference")) {
        $hasByRefErr = true;
    }
    if (str_contains($ve, "Unknown template hook 'footer_bottom'") && str_contains($ve, "Did you mean 'footer_")) {
        $hasTplSug = true;
    }
}
check($hasPhpSug, "Validator must detect unknown PHP hook and suggest closest match: " . implode(' | ', $valErrors));
check($hasByRefErr, "Validator must error on hook handler missing by-reference &\$params: " . implode(' | ', $valErrors));
check($hasTplSug, "Validator must detect unknown template hook and suggest closest match: " . implode(' | ', $valErrors));
echo "✓ Validator unknown hook suggestion and by-ref parameter verification PASSED!\n";

echo "✓ ALL CLI Generator and Packaging tests PASSED!\n";
