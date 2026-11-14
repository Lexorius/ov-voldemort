<?php
declare(strict_types=1);
/*
 * Einsätze, zweiter Teil: Fachgruppen, Fahrzeuge und Kilometer je Zeile, Dokumente am
 * Einsatz (Einsatzauftrag als PDF, Lagekarte), das Protokoll der Karten mit Einsatzzeiten
 * und die Seite „Bibliotheken und Lizenzen".
 */
session_start();
date_default_timezone_set('Europe/Berlin');
define('OVB_TEST_UPLOADS', true);

$tmp = sys_get_temp_dir() . '/ovb-einsatz-test-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OVB_UPLOAD_DIR=' . $tmp);
$_ENV['OVB_UPLOAD_DIR'] = $tmp;

$GLOBALS['settings'] = ['waehrung' => 'EUR', 'upload_max_mb' => '10', 'einsatz_modul_name' => 'Einsätze'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['rechte'] = [];

function db_all(string $sql, array $p = []): array {
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
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 100 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
final class StubStmtD { public function execute(array $p): bool { return true; } }
final class StubPdoD { public function prepare(string $s): StubStmtD { return new StubStmtD(); } }
function db(): StubPdoD { return new StubPdoD(); }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? true; }
function current_user(): ?array { return ['id' => 7, 'role' => 'leitung', 'display_name' => 'Tina Tester']; }
function geo_heim(): ?array { return null; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'vehicle_files', 'standorte', 'expenses', 'karten', 'karten_protokoll', 'einsaetze', 'lizenzen'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$nur = static fn(string $tabelle): array => array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === $tabelle));
$lies = static fn(string $pfad): string => (string)file_get_contents($app . '/' . $pfad);

/* ================= Kilometer ================= */
$check('Kilometer lesen: Komma, Punkt, Tausender, Einheit', einsatz_km('12,5') === 12.5 && einsatz_km('12.5') === 12.5 && einsatz_km('1.234,5') === 1234.5
    && einsatz_km(' 40 km') === 40.0 && einsatz_km('12,34') === 12.3);
$check('Kilometer: leer, null und Unsinn sind keine', einsatz_km('') === null && einsatz_km('0') === null && einsatz_km('-3') === null && einsatz_km('viel') === null && einsatz_km(null) === null);
$check('Kilometer als Text', einsatz_km_text(12.5) === '12,5 km' && einsatz_km_text(340.0) === '340 km' && einsatz_km_text(1234.5) === '1.234,5 km');

/* ================= Zeilen: mehrere Fachgruppen und Fahrzeuge am selben Tag ================= */
[$tage, $fehler] = einsatz_tage_aus_post([
    'tag_key'        => ['a', 'b', 'c'],
    'tag_datum'      => ['2026-11-14', '2026-11-14', ''],
    'tag_von'        => ['08:00', '10:00', ''],
    'tag_bis'        => ['18:00', '16:00', ''],
    'tag_fachgruppe' => ['21', '22', ''],
    'tag_staerke'    => ['1/2/9', '0/1/5', ''],
    'tag_taetigkeit' => ['Verbau', 'Pumpen', ''],
    'tag_fz'         => ['a' => ['3', '4'], 'b' => ['5']],
    'tag_km'         => ['a' => ['3' => '42', '4' => ''], 'b' => ['5' => '7,5', '9' => '3'], 'c' => ['3' => '']],
    'tag_bh'         => ['a' => ['4' => '3,5 h', '3' => ''], 'b' => ['11' => '6'], 'c' => ['4' => '']],
]);
$check('zwei Fachgruppen am selben Tag, die leere Zeile fällt weg', count($tage) === 2 && $fehler === [] && $tage[0]['fachgruppe_id'] === 21 && $tage[1]['fachgruppe_id'] === 22
    && $tage[0]['von'] === '08:00' && $tage[1]['von'] === '10:00');
$check('Fahrzeuge und Kilometer je Zeile; Kilometer wählen das Fahrzeug mit', $tage[0]['vehicles'] === [3, 4] && $tage[0]['km'] === [3 => 42.0]
    && $tage[1]['vehicles'] === [5, 9, 11] && $tage[1]['km'] === [5 => 7.5, 9 => 3.0]);
$check('Betriebsstunden je Fahrzeug, mit oder ohne Kilometer; auch sie wählen das Fahrzeug', $tage[0]['bh'] === [4 => 3.5] && $tage[1]['bh'] === [11 => 6.0]
    && einsatz_km('3,5 Bh') === 3.5 && einsatz_km('2 Std.') === 2.0 && einsatz_bh_text(3.5) === '3,5 Bh' && einsatz_bh_text(12.0) === '12 Bh');
$check('was an einem Fahrzeug steht', einsatz_fahrzeug_leistung(['km' => 42.0, 'bh' => 3.5]) === '42 km · 3,5 Bh' && einsatz_fahrzeug_leistung(['km' => null, 'bh' => 6.0]) === '6 Bh'
    && einsatz_fahrzeug_leistung(['km' => 7.5]) === '7,5 km' && einsatz_fahrzeug_leistung([]) === '');
[$nurFz] = einsatz_tage_aus_post(['tag_key' => ['x'], 'tag_datum' => ['2026-11-14'], 'tag_fz' => ['x' => ['3']]]);
$check('eine Zeile nur mit Datum und Fahrzeug zählt', count($nurFz) === 1 && $nurFz[0]['vehicles'] === [3] && $nurFz[0]['fachgruppe_id'] === null);

$zeilen = [
    ['datum' => '2026-11-14', 'von' => '08:00', 'bis' => '18:00', 'fachgruppe_id' => 21, 'fachgruppe_label' => 'B1', 'staerke_f' => 1, 'staerke_u' => 2, 'staerke_h' => 9,
     'fahrzeuge' => [['id' => 3, 'bezeichnung' => 'GKW 1', 'km' => 42.0, 'bh' => 2.5], ['id' => 4, 'bezeichnung' => 'MTW', 'km' => null, 'bh' => 4.0]]],
    ['datum' => '2026-11-14', 'von' => '10:00', 'bis' => '16:00', 'fachgruppe_id' => 22, 'fachgruppe_label' => 'FGr N', 'staerke_f' => 0, 'staerke_u' => 1, 'staerke_h' => 5,
     'fahrzeuge' => [['id' => 5, 'bezeichnung' => 'MLW', 'km' => 7.5]]],
    ['datum' => '2026-11-15', 'von' => '08:00', 'bis' => '12:00', 'fachgruppe_id' => 21, 'fachgruppe_label' => 'B1', 'staerke_f' => 1, 'staerke_u' => 1, 'staerke_h' => 6,
     'fahrzeuge' => [['id' => 3, 'bezeichnung' => 'GKW 1', 'km' => 18.5]]],
    ['datum' => '2026-11-15', 'von' => null, 'bis' => null, 'fachgruppe_id' => null, 'staerke_f' => 0, 'staerke_u' => 0, 'staerke_h' => 2, 'fahrzeuge' => []],
];
$fg = einsatz_summen_je_fachgruppe($zeilen);
$check('je Fachgruppe: Tage, Stunden, Helferstunden – größte zuerst', array_keys($fg) === [21, 22, 0] && $fg[21]['tage'] === 2 && $fg[21]['minuten'] === 840
    && $fg[21]['helferminuten'] === 600 * 12 + 240 * 8 && $fg[22]['minuten'] === 360 && $fg[22]['helferminuten'] === 2160 && $fg[0]['label'] === 'ohne Fachgruppe' && $fg[0]['minuten'] === 0);
