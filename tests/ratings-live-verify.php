<?php

declare(strict_types=1);

ini_set('zend.assertions', '1');
ini_set('assert.active', '1');

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: " . $message);
    }
}

$testDir = getenv('VBL_TEST_DIR') ?: null;
$envTestingFile = dirname(__DIR__) . '/.env.testing';
if (!$testDir && is_file($envTestingFile)) {
    $lines = file($envTestingFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'VBL_TEST_DIR=')) {
            $testDir = trim(substr($line, strlen('VBL_TEST_DIR=')));
            $testDir = trim($testDir, '"\'');
            break;
        }
    }
}
$testDir = $testDir ?: 'C:/xampp/htdocs/vbtest/core';

define('DIR', $testDir);
define('CWD', DIR);
define('VB_AREA', 'AdminCP');
define('THIS_SCRIPT', 'verify_ratings');

require_once DIR . '/vb/vb.php';
\vB::init();

$request = new \vB_Request_Cli();
\vB::setRequest($request);
$request->createSessionForUser(1); // Admin

require_once DIR . '/includes/adminfunctions.php';
require_once DIR . '/includes/adminfunctions_product.php';

$assertor = \vB::getDbAssertor();

echo "========================================\n";
echo "   Live Ratings Addon Verification     \n";
echo "========================================\n\n";

// 1. Ensure wsratings product is installed & datastore rebuilt
$xmlFile = DIR . '/packages/wsratings/xml/product_wsratings.xml';
check(is_file($xmlFile), "product_wsratings.xml must exist at {$xmlFile}");

ob_start();
install_product(file_get_contents($xmlFile), true, false, false);
ob_end_clean();

\vB::getDatastore()->build_options();
\vB_Api::instanceInternal('Hook')->buildHookDatastore();

// 2. Check hook datastore
$hookList = \vB::getDatastore()->getValue('hooks');
$hasDisplayControls = false;
$hasHeaderHead = false;
if (is_array($hookList)) {
    foreach ($hookList as $h) {
        if (($h['hookname'] ?? '') === 'conversation_post_above' && ($h['template'] ?? '') === 'wsratings_hook_conversation_post_above') {
            $hasDisplayControls = true;
        }
        if (($h['hookname'] ?? '') === 'header_head' && ($h['template'] ?? '') === 'wsratings_hook_header_head') {
            $hasHeaderHead = true;
        }
    }
}
check($hasDisplayControls, "wsratings_hook_conversation_post_above not registered in hooks datastore!");
check($hasHeaderHead, "wsratings_hook_header_head not registered in hooks datastore!");
echo "✓ Both template hooks registered in hooks datastore!\n";

// 3. Find a conversation / thread node
$thread = $assertor->getRow('vBForum:node', [
    'contenttypeid' => \vB_Types::instance()->getContentTypeID('vBForum_Text'),
    'starter' => 0 // In vB6 starter=nodeid for starter posts or starter=0 depending on query
]);

// If not found with starter=0, find any text node
if (!$thread) {
    $thread = $assertor->getRow('vBForum:node', [
        'contenttypeid' => \vB_Types::instance()->getContentTypeID('vBForum_Text'),
    ]);
}
check((bool)$thread, "A thread/text node must exist in the database for testing");
$threadNodeId = (int) $thread['nodeid'];
echo "✓ Found thread node ID: {$threadNodeId} ('{$thread['title']}')\n";

// 4. Test Rating API as Admin (User 1)
require_once DIR . '/packages/vblaravel/bootstrap.php';
\VbLaravel\Runtime::boot(DIR . '/packages/vblaravel');

$rateResult = \vB_Api::instance('wsratings:rating')->rate($threadNodeId, 5);
echo "✓ Admin rate(5) returned: " . json_encode($rateResult) . "\n";
check($rateResult['success'] === true, "Rating must return success=true for admin");
check((int) $rateResult['user_rating'] === 5, "user_rating must be 5");
check($rateResult['total_ratings'] >= 1, "Rating total_ratings must be >= 1");
check($rateResult['average_rating'] >= 1.0 && $rateResult['average_rating'] <= 5.0, "Rating average_rating must be between 1 and 5");

$getStats = \vB_Api::instance('wsratings:rating')->get($threadNodeId);
echo "✓ Admin get() stats returned: " . json_encode($getStats) . "\n";
check($getStats['nodeid'] === $threadNodeId, "Stats nodeid must match");
check($getStats['user_rating'] === 5, "Admin user_rating must match 5");

// 5. Test Rating API as Guest (User 0) - Must be denied
$request->createSessionForUser(0);
vb()->resetUser();

$guestDenied = false;
try {
    $guestRes = \vB_Api::instance('wsratings:rating')->rate($threadNodeId, 4);
    if (isset($guestRes['errors']) && $guestRes['errors'][0][0] === 'no_permission') {
        $guestDenied = true;
    }
} catch (\vB_Exception_Api $e) {
    if ($e->getMessage() === 'no_permission') {
        $guestDenied = true;
    }
}
check($guestDenied, "Guest rating must be rejected with 'no_permission'");
echo "✓ Guest rating denied with 'no_permission'!\n";

// 6. Test HTTP AJAX POST as unauthenticated guest
$httpRes = @file_get_contents('http://127.0.0.1/vbtest/ajax/api/wsratings.rating/rate', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nX-Requested-With: XMLHttpRequest\r\nUser-Agent: Mozilla/5.0\r\n",
        'content' => http_build_query(['nodeid' => $threadNodeId, 'rating' => 4]),
        'ignore_errors' => true,
    ],
]));
check($httpRes !== false && str_contains($httpRes, 'no_permission'), "HTTP AJAX POST by guest must return no_permission error");
echo "✓ HTTP AJAX POST /ajax/api/wsratings.rating/rate rejected guest with 'no_permission': {$httpRes}\n";

// 7. Verify rendered HTML on live thread page
$threadUrl = "http://127.0.0.1/vbtest/node/{$threadNodeId}";
echo "✓ Fetching live thread page: {$threadUrl}\n";

$pageHtml = file_get_contents($threadUrl);
check($pageHtml !== false, "Failed to fetch {$threadUrl}");
check(str_contains($pageHtml, 'ws-thread-ratings'), "Thread page must contain 'ws-thread-ratings'");
check(str_contains($pageHtml, 'ws-star'), "Thread page must contain 'ws-star'");
check(str_contains($pageHtml, 'wsratings.rating/rate'), "Thread page must contain rating API endpoint in script");
echo "✓ Real thread page rendered 'ws-thread-ratings' and interactive star elements in HTML!\n";

echo "\n========================================\n";
echo "   RATINGS ADDON VERIFIED SUCCESSFULLY! \n";
echo "========================================\n";
