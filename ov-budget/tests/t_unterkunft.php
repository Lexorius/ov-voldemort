<?php
declare(strict_types=1);
/* Unterkunft: Mängel und Aufgaben am Gebäude, Defekte aus einem Divera-Formular */
date_default_timezone_set('Europe/Berlin');
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'divera_aktiv' => '1', 'divera_accesskey' => 'system',
    'divera_personal_key' => 'persoenlich', 'unterkunft_modul_name' => 'Unterkunft', 'unterkunft_intro' => 'Alles am Gebäude.'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['zeilen'] = [];
$GLOBALS['vorhanden'] = null;
$GLOBALS['can'] = [];

$li = static fn(int $id, string $key, string $label, string $slug, int $default = 0, int $final = 0, int $weight = 0): array
    => ['id' => $id, 'list_key' => $key, 'label' => $label, 'slug' => $slug, 'color' => '#123456', 'sort_order' => $id,
        'is_default' => $default, 'is_final' => $final, 'is_active' => 1, 'weight' => $weight];
$GLOBALS['listen'] = [
    $li(1, 'unterkunft_status', 'Gemeldet', 'gemeldet', 1), $li(2, 'unterkunft_status', 'In Arbeit', 'in-arbeit'),
    $li(3, 'unterkunft_status', 'Firma beauftragt', 'beauftragt'), $li(4, 'unterkunft_status', 'Wartet', 'wartet'),
    $li(5, 'unterkunft_status', 'Erledigt', 'erledigt', 0, 1), $li(6, 'unterkunft_status', 'Abgelehnt', 'abgelehnt', 0, 1),
    $li(11, 'todo_prioritaet', 'Sofort', 'sofort', 0, 0, 40), $li(12, 'todo_prioritaet', 'Hoch', 'hoch', 0, 0, 30),
    $li(13, 'todo_prioritaet', 'Normal', 'normal', 1, 0, 20), $li(14, 'todo_prioritaet', 'Niedrig', 'niedrig', 0, 0, 10),
    $li(20, 'wunsch_status', 'Offen', 'offen', 1),
];
$pl = static fn(int $id, string $name, string $typ, ?int $parent = null, string $kurz = '', int $aktiv = 1): array
    => ['id' => $id, 'name' => $name, 'typ' => $typ, 'parent_id' => $parent, 'kurz' => $kurz, 'is_active' => $aktiv, 'sort_order' => $id];
