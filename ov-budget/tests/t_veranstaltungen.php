<?php
declare(strict_types=1);
/*
 * Veranstaltungen: Termin zusammensetzen, Einladungscodes, Rückmeldungen
 * und das Zählen der Personen.
 */

$GLOBALS['settings'] = [
    'waehrung' => 'EUR', 'haushaltsjahr' => '2026',
    'veranstaltung_code_laenge' => '6', 'veranstaltung_begleiter_max' => '2',
];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['codes'] = [];          // schon vergebene Codes
$GLOBALS['audit'] = [];

function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return $GLOBALS['rows'] ?? []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) {
        $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text',
                'label' => '', 'hint' => '', 'sort_order' => 0];
    }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'FROM event_guests WHERE code')) {
        return in_array($p[0] ?? '', $GLOBALS['codes'], true) ? 1 : null;
    }
    return $d;
}
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
function upload_dir(): string { return sys_get_temp_dir() . '/ovb-event-test'; }

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

/* ================= Termin zusammensetzen ================= */
$check('Datum und Uhrzeit', event_datetime('2026-12-05', '18:30') === '2026-12-05 18:30:00');
$check('ohne Uhrzeit die Vorgabe', event_datetime('2026-12-05', '') === '2026-12-05 00:00:00');
$check('krumme Uhrzeit wird begrenzt', event_datetime('2026-12-05', '99:99') === '2026-12-05 23:59:00');
$check('ohne Datum nichts', event_datetime(null, '18:00') === null
    && event_datetime('05.12.2026', '18:00') === null);

/* ================= Einladungscodes ================= */
$code = event_code_neu(6);
$check('Code hat die gewünschte Länge', strlen($code) === 6);
$check('Code nur aus erlaubten Zeichen', preg_match('/^[' . EVENT_CODE_ZEICHEN . ']+$/', $code) === 1);
$check('keine verwechselbaren Zeichen', !preg_match('/[01OIL]/', EVENT_CODE_ZEICHEN));
$check('Länge wird begrenzt', strlen(event_code_neu(2)) === 4 && strlen(event_code_neu(99)) === 16);

// Ein vergebener Code wird nicht noch einmal ausgegeben
$GLOBALS['codes'] = [];
for ($i = 0; $i < 40; $i++) { $GLOBALS['codes'][] = event_code_neu(4); }
$check('Codes sind eindeutig', count(array_unique($GLOBALS['codes'])) === 40);

$check('Code erkannt', event_code_gueltig('AB23CD') && event_code_gueltig('ab23cd'));
$check('Unsinn abgewiesen', !event_code_gueltig('AB1') && !event_code_gueltig('../etc')
    && !event_code_gueltig('AB0OIL') && !event_code_gueltig(''));
$check('Prüfsumme unabhängig von Groß- und Kleinschreibung',
    event_code_kennung('ab23cd') === event_code_kennung('AB23CD'));
$check('Prüfsumme ist eine Prüfsumme',
    preg_match('/^[a-f0-9]{64}$/', event_code_kennung('AB23CD')) === 1
    && !str_contains(event_code_kennung('AB23CD'), 'AB23CD'));

/* ================= Einladungsadresse ================= */
$kurz = ['url' => 'https://ov.example.de/connector', 'kurz_url' => 'https://i.example.de'];
$lang = ['url' => 'https://ov.example.de/connector', 'kurz_url' => ''];
$check('kurze Adresse: nur der Code', event_invite_url($kurz, 'AB23CD') === 'https://i.example.de/AB23CD');
$check('ohne kurze Adresse der lange Weg',
    event_invite_url($lang, 'AB23CD') === 'https://ov.example.de/connector/index.php?e=AB23CD');
$check('kein Name in der Adresse', !str_contains(event_invite_url($kurz, 'AB23CD'), 'Anna'));

/* ================= Namen ================= */
$check('Name aus dem Kontakt',
    event_guest_name(['vorname' => 'Anna', 'nachname' => 'Beispiel', 'name' => '']) === 'Anna Beispiel');
$check('eigener Name gewinnt',
    event_guest_name(['vorname' => 'Anna', 'nachname' => 'Beispiel', 'name' => 'Gast X']) === 'Gast X');
$check('ohne alles ein Platzhalter', event_guest_name([]) === 'Ohne Namen');

