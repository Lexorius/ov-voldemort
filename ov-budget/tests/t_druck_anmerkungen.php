<?php
declare(strict_types=1);
/* Anmerkungen im Ausdruck von Tagesordnung und Protokoll */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'ov_name' => 'THW OV Musterstadt', 'tp_bezeichnung' => 'Talking Points'];
$GLOBALS['zeilen'] = [];
$GLOBALS['abfragen'] = [];

function db_all(string $sql, array $p = []): array {
    $GLOBALS['abfragen'][] = [$sql, $p];
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
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/view.php';
require $app . '/src/lib/meetings.php';
require $app . '/src/lib/attendance.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

/* ---------- Wann erscheinen Anmerkungen? ---------- */
$check('Vorgabe: im Protokoll ja', meeting_print_with_comments('protokoll', 'protokoll', null));
$check('Vorgabe: in der Tagesordnung nein', !meeting_print_with_comments('tagesordnung', 'protokoll', null));
$check('"beide": auch in der Tagesordnung', meeting_print_with_comments('tagesordnung', 'beide', null));
$check('"aus": auch im Protokoll nicht', !meeting_print_with_comments('protokoll', 'aus', null));
$check('Umschalter ein schlägt "aus"', meeting_print_with_comments('protokoll', 'aus', '1'));
$check('Umschalter aus schlägt Vorgabe', !meeting_print_with_comments('protokoll', 'protokoll', '0'));
$check('unbekannter Wert wie Vorgabe', meeting_print_with_comments('protokoll', 'quatsch', null)
    && !meeting_print_with_comments('tagesordnung', 'quatsch', null));
$check('alle Möglichkeiten beschriftet', array_keys(MEETING_DRUCK_ANMERKUNGEN) === ['protokoll', 'beide', 'aus']);

/* ---------- Anmerkungen gesammelt laden ---------- */
$check('ohne Punkte keine Abfrage', tp_comments_for([]) === []);
$GLOBALS['zeilen']['FROM talking_point_comments c'] = fn($p) => [
    ['id' => 1, 'tp_id' => 4, 'body' => 'Erste', 'autor' => 'Anna', 'created_at' => '2026-09-20 10:00:00'],
    ['id' => 2, 'tp_id' => 4, 'body' => 'Zweite', 'autor' => 'Ben', 'created_at' => '2026-09-21 11:00:00'],
    ['id' => 3, 'tp_id' => 6, 'body' => 'Andere', 'autor' => null, 'created_at' => '2026-09-21 12:00:00'],
];
$GLOBALS['abfragen'] = [];
$je = tp_comments_for([4, 6, 4, 0]);
$check('nach Punkt gruppiert', array_keys($je) === [4, 6] && count($je[4]) === 2);
$check('Reihenfolge bleibt', $je[4][0]['body'] === 'Erste' && $je[4][1]['body'] === 'Zweite');
$check('eine einzige Abfrage mit eindeutigen ids', count($GLOBALS['abfragen']) === 1 && $GLOBALS['abfragen'][0][1] === [4, 6]);

/* ---------- Ausdruck ---------- */
$meeting = ['id' => 2, 'typ_label' => null, 'titel' => 'Leitungsrunde', 'beschreibung' => '', 'notizen' => '', 'datum' => '2026-09-25',
    'beginn' => '19:00:00', 'ende' => null, 'ort' => 'OV', 'leitung' => '', 'protokoll_von' => '', 'teilnehmer' => ''];
$punkt = ['id' => 4, 'titel' => 'Dienstplan', 'beschreibung' => '', 'fachgruppe_label' => null, 'status_slug' => 'offen',
    'status_label' => 'Offen', 'ergebnis' => 'Samstage tauschen', 'verantwortlich' => ''];
$daten = ['meeting' => $meeting, 'punkte' => [$punkt], 'zeiten' => ['19:00'], 'gesamt' => 10,
    'teilnehmer' => [], 'aufgaben' => [], 'anmerkungen' => $je, 'mitNamen' => true];
$_SERVER['REQUEST_URI'] = '/?p=meeting_print&id=2';

$html = render_partial('meeting_print', $daten + ['art' => 'protokoll', 'mitAnmerkungen' => true]);
$check('Anmerkungen im Protokoll', str_contains($html, 'class="anm"') && str_contains($html, 'Erste') && str_contains($html, 'Zweite'));
$check('mit Namen und Tag', str_contains($html, 'Anna') && str_contains($html, '20.09.'));
$check('fremder Punkt nicht drin', !str_contains($html, 'Andere'));
$check('Umschalter "ohne" angeboten', str_contains($html, 'anmerkungen=0') && str_contains($html, 'art=protokoll'));
$check('Umschalter wird nicht gedruckt', str_contains($html, '.knopf, .leiste { display: none; }'));

$html = render_partial('meeting_print', array_merge($daten, ['art' => 'protokoll', 'mitAnmerkungen' => true, 'mitNamen' => false]));
$check('ohne Namen nur der Text', str_contains($html, 'Erste') && !str_contains($html, 'Anna'));

$html = render_partial('meeting_print', $daten + ['art' => 'protokoll', 'mitAnmerkungen' => false]);
$check('abgeschaltet: keine Anmerkungen', !str_contains($html, 'class="anm"') && !str_contains($html, 'Erste'));
$check('Umschalter "mit" angeboten', str_contains($html, 'anmerkungen=1'));

$html = render_partial('meeting_print', $daten + ['art' => 'tagesordnung', 'mitAnmerkungen' => true]);
$check('auch in der Tagesordnung, wenn gewünscht', str_contains($html, 'Erste'));
$check('Tagesordnungslink ohne art=protokoll', !str_contains($html, 'art=protokoll'));

$seed = (string)file_get_contents($app . '/sql/seed.sql');
$check('Einstellungen angelegt', str_contains($seed, "('protokoll_anmerkungen','protokoll'")
    && str_contains($seed, "('protokoll_anmerkungen_namen','1'"));

echo "$ok bestanden, $fail fehlgeschlagen\n";
