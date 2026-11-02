<?php
declare(strict_types=1);
/* Bedeutungssuche in der STAN: Absätze teilen, Vektoren packen, Index nachholen über die Ollama-Attrappe, Suche, Verbund mit dem Chat */
session_start();
$GLOBALS['settings'] = ['ki_aktiv' => '1', 'ki_ollama_url' => 'http://ollama:11434', 'ki_modell' => 'qwen2.5:7b', 'ki_stan' => '1', 'ki_stan_chat' => '1',
    'ki_embedding_modell' => 'nomic-embed-text', 'ki_max_zeichen' => '12000', 'stan_modul_name' => 'STAN', 'upload_max_mb' => '10', 'stan_max_mb' => '1'];
$GLOBALS['absaetze'] = [];       // Tabelle stan_absaetze als Liste
$GLOBALS['dateien_ohne'] = [];   // Dateien mit Text ohne Absätze
$GLOBALS['dateien'] = [];        // Datei-Zeilen für stan_chat_dateien_laden
$GLOBALS['state'] = [];
$GLOBALS['ki_aufrufe'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['embed_antwort'] = null;

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0]; }
        return $r;
    }
    if (str_contains($sql, 'NOT EXISTS (SELECT 1 FROM stan_absaetze')) { return $GLOBALS['dateien_ohne']; }
    if (str_contains($sql, 'FROM stan_absaetze WHERE vektor IS NULL')) { return array_values(array_filter($GLOBALS['absaetze'], static fn($a) => $a['vektor'] === null)); }
    if (str_contains($sql, 'FROM stan_absaetze WHERE id IN')) { return array_values(array_filter($GLOBALS['absaetze'], static fn($a) => in_array($a['id'], $p, true))); }
    if (str_contains($sql, 'FROM stan_absaetze a')) {
        $r = array_values(array_filter($GLOBALS['absaetze'], static fn($a) => $a['vektor'] !== null));
        if (str_contains($sql, 'd.version_id = ?')) { $r = array_values(array_filter($r, static fn($a) => ($a['version_id'] ?? 1) === $p[0])); }
        return $r;
    }
    if (str_contains($sql, 'FROM stan_dateien d') && str_contains($sql, 'd.id IN')) { return array_values(array_filter($GLOBALS['dateien'], static fn($d) => in_array($d['id'], $p, true))); }
    if (str_contains($sql, 'FROM stan_dateien d')) { $GLOBALS['stichwort_sql'] = $sql; return $GLOBALS['treffer'] ?? []; }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'svalue FROM settings')) { return $GLOBALS['state'][$p[0]] ?? null; }
    if (str_contains($sql, 'COUNT(*) FROM stan_absaetze WHERE vektor IS NULL')) { return count(array_filter($GLOBALS['absaetze'], static fn($a) => $a['vektor'] === null)); }
    if (str_contains($sql, 'COUNT(*) FROM stan_absaetze')) { return count($GLOBALS['absaetze']); }
    if (str_contains($sql, 'NOT EXISTS')) { return count($GLOBALS['dateien_ohne']); }
    return $d;
}
function db_exec(string $sql, array $p = []): int {
    $GLOBALS['execs'][] = [$sql, $p];
    if (str_contains($sql, 'INSERT INTO settings')) { $GLOBALS['state'][$p[0]] = $p[1]; }
    if (str_contains($sql, 'DELETE FROM stan_absaetze WHERE datei_id')) { $GLOBALS['absaetze'] = array_values(array_filter($GLOBALS['absaetze'], static fn($a) => $a['datei_id'] !== $p[0])); }
    return 1;
}
function db_insert(string $t, array $d): int { $id = 100 + count($GLOBALS['absaetze']); $GLOBALS['absaetze'][] = $d + ['id' => $id, 'vektor' => null, 'version_id' => 1]; return $id; }
function db_update(string $t, array $d, string $w, array $p): int {
    foreach ($GLOBALS['absaetze'] as &$a) { if ($a['id'] === $p[0]) { $a = array_merge($a, $d); } }
    unset($a);
    return 1;
}
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'user']; }
$GLOBALS['ki_http_attrappe'] = static function (string $methode, string $pfad, ?array $daten): array {
    $GLOBALS['ki_aufrufe'][] = [$methode, $pfad, $daten];
    if ($pfad === '/api/embed') {
        if ($GLOBALS['embed_antwort'] instanceof Throwable) { throw $GLOBALS['embed_antwort']; }
        // Spielzeug-Vektoren: Richtung nach Inhalt – „sim" → (1,0,0), „aggregat" → (0,1,0), sonst (0,0,1)
        $out = [];
        foreach ($daten['input'] as $t) {
            $t = mb_strtolower($t);
            $out[] = str_contains($t, 'sim') || str_contains($t, 'teilnehmerkarte') ? [0.9, 0.1, 0.0] : (str_contains($t, 'aggregat') ? [0.1, 0.9, 0.0] : [0.0, 0.1, 0.9]);
        }
        return ['embeddings' => $out];
    }
    return ['response' => 'Antwort [1].'];
};

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'uploads', 'ki', 'stan', 'stan_chat', 'stan_embed'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Absätze teilen ---------- */
$a = stan_absaetze_teilen("Erster Absatz.\n\nZweiter Absatz mit mehr Text.\n\n\nDritter.", 1000, 0);
$check('Leerzeilen trennen, kleine Stücke werden gebündelt', $a === ["Erster Absatz.\nZweiter Absatz mit mehr Text.\nDritter."]);
$lang = str_repeat('Ein Satz mit Inhalt. ', 200);   // 4200 Zeichen
$a = stan_absaetze_teilen($lang, 1000, 0);
$check('langer Block an Satzenden geteilt', count($a) === 5 && max(array_map('mb_strlen', $a)) <= 1000 && str_ends_with($a[0], 'Inhalt.') && implode(' ', $a) === trim($lang));
$a = stan_absaetze_teilen(str_repeat('x', 2500), 1000, 0);
$check('ohne Satzenden hart geteilt', count($a) === 3 && mb_strlen($a[0]) === 1000);
$a = stan_absaetze_teilen("A " . str_repeat('aaa ', 300) . "\n\nB " . str_repeat('bbb ', 300), 1000, 50);
$check('Überlappung: Anfang des zweiten Absatzes wiederholt das Ende des ersten', str_starts_with($a[1], '… ') && str_contains(mb_substr($a[1], 0, 60), 'aaa'));
$check('leer bleibt leer', stan_absaetze_teilen("  \n\n ") === []);

