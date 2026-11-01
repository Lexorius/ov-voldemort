<?php
declare(strict_types=1);
/* Schlagwörter für Kontakte: Bitte an das Modell, strenge Antwortprüfung, Auswertung gegen die Ollama-Attrappe, Einreihen */
session_start();
$GLOBALS['settings'] = ['ki_aktiv' => '1', 'ki_ollama_url' => 'http://192.168.1.20:11434', 'ki_modell' => 'qwen2.5:7b', 'ki_kontakte' => '1',
    'kontakte_extra_felder' => "funk|Funkrufname|text\nmitglied|Fördermitglied|bool"];
$GLOBALS['execs'] = [];
$GLOBALS['kontakte'] = [];
$GLOBALS['tags_am_kontakt'] = [];
$GLOBALS['antwort'] = '';
$GLOBALS['prompts'] = [];

$LISTE = [
    ['id' => 101, 'list_key' => 'kontakt_tag', 'label' => 'Ehrengast',             'slug' => 'ehrengast', 'description' => 'Wird zu Feiern eingeladen', 'color' => '#7c3aed', 'weight' => 0, 'is_default' => 0, 'is_final' => 0, 'is_active' => 1, 'sort_order' => 20],
    ['id' => 102, 'list_key' => 'kontakt_tag', 'label' => 'Presseverteiler',       'slug' => 'presse',    'description' => '', 'color' => '#c2410c', 'weight' => 0, 'is_default' => 0, 'is_final' => 0, 'is_active' => 1, 'sort_order' => 30],
    ['id' => 103, 'list_key' => 'kontakt_tag', 'label' => 'Spender / Förderer',    'slug' => 'foerderer', 'description' => '', 'color' => '#15803d', 'weight' => 0, 'is_default' => 0, 'is_final' => 0, 'is_active' => 1, 'sort_order' => 40],
    ['id' => 104, 'list_key' => 'kontakt_tag', 'label' => 'Politik',               'slug' => 'politik',   'description' => '', 'color' => '#1e40af', 'weight' => 0, 'is_default' => 0, 'is_final' => 0, 'is_active' => 1, 'sort_order' => 60],
    ['id' => 105, 'list_key' => 'kontakt_tag', 'label' => 'Weihnachtsgruß',        'slug' => 'weihnachten', 'description' => '', 'color' => '#0d9488', 'weight' => 0, 'is_default' => 0, 'is_final' => 0, 'is_active' => 1, 'sort_order' => 100],
    ['id' => 106, 'list_key' => 'kontakt_tag', 'label' => 'Alt',                   'slug' => 'alt',       'description' => '', 'color' => '#000', 'weight' => 0, 'is_default' => 0, 'is_final' => 0, 'is_active' => 0, 'sort_order' => 999],
    ['id' => 5,   'list_key' => 'kontakt_kategorie', 'label' => 'Politik', 'slug' => 'politik', 'description' => '', 'color' => '#1e40af', 'weight' => 0, 'is_default' => 0, 'is_final' => 0, 'is_active' => 1, 'sort_order' => 20],
];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM list_items ORDER BY')) { return $GLOBALS['LISTE']; }
    if (str_contains($sql, 'FROM contact_tags t JOIN list_items')) { return $GLOBALS['tags_am_kontakt'][(int)$p[0]] ?? []; }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM contacts WHERE id = ?')) { return $GLOBALS['kontakte'][(int)$p[0]] ?? null; }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung']; }
final class StubStmt { public function execute(array $p): bool { return true; } }
final class StubPdo { public function prepare(string $s): StubStmt { return new StubStmt(); } }
function db(): StubPdo { return new StubPdo(); }
$GLOBALS['LISTE'] = $LISTE;

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'import', 'contacts', 'ki', 'kontakt_tags'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

// Ollama-Attrappe: merkt sich die Bitte, antwortet mit dem vorbereiteten Text
$GLOBALS['ki_http_attrappe'] = static function (string $methode, string $pfad, ?array $daten): array {
    $GLOBALS['prompts'][] = $daten;
    return ['response' => $GLOBALS['antwort']];
};