$fz = einsatz_summen_je_fahrzeug($zeilen);
$check('je Fahrzeug: Tage, Einsatzzeit, Kilometer – längste zuerst', array_keys($fz) === [3, 4, 5] && $fz[3]['tage'] === 2 && $fz[3]['minuten'] === 840 && $fz[3]['km'] === 60.5
    && $fz[4]['km'] === 0.0 && $fz[4]['minuten'] === 600 && $fz[5]['km'] === 7.5 && $fz[5]['tage'] === 1);
$check('je Fahrzeug: Betriebsstunden, auch ohne Kilometer', $fz[3]['bh'] === 2.5 && $fz[4]['bh'] === 4.0 && $fz[5]['bh'] === 0.0);
$s = einsatz_summen($zeilen);
$check('zwei Einsatztage in vier Zeilen', $s['tage'] === 2 && $s['zeilen'] === 4 && $s['minuten'] === 600 + 360 + 240 && $s['ohne_dauer'] === 1);

$GLOBALS['rows'] = [
    'FROM einsatz_tage t LEFT JOIN list_items fg' => [['id' => 31, 'einsatz_id' => 5, 'datum' => '2026-11-14', 'von' => '08:00:00', 'bis' => '18:00:00', 'fachgruppe_id' => 21,
        'fachgruppe_label' => 'B1', 'fachgruppe_color' => '#1d4ed8', 'staerke_f' => 1, 'staerke_u' => 2, 'staerke_h' => 9, 'taetigkeit' => 'Verbau'],
        ['id' => 32, 'einsatz_id' => 5, 'datum' => '2026-11-14', 'von' => '10:00:00', 'bis' => '16:00:00', 'fachgruppe_id' => null, 'staerke_f' => 0, 'staerke_u' => 0, 'staerke_h' => 0, 'taetigkeit' => '']],
    'FROM einsatz_tag_vehicles tv' => [['tag_id' => 31, 'km' => '42.0', 'betriebsstunden' => null, 'id' => 3, 'bezeichnung' => 'GKW 1', 'funkrufname' => 'Heros 22/51'],
        ['tag_id' => 31, 'km' => null, 'betriebsstunden' => '3.5', 'id' => 4, 'bezeichnung' => 'MTW', 'funkrufname' => '']],
];
$gelesen = einsatz_tage(5);
$check('Zeilen lesen: Fahrzeuge hängen an ihrer Zeile, Kilometer nur wo eingetragen', $gelesen[0]['vehicles'] === [3, 4] && $gelesen[0]['km'] === [3 => 42.0]
    && $gelesen[0]['fahrzeuge'][1]['km'] === null && $gelesen[1]['fahrzeuge'] === [] && $gelesen[1]['vehicles'] === []
    && $gelesen[0]['bh'] === [4 => 3.5] && $gelesen[0]['fahrzeuge'][0]['bh'] === null);
$GLOBALS['rows'] = [];

$seite = render_partial('einsatz', ['einsatz' => ['id' => 5, 'nummer' => '2026-014', 'titel' => 'Hochwasser', 'beginn' => '2026-11-14 08:00:00', 'ende' => '2026-11-15 12:00:00', 'ort' => '',
        'anforderer' => '', 'status' => 'laufend', 'jahr' => 2026, 'created_at' => '2026-11-14 07:00:00'],
    'tage' => $zeilen, 'summen' => $s, 'jeFachgruppe' => $fg, 'jeFahrzeug' => $fz, 'buchungen' => [], 'geld' => einsatz_geld_aus([]), 'mitGeld' => false,
    'karten' => [['id' => 9, 'name' => 'Deich Süd', 'marker' => 4, 'protokoll' => 12]],
    'dateien' => [['id' => 70, 'einsatz_id' => 5, 'herkunft' => 'auftrag', 'titel' => 'Einsatzauftrag Nr. 1 an B1', 'orig_name' => 'Einsatzauftrag-1.pdf', 'thumb_name' => null,
        'size_bytes' => 6000, 'created_at' => '2026-11-14 09:00:00', 'hochgeladen_von' => 'Tina Tester', 'karte_name' => null]]]);
$check('Seite: Fachgruppen am selben Tag untereinander, Fahrzeuge mit Kilometern', str_contains($seite, 'Sa 14.11.2026') && substr_count($seite, 'title="Sa 14.11.2026"') === 1
    && str_contains($seite, 'FGr N') && str_contains($seite, 'GKW 1 <span class="muted">· 42 km · 2,5 Bh</span>') && str_contains($seite, 'MTW <span class="muted">· 4 Bh</span>')
    && str_contains($seite, '68 km · 6,5 Bh'));
$check('Seite: Übersicht je Fachgruppe und je Fahrzeug', str_contains($seite, 'id="fachgruppen"') && str_contains($seite, 'id="fahrzeuge"') && str_contains($seite, '60,5 km')
    && str_contains($seite, '>Betriebsstunden</th>') && str_contains($seite, '6,5 Bh'));
$check('Seite: Dokumente mit Herkunft, Vorlage und Hochladen; Karten mit Protokoll', str_contains($seite, 'Einsatzauftrag Nr. 1 an B1') && str_contains($seite, 'p=einsatz_datei&amp;id=70')
    && str_contains($seite, 'vorlage=70') && str_contains($seite, 'name="dateien[]"') && str_contains($seite, 'p=karte_protokoll&amp;id=9') && str_contains($seite, '12 Einträge'));

/* ================= Einsatzauftrag: Felder, Nummer, Titel, Vorgabe ================= */
$roh = ['absender' => 'OV Musterstadt', 'empfaenger' => " B-Gruppe\n1. TZ ", 'laufendeNummer' => '3', 'auftrag' => "Zeile 1\r\nZeile 2", 'unterpunkte' => ['1a' => 'Pegel steigt', '9z' => 'gibt es nicht', '3b' => '  '],
    'fuehrung' => ['FüSt Nord', 'Kanal 304'], 'uebung' => 1, 'boese' => '<script>', 'einsatz' => str_repeat('x', 500), 'ortDatum' => ['kein', 'Text']];
$d = einsatz_auftrag_daten(json_encode($roh));
$check('Felder: nur bekannte, als Text, begrenzt', !isset($d['boese']) && $d['absender'] === 'OV Musterstadt' && mb_strlen($d['einsatz']) === 200 && !isset($d['ortDatum'])
    && $d['auftrag'] === "Zeile 1\nZeile 2" && $d['uebung'] === true);
