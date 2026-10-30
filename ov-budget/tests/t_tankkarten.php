<?php
declare(strict_types=1);
/* Tankkarten: Arten, Gültigkeit, Zuordnung zu Fahrzeugen, Speichern, Ansichten */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'tankkarte_modul_name' => 'Tankkarten', 'tankkarte_intro' => '', 'tankkarte_gueltig_warnung_tage' => '60'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['abfragen'] = [];
$GLOBALS['alle'] = [];        // Antwort für db_all außerhalb von Einstellungen und Listen
$GLOBALS['doppelt'] = null;   // Antwort für die Nummernprüfung
$GLOBALS['can'] = [];

function db_all(string $sql, array $p = []): array {
    $GLOBALS['abfragen'][] = [$sql, $p];
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    if (str_contains($sql, 'FROM list_items')) {
        $r = [];
        $id = 300;
        foreach (['tankkarte_anbieter' => [['dkv', 'DKV', 1], ['enbw', 'EnBW mobility+', 0]], 'tankkarte_status' => [['aktiv', 'Im Einsatz', 1], ['gesperrt', 'Gesperrt', 0]]] as $key => $items) {
            foreach ($items as [$slug, $label, $def]) {
                $r[] = ['id' => $id++, 'list_key' => $key, 'label' => $label, 'slug' => $slug, 'color' => '#123456', 'weight' => 0,
                        'is_default' => $def, 'is_final' => 0, 'is_active' => 1, 'sort_order' => 0];
            }
        }
        return $r;
    }
    if (str_contains($sql, 'FROM tankkarte_fahrzeuge kf')) {
        return array_values(array_filter($GLOBALS['zuordnung'] ?? [], static fn($z) => in_array($z['tankkarte_id'], $p, true)));
    }
    return $GLOBALS['alle'];
}
function db_row(string $sql, array $p = []): ?array { return $GLOBALS['zeile'] ?? null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'FROM tankkarten WHERE nummer')) { $GLOBALS['nummer_pruefung'] = $p; return $GLOBALS['doppelt']; }
    return $d;
}
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 40 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
final class StubStmt { public function execute(array $p): bool { return true; } }
final class StubPdo { public function prepare(string $s): StubStmt { return new StubStmt(); } }
function db(): StubPdo { return new StubPdo(); }
function can(string $what, mixed $ctx = null): bool { return $GLOBALS['can'][$what] ?? true; }
function current_user(): ?array { return ['id' => 1, 'display_name' => 'Tester']; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'tankkarten'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};
$karte = static fn(array $extra = []): array => $extra + [
    'id' => 5, 'nummer' => '7083001234567890', 'anbieter_id' => 300, 'anbieter_label' => 'DKV', 'anbieter_color' => '#1d4ed8',
    'art' => 'kraftstoff', 'status_id' => 302, 'status_label' => 'Im Einsatz', 'status_color' => '#15803d',
    'kundennummer' => 'K-77', 'gueltig_bis' => null, 'hat_pin' => 0, 'pin' => '', 'person_id' => null, 'person_label' => null,
    'aufbewahrung' => 'Handschuhfach', 'ausgegeben_am' => null, 'notiz' => '', 'is_active' => 1,
    'verbleib' => '', 'verbleib_am' => null, 'sperrgrund' => '',
    'fahrzeuge' => [['id' => 7, 'bezeichnung' => 'GKW 1', 'kennzeichen' => 'THW 99020', 'is_active' => 1]],
];

/* ---------- Reine Funktionen ---------- */
$check('Arten', tankkarte_art('laden') === 'laden' && tankkarte_art('unsinn') === 'kraftstoff'
    && tankkarte_laedt(['art' => 'beides']) && tankkarte_laedt(['art' => 'laden']) && !tankkarte_laedt(['art' => 'kraftstoff'])
    && tankkarte_tankt(['art' => 'beides']) && !tankkarte_tankt(['art' => 'laden']));
