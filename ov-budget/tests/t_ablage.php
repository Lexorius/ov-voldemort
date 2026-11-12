<?php
declare(strict_types=1);
/*
 * Dateiablage: Baum aus eigenen und automatischen Kategorien, Rechte über den
 * Baum, Dokumente aus allen Ablagen in einer Form, Suche, Kategorien pflegen,
 * Hochladen samt KI-Auswertung, Ansichten.
 */
session_start();
date_default_timezone_set('Europe/Berlin');
define('OVB_TEST_UPLOADS', true);

$tmp = sys_get_temp_dir() . '/ovb-ablage-test-' . getmypid();
@mkdir($tmp, 0777, true);
putenv('OVB_UPLOAD_DIR=' . $tmp);
$_ENV['OVB_UPLOAD_DIR'] = $tmp;
$_SERVER['REQUEST_METHOD'] = 'GET';

$GLOBALS['settings'] = ['waehrung' => 'EUR', 'upload_max_mb' => '10', 'fahrzeug_modul_name' => 'Fuhrpark',
    'ki_aktiv' => '1', 'ki_ollama_url' => 'http://192.168.1.20:11434', 'ablage_modul_name' => 'Dateiablage'];
$GLOBALS['rechte'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['abfragen'] = [];
$GLOBALS['zeilen'] = [];      // [tabelle => Zeilen] für die Dateiabfragen
$GLOBALS['ki_rows'] = [];
$GLOBALS['val'] = [];         // [Stichwort im SQL => Wert]
$GLOBALS['row'] = [];         // [Stichwort im SQL => Zeile]

$kat = static fn(int $id, string $name, ?int $parent = null, int $anzahl = 0, int $nurLeitung = 0): array
    => ['id' => $id, 'parent_id' => $parent, 'name' => $name, 'beschreibung' => $id === 2 ? 'Miete, Strom, Wartung' : '', 'nur_leitung' => $nurLeitung, 'anzahl' => $anzahl];
$GLOBALS['kategorien'] = [
    $kat(5, 'Ausbildung'), $kat(1, 'Verwaltung', null, 2), $kat(3, 'Personal', 1, 1, 1), $kat(4, 'Akten', 3, 4), $kat(2, 'Verträge', 1, 3),
];
$GLOBALS['fahrzeuge'] = [
    ['id' => 7, 'bezeichnung' => 'GKW', 'kennzeichen' => 'THW-80001', 'is_active' => 1, 'n' => 5],
    ['id' => 8, 'bezeichnung' => 'MTW', 'kennzeichen' => '', 'is_active' => 1, 'n' => 0],
    ['id' => 9, 'bezeichnung' => 'Alter Kipper', 'kennzeichen' => 'THW-1', 'is_active' => 0, 'n' => 2],
    ['id' => 10, 'bezeichnung' => 'Verkauft', 'kennzeichen' => '', 'is_active' => 0, 'n' => 0],
];
$GLOBALS['events'] = [
    ['id' => 55, 'name' => 'Sommerfest', 'jahr' => 2026, 'datum' => '2026-06-14 15:00:00', 'n' => 3],
    ['id' => 54, 'name' => 'Tag der offenen Tür', 'jahr' => 2026, 'datum' => '2026-05-02 10:00:00', 'n' => 1],
    ['id' => 40, 'name' => 'Jahresabschluss', 'jahr' => 2025, 'datum' => '2025-12-12 19:00:00', 'n' => 2],
];
$GLOBALS['termine'] = [
    ['id' => 88, 'name' => 'OV-Dienst', 'jahr' => 2026, 'datum' => '2026-03-01 09:00:00', 'ziel' => 'ov', 'ziel_id' => null, 'created_by' => 2, 'n' => 1],
    ['id' => 89, 'name' => 'Stabssitzung', 'jahr' => 2026, 'datum' => '2026-03-05 19:00:00', 'ziel' => 'funktion', 'ziel_id' => 4, 'created_by' => 2, 'n' => 2],
];
$GLOBALS['plaetze'] = [
    ['id' => 1, 'parent_id' => null, 'name' => 'Unterkunft', 'kurz' => ''],
    ['id' => 2, 'parent_id' => 1, 'name' => 'Küche', 'kurz' => 'KÜ'],
    ['id' => 3, 'parent_id' => null, 'name' => 'Fahrzeughalle', 'kurz' => ''],
    ['id' => 4, 'parent_id' => 3, 'name' => 'Tor 3', 'kurz' => 'T3'],
    ['id' => 5, 'parent_id' => 3, 'name' => 'Lager', 'kurz' => ''],
];
$GLOBALS['wuensche'] = [['id' => 123, 'name' => 'Stromerzeuger', 'jahr' => 2026, 'datum' => '2026-02-01 12:00:00', 'n' => 2]];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    $GLOBALS['abfragen'][] = [$sql, $p];
    if (str_starts_with($sql, 'SELECT f.id') && preg_match('/ FROM (\w+) f\b/', $sql, $m)) {
        return $GLOBALS['zeilen'][$m[1]] ?? [];
    }
    if (str_contains($sql, 'FROM ablage_kategorien k')) { return $GLOBALS['kategorien']; }
    if (str_contains($sql, 'FROM vehicles v')) { return $GLOBALS['fahrzeuge']; }
    if (str_contains($sql, 'FROM events e')) { return $GLOBALS['events']; }
    if (str_contains($sql, 'FROM calendar_entries k')) { return $GLOBALS['termine']; }
    if (str_contains($sql, 'FROM standort_bilder GROUP BY')) { return [['standort_id' => 2, 'n' => 3], ['standort_id' => 3, 'n' => 1]]; }
    if (str_contains($sql, 'FROM stan_versionen')) { return [['id' => 3, 'nummer' => 2, 'bemerkung' => 'Stand 2026'], ['id' => 1, 'nummer' => 1, 'bemerkung' => '']]; }
    if (str_contains($sql, 'SELECT version_id, pfad FROM stan_dateien')) {
        return [['version_id' => 3, 'pfad' => 'Lies mich.pdf'], ['version_id' => 3, 'pfad' => 'Fachgruppen/N/FGr N.pdf'],
                ['version_id' => 3, 'pfad' => 'Fachgruppen/N/Anlage.pdf'], ['version_id' => 3, 'pfad' => '2024/alt.pdf'], ['version_id' => 1, 'pfad' => 'a.pdf']];
    }
    if (str_contains($sql, 'FROM wishes w')) { return $GLOBALS['wuensche']; }
    if (str_starts_with($sql, 'SELECT gruppe, COUNT(*)')) { return [['gruppe' => '', 'n' => 2], ['gruppe' => 'Fahrzeuge', 'n' => 3], ['gruppe' => 'Taktische Zeichen', 'n' => 12]]; }
    if (str_starts_with($sql, 'SELECT x.jahr, x.art, COUNT(*)')) { return [['jahr' => 2026, 'art' => 'ausgabe', 'n' => 4], ['jahr' => 2026, 'art' => 'einnahme', 'n' => 1], ['jahr' => 2025, 'art' => 'ausgabe', 'n' => 2]]; }
    if (str_contains($sql, 'FROM datei_ki')) { return $GLOBALS['ki_rows']; }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    foreach ($GLOBALS['row'] as $stichwort => $zeile) { if (str_contains($sql, $stichwort)) { return $zeile; } }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) {
    foreach ($GLOBALS['val'] as $stichwort => $wert) { if (str_contains($sql, $stichwort)) { return $wert; } }
    return $d;
}
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 40 + count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
final class StubStmtA { public function execute(array $p): bool { return true; } }
final class StubPdoA { public function prepare(string $s): StubStmtA { return new StubStmtA(); } }
function db(): StubPdoA { return new StubPdoA(); }
function can(string $what, mixed $ctx = null): bool { return $GLOBALS['rechte'][$what] ?? true; }
function current_user(): ?array { return $GLOBALS['ich'] ?? ['id' => 1, 'role' => 'leitung', 'display_name' => 'Tester']; }
// Die Module als Attrappen – die Dateiablage kennt nur ihre Schnittstelle
function kalender_fachgruppen_sub(): string { return "'' AS fachgruppen_ids"; }
function kalender_sichtbar(array $t, array $u): bool { return (string)$t['ziel'] === 'ov' || in_array((string)($u['role'] ?? ''), ['admin', 'leitung'], true); }
function kalender_find(int $id): ?array { return ['id' => $id, 'titel' => 'Termin', 'ziel' => 'ov', 'created_by' => 2]; }
function kalender_darf_bearbeiten(array $t, array $u): bool { return in_array((string)($u['role'] ?? ''), ['admin', 'leitung'], true); }
function standort_all(bool $nurAktive = false): array { return $GLOBALS['plaetze']; }
function wish_find(int $id): ?array { return ['id' => $id, 'bezeichnung' => 'Stromerzeuger']; }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'vehicle_files', 'ki', 'ablage', 'expense_files'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};
$leitung = ['id' => 1, 'role' => 'leitung', 'display_name' => 'Tester'];
$helfer = ['id' => 17, 'role' => 'user', 'display_name' => 'Anna'];
$alsHelfer = static function () use ($helfer): void {
    $GLOBALS['ich'] = $helfer;
    $GLOBALS['rechte'] = ['manage_ablage' => false, 'upload_ablage' => true, 'view_newsletter' => false, 'manage_vehicles' => false,
        'manage_events' => false, 'admin' => false, 'manage_newsletter' => false, 'manage_wishes' => false, 'manage_budget' => false];
};
$alsLeitung = static function () use ($leitung): void { $GLOBALS['ich'] = $leitung; $GLOBALS['rechte'] = []; };
$kategorien = $GLOBALS['kategorien'];

