<?php
declare(strict_types=1);
/* Tankkarten-Import: Spalten erkennen, Zeilen übersetzen, Plan, Ausführung, Ansicht */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'upload_max_mb' => '10'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['karten'] = [];   // vorhandene Karten für den Index

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM list_items')) {
        $r = []; $id = 300;
        foreach (['tankkarte_anbieter' => [['dkv', 'DKV', 1], ['enbw', 'EnBW mobility+', 0]], 'tankkarte_status' => [['aktiv', 'Im Einsatz', 1], ['reserve', 'Reserve', 0]]] as $key => $items) {
            foreach ($items as [$slug, $label, $def]) {
                $r[] = ['id' => $id++, 'list_key' => $key, 'label' => $label, 'slug' => $slug, 'color' => '#123456', 'weight' => 0, 'is_default' => $def, 'is_final' => 0, 'is_active' => 1, 'sort_order' => 0];
            }
        }
        return $r;
    }
    if (str_contains($sql, 'FROM tankkarten')) { return $GLOBALS['karten']; }
    if (str_contains($sql, 'FROM vehicles')) {
        return [['id' => 7, 'bezeichnung' => 'GKW 1', 'funkrufname' => 'Heros 24/51', 'kennzeichen' => 'THW 99020'],
                ['id' => 8, 'bezeichnung' => 'MTW', 'funkrufname' => '', 'kennzeichen' => 'THW 99021'],
                ['id' => 9, 'bezeichnung' => 'MTW E', 'funkrufname' => '', 'kennzeichen' => '']];
    }
    if (str_contains($sql, 'FROM users')) { return [['id' => 4, 'display_name' => 'Anna Admin']]; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'FROM list_items WHERE list_key = ? AND slug = ?')) { return 777; }
    return $d;
}
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 50 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
final class StubStmt { public function execute(array $p): bool { return true; } }
final class StubPdo { public function prepare(string $s): StubStmt { return new StubStmt(); } }
function db(): StubPdo { return new StubPdo(); }
function can(string $what, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'display_name' => 'Tester']; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'import', 'vehicles', 'stein', 'tankkarten', 'tankkarten_import'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- Spalten erkennen ---------- */
$map = tankkarte_import_mapping(['Anbieter', 'Kartennummer', 'Schaltet frei', 'Status', 'Kundennummer', 'Gültig bis', 'Fahrzeuge', 'Liegt bei', 'PIN', 'Verbleib', 'Grund der Sperrung', 'Notiz', 'Unsinn']);
$check('Vorlage wird erkannt', $map === ['anbieter', 'nummer', 'art', 'status', 'kundennummer', 'gueltig_bis', 'fahrzeuge', 'person', 'pin', 'verbleib', 'sperrgrund', 'notiz', '']);
$map = tankkarte_import_mapping(['Card Number', 'Provider', 'Expiry Date', 'License Plate', 'Card Holder', 'Remarks']);
$check('Englische Spalten', $map === ['nummer', 'anbieter', 'gueltig_bis', 'fahrzeuge', 'person', 'notiz']);
$check('jedes Ziel nur einmal', tankkarte_import_mapping(['Nummer', 'Kartennummer']) === ['nummer', '']);

/* ---------- Einzelwerte ---------- */
$check('Datum', tankkarte_import_datum('30.06.2027') === '2027-06-30' && tankkarte_import_datum('2027-06-30') === '2027-06-30' && tankkarte_import_datum('1.7.27') === '2027-07-01'
    && tankkarte_import_datum('06/2027') === '2027-06-30' && tankkarte_import_datum('31.02.2027') === null && tankkarte_import_datum('bald') === null && tankkarte_import_datum('') === null);
$check('Art', tankkarte_import_art('Laden') === 'laden' && tankkarte_import_art('Ladekarte') === 'laden' && tankkarte_import_art('Strom') === 'laden' && tankkarte_import_art('beides') === 'beides'
    && tankkarte_import_art('Tanken und Laden') === 'beides' && tankkarte_import_art('Diesel') === 'kraftstoff' && tankkarte_import_art('') === 'kraftstoff');
$check('Verbleib', tankkarte_import_verbleib('gesperrt') === 'gesperrt' && tankkarte_import_verbleib('ja') === 'gesperrt' && tankkarte_import_verbleib('zurückgegeben') === 'zurueckgegeben'
    && tankkarte_import_verbleib('abgelaufen') === 'abgelaufen' && tankkarte_import_verbleib('nein') === '' && tankkarte_import_verbleib('') === '' && tankkarte_import_verbleib('im Bestand') === '');