$GLOBALS['plaetze'] = [
    $pl(1, 'Unterkunft', 'gebaeude'), $pl(2, 'Küche', 'raum', 1), $pl(3, 'Fahrzeughalle', 'halle'),
    $pl(4, 'Tor 3', 'stellplatz', 3, 'T3'), $pl(5, 'Lager', 'raum', 3), $pl(6, 'Lager', 'raum', 1),
    $pl(7, 'Werkstatt', 'raum', 3, '', 0),
];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM list_items')) { return $GLOBALS['listen']; }
    if (str_contains($sql, 'FROM standorte')) { return $GLOBALS['plaetze']; }
    if (str_contains($sql, 'FROM users')) { return [['id' => 17, 'display_name' => 'Anna Beispiel', 'username' => 'anna']]; }
    if (str_contains($sql, 'FROM unterkunft_punkte')) { $GLOBALS['abfragen'][] = [$sql, $p]; return $GLOBALS['zeilen']; }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM standorte')) {
        foreach ($GLOBALS['plaetze'] as $s) { if ((int)$s['id'] === (int)$p[0]) { return $s; } }
        return null;
    }
    return $GLOBALS['vorhanden'];
}
function db_val(string $sql, array $p = [], mixed $d = null) { $GLOBALS['vals'][] = [$sql, $p]; return $GLOBALS['val'] ?? $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 40 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
final class StubStmtU { public function execute(array $p): bool { return true; } }
final class StubPdoU { public function prepare(string $s): StubStmtU { return new StubStmtU(); } }
function db(): StubPdoU { return new StubPdoU(); }
function can(string $what, mixed $ctx = null): bool { return $GLOBALS['can'][$what] ?? true; }
function current_user(): ?array { return ['id' => 1, 'display_name' => 'Tester']; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'standorte', 'unterkunft'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}
@mkdir(__DIR__ . '/alt');
$quelle = (string)file_get_contents($app . '/src/lib/divera.php');
$quelle = (string)preg_replace('/function divera_request\(.*?\n}\n/s', '', $quelle, 1);
file_put_contents(__DIR__ . '/alt/divera_unterkunft_ohne_request.php', $quelle);
$GLOBALS['anfragen'] = [];
$GLOBALS['eintraege'] = [];
function divera_request(string $path, array $query = [], ?string $key = null, ?array $post = null): array {
    $GLOBALS['anfragen'][] = [$path, $key, $post];
    if ($post !== null) {
        return ['success' => true, 'status' => $post['Report']['status']];
    }
    return ['success' => true, 'data' => ['items' => array_slice($GLOBALS['eintraege'], (int)($query['offset'] ?? 0), 50), 'total' => count($GLOBALS['eintraege'])]];
}
require __DIR__ . '/alt/divera_unterkunft_ohne_request.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};
$plaetze = $GLOBALS['plaetze'];

/* ---------- Ort erkennen ---------- */
$check('Vergleichsform', unterkunft_norm(' Küche / Straße 3 ') === 'kuechestrasse3');
$check('genauer Name', unterkunft_standort_finden('Küche', $plaetze) === 2);
$check('Umlaut aufgelöst', unterkunft_standort_finden('kueche', $plaetze) === 2);
$check('Kürzel', unterkunft_standort_finden('T3', $plaetze) === 4);
$check('doppelter Name bleibt offen', unterkunft_standort_finden('Lager', $plaetze) === null);
$check('Pfad macht eindeutig', unterkunft_standort_finden('Fahrzeughalle Lager', $plaetze) === 5);
$check('Pfadende', unterkunft_standort_finden('Halle / Tor 3', $plaetze) === 4);
$check('Name im Satz', unterkunft_standort_finden('Wasserhahn in der Küche tropft', $plaetze) === 2);
$check('nichts erkannt', unterkunft_standort_finden('hinter dem Zaun', $plaetze) === null);
$check('leer', unterkunft_standort_finden('  ', $plaetze) === null);

/* ---------- Gebäude, Ort, Alter ---------- */
$check('Wurzel', unterkunft_wurzel(4, $plaetze) === 3 && unterkunft_wurzel(2, $plaetze) === 1 && unterkunft_wurzel(1, $plaetze) === 1);
$check('Wurzel unbekannt', unterkunft_wurzel(99, $plaetze) === null);
$offen = [
    ['id' => 1, 'art' => 'mangel', 'standort_id' => 2, 'status_final' => 0, 'zustaendig_id' => 5, 'faellig_am' => '2026-01-10', 'created_at' => '2026-01-01 08:00:00', 'divera_entry_id' => 'd1'],
    ['id' => 2, 'art' => 'aufgabe', 'standort_id' => 4, 'status_final' => 0, 'zustaendig_id' => null, 'faellig_am' => '2026-03-01', 'created_at' => '2026-01-19 08:00:00', 'divera_entry_id' => ''],
    ['id' => 3, 'art' => 'mangel', 'standort_id' => 7, 'status_final' => 0, 'zustaendig_id' => null, 'faellig_am' => null, 'created_at' => '2026-01-20 07:00:00', 'divera_entry_id' => ''],
    ['id' => 4, 'art' => 'mangel', 'standort_id' => null, 'ort_text' => 'am Zaun', 'status_final' => 0, 'zustaendig_id' => 5, 'faellig_am' => null, 'created_at' => '2026-01-20 07:00:00', 'divera_entry_id' => ''],
];
$g = unterkunft_gebaeude($plaetze, $offen);
$check('zwei Wurzeln', count($g['gebaeude']) === 2 && $g['gebaeude'][0]['platz']['name'] === 'Unterkunft');
$check('darunter gezählt', $g['gebaeude'][0]['darunter'] === ['raum' => 2] && $g['gebaeude'][1]['darunter'] === ['stellplatz' => 1, 'raum' => 1]);
$check('offene je Gebäude', $g['gebaeude'][0]['offen'] === 1 && $g['gebaeude'][1]['offen'] === 2 && $g['ohne'] === 1);
$check('Kennungen samt Unterplätzen', $g['gebaeude'][1]['ids'] === [3, 4, 5]);
$check('Text Mehrzahl', unterkunft_darunter_text(['raum' => 2]) === '2 Räume');
$check('Text gemischt', unterkunft_darunter_text(['stellplatz' => 1, 'raum' => 1]) === '1 Raum · 1 Fahrzeugstellplatz');
$check('Text leer', unterkunft_darunter_text([]) === '');
$check('Ort als Pfad', unterkunft_ort_text(['standort_id' => 4], $plaetze) === 'Fahrzeughalle › Tor 3');
$check('Ort als Freitext', unterkunft_ort_text(['standort_id' => null, 'ort_text' => ' am Zaun '], $plaetze) === 'am Zaun');
$jetzt = strtotime('2026-01-20 12:00:00');
$check('Alter in Tagen', unterkunft_alter_tage($offen[0], $jetzt) === 19 && unterkunft_alter_tage($offen[2], $jetzt) === 0);
$check('überfällig', unterkunft_ueberfaellig($offen[0], '2026-01-20') && !unterkunft_ueberfaellig($offen[1], '2026-01-20'));
$check('erledigt ist nie überfällig', !unterkunft_ueberfaellig(['status_final' => 1, 'faellig_am' => '2020-01-01'], '2026-01-20'));
$st = unterkunft_stats($offen, $jetzt);
$check('Kennzahlen', $st['offen'] === 4 && $st['maengel'] === 3 && $st['aufgaben'] === 1 && $st['ueberfaellig'] === 1
    && $st['ohne_zustaendig'] === 2 && $st['aeltester_tage'] === 19 && $st['aus_divera'] === 1);
$check('Kennzahlen leer', unterkunft_stats([])['offen'] === 0 && unterkunft_stats([])['aeltester_tage'] === 0);
$check('Art Vorgabe', unterkunft_art('x') === 'mangel' && unterkunft_art('aufgabe') === 'aufgabe');

/* ---------- Rechte ---------- */
$GLOBALS['can'] = ['manage_unterkunft' => false];
$check('eigene offene Meldung', unterkunft_darf_aendern(['created_by' => 9, 'status_final' => 0], ['id' => 9]));
$check('fremde Meldung nicht', !unterkunft_darf_aendern(['created_by' => 8, 'status_final' => 0], ['id' => 9]));
$check('abgeschlossene nicht', !unterkunft_darf_aendern(['created_by' => 9, 'status_final' => 1], ['id' => 9]));
$check('als Melder aus Divera erkannt', unterkunft_darf_aendern(['created_by' => null, 'melder_user_id' => 9, 'status_final' => 0], ['id' => 9]));
$GLOBALS['can'] = ['manage_unterkunft' => false, 'report_unterkunft' => false];
$check('ohne Melderecht nicht', !unterkunft_darf_aendern(['created_by' => 9, 'status_final' => 0], ['id' => 9]));
$GLOBALS['can'] = [];
$check('Leitung immer', unterkunft_darf_aendern(['created_by' => 8, 'status_final' => 1], ['id' => 9]));

/* ---------- Melden und ändern ---------- */
$user = ['id' => 9, 'display_name' => 'Bernd Helfer'];
$_POST = ['titel' => '', 'beschreibung' => 'x'];
[$id, $fehler] = unterkunft_punkt_save_from_post(null, $user);
$check('ohne Titel kein Punkt', $id === null && count($fehler) === 1 && $GLOBALS['inserts'] === []);

$GLOBALS['can'] = ['manage_unterkunft' => false];
$_POST = ['titel' => 'Wasserhahn tropft', 'beschreibung' => 'seit Montag', 'art' => 'mangel', 'standort_id' => '2', 'ort_text' => 'Spüle links',
    'prioritaet_id' => '12', 'zustaendig_id' => '17', 'faellig_am' => '2026-02-01', 'firma' => 'Müller'];
[$id, $fehler] = unterkunft_punkt_save_from_post(null, $user);
$d = $GLOBALS['inserts'][0][1] ?? [];
$check('Punkt angelegt', $id > 0 && $fehler === [] && $GLOBALS['inserts'][0][0] === 'unterkunft_punkte');
$check('Felder des Melders', $d['titel'] === 'Wasserhahn tropft' && $d['standort_id'] === 2 && $d['ort_text'] === 'Spüle links' && $d['prioritaet_id'] === 12);
$check('Status Vorgabe, Melder gesetzt', $d['status_id'] === 1 && $d['melder_user_id'] === 9 && $d['melder_name'] === 'Bernd Helfer' && $d['created_by'] === 9);
$check('Zuständigkeit und Frist nur für die Leitung', !array_key_exists('zustaendig_id', $d) && !array_key_exists('faellig_am', $d) && !array_key_exists('firma', $d));
$log = $GLOBALS['inserts'][1] ?? ['', []];
$check('Verlauf: gemeldet', $log[0] === 'unterkunft_log' && $log[1]['text'] === 'Mangel gemeldet' && $log[1]['status_neu'] === 1 && $log[1]['autor'] === 'Bernd Helfer' && $log[1]['quelle'] === 'mensch');

$GLOBALS['can'] = [];
$GLOBALS['inserts'] = [];
$_POST['standort_id'] = '999';
$_POST['art'] = 'aufgabe';
$_POST['prioritaet_id'] = '';
[$id] = unterkunft_punkt_save_from_post(null, $user);
$d = $GLOBALS['inserts'][0][1];
$check('unbekannter Platz fällt weg', $d['standort_id'] === null);
$check('Leitung setzt Zuständigkeit, Frist, Firma', $d['zustaendig_id'] === 17 && $d['faellig_am'] === '2026-02-01' && $d['firma'] === 'Müller');
$check('Aufgabe mit Vorgabe-Dringlichkeit', $d['art'] === 'aufgabe' && $d['prioritaet_id'] === 13 && $GLOBALS['inserts'][1][1]['text'] === 'Aufgabe gemeldet');

$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$alt = ['id' => 7, 'titel' => 'Wasserhahn tropft', 'beschreibung' => 'seit Montag', 'art' => 'aufgabe', 'standort_id' => null, 'ort_text' => 'Spüle links',
    'prioritaet_id' => 13, 'zustaendig_id' => 17, 'faellig_am' => '2026-02-01', 'firma' => 'Müller', 'status_id' => 1];
$_POST['titel'] = 'Wasserhahn in der Küche tropft';
$_POST['prioritaet_id'] = '11';
[$id] = unterkunft_punkt_save_from_post($alt, $user);
$check('Änderung gespeichert', $id === 7 && $GLOBALS['updates'][0][0] === 'unterkunft_punkte' && !array_key_exists('status_id', $GLOBALS['updates'][0][1]));
$check('Verlauf nennt, was sich änderte', ($GLOBALS['inserts'][0][1]['text'] ?? '') === 'Geändert: Titel, Dringlichkeit');
$GLOBALS['inserts'] = [];
unterkunft_punkt_save_from_post(['titel' => $_POST['titel'], 'prioritaet_id' => 11] + $alt, $user);
$check('ohne Änderung kein Verlaufseintrag', $GLOBALS['inserts'] === []);

/* ---------- Status und Verlauf ---------- */
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$p = ['id' => 7, 'status_id' => 1];
$check('auf Erledigt', unterkunft_status_setzen($p, 5, 'Dichtung getauscht', $user));
$check('Erledigt-Datum gesetzt', $GLOBALS['updates'][0][1]['status_id'] === 5 && $GLOBALS['updates'][0][1]['erledigt_am'] !== null);
$l = $GLOBALS['inserts'][0][1];
$check('Verlauf mit altem und neuem Status', $l['status_alt'] === 1 && $l['status_neu'] === 5 && $l['text'] === 'Dichtung getauscht');
$GLOBALS['updates'] = [];
$check('wieder geöffnet', unterkunft_status_setzen(['id' => 7, 'status_id' => 5], 2, '', $user) && $GLOBALS['updates'][0][1]['erledigt_am'] === null);
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$check('gleicher Status ohne Notiz ändert nichts', !unterkunft_status_setzen($p, 1, '  ', $user) && $GLOBALS['inserts'] === []);
$check('gleicher Status mit Notiz', unterkunft_status_setzen($p, 1, 'Termin Donnerstag', $user) && $GLOBALS['updates'] === []
    && $GLOBALS['inserts'][0][1]['status_neu'] === null && $GLOBALS['inserts'][0][1]['text'] === 'Termin Donnerstag');
$check('Status einer fremden Liste abgelehnt', !unterkunft_status_setzen($p, 20, '', $user));
$check('unbekannter Status abgelehnt', !unterkunft_status_setzen($p, 999, 'x', $user));
$GLOBALS['execs'] = [];
unterkunft_punkt_delete(['id' => 7, 'titel' => 'x']);
$check('Löschen', str_contains($GLOBALS['execs'][0][0], 'DELETE FROM unterkunft_punkte') && $GLOBALS['execs'][0][1] === [7]);

/* ---------- Abfragen ---------- */
$GLOBALS['abfragen'] = [];
unterkunft_punkte(['zustand' => 'offen', 'art' => 'aufgabe', 'status_id' => 2, 'standort_ids' => [3, 4, 4], 'q' => 'Tor']);
[$sql, $par] = $GLOBALS['abfragen'][0];
$check('Filter als Platzhalter', str_contains($sql, 'COALESCE(st.is_final, 0) = 0') && str_contains($sql, 'p.standort_id IN (?,?)')
    && $par === ['aufgabe', 2, 3, 4, '%Tor%', '%Tor%', '%Tor%', '%Tor%'] && !str_contains($sql, 'Tor'));
$GLOBALS['abfragen'] = [];
unterkunft_punkte(['zustand' => 'alle', 'ohne_ort' => true]);
$check('alle, ohne Ort', !str_contains($GLOBALS['abfragen'][0][0], 'is_final, 0) =') && str_contains($GLOBALS['abfragen'][0][0], 'p.standort_id IS NULL'));
$GLOBALS['vals'] = [];
$check('keine Plätze, keine Abfrage', unterkunft_offen_anzahl([]) === 0 && $GLOBALS['vals'] === []);
$GLOBALS['val'] = 3;
$check('offene an Plätzen', unterkunft_offen_anzahl([3, '4', 0]) === 3 && $GLOBALS['vals'][0][1] === [3, 4]);

/* ---------- Divera: Defektformular ---------- */
$check('Ziel defekt', divera_ziel(['ziel' => 'defekt']) === 'defekt' && divera_ziel_wert('defekt') === 'defekt' && divera_ziel_wert('quatsch') === 'wunsch');
$check('drei Ziele', array_keys(DIVERA_ZIELE) === ['wunsch', 'thema', 'defekt']);
$check('Mehrzahl', divera_ziel_mehrzahl('defekt') === 'Mängel' && divera_ziel_mehrzahl('thema') === 'Themen' && divera_ziel_mehrzahl('wunsch') === 'Wünsche');
$check('Felder des Mangels', array_keys(divera_map_targets('defekt')) === ['titel', 'beschreibung', 'ort', 'prioritaet', 'melder']);
$namen = ['Was ist defekt?', 'Wo (Raum)?', 'Beschreibung', 'Dringlichkeit', 'Gemeldet von', 'Telefon'];
$v = divera_suggest_map($namen, 'defekt');
$check('Vorschlag', $v === ['titel' => 'Was ist defekt?', 'ort' => 'Wo (Raum)?', 'beschreibung' => 'Beschreibung', 'prioritaet' => 'Dringlichkeit', 'melder' => 'Gemeldet von']);
$check('Themen-Vorschlag bleibt', (divera_suggest_map(['Thema'], 'thema')['titel'] ?? '') === 'Thema');

function feld(string $name): array { return ['id' => 'f-' . md5($name), 'name' => $name, 'type' => 'textinput', 'options' => [], 'required' => '0']; }
function defekt_eintrag(string $id, array $werte, int $anhaenge = 0, ?int $status = null): array {
    $felder = [];
    foreach ($werte as $name => $wert) { $felder[] = ['field' => feld($name), 'value' => $wert]; }
    return ['id' => $id, 'reporttype_id' => 800, 'attachment_count' => $anhaenge, 'attachment' => [], 'address' => '', 'fields' => $felder,
        'ts_create' => 1767864000, 'status' => $status];
}
$GLOBALS['eintraege'] = [
    defekt_eintrag('d1', ['Was ist defekt?' => 'Wasserhahn tropft', 'Wo (Raum)?' => 'Küche', 'Beschreibung' => 'seit Montag',
        'Dringlichkeit' => 'hoch', 'Gemeldet von' => 'Anna Beispiel', 'Telefon' => '0151 123'], 2, 0),
    defekt_eintrag('d2', ['Was ist defekt?' => '', 'Wo (Raum)?' => 'hinter dem Zaun', 'Beschreibung' => "Zaun locker\nzweite Zeile", 'Gemeldet von' => 'Unbekannt Person']),
    defekt_eintrag('d3', ['Was ist defekt?' => 'Licht in der Werkstatt flackert', 'Wo (Raum)?' => '']),
];
$form = ['id' => 5, 'form_id' => '800', 'name' => 'Defekte Unterkunft', 'ziel' => 'defekt', 'default_status_id' => null, 'default_fachgruppe_id' => null,
    'field_map' => json_encode($v), 'status_sync' => 1];
$e = divera_fetch_entries('800');
$m = unterkunft_aus_divera($e[0], $v, $form, $plaetze, ['anna beispiel' => 17]);
$check('Titel und Art', $m['titel'] === 'Wasserhahn tropft' && $m['art'] === 'mangel');
$check('Platz erkannt, kein Freitext', $m['standort_id'] === 2 && $m['ort_text'] === '');
$check('Dringlichkeit aus dem Text', $m['prioritaet_id'] === 12);
$check('Melder als Benutzer', $m['melder_user_id'] === 17 && $m['melder_name'] === 'Anna Beispiel');
$check('Restfeld und Anhänge in der Beschreibung', str_starts_with($m['beschreibung'], 'seit Montag') && str_contains($m['beschreibung'], 'Telefon: 0151 123') && str_contains($m['beschreibung'], '2 Datei(en)'));
$check('Herkunft und Status', $m['divera_form_id'] === '800' && $m['divera_entry_id'] === 'd1' && $m['divera_status'] === 0 && $m['status_id'] === 1);
$check('Meldezeit aus Divera', $m['created_at'] === date('Y-m-d H:i:s', 1767864000));
$m2 = unterkunft_aus_divera($e[1], $v, $form, $plaetze, ['anna beispiel' => 17]);
$check('Ersatztitel aus der ersten Zeile', $m2['titel'] === 'Zaun locker');
$check('unbekannter Ort bleibt Freitext', $m2['standort_id'] === null && $m2['ort_text'] === 'hinter dem Zaun');
$check('Name ohne Konto, Vorgabe-Dringlichkeit', $m2['melder_user_id'] === null && $m2['melder_name'] === 'Unbekannt Person' && $m2['prioritaet_id'] === 13);
$m3 = unterkunft_aus_divera($e[2], $v, $form, $plaetze);
$check('Raum nur im Titel wird gefunden', $m3['standort_id'] === 7 && $m3['ort_text'] === '');
$m4 = unterkunft_aus_divera($e[0], $v, ['default_status_id' => 2] + $form, $plaetze);
$check('Status-Vorgabe des Formulars', $m4['status_id'] === 2);
$leer = unterkunft_aus_divera(divera_entry_from_row(defekt_eintrag('d9', ['Telefon' => '1'])), $v, $form, $plaetze);
$check('ganz ohne Titel', $leer['titel'] === 'Defekt aus Divera d9');

$GLOBALS['inserts'] = [];
$res = divera_import_form($form, 1);
$tabellen = array_column($GLOBALS['inserts'], 0);
$check('drei Mängel', $res['created'] === 3 && $res['failed'] === 0);
$check('in unterkunft_punkte, nicht in Wünsche oder Themen', count(array_keys($tabellen, 'unterkunft_punkte')) === 3 && !in_array('wishes', $tabellen, true) && !in_array('talking_points', $tabellen, true));
$verlauf = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'unterkunft_log'));
$check('Verlauf nennt Divera', count($verlauf) === 3 && $verlauf[0][1]['quelle'] === 'divera' && $verlauf[0][1]['autor'] === 'Divera' && str_contains($verlauf[0][1]['text'], 'Defekte Unterkunft') && $verlauf[0][1]['status_neu'] === 1);
$dlog = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'divera_log'));
$check('Divera-Protokoll ohne Wunsch und Thema', count($dlog) === 3 && $dlog[0][1]['wish_id'] === null && $dlog[0][1]['tp_id'] === null && $dlog[0][1]['message'] === 'Mangel gemeldet: Wasserhahn tropft');
$GLOBALS['inserts'] = [];
$res = divera_import_form($form, 1, true);
$check('Vorschau legt nichts an', $res['created'] === 3 && $GLOBALS['inserts'] === [] && $res['preview'][0]['standort_id'] === 2);
$GLOBALS['inserts'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['vorhanden'] = ['id' => 41, 'divera_status' => 6];
$res = divera_import_form($form, 1);
$check('bekannte Einträge übersprungen', $res['created'] === 0 && $res['skipped'] === 3 && !in_array('unterkunft_punkte', array_column($GLOBALS['inserts'], 0), true));
$GLOBALS['vorhanden'] = null;

/* ---------- Divera: Bearbeitungsstand ---------- */
$check('Zielstatus', unterkunft_divera_zielstatus(['status_final' => 0, 'status_slug' => 'gemeldet']) === 6
    && unterkunft_divera_zielstatus(['status_final' => 0, 'status_slug' => 'beauftragt']) === 2
    && unterkunft_divera_zielstatus(['status_final' => 0, 'status_slug' => 'wartet']) === 2
    && unterkunft_divera_zielstatus(['status_final' => 1, 'status_slug' => 'abgelehnt']) === 4);
$GLOBALS['zeilen'] = [
    ['id' => 41, 'name' => 'Wasserhahn', 'divera_entry_id' => 'd1', 'divera_status' => 0, 'status_final' => 0, 'status_slug' => 'gemeldet'],
    ['id' => 42, 'name' => 'Zaun', 'divera_entry_id' => 'd2', 'divera_status' => 6, 'status_final' => 0, 'status_slug' => 'in-arbeit'],
    ['id' => 43, 'name' => 'Licht', 'divera_entry_id' => 'd3', 'divera_status' => 4, 'status_final' => 0, 'status_slug' => 'gemeldet'],
    ['id' => 44, 'name' => 'Tür', 'divera_entry_id' => 'd4', 'divera_status' => 2, 'status_final' => 1, 'status_slug' => 'erledigt'],
];
$GLOBALS['anfragen'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['execs'] = [];
$st = divera_status_sync_form($form);
$gesendet = array_map(fn($a) => [$a[0], $a[2]['Report']['status']], array_values(array_filter($GLOBALS['anfragen'], fn($a) => $a[2] !== null)));
$check('drei Schritte nach vorn, keiner zurück', $st['gemeldet'] === 3 && $st['fehler'] === ''
    && $gesendet === [['/v2/reports/d1/status', 6], ['/v2/reports/d2/status', 2], ['/v2/reports/d4/status', 4]]);
$check('Stand am Mangel vermerkt', count(array_filter($GLOBALS['execs'], fn($x) => str_starts_with($x[0], 'UPDATE unterkunft_punkte SET divera_status'))) === 3);
$sys = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'unterkunft_log'));
$check('Verlauf: an Divera gemeldet', count($sys) === 3 && $sys[0][1]['quelle'] === 'system' && str_contains($sys[0][1]['text'], 'Stand an Divera gemeldet'));
$GLOBALS['anfragen'] = [];
$check('ohne Haken kein Abgleich', divera_status_sync_form(['status_sync' => 0] + $form)['gemeldet'] === 0 && $GLOBALS['anfragen'] === []);

