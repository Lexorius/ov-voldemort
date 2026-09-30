<?php
declare(strict_types=1);
/*
 * Zweiter Faktor: Base32, Codes nach RFC 6238, Zeitfenster und Wiederholung,
 * Backup-Codes, Pflicht je Rolle, Ablauf der Anmeldung, Ansichten.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'totp_pflicht' => 'leitung', 'login_max_versuche' => '3', 'login_sperre_minuten' => '15'];
$GLOBALS['updates'] = [];
$GLOBALS['attempts'] = 0;
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return $GLOBALS['user'] ?? null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    return str_contains($sql, 'login_attempts') ? $GLOBALS['attempts'] : $d;
}
function db_exec(string $sql, array $p = []): int {
    if (str_contains($sql, 'INSERT INTO login_attempts')) { $GLOBALS['attempts']++; }
    if (str_contains($sql, 'DELETE FROM login_attempts')) { $GLOBALS['attempts'] = 0; }
    return 1;
}
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int {
    $GLOBALS['updates'][] = [$t, $d];
    if ($t === 'users' && isset($GLOBALS['user'])) { $GLOBALS['user'] = array_merge($GLOBALS['user'], $d); }
    return 1;
}
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'auth', 'totp', 'view'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Base32 ---------- */
$check('Base32 hin und zurück', totp_base32_decode(totp_base32_encode('12345678901234567890')) === '12345678901234567890');
$check('Base32 nach RFC 4648', totp_base32_encode('foobar') === 'MZXW6YTBOI' && totp_base32_decode('MZXW6YTBOI======') === 'foobar');
$check('Base32 verzeiht Kleinschrift und Leerzeichen', totp_base32_decode('mzxw 6ytb oi') === 'foobar');
$check('Base32 lehnt fremde Zeichen ab', totp_base32_decode('MZXW6YTB0I') === '' && totp_base32_decode('') === '');
$s = totp_secret_neu();
$check('neues Geheimnis: 32 Zeichen Base32', strlen($s) === 32 && preg_match('/^[A-Z2-7]+$/', $s) === 1 && $s !== totp_secret_neu());
$check('lesbar in Vierergruppen', totp_secret_lesbar('ABCDEFGHIJKL') === 'ABCD EFGH IJKL');

/* ---------- Codes nach RFC 6238 (SHA1, Geheimnis 12345678901234567890) ---------- */
$rfc = totp_base32_encode('12345678901234567890');
$check('T=59', totp_code($rfc, 59) === '287082');
$check('T=1111111109', totp_code($rfc, 1111111109) === '081804');
$check('T=1234567890', totp_code($rfc, 1234567890) === '005924');
$check('T=20000000000', totp_code($rfc, 20000000000) === '353130');
$check('acht Stellen', totp_code($rfc, 59, 30, 8) === '94287082');

/* ---------- Prüfen: Fenster, Wiederholung ---------- */
$t = 1234567890;
$code = totp_code($rfc, $t);
$check('aktueller Code passt', totp_pruefen($rfc, $code, $t) === intdiv($t, 30));
$check('mit Leerzeichen und Bindestrich', totp_pruefen($rfc, substr($code, 0, 3) . ' ' . substr($code, 3), $t) !== null);
$check('Code des vorigen Schritts geht noch', totp_pruefen($rfc, totp_code($rfc, $t - 30), $t) === intdiv($t, 30) - 1);
$check('Code des nächsten Schritts geht noch', totp_pruefen($rfc, totp_code($rfc, $t + 30), $t) !== null);
$check('zwei Schritte daneben nicht', totp_pruefen($rfc, totp_code($rfc, $t - 60), $t) === null && totp_pruefen($rfc, totp_code($rfc, $t + 60), $t) === null);
$check('derselbe Code kein zweites Mal', totp_pruefen($rfc, $code, $t, intdiv($t, 30)) === null);
$check('älterer Schritt nach jüngerem nicht', totp_pruefen($rfc, totp_code($rfc, $t - 30), $t, intdiv($t, 30)) === null);
$check('falscher Code', totp_pruefen($rfc, '000000', $t) === null || totp_code($rfc, $t) === '000000');
$check('zu kurz, leer, kaputtes Geheimnis', totp_pruefen($rfc, '12345', $t) === null && totp_pruefen($rfc, '', $t) === null && totp_pruefen('', $code, $t) === null);

