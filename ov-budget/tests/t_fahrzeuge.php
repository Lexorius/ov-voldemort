<?php
declare(strict_types=1);
// Fahrzeugakte: Journal-Kette, Fristen, Auftragsnummern, Stein.APP-Abgleich
session_start();
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['vals'] = [];        // Antworten für db_val, der Reihe nach
$GLOBALS['nummern'] = [];     // hoechste Auftragsnummer je Jahr
$GLOBALS['rows'] = [];        // Antworten für db_row
$GLOBALS['alle'] = [];        // Antwort für db_all (Journal)
$GLOBALS['gespeichert'] = []; // setting_save

function db_all(string $sql, array $p = []): array {
    $GLOBALS['abfragen'][] = [$sql, $p];
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM list_items')) {
        $r = [];
        $listen = [
            'fahrzeug_status' => [['einsatzbereit', 'Einsatzbereit', 1], ['bedingt', 'Bedingt einsatzbereit', 0],
                                  ['im-einsatz', 'Im Einsatz', 0], ['wartung', 'In Wartung', 0],
                                  ['nicht-einsatzbereit', 'Nicht einsatzbereit', 0]],
            'auftrag_status'  => [['gemeldet', 'Gemeldet', 1], ['werkstatt', 'In der Werkstatt', 0], ['erledigt', 'Erledigt', 0]],
        ];
        $id = 200;
        foreach ($listen as $key => $items) {
            foreach ($items as [$slug, $label, $def]) {
                $r[] = ['id' => $id++, 'list_key' => $key, 'label' => $label, 'slug' => $slug, 'color' => '#123456',
                        'weight' => 0, 'is_default' => $def, 'is_final' => $slug === 'erledigt' ? 1 : 0,
                        'is_active' => 1, 'sort_order' => 0];
            }
        }
        return $r;
    }
    return $GLOBALS['alle'];
}
function db_row(string $sql, array $p = []): ?array { $GLOBALS['letzte_sql'] = [$sql, $p]; return array_shift($GLOBALS['rows']); }
function db_val(string $sql, array $p = [], mixed $d = null) {
    $GLOBALS['letzte_sql'] = [$sql, $p];
    if (str_contains($sql, 'MAX(nummer)')) {
        // wie die Datenbank: nur Nummern des gefragten Jahres
        return $GLOBALS['nummern'][substr((string)$p[0], 0, 4)] ?? '';
    }
    return $GLOBALS['vals'] ? array_shift($GLOBALS['vals']) : $d;
}
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
final class StubStmt { public function execute(array $p): bool { return true; } }
final class StubPdo { public function prepare(string $s): StubStmt { return new StubStmt(); } }
function db(): StubPdo { return new StubPdo(); }
function db_lock(string $n, int $w = 0): bool { $GLOBALS['locks'][] = ['lock', $n, $w]; return !in_array($n, $GLOBALS['besetzt'] ?? [], true); }
function db_unlock(string $n): void { $GLOBALS['locks'][] = ['unlock', $n]; }
function current_user(): ?array { return ['id' => 4, 'role' => 'leitung', 'display_name' => 'Tester']; }
function can(string $what, mixed $ctx = null): bool { return $GLOBALS['can'][$what] ?? true; }

