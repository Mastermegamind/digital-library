<?php
require_once __DIR__ . '/../includes/functions.php';

$failures = 0;

function assertTrue($condition, string $message): void {
    global $failures;
    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}" . PHP_EOL;
    }
}

$token = get_csrf_token();
assertTrue(verify_csrf_token($token) === true, 'CSRF token should validate');
assertTrue(verify_csrf_token('invalid') === false, 'CSRF token should fail with invalid value');

$uploadResult = handle_file_upload(null, ['jpg'], __DIR__);
assertTrue($uploadResult['path'] === null, 'Handle upload with no file should return null path');

$rateKey = 'test:rate:' . uniqid('', true);
$rateState = rate_limit_is_blocked($rateKey);
assertTrue($rateState['blocked'] === false, 'Rate limiter should allow first request');
$rateResult = rate_limit_register_failure($rateKey, 1, 60, 60);
assertTrue($rateResult['blocked'] === true, 'Rate limiter should block after limit reached');
rate_limit_reset($rateKey);

if ($failures > 0) {
    exit(1);
}
echo "OK" . PHP_EOL;
