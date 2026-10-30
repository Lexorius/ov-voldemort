<?php
declare(strict_types=1);
/* Tanken und Verbrauch: Auswertung, Plausibilität, Speichern, Ansichten */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'upload_max_mb' => '10'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['rows'] = [];      // Antworten für db_row, der Reihe nach
$GLOBALS['alle'] = [];      // Antwort für db_all außerhalb der Einstellungen
$GLOBALS['can'] = [];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    $GLOBALS['letzte_all'] = [$sql, $p];
    return $GLOBALS['alle'];
}
function db_row(string $sql, array $p = []): ?array { $GLOBALS['abfragen'][] = [$sql, $p]; return array_shift($GLOBALS['rows']); }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 100 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function db_lock(string $n, int $w = 0): bool { return true; }
function db_unlock(string $n): void {}
function current_user(): ?array { return ['id' => 4, 'role' => 'leitung', 'display_name' => 'Tester']; }
function can(string $what, mixed $ctx = null): bool { return $GLOBALS['can'][$what] ?? true; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'vehicles', 'vehicle_files', 'tanken', 'tankkarten'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};
$tankung = static fn(int $id, string $datum, ?int $km, float $liter, array $extra = []): array => $extra + [
    'id' => $id, 'vehicle_id' => 7, 'datum' => $datum, 'km_stand' => $km, 'liter' => $liter, 'betrag' => null,
    'voll' => 1, 'art' => 'kraftstoff', 'tankstelle' => '', 'beleg_nr' => '', 'notiz' => '', 'user_id' => 4, 'erfasst_von' => 'Tester', 'belege' => 0,
];

/* ---------- Auswertung: Volltankung zu Volltankung ---------- */
$a = tank_auswerten([
    $tankung(1, '2026-03-01', 100000, 60.0, ['betrag' => 96.0]),
    $tankung(2, '2026-03-20', 100700, 56.0),                       // 700 km, 56 l → 8,0
    $tankung(3, '2026-04-02', 100900, 20.0, ['voll' => 0]),        // nur nachgefüllt
    $tankung(4, '2026-04-15', 101500, 44.0, ['betrag' => 70.4]),   // 800 km, 64 l → 8,0
]);
$check('leer ohne Tankungen', tank_auswerten([])['verbrauch'] === null && tank_auswerten([])['anzahl'] === 0 && tank_auswerten([])['letzte'] === null);
$check('erste Volltankung ist nur der Bezug', $a['eintraege'][3]['verbrauch'] === null && $a['eintraege'][3]['strecke'] === null);
$check('Verbrauch der zweiten Volltankung', abs($a['eintraege'][2]['verbrauch'] - 8.0) < 0.001 && $a['eintraege'][2]['strecke'] === 700);
$check('Teiltankung ohne eigenen Verbrauch', $a['eintraege'][1]['verbrauch'] === null && (int)$a['eintraege'][1]['voll'] === 0);
$check('Teiltankung zählt zur nächsten Volltankung', $a['eintraege'][0]['strecke'] === 800 && abs($a['eintraege'][0]['verbrauch'] - 8.0) < 0.001);
$check('Summen', $a['anzahl'] === 4 && abs($a['liter'] - 180.0) < 0.001 && $a['strecke'] === 1500 && abs($a['verbrauch'] - 8.0) < 0.001);
$check('Beträge nur, wo bekannt', abs($a['betrag'] - 166.4) < 0.001 && $a['betrag_fehlt'] === 2);
$check('Preis je Liter', abs($a['eintraege'][3]['preis_je_liter'] - 1.6) < 0.001 && $a['eintraege'][2]['preis_je_liter'] === null);
$check('neueste zuerst, Punkte chronologisch', $a['eintraege'][0]['id'] === 4 && $a['letzte']['id'] === 4
    && array_column($a['punkte'], 'id') === [2, 4] && $a['punkte'][0]['datum'] === '2026-03-20');
$check('Kilometer von bis', $a['km_von'] === 100000 && $a['km_bis'] === 101500);
$check('Jahreswerte', isset($a['jahre']['2026']) && $a['jahre']['2026']['anzahl'] === 4 && $a['jahre']['2026']['strecke'] === 1500
    && abs($a['jahre']['2026']['verbrauch'] - 8.0) < 0.001 && $a['jahre']['2026']['betrag_fehlt'] === 2);
