<?php

declare(strict_types=1);

require_once __DIR__ . '/../package/core/packages/vblaravel/bootstrap.php';
require_once __DIR__ . '/../src/Dev/Validator.php';
require_once __DIR__ . '/../src/Dev/Compiler.php';

use VbLaravel\Dev\Compiler;
use VbLaravel\Dev\Validator;
use VbLaravel\Models\Channel;
use VbLaravel\Models\Node;
use VbLaravel\Models\User;

echo "Running full VB Laravel verification suite...\n";

// 1. Validate both example addons
$bookmarkErrors = Validator::validate(__DIR__ . '/../examples/bookmarks');
assert(empty($bookmarkErrors), 'Bookmarks example validation should pass without errors.');

$formsErrors = Validator::validate(__DIR__ . '/../examples/wsforms');
assert(empty($formsErrors), 'WsForms example validation should pass without errors.');

// 2. Build both example addons
$bookmarkXml = Compiler::build(__DIR__ . '/../examples/bookmarks');
assert(is_file($bookmarkXml), 'Bookmarks XML was generated.');
$bookmarkXmlContent = file_get_contents($bookmarkXml);
assert(str_contains($bookmarkXmlContent, 'parentproductid="vblaravel"'), 'Product XML must declare dependency on vblaravel.');
assert(str_contains($bookmarkXmlContent, "DIR . '/packages/wsbookmarks'"), 'Product XML must build path from DIR constant, not __DIR__.');
assert(str_contains($bookmarkXmlContent, 'wsbookmarks_hook_header_head'), 'Template hooks must be isolated in XML <hooks>.');

$formsXml = Compiler::build(__DIR__ . '/../examples/wsforms');
assert(is_file($formsXml), 'WsForms XML was generated.');
$formsXmlContent = file_get_contents($formsXml);
assert(str_contains($formsXmlContent, 'parentproductid="vblaravel"'));
assert(str_contains($formsXmlContent, "DIR . '/packages/wsforms'"));

// 3. Test core models read-only enforcement
VbLaravel\Runtime::boot();

$models = [new User(), new Node(), new Channel()];
foreach ($models as $m) {
    try {
        $m->save();
        assert(false, get_class($m) . ' should be read-only');
    } catch (BadMethodCallException $e) {
        assert(str_contains($e->getMessage(), 'read-only core vBulletin model'));
    }

    try {
        $m->delete();
        assert(false, get_class($m) . ' should prevent delete');
    } catch (BadMethodCallException $e) {
        assert(str_contains($e->getMessage(), 'read-only core vBulletin model'));
    }
}

// 4. Test User error fallback in Bridge
vb()->resetUser();
assert(is_array(vb()->user()));

echo "All VB Laravel full lifecycle checks passed successfully!\n";