$check('Fahrzeuge zerlegen', tankkarte_import_fahrzeuge_split('THW 99020, MTW; MTW E') === ['THW 99020', 'MTW', 'MTW E'] && tankkarte_import_fahrzeuge_split('') === []);
$fahrzeuge = db_all('SELECT id FROM vehicles');
$check('Fahrzeug finden', tankkarte_import_fahrzeug('THW-99020', $fahrzeuge) === 7 && tankkarte_import_fahrzeug('mtw', $fahrzeuge) === 8 && tankkarte_import_fahrzeug('Heros 24/51', $fahrzeuge) === 7
    && tankkarte_import_fahrzeug('MTW-E', $fahrzeuge) === 9 && tankkarte_import_fahrzeug('Boot', $fahrzeuge) === null);

/* ---------- Zeile übersetzen ---------- */
$kontext = tankkarte_import_kontext(['anbieter_id' => null, 'anbieter_anlegen' => 0]);
$z = tankkarte_import_zeile(['nummer' => ' 7083 0012 3456 ', 'anbieter' => 'dkv', 'art' => 'Kraftstoff', 'status' => 'Reserve', 'gueltig_bis' => '30.06.2027',
    'fahrzeuge' => 'THW 99020, MTW, Boot', 'person' => 'anna admin', 'pin' => '4711', 'verbleib' => '', 'notiz' => 'n'], $kontext);
$check('Zeile vollständig', $z['fehler'] === [] && $z['karte']['nummer'] === '7083 0012 3456' && $z['karte']['anbieter_id'] === 300 && $z['karte']['status_id'] === 303
    && $z['karte']['gueltig_bis'] === '2027-06-30' && $z['fahrzeug_ids'] === [7, 8] && $z['fahrzeuge_unbekannt'] === ['Boot'] && $z['karte']['person_id'] === 4
    && $z['karte']['hat_pin'] === 1 && $z['karte']['pin'] === '4711' && $z['karte']['is_active'] === 1 && count($z['hinweise']) === 1);
$z = tankkarte_import_zeile(['nummer' => '1', 'anbieter' => 'Shell'], $kontext);
$check('unbekannter Anbieter ohne Anlegen: Fehler', count($z['fehler']) === 1 && str_contains($z['fehler'][0], 'Shell') && $z['anbieter_neu']);
$z = tankkarte_import_zeile(['nummer' => '1', 'anbieter' => 'Shell'], tankkarte_import_kontext(['anbieter_id' => null, 'anbieter_anlegen' => 1]));
$check('unbekannter Anbieter mit Anlegen: ok', $z['fehler'] === [] && $z['anbieter_neu'] && $z['karte']['anbieter_id'] === null);
$z = tankkarte_import_zeile(['nummer' => '2'], tankkarte_import_kontext(['anbieter_id' => 301, 'anbieter_anlegen' => 0]));
$check('Anbieter aus der Vorgabe', $z['fehler'] === [] && $z['karte']['anbieter_id'] === 301 && $z['karte']['status_id'] === 302);
$z = tankkarte_import_zeile(['anbieter' => 'DKV'], $kontext);
$check('ohne Nummer unbrauchbar', count($z['fehler']) === 1 && str_contains($z['fehler'][0], 'Kartennummer'));
$z = tankkarte_import_zeile(['nummer' => '3', 'anbieter' => 'DKV', 'verbleib' => 'gesperrt', 'sperrgrund' => 'verloren', 'verbleib_am' => '12.03.2026', 'person' => 'Max Muster', 'aufbewahrung' => 'Schrank'], $kontext);
$check('gesperrt mit Grund und Datum, unbekannte Person in der Aufbewahrung', $z['karte']['verbleib'] === 'gesperrt' && $z['karte']['sperrgrund'] === 'verloren' && $z['karte']['verbleib_am'] === '2026-03-12'
    && $z['karte']['is_active'] === 0 && $z['karte']['person_id'] === null && $z['karte']['aufbewahrung'] === 'Schrank · Max Muster');
$z = tankkarte_import_zeile(['nummer' => '3', 'anbieter' => 'DKV', 'verbleib' => 'ja'], $kontext);
$check('gesperrt ohne Grund bekommt einen', $z['karte']['sperrgrund'] === 'aus Import übernommen' && $z['karte']['verbleib_am'] === date('Y-m-d'));

/* ---------- Plan ---------- */
$GLOBALS['karten'] = [['id' => 5, 'nummer' => '7083 0012 3456', 'anbieter_id' => 300]];
$index = tankkarte_import_index();
$check('Index', $index === [tankkarte_import_schluessel(300, '7083 0012 3456') => 5]);
$zeilen = [
    ['nummer' => '7083 0012 3456', 'anbieter' => 'DKV'],
    ['nummer' => '7083 0012 9999', 'anbieter' => 'DKV', 'fahrzeuge' => 'MTW'],
    ['nummer' => '7083-0012-9999', 'anbieter' => 'DKV'],
    ['anbieter' => 'DKV'],
    ['nummer' => '4711', 'anbieter' => 'Shell'],
];
$plan = tankkarte_import_plan($zeilen, $kontext, $index, 'ueberspringen');
$check('Plan überspringen', array_column($plan, 'aktion') === ['ueberspringen', 'neu', 'ueberspringen', 'fehler', 'fehler'] && $plan[0]['id'] === 5
    && str_contains($plan[2]['grund'], 'Zeile 2') && $plan[1]['zeile'] === 2);