/* ---------- Schlüssel ---------- */
$check('eigene Kategorie', ablage_schluessel('k12') === ['art' => 'eigen', 'id' => 12, 'quelle' => '', 'teil' => '', 'rest' => '']);
$check('Wurzel eines Moduls', ablage_schluessel('fahrzeug') === ['art' => 'auto', 'id' => 0, 'quelle' => 'fahrzeug', 'teil' => '', 'rest' => '']);
$check('Fahrzeug', ablage_schluessel('fahrzeug.7')['id'] === 7 && ablage_schluessel('fahrzeug.7')['quelle'] === 'fahrzeug');
$check('Jahr hat keine Kennung', ablage_schluessel('veranstaltung.j2026')['id'] === 0 && ablage_schluessel('veranstaltung.j2026')['teil'] === 'j2026');
$check('STAN-Ordner mit Punkt im Namen', ablage_schluessel('stan.3.Fachgruppen/N.1/x')['rest'] === 'Fachgruppen/N.1/x' && ablage_schluessel('stan.3.A')['id'] === 3);
$check('Unbekanntes und Unsinn', ablage_schluessel('quatsch.1')['art'] === '' && ablage_schluessel('')['art'] === ''
    && ablage_schluessel('k0')['art'] === '' && ablage_schluessel('k1x')['art'] === '' && ablage_schluessel('12')['art'] === '');

/* ---------- Eigene Kategorien: Vererbung, Kreise, Auswahl ---------- */
$index = ablage_kategorien_index($kategorien);
$check('nur Leitung: selbst, geerbt, frei', ablage_kategorie_nur_leitung(3, $index) && ablage_kategorie_nur_leitung(4, $index)
    && !ablage_kategorie_nur_leitung(2, $index) && !ablage_kategorie_nur_leitung(99, $index));
$check('Nachkommen über alle Ebenen', ablage_kategorie_nachkommen(1, $kategorien) === [3, 2, 4] && ablage_kategorie_nachkommen(4, $kategorien) === []);
$check('umhängen: oberste Ebene und fremder Ast erlaubt', ablage_eltern_erlaubt(3, null, $kategorien) && ablage_eltern_erlaubt(3, 5, $kategorien) && ablage_eltern_erlaubt(null, 4, $kategorien));
$check('umhängen: nicht unter sich selbst, nicht unter die eigenen Kinder, nicht ins Leere',
    !ablage_eltern_erlaubt(1, 1, $kategorien) && !ablage_eltern_erlaubt(1, 4, $kategorien) && !ablage_eltern_erlaubt(1, 99, $kategorien));
$kreis = [['id' => 1, 'parent_id' => 2, 'name' => 'a', 'nur_leitung' => 0], ['id' => 2, 'parent_id' => 1, 'name' => 'b', 'nur_leitung' => 0]];
$check('ein Kreis in den Daten hängt nichts auf', ablage_kategorie_nachkommen(1, $kreis) === [2] && !ablage_kategorie_nur_leitung(1, ablage_kategorien_index($kreis)));
$opt = ablage_kategorie_optionen($kategorien, 2, '– oberste Ebene –', [3]);
$check('Auswahl: eingerückt, gewählt, ohne die ausgenommene samt Unterkategorien',
    str_contains($opt, '<option value="">– oberste Ebene –</option>') && str_contains($opt, '<option value="2" selected>– Verträge</option>')
    && str_contains($opt, '<option value="1">Verwaltung</option>') && !str_contains($opt, 'Personal') && !str_contains($opt, 'Akten'));

$alle = ablage_eigene_knoten($kategorien, true);
$offen = ablage_eigene_knoten($kategorien, false);
$check('Leitung sieht alle fünf, die anderen ohne Personal und alles darunter',
    count($alle) === 5 && array_column($offen, 'key') === ['k5', 'k1', 'k2']);
$check('Knoten der eigenen Kategorie', $alle[2]['key'] === 'k3' && $alle[2]['eltern'] === 'k1' && $alle[2]['nur_leitung'] === true
    && $alle[3]['nur_leitung'] === true && $alle[4]['beschreibung'] === 'Miete, Strom, Wartung' && $alle[0]['auto'] === false);

/* ---------- Baum ---------- */
$baum = ablage_baum($alle);
$check('Wurzeln in gelieferter Reihenfolge', $baum['wurzeln'] === ['k5', 'k1']);
$check('Kinder, Tiefe', $baum['knoten']['k1']['kinder'] === ['k3', 'k2'] && $baum['knoten']['k4']['tiefe'] === 2 && $baum['knoten']['k1']['tiefe'] === 0);
$check('Summe samt allem darunter', $baum['knoten']['k1']['gesamt'] === 10 && $baum['knoten']['k3']['gesamt'] === 5 && $baum['knoten']['k1']['anzahl'] === 2);
$check('Weg und Text', array_column(ablage_pfad('k4', $baum), 'key') === ['k1', 'k3', 'k4'] && ablage_pfad_text('k4', $baum) === 'Verwaltung › Personal › Akten');
$check('Weg ins Leere', ablage_pfad('k99', $baum) === [] && ablage_pfad_text('', $baum) === '');
$waise = ablage_baum([ablage_knoten_neu('k9', 'k77', 'Waise', ['anzahl' => 1])]);
$check('ohne Eltern wird es eine Wurzel', $waise['wurzeln'] === ['k9'] && $waise['knoten']['k9']['eltern'] === null);

/* ---------- Automatische Kategorien je Modul ---------- */
$fz = ablage_knoten_fahrzeuge($GLOBALS['fahrzeuge']);
$check('Fahrzeuge: alle im Dienst, außer Dienst nur mit Dateien', array_column($fz, 'key') === ['fahrzeug.7', 'fahrzeug.8', 'fahrzeug.9']);
$check('Fahrzeug: gesperrt, Kennzeichen, Zahl, Verweis', $fz[0]['auto'] === true && $fz[0]['eltern'] === 'fahrzeug' && $fz[0]['hinweis'] === 'THW-80001'
    && $fz[0]['anzahl'] === 5 && str_contains($fz[0]['url'], 'p=vehicle&id=7') && $fz[1]['hinweis'] === '' && $fz[2]['hinweis'] === 'THW-1 · außer Dienst');
$ev = ablage_knoten_jahre('veranstaltung', $GLOBALS['events'], 'event', '#dateien');
$check('Veranstaltungen: Jahre zuerst, dann die Einträge darunter', array_column($ev, 'key') === ['veranstaltung.j2026', 'veranstaltung.j2025', 'veranstaltung.55', 'veranstaltung.54', 'veranstaltung.40']);
$check('Jahr und Eintrag', $ev[0]['name'] === '2026' && $ev[0]['eltern'] === 'veranstaltung' && $ev[2]['eltern'] === 'veranstaltung.j2026'
    && $ev[2]['hinweis'] === '14.06.2026' && $ev[4]['eltern'] === 'veranstaltung.j2025' && str_ends_with($ev[2]['url'], '#dateien'));