$check('Unterpunkte: nur die des Vordrucks, leere fallen weg', $d['unterpunkte'] === ['1a' => 'Pegel steigt']);
$check('Abschnitt 5 als Liste wird Text mit Leerzeilen', $d['fuehrung'] === "FüSt Nord\n\nKanal 304");
$check('Unsinn ergibt nichts', einsatz_auftrag_daten('kein json') === [] && einsatz_auftrag_daten(null) === [] && einsatz_auftrag_daten('[]') === []);
$check('13 Unterpunkte wie im Vordruck', array_keys(EINSATZ_AUFTRAG_UNTERPUNKTE) === ['1a', '1b', '1c', '3a', '3b', '3c', '4a', '4b', '4c', '4d', '4e', '4f', '4g']);
$check('Titel des Dokuments', einsatz_auftrag_titel($d) === 'Einsatzauftrag Nr. 3 an B-Gruppe 1. TZ (Übung)' && einsatz_auftrag_titel([]) === 'Einsatzauftrag');
$vorhanden = [['herkunft' => 'auftrag', 'daten' => '{"laufendeNummer":"3"}'], ['herkunft' => 'auftrag', 'daten' => ['laufendeNummer' => ' 12 ']],
    ['herkunft' => 'auftrag', 'daten' => '{"laufendeNummer":"A-99"}'], ['herkunft' => 'upload', 'daten' => '{"laufendeNummer":"50"}']];
$check('nächste laufende Nummer', einsatz_auftrag_naechste_nr($vorhanden) === '13' && einsatz_auftrag_naechste_nr([]) === '1');
$einsatz = ['id' => 5, 'nummer' => '2026-014', 'titel' => 'Hochwasser Hunte', 'ort' => 'Deich Süd', 'beschreibung' => 'Pegel 5,80 m'];
$v = einsatz_auftrag_vorgabe($einsatz, $vorhanden, current_user(), 'THW OV Musterstadt', null, strtotime('2026-11-14 10:00:00'));
$check('Vorgabe: Absender, Einsatz, Nummer, Ort und Datum, Unterschrift, Lage', $v['absender'] === 'THW OV Musterstadt' && $v['einsatz'] === '2026-014 · Hochwasser Hunte'
    && $v['laufendeNummer'] === '13' && $v['ortDatum'] === 'Deich Süd, 14.11.2026' && $v['unterschrift'] === 'Tina Tester' && $v['unterpunkte']['1a'] === 'Pegel 5,80 m');
$v2 = einsatz_auftrag_vorgabe($einsatz, $vorhanden, current_user(), 'OV', ['empfaenger' => 'alt', 'laufendeNummer' => '3', 'auftrag' => 'Deich sichern', 'abgesandtUm' => '09:00',
    'unterpunkte' => ['3b' => 'Verbau']], strtotime('2026-11-15 10:00:00'));
$check('Vorlage: Texte bleiben, Nummer, Empfänger und Absendevermerk beginnen neu', $v2['auftrag'] === 'Deich sichern' && $v2['unterpunkte'] === ['3b' => 'Verbau']
    && $v2['laufendeNummer'] === '13' && !isset($v2['empfaenger']) && !isset($v2['abgesandtUm']) && $v2['ortDatum'] === 'Deich Süd, 15.11.2026');

/* ================= PDF auf dem Server ablegen ================= */
$hochladen = static function (string $name, string $inhalt): void {
    $datei = tempnam(sys_get_temp_dir(), 'ovb');
    file_put_contents($datei, $inhalt);
    $_FILES['pdf'] = ['name' => [$name], 'type' => ['application/pdf'], 'tmp_name' => [$datei], 'error' => [UPLOAD_ERR_OK], 'size' => [strlen($inhalt)]];
};
$pdf = "%PDF-1.3\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< >>\n%%EOF\n";
$hochladen('Einsatzauftrag-3.pdf', $pdf);
[$anzahl, $fehler, $ids] = einsatz_dateien_ablegen(5, 'pdf', 'Einsatzauftrag Nr. 3 an B1', current_user(), 'auftrag', $d);
$zeile = $nur('einsatz_files')[0][1] ?? [];
$check('Einsatzauftrag als PDF abgelegt' . ($fehler ? ' – ' . implode(' ', $fehler) : ''), $anzahl === 1 && $fehler === [] && count($ids) === 1 && ($zeile['herkunft'] ?? '') === 'auftrag'
    && $zeile['einsatz_id'] === 5 && $zeile['mime'] === 'application/pdf' && $zeile['titel'] === 'Einsatzauftrag Nr. 3 an B1' && $zeile['uploaded_by'] === 7);
$check('die Datei liegt im Ordner der Einsätze, die Felder daneben als JSON', is_file($tmp . '/einsaetze/' . ($zeile['stored_name'] ?? 'x'))
    && str_ends_with((string)$zeile['stored_name'], '.pdf') && json_decode((string)$zeile['daten'], true)['laufendeNummer'] === '3');
$check('Pfad zur Datei, nie aus dem Ordner hinaus', einsatz_datei_pfad(['stored_name' => $zeile['stored_name'], 'thumb_name' => null]) !== null
    && einsatz_datei_pfad(['stored_name' => '../../' . $zeile['stored_name'], 'thumb_name' => null]) === einsatz_datei_pfad(['stored_name' => $zeile['stored_name'], 'thumb_name' => null])
    && einsatz_datei_pfad(['stored_name' => 'gibtsnicht.pdf', 'thumb_name' => null]) === null);

$GLOBALS['inserts'] = [];
$hochladen('Einsatzauftrag-4.pdf', '<html><script>alert(1)</script></html>');
[$anzahl2, $fehler2] = einsatz_dateien_ablegen(5, 'pdf', 'x', current_user(), 'auftrag', []);
$check('was kein PDF ist, wird abgewiesen und bleibt nicht liegen', $anzahl2 === 0 && count($fehler2) === 1 && $nur('einsatz_files') === []
    && count(glob($tmp . '/einsaetze/*') ?: []) === 1);
$hochladen('Lage.pdf', $pdf);
[$anzahl3, , $ids3] = einsatz_dateien_ablegen(5, 'pdf', 'Lagekarte Deich Süd', current_user(), 'karte', null, 9);
$check('Lagekarte: Herkunft und Karte festgehalten', $anzahl3 === 1 && $nur('einsatz_files')[0][1]['herkunft'] === 'karte' && $nur('einsatz_files')[0][1]['karte_id'] === 9
    && $nur('einsatz_files')[0][1]['daten'] === null);

$liegt = $nur('einsatz_files')[0][1];
einsatz_datei_delete(['id' => 71, 'einsatz_id' => 5, 'titel' => 'Lagekarte', 'orig_name' => 'Lage.pdf', 'stored_name' => $liegt['stored_name'], 'thumb_name' => null]);
$check('Entfernen nimmt Datei und Zeile', !is_file($tmp . '/einsaetze/' . $liegt['stored_name']) && str_contains((string)end($GLOBALS['execs'])[0], 'DELETE FROM einsatz_files'));

/* ================= Kartenprotokoll ================= */
$check('Entfernung in Metern, null ohne Stelle', karten_entfernung_m(53.0, 8.0, 53.0, 8.0) === 0 && abs(karten_entfernung_m(53.0, 8.0, 53.001, 8.0) - 111) <= 1
    && karten_entfernung_m(null, 8.0, 53.0, 8.0) === null && karten_entfernung_m('', '', 53.0, 8.0) === null);
