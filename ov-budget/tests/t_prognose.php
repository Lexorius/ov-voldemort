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
$check('Tanken auto: Jahresdurchschnitt (1340 / 9,548 Monate) × Rest, Empfehlung bleibt aus (schwankend)', $je[2]['art'] === 'auto' && $je[2]['basis'] === 'jahr' && abs($je[2]['je_monat'] - 140.34) < 0.01 && abs($je[2]['hochrechnung'] - (1340 + 1340 / 9.548 * 2.452)) < 0.1 && $je[2]['empfehlung'] === '');
$check('ohne Kategorie mit einer Buchung: nicht hochgerechnet', $je[0]['basis'] === 'keine' && $je[0]['rest'] === 0.0 && $je[0]['hochrechnung'] === 30.0);
$alles = ['2026-09' => 32611.57];
$kImport = prognose_kategorien([['id' => 9, 'label' => 'Import', 'color' => '']], [9 => $alles], [9 => 32611.57], [], 2026, '2026-10-18');
$check('alles in einem Monat gebucht: kein Durchschnitt, Hochrechnung = Gebuchtes', $kImport['zeilen'][0]['basis'] === 'keine' && $kImport['zeilen'][0]['hochrechnung'] === 32611.57);
$kJung = prognose_kategorien([['id' => 2, 'label' => 'Tanken', 'color' => '']], [2 => $tanken], [2 => 200.0], [], 2026, '2026-02-10');
$check('junges Jahr: Fenster der letzten zwölf Monate', $kJung['zeilen'][0]['basis'] === 'fenster' && abs($kJung['zeilen'][0]['je_monat'] - prognose_statistik($tanken, prognose_fenster('2026-02-10'))['durchschnitt']) < 0.01);
$check('Anschaffungen aus: nur das Bisherige', $je[3]['art'] === 'aus' && $je[3]['rest'] === 0.0 && $je[3]['hochrechnung'] === 2500.0);
$check('ohne Kategorie ergänzt, leere Kategorie weggelassen', $je[0]['label'] === 'Ohne Kategorie' && !isset($je[4]) && count($k['zeilen']) === 4);
$check('Summe und Reihenfolge nach Hochrechnung', abs($k['summe']['bisher'] - 4275.0) < 0.01 && $k['zeilen'][0]['id'] === 3 && abs($k['summe']['hochrechnung'] - array_sum(array_column($k['zeilen'], 'hochrechnung'))) < 0.01);
$k2 = prognose_kategorien([['id' => 1, 'label' => 'Telefon', 'color' => '']], [1 => $telefon], [1 => 540.0], [], 2026, '2026-10-18');
$check('regelmäßig und auto: Empfehlung „fest"', $k2['zeilen'][0]['empfehlung'] === 'fest');
$k3 = prognose_kategorien($kategorien, $monatswerte, $bisher, $einst, 2025, '2026-10-18');
$check('vergangenes Jahr: Hochrechnung = bisher', $k3['monate']['rest'] === 0.0 && array_column($k3['zeilen'], 'rest') === [0.0, 0.0, 0.0, 0.0]);