$check('zu wenige Punkte: nichts auffällig', !array_filter($a['eintraege'], static fn($t) => $t['auffaellig']));

/* ---------- Reihenfolge, fehlende Stände, AdBlue, Ausreißer ---------- */
$b = tank_auswerten([
    $tankung(5, '2026-05-10', 102500, 50.0),                        // 1000 km → 5,0
    $tankung(3, '2026-04-15', 101500, 70.0),                        // 1500 km → 4,7 (Bezug 100000)
    $tankung(1, '2026-03-01', 100000, 60.0),
    $tankung(6, '2026-05-20', null, 30.0),                          // ohne Stand: zählt zur nächsten
    $tankung(7, '2026-06-01', 103000, 20.0),                        // 500 km, 50 l → 10,0 auffällig
    $tankung(8, '2026-06-02', null, 10.0, ['art' => 'adblue', 'betrag' => 12.0]),
    $tankung(9, '2026-06-10', 103400, 24.0),                        // 400 km → 6,0
]);
$check('sortiert nach Datum, nicht nach Eingabe', array_column($b['punkte'], 'id') === [3, 5, 7, 9]);
$check('Volltankung ohne Stand fließt in die nächste', $b['punkte'][2]['liter'] == 50.0 && abs($b['punkte'][2]['verbrauch'] - 10.0) < 0.001);
$check('AdBlue getrennt', abs($b['adblue_liter'] - 10.0) < 0.001 && abs($b['liter'] - 254.0) < 0.001 && abs($b['betrag'] - 12.0) < 0.001);
$check('Ausreißer markiert', $b['punkte'][2]['auffaellig'] === true && $b['punkte'][0]['auffaellig'] === false
    && array_values(array_filter($b['eintraege'], static fn($t) => $t['auffaellig']))[0]['id'] === 7);
$check('Mittel über alles', abs($b['verbrauch'] - (194.0 / 3400 * 100)) < 0.001 && $b['strecke'] === 3400);

$c = tank_auswerten([
    $tankung(1, '2026-01-05', 50000, 40.0),
    $tankung(2, '2026-01-20', 49000, 40.0),   // Stand kleiner: neuer Bezug, kein Verbrauch
    $tankung(3, '2026-02-01', 49500, 40.0),   // 500 km → 8,0
]);
$check('kleinerer Stand setzt den Bezug neu', $c['punkte'] !== [] && count($c['punkte']) === 1 && $c['punkte'][0]['id'] === 3 && $c['punkte'][0]['strecke'] === 500);
$check('nur Teiltankungen: kein Verbrauch', tank_auswerten([$tankung(1, '2026-01-05', 50000, 40.0, ['voll' => 0]), $tankung(2, '2026-01-20', 50500, 40.0, ['voll' => 0])])['verbrauch'] === null);

/* ---------- CSV ---------- */
$csv = tank_csv_zeilen($a);
$check('CSV: Kopf und älteste zuerst', $csv[0][0] === 'Datum' && $csv[1][0] === '2026-03-01' && $csv[4][0] === '2026-04-15' && count($csv) === 5);
$check('CSV: Werte deutsch, Verbrauch nur wo gerechnet', $csv[2][7] === '8,00' && $csv[1][7] === '' && $csv[3][5] === 'nein' && $csv[1][8] === '96,00' && $csv[2][8] === ''
    && $csv[1][3] === 'l' && $csv[0][10] === 'Tankkarte');

/* ---------- Strom: E-Fahrzeug und Hybrid ---------- */
$s = tank_auswerten([
    $tankung(1, '2026-03-01', 20000, 40.0, ['art' => 'strom']),
    $tankung(2, '2026-03-10', 20300, 54.0, ['art' => 'strom', 'betrag' => 27.0]),   // 300 km, 54 kWh → 18,0
    $tankung(3, '2026-03-15', 20400, 10.0, ['art' => 'strom', 'voll' => 0]),       // nachgeladen
    $tankung(4, '2026-03-22', 20600, 44.0, ['art' => 'strom']),                    // 300 km, 54 kWh → 18,0
    $tankung(5, '2026-03-05', 20100, 30.0),                                        // Hybrid: Kraftstoff daneben
    $tankung(6, '2026-03-25', 20700, 12.0),                                        // 600 km, 12 l → 2,0
]);
$check('Strom getrennt gerechnet', abs($s['strom']['verbrauch'] - 18.0) < 0.001 && $s['strom']['strecke'] === 600 && $s['strom']['anzahl'] === 4
    && abs($s['strom']['menge'] - 148.0) < 0.001 && $s['strom']['einheit'] === 'kWh' && count($s['strom']['punkte']) === 2);