/* ================= Rückmeldungen ================= */
$e = ['id' => 7, 'begleiter_max' => 2, 'kommentare_erlaubt' => 1, 'vertretung_erlaubt' => 1, 'code_laenge' => 6];
$gast = ['id' => 3, 'event_id' => 7, 'status' => 'offen', 'begleiter' => 0, 'vertretung' => '', 'kommentar' => null];

$r = event_guest_antwort($e, $gast, ['status' => 'zusage', 'begleiter' => 1, 'kommentar' => 'Komme gern'], 'einladung');
$check('Zusage gespeichert', $r['status'] === 'zusage' && $r['begleiter'] === 1
    && $r['kommentar'] === 'Komme gern' && $r['quelle'] === 'einladung');
$check('Zeitpunkt gesetzt', $r['geantwortet_am'] !== null);

$r = event_guest_antwort($e, $gast, ['status' => 'zusage', 'begleiter' => 9]);
$check('mehr Begleiter als erlaubt werden gekappt', $r['begleiter'] === 2);

$r = event_guest_antwort($e, $gast, ['status' => 'absage', 'begleiter' => 2]);
$check('Absage ohne Begleiter', $r['status'] === 'absage' && $r['begleiter'] === 0);

$r = event_guest_antwort($e, $gast, ['status' => 'vertretung', 'vertretung' => 'Hr. Müller']);
$check('Vertretung übernommen', $r['status'] === 'vertretung' && $r['vertretung'] === 'Hr. Müller');

$ohne = array_merge($e, ['vertretung_erlaubt' => 0]);
$r = event_guest_antwort($ohne, $gast, ['status' => 'vertretung', 'vertretung' => 'Hr. Müller']);
$check('ohne Erlaubnis keine Vertretung', $r['status'] === 'zusage' && $r['vertretung'] === '');

$stumm = array_merge($e, ['kommentare_erlaubt' => 0]);
$r = event_guest_antwort($stumm, $gast, ['status' => 'zusage', 'kommentar' => 'Hallo']);
$check('Kommentar bleibt draußen, wenn er nicht erlaubt ist', $r['kommentar'] === null);

$r = event_guest_antwort($e, $gast, ['status' => 'unsinn']);
$check('unbekannter Status wird offen', $r['status'] === 'offen' && $r['geantwortet_am'] === null);

$keine = array_merge($e, ['begleiter_max' => 0]);
$r = event_guest_antwort($keine, $gast, ['status' => 'zusage', 'begleiter' => 3]);
$check('ohne Begleiter erlaubt kommt niemand mit', $r['begleiter'] === 0);

/* ================= Zählen ================= */
$liste = [
    ['status' => 'zusage',     'begleiter' => 2, 'kommentar' => 'Freue mich'],
    ['status' => 'zusage',     'begleiter' => 0, 'kommentar' => null],
    ['status' => 'vertretung', 'begleiter' => 1, 'kommentar' => null],
    ['status' => 'absage',     'begleiter' => 0, 'kommentar' => 'Leider nicht'],
    ['status' => 'offen',      'begleiter' => 0, 'kommentar' => null],
];
$s = event_stats($liste);
$check('eingeladen gezählt', $s['eingeladen'] === 5);
$check('Zusagen und Vertretungen getrennt', $s['zusagen'] === 2 && $s['vertretungen'] === 1);
$check('Absagen und Offene', $s['absagen'] === 1 && $s['offen'] === 1);
$check('Personen: eingeladene Person plus Begleiter', $s['personen'] === 6);
$check('Begleiter gezählt', $s['begleiter'] === 3);
$check('Kommentare gezählt', $s['kommentare'] === 2);
$check('leere Liste', event_stats([])['personen'] === 0);

/* ================= Speichern aus dem Formular ================= */
$_POST = ['titel' => '', 'datum' => ''];
[$id, $fehler] = event_save_from_post(null, ['id' => 1]);
$check('ohne Titel und Datum kein Speichern', $id === null && count($fehler) === 2);

$_POST = [
    'titel' => 'Jahresabschluss', 'datum' => '2026-12-05', 'beginn_zeit' => '18:00',
    'ende_zeit' => '23:00', 'ort' => 'Unterkunft', 'status' => 'geplant',
    'kosten_geplant' => '1.250,50', 'begleiter_max' => '3', 'code_laenge' => '8',
    'kommentare_erlaubt' => '1', 'beschreibung' => 'Mit Essen',
];
$GLOBALS['inserts'] = [];
[$id, $fehler] = event_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1] ?? [];
$check('angelegt', $fehler === [] && $GLOBALS['inserts'][0][0] === 'events');
$check('Beginn und Ende zusammengesetzt',
    $daten['beginn'] === '2026-12-05 18:00:00' && $daten['ende'] === '2026-12-05 23:00:00');