$check('ohne Datum', ablage_knoten_jahre('wunsch', [['id' => 1, 'name' => 'x', 'jahr' => null, 'datum' => null, 'n' => 1]], 'wish')[0]['name'] === 'ohne Datum');
$st = ablage_knoten_standorte($GLOBALS['plaetze'], [2 => 3, 3 => 1, 5 => 0, 99 => 4]);
$check('Plätze: nur mit Bildern und der Weg dorthin', array_column($st, 'key') === ['standort.1', 'standort.2', 'standort.3']);
$check('Platz hängt unter seinem Platz, sonst unter dem Modul', $st[1]['eltern'] === 'standort.1' && $st[0]['eltern'] === 'standort'
    && $st[0]['anzahl'] === 0 && $st[1]['anzahl'] === 3 && $st[1]['hinweis'] === 'KÜ');
$check('STAN-Ordner aus dem Pfad', ablage_stan_ordner('A/B/c.pdf') === 'A/B' && ablage_stan_ordner('c.pdf') === '' && ablage_stan_ordner('A\\B\\c.pdf') === 'A/B');
$stan = ablage_knoten_stan([['id' => 3, 'nummer' => 2, 'bemerkung' => 'Stand 2026'], ['id' => 1, 'nummer' => 1, 'bemerkung' => '']], db_all('SELECT version_id, pfad FROM stan_dateien'));
$check('STAN: Versionen und Ordner als Baum', array_column($stan, 'key') === ['stan.3', 'stan.3.2024', 'stan.3.Fachgruppen', 'stan.3.Fachgruppen/N', 'stan.1']);
$check('STAN: Zahl je Ordner, Zwischenordner ohne Dateien, Name ist das letzte Stück', $stan[0]['anzahl'] === 1 && $stan[0]['hinweis'] === 'aktuell · Stand 2026'
    && $stan[2]['anzahl'] === 0 && $stan[3]['anzahl'] === 2 && $stan[3]['name'] === 'N' && $stan[3]['eltern'] === 'stan.3.Fachgruppen' && $stan[1]['eltern'] === 'stan.3'
    && $stan[4]['hinweis'] === '');
$bu = ablage_knoten_buchungen([['jahr' => 2026, 'art' => 'ausgabe', 'n' => 4], ['jahr' => 2026, 'art' => 'einnahme', 'n' => 1], ['jahr' => 2025, 'art' => 'ausgabe', 'n' => 2]]);
$check('Budget: je Jahr Ausgaben und Einnahmen', array_column($bu, 'key') === ['buchung.j2026', 'buchung.j2025', 'buchung.j2026.ausgabe', 'buchung.j2026.einnahme', 'buchung.j2025.ausgabe']);
$check('Budget: Namen, Zahl, Verweis in die Liste des Jahres', $bu[2]['name'] === 'Ausgaben' && $bu[3]['name'] === 'Einnahmen' && $bu[2]['eltern'] === 'buchung.j2026' && $bu[2]['anzahl'] === 4
    && $bu[0]['anzahl'] === 0 && $bu[0]['auto'] === true && str_contains($bu[3]['url'], 'p=expenses&jahr=2026&art=einnahme'));
$check('Schlüssel eines Budgetknotens', ablage_schluessel('buchung.j2026.einnahme')['teil'] === 'j2026' && ablage_schluessel('buchung.j2026.einnahme')['rest'] === 'einnahme'
    && ablage_schluessel('buchung.j2026.einnahme')['id'] === 0);

/* ---------- Der ganze Baum: Leitung ---------- */
$alsLeitung();
$GLOBALS['val'] = ['FROM newsletter' => 6];
$ganz = ablage_baum_fuer($leitung);
$check('erst die eigenen, dann je Modul eine gesperrte Kategorie', $ganz['wurzeln'] === ['k5', 'k1', 'fahrzeug', 'veranstaltung', 'termin', 'standort', 'stan', 'newsletter', 'wunsch', 'buchung', 'karte']);
$check('Karten › Gruppe, ohne Gruppe zuletzt', $ganz['knoten']['karte']['name'] === 'Karten' && $ganz['knoten']['karte']['kinder'] === ['karte.g.Fahrzeuge', 'karte.g.Taktische Zeichen', 'karte.ohne']
    && $ganz['knoten']['karte']['gesamt'] === 17 && $ganz['knoten']['karte.ohne']['name'] === 'Ohne Gruppe' && ablage_pfad_text('karte.g.Taktische Zeichen', $ganz) === 'Karten › Taktische Zeichen'
    && str_contains($ganz['knoten']['karte.g.Fahrzeuge']['url'], 'p=karten_symbole'));
$check('Budget › Jahr › Ausgaben mit Summe', $ganz['knoten']['buchung']['name'] === 'Budget' && $ganz['knoten']['buchung']['gesamt'] === 7
    && ablage_pfad_text('buchung.j2026.einnahme', $ganz) === 'Budget › 2026 › Einnahmen' && $ganz['knoten']['buchung.j2026']['gesamt'] === 5);
$check('Modulname aus den Einstellungen, sonst Vorgabe', $ganz['knoten']['fahrzeug']['name'] === 'Fuhrpark' && $ganz['knoten']['wunsch']['name'] === 'Wünsch dir was'
    && $ganz['knoten']['fahrzeug']['auto'] === true && str_contains($ganz['knoten']['fahrzeug']['url'], 'p=vehicles'));
$check('Fahrzeuge darunter mit Summe', $ganz['knoten']['fahrzeug']['kinder'] === ['fahrzeug.7', 'fahrzeug.8', 'fahrzeug.9'] && $ganz['knoten']['fahrzeug']['gesamt'] === 7);
$check('Veranstaltungen › Jahr › Eintrag', $ganz['knoten']['veranstaltung']['kinder'] === ['veranstaltung.j2026', 'veranstaltung.j2025']
    && $ganz['knoten']['veranstaltung.j2026']['gesamt'] === 4 && ablage_pfad_text('veranstaltung.55', $ganz) === 'Veranstaltungen › 2026 › Sommerfest');
$check('Leitung sieht beide Termine', isset($ganz['knoten']['termin.88'], $ganz['knoten']['termin.89']));
$check('Newsletter liegen direkt in der Kategorie', $ganz['knoten']['newsletter']['anzahl'] === 6 && $ganz['knoten']['newsletter']['kinder'] === []);
$check('STAN und Wünsche', $ganz['knoten']['stan']['gesamt'] === 5 && ablage_pfad_text('wunsch.123', $ganz) === 'Wünsch dir was › 2026 › Stromerzeuger');

/* ---------- Der ganze Baum: Helfer ohne Newsletter, ohne Fahrzeuge ---------- */
$alsHelfer();
$GLOBALS['rechte']['view_vehicles'] = false;
$klein = ablage_baum_fuer($helfer);
$check('ohne Recht keine Kategorie des Moduls', !isset($klein['knoten']['fahrzeug']) && !isset($klein['knoten']['fahrzeug.7']) && !isset($klein['knoten']['newsletter']));
$GLOBALS['rechte']['view_expenses'] = false;
$check('wer die Buchungen nicht sieht, sieht auch keine Belege', !isset(ablage_baum_fuer($helfer)['knoten']['buchung']) && isset($klein['knoten']['buchung.j2026.ausgabe']));
unset($GLOBALS['rechte']['view_expenses']);
$check('„Nur Leitung" fehlt samt allem darunter', !isset($klein['knoten']['k3']) && !isset($klein['knoten']['k4']) && isset($klein['knoten']['k2'])
    && $klein['knoten']['k1']['gesamt'] === 5);
$check('nur Termine, die man im Kalender sieht', isset($klein['knoten']['termin.88']) && !isset($klein['knoten']['termin.89']) && $klein['knoten']['termin']['gesamt'] === 1);
$GLOBALS['rechte']['view_vehicles'] = true;