/* ---------- Ansichten ---------- */
$zeile = ['id' => 41, 'titel' => 'Wasserhahn <tropft>', 'beschreibung' => 'seit Montag', 'art' => 'mangel', 'standort_id' => 2, 'ort_text' => '',
    'prioritaet_id' => 12, 'prioritaet_label' => 'Hoch', 'prioritaet_color' => '#b45309', 'status_id' => 1, 'status_label' => 'Gemeldet', 'status_color' => '#0284c7',
    'status_slug' => 'gemeldet', 'status_final' => 0, 'melder_user_id' => 17, 'melder_user_name' => 'Anna Beispiel', 'melder_name' => '', 'zustaendig_id' => null,
    'zustaendig_name' => null, 'firma' => 'Elektro Müller', 'faellig_am' => '2020-01-01', 'erledigt_am' => null, 'divera_entry_id' => 'd1', 'created_at' => '2026-01-08 10:00:00', 'created_by' => null];
$frei = ['id' => 42, 'titel' => 'Zaun locker', 'art' => 'aufgabe', 'standort_id' => null, 'ort_text' => 'hinter dem Zaun', 'divera_entry_id' => '', 'faellig_am' => null,
    'zustaendig_name' => 'Bernd Helfer', 'zustaendig_id' => 9, 'firma' => ''] + $zeile;