$plan = tankkarte_import_plan($zeilen, $kontext, $index, 'ueberschreiben');
$check('Plan überschreiben', $plan[0]['aktion'] === 'ueberschreiben' && $plan[0]['id'] === 5);

/* ---------- Ausführen ---------- */
$kontextAnlegen = tankkarte_import_kontext(['anbieter_id' => null, 'anbieter_anlegen' => 1]);
$plan = tankkarte_import_plan($zeilen, $kontextAnlegen, $index, 'ueberschreiben');
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['execs'] = [];
$erg = tankkarte_import_execute($plan, ['anbieter_anlegen' => 1], ['id' => 1]);
$check('Ergebnis', $erg['neu'] === 2 && $erg['ueberschrieben'] === 1 && $erg['uebersprungen'] === 1 && $erg['fehler'] === 1 && $erg['anbieter_neu'] === 1 && count($erg['hinweise']) >= 1);
$kartenIns = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'tankkarten'));
$zuord = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'tankkarte_fahrzeuge'));
$check('angelegt mit Fahrzeug und neuem Anbieter', count($kartenIns) === 2 && $kartenIns[0][1]['nummer'] === '7083 0012 9999' && $kartenIns[0][1]['created_by'] === 1
    && $kartenIns[1][1]['anbieter_id'] === 777 && count($zuord) === 1 && $zuord[0][1]['vehicle_id'] === 8);
$check('überschrieben: Karte und Fahrzeuge neu gesetzt', count($GLOBALS['updates']) === 1 && $GLOBALS['updates'][0][2] === [5]
    && (bool)array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'DELETE FROM tankkarte_fahrzeuge') && $x[1] === [5]));
$check('Anbieter in der Auswahlliste angelegt', (bool)array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'INSERT IGNORE INTO list_items') && $x[1][1] === 'Shell'));

/* ---------- Datei zerlegen ---------- */
$csv = "Anbieter;Kartennummer;Fahrzeuge\nDKV;123;\"THW 99020, MTW\"\n\nEnBW mobility+;DE*ENB*1;MTW E\n";
$teile = tankkarte_import_parse(import_to_utf8($csv));
$check('CSV zerlegt und zugeordnet', $teile['mapping'] === ['anbieter', 'nummer', 'fahrzeuge'] && count($teile['zeilen']) === 2 && $teile['zeilen'][0]['fahrzeuge'] === 'THW 99020, MTW');

/* ---------- Ansicht ---------- */
$_SERVER['REQUEST_URI'] = '/?p=tankkarten_import';
$_GET = ['p' => 'tankkarten_import'];
$html = render_partial('tankkarten_import', ['errors' => [], 'datei' => null, 'sitzung' => [], 'teile' => null, 'plan' => [], 'zaehler' => [], 'ergebnis' => null, 'dateiname' => '']);
$check('Schritt 1', str_contains($html, 'name="datei"') && str_contains($html, 'vorlage=1'));
$html = render_partial('tankkarten_import', ['errors' => [], 'datei' => ['name' => 'karten.csv'], 'sitzung' => ['mapping' => $teile['mapping'], 'optionen' => ['strategie' => 'ueberspringen', 'anbieter_id' => null, 'anbieter_anlegen' => 1], 'vorschau' => true],
    'teile' => $teile, 'plan' => $plan, 'zaehler' => ['neu' => 2, 'ueberschreiben' => 1, 'ueberspringen' => 1, 'fehler' => 1], 'ergebnis' => null, 'dateiname' => '']);
$check('Schritt 2 und 3', str_contains($html, 'name="map[0]"') && str_contains($html, 'value="anbieter" selected') && str_contains($html, 'name="anbieter_anlegen"') && str_contains($html, 'checked')
    && str_contains($html, 'id="vorschau"') && str_contains($html, 'wird angelegt') && str_contains($html, 'Jetzt 3 Tankkarten importieren') && str_contains($html, 'doppelt in der Datei'));
$html = render_partial('tankkarten_import', ['errors' => [], 'datei' => null, 'sitzung' => [], 'teile' => null, 'plan' => [], 'zaehler' => [], 'ergebnis' => $erg, 'dateiname' => 'karten.csv']);
$check('Ergebnis', str_contains($html, 'Import abgeschlossen') && str_contains($html, '3 Tankkarte(n) gespeichert') && str_contains($html, '1 Anbieter neu') && str_contains($html, 'karten.csv'));

$check('Route, Navigation, Knopf', str_contains((string)file_get_contents($app . '/public/index.php'), "'tankkarten_import'")
    && str_contains((string)file_get_contents($app . '/src/lib/nav.php'), "'tankkarten_import'") && str_contains((string)file_get_contents($app . '/views/tankkarten.php'), 'tankkarten_import'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