// Einstellungen stehen vor dem ersten Zugriff fest (settings_all() merkt sie sich)
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'stein_aktiv' => '1', 'stein_api_key' => 'geheim',
                        'stein_bu_id' => '42', 'stein_intervall_minuten' => '10',
                        'stein_letzter_abruf' => (string)time()];

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/uploads.php';
require $app . '/src/lib/webpush.php';
require $app . '/src/lib/vehicles.php';
require $app . '/src/lib/connector.php';
require $app . '/src/lib/notify.php';
require $app . '/src/lib/vehicle_files.php';
require $app . '/src/lib/stein.php';
require $app . '/src/lib/view.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- Fristen ---------- */
$heute = '2026-09-17';
$v = ['hu_bis' => '2026-12-01', 'sp_bis' => '2026-09-30', 'uvv_bis' => '2026-08-01'];
$f = vehicle_deadlines($v, 30, $heute);
$check('drei Fristen', count($f) === 3);
$check('HU in der Zukunft ist ok', $f[0]['status'] === 'ok' && $f[0]['tage'] === 75);
$check('SP bald fällig', $f[1]['status'] === 'bald' && $f[1]['tage'] === 13);
$check('UVV abgelaufen', $f[2]['status'] === 'abgelaufen' && $f[2]['tage'] === -47);
$check('Grenze: genau heute gilt als bald', vehicle_deadlines(['hu_bis' => $heute], 30, $heute)[0]['status'] === 'bald');
$check('Grenze: genau am Warntag', vehicle_deadlines(['hu_bis' => '2026-10-17'], 30, $heute)[0]['status'] === 'bald');
$check('einen Tag später ist ok', vehicle_deadlines(['hu_bis' => '2026-10-18'], 30, $heute)[0]['status'] === 'ok');
$check('gestern ist abgelaufen', vehicle_deadlines(['hu_bis' => '2026-09-16'], 30, $heute)[0]['status'] === 'abgelaufen');
$check('ohne Datum keine Frist', vehicle_deadlines(['hu_bis' => null, 'sp_bis' => ''], 30, $heute) === []);

/* ---------- Journal: Hash-Kette ---------- */
$mach = static function (int $id, string $titel, string $prev, array $x = []) {
    $e = $x + ['id' => $id, 'vehicle_id' => 1, 'created_at' => '2026-09-17 10:0' . $id . ':00', 'user_id' => 4,
        'autor' => 'Tester', 'quelle' => 'mensch', 'art' => 'notiz', 'titel' => $titel, 'text' => 'Text ' . $id,
        'feld' => '', 'alt_wert' => '', 'neu_wert' => '', 'ref_typ' => '', 'ref_id' => null];
    $e['prev_hash'] = $prev;
    $e['hash'] = journal_hash($e, $prev);
    return $e;
};
$e1 = $mach(1, 'Erster', '');
$e2 = $mach(2, 'Zweiter', $e1['hash']);
$e3 = $mach(3, 'Dritter', $e2['hash']);
$kette = [$e1, $e2, $e3];

$check('Hash ist 64 Zeichen', strlen($e1['hash']) === 64);
$check('gleiche Eingabe, gleicher Hash', journal_hash($e1, '') === $e1['hash']);
$check('Kette in Ordnung', journal_check_chain($kette) === ['ok' => true, 'geprueft' => 3, 'fehler' => []]);
$check('leeres Journal ist in Ordnung', journal_check_chain([])['ok'] === true);

$manipuliert = $kette;
$manipuliert[1]['text'] = 'nachträglich geändert';
$p = journal_check_chain($manipuliert);
$check('geänderter Text fällt auf', !$p['ok'] && $p['fehler'][0]['id'] === 2
    && str_contains($p['fehler'][0]['grund'], 'verändert'));
$check('nur der veränderte Eintrag wird gemeldet', count($p['fehler']) === 1);

$geloescht = [$e1, $e3];
$p = journal_check_chain($geloescht);
$check('gelöschter Eintrag fällt auf', !$p['ok'] && str_contains($p['fehler'][0]['grund'], 'fehlt ein Eintrag'));

$vertauscht = [$e2, $e1, $e3];
$check('vertauschte Reihenfolge fällt auf', !journal_check_chain($vertauscht)['ok']);

$datumGeaendert = $kette;
$datumGeaendert[0]['created_at'] = '2020-01-01 00:00:00';
$check('geändertes Datum fällt auf', !journal_check_chain($datumGeaendert)['ok']);
$check('Autor gehört zur Prüfsumme',
    !journal_check_chain([['id' => 1, 'vehicle_id' => 1, 'created_at' => $e1['created_at'], 'user_id' => 4,
        'autor' => 'Jemand anderes', 'quelle' => 'mensch', 'art' => 'notiz', 'titel' => 'Erster', 'text' => 'Text 1',
        'feld' => '', 'alt_wert' => '', 'neu_wert' => '', 'ref_typ' => '', 'ref_id' => null,
        'prev_hash' => '', 'hash' => $e1['hash']]])['ok']);