/* ---------- wiederkehrende Rechnungen ---------- */
$check('Rhythmus geprüft, Abzeichen', wiederkehrend('monatlich') === 'monatlich' && wiederkehrend('quatsch') === '' && wiederkehrend_badge(['wiederkehrend' => 'jaehrlich']) === '<span class="badge badge--outline" title="Wiederkehrende Rechnung – geht in die Hochrechnung">↻ jährlich</span>' && wiederkehrend_badge(['wiederkehrend' => '']) === '');
$b = [];
foreach (range(1, 9) as $mo) { $b[] = ['id' => $mo, 'jahr' => 2026, 'datum' => sprintf('2026-%02d-05', $mo), 'bezeichnung' => 'Telefon', 'lieferant' => 'Telekom', 'betrag_brutto' => $mo === 9 ? 49.9 : 45.0, 'wiederkehrend' => 'monatlich', 'kategorie_id' => 1, 'kategorie_label' => 'Telefon', 'kategorie_color' => '#111']; }
$b[] = ['id' => 20, 'jahr' => 2025, 'datum' => '2025-11-10', 'bezeichnung' => 'KFZ-Versicherung', 'lieferant' => 'HUK', 'betrag_brutto' => 1200.0, 'wiederkehrend' => 'jaehrlich', 'kategorie_id' => 2, 'kategorie_label' => 'Versicherung', 'kategorie_color' => '#222'];
$b[] = ['id' => 21, 'jahr' => 2026, 'datum' => '2026-02-01', 'bezeichnung' => 'Haftpflicht', 'lieferant' => '', 'betrag_brutto' => 300.0, 'wiederkehrend' => 'jaehrlich', 'kategorie_id' => 2, 'kategorie_label' => 'Versicherung', 'kategorie_color' => '#222'];
$b[] = ['id' => 22, 'jahr' => 2026, 'datum' => '2026-04-15', 'bezeichnung' => 'Wartung Aufzug', 'lieferant' => 'Fa. Lift', 'betrag_brutto' => 250.0, 'wiederkehrend' => 'vierteljaehrlich', 'kategorie_id' => 0, 'kategorie_label' => null, 'kategorie_color' => null];
$b[] = ['id' => 23, 'jahr' => 2026, 'datum' => '2026-03-03', 'bezeichnung' => 'Einmalig', 'lieferant' => '', 'betrag_brutto' => 99.0, 'wiederkehrend' => '', 'kategorie_id' => 0];
$se = prognose_serien($b, 2026, '2026-10-18');
$je = array_column($se['zeilen'], null, 'bezeichnung');
$check('Telefon monatlich: letzter Betrag 49,90 × 3 offene Monate (Okt–Dez)', $je['Telefon']['betrag'] === 49.9 && $je['Telefon']['anzahl_jahr'] === 9 && $je['Telefon']['offen'] === 3 && abs($je['Telefon']['rest'] - 149.7) < 0.01 && abs($je['Telefon']['hochrechnung'] - (8 * 45 + 49.9 + 149.7)) < 0.01);
$check('KFZ-Versicherung jährlich, zuletzt im Vorjahr: einmal offen', $je['KFZ-Versicherung']['bisher'] === 0.0 && $je['KFZ-Versicherung']['offen'] === 1 && $je['KFZ-Versicherung']['rest'] === 1200.0 && $je['KFZ-Versicherung']['letzte'] === '2025-11-10');
$check('Haftpflicht jährlich, dieses Jahr gebucht: nichts offen', $je['Haftpflicht']['offen'] === 0 && $je['Haftpflicht']['hochrechnung'] === 300.0);
$check('Wartung vierteljährlich, zuletzt im 2. Quartal: 4. Quartal offen (3. zählt als vorbei)', $je['Wartung Aufzug']['offen'] === 1 && $je['Wartung Aufzug']['rest'] === 250.0);
$check('einmalige Buchung bleibt draußen, Reihenfolge nach Jahresende, Summe', !isset($je['Einmalig']) && $se['zeilen'][0]['bezeichnung'] === 'KFZ-Versicherung' && abs($se['summe']['hochrechnung'] - array_sum(array_column($se['zeilen'], 'hochrechnung'))) < 0.01);
$check('Telefon im Oktober schon gebucht: zwei offen', prognose_serien(array_merge($b, [['id' => 30, 'jahr' => 2026, 'datum' => '2026-10-05', 'bezeichnung' => 'Telefon', 'lieferant' => 'Telekom', 'betrag_brutto' => 49.9, 'wiederkehrend' => 'monatlich', 'kategorie_id' => 1]]), 2026, '2026-10-18')['zeilen'] !== [] && array_column(prognose_serien(array_merge($b, [['id' => 30, 'jahr' => 2026, 'datum' => '2026-10-05', 'bezeichnung' => 'Telefon', 'lieferant' => 'Telekom', 'betrag_brutto' => 49.9, 'wiederkehrend' => 'monatlich', 'kategorie_id' => 1]]), 2026, '2026-10-18')['zeilen'], 'offen', 'bezeichnung')['Telefon'] === 2);
$mitPlan = array_merge($b, [['id' => 40, 'jahr' => 2026, 'datum' => '2026-11-05', 'bezeichnung' => 'Telefon', 'lieferant' => 'Telekom', 'betrag_brutto' => 49.9, 'wiederkehrend' => 'monatlich', 'kategorie_id' => 1, 'status' => 'geplant'],
    ['id' => 41, 'jahr' => 2026, 'datum' => '2026-12-05', 'bezeichnung' => 'Telefon', 'lieferant' => 'Telekom', 'betrag_brutto' => 49.9, 'wiederkehrend' => 'monatlich', 'kategorie_id' => 1, 'status' => 'geplant']]);
