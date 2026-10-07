<?php
declare(strict_types=1);
/*
 * Stell- und Lagerplätze: Typregeln, Baum, Pfad, Serie, Verschieben,
 * Löschen nur ohne Kinder, Ansichten.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['rows'] = [];
$GLOBALS['kinder'] = 0;
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    if (str_contains($sql, 'SELECT id FROM standorte WHERE')) { return array_values(array_filter($GLOBALS['rows'], static fn($r) => (int)($r['parent_id'] ?? 0) === (int)($p[0] ?? 0))); }
    return $GLOBALS['rows'];
}
function db_row(string $sql, array $p = []): ?array { foreach ($GLOBALS['rows'] as $r) { if ((int)$r['id'] === (int)$p[0]) { return $r; } } return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'COUNT(*) FROM standorte WHERE parent_id') ? $GLOBALS['kinder'] : (str_contains($sql, 'MAX(sort_order)') ? 40 : $d); }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 100 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'admin']; }
$app = dirname(__DIR__);
function dv_map_url(float $lat, float $lng): string { return 'https://osm/' . $lat . '/' . $lng; }
function dv_map_embed_url(float $lat, float $lng, float $s = 0.008): string { return 'https://osm/embed'; }
function upload_max_bytes(): int { return 8 * 1048576; }
foreach (['util', 'settings', 'view', 'standorte'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$u = ['id' => 1];

/* ---------- Typregeln ---------- */
$check('Gebäude, Halle und Hof oben; Halle auch im Gebäude', standort_passt('gebaeude', null) && standort_passt('hof', null) && standort_passt('halle', null) && standort_passt('halle', 'gebaeude') && !standort_passt('gebaeude', 'halle'));
$check('Stockwerk im Gebäude, Raum im Stockwerk, Stellplatz in der Halle', standort_passt('stockwerk', 'gebaeude') && standort_passt('raum', 'stockwerk') && standort_passt('stellplatz', 'halle') && !standort_passt('stellplatz', 'hof'));
$check('Hofstellplatz nur auf dem Hof, Regal im Schrank', standort_passt('hofstellplatz', 'hof') && !standort_passt('hofstellplatz', 'halle') && standort_passt('regal', 'schrank') && standort_passt('schrank', 'raum') && !standort_passt('raum', null));
$check('mögliche Kindtypen', standort_kindtypen(null) === ['gebaeude', 'halle', 'hof'] && standort_kindtypen('hof') === ['hofstellplatz'] && in_array('regal', standort_kindtypen('schrank'), true));
$check('unbekannter Typ wird Raum', standort_typ('garage') === 'raum');

/* ---------- Baum, Pfad, Nachkommen ---------- */
$rows = [
    ['id' => 1, 'parent_id' => null, 'typ' => 'gebaeude', 'name' => 'Haupthaus', 'sort_order' => 10, 'is_active' => 1, 'kurz' => 'HH'],
    ['id' => 2, 'parent_id' => 1, 'typ' => 'stockwerk', 'name' => '1. Stock', 'sort_order' => 10, 'is_active' => 1, 'kurz' => ''],
    ['id' => 3, 'parent_id' => 2, 'typ' => 'raum', 'name' => 'Raum 12', 'sort_order' => 10, 'is_active' => 1, 'kurz' => ''],
    ['id' => 4, 'parent_id' => null, 'typ' => 'halle', 'name' => 'Halle 1', 'sort_order' => 20, 'is_active' => 1, 'kurz' => ''],
    ['id' => 5, 'parent_id' => 4, 'typ' => 'stellplatz', 'name' => 'Stellplatz 3', 'sort_order' => 10, 'is_active' => 0, 'kurz' => ''],
    ['id' => 6, 'parent_id' => 99, 'typ' => 'raum', 'name' => 'Waise', 'sort_order' => 30, 'is_active' => 1, 'kurz' => ''],
];
$baum = standort_baum($rows);
$check('Baum: drei Wurzeln (Waise oben), Kinder mit Tiefe', count($baum) === 3 && $baum[0]['name'] === 'Haupthaus' && $baum[0]['kinder'][0]['name'] === '1. Stock'
    && $baum[0]['kinder'][0]['kinder'][0]['tiefe'] === 2 && $baum[2]['name'] === 'Waise');
