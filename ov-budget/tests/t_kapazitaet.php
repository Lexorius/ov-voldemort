<?php
declare(strict_types=1);
/*
 * Transportkapazität: Sitzplätze und Zuladung speichern, Summen, Sortierung,
 * Kapazitätsliste mit Filtern, Werte in Akte und Terminformular.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'fahrzeug_modul_name' => 'Fahrzeuge', 'fahrzeug_extra_felder' => ''];
$GLOBALS['inserts'] = [];
$GLOBALS['abfragen'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    $GLOBALS['abfragen'][] = [$sql, $p];
    return [];
}
function db_row(string $sql, array $p = []): ?array { return $GLOBALS['fahrzeuge'][(int)($p[0] ?? 0)] ?? null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function db_lock(string $n, int $w = 0): bool { return true; }
function db_unlock(string $n): void {}
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'admin', 'display_name' => 'Tester']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'vehicles', 'divera_vehicles'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Speichern ---------- */
$_POST = ['bezeichnung' => 'MTW 1', 'funkrufname' => 'Heros 24/10', 'kennzeichen' => 'THW-1', 'sitzplaetze' => '9', 'zuladung_kg' => '1200', 'is_active' => '1'];
[$id, $fehler] = vehicle_save_from_post(null, ['id' => 1, 'display_name' => 'Tester']);
$d = $GLOBALS['inserts'][0][1] ?? [];
$check('Sitzplätze und Zuladung gespeichert', $fehler === [] && $GLOBALS['inserts'][0][0] === 'vehicles' && $d['sitzplaetze'] === 9 && $d['zuladung_kg'] === 1200);
$_POST = ['bezeichnung' => 'Anhänger', 'is_active' => '1'];
$GLOBALS['inserts'] = [];
vehicle_save_from_post(null, ['id' => 1, 'display_name' => 'Tester']);
$d = $GLOBALS['inserts'][0][1];
$check('ohne Angabe bleibt leer', $d['sitzplaetze'] === null && $d['zuladung_kg'] === null);
$check('Felder im Journal benannt', FZ_JOURNAL_FELDER['sitzplaetze'] === 'Sitzplätze' && FZ_JOURNAL_FELDER['zuladung_kg'] === 'Zuladung (kg)');
$_POST = [];

/* ---------- Summen, Sortierung, Text ---------- */
$rows = [
    ['id' => 1, 'bezeichnung' => 'MTW 1', 'sitzplaetze' => 9, 'zuladung_kg' => 1200, 'is_active' => 1],
    ['id' => 2, 'bezeichnung' => 'GKW 1', 'sitzplaetze' => 6, 'zuladung_kg' => 3500, 'is_active' => 1],
    ['id' => 3, 'bezeichnung' => 'Anhänger', 'sitzplaetze' => null, 'zuladung_kg' => 2000, 'is_active' => 1],
    ['id' => 4, 'bezeichnung' => 'Boot', 'sitzplaetze' => null, 'zuladung_kg' => null, 'is_active' => 1],
];
$s = vehicle_kapazitaet_summe($rows);
$check('Summen mit Zählung der Lücken', $s === ['fahrzeuge' => 4, 'personen' => 15, 'zuladung' => 6700, 'ohne_personen' => 2, 'ohne_zuladung' => 1]);
$check('leere Summe', vehicle_kapazitaet_summe([])['personen'] === 0);
$check('Sortierung nach Sitzplätzen, ohne Angabe zuletzt', array_column(vehicle_kapazitaet_sortieren($rows, 'personen'), 'bezeichnung') === ['MTW 1', 'GKW 1', 'Anhänger', 'Boot']);
$check('Sortierung nach Zuladung', array_column(vehicle_kapazitaet_sortieren($rows, 'zuladung'), 'bezeichnung') === ['GKW 1', 'Anhänger', 'MTW 1', 'Boot']);
$check('Sortierung nach Name', array_column(vehicle_kapazitaet_sortieren($rows, 'name'), 'bezeichnung') === ['Anhänger', 'Boot', 'GKW 1', 'MTW 1']);
$check('Kapazitätstext', vehicle_kapazitaet_text($rows[0]) === '9 Pers. · 1.200 kg' && vehicle_kapazitaet_text($rows[2]) === '2.000 kg' && vehicle_kapazitaet_text($rows[3]) === '');

/* ---------- Abfrage mit Mindestwerten ---------- */
$GLOBALS['abfragen'] = [];
vehicle_query(['min_sitzplaetze' => 9, 'min_zuladung' => 1000, 'nur_aktive' => 1]);
[$sql, $p] = $GLOBALS['abfragen'][0];
$check('Filter: mindestens Sitzplätze und Zuladung', str_contains($sql, 'v.sitzplaetze >= ?') && str_contains($sql, 'v.zuladung_kg >= ?') && array_slice($p, -2) === [9, 1000]);

