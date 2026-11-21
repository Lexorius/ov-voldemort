<?php
declare(strict_types=1);
/*
 * Die Liste der Ausgaben und Einnahmen: blättert oben und unten (auch am
 * Handy) mit wählbarer Zahl je Seite, und die Spalten stehen so, dass der
 * Betrag ohne Scrollen zu sehen ist: Bearbeiten-Symbol, Datum, Bezeichnung,
 * Kategorie, Betrag, Beleg, Fachgruppe, Topf.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'budget_modul_name' => 'Budget', 'budget_rundung' => '0'];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'expenses'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- je Seite ---------- */
$check('je Seite: nur die angebotenen Zahlen, sonst die Vorgabe', seiten_je(100) === 100 && seiten_je('50') === 50 && seiten_je(0, 25) === 25 && seiten_je(33, 25) === 25 && seiten_je(null) === LISTE_JE_SEITE);
$check('Auswahl bietet 25 bis 200', LISTE_JE_SEITE_WAHL === [25, 50, 100, 200]);
$seite = (string)file_get_contents($app . '/src/pages/expenses.php');
$check('Seite: 25 je Seite als Vorgabe, Wahl aus ?je=', str_contains($seite, "seiten_je(get_int('je'), 25)") && str_contains($seite, "'jeSeite'"));

/* ---------- Ansicht ---------- */
$zeile = static fn(int $id): array => ['id' => $id, 'art' => 'ausgabe', 'jahr' => 2026, 'datum' => '2026-10-0' . ($id % 9 + 1), 'bezeichnung' => 'Buchung <' . $id . '>', 'betrag_brutto' => 10.0 * $id, 'status' => 'bezahlt',
    'lieferant' => 'Huber', 'kategorie_label' => 'Verpflegung', 'kategorie_color' => '#b45309', 'fachgruppe_label' => 'FGr N', 'budget_name' => 'Topf A', 'wunsch_bezeichnung' => '', 'wuensche_namen' => '',
    'fahrzeuge_namen' => '', 'bestellung_nummer' => '', 'veranstaltung_titel' => '', 'beleg_nr' => 'B-' . $id, 'referenz' => '', 'erfasser' => 'Tester', 'bezahlt_am' => '2026-10-03',
    'ausgelegt_von' => null, 'ausgelegt_display' => null, 'ausgelegt_name' => '', 'weitergegeben_am' => null, 'erstattet_am' => null];
$filters = ['q' => '', 'kategorie_id' => 0, 'fachgruppe_id' => 0, 'budget_id' => 0, 'status' => '', 'von' => '', 'bis' => '', 'sort' => 'datum', 'auslage' => ''];
$grund = ['art' => 'ausgabe', 'stats' => ['anzahl' => 60, 'summe' => 1234.5], 'filters' => $filters, 'jahr' => 2026, 'jahre' => [2026], 'jahresbudget' => 10000.0, 'jahresSumme' => 1234.5, 'gegenSumme' => 0.0,
    'budgets' => [], 'einnahmenStand' => null, 'wartend' => null, 'auslagenOffen' => []];
$rows = array_map($zeile, range(1, 25));
$_SERVER['REQUEST_URI'] = '/index.php?p=expenses';
$html = render_partial('expenses', array_replace($grund, ['rows' => $rows, 'blatt' => seiten_blaettern(60, 2, 25), 'blattParams' => ['jahr' => '2026', 'art' => 'ausgabe', 'je' => '25'], 'jeSeite' => 25]));

$check('Spaltenfolge: Bearbeiten, Datum, Bezeichnung, Kategorie, Betrag, Beleg, Fachgruppe, Topf',
    (bool)preg_match('#<th><span class="sr-only">Bearbeiten</span></th>\s*<th>Datum</th><th>Bezeichnung</th><th>Kategorie</th><th class="num">Betrag</th><th>Beleg</th>\s*<th>Fachgruppe</th><th>Topf</th></tr>#', $html));
