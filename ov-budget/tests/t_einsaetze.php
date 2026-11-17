<?php
declare(strict_types=1);
/*
 * Einsätze: Tage und Schichten lesen, Stunden und Helferstunden rechnen,
 * Zeitraum über mehrere Tage, Einsatznummer, Geld aus den Buchungen,
 * Speichern, Ansichten und der Bezug im Budget.
 */
session_start();
date_default_timezone_set('Europe/Berlin');

$GLOBALS['settings'] = ['waehrung' => 'EUR', 'einsatz_modul_name' => 'Einsätze', 'budget_rundung' => '0'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['sql'] = [];
$GLOBALS['rows'] = [];     // Antworten je SQL-Bruchstück

function db_all(string $sql, array $p = []): array {
    $GLOBALS['sql'][] = $sql;
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    foreach ($GLOBALS['rows'] as $stueck => $antwort) {
        if (str_contains($sql, $stueck)) { return $antwort; }
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    // das Zeichen 6 gibt es in der Kartenbibliothek, alle anderen nicht (mehr)
    return str_contains($sql, 'FROM karten_symbole') && (int)($p[0] ?? 0) === 6 ? ['id' => 6, 'name' => 'Feuerwehr'] : null;
}
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int {
    if (($GLOBALS['insert_scheitert'] ?? '') === $t) { throw new RuntimeException('Fremdschlüssel verletzt'); }
    $GLOBALS['inserts'][] = [$t, $d]; return 40 + count($GLOBALS['inserts']);
}
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
final class StubStmtE { public function execute(array $p): bool { $GLOBALS['audit'][] = $p; return true; } }
final class StubPdoE {
    public function prepare(string $s): StubStmtE { return new StubStmtE(); }
    public function beginTransaction(): bool { $GLOBALS['tx'][] = 'begin'; return true; }
    public function commit(): bool { $GLOBALS['tx'][] = 'commit'; return true; }
    public function rollBack(): bool { $GLOBALS['tx'][] = 'rollback'; return true; }
}
function db(): StubPdoE { return new StubPdoE(); }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? true; }
function current_user(): ?array { return ['id' => 7, 'role' => 'leitung', 'display_name' => 'Tester']; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'expenses', 'kalender', 'karten', 'einsaetze'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$nur = static fn(string $tabelle): array => array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === $tabelle));

/* ================= Uhrzeiten ================= */
$check('Uhrzeit: aufgefüllt, mit Sekunden, mit Punkt', einsatz_zeit('7:05') === '07:05' && einsatz_zeit('18:30:00') === '18:30' && einsatz_zeit('6.00') === '06:00');
$check('Uhrzeit: leer ist null, Unsinn ist false', einsatz_zeit('') === null && einsatz_zeit(null) === null && einsatz_zeit('25:00') === false && einsatz_zeit('abc') === false && einsatz_zeit('12:61') === false);

/* ================= Tage aus dem Formular ================= */
[$tage, $fehler] = einsatz_tage_aus_post([
    'tag_datum'      => ['2026-11-15', '', '2026-11-14', '2026-11-14'],
    'tag_von'        => ['22:00', '', '18:00', '08:00'],
    'tag_bis'        => ['06:00', '', '', '16:30'],
    'tag_staerke'    => ['1/2/9', '', '4', '1/1/6/8'],
    'tag_taetigkeit' => ['Nachtschicht Pumpen', '', 'Bereitschaft', 'Sandsackverbau'],
]);
$check('drei Zeilen, die leere fällt weg, keine Fehler', count($tage) === 3 && $fehler === []);
$check('sortiert nach Datum und Beginn', array_column($tage, 'taetigkeit') === ['Sandsackverbau', 'Bereitschaft', 'Nachtschicht Pumpen']);
$check('Stärke gelesen: mit Summe, einzelne Zahl als Helfer', [$tage[0]['staerke_f'], $tage[0]['staerke_u'], $tage[0]['staerke_h']] === [1, 1, 6]
    && [$tage[1]['staerke_f'], $tage[1]['staerke_u'], $tage[1]['staerke_h']] === [0, 0, 4]);
$check('Ende darf fehlen', $tage[1]['von'] === '18:00' && $tage[1]['bis'] === null);

[$leer, $fehlerLeer] = einsatz_tage_aus_post(['tag_datum' => ['', ''], 'tag_von' => ['', '']]);
$check('nur leere Zeilen: nichts, kein Fehler', $leer === [] && $fehlerLeer === []);
[, $f1] = einsatz_tage_aus_post(['tag_datum' => [''], 'tag_von' => ['08:00']]);
$check('Zeile ohne Datum wird gemeldet', count($f1) === 1 && str_contains($f1[0], 'Datum'));
[, $f2] = einsatz_tage_aus_post(['tag_datum' => ['2026-02-30']]);
$check('den 30. Februar gibt es nicht', count($f2) === 1 && str_contains($f2[0], 'kein gültiges Datum'));
[, $f3] = einsatz_tage_aus_post(['tag_datum' => ['2026-11-14'], 'tag_bis' => ['12:00']]);
$check('Ende ohne Beginn wird gemeldet', count($f3) === 1 && str_contains($f3[0], 'fehlt der Beginn'));
[, $f4] = einsatz_tage_aus_post(['tag_datum' => ['2026-11-14'], 'tag_staerke' => ['viele']]);
$check('Stärke, die keine ist, wird gemeldet', count($f4) === 1 && str_contains($f4[0], 'Stärke'));
[, $f5] = einsatz_tage_aus_post(['tag_datum' => ['2026-11-14'], 'tag_von' => ['25:99']]);
$check('falsche Uhrzeit wird gemeldet', count($f5) === 1 && str_contains($f5[0], 'hh:mm'));
[$lang] = einsatz_tage_aus_post(['tag_datum' => ['2026-11-14'], 'tag_taetigkeit' => [str_repeat('ä', 600)]]);
$check('Tätigkeit auf 500 Zeichen gekürzt', mb_strlen($lang[0]['taetigkeit']) === 500);

/* ================= Stunden ================= */
$check('Tagschicht: 8,5 Stunden', einsatz_tag_minuten(['von' => '08:00', 'bis' => '16:30']) === 510);
$check('über Mitternacht: 22 bis 6 sind acht Stunden', einsatz_tag_minuten(['von' => '22:00:00', 'bis' => '06:00:00']) === 480);
$check('gleiche Uhrzeit ist ein ganzer Tag', einsatz_tag_minuten(['von' => '08:00', 'bis' => '08:00']) === 1440);
$check('ohne Ende oder ohne Uhrzeit keine Dauer', einsatz_tag_minuten(['von' => '18:00', 'bis' => null]) === null && einsatz_tag_minuten(['von' => null, 'bis' => null]) === null);
$check('Stunden als Text', einsatz_stunden_text(480) === '8 h' && einsatz_stunden_text(510) === '8,5 h' && einsatz_stunden_text(735) === '12,25 h' && einsatz_stunden_text(0) === '0 h'
    && einsatz_stunden_text(90000) === '1.500 h');
