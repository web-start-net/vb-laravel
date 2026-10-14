<?php

declare(strict_types=1);

ini_set('zend.assertions', '1');
ini_set('assert.active', '1');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Dev/Compiler.php';

use VbLaravel\Dev\Compiler;

function check(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new RuntimeException("Test assertion failed: " . ($message ?: 'Condition was false'));
    }
}

echo "--- Testing Compiler AST Discovery and Error Handling ---\n";

$tmpDir = sys_get_temp_dir() . '/vbl_test_' . uniqid();
mkdir($tmpDir . '/src', 0777, true);

// 1. Test: defaults containing commas, named args, multi-line attributes, use-imported attributes
$code1 = <<<'PHP'
<?php
namespace MyAddon\Api;

use VbLaravel\Attributes\Api as VbApi;
use VbLaravel\Attributes\Can;

class TestController
{
    #[VbApi(
        controller: 'test:widget',
        action: 'save',
        write: true
    )]
    #[Can('canview', 'genericpermissions')]
    public function save(int $id, string $tags = "foo, bar, baz", array $opts = [1, 2]): array
    {
        return [];
    }
}
PHP;

file_put_contents($tmpDir . '/src/TestController.php', $code1);

$apis = Compiler::discoverApis($tmpDir . '/src');
check(isset($apis['test:widget']), 'Discovered API controller');
$endpoint = $apis['test:widget'][0];

check($endpoint['action'] === 'save', 'Action is save');
check($endpoint['write'] === true, 'Write flag is true');
check($endpoint['canPerm'] === 'canview', 'Can permission is canview');

// Check parameter fidelity: no synthetic defaults, comma-containing default preserved!
$sig = implode(', ', $endpoint['paramSignatures']);
echo "Discovered signature: {$sig}\n";
check(str_contains($sig, 'string $tags = "foo, bar, baz"'), 'Default containing commas preserved');
check(str_contains($sig, 'array $opts = [1, 2]'), 'Array default preserved');
check(str_starts_with($sig, 'int $id'), 'No injected default for int $id');
check(!str_contains($sig, 'int $id = 0'), 'int $id must not have injected = 0 default');
echo "✓ Multi-line, named-arg, use-imported, and comma-containing default tests PASSED!\n";

// 2. Test: unknown attribute throws build ERROR
$code2 = <<<'PHP'
<?php
namespace MyAddon\Hooks;

class BadListener
{
    #[UnknownAttr('foo')]
    public function handle(array &$params): void
    {
    }
}
PHP;
file_put_contents($tmpDir . '/src/BadListener.php', $code2);

$caughtUnknown = false;
try {
    Compiler::discoverPhpHooks($tmpDir . '/src');
} catch (RuntimeException $e) {
    $caughtUnknown = true;
    echo "✓ Unknown attribute threw build ERROR: " . $e->getMessage() . "\n";
}
check($caughtUnknown, 'Unknown attribute must throw build error');

// 3. Test: #[Can] without #[Api] throws build ERROR
unlink($tmpDir . '/src/BadListener.php');
$code3 = <<<'PHP'
<?php
namespace MyAddon\Hooks;

use VbLaravel\Attributes\Can;

class OrphanCan
{
    #[Can('canview')]
    public function handle(array &$params): void
    {
    }
}
PHP;
file_put_contents($tmpDir . '/src/OrphanCan.php', $code3);

$caughtCanWithoutApi = false;
try {
    Compiler::discoverPhpHooks($tmpDir . '/src');
} catch (RuntimeException $e) {
    $caughtCanWithoutApi = true;
    echo "✓ #[Can] without #[Api] threw build ERROR: " . $e->getMessage() . "\n";
}
check($caughtCanWithoutApi, '#[Can] without #[Api] must throw build error');
unlink($tmpDir . '/src/OrphanCan.php');

// 4. Test: #[Api] guest flag default (false) and explicit (true)
$code4 = <<<'PHP'
<?php
namespace MyAddon\Api;

use VbLaravel\Attributes\Api;

class GuestTestController
{
    #[Api(controller: 'test:guest', action: 'defaultWrite', write: true)]
    public function defaultWrite(): array
    {
        return [];
    }

    #[Api(controller: 'test:guest', action: 'guestWrite', write: true, guest: true)]
    public function guestWrite(): array
    {
        return [];
    }
}
PHP;
file_put_contents($tmpDir . '/src/GuestTestController.php', $code4);
$guestApis = Compiler::discoverApis($tmpDir . '/src');
check(isset($guestApis['test:guest']), 'Discovered test:guest controller');
check($guestApis['test:guest'][0]['guest'] === false, 'Default guest flag is false');
check($guestApis['test:guest'][1]['guest'] === true, 'Explicit guest flag is true');
unlink($tmpDir . '/src/GuestTestController.php');
echo "✓ #[Api] guest flag (default false, explicit true) verified!\n";

// Cleanup
unlink($tmpDir . '/src/TestController.php');
rmdir($tmpDir . '/src');
rmdir($tmpDir);

echo "✓ ALL Compiler AST tests PASSED!\n";
exit(0);
