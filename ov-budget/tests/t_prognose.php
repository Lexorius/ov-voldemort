<?php
declare(strict_types=1);
/*
 * Hochrechnung: Fenster und Monate, Durchschnitt und Schwankung je Kategorie,
 * Hochrechnung mit auto/fest/aus, Zählerrechnung, Formularwerte, Ansicht.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'budget_modul_name' => 'Budget', 'budget_rundung' => '0'];
$GLOBALS['execs'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'role' => 'leitung']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'expenses', 'prognose'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Fenster und Monate ---------- */
$f = prognose_fenster('2026-10-18');
$check('zwölf volle Monate vor Oktober 2026', count($f) === 12 && $f[0] === '2025-10' && end($f) === '2026-09');
$check('Fenster über den Jahreswechsel', prognose_fenster('2026-01-05')[0] === '2025-01' && end(prognose_fenster('2026-01-05')) === '2025-12');
$m = prognose_monate(2026, '2026-10-18');
$check('Monate: 9 volle plus 17/31 des Oktobers um, Rest entsprechend', abs($m['vergangen'] - 9.548) < 0.001 && abs($m['rest'] - 2.452) < 0.001);
$check('vergangenes Jahr 12/0, künftiges 0/12, Jahresanfang 0/12', prognose_monate(2025, '2026-10-18') === ['vergangen' => 12.0, 'rest' => 0.0] && prognose_monate(2027, '2026-10-18') === ['vergangen' => 0.0, 'rest' => 12.0]
    && prognose_monate(2026, '2026-01-01') === ['vergangen' => 0.0, 'rest' => 12.0]);

/* ---------- Statistik ---------- */
$telefon = array_fill_keys($f, 45.0);
$st = prognose_statistik($telefon, $f);
$check('Telefon: jeden Monat 45 € = regelmäßig', $st['durchschnitt'] === 45.0 && $st['monate'] === 12 && $st['monate_mit'] === 12 && $st['schwankung'] === 0.0 && prognose_schwankung_text($st) === 'regelmäßig');
$tanken = ['2025-10' => 180.0, '2025-11' => 0.0, '2025-12' => 240.0, '2026-01' => 60.0, '2026-02' => 200.0, '2026-03' => 150.0, '2026-04' => 0.0, '2026-05' => 310.0, '2026-06' => 90.0, '2026-07' => 220.0, '2026-08' => 140.0, '2026-09' => 170.0];
$st = prognose_statistik($tanken, $f);
$check('Tanken: Monate ohne Buchung zählen als 0, Durchschnitt über alle zwölf', $st['monate'] === 12 && $st['monate_mit'] === 10 && abs($st['durchschnitt'] - 146.67) < 0.01 && prognose_schwankung_text($st) === 'schwankend');
$st = prognose_statistik(['2026-07' => 120.0, '2026-09' => 130.0], $f);
$check('Kategorie erst seit Juli: Zählung ab dem ersten Monat mit Buchung', $st['monate'] === 3 && $st['monate_mit'] === 2 && abs($st['durchschnitt'] - 83.33) < 0.01);
$st = prognose_statistik(['2026-03' => 900.0], $f);
$check('einmalige Buchung: zu wenig Daten', prognose_schwankung_text($st) === 'zu wenig Daten' && $st['monate_mit'] === 1);
$check('nichts gebucht', prognose_statistik([], $f) === ['summe' => 0.0, 'durchschnitt' => 0.0, 'monate' => 0, 'monate_mit' => 0, 'schwankung' => 0.0]);
$versicherung = ['2025-11' => 1200.0];
$check('Jahresbeitrag im Fenster: eine Buchung = zu wenig Daten, Durchschnitt über elf Monate', prognose_schwankung_text(prognose_statistik($versicherung, $f)) === 'zu wenig Daten' && abs(prognose_statistik($versicherung, $f)['durchschnitt'] - 109.09) < 0.01);

