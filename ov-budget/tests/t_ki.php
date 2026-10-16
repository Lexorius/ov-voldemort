<?php
declare(strict_types=1);
/*
 * KI-Auswertung: Schalter (aus = nichts passiert), Adressprüfung (nur eigenes
 * Netz), Warteschlange, Schlagwörter und Antwort des Modells, Textgewinnung,
 * Auswertung mit Ollama-Attrappe, Anzeige und Suche.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR'];
$GLOBALS['execs'] = [];
$GLOBALS['locks'] = [];
$GLOBALS['ki_rows'] = [];
$GLOBALS['auftrag_rows'] = [];
$GLOBALS['rechte'] = [];
$GLOBALS['dateien'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM datei_ki')) { return $GLOBALS['ki_rows']; }
    if (str_contains($sql, 'FROM ki_auftraege')) { return $GLOBALS['auftrag_rows']; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function db_lock(string $name, int $warten = 0): bool { $GLOBALS['locks'][] = $name; return true; }
function db_unlock(string $name): void {}
function can(string $was, mixed $ctx = null): bool { return $GLOBALS['rechte'][$was] ?? true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung']; }
// Dateiablagen als Attrappen – die KI kennt nur ihre Schnittstelle
function vfile_find(int $id): ?array { return $GLOBALS['dateien'][$id] ?? null; }
function vfile_path(array $d, bool $v = false): ?string { return $d['pfad'] ?? null; }
function vehicle_find(int $id): ?array { return ['id' => $id, 'bezeichnung' => 'MTW', 'kennzeichen' => 'THW-12345']; }
function efile_find(?int $id): ?array { return null; }
function efile_path(array $d, bool $v = false): ?string { return null; }
function event_find(?int $id): ?array { return null; }
function kfile_find(?int $id): ?array { return $id === 7 ? ['id' => 7, 'entry_id' => 3, 'orig_name' => 'einladung.pdf', 'titel' => '', 'mime' => 'application/pdf'] : null; }
function kfile_path(array $d, bool $v = false): ?string { return null; }
function kalender_find(int $id): ?array { return $id === 3 ? ['id' => 3, 'titel' => 'Jahresabschluss', 'ziel' => 'ov'] : null; }
function kalender_sichtbar(array $t, array $u): bool { return $GLOBALS['rechte']['termin_sehen'] ?? true; }
function kalender_darf_bearbeiten(array $t, array $u): bool { return $GLOBALS['rechte']['termin_bearbeiten'] ?? true; }
function standort_bild_find(int $id): ?array { return null; }
function standort_bild_pfad(array $b, bool $v = false): ?string { return null; }
function standort_find(?int $id): ?array { return null; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'ki'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$u = ['id' => 1, 'role' => 'leitung'];
$einstellen = static function (array $w): void { $GLOBALS['settings'] = array_merge($GLOBALS['settings'], $w); settings_reset_cache(); };

/* ---------- Adresse: nur das eigene Netz ---------- */
$check('private IPv4 und IPv6 sind privat', ki_host_privat('192.168.1.20') && ki_host_privat('10.0.0.5') && ki_host_privat('172.20.1.1') && ki_host_privat('127.0.0.1') && ki_host_privat('::1') && ki_host_privat('fd12::1'));
$check('öffentliche Adressen nicht', !ki_host_privat('8.8.8.8') && !ki_host_privat('172.32.0.1') && !ki_host_privat('2606:4700::1111') && !ki_host_privat(''));
$check('Rechnername ohne Punkt, .local und .lan ja – fremde Domain nein', ki_host_privat('ollama') && ki_host_privat('Ollama.local') && ki_host_privat('nas.lan') && ki_host_privat('localhost') && !ki_host_privat('ollama.example.com') && !ki_host_privat('api.openai.com'));
$check('Adresse passt', ki_url_pruefen('http://192.168.1.20:11434') === null && ki_url_pruefen('http://ollama:11434/') === null && ki_url_pruefen('https://ki.home/ollama') === null);
$check('Adresse taugt nicht: leer, falsches Schema, Benutzer, öffentlich', ki_url_pruefen('') !== null && ki_url_pruefen('ftp://ollama') !== null && ki_url_pruefen('192.168.1.20:11434') !== null
    && ki_url_pruefen('http://user:pw@192.168.1.20') !== null && ki_url_pruefen('http://192.168.1.20?x=1') !== null && str_contains((string)ki_url_pruefen('https://api.openai.com/v1'), 'eigenen Netz'));