/* ---------- Vektoren ---------- */
$v = stan_vektor_entpacken(stan_vektor_packen([3.0, 4.0]));
$check('packen normiert auf Länge 1', abs($v[0] - 0.6) < 0.0001 && abs($v[1] - 0.8) < 0.0001 && stan_vektor_entpacken('') === []);
$check('Skalarprodukt', abs(stan_vektor_punkt([1.0, 0.0], [1.0, 0.0]) - 1.0) < 0.0001 && abs(stan_vektor_punkt([1.0, 0.0], [0.0, 1.0])) < 0.0001 && abs(stan_vektor_punkt([0.6, 0.8], [0.6, 0.8]) - 1.0) < 0.001);
$check('Nullvektor bleibt heil', stan_vektor_entpacken(stan_vektor_packen([0.0, 0.0])) === [0.0, 0.0]);

/* ---------- Index nachholen ---------- */
$check('aktiv mit Modell', stan_embed_aktiv() && stan_embed_modell() === 'nomic-embed-text');
$GLOBALS['dateien_ohne'] = [['id' => 7, 'text' => "Zugtrupp Technischer Zug: eine SIM-Karte im Fahrzeug.\n\nAußerdem ein Notstromaggregat 8 kVA."], ['id' => 8, 'text' => 'Vorbemerkung ohne besonderen Inhalt.']];
$r = stan_embed_nachholen();
$check('Dateien geteilt und Vektoren geholt', $r['dateien'] === 2 && $r['absaetze'] === 2 && $r['fehler'] === '' && count($GLOBALS['absaetze']) === 2
    && $GLOBALS['absaetze'][0]['datei_id'] === 7 && $GLOBALS['absaetze'][0]['nr'] === 1 && $GLOBALS['absaetze'][0]['vektor'] !== null && $GLOBALS['absaetze'][0]['modell'] === 'nomic-embed-text');
