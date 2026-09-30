<?php
declare(strict_types=1);
/*
 * Mitschnitt der Stein.APP-Antworten: Schwärzen, Dateinamen, Aufräumen.
 */
session_start();

// Mitschnitte in einen eigenen Ordner legen, nicht in die Anwendung
$tmp = sys_get_temp_dir() . '/ovb-stein-test';
@mkdir($tmp, 0777, true);
putenv('OVB_UPLOAD_DIR=' . $tmp);
$_ENV['OVB_UPLOAD_DIR'] = $tmp;

$GLOBALS['settings'] = [
    'waehrung' => 'EUR',
    'stein_aktiv' => '1', 'stein_api_key' => 'sk-geheim-1234567890', 'stein_bu_id' => '21',
    'stein_debug' => '1',
];
require __DIR__ . '/stub_db.php';

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/uploads.php';
require $app . '/src/lib/vehicles.php';
require $app . '/src/lib/stein.php';


$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- Schwärzen ---------- */
$feld = static fn(string $json, string $name) => json_decode(stein_redact($json, ''), true)[$name] ?? null;

$check('Schlüssel im Text wird geschwärzt',
    $feld(stein_redact('{"x":"sk-geheim-1234567890"}', 'sk-geheim-1234567890'), 'x') === '***');
$check('Schlüssel auch außerhalb von JSON', !str_contains(
    stein_redact('Authorization: Bearer sk-geheim-1234567890 ...', 'sk-geheim-1234567890'), 'sk-geheim'));
$check('kurzer Schlüssel wird nicht gesucht (sonst trifft er überall)',
    $feld(stein_redact('{"a":"abc"}', 'abc'), 'a') === 'abc');
$check('ohne Schlüssel bleibt der Inhalt', $feld('{"label":"GKW 1"}', 'label') === 'GKW 1');
$check('kein JSON bleibt unverändert',
    stein_redact('The requested URL was not found on this webserver.', '')
    === 'The requested URL was not found on this webserver.');

$bu = '{"id":10,"name":"OV Muster","webhookSecret":"abc123","webhookUrl":"https://example.org/hook"}';
$check('webhookSecret geschwärzt', $feld($bu, 'webhookSecret') === '***');
$check('Adresse bleibt lesbar', $feld($bu, 'webhookUrl') === 'https://example.org/hook');
$check('Name bleibt lesbar', $feld($bu, 'name') === 'OV Muster');
$check('Zahl bleibt Zahl', $feld($bu, 'id') === 10);

foreach (['apiKey', 'api_key', 'API_KEY', 'token', 'accessToken', 'refresh-token', 'password',
          'passwort', 'authorization', 'secret', 'webhook_secret', 'Secret'] as $name) {
    $roh = json_encode([$name => 'geheim-wert', 'label' => 'GKW 1']);
    if (($feld($roh, $name)) !== '***') {
        $fail++;
        echo "FAIL: Feld $name nicht geschwärzt
";
    } else {
        $ok++;
    }
}
foreach (['radioName', 'issi', 'label', 'monkeyName', 'category', 'comment'] as $name) {
    $roh = json_encode([$name => 'harmlos']);
    if (($feld($roh, $name)) !== 'harmlos') {
        $fail++;
        echo "FAIL: Feld $name unnötig geschwärzt
";
    } else {
        $ok++;
    }
}

// Auch tief verschachtelt und in Listen
$tief = '{"items":[{"bu":{"webhookSecret":"xyz","name":"OV"}}]}';
$roh = json_decode(stein_redact($tief, ''), true);
$check('geschachteltes Geheimnis', $roh['items'][0]['bu']['webhookSecret'] === '***');
$check('geschachtelter Name bleibt', $roh['items'][0]['bu']['name'] === 'OV');

// Liste von Fahrzeugen bleibt eine Liste
$liste = stein_redact('[{"id":1,"label":"GKW"},{"id":2,"label":"MTW"}]', '');
$check('Liste bleibt Liste', json_decode($liste, true)[1]['label'] === 'MTW');