/* ---------- Dokumente in einer Form ---------- */
foreach (['ablage', 'fahrzeug', 'veranstaltung', 'termin', 'standort', 'stan', 'newsletter', 'wunsch', 'buchung'] as $typ) {
    $sq = ablage_quelle_sql($typ);
    $alleSpalten = true;
    foreach (['orig_name', 'mime', 'size_bytes', 'created_at', 'titel', 'thumb_name', 'art', 'objekt_id', 'zusatz', 'uploaded_by', 'hochgeladen_von'] as $spalte) {
        $alleSpalten = $alleSpalten && (bool)preg_match('/\b' . $spalte . '\b/', $sq['spalten']);
    }
    $check("Quelle $typ: gemeinsame Spalten, Auslieferung wie bei der KI", $alleSpalten && str_starts_with($sq['spalten'], 'f.id') && $sq['suche'] !== []
        && $sq['route'] === KI_TYPEN[$typ]['route'] && str_contains($sq['von'], KI_TYPEN[$typ]['tabelle'] . ' f'));
}
$check('unbekannte Quelle', ablage_quelle_sql('kontakt') === null && ablage_zeilen('kontakt') === []);
$kartenQuelle = ablage_quelle_sql('karte');
$check('Quelle karte: Symbole, das Symbol ist seine eigene Vorschau', $kartenQuelle['route'] === 'karten_symbol' && str_contains($kartenQuelle['von'], 'karten_symbole f')
    && str_contains($kartenQuelle['spalten'], 'f.stored_name AS thumb_name') && str_contains($kartenQuelle['spalten'], 'f.gruppe AS zusatz') && str_starts_with($kartenQuelle['spalten'], 'f.id'));
$check('jede Quelle ist eine Kategorie und – bis auf die Symbole der Karten – eine KI-Ablage', array_values(array_diff(array_keys(ABLAGE_QUELLEN), array_keys(KI_TYPEN))) === ['karte']
    && KI_TYPEN['ablage']['schalter'] === 'ki_ablage' && KI_TYPEN['wunsch']['schalter'] === 'ki_wuensche');

$zeile = ablage_zeile_fertig('fahrzeug', ['id' => '31', 'titel' => ' ', 'orig_name' => 'Schein.pdf', 'thumb_name' => null, 'objekt_id' => '7', 'zusatz' => null], 'vehicle_file');
$check('Zeile: Name fällt auf den Dateinamen, Verweis, keine Vorschau', $zeile['typ'] === 'fahrzeug' && $zeile['id'] === 31 && $zeile['name'] === 'Schein.pdf'
    && $zeile['zusatz'] === '' && str_contains($zeile['link'], 'p=vehicle_file&id=31') && $zeile['vorschau'] === '' && $zeile['objekt_id'] === 7);
$mitBild = ablage_zeile_fertig('ablage', ['id' => 5, 'titel' => 'Plan', 'orig_name' => 'plan.jpg', 'thumb_name' => 'x_vorschau.jpg', 'objekt_id' => 2], 'ablage_datei');
$check('Zeile: Titel und Vorschau', $mitBild['name'] === 'Plan' && str_contains($mitBild['vorschau'], 'p=ablage_datei&id=5&vorschau=1'));

$check('Knoten einer Datei', ablage_datei_knoten(['typ' => 'ablage', 'objekt_id' => 2]) === 'k2' && ablage_datei_knoten(['typ' => 'fahrzeug', 'objekt_id' => 7]) === 'fahrzeug.7'
    && ablage_datei_knoten(['typ' => 'newsletter', 'objekt_id' => 0]) === 'newsletter' && ablage_datei_knoten(['typ' => 'stan', 'objekt_id' => 3, 'zusatz' => 'Fachgruppen/N/a.pdf']) === 'stan.3.Fachgruppen/N'
    && ablage_datei_knoten(['typ' => 'stan', 'objekt_id' => 3, 'zusatz' => 'a.pdf']) === 'stan.3' && ablage_datei_knoten(['typ' => 'kontakt', 'objekt_id' => 1]) === ''
    && ablage_datei_knoten(['typ' => 'buchung', 'objekt_id' => 9, 'gruppe_jahr' => '2026', 'gruppe_art' => 'einnahme']) === 'buchung.j2026.einnahme'
    && ablage_datei_knoten(['typ' => 'buchung', 'objekt_id' => 9, 'gruppe_jahr' => 2025, 'gruppe_art' => 'ausgabe']) === 'buchung.j2025.ausgabe'
    && ablage_datei_knoten(['typ' => 'karte', 'zusatz' => 'Taktische Zeichen']) === 'karte.g.Taktische Zeichen' && ablage_datei_knoten(['typ' => 'karte', 'zusatz' => '']) === 'karte.ohne'
    && ablage_datei_knoten(['typ' => 'karte', 'gruppe' => 'A.B', 'zusatz' => '']) === 'karte.g.A.B');
$check('Endung für das Symbol', ablage_endung('Schein.pdf') === 'PDF' && ablage_endung('README') === '?' && ablage_endung('a.tabelle') === 'TABE');

$roh = static fn(int $id, int $objekt, string $name, array $mehr = []): array => $mehr + ['id' => $id, 'titel' => '', 'orig_name' => $name, 'mime' => 'application/pdf',
    'size_bytes' => 2048, 'created_at' => '2026-01-0' . ($id % 9 + 1) . ' 10:00:00', 'thumb_name' => null, 'art' => 'dokument', 'objekt_id' => $objekt, 'zusatz' => null, 'uploaded_by' => 17, 'hochgeladen_von' => 'Anna'];
$GLOBALS['zeilen'] = [
    'ablage_dateien'   => [$roh(1, 2, 'Mietvertrag.pdf', ['titel' => 'Mietvertrag Halle']), $roh(2, 4, 'Personalakte.pdf', ['uploaded_by' => 1])],
    'vehicle_files'    => [$roh(31, 7, 'Schein.pdf', ['zusatz' => 'Auftrag 2026-003'])],
    'stan_dateien'     => [$roh(61, 3, 'Lies mich.pdf', ['zusatz' => 'Lies mich.pdf']), $roh(62, 3, 'FGr N.pdf', ['zusatz' => 'Fachgruppen/N/FGr N.pdf']), $roh(63, 3, 'alt.pdf', ['zusatz' => '2024/alt.pdf'])],
    'newsletter'       => [$roh(71, 0, 'ausgabe-3.pdf', ['titel' => 'Ausgabe 3'])],
    'calendar_files'   => [$roh(81, 89, 'stab.pdf')],
    'karten_symbole'   => [$roh(95, 0, 'gkw.svg', ['titel' => 'GKW', 'mime' => 'image/svg+xml', 'thumb_name' => 'x.svg', 'art' => 'bild', 'zusatz' => 'Taktische Zeichen'])],
    'expense_files'    => [$roh(91, 300, 'rechnung-zelt.pdf', ['zusatz' => 'Zelt für das Sommerfest', 'gruppe_jahr' => 2026, 'gruppe_art' => 'ausgabe'])],
];
$GLOBALS['abfragen'] = [];
$eigeneDocs = ablage_dateien_von($ganz['knoten']['k2']);
[$sql, $p] = end($GLOBALS['abfragen']);
$check('eigene Kategorie: nach kategorie_id, neueste zuerst', str_contains($sql, 'FROM ablage_dateien f') && str_contains($sql, 'WHERE (f.kategorie_id = ?)') && $p === [2]
    && str_contains($sql, 'ORDER BY f.created_at DESC') && $eigeneDocs[0]['typ'] === 'ablage' && $eigeneDocs[0]['name'] === 'Mietvertrag Halle');
ablage_dateien_von($ganz['knoten']['fahrzeug.7']);
[$sql, $p] = end($GLOBALS['abfragen']);
$check('Fahrzeug: nach vehicle_id mit Auftrag als Zusatz', str_contains($sql, 'FROM vehicle_files f') && str_contains($sql, 'f.vehicle_id = ?') && $p === [7] && str_contains($sql, "CONCAT('Auftrag '"));
$n = count($GLOBALS['abfragen']);
$check('Wurzel und Jahr haben keine eigenen Dokumente – und fragen auch nicht', ablage_dateien_von($ganz['knoten']['fahrzeug']) === []
    && ablage_dateien_von($ganz['knoten']['veranstaltung.j2026']) === [] && count($GLOBALS['abfragen']) === $n);
