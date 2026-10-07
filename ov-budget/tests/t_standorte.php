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

/* ---------- Ansichten ---------- */
$html = render_partial('admin/standorte', ['baum' => $baum, 'alle' => $rows, 'zaehlung' => standort_zaehlung($rows)]);
$check('Baum rendert mit Einrückung, Typen und Knöpfen', str_contains($html, 'Haupthaus') && str_contains($html, 'margin-left:2.8rem') && str_contains($html, '1 Gebäude') && str_contains($html, '2 Räume')
    && str_contains($html, 'parent_id=4') && str_contains($html, 'stillgelegt') && substr_count($html, 'value="hoch"') === 6);
$check('leerer Baum', str_contains(render_partial('admin/standorte', ['baum' => [], 'alle' => [], 'zaehlung' => []]), 'Noch kein Platz'));
$html = render_partial('admin/standort_edit', ['s' => ['id' => null, 'name' => '', 'typ' => 'raum', 'parent_id' => 2, 'kurz' => '', 'notiz' => '', 'is_active' => 1], 'parent' => $rows[1], 'alle' => $rows, 'errors' => [], 'pfad' => 'Haupthaus › 1. Stock', 'kinder' => 0]);
$check('Formular neu: Serie, Eltern vorgewählt, Pfad', str_contains($html, 'name="anzahl"') && str_contains($html, 'value="2" selected') && str_contains($html, 'Haupthaus › 1. Stock') && str_contains($html, 'value="raum" selected'));
$html = render_partial('admin/standort_edit', ['s' => $rows[0], 'parent' => null, 'alle' => $rows, 'errors' => [], 'pfad' => 'Haupthaus', 'kinder' => 1]);
$check('Formular bearbeiten: keine Serie, Löschen gesperrt, sich selbst nicht als Eltern', !str_contains($html, 'name="anzahl"') && str_contains($html, 'Löschen</button>') && str_contains($html, ' disabled>Löschen') && !str_contains($html, '>Haupthaus (Gebäude)<'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
