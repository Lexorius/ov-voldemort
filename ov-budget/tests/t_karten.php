<?php
declare(strict_types=1);
/*
 * Karten: Kartenausschnitte, Symbolbibliothek (SVG nur sauber), gesetzte
 * Symbole, Fahrzeuge mit Position, Ansichten, Schnittstelle zum Browser.
 */
session_start();
date_default_timezone_set('Europe/Berlin');
define('OVB_TEST_UPLOADS', true);

$tmp = sys_get_temp_dir() . '/ovb-karten-test-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OVB_UPLOAD_DIR=' . $tmp);
$_ENV['OVB_UPLOAD_DIR'] = $tmp;

$GLOBALS['settings'] = ['waehrung' => 'EUR', 'upload_max_mb' => '10', 'karten_modul_name' => 'Karten'];
$GLOBALS['rechte'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['gruppen'] = [['gruppe' => ''], ['gruppe' => 'Fahrzeuge'], ['gruppe' => 'Taktische Zeichen']];
$GLOBALS['val'] = 0;
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    if (str_contains($sql, 'SELECT DISTINCT gruppe')) { return $GLOBALS['gruppen']; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'FROM karten_marker') ? $GLOBALS['val'] : $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 20 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
final class StubStmtK { public function execute(array $p): bool { return true; } }
final class StubPdoK { public function prepare(string $s): StubStmtK { return new StubStmtK(); } }
function db(): StubPdoK { return new StubPdoK(); }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung', 'display_name' => 'Tester']; }
function geo_heim(): ?array { return $GLOBALS['heim'] ?? null; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'vehicle_files', 'standorte', 'karten'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$ich = ['id' => 1, 'role' => 'leitung'];

/* ---------- Grenzen ---------- */
$check('Punkt auf der Karte', karten_koordinate_ok(53.0793, 8.8017) && karten_koordinate_ok('53.1', '-8') && karten_koordinate_ok(0, 0));
$check('Punkt daneben', !karten_koordinate_ok(86, 8) && !karten_koordinate_ok(53, 181) && !karten_koordinate_ok('x', 8) && !karten_koordinate_ok(null, null) && !karten_koordinate_ok('', ''));
$check('Zoom in den Grenzen', karten_zoom(14) === 14 && karten_zoom(0) === 2 && karten_zoom(99) === 19 && karten_zoom('15.4') === 15 && karten_zoom('x') === 2);
$check('Größe in den Grenzen', karten_groesse(40) === 40 && karten_groesse(1) === 12 && karten_groesse(9999) === 400 && karten_groesse('64') === 64);
$check('Drehung auf 0 bis 359', karten_drehung(90) === 90 && karten_drehung(360) === 0 && karten_drehung(-90) === 270 && karten_drehung(725) === 5 && karten_drehung('x') === 0);

/* ---------- Kachelserver ---------- */
$check('Vorgabe ist OpenStreetMap', karten_kachel_url() === 'https://tile.openstreetmap.org/{z}/{x}/{y}.png');
$check('eigener Kachelserver', karten_kachel_url_pruefen(' http://kacheln.lan/osm/{z}/{x}/{y}.png ') === 'http://kacheln.lan/osm/{z}/{x}/{y}.png'
    && karten_kachel_url_pruefen('https://{s}.kacheln.example/{z}/{x}/{y}.png') === 'https://{s}.kacheln.example/{z}/{x}/{y}.png');
$check('Unbrauchbares fällt auf die Vorgabe', karten_kachel_url_pruefen('') === KARTEN_KACHEL_VORGABE && karten_kachel_url_pruefen('https://kacheln.example/karte.png') === KARTEN_KACHEL_VORGABE
    && karten_kachel_url_pruefen('javascript:alert(1)//{z}{x}{y}') === KARTEN_KACHEL_VORGABE && karten_kachel_url_pruefen('https://x/{z}/{x}/{y}.png" onerror="x') === KARTEN_KACHEL_VORGABE
    && karten_kachel_url_pruefen('ftp://x/{z}/{x}/{y}') === KARTEN_KACHEL_VORGABE);

/* ---------- SVG: sauber oder draußen ---------- */
$sauber = '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
    . '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 256 256"><defs><linearGradient id="g"><stop offset="0"/></linearGradient>'
    . '<style>.a{fill:url( "#g" );stroke:#039}</style></defs><rect class="a" width="256" height="256" fill="url(#g)"/><use xlink:href="#g"/><use href = \'#g\'/>'
    . '<image href="data:image/png;base64,iVBORw0KGgo="/><text x="10" y="20">GKW – Zug 1</text></svg>';
$check('saubere SVG mit Verlauf, Stil, Verweisen in sich und eingebettetem PNG', karten_svg_pruefen($sauber) === null);
$boese = [
    'Skript'                    => '<svg><script>alert(1)</script></svg>',
    'Skript mit Leerraum'       => "<svg><\n script >x</script></svg>",
    'onload'                    => '<svg onload="alert(1)"></svg>',
    'Ereignis ohne Leerzeichen' => '<svg><rect/onclick=alert(1)></svg>',
    'Ereignis nach Anführung'   => '<svg><rect x="1"onmouseover="x()"/></svg>',
    'javascript-Verweis'        => '<svg><a href="javascript:alert(1)">x</a></svg>',
    'versteckt in Zeichenverweisen' => '<svg><a xlink:href="&#106;&#97;vascript:alert(1)">x</a></svg>',
    'versteckt mit Steuerzeichen'   => "<svg><a href=\"java\x01script:alert(1)\">x</a></svg>",
    'foreignObject'             => '<svg><foreignObject><body xmlns="http://www.w3.org/1999/xhtml"/></foreignObject></svg>',
    'iframe'                    => '<svg><iframe src="x"/></svg>',
    'fremdes Bild'              => '<svg><image href="https://fremd.example/zaehler.png"/></svg>',
    'Bild neben der Datei'      => '<svg><image xlink:href="bild.png"/></svg>',
    'eingebettete SVG'          => '<svg><image href="data:image/svg+xml;base64,PHN2Zz4="/></svg>',
    'fremde Schrift im Stil'    => '<svg><style>@font-face{font-family:x;src:url(https://fremd.example/f.woff)}</style></svg>',
    'import'                    => '<svg><style>@import "x.css";</style></svg>',
    'fremdes Stylesheet'        => '<?xml-stylesheet href="x.css"?><svg></svg>',
    'eigene Entität'            => '<!DOCTYPE svg [<!ENTITY lol "lol">]><svg >&lol;</svg>',
    'set auf href'              => '<svg><a><set attributeName="href" to="x"/></a></svg>',
    'kein SVG'                  => '<html><body>hallo</body></html>',
    'leer'                      => '',
];
foreach ($boese as $was => $svg) {
    $check("SVG abgelehnt: $was", karten_svg_pruefen($svg) !== null);
}
$check('zu große SVG', str_contains((string)karten_svg_pruefen('<svg>' . str_repeat(' ', KARTEN_SVG_MAX_BYTES) . '</svg>'), 'zu groß'));
$check('kaputte Zeichenkodierung', karten_svg_pruefen("<svg>\xC3\x28</svg>") !== null);
$check('der Grund ist lesbar', karten_svg_pruefen('<svg onload="x()"/>') === 'sie enthält Ereignis-Attribute (onload, onclick …)');

$mb = 1024 * 1024;
$check('Symbol: SVG, PNG und JPG passen', karten_symbol_pruefen('gkw.svg', 900, 'image/svg+xml', $sauber, $mb) === null && karten_symbol_pruefen('a.PNG', 900, 'image/png', null, $mb) === null
    && karten_symbol_pruefen('a.jpeg', 900, 'image/jpeg', null, $mb) === null);
$check('Symbol: SVG, die der Rechner für Text hält, passt trotzdem', karten_symbol_pruefen('gkw.svg', 900, 'text/plain', $sauber, $mb) === null);
$check('Symbol: böse SVG nennt Datei und Grund', karten_symbol_pruefen('trick.svg', 50, 'image/svg+xml', '<svg><script/></svg>', $mb) === '„trick.svg" wurde abgelehnt: sie enthält Skript.');
$check('Symbol: PDF, leer, zu groß', str_contains((string)karten_symbol_pruefen('a.pdf', 9, 'application/pdf', null, $mb), 'geht nicht als Symbol') && str_contains((string)karten_symbol_pruefen('a.png', 0, 'image/png', null, $mb), 'leer')
    && str_contains((string)karten_symbol_pruefen('a.png', 2 * $mb, 'image/png', null, $mb), 'zu groß'));
$check('Symbol: SVG oder HTML als PNG getarnt', karten_symbol_pruefen('a.png', 9, 'image/svg+xml', null, $mb) !== null && karten_symbol_pruefen('a.png', 9, 'text/html', null, $mb) !== null);

/* ---------- Gruppen ---------- */
$check('Gruppe an vorhandene Schreibweise angleichen', karten_gruppe_angleichen('  taktische   zeichen ', ['Fahrzeuge', 'Taktische Zeichen']) === 'Taktische Zeichen'
    && karten_gruppe_angleichen('Neu', ['Fahrzeuge']) === 'Neu' && karten_gruppe_angleichen('', ['Fahrzeuge']) === '' && mb_strlen(karten_gruppe_angleichen(str_repeat('x', 300), [])) === 100);
$gr = karten_symbole_gruppiert([['id' => 1, 'gruppe' => ''], ['id' => 2, 'gruppe' => 'Taktische Zeichen'], ['id' => 3, 'gruppe' => 'Fahrzeuge'], ['id' => 4, 'gruppe' => 'Taktische Zeichen']]);
$check('gruppiert: nach Namen, ohne Gruppe zuletzt', array_keys($gr) === ['Fahrzeuge', 'Taktische Zeichen', ''] && count($gr['Taktische Zeichen']) === 2);

/* ---------- Fahrzeuge ---------- */
$check('Alter in Worten', karten_alter_text(30) === 'gerade eben' && karten_alter_text(300) === 'vor 5 Min.' && karten_alter_text(3 * 3600) === 'vor 3 Std.'
    && karten_alter_text(3 * 86400) === 'vor 3 Tagen' && karten_alter_text(-5) === 'gerade eben');
$jetzt = strtotime('2026-11-12 12:00:00');
$fz = karten_fahrzeug_punkte([
    ['id' => 7, 'bezeichnung' => 'GKW', 'funkrufname' => 'Heros Musterstadt 22/52', 'geo_lat' => '53.079300', 'geo_lng' => '8.801700', 'geo_at' => '2026-11-12 11:50:00', 'geo_quelle' => 'divera'],
    ['id' => 8, 'bezeichnung' => 'MTW', 'funkrufname' => ' ', 'geo_lat' => '53.1', 'geo_lng' => '8.9', 'geo_at' => '2026-11-10 08:00:00', 'geo_quelle' => 'qr'],
    ['id' => 9, 'bezeichnung' => 'Anhänger', 'funkrufname' => '', 'geo_lat' => '53.2', 'geo_lng' => '8.7', 'geo_at' => null, 'geo_quelle' => ''],
    ['id' => 10, 'bezeichnung' => 'ohne Position', 'funkrufname' => '', 'geo_lat' => null, 'geo_lng' => null, 'geo_at' => null, 'geo_quelle' => ''],
    ['id' => 11, 'bezeichnung' => 'kaputt', 'funkrufname' => '', 'geo_lat' => '153', 'geo_lng' => '8', 'geo_at' => null, 'geo_quelle' => ''],
], $jetzt);
$check('nur Fahrzeuge mit brauchbarer Position', array_column($fz, 'id') === [7, 8, 9]);
$check('Fahrzeug: Funkrufname, Stand, Quelle, frisch', $fz[0]['kurz'] === 'Heros Musterstadt 22/52' && $fz[0]['name'] === 'GKW' && $fz[0]['lat'] === 53.0793 && $fz[0]['lng'] === 8.8017
    && $fz[0]['stand'] === 'vor 10 Min. · Divera' && $fz[0]['alt'] === false && str_contains($fz[0]['url'], 'p=vehicle&id=7'));
$check('Fahrzeug: ohne Funkrufname die Bezeichnung, alte Position blass', $fz[1]['kurz'] === 'MTW' && $fz[1]['alt'] === true && $fz[1]['stand'] === 'vor 2 Tagen · QR-Code'
    && $fz[2]['alt'] === true && $fz[2]['stand'] === 'Zeitpunkt unbekannt');

/* ---------- Kartenausschnitt speichern ---------- */
$post = static function (array $p): void { $_POST = $p; $GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; };
$check('Start ohne Unterkunft: Mitte Deutschlands', karten_start() === KARTEN_START);
$GLOBALS['heim'] = ['lat' => 53.0793, 'lng' => 8.8017, 'name' => 'Unterkunft', 'id' => 1];
$check('Start an der Unterkunft', karten_start() === ['lat' => 53.0793, 'lng' => 8.8017, 'zoom' => 15]);
$post(['name' => '  ']);
$check('ohne Namen', karte_save_from_post(null, $ich)[1] !== [] && $GLOBALS['inserts'] === []);
$post(['name' => 'Gelände', 'mittelpunkt' => 'irgendwo']);
$check('unlesbarer Mittelpunkt', str_contains(karte_save_from_post(null, $ich)[1][0], 'Mittelpunkt') && $GLOBALS['inserts'] === []);
$post(['name' => " Gelände   der\tUnterkunft ", 'beschreibung' => ' Hof und Halle ', 'mittelpunkt' => '', 'zoom' => '', 'fahrzeuge' => '1']);
[$id, $fehler] = karte_save_from_post(null, $ich);
$check('neu: Name geglättet, Start an der Unterkunft, Fahrzeuge an', $fehler === [] && $id === 21 && $GLOBALS['inserts'][0][0] === 'karten'
    && $GLOBALS['inserts'][0][1] === ['name' => 'Gelände der Unterkunft', 'beschreibung' => 'Hof und Halle', 'lat' => 53.0793, 'lng' => 8.8017, 'zoom' => 15, 'fahrzeuge' => 1, 'created_by' => 1]);
$post(['name' => 'Übungsgebiet', 'mittelpunkt' => '48,137154; 11,576124', 'zoom' => '30']);
[$id, $fehler] = karte_save_from_post(['id' => 5, 'lat' => 1.0, 'lng' => 2.0, 'zoom' => 9, 'name' => 'alt'], $ich);
$check('ändern: Mittelpunkt mit Komma, Zoom begrenzt, Haken aus', $fehler === [] && $id === 5 && $GLOBALS['updates'][0][1]['lat'] === 48.137154 && $GLOBALS['updates'][0][1]['lng'] === 11.576124
    && $GLOBALS['updates'][0][1]['zoom'] === 19 && $GLOBALS['updates'][0][1]['fahrzeuge'] === 0);
$GLOBALS['updates'] = [];
karte_ausschnitt_setzen(['id' => 5], 53.12345678, 8.87654321, 99);
$check('Ausschnitt merken', $GLOBALS['updates'][0][1] === ['lat' => 53.123457, 'lng' => 8.876543, 'zoom' => 19] && $GLOBALS['updates'][0][2] === [5]);
$_POST = [];

/* ---------- Symbole hochladen ---------- */
$hochladen = static function (array $dateien) use ($tmp): void {
    $f = ['name' => [], 'tmp_name' => [], 'size' => [], 'error' => []];
    foreach (array_keys($dateien) as $i => $name) {
        $pfad = $tmp . '/quelle' . $i;
        file_put_contents($pfad, $dateien[$name]);
        $f['name'][] = $name; $f['tmp_name'][] = $pfad; $f['size'][] = filesize($pfad); $f['error'][] = UPLOAD_ERR_OK;
    }
    $_FILES = ['symbole' => $f];
    $GLOBALS['inserts'] = [];
};
$hochladen(['gkw.svg' => $sauber, 'trick.svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'liste.pdf' => '%PDF-1.4']);
[$anzahl, $fehler] = karten_symbole_speichern('symbole', 'wird bei mehreren nicht genommen', 'taktische zeichen', $ich);
$svg = $GLOBALS['inserts'][0][1] ?? [];
$check('eine saubere SVG angenommen, Skript-SVG und PDF abgelehnt', $anzahl === 1 && count($fehler) === 2 && str_contains($fehler[0], 'trick.svg') && str_contains($fehler[0], 'Ereignis-Attribute') && str_contains($fehler[1], 'liste.pdf'));
$check('Eintrag: Name aus der Datei, Gruppe angeglichen, fester Inhaltstyp', ($GLOBALS['inserts'][0][0] ?? '') === 'karten_symbole' && $svg['name'] === 'gkw' && $svg['gruppe'] === 'Taktische Zeichen'
    && $svg['mime'] === 'image/svg+xml' && $svg['orig_name'] === 'gkw.svg' && $svg['uploaded_by'] === 1);
$check('SVG liegt unverändert im Ordner karten', is_file($tmp . '/karten/' . $svg['stored_name']) && file_get_contents($tmp . '/karten/' . $svg['stored_name']) === $sauber
    && str_ends_with($svg['stored_name'], '.svg') && !str_contains($svg['stored_name'], 'gkw'));
$check('die abgelehnten Dateien liegen nicht in der Ablage', count(glob($tmp . '/karten/*') ?: []) === 1);
$check('Pfad zum Symbol, nie aus dem Ordner hinaus', karten_symbol_pfad($svg) === $tmp . '/karten/' . $svg['stored_name'] && karten_symbol_pfad(['stored_name' => '../../etc/passwd']) === null);

$hochladen(['zug.svg' => $sauber]);
[$anzahl] = karten_symbole_speichern('symbole', ' Zugtrupp ', 'Neu', $ich);
$check('eine einzelne Datei bekommt den eingegebenen Namen, neue Gruppe bleibt wie geschrieben', $anzahl === 1 && $GLOBALS['inserts'][0][1]['name'] === 'Zugtrupp' && $GLOBALS['inserts'][0][1]['gruppe'] === 'Neu');
if (function_exists('imagecreatetruecolor')) {
    $bild = imagecreatetruecolor(2000, 1000);
    imagesavealpha($bild, true);
    imagefill($bild, 0, 0, imagecolorallocatealpha($bild, 0, 51, 153, 60));
    ob_start(); imagepng($bild); $png = (string)ob_get_clean();
    $hochladen(['Fahne.png' => $png]);
    [$anzahl, $fehler] = karten_symbole_speichern('symbole', '', '', $ich);
    $eintrag = $GLOBALS['inserts'][0][1] ?? [];
    [$b, $h] = $anzahl === 1 ? getimagesize($tmp . '/karten/' . $eintrag['stored_name']) : [0, 0];
    $check('PNG: angenommen, auf Symbolgröße verkleinert', $anzahl === 1 && $fehler === [] && $eintrag['mime'] === 'image/png' && $eintrag['gruppe'] === '' && $b === KARTEN_SYMBOL_MAX && $h === 512);
} else {
    echo "ohne GD: Bildverarbeitung uebersprungen\n";
}
$_FILES = [];
$check('ohne Feld: Hinweis statt Absturz', karten_symbole_speichern('symbole', '', '', $ich)[0] === 0 && karten_symbole_speichern('symbole', '', '', $ich)[1] !== []);

$GLOBALS['updates'] = [];
karten_symbol_aendern(['id' => 21, 'orig_name' => 'gkw.svg'], '  ', ' fahrzeuge ');
$check('Symbol ändern: leerer Name fällt auf die Datei, Gruppe angeglichen', $GLOBALS['updates'][0][1] === ['name' => 'gkw', 'gruppe' => 'Fahrzeuge']);
$GLOBALS['val'] = 3;
$GLOBALS['execs'] = [];
$check('Symbol löschen: nicht, solange es auf Karten sitzt', str_contains((string)karten_symbol_delete($svg + ['id' => 21]), '3-mal') && $GLOBALS['execs'] === [] && is_file($tmp . '/karten/' . $svg['stored_name']));
$GLOBALS['val'] = 0;
$check('Symbol löschen: unbenutzt geht, die Datei auch', karten_symbol_delete($svg + ['id' => 21]) === null && !is_file($tmp . '/karten/' . $svg['stored_name'])
    && str_contains($GLOBALS['execs'][0][0], 'DELETE FROM karten_symbole') && $GLOBALS['execs'][0][1] === [21]);

/* ---------- Symbole setzen ---------- */
$karte = ['id' => 5, 'name' => 'Gelände', 'beschreibung' => 'Hof', 'lat' => 53.0793, 'lng' => 8.8017, 'zoom' => 16, 'fahrzeuge' => 1];
$symbol = ['id' => 21, 'name' => 'GKW', 'gruppe' => 'Fahrzeuge'];
$GLOBALS['inserts'] = [];
$m = karten_marker_neu($karte, $symbol, '53.07951234', '8.80212345', $ich);
$check('gesetzt: Stelle gerundet, Vorgabegröße, ohne Stärke und Abschnitt', is_array($m) && $GLOBALS['inserts'][0][0] === 'karten_marker' && $GLOBALS['inserts'][0][1] === ['karte_id' => 5, 'symbol_id' => 21,
    'abschnitt_id' => null, 'lat' => 53.079512, 'lng' => 8.802123, 'groesse' => 40, 'drehung' => 0, 'beschriftung' => '', 'staerke_f' => 0, 'staerke_u' => 0, 'staerke_h' => 0, 'created_by' => 1] && $m['symbol_name'] === 'GKW');
$check('neben der Karte wird nichts gesetzt', is_string(karten_marker_neu($karte, $symbol, 95, 8, $ich)) && is_string(karten_marker_neu($karte, $symbol, 'x', '', $ich)) && count($GLOBALS['inserts']) === 1);
$d = karten_marker_daten($m);
$check('Daten für den Browser', $d['id'] === 21 && $d['symbol'] === 21 && $d['groesse'] === 40 && $d['drehung'] === 0 && $d['text'] === '' && $d['name'] === 'GKW' && str_contains($d['src'], 'p=karten_symbol&id=21')
    && $d['staerke'] === ['f' => 0, 'u' => 0, 'h' => 0, 'gesamt' => 0, 'text' => '0/0/0/0'] && $d['abschnitt'] === null);
$GLOBALS['updates'] = [];
$geaendert = karten_marker_aendern(['id' => 30], ['groesse' => '5000', 'drehung' => '-45', 'beschriftung' => "  Zug 1 \n Halle "]);
$check('ändern: nur die genannten Felder, in den Grenzen', $geaendert === ['groesse' => 400, 'drehung' => 315, 'beschriftung' => 'Zug 1 Halle'] && $GLOBALS['updates'][0][1] === $geaendert && $GLOBALS['updates'][0][2] === [30]);
$GLOBALS['updates'] = [];
$check('versetzen: Breite und Länge nur zusammen und nur auf der Karte', karten_marker_aendern(['id' => 30], ['lat' => '53.1', 'lng' => '8.9']) === ['lat' => 53.1, 'lng' => 8.9]
    && is_string(karten_marker_aendern(['id' => 30], ['lat' => '53.1'])) && is_string(karten_marker_aendern(['id' => 30], ['lat' => '99', 'lng' => '8'])) && count($GLOBALS['updates']) === 1);
$GLOBALS['updates'] = [];
$check('nichts zu ändern schreibt nichts', karten_marker_aendern(['id' => 30], ['fremd' => 1]) === [] && $GLOBALS['updates'] === []);

/* ---------- Stärke je Einheit, Einheiten ohne Stelle, Abschnitte ---------- */
$check('Stärke lesen: Führer/Unterführer/Helfer', karten_staerke_parsen('1/2/9') === [1, 2, 9] && karten_staerke_parsen(' 0 / 1 / 8 ') === [0, 1, 8] && karten_staerke_parsen('1/2/9/12') === [1, 2, 9]
    && karten_staerke_parsen('1／2／9') === [1, 2, 9] && karten_staerke_parsen('//') === [0, 0, 0]);
$check('Stärke lesen: eine Zahl zählt als Helfer, leer ist 0/0/0, zu viel wird begrenzt', karten_staerke_parsen('12') === [0, 0, 12] && karten_staerke_parsen('') === [0, 0, 0] && karten_staerke_parsen(null) === [0, 0, 0]
    && karten_staerke_parsen('1/2/99999') === [1, 2, 9999] && karten_staerke_parsen([1, '2', 9]) === [1, 2, 9]);
$check('Stärke lesen: Unsinn wird abgelehnt', karten_staerke_parsen('1/2') === null && karten_staerke_parsen('a/b/c') === null && karten_staerke_parsen('1/2/3/4/5') === null
    && karten_staerke_parsen('-1/2/9') === null && karten_staerke_parsen('1,2,9') === null && karten_staerke_parsen('1/2/9.5') === null);
$check('Stärke in Worten der Lage', karten_staerke_text(1, 2, 9) === '1/2/9/12' && karten_staerke_text(0, 0, 0) === '0/0/0/0');
$st = karten_staerke_daten(['staerke_f' => '1', 'staerke_u' => '2', 'staerke_h' => '9']);
$check('Stärke einer Einheit', $st === ['f' => 1, 'u' => 2, 'h' => 9, 'gesamt' => 12, 'text' => '1/2/9/12'] && karten_staerke_daten([])['gesamt'] === 0);
$summe = karten_staerke_summe([['staerke_f' => 1, 'staerke_u' => 2, 'staerke_h' => 9], ['staerke_f' => 0, 'staerke_u' => 1, 'staerke_h' => 3, 'lat' => null], ['staerke_h' => -5]]);
$check('Gesamtstärke über alle Einheiten – auch die ohne Stelle', $summe === ['f' => 1, 'u' => 3, 'h' => 12, 'gesamt' => 16, 'text' => '1/3/12/16', 'einheiten' => 3]
    && karten_staerke_summe([]) === ['f' => 0, 'u' => 0, 'h' => 0, 'gesamt' => 0, 'text' => '0/0/0/0', 'einheiten' => 0]);
$check('Abschnittsname geglättet', karten_abschnitt_name("  EA 1 \n Nord ") === 'EA 1 Nord' && karten_abschnitt_name('   ') === '' && mb_strlen(karten_abschnitt_name(str_repeat('x', 200))) === 100);

$GLOBALS['inserts'] = [];
$abschnitt = ['id' => 7, 'karte_id' => 5, 'name' => 'EA 1 Nord'];
$m2 = karten_marker_neu($karte, $symbol, '53.1', '8.9', $ich, ['beschriftung' => " Zugtrupp \n 1 ", 'staerke' => '1/1/2', 'abschnitt' => $abschnitt]);
$check('Einheit mit Beschriftung, Stärke und Abschnitt gesetzt', is_array($m2) && $GLOBALS['inserts'][0][1]['beschriftung'] === 'Zugtrupp 1' && $GLOBALS['inserts'][0][1]['staerke_f'] === 1
    && $GLOBALS['inserts'][0][1]['staerke_u'] === 1 && $GLOBALS['inserts'][0][1]['staerke_h'] === 2 && $GLOBALS['inserts'][0][1]['abschnitt_id'] === 7 && $m2['beschriftung'] === 'Zugtrupp 1');
$d2 = karten_marker_daten($m2);
$check('Einheit für den Browser: Stärke und Abschnitt', $d2['staerke']['text'] === '1/1/2/4' && $d2['abschnitt'] === 7 && $d2['text'] === 'Zugtrupp 1' && $d2['lat'] === 53.1);
$GLOBALS['inserts'] = [];
$m3 = karten_marker_neu($karte, $symbol, '', '', $ich, ['staerke' => '9']);
$check('Einheit ohne Stelle: nur in der Liste, Stärke als Helfer', is_array($m3) && $GLOBALS['inserts'][0][1]['lat'] === null && $GLOBALS['inserts'][0][1]['lng'] === null && $GLOBALS['inserts'][0][1]['staerke_h'] === 9
    && karten_marker_daten($m3)['lat'] === null && karten_marker_daten($m3)['lng'] === null);
$check('Einheit mit unlesbarer Stärke wird nicht gesetzt', str_contains((string)karten_marker_neu($karte, $symbol, '53.1', '8.9', $ich, ['staerke' => '1/x/2']), 'Stärke') && count($GLOBALS['inserts']) === 1);
$GLOBALS['updates'] = [];
$check('ändern: Stärke, Abschnitt (0 = keiner), Symbol', karten_marker_aendern(['id' => 30], ['staerke' => '0/2/7']) === ['staerke_f' => 0, 'staerke_u' => 2, 'staerke_h' => 7]
    && karten_marker_aendern(['id' => 30], ['abschnitt_id' => '7']) === ['abschnitt_id' => 7] && karten_marker_aendern(['id' => 30], ['abschnitt_id' => '0']) === ['abschnitt_id' => null]
    && karten_marker_aendern(['id' => 30], ['symbol_id' => 22]) === ['symbol_id' => 22] && count($GLOBALS['updates']) === 4);
$check('ändern: unlesbare Stärke schreibt nichts', str_contains((string)karten_marker_aendern(['id' => 30], ['staerke' => 'viele']), 'Stärke') && count($GLOBALS['updates']) === 4);
$check('von der Karte nehmen: beide Koordinaten leer', karten_marker_aendern(['id' => 30], ['lat' => '', 'lng' => '']) === ['lat' => null, 'lng' => null]);

$GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['execs'] = [];
$a = karten_abschnitt_neu($karte, "  UA   Bergung ", $ich);
$check('Abschnitt anlegen: Name geglättet, an der Karte', is_array($a) && $GLOBALS['inserts'][0][0] === 'karten_abschnitte' && $GLOBALS['inserts'][0][1] === ['karte_id' => 5, 'name' => 'UA Bergung', 'created_by' => 1] && $a['id'] === 21
    && karten_abschnitt_daten($a) === ['id' => 21, 'name' => 'UA Bergung']);
$check('Abschnitt ohne Namen', is_string(karten_abschnitt_neu($karte, '  ', $ich)) && count($GLOBALS['inserts']) === 1);
$check('Abschnitt umbenennen', karten_abschnitt_aendern($abschnitt, ' EA 1  Süd ') === array_replace($abschnitt, ['name' => 'EA 1 Süd']) && $GLOBALS['updates'][0][1] === ['name' => 'EA 1 Süd'] && $GLOBALS['updates'][0][2] === [7]
    && is_string(karten_abschnitt_aendern($abschnitt, '')) && karten_abschnitt_aendern($abschnitt, 'EA 1 Nord') === $abschnitt && count($GLOBALS['updates']) === 1);
karten_abschnitt_delete($abschnitt);
$check('Abschnitt löschen: Einheiten bleiben und stehen ohne Abschnitt', count($GLOBALS['execs']) === 2 && str_contains($GLOBALS['execs'][0][0], 'UPDATE karten_marker SET abschnitt_id = NULL') && $GLOBALS['execs'][0][1] === [7]
    && str_contains($GLOBALS['execs'][1][0], 'DELETE FROM karten_abschnitte') && $GLOBALS['execs'][1][1] === [7]);

/* ---------- Was der Browser bekommt ---------- */
$marker = [['id' => 30, 'symbol_id' => 21, 'lat' => '53.079500', 'lng' => '8.802100', 'groesse' => 64, 'drehung' => 90, 'beschriftung' => 'Zug 1', 'symbol_name' => 'GKW']];
$symbole = [['id' => 21, 'name' => 'GKW', 'gruppe' => 'Fahrzeuge', 'orig_name' => 'gkw.svg', 'size_bytes' => 900, 'verwendet' => 1],
    ['id' => 22, 'name' => 'Sammelplatz', 'gruppe' => '', 'orig_name' => 's.png', 'size_bytes' => 12000, 'verwendet' => 0]];
$marker[] = ['id' => 31, 'symbol_id' => 22, 'abschnitt_id' => 7, 'lat' => null, 'lng' => null, 'groesse' => 40, 'drehung' => 0, 'beschriftung' => 'Zugtrupp', 'staerke_f' => 1, 'staerke_u' => 1, 'staerke_h' => 2, 'symbol_name' => 'Sammelplatz'];
$marker[0] += ['staerke_f' => 0, 'staerke_u' => 2, 'staerke_h' => 7];
$k = karten_konfig($karte, $marker, $symbole, ['setzen' => true, 'verwalten' => false, 'fahrzeuge' => true], [$abschnitt]);
$check('Paket: Ausschnitt, Kacheln, Quelle, Schnittstelle, Schutzmarke', $k['id'] === 5 && $k['lat'] === 53.0793 && $k['zoom'] === 16 && $k['kacheln'] === KARTEN_KACHEL_VORGABE
    && $k['quelle'] === '© OpenStreetMap-Mitwirkende' && str_contains($k['api'], 'p=karten_api') && strlen($k['csrf']) >= 32 && $k['zoomMin'] === 2 && $k['zoomMax'] === 19);
$check('Paket: Rechte, Marker und Symbole', $k['setzen'] === true && $k['verwalten'] === false && $k['fahrzeuge'] === true && $k['fahrzeugeAn'] === true
    && $k['marker'][0]['text'] === 'Zug 1' && $k['marker'][0]['groesse'] === 64 && count($k['symbole']) === 2 && $k['symbole'][0] === ['id' => 21, 'name' => 'GKW', 'gruppe' => 'Fahrzeuge', 'src' => $k['symbole'][0]['src']]);
$check('Paket: Abschnitte, Gesamtstärke, Einheit ohne Stelle', $k['abschnitte'] === [['id' => 7, 'name' => 'EA 1 Nord']] && $k['staerke']['text'] === '1/3/9/13' && $k['staerke']['einheiten'] === 2
    && $k['marker'][1]['lat'] === null && $k['marker'][1]['abschnitt'] === 7 && $k['marker'][1]['staerke']['text'] === '1/1/2/4' && $k['marker'][0]['abschnitt'] === null && $k['staerkeMax'] === 9999);
$ohne = karten_konfig($karte, [], [], ['setzen' => false, 'verwalten' => false, 'fahrzeuge' => false]);
$check('ohne Blick auf die Fahrzeuge bleiben sie aus – auch wenn die Karte sie vorsieht', $ohne['fahrzeuge'] === false && $ohne['fahrzeugeAn'] === false && $ohne['setzen'] === false);

/* ---------- Ansichten ---------- */
$seite = static fn(array $darf): string => render_partial('karte', ['title' => 'x', 'karte' => $karte, 'konfig' => karten_konfig($karte, $marker, $symbole, $darf, [$abschnitt]), 'symbole' => $symbole, 'darf' => $darf,
    'staerke' => karten_staerke_summe($marker)]);
$alles = $seite(['setzen' => true, 'verwalten' => true, 'fahrzeuge' => true]);
preg_match('/data-lagekarte="([^"]*)"/', $alles, $mm);
$zurueck = json_decode(html_entity_decode($mm[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
$check('Karte: das Paket kommt heil im Attribut an', is_array($zurueck) && $zurueck['id'] === 5 && $zurueck['marker'][0]['text'] === 'Zug 1' && $zurueck['quelle'] === '© OpenStreetMap-Mitwirkende');
$check('Karte: Fläche, Schalter Fahrzeuge, Ausschnitt merken, + Symbol', str_contains($alles, 'data-karte-flaeche') && str_contains($alles, 'data-karte-fahrzeuge') && str_contains($alles, 'data-karte-merken')
    && str_contains($alles, 'data-karte-setzen') && str_contains($alles, 'Rechtsklick') && str_contains($alles, 'p=karte_edit&amp;id=5'));
$check('Karte: neben der Karte die Gesamtstärke – schon ohne Skript', str_contains($alles, 'data-karte-seite') && str_contains($alles, 'Gesamtstärke') && str_contains($alles, '<div class="lagekarte__gesamt-zahl" data-karte-gesamt>1/3/9/13</div>')
    && str_contains($alles, '2 Einheiten') && $zurueck['abschnitte'][0]['name'] === 'EA 1 Nord' && $zurueck['marker'][1]['lat'] === null);
$nurSehen = $seite(['setzen' => false, 'verwalten' => false, 'fahrzeuge' => false]);
$check('Karte nur ansehen: nichts zum Setzen, Merken oder Fahrzeuge – die Stärke steht trotzdem', str_contains($nurSehen, 'data-karte-flaeche') && !str_contains($nurSehen, 'data-karte-fahrzeuge') && !str_contains($nurSehen, 'data-karte-merken')
    && !str_contains($nurSehen, 'data-karte-setzen') && !str_contains($nurSehen, 'p=karte_edit') && !str_contains($nurSehen, 'Rechtsklick') && str_contains($nurSehen, 'Gesamtstärke'));
$check('Karte: Vollbild-Knopf für alle, die die Karte sehen', str_contains($alles, 'data-karte-vollbild') && str_contains($nurSehen, 'data-karte-vollbild'));
$boeseKarte = ['name' => '"><script>alert(1)</script>', 'beschreibung' => '</script>'] + $karte;
$boeseMarker = [['beschriftung' => '"><img src=x onerror=alert(1)>'] + $marker[0]];
$html = render_partial('karte', ['title' => 'x', 'karte' => $boeseKarte, 'konfig' => karten_konfig($boeseKarte, $boeseMarker, $symbole, [], [['id' => 1, 'name' => '<script>alert(2)</script>']]), 'symbole' => $symbole,
    'darf' => ['setzen' => false, 'verwalten' => false, 'fahrzeuge' => false], 'staerke' => karten_staerke_summe($boeseMarker)]);
$check('Karte: Namen, Beschriftungen und Abschnitte werden maskiert', !str_contains($html, '<script>alert') && !str_contains($html, '<img src=x'));

$liste = render_partial('karten', ['karten' => [['marker' => 1] + $karte, ['id' => 6, 'name' => 'Übungsgebiet', 'beschreibung' => '', 'fahrzeuge' => 0, 'marker' => 0] + $karte], 'symbole' => 2, 'verwalten' => true]);
$check('Übersicht: Karten mit Symbolzahl, Symbole, + Karte', str_contains($liste, 'p=karte&amp;id=5') && str_contains($liste, '1 Symbol · mit Fahrzeugen') && str_contains($liste, '0 Symbole')
    && str_contains($liste, 'Symbole (2)') && str_contains($liste, '+ Karte'));
$check('Übersicht leer und ohne Rechte', str_contains(render_partial('karten', ['karten' => [], 'symbole' => 0, 'verwalten' => false]), 'Die Leitung legt sie an')
    && !str_contains(render_partial('karten', ['karten' => [], 'symbole' => 0, 'verwalten' => false]), '+ Karte'));

$form = render_partial('karte_edit', ['karte' => $karte, 'errors' => ['Hoppla'], 'marker' => 2]);
$check('Karte bearbeiten: Felder, Mittelpunkt, Haken, Fehler, Löschen nennt die Symbole', str_contains($form, 'value="Gelände"') && str_contains($form, 'value="53.079300, 8.801700"')
    && str_contains($form, 'name="fahrzeuge" value="1" checked') && str_contains($form, 'Hoppla') && str_contains($form, 'samt ihren 2 gesetzten Symbolen löschen?') && str_contains($form, 'min="2" max="19"'));
$check('Karte anlegen: kein Löschen', !str_contains(render_partial('karte_edit', ['karte' => ['id' => null, 'name' => '', 'beschreibung' => '', 'lat' => 51.1634, 'lng' => 10.4477, 'zoom' => 6, 'fahrzeuge' => 0],
    'errors' => [], 'marker' => 0]), 'value="loeschen"'));

$GLOBALS['settings']['upload_hinweis'] = 'Keine Dokumente mit personenbezogenen Daten.';
settings_reset_cache();
$bib = render_partial('karten_symbole', ['gruppiert' => karten_symbole_gruppiert($symbole), 'anzahl' => 2, 'gruppen' => ['Fahrzeuge'], 'verwalten' => true, 'maxMb' => 10]);
$check('Bibliothek: hochladen mit Hinweis, SVG erlaubt, Gruppen als Vorschlag', str_contains($bib, 'name="symbole[]" multiple') && str_contains($bib, 'upload-hinweis') && str_contains($bib, '.svg')
    && str_contains($bib, '<option value="Fahrzeuge">') && str_contains($bib, 'enctype="multipart/form-data"'));
$check('Bibliothek: Symbole nach Gruppe, als Bild eingebunden, mit Verwendung', str_contains($bib, '<h2>Fahrzeuge</h2>') && str_contains($bib, '<h2>Ohne Gruppe</h2>')
    && str_contains($bib, '<img src="/index.php?p=karten_symbol&amp;id=21"') && str_contains($bib, 'SVG · 900 B · 1× gesetzt') && str_contains($bib, 'value="aendern"') && str_contains($bib, 'value="loeschen"')
    && !str_contains($bib, '<object') && !str_contains($bib, '<embed') && !str_contains($bib, '<iframe'));
$nurBib = render_partial('karten_symbole', ['gruppiert' => karten_symbole_gruppiert($symbole), 'anzahl' => 2, 'gruppen' => [], 'verwalten' => false, 'maxMb' => 10]);
$check('Bibliothek nur ansehen', !str_contains($nurBib, 'value="hochladen"') && !str_contains($nurBib, 'value="loeschen"') && str_contains($nurBib, 'Sammelplatz'));

/* ---------- Einbindung ---------- */
$lies = static fn(string $rel): string => (string)file_get_contents($app . '/' . $rel);
$abruf = $lies('src/pages/karten_symbol.php');
$check('Symbol-Abruf: Recht, fester Inhaltstyp, kein Skript, kein Nachladen', str_contains($abruf, "can('view_karten')") && str_contains($abruf, "default-src \\'none\\'") && str_contains($abruf, 'sandbox')
    && str_contains($abruf, 'X-Content-Type-Options: nosniff') && str_contains($abruf, "'image/svg+xml', 'image/png'"));
$api = $lies('src/pages/karten_api.php');
$check('Schnittstelle: sehen, setzen und Ausschnitt je mit eigenem Recht', str_contains($api, "can('view_karten')") && str_contains($api, "can('mark_karten')")
    && str_contains($api, "can('manage_karten')") && strpos($api, "can('view_karten')") < strpos($api, 'karten_fahrzeuge('));
foreach (['abschnitt_neu', 'abschnitt_aendern', 'abschnitt_weg'] as $s) {
    $check("Schnittstelle: $s hinter dem Recht, Symbole zu setzen", strpos($api, "can('mark_karten')") < strpos($api, "case '$s':"));
}
$check('Schnittstelle: Abschnitt und Symbol einer Einheit werden geprüft, der Abschnitt muss zur Karte gehören', str_contains($api, "(int)\$a['karte_id'] !== \$karteId") && str_contains($api, "karten_symbol_find(post_int('symbol_id'))")
    && str_contains($api, "'staerke'") && substr_count($api, '$abschnittPruefen(') === 2);
// Der Abschnitt, der die Fahrzeuge liefert: von der Abfrage bis zur Antwort
$fzAbschnitt = substr($api, (int)strpos($api, "get_str('action') === 'fahrzeuge'"), (int)strpos($api, "'Unbekannte Anfrage.'") - (int)strpos($api, "get_str('action') === 'fahrzeuge'"));
$check('Schnittstelle: Fahrzeuge bekommt jeder, der die Karten sieht – ohne Fahrzeugmodul nur ohne Verweis zur Akte', !str_contains($fzAbschnitt, '403')
    && str_contains($fzAbschnitt, "\$akte = can('view_vehicles')") && str_contains($fzAbschnitt, "['url' => ''] + \$f") && str_contains($fzAbschnitt, "\$setzen = \$akte && can('report_vehicle')"));
$check('Seite: der Schalter Fahrzeuge steht jedem zu, der die Karte sieht', str_contains($lies('src/pages/karte.php'), "'fahrzeuge' => true")
    && str_contains($lies('src/pages/karte.php'), "'fahrzeugSetzen' => can('view_vehicles') && can('report_vehicle')"));
$check('Skript: ohne Adresse ist das Fahrzeug kein Verweis', str_contains($lies('public/assets/js/karte.js'), "f.el.removeAttribute('href')"));
$check('Schnittstelle: Schreiben nur per POST (dort prüft die Anwendung die Schutzmarke)', strpos($api, "REQUEST_METHOD'] !== 'POST'") < strpos($api, 'karten_marker_neu(')
    && str_contains($lies('public/index.php'), 'csrf_check();'));
$js = $lies('public/assets/js/karte.js');
$check('Skript: Rechtsklick, Schutzmarke, Herkunft für die Kacheln, keine fremde Bibliothek', str_contains($js, "addEventListener('contextmenu'") && str_contains($js, "fd.append('_csrf', K.csrf)")
    && str_contains($js, "referrerPolicy = 'strict-origin-when-cross-origin'") && !preg_match('/\b(L\.map|leaflet|ol\.Map)\b/i', $js));
$check('Skript: Vollbild über die Schnittstelle des Browsers, sonst über der Seite; Esc beendet', str_contains($js, "requestFullscreen({ navigationUI: 'hide' })") && str_contains($js, "classList.add('is-vollbild')")
    && str_contains($js, "addEventListener('fullscreenchange'") && str_contains($js, "classList.remove('is-vollbild'); vollbildAnzeigen(); }"));
$check('Skript: Seitenleiste mit Gesamtstärke, Abschnitten und Einheiten; Einheit ohne Stelle bleibt unsichtbar', str_contains($js, 'function seiteZeichnen()') && str_contains($js, "'+ Abschnitt'") && str_contains($js, "'+ Einheit'")
    && str_contains($js, "action: 'abschnitt_neu'") && str_contains($js, "action: 'abschnitt_aendern'") && str_contains($js, "action: 'abschnitt_weg'") && str_contains($js, 'm.el.hidden = ohneOrt(m)')
    && str_contains($js, "if (!ohneOrt(m)) { setzen(m.el, m.d, e); }") && str_contains($js, "spaeterSpeichern(m, 'staerke', stEingabe.value)") && str_contains($js, 'window.confirm('));
$check('Skript: Texte nur als Text, nie als HTML', !str_contains($js, 'innerHTML') && !str_contains($js, 'insertAdjacentHTML') && !str_contains($js, 'document.write'));
$check('Skript wird nur auf der Kartenseite geladen', str_contains($lies('views/layout.php'), "\$GLOBALS['ovb_karte_js']") && str_contains($lies('src/pages/karte.php'), "\$GLOBALS['ovb_karte_js'] = true"));
$routen = $lies('public/index.php');
foreach (['karten', 'karte', 'karte_edit', 'karten_symbole', 'karten_symbol', 'karten_api'] as $s) {
    $check("Seite $s", str_contains($routen, "'$s'") && is_file($app . '/src/pages/' . $s . '.php'));
}
$schema = $lies('sql/schema.sql');
$wanderung = $lies('src/cli/migrate.php');
$spalten = static fn(string $s): array => array_values(array_filter(array_map(static fn($z) => preg_match('/^\s+([a-z_]+)\s+[A-Z]/', $z, $m) ? $m[1] : '', explode("\n", $s))));
foreach (['karten', 'karten_symbole', 'karten_abschnitte', 'karten_marker'] as $t) {
    preg_match('/CREATE TABLE IF NOT EXISTS ' . $t . ' \((.*?)\) ENGINE/s', $schema, $ms);
    preg_match('/CREATE TABLE ' . $t . ' \((.*?)\) ENGINE/s', $wanderung, $mw);
    $check("Tabelle $t in Schema und Wanderung mit denselben Spalten", $spalten($ms[1] ?? '') !== [] && $spalten($ms[1] ?? '') === $spalten($mw[1] ?? 'x'));
}
$check('gesetzte Symbole gehen mit der Karte, ein benutztes Symbol bleibt', str_contains($schema, 'REFERENCES karten(id)            ON DELETE CASCADE') && str_contains($schema, 'REFERENCES karten_symbole(id)    ON DELETE RESTRICT')
    && str_contains($wanderung, "\$merken('104_karten')"));
$check('Stärke und Abschnitte: Spalten, Einheit ohne Stelle erlaubt, Abschnitt weg lässt die Einheit stehen, Wanderung für den Bestand',
    str_contains($schema, 'REFERENCES karten_abschnitte(id) ON DELETE SET NULL') && preg_match('/karten_marker \(.*?\n  lat          DECIMAL\(9,6\) NULL,/s', $schema) === 1
    && str_contains($wanderung, "\$merken('107_karten_staerke_abschnitte')") && str_contains($wanderung, 'MODIFY COLUMN lat DECIMAL(9,6) NULL') && str_contains($wanderung, 'ADD COLUMN staerke_f')
    && strpos($wanderung, 'CREATE TABLE karten_abschnitte') < strpos($wanderung, 'CREATE TABLE karten_marker'));
$seed = $lies('sql/seed.sql');
foreach (['karten_modul_name', 'karten_intro', 'karten_user_darf_sehen', 'karten_user_darf_setzen', 'karten_kachel_url', 'karten_kachel_quelle'] as $skey) {
    $check("Einstellung $skey", str_contains($seed, "('$skey',"));
}
$rechte = $lies('src/lib/auth.php');
$check('Rechte, Menü, Speicherübersicht', str_contains($rechte, "'manage_karten'") && str_contains($rechte, "'view_karten'") && str_contains($rechte, "'mark_karten'")
    && str_contains($lies('src/lib/nav.php'), "'route' => 'karten'") && str_contains($lies('src/lib/speicher.php'), "'karten'"));

// Aufräumen
foreach (glob($tmp . '/karten/*') ?: [] as $f) { @unlink($f); }
foreach (glob($tmp . '/*') ?: [] as $f) { is_dir($f) ? @rmdir($f) : @unlink($f); }
@rmdir($tmp);

echo "$ok bestanden, $fail fehlgeschlagen\n";