$check('Stärke einer Zeile', einsatz_tag_staerke(['staerke_f' => 1, 'staerke_u' => 2, 'staerke_h' => 9]) === ['f' => 1, 'u' => 2, 'h' => 9, 'gesamt' => 12, 'text' => '1/2/9/12']);

/* ================= Zeitraum und Summen über mehrere Tage ================= */
$check('Zeitraum einer Nachtschicht endet am Folgetag', einsatz_tag_zeitraum(['datum' => '2026-12-31', 'von' => '22:00', 'bis' => '06:00'])
    === ['beginn' => '2026-12-31 22:00:00', 'ende' => '2027-01-01 06:00:00']);
$check('ohne Uhrzeit der ganze Tag', einsatz_tag_zeitraum(['datum' => '2026-11-14', 'von' => null, 'bis' => null])
    === ['beginn' => '2026-11-14 00:00:00', 'ende' => '2026-11-14 23:59:59']);
$check('offene Schicht bis zum Ende des Tages', einsatz_tag_zeitraum(['datum' => '2026-11-14', 'von' => '18:00', 'bis' => null])['ende'] === '2026-11-14 23:59:59');

$s = einsatz_summen($tage);
$check('zwei Einsatztage in drei Zeilen', $s['tage'] === 2 && $s['zeilen'] === 3);
$check('Stunden: 8,5 + 8, die offene Schicht zählt nicht', $s['minuten'] === 510 + 480 && $s['ohne_dauer'] === 1);
$check('Helferstunden: Dauer mal Stärke', $s['helferminuten'] === 510 * 8 + 480 * 12);
$check('größte Stärke', $s['staerke_max'] === 12 && $s['staerke_max_text'] === '1/2/9/12');
$check('Zeitraum vom ersten Beginn bis zum letzten Ende', $s['beginn'] === '2026-11-14 08:00:00' && $s['ende'] === '2026-11-16 06:00:00');
$check('ohne Tage: alles null', einsatz_summen([]) === ['tage' => 0, 'zeilen' => 0, 'minuten' => 0, 'helferminuten' => 0, 'staerke_max' => 0,
    'staerke_max_text' => '', 'ohne_dauer' => 0, 'beginn' => null, 'ende' => null]);

$check('Zeitraum als Text: ein Tag, mehrere Tage', einsatz_zeitraum_text(['beginn' => '2026-11-14 08:00:00', 'ende' => '2026-11-14 16:30:00']) === '14.11.2026'
    && einsatz_zeitraum_text(['beginn' => '2026-11-14 08:00:00', 'ende' => '2026-11-16 06:00:00']) === '14.11.2026 – 16.11.2026'
    && einsatz_zeitraum_text(['beginn' => '2026-11-14 08:00:00', 'ende' => null]) === '14.11.2026' && einsatz_zeitraum_text([]) === '');
$check('Tag mit Wochentag', einsatz_tag_text('2026-11-14') === 'Sa 14.11.2026' && einsatz_tag_text('2026-11-16') === 'Mo 16.11.2026');

/* ================= Nummer und Bezeichnung ================= */
$check('nächste Nummer des Jahres', einsatz_naechste_nummer(2026, []) === '2026-001'
    && einsatz_naechste_nummer(2026, ['2026-001', '2026-014', '2025-099', 'LST 4711', ' 2026-003 ']) === '2026-015');
$check('Bezeichnung mit und ohne Nummer', einsatz_bezeichnung(['nummer' => '2026-014', 'titel' => 'Hochwasser']) === '2026-014 · Hochwasser'
    && einsatz_bezeichnung(['nummer' => ' ', 'titel' => 'Hochwasser']) === 'Hochwasser');
$check('Stand: unbekannt wird laufend, Abzeichen mit Farbe', einsatz_status('x') === 'laufend' && einsatz_status('abgerechnet') === 'abgerechnet'
    && str_contains(einsatz_status_badge('beendet'), 'Abrechnung offen') && str_contains(einsatz_status_badge('beendet'), EINSATZ_STATUS['beendet']['farbe']));

/* ================= Geld aus den Buchungen ================= */
$buchungen = [
    ['art' => 'ausgabe',  'status' => 'bezahlt',     'betrag_brutto' => '120.50'],
    ['art' => 'ausgabe',  'status' => 'offen',       'betrag_brutto' => '79.50'],
    ['art' => 'ausgabe',  'status' => 'geplant',     'betrag_brutto' => '300.00'],
    ['art' => 'einnahme', 'status' => 'bezahlt',     'betrag_brutto' => '1000.00'],
    ['art' => 'einnahme', 'status' => 'abgerechnet', 'betrag_brutto' => '2500.00'],
    ['art' => 'einnahme', 'status' => 'zugesagt',    'betrag_brutto' => '400.00'],
];
$g = einsatz_geld_aus($buchungen);
$check('Ausgaben: bezahlt und gebucht sind Ist, geplant steht daneben', $g['ausgaben'] === 200.0 && $g['ausgaben_geplant'] === 300.0);
$check('Einnahmen: nur Eingegangenes ist Ist, der Rest ist unterwegs', $g['einnahmen'] === 1000.0 && $g['einnahmen_offen'] === 2900.0);
$check('Saldo jetzt und mit allem, was aussteht', $g['saldo'] === 800.0 && $g['saldo_erwartet'] === 3400.0 && $g['buchungen'] === 6);
$check('ohne Buchungen: null', einsatz_geld_aus([])['saldo'] === 0.0 && einsatz_geld_aus([])['buchungen'] === 0);

$einsatz = ['id' => 5, 'nummer' => '2026-014', 'titel' => 'Hochwasser Hunte', 'anforderer' => 'Landkreis Oldenburg', 'fachgruppe_id' => 3];
$ve = einsatz_buchung_vorgabe($einsatz, 'einnahme', 77);
$check('Einnahme vom Einsatz aus: Bezug, Nummer, Kostenträger, Kategorie, erwartet', $ve['einsatz_id'] === 5 && $ve['referenz'] === '2026-014'
    && $ve['lieferant'] === 'Landkreis Oldenburg' && $ve['kategorie_id'] === 77 && $ve['status'] === 'geplant'
    && $ve['bezeichnung'] === 'Kostenerstattung 2026-014 · Hochwasser Hunte' && $ve['fachgruppe_id'] === 3);
$va = einsatz_buchung_vorgabe($einsatz, 'ausgabe', 77);
$check('Ausgabe vom Einsatz aus: nur Bezug, Nummer und Fachgruppe', $va === ['einsatz_id' => 5, 'referenz' => '2026-014', 'fachgruppe_id' => 3]);