$check('Bezeichnung kürzt die Nummer', tankkarte_bezeichnung($karte()) === 'DKV …7890' && tankkarte_bezeichnung(['nummer' => '1234', 'anbieter_label' => '']) === '1234'
    && tankkarte_bezeichnung([]) === 'Tankkarte');
$check('Fahrzeuge als Text', tankkarte_fahrzeuge_text($karte()) === 'GKW 1 · THW 99020' && tankkarte_fahrzeuge_text(['fahrzeuge' => []]) === '');
$check('Verbleib', tankkarte_verbleib('gesperrt') === 'gesperrt' && tankkarte_verbleib('unsinn') === '' && tankkarte_gesperrt(['verbleib' => 'gesperrt']) && !tankkarte_gesperrt(['verbleib' => 'abgelaufen'])
    && tankkarte_verbleib_text(['verbleib' => 'gesperrt', 'verbleib_am' => '2026-03-12', 'sperrgrund' => 'verloren']) === 'Gesperrt seit 12.03.2026 – verloren'
    && tankkarte_verbleib_text(['verbleib' => 'zurueckgegeben']) === 'Zurückgegeben' && tankkarte_verbleib_text(['verbleib' => '']) === '');
$check('PIN verdeckt', tankkarte_verdeckt('1234') === '••••' && tankkarte_verdeckt('12') === '••••' && tankkarte_verdeckt('') === '');
$check('Gültigkeit', tankkarte_gueltig(['gueltig_bis' => null])['stufe'] === 'offen'
    && tankkarte_gueltig(['gueltig_bis' => '2026-12-31'], 60, '2026-11-15')['stufe'] === 'bald'
    && tankkarte_gueltig(['gueltig_bis' => '2026-10-01'], 60, '2026-11-15')['stufe'] === 'faellig'
    && tankkarte_gueltig(['gueltig_bis' => '2027-12-31'], 60, '2026-11-15')['stufe'] === 'ok'
    && tankkarte_gueltig(['gueltig_bis' => '2026-11-20'], 60, '2026-11-15')['tage'] === 5);
$stats = tankkarte_stats([
    $karte(), $karte(['id' => 6, 'art' => 'laden', 'anbieter_label' => 'EnBW mobility+', 'fahrzeuge' => [], 'gueltig_bis' => '2026-12-01']),
    $karte(['id' => 7, 'art' => 'beides', 'gueltig_bis' => '2026-01-01']),
], 60, '2026-11-15');
$check('Zahlen für den Kopf', $stats['anzahl'] === 3 && $stats['laden'] === 2 && $stats['ohne_fahrzeug'] === 1 && $stats['gueltig_bald'] === 2
    && $stats['anbieter'] === ['DKV' => 2, 'EnBW mobility+' => 1] && $stats['gesperrt'] === 0
    && tankkarte_stats([$karte(['verbleib' => 'gesperrt'])])['gesperrt'] === 1);
$optionen = tankkarte_optionen([$karte()], [$karte(), $karte(['id' => 6, 'nummer' => '9999'])]);
$check('Auswahl fürs Tankformular: eigene zuerst, keine doppelt', array_keys($optionen) === ['Diesem Fahrzeug zugeordnet', 'Weitere Karten']
    && count($optionen['Diesem Fahrzeug zugeordnet']) === 1 && count($optionen['Weitere Karten']) === 1 && $optionen['Weitere Karten'][0]['id'] === 6);
$check('Auswahl ohne eigene', array_keys(tankkarte_optionen([], [$karte()])) === ['Weitere Karten'] && tankkarte_optionen([], []) === []);

/* ---------- Fahrzeuge an die Karten hängen ---------- */
$GLOBALS['zuordnung'] = [['tankkarte_id' => 5, 'id' => 7, 'bezeichnung' => 'GKW 1', 'kennzeichen' => 'THW 99020', 'is_active' => 1],
                         ['tankkarte_id' => 5, 'id' => 8, 'bezeichnung' => 'MTW', 'kennzeichen' => '', 'is_active' => 1]];