$check('Kraftstoff daneben unberührt', abs($s['verbrauch'] - 2.0) < 0.001 && $s['strecke'] === 600 && abs($s['liter'] - 42.0) < 0.001 && count($s['punkte']) === 1);
$check('Strom-Einträge mit Einheit', $s['eintraege'][0]['einheit'] === 'l' && $s['eintraege'][1]['einheit'] === 'kWh' && abs($s['eintraege'][1]['verbrauch'] - 18.0) < 0.001);
$check('Beträge über alle Arten, fehlende gezählt', abs($s['betrag'] - 27.0) < 0.001 && $s['betrag_fehlt'] === 5 && $s['strom']['betrag_fehlt'] === 3);
$check('Jahreswerte Strom', $s['strom']['jahre']['2026']['strecke'] === 600 && abs($s['strom']['jahre']['2026']['menge'] - 148.0) < 0.001);
$check('Mengentext mit Einheit', tank_menge_text(['liter' => 41.2, 'art' => 'strom']) === '41,20 kWh' && tank_menge_text(['liter' => 62.4, 'art' => 'kraftstoff']) === '62,40 l'
    && tank_verbrauch_text(18.04, 'kWh') === '18,0 kWh/100 km');
$check('Kurztext Strom', str_starts_with(tank_kurztext(['liter' => 54, 'art' => 'strom', 'km_stand' => 20300, 'voll' => 1]), '54,00 kWh Strom (Laden) bei 20.300 km')
    && str_ends_with(tank_kurztext(['liter' => 10, 'art' => 'strom', 'km_stand' => null, 'voll' => 0]), '(nicht voll geladen)'));
$check('Kartentext', tank_karte_text(['karte_nummer' => '7083001234567890', 'karte_anbieter' => 'DKV']) === 'DKV …7890' && tank_karte_text(['karte_nummer' => '']) === '');

/* ---------- Texte ---------- */
$check('Texte', tank_liter_text(62.4) === '62,40 l' && tank_km_text(123456) === '123.456 km' && tank_verbrauch_text(8.04) === '8,0 l/100 km'
    && tank_verbrauch_text(null) === '' && tank_km_text(null) === '');
$check('Kurztext', tank_kurztext(['liter' => 62.4, 'art' => 'kraftstoff', 'km_stand' => 123456, 'voll' => 1]) === '62,40 l Kraftstoff bei 123.456 km'
    && str_ends_with(tank_kurztext(['liter' => 20, 'art' => 'kraftstoff', 'km_stand' => null, 'voll' => 0]), '(Teiltankung)'));

/* ---------- Rechte ---------- */
$GLOBALS['can'] = ['manage_vehicles' => false, 'tank_vehicle' => true];
$check('eigene Tankung darf man ändern', tank_darf_aendern(['user_id' => 4], ['id' => 4]) && !tank_darf_aendern(['user_id' => 5], ['id' => 4]));
$GLOBALS['can'] = ['manage_vehicles' => false, 'tank_vehicle' => false];
$check('ohne Recht auch die eigene nicht', !tank_darf_aendern(['user_id' => 4], ['id' => 4]));
$GLOBALS['can'] = [];
$check('Leitung darf alles', tank_darf_aendern(['user_id' => 5], ['id' => 4]));

/* ---------- Speichern ---------- */
$fahrzeug = ['id' => 7, 'bezeichnung' => 'GKW 1', 'kennzeichen' => 'THW 99020', 'km_stand' => 100000];
$user = ['id' => 4, 'display_name' => 'Tester'];
$_POST = ['datum' => '', 'liter' => '0'];
[$id, $fehler] = tank_save_from_post(null, $fahrzeug, $user);
$check('Pflichtfelder', $id === null && count($fehler) === 2 && str_contains($fehler[0], 'Datum') && str_contains($fehler[1], 'Liter'));