/* ---------- Journal schreiben ---------- */
$GLOBALS['inserts'] = [];
$GLOBALS['vals'] = [''];   // noch kein Vorgänger
journal_add(7, ['art' => 'notiz', 'titel' => 'Ölwechsel', 'text' => 'bei 45.000 km']);
[$tabelle, $zeile] = $GLOBALS['inserts'][0];
$check('Journal-Tabelle', $tabelle === 'vehicle_journal');
$check('Benutzer übernommen', $zeile['user_id'] === 4 && $zeile['autor'] === 'Tester');
$check('erster Eintrag ohne Vorgänger', $zeile['prev_hash'] === '');
$check('Hash gesetzt', $zeile['hash'] === journal_hash($zeile, ''));
$check('Quelle Vorgabe mensch', $zeile['quelle'] === 'mensch');
$check('Journal sperrt je Fahrzeug', in_array(['lock', 'ovb_journal_7', 10], $GLOBALS['locks'], true));
$check('und gibt die Sperre wieder frei', in_array(['unlock', 'ovb_journal_7'], $GLOBALS['locks'], true));

$GLOBALS['inserts'] = [];
$GLOBALS['vals'] = ['abc123'];
journal_add(7, ['art' => 'stein', 'titel' => 'Status geändert', 'quelle' => 'stein', 'autor' => 'Stein.APP'], null);
$zeile = $GLOBALS['inserts'][0][1];
$check('Stein-Eintrag ohne Benutzer', $zeile['user_id'] === null && $zeile['autor'] === 'Stein.APP');
$check('Vorgänger verkettet', $zeile['prev_hash'] === 'abc123');
$check('Quelle stein', $zeile['quelle'] === 'stein');
$GLOBALS['vals'] = [''];
journal_add(7, ['quelle' => 'unsinn', 'titel' => 'x']);
$check('unbekannte Quelle wird zu mensch', $GLOBALS['inserts'][1][1]['quelle'] === 'mensch');

/* ---------- Stammdaten-Änderungen ---------- */
$GLOBALS['inserts'] = [];
$GLOBALS['vals'] = ['', '', ''];
$alt = ['bezeichnung' => 'GKW 1', 'kennzeichen' => 'THW-11111', 'km_stand' => 45000, 'is_active' => 1,
        'hu_bis' => '2026-12-01', 'status_id' => 200];
$neu = ['bezeichnung' => 'GKW 1', 'kennzeichen' => 'THW-22222', 'km_stand' => 46500, 'is_active' => 1,
        'hu_bis' => '2026-12-01', 'status_id' => 200];
$n = journal_changes(1, $alt, $neu);
$check('zwei Änderungen erkannt', $n === 2);
$check('Kennzeichen im Journal', $GLOBALS['inserts'][0][1]['alt_wert'] === 'THW-11111'
    && $GLOBALS['inserts'][0][1]['neu_wert'] === 'THW-22222');
$check('Kilometer im Journal', $GLOBALS['inserts'][1][1]['feld'] === 'km_stand');
$check('Titel nennt das Feld', $GLOBALS['inserts'][0][1]['titel'] === 'Kennzeichen geändert');

$GLOBALS['inserts'] = [];
$GLOBALS['vals'] = [''];
journal_changes(1, ['status_id' => 200, 'hu_bis' => '2026-12-01'], ['status_id' => 203, 'hu_bis' => '2027-01-31']);
$check('Listenwert als Text', $GLOBALS['inserts'][0][1]['alt_wert'] === 'Einsatzbereit'
    && $GLOBALS['inserts'][0][1]['neu_wert'] === 'In Wartung');
$check('Datum deutsch', $GLOBALS['inserts'][1][1]['neu_wert'] === '31.01.2027');
$check('Feld ohne Eintrag im Formular bleibt unbeachtet',
    journal_changes(1, ['kennzeichen' => 'A'], []) === 0);