$embed = $GLOBALS['ki_aufrufe'][0];
$check('Ein Aufruf an /api/embed mit allen Texten', $embed[1] === '/api/embed' && $embed[2]['model'] === 'nomic-embed-text' && count($embed[2]['input']) === 2 && $embed[2]['truncate'] === true);
$check('Stand', stan_embed_stand() === ['absaetze' => 2, 'offen' => 0, 'dateien_ohne' => 2, 'fehler' => '']);
$GLOBALS['dateien_ohne'] = [];
$GLOBALS['ki_aufrufe'] = [];
$check('nichts offen: kein Aufruf', stan_embed_nachholen() === ['dateien' => 0, 'absaetze' => 0, 'fehler' => '', 'ruhe' => false] && $GLOBALS['ki_aufrufe'] === []);

$GLOBALS['absaetze'][] = ['id' => 300, 'datei_id' => 9, 'nr' => 1, 'text' => 'offen', 'vektor' => null, 'version_id' => 1];
$GLOBALS['embed_antwort'] = new KiException('Ollama meldet: model "nomic-embed-text" not found');
$r = stan_embed_nachholen();
$check('Modell fehlt: Fehler gemerkt, Absatz bleibt offen', str_contains($r['fehler'], 'not found') && !$r['ruhe'] && stan_embed_stand()['fehler'] === $r['fehler'] && stan_embed_stand()['offen'] === 1);
$GLOBALS['embed_antwort'] = new KiNichtErreichbar('Ollama ist nicht erreichbar');
$r = stan_embed_nachholen();
$check('nicht erreichbar: Ruhe', $r['ruhe'] && (int)$GLOBALS['state']['ki_ruhe_bis'] > time());
$GLOBALS['state']['ki_ruhe_bis'] = '0';
$GLOBALS['embed_antwort'] = null;
$r = stan_embed_nachholen();
$check('danach geht es weiter und der Fehler ist weg', $r['absaetze'] === 1 && stan_embed_stand()['fehler'] === '' && stan_embed_stand()['offen'] === 0);

/* ---------- Suche ---------- */
$t = stan_embed_suche('Welche SIM-Karte hat der Zugtrupp?', null, 2);
$check('nächste Absätze zuerst', count($t) === 2 && $t[0]['datei_id'] === 7 && $t[0]['nr'] === 1 && str_contains($t[0]['text'], 'SIM-Karte') && $t[0]['score'] > $t[1]['score'] && $t[0]['score'] > 0.9);
$check('nach Version eingeschränkt', stan_embed_suche('SIM', 2, 5) === []);

