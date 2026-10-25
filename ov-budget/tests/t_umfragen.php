<?php
declare(strict_types=1);
/*
 * Abstimmungen: Seiten säubern und prüfen, Antworten normieren, Auswertung,
 * CSV, Zustand und Zeitfenster, Code und Adresse, Formular, Ansichten.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'umfrage_modul_name' => 'Abstimmungen', 'umfrage_code_laenge' => '6'];
$GLOBALS['inserts'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['zeilen'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM umfrage_antworten')) { return $GLOBALS['zeilen']; }
    if (str_contains($sql, 'FROM umfragen ')) { return $GLOBALS['umfragen_zeilen'] ?? []; }
    if (str_contains($sql, 'FROM umfrage_empfaenger')) { return $GLOBALS['empfaenger'] ?? []; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return str_contains($sql, 'FROM contacts') ? ($GLOBALS['kontakt'] ?? null) : null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 3; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return 7; }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'events', 'connector', 'contacts', 'mail', 'umfragen'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }
// KI-Attrappe: an/aus und eine feste Antwort
$GLOBALS['ki_an'] = false;
function ki_aktiv(): bool { return (bool)$GLOBALS['ki_an']; }
function ki_text_antwort(string $prompt, int $max = 6000): string { $GLOBALS['ki_prompt'] = $prompt; if (!empty($GLOBALS['ki_kaputt'])) { throw new RuntimeException('Ollama weg'); } return "Die Mehrheit will Samstag.\n"; }
function ki_text_kuerzen(string $t, int $max): string { return mb_substr($t, 0, $max); }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Seiten säubern und prüfen ---------- */
$seiten = [
    ['titel' => " Termin\x01 ", 'text' => 'Bitte eins wählen', 'felder' => [
        ['id' => 'f1', 'typ' => 'einfach', 'frage' => 'Welcher Tag?', 'pflicht' => 1, 'optionen' => ['Samstag', ' ', 'Sonntag', "Montag\x00"]],
        ['id' => 'f2', 'typ' => 'mehrfach', 'frage' => 'Was isst du?', 'pflicht' => 0, 'optionen' => ['Fleisch', 'Vegetarisch', 'Vegan']],
        ['id' => 'f3', 'typ' => 'skala', 'frage' => 'Wie gern?', 'pflicht' => 1, 'min' => 1, 'max' => 5, 'links' => 'gar nicht', 'rechts' => 'sehr'],
        ['id' => 'f4', 'typ' => 'janein', 'frage' => 'Kommst du?', 'pflicht' => 1],
        ['id' => 'f5', 'typ' => 'text', 'frage' => 'Noch was?', 'pflicht' => 0, 'lang' => 1],
        ['id' => 'f6', 'typ' => 'hinweis', 'frage' => 'Danke fürs Mitmachen', 'pflicht' => 1],
    ]],
];
[$sauber, $fehler] = umfrage_seiten_pruefen($seiten);
$check('gültige Seiten ohne Fehler', $fehler === [] && count($sauber) === 1 && count($sauber[0]['felder']) === 6);
$check('Steuerzeichen und Leeres raus', $sauber[0]['titel'] === 'Termin' && $sauber[0]['felder'][0]['optionen'] === ['Samstag', 'Sonntag', 'Montag']);
$check('Hinweis ist nie Pflicht', $sauber[0]['felder'][5]['pflicht'] === 0);
$check('Skala mit Grenzen und Beschriftung', $sauber[0]['felder'][2]['min'] === 1 && $sauber[0]['felder'][2]['max'] === 5 && $sauber[0]['felder'][2]['links'] === 'gar nicht');
$check('sechs Fragen, fünf echte', umfrage_fragen_anzahl($sauber) === 5);

[$s2, $f2] = umfrage_seiten_pruefen([['felder' => [
    ['id' => 'f1', 'typ' => 'einfach', 'frage' => 'Nur eine Antwort', 'optionen' => ['A']],
    ['id' => 'f2', 'typ' => 'text', 'frage' => ''],
    ['id' => 'f3', 'typ' => 'tabelle', 'frage' => 'Gibt es nicht'],
    ['id' => 'f4', 'typ' => 'skala', 'frage' => 'Kaputt', 'min' => 5, 'max' => 5],
    ['id' => 'f1', 'typ' => 'janein', 'frage' => 'Doppelte Kennung'],
]], ['felder' => []]]);
$check('Fehler je Frage benannt', count($f2) === 5
    && str_contains($f2[0], 'Seite 1, Frage 1') && str_contains($f2[0], 'mindestens zwei')
    && str_contains($f2[1], 'keinen Text') && str_contains($f2[2], 'unbekannter Fragetyp')
    && str_contains($f2[3], 'Skala') && str_contains($f2[4], 'Seite 2 hat keine Frage'));