/* ================= Speichern ================= */
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['titel' => '', 'tag_datum' => ['']];
[$id, $err] = einsatz_save_from_post(null, current_user());
$check('ohne Titel und ohne Tag: zwei Fehler, nichts gespeichert', $id === null && count($err) === 2 && $nur('einsaetze') === []);

$_POST = [
    'titel' => 'Hochwasser Hunte', 'nummer' => '2026-014', 'art_id' => '11', 'status' => 'beendet',
    'anforderer' => 'Landkreis Oldenburg', 'ort' => 'Deich Süd', 'fachgruppe_id' => '', 'beschreibung' => 'Pegel steigt', 'notiz' => '',
    'tag_datum' => ['2026-11-14', '2026-11-15', '2026-11-16'], 'tag_von' => ['08:00', '08:00', '08:00'], 'tag_bis' => ['18:00', '18:00', '14:00'],
    'tag_staerke' => ['1/2/9', '1/2/9', '0/1/5'], 'tag_taetigkeit' => ['Verbau', 'Verbau', 'Rückbau'],
    'tag_key' => ['0', '1', '7'], 'tag_fachgruppe' => ['21', '22', ''],
    'tag_fz' => ['0' => ['3', '8', '3'], '7' => ['3']], 'tag_km' => ['0' => ['3' => '12,5', '8' => ''], '1' => ['12' => '40'], '7' => ['3' => '1.234,5']],
    'tag_bh' => ['0' => ['8' => '2,5'], '1' => ['12' => '']],
];
[$id, $err] = einsatz_save_from_post(null, current_user());
$kopf = $nur('einsaetze')[0][1] ?? [];
$check('Einsatz über drei Tage angelegt', $id > 0 && $err === [] && count($nur('einsaetze')) === 1);
$check('Beginn, Ende und Jahr aus den Tagen', $kopf['beginn'] === '2026-11-14 08:00:00' && $kopf['ende'] === '2026-11-16 14:00:00' && $kopf['jahr'] === 2026);
$check('Felder übernommen, Ersteller gesetzt', $kopf['nummer'] === '2026-014' && $kopf['status'] === 'beendet' && $kopf['art_id'] === 11
    && $kopf['fachgruppe_id'] === null && $kopf['created_by'] === 7 && $kopf['updated_by'] === 7);
$zeilen = $nur('einsatz_tage');
$check('drei Tage gespeichert, mit Sekunden und Stärke', count($zeilen) === 3 && $zeilen[0][1]['von'] === '08:00:00' && $zeilen[2][1]['bis'] === '14:00:00'
    && $zeilen[2][1]['staerke_u'] === 1 && $zeilen[2][1]['staerke_h'] === 5 && $zeilen[0][1]['einsatz_id'] === $id);
$check('Fachgruppe je Zeile', $zeilen[0][1]['fachgruppe_id'] === 21 && $zeilen[1][1]['fachgruppe_id'] === 22 && $zeilen[2][1]['fachgruppe_id'] === null);
$jeZeile = array_map(static fn($i) => [$i[1]['tag_id'], $i[1]['vehicle_id'], $i[1]['km']], $nur('einsatz_tag_vehicles'));
$check('Fahrzeuge je Zeile mit Kilometern; wer Kilometer hat, ist gewählt', $jeZeile === [
    [$zeilen[0][1]['einsatz_id'] + 1, 3, 12.5], [$zeilen[0][1]['einsatz_id'] + 1, 8, null],
    [$jeZeile[2][0], 12, 40.0], [$jeZeile[3][0], 3, 1234.5]] && $jeZeile[2][0] !== $jeZeile[3][0] && $jeZeile[2][0] !== $jeZeile[0][0]);
$check('Betriebsstunden je Fahrzeug gespeichert, ohne Kilometer', array_map(static fn($i) => $i[1]['betriebsstunden'], $nur('einsatz_tag_vehicles')) === [null, 2.5, null, null]);
$check('Fahrzeuge des Einsatzes sind die Summe aus den Zeilen, jedes einmal', array_map(static fn($i) => $i[1]['vehicle_id'], $nur('einsatz_vehicles')) === [3, 8, 12]);
$check('vorher alte Tage und Fahrzeuge entfernt', count(array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'DELETE FROM einsatz_tage'))) === 1
    && count(array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'DELETE FROM einsatz_vehicles'))) === 1);

$GLOBALS['inserts'] = [];
$_POST = ['titel' => 'Hochwasser Hunte', 'status' => 'abgerechnet', 'tag_datum' => ['2026-11-14'], 'tag_von' => [''], 'tag_bis' => ['']];
[$id2, $err2] = einsatz_save_from_post(['id' => 5, 'status' => 'beendet'], current_user());
$aend = end($GLOBALS['updates']);
$check('Bearbeiten: Update statt Insert, ganztägiger Tag', $id2 === 5 && $err2 === [] && $aend[0] === 'einsaetze' && $aend[2] === [5]
    && $aend[1]['beginn'] === '2026-11-14 00:00:00' && $aend[1]['ende'] === '2026-11-14 23:59:59' && $aend[1]['status'] === 'abgerechnet' && !isset($aend[1]['created_by']));
$check('Zeile ohne Fahrzeuge: der Einsatz hat keine mehr', $nur('einsatz_vehicles') === [] && $nur('einsatz_tag_vehicles') === []);

// Scheitert eine Zeile (etwa: das Fahrzeug wurde inzwischen gelöscht), wird alles zurückgenommen
$GLOBALS['tx'] = [];
$GLOBALS['insert_scheitert'] = 'einsatz_tag_vehicles';
$_POST = ['titel' => 'Hochwasser Hunte', 'tag_key' => ['0'], 'tag_datum' => ['2026-11-14'], 'tag_fz' => ['0' => ['999']]];
$geworfen = false;
try { einsatz_save_from_post(['id' => 5, 'status' => 'beendet'], current_user()); } catch (RuntimeException $ex) { $geworfen = true; }
$GLOBALS['insert_scheitert'] = '';
$check('Speichern scheitert: zurückgenommen statt halb geschrieben, der Fehler geht weiter', $geworfen && $GLOBALS['tx'] === ['begin', 'rollback']);
$GLOBALS['tx'] = [];
einsatz_save_from_post(['id' => 5, 'status' => 'beendet'], current_user());
$check('Speichern gelingt: in einer Transaktion abgeschlossen', $GLOBALS['tx'] === ['begin', 'commit']);

$GLOBALS['execs'] = [];
einsatz_delete(['id' => 5, 'nummer' => '2026-014', 'titel' => 'Hochwasser Hunte']);
$check('Löschen: Buchungen verlieren nur den Bezug', str_contains($GLOBALS['execs'][0][0], 'UPDATE expenses SET einsatz_id = NULL') && str_contains($GLOBALS['execs'][1][0], 'DELETE FROM einsaetze'));