/* ---------- Auftragsnummern ---------- */
$GLOBALS['nummern'] = [];
$check('erste Nummer des Jahres', order_next_number('2026') === '2026-0001');
$GLOBALS['nummern'] = ['2026' => '2026-0009'];
$check('nächste Nummer', order_next_number('2026') === '2026-0010');
$GLOBALS['nummern'] = ['2026' => '2026-0099'];
$check('Stellen bleiben', order_next_number('2026') === '2026-0100');
$GLOBALS['nummern'] = ['2026' => '2026-0042'];
$check('neues Jahr beginnt neu', order_next_number('2027') === '2027-0001');
$check('Suche nach dem richtigen Jahr', $GLOBALS['letzte_sql'][1] === ['2027-%']);

/* ---------- Stein.APP: Wartezeit ---------- */
$jetzt = 1_800_000_000;
$check('erster Abruf sofort', stein_wait_reason(0, 10, 0, $jetzt) === null);
$check('zu früh', stein_wait_reason($jetzt - 300, 10, 0, $jetzt) !== null);
$check('genau 10 Minuten reichen', stein_wait_reason($jetzt - 600, 10, 0, $jetzt) === null);
$check('kurz davor nicht', stein_wait_reason($jetzt - 599, 10, 0, $jetzt) !== null);
$grund = stein_wait_reason($jetzt - 120, 10, 0, $jetzt);
$check('Grund nennt Minuten', str_contains((string)$grund, '2 Minute') && str_contains((string)$grund, '10 Minuten'));
$check('Pause hat Vorrang', str_contains((string)stein_wait_reason(0, 10, $jetzt + 300, $jetzt), 'gebremst'));
$check('abgelaufene Pause zählt nicht', stein_wait_reason(0, 10, $jetzt - 1, $jetzt) === null);

/* ---------- Stein.APP: Unterschiede ---------- */
$asset = ['id' => 'a1', 'label' => 'GKW 1', 'radioName' => 'Heros 24/51', 'name' => '', 'category' => 'K (A)',
          'status' => 'ready', 'comment' => '', 'huValidUntil' => '2026-12-01T00:00:00Z',
          'spValidUntil' => null, 'operationReservation' => false, 'issi' => '1234'];
$check('gleicher Stand: kein Unterschied', stein_diff($asset, $asset) === []);
$neuStand = $asset;
$neuStand['status'] = 'notready';
$neuStand['comment'] = 'Bremse defekt';
$d = stein_diff($asset, $neuStand);
$check('zwei Unterschiede', count($d) === 2);
$check('Status übersetzt', $d[0] === ['feld' => 'status', 'label' => 'Status',
    'alt' => 'Einsatzbereit', 'neu' => 'Nicht einsatzbereit']);
$check('Bemerkung von leer', $d[1]['alt'] === '' && $d[1]['neu'] === 'Bremse defekt');
$neuStand = $asset;
$neuStand['huValidUntil'] = '2027-11-30T23:59:59Z';
$check('HU-Datum deutsch', stein_diff($asset, $neuStand)[0] === ['feld' => 'huValidUntil', 'label' => 'HU gültig bis',
    'alt' => '01.12.2026', 'neu' => '30.11.2027']);
$neuStand = $asset;
$neuStand['operationReservation'] = true;
$check('Einsatzvorbehalt als ja/nein', stein_diff($asset, $neuStand)[0]['neu'] === 'ja');
$check('gleiche Zeit anders geschrieben ist keine Änderung',
    stein_diff($asset, array_merge($asset, ['huValidUntil' => '2026-12-01']))  === []);
$check('fehlendes Feld gilt nicht als Änderung', stein_diff($asset, ['id' => 'a1']) === []);
$check('unbekanntes Feld bleibt unbeachtet', stein_diff($asset, array_merge($asset, ['irgendwas' => 'neu'])) === []);
$erst = stein_diff([], $asset);
$check('erster Kontakt: alle gefüllten Felder', count($erst) === 6);
$check('erster Kontakt: leere Felder bleiben weg',
    !in_array('comment', array_column($erst, 'feld'), true)
    && !in_array('spValidUntil', array_column($erst, 'feld'), true));