$check('doppelte Kennung bekommt eine neue', count($s2) === 1 && count($s2[0]['felder']) === 1 && $s2[0]['felder'][0]['id'] !== 'f1');
[$s3, $f3] = umfrage_seiten_pruefen([]);
$check('ganz leer: eine Seite mit Frage nötig', $s3 === [] && count($f3) === 1);
[$s4, $f4] = umfrage_seiten_pruefen(array_fill(0, 21, ['felder' => [['id' => 'x', 'typ' => 'janein', 'frage' => 'Ja?']]]));
$check('höchstens 20 Seiten', count($s4) === 20 && str_contains($f4[0], 'Höchstens 20'));

/* ---------- Antworten normieren ---------- */
$felder = umfrage_felder($sauber);
$check('Felder nach Kennung', array_keys($felder) === ['f1', 'f2', 'f3', 'f4', 'f5', 'f6']);
$n = umfrage_antwort_normieren($sauber, ['antworten' => [
    'f1' => '2', 'f2' => [0, 2, 9, 'x'], 'f3' => 7, 'f4' => 'ja', 'f5' => "  Gerne\x00 wieder ", 'f6' => 'egal', 'f99' => 1,
], 'name' => " Anna\n "]);
$check('einfach als Zahl, mehrfach nur gültige, Skala außerhalb weg', $n['antworten']['f1'] === 2 && $n['antworten']['f2'] === [0, 2]
    && !isset($n['antworten']['f3']) && $n['antworten']['f4'] === 'ja' && $n['antworten']['f5'] === 'Gerne wieder');
$check('Hinweis und Unbekanntes fallen weg, Name gesäubert', !isset($n['antworten']['f6'], $n['antworten']['f99']) && $n['name'] === 'Anna');
$check('leere Antwort bleibt leer', umfrage_antwort_normieren($sauber, []) === ['antworten' => [], 'name' => '']);

/* ---------- Auswertung ---------- */
$antworten = [
    ['ts' => '2026-10-20 18:00:00', 'name' => 'Anna', 'antworten' => ['f1' => 0, 'f2' => [0, 1], 'f3' => 5, 'f4' => 'ja', 'f5' => 'Super']],
    ['ts' => '2026-10-20 18:05:00', 'name' => '', 'antworten' => ['f1' => 0, 'f2' => [1], 'f3' => 3, 'f4' => 'nein']],
    ['ts' => '2026-10-20 18:09:00', 'name' => 'Ben', 'antworten' => ['f1' => 2, 'f3' => 4, 'f4' => 'ja', 'f5' => "Zeile 1\nZeile 2"]],
    ['ts' => '2026-10-20 18:10:00', 'name' => '', 'antworten' => []],
];
$a = umfrage_auswertung($sauber, $antworten);
$check('vier Antworten, Hinweis nicht dabei', $a['anzahl'] === 4 && !isset($a['felder']['f6']) && count($a['felder']) === 5);
$check('einfach gezählt', $a['felder']['f1']['zaehler'] === [2, 0, 1] && $a['felder']['f1']['beantwortet'] === 3);
$check('mehrfach gezählt', $a['felder']['f2']['zaehler'] === [1, 2, 0] && $a['felder']['f2']['beantwortet'] === 2);
$check('Skala: Verteilung und Durchschnitt', $a['felder']['f3']['verteilung'] === [1 => 0, 2 => 0, 3 => 1, 4 => 1, 5 => 1] && $a['felder']['f3']['schnitt'] === 4.0);
$check('ja/nein', $a['felder']['f4']['ja'] === 2 && $a['felder']['f4']['nein'] === 1);
$check('Freitext gesammelt', $a['felder']['f5']['texte'] === ['Super', "Zeile 1\nZeile 2"]);
$check('Prozent ohne Division durch null', umfrage_prozent(2, 3) === 67 && umfrage_prozent(1, 0) === 0);
$check('Werte lesbar', umfrage_wert_text($felder['f1'], 2) === 'Montag' && umfrage_wert_text($felder['f2'], [0, 2]) === 'Fleisch; Vegan'
    && umfrage_wert_text($felder['f4'], 'nein') === 'Nein' && umfrage_wert_text($felder['f3'], 4) === '4' && umfrage_wert_text($felder['f1'], null) === '');

