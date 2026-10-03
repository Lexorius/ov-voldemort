<?php
declare(strict_types=1);
/* Benachrichtigungen über Home Assistant: Empfänger, Warteschlange, Tageslauf */
$GLOBALS['settings'] = [
    'waehrung' => 'EUR', 'ha_benachrichtigung_aktiv' => '1', 'ha_benachrichtigung_stunde' => '7',
    'ha_benachrichtigung_basis_url' => 'https://ha.example.de/ingress/ovbudget/',
    'notify_aufgabe_neu' => '1', 'notify_aufgabe_faellig' => '1', 'notify_besprechung' => '0',
    'fahrzeug_warn_tage' => '30',
];
$GLOBALS['merker'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['zeilen'] = [];      // SQL-Teil => Zeilen
$GLOBALS['gesendet'] = [];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    foreach ($GLOBALS['zeilen'] as $teil => $fn) {
        if (str_contains($sql, $teil)) { return $fn($p); }
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    return str_contains($sql, 'FROM settings') ? ($GLOBALS['merker'][$p[0] ?? ''] ?? $d) : $d;
}
function db_exec(string $sql, array $p = []): int {
    if (str_contains($sql, 'INTO settings')) { $GLOBALS['merker'][$p[0]] = (string)$p[1]; }
    return 1;
}
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 100 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/vehicles.php';
require $app . '/src/lib/webpush.php';

// notify.php ohne die echte Home-Assistant-Anbindung laden
@mkdir(__DIR__ . '/alt');
$quelle = (string)file_get_contents($app . '/src/lib/notify.php');
$quelle = (string)preg_replace('/function ha_api\(.*?\n}\n/s', '', $quelle, 1);
file_put_contents(__DIR__ . '/alt/notify_ohne_api.php', $quelle);
function ha_api(string $pfad, ?array $body = null, int $timeout = 10): array {
    $GLOBALS['gesendet'][] = [$pfad, $body];
    // Die Dienstliste bleibt erreichbar – gestört ist nur der eigentliche Aufruf
    if ($pfad !== 'services' && ($GLOBALS['api_fehler'] ?? false) === true) {
        throw new RuntimeException('Home Assistant antwortete auf ' . $pfad . ' mit HTTP 400: 400: Bad Request');
    }
    return $pfad === 'services'
        ? [['domain' => 'persistent_notification', 'services' => ['create' => []]],
           ['domain' => 'notify', 'services' => ['mobile_app_pixel' => [], 'persistent_notification' => []]]]
        : [];
}
require __DIR__ . '/alt/notify_ohne_api.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};
$ist = function (string $name, mixed $got, mixed $want) use (&$ok, &$fail) {
    if ($got === $want) { $ok++; return; }
    $fail++; echo "FEHL  $name\n      erwartet: " . var_export($want, true) . "\n      erhalten: " . var_export($got, true) . "\n";
};

/* ---------- Ereignisse und Einstellungen ---------- */
$ereignisse = notify_ereignisse();
$check('vierzehn Ereignisse', count($ereignisse) === 14);
$seed = (string)file_get_contents($app . '/sql/seed.sql');
$fehlend = [];
foreach (array_keys($ereignisse) as $key) {
    if (!str_contains($seed, "('notify_" . $key . "',")) {
        $fehlend[] = $key;
    }
}
$check('jedes Ereignis hat eine Einstellung: ' . implode(', ', $fehlend), $fehlend === []);
$check('eingeschaltet', notify_ereignis_aktiv('aufgabe_neu'));
$check('einzeln abschaltbar', !notify_ereignis_aktiv('besprechung'));

/* ---------- Unbekanntes Ziel ---------- */
$GLOBALS['api_fehler'] = true;
$GLOBALS['gesendet'] = [];
try {
    ha_notify_send('mobile_app_gibtsnicht', 'T', 'X');
    $check('unbekanntes Ziel meldet sich', false);
} catch (Throwable $e) {
    $check('Hinweis auf das fehlende Ziel', str_contains($e->getMessage(), 'kennt kein notify.mobile_app_gibtsnicht'));
    $check('vorhandene Ziele genannt', str_contains($e->getMessage(), 'notify.mobile_app_pixel'));
    $check('ursprüngliche Meldung bleibt', str_contains($e->getMessage(), 'HTTP 400'));
}
$GLOBALS['gesendet'] = [];
try {
    ha_notify_send('mobile_app_pixel', 'T', 'X');
    $check('bekanntes Ziel meldet sich', false);
} catch (Throwable $e) {
    $check('bei bekanntem Ziel kein Zusatz', !str_contains($e->getMessage(), 'kennt kein'));
}
$GLOBALS['api_fehler'] = false;

/* ---------- Zugang zu Home Assistant ---------- */
define('OVB_HA_TOKEN_DATEI', __DIR__ . '/alt/ha_token_test');
@unlink(OVB_HA_TOKEN_DATEI);
putenv('SUPERVISOR_TOKEN');
putenv('HASSIO_TOKEN');
$ist('ohne alles kein Token', ha_token(), ['', '']);
file_put_contents(OVB_HA_TOKEN_DATEI, "aus-der-datei
");
$ist('Token aus der Datei', ha_token(), ['aus-der-datei', 'Datei']);
@unlink(OVB_HA_TOKEN_DATEI);
putenv('HASSIO_TOKEN=alt');
$ist('alter Name geht auch', ha_token(), ['alt', 'HASSIO_TOKEN']);
putenv('SUPERVISOR_TOKEN=neu');
$ist('Umgebung hat Vorrang', ha_token(), ['neu', 'SUPERVISOR_TOKEN']);
putenv('SUPERVISOR_TOKEN');
putenv('HASSIO_TOKEN');
@mkdir(__DIR__ . '/alt/s6', 0777, true);
define('OVB_HA_S6', __DIR__ . '/alt/s6');
file_put_contents(OVB_HA_S6 . '/SUPERVISOR_TOKEN', "aus-s6
");
$ist('Token aus der s6-Umgebung', ha_token(), ['aus-s6', 's6']);
file_put_contents(OVB_HA_TOKEN_DATEI, "aus-der-datei
");
$ist('abgelegte Datei hat Vorrang vor s6', ha_token(), ['aus-der-datei', 'Datei']);
@unlink(OVB_HA_TOKEN_DATEI);
@unlink(OVB_HA_S6 . '/SUPERVISOR_TOKEN');
$ist('wieder nichts', ha_token(), ['', '']);

$start = (string)file_get_contents(dirname(__DIR__) . '/docker/rootfs/run.sh');
$check('Start liest die Container-Umgebung von s6', str_contains($start, 'container_environment'));
$check('Start legt das Token ab', str_contains($start, '/data/ha_token'));
$check('ohne Token wird die Datei entfernt', str_contains($start, 'rm -f /data/ha_token'));

/* ---------- Ziel prüfen ---------- */
foreach (['', 'Mobile_App', 'notify.mobile_app', 'mobile app', '../../hack'] as $boese) {
    try { ha_notify_send($boese, 't', 'x'); $check('Ziel abgewiesen: ' . $boese, false); }
    catch (RuntimeException $e) { $check('Ziel abgewiesen: ' . ($boese ?: '(leer)'), true); }
}
$GLOBALS['gesendet'] = [];
ha_notify_send('mobile_app_pixel', 'Titel', 'Text', 'https://ha/x');
[$pfad, $body] = $GLOBALS['gesendet'][0];
$ist('Dienstpfad', $pfad, 'services/notify/mobile_app_pixel');
$ist('Titel und Text', [$body['title'], $body['message']], ['Titel', 'Text']);
$ist('Link zum Antippen', $body['data']['clickAction'], 'https://ha/x');
$GLOBALS['gesendet'] = [];
ha_notify_send('mobile_app_pixel', 'T', 'X');
$check('ohne Link keine Zusatzdaten', !isset($GLOBALS['gesendet'][0][1]['data']));

$ist('Ziele aus Home Assistant', ha_notify_dienste(true), ['mobile_app_pixel', 'persistent_notification']);
$GLOBALS['gesendet'] = [];
ha_notify_dienste();
$check('Ziele zwischengespeichert', $GLOBALS['gesendet'] === []);

/* ---------- Adresse ---------- */
$ist('Link gebaut', notify_url('?p=todo&id=5'), 'https://ha.example.de/ingress/ovbudget/?p=todo&id=5');
$ist('ohne Pfad nur die Adresse', notify_url(''), 'https://ha.example.de/ingress/ovbudget');
$ist('ohne Adresse kein Link', notify_url('?p=todo&id=5', ''), '');
$ist('Panel-Link bleibt unverändert',
    notify_url('?p=todo&id=5', 'https://x.ui.nabu.casa/hassio/ingress/6c0cd8ac_ov_budget'),
    'https://x.ui.nabu.casa/hassio/ingress/6c0cd8ac_ov_budget');
$ist('App-Verweis bleibt unverändert',
    notify_url('?p=todo&id=5', 'homeassistant://navigate/hassio/ingress/6c0cd8ac_ov_budget'),
    'homeassistant://navigate/hassio/ingress/6c0cd8ac_ov_budget');
$ist('Platzhalter wird gefüllt',
    notify_url('?p=todo&id=5', 'https://ov.example.de/{pfad}'), 'https://ov.example.de/?p=todo&id=5');
$ist('Schrägstrich am Ende egal',
    notify_url('?p=x', 'https://ov.example.de'), 'https://ov.example.de/?p=x');

/* ---------- Empfängerkreise ---------- */
$GLOBALS['zeilen'] = [
    'FROM users WHERE is_active = 1 AND fachgruppe_id' => fn($p) => $p[0] === 4 ? [['id' => 2], ['id' => 3]] : [],
    'FROM user_functions WHERE function_id' => fn($p) => [['user_id' => 9]],
    "role IN ('admin','leitung')" => fn($p) => [['id' => 1], ['id' => 7]],
];
$ist('Aufgabe an eine Person', notify_todo_users(['target_type' => 'user', 'target_id' => 5]), [5]);
$ist('Aufgabe an die Fachgruppe', notify_todo_users(['target_type' => 'fachgruppe', 'target_id' => 4]), [2, 3]);
$ist('Aufgabe an eine Funktion', notify_todo_users(['target_type' => 'funktion', 'target_id' => 8]), [9]);
$ist('ganzer OV geht an die Leitung', notify_todo_users(['target_type' => 'ov', 'target_id' => null]), [1, 7]);

/* ---------- Warteschlange ---------- */
$GLOBALS['zeilen']['WHERE u.id IN'] = fn($p) => array_values(array_filter([
    ['id' => 2, 'display_name' => 'Anna', 'username' => 'anna', 'ha_notify' => 'mobile_app_anna'],
    ['id' => 3, 'display_name' => 'Ben', 'username' => 'ben', 'ha_notify' => 'mobile_app_ben'],
], fn($u) => in_array($u['id'], array_map('intval', $p), true)));

$GLOBALS['inserts'] = [];
$n = notify_queue([2, 3, 2], 'aufgabe_neu', 'Neue Aufgabe', 'Text', '?p=todo&id=9');
$ist('zwei Empfänger, keine Dublette', $n, 2);
$d = $GLOBALS['inserts'][0][1];
$ist('in die Warteschlange', $GLOBALS['inserts'][0][0], 'notifications');
$ist('Empfänger und Ereignis', [$d['user_id'], $d['ereignis'], $d['status']], [2, 'aufgabe_neu', 'offen']);
$ist('Pfad gemerkt, nicht die volle Adresse', $d['url'], '?p=todo&id=9');

$GLOBALS['inserts'] = [];
$ist('abgeschaltetes Ereignis: nichts', notify_queue([2], 'besprechung', 't', 'x'), 0);
$ist('nichts eingereiht', $GLOBALS['inserts'], []);
$ist('unbekannte Person: nichts', notify_queue([99], 'aufgabe_neu', 't', 'x'), 0);
$ist('lange Texte gekürzt', mb_strlen(($GLOBALS['inserts'][0][1] ?? ['titel' => ''])['titel'] ?? ''), 0);
notify_queue([2], 'aufgabe_neu', str_repeat('T', 300), str_repeat('x', 900));
$d = $GLOBALS['inserts'][0][1];
$ist('Titel auf 150', mb_strlen($d['titel']), 150);
$ist('Text auf 500', mb_strlen($d['text']), 500);

/* ---------- Versand ---------- */
$GLOBALS['zeilen']["FROM notifications n"] = fn($p) => [
    ['id' => 11, 'user_id' => 2, 'ereignis' => 'aufgabe_neu', 'titel' => 'T1', 'text' => 'X1',
     'url' => '?p=todo&id=9', 'versuche' => 0, 'ha_notify' => 'mobile_app_anna'],
    ['id' => 12, 'user_id' => 3, 'ereignis' => 'aufgabe_neu', 'titel' => 'T2', 'text' => 'X2',
     'url' => '', 'versuche' => 0, 'ha_notify' => 'mobile_app_ben'],
];
$GLOBALS['gesendet'] = []; $GLOBALS['updates'] = [];
$res = notify_flush();
$ist('beide gesendet', [$res['gesendet'], $res['fehler']], [2, 0]);
$ist('Dienst je Empfänger', array_column($GLOBALS['gesendet'], 0),
    ['services/notify/mobile_app_anna', 'services/notify/mobile_app_ben']);
$ist('Link aus dem Pfad', $GLOBALS['gesendet'][0][1]['data']['url'], 'https://ha.example.de/ingress/ovbudget/?p=todo&id=9');
$ist('ohne Pfad der Weg zur Startseite', $GLOBALS['gesendet'][1][1]['data']['url'],
    'https://ha.example.de/ingress/ovbudget');
$ist('als gesendet vermerkt', $GLOBALS['updates'][0][1]['status'], 'gesendet');
$ist('richtige Zeile', $GLOBALS['updates'][0][2], [11]);

$GLOBALS['api_fehler'] = true;
$GLOBALS['gesendet'] = []; $GLOBALS['updates'] = [];
$res = notify_flush();
$versuche = array_values(array_filter($GLOBALS['gesendet'], fn($g) => str_starts_with($g[0], 'services/notify/')));
$ziele = array_unique(array_column($versuche, 0));
$ist('Abbruch nach dem ersten Empfänger', [$res['gesendet'], $res['fehler'], count($ziele)], [0, 1, 1]);
$check('zweiter Versuch ohne Link', count($versuche) === 2 && !isset($versuche[1][1]['data']));
$ist('bleibt offen für den nächsten Versuch', $GLOBALS['updates'][0][1]['status'], 'offen');
$ist('Versuch gezählt', $GLOBALS['updates'][0][1]['versuche'], 1);
$check('Fehler vermerkt', str_contains($GLOBALS['updates'][0][1]['fehler'], 'HTTP 400'));
$GLOBALS['zeilen']["FROM notifications n"] = fn($p) => [
    ['id' => 11, 'user_id' => 2, 'ereignis' => 'x', 'titel' => 'T', 'text' => 'X', 'url' => '',
     'versuche' => 2, 'ha_notify' => 'mobile_app_anna'],
];
$GLOBALS['updates'] = [];
notify_flush();
$ist('nach drei Versuchen endgültig Fehler', $GLOBALS['updates'][0][1]['status'], 'fehler');
$GLOBALS['api_fehler'] = false;
$GLOBALS['zeilen']["FROM notifications n"] = fn($p) => [];

// Ohne erreichbaren Weg bleibt die Nachricht liegen
$GLOBALS['zeilen']["FROM notifications n"] = fn($p) => [
    ['id' => 20, 'user_id' => 4, 'ereignis' => 'x', 'titel' => 'T', 'text' => 'X', 'url' => '',
     'versuche' => 0, 'ha_notify' => ''],
];
$GLOBALS['gesendet'] = []; $GLOBALS['updates'] = [];
$res = notify_flush();
$ist('kein Weg: nichts gesendet', [$res['gesendet'], $res['fehler'], $GLOBALS['gesendet']], [0, 1, []]);
$check('Grund vermerkt', str_contains($GLOBALS['updates'][0][1]['fehler'], 'Kein Weg'));
$GLOBALS['zeilen']["FROM notifications n"] = fn($p) => [];

/* ---------- Tageslauf ---------- */
$GLOBALS['zeilen']['FROM todos t'] = fn($p) => [
    ['id' => 5, 'titel' => 'Ölwechsel', 'faellig_am' => '2026-09-18', 'target_type' => 'user', 'target_id' => 2],
    ['id' => 6, 'titel' => 'Bericht', 'faellig_am' => '2026-09-20', 'target_type' => 'user', 'target_id' => 2],
];
$morgens = mktime(7, 30, 0, 9, 20, 2026);
$frueh = mktime(5, 0, 0, 9, 20, 2026);
$GLOBALS['merker'] = [];
$GLOBALS['inserts'] = [];
$ist('vor der eingestellten Stunde nichts', notify_taeglich($frueh), 0);
$n = notify_taeglich($morgens);
$check('Aufgaben gemeldet', $n >= 1);
$d = $GLOBALS['inserts'][0][1];
$ist('Sammelmeldung an die Person', $d['user_id'], 2);
$check('überfällige gezählt', str_contains($d['text'], '2 Aufgaben sind fällig, davon 1 überfällig'));
$ist('Link auf die Aufgabenliste', $d['url'], '?p=todos&offen=1');
$GLOBALS['inserts'] = [];
$ist('am selben Tag nur einmal', notify_taeglich($morgens + 3600), 0);
$ist('nichts doppelt eingereiht', $GLOBALS['inserts'], []);

$GLOBALS['zeilen']['FROM todos t'] = fn($p) => [
    ['id' => 5, 'titel' => 'Ölwechsel', 'faellig_am' => '2026-09-21', 'target_type' => 'user', 'target_id' => 2],
];
$GLOBALS['merker'] = [];
notify_taeglich(mktime(7, 0, 0, 9, 21, 2026));
$d = $GLOBALS['inserts'][0][1];
$check('einzelne Aufgabe wird genannt', str_contains($d['text'], '„Ölwechsel" ist heute fällig'));
$ist('Link auf die Aufgabe', $d['url'], '?p=todo&id=5');

echo "$ok bestanden, $fail fehlgeschlagen\n";