$nl = ablage_dateien_von($ganz['knoten']['newsletter']);
[$sql] = end($GLOBALS['abfragen']);
$check('Newsletter: nur mit hochgeladenem PDF', count($nl) === 1 && str_contains($sql, "WHERE (f.stored_name <> '')"));
$belegDocs = ablage_dateien_von($ganz['knoten']['buchung.j2026.ausgabe']);
[$sql, $p] = end($GLOBALS['abfragen']);
$check('Budget: Belege nach Jahr und Art, die Buchung als Zusatz', str_contains($sql, 'FROM expense_files f') && str_contains($sql, 'JOIN expenses x ON x.id = f.expense_id')
    && str_contains($sql, 'WHERE (x.jahr = ? AND x.art = ?)') && $p === [2026, 'ausgabe'] && $belegDocs[0]['zusatz'] === 'Zelt für das Sommerfest'
    && str_contains($belegDocs[0]['link'], 'p=expense_file&id=91') && ablage_im_baum($belegDocs, $ganz)[0]['knoten'] === 'buchung.j2026.ausgabe');
$n = count($GLOBALS['abfragen']);
$check('Budget: Wurzel und Jahr tragen keine Belege, Unsinn fragt nicht', ablage_dateien_von($ganz['knoten']['buchung']) === [] && ablage_dateien_von($ganz['knoten']['buchung.j2026']) === []
    && ablage_dateien_von(['key' => 'buchung.j2026.quatsch']) === [] && ablage_dateien_von(['key' => 'buchung.jx.ausgabe']) === [] && count($GLOBALS['abfragen']) === $n);
$symbolDocs = ablage_dateien_von($ganz['knoten']['karte.g.Taktische Zeichen']);
[$sql, $p] = end($GLOBALS['abfragen']);
$check('Karten: Symbole einer Gruppe, mit Vorschau, ohne doppelte Gruppe', str_contains($sql, 'FROM karten_symbole f') && str_contains($sql, 'WHERE (f.gruppe = ?)') && $p === ['Taktische Zeichen']
    && $symbolDocs[0]['name'] === 'GKW' && $symbolDocs[0]['zusatz'] === '' && str_contains($symbolDocs[0]['vorschau'], 'p=karten_symbol&id=95')
    && ablage_im_baum($symbolDocs, $ganz)[0]['knoten'] === 'karte.g.Taktische Zeichen');
ablage_dateien_von($ganz['knoten']['karte.ohne']);
$check('Karten: ohne Gruppe fragt nach der leeren Gruppe', end($GLOBALS['abfragen'])[1] === ['']);
$n = count($GLOBALS['abfragen']);
$check('Karten: die Wurzel trägt keine Symbole', ablage_dateien_von($ganz['knoten']['karte']) === [] && ablage_dateien_von(['key' => 'karte.g']) === [] && count($GLOBALS['abfragen']) === $n);
$symbolRechte = ablage_rechte_setzen(ablage_im_baum($symbolDocs, $ganz), $leitung);
$check('Karten: Symbole haben keine KI-Auswertung und werden im Modul gepflegt', $symbolRechte[0]['darf_ki'] === false && $symbolRechte[0]['darf_aendern'] === false
    && !str_contains(render_partial('partials/ablage_datei', ['d' => $symbolRechte[0], 'kiDaten' => [], 'baum' => $ganz, 'eigene' => [], 'zeigeOrt' => false, 'hier' => '/index.php?p=ablage']), 'name="typ"'));
$wurzelStan = ablage_dateien_von($ganz['knoten']['stan.3']);
$ordnerStan = ablage_dateien_von($ganz['knoten']['stan.3.Fachgruppen/N']);
$check('STAN: je Ordner nur die Dateien direkt darin', array_column($wurzelStan, 'id') === [61] && array_column($ordnerStan, 'id') === [62]
    && $ordnerStan[0]['zusatz'] === '' && $ordnerStan[0]['pfad'] === 'Fachgruppen/N/FGr N.pdf' && ablage_dateien_von($ganz['knoten']['stan.3.Fachgruppen']) === []);

/* ---------- Sichtbarkeit über den Baum, Rechte je Datei ---------- */
$gemischt = array_merge(ablage_zeilen('ablage'), ablage_zeilen('termin'), ablage_zeilen('fahrzeug'));
$check('Leitung: alles im Baum', array_column(ablage_im_baum($gemischt, $ganz), 'knoten') === ['k2', 'k4', 'termin.89', 'fahrzeug.7']);
$check('Helfer: keine Personalakte, kein fremder Termin', array_column(ablage_im_baum($gemischt, $klein), 'knoten') === ['k2']);
$alsHelfer();
$r = ablage_rechte_setzen(ablage_im_baum($gemischt, $ganz), $helfer);
$check('Helfer: das Eigene ändern, Fremdes nicht, Module gar nicht', $r[0]['darf_aendern'] === true && $r[0]['darf_ki'] === true && $r[1]['darf_aendern'] === false
    && $r[2]['darf_ki'] === false && $r[3]['darf_aendern'] === false && $r[3]['darf_ki'] === false);
$GLOBALS['rechte']['upload_ablage'] = false;
$check('ohne Hochladerecht auch das Eigene nicht', ablage_rechte_setzen(ablage_im_baum($gemischt, $ganz), $helfer)[0]['darf_aendern'] === false);
$alsLeitung();
$r = ablage_rechte_setzen(ablage_im_baum($gemischt, $ganz), $leitung);
$check('Leitung: alles Eigene, Module nie hier umhängen', $r[1]['darf_aendern'] === true && $r[3]['darf_aendern'] === false && $r[3]['darf_ki'] === true && $r[2]['darf_ki'] === true);
$check('neueste: nur aus eigenen Kategorien, begrenzt', array_column(ablage_neueste($ganz, 1), 'id') === [1] && count(ablage_neueste($klein)) === 1);

/* ---------- Suche ---------- */
$GLOBALS['ki_rows'] = [['typ' => 'fahrzeug', 'datei_id' => 31], ['typ' => 'stan', 'datei_id' => 62], ['typ' => 'kontakt', 'datei_id' => 5], ['typ' => 'ablage', 'datei_id' => 1]];
$GLOBALS['abfragen'] = [];
$treffer = ablage_suche(' 100%_miete ', $ganz);
$erste = $GLOBALS['abfragen'][0];
$check('Suche maskiert % und _', $erste[1] === ['%100\\%\\_miete%', '%100\\%\\_miete%'] && str_contains($erste[0], 'f.titel LIKE ? OR f.orig_name LIKE ?'));
$check('Suche läuft durch alle Ablagen der Person', count(array_filter($GLOBALS['abfragen'], static fn($a) => str_starts_with($a[0], 'SELECT f.id'))) === 10);
$belegSuche = array_values(array_filter($GLOBALS['abfragen'], static fn($a) => str_contains($a[0], 'FROM expense_files f')))[0];
$check('Belege findet man auch über Buchung, Lieferant und Belegnummer', str_contains($belegSuche[0], 'x.bezeichnung LIKE ? OR x.lieferant LIKE ? OR x.beleg_nr LIKE ?') && count($belegSuche[1]) === 5);
$check('leere Suche', ablage_suche('  ', $ganz) === []);
$check('Treffer tragen ihren Knoten, jede Datei einmal', count($treffer) === count(array_unique(array_map(static fn($d) => $d['typ'] . ':' . $d['id'], $treffer)))
    && in_array('k2', array_column($treffer, 'knoten'), true) && in_array('stan.3.Fachgruppen/N', array_column($treffer, 'knoten'), true));
$GLOBALS['zeilen']['newsletter'] = [];
$kleinTreffer = ablage_suche('akte', $klein);
$check('Helfer findet nichts außerhalb seines Baums', !in_array('k4', array_column($kleinTreffer, 'knoten'), true) && !in_array('termin.89', array_column($kleinTreffer, 'knoten'), true)
    && !in_array('newsletter', array_column($kleinTreffer, 'typ'), true));
$GLOBALS['abfragen'] = [];
ablage_suche('x', $klein);
$check('Ablagen ohne Recht werden gar nicht erst gefragt', array_filter($GLOBALS['abfragen'], static fn($a) => str_contains($a[0], 'FROM newsletter f')) === []);

