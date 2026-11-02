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
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'ki', 'stan', 'stan_chat'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- reine Helfer ---------- */
$check('Stichwörter ohne Füllwörter', stan_chat_stichworte('Wie viele Notstromaggregate hat die Fachgruppe N und welche Leistung in kVA?') === ['notstromaggregate', 'fachgruppe', 'leistung', 'kva']);
$check('Zahlen und Bindestriche', stan_chat_stichworte('Gibt es 50 kVA im GKW-1?') === ['50', 'kva', 'gkw-1'] && stan_chat_stichworte('und oder ist') === []);
$check('höchstens acht', count(stan_chat_stichworte('alpha beta gamma delta epsilon zeta eta theta iota kappa')) === 8);
$check('Stamm', stan_chat_stamm('Aggregate') === 'aggregat' && stan_chat_stamm('Notstromaggregate') === 'notstromaggregat' && stan_chat_stamm('Leistungen') === 'leist'
    && stan_chat_stamm('kVA') === 'kva' && stan_chat_stamm('12345') === '12345' && stan_chat_stamm('Fachgruppe') === 'fachgrupp');
$check('Bewertung: mehr verschiedene Stämme zählen mehr', stan_chat_bewerten('Aggregat Aggregat Aggregat', ['aggregat', 'leist']) === 6
    && stan_chat_bewerten('Aggregat mit Leistung', ['aggregat', 'leist']) === 8 && stan_chat_bewerten('nichts', ['aggregat']) === 0);
$text = str_repeat('Füllwort ', 200) . 'Das Notstromaggregat NEA 50 kVA steht im Anhänger. ' . str_repeat('Blabla ', 200) . 'Ein zweites Aggregat mit 8 kVA. ' . str_repeat('Ende ', 50);
$a = stan_chat_auszuege($text, ['aggregat', 'kva'], 200, 3);
$check('Auszüge um die Treffer, zusammengelegt', count($a) === 2 && str_contains($a[0], 'NEA 50 kVA') && str_starts_with($a[0], '… ') && str_contains($a[1], '8 kVA') && str_ends_with($a[1], ' …'));
$check('ohne Treffer der Anfang', stan_chat_auszuege('Kurzer Text ohne Treffer.', ['xyz']) === ['Kurzer Text ohne Treffer.'] && stan_chat_auszuege('   ', ['x']) === []);

$quellen = [['id' => 11, 'name' => 'FGr N.pdf', 'pfad' => 'STAN/Fachgruppe N/FGr N.pdf', 'version_nummer' => 3, 'auszuege' => ['… NEA 50 kVA …']],
            ['id' => 12, 'name' => 'Anhang.pdf', 'pfad' => 'Anhang.pdf', 'version_nummer' => 3, 'auszuege' => ['8 kVA']]];
$prompt = stan_chat_prompt('Wie viele Aggregate?', $quellen, [['frage' => 'Vorherige Frage', 'antwort' => 'Vorherige Antwort']]);
$check('Prompt: nummerierte Quellen mit Ordner, Verlauf, Frage, Regeln', str_contains($prompt, '[1] FGr N.pdf (STAN/Fachgruppe N), Version 3') && str_contains($prompt, '[2] Anhang.pdf, Version 3')
    && str_contains($prompt, 'NEA 50 kVA') && str_contains($prompt, 'Frage: Vorherige Frage') && str_contains($prompt, 'Antwort: Vorherige Antwort')
    && str_ends_with($prompt, "Frage: Wie viele Aggregate?\nAntwort:") && str_contains($prompt, 'rate nicht'));
$lang = [['id' => 1, 'name' => 'a.pdf', 'pfad' => 'a.pdf', 'version_nummer' => 1, 'auszuege' => [str_repeat('x', 5000)]], ['id' => 2, 'name' => 'b.pdf', 'pfad' => 'b.pdf', 'version_nummer' => 1, 'auszuege' => [str_repeat('y', 5000)]]];
$p = stan_chat_prompt('f', $lang, [], 6000);
$check('Prompt gedeckelt', mb_strlen($p) < 7500 && str_contains($p, '[1] a.pdf') && substr_count($p, 'y') < 1200);