/* ---------- Hochrechnung je Kategorie ---------- */
$kategorien = [['id' => 1, 'label' => 'Telefon', 'color' => '#111'], ['id' => 2, 'label' => 'Tanken', 'color' => '#222'], ['id' => 3, 'label' => 'Anschaffungen', 'color' => '#333'], ['id' => 4, 'label' => 'Leer', 'color' => '#444']];
$monatswerte = [1 => $telefon, 2 => $tanken, 3 => ['2026-02' => 2500.0], 0 => ['2026-05' => 30.0]];
$bisher = [1 => 405.0, 2 => 1340.0, 3 => 2500.0, 0 => 30.0];
$einst = [1 => ['art' => 'fest', 'betrag_monat' => 49.9, 'notiz' => 'neuer Vertrag ab Oktober'], 3 => ['art' => 'aus', 'betrag_monat' => 0, 'notiz' => '']];
$k = prognose_kategorien($kategorien, $monatswerte, $bisher, $einst, 2026, '2026-10-18');
$je = array_column($k['zeilen'], null, 'id');
$check('Telefon fest: 49,90 × Rest', $je[1]['art'] === 'fest' && $je[1]['je_monat'] === 49.9 && abs($je[1]['rest'] - 49.9 * 2.452) < 0.05 && abs($je[1]['hochrechnung'] - (405 + 49.9 * 2.452)) < 0.05 && $je[1]['notiz'] === 'neuer Vertrag ab Oktober');
$check('Tanken auto: Durchschnitt × Rest, Empfehlung bleibt aus (schwankend)', $je[2]['art'] === 'auto' && abs($je[2]['je_monat'] - 146.67) < 0.01 && abs($je[2]['hochrechnung'] - (1340 + 146.67 * 2.452)) < 0.1 && $je[2]['empfehlung'] === '');
$check('Anschaffungen aus: nur das Bisherige', $je[3]['art'] === 'aus' && $je[3]['rest'] === 0.0 && $je[3]['hochrechnung'] === 2500.0);
$check('ohne Kategorie ergänzt, leere Kategorie weggelassen', $je[0]['label'] === 'Ohne Kategorie' && !isset($je[4]) && count($k['zeilen']) === 4);
$check('Summe und Reihenfolge nach Hochrechnung', abs($k['summe']['bisher'] - 4275.0) < 0.01 && $k['zeilen'][0]['id'] === 3 && abs($k['summe']['hochrechnung'] - array_sum(array_column($k['zeilen'], 'hochrechnung'))) < 0.01);
$k2 = prognose_kategorien([['id' => 1, 'label' => 'Telefon', 'color' => '']], [1 => $telefon], [1 => 540.0], [], 2026, '2026-10-18');
$check('regelmäßig und auto: Empfehlung „fest"', $k2['zeilen'][0]['empfehlung'] === 'fest');
$k3 = prognose_kategorien($kategorien, $monatswerte, $bisher, $einst, 2025, '2026-10-18');
$check('vergangenes Jahr: Hochrechnung = bisher', $k3['monate']['rest'] === 0.0 && array_column($k3['zeilen'], 'rest') === [0.0, 0.0, 0.0, 0.0]);

/* ---------- Zähler ---------- */
$kosten = [1 => 120.0, 2 => 110.0, 3 => 100.0, 4 => 80.0, 5 => 70.0, 6 => 60.0, 7 => 60.0, 8 => 65.0, 9 => 75.0, 10 => null, 11 => null, 12 => null];
$zr = prognose_zaehler_rechnen($kosten, 2026, '2026-10-18');
$check('Zähler: neun Monate mit Kosten, drei fehlen aus dem Durchschnitt', $zr['bisher'] === 740.0 && $zr['monate_mit'] === 9 && abs($zr['durchschnitt'] - 82.22) < 0.01 && abs($zr['rest'] - 246.67) < 0.01 && abs($zr['hochrechnung'] - 986.67) < 0.01);
$check('Zähler im vergangenen Jahr: nichts dazu', prognose_zaehler_rechnen($kosten, 2025, '2026-10-18')['rest'] === 0.0 && prognose_zaehler_rechnen(array_fill(1, 12, null), 2026, '2026-10-18') === ['bisher' => 0.0, 'monate_mit' => 0, 'durchschnitt' => 0.0, 'rest' => 0.0, 'hochrechnung' => 0.0]);

