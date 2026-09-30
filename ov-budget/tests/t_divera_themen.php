<?php
declare(strict_types=1);
/* Themen für Besprechungen über ein Divera-Formular einreichen */
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'divera_aktiv' => '1', 'divera_accesskey' => 'system',
    'divera_personal_key' => 'persoenlich', 'mwst_satz' => '19'];
require __DIR__ . '/stub_db.php';
$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';

@mkdir(__DIR__ . '/alt');
$quelle = (string)file_get_contents($app . '/src/lib/divera.php');
$quelle = (string)preg_replace('/function divera_request\(.*?\n}\n/s', '', $quelle, 1);
file_put_contents(__DIR__ . '/alt/divera_themen_ohne_request.php', $quelle);

function feld(string $name, string $typ = 'textinput', array $optionen = []): array {
    return ['id' => 'f-' . md5($name), 'name' => $name, 'type' => $typ, 'options' => $optionen, 'required' => '0'];
}
function thema_eintrag(string $id, array $werte, int $anhaenge = 0): array {
    $felder = [];
    foreach ($werte as $name => $wert) {
        $felder[] = ['field' => $name === 'Priorität'
            ? feld($name, 'radio', [['id' => 'o-h', 'name' => 'hoch'], ['id' => 'o-n', 'name' => 'normal']])
            : feld($name), 'value' => $wert];
    }
    return ['id' => $id, 'cluster_id' => 70, 'user_cluster_relation_id' => 5, 'reporttype_id' => 700,
        'attachment_count' => $anhaenge, 'attachment' => [], 'address' => '', 'fields' => $felder, 'ts_create' => 1758200000];
}
$GLOBALS['eintraege'] = [
    thema_eintrag('t1', ['Thema' => 'Neue Dienstplanung', 'Beschreibung' => 'Samstage besser verteilen',
        'Priorität' => 'o-h', 'Zeitbedarf (Minuten)' => '20', 'Eingereicht von' => 'Anna Beispiel', 'Rückfragen an' => '0151 123'], 2),
    thema_eintrag('t2', ['Thema' => '', 'Beschreibung' => 'ohne Titel', 'Eingereicht von' => 'Unbekannt Person']),
];
function divera_request(string $path, array $query = [], ?string $key = null): array {
    if (preg_match('#/v2/reporttypes/(\d+)/reports$#', $path)) {
        return ['success' => true, 'data' => ['items' => array_slice($GLOBALS['eintraege'], (int)($query['offset'] ?? 0), 50),
            'total' => count($GLOBALS['eintraege'])]];
    }
    throw new RuntimeException('HTTP 404 für ' . $path);
}
require __DIR__ . '/alt/divera_themen_ohne_request.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- Ziel und Zuordnungsfelder ---------- */
$check('Ziel Vorgabe wunsch', divera_ziel([]) === 'wunsch' && divera_ziel(['ziel' => 'x']) === 'wunsch');
$check('Ziel thema', divera_ziel(['ziel' => 'thema']) === 'thema');
$check('Themenfelder', array_keys(divera_map_targets('thema')) === ['titel', 'beschreibung', 'fachgruppe', 'prioritaet', 'dauer_min', 'einbringer']);
$check('Wunschfelder unverändert', isset(divera_map_targets()['bezeichnung']) && !isset(divera_map_targets()['titel']));

$namen = ['Thema', 'Beschreibung', 'Priorität', 'Zeitbedarf (Minuten)', 'Eingereicht von', 'Rückfragen an'];
$v = divera_suggest_map($namen, 'thema');
$check('Vorschlag Titel', ($v['titel'] ?? '') === 'Thema');
$check('Vorschlag Beschreibung', ($v['beschreibung'] ?? '') === 'Beschreibung');
$check('Vorschlag Priorität', ($v['prioritaet'] ?? '') === 'Priorität');
$check('Vorschlag Dauer', ($v['dauer_min'] ?? '') === 'Zeitbedarf (Minuten)');
$check('Vorschlag Einbringer', ($v['einbringer'] ?? '') === 'Eingereicht von');
$check('Rückfragen nicht vergeben', !in_array('Rückfragen an', $v, true));
$check('Wunsch-Vorschlag bleibt', (divera_suggest_map(['Bezeichnung'])['bezeichnung'] ?? '') === 'Bezeichnung');