$_POST = ['datum' => '2026-04-15', 'liter' => '62,40', 'km_stand' => '101500', 'voll' => '1', 'art' => 'kraftstoff', 'betrag' => '', 'tankstelle' => 'Aral Süd', 'beleg_nr' => 'A-17', 'tankkarte_id' => '3'];
$GLOBALS['rows'] = [['datum' => '2026-03-20', 'km_stand' => 100700], null];
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = [];
[$id, $fehler] = tank_save_from_post(null, $fahrzeug, $user);
$ins = $GLOBALS['inserts'][0] ?? null;
$check('gespeichert', $id !== null && $fehler === [] && $ins !== null && $ins[0] === 'vehicle_tankungen'
    && $ins[1]['liter'] === 62.4 && $ins[1]['km_stand'] === 101500 && $ins[1]['betrag'] === null && $ins[1]['voll'] === 1
    && $ins[1]['tankstelle'] === 'Aral Süd' && $ins[1]['user_id'] === 4 && $ins[1]['datum'] === '2026-04-15' && $ins[1]['tankkarte_id'] === 3);
$journal = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'vehicle_journal'));
$check('Journal: Tankung und Kilometerstand', count($journal) === 2 && $journal[0][1]['art'] === 'tankung' && str_starts_with($journal[0][1]['titel'], 'Getankt: 62,40 l Kraftstoff bei 101.500 km')
    && str_contains($journal[0][1]['text'], 'Beleg A-17') && $journal[0][1]['ref_typ'] === 'tankung'
    && $journal[1][1]['feld'] === 'km_stand' && $journal[1][1]['neu_wert'] !== '');
$check('Stand des Fahrzeugs nachgezogen', count($GLOBALS['updates']) === 1 && $GLOBALS['updates'][0][0] === 'vehicles' && $GLOBALS['updates'][0][1] === ['km_stand' => 101500]);

$GLOBALS['rows'] = [['datum' => '2026-03-20', 'km_stand' => 100700], null];
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = [];
$_POST['km_stand'] = '100500';
[$id, $fehler] = tank_save_from_post(null, $fahrzeug, $user);
$check('Stand unter der früheren Tankung', $id === null && count($fehler) === 1 && str_contains($fehler[0], '20.03.2026') && str_contains($fehler[0], '100.700 km'));

$GLOBALS['rows'] = [null, ['datum' => '2026-05-01', 'km_stand' => 101400]];
$_POST['km_stand'] = '101500';
[$id, $fehler] = tank_save_from_post(null, $fahrzeug, $user);
$check('Stand über der späteren Tankung', $id === null && count($fehler) === 1 && str_contains($fehler[0], 'späteren') && str_contains($fehler[0], '01.05.2026'));

$GLOBALS['rows'] = [];
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = [];
$_POST = ['datum' => '2026-04-16', 'liter' => '20', 'km_stand' => '', 'art' => 'kraftstoff', 'betrag' => '31,90'];
[$id, $fehler] = tank_save_from_post(null, $fahrzeug, $user);
$check('ohne Stand: keine Prüfung, Teiltankung, Betrag', $id !== null && $GLOBALS['inserts'][0][1]['km_stand'] === null && $GLOBALS['inserts'][0][1]['voll'] === 0
    && $GLOBALS['inserts'][0][1]['betrag'] === 31.9 && $GLOBALS['updates'] === [] && ($GLOBALS['abfragen'] ?? []) !== null);

$_POST = ['datum' => '2026-04-16', 'liter' => '10', 'km_stand' => '90000', 'art' => 'adblue', 'voll' => '1'];
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = [];
[$id, $fehler] = tank_save_from_post(null, $fahrzeug, $user);
$check('AdBlue nie voll, Stand kleiner ohne Prüfung', $id !== null && $GLOBALS['inserts'][0][1]['art'] === 'adblue' && $GLOBALS['inserts'][0][1]['voll'] === 0
    && $GLOBALS['updates'] === []);

$_POST = ['datum' => '2099-01-01', 'liter' => '5000', 'km_stand' => '-5', 'betrag' => '-1'];
[$id, $fehler] = tank_save_from_post(null, $fahrzeug, $user);
$check('Unsinn abgelehnt', $id === null && count($fehler) === 4);