$csv = umfrage_csv($sauber, $antworten);
$zeilen = explode("\r\n", trim($csv));
$check('CSV: BOM, Kopf mit Fragen, eine Zeile je Antwort', str_starts_with($csv, "\xEF\xBB\xBF") && count($zeilen) === 5
    && str_contains($zeilen[0], '"Welcher Tag?";"Was isst du?"') && !str_contains($zeilen[0], 'Danke fürs')
    && str_contains($zeilen[1], '"Anna";"Samstag";"Fleisch; Vegetarisch";"5";"Ja";"Super"'));
$check('CSV: Zeilenumbruch im Text bleibt in Anführungszeichen', str_contains($csv, "\"Zeile 1\nZeile 2\""));

/* ---------- Zustand und Zeitfenster ---------- */
$jetzt = (int)strtotime('2026-10-24 12:00:00');
$u = ['status' => 'offen', 'beginn' => '2026-10-24 10:00:00', 'ende' => '2026-10-31 20:00:00'];
$check('läuft', umfrage_zustand($u, $jetzt) === 'laeuft' && umfrage_laeuft($u, $jetzt));
$check('wartet vor dem Beginn', umfrage_zustand(['status' => 'offen', 'beginn' => '2026-10-25 10:00:00', 'ende' => '2026-10-31 20:00:00'], $jetzt) === 'wartet');
$check('ohne Beginn sofort', umfrage_zustand(['status' => 'offen', 'beginn' => null, 'ende' => '2026-10-31 20:00:00'], $jetzt) === 'laeuft');
$check('abgelaufen nach dem Ende', umfrage_zustand(['status' => 'offen', 'beginn' => '', 'ende' => '2026-10-24 11:59:00'], $jetzt) === 'abgelaufen');
$check('Entwurf und geschlossen unabhängig von der Zeit', umfrage_zustand(['status' => 'entwurf'] + $u, $jetzt) === 'entwurf'
    && umfrage_zustand(['status' => 'geschlossen'] + $u, $jetzt) === 'geschlossen');
$check('Texte und Marken', umfrage_zustand_text('laeuft') === 'läuft' && str_contains(umfrage_zustand_badge('wartet'), 'öffnet später'));

/* ---------- Code und Adresse ---------- */
$check('Kennung ist Prüfsumme, nicht der Code', umfrage_kennung('ab23cd') === umfrage_kennung('AB23CD') && strlen(umfrage_kennung('AB23CD')) === 64 && !str_contains(umfrage_kennung('AB23CD'), 'AB23CD'));
$check('Code-Form', umfrage_code_gueltig('AB23CD') && !umfrage_code_gueltig('ab-23') && !umfrage_code_gueltig('AB1'));
$code = umfrage_code_neu(6);
$check('neuer Code aus dem Einladungsalphabet', strlen($code) === 6 && umfrage_code_gueltig($code) && !str_contains($code, '0') && !str_contains($code, 'O'));
$con = ['url' => 'https://ov.example.de/connector', 'kurz_url' => 'https://i.example.de'];
$check('kurze Adresse', umfrage_url($con, 'AB23CD') === 'https://i.example.de/AB23CD');
$check('ohne kurze Adresse der lange Weg', umfrage_url(['url' => 'https://ov.example.de/connector/', 'kurz_url' => ''], 'AB23CD') === 'https://ov.example.de/connector/index.php?u=AB23CD');

/* ---------- Formular ---------- */
$_POST = ['titel' => '', 'ende' => '', 'beginn' => 'kaputt', 'seiten_json' => '{', 'connector_id' => '0'];
[$id, $f] = umfrage_save_from_post(null);
$check('Formular: Titel, Beginn, Ende und Seiten bemängelt', $id === null && count($f) === 4
    && str_contains($f[0], 'Titel') && str_contains($f[1], 'Beginn') && str_contains($f[2], 'Ende') && str_contains($f[3], 'mindestens eine Seite'));
