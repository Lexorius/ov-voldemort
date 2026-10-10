<?php
declare(strict_types=1);
/*
 * Auslagen: wer hat vorgestreckt, wann erstattet – Speichern, Plakette,
 * Filter, Formular, Liste mit Hinweis, Export.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'budget_modul_name' => 'Budget', 'budget_rundung' => '0'];
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
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'expenses'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Speichern ---------- */
$_POST = ['art' => 'ausgabe', 'bezeichnung' => 'Getränke Übung', 'datum' => '2026-10-03', 'jahr' => '2026', 'betrag' => '48,90', 'status' => 'bezahlt',
    'ausgelegt_von' => '5', 'ausgelegt_name' => '  ', 'erstattet_am' => ''];
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$d = $GLOBALS['inserts'][0][1];
$check('Ausgabe mit Auslage gespeichert', $fehler === [] && $d['ausgelegt_von'] === 5 && $d['ausgelegt_name'] === '' && $d['erstattet_am'] === null);
$_POST = ['art' => 'ausgabe', 'bezeichnung' => 'Brötchen', 'datum' => '2026-10-04', 'jahr' => '2026', 'betrag' => '12', 'status' => 'bezahlt',
    'ausgelegt_von' => '', 'ausgelegt_name' => 'Nachbar-OV Müller', 'weitergegeben_am' => '2026-10-05', 'erstattet_am' => '2026-10-08'];
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$d = $GLOBALS['inserts'][1][1];
$check('freier Name, Weitergabe und Erstattung', $d['ausgelegt_von'] === null && $d['ausgelegt_name'] === 'Nachbar-OV Müller' && $d['weitergegeben_am'] === '2026-10-05' && $d['erstattet_am'] === '2026-10-08');
$_POST = ['art' => 'einnahme', 'bezeichnung' => 'Einsatz', 'datum' => '2026-10-04', 'jahr' => '2026', 'betrag' => '500', 'status' => 'bezahlt', 'ausgelegt_von' => '5', 'ausgelegt_name' => 'x', 'erstattet_am' => '2026-10-08'];
[$id, $fehler] = expense_save_from_post(null, ['id' => 1]);
$d = $GLOBALS['inserts'][2][1];
$check('Einnahmen kennen keine Auslage', $d['ausgelegt_von'] === null && $d['ausgelegt_name'] === '' && $d['erstattet_am'] === null && $d['weitergegeben_am'] === null);
$_POST = [];

/* ---------- Plakette und Helfer ---------- */
$offen = ['ausgelegt_von' => 5, 'ausgelegt_display' => 'Max Muster', 'ausgelegt_name' => '', 'weitergegeben_am' => null, 'erstattet_am' => null, 'betrag_brutto' => 48.9];
$weiter = ['ausgelegt_von' => 6, 'ausgelegt_display' => 'Anna', 'ausgelegt_name' => '', 'weitergegeben_am' => '2026-10-05', 'erstattet_am' => null, 'betrag_brutto' => 30.0];
$erstattet = ['ausgelegt_von' => null, 'ausgelegt_display' => null, 'ausgelegt_name' => 'Nachbar-OV Müller', 'weitergegeben_am' => '2026-10-05', 'erstattet_am' => '2026-10-08', 'betrag_brutto' => 12.0];
$keine = ['ausgelegt_von' => null, 'ausgelegt_display' => null, 'ausgelegt_name' => '', 'weitergegeben_am' => null, 'erstattet_am' => null, 'betrag_brutto' => 5.0];
$check('Stufen', auslage_stufe($offen) === 'ausgelegt' && auslage_stufe($weiter) === 'weitergegeben' && auslage_stufe($erstattet) === 'erstattet' && auslage_stufe($keine) === '');
$check('nach Stufe gezählt', auslagen_nach_stufe([$offen, $weiter, $erstattet, $keine, $offen]) === ['ausgelegt' => ['anzahl' => 2, 'summe' => 97.8], 'weitergegeben' => ['anzahl' => 1, 'summe' => 30.0]]);
$check('wer ausgelegt hat', auslage_von($offen) === 'Max Muster' && auslage_von($erstattet) === 'Nachbar-OV Müller' && auslage_von($keine) === '' && auslage_von(['ausgelegt_von' => 9]) === 'Helfer #9');
$check('offen oder erstattet', auslage_offen($offen) && auslage_offen($weiter) && !auslage_offen($erstattet) && !auslage_offen($keine));
$check('Plakette je Stufe', str_contains(auslage_badge($offen), 'noch nicht weitergegeben') && str_contains(auslage_badge($offen), 'Max Muster') && str_contains(auslage_badge($weiter), 'bei der Regionalstelle seit 05.10.2026')
    && str_contains(auslage_badge($weiter), '#0369a1') && str_contains(auslage_badge($erstattet), 'Geld erhalten 08.10.2026') && auslage_badge($keine) === '');