$check('Strecke und Dauer als Text', karten_strecke_text(240) === '240 m' && karten_strecke_text(3400) === '3,4 km' && karten_strecke_text(null) === ''
    && karten_dauer_text(45) === '45 min' && karten_dauer_text(205) === '3 h 25 min' && karten_dauer_text(120) === '2 h' && karten_dauer_text(1440 * 2 + 250) === '2 Tage 4 h');

$GLOBALS['inserts'] = [];
$karte = ['id' => 9, 'name' => 'Deich Süd'];
$symbol = ['id' => 2, 'name' => 'Bergungsgruppe'];
$m = karten_marker_neu($karte, $symbol, '53.079300', '8.801700', current_user(), ['beschriftung' => 'B1', 'staerke' => '1/2/9']);
$p = $nur('karten_protokoll')[0][1] ?? [];
$check('Einheit gesetzt: steht im Protokoll mit Name, Stelle, Stärke und Person', ($p['aktion'] ?? '') === 'gesetzt' && $p['art'] === 'einheit' && $p['karte_id'] === 9
    && $p['marker_id'] === $m['id'] && $p['name'] === 'B1' && $p['lat'] === 53.0793 && $p['staerke'] === '1/2/9/12' && $p['user_id'] === 7 && $p['meter'] === null);
$GLOBALS['inserts'] = [];
karten_marker_aendern($m, ['groesse' => 60, 'beschriftung' => 'B1 neu']);
$check('Größe und Beschriftung ändern schreibt nichts ins Protokoll', $nur('karten_protokoll') === []);
karten_marker_aendern($m, ['lat' => '53.079300', 'lng' => '8.801700']);
$check('dieselbe Stelle ist keine Bewegung', $nur('karten_protokoll') === []);
karten_marker_aendern($m, ['lat' => '53.080300', 'lng' => '8.801700']);
$p = $nur('karten_protokoll')[0][1] ?? [];
$check('Einheit versetzt: von, nach und Strecke', ($p['aktion'] ?? '') === 'versetzt' && $p['von_lat'] === 53.0793 && $p['lat'] === 53.0803 && abs($p['meter'] - 111) <= 1 && $p['name'] === 'B1');
$GLOBALS['inserts'] = [];
karten_marker_delete($m);
$p = $nur('karten_protokoll')[0][1] ?? [];
$check('Einheit gelöscht: herausgenommen, die letzte Stelle bleibt als „von"', ($p['aktion'] ?? '') === 'entfernt' && $p['lat'] === null && $p['von_lat'] === 53.0793);
$GLOBALS['inserts'] = [];
karten_protokoll_fahrzeug(9, ['id' => 3, 'bezeichnung' => 'GKW 1', 'funkrufname' => 'Heros 22/51'], 'versetzt', 53.09, 8.81, ['lat' => 53.08, 'lng' => 8.81]);
$p = $nur('karten_protokoll')[0][1] ?? [];
$check('Fahrzeug gezogen: Fahrzeug, Name mit Funkrufname, Strecke', ($p['art'] ?? '') === 'fahrzeug' && $p['vehicle_id'] === 3 && $p['name'] === 'GKW 1 (Heros 22/51)' && abs($p['meter'] - 1112) <= 2);
$GLOBALS['inserts'] = [];
karten_protokoll_schreiben(0, 'einheit', 'gesetzt', []);
karten_protokoll_schreiben(9, 'unsinn', 'gesetzt', []);
karten_protokoll_schreiben(9, 'einheit', 'gesprengt', []);
$check('ohne Karte oder mit unbekannter Art oder Aktion wird nichts geschrieben', $nur('karten_protokoll') === []);

$protokoll = [
    ['art' => 'einheit', 'marker_id' => 1, 'vehicle_id' => null, 'name' => 'B1', 'aktion' => 'gesetzt', 'meter' => null, 'abschnitt' => 'Nord', 'staerke' => '1/2/9/12', 'created_at' => '2026-11-14 08:00:00'],
    ['art' => 'fahrzeug', 'marker_id' => null, 'vehicle_id' => 3, 'name' => 'GKW 1', 'aktion' => 'gesetzt', 'meter' => null, 'abschnitt' => '', 'staerke' => '', 'created_at' => '2026-11-14 08:30:00'],
    ['art' => 'einheit', 'marker_id' => 1, 'vehicle_id' => null, 'name' => 'B1', 'aktion' => 'versetzt', 'meter' => 240, 'abschnitt' => 'Süd', 'staerke' => '1/2/9/12', 'created_at' => '2026-11-14 09:00:00'],
    ['art' => 'fahrzeug', 'marker_id' => null, 'vehicle_id' => 3, 'name' => 'GKW 1', 'aktion' => 'versetzt', 'meter' => 1500, 'abschnitt' => '', 'staerke' => '', 'created_at' => '2026-11-14 09:30:00'],
    ['art' => 'fahrzeug', 'marker_id' => null, 'vehicle_id' => 3, 'name' => 'GKW 1', 'aktion' => 'entfernt', 'meter' => null, 'abschnitt' => '', 'staerke' => '', 'created_at' => '2026-11-14 11:30:00'],
    ['art' => 'fahrzeug', 'marker_id' => null, 'vehicle_id' => 3, 'name' => 'GKW 1 neu', 'aktion' => 'gesetzt', 'meter' => null, 'abschnitt' => '', 'staerke' => '', 'created_at' => '2026-11-14 13:00:00'],
    ['art' => 'fahrzeug', 'marker_id' => null, 'vehicle_id' => 3, 'name' => 'GKW 1 neu', 'aktion' => 'entfernt', 'meter' => null, 'abschnitt' => '', 'staerke' => '', 'created_at' => '2026-11-14 14:00:00'],
    ['art' => 'einheit', 'marker_id' => 2, 'vehicle_id' => null, 'name' => 'FGr N', 'aktion' => 'versetzt', 'meter' => 50, 'abschnitt' => '', 'staerke' => '', 'created_at' => '2026-11-14 12:00:00'],
];
$zeiten = karten_einsatzzeiten($protokoll, strtotime('2026-11-14 15:00:00'));
$nach = array_column($zeiten, null, 'schluessel');
$check('Einheit im Einsatz: läuft seit dem Setzen bis jetzt', $nach['einheit:1']['aktiv'] === true && $nach['einheit:1']['minuten'] === 420 && $nach['einheit:1']['bis'] === null
    && $nach['einheit:1']['bewegungen'] === 1 && $nach['einheit:1']['meter'] === 240 && $nach['einheit:1']['abschnitt'] === 'Süd');
$check('Fahrzeug herausgenommen und wieder gesetzt: die Zeiten sammeln sich', $nach['fahrzeug:3']['aktiv'] === false && $nach['fahrzeug:3']['minuten'] === 180 + 60
    && $nach['fahrzeug:3']['bis'] === '2026-11-14 14:00:00' && $nach['fahrzeug:3']['seit'] === '2026-11-14 08:30:00' && $nach['fahrzeug:3']['name'] === 'GKW 1 neu' && $nach['fahrzeug:3']['meter'] === 1500);