$_POST = ['titel' => 'Sommerfest', 'beginn' => '2026-11-01T20:00', 'ende' => '2026-11-01T19:00', 'seiten_json' => json_encode($seiten)];
[$id, $f] = umfrage_save_from_post(null);
$check('Ende vor Beginn', $id === null && count($f) === 1 && str_contains($f[0], 'nach dem Beginn'));
$_POST = ['titel' => 'X', 'ende' => '2026-11-08T20:00', 'seiten_json' => json_encode($seiten), 'bericht_rhythmus' => 'taeglich', 'bericht_kontakte' => ['5']];
[$id, $f] = umfrage_save_from_post(null);
$check('Bericht ohne gültigen Kontakt bemängelt', $id === null && count($f) === 1 && str_contains($f[0], 'mindestens einen Kontakt'));
$GLOBALS['kontakt'] = ['id' => 9, 'vorname' => 'Max', 'nachname' => 'Muster', 'organisation' => '', 'email' => 'max@example.de', 'anrede' => '', 'titel' => ''];
$GLOBALS['inserts'] = []; $GLOBALS['execs'] = [];
$_POST = ['titel' => '  Sommerfest 2027 ', 'beschreibung' => 'Bitte abstimmen', 'beginn' => '', 'ende' => '2026-11-08T20:00',
          'seiten_json' => json_encode($seiten), 'name_feld' => '1', 'dank' => 'Danke!', 'connector_id' => '0',
          'bericht_rhythmus' => 'woechentlich', 'bericht_kontakte' => ['9', '9', 'x', '0'], 'bericht_ki' => '1'];
[$id, $f] = umfrage_save_from_post(null);
$check('angelegt, Empfänger einmal eingetragen', $id === 7 && $f === [] && count($GLOBALS['inserts']) === 2
    && $GLOBALS['inserts'][1] === ['umfrage_empfaenger', ['umfrage_id' => 7, 'contact_id' => 9]]
    && str_contains($GLOBALS['execs'][0][0], 'DELETE FROM umfrage_empfaenger'));
$neu = $GLOBALS['inserts'][0][1];
$check('Rhythmus und KI gespeichert, kein Kontaktfeld mehr', $neu['bericht_rhythmus'] === 'woechentlich' && $neu['bericht_ki'] === 1 && !array_key_exists('bericht_kontakt_id', $neu));
$GLOBALS['kontakt'] = null;
$check('Entwurf mit Code, ohne Beginn, Ende als DATETIME, Seiten als JSON', $neu['status'] === 'entwurf' && strlen($neu['code']) === 6
    && $neu['beginn'] === null && $neu['ende'] === '2026-11-08 20:00:00' && $neu['titel'] === 'Sommerfest 2027'
    && $neu['name_feld'] === 1 && $neu['connector_id'] === null && json_decode($neu['seiten'], true)[0]['felder'][0]['frage'] === 'Welcher Tag?');
$check('Seiten aus der Zeile wieder lesbar', umfrage_seiten($neu)[0]['felder'][2]['typ'] === 'skala' && umfrage_seiten(['seiten' => 'kaputt']) === []);
$_POST = [];

/* ---------- Antworten speichern und löschen ---------- */
$GLOBALS['inserts'] = [];
umfrage_antwort_speichern(['id' => 7, 'seiten' => $neu['seiten']], ['antworten' => ['f1' => 1, 'f4' => 'ja', 'f3' => 99], 'name' => 'Ben'], $jetzt);
$gespeichert = $GLOBALS['inserts'][0][1];
$check('Antwort geprüft gespeichert', $GLOBALS['inserts'][0][0] === 'umfrage_antworten' && $gespeichert['umfrage_id'] === 7
    && $gespeichert['name'] === 'Ben' && json_decode($gespeichert['daten'], true) === ['f1' => 1, 'f4' => 'ja'] && $gespeichert['ts'] === date('Y-m-d H:i:s', $jetzt));
$GLOBALS['execs'] = [];
$check('Antworten löschen', umfrage_antworten_loeschen(['id' => 7]) === 3 && str_contains($GLOBALS['execs'][0][0], 'DELETE FROM umfrage_antworten'));

