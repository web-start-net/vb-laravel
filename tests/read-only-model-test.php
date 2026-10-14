<?php

declare(strict_types=1);

ini_set('zend.assertions', '1');
ini_set('assert.active', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use VbLaravel\Runtime;
use VbLaravel\Models\Node;
use VbLaravel\Models\User;
use VbLaravel\Models\Channel;

function check(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new RuntimeException("Test assertion failed: " . ($message ?: 'Condition was false'));
    }
}

echo "--- Testing ReadOnlyModel and ReadOnlyBuilder ---\n";

Runtime::boot();
Runtime::bootDatabase([
    'driver' => 'sqlite',
    'database' => ':memory:',
]);

// 1. Test Node::where()->update() throws BadMethodCallException
$updateThrown = false;
try {
    Node::where('nodeid', 1)->update(['title' => 'malicious_update']);
} catch (BadMethodCallException $e) {
    $updateThrown = true;
    echo "✓ Node::where()->update() threw BadMethodCallException: " . $e->getMessage() . "\n";
}
check($updateThrown, 'Node::where()->update() did not throw BadMethodCallException');

// 2. Test Node::create() throws BadMethodCallException
$createThrown = false;
try {
    Node::create(['title' => 'new_node']);
} catch (BadMethodCallException $e) {
    $createThrown = true;
    echo "✓ Node::create() threw BadMethodCallException: " . $e->getMessage() . "\n";
}
check($createThrown, 'Node::create() did not throw BadMethodCallException');

// 3. Test Node::where()->delete() throws BadMethodCallException
$deleteThrown = false;
try {
    Node::where('nodeid', 1)->delete();
} catch (BadMethodCallException $e) {
    $deleteThrown = true;
    echo "✓ Node::where()->delete() threw BadMethodCallException: " . $e->getMessage() . "\n";
}
check($deleteThrown, 'Node::where()->delete() did not throw BadMethodCallException');

// 4. Test Node::where()->increment() throws BadMethodCallException
$incrementThrown = false;
try {
    Node::where('nodeid', 1)->increment('views');
} catch (BadMethodCallException $e) {
    $incrementThrown = true;
    echo "✓ Node::where()->increment() threw BadMethodCallException: " . $e->getMessage() . "\n";
}
check($incrementThrown, 'Node::where()->increment() did not throw BadMethodCallException');

// 5. Test Node::where()->upsert() throws BadMethodCallException
$upsertThrown = false;
try {
    Node::where('nodeid', 1)->upsert([['nodeid' => 1]], ['nodeid']);
} catch (BadMethodCallException $e) {
    $upsertThrown = true;
    echo "✓ Node::where()->upsert() threw BadMethodCallException: " . $e->getMessage() . "\n";
}
check($upsertThrown, 'Node::where()->upsert() did not throw BadMethodCallException');

echo "✓ ALL ReadOnlyModel write prevention tests PASSED!\n";
exit(0);
