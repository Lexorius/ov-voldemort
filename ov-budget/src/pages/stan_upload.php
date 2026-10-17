<?php
declare(strict_types=1);

/**
 * Stückweiser Upload für STAN: der Browser schickt große Dateien in Stücken
 * (je unter der 16-MB-Grenze der Home-Assistant-Oberfläche), die Anwendung
 * setzt sie zusammen. Antworten sind JSON.
 */
$user = require_login();
header('Content-Type: application/json; charset=UTF-8');
$antwort = static function (array $d, int $code = 200): never {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
if (!in_array((string)($user['role'] ?? ''), ['admin', 'leitung'], true)) {
    $antwort(['ok' => false, 'fehler' => 'STAN-Pakete pflegt die Leitung.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $antwort(['ok' => false, 'fehler' => 'Nur per POST.'], 405);
}
// Was hier schiefgeht, soll der Browser als Text sehen – nicht als nackter HTTP 500
set_error_handler(static function (int $nr, string $text, string $datei, int $zeile): bool {
    throw new ErrorException($text, 0, $nr, $datei, $zeile);
});
try {
    stan_teile_aufraeumen();
    $id = post_str('upload_id');
    $aktion = post_str('action');
} catch (Throwable $e) {
    $antwort(['ok' => false, 'fehler' => 'Fehler in der Anwendung: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'], 500);
}

try {
switch ($aktion) {
    case 'chunk':
        $f = $_FILES['chunk'] ?? null;
        if (!$f || (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $antwort(['ok' => false, 'fehler' => 'Das Stück kam nicht an (Fehlercode ' . (int)($f['error'] ?? -1) . ').'], 400);
        }
        if (!is_uploaded_file((string)$f['tmp_name']) && !defined('OVB_TEST_UPLOADS')) {
            $antwort(['ok' => false, 'fehler' => 'Das Stück wurde nicht über das Formular hochgeladen.'], 400);
        }
        $r = stan_teil_anfuegen($id, post_int('index') ?? -1, post_int('total') ?? 0, post_str('name'), (string)$f['tmp_name']);
        $antwort($r, $r['ok'] ? 200 : 400);

    case 'fertig':
        $datei = stan_teile_fertig($id);
        if ($datei === null) {
            $antwort(['ok' => false, 'fehler' => 'Die Stücke sind nicht vollständig – bitte noch einmal hochladen.'], 400);
        }
        if (post_str('ziel') === 'nachreichen') {
            $v = stan_version_find(post_int('version_id'));
            if (!$v) {
                @unlink($datei['pfad']);
                $antwort(['ok' => false, 'fehler' => 'Diese Version gibt es nicht.'], 404);
            }
            $grund = stan_datei_nachreichen_pfad((int)$v['id'], $datei['pfad'], $datei['name'], $datei['size'], post_str('ordner'));
            if ($grund !== null) {
                @unlink($datei['pfad']);
                $antwort(['ok' => false, 'fehler' => $grund], 400);
            }
            audit('stan.nachgereicht', 'stan', (int)$v['id'], $datei['name']);
            flash('success', '„' . e($datei['name']) . '" zu ' . e(stan_version_text($v)) . ' nachgereicht.');
            $antwort(['ok' => true, 'redirect' => url('stan', ['version' => (int)$v['id']]) . '#dateien']);
        }
        [$neu, $fehler, $hinweise] = stan_version_aus_datei($datei['pfad'], $datei['name'], $datei['size'], post_str('bemerkung'), $user);
        if (!$neu) {
            @unlink($datei['pfad']);
            $antwort(['ok' => false, 'fehler' => implode(' ', $fehler)], 400);
        }
        $v = stan_version_find($neu);
        flash('success', e(sprintf('%s eingespielt – %d Datei(en).', stan_version_text($v ?? ['nummer' => 0]), count(stan_dateien($neu)))));
        foreach ($hinweise as $h) {
            flash('warn', e($h));
        }
        $antwort(['ok' => true, 'redirect' => url('stan', ['version' => $neu])]);

    default:
        $antwort(['ok' => false, 'fehler' => 'Unbekannte Aktion.'], 400);
}
} catch (Throwable $e) {
    $antwort(['ok' => false, 'fehler' => 'Fehler in der Anwendung: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'], 500);
}