$daten = [
    'standorte' => $plaetze, 'punkte' => [$zeile, $frei], 'gesamt' => 2, 'blatt' => seiten_blaettern(2, 1, 50),
    'filter' => ['zustand' => 'offen', 'art' => '', 'status_id' => null, 'prioritaet_id' => null, 'q' => '', 'ohne_ort' => false, 'standort' => null],
    'stats' => unterkunft_stats([$zeile, $frei]), 'gebaeude' => unterkunft_gebaeude($plaetze, [$zeile, $frei]), 'jahr' => 2026,
    'kosten' => null, 'zaehler' => null, 'defektFormulare' => [['id' => 5, 'name' => 'Defekte Unterkunft', 'last_sync' => '2026-01-08 10:05:00', 'auto_import' => 1]],
    'melden' => true, 'verwalten' => true,
];
$html = render_partial('unterkunft', $daten);
$check('Übersicht: Titel maskiert', str_contains($html, 'Wasserhahn &lt;tropft&gt;') && !str_contains($html, '<tropft>'));
$check('Übersicht: Ort verlinkt, Freitext gekennzeichnet', str_contains($html, 'Unterkunft › Küche') && str_contains($html, 'hinter dem Zaun <span class="muted">(Freitext)</span>'));
$check('Übersicht: Marken', str_contains($html, '>Divera</span>') && str_contains($html, '>Aufgabe</span>') && str_contains($html, 'Elektro Müller'));
$check('Übersicht: Kennzahlen', str_contains($html, '1 Mängel · 1 Aufgaben · 1 aus Divera') && str_contains($html, 'niemand kümmert sich'));
$check('Übersicht: Räume mit offenen Punkten', str_contains($html, 'id="raeume"') && str_contains($html, '2 Räume') && str_contains($html, 'ohne zugeordneten Platz'));
$check('Übersicht: Divera-Formular genannt', str_contains($html, 'Defekte Unterkunft') && str_contains($html, 'automatisch'));
$check('Übersicht: ohne Rechte keine Kosten und Zähler', !str_contains($html, 'id="kosten"') && !str_contains($html, 'id="zaehler"'));
$check('Übersicht: Meldeknöpfe', str_contains($html, '+ Mangel melden') && str_contains($html, '+ Mangel hier'));
$html = render_partial('unterkunft', ['melden' => false, 'verwalten' => false, 'punkte' => [], 'gesamt' => 0, 'defektFormulare' => [],
    'kosten' => ['zeilen' => [], 'ohne' => ['ausgaben' => 0.0], 'summe' => ['ausgaben' => 0.0, 'einnahmen' => 0.0]]] + $daten);
