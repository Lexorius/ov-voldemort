<?php
declare(strict_types=1);
/* Rechte: wer darf einen Auftrag bearbeiten, und was zählt als Rücksprungziel */
$GLOBALS['settings'] = [];
$GLOBALS['rechte'] = [];
function db_all(string $sql, array $p = []): array { return []; }
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? false; }
function current_user(): ?array { return ['id' => 7]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'vehicles'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

$offen = ['id' => 1, 'status_final' => 0];
$fertig = ['id' => 2, 'status_final' => 1];

$GLOBALS['rechte'] = ['manage_vehicles' => true, 'report_vehicle' => true];
$check('Leitung: offen', order_editable($offen));
$check('Leitung: abgeschlossen', order_editable($fertig));

$GLOBALS['rechte'] = ['report_vehicle' => true];
$check('Melder: offen ja', order_editable($offen));
$check('Melder: abgeschlossen nein', !order_editable($fertig));
$check('Melder: ohne Statusangabe wie offen', order_editable(['id' => 3]));

$GLOBALS['rechte'] = [];
$check('ohne Melderecht: nichts', !order_editable($offen) && !order_editable($fertig));

// Rücksprung nach einer Wunsch-Aktion: dieselbe Regel wie beim Anmelden
$quelle = (string)file_get_contents($app . '/src/pages/wish_action.php');
$check('Wunsch-Aktion prüft den Rücksprung', str_contains($quelle, 'url_ist_intern($back)')
    && !str_contains($quelle, "str_starts_with(\$back, '/')"));
$check('Auftrag bearbeiten prüft die Freigabe', str_contains(
    (string)file_get_contents($app . '/src/pages/vehicle_order_edit.php'), 'order_editable($order)'));
$check('Ansicht und Seite nutzen dieselbe Regel', str_contains(
    (string)file_get_contents($app . '/views/vehicle_order.php'), 'order_editable($order)'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
