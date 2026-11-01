<?php
declare(strict_types=1);
/* KI-Vorschlag für die Kontaktkategorie: Bitte an das Modell, strenge Antwortprüfung, Auswertung gegen die Ollama-Attrappe, Einreihen, Entscheiden */
session_start();
$GLOBALS['settings'] = ['ki_aktiv' => '1', 'ki_ollama_url' => 'http://192.168.1.20:11434', 'ki_modell' => 'qwen2.5:7b', 'ki_kontakte' => '1',
    'kontakte_extra_felder' => "funk|Funkrufname|text\nmitglied|Fördermitglied|bool"];
$GLOBALS['execs'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['kontakte'] = [];
$GLOBALS['vorschlag'] = null;
$GLOBALS['antwort'] = '';
$GLOBALS['prompts'] = [];

function kat(int $id, string $label, string $slug, string $beschreibung = '', int $aktiv = 1): array {
    return ['id' => $id, 'list_key' => 'kontakt_kategorie', 'label' => $label, 'slug' => $slug, 'description' => $beschreibung, 'color' => '#123456',
            'weight' => 0, 'is_default' => 0, 'is_final' => 0, 'is_active' => $aktiv, 'sort_order' => $id];
}
$GLOBALS['LISTE'] = [
    kat(1, 'Kommune / Verwaltung', 'kommune', 'Rathaus, Ämter, Bürgermeister'),
    kat(2, 'Politik', 'politik'),
    kat(4, 'Feuerwehr', 'feuerwehr'),
    kat(9, 'Presse / Medien', 'presse'),
    kat(10, 'Firma / Lieferant', 'firma'),
    kat(11, 'Foerderer / Spender', 'foerderer'),
    kat(15, 'Sonstiges', 'sonstiges'),
    kat(16, 'Alt', 'alt', '', 0),
    ['id' => 50, 'list_key' => 'einladung_status', 'label' => 'Politik', 'slug' => 'politik-x', 'description' => '', 'color' => '#000', 'weight' => 0, 'is_default' => 1, 'is_final' => 0, 'is_active' => 1, 'sort_order' => 1],
];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM list_items ORDER BY')) { return $GLOBALS['LISTE']; }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM contacts WHERE id = ?')) { return $GLOBALS['kontakte'][(int)$p[0]] ?? null; }
    if (str_contains($sql, 'FROM contact_kategorie_vorschlaege WHERE contact_id = ?')) { return $GLOBALS['vorschlag']; }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung']; }
final class StubStmt { public function execute(array $p): bool { return true; } }
final class StubPdo { public function prepare(string $s): StubStmt { return new StubStmt(); } }
function db(): StubPdo { return new StubPdo(); }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'import', 'contacts', 'ki', 'kontakt_ki'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

// Ollama-Attrappe: merkt sich die Bitte, antwortet mit dem vorbereiteten Text
$GLOBALS['ki_http_attrappe'] = static function (string $methode, string $pfad, ?array $daten): array {
    $GLOBALS['prompts'][] = $daten;
    return ['response' => $GLOBALS['antwort']];
};

$ok = 0; $fail = 0;
$check = function (string $n, bool $c, mixed $info = null) use (&$ok, &$fail) {
    if ($c) { $ok++; } else { $fail++; echo "FAIL: $n" . ($info !== null ? ' – ' . var_export($info, true) : '') . "\n"; }
};
$kategorien = list_items('kontakt_kategorie');
$check('nur aktive Kontaktkategorien', array_column($kategorien, 'id') === [1, 2, 4, 9, 10, 11, 15], array_column($kategorien, 'id'));

/* ---------- Bitte an das Modell ---------- */
$k = ['id' => 9, 'anrede' => 'Frau', 'titel' => 'Dr.', 'vorname' => 'Anna', 'nachname' => 'Berg', 'organisation' => 'Stadt Musterstadt',
      'position' => 'Bürgermeisterin', 'kategorie_id' => null, 'email' => 'anna.berg@musterstadt.de',
      'telefon' => '0221 1234567', 'mobil' => '0151 999', 'strasse' => 'Rathausplatz 1', 'plz' => '12345', 'ort' => 'Musterstadt',
      'notiz' => "Kommt jedes Jahr zum Jubiläum.\nSpendet regelmäßig.", 'extra' => json_encode(['funk' => 'Heros 1', 'mitglied' => '1']), 'is_active' => 1];
