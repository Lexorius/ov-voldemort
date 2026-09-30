<?php
declare(strict_types=1);
// Minimale Datenbank-Attrappe für Tests, die nur reine Funktionen prüfen
$GLOBALS['settings'] = $GLOBALS['settings'] ?? ['waehrung' => 'EUR'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 0; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function db_lock(string $n, int $w = 0): bool { $GLOBALS['locks'][] = ['lock', $n, $w]; return !in_array($n, $GLOBALS['besetzt'] ?? [], true); }
function db_unlock(string $n): void { $GLOBALS['locks'][] = ['unlock', $n]; }
function current_user(): ?array { return ['id' => 1, 'role' => 'admin', 'display_name' => 'Tester']; }
function can(string $what, mixed $ctx = null): bool { return true; }
final class StubStmt2 { public function execute(array $p): bool { return true; } }
final class StubPdo2 { public function prepare(string $s): StubStmt2 { return new StubStmt2(); } }
function db(): StubPdo2 { return new StubPdo2(); }
