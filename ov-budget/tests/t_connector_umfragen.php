<?php
declare(strict_types=1);
/*
 * Connector: Abstimmungen – säubern, setzen, Zeitfenster, Antworten ablegen
 * und abholen, nichts im Klartext, die Umfrageseite, und der ganze Weg einer
 * Antwort vom Handy bis in die Auswertung.
 */
$tmp = sys_get_temp_dir() . '/ovb-umfrage-test';
@mkdir($tmp, 0777, true);
$leeren = static function (string $o) use (&$leeren): void {
    foreach (glob($o . '/*') ?: [] as $f) { if (is_dir($f)) { $leeren($f); @rmdir($f); } else { @unlink($f); } }
};
$leeren($tmp);
$quelle = (string)file_get_contents(dirname(__DIR__, 2) . '/connector/src/connector.php');
$quelle = str_replace("dirname(__DIR__) . '/daten'", var_export($tmp . '/daten', true), $quelle);
@mkdir(__DIR__ . '/alt', 0777, true);
file_put_contents(__DIR__ . '/alt/connector_umfragen.php', $quelle);
require __DIR__ . '/alt/connector_umfragen.php';
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Säubern ---------- */
$seiten = [
    ['titel' => "Seite 1\x01", 'text' => 'Hallo', 'felder' => [
        ['id' => 'f1', 'typ' => 'einfach', 'frage' => 'Welcher Tag?', 'pflicht' => 1, 'optionen' => ['Sa', '', 'So']],
        ['id' => 'f2', 'typ' => 'einfach', 'frage' => 'Nur eine Option', 'optionen' => ['A']],
        ['id' => 'f3', 'typ' => 'skala', 'frage' => 'Wie?', 'min' => 3, 'max' => 2],
        ['id' => 'f4', 'typ' => 'text', 'frage' => '', 'lang' => 1],
        ['id' => 'bad id', 'typ' => 'janein', 'frage' => 'Ja?'],
        ['id' => 'f5', 'typ' => 'unsinn', 'frage' => 'X'],
        ['id' => 'f6', 'typ' => 'hinweis', 'frage' => 'Danke', 'pflicht' => 1],
        'kein array',
    ]],
    ['felder' => []],
    'kein array',
];
$sauber = con_umfrage_seiten_saeubern($seiten);
$check('nur taugliche Fragen bleiben', count($sauber) === 1 && array_column($sauber[0]['felder'], 'id') === ['f1', 'f3', 'f6']);
$check('Optionen ohne Leere, Skala repariert, Hinweis nie Pflicht', $sauber[0]['felder'][0]['optionen'] === ['Sa', 'So']
    && $sauber[0]['felder'][1]['min'] === 3 && $sauber[0]['felder'][1]['max'] === 4 && $sauber[0]['felder'][2]['pflicht'] === 0 && $sauber[0]['titel'] === 'Seite 1');

$kennung = con_umfrage_kennung('AB23CD');
$roh = ['kennung' => $kennung, 'titel' => "Sommerfest\x00", 'beschreibung' => 'Bitte', 'beginn_ts' => 0, 'ende_ts' => time() + 3600,
        'name_feld' => 1, 'dank' => 'Danke!', 'seiten' => $seiten];
$u = con_umfrage_saeubern($roh);
$check('Abstimmung gesäubert', $u !== null && $u['titel'] === 'Sommerfest' && $u['name_feld'] === 1 && count($u['seiten']) === 1);
$check('ohne Ende keine Abstimmung', con_umfrage_saeubern(['ende_ts' => 0] + $roh) === null);
$check('ohne Fragen keine Abstimmung', con_umfrage_saeubern(['seiten' => []] + $roh) === null);
$check('kaputte Kennung weg', con_umfrage_saeubern(['kennung' => 'x'] + $roh) === null);