/* ================= Fremde Kräfte ================= */
[$fremde, $ff] = einsatz_fremde_aus_post([
    'fremd_einheit' => ['  FF   Musterstadt ', '', 'DRK Bereitschaft', 'FF Musterstadt', '', 'THW OV Nachbar', 'ASB'],
    'fremd_datum'   => ['2026-11-15', '', '2026-11-14', '2026-11-14', '2026-11-14', '2026-11-14', '2026-11-14'],
    'fremd_von'     => ['08:00', '', '', '18:00', '09:00', '25:00', ''],
    'fremd_bis'     => ['16:00', '', '', '', '', '', ''],
    'fremd_staerke' => ['1/3/12', '', '6', '1/2/9/12', '', '', 'viele'],
    'fremd_symbol'  => ['6', '', '', '4', '', '', ''],
]);
$check('fremde Kräfte: drei Zeilen, die leere fällt weg, sortiert nach Datum und Beginn', array_column($fremde, 'einheit') === ['DRK Bereitschaft', 'FF Musterstadt', 'FF Musterstadt']
    && array_column($fremde, 'von') === [null, '18:00', '08:00']);
$check('fremde Kräfte: Name gesäubert, Stärke wie bei den eigenen Zeilen, Zeichen', [$fremde[2]['staerke_f'], $fremde[2]['staerke_u'], $fremde[2]['staerke_h']] === [1, 3, 12]
    && $fremde[2]['symbol_id'] === 6 && $fremde[0]['symbol_id'] === null && $fremde[0]['staerke_h'] === 6 && $fremde[1]['staerke_h'] === 9);
$check('fremde Kräfte: ohne Einheit, mit falscher Uhrzeit, mit unlesbarer Stärke – je ein Fehler mit Ort', count($ff) === 3
    && str_starts_with($ff[0], 'Fremde Kräfte, Zeile') && str_contains($ff[0], 'Einheit nennen') && str_contains($ff[1], 'hh:mm') && str_contains($ff[2], 'Stärke'));
$check('Vorauswahl für die Karte: jede Einheit einmal, mit Stärke und Zeichen der jüngsten Zeile', einsatz_fremde_vorauswahl($fremde) === [
    ['name' => 'DRK Bereitschaft', 'staerke' => '0/0/6', 'symbol' => 0], ['name' => 'FF Musterstadt', 'staerke' => '1/3/12', 'symbol' => 6]]);
$check('Vorauswahl: ohne Stärke bleibt sie leer', einsatz_fremde_vorauswahl([['einheit' => 'Polizei', 'staerke_f' => 0, 'staerke_u' => 0, 'staerke_h' => 0]]) === [['name' => 'Polizei', 'staerke' => '', 'symbol' => 0]]);

$GLOBALS['inserts'] = []; $GLOBALS['execs'] = [];
$_POST = ['titel' => 'Hochwasser Hunte', 'tag_datum' => ['2026-11-14'],
    'fremd_einheit' => ['FF Musterstadt', 'DRK', ''], 'fremd_datum' => ['2026-11-14', '2026-11-14', ''], 'fremd_von' => ['08:00', '', ''], 'fremd_bis' => ['16:00', '', ''],
    'fremd_staerke' => ['1/3/12', '', ''], 'fremd_symbol' => ['6', '4', '']];
[$id3, $err3] = einsatz_save_from_post(['id' => 5, 'status' => 'beendet'], current_user());
$gespeichert = array_column($nur('einsatz_fremde'), 1);
$check('fremde Kräfte gespeichert: zwei Zeilen am Einsatz, alte vorher weg', $id3 === 5 && $err3 === [] && count($gespeichert) === 2 && $gespeichert[1]['einsatz_id'] === 5
    && $gespeichert[1]['einheit'] === 'FF Musterstadt' && $gespeichert[1]['von'] === '08:00:00' && $gespeichert[1]['staerke_h'] === 12
    && count(array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'DELETE FROM einsatz_fremde'))) === 1);
$check('ein Zeichen, das es nicht mehr gibt, lässt das Speichern nicht scheitern', $gespeichert[1]['symbol_id'] === 6 && $gespeichert[0]['symbol_id'] === null);
$GLOBALS['execs'] = [];
$_POST = ['titel' => 'Hochwasser Hunte', 'tag_datum' => ['2026-11-14']];
einsatz_save_from_post(['id' => 5, 'status' => 'beendet'], current_user());
$check('ohne die Felder im Formular bleiben die fremden Kräfte stehen', array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'einsatz_fremde')) === []);
$GLOBALS['inserts'] = [];
$_POST = ['titel' => 'Hochwasser Hunte', 'tag_datum' => ['2026-11-14'], 'fremd_einheit' => ['FF Musterstadt'], 'fremd_datum' => ['']];
[$id4, $err4] = einsatz_save_from_post(['id' => 5, 'status' => 'beendet'], current_user());
$fremdOhneDatum = array_column($nur('einsatz_fremde'), 1);
$check('fremde Kraft ohne Datum bekommt den ersten Einsatztag', $id4 === 5 && $err4 === [] && count($fremdOhneDatum) === 1 && $fremdOhneDatum[0]['datum'] === '2026-11-14');
[, $f5] = einsatz_fremde_aus_post(['fremd_einheit' => ['FF Musterstadt'], 'fremd_datum' => ['']]);
$check('… ohne bekannten Einsatztag bleibt es ein Fehler', count($f5) === 1 && str_contains($f5[0], 'Datum'));
// Leer heißt: keine Einheit, keine Zeit, keine Stärke – ein Datum oder ein Zeichen allein zählt nicht
[$nurDatum, $fd] = einsatz_fremde_aus_post(['fremd_einheit' => ['', ''], 'fremd_datum' => ['2026-11-15', ''], 'fremd_von' => ['', ''], 'fremd_bis' => ['', ''], 'fremd_staerke' => ['', ''], 'fremd_symbol' => ['', '6']]);
$check('Zeile nur mit Datum oder nur mit Zeichen gilt als leer', $nurDatum === [] && $fd === []);
[, $fz] = einsatz_fremde_aus_post(['fremd_einheit' => [''], 'fremd_datum' => ['2026-11-15'], 'fremd_von' => ['08:00']]);
$check('Zeit ohne Einheit wird weiter gemeldet – mit dem Rat, die Zeile zu leeren', count($fz) === 1 && str_contains($fz[0], 'Einheit') && str_contains($fz[0], 'leeren'));
$check('Seite behält nach einem Fehler auch ein gewähltes Zeichen', str_contains((string)file_get_contents($app . '/src/pages/einsatz_edit.php'), "\$f['symbol_id'] = (int)(\$spalte('fremd_symbol')[\$i] ?? 0);"));