$check('Summe je Helfer, größte zuerst', auslagen_je_helfer([$offen, $erstattet, $keine, $offen]) === ['Max Muster' => 97.8, 'Nachbar-OV Müller' => 12.0]);

/* ---------- Abfrage ---------- */
$GLOBALS['abfragen'] = [];
expense_query(['jahr' => 2026, 'art' => 'ausgabe', 'auslage' => 'offen']);
[$sql, $p] = $GLOBALS['abfragen'][0];
$check('Abfrage: Helfer dabei, Filter offen', str_contains($sql, 'LEFT JOIN users      au ON au.id = e.ausgelegt_von') && str_contains($sql, 'au.display_name AS ausgelegt_display')
    && str_contains($sql, "(e.ausgelegt_von IS NOT NULL OR e.ausgelegt_name <> '')") && str_contains($sql, 'e.erstattet_am IS NULL'));
$GLOBALS['abfragen'] = [];
expense_query(['auslage' => 'erstattet']);
$check('Filter erstattet', str_contains($GLOBALS['abfragen'][0][0], 'e.erstattet_am IS NOT NULL'));
$GLOBALS['abfragen'] = [];
expense_query(['auslage' => 'weitergegeben']);
$check('Filter bei der Regionalstelle', str_contains($GLOBALS['abfragen'][0][0], 'e.erstattet_am IS NULL AND e.weitergegeben_am IS NOT NULL'));
$GLOBALS['abfragen'] = [];
expense_query(['auslage' => 'ausgelegt']);
$check('Filter noch nicht weitergegeben', str_contains($GLOBALS['abfragen'][0][0], 'e.erstattet_am IS NULL AND e.weitergegeben_am IS NULL'));
$GLOBALS['abfragen'] = [];
expense_query(['auslage' => 'ja']);
$check('Filter nur Auslagen', str_contains($GLOBALS['abfragen'][0][0], "ausgelegt_name <> ''") && !str_contains($GLOBALS['abfragen'][0][0], 'erstattet_am IS'));
$GLOBALS['abfragen'] = [];
auslagen_offen(2026);
$check('offene Auslagen: Ausgaben des Jahres, älteste zuerst', str_contains($GLOBALS['abfragen'][0][0], 'e.datum ASC') && $GLOBALS['abfragen'][0][1] === [2026, 'ausgabe']);

/* ---------- Formular ---------- */
$leer = ['id' => null, 'art' => 'ausgabe', 'jahr' => 2026, 'datum' => '2026-03-12', 'bezeichnung' => '', 'beschreibung' => '', 'kategorie_id' => null, 'fachgruppe_id' => null, 'budget_id' => null,
    'wish_id' => null, 'event_id' => null, 'betrag_brutto' => '', 'betrag_netto' => '', 'lieferant' => '', 'beleg_nr' => '', 'referenz' => '', 'notiz' => '', 'bezahlt_am' => null, 'status' => 'bezahlt',
    'ausgelegt_von' => 5, 'ausgelegt_name' => '', 'weitergegeben_am' => '2026-10-05', 'erstattet_am' => null];
$html = render_partial('expense_edit', ['art' => 'ausgabe', 'expense' => $leer, 'errors' => [], 'budgets' => [], 'wishes' => [], 'events' => [],
    'helfer' => [['id' => 4, 'display_name' => 'Anna'], ['id' => 5, 'display_name' => 'Max Muster']]]);
$check('Formular: Auslage mit Helferwahl, Name, Erstattungsdatum', str_contains($html, '<h3 class="mt">Auslage</h3>') && str_contains($html, 'value="5" selected>Max Muster') && str_contains($html, 'name="ausgelegt_name"')
    && str_contains($html, 'name="erstattet_am"') && str_contains($html, 'name="weitergegeben_am"') && str_contains($html, 'direkt vom OV bezahlt')
    && str_contains($html, 'Stand: <strong>an die Regionalstelle weitergegeben</strong>'));