$tp = array_column(prognose_serien($mitPlan, 2026, '2026-10-18')['zeilen'], null, 'bezeichnung')['Telefon'];
$check('geplante Buchungen der Serie: zählen bei der Planung, nicht noch einmal als Schätzung', $tp['geplant_anzahl'] === 2 && abs($tp['geplant_summe'] - 99.8) < 0.01 && $tp['offen'] === 1 && abs($tp['rest'] - 49.9) < 0.01 && $tp['bisher'] === 8 * 45 + 49.9 && $tp['letzte'] === '2026-09-05');
$neben = [
    ['id' => 50, 'jahr' => 2026, 'datum' => '2026-03-10', 'bezeichnung' => 'Nebenkostenabrechnung 2025', 'lieferant' => 'BIMA', 'betrag_brutto' => 7100.0, 'wiederkehrend' => 'jaehrlich', 'kategorie_id' => 5, 'status' => 'bezahlt'],
    ['id' => 51, 'jahr' => 2027, 'datum' => '2027-03-01', 'bezeichnung' => 'Nebenkosten 2026', 'lieferant' => 'BIMA', 'betrag_brutto' => 7500.0, 'wiederkehrend' => 'jaehrlich', 'kategorie_id' => 5, 'status' => 'geplant'],
    ['id' => 52, 'jahr' => 2027, 'datum' => '2027-01-01', 'bezeichnung' => 'Spritt', 'lieferant' => '', 'betrag_brutto' => 2000.0, 'wiederkehrend' => 'monatlich', 'kategorie_id' => 6, 'status' => 'geplant'],
];
$sn = array_column(prognose_serien($neben, 2027, '2027-02-10')['zeilen'], null, 'lieferant');
$check('gleicher Lieferant trotz anderer Bezeichnung = eine Serie; geplante 2027-Buchung deckt den Termin, nichts doppelt', count($sn) === 1 && isset($sn['BIMA']) && $sn['BIMA']['betrag'] === 7100.0 && $sn['BIMA']['geplant_anzahl'] === 1 && $sn['BIMA']['offen'] === 0 && $sn['BIMA']['rest'] === 0.0 && $sn['BIMA']['bezeichnung'] === 'Nebenkostenabrechnung 2025');
$check('nur geplant, nie gebucht (Sprit-Pauschale): keine Serie, keine Schätzung', !isset(array_column(prognose_serien($neben, 2027, '2027-02-10')['zeilen'], null, 'bezeichnung')['Spritt']));
$check('vergangenes und künftiges Jahr', array_column(prognose_serien($b, 2025, '2026-10-18')['zeilen'], 'offen') === [0, 0, 0, 0] && array_column(prognose_serien($b, 2027, '2026-10-18')['zeilen'], 'offen', 'bezeichnung')['Telefon'] === 12);

$check('Hochrechnung liest die Ausgabenkategorien, nicht eine fremde Liste', str_contains((string)file_get_contents($app . '/src/lib/prognose.php'), "list_items(buchung_list_key('ausgabe'))") && !str_contains((string)file_get_contents($app . '/src/lib/prognose.php'), "list_items('kategorie')"));