/* ---------- Kategorien pflegen ---------- */
$post = static function (array $p): void { $_POST = $p; $GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; };
$post(['name' => '  ', 'parent_id' => '']);
$check('ohne Namen', ablage_kategorie_save_from_post(null, $leitung)[1] !== [] && $GLOBALS['inserts'] === []);
$post(['name' => 'verträge', 'parent_id' => '1']);
$check('gleicher Name auf gleicher Ebene', str_contains(ablage_kategorie_save_from_post(null, $leitung)[1][0], 'schon eine Kategorie'));
$post(['name' => 'Verträge', 'parent_id' => '5']);
[$neuId, $fehler] = ablage_kategorie_save_from_post(null, $leitung);
$check('gleicher Name auf anderer Ebene geht', $fehler === [] && $neuId > 0 && $GLOBALS['inserts'][0][0] === 'ablage_kategorien'
    && $GLOBALS['inserts'][0][1]['parent_id'] === 5 && $GLOBALS['inserts'][0][1]['created_by'] === 1 && $GLOBALS['inserts'][0][1]['nur_leitung'] === 0);
$post(['name' => 'Verwaltung', 'parent_id' => '4']);
$check('nicht unter die eigene Unterkategorie', str_contains(ablage_kategorie_save_from_post($index[1], $leitung)[1][0], 'unter sich selbst') && $GLOBALS['updates'] === []);
$post(['name' => " Verwaltung   und\tKasse ", 'parent_id' => '', 'beschreibung' => ' Alles Amtliche ', 'nur_leitung' => '1']);
[$id, $fehler] = ablage_kategorie_save_from_post($index[1], $leitung);
$check('ändern: Name geglättet, oberste Ebene, Haken', $fehler === [] && $id === 1 && $GLOBALS['updates'][0][1] === ['parent_id' => null, 'name' => 'Verwaltung und Kasse', 'beschreibung' => 'Alles Amtliche', 'nur_leitung' => 1]);
$_POST = [];
$GLOBALS['execs'] = [];
$GLOBALS['val'] = ['FROM ablage_kategorien WHERE parent_id' => 2];
$check('löschen: nicht mit Unterkategorien', str_contains((string)ablage_kategorie_delete($index[1]), 'Unterkategorien') && $GLOBALS['execs'] === []);
$GLOBALS['val'] = ['FROM ablage_dateien WHERE kategorie_id' => 3];
$check('löschen: nicht mit Dokumenten', str_contains((string)ablage_kategorie_delete($index[2]), 'Dokumente') && $GLOBALS['execs'] === []);
$GLOBALS['val'] = [];
$check('löschen: leer geht', ablage_kategorie_delete($index[5]) === null && str_contains($GLOBALS['execs'][0][0], 'DELETE FROM ablage_kategorien') && $GLOBALS['execs'][0][1] === [5]);

/* ---------- Hochladen: Datei, Eintrag, KI-Auftrag ---------- */
$pdf = $tmp . '/quelle.pdf';
file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
$boese = $tmp . '/boese.html';
file_put_contents($boese, '<html><body><script>alert(1)</script></body></html>');
$_FILES = ['dateien' => [
    'name' => ['Mietvertrag 2026.pdf', 'seite.html', ''],
    'tmp_name' => [$pdf, $boese, ''],
    'size' => [filesize($pdf), filesize($boese), 0],
    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
]];
$GLOBALS['inserts'] = [];
$GLOBALS['execs'] = [];
[$anzahl, $fehler] = afile_store_uploads(2, 'dateien', '', $leitung);
$eintrag = $GLOBALS['inserts'][0][1] ?? [];
$check('ein Dokument abgelegt, HTML abgelehnt', $anzahl === 1 && count($fehler) === 1 && str_contains($fehler[0], 'seite.html'));
$check('Eintrag: Kategorie, Titel aus dem Dateinamen, wer', ($GLOBALS['inserts'][0][0] ?? '') === 'ablage_dateien' && $eintrag['kategorie_id'] === 2 && $eintrag['titel'] === 'Mietvertrag 2026'
    && $eintrag['orig_name'] === 'Mietvertrag 2026.pdf' && $eintrag['art'] === 'dokument' && $eintrag['uploaded_by'] === 1 && $eintrag['thumb_name'] === null);
$check('Datei liegt im Ordner ablage unter zufälligem Namen', is_file($tmp . '/ablage/' . $eintrag['stored_name']) && !str_contains($eintrag['stored_name'], 'Mietvertrag')
    && str_ends_with($eintrag['stored_name'], '.pdf'));
$kiAuftrag = array_values(array_filter($GLOBALS['execs'], static fn($e) => str_contains($e[0], 'INSERT INTO ki_auftraege')));
$check('jedes Dokument geht in die KI-Auswertung', count($kiAuftrag) === 1 && $kiAuftrag[0][1][0] === 'ablage' && $kiAuftrag[0][1][1] === 41);
$check('ohne Feld im Formular', afile_store_uploads(2, 'fehlt', '', $leitung)[0] === 0 && afile_store_uploads(2, 'fehlt', '', $leitung)[1] !== []);

$GLOBALS['settings']['ki_ablage'] = '0';
settings_reset_cache();
file_put_contents($pdf, "%PDF-1.4\n%%EOF\n");
$_FILES['dateien'] = ['name' => ['zwei.pdf'], 'tmp_name' => [$pdf], 'size' => [filesize($pdf)], 'error' => [UPLOAD_ERR_OK]];
$GLOBALS['execs'] = [];
$GLOBALS['inserts'] = [];
[$anzahl] = afile_store_uploads(2, 'dateien', 'Zweitschrift', $leitung);
$check('Schalter aus: abgelegt, aber nichts eingereiht', $anzahl === 1 && $GLOBALS['inserts'][0][1]['titel'] === 'Zweitschrift'
    && array_filter($GLOBALS['execs'], static fn($e) => str_contains($e[0], 'ki_auftraege')) === []);
$GLOBALS['settings']['ki_ablage'] = '1';
settings_reset_cache();

$datei = $GLOBALS['inserts'][0][1] + ['id' => 41];
$check('Pfad zur Datei', afile_path($datei) === $tmp . '/ablage/' . $datei['stored_name'] && afile_path(['stored_name' => '../../etc/passwd']) === null);
$GLOBALS['updates'] = [];
afile_aendern($datei, '  ', 5);
$check('ändern: leerer Titel fällt auf den Dateinamen, neue Kategorie', $GLOBALS['updates'][0][1] === ['titel' => 'zwei', 'kategorie_id' => 5] && $GLOBALS['updates'][0][2] === [41]);
$GLOBALS['execs'] = [];
afile_delete($datei);
$check('entfernen: Datei weg, Eintrag weg, KI vergisst', !is_file($tmp . '/ablage/' . $datei['stored_name'])
    && str_contains($GLOBALS['execs'][0][0], 'DELETE FROM ablage_dateien') && count(array_filter($GLOBALS['execs'], static fn($e) => str_contains($e[0], 'DELETE FROM datei_ki') && $e[1] === ['ablage', 41])) === 1);

/* ---------- KI: Kontext, Rechte, Anlagen der Wünsche ---------- */
$GLOBALS['row'] = ['FROM ablage_dateien f' => ['id' => 9, 'kategorie_id' => 4, 'titel' => 'Akte Muster', 'orig_name' => 'akte.pdf', 'mime' => 'application/pdf',
    'stored_name' => 'fehlt.pdf', 'thumb_name' => null, 'uploaded_by' => 17]];
$l = ki_datei_laden('ablage', 9);
$check('KI kennt den Weg durch die Kategorien', $l !== null && str_contains($l['kontext'], 'Kategorie „Verwaltung › Personal › Akten"') && str_contains($l['kontext'], 'Dateiname: akte.pdf, Titel: Akte Muster')
    && $l['pfad'] === null && str_contains($l['eltern']['url'], 'k=k4') && str_ends_with($l['eltern']['url'], '#d9'));