$check('Übersicht leer: nichts offen, keine Meldeknöpfe', str_contains($html, 'Nichts offen') && !str_contains($html, '+ Mangel melden') && !str_contains($html, '+ Mangel hier'));
$check('Übersicht: Kostenabschnitt mit Verweis ins Budget', str_contains($html, 'id="kosten"') && str_contains($html, 'budget_objektkonten'));

$verlaufZeilen = [
    ['created_at' => '2026-01-08 10:00:00', 'user_name' => null, 'autor' => 'Divera', 'quelle' => 'divera', 'text' => 'Aus dem Divera-Formular übernommen', 'status_neu' => 1, 'alt_label' => null, 'neu_label' => 'Gemeldet', 'neu_color' => '#0284c7'],
    ['created_at' => '2026-01-09 09:00:00', 'user_name' => 'Bernd Helfer', 'autor' => 'Bernd Helfer', 'quelle' => 'mensch', 'text' => 'Elektriker <kommt>', 'status_neu' => 2, 'alt_label' => 'Gemeldet', 'neu_label' => 'In Arbeit', 'neu_color' => '#b45309'],
];
$html = render_partial('unterkunft_punkt', ['punkt' => $zeile, 'standorte' => $plaetze, 'verlauf' => $verlaufZeilen, 'verwalten' => true, 'darfAendern' => true, 'notizDarf' => true]);
$check('Punkt: Kopf und Marken', str_contains($html, 'Wasserhahn &lt;tropft&gt;') && str_contains($html, 'aus Divera') && str_contains($html, 'überfällig'));
$check('Punkt: Statusformular für die Leitung', str_contains($html, 'name="action" value="status"') && str_contains($html, 'Firma beauftragt') && str_contains($html, 'value="loeschen"'));
$check('Punkt: Verlauf maskiert, mit Wechsel', str_contains($html, 'Elektriker &lt;kommt&gt;') && str_contains($html, 'Gemeldet → ') && str_contains($html, '2 Einträge'));
$html = render_partial('unterkunft_punkt', ['punkt' => $frei, 'standorte' => $plaetze, 'verlauf' => [], 'verwalten' => false, 'darfAendern' => false, 'notizDarf' => true]);
$check('Punkt für Mitglieder: nur Notiz', !str_contains($html, 'value="status"') && str_contains($html, 'value="notiz"') && !str_contains($html, 'value="loeschen"') && !str_contains($html, '>Bearbeiten<'));
$check('Punkt: Freitext-Ort', str_contains($html, 'hinter dem Zaun') && str_contains($html, 'kein Platz zugeordnet'));

