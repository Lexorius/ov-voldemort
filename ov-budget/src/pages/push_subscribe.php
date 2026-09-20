<?php
declare(strict_types=1);

/**
 * Browser meldet sich für Push-Nachrichten an.
 * Antwort ist JSON, weil der Aufruf aus dem Hintergrund kommt.
 */
$user = require_login();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'fehler' => 'Nur POST.']);
    return;
}

try {
    $abo = json_decode(post_str('abo'), true);
    if (!is_array($abo)) {
        throw new WebPushException('Das Abo war nicht lesbar.');
    }
    $id = push_subscribe((int)$user['id'], $abo, post_str('geraet'));
    audit('push.angemeldet', 'push', $id, mb_substr((string)($abo['endpoint'] ?? ''), 0, 80));
    echo json_encode(['ok' => true, 'id' => $id]);
} catch (Throwable $ex) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fehler' => $ex->getMessage()]);
}
