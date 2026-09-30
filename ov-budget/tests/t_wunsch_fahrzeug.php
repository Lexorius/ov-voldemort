<?php
declare(strict_types=1);
/* Wünsche einem Fahrzeug zuordnen */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'mwst_satz' => '19', 'wunsch_modul_name' => 'Wünsch dir was'];
$GLOBALS['abfragen'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];

function db_all(string $sql, array $p = []): array {
    $GLOBALS['abfragen'][] = [$sql, $p];
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) {
        $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
    }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 90 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 2, 'display_name' => 'Ich', 'username' => 'ich']; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/view.php';
require $app . '/src/lib/order_rights.php';
require $app . '/src/lib/notify.php';
require $app . '/src/lib/uploads.php';
require $app . '/src/lib/wishes.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- Abfrage ---------- */
$GLOBALS['abfragen'] = [];
wish_query(['vehicle_id' => 7]);
[$q, $p] = $GLOBALS['abfragen'][0];
$check('nach Fahrzeug gefiltert', str_contains($q, 'w.vehicle_id = ?') && $p === [7]);
$check('Fahrzeug verbunden', str_contains($q, 'LEFT JOIN vehicles  fz ON fz.id = w.vehicle_id'));
$check('Bezeichnung und Kennzeichen dabei', str_contains($q, 'fz.bezeichnung AS fahrzeug')
    && str_contains($q, 'fz.kennzeichen AS fahrzeug_kennzeichen'));

$GLOBALS['abfragen'] = [];
wish_query([]);
$check('ohne Filter kein Fahrzeugteil', !str_contains($GLOBALS['abfragen'][0][0], 'w.vehicle_id = ?'));

/* ---------- Speichern ---------- */
$_POST = ['bezeichnung' => 'Keilriemen', 'anzahl' => '1', 'netto_einzel' => '49,90',
    'begruendung' => 'Der alte ist rissig.', 'vehicle_id' => '7'];
$GLOBALS['inserts'] = [];
[$id, $fehler] = wish_save_from_post(null, current_user());
$neu = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'wishes'))[0][1];
$check('ohne Fehler gespeichert', $fehler === []);
$check('Fahrzeug gemerkt', ($neu['vehicle_id'] ?? null) === 7);

$_POST['vehicle_id'] = '';
$GLOBALS['inserts'] = [];
wish_save_from_post(null, current_user());
$neu = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'wishes'))[0][1];
$check('ohne Auswahl bleibt es leer', array_key_exists('vehicle_id', $neu) && $neu['vehicle_id'] === null);
$_POST = [];

/* ---------- Formular ---------- */
$_SERVER['REQUEST_URI'] = '/?p=wish_edit';
$wish = ['id' => null, 'bezeichnung' => '', 'beschreibung' => '', 'begruendung' => '', 'anzahl' => 1,
    'einheit_id' => null, 'netto_einzel' => 0, 'netto_gesamt' => 0, 'mwst_satz' => 19, 'fachgruppe_id' => null,
    'kategorie_id' => null, 'dringlichkeit_id' => null, 'status_id' => null, 'budget_id' => null,
    'vehicle_id' => 7, 'nice_to_have' => 0, 'benoetigt_bis' => null, 'lieferant' => '', 'artikelnummer' => '',
    'link' => '', 'antragsteller' => '', 'extra' => null];
$fahrzeuge = [['id' => 7, 'bezeichnung' => 'GKW 1', 'kennzeichen' => 'THW 99020'],
              ['id' => 8, 'bezeichnung' => 'MTW', 'kennzeichen' => '']];
$html = render_partial('wish_edit', ['wish' => $wish, 'errors' => [], 'budgets' => [],
    'anlagen' => [], 'fahrzeuge' => $fahrzeuge]);
$check('Auswahlfeld vorhanden', str_contains($html, 'name="vehicle_id"'));
$check('Fahrzeug mit Kennzeichen', str_contains($html, 'GKW 1 · THW 99020'));
$check('vorbelegtes Fahrzeug gewählt', str_contains($html, 'value="7" selected'));
$check('auch ohne Zuordnung möglich', str_contains($html, 'kein bestimmtes Fahrzeug'));

$wish['vehicle_id'] = null;
$html = render_partial('wish_edit', ['wish' => $wish, 'errors' => [], 'budgets' => [],
    'anlagen' => [], 'fahrzeuge' => []]);
$check('ohne Fahrzeuge bleibt das Feld verborgen', str_contains($html, '<div class="field" hidden>'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