/* ---------- Zähler ---------- */
$kosten = [1 => 120.0, 2 => 110.0, 3 => 100.0, 4 => 80.0, 5 => 70.0, 6 => 60.0, 7 => 60.0, 8 => 65.0, 9 => 75.0, 10 => null, 11 => null, 12 => null];
$zr = prognose_zaehler_rechnen($kosten, 2026, '2026-10-18');
$check('Zähler ohne Vorjahr: neun Monate mit Kosten, drei fehlen aus dem Durchschnitt', $zr['bisher'] === 740.0 && $zr['monate_mit'] === 9 && abs($zr['durchschnitt'] - 82.22) < 0.01 && abs($zr['rest'] - 246.67) < 0.01 && abs($zr['hochrechnung'] - 986.67) < 0.01 && $zr['aus_schnitt'] === 3 && $zr['aus_vorjahr'] === 0 && $zr['vorjahr']['monate_mit'] === 0);
$vorjahr = [1 => 130.0, 2 => 120.0, 3 => 100.0, 4 => 85.0, 5 => 70.0, 6 => 60.0, 7 => 60.0, 8 => 62.0, 9 => 78.0, 10 => 95.0, 11 => 118.0, 12 => null];
$zv = prognose_zaehler_rechnen($kosten, 2026, '2026-10-18', $vorjahr);
$check('Zähler mit Vorjahr: Oktober und November aus dem Vorjahr (Heizung!), Dezember aus dem Durchschnitt', $zv['aus_vorjahr'] === 2 && $zv['aus_schnitt'] === 1 && abs($zv['rest'] - (95 + 118 + 82.22)) < 0.01 && abs($zv['hochrechnung'] - (740 + 95 + 118 + 82.22)) < 0.01
    && $zv['vorjahr']['summe'] === 978.0 && $zv['vorjahr']['monate_mit'] === 11 && abs($zv['vorjahr']['durchschnitt'] - 88.91) < 0.01);
$check('Zähler im Vorjahr-Modus: vergangenes Jahr kein Rest', prognose_zaehler_rechnen($kosten, 2025, '2026-10-18', $vorjahr)['rest'] === 0.0);
$GLOBALS['execs'] = [];
prognose_zaehler_schalten(2026, false);
$check('Schalter: Vorgabe an, abschalten merkt sich je Jahr', prognose_zaehler_aktiv(2026) && count($GLOBALS['execs']) === 1 && $GLOBALS['execs'][0][1][0] === 'prognose_zaehler_an_2026' && $GLOBALS['execs'][0][1][1] === '0');
$check('Zähler im vergangenen Jahr: nichts dazu', prognose_zaehler_rechnen($kosten, 2025, '2026-10-18')['rest'] === 0.0 && array_slice(prognose_zaehler_rechnen(array_fill(1, 12, null), 2026, '2026-10-18'), 0, 5) === ['bisher' => 0.0, 'monate_mit' => 0, 'durchschnitt' => 0.0, 'rest' => 0.0, 'hochrechnung' => 0.0]);

/* ---------- Formular ---------- */
$e = prognose_aus_post(['art' => ['1' => 'fest', '2' => 'quatsch', '3' => 'aus'], 'betrag' => ['1' => '49,90', '2' => '1.250,50', '3' => ''], 'notiz' => ['1' => ' neuer Vertrag ']]);
$check('Formularwerte: Art geprüft, deutsche Beträge gelesen, leer = 0', $e[1] === ['art' => 'fest', 'betrag' => 49.9, 'notiz' => ' neuer Vertrag '] && $e[2]['art'] === 'auto' && $e[2]['betrag'] === 1250.5 && $e[3]['betrag'] === 0.0);
$GLOBALS['execs'] = [];
prognose_einstellung_speichern(2026, 1, 'fest', 49.9, ' neuer Vertrag ');
$check('speichern: eine Abfrage mit geprüften Werten', count($GLOBALS['execs']) === 1 && str_contains($GLOBALS['execs'][0][0], 'INSERT INTO budget_prognosen') && $GLOBALS['execs'][0][1] === [2026, 1, 'fest', 49.9, 'neuer Vertrag']);

