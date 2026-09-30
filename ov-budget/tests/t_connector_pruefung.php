<?php
declare(strict_types=1);
/*
 * Ist der Connector sauber? Selbstprüfung dort, Maßstab und Auswertung hier.
 */
$tmp = sys_get_temp_dir() . '/ovb-pruefung-test';
@mkdir($tmp, 0777, true);
$leeren = static function (string $o) use (&$leeren): void {
    foreach (glob($o . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
        if (in_array(basename($f), ['.', '..'], true)) { continue; }
        if (is_dir($f)) { $leeren($f); @rmdir($f); } else { @unlink($f); }
    }
};
$leeren($tmp);

// Connector-Kopie mit Ablage im Testordner
$quelle = (string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/connector.php');
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
@mkdir(__DIR__ . '/alt', 0777, true);
file_put_contents(__DIR__ . '/alt/connector_pruefung.php', $quelle);
require __DIR__ . '/alt/connector_pruefung.php';

// OV-Multitool-Seite
$GLOBALS['settings'] = ['connector_pruefung_taeglich' => '1'];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'webpush', 'connector'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Maßstab passt zu den echten Dateien ---------- */
$echt = con_manifest_erzeugen(dirname(__DIR__, 2) . '/connector');
$manifest = connector_manifest();
$check('Maßstab vorhanden', $manifest['dateien'] !== [] && $manifest['version'] === CON_VERSION);
$check('Maßstab ist aktuell (sonst: php bin/manifest.php)',
    array_map(static fn($d) => $d['sha256'], $echt['dateien']) === array_map(static fn($d) => $d['sha256'], $manifest['dateien']));
$check('Maßstab umfasst Einstieg, .htaccess, Skripte und Seiten',
    isset($manifest['dateien']['public/index.php'], $manifest['dateien']['public/.htaccess'],
          $manifest['dateien']['public/assets/melden.js'], $manifest['dateien']['src/seite_zaehler.php']));
$check('manifest.json des Connectors stimmt überein',
    json_decode((string)file_get_contents(dirname(__DIR__, 2) . '/connector/manifest.json'), true)['dateien'] == $manifest['dateien']);

/* ---------- Selbstprüfung auf einem nachgebauten Connector ---------- */
$w = $tmp . '/con';
@mkdir($w . '/public/assets', 0777, true);
@mkdir($w . '/src', 0777, true);
file_put_contents($w . '/public/index.php', '<?php echo 1;');
file_put_contents($w . '/public/assets/melden.js', 'js');
file_put_contents($w . '/src/connector.php', '<?php');
file_put_contents($w . '/manifest.json', json_encode(con_manifest_erzeugen($w)));
$p = con_pruefung($w);
$check('Selbstprüfung listet die Dateien', array_keys($p['dateien']) === ['public/assets/melden.js', 'public/index.php', 'src/connector.php']);
$check('sauberer Nachbau: nichts Fremdes, keine Alarme',
    $p['fremd'] === [] && array_filter($p['befunde'], static fn($b) => $b['stufe'] === 'alarm') === []);

// Fremde Datei einschleusen, Ausführbares in daten/
file_put_contents($w . '/public/shell.php', '<?php system($_GET["c"]);');
file_put_contents($tmp . '/daten/x.php', '<?php');
@mkdir($tmp . '/daten/meldungen', 0777, true);
file_put_contents($tmp . '/daten/meldungen/tief.phar', 'x');
$p = con_pruefung($w);
$alarme = array_map(static fn($b) => $b['text'], array_filter($p['befunde'], static fn($b) => $b['stufe'] === 'alarm'));
$check('fremde Datei erkannt', $p['fremd'] === ['public/shell.php']);
$check('PHP in daten/ erkannt', count(array_filter($alarme, static fn($t) => str_contains($t, 'x.php'))) === 1);
$check('Ausführbares tief in daten/ erkannt', count(array_filter($alarme, static fn($t) => str_contains($t, 'tief.phar'))) === 1);
$check('erlaubte Ablagedateien gelten nicht als fremd', con_daten_erwartet('kopplung.json', false)
    && con_daten_erwartet('limit.json.lock', false) && con_daten_erwartet('meldungen', true)
    && !con_daten_erwartet('backdoor.php', false) && !con_daten_erwartet('sonstwas', true));

/* ---------- Auswertung (rein) ---------- */
$soll = ['version' => '1.4.0', 'dateien' => [
    'public/index.php' => ['sha256' => 'aaa', 'bytes' => 1],
    'public/assets/melden.js' => ['sha256' => 'bbb', 'bytes' => 1],
    'src/connector.php' => ['sha256' => 'ccc', 'bytes' => 1],
]];
$gut = ['version' => '1.4.0', 'dateien' => [
    'public/index.php' => ['sha256' => 'aaa'], 'public/assets/melden.js' => ['sha256' => 'bbb'], 'src/connector.php' => ['sha256' => 'ccc'],
], 'fremd' => [], 'befunde' => []];
$netzGut = ['js' => ['public/assets/melden.js' => true], 'daten_offen' => false, 'unsigniert' => false, 'kopfzeilen' => [], 'hsts' => true];
$e = connector_pruefung_auswerten($gut, $soll, $netzGut);
$check('alles gut: sauber', $e['stufe'] === 'sauber' && $e['befunde'] === [] && $e['js_geprueft'] === 1);

$boese = $gut;
$boese['dateien']['src/connector.php']['sha256'] = 'zzz';
$boese['dateien']['public/shell.php'] = ['sha256' => 'x'];
unset($boese['dateien']['public/assets/melden.js']);
$e = connector_pruefung_auswerten($boese, $soll, $netzGut);
$texte = implode(' | ', array_map(static fn($b) => $b['text'], $e['befunde']));
$check('geändert, fremd und fehlt erkannt: Alarm', $e['stufe'] === 'alarm' && str_contains($texte, 'weicht vom Maßstab ab: src/connector.php')
    && str_contains($texte, 'Fremde Datei auf dem Server: public/shell.php') && str_contains($texte, 'fehlt auf dem Server: public/assets/melden.js'));

// Server behauptet, alles sei in Ordnung – aber liefert ein anderes Skript aus
$netzLuegt = $netzGut;
$netzLuegt['js']['public/assets/melden.js'] = false;
$e = connector_pruefung_auswerten($gut, $soll, $netzLuegt);
$check('Netzsicht schlägt die Selbstprüfung', $e['stufe'] === 'alarm' && str_contains($e['befunde'][0]['text'], 'Ausgeliefertes Skript weicht ab'));

$e = connector_pruefung_auswerten($gut, $soll, $netzGut + ['daten_offen' => true, 'unsigniert' => true]);
$e = connector_pruefung_auswerten($gut, $soll, ['js' => [], 'daten_offen' => true, 'unsigniert' => true, 'kopfzeilen' => ['Content-Security-Policy'], 'hsts' => false]);
$texte = implode(' | ', array_map(static fn($b) => $b['text'], $e['befunde']));
$check('offene Ablage und unsignierte Antwort sind Alarm', $e['stufe'] === 'alarm' && str_contains($texte, 'kopplung.json') && str_contains($texte, 'ohne Signatur'));
$check('Kopfzeilen und HSTS sind Hinweise', str_contains($texte, 'Kopfzeile fehlt: Content-Security-Policy') && str_contains($texte, 'Strict-Transport-Security'));

$e = connector_pruefung_auswerten(['version' => '1.3.0'] + $gut, $soll, $netzGut);
$check('andere Fassung ist ein Hinweis', $e['stufe'] === 'hinweis' && str_contains($e['befunde'][0]['text'], '1.3.0'));
$e = connector_pruefung_auswerten(['version' => '1.4.0', 'dateien' => [], 'fremd' => ['public/x.php'], 'befunde' => [['stufe' => 'alarm', 'text' => 'Ausführbare Datei in der Ablage: x.php']]], ['version' => '', 'dateien' => []], $netzGut);
$check('ohne Maßstab zählt die Selbstprüfung', $e['stufe'] === 'alarm' && count($e['befunde']) === 3);

$e = connector_pruefung_auswerten($gut, $soll, ['js' => ['public/assets/melden.js' => null], 'daten_offen' => null, 'unsigniert' => null, 'kopfzeilen' => [], 'hsts' => null]);
$check('nicht erreichbar ist nur ein Hinweis', $e['stufe'] === 'hinweis');

$leeren($tmp);
echo "$ok bestanden, $fail fehlgeschlagen\n";