$html = render_partial('unterkunft_punkt_edit', ['punkt' => ['id' => null, 'titel' => '', 'beschreibung' => '', 'art' => 'mangel', 'standort_id' => 2, 'ort_text' => '',
    'prioritaet_id' => 13, 'zustaendig_id' => null, 'faellig_am' => null, 'firma' => ''], 'standorte' => $plaetze, 'personen' => [], 'errors' => [], 'verwalten' => false]);
$check('Formular: melden, Platz vorbelegt', str_contains($html, '>Melden<') && (bool)preg_match('/<option value="2"[^>]* selected/', $html));
$check('Formular: Mitglieder ohne Zuständigkeit', !str_contains($html, 'name="zustaendig_id"') && !str_contains($html, 'name="faellig_am"'));
$html = render_partial('unterkunft_punkt_edit', ['punkt' => $zeile, 'standorte' => $plaetze, 'personen' => [['id' => 9, 'display_name' => 'Bernd Helfer']],
    'errors' => ['Bitte kurz sagen, worum es geht.'], 'verwalten' => true]);
$check('Formular: Leitung mit Zuständigkeit, Fehler gezeigt', str_contains($html, 'name="zustaendig_id"') && str_contains($html, 'Bernd Helfer') && str_contains($html, 'Bitte kurz sagen') && str_contains($html, '>Speichern<'));

