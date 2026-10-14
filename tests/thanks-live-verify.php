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
define('THIS_SCRIPT', 'verify_thanks');

require_once DIR . '/vb/vb.php';
\vB::init();

$request = new \vB_Request_Cli();
\vB::setRequest($request);
$request->createSessionForUser(1); // Admin

require_once DIR . '/includes/adminfunctions.php';
require_once DIR . '/includes/adminfunctions_product.php';

$assertor = \vB::getDbAssertor();

echo "========================================\n";
echo "   Live Thanks Addon Verification       \n";
echo "========================================\n\n";

// 1. Ensure wsthanks product is installed & datastore rebuilt
$xmlFile = DIR . '/packages/wsthanks/xml/product_wsthanks.xml';
check(is_file($xmlFile), "product_wsthanks.xml must exist at {$xmlFile}");

ob_start();
install_product(file_get_contents($xmlFile), true, false, false);
ob_end_clean();

\vB::getDatastore()->build_options();
\vB_Api::instanceInternal('Hook')->buildHookDatastore();

// 2. Check hook datastore
$hookList = \vB::getDatastore()->getValue('hooks');
$hasPostBelow = false;
if (is_array($hookList)) {
    foreach ($hookList as $h) {
        if (($h['hookname'] ?? '') === 'conversation_post_below' && ($h['template'] ?? '') === 'wsthanks_hook_conversation_post_below') {
            $hasPostBelow = true;
            break;
        }
    }
}
check($hasPostBelow, "wsthanks_hook_conversation_post_below not registered in hooks datastore!");
echo "✓ conversation_post_below template hook registered in datastore!\n";

// 3. Find a conversation / thread node
$thread = $assertor->getRow('vBForum:node', [
    'contenttypeid' => \vB_Types::instance()->getContentTypeID('vBForum_Text'),
]);
check((bool)$thread, "A thread/text node must exist in the database for testing");
$threadNodeId = (int) $thread['nodeid'];
echo "✓ Found thread node ID: {$threadNodeId} ('{$thread['title']}')\n";

// 4. Test Thank API as Admin (User 1)
require_once DIR . '/packages/vblaravel/bootstrap.php';
require_once DIR . '/packages/wsthanks/product.php';
\VbLaravel\Runtime::boot(DIR . '/packages/vblaravel');

// Clean existing thank for test predictability
\WsThanks\Models\Thank::where('nodeid', $threadNodeId)->where('userid', 1)->delete();

// Initial get
$initStats = \vB_Api::instance('wsthanks:thank')->get($threadNodeId);
echo "✓ Initial get() stats: " . json_encode($initStats) . "\n";
check($initStats['nodeid'] === $threadNodeId, "Node ID must match");
check($initStats['user_thanked'] === false, "User should not be thanked yet");
$baseCount = (int) $initStats['count'];

// Toggle on
$toggleOn = \vB_Api::instance('wsthanks:thank')->toggle($threadNodeId);
echo "✓ Toggle ON returned: " . json_encode($toggleOn) . "\n";
check($toggleOn['success'] === true, "Toggle ON must succeed");
check($toggleOn['thanked'] === true, "thanked must be true");
check($toggleOn['count'] === $baseCount + 1, "Count must increment by 1");

// Get stats after toggle on
$afterStats = \vB_Api::instance('wsthanks:thank')->get($threadNodeId);
check($afterStats['user_thanked'] === true, "user_thanked must be true after toggle ON");
check($afterStats['count'] === $baseCount + 1, "Count must match incremented value");

// Toggle off
$toggleOff = \vB_Api::instance('wsthanks:thank')->toggle($threadNodeId);
echo "✓ Toggle OFF returned: " . json_encode($toggleOff) . "\n";
check($toggleOff['success'] === true, "Toggle OFF must succeed");
check($toggleOff['thanked'] === false, "thanked must be false after toggle OFF");
check($toggleOff['count'] === $baseCount, "Count must revert back");

// 5. Test Thank API as Guest (User 0) - Must be denied
$request->createSessionForUser(0);
vb()->resetUser();

$guestDenied = false;
try {
    $guestRes = \vB_Api::instance('wsthanks:thank')->toggle($threadNodeId);
    if (isset($guestRes['errors']) && $guestRes['errors'][0][0] === 'no_permission') {
        $guestDenied = true;
    }
} catch (\vB_Exception_Api $e) {
    if ($e->getMessage() === 'no_permission') {
        $guestDenied = true;
    }
}
check($guestDenied, "Guest toggle must be rejected with 'no_permission'");
echo "✓ Guest toggle denied with 'no_permission'!\n";

// 6. Test HTTP AJAX POST as unauthenticated guest
$httpRes = @file_get_contents('http://127.0.0.1/vbtest/ajax/api/wsthanks.thank/toggle', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nX-Requested-With: XMLHttpRequest\r\nUser-Agent: Mozilla/5.0\r\n",
        'content' => http_build_query(['nodeid' => $threadNodeId]),
        'ignore_errors' => true,
    ],
]));
check($httpRes !== false && str_contains($httpRes, 'no_permission'), "HTTP AJAX POST by guest must return no_permission error");
echo "✓ HTTP AJAX POST /ajax/api/wsthanks.thank/toggle rejected guest with 'no_permission': {$httpRes}\n";

// 7. Verify rendered HTML on live thread page
$threadUrl = "http://127.0.0.1/vbtest/node/{$threadNodeId}";
echo "✓ Fetching live thread page: {$threadUrl}\n";

$pageHtml = file_get_contents($threadUrl);
check($pageHtml !== false, "Failed to fetch {$threadUrl}");
check(str_contains($pageHtml, 'ws-thanks-container'), "Thread page must contain 'ws-thanks-container'");
check(str_contains($pageHtml, 'wsthanks.thank/toggle'), "Thread page must contain toggle API endpoint in script");
check(str_contains($pageHtml, 'wsthanks_thank') || str_contains($pageHtml, 'Thank'), "Thread page must contain Thank button text or phrase");
echo "✓ Real thread page rendered 'ws-thanks-container' and interactive Thank button in HTML!\n";

echo "\n========================================\n";
echo "    THANKS ADDON VERIFIED SUCCESSFULLY! \n";
echo "========================================\n";