/* ---------- Schalter ---------- */
$check('Standard: aus', !ki_aktiv() && !ki_modul_aktiv('fahrzeug') && ki_url() === '');
$einstellen(['ki_aktiv' => '1', 'ki_ollama_url' => 'https://api.openai.com']);
$check('an, aber öffentliche Adresse = aus', !ki_aktiv() && ki_url() === '');
$einstellen(['ki_ollama_url' => 'http://192.168.1.20:11434/']);
$check('an mit Adresse im eigenen Netz', ki_aktiv() && ki_url() === 'http://192.168.1.20:11434' && ki_modul_aktiv('fahrzeug') && ki_modul_aktiv('termin') && !ki_modul_aktiv('quatsch'));
$einstellen(['ki_fahrzeuge' => '0']);
$check('Modulschalter', !ki_modul_aktiv('fahrzeug') && ki_modul_aktiv('veranstaltung'));
$check('Zeitlimit eingegrenzt', ki_timeout() === 120 && ($einstellen(['ki_timeout_sek' => '5']) ?? ki_timeout() === 10) && ($einstellen(['ki_timeout_sek' => '9999']) ?? ki_timeout() === 600));
$einstellen(['ki_timeout_sek' => '120']);

/* ---------- Warteschlange: aus = nichts ---------- */
$einstellen(['ki_aktiv' => '0']);
$GLOBALS['execs'] = [];
$r = ki_warteschlange_abarbeiten();
$check('aus: nichts eingereiht, nichts abgearbeitet, keine Sperre, keine Datenbank', !ki_einreihen('veranstaltung', 5) && $r['bearbeitet'] === 0 && $GLOBALS['locks'] === [] && $GLOBALS['execs'] === [] && ki_alle_einreihen() === 0 && $GLOBALS['execs'] === []);
$einstellen(['ki_aktiv' => '1']);
$check('abgeschaltete Ablage: nichts eingereiht', !ki_einreihen('fahrzeug', 5) && $GLOBALS['execs'] === []);
$check('eingeschaltete Ablage: Auftrag', ki_einreihen('veranstaltung', 5) && str_contains($GLOBALS['execs'][0][0], 'INSERT INTO ki_auftraege') && $GLOBALS['execs'][0][1] === ['veranstaltung', 5] && !ki_einreihen('veranstaltung', 0));
$GLOBALS['execs'] = [];
ki_vergessen('veranstaltung', 5);
$check('vergessen löscht Auftrag und Ergebnis', count($GLOBALS['execs']) === 2 && str_contains($GLOBALS['execs'][0][0], 'DELETE FROM ki_auftraege') && str_contains($GLOBALS['execs'][1][0], 'DELETE FROM datei_ki'));
$GLOBALS['execs'] = [];
$r = ki_warteschlange_abarbeiten();
$check('an, aber leer: Sperre geholt, Hängengebliebene freigegeben, nichts bearbeitet', $r['bearbeitet'] === 0 && $GLOBALS['locks'] === ['ki_warteschlange'] && count($GLOBALS['execs']) === 1 && str_contains($GLOBALS['execs'][0][0], "status = 'laeuft' AND gestartet_am"));
$GLOBALS['execs'] = [];
$check('alle einreihen: nur eingeschaltete Ablagen, ohne Vision-Modell keine Bilder und keine Plätze', ki_alle_einreihen() === 2 && count($GLOBALS['execs']) === 2 && !str_contains(implode(' ', array_column($GLOBALS['execs'], 0)), 'vehicle_files')
    && !str_contains(implode(' ', array_column($GLOBALS['execs'], 0)), 'standort_bilder') && str_contains($GLOBALS['execs'][0][0], "AND f.art <> 'bild'"));
$GLOBALS['execs'] = [];
$check('Bild ohne Vision-Modell wird nicht eingereiht, Dokument schon', !ki_einreihen('veranstaltung', 5, 'bild') && $GLOBALS['execs'] === [] && ki_einreihen('veranstaltung', 5, 'dokument'));
$einstellen(['ki_vision_modell' => 'llava:7b', 'ki_fahrzeuge' => '1']);
$GLOBALS['execs'] = [];
$check('mit Vision-Modell: Bilder und Plätze dabei', ki_einreihen('standort', 3, 'bild') && ki_alle_einreihen() === 4 && !str_contains(implode(' ', array_column($GLOBALS['execs'], 0)), "art <> 'bild'"));
$einstellen(['ki_vision_modell' => '']);

