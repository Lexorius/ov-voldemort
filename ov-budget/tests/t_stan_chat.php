<?php
declare(strict_types=1);
/* Fragen an die STAN: Stichwörter, Bewertung, Auszüge, Prompt, Antwort über die Ollama-Attrappe, Ansicht */
session_start();
$GLOBALS['settings'] = ['ki_aktiv' => '1', 'ki_ollama_url' => 'http://ollama:11434', 'ki_modell' => 'qwen2.5:7b', 'ki_stan' => '1', 'ki_stan_chat' => '1',
    'ki_max_zeichen' => '12000', 'stan_modul_name' => 'STAN', 'upload_max_mb' => '10', 'stan_max_mb' => '1'];
$GLOBALS['rows'] = [];
$GLOBALS['sql'] = [];
$GLOBALS['ki_aufrufe'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM stan_dateien d')) { $GLOBALS['sql'][] = [$sql, $p]; return $GLOBALS['rows']; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'user']; }
$GLOBALS['ki_http_attrappe'] = static function (string $methode, string $pfad, ?array $daten): array {
    $GLOBALS['ki_aufrufe'][] = [$methode, $pfad, $daten];
    return ['response' => "Die Fachgruppe N hat zwei Notstromaggregate mit 50 kVA [1] und eines mit 8 kVA [2].\n"];
};

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'ki', 'stan', 'stan_chat', 'stan_embed'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- reine Helfer ---------- */
$check('Stichwörter ohne Füllwörter', stan_chat_stichworte('Wie viele Notstromaggregate hat die Fachgruppe N und welche Leistung in kVA?') === ['notstromaggregate', 'fachgruppe', 'leistung', 'kva']);
$check('Zahlen und Bindestriche', stan_chat_stichworte('Gibt es 50 kVA im GKW-1?') === ['50', 'kva', 'gkw-1'] && stan_chat_stichworte('und oder ist') === []);
$check('höchstens acht', count(stan_chat_stichworte('alpha beta gamma delta epsilon zeta eta theta iota kappa')) === 8);
$check('Stamm', stan_chat_stamm('Aggregate') === 'aggregat' && stan_chat_stamm('Notstromaggregate') === 'notstromaggregat' && stan_chat_stamm('Leistungen') === 'leist'
    && stan_chat_stamm('kVA') === 'kva' && stan_chat_stamm('12345') === '12345' && stan_chat_stamm('Fachgruppe') === 'fachgrupp');
$check('Kürzel: Großbuchstaben bis drei Zeichen, klein zurück', stan_chat_kuerzel('Hat der ZTr TZ eine SIM-Karte? Und die FGr N?') === ['tz', 'n'] && stan_chat_kuerzel('aber der zgr tz') === []);
$check('Phrasen: Wort vor dem Kürzel', stan_chat_phrasen('Was hat der ZTr TZ und die Fachgruppe N und FGr K (B)?') === ['ztr tz', 'fachgruppe n', 'fgr k']);
$b = stan_chat_suchbegriffe('Hat der ZTr TZ eine SIM-Karte?');
$check('Suchbegriffe zusammen', $b['staemme'] === ['ztr', 'sim-kart'] && $b['kuerzel'] === ['tz'] && $b['phrasen'] === ['ztr tz']);
$check('Kürzel nur als ganzes Wort', stan_chat_wort_zaehlen('Der TZ fährt zum Einsatz, tz.', 'tz') === 2 && stan_chat_wort_zaehlen('Einsatz jetzt', 'tz') === 0);
$check('Bewertung mit Kürzel und Phrase', stan_chat_bewerten('Der Zugtrupp (ZTr TZ) hat', [], ['tz'], ['ztr tz']) === 4 + 12 && stan_chat_bewerten('Einsatz', [], ['tz'], []) === 0);
$check('Dateiname wiegt schwer', stan_chat_bewerten_name('02-01 StAN ZTr TZ-bf.pdf', ['ztr'], ['tz'], ['ztr tz']) === 10 + 12 + 25 && stan_chat_bewerten_name('10-03 StAN ZTr FK-bf.pdf', ['ztr'], ['tz'], ['ztr tz']) === 10
    && stan_chat_bewerten_name('00-00 StAN-Vorbemerkung.pdf', ['ztr'], ['tz'], ['ztr tz']) === 0);
$check('Beschriftung der Quelle', stan_chat_art_text('vollstaendig') === 'vollständig' && stan_chat_art_text('absaetze') === 'Absätze' && stan_chat_art_text('x') === 'Auszüge');
$check('Bewertung: mehr verschiedene Stämme zählen mehr', stan_chat_bewerten('Aggregat Aggregat Aggregat', ['aggregat', 'leist']) === 6
    && stan_chat_bewerten('Aggregat mit Leistung', ['aggregat', 'leist']) === 8 && stan_chat_bewerten('nichts', ['aggregat']) === 0);
$text = str_repeat('Füllwort ', 200) . 'Das Notstromaggregat NEA 50 kVA steht im Anhänger. ' . str_repeat('Blabla ', 200) . 'Ein zweites Aggregat mit 8 kVA. ' . str_repeat('Ende ', 50);
$a = stan_chat_auszuege($text, ['aggregat', 'kva'], 200, 3);
$check('Auszüge um die Treffer, zusammengelegt', count($a) === 2 && str_contains($a[0], 'NEA 50 kVA') && str_starts_with($a[0], '… ') && str_contains($a[1], '8 kVA') && str_ends_with($a[1], ' …'));
$check('ohne Treffer der Anfang', stan_chat_auszuege('Kurzer Text ohne Treffer.', ['xyz']) === ['Kurzer Text ohne Treffer.'] && stan_chat_auszuege('   ', ['x']) === []);

$quellen = [['id' => 11, 'name' => 'FGr N.pdf', 'pfad' => 'STAN/Fachgruppe N/FGr N.pdf', 'version_nummer' => 3, 'auszuege' => ['… NEA 50 kVA …']],
            ['id' => 12, 'name' => 'Anhang.pdf', 'pfad' => 'Anhang.pdf', 'version_nummer' => 3, 'auszuege' => ['8 kVA']]];
$prompt = stan_chat_prompt('Wie viele Aggregate?', $quellen, [['frage' => 'Vorherige Frage', 'antwort' => 'Vorherige Antwort']]);
$check('Prompt: nummerierte Quellen mit Ordner, Verlauf, Frage, Regeln', str_contains($prompt, '[1] FGr N.pdf (STAN/Fachgruppe N), Version 3, Auszüge') && str_contains($prompt, '[2] Anhang.pdf, Version 3, Auszüge')
    && str_contains($prompt, 'NEA 50 kVA') && str_contains($prompt, 'Frage: Vorherige Frage') && str_contains($prompt, 'Antwort: Vorherige Antwort')
    && str_ends_with($prompt, "Frage: Wie viele Aggregate?\nAntwort:") && str_contains($prompt, 'rate nicht'));
$lang = [['id' => 1, 'name' => 'a.pdf', 'pfad' => 'a.pdf', 'version_nummer' => 1, 'auszuege' => [str_repeat('x', 5000)]], ['id' => 2, 'name' => 'b.pdf', 'pfad' => 'b.pdf', 'version_nummer' => 1, 'auszuege' => [str_repeat('y', 5000)]]];
$p = stan_chat_prompt('f', $lang, [], 6000);
$check('Prompt gedeckelt', mb_strlen($p) < 7500 && str_contains($p, '[1] a.pdf') && substr_count($p, 'y') < 1200);

/* ---------- Quellen aus der Datenbank ---------- */
$fachgruppeRows = [
    ['id' => 11, 'version_id' => 3, 'pfad' => 'STAN/Fachgruppe N/FGr N.pdf', 'name' => 'FGr N.pdf', 'text' => $text, 'version_nummer' => 3, 'ki_zusammenfassung' => 'Ausstattung der Fachgruppe N', 'ki_tags' => 'Aggregat, Fachgruppe N'],
    ['id' => 12, 'version_id' => 3, 'pfad' => 'Lies mich.txt', 'name' => 'Lies mich.txt', 'text' => 'Stand 10/2026', 'version_nummer' => 3, 'ki_zusammenfassung' => null, 'ki_tags' => null],
    ['id' => 13, 'version_id' => 2, 'pfad' => 'alt.pdf', 'name' => 'Aggregat alt.pdf', 'text' => '', 'version_nummer' => 2, 'ki_zusammenfassung' => 'Altes Aggregat', 'ki_tags' => ''],
];
$GLOBALS['rows'] = $fachgruppeRows;
$q = stan_chat_quellen('Welche Aggregate hat die Fachgruppe N?', 3);
$check('Quellen: passende zuerst, Unpassendes raus, Text passt ganz ins Fenster', count($q) === 2 && $q[0]['id'] === 11 && $q[1]['id'] === 13 && $q[0]['vollstaendig'] === true
    && count($q[0]['auszuege']) === 1 && str_contains($q[0]['auszuege'][0], 'NEA 50 kVA') && !isset($q[0]['text']) && $q[1]['auszuege'] === ['Altes Aggregat'] && $q[1]['vollstaendig'] === false);
$q = stan_chat_quellen('Welche Aggregate hat die Fachgruppe N?', 3, null, 1500);
$check('kleines Fenster: Auszüge statt ganzem Text, Zusammenfassung vorangestellt, auf den Anteil gestutzt', $q[0]['vollstaendig'] === false && str_starts_with($q[0]['auszuege'][0], 'Zusammenfassung:')
    && array_sum(array_map('mb_strlen', $q[0]['auszuege'])) <= 800 && count($q) === 2);
$check('Fenster und Zahl der Quellen', stan_chat_budget() === 8192 * 3 - 2500 && stan_chat_quellen_max(6000) === 4 && stan_chat_quellen_max(60000) === 10 && stan_chat_quellen_max(200000) === 12);
$zu = stan_chat_zuschneiden([['id' => 1, 'text' => str_repeat('a', 900), 'ki' => ''], ['id' => 2, 'text' => str_repeat('b', 5000), 'ki' => 'Kurz']], ['x'], 2000);
$check('Zuschneiden: erste ganz, zweite als Auszug mit Zusammenfassung', $zu[0]['vollstaendig'] && $zu[0]['auszuege'] === [str_repeat('a', 900)] && !$zu[1]['vollstaendig']
    && str_starts_with($zu[1]['auszuege'][0], 'Zusammenfassung: Kurz') && array_sum(array_map('mb_strlen', $zu[1]['auszuege'])) <= 1100);
$check('Abfrage je Stamm, Phrase „fachgruppe n" dazu, mit Version', str_contains($GLOBALS['sql'][0][0], 'd.version_id = ?') && end($GLOBALS['sql'][0][1]) === 3 && substr_count($GLOBALS['sql'][0][0], 'd.text LIKE ?') === 3
    && $GLOBALS['sql'][0][1][0] === '%aggregat%' && in_array('%fachgruppe n%', $GLOBALS['sql'][0][1], true));
$GLOBALS['rows'] = [
    ['id' => 21, 'version_id' => 1, 'pfad' => '10-03 StAN ZTr FK-bf.pdf', 'name' => '10-03 StAN ZTr FK-bf.pdf', 'text' => 'Zugtrupp Fachzug Führung/Kommunikation (ZTr FK) ohne Mobilfunk-Karte', 'version_nummer' => 1, 'ki_zusammenfassung' => null, 'ki_tags' => null],
    ['id' => 22, 'version_id' => 1, 'pfad' => '02-01 StAN ZTr TZ-bf.pdf', 'name' => '02-01 StAN ZTr TZ-bf.pdf', 'text' => 'Zugtrupp Technischer Zug (ZTr TZ): eine SIM-Karte im Fahrzeug', 'version_nummer' => 1, 'ki_zusammenfassung' => null, 'ki_tags' => null],
    ['id' => 23, 'version_id' => 1, 'pfad' => '00-00 Vorbemerkung.pdf', 'name' => '00-00 Vorbemerkung.pdf', 'text' => 'Einsatz jetzt und jeder Zugtrupp braucht etwas', 'version_nummer' => 1, 'ki_zusammenfassung' => null, 'ki_tags' => null],
];
$q = stan_chat_quellen('Hat der ZTr TZ eine SIM-Karte?', null);
$check('Zugtrupp TZ: die richtige Datei zuerst, „Einsatz" zählt nicht als TZ', $q[0]['id'] === 22 && $q[1]['id'] === 21 && str_contains($GLOBALS['sql'][count($GLOBALS['sql']) - 1][0], 'd.text REGEXP ?'));
$GLOBALS['rows'] = $fachgruppeRows;
$vorher = count($GLOBALS['sql']);
$check('ohne Stichwörter keine Abfrage', stan_chat_quellen('und oder', null) === [] && count($GLOBALS['sql']) === $vorher);

/* ---------- Fragen ---------- */
$antwort = stan_chat_fragen('  Welche   Aggregate hat die Fachgruppe N? ', null, [['frage' => 'Hallo', 'antwort' => 'Hallo zurück']]);
$check('Antwort vom Modell samt Quellen', str_contains($antwort['antwort'], '50 kVA [1]') && $antwort['frage'] === 'Welche Aggregate hat die Fachgruppe N?' && array_column($antwort['quellen'], 'id') === [11, 13]
    && $antwort['modell'] === 'qwen2.5:7b' && $antwort['am'] !== '' && $antwort['wiederholt'] === false && $antwort['quellen'][0]['vollstaendig'] === true);
$aufruf = $GLOBALS['ki_aufrufe'][0];
$GLOBALS['ki_aufrufe'] = [];
$nochmal = stan_chat_fragen('welche aggregate hat die fachgruppe n?', null, [$antwort]);
$check('Doppelklick: dieselbe Frage kurz danach bekommt die alte Antwort ohne KI-Aufruf', $nochmal['wiederholt'] === true && $nochmal['antwort'] === $antwort['antwort'] && $GLOBALS['ki_aufrufe'] === []);
$check('Doppelt rein', stan_chat_doppelt('Frage', [['frage' => 'Frage', 'antwort' => 'A', 'am' => '2026-11-02 10:00:00']], strtotime('2026-11-02 10:02:00')) !== null
    && stan_chat_doppelt('Frage', [['frage' => 'Frage', 'antwort' => 'A', 'am' => '2026-11-02 10:00:00']], strtotime('2026-11-02 10:04:00')) === null
    && stan_chat_doppelt('Andere', [['frage' => 'Frage', 'antwort' => 'A', 'am' => '2026-11-02 10:00:00']], strtotime('2026-11-02 10:00:30')) === null && stan_chat_doppelt('x', []) === null);
stan_chat_verlauf_leeren();
stan_chat_verlauf_merken($nochmal);
$check('Wiederholtes landet nicht im Verlauf', stan_chat_verlauf() === []);
$check('Ollama bekommt Prompt ohne JSON-Zwang mit Verlauf', $aufruf[1] === '/api/generate' && $aufruf[2]['model'] === 'qwen2.5:7b' && !isset($aufruf[2]['format'])
    && str_contains($aufruf[2]['prompt'], 'Frage: Hallo') && str_contains($aufruf[2]['prompt'], '[1] FGr N.pdf'));
$GLOBALS['rows'] = [];
$GLOBALS['ki_aufrufe'] = [];
$antwort = stan_chat_fragen('Gibt es Boote?', 3, []);
$check('nichts gefunden: Hinweis ohne KI-Aufruf', str_contains($antwort['antwort'], 'nichts gefunden') && str_contains($antwort['antwort'], 'alle Versionen') && $antwort['quellen'] === [] && $GLOBALS['ki_aufrufe'] === []);
try { stan_chat_fragen('   ', null, []); $check('leere Frage abgelehnt', false); } catch (KiException $ex) { $check('leere Frage abgelehnt', str_contains($ex->getMessage(), 'Frage')); }
$GLOBALS['settings']['ki_stan_chat'] = '0';
settings_reset_cache();
$check('Schalter aus', !stan_chat_aktiv());
try { stan_chat_fragen('Was?', null, []); $check('ausgeschaltet abgelehnt', false); } catch (KiException $ex) { $check('ausgeschaltet abgelehnt', str_contains($ex->getMessage(), 'ausgeschaltet')); }
$GLOBALS['settings']['ki_stan_chat'] = '1';
$GLOBALS['settings']['ki_aktiv'] = '0';
settings_reset_cache();
$check('Hauptschalter aus = Chat aus', !stan_chat_aktiv());
$GLOBALS['settings']['ki_aktiv'] = '1';
settings_reset_cache();

/* ---------- Verlauf in der Sitzung ---------- */
stan_chat_verlauf_leeren();
for ($i = 1; $i <= 10; $i++) { stan_chat_verlauf_merken(['frage' => 'F' . $i, 'antwort' => 'A' . $i, 'quellen' => [], 'am' => '2026-11-02 10:00:00']); }
$check('Verlauf auf acht begrenzt, älteste fallen weg', count(stan_chat_verlauf()) === 8 && stan_chat_verlauf()[0]['frage'] === 'F3');
stan_chat_verlauf_leeren();
$check('Verlauf geleert', stan_chat_verlauf() === []);

/* ---------- Ansicht ---------- */
$_SERVER['REQUEST_URI'] = '/?p=stan';
$_GET = ['p' => 'stan'];
$versionen = [['id' => 3, 'nummer' => 3, 'bemerkung' => '', 'created_at' => '2026-10-01 10:00:00', 'hochgeladen_von' => 'Anna', 'orig_name' => 'stan.zip']];
$basis = ['title' => 'STAN', 'versionen' => $versionen, 'aktuell' => $versionen[0], 'gezeigt' => $versionen[0], 'dateien' => [], 'darf' => false, 'maxMb' => 10];
$html = render_partial('stan', $basis + ['chatAktiv' => true, 'chatVerlauf' => [['frage' => 'Wie viele Aggregate?', 'antwort' => 'Zwei [1].', 'quellen' => [['id' => 11, 'name' => 'FGr N.pdf', 'pfad' => 'STAN/Fachgruppe N/FGr N.pdf', 'version_nummer' => 3]], 'modell' => 'qwen2.5:7b', 'am' => '2026-11-02 10:00:00']]]);
$check('Chat-Karte mit Verlauf als Sprechblasen, Quelle als Link, Formular mit Version und JS-Ziel, Verlauf leeren', str_contains($html, 'id="chat"') && str_contains($html, 'class="chat__frage">Wie viele Aggregate?')
    && str_contains($html, 'Zwei [1].') && str_contains($html, 'p=stan_file&amp;id=11') && str_contains($html, 'name="frage"') && str_contains($html, 'value="chat_leeren" formnovalidate data-chat-leeren>')
    && str_contains($html, 'name="version" value="3"') && str_contains($html, 'data-stan-chat="') && str_contains($html, 'p=stan_chat') && str_contains($html, 'in Version 3') && str_contains($html, 'data-chat-verlauf>'));
$html = render_partial('stan', $basis + ['chatAktiv' => true, 'chatVerlauf' => [], 'alleVersionen' => true]);
$check('Chat-Karte leer: Verlauf leeren versteckt, alle Versionen', str_contains($html, 'data-chat-leeren hidden') && str_contains($html, 'name="alle" value="1"') && str_contains($html, 'data-chat-verlauf hidden') && str_contains($html, 'in allen Versionen'));
$check('Route, Endpunkt, Skript und Stil', str_contains((string)file_get_contents($app . '/public/index.php'), "'stan_chat'") && is_file($app . '/src/pages/stan_chat.php')
    && str_contains((string)file_get_contents($app . '/public/assets/js/app.js'), 'form[data-stan-chat]') && str_contains((string)file_get_contents($app . '/public/assets/css/app.css'), '@keyframes chat-tippt'));
$html = render_partial('stan', $basis + ['chatAktiv' => false]);
$check('ohne KI keine Chat-Karte', !str_contains($html, 'id="chat"'));
$check('Einstellung und Seite', str_contains((string)file_get_contents($app . '/sql/seed.sql'), "'ki_stan_chat'") && str_contains((string)file_get_contents($app . '/src/pages/stan.php'), 'stan_chat_fragen'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