/* ---------- Ansichten ---------- */
$umfrage = ['id' => 7, 'titel' => 'Sommerfest 2027', 'beschreibung' => 'Bitte abstimmen', 'code' => 'AB23CD', 'connector_id' => 3,
            'status' => 'offen', 'beginn' => null, 'ende' => '2026-11-08 20:00:00', 'name_feld' => 1, 'dank' => '', 'seiten' => $neu['seiten']];
$connector = ['id' => 3, 'name' => 'Feedback', 'url' => 'https://ov.example.de/connector', 'kurz_url' => 'https://i.example.de',
              'is_active' => 1, 'fuer_umfragen' => 1, 'pem' => 'x', 'pubkey' => 'y', 'server_pub' => 'z'];
$vars = ['umfrage' => $umfrage, 'seiten' => $sauber, 'connector' => $connector, 'zustand' => 'laeuft',
         'adresse' => umfrage_url($connector, 'AB23CD'), 'antworten' => $antworten, 'auswertung' => $a, 'darf' => true,
         'letzterAbruf' => $jetzt, 'abholbar' => true, 'empfaenger' => [], 'mailAktiv' => false];
$html = render_partial('umfrage', $vars);
$check('Detail: QR mit kurzer Adresse, Link kopieren, Bild laden, Drucken', str_contains($html, 'data-qr="https://i.example.de/AB23CD"')
    && str_contains($html, 'data-kopieren="#umfrage-link"') && str_contains($html, 'id="qr-laden"') && str_contains($html, 'id="qr-drucken"')
    && str_contains($html, 'data-qr-untertitel="Abstimmung"'));
$check('Detail: Schließen statt Freigeben, Abholen, CSV, Antworten löschen', str_contains($html, 'value="schliessen"') && !str_contains($html, 'value="freigeben"')
    && str_contains($html, 'value="abholen"') && str_contains($html, 'umfrage_export') && str_contains($html, 'value="antworten_loeschen"'));
$check('Detail: Ergebnisse mit Balken, Durchschnitt und Texten', str_contains($html, '4 Antwort(en)') && str_contains($html, 'Ø 4,0')
    && str_contains($html, 'style="width:67%"') && str_contains($html, 'Zeile 1<br') && str_contains($html, 'Anna, Ben'));
$check('Detail: Fragen mit Typen', str_contains($html, 'Welcher Tag?') && str_contains($html, 'Samstag · Sonntag · Montag') && str_contains($html, 'Hinweis:'));
$html = render_partial('umfrage', ['zustand' => 'entwurf', 'antworten' => [], 'auswertung' => umfrage_auswertung($sauber, [])] + $vars);
$check('Entwurf: Freigeben, kein QR, Adresse als Vorschau', str_contains($html, 'value="freigeben"') && !str_contains($html, 'data-qr=')
    && str_contains($html, 'https://i.example.de/AB23CD') && str_contains($html, 'Noch keine Antworten'));
$html = render_partial('umfrage', ['connector' => null, 'adresse' => '', 'abholbar' => false] + $vars);
$check('ohne Connector: Hinweis statt QR', str_contains($html, 'Ohne Connector gibt es keine Adresse') && !str_contains($html, 'data-qr='));
$html = render_partial('umfrage', ['darf' => false] + $vars);
$check('ohne Rechte keine Knöpfe', !str_contains($html, 'value="schliessen"') && !str_contains($html, 'value="loeschen"') && str_contains($html, 'umfrage_export'));

$html = render_partial('umfragen', ['title' => 'Abstimmungen', 'darf' => true, 'connectoren' => [],
    'gruppen' => ['laufend' => [$umfrage + ['zustand' => 'laeuft', 'antworten_anzahl' => 4]], 'kommend' => [], 'entwurf' => [], 'vorbei' => []]]);
$check('Liste: Gruppe, Zeile, Hinweis auf fehlenden Connector', str_contains($html, 'Läuft gerade') && str_contains($html, 'Sommerfest 2027')
    && str_contains($html, '>4<') && str_contains($html, 'Verwendung') && str_contains($html, '+ Abstimmung'));
$html = render_partial('umfragen', ['title' => 'Abstimmungen', 'darf' => false, 'connectoren' => [], 'gruppen' => ['laufend' => [], 'kommend' => [], 'entwurf' => [], 'vorbei' => []]]);
$check('Liste leer', str_contains($html, 'Noch keine Abstimmung') && !str_contains($html, '+ Abstimmung'));