/* ---------- Setzen, Zustand, Zeitfenster ---------- */
$n = con_umfragen_setzen([$roh, ['kennung' => str_repeat('b', 64), 'ende_ts' => time() + 10, 'seiten' => $seiten], 'unsinn']);
$check('zwei Abstimmungen gesetzt', $n === 2);
$check('Code im Klartext nirgends', !str_contains((string)file_get_contents($tmp . '/daten/umfragen.json'), 'AB23CD'));
$gefunden = con_umfrage('ab23cd');
$check('Abstimmung über den Code, Groß/Klein egal', $gefunden !== null && $gefunden['kennung'] === $kennung);
$check('unbekannter oder kaputter Code: null', con_umfrage('ZZZZZZ') === null && con_umfrage('../x') === null);
$check('Zustand: offen', con_umfrage_zustand($gefunden) === 'offen');
$check('Zustand: wartet und vorbei', con_umfrage_zustand(['beginn_ts' => 2000, 'ende_ts' => 3000], 1000) === 'wartet'
    && con_umfrage_zustand(['beginn_ts' => 0, 'ende_ts' => 3000], 3001) === 'vorbei' && con_umfrage_zustand(['beginn_ts' => 0, 'ende_ts' => 3000], 3000) === 'offen');

/* ---------- Antworten ablegen ---------- */
try { con_umfrage_antwort_ablegen('ZZZZZZ', 'geheim'); $check('unbekannter Code abgewiesen', false); }
catch (ConException $e) { $check('unbekannter Code abgewiesen', str_contains($e->getMessage(), 'nicht')); }
try { con_umfrage_antwort_ablegen('AB23CD', ''); $check('leere Antwort abgewiesen', false); }
catch (ConException $e) { $check('leere Antwort abgewiesen', true); }
try { con_umfrage_antwort_ablegen('AB23CD', str_repeat('x', CON_MAX_ANTWORT + 1)); $check('zu große Antwort abgewiesen', false); }
catch (ConException $e) { $check('zu große Antwort abgewiesen', true); }

con_umfragen_setzen([['beginn_ts' => time() + 600] + $roh]);
try { con_umfrage_antwort_ablegen('AB23CD', 'geheim'); $check('vor dem Beginn abgewiesen', false); }
catch (ConException $e) { $check('vor dem Beginn abgewiesen', str_contains($e->getMessage(), 'noch nicht')); }
con_umfragen_setzen([['ende_ts' => time() - 1] + $roh]);
try { con_umfrage_antwort_ablegen('AB23CD', 'geheim'); $check('nach dem Ende abgewiesen', false); }
catch (ConException $e) { $check('nach dem Ende abgewiesen', str_contains($e->getMessage(), 'beendet')); }
con_umfragen_setzen([$roh]);

$gebremst = false;
for ($i = 0; $i < CON_LIMIT_UMFRAGE + 2; $i++) {
    try { con_umfrage_antwort_ablegen('AB23CD', 'geheimtext-' . $i); }
    catch (ConException $e) { $gebremst = true; break; }
}
$check('je Abstimmung begrenzt', $gebremst && $i === CON_LIMIT_UMFRAGE);
$ordner = $tmp . '/daten/umfrageantworten/' . $kennung;
$check('Antworten liegen nur als Geheimtext, im Ordner der Prüfsumme', count(glob($ordner . '/*.json')) === CON_LIMIT_UMFRAGE
    && !is_dir($tmp . '/daten/umfrageantworten/AB23CD'));

$geholt = con_umfrage_antworten_abholen(10);
$check('abholen liefert höchstens max', count($geholt) === 10 && $geholt[0]['umfrage'] === $kennung && str_starts_with($geholt[0]['daten'], 'geheimtext-'));
$check('abgeholt ist gelöscht', count(glob($ordner . '/*.json')) === CON_LIMIT_UMFRAGE - 10);
con_umfrage_antworten_abholen(1000);
@unlink($tmp . '/daten/limit.json');   // Stundenbegrenzung für die weiteren Schritte zurücksetzen
$check('danach liegt nichts mehr', con_umfrage_antworten_abholen() === [] && glob($ordner . '/*.json') === []);

