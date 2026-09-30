<?php
declare(strict_types=1);
/* Aufgaben aus Besprechungen: Vorbelegung, Zuständigkeit, Anlegen, Herkunft */
$GLOBALS['settings'] = ['waehrung' => 'EUR'];
$GLOBALS['inserts'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['zeilen'] = [];   // SQL-Teil => Zeile
$GLOBALS['audits'] = [];
$GLOBALS['post'] = [];

function db_all(string $sql, array $p = []): array { return []; }
function db_row(string $sql, array $p = []): ?array {
    foreach ($GLOBALS['zeilen'] as $teil => $fn) {
        if (str_contains($sql, $teil)) { return $fn($p); }
    }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) {
    foreach ($GLOBALS['zeilen'] as $teil => $fn) {
        if (str_contains($sql, $teil)) { $r = $fn($p); return $r ? reset($r) : $d; }
    }
    return $d;
}
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 100 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function audit(string $a, ?string $t = null, ?int $id = null, ?string $info = null): void { $GLOBALS['audits'][] = [$a, $t, $id, $info]; }
function can(string $what, mixed $ctx = null): bool { return true; }
function list_default_id(string $key): ?int { return ['todo_status' => 7, 'todo_prioritaet' => 3][$key] ?? null; }
function post_int(string $k, ?int $d = null): ?int { return isset($GLOBALS['post'][$k]) ? (int)$GLOBALS['post'][$k] : $d; }
function de_date(?string $d): string { return $d ? date('d.m.Y', strtotime($d)) : ''; }

$app = dirname(__DIR__);
// nur die benötigten Funktionen laden – list_default_id etc. kommen aus den Attrappen
foreach (['todos', 'meetings'] as $lib) {
    $q = (string)file_get_contents("$app/src/lib/$lib.php");
    file_put_contents(__DIR__ . "/alt/{$lib}_test.php", $q);
    require __DIR__ . "/alt/{$lib}_test.php";
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function list_items(string $k): array { return $k === 'fachgruppe' ? [['id' => 4, 'label' => 'B<1>'], ['id' => 5, 'label' => 'N']] : []; }

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- Zuständigkeit aus der Sammelauswahl ---------- */
$check('tp = wie der Punkt', todo_target_from_value('tp') === null);
$check('leer = wie der Punkt', todo_target_from_value('') === null);
$check('ov', todo_target_from_value('ov') === ['ov', null]);
$check('fachgruppe', todo_target_from_value('fachgruppe:12') === ['fachgruppe', 12]);
$check('funktion', todo_target_from_value('funktion:3') === ['funktion', 3]);
$check('user', todo_target_from_value('user:5') === ['user', 5]);
$check('Unsinn abgewiesen', todo_target_from_value('admin:1') === null);
$check('Einschleusung abgewiesen', todo_target_from_value("user:5 OR 1=1") === null);
$check('ohne id abgewiesen', todo_target_from_value('fachgruppe:') === null);

$opt = todo_target_options('fachgruppe');
$check('Optionen mit Wert', str_contains($opt, 'value="fachgruppe:4"') && str_contains($opt, 'value="fachgruppe:5"'));
$check('Optionen maskiert', str_contains($opt, 'B&lt;1&gt;'));

/* ---------- Vorbelegung ---------- */
$tp = ['id' => 9, 'meeting_id' => 2, 'titel' => 'Leiter prüfen lassen', 'ergebnis' => 'Prüfung bis Monatsende beauftragen',
    'beschreibung' => 'Prüffrist abgelaufen', 'verantwortlich' => 'ZTr', 'fachgruppe_id' => 4, 'prioritaet_id' => 11, 'todo_id' => null];
$meeting = ['id' => 2, 'titel' => 'Leitungsrunde', 'datum' => '2026-09-15'];
$v = todo_prefill_from_tp($tp, $meeting);
$check('Titel', $v['titel'] === 'Leiter prüfen lassen');
$check('Ergebnis vorn', str_starts_with($v['beschreibung'], 'Prüfung bis Monatsende beauftragen'));
$check('Hintergrund', str_contains($v['beschreibung'], "— Hintergrund —\nPrüffrist abgelaufen"));
$check('Herkunft', str_contains($v['beschreibung'], 'Aus: Leitungsrunde am 15.09.2026'));
$check('Verantwortlich', str_contains($v['beschreibung'], 'Verantwortlich laut Besprechung: ZTr'));
$check('Fachgruppe als Ziel', $v['target_type'] === 'fachgruppe' && $v['target_id'] === 4);
$check('Priorität vom Punkt', $v['prioritaet_id'] === 11);
$v2 = todo_prefill_from_tp(['titel' => str_repeat('x', 250), 'ergebnis' => '', 'beschreibung' => '', 'verantwortlich' => '',
    'fachgruppe_id' => null, 'prioritaet_id' => null], null);
$check('ohne Fachgruppe: OV', $v2['target_type'] === 'ov' && $v2['target_id'] === null);
$check('Priorität Vorgabe', $v2['prioritaet_id'] === 3);
$check('Titel gekürzt', mb_strlen($v2['titel']) === 200);
$check('leere Beschreibung', $v2['beschreibung'] === '');

/* ---------- Anlegen aus dem Punkt ---------- */
$GLOBALS['zeilen'] = [
    'FROM meetings' => fn($p) => $p[0] === 2 ? $meeting : null,
];
$id = tp_create_todo($tp, ['id' => 1]);
[$tab, $d] = $GLOBALS['inserts'][0];
$check('in todos', $tab === 'todos' && $id === 101);
$check('Besprechung gemerkt', $d['meeting_id'] === 2 && $d['talking_point_id'] === 9);
$check('Ziel wie der Punkt', $d['target_type'] === 'fachgruppe' && $d['target_id'] === 4);
$check('Status Vorgabe', $d['status_id'] === 7);
$check('ohne Frist', $d['faellig_am'] === null);
$check('Beschreibung mit Herkunft', str_contains($d['beschreibung'], 'Aus: Leitungsrunde'));
$check('todo_id nur wenn leer', str_contains($GLOBALS['execs'][0][0], 'todo_id IS NULL') && $GLOBALS['execs'][0][1] === [101, 9]);
$check('protokolliert', $GLOBALS['audits'][0][0] === 'tp.aufgabe');

$GLOBALS['inserts'] = [];
tp_create_todo($tp, ['id' => 1], ['target' => ['user', 5], 'faellig_am' => '2026-10-01']);
$d = $GLOBALS['inserts'][0][1];
$check('gemeinsame Person', $d['target_type'] === 'user' && $d['target_id'] === 5);
$check('gemeinsame Frist', $d['faellig_am'] === '2026-10-01');
$GLOBALS['inserts'] = [];
tp_create_todo($tp, ['id' => 1], ['target' => ['ov', null]]);
$d = $GLOBALS['inserts'][0][1];
$check('ganzer OV ohne id', $d['target_type'] === 'ov' && $d['target_id'] === null);

/* ---------- Herkunft aus dem Formular ---------- */
$GLOBALS['zeilen'] = [
    'FROM meetings' => fn($p) => $p[0] === 2 ? ['id' => 2] : null,
    'FROM talking_points' => fn($p) => $p[0] === 9 ? ['meeting_id' => 2] : ($p[0] === 10 ? ['meeting_id' => null] : null),
];
$GLOBALS['post'] = ['meeting_id' => 2, 'talking_point_id' => 9];
$check('beides gültig', todo_origin_from_post() === [2, 9]);
$GLOBALS['post'] = ['talking_point_id' => 9];
$check('Besprechung aus dem Punkt', todo_origin_from_post() === [2, 9]);
$GLOBALS['post'] = ['meeting_id' => 77, 'talking_point_id' => 55];
$check('unbekannte ids verworfen', todo_origin_from_post() === [null, null]);
$GLOBALS['post'] = ['meeting_id' => 2];
$check('freie Aufgabe der Besprechung', todo_origin_from_post() === [2, null]);
$GLOBALS['post'] = ['talking_point_id' => 10];
$check('Punkt im Themenspeicher', todo_origin_from_post() === [null, 10]);
$GLOBALS['post'] = [];
$check('ohne Herkunft', todo_origin_from_post() === [null, null]);

echo "$ok bestanden, $fail fehlgeschlagen\n";
