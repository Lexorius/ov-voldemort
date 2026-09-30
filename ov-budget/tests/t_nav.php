<?php
declare(strict_types=1);
/* Menüleiste: Reihenfolge, Verschieben, Rechte, Ansicht */
session_start();
$GLOBALS['settings'] = ['wunsch_modul_name' => 'Wünsche', 'funk_modul_name' => 'Funk'];
$GLOBALS['state'] = [];
$GLOBALS['rechte'] = ['view_vehicles' => true, 'admin' => true];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return str_contains($sql, 'FROM settings') ? ($GLOBALS['state'][$p[0] ?? ''] ?? $d) : $d; }
function db_exec(string $sql, array $p = []): int { if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = (string)$p[1]; } return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? false; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'nav'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

$module = nav_module();
$check('zwölf Module, Übersicht zuerst, Verwaltung zuletzt', count($module) === 12
    && array_key_first($module) === 'dashboard' && array_key_last($module) === 'admin');
$check('Modulnamen aus den Einstellungen', $module['wishes']['label'] === 'Wünsche' && $module['radios']['label'] === 'Funk');

$sortiert = nav_sortieren($module, 'vehicles, admin, unbekannt, vehicles');
$check('Reihenfolge: genannte zuerst, Rest in Vorgabe, Unbekanntes und Doppeltes übergangen',
    array_slice(array_keys($sortiert), 0, 3) === ['vehicles', 'admin', 'dashboard'] && count($sortiert) === 12);
$check('leere Reihenfolge = Vorgabe', array_keys(nav_sortieren($module, '')) === array_keys($module));

$keys = ['a', 'b', 'c'];
$check('nach oben', nav_verschieben($keys, 'b', -1) === ['b', 'a', 'c']);
$check('nach unten', nav_verschieben($keys, 'b', 1) === ['a', 'c', 'b']);
$check('am Rand bleibt es', nav_verschieben($keys, 'a', -1) === $keys && nav_verschieben($keys, 'c', 1) === $keys);
$check('unbekannt bleibt', nav_verschieben($keys, 'x', 1) === $keys);

$leiste = nav_leiste();
$check('Leiste nur mit Rechten: keine Kontakte, aber Fahrzeuge und Verwaltung',
    isset($leiste['vehicles'], $leiste['admin'], $leiste['dashboard']) && !isset($leiste['contacts']) && !isset($leiste['radios']));
$GLOBALS['state']['nav_reihenfolge'] = 'admin,dashboard';
$check('gespeicherte Reihenfolge wirkt', array_keys(nav_leiste())[0] === 'admin');

$html = render_partial('admin/nav', ['module' => nav_sortieren($module, 'admin'), 'eigene' => true]);
$check('Verwaltungsseite rendert mit Pfeilen und Rücksetzen', substr_count($html, 'value="hoch"') === 12
    && str_contains($html, 'Vorgabereihenfolge wiederherstellen') && str_contains($html, 'Verwaltung'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