/* ---------- Eintrag → Talking Point ---------- */
$form = ['form_id' => '700', 'ziel' => 'thema', 'default_status_id' => null, 'default_fachgruppe_id' => 4];
$e = divera_fetch_entries('700');
$tp = divera_entry_to_tp($e[0], $v, $form, ['anna beispiel' => 17]);
$check('Titel', $tp['titel'] === 'Neue Dienstplanung');
$check('Beschreibung', str_starts_with($tp['beschreibung'], 'Samstage besser verteilen'));
$check('Restfeld erhalten', str_contains($tp['beschreibung'], 'Rückfragen an: 0151 123'));
$check('Anhänge genannt', str_contains($tp['beschreibung'], '2 Datei(en)'));
$check('Option als Text genutzt (nicht gefunden → Vorgabe)', array_key_exists('prioritaet_id', $tp));
$check('Dauer', $tp['dauer_min'] === 20);
$check('in den Themenspeicher', $tp['meeting_id'] === null);
$check('Fachgruppe Vorgabe', $tp['fachgruppe_id'] === 4);
$check('Benutzer erkannt', $tp['eingebracht_von'] === 17 && $tp['einbringer_name'] === '');
$check('Herkunft', $tp['divera_form_id'] === '700' && $tp['divera_entry_id'] === 't1');

$tp2 = divera_entry_to_tp($e[1], $v, $form, ['anna beispiel' => 17]);
$check('Ersatztitel', $tp2['titel'] === 'Thema aus Divera t2');
$check('Name ohne Konto', $tp2['eingebracht_von'] === null && $tp2['einbringer_name'] === 'Unbekannt Person');
$check('ohne Dauer', $tp2['dauer_min'] === null);
$lang = divera_entry_to_tp(divera_entry_from_row(thema_eintrag('t3', ['Thema' => 'x', 'Zeitbedarf (Minuten)' => '5000'])), $v, $form);
$check('Dauer gedeckelt', $lang['dauer_min'] === 600);

/* ---------- Import ---------- */
$GLOBALS['inserts'] = [];
$res = divera_import_form(['id' => 3] + $form + ['field_map' => json_encode($v)], 1);
$check('zwei neue Themen', $res['created'] === 2 && $res['failed'] === 0);
$tabellen = array_column($GLOBALS['inserts'], 0);
$check('in talking_points, nicht wishes', count(array_keys($tabellen, 'talking_points')) === 2 && !in_array('wishes', $tabellen, true));
$log = array_values(array_filter($GLOBALS['inserts'], fn($i) => $i[0] === 'divera_log'));
$check('Protokoll mit tp_id', count($log) === 2 && $log[0][1]['tp_id'] !== null && $log[0][1]['wish_id'] === null);
$check('Protokolltext', str_starts_with($log[0][1]['message'], 'Thema eingereicht: Neue Dienstplanung'));

$GLOBALS['inserts'] = [];
$res = divera_import_form(['id' => 3] + $form + ['field_map' => json_encode($v)], 1, true);
$check('Vorschau legt nichts an', $res['created'] === 2 && $GLOBALS['inserts'] === [] && $res['preview'][0]['titel'] === 'Neue Dienstplanung');

$GLOBALS['inserts'] = [];
$wunsch = ['id' => 4, 'form_id' => '700', 'ziel' => 'wunsch', 'field_map' => '{}', 'default_status_id' => null, 'default_fachgruppe_id' => null];
divera_import_form($wunsch, 1);
$check('Wunschformular legt Wünsche an', in_array('wishes', array_column($GLOBALS['inserts'], 0), true));

echo "$ok bestanden, $fail fehlgeschlagen\n";