/* ---------- Dateinamen ---------- */
$name = stein_debug_name('/assets/', mktime(14, 5, 9, 9, 18, 2026));
$check('Dateiname mit Zeitstempel', $name === '20260918-140509_assets.json');
$check('Dateiname ohne heikle Zeichen',
    stein_debug_name('../../etc/passwd') === date('Ymd-His') . '_etc-passwd.json');
$check('Pfad wird geprüft', stein_debug_path('../../etc/passwd') === null);
$check('erfundener Name wird abgewiesen', stein_debug_path('beliebig.json') === null);

/* ---------- Ablegen, finden, herunterladen ---------- */
stein_debug_delete_all();
$datei = stein_debug_save('/assets/', ['buIds' => '21'], 200,
    '[{"id":206,"label":"MTW","name":"THW 99020","secret":"nicht-zeigen"}]');
$check('Datei angelegt', is_string($datei) && str_ends_with($datei, '_assets.json'));
$inhalt = (string)file_get_contents(stein_debug_dir() . '/' . $datei);
$gelesen = json_decode($inhalt, true);
$check('Datei ist gültiges JSON', is_array($gelesen));
$check('Anfrage und Status vermerkt', $gelesen['http'] === 200 && $gelesen['anfrage'] === '/assets/?buIds=21');
$check('Zeitpunkt vermerkt', !empty($gelesen['zeitpunkt']));
$check('Nutzdaten enthalten', $gelesen['antwort'][0]['name'] === 'THW 99020');
$check('Geheimnis im Inhalt geschwärzt', $gelesen['antwort'][0]['secret'] === '***');
$check('Schlüssel steht nirgends', !str_contains($inhalt, 'sk-geheim'));

// Fehlerseite statt JSON
$fehlerDatei = stein_debug_save('/assets/', [], 404, 'The requested URL was not found on this webserver.');
$fehler = json_decode((string)file_get_contents(stein_debug_dir() . '/' . $fehlerDatei), true);
$check('Fehlerantwort als Text festgehalten', $fehler['http'] === 404
    && str_contains((string)$fehler['antwort'], 'not found'));
$check('Pfad zur Datei gefunden', stein_debug_path($datei) !== null);
$check('in der Liste', in_array($datei, array_column(stein_debug_files(), 'name'), true));

/* ---------- Der Schalter steuert das Mitschreiben ---------- */
$check('Schalter eingeschaltet erkannt', stein_debug_on() === true);
stein_debug_delete_all();

/* ---------- Nur ein Stein-Abgleich zur Zeit ---------- */
// Der Abgleich ist fällig (noch nie abgerufen) – aber ein anderer Prozess hält die Sperre
$GLOBALS['besetzt'] = ['ovb_stein_sync'];
$GLOBALS['inserts'] = [];
$res = stein_sync();
$check('läuft schon einer: kein zweiter Abruf', $res['status'] === 'wartet' && str_contains($res['message'], 'läuft gerade'));
$check('dabei nichts protokolliert', $GLOBALS['inserts'] === []);
$GLOBALS['besetzt'] = [];

/* ---------- Aufräumen ---------- */
for ($i = 1; $i <= 25; $i++) {
    file_put_contents(stein_debug_dir() . '/' . sprintf('202609%02d-120000_assets.json', $i), '{}');
}
$check('25 Dateien vorhanden', count(stein_debug_files()) === 25);
stein_debug_cleanup();
$übrig = stein_debug_files();
$check('auf 20 gekürzt', count($übrig) === 20);
$check('die neuesten bleiben', $übrig[0]['name'] === '20260925-120000_assets.json');
$check('die ältesten sind weg', !in_array('20260901-120000_assets.json', array_column($übrig, 'name'), true));
$check('alles löschen', stein_debug_delete_all() === 20 && stein_debug_files() === []);

echo "$ok bestanden, $fail fehlgeschlagen\n";