$check('Leitung sieht und ändert', ki_darf_sehen('ablage', $l['datei'], $leitung) && ki_darf_bearbeiten('ablage', $l['datei'], $leitung));
$alsHelfer();
$check('„Nur Leitung" gilt auch beim Abruf der Datei und in der Dateisuche', !ki_darf_sehen('ablage', $l['datei'], $helfer) && !ki_darf_bearbeiten('ablage', $l['datei'], $helfer));
$check('offene Kategorie: sehen ja, ändern nur das Eigene', ki_darf_sehen('ablage', ['kategorie_id' => 2, 'uploaded_by' => 1], $helfer)
    && !ki_darf_bearbeiten('ablage', ['kategorie_id' => 2, 'uploaded_by' => 1], $helfer) && ki_darf_bearbeiten('ablage', ['kategorie_id' => 2, 'uploaded_by' => 17], $helfer));
$GLOBALS['rechte']['view_ablage'] = false;
$check('ohne Recht auf die Ablage gar nichts', !ki_darf_sehen('ablage', ['kategorie_id' => 2, 'uploaded_by' => 17], $helfer));
$alsLeitung();
$GLOBALS['row'] = ['FROM wish_attachments' => ['id' => 4, 'wish_id' => 123, 'orig_name' => 'angebot.pdf', 'stored_name' => 'gibtsnicht.pdf', 'mime' => 'application/pdf', 'kind' => 'angebot', 'uploaded_by' => 17]];
$l = ki_datei_laden('wunsch', 4);
$check('Anlage eines Wunsches', $l !== null && str_contains($l['kontext'], 'Anlage (angebot) zum Beschaffungswunsch „Stromerzeuger"') && str_contains($l['eltern']['url'], 'p=wish&id=123') && $l['pfad'] === null);
$alsHelfer();
$check('Anlage: jeder sieht, ändern nur wer hochlud oder die Leitung', ki_darf_sehen('wunsch', $l['datei'], $helfer) && ki_darf_bearbeiten('wunsch', $l['datei'], $helfer)
    && !ki_darf_bearbeiten('wunsch', ['uploaded_by' => 1], $helfer));
$alsLeitung();
$GLOBALS['execs'] = [];
attachment_delete(['id' => 4, 'stored_name' => 'gibtsnicht.pdf']);
$check('Anlage gelöscht: KI vergisst', count(array_filter($GLOBALS['execs'], static fn($e) => str_contains($e[0], 'DELETE FROM datei_ki') && $e[1] === ['wunsch', 4])) === 1);
$GLOBALS['execs'] = [];
ki_alle_einreihen();
$nachtrag = array_values(array_filter($GLOBALS['execs'], static fn($e) => str_contains($e[0], 'FROM `wish_attachments`') || str_contains($e[0], 'FROM `ablage_dateien`')));
$check('Bestand nachtragen: ohne Vision-Modell keine Bilder, Anlagen nach Inhaltstyp', count($nachtrag) === 2 && str_contains($nachtrag[0][0], "f.mime NOT LIKE 'image/%'") && str_contains($nachtrag[1][0], "f.art <> 'bild'"));

/* ---------- Ansichten ---------- */
$GLOBALS['row'] = [];
$html = ablage_baum_html($ganz, 'veranstaltung.55');
$check('Baum: Weg zum gewählten Knoten ist aufgeklappt, der Rest zu', substr_count($html, '<details open>') === 2 && str_contains($html, 'aria-current="page">Sommerfest')
    && substr_count($html, '<details>') >= 5);
$check('Baum: Schloss an allem aus den Modulen, Ordner an eigenen', substr_count($html, '🔒') === count(array_filter($ganz['knoten'], static fn($n) => $n['auto']))
    && substr_count($html, '📁') === 3 && substr_count($html, '👁') === 2 && str_contains($html, 'wird dort gepflegt'));
$check('leerer Baum', ablage_baum_html(ablage_baum([])) === '');

$sicht = static function (array $baum, string $key, string $q, array $dateien, bool $verwalten, bool $hochladen) use ($kategorien): string {
    return render_partial('ablage', ['title' => 'Dateiablage', 'baum' => $baum, 'knoten' => $key !== '' ? $baum['knoten'][$key] : null, 'key' => $key, 'q' => $q,
        'dateien' => $dateien, 'kiDaten' => [], 'eigene' => $kategorien, 'verwalten' => $verwalten, 'hochladen' => $hochladen,
        'tags' => $key === '' && $q === '' ? [['tag' => 'rechnung', 'n' => 4]] : [], 'maxMb' => 10, 'typen' => ['pdf', 'jpg']]);
};
$wurzel = $sicht($ganz, '', '', ablage_rechte_setzen(ablage_neueste($ganz), $leitung), true, true);
$check('Übersicht: alle Kategorien, Schlagwörter, zuletzt abgelegt, + Kategorie', str_contains($wurzel, 'Alle Kategorien') && str_contains($wurzel, '#rechnung')
    && str_contains($wurzel, 'Zuletzt abgelegt') && str_contains($wurzel, '+ Kategorie') && str_contains($wurzel, 'Fuhrpark') && str_contains($wurzel, 'Verwaltung › Verträge'));
$check('Übersicht: kein Formular zum Ablegen ohne Kategorie', !str_contains($wurzel, 'value="hochladen"'));

$docs = ablage_rechte_setzen(ablage_im_baum(ablage_dateien_von($ganz['knoten']['k2']), $ganz), $leitung);
$eigen = $sicht($ganz, 'k2', '', $docs, true, true);
$check('eigene Kategorie: Pfad, Beschreibung, Bearbeiten, + Unterkategorie', str_contains($eigen, '<strong>Verträge</strong>') && str_contains($eigen, 'Miete, Strom, Wartung')
    && str_contains($eigen, 'p=ablage_kategorie_edit&amp;id=2') && str_contains($eigen, '+ Unterkategorie') && str_contains($eigen, 'eltern=k2'));
$check('eigene Kategorie: ablegen, umbenennen, verschieben, entfernen', str_contains($eigen, 'value="hochladen"') && str_contains($eigen, 'enctype="multipart/form-data"')
    && str_contains($eigen, 'value="datei_aendern"') && str_contains($eigen, 'value="datei_loeschen"') && str_contains($eigen, 'Mietvertrag Halle')
    && str_contains($eigen, '<option value="2" selected>– Verträge</option>') && str_contains($eigen, 'id="d1"'));
$check('eigene Kategorie: Schlagwörter von Hand, zurück in die Ablage', str_contains($eigen, 'name="zurueck"') && str_contains($eigen, 'k=k2#d1') && str_contains($eigen, 'name="tags"'));
$check('kein Schloss an der eigenen Kategorie', !str_contains($eigen, 'aus dem Modul</span>') && !str_contains($eigen, 'Im Modul öffnen'));

$auto = $sicht($ganz, 'fahrzeug.7', '', ablage_rechte_setzen(ablage_im_baum(ablage_dateien_von($ganz['knoten']['fahrzeug.7']), $ganz), $leitung), true, true);
$check('Modulkategorie: Schloss, Verweis ins Modul, Auftrag als Zusatz', str_contains($auto, 'aus dem Modul</span>') && str_contains($auto, 'Im Modul öffnen')
    && str_contains($auto, 'p=vehicle&amp;id=7') && str_contains($auto, 'Auftrag 2026-003') && str_contains($auto, 'THW-80001') && str_contains($auto, 'id="d-fahrzeug-31"'));
$check('Modulkategorie: gesperrt – kein Ablegen, kein Umhängen, kein Bearbeiten, keine Unterkategorie', !str_contains($auto, 'value="hochladen"') && !str_contains($auto, 'value="datei_aendern"')
    && !str_contains($auto, 'p=ablage_kategorie_edit&amp;id=') && !str_contains($auto, '+ Unterkategorie') && !str_contains($auto, 'eltern='));
$modul = $sicht($ganz, 'fahrzeug', '', [], true, true);
$check('Modulwurzel: Fahrzeuge als Unterkategorien mit Zahl', str_contains($modul, 'GKW') && str_contains($modul, 'außer Dienst') && str_contains($modul, 'k=fahrzeug.7') && !str_contains($modul, 'Hier liegt noch nichts'));