// Ändern
$bestehend = $tankung(9, '2026-04-15', 101500, 60.0);
$_POST = ['datum' => '2026-04-15', 'liter' => '61', 'km_stand' => '101500', 'voll' => '1', 'art' => 'kraftstoff'];
$GLOBALS['rows'] = [null, null];
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['abfragen'] = [];
$fahrzeug['km_stand'] = 101500;
[$id, $fehler] = tank_save_from_post($bestehend, $fahrzeug, $user);
$check('Änderung', $id === 9 && $GLOBALS['updates'][0][0] === 'vehicle_tankungen' && $GLOBALS['updates'][0][1]['liter'] === 61.0 && $GLOBALS['updates'][0][2] === [9]
    && count($GLOBALS['updates']) === 1 && str_starts_with($GLOBALS['inserts'][0][1]['titel'], 'Tankung geändert')
    && $GLOBALS['abfragen'][0][1][2] === 9);

// Löschen
$GLOBALS['alle'] = [];
$GLOBALS['inserts'] = []; $GLOBALS['execs'] = [];
tank_delete($bestehend, $user);
$check('Löschen', str_contains($GLOBALS['execs'][0][0], 'DELETE FROM vehicle_tankungen') && $GLOBALS['execs'][0][1] === [9]
    && str_starts_with($GLOBALS['inserts'][0][1]['titel'], 'Tankung gelöscht'));

/* ---------- Ansichten ---------- */
$_SERVER['REQUEST_URI'] = '/?p=vehicle_tanken';
$_GET = ['p' => 'vehicle_tanken', 'id' => '7'];
$daten = ['vehicle' => $fahrzeug, 'a' => $b, 'rows' => $b['eintraege'], 'blatt' => seiten_blaettern(count($b['eintraege']), 1, 50),
          'erfassen' => true, 'verwalten' => true, 'user' => current_user()];
$html = render_partial('vehicle_tanken', $daten);
$check('Seite: Kennzahlen, Verlauf, Tabelle', str_contains($html, 'Verbrauch im Mittel') && str_contains($html, 'months--tank')
    && str_contains($html, 'months__bar--hoch') && str_contains($html, 'auffällig') && str_contains($html, 'AdBlue')
    && str_contains($html, '10,0 l/100 km') && str_contains($html, 'id="t9"'));
$check('Seite: Tankkarte an der Tankung', str_contains(render_partial('vehicle_tanken', ['vehicle' => $fahrzeug,
    'a' => tank_auswerten([$tankung(1, '2026-03-01', 100000, 60.0, ['karte_nummer' => '7083001234567890', 'karte_anbieter' => 'DKV'])]),
    'rows' => tank_auswerten([$tankung(1, '2026-03-01', 100000, 60.0, ['karte_nummer' => '7083001234567890', 'karte_anbieter' => 'DKV'])])['eintraege'],
    'blatt' => seiten_blaettern(1, 1, 50), 'erfassen' => true, 'verwalten' => true, 'user' => current_user()]), 'DKV …7890'));
$html = render_partial('vehicle_tanken', ['a' => $s, 'rows' => $s['eintraege']] + $daten);
$check('Seite: Strom-Kennzahlen und Verlauf', str_contains($html, 'Strom im Mittel') && str_contains($html, '18,0 kWh/100 km') && str_contains($html, 'months--strom')
    && str_contains($html, 'Verbrauch im Mittel'));
$nurStrom = tank_auswerten([$tankung(1, '2026-03-01', 20000, 40.0, ['art' => 'strom']), $tankung(2, '2026-03-10', 20300, 54.0, ['art' => 'strom'])]);
$html = render_partial('vehicle_tanken', ['a' => $nurStrom, 'rows' => $nurStrom['eintraege']] + $daten);
$check('Seite: reines E-Fahrzeug ohne Kraftstoff-Kacheln', !str_contains($html, 'Verbrauch im Mittel') && str_contains($html, 'Strom im Mittel') && str_contains($html, 'Ladekarte'));
$html = render_partial('vehicle_tanken', $daten);
$check('Seite: Knöpfe', str_contains($html, 'vehicle_tank_edit') && str_contains($html, 'vehicle_tanken_export') && str_contains($html, '+ Tankung'));
$check('Seite: Betrag fehlt wird benannt', str_contains($html, 'ohne Betrag'));
$leer = render_partial('vehicle_tanken', ['vehicle' => $fahrzeug, 'a' => tank_auswerten([]), 'rows' => [], 'blatt' => seiten_blaettern(0, 1, 50), 'erfassen' => false, 'verwalten' => false, 'user' => null] );
$check('Seite leer', str_contains($leer, 'Noch keine Tankung') && !str_contains($leer, '+ Tankung') && !str_contains($leer, 'months--tank'));