$html = render_partial('expense_edit', ['art' => 'einnahme', 'expense' => ['art' => 'einnahme'] + $leer, 'errors' => [], 'budgets' => [], 'wishes' => [], 'events' => []]);
$check('Einnahmen-Formular ohne Auslage', !str_contains($html, 'name="ausgelegt_von"'));

/* ---------- Liste ---------- */
$row = ['id' => 1, 'art' => 'ausgabe', 'jahr' => 2026, 'datum' => '2026-10-03', 'bezeichnung' => 'Getränke Übung', 'betrag_brutto' => 48.9, 'status' => 'bezahlt', 'lieferant' => 'Getränke Huber',
    'kategorie_label' => 'Verpflegung', 'kategorie_color' => '#b45309', 'fachgruppe_label' => '', 'budget_name' => '', 'wunsch_bezeichnung' => '', 'wuensche_namen' => '', 'fahrzeuge_namen' => '',
    'bestellung_nummer' => '', 'veranstaltung_titel' => '', 'beleg_nr' => '', 'referenz' => '', 'erfasser' => 'Tester', 'bezahlt_am' => '2026-10-03'] + $offen;
$filters = ['q' => '', 'kategorie_id' => 0, 'fachgruppe_id' => 0, 'budget_id' => 0, 'status' => '', 'von' => '', 'bis' => '', 'sort' => 'datum', 'auslage' => ''];
$html = render_partial('expenses', ['art' => 'ausgabe', 'rows' => [$row], 'stats' => ['anzahl' => 1, 'summe' => 48.9], 'filters' => $filters, 'jahr' => 2026, 'jahre' => [2026],
    'jahresbudget' => 10000.0, 'jahresSumme' => 48.9, 'gegenSumme' => 0.0, 'budgets' => [], 'einnahmenStand' => null, 'wartend' => null, 'auslagenOffen' => [$row]]);
$check('Liste: Hinweis oben, Plakette an der Buchung, Filter Auslagen', str_contains($html, 'Ein Helfer wartet noch auf Erstattung</strong>') && str_contains($html, 'Max Muster 48,90 €')
    && str_contains($html, '1 noch nicht an die Regionalstelle weitergegeben (48,90 €)') && str_contains($html, 'ausgelegt von Max Muster – noch nicht weitergegeben') && str_contains($html, 'name="auslage"')
    && str_contains($html, 'auslage=ausgelegt') && str_contains($html, 'bei der Regionalstelle</option>'));
$html = render_partial('expenses', ['art' => 'ausgabe', 'rows' => [$row, ['id' => 2] + $weiter + $row], 'stats' => ['anzahl' => 2, 'summe' => 78.9], 'filters' => $filters, 'jahr' => 2026, 'jahre' => [2026],
    'jahresbudget' => 10000.0, 'jahresSumme' => 78.9, 'gegenSumme' => 0.0, 'budgets' => [], 'einnahmenStand' => null, 'wartend' => null, 'auslagenOffen' => [$row, ['id' => 2] + $weiter + $row]]);
$check('Liste: beide Stufen im Hinweis', str_contains($html, '2 Auslagen warten noch auf Erstattung') && str_contains($html, '1 bei der Regionalstelle (30,00 €)') && str_contains($html, 'auslage=weitergegeben')
    && str_contains($html, 'bei der Regionalstelle seit 05.10.2026'));
$html = render_partial('expenses', ['art' => 'ausgabe', 'rows' => [], 'stats' => ['anzahl' => 0, 'summe' => 0.0], 'filters' => $filters, 'jahr' => 2026, 'jahre' => [2026],
    'jahresbudget' => 10000.0, 'jahresSumme' => 0.0, 'gegenSumme' => 0.0, 'budgets' => [], 'einnahmenStand' => null, 'wartend' => null]);
$check('Liste ohne offene Auslagen: kein Hinweis', !str_contains($html, 'wartet noch auf Erstattung') && !str_contains($html, 'warten noch auf Erstattung'));
$export = (string)file_get_contents($app . '/src/pages/expenses_export.php');
$check('Export mit drei Spalten', str_contains($export, "'Ausgelegt von', 'Weitergegeben am', 'Erstattet am'") && str_contains($export, 'auslage_von($r)') && str_contains($export, "weitergegeben_am"));
$mig = (string)file_get_contents($app . '/src/cli/migrate.php');
$check('Wanderung 058 und 059', str_contains($mig, '058_auslagen') && str_contains($mig, 'fk_exp_auslage') && str_contains($mig, '059_auslage_stufen'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