/* ---------- Schlagwörter und Antwort ---------- */
$check('Schlagwörter: getrimmt, ohne Dubletten, ohne #', ki_tags_parsen("  Rechnung, HU , rechnung; \"Werkstatt\" #tüv\n") === ['Rechnung', 'HU', 'Werkstatt', 'tüv']);
$check('Schlagwörter: höchstens 10, höchstens 40 Zeichen', count(ki_tags_parsen(implode(',', range(1, 20)))) === 10 && mb_strlen(ki_tags_parsen([str_repeat('ä', 60)][0])[0]) === 40 && ki_tags_parsen('') === [] && ki_tags_text(['a', 'b']) === 'a, b');
$a = ki_antwort_parsen("```json\n{\"zusammenfassung\": \"Rechnung der Werkstatt Müller über 412,50 € für die HU des MTW am 3.3.2026.\", \"schlagwoerter\": [\"Rechnung\", \"HU\", \"Werkstatt Müller\", \"MTW\"]}\n```");
$check('Antwort aus ```-Zaun', $a !== null && str_starts_with($a['zusammenfassung'], 'Rechnung der Werkstatt') && $a['tags'] === ['Rechnung', 'HU', 'Werkstatt Müller', 'MTW']);
$a = ki_antwort_parsen('Gerne! Hier ist das Ergebnis: {"summary": "Einladung zum Jahresabschluss.", "tags": "einladung, jahresabschluss"} Viel Erfolg.');
$check('Antwort mit Geplauder, englischen Schlüsseln und Text-Tags', $a !== null && $a['zusammenfassung'] === 'Einladung zum Jahresabschluss.' && $a['tags'] === ['einladung', 'jahresabschluss']);
$check('unverwertbar: kein JSON, leer, kaputt', ki_antwort_parsen('Ich weiß es nicht.') === null && ki_antwort_parsen('{}') === null && ki_antwort_parsen('{"zusammenfassung": ') === null && ki_antwort_parsen('{"zusammenfassung": "", "schlagwoerter": []}') === null);
$check('Zusammenfassung wird gekürzt', mb_strlen(ki_antwort_parsen('{"zusammenfassung": "' . str_repeat('a', 3000) . '"}')['zusammenfassung']) <= 2002);
$check('Daten, Nummern und Beträge sind keine Schlagwörter', ki_antwort_parsen('{"zusammenfassung": "x", "schlagwoerter": ["Volvo", "90440", "15.10.2026", "412,50 €", "Fensterheber", "14:30 Uhr", "OHBR/I-475"]}')['tags'] === ['Volvo', 'Fensterheber', 'OHBR/I-475']);
$check('Prompt verlangt Schlagwörter ohne Daten und Nummern', str_contains(ki_prompt('x', 'y'), 'keine Daten, Beträge, Kennzeichen oder Nummern'));
$check('Bild-Prompt: nur, was zu sehen ist, nichts über Fehlendes', str_contains(ki_prompt('x', ''), 'Keine Sätze darüber, was fehlt') && !str_contains(ki_prompt('x', ''), 'Beträge, Namen, Nummern'));
$check('Satzfetzen sind keine Schlagwörter', ki_antwort_parsen('{"zusammenfassung": "x", "schlagwoerter": ["THW-Lkw", "Fahrzeug für den THW-Ortsverbands", "Garagenraum", "Kran mit Haken"]}')['tags'] === ['THW-Lkw', 'Garagenraum', 'Kran mit Haken']);
$check('Text glätten: Umbrüche, Leerraum, Kürzung', ki_text_kuerzen("a  b\r\n\r\n\r\n\r\nc \n d", 100) === "a b\n\nc\nd" && ki_text_kuerzen('abcdefghij', 5) === 'abcde …' && ki_text_kuerzen("x\xffy", 10) !== '');
$p = ki_prompt('Dokument aus der Fahrzeugakte von MTW', 'Hallo Welt');
$check('Prompt mit Kontext, Text und JSON-Form', str_contains($p, 'Kontext: Dokument aus der Fahrzeugakte von MTW') && str_contains($p, "Inhalt:\n\"\"\"\nHallo Welt\n\"\"\"") && str_contains($p, '"schlagwoerter"') && str_contains(ki_prompt('x', ''), 'was auf dem Bild zu sehen ist'));
$check('Modell in der Liste', ki_modell_vorhanden('qwen2.5:7b', ['llava:7b', 'qwen2.5:7b']) && ki_modell_vorhanden('llama3', ['llama3:latest']) && ki_modell_vorhanden('Llama3:latest', ['llama3']) && !ki_modell_vorhanden('mistral', ['llama3']) && !ki_modell_vorhanden('', ['x']));
$check('häufigste Schlagwörter', ki_tags_zaehlen(['Rechnung, HU', 'rechnung, Einladung', 'HU'], 2) === [['tag' => 'Rechnung', 'n' => 2], ['tag' => 'HU', 'n' => 2]] && ki_tags_zaehlen([]) === []);