$mit = tankkarten_mit_fahrzeugen([['id' => 5], ['id' => 6]]);
$check('Zuordnung je Karte', count($mit[0]['fahrzeuge']) === 2 && $mit[0]['fahrzeuge'][1]['bezeichnung'] === 'MTW' && $mit[1]['fahrzeuge'] === []);
$check('ohne Karten keine Abfrage', tankkarten_mit_fahrzeugen([]) === []);

$GLOBALS['abfragen'] = [];
$GLOBALS['alle'] = [];
tankkarte_query(['vehicle_id' => 7, 'q' => 'DKV', 'art' => 'laden']);
$sql = $GLOBALS['abfragen'][0][0];
$p = $GLOBALS['abfragen'][0][1];
$check('Abfrage je Fahrzeug: Join vor den Bedingungen, Fahrzeug erster Parameter', str_contains($sql, 'JOIN tankkarte_fahrzeuge kf') && strpos($sql, 'JOIN tankkarte_fahrzeuge') < strpos($sql, 'LEFT JOIN')
    && $p[0] === 7 && $p[1] === '%DKV%' && end($p) === 'laden' && str_contains($sql, "k.art = ? OR k.art = 'beides'") && str_contains($sql, 'k.is_active = 1'));
$GLOBALS['abfragen'] = [];
tankkarte_query(['aktiv' => 'alle', 'sort' => 'gueltig']);
$check('alle, nach Gültigkeit', !str_contains($GLOBALS['abfragen'][0][0], 'is_active = 1') && str_contains($GLOBALS['abfragen'][0][0], 'k.gueltig_bis IS NULL, k.gueltig_bis ASC'));

/* ---------- Speichern ---------- */
$user = ['id' => 1];
$_POST = ['nummer' => '', 'anbieter_id' => ''];
[$id, $fehler] = tankkarte_save_from_post(null, $user);
$check('Pflichtfelder', $id === null && count($fehler) === 2 && str_contains($fehler[0], 'Kartennummer') && str_contains($fehler[1], 'Anbieter'));

$_POST = ['nummer' => ' 7083  0012 ', 'anbieter_id' => '300', 'art' => 'beides', 'status_id' => '302', 'kundennummer' => 'K-77', 'gueltig_bis' => '2027-06-30',
          'hat_pin' => '1', 'pin' => '4711', 'person_id' => '9', 'aufbewahrung' => 'Handschuhfach', 'ausgegeben_am' => '2026-01-10', 'notiz' => 'n', 'is_active' => '1',
          'fahrzeuge_feld' => '1', 'fahrzeuge' => ['7', '8', '7'], 'fahrzeuge_neu' => '9'];
$GLOBALS['inserts'] = []; $GLOBALS['execs'] = []; $GLOBALS['doppelt'] = null;
[$id, $fehler] = tankkarte_save_from_post(null, $user);
$karteIns = $GLOBALS['inserts'][0] ?? null;
$check('angelegt', $id === 41 && $fehler === [] && $karteIns[0] === 'tankkarten' && $karteIns[1]['nummer'] === '7083 0012' && $karteIns[1]['art'] === 'beides'
    && $karteIns[1]['pin'] === '4711' && $karteIns[1]['hat_pin'] === 1 && $karteIns[1]['person_id'] === 9 && $karteIns[1]['gueltig_bis'] === '2027-06-30'
    && $karteIns[1]['created_by'] === 1 && $karteIns[1]['is_active'] === 1);
$zuord = array_values(array_filter($GLOBALS['inserts'], static fn($i) => $i[0] === 'tankkarte_fahrzeuge'));
$check('Fahrzeuge gesetzt: alt gelöscht, jede einmal, neues aus dem Dropdown', str_contains($GLOBALS['execs'][0][0], 'DELETE FROM tankkarte_fahrzeuge') && $GLOBALS['execs'][0][1] === [41]
    && array_column(array_column($zuord, 1), 'vehicle_id') === [7, 8, 9] && $zuord[0][1]['tankkarte_id'] === 41);
$check('Nummernprüfung mit Anbieter', $GLOBALS['nummer_pruefung'] === ['7083 0012', 300, 0]);