$maxK = ['id' => 9, 'vorname' => 'Max', 'nachname' => 'Muster', 'organisation' => '', 'email' => 'max@example.de', 'anrede' => '', 'titel' => ''];
$annaK = ['id' => 10, 'vorname' => 'Anna', 'nachname' => 'Ärztin', 'organisation' => '', 'email' => 'anna@example.de', 'anrede' => '', 'titel' => ''];
$html = render_partial('umfrage', ['empfaenger' => [$maxK, $annaK], 'mailAktiv' => true, 'umfrage' => ['bericht_rhythmus' => 'taeglich', 'bericht_ki' => 1, 'bericht_zuletzt' => '2026-10-24 07:00:00'] + $umfrage] + $vars);
$check('Detail: zwei Empfänger, KI, Knopf zum Senden', str_contains($html, '2 Empfänger') && str_contains($html, 'Max Muster (max@example.de), Anna Ärztin (anna@example.de)')
    && str_contains($html, 'mit KI-Zusammenfassung') && str_contains($html, 'value="bericht"') && str_contains($html, 'Zuletzt verschickt 24.10.2026'));
$html = render_partial('umfrage_edit', ['umfrage' => ['bericht_rhythmus' => 'taeglich', 'bericht_kontakte' => [10], 'bericht_ki' => 0] + $umfrage, 'errors' => ['Bitte einen Titel angeben.'],
    'connectoren' => [$connector], 'antwortenAnzahl' => 4, 'kontakte' => [$maxK, $annaK], 'mailAktiv' => true, 'kiAktiv' => false]);
$check('Bearbeiten: Mehrfachauswahl der Empfänger, Anna gewählt', str_contains($html, 'name="bericht_kontakte[]" multiple')
    && str_contains($html, 'value="10" selected') && !str_contains($html, 'value="9" selected') && str_contains($html, 'option value="taeglich" selected'));
$check('Bearbeiten: Gestalter, JSON-Feld, Fehler, Warnung bei Antworten, Connector gewählt', str_contains($html, 'data-umfrage-editor')
    && str_contains($html, 'id="seiten_json"') && str_contains($html, 'Bitte einen Titel angeben.') && str_contains($html, 'schon 4 Antwort(en)')
    && str_contains($html, 'value="3" selected') && str_contains($html, 'value="2026-11-08T20:00"') && str_contains($html, 'name="name_feld" value="1" checked'));

/* ---------- Bericht per E-Mail ---------- */
$GLOBALS['kontakt'] = ['id' => 9, 'vorname' => 'Max', 'nachname' => 'Muster', 'organisation' => '', 'email' => 'max@example.de', 'anrede' => '', 'titel' => ''];
$check('Rhythmus-Texte', umfrage_bericht_text_rhythmus('taeglich') === 'täglich – und zum Abschluss' && umfrage_bericht_text_rhythmus('x') === 'kein Bericht');
$ub = ['id' => 7, 'titel' => 'Sommerfest', 'beschreibung' => '', 'status' => 'offen', 'beginn' => '2026-10-20 10:00:00', 'ende' => '2026-10-31 20:00:00',
       'created_at' => '2026-10-19 12:00:00', 'bericht_rhythmus' => 'taeglich', 'empfaenger_anzahl' => 1, 'bericht_ki' => 0,
       'bericht_zuletzt' => null, 'bericht_ende_am' => null, 'name_feld' => 1, 'seiten' => $neu['seiten']];
$t = static fn(string $s): int => (int)strtotime($s);
$check('täglich: erster Bericht am Morgen nach dem Anlegen', umfrage_bericht_faellig($ub, $t('2026-10-24 07:00:00')) === 'taeglich');
$check('täglich: vor der Stunde nicht', umfrage_bericht_faellig($ub, $t('2026-10-24 06:59:00')) === null);
$check('täglich: heute schon geschickt', umfrage_bericht_faellig(['bericht_zuletzt' => '2026-10-24 07:01:00'] + $ub, $t('2026-10-24 18:00:00')) === null
    && umfrage_bericht_faellig(['bericht_zuletzt' => '2026-10-23 07:01:00'] + $ub, $t('2026-10-24 18:00:00')) === 'taeglich');