/* ================= Budget: Buchung trägt den Bezug ================= */
$GLOBALS['inserts'] = [];
$_POST = ['art' => 'einnahme', 'bezeichnung' => 'Kostenerstattung', 'datum' => '2026-12-01', 'betrag' => '1.250,00', 'status' => 'geplant', 'einsatz_id' => '5'];
[$bid, $berr] = expense_save_from_post(null, current_user());
$check('Einnahme mit Bezug zum Einsatz gespeichert', $bid > 0 && $berr === [] && ($nur('expenses')[0][1]['einsatz_id'] ?? null) === 5 && $nur('expenses')[0][1]['art'] === 'einnahme');
$_POST = ['art' => 'ausgabe', 'bezeichnung' => 'Verpflegung', 'datum' => '2026-11-14', 'betrag' => '84,20', 'status' => 'bezahlt', 'einsatz_id' => ''];
expense_save_from_post(null, current_user());
$zweite = $nur('expenses')[1][1] ?? [];
$check('ohne Auswahl kein Bezug', array_key_exists('einsatz_id', $zweite) && $zweite['einsatz_id'] === null && $zweite['art'] === 'ausgabe');
$GLOBALS['sql'] = [];
expense_query(['jahr' => 2026, 'einsatz_id' => 5]);
$abfrage = (string)end($GLOBALS['sql']);
$check('Buchungsliste kennt den Einsatz und filtert danach', str_contains($abfrage, 'LEFT JOIN einsaetze') && str_contains($abfrage, 'es.titel AS einsatz_titel') && str_contains($abfrage, 'e.einsatz_id = ?'));
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';

/* ================= Kalender ================= */
$check('Kalender kennt die Quelle Einsätze', isset(KALENDER_QUELLEN['einsatz']) && in_array('einsatz', kalender_quellen_fuer(current_user()), true));
$GLOBALS['rechte'] = ['view_einsaetze' => false];
$check('ohne Recht keine Einsätze im Kalender', !in_array('einsatz', kalender_quellen_fuer(current_user()), true));
$GLOBALS['rechte'] = [];
$GLOBALS['rows'] = ['FROM einsaetze e LEFT JOIN list_items a' => [
    ['id' => 5, 'nummer' => '2026-014', 'titel' => 'Hochwasser Hunte', 'beginn' => '2026-11-14 08:00:00', 'ende' => '2026-11-16 14:00:00', 'ort' => 'Deich Süd',
     'status' => 'beendet', 'art_color' => '', 'art_label' => 'Einsatz', 'fahrzeuge_namen' => 'GKW 1 · MTW'],
]];
$kal = kalender_quelle('einsatz', '2026-11-01', '2026-11-30', current_user());
$check('Einsatz im Kalender: mehrtägig, Farbe der Quelle, Link, Fahrzeuge', count($kal) === 1 && $kal[0]['quelle'] === 'einsatz' && $kal[0]['ende'] === '2026-11-16 14:00:00'
    && $kal[0]['farbe'] === KALENDER_QUELLEN['einsatz']['color'] && str_contains($kal[0]['url'], 'p=einsatz&id=5') && $kal[0]['fahrzeuge'] === 'GKW 1 · MTW'
    && $kal[0]['untertitel'] === 'Einsatz · Einsatz · 2026-014' && $kal[0]['ganztag'] === false);
$jeTag = kalender_je_tag($kal, '2026-11-01', '2026-11-30');
$check('steht an jedem seiner drei Tage', isset($jeTag['2026-11-14'], $jeTag['2026-11-15'], $jeTag['2026-11-16']) && empty($jeTag['2026-11-17']));
$GLOBALS['rows'] = [];

/* ================= Ansichten ================= */
$zeile = ['id' => 5, 'nummer' => '2026-014', 'titel' => 'Hochwasser <Hunte>', 'beginn' => '2026-11-14 08:00:00', 'ende' => '2026-11-16 14:00:00', 'ort' => 'Deich Süd',
    'anforderer' => 'Landkreis', 'status' => 'beendet', 'art_label' => 'Einsatz', 'art_color' => '#b91c1c', 'fachgruppe_label' => '', 'fahrzeuge_namen' => 'GKW 1',
    'tage' => 3, 'staerke_max' => 12, 'ausgaben' => '200.00', 'einnahmen' => '1000.00', 'jahr' => 2026, 'fachgruppe_id' => null,
    'beschreibung' => 'Pegel steigt', 'notiz' => '', 'created_at' => '2026-11-14 07:30:00', 'ersteller' => 'Tester', 'art_id' => 11];
$blatt = seiten_blaettern(1, 1, 50);
$liste = render_partial('einsaetze', ['laufend' => [], 'uebrige' => [$zeile], 'blatt' => $blatt, 'blattParams' => [], 'jahre' => [2026], 'jahr' => null,
    'status' => '', 'art' => null, 'q' => '', 'mitGeld' => true]);
$check('Liste: Titel maskiert, Zeitraum, Tage, Geld, Knopf zum Anlegen', str_contains($liste, 'Hochwasser &lt;Hunte&gt;') && !str_contains($liste, '<Hunte>')
    && str_contains($liste, '14.11.2026 – 16.11.2026') && str_contains($liste, '3 Tage') && str_contains($liste, 'Ausgaben 200,00') && str_contains($liste, '+ Einsatz'));
$ohneGeld = render_partial('einsaetze', ['laufend' => [$zeile], 'uebrige' => [], 'blatt' => seiten_blaettern(0, 1, 50), 'blattParams' => [], 'jahre' => [2026], 'jahr' => null,
    'status' => '', 'art' => null, 'q' => '', 'mitGeld' => false]);
$check('Liste ohne Budgetrecht: keine Beträge, laufende oben', !str_contains($ohneGeld, 'Ausgaben 200,00') && str_contains($ohneGeld, 'Laufende Einsätze'));

$tageDb = [
    ['datum' => '2026-11-14', 'von' => '08:00:00', 'bis' => '18:00:00', 'staerke_f' => 1, 'staerke_u' => 2, 'staerke_h' => 9, 'taetigkeit' => 'Verbau'],
    ['datum' => '2026-11-15', 'von' => '22:00:00', 'bis' => '06:00:00', 'staerke_f' => 0, 'staerke_u' => 1, 'staerke_h' => 5, 'taetigkeit' => 'Nachtwache'],
];
$seite = render_partial('einsatz', ['einsatz' => $zeile, 'tage' => $tageDb, 'summen' => einsatz_summen($tageDb),
    'buchungen' => [['id' => 9, 'art' => 'einnahme', 'status' => 'abgerechnet', 'datum' => '2026-12-01', 'bezeichnung' => 'Kostenerstattung', 'lieferant' => 'Landkreis',
        'kategorie_label' => 'Einsatzkostenerstattung', 'kategorie_color' => '#15803d', 'betrag_brutto' => '2500.00', 'abgerechnet_am' => '2026-12-01']],
    'geld' => einsatz_geld_aus([['art' => 'einnahme', 'status' => 'abgerechnet', 'betrag_brutto' => '2500.00']]), 'mitGeld' => true]);