/* ---------- Verdrahtung ---------- */
$lies = static fn(string $rel): string => (string)file_get_contents($app . '/' . $rel);
$index = $lies('public/index.php');
$check('Routen', str_contains($index, "'unterkunft'         => 'unterkunft'") && str_contains($index, "'unterkunft_punkt'") && str_contains($index, "'unterkunft_punkt_edit'"));
$check('Bibliothek geladen', str_contains($lies('src/bootstrap.php'), "/lib/unterkunft.php'"));
$auth = $lies('src/lib/auth.php');
$check('Rechte', str_contains($auth, "'manage_unterkunft' => \$leitung") && str_contains($auth, "setting_bool('unterkunft_user_darf_sehen', true)") && str_contains($auth, "setting_bool('unterkunft_user_darf_melden', true)"));
$check('Menü', str_contains($lies('src/lib/nav.php'), "'unterkunft' => ['label'") && str_contains($lies('src/lib/nav.php'), "can('view_unterkunft')"));
$check('Auswahlliste bekannt', isset(LIST_KEYS['unterkunft_status']));
$schema = $lies('sql/schema.sql');
$migrate = $lies('src/cli/migrate.php');
foreach (['unterkunft_punkte', 'unterkunft_log'] as $tab) {
    $check("Tabelle $tab im Schema und in der Migration", str_contains($schema, "CREATE TABLE IF NOT EXISTS $tab (") && str_contains($migrate, "CREATE TABLE $tab ("));
}
$check('Migration vermerkt', str_contains($migrate, "\$merken('101_unterkunft');"));
$check('Herkunft aus Divera indiziert', substr_count($schema, 'KEY idx_up_divera (divera_form_id, divera_entry_id)') === 1 && str_contains($migrate, 'KEY idx_up_divera (divera_form_id, divera_entry_id)'));
$seed = $lies('sql/seed.sql');
$check('Status mitgeliefert', substr_count($seed, "('unterkunft_status',") === 6 && str_contains($seed, "'in-arbeit'") && str_contains($seed, "'beauftragt'") && str_contains($seed, "'wartet'"));
$check('Einstellungen mitgeliefert', str_contains($seed, "('unterkunft_user_darf_sehen','1'") && str_contains($seed, "('unterkunft_user_darf_melden','1'"));
foreach (UNTERKUNFT_IN_ARBEIT as $slug) {
    $check("Status $slug gilt als in Bearbeitung und steht in den Vorgaben", str_contains($seed, "'$slug'"));
}
$seite = $lies('src/pages/unterkunft.php');
$check('Übersicht prüft Rechte je Abschnitt', str_contains($seite, "can('view_unterkunft')") && str_contains($seite, "can('view_expenses')") && str_contains($seite, "can('view_verbrauch')"));
$check('Meldeseite prüft Rechte und Eigentum', str_contains($lies('src/pages/unterkunft_punkt_edit.php'), 'unterkunft_darf_aendern') && str_contains($lies('src/pages/unterkunft_punkt_edit.php'), "can('report_unterkunft')"));
$check('Platzseite verweist auf die Unterkunft', str_contains($lies('views/standorte.php'), "url('unterkunft', ['standort' => \$s['id']])") && str_contains($lies('views/standorte.php'), '+ Mangel hier'));
$check('Verwaltung bietet das Defektformular', str_contains($lies('views/admin/divera.php'), 'value="defekt">Als Defektformular'));
$check('Doku', str_contains($lies('DOCS.md'), '## Unterkunft') && str_contains($lies('README.md'), '### Unterkunft') && str_contains($lies('CHANGELOG.md'), '**Unterkunft:**'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