$GLOBALS['doppelt'] = 3;
[$id, $fehler] = tankkarte_save_from_post(null, $user);
$check('doppelte Nummer beim selben Anbieter', $id === null && count($fehler) === 1 && str_contains($fehler[0], 'schon auf einer anderen Karte'));
$GLOBALS['doppelt'] = null;

// Gesperrt braucht einen Grund
$_POST = ['nummer' => '7083 0012', 'anbieter_id' => '300', 'verbleib' => 'gesperrt', 'sperrgrund' => '  '];
[$id, $fehler] = tankkarte_save_from_post(null, $user);
$check('gesperrt ohne Grund abgelehnt', $id === null && count($fehler) === 1 && str_contains($fehler[0], 'begründen'));
$_POST = ['nummer' => '7083 0012', 'anbieter_id' => '300', 'verbleib' => 'gesperrt', 'sperrgrund' => 'verloren am Einsatz'];
$GLOBALS['inserts'] = [];
[$id, $fehler] = tankkarte_save_from_post(null, $user);
$check('gesperrt mit Grund: aus dem Bestand, Datum heute', $id !== null && $GLOBALS['inserts'][0][1]['verbleib'] === 'gesperrt' && $GLOBALS['inserts'][0][1]['sperrgrund'] === 'verloren am Einsatz'
    && $GLOBALS['inserts'][0][1]['is_active'] === 0 && $GLOBALS['inserts'][0][1]['verbleib_am'] === date('Y-m-d'));
$_POST = ['nummer' => '7083 0012', 'anbieter_id' => '300', 'verbleib' => 'zurueckgegeben', 'verbleib_am' => '2026-02-01', 'sperrgrund' => 'wird verworfen'];
$GLOBALS['inserts'] = [];
[$id, $fehler] = tankkarte_save_from_post(null, $user);
$check('zurückgegeben mit Datum, Grund nur bei Sperrung', $GLOBALS['inserts'][0][1]['verbleib'] === 'zurueckgegeben' && $GLOBALS['inserts'][0][1]['verbleib_am'] === '2026-02-01'
    && $GLOBALS['inserts'][0][1]['sperrgrund'] === '' && $GLOBALS['inserts'][0][1]['is_active'] === 0);
$_POST = ['nummer' => '7083 0012', 'anbieter_id' => '300', 'verbleib' => 'gesperrt', 'sperrgrund' => 'verloren'];
$GLOBALS['updates'] = [];
[$id, $fehler] = tankkarte_save_from_post($karte(['id' => 41, 'verbleib' => 'gesperrt', 'verbleib_am' => '2026-03-12']), $user);
$check('bestehendes Datum bleibt beim Ändern', $GLOBALS['updates'][0][1]['verbleib_am'] === '2026-03-12');

// Ändern ohne Fahrzeugfeld: Zuordnung bleibt; PIN ohne Haken geleert; Art unsinnig → Kraftstoff; Verbleib zurückgegeben
$_POST = ['nummer' => '7083 0012', 'anbieter_id' => '300', 'art' => 'unsinn', 'pin' => '4711', 'verbleib' => 'zurueckgegeben'];
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['execs'] = [];
[$id, $fehler] = tankkarte_save_from_post($karte(['id' => 41]), $user);
$check('geändert', $id === 41 && $GLOBALS['updates'][0][0] === 'tankkarten' && $GLOBALS['updates'][0][2] === [41] && $GLOBALS['updates'][0][1]['pin'] === ''
    && $GLOBALS['updates'][0][1]['hat_pin'] === 0 && $GLOBALS['updates'][0][1]['art'] === 'kraftstoff' && $GLOBALS['updates'][0][1]['is_active'] === 0
    && $GLOBALS['execs'] === [] && $GLOBALS['inserts'] === []);
$check('Nummernprüfung lässt die eigene Karte aus', $GLOBALS['nummer_pruefung'][2] === 41);