/* ---------- Quellen aus der Datenbank ---------- */
$GLOBALS['rows'] = [
    ['id' => 11, 'version_id' => 3, 'pfad' => 'STAN/Fachgruppe N/FGr N.pdf', 'name' => 'FGr N.pdf', 'text' => $text, 'version_nummer' => 3, 'ki_zusammenfassung' => 'Ausstattung der Fachgruppe N', 'ki_tags' => 'Aggregat, Fachgruppe N'],
    ['id' => 12, 'version_id' => 3, 'pfad' => 'Lies mich.txt', 'name' => 'Lies mich.txt', 'text' => 'Stand 10/2026', 'version_nummer' => 3, 'ki_zusammenfassung' => null, 'ki_tags' => null],
    ['id' => 13, 'version_id' => 2, 'pfad' => 'alt.pdf', 'name' => 'Aggregat alt.pdf', 'text' => '', 'version_nummer' => 2, 'ki_zusammenfassung' => 'Altes Aggregat', 'ki_tags' => ''],
];
$q = stan_chat_quellen('Welche Aggregate hat die Fachgruppe N?', 3);
$check('Quellen: passende zuerst, Unpassendes raus, Zusammenfassung vorangestellt', count($q) === 2 && $q[0]['id'] === 11 && $q[1]['id'] === 13 && str_starts_with($q[0]['auszuege'][0], 'Zusammenfassung:')
    && count($q[0]['auszuege']) >= 2 && $q[1]['auszuege'] === ['Altes Aggregat']);
$check('Abfrage je Stamm mit Version', str_contains($GLOBALS['sql'][0][0], 'd.version_id = ?') && end($GLOBALS['sql'][0][1]) === 3 && substr_count($GLOBALS['sql'][0][0], 'd.text LIKE ?') === 2
    && $GLOBALS['sql'][0][1][0] === '%aggregat%');
$check('ohne Stichwörter keine Abfrage', stan_chat_quellen('und oder', null) === [] && count($GLOBALS['sql']) === 1);

/* ---------- Fragen ---------- */
$antwort = stan_chat_fragen('  Welche   Aggregate hat die Fachgruppe N? ', null, [['frage' => 'Hallo', 'antwort' => 'Hallo zurück']]);
$check('Antwort vom Modell samt Quellen', str_contains($antwort['antwort'], '50 kVA [1]') && $antwort['frage'] === 'Welche Aggregate hat die Fachgruppe N?' && array_column($antwort['quellen'], 'id') === [11, 13]
    && $antwort['modell'] === 'qwen2.5:7b' && $antwort['am'] !== '');
$aufruf = $GLOBALS['ki_aufrufe'][0];
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
$check('Chat-Karte mit Verlauf, Quelle als Link, Formular mit Version, Verlauf leeren', str_contains($html, 'id="chat"') && str_contains($html, 'Wie viele Aggregate?') && str_contains($html, 'Zwei [1].')
    && str_contains($html, 'p=stan_file&amp;id=11') && str_contains($html, 'name="frage"') && str_contains($html, 'value="chat_leeren"') && str_contains($html, 'version=3') && str_contains($html, 'in Version 3'));
$html = render_partial('stan', $basis + ['chatAktiv' => true, 'chatVerlauf' => [], 'alleVersionen' => true]);
$check('Chat-Karte leer: kein Verlauf leeren, alle Versionen', !str_contains($html, 'value="chat_leeren"') && str_contains($html, 'alle=1') && str_contains($html, 'in allen Versionen'));
$html = render_partial('stan', $basis + ['chatAktiv' => false]);
$check('ohne KI keine Chat-Karte', !str_contains($html, 'id="chat"'));
$check('Einstellung und Seite', str_contains((string)file_get_contents($app . '/sql/seed.sql'), "'ki_stan_chat'") && str_contains((string)file_get_contents($app . '/src/pages/stan.php'), 'stan_chat_fragen'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
