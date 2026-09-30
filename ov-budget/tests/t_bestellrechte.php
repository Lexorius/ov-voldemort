<?php
declare(strict_types=1);
// Bestellberechtigungen nach Rolle und Funktion, mit gestubbter Datenbank
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'bestell_eigene_freigeben' => '1'];
$GLOBALS['funktionen'] = [];   // user_id => [function_id]
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
// Funktionen: 10 OB, 11 stellv. OB, 12 Zugführer, 13 Verwaltung
$GLOBALS['rechte'] = [
    ['rolle' => 'admin',   'funktion_id' => null, 'darf_freigeben' => 1, 'freigabe_grenze' => null,     'darf_bestellen' => 1],
    ['rolle' => 'leitung', 'funktion_id' => null, 'darf_freigeben' => 0, 'freigabe_grenze' => null,     'darf_bestellen' => 0],
    ['rolle' => 'user',    'funktion_id' => null, 'darf_freigeben' => 0, 'freigabe_grenze' => null,     'darf_bestellen' => 0],
    ['rolle' => null, 'funktion_id' => 10, 'darf_freigeben' => 1, 'freigabe_grenze' => null,     'darf_bestellen' => 0],
    ['rolle' => null, 'funktion_id' => 12, 'darf_freigeben' => 1, 'freigabe_grenze' => '500.00', 'darf_bestellen' => 0],
    ['rolle' => null, 'funktion_id' => 11, 'darf_freigeben' => 1, 'freigabe_grenze' => '2000.00', 'darf_bestellen' => 0],
    ['rolle' => null, 'funktion_id' => 13, 'darf_freigeben' => 0, 'freigabe_grenze' => null,     'darf_bestellen' => 1],
];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM list_items')) {
        $r = [];
        foreach (['neu' => 1, 'freigegeben' => 55, 'bestellt' => 6, 'beschafft' => 7] as $slug => $id) {
            $r[] = ['id' => $id, 'list_key' => 'wunsch_status', 'label' => ucfirst($slug), 'slug' => $slug, 'color' => '#123456',
                    'weight' => 0, 'is_default' => 0, 'is_final' => $slug === 'beschafft' ? 1 : 0, 'is_active' => 1, 'sort_order' => $id];
        }
        foreach ([10 => 'Ortsbeauftragte:r', 11 => 'stellv. OB', 12 => 'Zugführer:in', 13 => 'Verwaltung', 14 => 'Koch'] as $id => $l) {
            $r[] = ['id' => $id, 'list_key' => 'funktion', 'label' => $l, 'slug' => 'f' . $id, 'color' => '', 'weight' => 0,
                    'is_default' => 0, 'is_final' => 0, 'is_active' => 1, 'sort_order' => $id];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM bestell_rechte')) {
        return $GLOBALS['rechte'];
    }
    if (str_contains($sql, 'FROM user_functions WHERE user_id')) {
        return array_map(static fn($f) => ['function_id' => $f], $GLOBALS['funktionen'][$p[0]] ?? []);
    }
    if (str_contains($sql, 'FROM users u')) {
        $out = [];
        foreach ([[1, 'admin', 'Admin'], [2, 'leitung', 'Leitung ohne Funktion'], [3, 'user', 'Olga OB'],
                  [4, 'user', 'Zora Zugführerin'], [5, 'user', 'Viktor Verwaltung'], [6, 'leitung', 'Stella Stellv']] as [$id, $role, $name]) {
            $f = $GLOBALS['funktionen'][$id] ?? [];
            $out[] = ['id' => $id, 'role' => $role, 'name' => $name, 'funktionen' => $f ? implode(',', $f) : null];
        }
        return $out;
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null): mixed { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
final class StubStmt { public function execute(array $p): bool { return true; } }
final class StubPdo { public function prepare(string $s): StubStmt { return new StubStmt(); } }
function db(): StubPdo { return new StubPdo(); }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/wishes.php';
require $app . '/src/lib/order_rights.php';
// user_functions() aus auth.php, ohne current_user-Anmeldelogik
function user_functions(int $userId): array {
    return array_map(static fn($r) => (int)$r['function_id'], db_all('SELECT function_id FROM user_functions WHERE user_id = ?', [$userId]));
}
function current_user(): ?array { return $GLOBALS['ich'] ?? null; }

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

$GLOBALS['funktionen'] = [3 => [10], 4 => [12, 14], 5 => [13], 6 => [11, 12]];
$u = static fn(int $id, string $role) => ['id' => $id, 'role' => $role, 'display_name' => 'x'];
$wunsch = static fn(float $betrag, array $x = []) => $x + ['id' => 9, 'bezeichnung' => 'Pumpe', 'netto_gesamt' => $betrag,
    'status_slug' => 'neu', 'status_final' => 0, 'status_id' => 1, 'created_by' => 99];

// order_rights_combine (rein)
$c = order_rights_combine([]);
$check('leer: nichts', !$c['freigeben'] && !$c['bestellen'] && $c['grenze'] === 0.0);
$c = order_rights_combine([['label' => 'A', 'row' => ['darf_freigeben' => 1, 'freigabe_grenze' => '300', 'darf_bestellen' => 0]],
                           ['label' => 'B', 'row' => ['darf_freigeben' => 1, 'freigabe_grenze' => '800', 'darf_bestellen' => 0]]]);
$check('höchste Grenze gilt', $c['grenze'] === 800.0 && $c['freigabe_durch'] === ['A', 'B']);
$c = order_rights_combine([['label' => 'A', 'row' => ['darf_freigeben' => 1, 'freigabe_grenze' => null, 'darf_bestellen' => 0]],
                           ['label' => 'B', 'row' => ['darf_freigeben' => 1, 'freigabe_grenze' => '800', 'darf_bestellen' => 0]]]);
$check('unbegrenzt schlägt Grenze', $c['grenze'] === null);
$c = order_rights_combine([['label' => 'B', 'row' => ['darf_freigeben' => 1, 'freigabe_grenze' => '800', 'darf_bestellen' => 0]],
                           ['label' => 'A', 'row' => ['darf_freigeben' => 1, 'freigabe_grenze' => null, 'darf_bestellen' => 0]]]);
$check('unbegrenzt unabhängig von Reihenfolge', $c['grenze'] === null);
$c = order_rights_combine([['label' => 'A', 'row' => ['darf_freigeben' => 0, 'freigabe_grenze' => null, 'darf_bestellen' => 1]]]);
$check('Grenze ohne Freigaberecht zählt nicht', !$c['freigeben'] && $c['grenze'] === 0.0 && $c['bestellen']);
$c = order_rights_combine([['label' => 'A', 'row' => ['darf_freigeben' => 1, 'freigabe_grenze' => '0.00', 'darf_bestellen' => 0]]]);
$check('Grenze 0', $c['freigeben'] && $c['grenze'] === 0.0);

// Rollen und Funktionen
$r = order_rights_for_user($u(3, 'user'));
$check('Mitglied mit Funktion OB darf unbegrenzt', $r['freigeben'] && $r['grenze'] === null && !$r['bestellen']);
$check('Quelle OB genannt', $r['freigabe_durch'] === ['Ortsbeauftragte:r']);
$r = order_rights_for_user($u(2, 'leitung'));
$check('Leitung ohne Funktion: nichts (so eingestellt)', !$r['freigeben'] && !$r['bestellen']);
$r = order_rights_for_user($u(4, 'user'));
$check('Zugführerin bis 500', $r['freigeben'] && $r['grenze'] === 500.0);
$r = order_rights_for_user($u(5, 'user'));
$check('Verwaltung darf bestellen, nicht freigeben', !$r['freigeben'] && $r['bestellen']);
$r = order_rights_for_user($u(6, 'leitung'));
$check('stellv. OB + Zugführer: höhere Grenze', $r['grenze'] === 2000.0);
$r = order_rights_for_user($u(1, 'admin'));
$check('Admin per Rolle', $r['freigeben'] && $r['bestellen'] && $r['freigabe_durch'] === ['Administration']);
$r = order_rights_for_user($u(77, 'user'));
$check('Mitglied ohne Funktion: nichts', !$r['freigeben'] && !$r['bestellen']);

// wish_release_denied
$check('OB gibt 10.000 frei', wish_release_denied($wunsch(10000), $u(3, 'user')) === null);
$check('Zugführerin 500 genau ok', wish_release_denied($wunsch(500), $u(4, 'user')) === null);
$grund = wish_release_denied($wunsch(500.01), $u(4, 'user'));
$check('Zugführerin 500,01 abgelehnt', $grund !== null && str_contains($grund, 'Freigabegrenze liegt bei 500,00'));
$check('Verwaltung darf nicht freigeben', str_contains((string)wish_release_denied($wunsch(10), $u(5, 'user')), 'keine Berechtigung'));
$check('bereits freigegeben', str_contains((string)wish_release_denied($wunsch(10, ['status_slug' => 'freigegeben']), $u(3, 'user')), 'bereits'));
$check('abgeschlossen', wish_release_denied($wunsch(10, ['status_slug' => 'beschafft', 'status_final' => 1]), $u(3, 'user')) !== null);
$check('eigener Wunsch erlaubt', wish_release_denied($wunsch(10, ['created_by' => 3]), $u(3, 'user'), true) === null);
$check('eigener Wunsch: Vier Augen', str_contains((string)wish_release_denied($wunsch(10, ['created_by' => 3]), $u(3, 'user'), false), 'andere Person'));
$check('fremder Wunsch mit Vier Augen ok', wish_release_denied($wunsch(10), $u(3, 'user'), false) === null);

// Statuswechsel über Formular
$GLOBALS['settings']['bestell_eigene_freigeben'] = '1';
$check('Formular: anderer Status egal', wish_status_change_denied(null, 1, 99999, $u(5, 'user')) === null);
$check('Formular: freigeben ohne Recht', wish_status_change_denied(['status_id' => 1, 'netto_gesamt' => 10, 'created_by' => 9], 55, 10, $u(5, 'user')) !== null);
$check('Formular: freigeben mit Recht', wish_status_change_denied(['status_id' => 1, 'netto_gesamt' => 10, 'created_by' => 9], 55, 10, $u(3, 'user')) === null);
$check('Formular: über Grenze', wish_status_change_denied(['status_id' => 1, 'netto_gesamt' => 900, 'created_by' => 9], 55, 900, $u(4, 'user')) !== null);
$bleibt = ['status_id' => 55, 'netto_gesamt' => 400, 'created_by' => 9];
$check('Formular: freigegeben bleibt, Betrag gleich – jeder', wish_status_change_denied($bleibt, 55, 400, $u(77, 'user')) === null);
$check('Formular: günstiger – jeder', wish_status_change_denied($bleibt, 55, 350, $u(77, 'user')) === null);
$grund = wish_status_change_denied($bleibt, 55, 450, $u(77, 'user'));
$check('Formular: teurer ohne Recht', $grund !== null && str_contains($grund, 'über dem freigegebenen'));
$check('Formular: teurer innerhalb Grenze', wish_status_change_denied($bleibt, 55, 450, $u(4, 'user')) === null);
$check('Formular: teurer über Grenze', wish_status_change_denied($bleibt, 55, 600, $u(4, 'user')) !== null);

// Wer darf?
$check('Ansprechpartner 300', order_release_people(300) === ['Admin', 'Olga OB', 'Zora Zugführerin', 'Stella Stellv']);
$check('Ansprechpartner 1500', order_release_people(1500) === ['Admin', 'Olga OB', 'Stella Stellv']);
$check('Ansprechpartner 5000', order_release_people(5000) === ['Admin', 'Olga OB']);
$check('Ansprechpartner ohne Antragsteller', order_release_people(5000, 3) === ['Admin']);

// Speichern aus der Verwaltung
$_POST = [
    'freigeben' => ['rolle_admin' => '1', 'funktion_10' => '1', 'funktion_12' => '1'],
    'grenze'    => ['funktion_12' => '1.500,50', 'funktion_10' => '', 'rolle_user' => 'abc'],
    'bestellen' => ['funktion_13' => '1'],
];
order_rights_save_from_post();
$check('Speichern: je Rolle und Funktion eine Zeile', count($GLOBALS['execs']) === 3 + 5);
$nach = [];
foreach ($GLOBALS['execs'] as [$sql, $p]) {
    $nach[$p[0] ?? 'f' . $p[1]] = array_slice($p, 2);
}
$check('Speichern: upsert', str_contains($GLOBALS['execs'][0][0], 'ON DUPLICATE KEY UPDATE'));
$check('Speichern: admin', $nach['admin'] === [1, null, 1 - 1]);
$check('Speichern: OB unbegrenzt', $nach['f10'] === [1, null, 0]);
$check('Speichern: deutscher Betrag', $nach['f12'] === [1, 1500.5, 0]);
$check('Speichern: Unsinn = unbegrenzt', $nach['user'] === [0, null, 0]);
$check('Speichern: Verwaltung bestellt', $nach['f13'] === [0, null, 1]);
$check('Speichern: nicht angekreuzt = 0', $nach['leitung'] === [0, null, 0] && $nach['f14'] === [0, null, 0]);

echo "$ok bestanden, $fail fehlgeschlagen\n";