$html = render_partial('vehicle_tank_edit', ['tank' => ['id' => null, 'datum' => '2026-04-20', 'km_stand' => null, 'liter' => '', 'betrag' => null, 'voll' => 1, 'art' => 'kraftstoff', 'tankstelle' => 'Aral Süd', 'beleg_nr' => '', 'notiz' => ''],
    'vehicle' => $fahrzeug, 'letzte' => ['datum' => '2026-04-15', 'km_stand' => 101500, 'tankstelle' => 'Aral Süd'], 'belege' => [], 'errors' => [], 'loeschen' => false, 'verwalten' => true,
    'karten' => ['Diesem Fahrzeug zugeordnet' => [['id' => 3, 'nummer' => '7083001234567890', 'anbieter_label' => 'DKV', 'art' => 'kraftstoff']], 'Weitere Karten' => [['id' => 4, 'nummer' => '9999', 'anbieter_label' => 'EnBW', 'art' => 'laden']]]]);
$check('Formular: Tankkarten in Gruppen, Ladekarte mit Blitz', str_contains($html, 'name="tankkarte_id"') && str_contains($html, 'label="Diesem Fahrzeug zugeordnet"')
    && str_contains($html, 'DKV 7083001234567890') && str_contains($html, 'EnBW 9999 ⚡'));
$check('Formular neu', str_contains($html, 'name="liter"') && str_contains($html, 'name="km_stand"') && str_contains($html, 'zuletzt 101.500')
    && str_contains($html, 'name="voll" value="1" checked') && str_contains($html, 'name="belege[]"') && str_contains($html, 'Tankkarte')
    && str_contains($html, 'value="Aral Süd"') && !str_contains($html, 'tank_delete'));
$html = render_partial('vehicle_tank_edit', ['tank' => $tankung(9, '2026-04-15', 101500, 60.5, ['betrag' => 96.8]), 'vehicle' => $fahrzeug, 'letzte' => null,
    'belege' => [['id' => 31, 'art' => 'bild', 'orig_name' => 'beleg.jpg', 'size_bytes' => 2048]], 'errors' => ['Der Betrag darf nicht negativ sein.'], 'loeschen' => true, 'verwalten' => true]);
$check('Formular bestehend', str_contains($html, 'value="60,50"') && str_contains($html, 'value="96,80"') && str_contains($html, 'tank_delete')
    && str_contains($html, 'beleg.jpg') && str_contains($html, 'file_delete') && str_contains($html, 'alert--error'));

/* ---------- Fahrzeugakte: Abschnitt, Journal-Filter, Dateien bleiben getrennt ---------- */
$akte = (string)file_get_contents($app . '/views/vehicle.php');
$check('Fahrzeugakte mit Abschnitt', str_contains($akte, 'id="tanken"') && str_contains($akte, "'tankung' => 'Tankungen'") && str_contains($akte, 'zu den Tankungen'));
$seite = (string)file_get_contents($app . '/src/pages/vehicle.php');
$check('Belege nicht in Galerie und Dokumenten', substr_count($seite, "empty(\$f['tank_id'])") === 2 && str_contains($seite, 'tank_uebersicht'));
$check('Routen', str_contains((string)file_get_contents($app . '/public/index.php'), "'vehicle_tanken_export'"));
$check('Rechte', str_contains((string)file_get_contents($app . '/src/lib/auth.php'), "'tank_vehicle'"));
$check('Aktion löschen', str_contains((string)file_get_contents($app . '/src/pages/vehicle_action.php'), "case 'tank_delete'"));
$schema = (string)file_get_contents($app . '/sql/schema.sql');
$check('Schema', str_contains($schema, 'CREATE TABLE IF NOT EXISTS vehicle_tankungen') && str_contains($schema, 'fk_vf_tank')
    && strpos($schema, 'CREATE TABLE IF NOT EXISTS vehicle_tankungen') < strpos($schema, 'CREATE TABLE IF NOT EXISTS vehicle_files'));
$check('Migration', str_contains((string)file_get_contents($app . '/src/cli/migrate.php'), "\$merken('091_tanken')"));
$check('Einstellung', str_contains((string)file_get_contents($app . '/sql/seed.sql'), "'fahrzeug_user_darf_tanken'"));

echo "$ok bestanden, $fail fehlgeschlagen\n";