$check('flache Lesereihenfolge', array_column(standort_flach($baum), 'name') === ['Haupthaus', '1. Stock', 'Raum 12', 'Halle 1', 'Stellplatz 3', 'Waise']);
$check('Pfad', standort_pfad(3, $rows) === 'Haupthaus › 1. Stock › Raum 12' && standort_pfad(4, $rows) === 'Halle 1' && standort_pfad(42, $rows) === '');
$check('Nachkommen', standort_nachkommen(1, $rows) === [2, 3] && standort_nachkommen(3, $rows) === []);
$opt = standort_optionen($rows, 3, '– keiner –', [4]);
$check('Optionen eingerückt, Ausgeschlossene fehlen', str_contains($opt, '>— — Raum 12 (Raum)<') && str_contains($opt, 'value="3" selected') && !str_contains($opt, 'Halle 1'));
$check('Zählung je Typ', standort_zaehlung($rows) === ['gebaeude' => 1, 'stockwerk' => 1, 'raum' => 2, 'halle' => 1, 'stellplatz' => 1]);

/* ---------- Speichern ---------- */
$GLOBALS['rows'] = $rows;
$_POST = ['name' => 'Raum', 'typ' => 'raum', 'parent_id' => '2', 'kurz' => 'R', 'anzahl' => '3', 'start' => '14', 'is_active' => '1'];
[$ids, $fehler] = standort_save_from_post(null, $u);
$check('Serie: drei Räume mit Nummer und Kurzzeichen', $fehler === [] && count($ids) === 3 && $GLOBALS['inserts'][0][1]['name'] === 'Raum 14' && $GLOBALS['inserts'][2][1]['name'] === 'Raum 16'
    && $GLOBALS['inserts'][0][1]['kurz'] === 'R14' && $GLOBALS['inserts'][0][1]['parent_id'] === 2 && $GLOBALS['inserts'][0][1]['sort_order'] === 40 && $GLOBALS['inserts'][1][1]['sort_order'] === 50);
$GLOBALS['inserts'] = [];
$_POST = ['name' => 'Haupthaus', 'typ' => 'gebaeude', 'parent_id' => '', 'anzahl' => '1', 'is_active' => '1'];
[$ids, $fehler] = standort_save_from_post(null, $u);
$check('einzeln ganz oben, Name ohne Nummer', $fehler === [] && count($ids) === 1 && $GLOBALS['inserts'][0][1]['name'] === 'Haupthaus' && $GLOBALS['inserts'][0][1]['parent_id'] === null);
$_POST = ['name' => 'Stellplatz', 'typ' => 'stellplatz', 'parent_id' => '1', 'anzahl' => '2'];
[$ids, $fehler] = standort_save_from_post(null, $u);
$check('Regel verletzt: Stellplatz unter Gebäude ist erlaubt, unter Hof nicht', $fehler === []);
$_POST = ['name' => 'Platz', 'typ' => 'hofstellplatz', 'parent_id' => '4'];
[$ids, $fehler] = standort_save_from_post(null, $u);
$check('Hofstellplatz unter Halle abgelehnt', $ids === [] && str_contains($fehler[0], 'nur unter Hof'));
$_POST = ['name' => 'Gebäude 2', 'typ' => 'gebaeude', 'parent_id' => '1'];
[$ids, $fehler] = standort_save_from_post(null, $u);
$check('Gebäude unter Gebäude abgelehnt', $ids === [] && str_contains($fehler[0], 'ganz oben'));
$_POST = ['name' => '', 'typ' => 'raum', 'parent_id' => '2'];
[$ids, $fehler] = standort_save_from_post(null, $u);
$check('ohne Namen Fehler', $ids === [] && str_contains($fehler[0], 'Namen'));
$_POST = ['name' => 'Haupthaus', 'typ' => 'gebaeude', 'parent_id' => '3'];
[$ids, $fehler] = standort_save_from_post($rows[0], $u);
$check('nicht unter den eigenen Unterplatz', $ids === [] && (str_contains(implode(' ', $fehler), 'unter sich selbst') || str_contains(implode(' ', $fehler), 'ganz oben')));
$GLOBALS['updates'] = [];
$_POST = ['name' => 'Haupthaus neu', 'typ' => 'gebaeude', 'parent_id' => '', 'kurz' => 'HH', 'is_active' => '1', 'anzahl' => '5'];
[$ids, $fehler] = standort_save_from_post($rows[0], $u);
$check('Ändern: einzeln, Anzahl ignoriert', $fehler === [] && $ids === [1] && $GLOBALS['updates'][0][1]['name'] === 'Haupthaus neu');
$_POST = ['name' => 'X', 'typ' => 'raum', 'parent_id' => '2', 'anzahl' => '999'];
$GLOBALS['inserts'] = [];
[$ids] = standort_save_from_post(null, $u);
$check('Serie gedeckelt', count($ids) === STANDORT_MAX_SERIE);