/* ---------- Verbund mit dem Chat ---------- */
$GLOBALS['dateien'] = [
    // langer Text, damit die Datei bei kleinem Fenster nicht ganz hineinpasst
    ['id' => 7, 'version_id' => 1, 'pfad' => '02-01 StAN ZTr TZ-bf.pdf', 'name' => '02-01 StAN ZTr TZ-bf.pdf', 'text' => "Zugtrupp Technischer Zug: eine SIM-Karte im Fahrzeug.\n\nAußerdem ein Notstromaggregat 8 kVA." . str_repeat(' Weitere Ausstattung folgt.', 150), 'version_nummer' => 1, 'ki_zusammenfassung' => null, 'ki_tags' => null],
    ['id' => 8, 'version_id' => 1, 'pfad' => '00-00 Vorbemerkung.pdf', 'name' => '00-00 Vorbemerkung.pdf', 'text' => 'Vorbemerkung ohne besonderen Inhalt.', 'version_nummer' => 1, 'ki_zusammenfassung' => null, 'ki_tags' => null],
];
$GLOBALS['treffer'] = [];   // Stichwortsuche findet nichts („Teilnehmerkarte" steht nirgends)
$q = stan_chat_quellen('Welche Mobilfunk-Teilnehmerkarte hat der Zugtrupp?', null, null, 400);
$check('Bedeutungssuche findet die Datei ohne gleiches Wort, Absätze als Quelle', count($q) >= 1 && $q[0]['id'] === 7 && $q[0]['art'] === 'absaetze' && str_contains($q[0]['auszuege'][0], 'SIM-Karte'));
$q = stan_chat_quellen('Welche Mobilfunk-Teilnehmerkarte hat der Zugtrupp?', null, null, 20000);
$check('großes Fenster: Datei ganz', $q[0]['art'] === 'vollstaendig');
$GLOBALS['embed_antwort'] = new KiException('Ollama meldet: model not found');
$GLOBALS['treffer'] = [$GLOBALS['dateien'][0]];
$q = stan_chat_quellen('Welche SIM-Karte hat der Zugtrupp?', null, null, 400);
$check('Bedeutungssuche gestört: Stichwörter tragen weiter', count($q) === 1 && $q[0]['id'] === 7 && $q[0]['art'] === 'auszuege');
$GLOBALS['embed_antwort'] = null;
$antwort = stan_chat_fragen('Welche Mobilfunk-Teilnehmerkarte hat der Zugtrupp?', null, []);
$check('Antwort mit Quellenart', $antwort['quellen'][0]['art'] === 'vollstaendig' && $antwort['antwort'] === 'Antwort [1].');

/* ---------- aus ---------- */
$GLOBALS['settings']['ki_embedding_modell'] = '';
settings_reset_cache();
$check('ohne Modell aus', !stan_embed_aktiv() && stan_embed_nachholen() === ['dateien' => 0, 'absaetze' => 0, 'fehler' => '', 'ruhe' => false]);

/* ---------- Einbau ---------- */
$check('Schema, Migration, Abruf, Einstellung', str_contains((string)file_get_contents($app . '/sql/schema.sql'), 'CREATE TABLE IF NOT EXISTS stan_absaetze')
    && str_contains((string)file_get_contents($app . '/src/cli/migrate.php'), "\$merken('097_stan_absaetze')")
    && str_contains((string)file_get_contents($app . '/public/cron.php'), 'stan_embed_nachholen') && str_contains((string)file_get_contents($app . '/sql/seed.sql'), "'ki_embedding_modell'")
    && str_contains((string)file_get_contents($app . '/src/lib/stan.php'), 'DELETE FROM stan_absaetze WHERE datei_id'));
$_SERVER['REQUEST_URI'] = '/?p=stan';
$_GET = ['p' => 'stan'];
$versionen = [['id' => 3, 'nummer' => 3, 'bemerkung' => '', 'created_at' => '2026-10-01 10:00:00', 'hochgeladen_von' => 'Anna', 'orig_name' => 'stan.zip', 'anzahl' => 0]];
$basis = ['title' => 'STAN', 'versionen' => $versionen, 'aktuell' => $versionen[0], 'gezeigt' => $versionen[0], 'dateien' => [], 'darf' => false, 'maxMb' => 10, 'chatAktiv' => true, 'chatVerlauf' => []];
$html = render_partial('stan', $basis + ['embedStand' => ['absaetze' => 1300, 'offen' => 66, 'dateien_ohne' => 2, 'fehler' => '']]);
$check('Karte zeigt den Stand', str_contains($html, '1.234 Absätze eingelesen') && str_contains($html, '66 warten') && str_contains($html, '2 Datei(en)'));
$html = render_partial('stan', $basis + ['embedStand' => ['absaetze' => 0, 'offen' => 0, 'dateien_ohne' => 0, 'fehler' => 'Ollama meldet: model not found']]);
$check('Karte zeigt die Störung', str_contains($html, 'Bedeutungssuche gestört') && str_contains($html, 'model not found'));
$html = render_partial('stan', $basis + ['embedStand' => null]);
$check('Karte ohne Modell', str_contains($html, 'Bedeutungssuche aus'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