/* ---------- Adresse für die App ---------- */
$uri = totp_uri('ABC234', 'anna', 'OV-Multitool OV Musterstadt');
$check('otpauth-Adresse', $uri === 'otpauth://totp/OV-Multitool%20OV%20Musterstadt:anna?secret=ABC234&issuer=OV-Multitool%20OV%20Musterstadt&algorithm=SHA1&digits=6&period=30');
$check('Doppelpunkt im Aussteller wird ersetzt', !str_contains(explode('?', totp_uri('X', 'a', 'A:B'))[0], 'A%3AB'));

/* ---------- Backup-Codes ---------- */
$codes = totp_backup_neu();
$check('acht Codes der Form abcd-2345', count($codes) === 8 && count(array_unique($codes)) === 8
    && count(array_filter($codes, static fn($c) => preg_match('/^[a-z2-9]{4}-[a-z2-9]{4}$/', $c) === 1)) === 8);
$check('keine verwechselbaren Zeichen', !preg_match('/[01oli]/', implode('', $codes)));
$hashes = totp_backup_hashes($s, $codes);
$check('Hashes verraten die Codes nicht', count($hashes) === 8 && !in_array($codes[0], $hashes, true) && strlen($hashes[0]) === 64);
$rest = totp_backup_einloesen($hashes, $s, strtoupper($codes[2]));
$check('Code einlösen (Groß/klein egal): sieben bleiben', $rest !== null && count($rest) === 7 && !in_array($hashes[2], $rest, true));
$check('derselbe Code kein zweites Mal', totp_backup_einloesen($rest, $s, $codes[2]) === null);
$check('Code ohne Bindestrich geht', totp_backup_einloesen($hashes, $s, str_replace('-', '', $codes[5])) !== null);
$check('falscher Code, falsches Geheimnis', totp_backup_einloesen($hashes, $s, 'zzzz-zzzz') === null && totp_backup_einloesen($hashes, 'ANDERES', $codes[0]) === null);
$check('sechsstelliger App-Code ist kein Backup-Code', totp_backup_einloesen($hashes, $s, '123456') === null);

/* ---------- Pflicht je Rolle ---------- */
$check('Vorgabe leitung: Admin und Leitung', totp_pflichtig(['role' => 'admin']) && totp_pflichtig(['role' => 'leitung']) && !totp_pflichtig(['role' => 'user']));
$check('nur admin', totp_pflichtig(['role' => 'admin'], 'admin') && !totp_pflichtig(['role' => 'leitung'], 'admin'));
$check('alle / keine', totp_pflichtig(['role' => 'user'], 'alle') && !totp_pflichtig(['role' => 'admin'], 'keine'));
$check('aktiv nur mit Geheimnis', totp_aktiv(['totp_aktiv' => 1, 'totp_secret' => 'X']) && !totp_aktiv(['totp_aktiv' => 1, 'totp_secret' => '']) && !totp_aktiv([]));
$st = totp_status(['role' => 'leitung', 'totp_aktiv' => 1, 'totp_secret' => 'X', 'totp_seit' => '2026-10-01 10:00:00', 'totp_backup' => json_encode(['a', 'b', 'c'])]);
$check('Status', $st['aktiv'] && $st['backup_rest'] === 3 && $st['pflicht'] && $st['seit'] === '2026-10-01 10:00:00');

/* ---------- Einschalten, Anmeldung in zwei Schritten ---------- */
$GLOBALS['user'] = ['id' => 7, 'username' => 'anna', 'role' => 'leitung', 'is_active' => 1,
    'password_hash' => password_hash('richtig!!!', PASSWORD_DEFAULT), 'totp_aktiv' => 0, 'totp_secret' => '', 'totp_letzter' => 0, 'totp_backup' => null];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];
[$ok1, , $zf] = auth_attempt('anna', 'richtig!!!');
$check('ohne zweiten Faktor sofort angemeldet', $ok1 && $zf === false && ($_SESSION['uid'] ?? 0) === 7);

$_SESSION = [];
$klar = totp_aktivieren(7, $rfc);
$check('Einschalten speichert Geheimnis und Hashes', totp_aktiv($GLOBALS['user']) && count($klar) === 8
    && count(json_decode((string)$GLOBALS['user']['totp_backup'], true)) === 8 && (int)$GLOBALS['user']['totp_letzter'] > 0);

[$ok1, , $zf] = auth_attempt('anna', 'richtig!!!');
$check('mit zweitem Faktor: Passwort ok, Sitzung noch nicht', $ok1 && $zf === true && !isset($_SESSION['uid']) && auth_zweiter_faktor_offen());
[$ok2, $msg] = auth_zweiter_faktor('000000');
$check('falscher Code abgewiesen und gezählt', !$ok2 && $msg !== '' && $GLOBALS['attempts'] === 1 && !isset($_SESSION['uid']));
[$ok2, $msg] = auth_zweiter_faktor(totp_code($rfc, time()));
$check('Code der Einrichtung gilt nicht noch einmal', !$ok2 && $GLOBALS['attempts'] === 2);
[$ok2, , $art] = auth_zweiter_faktor(totp_code($rfc, time() + 30));
$check('richtiger Code: angemeldet', $ok2 && $art === 'app' && ($_SESSION['uid'] ?? 0) === 7 && !isset($_SESSION['totp_pending']) && $GLOBALS['attempts'] === 0);