/* ---------- Ansichten ---------- */
$voll = static fn(array $v) => $v + ['funkrufname' => '', 'kennzeichen' => '', 'typ_label' => 'MTW', 'fachgruppe_label' => 'Bergung', 'status_label' => 'Einsatzbereit', 'status_color' => '#15803d', 'stellplatz_pfad' => 'Halle 1 › Stellplatz 3'];
$html = render_partial('vehicle_kapazitaet', ['rows' => array_map($voll, $rows), 'summe' => $s, 'filter' => ['q' => '', 'typ_id' => 0, 'fachgruppe_id' => 0, 'status_id' => 0, 'min_personen' => 9, 'min_zuladung' => 0, 'alle' => false], 'sort' => 'personen']);
$check('Kapazitätsliste: Werte, Summen, Filter, Hinweis auf Lücken', str_contains($html, 'Kapazitäten') && str_contains($html, '<strong>9</strong>') && str_contains($html, '3.500 kg') && str_contains($html, '>15<')
    && str_contains($html, '6.700 kg') && str_contains($html, '2 Fahrzeug(e) ohne Angabe') && str_contains($html, 'name="min_personen"') && str_contains($html, 'value="9"') && str_contains($html, 'Halle 1 › Stellplatz 3')
    && str_contains($html, 'p=vehicle&amp;id=2') && str_contains($html, 'ohne Angabe zählen in den Summen nicht'));
$html = render_partial('vehicle_kapazitaet', ['rows' => [], 'summe' => vehicle_kapazitaet_summe([]), 'filter' => ['q' => 'x', 'typ_id' => 0, 'fachgruppe_id' => 0, 'status_id' => 0, 'min_personen' => 0, 'min_zuladung' => 0, 'alle' => true], 'sort' => 'name']);
$check('Kapazitätsliste leer', str_contains($html, 'Kein Fahrzeug passt') && str_contains($html, 'name="alle" value="1" checked'));
$html = render_partial('vehicle_edit', ['vehicle' => ['id' => null, 'bezeichnung' => '', 'funkrufname' => '', 'issi' => '', 'opta' => '', 'ric' => '', 'kennzeichen' => '', 'kennung' => '', 'typ_id' => null, 'fachgruppe_id' => null,
    'status_id' => null, 'hersteller' => '', 'modell' => '', 'baujahr' => '', 'fahrgestellnummer' => '', 'erstzulassung' => null, 'km_stand' => null, 'betriebsstunden' => null, 'sitzplaetze' => 9, 'zuladung_kg' => null,
    'hu_bis' => null, 'sp_bis' => null, 'uvv_bis' => null, 'standort' => '', 'standort_id' => null, 'notiz' => '', 'is_active' => 1, 'ausgemustert_am' => null, 'extra' => null], 'errors' => [], 'felder' => [], 'extra' => [], 'standorte' => []]);
$check('Formular: Felder für Sitzplätze und Zuladung', str_contains($html, 'name="sitzplaetze"') && str_contains($html, 'value="9"') && str_contains($html, 'name="zuladung_kg"'));
$check('Route und Knopf', str_contains((string)file_get_contents($app . '/public/index.php'), "'vehicle_kapazitaet'") && str_contains((string)file_get_contents($app . '/views/vehicles.php'), "url('vehicle_kapazitaet')")
    && str_contains((string)file_get_contents($app . '/src/cli/migrate.php'), '066_kapazitaet'));