/* ---------- Verschieben, Löschen ---------- */
$geschwister = [['id' => 1], ['id' => 4], ['id' => 6]];
$check('verschieben', standort_verschieben($geschwister, 4, 'hoch') === [4, 1, 6] && standort_verschieben($geschwister, 1, 'hoch') === [1, 4, 6] && standort_verschieben($geschwister, 6, 'runter') === [1, 4, 6] && standort_verschieben($geschwister, 9, 'hoch') === [1, 4, 6]);
$GLOBALS['updates'] = [];
standort_move($rows[3], 'hoch');
$check('Verschieben schreibt neue Reihenfolge', count($GLOBALS['updates']) === 2 && $GLOBALS['updates'][0][2] === [4] && $GLOBALS['updates'][0][1]['sort_order'] === 10);
$GLOBALS['kinder'] = 2;
$check('Löschen mit Kindern abgelehnt', str_contains((string)standort_delete($rows[0]), '2 Unterplatz'));
$GLOBALS['kinder'] = 0;
$GLOBALS['execs'] = [];
$check('Löschen ohne Kinder', standort_delete($rows[2]) === null && str_contains($GLOBALS['execs'][0][0], 'DELETE FROM standorte'));

/* ---------- Lage ---------- */
$check('Koordinaten mit Punkt und Komma', standort_koordinaten_parsen('49.142345, 9.218765') === [49.142345, 9.218765] && standort_koordinaten_parsen('49,142345; 9,218765') === [49.142345, 9.218765]
    && standort_koordinaten_parsen('49.1 9.2') === [49.1, 9.2]);
$check('unbrauchbare Koordinaten', standort_koordinaten_parsen('') === null && standort_koordinaten_parsen('Heilbronn') === null && standort_koordinaten_parsen('95, 9') === null
    && standort_koordinaten_parsen('0, 0') === null && standort_koordinaten_parsen('49.1') === null);
$check('Koordinaten lesbar', standort_koordinaten_text(49.142345, 9.218765) === '49,14235 / 9,21877' && standort_koordinaten_text(null, null) === '');
$GLOBALS['updates'] = [];
$check('Position vom Gerät', standort_position_setzen($rows[0], 49.142345678, 9.2, 12.4, 'geraet', $u) === null && $GLOBALS['updates'][0][1]['geo_lat'] === 49.142346
    && $GLOBALS['updates'][0][1]['geo_genauigkeit'] === 12.4 && $GLOBALS['updates'][0][1]['geo_quelle'] === 'geraet');