$check('vor dem Beginn nichts', umfrage_bericht_faellig($ub, $t('2026-10-19 12:00:00')) === null);
$check('wöchentlich: sieben Tage nach dem letzten', umfrage_bericht_faellig(['bericht_rhythmus' => 'woechentlich', 'bericht_zuletzt' => '2026-10-20 08:00:00'] + $ub, $t('2026-10-26 12:00:00')) === null
    && umfrage_bericht_faellig(['bericht_rhythmus' => 'woechentlich', 'bericht_zuletzt' => '2026-10-20 08:00:00'] + $ub, $t('2026-10-27 09:00:00')) === 'woechentlich');
$check('nur am Ende: vorher nichts, danach nach der Karenz einmal', umfrage_bericht_faellig(['bericht_rhythmus' => 'ende'] + $ub, $t('2026-10-30 09:00:00')) === null
    && umfrage_bericht_faellig(['bericht_rhythmus' => 'ende'] + $ub, $t('2026-10-31 20:05:00')) === null
    && umfrage_bericht_faellig(['bericht_rhythmus' => 'ende'] + $ub, $t('2026-10-31 20:11:00')) === 'ende'
    && umfrage_bericht_faellig(['bericht_rhythmus' => 'ende', 'bericht_ende_am' => '2026-10-31 20:12:00'] + $ub, $t('2026-11-01 09:00:00')) === null);
$check('täglich bekommt am Ende auch den Abschluss', umfrage_bericht_faellig($ub, $t('2026-11-01 09:00:00')) === 'ende');
$check('ohne Kontakt, ohne Rhythmus, nicht offen: nichts', umfrage_bericht_faellig(['empfaenger_anzahl' => 0] + $ub, $t('2026-10-24 09:00:00')) === null
    && umfrage_bericht_faellig(['bericht_rhythmus' => ''] + $ub, $t('2026-10-24 09:00:00')) === null
    && umfrage_bericht_faellig(['status' => 'entwurf'] + $ub, $t('2026-10-24 09:00:00')) === null);

$text = umfrage_bericht_text($ub, $sauber, $antworten, 'taeglich', null, $t('2026-10-24 07:00:00'), 'Max Muster');
$check('Berichtstext: Kopf, Zählung, Skala, Freitext, Namen, Fuß', str_contains($text, 'Abstimmung „Sommerfest"') && str_contains($text, 'Zwischenstand vom 24.10.2026, 07:00 Uhr')
    && str_contains($text, 'Antworten: 4') && str_contains($text, "1. Welcher Tag? (3 Antworten)\n   Samstag: 2 (67 %)") && str_contains($text, 'Mehrfachauswahl')
    && str_contains($text, 'Durchschnitt 4,0') && str_contains($text, '– Super') && str_contains($text, "– Zeile 1\n     Zeile 2")
    && str_contains($text, 'Ja: 2 (67 %) · Nein: 1 (33 %)') && str_contains($text, 'Namen (freiwillig angegeben): Anna, Ben') && str_contains($text, 'verschickt an Max Muster'));
$check('Ergebnisbericht mit KI oben', str_contains(umfrage_bericht_text($ub, $sauber, $antworten, 'ende', 'Alles gut.', $t('2026-11-01 09:00:00')), 'Ergebnis – die Abstimmung ist beendet')
    && str_contains(umfrage_bericht_text($ub, $sauber, [], 'ende', 'Alles gut.'), "--- Zusammenfassung (KI, ohne Gewähr) ---\nAlles gut."));
$check('Betreff', umfrage_bericht_betreff($ub, 'ende') === 'Ergebnis der Abstimmung „Sommerfest"'
    && umfrage_bericht_betreff($ub, 'taeglich', $t('2026-10-24 07:00:00')) === 'Abstimmung „Sommerfest": Zwischenstand 24.10.2026');

$check('KI aus: keine Zusammenfassung', umfrage_bericht_ki('x') === null);
$GLOBALS['ki_an'] = true;
$check('KI an: Zusammenfassung, Prompt enthält den Bericht', umfrage_bericht_ki('Zahlen hier') === 'Die Mehrheit will Samstag.'
    && str_contains($GLOBALS['ki_prompt'], 'Zahlen hier') && str_contains($GLOBALS['ki_prompt'], 'ohne JSON'));
$GLOBALS['ki_kaputt'] = true;
$check('KI kaputt: Bericht geht ohne', umfrage_bericht_ki('x') === null);
$GLOBALS['ki_kaputt'] = false;