$check('Jahr aus dem Datum', $daten['jahr'] === 2026);
$check('deutscher Betrag gelesen', abs($daten['kosten_geplant'] - 1250.50) < 0.001);
$check('Begleiter und Codelänge übernommen', $daten['begleiter_max'] === 3 && $daten['code_laenge'] === 8);
$check('Vertretung ohne Haken ist aus', $daten['vertretung_erlaubt'] === 0);

$_POST['ende_zeit'] = '16:00';
[$id, $fehler] = event_save_from_post(null, ['id' => 1]);
$check('Ende vor Beginn wird abgewiesen', $id === null && count($fehler) === 1);

$_POST['ende_zeit'] = '';
$_POST['ende_datum'] = '';
$GLOBALS['inserts'] = [];
[$id, $fehler] = event_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1];
$check('ohne Endzeit kein Ende', array_key_exists('ende', $daten) && $daten['ende'] === null);

/* ================= Gästeliste oder nur eine Zahl ================= */
$check('Kurznamen gelesen',
    event_typen_ohne_liste('ausbildung, uebung ,, dienstabend') === ['ausbildung', 'uebung', 'dienstabend']);
$check('leere Einstellung', event_typen_ohne_liste('') === [] && event_typen_ohne_liste('  ,  ') === []);

$check('mit Liste ist die Vorgabe', event_mit_gaesteliste([]) && event_mit_gaesteliste(['gaesteliste' => 1]));
$check('ohne Liste erkannt', !event_mit_gaesteliste(['gaesteliste' => 0]));

$mitListe = ['gaesteliste' => 1];
$ohneListe = ['gaesteliste' => 0, 'teilnehmer_geplant' => 20, 'teilnehmer_ist' => null];
$check('mit Liste zählen die Zusagen',
    event_personen($mitListe, ['personen' => 7]) === 7);
$check('ohne Liste zählt die geplante Zahl',
    event_personen($ohneListe, ['personen' => 7]) === 20);
$check('ist schlägt geplant',
    event_personen(array_merge($ohneListe, ['teilnehmer_ist' => 18]), ['personen' => 7]) === 18);
$check('auch die Null zählt',
    event_personen(array_merge($ohneListe, ['teilnehmer_ist' => 0]), []) === 0);

$_POST = [
    'titel' => 'Ausbildung Erste Hilfe', 'datum' => '2026-03-14', 'beginn_zeit' => '09:00',
    'teilnehmer_geplant' => '24', 'teilnehmer_ist' => '21',
];
$GLOBALS['inserts'] = [];
event_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1];
$check('ohne Haken keine Gästeliste', $daten['gaesteliste'] === 0);
$check('Zahlen übernommen', $daten['teilnehmer_geplant'] === 24 && $daten['teilnehmer_ist'] === 21);

$_POST['gaesteliste'] = '1';
unset($_POST['teilnehmer_ist']);
$GLOBALS['inserts'] = [];
event_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1];
$check('mit Haken wieder Gästeliste', $daten['gaesteliste'] === 1);
$check('offene Zahl bleibt leer',
    array_key_exists('teilnehmer_ist', $daten) && $daten['teilnehmer_ist'] === null);

/* ================= Art der Veranstaltung ================= */
$_POST['typ_id'] = '7';
$GLOBALS['inserts'] = [];
event_save_from_post(null, ['id' => 1]);
$check('Art wird gespeichert', $GLOBALS['inserts'][0][1]['typ_id'] === 7);

unset($_POST['typ_id']);
$GLOBALS['inserts'] = [];
event_save_from_post(null, ['id' => 1]);
$daten = $GLOBALS['inserts'][0][1];
$check('ohne Art bleibt das Feld leer', array_key_exists('typ_id', $daten) && $daten['typ_id'] === null);

$_POST['status'] = 'erfunden';
$GLOBALS['inserts'] = [];
event_save_from_post(null, ['id' => 1]);
$check('unbekannter Status wird geplant', $GLOBALS['inserts'][0][1]['status'] === 'geplant');