con_umfrage_antwort_ablegen('AB23CD', 'noch-eine');
con_umfragen_setzen([]);
$check('zurückgezogen: Abstimmung und Antworten weg', con_umfrage('AB23CD') === null && !is_dir($ordner));
$s = con_status();
$check('Zustand zählt Abstimmungen', $s['umfragen'] === 0 && $s['umfrageantworten'] === 0);
$check('Ablage kennt die neuen Namen', con_daten_erwartet('umfragen.json', false) && con_daten_erwartet('umfrageantworten', true) && !con_daten_erwartet('umfragen.php', false));

/* ---------- Die Seite ---------- */
con_schreiben('kopplung.json', ['ov_pubkey' => 'PUB']);
$umfrage = null; $code = 'x';
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_umfrage.php'; $html = ob_get_clean();
$check('Seite ohne Abstimmung: Hinweis, kein Schlüssel', str_contains($html, 'gibt es nicht') && !str_contains($html, 'data-schluessel'));

$umfrage = $u; $code = 'AB23CD';
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_umfrage.php'; $html = ob_get_clean();
$check('Seite offen: Fragen, Pflichtstern, Skala, Name, Skript', str_contains($html, 'Welcher Tag?') && str_contains($html, 'data-schluessel="PUB"')
    && str_contains($html, 'data-code="AB23CD"') && str_contains($html, 'class="pflicht"') && str_contains($html, 'name="f_f3" value="3"')
    && str_contains($html, 'name="_name"') && str_contains($html, 'umfrage.js') && str_contains($html, 'Antwort senden'));
$check('Seite: Code steht nur im Datenattribut, Hinweistext als Absatz', substr_count($html, 'AB23CD') === 1 && str_contains($html, '<div class="hinweis">Danke</div>'));

$umfrage = ['beginn_ts' => time() + 600] + $u;
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_umfrage.php'; $html = ob_get_clean();
$check('Seite wartet: kein Formular', str_contains($html, 'noch nicht geöffnet') && !str_contains($html, 'id="formular"') && str_contains($html, 'umfrage.js'));
$umfrage = ['ende_ts' => time() - 5] + $u;
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_umfrage.php'; $html = ob_get_clean();
$check('Seite vorbei: kein Formular', str_contains($html, 'beendet') && !str_contains($html, 'id="formular"'));

$zwei = ['seiten' => [$seiten[0], ['titel' => 'Zweite', 'felder' => [['id' => 'f9', 'typ' => 'text', 'frage' => 'Noch was?']]]]] + $roh;
$umfrage = con_umfrage_saeubern($zwei);
ob_start(); require dirname(__DIR__, 2) . '/connector/src/seite_umfrage.php'; $html = ob_get_clean();
$check('zwei Seiten: zweite versteckt, Weiter, Fortschritt', substr_count($html, 'class="seite"') === 2 && str_contains($html, 'data-seite="1" hidden')
    && str_contains($html, '>Weiter<') && str_contains($html, 'Seite 1 von 2') && str_contains($html, 'name="f_f9"'));

