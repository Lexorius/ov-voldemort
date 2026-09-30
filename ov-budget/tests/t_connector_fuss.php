<?php
declare(strict_types=1);
/* Connector: Betreiber, Impressum, Datenschutz – Ablage, Säuberung, Fußzeile, Übertragung */
$tmp = sys_get_temp_dir() . '/ovb-fuss-test';
@mkdir($tmp, 0777, true);
foreach (glob($tmp . '/daten/*') ?: [] as $f) { if (is_file($f)) { @unlink($f); } }
$quelle = (string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/connector.php');
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
@mkdir(__DIR__ . '/alt', 0777, true);
file_put_contents(__DIR__ . '/alt/connector_fuss.php', $quelle);
require __DIR__ . '/alt/connector_fuss.php';

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Säuberung ---------- */
$check('https-Adresse bleibt', con_url_sauber('https://www.thw-musterstadt.de/impressum') === 'https://www.thw-musterstadt.de/impressum');
$check('http erlaubt', con_url_sauber('http://verein.example/ds') === 'http://verein.example/ds');
$check('javascript: fliegt raus', con_url_sauber('javascript:alert(1)') === '');
$check('Anführungszeichen und Winkel fliegen raus', con_url_sauber('https://x.de/"onclick=') === '' && con_url_sauber('https://x.de/<b>') === '');
$check('Steuerzeichen fliegen raus', con_url_sauber("https://x.de/a\nb") === '' && con_url_sauber('https://x.de/a b') === '');
$check('zu lang fliegt raus', con_url_sauber('https://x.de/' . str_repeat('a', 300)) === '');

$neu = con_einstellungen_setzen(['betreiber' => "THW OV Musterstadt\x00<script>", 'impressum_url' => 'https://x.de/impressum',
    'datenschutz_url' => 'ftp://x.de/ds', 'fremd' => 'egal']);
$check('Betreiber ohne Steuerzeichen, Datenschutz ohne https leer', $neu['betreiber'] === 'THW OV Musterstadt <script>'
    && $neu['impressum_url'] === 'https://x.de/impressum' && $neu['datenschutz_url'] === '' && !isset($neu['fremd']));
$check('abgelegt und wieder lesbar', con_einstellungen() === $neu);
$check('einstellungen.json gilt in der Ablage nicht als fremd', con_daten_erwartet('einstellungen.json', false));

/* ---------- Fußzeile ---------- */
ob_start(); require dirname(__DIR__, 2) . '/connector/src/fuss.php'; $html = ob_get_clean();
$check('Fußzeile mit Betreiber und Impressum, Datenschutz fehlt', str_contains($html, 'THW OV Musterstadt &lt;script&gt;')
    && str_contains($html, 'href="https://x.de/impressum"') && !str_contains($html, 'Datenschutz'));
con_einstellungen_setzen([]);
ob_start(); require dirname(__DIR__, 2) . '/connector/src/fuss.php'; $html = ob_get_clean();
$check('ohne Angaben keine Fußzeile', $html === '');

// Jede offene Seite bindet die Fußzeile ein
foreach (['seite_start', 'seite_melden', 'seite_einladung', 'seite_bestand', 'seite_zaehler'] as $s) {
    $check("$s hat die Fußzeile", str_contains((string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/' . $s . '.php'), "require __DIR__ . '/fuss.php'"));
}

/* ---------- OV-Multitool: nur bei Änderung senden ---------- */
$GLOBALS['settings'] = ['connector_betreiber' => 'OV', 'connector_impressum_url' => 'https://x.de/i', 'connector_datenschutz_url' => ''];
$GLOBALS['state'] = [];
$GLOBALS['gesendet'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM connectors')) { return [['id' => 1, 'name' => 'A', 'url' => 'https://a', 'is_active' => 1, 'pem' => 'p', 'server_pub' => 's', 'pubkey' => 'k']]; }
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'FROM settings') ? ($GLOBALS['state'][$p[0] ?? ''] ?? $d) : $d; }
function db_exec(string $sql, array $p = []): int { if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = (string)$p[1]; } return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'webpush', 'connector'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }
// Netz abfangen: connector_call ist in connector.php definiert – wir prüfen über den Merker
$check('Paket aus den Einstellungen', connector_einstellungen_paket() === ['betreiber' => 'OV', 'impressum_url' => 'https://x.de/i', 'datenschutz_url' => '']);
$check('Merker leer: senden nötig', state_get('connector_einstellungen_1', '') === '');
$GLOBALS['state']['connector_einstellungen_1'] = hash('sha256', (string)json_encode(connector_einstellungen_paket()));
$check('gleicher Stand: nichts zu senden', connector_push_einstellungen(['id' => 1]) === false);

echo "$ok bestanden, $fail fehlgeschlagen\n";