$check('Seite: Tage mit Wochentag, Dauer, Stärke und Helferstunden', str_contains($seite, 'Sa 14.11.2026') && str_contains($seite, '08:00 – 18:00') && str_contains($seite, '1/2/9/12')
    && str_contains($seite, '120 h') && str_contains($seite, 'endet am Folgetag'));
$check('Seite: Summen über beide Tage', str_contains($seite, '2 Einsatztage') && str_contains($seite, '18 h') && str_contains($seite, '168 h'));
$check('Seite: Buchungen mit Stand, Knöpfe für Ausgabe und Einnahme mit Bezug', str_contains($seite, 'Kostenerstattung') && str_contains($seite, 'abgerechnet')
    && str_contains($seite, 'art=ausgabe&amp;einsatz_id=5') && str_contains($seite, 'art=einnahme&amp;einsatz_id=5') && str_contains($seite, 'noch unterwegs'));
$stumm = render_partial('einsatz', ['einsatz' => $zeile, 'tage' => $tageDb, 'summen' => einsatz_summen($tageDb), 'buchungen' => [], 'geld' => einsatz_geld_aus([]), 'mitGeld' => false]);
$check('Seite ohne Budgetrecht: kein Geld', !str_contains($stumm, 'id="geld"') && !str_contains($stumm, 'art=ausgabe') && !str_contains($stumm, 'art=einnahme'));
$fremdDb = [['datum' => '2026-11-14', 'von' => '08:00:00', 'bis' => '16:00:00', 'einheit' => 'FF <Musterstadt>', 'symbol_id' => 6, 'symbol_name' => 'Feuerwehr', 'staerke_f' => 1, 'staerke_u' => 3, 'staerke_h' => 12]];
$mitFremden = render_partial('einsatz', ['einsatz' => $zeile, 'tage' => $tageDb, 'fremde' => $fremdDb, 'summen' => einsatz_summen($tageDb), 'buchungen' => [], 'geld' => einsatz_geld_aus([]), 'mitGeld' => false]);
$check('Seite: fremde Kräfte mit Zeit, Dauer, Stärke und Zeichen – maskiert, und nur wenn es welche gibt', str_contains($mitFremden, 'id="fremde"') && str_contains($mitFremden, 'FF &lt;Musterstadt&gt;')
    && !str_contains($mitFremden, '<Musterstadt>') && str_contains($mitFremden, '1/3/12/16') && str_contains($mitFremden, '· Feuerwehr') && !str_contains($stumm, 'id="fremde"'));
$check('fremde Kräfte zählen nicht in die Helferstunden', str_contains($mitFremden, '168 h') && einsatz_summen($tageDb)['helferminuten'] === 168 * 60);

$tageMaske = $tageDb;
$tageMaske[0] += ['fachgruppe_id' => 21, 'vehicles' => [3], 'km' => [3 => 12.5]];
$maske = render_partial('einsatz_edit', ['einsatz' => $zeile, 'tage' => $tageMaske, 'errors' => [], 'buchungen' => 2,
    'fahrzeuge' => [['id' => 3, 'bezeichnung' => 'GKW 1', 'funkrufname' => 'Heros 21/52'], ['id' => 8, 'bezeichnung' => 'MTW', 'funkrufname' => '']]]);
$check('Maske: je Zeile ein Block plus ein leerer, Stärke als Text, jede mit eigener Kennung', substr_count($maske, 'name="tag_datum[]"') === 3 && str_contains($maske, 'value="2026-11-15"')
    && str_contains($maske, 'value="22:00"') && str_contains($maske, 'value="1/2/9"') && str_contains($maske, 'data-zeile-folgetag') && str_contains($maske, 'data-zeile-kopie')
    && str_contains($maske, 'name="tag_key[]" value="0"') && str_contains($maske, 'name="tag_key[]" value="2"') && substr_count($maske, 'name="tag_fachgruppe[]"') === 3);
$check('Maske: Fahrzeuge je Zeile mit Kilometern, Löschen nennt die Buchungen', str_contains($maske, 'name="tag_fz[0][]" value="3" checked') && str_contains($maske, 'name="tag_km[0][3]" value="12,5"')
    && str_contains($maske, 'name="tag_fz[1][]" value="8">') && str_contains($maske, 'name="tag_km[1][8]" value=""') && !str_contains($maske, 'fahrzeuge_feld')
    && str_contains($maske, 'name="tag_bh[0][3]" value=""') && substr_count($maske, 'placeholder="Bh"') === 6
    && str_contains($maske, 'Die 2 Buchungen bleiben'));
$check('Maske ohne fremde Kräfte: eine leere Zeile, ohne Zeichen-Auswahl solange die Bibliothek leer ist', substr_count($maske, 'name="fremd_einheit[]"') === 1
    && !str_contains($maske, 'fremd_symbol') && str_contains($maske, 'name="fremd_key[]" value="0" data-zeile-key'));
$maskeFremd = render_partial('einsatz_edit', ['einsatz' => $zeile, 'tage' => $tageMaske, 'errors' => [], 'buchungen' => 0, 'fahrzeuge' => [],
    'fremde' => $fremdDb, 'symbole' => [['id' => 6, 'name' => 'Feuerwehr', 'gruppe' => 'Einheiten'], ['id' => 9, 'name' => 'Rettungsdienst', 'gruppe' => 'Einheiten']]]);
$check('Maske: fremde Kräfte je Zeile mit Einheit, Zeit, Stärke und gewähltem Zeichen, dazu eine leere', substr_count($maskeFremd, 'name="fremd_einheit[]"') === 2
    && str_contains($maskeFremd, 'value="FF &lt;Musterstadt&gt;"') && str_contains($maskeFremd, 'name="fremd_von[]" value="08:00"') && str_contains($maskeFremd, 'name="fremd_staerke[]" value="1/3/12"')
    && substr_count($maskeFremd, 'name="fremd_symbol[]"') === 2 && substr_count($maskeFremd, 'value="6"') >= 2 && (bool)preg_match('/<option value="6"[^>]* selected>Feuerwehr/', $maskeFremd));
$check('Maske: die Liste der Zeichen steht einmal auf der Seite, nicht in jeder Zeile', substr_count($maskeFremd, '<template id="fremd-zeichen">') === 1
    && substr_count($maskeFremd, '>Rettungsdienst<') === 1 && substr_count($maskeFremd, 'data-optionen="fremd-zeichen"') === 2 && substr_count($maskeFremd, '<optgroup label="Einheiten">') === 1
    && str_contains((string)file_get_contents($app . '/public/assets/js/app.js'), "select[data-optionen]"));
$falsch = render_partial('einsatz_edit', ['einsatz' => ['id' => null, 'titel' => 'x', 'status' => 'laufend'], 'tage' => [['datum' => '2026-11-14', 'von' => '25:99', 'bis' => '', 'staerke_text' => 'viele', 'taetigkeit' => '']],
    'errors' => ['Zeile 1: …'], 'fahrzeuge' => [], 'buchungen' => 0]);