// Ja/Nein-Felder gelten auch ohne Angabe als "nein" – sonst meldete der erste
// Abgleich einen Wechsel auf "nein", den es nie gab
$check('erster Kontakt: kein Scheinwechsel beim Einsatzvorbehalt',
    !in_array('operationReservation', array_column($erst, 'feld'), true));

/* ---------- Stein.APP: Stammdaten ableiten ---------- */
$daten = stein_vehicle_data($asset, ['funkrufname' => '']);
$check('Status auf unsere Liste', $daten['status_id'] === 200);
$check('HU übernommen', $daten['hu_bis'] === '2026-12-01');
$check('SP bleibt weg, wenn leer', !array_key_exists('sp_bis', $daten));
$check('Funkrufname gefüllt', $daten['funkrufname'] === 'Heros 24/51');
$check('Rohdaten gemerkt', json_decode($daten['stein_daten'], true)['id'] === 'a1');
$daten = stein_vehicle_data($asset, ['funkrufname' => 'Eigener Name']);
$check('eigener Funkrufname bleibt', !array_key_exists('funkrufname', $daten));
$daten = stein_vehicle_data(array_merge($asset, ['status' => 'maint']), []);
$check('Wartung zugeordnet', $daten['status_id'] === 203);
$daten = stein_vehicle_data(array_merge($asset, ['status' => 'unbekannt']), []);
$check('unbekannter Status ändert nichts', !array_key_exists('status_id', $daten));

/* ---------- Kleinkram ---------- */
$check('Name aus Label und Funkrufname', stein_asset_name($asset) === 'GKW 1 · Heros 24/51');
$check('Name ohne alles', stein_asset_name(['id' => 'x9']) === 'Fahrzeug x9');
$check('doppelte Teile nur einmal', stein_asset_name(['label' => 'MTW', 'name' => 'MTW']) === 'MTW');
$check('Datum gekürzt', stein_date('2026-12-01T10:00:00Z') === '2026-12-01');
$check('kein Datum', stein_date('') === null && stein_date(null) === null);

/* ---------- Abgleich wartet (kein Netzzugriff) ---------- */
$GLOBALS['inserts'] = [];
$res = stein_sync();
$check('Abgleich hält das Intervall ein', $res['status'] === 'wartet');
$check('dabei kein Abruf protokolliert', $GLOBALS['inserts'] === []);
$check('Grund wird genannt', str_contains($res['message'], 'Intervall'));

