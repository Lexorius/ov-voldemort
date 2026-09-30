<?php
declare(strict_types=1);
/*
 * Budget ohne Mehrwertsteuer: ein Betrag je Buchung, Jahreszahlen,
 * Übersicht und MQTT rechnen mit dem Jahresbudget.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'haushaltsjahr' => '2026', 'budget_warn_prozent' => '90',
    'ov_name' => 'OV', 'budget_modul_name' => 'Budget', 'budget_intro' => ''];
$GLOBALS['werte'] = [];
$GLOBALS['inserts'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM budget_years')) { return ['jahr' => 2026, 'betrag' => 10000, 'beschreibung' => '', 'is_active' => 1]; }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, "FROM expenses WHERE jahr = ? AND art = ?")) { return $p[1] === 'einnahme' ? 2500 : 4000; }
    if (str_contains($sql, 'FROM budgets')) { return 6000; }
    return $d;
}
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 5; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'display_name' => 'Anna', 'username' => 'anna']; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'expenses'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Buchung speichern ---------- */
$_POST = ['art' => 'ausgabe', 'bezeichnung' => 'Strom', 'datum' => '2026-03-04', 'betrag' => '119,00', 'mwst_satz' => '19'];
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$d = $GLOBALS['inserts'][0][1] ?? [];
$check('Buchung angelegt', $id === 5 && $fehler === []);
$check('ein Betrag in beiden Spalten', (float)$d['betrag_brutto'] === 119.0 && (float)$d['betrag_netto'] === 119.0);
$check('MwSt-Satz bleibt null, auch wenn einer geschickt wird', (float)$d['mwst_satz'] === 0.0);
$_POST['betrag'] = '0';
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$check('ohne Betrag geht nicht', $id === null && count($fehler) === 1);

/* ---------- Jahreszahlen ---------- */
$z = budget_jahr_zahlen(2026);
$check('Zuweisung', $z['budget'] === 10000.0);
$check('verfügbar = Zuweisung + Einnahmen', $z['verfuegbar'] === 12500.0);
$check('frei = verfügbar - Ausgaben', $z['frei'] === 8500.0);
$check('Quote = Ausgaben / verfügbar', abs($z['quote'] - 32.0) < 0.01);
$check('Summe hat nur noch einen Wert', expense_stats([['betrag_brutto' => 10], ['betrag_brutto' => 5.5]]) === ['anzahl' => 2, 'summe' => 15.5]);

/* ---------- Ansichten ohne Mehrwertsteuer ---------- */
$dateien = ['views/expense_edit.php', 'views/expenses.php', 'views/budget.php', 'views/dashboard.php',
            'views/wish_edit.php', 'views/wish_view.php', 'views/wishes.php', 'views/budget_edit.php',
            'views/budget_pots.php', 'views/budget_year_edit.php', 'src/pages/expenses_export.php',
            'src/pages/wishes_export.php'];
$treffer = [];
foreach ($dateien as $f) {
    $q = (string)file_get_contents($app . '/' . $f);
    // Sichtbarer Text – Bezeichner wie netto_gesamt oder f-netto-einzel dürfen bleiben
    if (preg_match('/MwSt|Mehrwertsteuer|(?<![\w\'-])[Nn]etto(?![\w\'-])|(?<![\w\'-])[Bb]rutto(?![\w\'-])/u', $q)) {
        $treffer[] = $f;
    }
}
$check('kein MwSt, Netto oder Brutto mehr in den Ansichten', $treffer === []);
$check('Einstellungen weg', !str_contains((string)file_get_contents($app . '/sql/seed.sql'), "'mwst_satz'")
    && !str_contains((string)file_get_contents($app . '/sql/seed.sql'), "'ausgaben_betragsart'"));
$check('Wanderung 030 vorhanden', str_contains((string)file_get_contents($app . '/src/cli/migrate.php'), "030_ohne_mwst"));

/* ---------- Übersicht rendern ---------- */
require $app . '/src/lib/wishes.php';
$budgets1 = [['id' => 1, 'name' => 'Allgemein', 'betrag_netto' => 6000, 'verplant' => 1200, 'is_active' => 1]];
$budgets2 = $budgets1 + [1 => ['id' => 2, 'name' => 'Fahrzeuge', 'betrag_netto' => 4000, 'verplant' => 300, 'is_active' => 1]];
$basis = ['title' => 'x', 'user' => ['display_name' => 'Anna Beispiel', 'username' => 'anna'], 'jahr' => 2026,
    'wuensche' => [], 'statsW' => ['anzahl' => 0, 'netto' => 0.0, 'netto_offen' => 0.0, 'nice' => 0.0],
    'todos' => [], 'todosGesamt' => 0, 'ueberfaellig' => 0, 'zuBestellen' => [], 'fahrzeuge' => [],
    'zahlen' => $z, 'budgetVerplant' => 1200.0];
$html1 = render_partial('dashboard', $basis + ['budgets' => $budgets1]);
$html2 = render_partial('dashboard', $basis + ['budgets' => $budgets2]);
$check('Übersicht zeigt das Jahr', str_contains($html1, 'Budget 2026') && str_contains($html1, '10.000,00') && str_contains($html1, '8.500,00'));
$check('ein einzelner Topf wird nicht aufgeführt', !str_contains($html1, 'Budgettöpfe') && !str_contains($html1, 'Allgemein'));
$check('mehrere Töpfe schon', str_contains($html2, 'Budgettöpfe') && str_contains($html2, 'Fahrzeuge'));
$check('Kennzahl oben heißt Budget frei', str_contains($html1, 'Budget frei'));
$leer = render_partial('dashboard', ['budgets' => [], 'zahlen' => ['budget' => 0.0, 'einnahmen' => 0.0, 'ausgaben' => 0.0, 'verfuegbar' => 0.0, 'frei' => 0.0, 'quote' => 0.0]] + $basis);
$check('ohne Jahresbudget: Hinweis mit Link', str_contains($leer, 'Jahresbudget eintragen'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