/* ---------- Ansicht ---------- */
$zahlen = ['verfuegbar' => 20000.0, 'ausgaben' => 9000.0, 'geplant' => 1500.0];
$html = render_partial('budget_prognose', ['jahr' => 2026, 'jahre' => [2026, 2025], 'darf' => true, 'prognose' => [
    'jahr' => 2026, 'heute' => '2026-10-18', 'kategorien' => $k, 'zahlen' => $zahlen, 'serien' => $se,
    'zaehler' => ['zeilen' => [$zv + ['id' => 5, 'name' => 'Hauptanschluss', 'art' => 'strom', 'erloes' => false, 'ohne_tarif' => 1]], 'summe' => 1035.22, 'erloes' => 0.0, 'aktiv' => true, 'vorjahr_summe' => 978.0],
    'laufend' => 8300.0, 'geplant' => 1500.0, 'hochrechnung' => 9800.0, 'frei' => 10200.0, 'frei_mit_planung' => 10200.0]]);
$check('Ansicht: Kennzahlen, Kategorien mit Auswahl, Empfehlung, Zähler, Hinweis auf fehlenden Tarif', str_contains($html, 'Hochrechnung Jahresende') && str_contains($html, 'name="art[1]"') && str_contains($html, 'value="fest" selected') && str_contains($html, 'name="betrag[2]"')
    && str_contains($html, '>regelmäßig<') && str_contains($html, '>schwankend<') && str_contains($html, 'Hauptanschluss') && str_contains($html, '1 Monat(e) ohne Tarif') && str_contains($html, 'sind abgezogen') && str_contains($html, '1.500,00 geplant') && str_contains($html, 'Speichern')
    && str_contains($html, 'Wiederkehrende Rechnungen') && str_contains($html, '↻ monatlich') && str_contains($html, 'KFZ-Versicherung') && str_contains($html, 'wiederkehrend · ')
    && str_contains($html, 'Nebenkosten aus den Zählern') && str_contains($html, '2 Monat(e) aus dem Vorjahr') && str_contains($html, '1 aus dem Durchschnitt') && str_contains($html, 'Aus der Hochrechnung nehmen') && str_contains($html, 'value="zaehler"') && str_contains($html, 'Nebenkosten aus Zählern'));
$htmlZ = render_partial('budget_prognose', ['jahr' => 2026, 'jahre' => [2026], 'darf' => true, 'prognose' => [
    'jahr' => 2026, 'heute' => '2026-10-18', 'kategorien' => $k, 'zahlen' => $zahlen, 'serien' => $se,
    'zaehler' => ['zeilen' => [$zv + ['id' => 5, 'name' => 'Hauptanschluss', 'art' => 'strom', 'erloes' => false, 'ohne_tarif' => 0]], 'summe' => 1035.22, 'erloes' => 0.0, 'aktiv' => false, 'vorjahr_summe' => 978.0],
    'laufend' => 8300.0, 'geplant' => 0.0, 'hochrechnung' => 8300.0, 'frei' => 11700.0, 'frei_mit_planung' => 11700.0]]);
$check('Zähler ausgeschaltet: Knopf zum Aufnehmen, nicht in der Kennzahl', str_contains($htmlZ, 'In die Hochrechnung aufnehmen') && str_contains($htmlZ, 'ausgeschaltet') && !str_contains($htmlZ, 'Nebenkosten aus Zählern'));
$html = render_partial('budget_prognose', ['jahr' => 2025, 'jahre' => [2026, 2025], 'darf' => false, 'prognose' => [
    'jahr' => 2025, 'heute' => '2026-10-18', 'kategorien' => $k3, 'zahlen' => $zahlen, 'serien' => ['zeilen' => [], 'summe' => ['bisher' => 0.0, 'rest' => 0.0, 'hochrechnung' => 0.0]], 'zaehler' => ['zeilen' => [], 'summe' => 0.0, 'erloes' => 0.0, 'aktiv' => true, 'vorjahr_summe' => 0.0], 'laufend' => 4275.0, 'geplant' => 0.0, 'hochrechnung' => 4275.0, 'frei' => 15725.0, 'frei_mit_planung' => 15725.0]]);
$check('Ansicht ohne Recht und vergangenes Jahr: keine Felder, Hinweis', !str_contains($html, '<select') && !str_contains($html, 'Speichern') && str_contains($html, 'ist um') && str_contains($html, 'Kein Zähler mit Tarif') && str_contains($html, 'Noch keine Ausgabe als wiederkehrend markiert'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