$ok = 0; $fail = 0;
$check = function (string $n, bool $c, mixed $info = null) use (&$ok, &$fail) {
    if ($c) { $ok++; } else { $fail++; echo "FAIL: $n" . ($info !== null ? ' – ' . var_export($info, true) : '') . "\n"; }
};
$tags = array_values(array_filter($LISTE, static fn($t) => $t['list_key'] === 'kontakt_tag' && $t['is_active']));

/* ---------- Liste ---------- */
$liste = kontakt_tags_liste();
$check('nur aktive Schlagwörter der richtigen Liste', array_keys($liste) === [101, 102, 103, 104, 105], array_keys($liste));
$opt = kontakt_tag_optionen();
$check('Optionen mit Beschreibung', $opt[0] === ['id' => 101, 'text' => 'Ehrengast', 'sub' => 'Wird zu Feiern eingeladen'], $opt[0]);

/* ---------- Bitte an das Modell ---------- */
$k = ['id' => 9, 'anrede' => 'Frau', 'titel' => 'Dr.', 'vorname' => 'Anna', 'nachname' => 'Berg', 'organisation' => 'Stadt Musterstadt',
      'position' => 'Bürgermeisterin', 'kategorie_label' => 'Kommune / Verwaltung', 'email' => 'anna.berg@musterstadt.de',
      'telefon' => '0221 1234567', 'mobil' => '0151 999', 'strasse' => 'Rathausplatz 1', 'plz' => '12345', 'ort' => 'Musterstadt',
      'notiz' => "Kommt jedes Jahr zum Jubiläum.\nSpendet regelmäßig.", 'extra' => json_encode(['funk' => 'Heros 1', 'mitglied' => '1'])];
$p = kontakt_ki_prompt($k, $tags, contact_extra_fields());
$check('alle erlaubten Schlagwörter stehen in der Bitte', str_contains($p, '- Ehrengast (Wird zu Feiern eingeladen)') && str_contains($p, '- Presseverteiler') && str_contains($p, '- Weihnachtsgruß'));
$check('inaktives Schlagwort fehlt', !str_contains($p, 'Alt'));
$check('Kontaktdaten drin', str_contains($p, 'Name: Frau Dr. Anna Berg') && str_contains($p, 'Organisation: Stadt Musterstadt') && str_contains($p, 'Funktion: Bürgermeisterin')
    && str_contains($p, 'Kategorie: Kommune / Verwaltung') && str_contains($p, 'Ort: Musterstadt') && str_contains($p, 'E-Mail-Domain: musterstadt.de') && str_contains($p, 'Notiz: Kommt jedes Jahr'));
$check('Zusatzfeld Text drin, Haken nicht', str_contains($p, 'Funkrufname: Heros 1') && !str_contains($p, 'Fördermitglied'));
$check('keine Rufnummer, keine Straße, keine Adresse', !str_contains($p, '1234567') && !str_contains($p, '0151') && !str_contains($p, 'Rathausplatz') && !str_contains($p, 'anna.berg@'));
$check('Regeln: nur aus der Liste, nichts erfinden, Höchstzahl', str_contains($p, 'ausschließlich') && str_contains($p, 'Erfinde keine neuen') && str_contains($p, 'höchstens ' . KONTAKT_TAG_VORSCHLAEGE_MAX));
$check('JSON-Form verlangt', str_contains($p, '{"schlagwoerter": ["…"]}'));
$check('leere Felder fehlen', !str_contains(kontakt_ki_prompt(['nachname' => 'Kurz'], $tags), 'Organisation:'));