$alsHelfer();
$lesend = $sicht($klein, 'k2', '', ablage_rechte_setzen($docs, $helfer), false, false);
$check('nur lesen: nichts zum Ändern', !str_contains($lesend, 'value="hochladen"') && !str_contains($lesend, '+ Unterkategorie') && !str_contains($lesend, 'p=ablage_kategorie_edit')
    && !str_contains($lesend, 'Personal</'));
$check('nur lesen: das eigene Dokument bleibt änderbar, wenn man hochladen darf', str_contains($sicht($klein, 'k2', '', ablage_rechte_setzen($docs, $helfer), false, true), 'value="datei_aendern"'));
$alsLeitung();
$suche = $sicht($ganz, '', 'miete', ablage_rechte_setzen(ablage_suche('miete', $ganz), $leitung), true, true);
$check('Suche: Treffer mit Kategorie, Suche beenden', str_contains($suche, 'Treffer für „miete"') && str_contains($suche, 'Suche beenden') && str_contains($suche, 'Verwaltung › Verträge')
    && str_contains($suche, 'Fuhrpark › GKW') && !str_contains($suche, 'Alle Kategorien</h2>'));
$check('Suche ohne Treffer', str_contains($sicht($ganz, '', 'nix', [], true, true), 'Nichts gefunden.'));
$leerBaum = ablage_baum(ablage_auto_knoten($leitung));
$check('ohne eigene Kategorie: Hinweis für die Leitung', str_contains($sicht($leerBaum, '', '', [], true, true), 'Noch keine eigene Kategorie') && str_contains($sicht($leerBaum, '', '', [], true, true), 'entsteht die erste'));

$form = render_partial('ablage_kategorie_edit', ['title' => 'x', 'kat' => $index[3], 'rows' => $kategorien, 'ausser' => [3, 4], 'geerbt' => false, 'leer' => false, 'errors' => ['Hoppla']]);
$check('Kategorie bearbeiten: Felder, Haken, Eltern ohne sich selbst, Fehler', str_contains($form, 'value="Personal"') && str_contains($form, 'name="nur_leitung" value="1" checked')
    && str_contains($form, '<option value="1" selected>Verwaltung</option>') && !str_contains($form, '<option value="3"') && !str_contains($form, '<option value="4"')
    && str_contains($form, 'Hoppla') && str_contains($form, 'Löschen geht nur leer') && !str_contains($form, 'value="loeschen"'));
$neuForm = render_partial('ablage_kategorie_edit', ['title' => 'x', 'kat' => ['id' => null, 'name' => '', 'beschreibung' => '', 'parent_id' => 4, 'nur_leitung' => 0],
    'rows' => $kategorien, 'ausser' => [], 'geerbt' => true, 'leer' => false, 'errors' => []]);
$check('Kategorie anlegen: Eltern vorgewählt, Hinweis auf geerbte Sperre, kein Löschen', str_contains($neuForm, 'Kategorie anlegen') && str_contains($neuForm, '<option value="4" selected>– – Akten</option>')
    && str_contains($neuForm, 'bereits nur für die Leitung') && !str_contains($neuForm, '<h2>Löschen</h2>'));
$leerForm = render_partial('ablage_kategorie_edit', ['title' => 'x', 'kat' => $index[5], 'rows' => $kategorien, 'ausser' => [5], 'geerbt' => false, 'leer' => true, 'errors' => []]);
$check('leere Kategorie lässt sich löschen', str_contains($leerForm, 'value="loeschen"') && str_contains($leerForm, 'Kategorie „Ausbildung“ löschen?'));

/* ---------- KI-Baustein: Rückweg nur mit eigener Adresse, Schlagwörter auch ohne KI ---------- */
$GLOBALS['settings']['ki_aktiv'] = '0';
settings_reset_cache();
$ohne = ki_hinweis_html('ablage', ['id' => 1], ['ergebnisse' => [], 'auftraege' => []], true);
$mit = ki_hinweis_html('ablage', ['id' => 1], ['ergebnisse' => [], 'auftraege' => []], true, ['immer' => true, 'zurueck' => '/index.php?p=ablage&k=k2#d1', 'suche' => 'ablage']);
$check('KI aus: sonst nichts, in der Ablage Schlagwörter von Hand', $ohne === '' && str_contains($mit, 'Schlagwörter von Hand') && str_contains($mit, 'name="zurueck" value="/index.php?p=ablage&amp;k=k2#d1"')
    && !str_contains($mit, 'value="neu"'));
$ergebnis = ['ergebnisse' => [1 => ['zusammenfassung' => 'Mietvertrag der Halle.', 'tags' => ['mietvertrag'], 'modell' => 'qwen', 'geaendert_am' => '2026-01-02 10:00:00', 'quelle' => 'text']], 'auftraege' => []];
$check('Schlagwörter zeigen in die Ablage, sonst in die Dateisuche', str_contains(ki_hinweis_html('ablage', ['id' => 1], $ergebnis, false, ['suche' => 'ablage']), 'p=ablage&amp;q=mietvertrag')
    && str_contains(ki_hinweis_html('fahrzeug', ['id' => 1], $ergebnis, false), 'p=ki_suche&amp;q=mietvertrag'));
$aktion = (string)file_get_contents($app . '/src/pages/ki_action.php');
$check('Rückweg wird geprüft', str_contains($aktion, "url_ist_intern(\$zurueck)") && url_ist_intern('/index.php?p=ablage&k=k2#d1') && !url_ist_intern('//fremd.example/') && !url_ist_intern('https://fremd.example/'));

/* ---------- Einbindung: Schema, Wanderung, Einstellungen, Seiten ---------- */
$schema = (string)file_get_contents($app . '/sql/schema.sql');
$wanderung = (string)file_get_contents($app . '/src/cli/migrate.php');
foreach (['ablage_kategorien', 'ablage_dateien'] as $tabelle) {
    $check("Tabelle $tabelle in Schema und Wanderung", str_contains($schema, "CREATE TABLE IF NOT EXISTS $tabelle (") && str_contains($wanderung, "CREATE TABLE $tabelle ("));
}
preg_match('/CREATE TABLE IF NOT EXISTS ablage_dateien \((.*?)\) ENGINE/s', $schema, $ms);
preg_match('/CREATE TABLE ablage_dateien \((.*?)\) ENGINE/s', $wanderung, $mw);
$spalten = static fn(string $s): array => array_values(array_filter(array_map(static fn($z) => preg_match('/^\s+([a-z_]+)\s+[A-Z]/', $z, $m) ? $m[1] : '', explode("\n", $s))));
$check('Schema und Wanderung haben dieselben Spalten', $spalten($ms[1] ?? '') === $spalten($mw[1] ?? 'x') && in_array('kategorie_id', $spalten($ms[1] ?? ''), true));
$check('Wanderung merkt sich den Schritt', str_contains($wanderung, "\$merken('102_dateiablage')"));
$seed = (string)file_get_contents($app . '/sql/seed.sql');
foreach (['ablage_modul_name', 'ablage_intro', 'ablage_user_darf_sehen', 'ablage_user_darf_hochladen', 'ki_ablage', 'ki_wuensche'] as $skey) {
    $check("Einstellung $skey", str_contains($seed, "('$skey',"));
}
$routen = (string)file_get_contents($app . '/public/index.php');
foreach (['ablage', 'ablage_kategorie_edit', 'ablage_datei'] as $seite) {
    $check("Seite $seite", str_contains($routen, "'$seite'") && is_file($app . '/src/pages/' . $seite . '.php'));
}
$rechte = (string)file_get_contents($app . '/src/lib/auth.php');
$check('Rechte der Ablage', str_contains($rechte, "'manage_ablage'") && str_contains($rechte, "'view_ablage'") && str_contains($rechte, "'upload_ablage'"));
$check('Ablage in der Speicherübersicht', str_contains((string)file_get_contents($app . '/src/lib/speicher.php'), "'ablage'"));
$check('Ablage im Menü', str_contains((string)file_get_contents($app . '/src/lib/nav.php'), "'route' => 'ablage'"));

// Aufräumen
foreach (glob($tmp . '/ablage/*') ?: [] as $f) { @unlink($f); }
foreach (glob($tmp . '/*') ?: [] as $f) { is_dir($f) ? @rmdir($f) : @unlink($f); }
@rmdir($tmp);

echo "$ok bestanden, $fail fehlgeschlagen\n";