$check('wer ohne „gesetzt" auftaucht, zählt ab der ersten Zeile', $nach['einheit:2']['aktiv'] === true && $nach['einheit:2']['minuten'] === 180);
$check('Aktive stehen oben', array_column($zeiten, 'schluessel') === ['einheit:1', 'einheit:2', 'fahrzeug:3']);
$check('Fahrzeuge im Einsatz', karten_fahrzeuge_im_einsatz($zeiten) === [] && karten_fahrzeuge_im_einsatz(karten_einsatzzeiten(array_slice($protokoll, 0, 4), strtotime('2026-11-14 10:00:00'))) === [3 => true]);
$check('Zeile in Worten', karten_protokoll_text(['aktion' => 'versetzt', 'meter' => 240, 'lat' => 53.0, 'lng' => 8.0]) === 'versetzt um 240 m'
    && karten_protokoll_text(['aktion' => 'versetzt', 'meter' => null, 'lat' => null, 'lng' => null]) === 'von der Karte genommen, bleibt in der Liste'
    && karten_protokoll_text(['aktion' => 'gesetzt', 'lat' => null, 'lng' => null]) === 'aufgenommen (ohne Stelle)' && karten_protokoll_text(['aktion' => 'entfernt']) === 'herausgenommen');

$GLOBALS['rows'] = ['FROM karten_protokoll p LEFT JOIN users u' => array_map(static fn($x, $i) => $x + ['id' => $i + 1, 'karte_id' => 9, 'von_lat' => null, 'von_lng' => null, 'lat' => 53.0793, 'lng' => 8.8017, 'wer' => 'Tina Tester'], $protokoll, array_keys($protokoll))];
$daten = karten_protokoll_daten(9, strtotime('2026-11-14 15:00:00'));
$check('fertig beschriftet für Seite und PDF', count($daten['protokoll']) === 8 && $daten['protokoll'][0]['zeit'] === '14.11.2026 08:00' && $daten['protokoll'][0]['nach'] === '53.07930, 8.80170'
    && $daten['zeiten'][0]['dauer_text'] === '7 h' && $daten['zeiten'][0]['bis_text'] === 'noch im Einsatz' && $daten['zeiten'][2]['bis_text'] === '14.11.2026 14:00' && $daten['zeiten'][2]['strecke_text'] === '1,5 km');
$html = render_partial('karte_protokoll', ['karte' => $karte, 'einsatz' => $einsatz, 'zeiten' => $daten['zeiten'], 'protokoll' => array_reverse($daten['protokoll']),
    'darfFahrzeuge' => true, 'fahrzeuge' => [['id' => 4, 'bezeichnung' => 'MTW', 'funkrufname' => '']]]);
$check('Protokollseite: Einsatzzeiten, Protokoll, Fahrzeug aufnehmen, Verweis auf den Einsatz', str_contains($html, 'noch im Einsatz') && str_contains($html, '7 h') && str_contains($html, 'versetzt um 240 m')
    && str_contains($html, 'value="fahrzeug_auf"') && str_contains($html, 'p=einsatz&amp;id=5') && str_contains($html, 'csv=1') && !str_contains($html, 'value="fahrzeug_ende"'));
$GLOBALS['rows'] = [];

/* ================= Bibliotheken und Lizenzen ================= */
$bib = lizenzen_bibliotheken();
$check('drei mitgelieferte Bibliotheken', array_keys($bib) === ['einsatzauftrag', 'jspdf', 'qrcode']);
foreach ($bib as $key => $b) {
    $check("$key: Datei und Lizenztext sind da, Urheber, Lizenz, Quelle und Stand genannt", $b['vorhanden'] && $b['groesse'] > 1000 && strlen($b['text']) > 500
        && $b['urheber'] !== '' && $b['lizenz'] !== '' && str_starts_with($b['quelle'], 'https://') && $b['stand'] !== '' && $b['aenderung'] !== '');
}
$check('einsatzauftrag: EUPL-1.2 mit Urhebervermerk, im Lizenztext wie im Kopf der Datei', $bib['einsatzauftrag']['lizenz'] === 'EUPL-1.2'
    && str_contains($bib['einsatzauftrag']['text'], 'EUROPEAN UNION PUBLIC LICENCE v. 1.2') && str_contains($bib['einsatzauftrag']['text'], 'Johannes Rudolph')
    && str_contains($bib['einsatzauftrag']['urheber'], 'Johannes Rudolph'));
$kopf = substr($lies('public/assets/vendor/einsatzauftrag/einsatzauftrag.js'), 0, 900);
$check('einsatzauftrag.js nennt Urheber, Lizenz, Quelle und Stand', str_contains($kopf, 'Copyright (c) 2025-2026 Johannes Rudolph') && str_contains($kopf, 'EUPL-1.2')
    && str_contains($kopf, 'https://github.com/wattnpapa/einsatzauftrag') && str_contains($kopf, 'f642655c20d75416f52412c1f3994618303ec0bf')
    && str_contains($bib['einsatzauftrag']['stand'], 'f642655c20d75416f52412c1f3994618303ec0bf'));
$check('jsPDF: unverändert mit eigenem Lizenzkopf, MIT', str_starts_with($lies('public/assets/vendor/jspdf/jspdf.umd.min.js'), '/** @license') && str_contains($bib['jspdf']['text'], 'James Hall')
    && str_contains($bib['jspdf']['text'], 'Permission is hereby granted') && str_contains($bib['jspdf']['stand'], '4.2.1'));
$check('qrcode: MIT, Lizenzkopf in der Datei belassen', str_contains($lies('public/assets/js/qrcode.js'), 'Copyright (c) 2009 Kazuhiko Arase') && str_contains($bib['qrcode']['text'], 'Kazuhiko Arase'));
$check('Pfade führen nicht aus dem Ordner', lizenzen_pfad('../../config/config.php') === null && lizenzen_pfad('') === null && lizenzen_pfad('js/app.js') !== null);
$info = render_partial('lizenzen', ['bibliotheken' => $bib, 'dienste' => LIZENZEN_DIENSTE, 'symbole' => [['quelle' => 'github:eigner/zeichen@abc', 'lizenz' => 'CC BY 4.0', 'anzahl' => 12]]]);
$check('Seite nennt jede Bibliothek mit Urheber, Lizenz, Quelle und Lizenztext', str_contains($info, 'einsatzauftrag') && str_contains($info, 'Johannes Rudolph') && str_contains($info, 'EUPL-1.2')
    && str_contains($info, 'jsPDF') && str_contains($info, 'Kazuhiko Arase') && substr_count($info, '<summary>Lizenztext</summary>') === 3
    && str_contains($info, 'https://github.com/wattnpapa/einsatzauftrag') && str_contains($info, 'EUROPEAN UNION PUBLIC LICENCE'));