/* ---------- Der ganze Weg: Handy verschlüsselt, OV-Multitool liest und wertet aus ---------- */
$GLOBALS['settings'] = ['waehrung' => 'EUR'];
$GLOBALS['inserts'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM umfragen')) { return $GLOBALS['umfragen'] ?? []; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'webpush', 'events', 'connector', 'umfragen'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

[$ovPem, $ovPunkt] = p256_keypair();
$handy = static function (array $inhalt) use ($ovPunkt): string {
    [$handyPem, $handyPunkt] = p256_keypair();
    $gemeinsam = openssl_pkey_derive(p256_public_pem($ovPunkt), openssl_pkey_get_private($handyPem));
    $salz = random_bytes(16);
    $abgeleitet = hash_hkdf('sha256', $gemeinsam, 44, CONNECTOR_INFO, $salz);
    $tag = '';
    $geheim = openssl_encrypt((string)json_encode($inhalt), 'aes-256-gcm', substr($abgeleitet, 0, 32),
        OPENSSL_RAW_DATA, substr($abgeleitet, 32, 12), $tag);
    return b64u_encode("\x01" . $handyPunkt . $salz . $geheim . $tag);
};

// Was OV-Multitool an den Connector schickt
$zeile = ['id' => 5, 'code' => 'AB23CD', 'titel' => 'Sommerfest', 'beschreibung' => '', 'beginn' => null, 'ende' => date('Y-m-d H:i:s', time() + 3600),
          'name_feld' => 1, 'dank' => '', 'status' => 'offen', 'connector_id' => 1,
          'seiten' => json_encode([['titel' => '', 'text' => '', 'felder' => [
              ['id' => 'f1', 'typ' => 'einfach', 'frage' => 'Welcher Tag?', 'pflicht' => 1, 'optionen' => ['Sa', 'So']],
              ['id' => 'f2', 'typ' => 'skala', 'frage' => 'Wie gern?', 'pflicht' => 0, 'min' => 1, 'max' => 5, 'links' => '', 'rechts' => '']]]])];
$paket = connector_umfrage_daten($zeile);
$check('Paket: Prüfsumme statt Code, Zeiten als Unix-Zeit, Seiten entpackt', $paket['kennung'] === umfrage_kennung('AB23CD')
    && !isset($paket['code']) && $paket['beginn_ts'] === 0 && $paket['ende_ts'] === (int)strtotime($zeile['ende']) && $paket['seiten'][0]['felder'][1]['typ'] === 'skala');
$check('Kennung passt zwischen beiden Seiten', umfrage_kennung('AB23CD') === con_umfrage_kennung('AB23CD'));
$GLOBALS['umfragen'] = [$zeile, ['ende' => date('Y-m-d H:i:s', time() - 2 * 86400)] + $zeile];
$liste = connector_umfrage_paket(['id' => 1]);
$check('Paket lässt lange Beendetes weg', count($liste) === 1);
$check('Connector nimmt das Paket an', con_umfragen_setzen($liste) === 1 && con_umfrage('AB23CD') !== null);

// Das Handy antwortet
con_umfrage_antwort_ablegen('AB23CD', $handy(['antworten' => ['f1' => 1, 'f2' => 4, 'f7' => 'x'], 'name' => 'Anna', 'zeit' => time()]));
con_umfrage_antwort_ablegen('AB23CD', $handy(['antworten' => ['f1' => 0], 'name' => '', 'zeit' => time()]));
$platte = implode('', array_map('file_get_contents', glob($tmp . '/daten/umfrageantworten/' . $kennung . '/*.json')));
$check('nichts davon auf der Platte lesbar', !str_contains($platte, 'Anna') && !str_contains($platte, 'antworten'));

// OV-Multitool holt ab, entschlüsselt, legt ab
$geholt = con_umfrage_antworten_abholen();
$check('zwei Antworten geholt und beim Connector gelöscht', count($geholt) === 2 && con_umfrage_antworten_abholen() === []);
foreach ($geholt as $m) {
    $daten = connector_entschluesseln($m['daten'], $ovPem, ['antworten']);
    umfrage_antwort_speichern($zeile, $daten, (int)$m['ts']);
}
$check('zwei Zeilen gespeichert, Unbekanntes weg', count($GLOBALS['inserts']) === 2
    && json_decode($GLOBALS['inserts'][0][1]['daten'], true) === ['f1' => 1, 'f2' => 4] && $GLOBALS['inserts'][0][1]['name'] === 'Anna'
    && json_decode($GLOBALS['inserts'][1][1]['daten'], true) === ['f1' => 0]);
$antworten = array_map(static fn($i) => ['ts' => $i[1]['ts'], 'name' => $i[1]['name'], 'antworten' => json_decode($i[1]['daten'], true)], $GLOBALS['inserts']);
$a = umfrage_auswertung(umfrage_seiten($zeile), $antworten);
$check('Auswertung stimmt', $a['anzahl'] === 2 && $a['felder']['f1']['zaehler'] === [1, 1] && $a['felder']['f2']['schnitt'] === 4.0 && $a['felder']['f2']['beantwortet'] === 1);
try { connector_entschluesseln($handy(['name' => 'ohne Antworten']), $ovPem, ['antworten']); $check('Pflichtfeld antworten', false); }
catch (ConnectorException $e) { $check('Pflichtfeld antworten', str_contains($e->getMessage(), 'antworten')); }

$leeren($tmp);
echo "$ok bestanden, $fail fehlgeschlagen\n";