/* ---------- Text aus Dateien ---------- */
$check('Office-XML zu Text', ki_xml_text('<w:p><w:r><w:t>Hallo</w:t></w:r></w:p><w:p><w:r><w:t>Welt &amp; Co</w:t><w:tab/><w:t>x</w:t></w:r></w:p>') === "Hallo\nWelt & Co\tx\n");
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ovb_ki_test_' . bin2hex(random_bytes(4));
@mkdir($tmp);
file_put_contents($tmp . '/notiz.txt', "Prüfbericht UVV\nAlles in Ordnung.");
$q = ki_text_aus_datei($tmp . '/notiz.txt', 'text/plain', 'notiz.txt');
$check('Textdatei', $q['text'] === "Prüfbericht UVV\nAlles in Ordnung." && $q['bild'] === null);
$q = ki_text_aus_datei($tmp . '/notiz.txt', 'image/jpeg', 'foto.jpg');
$check('Bild geht als Bild', $q['text'] === null && $q['bild'] === $tmp . '/notiz.txt');
try { ki_text_aus_datei($tmp . '/notiz.txt', 'application/octet-stream', 'daten.bin'); $check('unbekannte Dateiart wirft', false); }
catch (KiException $e) { $check('unbekannte Dateiart wirft', $e instanceof KiUebersprungen && str_contains($e->getMessage(), 'bin')); }
if (class_exists('ZipArchive')) {
    $z = new ZipArchive();
    $z->open($tmp . '/bericht.docx', ZipArchive::CREATE);
    $z->addFromString('[Content_Types].xml', '<Types/>');
    $z->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>Prüfbericht</w:t></w:r></w:p><w:p><w:r><w:t>Mängel: keine</w:t></w:r></w:p></w:body></w:document>');
    $z->close();
    $q = ki_text_aus_datei($tmp . '/bericht.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'bericht.docx');
    $check('DOCX zu Text', str_contains((string)$q['text'], "Prüfbericht\nMängel: keine"));
    file_put_contents($tmp . '/kaputt.docx', 'kein zip');
    try { ki_text_aus_datei($tmp . '/kaputt.docx', '', 'kaputt.docx'); $check('kaputte Office-Datei wirft', false); }
    catch (KiException $e) { $check('kaputte Office-Datei wirft', true); }
}
$check('Programmsuche findet nichts Erfundenes', ki_programm_finden('gibtsnicht_ovb_xyz') === null);

/* ---------- Ollama-Attrappe: Verbindung, Auswertung ---------- */
$GLOBALS['anfragen'] = [];
$GLOBALS['ki_http_attrappe'] = static function (string $m, string $pfad, ?array $d): array {
    $GLOBALS['anfragen'][] = [$m, $pfad, $d];
    if ($pfad === '/api/tags') { return ['models' => [['name' => 'qwen2.5:7b'], ['name' => 'llava:7b']]]; }
    return ['response' => '{"zusammenfassung": "Prüfbericht zur UVV ohne Mängel.", "schlagwoerter": ["Prüfbericht", "UVV"]}'];
};
$t = ki_verbindung_testen();
$check('Verbindung testen: Modelle', $t['ok'] && $t['modelle'] === ['qwen2.5:7b', 'llava:7b'] && $GLOBALS['anfragen'][0] === ['GET', '/api/tags', null]);
$einstellen(['ki_modell' => 'qwen2.5:7b', 'ki_vision_modell' => '']);
$GLOBALS['dateien'][5] = ['id' => 5, 'vehicle_id' => 2, 'order_id' => null, 'art' => 'dokument', 'orig_name' => 'notiz.txt', 'titel' => 'UVV 2026', 'mime' => 'text/plain', 'pfad' => $tmp . '/notiz.txt', 'auftrag_nummer' => null];
$GLOBALS['execs'] = [];
$e = ki_datei_auswerten('fahrzeug', 5);
$anfrage = end($GLOBALS['anfragen']);
$check('Auswertung: Prompt mit Fahrzeug, Kennzeichen, Titel und Inhalt, JSON-Format, kein Bild, Antwort begrenzt', $anfrage[1] === '/api/generate' && $anfrage[2]['model'] === 'qwen2.5:7b' && $anfrage[2]['format'] === 'json' && $anfrage[2]['stream'] === false && !isset($anfrage[2]['images'])
    && $anfrage[2]['options']['num_predict'] === 600 && $anfrage[2]['options']['repeat_penalty'] === 1.15
    && str_contains($anfrage[2]['prompt'], 'MTW (THW-12345)') && str_contains($anfrage[2]['prompt'], 'Titel: UVV 2026') && str_contains($anfrage[2]['prompt'], 'Alles in Ordnung.'));
$check('Auswertung: Ergebnis gespeichert', $e['zusammenfassung'] === 'Prüfbericht zur UVV ohne Mängel.' && $e['tags'] === ['Prüfbericht', 'UVV'] && $e['quelle'] === 'text'
    && str_contains($GLOBALS['execs'][0][0], 'INSERT INTO datei_ki') && $GLOBALS['execs'][0][1] === ['fahrzeug', 5, 'Prüfbericht zur UVV ohne Mängel.', 'Prüfbericht, UVV', 'qwen2.5:7b', 'text']);
$GLOBALS['dateien'][6] = ['id' => 6, 'vehicle_id' => 2, 'order_id' => 9, 'art' => 'bild', 'orig_name' => 'schaden.jpg', 'titel' => 'schaden', 'mime' => 'image/jpeg', 'pfad' => $tmp . '/notiz.txt', 'auftrag_nummer' => 'A-9'];
try { ki_datei_auswerten('fahrzeug', 6); $check('Bild ohne Vision-Modell wirft', false); }
catch (KiException $ex) { $check('Bild ohne Vision-Modell wirft – als dauerhafter Grund', $ex instanceof KiUebersprungen && str_contains($ex->getMessage(), 'Vision-Modell')); }
$einstellen(['ki_vision_modell' => 'llava:7b']);
$e = ki_datei_auswerten('fahrzeug', 6);
$anfrage = end($GLOBALS['anfragen']);
$check('Bild geht Base64 ans Vision-Modell, Kontext nennt den Auftrag', $anfrage[2]['model'] === 'llava:7b' && $anfrage[2]['images'] === [base64_encode((string)file_get_contents($tmp . '/notiz.txt'))] && str_contains($anfrage[2]['prompt'], 'Instandsetzungsauftrag A-9') && $e['quelle'] === 'bild');
$GLOBALS['dateien'][8] = ['id' => 8, 'vehicle_id' => 2, 'order_id' => null, 'art' => 'dokument', 'orig_name' => 'weg.pdf', 'titel' => '', 'mime' => 'application/pdf', 'pfad' => null];
try { ki_datei_auswerten('fahrzeug', 8); $check('fehlende Datei wirft', false); } catch (KiException $ex) { $check('fehlende Datei wirft', str_contains($ex->getMessage(), 'fehlt')); }
try { ki_datei_auswerten('fahrzeug', 99); $check('unbekannte Datei wirft', false); } catch (KiException $ex) { $check('unbekannte Datei wirft', true); }
$GLOBALS['ki_http_attrappe'] = static function (): array { throw new KiException('Ollama ist nicht erreichbar: Verbindung abgelehnt'); };
$t = ki_verbindung_testen();
$check('Verbindung testen: Fehler wird gemeldet', !$t['ok'] && str_contains($t['fehler'], 'nicht erreichbar') && $t['modelle'] === []);
$GLOBALS['ki_http_attrappe'] = static fn(): array => ['response' => 'Ich kann das nicht lesen.'];
try { ki_datei_auswerten('fahrzeug', 5); $check('unverwertbare Antwort wirft', false); } catch (KiException $ex) { $check('unverwertbare Antwort wirft', str_contains($ex->getMessage(), 'nicht verwertbar')); }

/* ---------- Warteschlange abarbeiten ---------- */
$GLOBALS['ki_http_attrappe'] = static fn(): array => ['response' => '{"zusammenfassung": "Notiz.", "schlagwoerter": ["notiz"]}'];
$GLOBALS['auftrag_rows'] = [['id' => 1, 'typ' => 'fahrzeug', 'datei_id' => 5, 'status' => 'offen'], ['id' => 2, 'typ' => 'fahrzeug', 'datei_id' => 8, 'status' => 'offen'], ['id' => 3, 'typ' => 'termin', 'datei_id' => 7, 'status' => 'offen']];
$einstellen(['ki_termine' => '0']);
$GLOBALS['execs'] = [];
$r = ki_warteschlange_abarbeiten();
$sql = implode("\n", array_column($GLOBALS['execs'], 0));
$check('abarbeiten: eine fertig, eine übersprungen (Datei fehlt – ohne Wiederholung), abgeschaltete Ablage bleibt liegen', $r['bearbeitet'] === 2 && $r['fertig'] === 1 && $r['uebersprungen'] === 1 && $r['fehler'] === 0 && $r['meldungen'] === []
    && str_contains($sql, "status = 'laeuft', versuche = versuche + 1") && str_contains($sql, "status = 'fertig'") && str_contains($sql, "status = 'fehler', versuche = ?") && substr_count($sql, 'laeuft') === 3);
$GLOBALS['ki_http_attrappe'] = static function (): array { throw new KiException('Ollama ist nicht erreichbar: Zeitüberschreitung'); };
$GLOBALS['auftrag_rows'] = [['id' => 1, 'typ' => 'fahrzeug', 'datei_id' => 5, 'status' => 'offen']];
$r = ki_warteschlange_abarbeiten();
$check('abarbeiten: Ollama weg = richtiger Fehler mit Meldung, wird wiederholt', $r['fehler'] === 1 && $r['uebersprungen'] === 0 && str_contains($r['meldungen'][0], 'Zeitüberschreitung'));
$GLOBALS['execs'] = [];
$check('alles neu: zurück auf offen, Laufendes bleibt', ki_alles_neu() === 1 && str_contains($GLOBALS['execs'][0][0], "SET status = 'offen', versuche = 0") && str_contains($GLOBALS['execs'][0][0], "status <> 'laeuft'"));
$GLOBALS['auftrag_rows'] = [];
$einstellen(['ki_termine' => '1']);

/* ---------- Ergebnisse, Anzeige ---------- */
$GLOBALS['ki_rows'] = [['id' => 1, 'typ' => 'fahrzeug', 'datei_id' => 5, 'zusammenfassung' => 'Prüfbericht zur UVV ohne Mängel.', 'tags' => 'Prüfbericht, UVV', 'modell' => 'qwen2.5:7b', 'quelle' => 'text', 'erstellt_am' => '2026-10-15 08:00:00', 'geaendert_am' => '2026-10-15 08:00:00']];
$GLOBALS['auftrag_rows'] = [['datei_id' => 6, 'status' => 'offen', 'fehler' => '', 'versuche' => 0], ['datei_id' => 8, 'status' => 'fehler', 'fehler' => 'Die Datei fehlt in der Ablage.', 'versuche' => 3]];
$k = ki_fuer_dateien('fahrzeug', [5, 6, 8, 8]);
$check('Ergebnisse und Aufträge je Datei', $k['ergebnisse'][5]['tags'] === ['Prüfbericht', 'UVV'] && $k['auftraege'][6]['status'] === 'offen' && !isset($k['ergebnisse'][6]) && ki_fuer_dateien('fahrzeug', []) === ['ergebnisse' => [], 'auftraege' => []]);
$h = ki_hinweis_html('fahrzeug', $GLOBALS['dateien'][5], $k, true);
$check('Anzeige: Zusammenfassung, Schlagwort-Chips, Bearbeiten-Formular', str_contains($h, '✨') && str_contains($h, 'Prüfbericht zur UVV ohne Mängel.') && str_contains($h, '#UVV</a>') && str_contains($h, 'q=Pr%C3%BCfbericht') && str_contains($h, 'qwen2.5:7b')
    && str_contains($h, 'KI-Auswertung bearbeiten') && str_contains($h, 'value="neu"') && str_contains($h, 'value="loeschen"') && str_contains($h, 'name="datei_id" value="5"'));
$h = ki_hinweis_html('fahrzeug', $GLOBALS['dateien'][5], $k, false);
$check('Anzeige ohne Recht: kein Formular', str_contains($h, '#UVV</a>') && !str_contains($h, '<form'));
$h = ki_hinweis_html('fahrzeug', $GLOBALS['dateien'][6], $k, false);
$check('Anzeige: ausstehend', str_contains($h, '⏳') && !str_contains($h, '<form'));
$h = ki_hinweis_html('fahrzeug', $GLOBALS['dateien'][8], $k, true);
$check('Anzeige: fehlgeschlagen, neu auswerten möglich', str_contains($h, 'fehlgeschlagen: Die Datei fehlt') && str_contains($h, 'value="neu"') && !str_contains($h, 'value="loeschen"'));
$h = ki_hinweis_html('fahrzeug', ['id' => 77], $k, true);
$check('Anzeige: nichts da, aber darf → Auswerten anbieten', str_contains($h, 'value="neu"') && !str_contains($h, '✨'));
$check('Anzeige: nichts da, darf nicht → leer', trim(ki_hinweis_html('fahrzeug', ['id' => 77], $k, false)) === '');
$einstellen(['ki_aktiv' => '0']);
$h = ki_hinweis_html('fahrzeug', $GLOBALS['dateien'][5], ki_fuer_dateien('fahrzeug', [5, 6]), true);
$check('aus: Ergebnis bleibt sichtbar und änderbar, kein Neu-auswerten, keine Aufträge geholt', str_contains($h, '#UVV</a>') && str_contains($h, 'value="speichern"') && !str_contains($h, 'value="neu"') && ki_fuer_dateien('fahrzeug', [6])['auftraege'] === []);
$check('aus: nichts da → leer', trim(ki_hinweis_html('fahrzeug', ['id' => 77], ki_fuer_dateien('fahrzeug', [77]), true)) === '');
$einstellen(['ki_aktiv' => '1']);

/* ---------- Rechte und Suche ---------- */
$check('Rechte je Ablage', ki_darf_sehen('fahrzeug', [], $u) && ki_darf_bearbeiten('fahrzeug', [], $u) && ki_darf_sehen('termin', ['entry_id' => 3], $u) && !ki_darf_sehen('termin', ['entry_id' => 4], $u) && !ki_darf_sehen('quatsch', [], $u));
$GLOBALS['rechte'] = ['manage_vehicles' => false, 'termin_bearbeiten' => false];
$check('Rechte verneint', !ki_darf_bearbeiten('fahrzeug', [], $u) && !ki_darf_bearbeiten('termin', ['entry_id' => 3], $u) && ki_darf_sehen('fahrzeug', [], $u));
$GLOBALS['rechte'] = [];
$GLOBALS['ki_rows'][] = ['id' => 2, 'typ' => 'termin', 'datei_id' => 7, 'zusammenfassung' => 'Einladung zum Jahresabschluss.', 'tags' => 'einladung', 'modell' => 'qwen2.5:7b', 'quelle' => 'text', 'erstellt_am' => '2026-10-15 09:00:00', 'geaendert_am' => '2026-10-15 09:00:00'];
$GLOBALS['ki_rows'][] = ['id' => 3, 'typ' => 'veranstaltung', 'datei_id' => 1, 'zusammenfassung' => 'gelöscht', 'tags' => 'einladung', 'modell' => '', 'quelle' => 'text', 'erstellt_am' => '2026-10-15 09:00:00', 'geaendert_am' => '2026-10-15 09:00:00'];
$s = ki_suche('einladung', $u);
$check('Suche: Treffer mit Link, Eltern und Ablage; gelöschte Datei fällt weg', count($s) === 2 && $s[0]['typ'] === 'fahrzeug' && str_contains($s[0]['link'], 'p=vehicle_file&id=5') && $s[0]['eltern']['text'] === 'MTW' && str_contains($s[0]['eltern']['url'], '#dokumente')
    && $s[1]['typ'] === 'termin' && $s[1]['label'] === 'Termin' && $s[1]['eltern']['text'] === 'Jahresabschluss' && $s[1]['ki']['tags'] === ['einladung']);
$GLOBALS['rechte'] = ['view_vehicles' => false, 'termin_sehen' => false];
$check('Suche: nur, was man sehen darf', ki_suche('einladung', $u) === [] && ki_suche('', $u) === []);
$GLOBALS['rechte'] = [];
$check('Dateiname im Kontext, Titel nur, wenn er abweicht', str_ends_with(ki_datei_laden('fahrzeug', 5)['kontext'], 'Dateiname: notiz.txt, Titel: UVV 2026') && str_contains(ki_datei_laden('termin', 7)['kontext'], 'Anhang zum Termin „Jahresabschluss". Dateiname: einladung.pdf') && ki_datei_laden('standort', 1) === null);
$GLOBALS['execs'] = [];
ki_hand_speichern('fahrzeug', 5, "  Von Hand.\r\n", 'a, a, b');
$check('von Hand speichern', str_contains($GLOBALS['execs'][0][0], 'INSERT INTO datei_ki') && $GLOBALS['execs'][0][1] === ['fahrzeug', 5, 'Von Hand.', 'a, b', '', 'hand']);

/* ---------- Seiten ---------- */
$htmlS = render_partial('ki_suche', ['q' => 'UVV', 'treffer' => $s, 'tags' => ki_tags_zaehlen(['UVV, Rechnung', 'uvv']), 'aktiv' => true]);
$check('Suchseite: Formular, häufige Schlagwörter, Treffer', str_contains($htmlS, 'name="q" value="UVV"') && str_contains($htmlS, '#UVV <span class="muted">2</span>') && str_contains($htmlS, 'chip--aktiv') && str_contains($htmlS, 'Treffer für „UVV"') && str_contains($htmlS, 'Jahresabschluss'));
$htmlS = render_partial('ki_suche', ['q' => '', 'treffer' => [], 'tags' => [], 'aktiv' => false]);
$check('Suchseite ohne Auswertung: Hinweis', str_contains($htmlS, 'ausgeschaltet') && !str_contains($htmlS, 'Treffer für'));
$htmlA = render_partial('admin/ki', ['fehler' => '', 'hinweis' => '', 'test' => ['ok' => true, 'fehler' => '', 'modelle' => ['qwen2.5:7b']], 'status' => ['offen' => 2, 'laeuft' => 0, 'fertig' => 5, 'fehler' => 1, 'ergebnisse' => 5],
    'fehlerliste' => [['typ' => 'fahrzeug', 'datei_id' => 8, 'fehler' => 'Die Datei fehlt in der Ablage.', 'versuche' => 3, 'erledigt_am' => '2026-10-15 10:00:00', 'name' => 'weg.pdf', 'eltern' => ['text' => 'MTW', 'url' => '?p=vehicle&id=2']]],
    'gruende' => [['fehler' => 'Bilder werden nur mit einem Vision-Modell ausgewertet (Einstellung „Vision-Modell").', 'n' => 61], ['fehler' => 'Die Datei fehlt in der Ablage.', 'n' => 1]],
    'aktiv' => true, 'url' => 'http://192.168.1.20:11434', 'urlGrund' => null, 'pdftotext' => true, 'pdftoppm' => false, 'module' => [['label' => 'Fahrzeugakte', 'an' => true], ['label' => 'Termin', 'an' => false]]]);
$check('Verwaltungsseite: Verbindung, Modelle, Warteschlange, Gründe, Fehler, Knöpfe', str_contains($htmlA, 'http://192.168.1.20:11434') && str_contains($htmlA, '<code>qwen2.5:7b</code>') && str_contains($htmlA, 'pdftoppm ✘ fehlt') && str_contains($htmlA, 'Termin (aus)')
    && str_contains($htmlA, 'value="abarbeiten"') && str_contains($htmlA, 'value="alle"') && str_contains($htmlA, 'value="erneut"') && str_contains($htmlA, 'weg.pdf') && str_contains($htmlA, 'Die Datei fehlt')
    && str_contains($htmlA, 'Woran es scheitert') && str_contains($htmlA, '<td style="text-align:right">61</td>') && str_contains($htmlA, '1 von 1') && str_contains($htmlA, 'value="alle_neu"'));
$einstellen(['ki_aktiv' => '0']);
$htmlA = render_partial('admin/ki', ['fehler' => '', 'hinweis' => '', 'test' => null, 'status' => ['offen' => 0, 'laeuft' => 0, 'fertig' => 0, 'fehler' => 0, 'ergebnisse' => 0], 'fehlerliste' => [], 'gruende' => [], 'aktiv' => false, 'url' => '', 'urlGrund' => 'Keine Adresse eingetragen.', 'pdftotext' => false, 'pdftoppm' => false, 'module' => []]);
$check('Verwaltungsseite aus: Warnung, Knöpfe gesperrt', str_contains($htmlA, 'ausgeschaltet') && str_contains($htmlA, 'value="abarbeiten" disabled') && str_contains($htmlA, 'value="test" disabled') && !str_contains($htmlA, 'value="erneut"'));

// aufräumen
foreach (glob($tmp . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($tmp);

echo "$ok bestanden, $fail fehlgeschlagen\n";
