<?php
declare(strict_types=1);
/* Bearbeitungsstand an Divera zurückmelden */
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'divera_aktiv' => '1', 'divera_accesskey' => 'system',
    'divera_personal_key' => 'persoenlich', 'divera_status_bearbeitung' => 'freigegeben, eingeplant',
    'divera_status_abgeschlossen' => 'bestellt'];
$GLOBALS['inserts'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['zeilen'] = [];
$GLOBALS['vorhanden'] = null;

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM wishes') || str_contains($sql, 'FROM talking_points')) {
        return $GLOBALS['zeilen'];
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return $GLOBALS['vorhanden']; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $what, mixed $ctx = null): bool { return true; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
$quelle = (string)file_get_contents($app . '/src/lib/divera.php');
$quelle = (string)preg_replace('/function divera_request\(.*?\n}\n/s', '', $quelle, 1);
file_put_contents(__DIR__ . '/alt/divera_status_ohne_request.php', $quelle);

$GLOBALS['anfragen'] = [];
$GLOBALS['ablehnen'] = false;
$GLOBALS['eintraege'] = [];
function divera_request(string $path, array $query = [], ?string $key = null, ?array $post = null): array {
    $GLOBALS['anfragen'][] = [$path, $key, $post];
    if ($post !== null) {
        if ($GLOBALS['ablehnen']) { throw new DiveraException('Divera antwortete mit HTTP 403: Forbidden'); }
        return ['success' => true, 'status' => $post['Report']['status']];
    }
    return ['success' => true, 'data' => ['items' => $GLOBALS['eintraege'], 'total' => count($GLOBALS['eintraege'])]];
}
require __DIR__ . '/alt/divera_status_ohne_request.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- Reihenfolge ---------- */
$check('neu → Weitergeleitet', divera_status_weiter(null, 6));
$check('ungelesen → Weitergeleitet', divera_status_weiter(0, 6));
$check('Weitergeleitet → In Bearbeitung', divera_status_weiter(6, 2));
$check('In Bearbeitung → Abgeschlossen', divera_status_weiter(2, 4));
$check('nicht zurück: Abgeschlossen → In Bearbeitung', !divera_status_weiter(4, 2));
$check('nicht zurück: In Bearbeitung → Weitergeleitet', !divera_status_weiter(2, 6));
$check('nicht zurück: Archiviert → Abgeschlossen', !divera_status_weiter(5, 4));
$check('gleich bleibt', !divera_status_weiter(4, 4));
$check('unbekannter Status nie', !divera_status_weiter(null, 9));

/* ---------- Zielstatus ---------- */
$b = divera_slugs('freigegeben, eingeplant');
$a = divera_slugs('Bestellt');
$check('Slugs getrimmt und klein', $b === ['freigegeben', 'eingeplant'] && $a === ['bestellt']);
$check('Wunsch neu → Weitergeleitet', divera_zielstatus_wunsch(['status_slug' => 'neu', 'status_final' => 0], $b, $a) === 6);
$check('Wunsch freigegeben → In Bearbeitung', divera_zielstatus_wunsch(['status_slug' => 'freigegeben', 'status_final' => 0], $b, $a) === 2);
$check('Wunsch bestellt → Abgeschlossen', divera_zielstatus_wunsch(['status_slug' => 'bestellt', 'status_final' => 0], $b, $a) === 4);
$check('Wunsch abgelehnt (final) → Abgeschlossen', divera_zielstatus_wunsch(['status_slug' => 'abgelehnt', 'status_final' => 1], $b, $a) === 4);
$check('Wunsch ohne Status → Weitergeleitet', divera_zielstatus_wunsch([], $b, $a) === 6);
$check('Thema im Speicher → Weitergeleitet', divera_zielstatus_thema(['meeting_id' => null, 'status_final' => 0]) === 6);
$check('Thema auf Tagesordnung → In Bearbeitung', divera_zielstatus_thema(['meeting_id' => 3, 'status_final' => 0]) === 2);
$check('Thema besprochen → Abgeschlossen', divera_zielstatus_thema(['meeting_id' => 3, 'status_final' => 1]) === 4);

/* ---------- Abgleich ---------- */
$form = ['form_id' => '654', 'ziel' => 'wunsch', 'status_sync' => 1, 'name' => 'Wünsch dir was'];
$check('ausgeschaltet: nichts', divera_status_sync_form(['status_sync' => 0] + $form)['gemeldet'] === 0 && $GLOBALS['anfragen'] === []);

$GLOBALS['zeilen'] = [
    ['id' => 1, 'name' => 'Pumpe', 'divera_entry_id' => '9001', 'divera_status' => 0, 'status_slug' => 'neu', 'status_final' => 0],
    ['id' => 2, 'name' => 'Lampen', 'divera_entry_id' => '9002', 'divera_status' => 6, 'status_slug' => 'freigegeben', 'status_final' => 0],
    ['id' => 3, 'name' => 'Leiter', 'divera_entry_id' => '9003', 'divera_status' => 2, 'status_slug' => 'abgelehnt', 'status_final' => 1],
    ['id' => 4, 'name' => 'Zelt', 'divera_entry_id' => '9004', 'divera_status' => 4, 'status_slug' => 'freigegeben', 'status_final' => 0],
    ['id' => 5, 'name' => 'Funk', 'divera_entry_id' => '9005', 'divera_status' => 6, 'status_slug' => 'neu', 'status_final' => 0],
];
$res = divera_status_sync_form($form);
$posts = array_values(array_filter($GLOBALS['anfragen'], fn($a) => $a[2] !== null));
$check('drei Meldungen', $res['gemeldet'] === 3 && count($posts) === 3);
$check('Pfad und Feld', $posts[0][0] === '/v2/reports/9001/status' && $posts[0][2] === ['Report' => ['status' => 6]]);
$check('mit persönlichem Schlüssel', $posts[0][1] === 'persoenlich');
$check('freigegeben → 2', $posts[1][0] === '/v2/reports/9002/status' && $posts[1][2]['Report']['status'] === 2);
$check('abgelehnt → 4', $posts[2][0] === '/v2/reports/9003/status' && $posts[2][2]['Report']['status'] === 4);
$check('in Divera abgeschlossen bleibt', !in_array('/v2/reports/9004/status', array_column($posts, 0), true));
$check('unverändert bleibt', !in_array('/v2/reports/9005/status', array_column($posts, 0), true));
$check('gemerkt', $GLOBALS['execs'][0][1] === [6, 1] && str_contains($GLOBALS['execs'][0][0], 'UPDATE wishes'));
$logs = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'divera_log'));
$check('protokolliert', count($logs) === 3 && $logs[1][1]['wish_id'] === 2 && str_contains($logs[1][1]['message'], 'In Bearbeitung'));