$check('Maske nach Fehler: die Eingaben stehen noch da', str_contains($falsch, 'value="25:99"') && str_contains($falsch, 'value="viele"') && str_contains($falsch, 'alert--error')
    && !str_contains($falsch, 'Einsatz löschen'));

$leer = ['id' => null, 'art' => 'einnahme', 'jahr' => 2026, 'datum' => '2026-12-01', 'bezeichnung' => '', 'einsatz_id' => 5, 'status' => 'geplant'];
$buchung = render_partial('expense_edit', ['art' => 'einnahme', 'expense' => $leer, 'errors' => [], 'budgets' => [], 'wishes' => [], 'events' => [], 'helfer' => [],
    'stichtag' => ['stichtag' => null, 'tage' => null, 'gesperrt' => false], 'einsaetze' => [$zeile], 'zurueckEinsatz' => true]);
$check('Buchungsformular: Auswahl „Gehört zu Einsatz", der Einsatz ist gewählt', str_contains($buchung, 'name="einsatz_id"') && str_contains($buchung, 'value="5" selected')
    && str_contains($buchung, '14.11.2026 · 2026-014 · Hochwasser &lt;Hunte&gt;'));
$check('Buchungsformular: merkt sich den Rückweg zum Einsatz', str_contains($buchung, 'name="zurueck" value="einsatz"') && str_contains($buchung, 'p=einsatz&amp;id=5#geld'));
$ohne = render_partial('expense_edit', ['art' => 'ausgabe', 'expense' => ['art' => 'ausgabe', 'einsatz_id' => null] + $leer, 'errors' => [], 'budgets' => [], 'wishes' => [], 'events' => [], 'helfer' => [],
    'stichtag' => ['stichtag' => null, 'tage' => null, 'gesperrt' => false]]);
$check('Buchungsformular ohne Einsätze: kein Feld, kein Rückweg', !str_contains($ohne, 'einsatz_id') && !str_contains($ohne, 'name="zurueck"'));

/* ================= Verdrahtung ================= */
$index = (string)file_get_contents($app . '/public/index.php');
$check('Routen', str_contains($index, "'einsaetze'          => 'einsaetze'") && str_contains($index, "'einsatz_edit'       => 'einsatz_edit'")
    && is_file($app . '/src/pages/einsaetze.php') && is_file($app . '/src/pages/einsatz.php') && is_file($app . '/src/pages/einsatz_edit.php'));
$check('Auswahlliste und Rechte bekannt', isset(LIST_KEYS['einsatz_art'])
    && substr_count((string)file_get_contents($app . '/src/lib/auth.php'), "_einsaetze'") === 3);
$check('Fahrzeuge im Formular und auf der Seite hängen nicht am Recht des Fahrzeugmoduls', !str_contains((string)file_get_contents($app . '/src/pages/einsatz_edit.php'), "can('view_vehicles')")
    && !str_contains((string)file_get_contents($app . '/src/pages/einsatz.php'), "can('view_vehicles')"));
$check('Bibliothek wird geladen', str_contains((string)file_get_contents($app . '/src/bootstrap.php'), "/lib/einsaetze.php'"));
$schema = (string)file_get_contents($app . '/sql/schema.sql');
$wanderung = (string)file_get_contents($app . '/src/cli/migrate.php');
foreach (['einsaetze', 'einsatz_tage', 'einsatz_vehicles', 'einsatz_fremde'] as $tabelle) {
    $check("Tabelle $tabelle in Schema und Wanderung", str_contains($schema, "CREATE TABLE IF NOT EXISTS $tabelle (") && str_contains($wanderung, "CREATE TABLE $tabelle ("));
}
$check('Wanderung 113 gemerkt, Karte bekommt die Vorauswahl', str_contains($wanderung, "\$merken('113_einsatz_fremde')")
    && str_contains((string)file_get_contents($app . '/src/pages/karte.php'), 'einsatz_fremde_vorauswahl(')
    && str_contains((string)file_get_contents($app . '/public/assets/js/karte.js'), 'K.fremde'));
$check('Buchungen: Spalte im Schema, Spalte und Fremdschlüssel in der Wanderung', (bool)preg_match('/CREATE TABLE IF NOT EXISTS expenses \(.*?einsatz_id\s+INT UNSIGNED\s+NULL.*?\) ENGINE/s', $schema)
    && str_contains($wanderung, 'ADD COLUMN einsatz_id INT UNSIGNED NULL AFTER event_id') && str_contains($wanderung, 'fk_exp_einsatz') && str_contains($wanderung, "\$merken('108_einsaetze')"));
$check('Wanderung räumt Dubletten auch in Einsätzen auf', in_array(['einsaetze', 'art_id', ''], array_map(static fn($r) => [trim($r[0]), trim($r[1]), $r[2]], (static function () use ($wanderung) {
    preg_match_all("/\['(\w+)',\s*'(\w+)',\s*'([^']*)'\],/", $wanderung, $m, PREG_SET_ORDER);
    return array_map(static fn($x) => [$x[1], $x[2], $x[3]], $m);
})()), true));
$seed = (string)file_get_contents($app . '/sql/seed.sql');
$check('Grunddaten: Einsatzarten mit einer Vorgabe, vier Einstellungen', substr_count($seed, "('einsatz_art',") === 6 && (bool)preg_match("/\('einsatz_art','Einsatz',\s+'einsatz',\s+'#b91c1c',10,1\)/", $seed)
    && substr_count($seed, "'Einsätze',") === 4 + 1);
$check('Skript für die Zeilen ist da', str_contains((string)file_get_contents($app . '/public/assets/js/app.js'), '[data-zeilen]'));

/* ================= Ergebnis und Kuchen ================= */
$buch = [
    ['art' => 'ausgabe', 'status' => 'bezahlt', 'betrag_brutto' => '300.00', 'kategorie_label' => 'Verpflegung', 'kategorie_color' => '#D97706'],
    ['art' => 'ausgabe', 'status' => 'bezahlt', 'betrag_brutto' => '100.00', 'kategorie_label' => 'Kraftstoff', 'kategorie_color' => 'rot'],
    ['art' => 'ausgabe', 'status' => 'geplant', 'betrag_brutto' => '50.00', 'kategorie_label' => 'Verpflegung', 'kategorie_color' => '#d97706'],
    ['art' => 'ausgabe', 'status' => 'offen', 'betrag_brutto' => '20.00', 'kategorie_label' => '', 'kategorie_color' => ''],
    ['art' => 'einnahme', 'status' => 'bezahlt', 'betrag_brutto' => '1000.00', 'kategorie_label' => 'Einsatzkostenerstattung', 'kategorie_color' => '#15803d'],
    ['art' => 'einnahme', 'status' => 'erwartet', 'betrag_brutto' => '500.00', 'kategorie_label' => 'Einsatzkostenerstattung', 'kategorie_color' => '#15803d'],
];
$g = einsatz_geld_aus($buch);
$erg = einsatz_ergebnis($g);
$check('Ergebnis: Gewinn mit Vorzeichen, erwartet getrennt', $erg['wort'] === 'Gewinn' && $erg['klasse'] === 'ok' && $erg['text'] === '+580,00 €'
    && str_contains($erg['erwartet'], '+1.030,00 €') && str_contains($erg['erwartet'], 'Gewinn'));