/* ---------- Antwort streng prüfen ---------- */
$parse = static fn(string $roh) => kontakt_ki_antwort_parsen($roh, $tags);
$check('saubere Antwort', $parse('{"schlagwoerter": ["Ehrengast", "Politik"]}') === [101, 104]);
$check('Schreibweise, Umlaute, Leerzeichen egal', $parse('{"schlagwoerter": ["ehrengast", "WEIHNACHTSGRUSS", "spender/förderer"]}') === [101, 105, 103]);
$check('Schlüssel zählt auch', $parse('{"schlagwoerter": ["presse", "foerderer"]}') === [102, 103]);
$check('Erfundenes fällt weg', $parse('{"schlagwoerter": ["Ehrengast", "Bürgermeisterin", "Feuerwehr", "VIP"]}') === [101]);
$check('inaktives Schlagwort fällt weg', $parse('{"schlagwoerter": ["Alt", "Politik"]}') === [104]);
$check('Dubletten nur einmal', $parse('{"schlagwoerter": ["Politik", "politik", "Politik"]}') === [104]);
$check('höchstens vier', count($parse('{"schlagwoerter": ["Ehrengast", "Presseverteiler", "Spender / Förderer", "Politik", "Weihnachtsgruß"]}')) === KONTAKT_TAG_VORSCHLAEGE_MAX);
$check('Geplauder um das JSON', $parse("Gerne! Hier mein Vorschlag:\n```json\n{\"tags\": [\"Politik\"]}\n```\nViel Erfolg.") === [104]);
$check('Schlagwörter als Text mit Komma', $parse('{"schlagwoerter": "Ehrengast, Politik"}') === [101, 104]);
$check('schlichte Aufzählung ohne JSON', $parse("- Politik\n- Ehrengast\n- Irgendwas") === [104, 101]);
$check('leer und Unfug', $parse('') === [] && $parse('{"schlagwoerter": []}') === [] && $parse('{"x": 1}') === [] && $parse('kein json {') === []);
$check('ohne Liste nie etwas', kontakt_ki_antwort_parsen('{"schlagwoerter": ["Politik"]}', []) === []);

/* ---------- Auswertung gegen die Attrappe ---------- */
$GLOBALS['kontakte'] = [9 => $k + ['kategorie_id' => 5, 'is_active' => 1]];
$GLOBALS['tags_am_kontakt'] = [9 => [['id' => 104, 'label' => 'Politik']]];   // schon gesetzt
$GLOBALS['antwort'] = '{"schlagwoerter": ["Politik", "Ehrengast", "Spender / Förderer", "Superheld"]}';
$GLOBALS['execs'] = [];
$e = kontakt_ki_auswerten(9);
$check('Ergebnis: nur Neues aus der Liste, Gesetztes nicht noch einmal', $e['tags'] === [101, 103] && $e['modell'] === 'qwen2.5:7b', $e);
$check('Bitte ging an das Textmodell im JSON-Modus', ($GLOBALS['prompts'][0]['model'] ?? '') === 'qwen2.5:7b' && ($GLOBALS['prompts'][0]['format'] ?? '') === 'json');
$check('Kategorie aus der Liste aufgelöst', str_contains((string)$GLOBALS['prompts'][0]['prompt'], 'Kategorie: Politik'));
$sqls = array_column($GLOBALS['execs'], 0);
$check('alte Vorschläge weg, zwei neue', $sqls[0] === 'DELETE FROM contact_tag_vorschlaege WHERE contact_id = ?' && $GLOBALS['execs'][0][1] === [9]
    && count(array_filter($sqls, static fn($s) => str_starts_with($s, 'INSERT IGNORE INTO contact_tag_vorschlaege'))) === 2
    && $GLOBALS['execs'][1][1] === [9, 101, 'qwen2.5:7b'] && $GLOBALS['execs'][2][1] === [9, 103, 'qwen2.5:7b'], $GLOBALS['execs']);

$GLOBALS['antwort'] = '{"schlagwoerter": ["Feuerwehr"]}';
$GLOBALS['execs'] = [];
$e = kontakt_ki_auswerten(9);
$check('nichts Passendes: keine Vorschläge, alte trotzdem weg', $e['tags'] === [] && count($GLOBALS['execs']) === 1);