/* ================= Verpflegung ================= */
$v = verpflegung_vorschlag('2026-10-10 09:00:00', '2026-10-10 17:00:00');
$check('Tagesveranstaltung: nur Mittag', $v === ['fruehstueck' => 0, 'mittag' => 1, 'abend' => 0]);
$v = verpflegung_vorschlag('2026-10-10 07:30:00', '2026-10-11 16:00:00');
$check('Wochenende ab 7:30: 2 Frühstück, 2 Mittag, 1 Abend', $v === ['fruehstueck' => 2, 'mittag' => 2, 'abend' => 1]);
$v = verpflegung_vorschlag('2026-10-10 19:00:00', null);
$check('Abendtermin ohne Ende: nichts', $v === ['fruehstueck' => 0, 'mittag' => 0, 'abend' => 0]);
$tagessaetze = [
    ['id' => 1, 'gueltig_von' => '2025-01-01', 'gueltig_bis' => '2025-12-31', 'tagessatz' => 15.00, 'anteil_fruehstueck' => 20, 'anteil_mittag' => 40, 'anteil_abend' => 40],
    ['id' => 2, 'gueltig_von' => '2026-01-01', 'gueltig_bis' => null, 'tagessatz' => 16.50, 'anteil_fruehstueck' => 20, 'anteil_mittag' => 40, 'anteil_abend' => 40],
];
$check('Tagessatz am Tag: der passende Zeitraum', tagessatz_am($tagessaetze, '2025-06-01')['id'] === 1 && tagessatz_am($tagessaetze, '2026-06-01')['id'] === 2);
$check('vor dem ersten Satz nichts', tagessatz_am($tagessaetze, '2024-01-01') === null);
$check('Verteilung: 16,50 -> 3,30 / 6,60 / 6,60', tagessatz_mahlzeiten($tagessaetze[1]) === ['fruehstueck' => 3.3, 'mittag' => 6.6, 'abend' => 6.6]);
$check('ohne Satz: alles null', tagessatz_mahlzeiten(null) === ['fruehstueck' => 0.0, 'mittag' => 0.0, 'abend' => 0.0]);
$GLOBALS['rows'] = $tagessaetze;
$check('Sätze für den Beginn der Veranstaltung', verpflegung_saetze('2025-03-03 09:00:00') === ['fruehstueck' => 3.0, 'mittag' => 6.0, 'abend' => 6.0]);
$GLOBALS['rows'] = [];

$_POST = ['gueltig_von' => '2026-01-01', 'gueltig_bis' => '', 'tagessatz' => '16,50', 'anteil_fruehstueck' => '20', 'anteil_mittag' => '40', 'anteil_abend' => '40', 'notiz' => ''];
$GLOBALS['inserts'] = [];
[$id, $fehler] = tagessatz_save_from_post(null);
$check('Tagessatz angelegt', $fehler === [] && $GLOBALS['inserts'][0][0] === 'verpflegung_saetze' && $GLOBALS['inserts'][0][1]['tagessatz'] === 16.5);
$_POST['anteil_abend'] = '30';
[$id, $fehler] = tagessatz_save_from_post(null);
$check('Anteile müssen 100 ergeben', $id === null && str_contains($fehler[0], '90 %'));
$_POST['anteil_abend'] = '40'; $_POST['tagessatz'] = '0';
[$id, $fehler] = tagessatz_save_from_post(null);
$check('Tagessatz null abgewiesen', $id === null);

$e = ['verpflegung_fruehstueck' => 1, 'verpflegung_mittag' => 2, 'verpflegung_abend' => 1, 'verpflegung_personen' => null,
      'gaesteliste' => 0, 'teilnehmer_geplant' => 20, 'teilnehmer_ist' => null];
$saetze = ['fruehstueck' => 3.5, 'mittag' => 8.0, 'abend' => 5.0];
$k = verpflegung_kalkulation($e, $saetze, verpflegung_personen($e, []));
$check('Personen wie Teilnehmer', $k['personen'] === 20);
$check('Rechnung je Mahlzeit', $k['zeilen']['fruehstueck']['betrag'] === 70.0 && $k['zeilen']['mittag']['betrag'] === 320.0 && $k['zeilen']['abend']['betrag'] === 100.0);
$check('Summe', $k['gesamt'] === 490.0 && $k['ohne_satz'] === false);
$e['verpflegung_personen'] = 12;
$check('eigene Personenzahl schlägt Teilnehmer', verpflegung_personen($e, []) === 12);
$k = verpflegung_kalkulation($e, ['fruehstueck' => 0, 'mittag' => 8, 'abend' => 5], 12);
$check('fehlender Satz wird gemeldet', $k['ohne_satz'] === true && $k['zeilen']['fruehstueck']['betrag'] === 0.0);
$check('Arten mit Verpflegung: Vorgabe', verpflegung_typen() === ['ausbildung', 'uebung', 'einsatz']);

