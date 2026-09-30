<?php
declare(strict_types=1);
// Anwesenheit bei Besprechungen: Zählungen, Aufnahme, Status, Darstellung
session_start();
$GLOBALS['execs'] = [];
$GLOBALS['liste'] = [];
$GLOBALS['rows'] = [];      // Antwort für db_row
$GLOBALS['exec_rows'] = 1;  // von db_exec gemeldete Zeilen

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        return [['skey' => 'waehrung', 'svalue' => 'EUR', 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]];
    }
    if (str_contains($sql, 'FROM list_items')) {
        $r = [];
        foreach ([['eingeladen', 'Eingeladen', 1], ['zugesagt', 'Zugesagt', 0], ['teilgenommen', 'Teilgenommen', 0],
                  ['entschuldigt', 'Entschuldigt', 0], ['fehlt', 'Nicht erschienen', 0]] as $i => [$slug, $label, $def]) {
            $r[] = ['id' => 100 + $i, 'list_key' => 'teilnahme_status', 'label' => $label, 'slug' => $slug,
                    'color' => '#123456', 'weight' => 0, 'is_default' => $def, 'is_final' => 0, 'is_active' => 1, 'sort_order' => $i];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM meeting_attendees a')) {
        $GLOBALS['letzte_liste_sql'] = [$sql, $p];
        return $GLOBALS['liste'];
    }
    if (str_contains($sql, 'FROM contact_group_members')) {
        return [['contact_id' => 21], ['contact_id' => 22], ['contact_id' => 23]];
    }
    $GLOBALS['letzte_sql'] = [$sql, $p];
    return $GLOBALS['rows'];
}
function db_row(string $sql, array $p = []): ?array { $GLOBALS['letzte_sql'] = [$sql, $p]; return array_shift($GLOBALS['rows']); }
function db_val(string $sql, array $p = [], mixed $d = null): mixed { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return $GLOBALS['exec_rows']; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
final class StubStmt { public function execute(array $p): bool { return true; } }
final class StubPdo { public function prepare(string $s): StubStmt { return new StubStmt(); } }
function db(): StubPdo { return new StubPdo(); }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung', 'display_name' => 'Tester']; }
function can(string $what, mixed $ctx = null): bool { return $GLOBALS['can'][$what] ?? true; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/attendance.php';
require $app . '/src/lib/meetings.php';
require $app . '/src/lib/view.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};

$person = static fn(array $x) => $x + ['id' => 1, 'user_id' => null, 'contact_id' => null, 'name' => '',
    'anzeige' => 'Anna Admin', 'organisation' => null, 'fachgruppe_label' => null, 'notiz' => '',
    'status_id' => 100, 'status_slug' => 'eingeladen', 'status_label' => 'Eingeladen', 'status_color' => '#123456'];

// Zählung
$personen = [
    $person(['id' => 1, 'status_slug' => 'teilgenommen', 'status_id' => 102, 'anzeige' => 'Anna']),
    $person(['id' => 2, 'status_slug' => 'teilgenommen', 'status_id' => 102, 'anzeige' => 'Bert']),
    $person(['id' => 3, 'status_slug' => 'entschuldigt', 'status_id' => 103, 'anzeige' => 'Cem']),
    $person(['id' => 4, 'status_slug' => 'fehlt', 'status_id' => 104, 'anzeige' => 'Dora']),
    $person(['id' => 5, 'status_slug' => 'eingeladen', 'status_id' => 100, 'anzeige' => 'Eva']),
    $person(['id' => 6, 'status_slug' => 'zugesagt', 'status_id' => 101, 'anzeige' => 'Fritz']),
    $person(['id' => 7, 'status_slug' => '', 'status_id' => null, 'anzeige' => 'Ohne Status']),
];
$s = attendance_stats($personen);
$check('Zählung gesamt', $s['anzahl'] === 7);
$check('Zählung anwesend', $s['anwesend'] === 2);
$check('Zählung entschuldigt', $s['entschuldigt'] === 1);
$check('Zählung fehlt', $s['fehlt'] === 1);
$check('Zugesagt und ohne Status gelten als offen', $s['offen'] === 3);
$check('leere Liste', attendance_stats([]) === ['anzahl' => 0, 'anwesend' => 0, 'entschuldigt' => 0, 'fehlt' => 0, 'offen' => 0]);

// Namen je Status
$check('Anwesende', attendance_by_status($personen, ANWESEND_SLUG) === ['Anna', 'Bert']);
$check('Entschuldigte', attendance_by_status($personen, ENTSCHULDIGT_SLUG) === ['Cem']);
$check('Fehlende', attendance_by_status($personen, FEHLT_SLUG) === ['Dora']);

// Namen
$check('Name schlicht', attendance_name($person(['anzeige' => 'Max Muster'])) === 'Max Muster');
$check('Name mit Organisation', attendance_name($person(['anzeige' => 'Max', 'organisation' => 'Stadt']), true) === 'Max (Stadt)');
$check('Name mit Fachgruppe', attendance_name($person(['anzeige' => 'Max', 'fachgruppe_label' => 'Zugtrupp']), true) === 'Max (Zugtrupp)');
$check('Zusatz gleich Name wird weggelassen', attendance_name($person(['anzeige' => 'Stadt', 'organisation' => 'Stadt']), true) === 'Stadt');
$check('leerer Name', attendance_name($person(['anzeige' => ''])) === '?');

// Aufnehmen
$check('Vorgabestatus ist eingeladen', attendance_default_status() === 100);
$GLOBALS['execs'] = [];
$check('Benutzer aufnehmen', attendance_add(5, 'user', 7));
$check('INSERT IGNORE', str_contains($GLOBALS['execs'][0][0], 'INSERT IGNORE INTO meeting_attendees'));
$check('Benutzerzeile', $GLOBALS['execs'][0][1] === [5, 7, null, '', 100]);
attendance_add(5, 'contact', 9);
$check('Kontaktzeile', $GLOBALS['execs'][1][1] === [5, null, 9, '', 100]);
attendance_add(5, 'name', '  Herr Meier  ');
$check('Freitext wird getrimmt', $GLOBALS['execs'][2][1] === [5, null, null, 'Herr Meier', 100]);
$GLOBALS['execs'] = [];
$check('leerer Name wird nicht aufgenommen', !attendance_add(5, 'name', '   '));
$check('kein Schreibzugriff dabei', $GLOBALS['execs'] === []);
$GLOBALS['exec_rows'] = 0;
$check('doppelte Einladung meldet false', !attendance_add(5, 'user', 7));
$GLOBALS['exec_rows'] = 1;

// Verteiler
$GLOBALS['execs'] = [];
$check('Verteiler bringt drei Kontakte', attendance_add_group(5, 3) === 3);
$check('Verteiler nimmt Kontakte auf', $GLOBALS['execs'][0][1] === [5, null, 21, '', 100]);

// Übernehmen von einem anderen Termin
$GLOBALS['liste'] = [
    $person(['id' => 1, 'user_id' => 7, 'status_slug' => 'teilgenommen', 'status_id' => 102]),
    $person(['id' => 2, 'contact_id' => 9, 'status_slug' => 'fehlt', 'status_id' => 104]),
    $person(['id' => 3, 'name' => 'Herr Meier', 'anzeige' => 'Herr Meier', 'status_slug' => 'entschuldigt']),
];
$GLOBALS['execs'] = [];
$check('drei Personen übernommen', attendance_copy(4, 5) === 3);
$check('Übernahme: Benutzer', $GLOBALS['execs'][0][1] === [5, 7, null, '', 100]);
$check('Übernahme: Kontakt', $GLOBALS['execs'][1][1] === [5, null, 9, '', 100]);
$check('Übernahme: Freitext', $GLOBALS['execs'][2][1] === [5, null, null, 'Herr Meier', 100]);
$check('Übernahme setzt Status zurück', !str_contains(json_encode($GLOBALS['execs']), '102'));

// Status speichern
$GLOBALS['execs'] = [];
$n = attendance_save_status(5, [3 => '102', 4 => '104', 0 => '102', 9 => '0', 10 => 'abc'], [3 => str_repeat('x', 300)]);
$check('zwei Zeilen geändert', $n === 2);
$check('nur gültige Zeilen', count($GLOBALS['execs']) === 2);
$check('Meeting wird mitgeprüft', str_contains($GLOBALS['execs'][0][0], 'WHERE id = ? AND meeting_id = ?'));
$check('Notiz gekürzt', mb_strlen($GLOBALS['execs'][0][1][1]) === 255);
$check('fehlende Notiz ist leer', $GLOBALS['execs'][1][1][1] === '');

// Alle offenen setzen
$GLOBALS['execs'] = [];
attendance_set_all(5, ANWESEND_SLUG);
$check('nur offene Einträge', str_contains($GLOBALS['execs'][0][0], "IN ('', 'eingeladen', 'zugesagt')"));
$check('Status teilgenommen', $GLOBALS['execs'][0][1] === [102, 5]);
$GLOBALS['execs'] = [];
attendance_set_all(5, FEHLT_SLUG, false);
$check('alle Einträge auf Wunsch', !str_contains($GLOBALS['execs'][0][0], 'eingeladen') && $GLOBALS['execs'][0][1] === [104, 5]);
$check('unbekannter Status ändert nichts', attendance_set_all(5, 'gibtsnicht') === 0);

// Vorlage für die Übernahme
$GLOBALS['rows'] = [['id' => 2, 'titel' => 'Letzte', 'datum' => '2026-09-01', 'anzahl' => 4]];
$q = attendance_source_meeting(['id' => 3, 'datum' => '2026-09-15', 'series_id' => 8, 'typ_id' => 2]);
$check('Serie zuerst', $q['id'] === 2 && str_contains($GLOBALS['letzte_sql'][0], 'AND m.series_id = ?'));
$check('Parameter der Suche', $GLOBALS['letzte_sql'][1] === [3, '2026-09-15', 8]);
$GLOBALS['rows'] = [null, ['id' => 9, 'titel' => 'Andere', 'datum' => '2026-08-01', 'anzahl' => 2]];
$q = attendance_source_meeting(['id' => 3, 'datum' => '2026-09-15', 'series_id' => 8, 'typ_id' => 2]);
$check('sonst dieselbe Besprechungsart', $q['id'] === 9 && str_contains($GLOBALS['letzte_sql'][0], 'AND m.typ_id = ?'));
$GLOBALS['rows'] = [['id' => 5, 'titel' => 'X', 'datum' => '2026-01-01', 'anzahl' => 1]];
attendance_source_meeting(['id' => 3, 'datum' => '2026-09-15', 'series_id' => null, 'typ_id' => null]);
$check('ohne Serie und Art: irgendeiner',
    !str_contains($GLOBALS['letzte_sql'][0], 'series_id') && !str_contains($GLOBALS['letzte_sql'][0], 'typ_id'));
$check('nur Termine mit Liste', str_contains($GLOBALS['letzte_sql'][0], 'HAVING anzahl > 0'));

// Darstellung
$GLOBALS['liste'] = $personen;
$_SERVER['REQUEST_URI'] = '/?p=meeting&id=5';
$vars = [
    'meeting' => ['id' => 5, 'titel' => 'Dienstbesprechung', 'datum' => '2026-09-24'],
    'teilnehmer' => $personen, 'anwesenheit' => attendance_stats($personen), 'verwalten' => true,
    'kandidaten' => [['id' => 3, 'name' => 'Gerd Gruppenführer', 'fachgruppe_label' => 'Bergung']],
    'kontakte' => [], 'kontaktSuche' => '', 'verteiler' => [['id' => 2, 'name' => 'Ehrengäste']], 'quelle' => null,
];
$html = render_partial('partials/attendance', $vars);
$check('Ansicht: Zählungen', str_contains($html, '2 da') && str_contains($html, '1 entschuldigt') && str_contains($html, '1 fehlten'));
$check('Ansicht: offene Einträge', str_contains($html, '3 offen'));
$check('Ansicht: Radio je Person und Status', substr_count($html, 'type="radio"') === 7 * 5);
$check('Ansicht: gewählter Status angehakt', str_contains($html, 'name="status[1]" value="102" checked')
    && str_contains($html, 'name="status[3]" value="103" checked'));
$check('Ansicht: genau ein Haken je Person', substr_count($html, ' checked') === 6);
$check('Ansicht: Sammelknöpfe', str_contains($html, 'Alle offenen: waren da') && str_contains($html, 'value="entschuldigt"'));
$check('Ansicht: entfernen', str_contains($html, 'name="remove[]" value="4"'));
$check('Ansicht: Benutzer zum Hinzufügen', str_contains($html, 'name="user_ids[]" value="3"'));
$check('Ansicht: Verteiler', str_contains($html, 'name="group_id"') && str_contains($html, 'Ehrengäste'));

$vars['verwalten'] = false;
$html = render_partial('partials/attendance', $vars);
$check('Mitglied: keine Radios', !str_contains($html, 'type="radio"') && !str_contains($html, '<form'));
$check('Mitglied: Namen sichtbar', str_contains($html, 'Anna') && str_contains($html, 'Dora'));

$vars['verwalten'] = true;
$vars['teilnehmer'] = [];
$vars['anwesenheit'] = attendance_stats([]);
$vars['quelle'] = ['id' => 2, 'titel' => 'Letzte', 'datum' => '2026-09-01', 'anzahl' => 4];
$html = render_partial('partials/attendance', $vars);
$check('leere Liste: Übernahme angeboten', str_contains($html, 'Liste vom 01.09.2026 übernehmen (4)')
    && str_contains($html, 'name="von_meeting_id" value="2"'));
$check('leere Liste: niemand eingeladen', str_contains($html, 'niemand eingeladen'));

// Druckansicht (Protokoll)
$druck = render_partial('meeting_print', [
    'meeting' => ['id' => 5, 'titel' => 'Dienstbesprechung', 'datum' => '2026-09-24', 'beginn' => '19:30:00',
                  'ende' => null, 'ort' => 'Unterkunft', 'typ_label' => '', 'leitung' => 'OB',
                  'protokoll_von' => 'Schriftführer', 'teilnehmer' => "Gast aus der Nachbarschaft",
                  'beschreibung' => '', 'notizen' => ''],
    'punkte' => [], 'zeiten' => [], 'gesamt' => 0, 'art' => 'protokoll', 'teilnehmer' => $personen,
]);
$check('Druck: Anwesende', str_contains($druck, '<td>Anwesend</td><td>') && str_contains($druck, 'Anna, Bert'));
$check('Druck: Freitext bleibt', str_contains($druck, 'Gast aus der Nachbarschaft'));
$check('Druck: Entschuldigte', str_contains($druck, 'Entschuldigt</td><td>Cem'));
$check('Druck: Nicht erschienen', str_contains($druck, 'Nicht erschienen</td><td>Dora'));

$druck = render_partial('meeting_print', [
    'meeting' => ['id' => 5, 'titel' => 'T', 'datum' => '2026-09-24', 'beginn' => null, 'ende' => null, 'ort' => '',
                  'typ_label' => '', 'leitung' => '', 'protokoll_von' => '', 'teilnehmer' => '', 'beschreibung' => '', 'notizen' => ''],
    'punkte' => [], 'zeiten' => [], 'gesamt' => 30, 'art' => 'tagesordnung', 'teilnehmer' => $personen,
]);
$check('Tagesordnung: Eingeladene', str_contains($druck, 'Eingeladen</td><td>Anna, Bert, Cem, Dora, Eva, Fritz, Ohne Status'));
$check('Tagesordnung: kein Protokollfeld', !str_contains($druck, 'Entschuldigt</td>'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