$post = [];
$GLOBALS['mail_attrappe'] = static function (string $an, string $betreff, string $text, string $name) use (&$post): void { $post[] = [$an, $betreff, $text, $name]; };
$GLOBALS['zeilen'] = array_map(static fn($a) => ['ts' => $a['ts'], 'name' => $a['name'], 'daten' => json_encode($a['antworten'])], $antworten);
$GLOBALS['empfaenger'] = [$maxK, $annaK, ['id' => 11, 'vorname' => 'Ohne', 'nachname' => 'Mail', 'organisation' => '', 'email' => '', 'anrede' => '', 'titel' => '']];
$GLOBALS['updates'] = [];
$r = umfrage_bericht_senden(['bericht_ki' => 1] + $ub, 'ende', $t('2026-11-01 09:00:00'));
$check('Bericht an beide Empfänger mit Adresse, mit KI, Zeitpunkte gemerkt', $r['ok'] && $r['ki'] && $r['gesendet'] === 2 && count($post) === 2
    && $post[0][0] === 'max@example.de' && $post[0][3] === 'Max Muster' && $post[1][0] === 'anna@example.de' && $post[1][3] === 'Anna Ärztin'
    && str_starts_with($post[0][1], 'Ergebnis der Abstimmung') && str_contains($post[0][2], 'Die Mehrheit will Samstag.') && str_contains($post[0][2], 'Samstag: 2')
    && str_contains($post[0][2], 'verschickt an Max Muster, Anna Ärztin') && str_contains($r['text'], 'Max Muster (max@example.de), Anna Ärztin (anna@example.de)')
    && $GLOBALS['updates'][0][1] === ['bericht_zuletzt' => '2026-11-01 09:00:00', 'bericht_ende_am' => '2026-11-01 09:00:00']);
$check('KI nur einmal geholt', substr_count($GLOBALS['ki_prompt'], 'Abstimmung „Sommerfest"') === 1);
$GLOBALS['empfaenger'] = [];
$r = umfrage_bericht_senden($ub, 'taeglich');
$check('ohne Kontakt kein Versand', !$r['ok'] && str_contains($r['text'], 'kein Kontakt'));
$GLOBALS['empfaenger'] = [$maxK, $annaK];
$GLOBALS['mail_attrappe'] = static function (string $an): void { if ($an === 'max@example.de') { throw new MailException('Server weg'); } };
$GLOBALS['updates'] = [];
$r = umfrage_bericht_senden($ub, 'taeglich');
$check('einer scheitert: trotzdem gelungen, Fehler genannt', $r['ok'] && $r['gesendet'] === 1 && count($r['fehler']) === 1
    && str_contains($r['text'], 'nicht erreicht: Max Muster: Server weg') && count($GLOBALS['updates']) === 1);
$GLOBALS['mail_attrappe'] = static function (): void { throw new MailException('Server weg'); };
$r = umfrage_bericht_senden($ub, 'taeglich');
$check('alle scheitern: Fehler', !$r['ok'] && $r['gesendet'] === 0 && str_contains($r['text'], 'Server weg'));

$GLOBALS['umfragen_zeilen'] = [$ub, ['id' => 8, 'bericht_rhythmus' => 'ende'] + $ub];
$GLOBALS['settings'] += ['mail_aktiv' => '1', 'mail_host' => 'smtp.example.de', 'mail_absender' => 'ov@example.de'];
settings_reset_cache();
$post = [];
$GLOBALS['updates'] = [];
$GLOBALS['mail_attrappe'] = static function (string $an, string $betreff, string $text, string $name) use (&$post): void { $post[] = $betreff; };
$r = umfrage_berichte_senden($t('2026-10-24 07:30:00'));
$check('fällige Berichte: täglich ja an beide Empfänger, nur am Ende nein', $r['geprueft'] === 2 && $r['gesendet'] === 1 && $r['fehler'] === [] && count($post) === 2 && str_contains($post[0], 'Zwischenstand'));
$GLOBALS['settings']['mail_aktiv'] = '0';
settings_reset_cache();
$check('Versand aus: nichts geprüft', umfrage_berichte_senden($t('2026-10-24 07:30:00')) === ['geprueft' => 0, 'gesendet' => 0, 'fehler' => []]);

echo "$ok bestanden, $fail fehlgeschlagen\n";
