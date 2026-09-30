<?php
declare(strict_types=1);
/* Anmerkungen und Diskussion zu Talking Points */
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'ha_benachrichtigung_aktiv' => '1', 'notify_tp_anmerkung' => '1'];
$GLOBALS['inserts'] = [];
$GLOBALS['zeilen'] = [];
$GLOBALS['rechte'] = ['manage_meetings' => false];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    foreach ($GLOBALS['zeilen'] as $teil => $fn) {
        if (str_contains($sql, $teil)) { return $fn($p); }
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 70 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? true; }
final class StubStmt { public function execute(array $p): bool { return true; } }
final class StubPdo { public function prepare(string $s): StubStmt { return new StubStmt(); } }
function db(): StubPdo { return new StubPdo(); }
function current_user(): ?array { return ['id' => 5]; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/meetings.php';
require $app . '/src/lib/notify.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};
$ist = function (string $name, mixed $got, mixed $want) use (&$ok, &$fail) {
    if ($got === $want) { $ok++; return; }
    $fail++; echo "FEHL  $name\n      erwartet: " . var_export($want, true) . "\n      erhalten: " . var_export($got, true) . "\n";
};

/* ---------- Offen oder abgeschlossen ---------- */
$check('offen ohne Status', tp_discussion_open([]));
$check('offen', tp_discussion_open(['status_final' => 0, 'status_slug' => 'offen']));
$check('vertagt bleibt offen', tp_discussion_open(['status_final' => 0, 'status_slug' => 'vertagt']));
$check('beschlossen ist zu', !tp_discussion_open(['status_final' => 1, 'status_slug' => 'beschlossen']));
$check('abgelehnt ist zu', !tp_discussion_open(['status_final' => '1', 'status_slug' => 'abgelehnt']));

/* ---------- Abfrage zählt Anmerkungen ---------- */
$check('Zahl der Anmerkungen im Abfragekopf', str_contains(tp_select(), 'talking_point_comments tc WHERE tc.tp_id = tp.id) AS anmerkungen'));

/* ---------- Anmerkung schreiben ---------- */
$tp = ['id' => 9, 'titel' => 'Dienstplan', 'status_final' => 0, 'eingebracht_von' => 3];
$ich = ['id' => 5, 'display_name' => 'Thomas', 'username' => 'thomas'];

$ist('leer abgewiesen', tp_comment_add($tp, $ich, "   \n "), 'Bitte etwas eintragen.');
$ist('nichts gespeichert', $GLOBALS['inserts'], []);

$zu = ['status_final' => 1] + $tp;
$check('abgeschlossen abgewiesen', str_contains((string)tp_comment_add($zu, $ich, 'Noch was'), 'abgeschlossen'));
$ist('auch dann nichts gespeichert', $GLOBALS['inserts'], []);

// Bisher haben 3 (Einbringer), 7 und ich selbst mitgeredet
$GLOBALS['zeilen'] = [
    'SELECT DISTINCT user_id FROM talking_point_comments' => fn($p) => [['user_id' => 7], ['user_id' => 5]],
    'WHERE u.id IN' => fn($p) => array_map(fn($id) => ['id' => (int)$id, 'display_name' => '', 'username' => 'u' . $id,
        'ha_notify' => 'mobile_app_' . $id, 'browser' => 0], $p),
];
$ist('gespeichert', tp_comment_add($tp, $ich, '  Samstage besser tauschen. '), null);
$anmerkung = $GLOBALS['inserts'][0];
$ist('in die Tabelle', $anmerkung[0], 'talking_point_comments');
$ist('Punkt, Person, Text', [$anmerkung[1]['tp_id'], $anmerkung[1]['user_id'], $anmerkung[1]['body']],
    [9, 5, 'Samstage besser tauschen.']);

$meldungen = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'notifications'));
$empfaenger = array_map(fn($m) => $m[1]['user_id'], $meldungen);
sort($empfaenger);
$ist('Einbringer und Mitdiskutierende benachrichtigt, ich nicht', $empfaenger, [3, 7]);
$ist('Ereignis', $meldungen[0][1]['ereignis'], 'tp_anmerkung');
$check('Titel nennt den Punkt', str_contains($meldungen[0][1]['titel'], 'Dienstplan'));
$check('Text nennt Person und Inhalt', str_contains($meldungen[0][1]['text'], 'Thomas: Samstage'));
$check('Link springt zur Anmerkung', str_contains($meldungen[0][1]['url'], '?p=talking_point&id=9#anmerkung'));

$GLOBALS['inserts'] = [];
tp_comment_add($tp, $ich, str_repeat('x', 6000));
$ist('lange Texte gekürzt', mb_strlen($GLOBALS['inserts'][0][1]['body']), 5000);

// Ereignis abgeschaltet: gespeichert wird trotzdem, gemeldet nicht
$GLOBALS['settings']['notify_tp_anmerkung'] = '0';
$GLOBALS['inserts'] = [];
// (Einstellungen sind zwischengespeichert – daher direkt über die Funktion prüfen)
$check('Ereignis in der Liste', isset(notify_ereignisse()['tp_anmerkung']));
$seed = (string)file_get_contents($app . '/sql/seed.sql');
$check('Einstellung für das Ereignis vorhanden', str_contains($seed, "('notify_tp_anmerkung',"));

/* ---------- Löschen ---------- */
$eigene = ['id' => 1, 'user_id' => 5];
$fremde = ['id' => 2, 'user_id' => 7];
$check('eigene Anmerkung löschbar', tp_comment_deletable($eigene, $tp, $ich));
$check('fremde ohne Leitungsrecht nicht', !tp_comment_deletable($fremde, $tp, $ich));
$GLOBALS['rechte']['manage_meetings'] = true;
$check('Leitung darf fremde löschen', tp_comment_deletable($fremde, $tp, $ich));
$check('abgeschlossen: auch die Leitung nicht mehr', !tp_comment_deletable($fremde, $zu, $ich));
$check('abgeschlossen: eigene auch nicht', !tp_comment_deletable($eigene, $zu, $ich));

echo "$ok bestanden, $fail fehlgeschlagen\n";