/* ---------- Ansicht der Akte ---------- */
$_SERVER['REQUEST_URI'] = '/?p=vehicle&id=1';
$GLOBALS['alle'] = [];
$html = render_partial('vehicle', [
    'vehicle' => ['id' => 1, 'bezeichnung' => 'GKW 1', 'funkrufname' => 'Heros 24/51', 'kennzeichen' => 'THW-11111',
        'kennung' => '', 'typ_label' => 'GKW', 'typ_id' => 1, 'fachgruppe_label' => 'Bergung', 'status_label' => 'Einsatzbereit',
        'status_color' => '#15803d', 'status_slug' => 'einsatzbereit', 'hersteller' => 'MAN', 'modell' => 'TGM',
        'baujahr' => 2015, 'erstzulassung' => '2015-04-01', 'fahrgestellnummer' => 'WMA123', 'km_stand' => 46500,
        'betriebsstunden' => null, 'standort' => 'Halle 1', 'notiz' => '', 'is_active' => 1,
        'stein_asset_id' => 'a1', 'stein_sync_at' => '2026-09-17 10:00:00', 'extra' => null, 'standort_id' => 5],
    'stellplatzPfad' => 'Halle 1 › Stellplatz 3',
    'fristen' => vehicle_deadlines(['hu_bis' => '2026-09-20', 'uvv_bis' => '2026-08-01'], 30, $heute),
    'auftraege' => [['id' => 5, 'nummer' => '2026-0005', 'titel' => 'Bremse', 'art_label' => 'Instandsetzung',
        'prio_label' => 'Hoch', 'prio_color' => '#c2410c', 'status_label' => 'Gemeldet', 'status_color' => '#0284c7',
        'status_final' => 0, 'gemeldet_am' => '2026-09-15', 'ausfall' => 1]],
    'journal' => [
        ['id' => 2, 'created_at' => '2026-09-17 10:00:00', 'autor' => 'Stein.APP', 'quelle' => 'stein', 'art' => 'stein',
         'titel' => 'Status in der Stein.APP geändert', 'text' => '', 'feld' => 'status', 'alt_wert' => 'Einsatzbereit',
         'neu_wert' => 'Nicht einsatzbereit', 'ref_typ' => '', 'ref_id' => null, 'benutzer' => null],
        ['id' => 1, 'created_at' => '2026-09-16 19:00:00', 'autor' => 'Tester', 'quelle' => 'mensch', 'art' => 'notiz',
         'titel' => 'Ölwechsel', 'text' => 'bei 45.000 km', 'feld' => '', 'alt_wert' => '', 'neu_wert' => '',
         'ref_typ' => '', 'ref_id' => null, 'benutzer' => 'Tester'],
    ],
    'journalArt' => '', 'gesamt' => 2, 'extraFields' => [], 'extra' => [], 'pruefung' => null,
    'bilder' => [], 'dokumente' => [], 'titelbild' => null,
]);
$check('Akte: Stammdaten', str_contains($html, 'THW-11111') && str_contains($html, '46.500 km'));
$check('Akte: abgelaufene Frist', str_contains($html, 'UVV abgelaufen am 01.08.2026'));
$check('Akte: bald fällige Frist', str_contains($html, 'HU bis 20.09.2026'));
$check('Akte: Auftrag mit Ausfall', str_contains($html, '2026-0005') && str_contains($html, 'Ausfall'));
$check('Akte: Journal mit Wertwechsel', str_contains($html, 'Einsatzbereit') && str_contains($html, 'Nicht einsatzbereit'));
$check('Akte: Stein-Eintrag gekennzeichnet', str_contains($html, 'journal__row--stein'));
$check('Akte: Prüf-Link', str_contains($html, 'pruefen=1'));
$check('Akte: Eingabefeld für Journal', str_contains($html, 'name="text"'));
$check('Akte: Stellplatz verlinkt zur Standortseite', str_contains($html, 'p=standorte&amp;id=5') && str_contains($html, '>Halle 1 › Stellplatz 3</a>'));

$html = render_partial('vehicle', [
    'vehicle' => ['id' => 1, 'bezeichnung' => 'GKW 1', 'funkrufname' => '', 'kennzeichen' => '', 'kennung' => '',
        'typ_label' => '', 'typ_id' => null, 'fachgruppe_label' => '', 'status_label' => '', 'status_color' => '',
        'status_slug' => '', 'hersteller' => '', 'modell' => '', 'baujahr' => null, 'erstzulassung' => null,
        'fahrgestellnummer' => '', 'km_stand' => null, 'betriebsstunden' => null, 'standort' => '', 'notiz' => '',
        'is_active' => 0, 'stein_asset_id' => null, 'stein_sync_at' => null, 'extra' => null],
    'fristen' => [], 'auftraege' => [], 'journal' => [], 'journalArt' => '', 'gesamt' => 0,
    'extraFields' => [], 'extra' => [], 'bilder' => [], 'dokumente' => [], 'titelbild' => null,
    'pruefung' => ['ok' => false, 'geprueft' => 3, 'fehler' => [['id' => 2, 'grund' => 'Inhalt passt nicht zur Prüfsumme.']]],
]);
$check('Akte: Warnung bei Auffälligkeit', str_contains($html, 'alert--warn') && str_contains($html, 'Eintrag #2'));
$check('Akte: ausgemustert', str_contains($html, 'ausgemustert'));

/* ---------- Auftragsliste zählt Anhänge ---------- */
$GLOBALS['abfragen'] = [];
order_query(['vehicle_id' => 7]);
[$q, $pp] = $GLOBALS['abfragen'][0];
$check('Fotos gezählt', str_contains($q, "WHERE f.order_id = o.id AND f.art = 'bild') AS fotos"));
$check('Dokumente gezählt', str_contains($q, "AND f.art = 'dokument') AS dokumente"));
$check('Filter bleibt', str_contains($q, 'o.vehicle_id = ?') && $pp === [7]);