$check('Position außerhalb abgelehnt', standort_position_setzen($rows[0], 95.0, 9.2, null, 'geraet', $u) !== null);
$GLOBALS['updates'] = [];
standort_position_setzen($rows[0], null, null, null, 'mensch', $u);
$check('Position entfernt', $GLOBALS['updates'][0][1]['geo_lat'] === null && $GLOBALS['updates'][0][1]['geo_quelle'] === null);
$GLOBALS['updates'] = [];
$_POST = ['name' => 'Halle 1', 'typ' => 'halle', 'parent_id' => '', 'koordinaten' => '49.14, 9.22', 'is_active' => '1'];
[$ids, $fehler] = standort_save_from_post($rows[3] + ['geo_lat' => null, 'geo_lng' => null], $u);
$check('Koordinaten im Formular gespeichert', $fehler === [] && $GLOBALS['updates'][0][1]['geo_lat'] === 49.14 && $GLOBALS['updates'][0][1]['geo_quelle'] === 'mensch');
$GLOBALS['updates'] = [];
$_POST['koordinaten'] = '49.14, 9.22';
[$ids, $fehler] = standort_save_from_post($rows[3] + ['geo_lat' => '49.140000', 'geo_lng' => '9.220000', 'geo_quelle' => 'geraet'], $u);
$check('unveränderte Koordinaten lassen Quelle und Zeit in Ruhe', $fehler === [] && !array_key_exists('geo_lat', $GLOBALS['updates'][0][1]));
$_POST['koordinaten'] = 'irgendwo';
[$ids, $fehler] = standort_save_from_post($rows[3], $u);
$check('unlesbare Koordinaten: Fehler', $ids === [] && str_contains($fehler[0], 'Koordinaten'));

/* ---------- Bilder (ohne Dateien) ---------- */
// Achtung: $GLOBALS['rows'] ist hier dieselbe Variable wie $rows – deshalb vorher sichern
$standortZeilen = $rows;
$GLOBALS['rows'] = [['id' => 7, 'standort_id' => 1, 'is_cover' => 0, 'titel' => 'Tür'], ['id' => 8, 'standort_id' => 1, 'is_cover' => 1, 'titel' => 'Regal'], ['id' => 9, 'standort_id' => 2, 'is_cover' => 0, 'titel' => 'Flur']];
$tb = standort_titelbilder([1, 2, 3]);
$check('Titelbild je Platz: markiertes zuerst, sonst das erste', $tb[1]['id'] === 8 && $tb[2]['id'] === 9 && !isset($tb[3]) && standort_titelbilder([]) === []);
$GLOBALS['execs'] = [];
standort_bild_cover_setzen(1, 7);
$check('Titelbild setzen', str_contains($GLOBALS['execs'][0][0], 'is_cover = CASE') && $GLOBALS['execs'][0][1] === [7, 1]);
$GLOBALS['rows'] = $standortZeilen;
$rows = $standortZeilen;

/* ---------- Ansichten ---------- */
$html = render_partial('admin/standorte', ['baum' => $baum, 'alle' => $rows, 'zaehlung' => standort_zaehlung($rows)]);
$check('Baum rendert mit Einrückung, Typen und Knöpfen', str_contains($html, 'Haupthaus') && str_contains($html, 'margin-left:2.8rem') && str_contains($html, '1 Gebäude') && str_contains($html, '2 Räume')
    && str_contains($html, 'parent_id=4') && str_contains($html, 'stillgelegt') && substr_count($html, 'value="hoch"') === 6);
$check('leerer Baum', str_contains(render_partial('admin/standorte', ['baum' => [], 'alle' => [], 'zaehlung' => []]), 'Noch kein Platz'));
$html = render_partial('admin/standort_edit', ['s' => ['id' => null, 'name' => '', 'typ' => 'raum', 'parent_id' => 2, 'kurz' => '', 'notiz' => '', 'is_active' => 1], 'parent' => $rows[1], 'alle' => $rows, 'errors' => [], 'pfad' => 'Haupthaus › 1. Stock', 'kinder' => 0]);
$check('Formular neu: Serie, Eltern vorgewählt, Pfad', str_contains($html, 'name="anzahl"') && str_contains($html, 'value="2" selected') && str_contains($html, 'Haupthaus › 1. Stock') && str_contains($html, 'value="raum" selected'));
$html = render_partial('admin/standort_edit', ['s' => $rows[0] + ['geo_lat' => '49.142345', 'geo_lng' => '9.218765', 'geo_quelle' => 'geraet', 'geo_at' => '2026-10-08 10:00:00', 'geo_genauigkeit' => '12.4'], 'parent' => null, 'alle' => $rows, 'errors' => [], 'pfad' => 'Haupthaus', 'kinder' => 1,
    'bilder' => [['id' => 8, 'standort_id' => 1, 'is_cover' => 1, 'titel' => 'Regal'], ['id' => 7, 'standort_id' => 1, 'is_cover' => 0, 'titel' => 'Tür']]]);
