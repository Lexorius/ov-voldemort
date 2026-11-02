<?php
declare(strict_types=1);

/**
 * Fragen an die STAN ohne Seitenwechsel: der Browser schickt die Frage,
 * bekommt die Antwort als JSON und zeichnet die Sprechblasen selbst.
 * Ohne JavaScript bleibt das Formular auf der STAN-Seite.
 */
$user = require_login();
header('Content-Type: application/json; charset=UTF-8');
$antwort = static function (array $d, int $code = 200): never {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $antwort(['ok' => false, 'fehler' => 'Nur per POST.'], 405);
}
if (!stan_chat_aktiv()) {
    $antwort(['ok' => false, 'fehler' => 'Fragen an die STAN sind ausgeschaltet.'], 403);
}
if (post_str('action') === 'chat_leeren') {
    stan_chat_verlauf_leeren();
    $antwort(['ok' => true, 'geleert' => true]);
}

try {
    $versionId = post_str('alle') === '1' || !post_int('version') ? null : post_int('version');
    $eintrag = stan_chat_fragen(post_str('frage'), $versionId, stan_chat_verlauf());
    stan_chat_verlauf_merken($eintrag);
    if (empty($eintrag['wiederholt'])) {
        audit('stan.chat', 'stan', post_int('version'), mb_substr(post_str('frage'), 0, 200));
    }
} catch (KiException $ex) {
    $antwort(['ok' => false, 'fehler' => $ex->getMessage()], 422);
} catch (Throwable $ex) {
    error_log('STAN-Chat: ' . $ex->getMessage());
    $antwort(['ok' => false, 'fehler' => 'Die Anfrage an die KI ist fehlgeschlagen.' . (app_config('debug', false) ? ' ' . $ex->getMessage() : '')], 500);
}

foreach ($eintrag['quellen'] as &$q) {
    $q['url'] = url('stan_file', ['id' => $q['id']]);
    $q['ordner'] = dirname((string)$q['pfad']) !== '.' ? dirname((string)$q['pfad']) : '';
    $q['art_text'] = stan_chat_art_text((string)($q['art'] ?? 'auszuege'));
}
unset($q);
$eintrag['am_text'] = de_datetime((string)$eintrag['am']);
$antwort(['ok' => true, 'eintrag' => $eintrag]);