$p = kontakt_ki_prompt($k, $kategorien, contact_extra_fields());
$check('alle erlaubten Kategorien stehen in der Bitte', str_contains($p, '- Kommune / Verwaltung (Rathaus, Ämter, Bürgermeister)') && str_contains($p, '- Politik') && str_contains($p, '- Sonstiges'));
$check('inaktive Kategorie fehlt', !preg_match('/^- Alt$/m', $p));
$check('Kontaktdaten drin', str_contains($p, 'Name: Frau Dr. Anna Berg') && str_contains($p, 'Organisation: Stadt Musterstadt') && str_contains($p, 'Funktion: Bürgermeisterin')
    && str_contains($p, 'Ort: Musterstadt') && str_contains($p, 'E-Mail-Domain: musterstadt.de') && str_contains($p, 'Notiz: Kommt jedes Jahr'));
$check('Zusatzfeld Text drin, Haken nicht', str_contains($p, 'Funkrufname: Heros 1') && !str_contains($p, 'Fördermitglied'));
$check('keine Rufnummer, keine Straße, keine Adresse', !str_contains($p, '1234567') && !str_contains($p, '0151') && !str_contains($p, 'Rathausplatz') && !str_contains($p, 'anna.berg@'));
$check('Regeln: genau eine, nur aus der Liste, nichts erfinden', str_contains($p, 'genau einer Kategorie') && str_contains($p, 'ausschließlich') && str_contains($p, 'Erfinde keine neue'));
$check('JSON-Form verlangt', str_contains($p, '{"kategorie": "…"}'));
$check('leere Felder fehlen', !str_contains(kontakt_ki_prompt(['nachname' => 'Kurz'], $kategorien), 'Organisation:'));

/* ---------- Antwort streng prüfen ---------- */
$parse = static fn(string $roh) => kontakt_ki_antwort_parsen($roh, $kategorien);
$check('saubere Antwort', $parse('{"kategorie": "Politik"}') === 2);
$check('Schreibweise, Umlaute, Leerzeichen egal', $parse('{"kategorie": "kommune/verwaltung"}') === 1 && $parse('{"kategorie": "FÖRDERER / SPENDER"}') === 11);
$check('Schlüssel zählt auch', $parse('{"kategorie": "presse"}') === 9);
$check('Erfundenes fällt weg', $parse('{"kategorie": "Bürgermeisterin"}') === null && $parse('{"kategorie": "VIP"}') === null);
$check('inaktive Kategorie fällt weg', $parse('{"kategorie": "Alt"}') === null);
$check('mehrere genannt: die erste bekannte', $parse('{"kategorie": ["Unfug", "Feuerwehr", "Politik"]}') === 4);
$check('Geplauder um das JSON', $parse("Gerne! Hier mein Vorschlag:\n```json\n{\"category\": \"Firma / Lieferant\"}\n```\nViel Erfolg.") === 10);
$check('nackte Bezeichnung ohne JSON', $parse('Politik') === 2 && $parse("\"Feuerwehr\"\n") === 4);
$check('leer und Unfug', $parse('') === null && $parse('{"kategorie": ""}') === null && $parse('{"x": 1}') === null && $parse('kein json {') === null);
$check('ohne Liste nie etwas', kontakt_ki_antwort_parsen('{"kategorie": "Politik"}', []) === null);

/* ---------- Auswertung gegen die Attrappe ---------- */
$GLOBALS['kontakte'] = [9 => $k];
$GLOBALS['antwort'] = '{"kategorie": "Kommune / Verwaltung"}';
$GLOBALS['execs'] = [];
$e = kontakt_ki_auswerten(9);
$check('Ergebnis: Kategorie aus der Liste, Modell', $e === ['kategorie_id' => 1, 'modell' => 'qwen2.5:7b'], $e);
$check('Bitte ging an das Textmodell im JSON-Modus', ($GLOBALS['prompts'][0]['model'] ?? '') === 'qwen2.5:7b' && ($GLOBALS['prompts'][0]['format'] ?? '') === 'json');
$check('alter Vorschlag weg, neuer gespeichert', $GLOBALS['execs'][0][0] === 'DELETE FROM contact_kategorie_vorschlaege WHERE contact_id = ?' && $GLOBALS['execs'][0][1] === [9]
    && str_starts_with($GLOBALS['execs'][1][0], 'INSERT INTO contact_kategorie_vorschlaege') && $GLOBALS['execs'][1][1] === [9, 1, 'qwen2.5:7b'] && count($GLOBALS['execs']) === 2, $GLOBALS['execs']);

$GLOBALS['kontakte'][9]['kategorie_id'] = 1;
$GLOBALS['execs'] = [];
$e = kontakt_ki_auswerten(9);
$check('schon gesetzte Kategorie genannt: kein Vorschlag, alter weg', $e['kategorie_id'] === null && count($GLOBALS['execs']) === 1);