$check('Platzseite: Karte, Koordinaten, Position setzen, Galerie mit Titelbild', str_contains($html, 'class="karte"') && str_contains($html, '49,14235 / 9,21877') && str_contains($html, 'data-position')
    && str_contains($html, 'value="49.142345, 9.218765"') && str_contains($html, 'Genauigkeit etwa 12 m') && substr_count($html, 'galerie__bild') >= 2 && str_contains($html, 'galerie__bild--titel')
    && substr_count($html, 'value="bild_cover"') === 1 && str_contains($html, 'name="bilder[]"'));
$html = render_partial('admin/standorte', ['baum' => $baum, 'alle' => $rows, 'zaehlung' => standort_zaehlung($rows), 'titelbilder' => [1 => ['id' => 8]]]);
$check('Baum mit Vorschaubild', str_contains($html, 'standort__thumb') && str_contains($html, 'p=standort_bild'));
$html = render_partial('admin/standort_edit', ['s' => $rows[0], 'parent' => null, 'alle' => $rows, 'errors' => [], 'pfad' => 'Haupthaus', 'kinder' => 1]);
$check('Formular bearbeiten: keine Serie, Löschen gesperrt, sich selbst nicht als Eltern', !str_contains($html, 'name="anzahl"') && str_contains($html, 'Löschen</button>') && str_contains($html, ' disabled>Löschen') && !str_contains($html, '>Haupthaus (Gebäude)<'));

/* ---------- Fahrzeuge: fester Stellplatz ---------- */
$fz = (string)file_get_contents($app . '/src/lib/vehicles.php');
$check('Fahrzeugabfragen lesen den Stellplatz mit', substr_count($fz, 'LEFT JOIN standorte sp ON sp.id = v.standort_id') >= 2 && str_contains($fz, "'standort_id'       => vehicle_stellplatz_pruefen(post_int('standort_id'))"));
require $app . '/src/lib/vehicles.php';
$GLOBALS['rows'] = $rows;
$check('nur aktive Plätze werden Stellplatz', vehicle_stellplatz_pruefen(1) === 1 && vehicle_stellplatz_pruefen(5) === null && vehicle_stellplatz_pruefen(42) === null && vehicle_stellplatz_pruefen(null) === null);
$html = render_partial('admin/standort_edit', ['s' => $rows[3], 'parent' => null, 'alle' => $rows, 'errors' => [], 'pfad' => 'Halle 1', 'kinder' => 1, 'bilder' => [],
    'fahrzeuge' => [['id' => 2, 'bezeichnung' => 'GKW 1', 'funkrufname' => 'Heros HN 21/51', 'kennzeichen' => 'THW-1234', 'is_active' => 1]]]);
$check('Platzseite zeigt Fahrzeuge', str_contains($html, 'Fahrzeuge mit diesem Stellplatz') && str_contains($html, 'GKW 1') && str_contains($html, 'p=vehicle'));
const METER_ARTEN = ['strom' => ['label' => 'Strom'], 'gas' => ['label' => 'Gas'], 'wasser' => ['label' => 'Wasser']];
$html = render_partial('admin/standort_edit', ['s' => $rows[1], 'parent' => $rows[0], 'alle' => $rows, 'errors' => [], 'pfad' => 'Haupthaus › 1. Stock', 'kinder' => 1, 'bilder' => [], 'fahrzeuge' => [],
    'zaehler' => [['id' => 3, 'name' => 'Strom 1. OG', 'art' => 'strom', 'rolle' => 'unter', 'is_active' => 1]]]);
$check('Platzseite zeigt Zähler', str_contains($html, 'Zähler an diesem Platz') && str_contains($html, 'Strom 1. OG') && str_contains($html, 'Unterzähler') && str_contains($html, 'p=meter'));
$vb = (string)file_get_contents($app . '/src/lib/verbrauch.php');
$check('Zählerabfrage liest den Platz mit', str_contains($vb, 'LEFT JOIN standorte sp ON sp.id = m.standort_id') && str_contains($vb, "'standort_id'   => function_exists('vehicle_stellplatz_pruefen')"));

echo "$ok bestanden, $fail fehlgeschlagen\n";