$_SESSION = [];
auth_attempt('anna', 'richtig!!!');
[$ok2, , $art] = auth_zweiter_faktor($klar[3]);
$check('Backup-Code meldet an und verbraucht sich', $ok2 && $art === 'backup' && count(json_decode((string)$GLOBALS['user']['totp_backup'], true)) === 7);
$_SESSION = [];
auth_attempt('anna', 'richtig!!!');
[$ok2] = auth_zweiter_faktor($klar[3]);
$check('verbrauchter Backup-Code nicht noch einmal', !$ok2);

$_SESSION = [];
auth_attempt('anna', 'richtig!!!');
$GLOBALS['attempts'] = 3;
[$ok2, $msg] = auth_zweiter_faktor(totp_code($rfc, time() + 30));
$check('Sperre greift auch beim Code', !$ok2 && str_contains($msg, 'Fehlversuche') && !isset($_SESSION['totp_pending']));
$GLOBALS['attempts'] = 0;

$_SESSION = [];
[$ok2, $msg] = auth_zweiter_faktor('123456');
$check('ohne offene Anmeldung abgelaufen', !$ok2 && str_contains($msg, 'abgelaufen'));
$_SESSION = ['totp_pending' => ['uid' => 7, 'username' => 'anna', 'seit' => time() - 301]];
$check('nach fünf Minuten abgelaufen', !auth_zweiter_faktor_offen() && !isset($_SESSION['totp_pending']));

totp_abschalten(7);
$check('Abschalten leert alles', !totp_aktiv($GLOBALS['user']) && $GLOBALS['user']['totp_backup'] === null);

/* ---------- Ansichten ---------- */
$_SESSION = [];
$html = render_partial('login', ['error' => '', 'username' => '', 'zweiterFaktor' => true]);
$check('Anmeldung fragt nach dem Code', str_contains($html, 'name="code"') && str_contains($html, 'value="totp"') && !str_contains($html, 'name="password"'));
$html = render_partial('login', ['error' => '', 'username' => '', 'zweiterFaktor' => false]);
$check('Anmeldung normal', str_contains($html, 'name="password"') && !str_contains($html, 'name="code"'));
$html = render_partial('partials/profil_totp', ['totp' => ['status' => totp_status(['role' => 'user']), 'setup' => '', 'uri' => '', 'codes' => []]]);
$check('Profil: einrichten anbieten', str_contains($html, 'totp_start') && !str_contains($html, 'Pflicht</strong>'));
$html = render_partial('partials/profil_totp', ['totp' => ['status' => totp_status(['role' => 'admin']), 'setup' => '', 'uri' => '', 'codes' => []]]);
$check('Profil: Pflicht genannt', str_contains($html, 'Pflicht</strong>'));
$html = render_partial('partials/profil_totp', ['totp' => ['status' => totp_status(['role' => 'user']), 'setup' => $rfc, 'uri' => $uri, 'codes' => []]]);
$check('Profil: QR-Code und Schlüssel', str_contains($html, 'data-qr="' . e($uri) . '"') && str_contains($html, totp_secret_lesbar($rfc)) && !empty($GLOBALS['ovb_qr_js']));
$html = render_partial('partials/profil_totp', ['totp' => ['status' => totp_status(['role' => 'leitung', 'totp_aktiv' => 1, 'totp_secret' => 'X', 'totp_backup' => '["a"]']), 'setup' => '', 'uri' => '', 'codes' => $codes]]);
$check('Profil: aktiv, Codes einmalig, Pflichtige ohne Abschalten', str_contains($html, 'eingeschaltet') && str_contains($html, $codes[0]) && str_contains($html, '1 Backup-Code übrig') && !str_contains($html, 'totp_aus'));
$html = render_partial('partials/profil_totp', ['totp' => ['status' => totp_status(['role' => 'user', 'totp_aktiv' => 1, 'totp_secret' => 'X', 'totp_backup' => '[]']), 'setup' => '', 'uri' => '', 'codes' => []]]);
$check('Profil: freiwillige dürfen abschalten', str_contains($html, 'totp_aus') && str_contains($html, '0 Backup-Codes übrig'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