$GLOBALS['antwort'] = '{"kategorie": "Superheld"}';
$GLOBALS['execs'] = [];
$check('Erfundenes: kein Vorschlag', kontakt_ki_auswerten(9)['kategorie_id'] === null && count($GLOBALS['execs']) === 1);

$GLOBALS['antwort'] = '{"kategorie": "Politik"}';
$GLOBALS['execs'] = [];
$check('andere Kategorie als gesetzt: Vorschlag', kontakt_ki_auswerten(9)['kategorie_id'] === 2 && $GLOBALS['execs'][1][1] === [9, 2, 'qwen2.5:7b']);

try {
    kontakt_ki_auswerten(77);
    $check('unbekannter Kontakt wird übersprungen', false);
} catch (KiUebersprungen $ex) {
    $check('unbekannter Kontakt wird übersprungen', true);
}
$check('Warteschlange läuft über ki_datei_auswerten', ki_datei_auswerten('kontakt', 9)['quelle'] === 'kontakt');

/* ---------- Einreihen ---------- */
$GLOBALS['execs'] = [];
$check('vorschlagen lassen reiht als Typ kontakt ein', kontakt_kategorie_vorschlagen_lassen(9) === true && str_contains($GLOBALS['execs'][0][0], 'INSERT INTO ki_auftraege') && $GLOBALS['execs'][0][1] === ['kontakt', 9]);
$GLOBALS['settings']['ki_kontakte'] = '0'; settings_reset_cache();
$GLOBALS['execs'] = [];
$check('Modulschalter aus: nichts eingereiht', kontakt_kategorie_vorschlagen_lassen(9) === false && kontakt_kategorie_alle_einreihen() === 0 && $GLOBALS['execs'] === []);
$GLOBALS['settings']['ki_kontakte'] = '1'; settings_reset_cache();
$GLOBALS['execs'] = [];
kontakt_kategorie_alle_einreihen();
$check('alle einreihen: nur aktive ohne Kategorie, ohne Vorschlag, ohne Auftrag', count($GLOBALS['execs']) === 1
    && str_contains($GLOBALS['execs'][0][0], "SELECT 'kontakt', c.id") && str_contains($GLOBALS['execs'][0][0], 'c.kategorie_id IS NULL')
    && str_contains($GLOBALS['execs'][0][0], 'NOT EXISTS (SELECT 1 FROM contact_kategorie_vorschlaege') && str_contains($GLOBALS['execs'][0][0], 'c.is_active = 1'));

/* ---------- Entscheiden ---------- */
$GLOBALS['vorschlag'] = ['kategorie_id' => 2];
$GLOBALS['execs'] = []; $GLOBALS['updates'] = [];
$check('übernehmen: Kategorie gesetzt, Vorschlag weg', kontakt_kategorie_vorschlag_entscheiden(9, true, 7) === true
    && $GLOBALS['updates'] === [['contacts', ['kategorie_id' => 2, 'updated_by' => 7], [9]]]
    && $GLOBALS['execs'][0][0] === 'DELETE FROM contact_kategorie_vorschlaege WHERE contact_id = ?' && $GLOBALS['execs'][0][1] === [9]);
$GLOBALS['execs'] = []; $GLOBALS['updates'] = [];
$check('verwerfen: nichts gesetzt, Vorschlag weg', kontakt_kategorie_vorschlag_entscheiden(9, false) === false && $GLOBALS['updates'] === [] && count($GLOBALS['execs']) === 1);
$GLOBALS['vorschlag'] = null;
$GLOBALS['updates'] = [];
$check('übernehmen ohne Vorschlag: nichts gesetzt', kontakt_kategorie_vorschlag_entscheiden(9, true) === false && $GLOBALS['updates'] === []);

/* ---------- KI-Typ, Rechte, Laden ---------- */
$check('Typ kontakt am Schalter ki_kontakte', KI_TYPEN['kontakt']['schalter'] === 'ki_kontakte' && ki_modul_aktiv('kontakt'));
$l = ki_datei_laden('kontakt', 9);
$check('Laden: Name als Titel, kein Pfad, Link zum Kontakt', $l !== null && $l['datei']['titel'] === 'Dr. Anna Berg' && $l['pfad'] === null && str_contains($l['eltern']['url'], 'contact_edit') && str_contains($l['eltern']['url'], '#kategorie'));
$check('Laden: unbekannter Kontakt null', ki_datei_laden('kontakt', 77) === null);
$check('Rechte an den Kontaktrechten', ki_darf_sehen('kontakt', [], ['id' => 1]) && ki_darf_bearbeiten('kontakt', [], ['id' => 1]));

echo "$ok bestanden, $fail fehlgeschlagen\n";
