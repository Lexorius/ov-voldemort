<?php
declare(strict_types=1);
/* Fahrzeugliste: Kacheln, Liste, Stern zum Anheften */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'fahrzeug_modul_name' => 'Fahrzeuge', 'fahrzeug_intro' => ''];

function db_all(string $sql, array $p = []): array {
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
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 3]; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/view.php';
require $app . '/src/lib/vehicles.php';
require $app . '/src/lib/divera_vehicles.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

$fahrzeug = static fn(array $extra = []) => $extra + [
    'id' => 4, 'bezeichnung' => 'GKW 1', 'funkrufname' => 'Heros 24/51', 'kennzeichen' => 'THW 99020',
    'issi' => '', 'typ_label' => 'GKW', 'fachgruppe_label' => 'B1', 'status_label' => 'Einsatzbereit',
    'status_color' => '#15803d', 'status_slug' => 'einsatzbereit', 'fms_status' => 2, 'fms_at' => null,
    'offene_auftraege' => 1, 'stein_asset_id' => '', 'is_active' => 1, 'favorit' => 0,
];
$_SERVER['REQUEST_URI'] = '/?p=vehicles';
$_GET = ['p' => 'vehicles', 'sort' => 'name'];

$daten = [
    'fahrzeuge' => [$fahrzeug()], 'fristen' => [4 => []], 'auffaellig' => 0,
    'filter' => ['q' => '', 'status_id' => null, 'typ_id' => null, 'fachgruppe_id' => null],
    'alle' => false, 'auftraege' => [], 'steinAktiv' => false, 'steinStand' => 0,
    'titelbilder' => [], 'sort' => 'name', 'nurFavoriten' => false, 'favoriten' => 2,
];

/* ---------- Listenansicht ---------- */
$html = render_partial('vehicles', $daten + ['ansicht' => 'liste']);
$check('Liste zeigt die Einträge', str_contains($html, 'GKW 1') && str_contains($html, 'class="itemlist"'));
$check('Stern zum Anheften', str_contains($html, 'name="action" value="favorit"') && str_contains($html, '☆'));
$check('Stern liegt neben dem Eintrag, nicht im Link', str_contains($html, 'class="item-mit-stern"'));
$check('Sortierung zur Auswahl', str_contains($html, 'name="sort"')
    && str_contains($html, 'value="frist"') && str_contains($html, 'value="name" selected'));
$check('Umschalter auf Kacheln', str_contains($html, 'ansicht=kacheln'));
$check('Liste ist hervorgehoben', str_contains($html, 'btn--sm is-active'));
$check('Filter für Angeheftete mit Zahl', str_contains($html, 'name="favoriten"') && str_contains($html, '(2)'));
$check('keine Kacheln', !str_contains($html, 'class="kacheln"'));

/* ---------- Kachelansicht ---------- */
$html = render_partial('vehicles', $daten + ['ansicht' => 'kacheln']);
$check('Kacheln', str_contains($html, 'class="kacheln"') && str_contains($html, 'class="kachel'));
$check('ohne Bild ein Platzhalter', str_contains($html, 'kachel__leer'));
$check('Stern auch in der Kachel', str_contains($html, 'kachel__stern'));
$check('Status als Plakette', str_contains($html, 'Einsatzbereit') && str_contains($html, 'S2'));
$check('keine Listeneinträge', !str_contains($html, 'class="item-mit-stern"'));

$mitBild = $daten;
$mitBild['titelbilder'] = [4 => ['id' => 11]];
$html = render_partial('vehicles', $mitBild + ['ansicht' => 'kacheln']);
$check('Titelbild in der Kachel', str_contains($html, 'vehicle_file') && str_contains($html, 'vorschau=1'));

/* ---------- Angeheftetes Fahrzeug ---------- */
$angeheftet = $daten;
$angeheftet['fahrzeuge'] = [$fahrzeug(['favorit' => 1])];
$html = render_partial('vehicles', $angeheftet + ['ansicht' => 'liste']);
$check('gefüllter Stern', str_contains($html, '★') && str_contains($html, 'stern--an'));
$check('Titel zum Lösen', str_contains($html, 'Nicht mehr anheften'));

/* ---------- Leere Liste ---------- */
$leer = $daten;
$leer['fahrzeuge'] = [];
$html = render_partial('vehicles', array_merge($leer, ['ansicht' => 'liste', 'nurFavoriten' => true]));
$check('Hinweis bei leerer Merkliste', str_contains($html, 'Noch kein Fahrzeug angeheftet'));
$html = render_partial('vehicles', $leer + ['ansicht' => 'liste']);
$check('sonst der übliche Hinweis', str_contains($html, 'Kein Fahrzeug gefunden'));

/* ---------- Stempel in der Kachel ---------- */
$defekt = $daten;
$defekt['fahrzeuge'] = [$fahrzeug(['status_slug' => 'nicht-einsatzbereit', 'status_label' => 'Nicht einsatzbereit',
    'status_color' => '#b91c1c'])];
$html = render_partial('vehicles', $defekt + ['ansicht' => 'kacheln']);
$check('Stempel auf der Kachel', str_contains($html, 'class="stempel"') && str_contains($html, '>Nicht einsatzbereit<'));
$html = render_partial('vehicles', $defekt + ['ansicht' => 'liste']);
$check('in der Liste kein Stempel', !str_contains($html, 'class="stempel'));

$alt = $daten;
$alt['fahrzeuge'] = [$fahrzeug(['is_active' => 0])];
$html = render_partial('vehicles', $alt + ['ansicht' => 'kacheln']);
$check('ausgemustert grau gestempelt', str_contains($html, 'stempel--grau') && str_contains($html, '>Ausgemustert<'));

$html = render_partial('vehicles', $daten + ['ansicht' => 'kacheln']);
$check('einsatzbereit bleibt ohne Stempel', !str_contains($html, 'class="stempel'));


echo "$ok bestanden, $fail fehlgeschlagen\n";
