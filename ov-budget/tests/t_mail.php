<?php
declare(strict_types=1);
/*
 * E-Mail: Adressen, Kopfzeilen, Nachricht – und der ganze SMTP-Dialog gegen
 * einen nachgemachten Server (hilfe_smtp.php) in einem zweiten Prozess.
 */
$GLOBALS['settings'] = ['mail_aktiv' => '1', 'mail_host' => '127.0.0.1', 'mail_port' => '0', 'mail_sicherheit' => 'keine',
    'mail_benutzer' => 'anna', 'mail_passwort' => 'geheim', 'mail_absender' => 'multitool@ov.example', 'mail_absender_name' => 'OV Musterstadt'];
$GLOBALS['merker'] = [];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'FROM settings') ? ($GLOBALS['merker'][$p[0] ?? ''] ?? $d) : $d; }
function db_exec(string $sql, array $p = []): int { if (str_contains($sql, 'INTO settings')) { $GLOBALS['merker'][$p[0]] = (string)$p[1]; } return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'mail'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Adressen und Kopfzeilen ---------- */
$check('gültige Adressen', mail_adresse_gueltig('anna@ov.example') && mail_adresse_gueltig(' ob@thw-musterstadt.de '));
$check('ungültige Adressen', !mail_adresse_gueltig('anna') && !mail_adresse_gueltig("a@b.de\r\nBcc: x@y.de") && !mail_adresse_gueltig('<a@b.de>') && !mail_adresse_gueltig(''));
$check('ASCII bleibt, Umlaute werden kodiert', mail_kopf_kodieren('Test 1') === 'Test 1'
    && mail_kopf_kodieren("Abstimmung „Sommerfest\"\r\nX") === '=?UTF-8?B?' . base64_encode('Abstimmung „Sommerfest" X') . '?=');
$check('Name mit Adresse', mail_adresse_mit_name('a@b.de', 'Max <Muster>') === 'Max Muster <a@b.de>' && mail_adresse_mit_name('a@b.de', '') === 'a@b.de');

$n = mail_nachricht_bauen('multitool@ov.example', 'OV Musterstadt', 'ob@example.de', 'Anna Ärztin', 'Ergebnis „Sommerfest"', "Zeile 1\n.Punktzeile\nÄöü", 1792900000);
$check('Kopfzeilen vollständig, CRLF, Base64', str_contains($n, "From: OV Musterstadt <multitool@ov.example>\r\n")
    && str_contains($n, 'To: =?UTF-8?B?' . base64_encode('Anna Ärztin') . '?= <ob@example.de>')
    && str_contains($n, 'Subject: =?UTF-8?B?') && str_contains($n, 'Content-Transfer-Encoding: base64')
    && str_contains($n, 'Message-ID: <') && str_contains($n, '@ov.example>') && str_contains($n, 'Date: ' . date('r', 1792900000)));
[$kopf, $koerper] = explode("\r\n\r\n", $n, 2);
$check('Text kommt unversehrt aus dem Base64', base64_decode(str_replace("\r\n", '', $koerper)) === "Zeile 1\n.Punktzeile\nÄöü");
$check('kein nacktes LF in der Nachricht', !preg_match('/(?<!\r)\n/', $n));

/* ---------- Nicht eingerichtet ---------- */
$GLOBALS['settings']['mail_aktiv'] = '0';
settings_reset_cache();
$check('ausgeschaltet heißt nicht eingerichtet', !mail_eingerichtet());
try { mail_senden('ob@example.de', 'x', 'y'); $check('ausgeschaltet: wirft', false); }
catch (MailException $e) { $check('ausgeschaltet: wirft', str_contains($e->getMessage(), 'nicht eingerichtet')); }
try { mail_senden('kaputt', 'x', 'y'); $check('kaputte Adresse: wirft', false); }
catch (MailException $e) { $check('kaputte Adresse: wirft', str_contains($e->getMessage(), 'ungültig')); }

/* ---------- Attrappe ---------- */
$gefangen = [];
$GLOBALS['mail_attrappe'] = static function (string $an, string $betreff, string $text, string $name) use (&$gefangen): void { $gefangen[] = [$an, $betreff, $text, $name]; };
mail_senden('ob@example.de', 'Hallo', 'Text', 'OB');
$check('Attrappe fängt ab', $gefangen === [['ob@example.de', 'Hallo', 'Text', 'OB']]);
unset($GLOBALS['mail_attrappe']);

/* ---------- Der Dialog gegen den nachgemachten Server ---------- */
$GLOBALS['settings']['mail_aktiv'] = '1';
$port = random_int(20000, 40000);
$GLOBALS['settings']['mail_port'] = (string)$port;
settings_reset_cache();
$protokoll = sys_get_temp_dir() . '/ovb-smtp-' . $port . '.log';
@unlink($protokoll);
@unlink($protokoll . '.bereit');
$proc = proc_open([PHP_BINARY, __DIR__ . '/hilfe_smtp.php', (string)$port, $protokoll], [], $pipes);
$bereit = false;
for ($i = 0; $i < 100 && !$bereit; $i++) { usleep(50000); $bereit = is_file($protokoll . '.bereit'); }
$check('Attrappe läuft', $bereit);
if ($bereit) {
    try {
        mail_senden('ob@example.de', 'Ergebnis „Sommerfest"', "Hallo\n.Punkt\nEnde", 'Anna');
        $check('Versand ohne Fehler', true);
    } catch (Throwable $e) {
        $check('Versand ohne Fehler: ' . $e->getMessage(), false);
    }
    for ($i = 0; $i < 40 && proc_get_status($proc)['running']; $i++) { usleep(50000); }
    $log = (string)@file_get_contents($protokoll);
    $check('EHLO, Anmeldung mit Base64, Absender, Empfänger', str_contains($log, 'EHLO ') && str_contains($log, 'AUTH LOGIN')
        && str_contains($log, '<Benutzer ' . base64_encode('anna') . '>') && str_contains($log, '<Passwort ' . base64_encode('geheim') . '>')
        && str_contains($log, 'MAIL FROM:<multitool@ov.example>') && str_contains($log, 'RCPT TO:<ob@example.de>') && str_contains($log, 'DATA') && str_contains($log, 'QUIT'));
    $check('Nachricht mit Kopf und Subject im Dialog', str_contains($log, 'Subject: =?UTF-8?B?') && str_contains($log, 'To: Anna <ob@example.de>'));
    $check('Zustand gemerkt', (int)state_get('mail_zuletzt', '0') > 0 && state_get('mail_letzter_fehler', '') === '');
}
@proc_terminate($proc);
@proc_close($proc);
@unlink($protokoll);
@unlink($protokoll . '.bereit');

/* ---------- Niemand da ---------- */
$GLOBALS['settings']['mail_port'] = '1';   // tcpmux – da hört sicher nichts
settings_reset_cache();
try { mail_senden('ob@example.de', 'x', 'y'); $check('kein Server: wirft', false); }
catch (MailException $e) { $check('kein Server: wirft und merkt den Fehler', str_contains($e->getMessage(), 'Keine Verbindung') && state_get('mail_letzter_fehler', '') !== ''); }

echo "$ok bestanden, $fail fehlgeschlagen\n";
