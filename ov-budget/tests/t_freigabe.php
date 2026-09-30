<?php
declare(strict_types=1);
// Freigabe „bitte bestellen“: Logik und Darstellung mit gestubbter Datenbank
session_start();
$GLOBALS['updates'] = [];
$GLOBALS['audits'] = [];
$GLOBALS['rolle'] = 'leitung';
$GLOBALS['wishes'] = [];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        return [['skey' => 'waehrung', 'svalue' => 'EUR', 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]];
    }
    if (str_contains($sql, 'FROM list_items')) {
        $r = [];
        foreach (['neu' => 1, 'eingeplant' => 5, 'freigegeben' => 55, 'bestellt' => 6, 'beschafft' => 7] as $slug => $id) {
            $r[] = ['id' => $id, 'list_key' => 'wunsch_status', 'label' => ucfirst($slug), 'slug' => $slug, 'color' => '#123456',
                    'weight' => 0, 'is_default' => 0, 'is_final' => $slug === 'beschafft' ? 1 : 0, 'is_active' => 1, 'sort_order' => $id];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM bestell_rechte')) {
        return [['rolle' => 'leitung', 'funktion_id' => null, 'darf_freigeben' => 1, 'freigabe_grenze' => null, 'darf_bestellen' => 1],
                ['rolle' => 'user', 'funktion_id' => null, 'darf_freigeben' => 0, 'freigabe_grenze' => null, 'darf_bestellen' => 0],
                ['rolle' => null, 'funktion_id' => 12, 'darf_freigeben' => 1, 'freigabe_grenze' => '1000', 'darf_bestellen' => 0]];
    }
    if (str_contains($sql, 'FROM user_functions')) {
        return (int)$p[0] === 8 ? [['function_id' => 12]] : [];
    }
    if (str_contains($sql, 'FROM wishes w')) {
        $GLOBALS['last_wish_sql'] = [$sql, $p];
        return $GLOBALS['wishes'];
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null): mixed { return $d; }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
final class StubStmt { public function execute(array $p): bool { $GLOBALS['audits'][] = $p[1]; return true; } }
final class StubPdo { public function prepare(string $s): StubStmt { return new StubStmt(); } }
function db(): StubPdo { return new StubPdo(); }
function current_user(): ?array { return ['id' => $GLOBALS['uid'] ?? 7, 'role' => $GLOBALS['rolle'], 'display_name' => 'Tester']; }
function user_functions(int $id): array { return array_map(static fn($r) => (int)$r['function_id'], db_all('FROM user_functions', [$id])); }
function can(string $what, mixed $ctx = null): bool { return $what === 'order_wish' && order_rights_for_user()['bestellen']; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/notify.php';
require $app . '/src/lib/wishes.php';
require $app . '/src/lib/order_rights.php';
require $app . '/src/lib/view.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

$w = static fn(array $x) => $x + ['id' => 3, 'bezeichnung' => 'Stromerzeuger 5 kVA', 'netto_gesamt' => 2499.5,
    'status_slug' => 'eingeplant', 'status_final' => 0, 'status_label' => 'Eingeplant', 'status_color' => '#1d4ed8',
    'dring_label' => 'hoch', 'dring_color' => '#b91c1c', 'fachgruppe_label' => 'Notversorgung', 'budget_name' => 'Ausstattung',
    'freigegeben_am' => null, 'freigeber' => null, 'lieferant' => 'Muster GmbH'];

// wish_releasable
$check('eingeplant freigebbar', wish_releasable($w([])));
$check('neu freigebbar', wish_releasable($w(['status_slug' => 'neu'])));
$check('ohne Status freigebbar', wish_releasable($w(['status_slug' => null])));
$check('freigegeben nicht erneut', !wish_releasable($w(['status_slug' => 'freigegeben'])));
$check('bestellt nicht', !wish_releasable($w(['status_slug' => 'bestellt'])));
$check('abgeschlossen nicht', !wish_releasable($w(['status_slug' => 'beschafft', 'status_final' => 1])));

// wish_release
$err = wish_release($w([]), current_user());
$check('release ohne Fehler', $err === null);
[$t, $d, $p] = $GLOBALS['updates'][0];
$check('release Tabelle', $t === 'wishes' && $p === [3]);
$check('release Status', $d['status_id'] === 55);
$check('release von', $d['freigegeben_von'] === 7);
$check('release am', (bool)preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $d['freigegeben_am']));
$check('release audit', $GLOBALS['audits'] === ['wunsch.freigegeben']);
$check('release doppelt abgewiesen', wish_release($w(['status_slug' => 'freigegeben']), current_user()) !== null);
$check('keine zweite Aktualisierung', count($GLOBALS['updates']) === 1);

// wish_mark_ordered
$check('bestellt nur nach Freigabe', wish_mark_ordered($w([]), current_user()) !== null);
$check('bestellt ok', wish_mark_ordered($w(['status_slug' => 'freigegeben']), current_user()) === null);
$check('bestellt Status', $GLOBALS['updates'][1][1]['status_id'] === 6);
$check('bestellt behält Freigabevermerk', !array_key_exists('freigegeben_am', $GLOBALS['updates'][1][1]));

// Filter in wish_query
wish_query(['status_slug' => 'freigegeben']);
$check('Filter status_slug', str_contains($GLOBALS['last_wish_sql'][0], 'st.slug = ?') && $GLOBALS['last_wish_sql'][1] === ['freigegeben']);
wish_query(['freigabe_offen' => 1]);
$check('Filter freigabe_offen', str_contains($GLOBALS['last_wish_sql'][0], "NOT IN ('freigegeben','bestellt','zurueckgestellt')"));
wish_query(['id' => 9]);
$check('Filter id', str_contains($GLOBALS['last_wish_sql'][0], 'w.id = ?') && $GLOBALS['last_wish_sql'][1] === [9]);
$check('Freigeber im Select', str_contains($GLOBALS['last_wish_sql'][0], 'fu.display_name AS freigeber'));

// Fehlender Status in der Liste
function list_ohne_freigabe(): void {}
$GLOBALS['wishes'] = [];

// Darstellung: Freigabebereich der Budgetübersicht (ohne den Rest der Seite)
$src = file_get_contents($app . '/views/budget.php');
$check('Knopftext', str_contains($src, 'Freigegeben, bitte bestellen'));
$start = strpos($src, '$freigabeZeile = ');
$ende = strpos($src, '$maxMonat = max(');
$zeileCode = substr($src, $start, $ende - $start);
eval($zeileCode);
$_SERVER['REQUEST_URI'] = '/?p=budget&jahr=2026';
$html = $freigabeZeile($w([]), 'freigeben');
$check('Zeile: Knopf freigeben', str_contains($html, 'value="freigeben"') && str_contains($html, 'Freigegeben, bitte bestellen'));
$check('Zeile: Rückfrage mit Betrag', str_contains($html, 'data-confirm="„Stromerzeuger 5 kVA“ für 2.499,50'));
$check('Zeile: back mit Anker', str_contains($html, 'name="back" value="/?p=budget&amp;jahr=2026#bestellung"'));
$check('Zeile: Topf', str_contains($html, 'Topf: Ausstattung'));
$html = $freigabeZeile($w(['status_slug' => 'freigegeben', 'freigegeben_am' => '2026-09-16 10:00:00', 'freigeber' => 'OB']), 'bestellt');
$check('Zeile: Knopf bestellt', str_contains($html, 'value="bestellt"') && !str_contains($html, 'value="freigeben"'));
$check('Zeile: Freigabevermerk', str_contains($html, 'freigegeben 16.09.2026 von OB'));
$GLOBALS['rolle'] = 'user';
$GLOBALS['uid'] = 9;
$html = $freigabeZeile($w([]), 'freigeben');
$check('Mitglied: kein Knopf', !str_contains($html, '<form') && str_contains($html, 'Stromerzeuger'));
$html2 = $freigabeZeile($w(['status_slug' => 'freigegeben']), 'bestellt');
$check('Mitglied: kein Bestellt-Knopf', !str_contains($html2, '<form'));
$GLOBALS['uid'] = 8;   // Zugführer, Grenze 1000
$html = $freigabeZeile($w([]), 'freigeben');
$check('Zugführer über Grenze: Hinweis statt Knopf', !str_contains($html, '<form') && str_contains($html, 'über deiner Grenze'));
$html = $freigabeZeile($w(['netto_gesamt' => 999]), 'freigeben');
$check('Zugführer unter Grenze: Knopf', str_contains($html, 'value="freigeben"'));
echo "Freigabe-Zeile (Mitglied):\n$html\n";

echo "$ok bestanden, $fail fehlgeschlagen\n";
