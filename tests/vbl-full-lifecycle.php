<?php

declare(strict_types=1);

ini_set('zend.assertions', '1');
ini_set('assert.active', '1');

require_once __DIR__ . '/../package/core/packages/vblaravel/bootstrap.php';
require_once __DIR__ . '/../src/Dev/Validator.php';
require_once __DIR__ . '/../src/Dev/Compiler.php';

use VbLaravel\Dev\Compiler;
use VbLaravel\Dev\Validator;
use VbLaravel\Models\Channel;
use VbLaravel\Models\Node;
use VbLaravel\Models\User;

function check(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new RuntimeException("Test assertion failed: " . ($message ?: 'Condition was false'));
    }
}

echo "Running full VB Laravel verification suite...\n";

// 1. Validate example addons
$bookmarkErrors = Validator::validate(__DIR__ . '/../examples/bookmarks');
check(empty($bookmarkErrors), 'Bookmarks example validation should pass without errors: ' . implode(', ', $bookmarkErrors));

$formsErrors = Validator::validate(__DIR__ . '/../examples/wsforms');
check(empty($formsErrors), 'WsForms example validation should pass without errors: ' . implode(', ', $formsErrors));

if (is_dir(__DIR__ . '/../examples/ratings')) {
    $ratingsErrors = Validator::validate(__DIR__ . '/../examples/ratings');
    check(empty($ratingsErrors), 'Ratings example validation should pass without errors: ' . implode(', ', $ratingsErrors));
}

// 2. Build example addons
$bookmarkXml = Compiler::build(__DIR__ . '/../examples/bookmarks');
check(is_file($bookmarkXml), 'Bookmarks XML was generated.');
$bookmarkXmlContent = file_get_contents($bookmarkXml);
check(str_contains($bookmarkXmlContent, 'parentproductid="vblaravel"'), 'Product XML must declare dependency on vblaravel.');
check(str_contains($bookmarkXmlContent, "DIR . '/packages/wsbookmarks'"), 'Product XML must build path from DIR constant, not __DIR__.');
check(str_contains($bookmarkXmlContent, 'wsbookmarks_hook_header_head'), 'Template hooks must be isolated in XML <hooks>.');

$formsXml = Compiler::build(__DIR__ . '/../examples/wsforms');
check(is_file($formsXml), 'WsForms XML was generated.');
$formsXmlContent = file_get_contents($formsXml);
check(str_contains($formsXmlContent, 'parentproductid="vblaravel"'));
check(str_contains($formsXmlContent, "DIR . '/packages/wsforms'"));

if (is_dir(__DIR__ . '/../examples/ratings')) {
    $ratingsXml = Compiler::build(__DIR__ . '/../examples/ratings');
    check(is_file($ratingsXml), 'Ratings XML was generated.');
    $ratingsXmlContent = file_get_contents($ratingsXml);
    check(str_contains($ratingsXmlContent, 'parentproductid="vblaravel"'));
    check(str_contains($ratingsXmlContent, "DIR . '/packages/wsratings'"));
}

// 3. Test core models read-only enforcement
VbLaravel\Runtime::boot();

$models = [new User(), new Node(), new Channel()];
foreach ($models as $m) {
    try {
        $m->save();
        check(false, get_class($m) . ' should be read-only');
    } catch (BadMethodCallException $e) {
        check(str_contains($e->getMessage(), 'read-only core vBulletin model'));
    }

    try {
        $m->delete();
        check(false, get_class($m) . ' should prevent delete');
    } catch (BadMethodCallException $e) {
        check(str_contains($e->getMessage(), 'read-only core vBulletin model'));
    }
}

// 4. Test User error fallback in Bridge
vb()->resetUser();
check(is_array(vb()->user()));

echo "All VB Laravel full lifecycle checks passed successfully!\n";