$erg2 = einsatz_ergebnis(['saldo' => -120.5, 'saldo_erwartet' => -120.5]);
$check('Ergebnis: Verlust rot, ohne Erwartungszeile wenn gleich', $erg2['wort'] === 'Verlust' && $erg2['klasse'] === 'bad' && $erg2['text'] === '−120,50 €' && $erg2['erwartet'] === '');
$erg3 = einsatz_ergebnis(['saldo' => 0.0]);
$check('Ergebnis: ausgeglichen ohne Farbe', $erg3['wort'] === 'ausgeglichen' && $erg3['klasse'] === '' && $erg3['text'] === '0,00 €');
$check('Ergebnis aus der Liste: nur der Saldo reicht', einsatz_ergebnis(['saldo' => 12.0])['text'] === '+12,00 €');

$verh = einsatz_geld_verhaeltnis($g);
$check('Verhältnis: vier Stücke mit Ist und Ausstehendem', array_column($verh, 'label') === ['Ausgaben', 'geplante Ausgaben', 'Einnahmen', 'Einnahmen unterwegs']
    && array_column($verh, 'wert') === [420.0, 50.0, 1000.0, 500.0]);
$check('Verhältnis: Nullen fallen weg', array_column(einsatz_geld_verhaeltnis(['ausgaben' => 10, 'einnahmen' => 0]), 'label') === ['Ausgaben']);
$kat = einsatz_geld_je_kategorie($buch, 'ausgabe');
$check('Ausgaben je Kategorie: nur Ist, größte zuerst, Farbe der Kategorie oder aus der Reihe, ohne Kategorie benannt',
    array_column($kat, 'label') === ['Verpflegung', 'Kraftstoff', 'ohne Kategorie'] && array_column($kat, 'wert') === [300.0, 100.0, 20.0]
    && $kat[0]['farbe'] === '#d97706' && $kat[1]['farbe'] === EINSATZ_KUCHEN_FARBEN[0] && $kat[2]['farbe'] === EINSATZ_KUCHEN_FARBEN[2]
    && !in_array('#d97706', [$kat[1]['farbe'], $kat[2]['farbe']], true));
$check('Einnahmen je Kategorie: eine Kategorie, nur die eingegangene', einsatz_geld_je_kategorie($buch, 'einnahme') === [['label' => 'Einsatzkostenerstattung', 'wert' => 1000.0, 'farbe' => '#15803d']]);

$svg = kuchen_svg($verh);
$check('Kuchen: ein Pfad je Stück, Anteile im Titel, Farben drin', substr_count($svg, '<path ') === 4 && str_contains($svg, 'Einnahmen: 50,8 %') && str_contains($svg, 'fill="#b91c1c"')
    && str_starts_with($svg, '<svg ') && str_ends_with($svg, '</svg>'));
$check('Kuchen: Stück über die Hälfte nimmt den großen Bogen', (bool)preg_match('/A50 50 0 1 1 [^"]*" fill="#15803d"/', $svg) && (bool)preg_match('/A50 50 0 0 1 [^"]*" fill="#b91c1c"/', $svg));
$check('Kuchen: ein Stück wird ein voller Kreis, nichts bleibt leer', str_contains(kuchen_svg([['label' => 'x', 'wert' => 5, 'farbe' => '#000']]), '<circle r="50"') && kuchen_svg([]) === '' && kuchen_svg([['label' => 'x', 'wert' => 0, 'farbe' => '#000']]) === '');
$check('Kuchen maskiert Namen', str_contains(kuchen_svg([['label' => '<b>', 'wert' => 1, 'farbe' => '#000'], ['label' => 'y', 'wert' => 1, 'farbe' => '#111']]), '&lt;b&gt;'));
$leg = kuchen_legende($kat);
$check('Legende: Punkt, Name, Betrag und Anteil', substr_count($leg, '<li>') === 3 && str_contains($leg, 'Verpflegung') && str_contains($leg, '300,00 € · 71,4 %') && str_contains($leg, 'background:#d97706'));

$mitGeld = render_partial('einsatz', ['einsatz' => $zeile, 'tage' => $tageDb, 'summen' => einsatz_summen($tageDb),
    'buchungen' => array_map(static fn($b, $i) => $b + ['id' => $i + 1, 'datum' => '2026-11-20', 'bezeichnung' => 'B' . $i, 'lieferant' => ''], $buch, array_keys($buch)),
    'geld' => $g, 'mitGeld' => true]);
$check('Seite: Ergebnis-Kachel mit Gewinn und Erwartung, Summenzeile nennt das Ergebnis', str_contains($mitGeld, 'id="ergebnis"') && str_contains($mitGeld, 'stat--ok')
    && str_contains($mitGeld, '+580,00 €') && str_contains($mitGeld, 'wenn alles eingeht') && str_contains($mitGeld, '– Gewinn</th>'));
$check('Seite: drei Kuchen – Verhältnis, Ausgaben nach Kategorie; Einnahmen mit einer Kategorie ohne Kuchen', substr_count($mitGeld, 'class="kuchen"') === 2
    && str_contains($mitGeld, 'Ausgaben und Einnahmen</h3>') && str_contains($mitGeld, 'Ausgaben nach Kategorie') && !str_contains($mitGeld, 'Einnahmen nach Kategorie')
    && substr_count($mitGeld, '<svg class="kuchen__bild"') === 2);
$check('Seite ohne Buchungen: Ergebnis 0, kein Kuchen', str_contains($stumm, 'kuchen') === false && str_contains(render_partial('einsatz', ['einsatz' => $zeile, 'tage' => $tageDb, 'summen' => einsatz_summen($tageDb), 'buchungen' => [], 'geld' => einsatz_geld_aus([]), 'mitGeld' => true]), 'noch nichts gebucht'));
$liste = render_partial('einsaetze', ['laufend' => [], 'uebrige' => [array_merge($zeile, ['ausgaben' => '420.00', 'einnahmen' => '1000.00', 'tage' => 2, 'staerke_max' => 12, 'fahrzeuge_namen' => ''])], 'blatt' => seiten_blaettern(0, 1, 50), 'blattParams' => [],
    'jahre' => [2026], 'jahr' => null, 'status' => '', 'art' => null, 'q' => '', 'mitGeld' => true]);
$check('Liste: Ergebnis je Einsatz als Abzeichen', str_contains($liste, 'badge--ok') && str_contains($liste, 'Gewinn +580,00 €'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