/* ---------- Formular ---------- */
$e = prognose_aus_post(['art' => ['1' => 'fest', '2' => 'quatsch', '3' => 'aus'], 'betrag' => ['1' => '49,90', '2' => '1.250,50', '3' => ''], 'notiz' => ['1' => ' neuer Vertrag ']]);
$check('Formularwerte: Art geprüft, deutsche Beträge gelesen, leer = 0', $e[1] === ['art' => 'fest', 'betrag' => 49.9, 'notiz' => ' neuer Vertrag '] && $e[2]['art'] === 'auto' && $e[2]['betrag'] === 1250.5 && $e[3]['betrag'] === 0.0);
$GLOBALS['execs'] = [];
prognose_einstellung_speichern(2026, 1, 'fest', 49.9, ' neuer Vertrag ');
$check('speichern: eine Abfrage mit geprüften Werten', count($GLOBALS['execs']) === 1 && str_contains($GLOBALS['execs'][0][0], 'INSERT INTO budget_prognosen') && $GLOBALS['execs'][0][1] === [2026, 1, 'fest', 49.9, 'neuer Vertrag']);

/* ---------- Ansicht ---------- */
$zahlen = ['verfuegbar' => 20000.0, 'ausgaben' => 9000.0, 'geplant' => 1500.0];
$html = render_partial('budget_prognose', ['jahr' => 2026, 'jahre' => [2026, 2025], 'darf' => true, 'prognose' => [
    'jahr' => 2026, 'heute' => '2026-10-18', 'kategorien' => $k, 'zahlen' => $zahlen,
    'zaehler' => ['zeilen' => [$zr + ['id' => 5, 'name' => 'Hauptanschluss', 'art' => 'strom', 'erloes' => false, 'ohne_tarif' => 1]], 'summe' => 986.67, 'erloes' => 0.0],
    'hochrechnung' => 9800.0, 'frei' => 10200.0, 'frei_mit_planung' => 8700.0]]);
$check('Ansicht: Kennzahlen, Kategorien mit Auswahl, Empfehlung, Zähler, Hinweis auf fehlenden Tarif', str_contains($html, 'Hochrechnung Jahresende') && str_contains($html, 'name="art[1]"') && str_contains($html, 'value="fest" selected') && str_contains($html, 'name="betrag[2]"')
    && str_contains($html, '>regelmäßig<') && str_contains($html, '>schwankend<') && str_contains($html, 'Hauptanschluss') && str_contains($html, '1 Monat(e) ohne Tarif') && str_contains($html, 'nach geplanten Vorhaben') && str_contains($html, 'Speichern'));
$html = render_partial('budget_prognose', ['jahr' => 2025, 'jahre' => [2026, 2025], 'darf' => false, 'prognose' => [
    'jahr' => 2025, 'heute' => '2026-10-18', 'kategorien' => $k3, 'zahlen' => $zahlen, 'zaehler' => ['zeilen' => [], 'summe' => 0.0, 'erloes' => 0.0], 'hochrechnung' => 4275.0, 'frei' => 15725.0, 'frei_mit_planung' => 14225.0]]);
$check('Ansicht ohne Recht und vergangenes Jahr: keine Felder, Hinweis', !str_contains($html, '<select') && !str_contains($html, 'Speichern') && str_contains($html, 'ist um') && str_contains($html, 'Kein Zähler mit Tarif'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
