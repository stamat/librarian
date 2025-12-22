<?php
require_once 'lib/utils.php';

$secret = loadini(__DIR__ . '/../.env')['GITHUB_WEBHOOK_SECRET'] ?? '';
$signatureHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
$payload = file_get_contents('php://input');
$log = __DIR__ . '/deployhook.log';

function logmsg($m){
  global $log;
  file_put_contents($log, date('c').' '.$m.PHP_EOL, FILE_APPEND);
}

if (!$payload) {
  http_response_code(400);
  echo "No payload";
  logmsg("No payload");
  exit;
}

$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
if (!hash_equals($expected, $signatureHeader)) {
  http_response_code(403);
  echo "Invalid signature";
  logmsg("Invalid signature");
  exit;
}

if ($event !== 'push') {
  http_response_code(200);
  echo "Ignored event: $event";
  logmsg("Ignored event: $event");
  exit;
}

$data = json_decode($payload, true);
$ref = $data['ref'] ?? '';
$repo = $data['repository']['full_name'] ?? '';
logmsg("Received push to {$ref} on {$repo}");

if ($ref !== 'refs/heads/main') {
    http_response_code(200);
    echo "Ignored ref: {$ref}";
    logmsg("Ignored ref (not main): {$ref}");
    exit;
}

$repoPath = __DIR__;
$cmd = sprintf('/usr/bin/git -C %s pull --rebase 2>&1', escapeshellarg($repoPath));
exec($cmd, $output, $return);

logmsg("Cmd: {$cmd} Return: {$return} Output: ".implode("\n", $output));

if ($return === 0) {
    http_response_code(200);
    echo "OK";
} else {
    http_response_code(500);
    echo "Deploy failed";
}