$check('Zeile: erst das Bearbeiten-Symbol, dann das Datum', (bool)preg_match('#<td class="nowrap"><a class="btn btn--sec btn--sm btn--icon" href="[^"]*p=expense_edit&amp;id=1" title="Bearbeiten" aria-label="Bearbeiten: Buchung &lt;1&gt;">✎</a></td>\s*<td class="nowrap small">02\.10\.2026</td>#', $html));
$check('Bearbeiten nur noch als Symbol', !str_contains($html, '>Bearbeiten</a>') && substr_count($html, '>✎</a>') === 25);
$check('Betrag vor Beleg, Fachgruppe und Topf', ($b = strpos($html, '<strong>10,00</strong>')) !== false && $b < ($be = strpos($html, '<span class="mono">B-1</span>'))
    && $be < ($f = strpos($html, '<td class="small">FGr N</td>')) && $f < strpos($html, '<td class="small">Topf A</td>'));
$check('Summenzeile passt zur Spaltenfolge', str_contains($html, '<td colspan="4"><strong>Summe (60)</strong></td>') && str_contains($html, '<td colspan="3"></td>'));
$check('Blätterer oben und unten am Tisch, dazu oben und unten am Handy', substr_count($html, '<nav class="seiten"') === 4 && substr_count($html, '<div class="seiten--oben">') === 2);
$check('Blätterer nennt Stand und trägt die Wahl je Seite weiter', str_contains($html, 'Seite 2 von 3 · 26–50 von 60') && str_contains($html, 'je=25&amp;seite=3'));
$check('Auswahl je Seite im Filter, 25 gewählt', (bool)preg_match('#<select id="je" name="je">\s*<option value="25" selected>25</option>\s*<option value="50">50</option>\s*<option value="100">100</option>\s*<option value="200">200</option>#', $html));

$eine = render_partial('expenses', array_replace($grund, ['rows' => [$zeile(1)], 'stats' => ['anzahl' => 1, 'summe' => 10.0], 'blatt' => seiten_blaettern(1, 1, 25), 'blattParams' => [], 'jeSeite' => 25]));
$check('eine Seite: kein Blätterer, kein leerer Kasten', !str_contains($eine, '<nav class="seiten"') && !str_contains($eine, 'seiten--oben'));

$GLOBALS['rechte'] = ['manage_budget' => false];
$nurSehen = render_partial('expenses', array_replace($grund, ['rows' => [$zeile(1)], 'stats' => ['anzahl' => 1, 'summe' => 10.0], 'blatt' => seiten_blaettern(1, 1, 25), 'blattParams' => [], 'jeSeite' => 25]));
$check('ohne Budgetrecht: keine Bearbeiten-Spalte, Summenzeile rückt nach', !str_contains($nurSehen, '✎') && !str_contains($nurSehen, 'sr-only">Bearbeiten') && str_contains($nurSehen, '<td colspan="3"><strong>Summe (1)</strong></td>')
    && (bool)preg_match('#<tr>\s*<th>Datum</th>#', $nurSehen));
$GLOBALS['rechte'] = [];

$einnahmen = render_partial('expenses', array_replace($grund, ['art' => 'einnahme', 'rows' => [$zeile(1) + ['art' => 'einnahme']], 'stats' => ['anzahl' => 1, 'summe' => 10.0], 'blatt' => seiten_blaettern(1, 1, 25), 'blattParams' => [], 'jeSeite' => 25]));
$check('Einnahmen: letzte Spalte ist die Einsatz-Nr.', str_contains($einnahmen, '<th>Fachgruppe</th><th>Einsatz-Nr.</th></tr>'));

$css = (string)file_get_contents($app . '/public/assets/css/app.css');
$check('Stil für Symbolknopf und Blätterer oben', str_contains($css, '.btn--icon {') && str_contains($css, '.seiten--oben .seiten {'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
