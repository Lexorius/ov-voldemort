<?php
declare(strict_types=1);
/* Wünsche: Haken „ist STAN" – Filter, Statistik, Speichern, Formular, Marke, Export-Spalte, Divera-Zuordnung */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'mwst_satz' => '19', 'wunsch_modul_name' => 'Wünsch dir was', 'wunsch_begruendung_pflicht' => '0'];
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
foreach (['util', 'settings', 'lists', 'view', 'order_rights', 'notify', 'uploads', 'wishes', 'divera'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- Schema und Wanderung ---------- */
$schema = (string)file_get_contents($app . '/sql/schema.sql');
$check('Spalte im Schema', preg_match('/CREATE TABLE IF NOT EXISTS wishes \(.*?ist_stan\s+TINYINT\(1\)\s+NOT NULL DEFAULT 0/s', $schema) === 1);
$wanderung = (string)file_get_contents($app . '/src/cli/migrate.php');
$check('Wanderung legt die Spalte nach', str_contains($wanderung, "ovb_column_exists(\$pdo, 'wishes', 'ist_stan')") && str_contains($wanderung, 'ADD COLUMN ist_stan TINYINT(1) NOT NULL DEFAULT 0'));

/* ---------- Abfrage ---------- */
$GLOBALS['abfragen'] = [];
wish_query(['stan' => '1']);
[$q, $p] = $GLOBALS['abfragen'][0];
$check('nur STAN gefiltert', str_contains($q, 'w.ist_stan = ?') && $p === [1]);
$GLOBALS['abfragen'] = [];
wish_query(['stan' => '0']);
$check('ohne STAN gefiltert (0 ist nicht leer)', str_contains($GLOBALS['abfragen'][0][0], 'w.ist_stan = ?') && $GLOBALS['abfragen'][0][1] === [0]);
$GLOBALS['abfragen'] = [];
wish_query(['stan' => '']);
$check('leer heißt alle', !str_contains($GLOBALS['abfragen'][0][0], 'w.ist_stan'));

/* ---------- Statistik ---------- */
$zeile = static fn(float $betrag, int $stan, int $final = 0) => ['netto_gesamt' => $betrag, 'status_final' => $final, 'nice_to_have' => 0, 'ist_stan' => $stan];
$s = wish_stats([$zeile(100, 1), $zeile(50, 0), $zeile(25.5, 1, 1)]);
$check('Summe und Zahl der STAN-Wünsche', abs($s['stan'] - 125.5) < 0.001 && $s['stan_anzahl'] === 2 && abs($s['netto'] - 175.5) < 0.001);
$check('alte Zeilen ohne Spalte stören nicht', wish_stats([['netto_gesamt' => 1, 'status_final' => 0, 'nice_to_have' => 0]])['stan_anzahl'] === 0);

/* ---------- Speichern ---------- */
$_POST = ['bezeichnung' => 'Tauchpumpe TP 8/1', 'anzahl' => '1', 'netto_einzel' => '1249,90', 'ist_stan' => '1'];
$GLOBALS['inserts'] = [];
[$id, $fehler] = wish_save_from_post(null, current_user());
$neu = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'wishes'))[0][1];
$check('ohne Fehler gespeichert', $fehler === [] && $id > 0);
$check('Haken gemerkt', ($neu['ist_stan'] ?? null) === 1);
unset($_POST['ist_stan']);
$GLOBALS['inserts'] = [];
wish_save_from_post(null, current_user());
$neu = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'wishes'))[0][1];
$check('ohne Haken 0', ($neu['ist_stan'] ?? null) === 0);
$_POST = [];

/* ---------- Formular und Marke ---------- */
$_SERVER['REQUEST_URI'] = '/?p=wish_edit';
$wish = ['id' => null, 'bezeichnung' => '', 'beschreibung' => '', 'begruendung' => '', 'anzahl' => 1,
    'einheit_id' => null, 'netto_einzel' => 0, 'netto_gesamt' => 0, 'mwst_satz' => 19, 'fachgruppe_id' => null,
    'kategorie_id' => null, 'dringlichkeit_id' => null, 'status_id' => null, 'budget_id' => null,
    'vehicle_id' => null, 'nice_to_have' => 0, 'ist_stan' => 1, 'benoetigt_bis' => null, 'lieferant' => '', 'artikelnummer' => '',
    'link' => '', 'antragsteller' => '', 'extra' => null];
