<?php
declare(strict_types=1);
/*
 * Einladungen: der ganze Weg vom Code auf dem Papier bis zur Rückmeldung
 * in der Gästeliste – und die Frage, was dabei auf dem Connector liegt.
 *
 * Der Test übernimmt die Rolle des Browsers: Er verschlüsselt genau so, wie
 * einladung.js es tut, und OV-Multitool muss es lesen können.
 */
$tmp = sys_get_temp_dir() . '/ovb-einladung-test';
@mkdir($tmp, 0777, true);
$leeren = static function (string $ordner) use (&$leeren): void {
    foreach (glob($ordner . '/*') ?: [] as $f) {
        if (is_dir($f)) { $leeren($f); @rmdir($f); } else { @unlink($f); }
    }
};
$leeren($tmp);

// Connector-Bibliothek mit eigenem Datenordner laden
$quelle = (string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/connector.php');
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
@mkdir(__DIR__ . '/alt', 0777, true);
file_put_contents(__DIR__ . '/alt/connector_einladung.php', $quelle);
require __DIR__ . '/alt/connector_einladung.php';

/* ---------------- OV-Multitool-Seite mit Attrappen ---------------- */
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'haushaltsjahr' => '2026',
    'veranstaltung_intervall_minuten' => '10'];
$GLOBALS['merker'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['gaeste'] = [];
$GLOBALS['events'] = [];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text',
                    'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM events WHERE connector_id')) { return $GLOBALS['events']; }
    if (str_contains($sql, 'FROM event_guests WHERE event_id')) {
        return array_values(array_filter($GLOBALS['gaeste'],
            static fn($g) => (int)$g['event_id'] === (int)$p[0]));
    }
    if (str_contains($sql, 'FROM event_guests g')) { return array_values($GLOBALS['gaeste']); }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM events e')) {
        foreach ($GLOBALS['events'] as $e) {
            if ((int)$e['id'] === (int)$p[0]) { return $e; }
        }
    }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) {
    return str_contains($sql, 'FROM settings') ? ($GLOBALS['merker'][$p[0] ?? ''] ?? $d) : $d;
}
function db_exec(string $sql, array $p = []): int {
    if (str_contains($sql, 'INTO settings')) { $GLOBALS['merker'][$p[0]] = (string)$p[1]; }
    return 1;
}
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 1; }
function db_update(string $t, array $d, string $w, array $p): int {
    $GLOBALS['updates'][] = [$t, $d, $p];
    if ($t === 'event_guests') {
        foreach ($GLOBALS['gaeste'] as $i => $g) {
            if ((int)$g['id'] === (int)$p[0]) { $GLOBALS['gaeste'][$i] = array_merge($g, $d); }
        }
    }
    return 1;
}
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
function upload_dir(): string { return sys_get_temp_dir() . '/ovb-einladung-test'; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/webpush.php';
require $app . '/src/lib/vehicles.php';
require $app . '/src/lib/vehicle_files.php';
require $app . '/src/lib/contacts.php';
require $app . '/src/lib/divera_vehicles.php';
require $app . '/src/lib/notify.php';
require $app . '/src/lib/connector.php';
require $app . '/src/lib/events.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ================= Kopplung ================= */
[$ovPem, $ovPunkt] = p256_keypair();
con_koppeln(con_kopplungscode(), con_b64u($ovPunkt));
$check('gekoppelt', con_gekoppelt());

/* ================= Veranstaltung und Codes anmelden ================= */
$event = [
    'id' => 12, 'titel' => 'Jahresabschlussfeier', 'beginn' => '2026-12-05 18:00:00',
    'ende' => '2026-12-05 23:00:00', 'ort' => 'Unterkunft', 'hinweis' => 'Bitte pünktlich',
    'rueckmeldung_bis' => '2026-11-28', 'status' => 'geplant', 'begleiter_max' => 2,
    'kommentare_erlaubt' => 1, 'vertretung_erlaubt' => 1, 'code_laenge' => 6,
    'connector_id' => 1,
];
$GLOBALS['events'] = [$event];
$GLOBALS['gaeste'] = [
    ['id' => 1, 'event_id' => 12, 'contact_id' => 5, 'name' => 'Anna Beispiel', 'code' => 'AB23CD',
     'status' => 'offen', 'begleiter' => 0, 'vertretung' => '', 'kommentar' => null],
    ['id' => 2, 'event_id' => 12, 'contact_id' => 6, 'name' => 'Bernd Muster', 'code' => 'XY79ZK',
     'status' => 'offen', 'begleiter' => 0, 'vertretung' => '', 'kommentar' => null],
];

$c = ['id' => 1, 'name' => 'Test', 'url' => 'https://ov.example.de/connector',
      'kurz_url' => 'https://i.example.de', 'is_active' => 1, 'fuer_fahrzeuge' => 0,
      'fuer_veranstaltungen' => 1, 'pem' => $ovPem, 'pubkey' => con_b64u($ovPunkt), 'server_pub' => 'x'];

$paket = connector_event_paket($c);
$check('eine Veranstaltung im Paket', count($paket['veranstaltungen']) === 1);
$check('zwei Einladungen im Paket', count($paket['einladungen']) === 2);
$check('nur Prüfsummen der Codes',
    array_keys($paket['einladungen']) === [event_code_kennung('AB23CD'), event_code_kennung('XY79ZK')]);
$check('keine Namen im Paket', !str_contains((string)json_encode($paket), 'Anna')
    && !str_contains((string)json_encode($paket), 'AB23CD'));
$check('Prüfsummen beidseitig gleich', con_code_kennung('AB23CD') === event_code_kennung('AB23CD'));

$res = con_veranstaltungen_setzen($paket['veranstaltungen'], $paket['einladungen']);
$check('angenommen', $res === ['veranstaltungen' => 1, 'einladungen' => 2]);

$abgelegt = (string)file_get_contents($tmp . '/daten/einladungen.json');
$check('kein Code im Klartext auf dem Server', !str_contains($abgelegt, 'AB23CD'));
$check('keine Namen auf dem Server',
    !str_contains((string)file_get_contents($tmp . '/daten/veranstaltungen.json'), 'Anna'));
$check('Titel steht dort (die Seite muss ihn zeigen)',
    str_contains((string)file_get_contents($tmp . '/daten/veranstaltungen.json'), 'Jahresabschlussfeier'));

/* ================= Einladung nachschlagen ================= */
$einladung = con_einladung('AB23CD');
$check('Code führt zur Veranstaltung', $einladung !== null
    && $einladung['veranstaltung']['titel'] === 'Jahresabschlussfeier');
$check('Kleinschreibung geht auch', con_einladung('ab23cd') !== null);
$check('falscher Code führt nirgendwohin', con_einladung('ZZ99ZZ') === null);
$check('Unsinn wird abgewiesen', con_einladung('../etc/passwd') === null && con_einladung('') === null);
$check('Einstellungen kamen mit', (int)$einladung['veranstaltung']['begleiter_max'] === 2
    && (int)$einladung['veranstaltung']['kommentare'] === 1
    && (int)$einladung['veranstaltung']['vertretung'] === 1);

/* ================= Frist ================= */
$check('vor der Frist geht es', !con_frist_vorbei($einladung['veranstaltung'], '2026-11-01'));
$check('am Tag der Frist noch', !con_frist_vorbei($einladung['veranstaltung'], '2026-11-28'));
$check('danach nicht mehr', con_frist_vorbei($einladung['veranstaltung'], '2026-11-29'));
$check('ohne Frist immer offen', !con_frist_vorbei(['bis' => ''], '2099-01-01'));

/* ================= Der Browser antwortet ================= */
$browser = static function (array $inhalt) use ($ovPunkt): string {
    [$pem, $punkt] = p256_keypair();
    $gemeinsam = openssl_pkey_derive(p256_public_pem($ovPunkt), openssl_pkey_get_private($pem));
    $salz = random_bytes(16);
    $abgeleitet = hash_hkdf('sha256', $gemeinsam, 44, CONNECTOR_INFO, $salz);
    $tag = '';
    $geheim = openssl_encrypt((string)json_encode($inhalt), 'aes-256-gcm', substr($abgeleitet, 0, 32),
        OPENSSL_RAW_DATA, substr($abgeleitet, 32, 12), $tag);
    return b64u_encode("\x01" . $punkt . $salz . $geheim . $tag);
};

$paketA = $browser(['status' => 'zusage', 'begleiter' => 1, 'vertretung' => '',
                    'kommentar' => 'Komme gern', 'zeit' => time()]);
con_rueckmeldung_ablegen('AB23CD', $paketA);
$ordner = $tmp . '/daten/rueckmeldungen/' . con_code_kennung('AB23CD');
$check('Rückmeldung liegt beim Connector', count(glob($ordner . '/*.json')) === 1);
$aufPlatte = (string)file_get_contents(glob($ordner . '/*.json')[0]);
$check('nichts Lesbares auf der Platte',
    !str_contains($aufPlatte, 'zusage') && !str_contains($aufPlatte, 'Komme gern'));

try { con_rueckmeldung_ablegen('ZZ99ZZ', $paketA); $check('unbekannter Code abgewiesen', false); }
catch (ConException $e) { $check('unbekannter Code abgewiesen', true); }
try { con_rueckmeldung_ablegen('AB23CD', str_repeat('x', 9000)); $check('zu große Rückmeldung abgewiesen', false); }
catch (ConException $e) { $check('zu große Rückmeldung abgewiesen', true); }

// Nach der Frist nimmt der Connector nichts mehr an
con_veranstaltungen_setzen(
    [array_merge($paket['veranstaltungen'][0], ['bis' => '2020-01-01'])],
    $paket['einladungen']
);
try { con_rueckmeldung_ablegen('AB23CD', $paketA); $check('nach der Frist zu', false); }
catch (ConException $e) { $check('nach der Frist zu', str_contains($e->getMessage(), 'frist')); }
con_veranstaltungen_setzen($paket['veranstaltungen'], $paket['einladungen']);

/* ================= Abholen und eintragen ================= */
$paketB = $browser(['status' => 'absage', 'begleiter' => 0, 'vertretung' => '',
                    'kommentar' => '', 'zeit' => time()]);
con_rueckmeldung_ablegen('XY79ZK', $paketB);

$geholt = con_rueckmeldungen_abholen();
$check('beide abgeholt', count($geholt) === 2);
$check('nach dem Abholen gelöscht', count(glob($ordner . '/*.json')) === 0);
$check('Rückmeldung trägt die Prüfsumme des Codes',
    in_array(con_code_kennung('AB23CD'), array_column($geholt, 'code'), true));

$klar = connector_entschluesseln($geholt[0]['daten'], $ovPem, ['status']);
$check('OV-Multitool kann lesen', in_array($klar['status'], ['zusage', 'absage'], true));

// Der ganze Weg: Antwort auf den Gast anwenden
foreach ($geholt as $m) {
    $gast = null;
    foreach ($GLOBALS['gaeste'] as $g) {
        if (event_code_kennung((string)$g['code']) === $m['code']) { $gast = $g; }
    }
    $check('Gast über die Prüfsumme gefunden', $gast !== null);
    $daten = connector_entschluesseln((string)$m['daten'], $ovPem, ['status']);
    event_guest_antwort($event, $gast, $daten, 'einladung');
}
$anna = $GLOBALS['gaeste'][0];
$bernd = $GLOBALS['gaeste'][1];
$check('Anna hat zugesagt', $anna['status'] === 'zusage' && (int)$anna['begleiter'] === 1
    && $anna['kommentar'] === 'Komme gern' && $anna['quelle'] === 'einladung');
$check('Bernd hat abgesagt', $bernd['status'] === 'absage' && (int)$bernd['begleiter'] === 0);

$s = event_stats($GLOBALS['gaeste']);
$check('gezählt: eine Zusage mit Begleitung', $s['zusagen'] === 1 && $s['personen'] === 2 && $s['absagen'] === 1);

/* ================= Zurückgezogene Einladung ================= */
con_rueckmeldung_ablegen('AB23CD', $paketA);
con_veranstaltungen_setzen($paket['veranstaltungen'], [event_code_kennung('XY79ZK') => event_kennung($event)]);
$check('Code weg, wartende Rückmeldungen weg', !is_dir($ordner) && con_einladung('AB23CD') === null);

/* ================= Was der Connector annimmt ================= */
$res = con_veranstaltungen_setzen([['kennung' => 'unsinn', 'titel' => 'X']], ['abc' => 'def']);
$check('krumme Angaben fallen raus', $res === ['veranstaltungen' => 0, 'einladungen' => 0]);

$lang = con_veranstaltung_saeubern([
    'kennung' => event_kennung($event), 'titel' => str_repeat('T', 500),
    'hinweis' => str_repeat('H', 5000), 'status' => 'erfunden', 'begleiter_max' => 999,
    'bis' => 'morgen', 'beginn' => "2026-12-05 18:00:00\x00böse",
]);
$check('Titel und Hinweis werden gekürzt',
    mb_strlen($lang['titel']) === 200 && mb_strlen($lang['hinweis']) === 2000);
$check('unbekannter Status wird geplant', $lang['status'] === 'geplant');
$check('Begleiter werden begrenzt', $lang['begleiter_max'] === 50);
$check('krummes Datum fällt weg', $lang['bis'] === '');
$check('Steuerzeichen raus', !str_contains($lang['beginn'], "\x00"));

/* ================= Takt ================= */
$GLOBALS['merker']['veranstaltung_letzter_abruf'] = (string)(time() - 300);
$check('nach fünf Minuten noch nicht fällig', !connector_events_due());
$GLOBALS['merker']['veranstaltung_letzter_abruf'] = (string)(time() - 3600);
$check('ohne Connector auch dann nicht', !connector_events_due());

echo "$ok bestanden, $fail fehlgeschlagen\n";