try {
    kontakt_ki_auswerten(77);
    $check('unbekannter Kontakt wird übersprungen', false);
} catch (KiUebersprungen $ex) {
    $check('unbekannter Kontakt wird übersprungen', true);
}
$GLOBALS['LISTE'] = array_values(array_filter($LISTE, static fn($t) => $t['list_key'] !== 'kontakt_tag'));
// Listen-Cache ist statisch – neue Prüfung über die Funktion mit frischem Cache geht nur im eigenen Prozess; hier reicht die Funktion selbst
$check('Auswertung über die Warteschlange läuft über ki_datei_auswerten', ki_datei_auswerten('kontakt', 9)['quelle'] === 'kontakt');
$GLOBALS['LISTE'] = $LISTE;

/* ---------- Einreihen und Speichern ---------- */
$GLOBALS['execs'] = [];
$check('vorschlagen lassen reiht als Typ kontakt ein', kontakt_tags_vorschlagen_lassen(9) === true && str_contains($GLOBALS['execs'][0][0], 'INSERT INTO ki_auftraege') && $GLOBALS['execs'][0][1] === ['kontakt', 9]);
$GLOBALS['settings']['ki_kontakte'] = '0'; settings_reset_cache();
$GLOBALS['execs'] = [];
$check('Modulschalter aus: nichts eingereiht', kontakt_tags_vorschlagen_lassen(9) === false && kontakt_tags_alle_einreihen() === 0 && $GLOBALS['execs'] === []);
$GLOBALS['settings']['ki_kontakte'] = '1'; settings_reset_cache();
$GLOBALS['execs'] = [];
kontakt_tags_alle_einreihen();
$check('alle einreihen: nur aktive ohne Schlagwörter, ohne Vorschläge, ohne Auftrag', count($GLOBALS['execs']) === 1
    && str_contains($GLOBALS['execs'][0][0], "SELECT 'kontakt', c.id") && str_contains($GLOBALS['execs'][0][0], 'NOT EXISTS (SELECT 1 FROM contact_tags')
    && str_contains($GLOBALS['execs'][0][0], 'NOT EXISTS (SELECT 1 FROM contact_tag_vorschlaege') && str_contains($GLOBALS['execs'][0][0], 'c.is_active = 1'));

$GLOBALS['execs'] = [];
kontakt_tags_setzen(9, [102, '104', 0, 102]);
$check('setzen: löschen, dann je Schlagwort einmal', array_column($GLOBALS['execs'], 0)[0] === 'DELETE FROM contact_tags WHERE contact_id = ?'
    && count($GLOBALS['execs']) === 3 && $GLOBALS['execs'][1][1] === [9, 102, 'hand'] && $GLOBALS['execs'][2][1] === [9, 104, 'hand']);

$GLOBALS['execs'] = [];
$n = kontakt_tag_vorschlaege_entscheiden(9, [101]);
$check('entscheiden: übernommen als ki, alle Vorschläge weg', $n === 1 && $GLOBALS['execs'][0][1] === [9, 101, 'ki'] && end($GLOBALS['execs'])[0] === 'DELETE FROM contact_tag_vorschlaege WHERE contact_id = ?');
$GLOBALS['execs'] = [];
$check('entscheiden ohne Haken: nur verwerfen', kontakt_tag_vorschlaege_entscheiden(9, []) === 0 && count($GLOBALS['execs']) === 1);

/* ---------- KI-Typ, Rechte, Laden ---------- */
$check('Typ kontakt am Schalter ki_kontakte', KI_TYPEN['kontakt']['schalter'] === 'ki_kontakte' && ki_modul_aktiv('kontakt'));
$l = ki_datei_laden('kontakt', 9);
$check('Laden: Name als Titel, kein Pfad, Link zum Kontakt', $l !== null && $l['datei']['titel'] === 'Dr. Anna Berg' && $l['pfad'] === null && str_contains($l['eltern']['url'], 'contact_edit') && str_contains($l['eltern']['url'], '#schlagwoerter'));
$check('Laden: unbekannter Kontakt null', ki_datei_laden('kontakt', 77) === null);
$check('Rechte an den Kontaktrechten', ki_darf_sehen('kontakt', [], ['id' => 1]) && ki_darf_bearbeiten('kontakt', [], ['id' => 1]));

echo "$ok bestanden, $fail fehlgeschlagen\n";