// Fahrzeug aus der Fahrzeugakte dazu
$GLOBALS['inserts'] = []; $GLOBALS['execs'] = []; $GLOBALS['updates'] = [];
tankkarte_fahrzeug_hinzu($karte(), 8, $user);
$check('Fahrzeug dazu', count($GLOBALS['execs']) === 1 && count($GLOBALS['inserts']) === 2 && $GLOBALS['inserts'][1][1]['vehicle_id'] === 8);
$GLOBALS['inserts'] = []; $GLOBALS['execs'] = [];
tankkarte_fahrzeug_hinzu($karte(), 7, $user);
$check('schon zugeordnet: nichts zu tun', $GLOBALS['execs'] === [] && $GLOBALS['inserts'] === []);

$GLOBALS['execs'] = [];
tankkarte_delete($karte());
$check('löschen', str_contains($GLOBALS['execs'][0][0], 'DELETE FROM tankkarten WHERE id') && $GLOBALS['execs'][0][1] === [5]);

/* ---------- Ansichten ---------- */
$_SERVER['REQUEST_URI'] = '/?p=tankkarten';
$_GET = ['p' => 'tankkarten'];
$karten = [$karte(['hat_pin' => 1, 'pin' => '4711']), $karte(['id' => 6, 'nummer' => 'ENBW-0001', 'art' => 'laden', 'anbieter_label' => 'EnBW mobility+', 'fahrzeuge' => [], 'gueltig_bis' => '2020-01-01', 'is_active' => 0, 'person_label' => 'Anna Admin', 'verbleib' => 'gesperrt', 'verbleib_am' => '2026-03-12', 'sperrgrund' => 'verloren'])];
$daten = ['karten' => $karten, 'stats' => tankkarte_stats($karten, 60), 'warn' => 60,
          'filter' => ['q' => '', 'anbieter_id' => null, 'status_id' => null, 'art' => '', 'aktiv' => 'aktiv', 'sort' => '']];
$html = render_partial('tankkarten', $daten);
$check('Liste: Karten, Laden-Plakette, Fahrzeug-Link, PIN verdeckt', str_contains($html, '7083001234567890') && str_contains($html, '⚡ Laden')
    && str_contains($html, 'p=vehicle&amp;id=7') && str_contains($html, 'data-geheim="4711"') && str_contains($html, '••••') && str_contains($html, '>gesperrt<')
    && str_contains($html, 'Gesperrt seit 12.03.2026 – verloren') && str_contains($html, 'Anna Admin') && str_contains($html, 'color:var(--bad)'));
$check('Liste: Filter und Knöpfe', str_contains($html, 'name="art"') && str_contains($html, 'name="anbieter_id"') && str_contains($html, '+ Tankkarte') && str_contains($html, 'tankkarten_export'));
$GLOBALS['can'] = ['manage_tankkarten' => false];
$html = render_partial('tankkarten', $daten);
$check('Liste ohne Leitung: keine PIN, kein Bearbeiten', !str_contains($html, 'data-geheim') && !str_contains($html, 'tankkarte_edit') && !str_contains($html, '+ Tankkarte'));
$GLOBALS['can'] = [];
$html = render_partial('tankkarten', ['karten' => [], 'stats' => tankkarte_stats([], 60)] + $daten);
$check('Liste leer', str_contains($html, 'Keine Karte gefunden'));

$optionenFz = [['id' => 7, 'text' => 'GKW 1', 'sub' => 'THW 99020', 'gruppe' => ''], ['id' => 8, 'text' => 'MTW', 'sub' => '', 'gruppe' => '']];
$html = render_partial('tankkarte_edit', ['karte' => $karte(['id' => null]), 'errors' => [], 'fahrzeugOptionen' => $optionenFz, 'fahrzeugeGewaehlt' => [7],
    'personen' => [['id' => 9, 'display_name' => 'Anna Admin']], 'vehicleId' => 7]);