/* ---------- Sortierung ---------- */
$check('Vorgabe: Favoriten zuerst', str_starts_with(vehicle_sort_sql('standard'), 'v.is_active DESC, favorit DESC'));
$check('unbekannt wie Vorgabe', vehicle_sort_sql('quatsch') === vehicle_sort_sql('standard'));
$check('Name auf- und absteigend', str_contains(vehicle_sort_sql('name'), 'v.bezeichnung ASC')
    && str_contains(vehicle_sort_sql('name_ab'), 'v.bezeichnung DESC'));
$check('Frist zuerst', str_contains(vehicle_sort_sql('frist'), 'naechste_frist ASC'));
$check('Aufträge zuerst', str_contains(vehicle_sort_sql('auftraege'), 'offene_auftraege DESC'));
$check('Kilometer: leere hinten', str_contains(vehicle_sort_sql('km'), 'v.km_stand IS NULL, v.km_stand DESC'));
$check('Ausgemusterte immer hinten', (function () {
    foreach (array_keys(vehicle_sorts()) as $k) {
        if (!str_starts_with(vehicle_sort_sql($k), 'v.is_active DESC')) { return false; }
    }
    return true;
})());
$check('jede Sortierung beschriftet', count(vehicle_sorts()) === 11
    && array_key_exists('standard', vehicle_sorts()));

/* ---------- Abfrage: Favoriten und Filter ---------- */
$GLOBALS['abfragen'] = [];
vehicle_query(['user_id' => 3, 'sort' => 'frist', 'nur_aktive' => 1]);
[$q, $pp] = $GLOBALS['abfragen'][0];
$check('Favorit als Spalte', str_contains($q, 'FROM vehicle_favorites vf') && str_contains($q, 'AS favorit'));
$check('nächste Frist berechnet', str_contains($q, 'AS naechste_frist'));
$check('Benutzer ist der erste Parameter', $pp === [3]);
$check('nach Frist sortiert', str_contains($q, 'ORDER BY v.is_active DESC, naechste_frist ASC'));

$GLOBALS['abfragen'] = [];
vehicle_query(['user_id' => 3, 'nur_favoriten' => 1, 'q' => 'GKW']);
[$q, $pp] = $GLOBALS['abfragen'][0];
$check('nur Angeheftete', str_contains($q, 'EXISTS (SELECT 1 FROM vehicle_favorites vf2'));
$check('Parameter in der Reihenfolge', $pp[0] === 3 && $pp[1] === '%GKW%' && end($pp) === 3);


/* ---------- Nummer der THW-Verwaltung ---------- */
$GLOBALS['abfragen'] = [];
order_query(['vehicle_id' => 4, 'q' => 'IS-2026']);
[$q, $pp] = $GLOBALS['abfragen'][0];
$check('Suche kennt die THW-Nummer', str_contains($q, 'o.thw_nummer LIKE ?'));
$check('Suche kennt auch Titel und Werkstatt', str_contains($q, 'o.titel LIKE ?') && str_contains($q, 'o.werkstatt LIKE ?'));
$check('Suchparameter fünfmal', count(array_filter($pp, fn($x) => $x === '%IS-2026%')) === 5);

$_POST = ['titel' => 'Bremsen', 'thw_nummer' => '  IS-2026-0815 ', 'gemeldet_von' => 'Ich'];
$GLOBALS['inserts'] = [];
order_save_from_post(null, ['id' => 4, 'bezeichnung' => 'GKW 1'], ['id' => 1, 'display_name' => 'Ich']);
$neu = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'vehicle_orders'))[0][1];
$check('Nummer ohne Leerzeichen gespeichert', ($neu['thw_nummer'] ?? null) === 'IS-2026-0815');
$check('Werkstattnummer bleibt eigenständig', ($neu['auftragsnummer'] ?? null) === '');
$_POST = [];


