<?php
declare(strict_types=1);
/* Budgettöpfe verwalten: Übernahme aus dem Vorjahr und die Verwaltungsseite. */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'haushaltsjahr' => '2026', 'budget_rundung' => '0'];
$GLOBALS['inserts'] = [];
$GLOBALS['audit'] = [];
$GLOBALS['toepfe'] = [];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text',
                    'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM budgets')) {
        $jahr = (int)($p[0] ?? 0);
        $rows = array_values(array_filter($GLOBALS['toepfe'], static fn($b) => (int)$b['jahr'] === $jahr));
        if (str_contains($sql, 'is_active = 1')) {
            $rows = array_values(array_filter($rows, static fn($b) => (int)$b['is_active'] === 1));
        }
        return $rows;
    }
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
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/view.php';
require $app . '/src/lib/expenses.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ================= Übernahme aus dem Vorjahr ================= */
$GLOBALS['toepfe'] = [
    ['id' => 1, 'jahr' => 2025, 'name' => 'Ausstattung', 'kategorie_id' => 3, 'fachgruppe_id' => null,
     'betrag_netto' => '4000.00', 'beschreibung' => 'Selbstbeschaffung', 'is_active' => 1],
    ['id' => 2, 'jahr' => 2025, 'name' => 'Liegenschaft', 'kategorie_id' => null, 'fachgruppe_id' => null,
     'betrag_netto' => '1500.00', 'beschreibung' => '', 'is_active' => 1],
    ['id' => 3, 'jahr' => 2025, 'name' => 'Altlast', 'kategorie_id' => null, 'fachgruppe_id' => null,
     'betrag_netto' => '100.00', 'beschreibung' => '', 'is_active' => 0],
    ['id' => 4, 'jahr' => 2026, 'name' => 'ausstattung', 'kategorie_id' => null, 'fachgruppe_id' => null,
     'betrag_netto' => '0.00', 'beschreibung' => '', 'is_active' => 1],
];

$anzahl = budget_pot_copy(2025, 2026);
$check('nur die fehlenden übernommen', $anzahl === 1);
$check('gleichnamiger Topf bleibt unberührt',
    count(array_filter($GLOBALS['inserts'], static fn($i) => $i[1]['name'] === 'Ausstattung')) === 0);
$check('stillgelegte bleiben liegen',
    count(array_filter($GLOBALS['inserts'], static fn($i) => $i[1]['name'] === 'Altlast')) === 0);
$neu = $GLOBALS['inserts'][0][1];
$check('Topf landet im Zieljahr', (int)$neu['jahr'] === 2026 && $neu['name'] === 'Liegenschaft');
$check('Betrag und Zuordnung kommen mit', abs($neu['betrag_netto'] - 1500.0) < 0.001);
$check('Übernommenes ist aktiv', (int)$neu['is_active'] === 1);

$GLOBALS['inserts'] = [];
$check('leeres Vorjahr ändert nichts', budget_pot_copy(2019, 2026) === 0 && $GLOBALS['inserts'] === []);

/* ================= Die Verwaltungsseite ================= */
$_SERVER['REQUEST_URI'] = '/?p=budget_pots';
$_GET = ['p' => 'budget_pots', 'jahr' => '2026'];

$liste = [
    ['id' => 10, 'jahr' => 2026, 'name' => 'Ausstattung', 'beschreibung' => 'Selbstbeschaffung',
     'betrag_netto' => '4000.00', 'is_active' => 1, 'kategorie_label' => 'Ausstattung / Gerät',
     'fachgruppe_label' => null, 'verplant' => '900.00', 'wuensche' => 3,
     'ausgegeben' => '3500.00', 'buchungen' => 4],
    ['id' => 11, 'jahr' => 2026, 'name' => 'Altlast', 'beschreibung' => '',
     'betrag_netto' => '100.00', 'is_active' => 0, 'kategorie_label' => null,
     'fachgruppe_label' => 'Zugtrupp', 'verplant' => '0.00', 'wuensche' => 0,
     'ausgegeben' => '0.00', 'buchungen' => 0],
];
$html = render_partial('budget_pots', [
    'jahr' => 2026, 'jahre' => [2026, 2025], 'toepfe' => $liste,
    'summe' => 4100.0, 'gesamt' => 5000.0, 'vorjahr' => 2,
]);

$check('Überschrift mit Jahr', str_contains($html, 'Budgettöpfe 2026'));
$check('Knopf zum Anlegen', str_contains($html, '+ Topf')
    && str_contains($html, 'p=budget_edit'));
$check('Übernahme aus dem Vorjahr angeboten', str_contains($html, 'Aus 2025 übernehmen')
    && str_contains($html, 'name="action" value="kopieren"'));
$check('Rückfrage vor der Übernahme', str_contains($html, 'data-confirm="2 Topf/Töpfe aus 2025'));
$check('beide Töpfe in der Tabelle', str_contains($html, 'Ausstattung') && str_contains($html, 'Altlast'));
$check('Stillgelegtes ist gekennzeichnet', str_contains($html, 'stillgelegt'));
$check('Stilllegen und Aktivieren als Knopf', str_contains($html, '>Stilllegen<')
    && str_contains($html, '>Aktivieren<') && str_contains($html, 'name="action" value="aktiv"'));
$check('Zahlen je Topf', str_contains($html, '3 Wunsch/Wünsche') && str_contains($html, '4 Buchung(en)'));
$check('Summe unter der Tabelle', str_contains($html, '2 Topf/Töpfe'));
$check('nicht verteiltes Geld', str_contains($html, 'Nicht verteilt'));
$check('Bearbeiten führt zum Topf', str_contains($html, 'p=budget_edit&amp;id=10'));

// Leeres Jahr
$html = render_partial('budget_pots', [
    'jahr' => 2027, 'jahre' => [2027, 2026], 'toepfe' => [],
    'summe' => 0.0, 'gesamt' => 0.0, 'vorjahr' => 0,
]);
$check('leeres Jahr sagt es', str_contains($html, 'ist noch kein Topf angelegt'));
$check('ohne Vorjahr keine Übernahme', !str_contains($html, 'übernehmen?'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