$check('Seite nennt Dienste und die Quellen der Symbole', str_contains($info, 'OpenStreetMap') && str_contains($info, 'ODbL') && str_contains($info, 'github:eigner/zeichen@abc') && str_contains($info, 'CC BY 4.0'));
$layout = $lies('views/layout.php');
$check('Fußzeile verweist auf die Seite', str_contains($layout, "url('lizenzen')") && str_contains($layout, 'Bibliotheken und Lizenzen'));
$check('fremde Bibliotheken werden nur dort geladen, wo sie gebraucht werden', str_contains($layout, "\$GLOBALS['ovb_einsatzauftrag_js']") && str_contains($layout, "\$GLOBALS['ovb_karte_einsatz_js']")
    && str_contains($lies('src/pages/einsatz_auftrag.php'), "\$GLOBALS['ovb_einsatzauftrag_js'] = true") && str_contains($lies('src/pages/karte.php'), "\$GLOBALS['ovb_karte_einsatz_js'] = \$speichern"));
$check('Skript zum Aktualisieren liegt bei', is_file(dirname($app) . '/scripts/einsatzauftrag-aktualisieren.sh'));

/* ================= Veranstaltung: Einsatzauftrag ablegen, in einen Einsatz umwandeln ================= */
require_once $app . '/src/lib/events.php';
$ev = ['id' => 40, 'titel' => 'Übung Deichverteidigung', 'beginn' => '2026-11-14 09:00:00', 'ende' => '2026-11-14 16:30:00', 'ort' => 'Deich Süd', 'standorte_namen' => 'Halle 1',
    'status' => 'geplant', 'fachgruppe_id' => 21, 'beschreibung' => 'Verbau üben', 'typ_slug' => 'uebung'];
$t1 = einsatz_tage_aus_veranstaltung($ev, [3, 8, 3, 0], 14);
$check('ein Tag: Beginn und Ende, Fachgruppe, Fahrzeuge, Teilnehmer als Helfer', count($t1) === 1 && $t1[0]['datum'] === '2026-11-14' && $t1[0]['von'] === '09:00' && $t1[0]['bis'] === '16:30'
    && $t1[0]['fachgruppe_id'] === 21 && $t1[0]['vehicles'] === [3, 8] && $t1[0]['staerke_h'] === 14 && $t1[0]['staerke_f'] === 0 && einsatz_tag_minuten($t1[0]) === 450);
$t2 = einsatz_tage_aus_veranstaltung(['beginn' => '2026-11-14 00:00:00', 'ende' => null, 'fachgruppe_id' => null] + $ev);
$check('ein Tag ohne Uhrzeit und ohne Ende: ganztägig', count($t2) === 1 && $t2[0]['von'] === null && $t2[0]['bis'] === null && $t2[0]['fachgruppe_id'] === null && $t2[0]['vehicles'] === []);
$t3 = einsatz_tage_aus_veranstaltung(['beginn' => '2026-11-14 18:00:00', 'ende' => null] + $ev);
$check('ein Tag mit Beginn, aber ohne Ende: läuft offen', $t3[0]['von'] === '18:00' && $t3[0]['bis'] === null);
$t4 = einsatz_tage_aus_veranstaltung(['beginn' => '2026-12-30 18:00:00', 'ende' => '2027-01-02 12:00:00'] + $ev, [3], 9);
$check('mehrere Tage: je Kalendertag eine Zeile ohne Uhrzeit, über den Jahreswechsel', array_column($t4, 'datum') === ['2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02']
    && array_filter(array_column($t4, 'von')) === [] && $t4[3]['vehicles'] === [3] && $t4[2]['staerke_h'] === 9);
$check('höchstens 60 Tage, ohne Beginn nichts', count(einsatz_tage_aus_veranstaltung(['beginn' => '2026-01-01 08:00:00', 'ende' => '2026-12-31 18:00:00'] + $ev)) === 60
    && einsatz_tage_aus_veranstaltung(['beginn' => ''] + $ev) === []);
$kopf = einsatz_kopf_aus_veranstaltung($ev, 'Halle 1 · Deich Süd', '2026-015', 11, strtotime('2026-11-10 12:00:00'));
$check('Kopf: Titel, Ort, Fachgruppe, Beschreibung, Bezug zur Veranstaltung; noch nicht vorbei = läuft', $kopf['titel'] === 'Übung Deichverteidigung' && $kopf['ort'] === 'Halle 1 · Deich Süd'
    && $kopf['event_id'] === 40 && $kopf['fachgruppe_id'] === 21 && $kopf['beschreibung'] === 'Verbau üben' && $kopf['status'] === 'laufend' && $kopf['nummer'] === '2026-015'
    && $kopf['art_id'] === 11 && str_contains($kopf['notiz'], 'umgewandelt am 10.11.2026'));
$check('was vorbei oder abgeschlossen ist, beginnt als beendet', einsatz_kopf_aus_veranstaltung($ev, '', '', null, strtotime('2026-11-20 12:00:00'))['status'] === 'beendet'
    && einsatz_kopf_aus_veranstaltung(['status' => 'abgeschlossen'] + $ev, '', '', null, strtotime('2026-11-10 12:00:00'))['status'] === 'beendet');

// Einsatzauftrag bei den Dateien der Veranstaltung ablegen
$GLOBALS['inserts'] = [];
$hochladen('Einsatzauftrag-1.pdf', $pdf);
[$n, $f, $evIds] = event_auftrag_ablegen(40, 'pdf', ['laufendeNummer' => '1', 'empfaenger' => 'B1', 'uebung' => true], current_user());
$evZeile = $nur('event_files')[0][1] ?? [];
$check('Einsatzauftrag an der Veranstaltung abgelegt' . ($f ? ' – ' . implode(' ', $f) : ''), $n === 1 && $f === [] && ($evZeile['herkunft'] ?? '') === 'auftrag' && $evZeile['event_id'] === 40
    && $evZeile['titel'] === 'Einsatzauftrag Nr. 1 an B1 (Übung)' && $evZeile['mime'] === 'application/pdf' && json_decode((string)$evZeile['daten'], true)['empfaenger'] === 'B1'
    && is_file($tmp . '/veranstaltungen/' . $evZeile['stored_name']));
$hochladen('Einsatzauftrag-2.pdf', 'kein pdf');
[$n2, $f2] = event_auftrag_ablegen(40, 'pdf', [], current_user());
$check('was kein PDF ist, wird auch dort abgewiesen', $n2 === 0 && count($f2) === 1 && count($nur('event_files')) === 1 && count(glob($tmp . '/veranstaltungen/*') ?: []) === 1);