/* ---------- Stempel auf der Kachel ---------- */
$check('einsatzbereit ohne Stempel', vehicle_stamp(['is_active' => 1, 'status_slug' => 'einsatzbereit']) === null);
$check('bedingt ohne Stempel', vehicle_stamp(['is_active' => 1, 'status_slug' => 'bedingt']) === null);
$check('nicht einsatzbereit', vehicle_stamp(['is_active' => 1, 'status_slug' => 'nicht-einsatzbereit']) === 'Nicht einsatzbereit');
$check('in Wartung', vehicle_stamp(['is_active' => 1, 'status_slug' => 'wartung']) === 'In Wartung');
$check('ausgemustert schlägt den Status',
    vehicle_stamp(['is_active' => 0, 'status_slug' => 'nicht-einsatzbereit']) === 'Ausgemustert');
$check('ohne Angaben kein Stempel', vehicle_stamp([]) === null);


/* ---------- Standort von Hand setzen ---------- */
$GLOBALS['updates'] = [];
$GLOBALS['inserts'] = [];
$fehler = vehicle_position_set(['id' => 4], 51.450123456, 7.0100004, 12.7, ['id' => 2, 'display_name' => 'Ich']);
$check('ohne Fehler', $fehler === null);
$gespeichert = array_values(array_filter($GLOBALS['updates'], fn($u) => $u[0] === 'vehicles'))[0][1];
$check('Koordinaten gerundet gespeichert',
    $gespeichert['geo_lat'] === 51.450123 && $gespeichert['geo_lng'] === 7.01);
$check('Herkunft vermerkt', $gespeichert['geo_quelle'] === 'mensch');
$check('Zeitpunkt gesetzt', !empty($gespeichert['geo_at']));
$eintrag = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'vehicle_journal'))[0][1];
$check('im Journal', $eintrag['titel'] === 'Standort gesetzt'
    && str_contains($eintrag['text'], '51.45012, 7.01000'));
$check('Genauigkeit genannt', str_contains($eintrag['text'], '(±13 m)'));

foreach ([[91.0, 7.0], [51.0, 181.0], [0.0, 0.0]] as [$la, $lo]) {
    $GLOBALS['updates'] = [];
    $check('unbrauchbare Position abgewiesen (' . $la . '/' . $lo . ')',
        vehicle_position_set(['id' => 4], $la, $lo, null, ['id' => 2]) !== null
        && $GLOBALS['updates'] === []);
}

$GLOBALS['inserts'] = [];
vehicle_position_set(['id' => 4], 51.45, 7.01, null, ['id' => 2]);
$eintrag = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'vehicle_journal'))[0][1];
$check('ohne Genauigkeit kein Zusatz', !str_contains($eintrag['text'], '±'));

$akte = (string)file_get_contents(dirname(__DIR__) . '/views/vehicle.php');
$check('Knopf in der Akte', str_contains($akte, 'Jetzt Position setzen')
    && str_contains($akte, 'name="action" value="position"'));
$check('Standort auch ohne Divera', str_contains($akte, '$hatPosition || can(' . "'report_vehicle')"));
$js = (string)file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');
$check('Browser holt den Standort', str_contains($js, 'navigator.geolocation.getCurrentPosition'));
$check('ohne Unterstützung gesperrt', str_contains($js, 'if (!navigator.geolocation)'));


/* ---------- QR-Knöpfe doppelt abgesichert ---------- */
$akte = (string)file_get_contents(dirname(__DIR__) . '/views/vehicle.php');
foreach (['qr_neu', 'qr_weg'] as $was) {
    $stelle = strpos($akte, 'value="' . $was . '"');
    $block = $stelle !== false ? substr($akte, $stelle, 700) : '';
    $check($was . ': erste Rückfrage', str_contains($block, 'data-confirm="'));
    $check($was . ': zweite Rückfrage', str_contains($block, 'data-confirm2="Bist du wirklich sicher?'));
}
$js = (string)file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');
$check('zweite Rückfrage wird ausgewertet', str_contains($js, "getAttribute('data-confirm2')")
    && str_contains($js, 'zweite && !window.confirm(zweite)'));


echo "$ok bestanden, $fail fehlgeschlagen\n";