$tage = verpflegung_tage('2026-10-01 17:00:00', '2026-10-03 11:00:00');
$check('1.10. 17:00 bis 3.10. 11:00: Abend, voller Tag, Frühstück', array_keys($tage) === ['2026-10-01', '2026-10-02', '2026-10-03']
    && $tage['2026-10-01'] === ['fruehstueck' => false, 'mittag' => false, 'abend' => true]
    && $tage['2026-10-02'] === ['fruehstueck' => true, 'mittag' => true, 'abend' => true]
    && $tage['2026-10-03'] === ['fruehstueck' => true, 'mittag' => false, 'abend' => false]);
$check('Summen aus den Tagen', verpflegung_summen($tage) === ['fruehstueck' => 2, 'mittag' => 1, 'abend' => 2]);
$check('Dauer lesbar', event_dauer_text('2026-10-01 17:00:00', '2026-10-03 11:00:00') === '1 Tag 18 Std.'
    && event_dauer_text('2026-10-10 09:00:00', '2026-10-10 12:30:00') === '3 Std. 30 Min.'
    && event_dauer_text('2026-10-10 09:00:00', null) === '');
$abgeglichen = verpflegung_tage_abgleichen(['2026-10-02' => ['fruehstueck' => false, 'mittag' => true, 'abend' => false], '2025-01-01' => ['mittag' => true]],
    '2026-10-01 17:00:00', '2026-10-03 11:00:00');
$check('gespeicherte Tage bleiben, fremde fallen weg, neue bekommen den Vorschlag',
    $abgeglichen['2026-10-02'] === ['fruehstueck' => false, 'mittag' => true, 'abend' => false] && !isset($abgeglichen['2025-01-01'])
    && $abgeglichen['2026-10-01']['abend'] === true);
$ausPost = verpflegung_tage_aus_post(['2026-10-01' => ['abend' => '1', 'mittag' => '1'], '2026-10-02' => [], 'boese' => ['x' => 1]],
    '2026-10-01 17:00:00', '2026-10-03 11:00:00');
$check('Raster aus dem Formular: leerer Tag bleibt leer, unbekannter Tag übergangen',
    $ausPost['2026-10-01'] === ['fruehstueck' => false, 'mittag' => true, 'abend' => true]
    && $ausPost['2026-10-02'] === ['fruehstueck' => false, 'mittag' => false, 'abend' => false]
    && $ausPost['2026-10-03']['fruehstueck'] === true && !isset($ausPost['boese']));

$_POST = ['titel' => 'Grundausbildung', 'datum' => '2026-10-10', 'beginn_zeit' => '08:00', 'ende_datum' => '2026-10-11', 'ende_zeit' => '16:00',
          'status' => 'geplant', 'gaesteliste' => '0', 'teilnehmer_geplant' => '15', 'verpflegung' => '1',
          'verpflegung_fruehstueck' => '', 'verpflegung_mittag' => '', 'verpflegung_abend' => ''];
$GLOBALS['inserts'] = [];
event_save_from_post(null, ['id' => 1]);
$d = $GLOBALS['inserts'][0][1];
$check('leere Mahlzeiten: Vorschlag aus dem Zeitraum', $d['verpflegung'] === 1 && $d['verpflegung_fruehstueck'] === 2 && $d['verpflegung_mittag'] === 2 && $d['verpflegung_abend'] === 1);
$_POST['vt_da'] = '1'; $_POST['vt'] = ['2026-10-10' => ['mittag' => '1'], '2026-10-11' => ['mittag' => '1', 'abend' => '1']];
$GLOBALS['inserts'] = [];
event_save_from_post(null, ['id' => 1]);
$d = $GLOBALS['inserts'][0][1];
$check('Raster aus dem Formular: Summen und Tage gespeichert', $d['verpflegung_mittag'] === 2 && $d['verpflegung_fruehstueck'] === 0 && $d['verpflegung_abend'] === 1
    && json_decode((string)$d['verpflegung_tage'], true)['2026-10-11']['abend'] === true);

echo "$ok bestanden, $fail fehlgeschlagen\n";