// Umwandeln: Einsatz mit Zeilen und Fahrzeugen, Buchungen bekommen den Bezug, Einsatzaufträge kommen mit
$GLOBALS['inserts'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['rows'] = [
    'FROM event_vehicles ev' => [['id' => 3, 'bezeichnung' => 'GKW 1', 'funkrufname' => '', 'kennzeichen' => '', 'is_active' => 1]],
    'FROM event_files f' => [['id' => 90, 'event_id' => 40, 'herkunft' => 'auftrag', 'titel' => 'Einsatzauftrag Nr. 1 an B1 (Übung)', 'orig_name' => 'Einsatzauftrag-1.pdf',
        'stored_name' => $evZeile['stored_name'], 'thumb_name' => null, 'size_bytes' => strlen($pdf), 'daten' => $evZeile['daten'], 'uploaded_by' => 7],
        ['id' => 91, 'event_id' => 40, 'herkunft' => 'upload', 'titel' => 'Rechnung', 'orig_name' => 'r.pdf', 'stored_name' => 'gibtsnicht.pdf', 'thumb_name' => null, 'size_bytes' => 1, 'daten' => null, 'uploaded_by' => 7]],
];
$neuerEinsatz = einsatz_aus_veranstaltung($ev, current_user(), 14);
$e1 = $nur('einsaetze')[0][1] ?? [];
$check('Einsatz aus der Veranstaltung: Kopf mit Bezug, Zeitraum aus dem Tag', $neuerEinsatz > 0 && ($e1['event_id'] ?? 0) === 40 && $e1['titel'] === 'Übung Deichverteidigung'
    && $e1['beginn'] === '2026-11-14 09:00:00' && $e1['ende'] === '2026-11-14 16:30:00' && $e1['jahr'] === 2026 && $e1['ort'] === 'Halle 1 · Deich Süd' && $e1['created_by'] === 7);
$check('Zeile mit Fachgruppe, Stärke und Fahrzeug übernommen', count($nur('einsatz_tage')) === 1 && $nur('einsatz_tage')[0][1]['fachgruppe_id'] === 21 && $nur('einsatz_tage')[0][1]['staerke_h'] === 14
    && $nur('einsatz_tag_vehicles')[0][1]['vehicle_id'] === 3 && $nur('einsatz_vehicles')[0][1]['vehicle_id'] === 3);
$bezug = array_values(array_filter($GLOBALS['execs'], static fn($x) => str_contains($x[0], 'UPDATE expenses SET einsatz_id = ? WHERE event_id = ? AND einsatz_id IS NULL')));
$check('Buchungen der Veranstaltung hängen danach auch am Einsatz', count($bezug) === 1 && $bezug[0][1] === [$neuerEinsatz, 40]);
$kopie = $nur('einsatz_files');
$check('nur die Einsatzaufträge kommen mit – als eigene Kopie mit ihren Feldern', count($kopie) === 1 && $kopie[0][1]['herkunft'] === 'auftrag' && $kopie[0][1]['einsatz_id'] === $neuerEinsatz
    && $kopie[0][1]['stored_name'] !== $evZeile['stored_name'] && is_file($tmp . '/einsaetze/' . $kopie[0][1]['stored_name']) && is_file($tmp . '/veranstaltungen/' . $evZeile['stored_name'])
    && json_decode((string)$kopie[0][1]['daten'], true)['empfaenger'] === 'B1');
$GLOBALS['rows'] = [];

$aktionV = $lies('src/pages/event_action.php');
$check('Veranstaltung: PDF-Ablage und Umwandeln nur mit Recht', str_contains($aktionV, "post_str('action') === 'auftrag_pdf'") && str_contains($aktionV, "case 'zu_einsatz':")
    && str_contains($aktionV, "can('manage_einsaetze')") && str_contains($aktionV, 'einsatz_zu_veranstaltung(') && strpos($aktionV, "can('manage_events')") < strpos($aktionV, 'event_auftrag_ablegen('));
$ansichtV = $lies('views/event.php');
$check('Seite der Veranstaltung: Einsatzauftrag schreiben, umwandeln oder zum Einsatz', str_contains($ansichtV, "url('einsatz_auftrag', ['event_id' => \$id])") && str_contains($ansichtV, 'value="zu_einsatz"')
    && str_contains($ansichtV, 'In Einsatz umwandeln') && str_contains($ansichtV, 'Zum Einsatz') && str_contains($ansichtV, "'vorlage' => \$f['id']"));
$maskeV = render_partial('einsatz_auftrag', ['daten' => ['einsatz' => 'Übung Deichverteidigung', 'uebung' => true, 'laufendeNummer' => '2'], 'vorlage' => null,
    'ziel' => ['name' => 'Übung Deichverteidigung', 'url' => '/index.php?p=event&id=40', 'zurueck' => '/index.php?p=event&id=40#dateien', 'wo' => 'an der Veranstaltung', 'art' => 'Veranstaltung'],
    'konfig' => ['ablage' => '/index.php?p=event_action', 'csrf' => 'abc', 'felder' => ['action' => 'auftrag_pdf', 'event_id' => 40], 'zurueck' => '/index.php?p=event&id=40#dateien', 'fusszeile' => 'OV']]);
$check('Maske zur Veranstaltung: speichert dorthin, Übung ist angehakt', str_contains($maskeV, 'Als PDF an der Veranstaltung speichern') && str_contains($maskeV, 'p=event&amp;id=40#dateien')
    && (bool)preg_match('/data-ea-uebung value="1" checked/', $maskeV) && str_contains($maskeV, '&quot;action&quot;:&quot;auftrag_pdf&quot;') && str_contains($maskeV, '&quot;event_id&quot;:40'));
$seiteA = $lies('src/pages/einsatz_auftrag.php');
$check('Seite: Veranstaltung braucht das Recht der Veranstaltungen, Übungen bekommen das Wasserzeichen', str_contains($seiteA, "can('manage_events')") && str_contains($seiteA, "can('manage_einsaetze')")
    && str_contains($seiteA, "=== 'uebung'") && str_contains($seiteA, "'felder' => ['action' => 'auftrag_pdf', 'event_id' => \$id]"));
$check('Maske schickt die Felder, die die Seite vorgibt', str_contains($lies('public/assets/js/einsatzauftrag-maske.js'), 'Object.keys(K.felder || {})'));
$check('Wanderung 112: Herkunft und Felder an den Dateien, Bezug am Einsatz', str_contains($lies('src/cli/migrate.php'), "\$merken('112_veranstaltung_auftrag_einsatz')")
    && str_contains($lies('src/cli/migrate.php'), "ADD COLUMN herkunft VARCHAR(20) NOT NULL DEFAULT 'upload' AFTER art") && str_contains($lies('src/cli/migrate.php'), 'fk_einsatz_event')
    && (bool)preg_match('/CREATE TABLE IF NOT EXISTS event_files \(.*?herkunft\s+VARCHAR\(20\).*?daten\s+MEDIUMTEXT.*?\) ENGINE/s', $lies('sql/schema.sql'))
    && (bool)preg_match('/CREATE TABLE IF NOT EXISTS einsaetze \(.*?event_id\s+INT UNSIGNED NULL.*?fk_einsatz_event.*?\) ENGINE/s', $lies('sql/schema.sql')));

/* ================= Treibstoffart am Fahrzeug ================= */
require_once $app . '/src/lib/vehicles.php';
$check('sechs Treibstoffarten', array_values(FZ_TREIBSTOFFE) === ['Diesel', 'Diesel HVO', 'Benzin', 'Super', 'Super mit Bleizusatz', 'Super E10']);
$check('nur bekannte Schlüssel, sonst leer', vehicle_treibstoff('diesel_hvo') === 'diesel_hvo' && vehicle_treibstoff(' e10 ') === 'e10' && vehicle_treibstoff('kerosin') === ''
    && vehicle_treibstoff(null) === '' && vehicle_treibstoff_text('super_blei') === 'Super mit Bleizusatz' && vehicle_treibstoff_text('') === '');
$check('Änderung der Treibstoffart steht lesbar im Journal', isset(FZ_JOURNAL_FELDER['kraftstoff']) && vehicle_field_text('kraftstoff', 'diesel_hvo') === 'Diesel HVO');
$check('Formular, Akte, Tankmaske, Schema und Wanderung kennen die Treibstoffart', str_contains($lies('views/vehicle_edit.php'), 'name="kraftstoff"')
    && str_contains($lies('views/vehicle.php'), "'Treibstoff'") && str_contains($lies('views/vehicle_tank_edit.php'), 'vehicle_treibstoff_text')
    && str_contains($lies('src/lib/vehicles.php'), "'kraftstoff'        => vehicle_treibstoff(post_str('kraftstoff'))")
    && (bool)preg_match('/CREATE TABLE IF NOT EXISTS vehicles \(.*?kraftstoff\s+VARCHAR\(20\)\s+NOT NULL DEFAULT \'\'.*?\) ENGINE/s', $lies('sql/schema.sql'))
    && str_contains($lies('src/cli/migrate.php'), "\$merken('111_fahrzeug_treibstoff')"));

/* ================= Maske und Verdrahtung ================= */
$maske = render_partial('einsatz_auftrag', ['daten' => $v, 'vorlage' => null,
    'ziel' => ['name' => '2026-014 · Hochwasser Hunte', 'url' => '/index.php?p=einsatz&id=5', 'zurueck' => '/index.php?p=einsatz&id=5#dateien', 'wo' => 'am Einsatz', 'art' => 'Einsatz'],
    'konfig' => ['ablage' => '/index.php?p=einsatz_action', 'csrf' => 'abc', 'felder' => ['action' => 'pdf', 'herkunft' => 'auftrag', 'einsatz_id' => 5],
        'zurueck' => '/index.php?p=einsatz&id=5#dateien', 'fusszeile' => 'OV']]);
$check('Maske: alle Felder des Vordrucks, vorbelegt', substr_count($maske, 'data-ea-punkt=') === 13 && str_contains($maske, 'data-ea="auftrag"') && str_contains($maske, 'data-ea="fuehrung"')
    && str_contains($maske, 'value="THW OV Musterstadt"') && str_contains($maske, 'value="13"') && str_contains($maske, '>Pegel 5,80 m</textarea>') && str_contains($maske, 'data-ea-uebung'));
$check('Maske: speichern am Einsatz, nur herunterladen, Vorschau – ohne Skript im HTML', str_contains($maske, 'data-ea-speichern') && str_contains($maske, 'data-ea-laden')
    && str_contains($maske, 'data-ea-vorschau') && !preg_match('/\son[a-z]+="/', $maske) && !str_contains($maske, '<script'));
$index = $lies('public/index.php');
foreach (['einsatz_auftrag', 'einsatz_action', 'einsatz_datei', 'karte_protokoll', 'lizenzen'] as $route) {
    $check("Seite $route", str_contains($index, "'$route'") && is_file($app . '/src/pages/' . $route . '.php'));
}
$aktion = $lies('src/pages/einsatz_action.php');
$check('Ablegen nur mit Recht, PDF-Antwort als JSON', str_contains($aktion, "can('manage_einsaetze')") && str_contains($aktion, 'application/json') && str_contains($aktion, 'einsatz_auftrag_daten(post_str'));
$check('Dokumente ausliefern: nur mit Recht, ohne Typraten, mit strenger Regel', str_contains($lies('src/pages/einsatz_datei.php'), "can('view_einsaetze')")
    && str_contains($lies('src/pages/einsatz_datei.php'), 'nosniff') && str_contains($lies('src/pages/einsatz_datei.php'), "default-src \\'none\\'"));
$schema = $lies('sql/schema.sql');
$wanderung = $lies('src/cli/migrate.php');
$spalten = static fn(string $s): array => array_values(array_filter(array_map(static fn($z) => preg_match('/^\s+([a-z_]+)\s+[A-Z]/', $z, $m) ? $m[1] : '', explode("\n", $s))));
foreach (['einsatz_tag_vehicles', 'einsatz_files', 'karten_protokoll'] as $t) {
    preg_match('/CREATE TABLE IF NOT EXISTS ' . $t . ' \((.*?)\) ENGINE/s', $schema, $ms);
    preg_match('/CREATE TABLE ' . $t . ' \((.*?)\) ENGINE/s', $wanderung, $mw);
    $check("Tabelle $t in Schema und Wanderung mit denselben Spalten", $spalten($ms[1] ?? '') !== [] && $spalten($ms[1] ?? '') === $spalten($mw[1] ?? 'x'));
}
$check('Wanderungen 109 und 110', str_contains($wanderung, "\$merken('109_einsatz_zeilen_dateien')") && str_contains($wanderung, "\$merken('110_karten_einsatz_protokoll')")
    && str_contains($wanderung, 'ADD COLUMN fachgruppe_id INT UNSIGNED NULL AFTER bis') && str_contains($wanderung, 'fk_karte_einsatz') && str_contains($wanderung, 'fk_etag_fg'));
$check('Schema: Betriebsstunden je Fahrzeug', (bool)preg_match('/CREATE TABLE IF NOT EXISTS einsatz_tag_vehicles \(.*?betriebsstunden DECIMAL\(6,1\) NULL.*?\) ENGINE/s', $schema));
$check('Schema: Fachgruppe je Zeile, Kilometer je Fahrzeug, Karte am Einsatz', (bool)preg_match('/CREATE TABLE IF NOT EXISTS einsatz_tage \(.*?fachgruppe_id INT UNSIGNED NULL.*?\) ENGINE/s', $schema)
    && (bool)preg_match('/CREATE TABLE IF NOT EXISTS einsatz_tag_vehicles \(.*?km\s+DECIMAL\(7,1\) NULL.*?\) ENGINE/s', $schema)
    && (bool)preg_match('/CREATE TABLE IF NOT EXISTS karten \(.*?einsatz_id\s+INT UNSIGNED NULL.*?\) ENGINE/s', $schema));
$js = $lies('public/assets/js/karte.js');
$check('Karte meldet beim Ziehen, auf welcher Karte gezogen wurde', substr_count($js, "action: 'fahrzeug_setzen', karte_id: K.id") === 2);
$api = $lies('src/pages/karten_api.php');
$check('Schnittstelle: Fahrzeug gezogen kommt ins Protokoll, Abruf des Protokolls', str_contains($api, 'karten_protokoll_fahrzeug(') && str_contains($api, "get_str('action') === 'protokoll'"));
$sicher = $lies('public/assets/js/karte-einsatz.js') . $lies('public/assets/js/einsatzauftrag-maske.js');
$check('Skripte: Texte nur als Text, nie als HTML', !str_contains($sicher, 'innerHTML') && !str_contains($sicher, 'insertAdjacentHTML') && !str_contains($sicher, 'document.write') && !str_contains($sicher, 'eval('));

// aufräumen
foreach (array_merge(glob($tmp . '/einsaetze/*') ?: [], glob($tmp . '/veranstaltungen/*') ?: []) as $f) { @unlink($f); }
@rmdir($tmp . '/einsaetze');
@rmdir($tmp . '/veranstaltungen');
@rmdir($tmp);

echo "$ok bestanden, $fail fehlgeschlagen\n";