// Fehler: abbrechen, protokollieren, nichts merken
$GLOBALS['anfragen'] = []; $GLOBALS['execs'] = []; $GLOBALS['inserts'] = []; $GLOBALS['ablehnen'] = true;
$res = divera_status_sync_form($form);
$posts = array_values(array_filter($GLOBALS['anfragen'], fn($a) => $a[2] !== null));
$check('bricht nach erstem Fehler ab', count($posts) === 1 && $res['gemeldet'] === 0 && str_contains($res['fehler'], '403'));
$check('nichts gemerkt', $GLOBALS['execs'] === []);
$check('Fehler protokolliert', $GLOBALS['inserts'][0][1]['status'] === 'fehler');
$GLOBALS['ablehnen'] = false;

// Obergrenze je Lauf
$GLOBALS['zeilen'] = [];
for ($i = 1; $i <= 30; $i++) {
    $GLOBALS['zeilen'][] = ['id' => $i, 'name' => "W$i", 'divera_entry_id' => (string)(100 + $i), 'divera_status' => null, 'status_slug' => 'neu', 'status_final' => 0];
}
$GLOBALS['anfragen'] = [];
$res = divera_status_sync_form($form);
$check('höchstens 25 je Lauf', $res['gemeldet'] === 25 && $res['offen'] === 5);

// Themen
$GLOBALS['zeilen'] = [['id' => 7, 'name' => 'Dienstplan', 'meeting_id' => 3, 'divera_entry_id' => 't1', 'divera_status' => 6, 'status_slug' => 'offen', 'status_final' => 0]];
$GLOBALS['anfragen'] = []; $GLOBALS['execs'] = []; $GLOBALS['inserts'] = [];
$res = divera_status_sync_form(['form_id' => '700', 'ziel' => 'thema', 'status_sync' => 1]);
$check('Thema → In Bearbeitung', $res['gemeldet'] === 1 && $GLOBALS['anfragen'][0][2]['Report']['status'] === 2);
$check('Thema gemerkt', str_contains($GLOBALS['execs'][0][0], 'UPDATE talking_points'));
$check('Protokoll mit tp_id', $GLOBALS['inserts'][0][1]['tp_id'] === 7 && $GLOBALS['inserts'][0][1]['wish_id'] === null);

/* ---------- Import: Status mitlesen ---------- */
$check('Status aus dem Eintrag', divera_entry_from_row(['id' => 1, 'status' => '4', 'fields' => []])['status'] === 4);
$check('ohne Status: null', divera_entry_from_row(['id' => 1, 'fields' => []])['status'] === null);

$GLOBALS['eintraege'] = [['id' => 9001, 'status' => 4, 'fields' => []]];
$GLOBALS['vorhanden'] = ['id' => 1, 'divera_status' => 6];
$GLOBALS['execs'] = [];
divera_import_form(['id' => 1, 'form_id' => '654', 'ziel' => 'wunsch', 'field_map' => '{}'], null);
$check('von Hand abgeschlossen wird übernommen', ($GLOBALS['execs'][0][1] ?? null) === [4, 1]);
$GLOBALS['eintraege'] = [['id' => 9001, 'status' => 1, 'fields' => []]];
$GLOBALS['execs'] = [];
divera_import_form(['id' => 1, 'form_id' => '654', 'ziel' => 'wunsch', 'field_map' => '{}'], null);
$check('„Gelesen“ überschreibt Weitergeleitet nicht', !array_filter($GLOBALS['execs'], fn($e) => str_contains($e[0], 'divera_status')));

echo "$ok bestanden, $fail fehlgeschlagen\n";