$html = render_partial('wish_edit', ['wish' => $wish, 'errors' => [], 'budgets' => [], 'anlagen' => [], 'fahrzeuge' => []]);
$check('Haken im Formular, vorbelegt', preg_match('/name="ist_stan" value="1" checked/', $html) === 1);
$check('Beschriftung erklärt die STAN', str_contains($html, 'Stärke- und Ausstattungsnachweisung'));
$wish['ist_stan'] = 0;
$html = render_partial('wish_edit', ['wish' => $wish, 'errors' => [], 'budgets' => [], 'anlagen' => [], 'fahrzeuge' => []]);
$check('ohne Haken nicht vorbelegt', preg_match('/name="ist_stan" value="1" checked/', $html) === 0);

$w = ['id' => 5, 'bezeichnung' => 'Tauchpumpe', 'fachgruppe_label' => 'FGr N', 'anzahl' => 1, 'einheit_label' => '', 'benoetigt_bis' => null,
      'netto_gesamt' => 10, 'dring_color' => '', 'dring_label' => '', 'status_label' => '', 'status_color' => '', 'nice_to_have' => 0,
      'ist_stan' => 1, 'anlagen' => 0, 'votes' => 0, 'source' => 'manuell', 'budget_name' => ''];
$html = render_partial('partials/wish_item', ['w' => $w]);
$check('Marke STAN in der Liste', str_contains($html, 'badge--stan') && str_contains($html, '>STAN</span>'));
$w['ist_stan'] = 0;
$check('ohne Haken keine Marke', !str_contains(render_partial('partials/wish_item', ['w' => $w]), 'badge--stan'));
$check('Marke hat eine Farbe', str_contains((string)file_get_contents($app . '/public/assets/css/app.css'), '.badge--stan'));

/* ---------- Divera ---------- */
$vorschlag = divera_suggest_map(['Bezeichnung', 'Anzahl', 'Ist STAN', 'Nice to have', 'Begründung']);
$check('Divera: Feld „Ist STAN" erkannt', ($vorschlag['ist_stan'] ?? '') === 'Ist STAN');
$check('Divera: jedes Feld nur einmal', count($vorschlag) === count(array_unique($vorschlag)));
$vorschlag2 = divera_suggest_map(['Bezeichnung', 'Soll-Ausstattung (ja/nein)']);
$check('Divera: auch „Soll-Ausstattung"', ($vorschlag2['ist_stan'] ?? '') === 'Soll-Ausstattung (ja/nein)');
$check('Divera: Beschriftung für die Zuordnung', (divera_map_targets()['ist_stan'] ?? '') === 'Ist STAN (ja/nein)');

$eintrag = ['id' => '9001', 'user' => 'Max', 'created' => '2026-11-01 10:00:00', 'fields' => ['Bezeichnung' => 'Tauchpumpe', 'Anzahl' => '1', 'Ist STAN' => 'ja', 'Begründung' => 'STAN-Soll'], 'attachments' => []];
$form = ['form_id' => '654', 'default_status_id' => null, 'default_fachgruppe_id' => null];
$wunsch = divera_entry_to_wish($eintrag, $vorschlag, $form);
$check('Divera: ja wird 1', ($wunsch['ist_stan'] ?? null) === 1);
$eintrag['fields']['Ist STAN'] = 'nein';
$check('Divera: nein wird 0', (divera_entry_to_wish($eintrag, $vorschlag, $form)['ist_stan'] ?? null) === 0);

/* ---------- Export ---------- */
$export = (string)file_get_contents($app . '/src/pages/wishes_export.php');
$check('Spalte STAN im CSV', str_contains($export, "'Nice to have', 'STAN',") && str_contains($export, "(int)(\$w['ist_stan'] ?? 0) ? 'ja' : 'nein'"));

echo "$ok bestanden, $fail fehlgeschlagen\n";
