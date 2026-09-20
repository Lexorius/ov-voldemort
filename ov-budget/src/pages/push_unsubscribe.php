<?php
declare(strict_types=1);

/** Browser meldet sich wieder ab */
$user = require_login();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'fehler' => 'Nur POST.']);
    return;
}

$abo = json_decode(post_str('abo'), true);
$endpoint = trim((string)($abo['endpoint'] ?? post_str('endpoint')));
if ($endpoint !== '') {
    push_unsubscribe($endpoint, (int)$user['id']);
    audit('push.abgemeldet', 'push', null, mb_substr($endpoint, 0, 80));
}
echo json_encode(['ok' => true]);