/* ---------- Trägerfahrzeug ---------- */
$GLOBALS['fahrzeuge'] = [2 => ['id' => 2, 'traeger_id' => null, 'bezeichnung' => 'FwA Boot'], 3 => ['id' => 3, 'traeger_id' => 2, 'bezeichnung' => 'MzB 1'], 4 => ['id' => 4, 'traeger_id' => 3, 'bezeichnung' => 'Kreis'], 5 => ['id' => 5, 'traeger_id' => 4, 'bezeichnung' => 'Kreis 2']];
$check('Träger prüfen: anderes vorhandenes Fahrzeug', vehicle_traeger_pruefen(2, 3) === 2 && vehicle_traeger_pruefen(null, 3) === null && vehicle_traeger_pruefen(3, 3) === null && vehicle_traeger_pruefen(99, 3) === null);
$check('Träger prüfen: kein Kreis', vehicle_traeger_pruefen(5, 2) === null && vehicle_traeger_pruefen(2, 0) === 2);
$zeilen = [3 => ['bezeichnung' => 'MzB 1', 'traeger_id' => 2, 'traeger_bezeichnung' => 'FwA Boot'], 2 => ['bezeichnung' => 'FwA Boot', 'traeger_id' => 7, 'traeger_bezeichnung' => 'GKW 1'], 7 => ['bezeichnung' => 'GKW 1', 'traeger_id' => null]];
[$alle, $erg] = vehicle_traeger_ergaenzen([3, 3, 'x'], $zeilen);
$check('Boot zieht Anhänger und Zugfahrzeug nach', $alle === [3, 2, 7] && count($erg) === 2 && $erg[0] === ['id' => 2, 'bezeichnung' => 'FwA Boot', 'wegen' => 'MzB 1'] && $erg[1]['id'] === 7);
[$alle, $erg] = vehicle_traeger_ergaenzen([3, 2], $zeilen);
$check('schon gewählter Träger wird nicht doppelt ergänzt', $alle === [3, 2, 7] && count($erg) === 1 && $erg[0]['id'] === 7);
$check('Trägertext', vehicle_traeger_text(['traeger_bezeichnung' => 'FwA Boot', 'traeger_kennzeichen' => 'HN-TH 123']) === 'auf FwA Boot · HN-TH 123' && vehicle_traeger_text(['traeger_bezeichnung' => 'GKW 1', 'traeger_kennzeichen' => '']) === 'auf GKW 1' && vehicle_traeger_text([]) === '');
$mit = vehicle_mit_stellplatz_pfad([['id' => 3, 'standort_id' => null, 'stellplatz_name' => null, 'traeger_standort_id' => 5, 'traeger_stellplatz_name' => 'Stellplatz 3', 'traeger_bezeichnung' => 'FwA Boot'], ['id' => 2, 'standort_id' => 5, 'stellplatz_name' => 'Stellplatz 3']], []);
$check('Boot erbt den Stellplatz des Anhängers', $mit[0]['stellplatz_pfad'] === 'Stellplatz 3 (über FwA Boot)' && $mit[0]['standort_id'] === 5 && $mit[1]['stellplatz_pfad'] === 'Stellplatz 3');
$_POST = ['bezeichnung' => 'MzB 1', 'is_active' => '1', 'traeger_id' => '2'];
$GLOBALS['inserts'] = [];
vehicle_save_from_post(null, ['id' => 1, 'display_name' => 'Tester']);
$check('Träger gespeichert', $GLOBALS['inserts'][0][1]['traeger_id'] === 2);
$_POST = [];
$GLOBALS['abfragen'] = [];
vehicle_query(['q' => 'HN-TH']);
$check('Suche findet das Kennzeichen des Trägers', str_contains($GLOBALS['abfragen'][0][0], 'tr.kennzeichen LIKE ?') && str_contains($GLOBALS['abfragen'][0][0], 'LEFT JOIN vehicles tr ON tr.id = v.traeger_id') && str_contains($GLOBALS['abfragen'][0][0], 'AS ladung_namen'));
$html = render_partial('partials/fahrzeug_kachel', ['v' => ['id' => 3, 'bezeichnung' => 'MzB 1', 'funkrufname' => '', 'kennzeichen' => '', 'is_active' => 1, 'traeger_bezeichnung' => 'FwA Boot', 'traeger_kennzeichen' => 'HN-TH 123', 'status_label' => '', 'status_color' => '', 'offene_auftraege' => 0], 'titelbilder' => [], 'fristen' => [], 'mitPlatz' => false]);
$check('Kachel: Kennzeichen des Anhängers beim Boot', str_contains($html, 'auf FwA Boot · HN-TH 123'));
$html = render_partial('vehicle_edit', ['vehicle' => ['id' => 3, 'bezeichnung' => 'MzB 1', 'funkrufname' => '', 'issi' => '', 'opta' => '', 'ric' => '', 'kennzeichen' => '', 'kennung' => '', 'typ_id' => null, 'fachgruppe_id' => null,
    'status_id' => null, 'hersteller' => '', 'modell' => '', 'baujahr' => '', 'fahrgestellnummer' => '', 'erstzulassung' => null, 'km_stand' => null, 'betriebsstunden' => null, 'sitzplaetze' => null, 'zuladung_kg' => null, 'traeger_id' => 2,
    'hu_bis' => null, 'sp_bis' => null, 'uvv_bis' => null, 'standort' => '', 'standort_id' => null, 'notiz' => '', 'is_active' => 1, 'ausgemustert_am' => null, 'extra' => null], 'errors' => [], 'felder' => [], 'extra' => [], 'standorte' => [],
    'traeger' => [['id' => 2, 'bezeichnung' => 'FwA Boot', 'kennzeichen' => 'HN-TH 123', 'typ_label' => 'Anhänger'], ['id' => 7, 'bezeichnung' => 'GKW 1', 'kennzeichen' => 'THW-1', 'typ_label' => 'GKW']]]);
$check('Formular: Träger wählbar, Anhänger vorgewählt', str_contains($html, 'name="traeger_id"') && str_contains($html, 'value="2" selected>FwA Boot · HN-TH 123 (Anhänger)') && str_contains($html, 'fährt selbst'));
$check('Seed kennt die Art Boot, Wanderung 070', str_contains((string)file_get_contents($app . '/sql/seed.sql'), "('fahrzeug_typ','Boot'") && str_contains((string)file_get_contents($app . '/src/cli/migrate.php'), '070_traeger'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