$check('Formular: Anbieter, Art, Fahrzeugwahl mit Hinzufügen, Person, PIN-Schalter', str_contains($html, 'name="anbieter_id"') && str_contains($html, 'value="laden"')
    && str_contains($html, 'data-wahl="fahrzeuge"') && str_contains($html, 'name="fahrzeuge[]" value="7"') && str_contains($html, 'name="fahrzeuge_feld"')
    && str_contains($html, 'name="person_id"') && str_contains($html, 'data-schalter="pin"') && !str_contains($html, 'value="loeschen"')
    && str_contains($html, 'p=vehicle&amp;id=7') && str_contains($html, 'name="verbleib"') && str_contains($html, 'value="zurueckgegeben"')
    && str_contains($html, 'data-verbleib-grund hidden') && str_contains($html, 'name="sperrgrund"'));
$html = render_partial('tankkarte_edit', ['karte' => $karte(['verbleib' => 'gesperrt', 'sperrgrund' => 'verloren']), 'errors' => [], 'fahrzeugOptionen' => $optionenFz, 'fahrzeugeGewaehlt' => [7],
    'personen' => [], 'vehicleId' => null]);
$check('Formular gesperrt: Grund sichtbar und gefüllt', str_contains($html, 'data-verbleib-grund>') && str_contains($html, 'value="verloren"') && str_contains($html, 'value="gesperrt" selected'));
$html = render_partial('tankkarte_edit', ['karte' => $karte(['hat_pin' => 1, 'pin' => '4711']), 'errors' => ['Bitte die Kartennummer angeben – sie steht auf der Karte.'], 'fahrzeugOptionen' => $optionenFz,
    'fahrzeugeGewaehlt' => [7, 8], 'personen' => [], 'vehicleId' => null]);
$check('Formular bestehend: Fehler, PIN sichtbar, Löschen', str_contains($html, 'alert--error') && str_contains($html, 'value="4711"') && str_contains($html, 'value="loeschen"')
    && substr_count($html, 'name="fahrzeuge[]"') === 2 && str_contains($html, 'p=tankkarten'));

/* ---------- Einbau ---------- */
$check('Routen', str_contains((string)file_get_contents($app . '/public/index.php'), "'tankkarten_export'"));
$check('Rechte', str_contains((string)file_get_contents($app . '/src/lib/auth.php'), "'view_tankkarten'") && str_contains((string)file_get_contents($app . '/src/lib/auth.php'), "'manage_tankkarten'"));
$check('Navigation', str_contains((string)file_get_contents($app . '/src/lib/nav.php'), "'tankkarten' =>"));
$check('Auswahllisten', str_contains((string)file_get_contents($app . '/src/lib/lists.php'), "'tankkarte_anbieter'") && str_contains((string)file_get_contents($app . '/sql/seed.sql'), "('tankkarte_anbieter','DKV'"));
$check('Fahrzeugakte', str_contains((string)file_get_contents($app . '/views/vehicle.php'), 'id="tankkarten"') && str_contains((string)file_get_contents($app . '/src/pages/vehicle.php'), 'tankkarten_for_vehicle'));
$schema = (string)file_get_contents($app . '/sql/schema.sql');
$check('Schema', str_contains($schema, 'CREATE TABLE IF NOT EXISTS tankkarten') && str_contains($schema, 'CREATE TABLE IF NOT EXISTS tankkarte_fahrzeuge') && str_contains($schema, 'fk_tank_karte')
    && strpos($schema, 'CREATE TABLE IF NOT EXISTS tankkarten') < strpos($schema, 'CREATE TABLE IF NOT EXISTS vehicle_tankungen'));
$check('Migration', str_contains((string)file_get_contents($app . '/src/cli/migrate.php'), "\$merken('092_tankkarten')") && str_contains((string)file_get_contents($app . '/src/cli/migrate.php'), "\$merken('093_tankkarte_verbleib')"));
$check('Schema Verbleib', str_contains($schema, 'sperrgrund    VARCHAR(255)') && str_contains($schema, 'verbleib_am   DATE'));
$check('Export mit Verbleib', str_contains((string)file_get_contents($app . '/src/pages/tankkarten_export.php'), "'Grund der Sperrung'"));
$check('Einstellungen', str_contains((string)file_get_contents($app . '/sql/seed.sql'), "'tankkarte_gueltig_warnung_tage'"));

echo "$ok bestanden, $fail fehlgeschlagen\n";
