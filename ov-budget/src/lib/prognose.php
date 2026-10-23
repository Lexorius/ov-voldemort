<?php
declare(strict_types=1);

/**
 * Hochrechnung aufs Jahresende: wiederkehrende Ausgaben je Kategorie
 * (Telefon, Tanken, Versicherungen …) aus dem Durchschnitt der letzten
 * zwölf Monate, dazu die Energie- und Wasserkosten aus den Zählern.
 *
 * Je Kategorie und Jahr lässt sich die Art wählen: „auto" (Durchschnitt),
 * „fest" (bekannter Betrag je Monat, etwa die Telefonrechnung) oder „aus"
 * (nicht hochrechnen, etwa einmalige Anschaffungen).
 */

const PROGNOSE_ARTEN = ['auto' => 'Durchschnitt', 'fest' => 'fester Betrag je Monat', 'aus' => 'nicht hochrechnen'];
const PROGNOSE_FENSTER = 12;
const PROGNOSE_MIN_MONATE = 3;   // so viele Monate mit Buchungen braucht ein Durchschnitt, sonst wird nicht hochgerechnet

/* ==================================================================== */
/* Einstellungen je Kategorie                                             */
/* ==================================================================== */

/** [kategorie_id => Zeile]; 0 = Buchungen ohne Kategorie */
function prognose_einstellungen(int $jahr): array
{
    $out = [];
    foreach (db_all('SELECT * FROM budget_prognosen WHERE jahr = ?', [$jahr]) as $r) {
        $out[(int)$r['kategorie_id']] = $r;
    }
    return $out;
}

function prognose_art(string $art): string
{
    return isset(PROGNOSE_ARTEN[$art]) ? $art : 'auto';
}

function prognose_einstellung_speichern(int $jahr, int $katId, string $art, float $betrag, string $notiz): void
{
    db_exec(
        'INSERT INTO budget_prognosen (jahr, kategorie_id, art, betrag_monat, notiz) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE art = VALUES(art), betrag_monat = VALUES(betrag_monat), notiz = VALUES(notiz)',
        [$jahr, $katId, prognose_art($art), round(max(0.0, $betrag), 2), mb_substr(trim($notiz), 0, 200)]
    );
}

/** Einstellungen aus dem Formular: art[id], betrag[id], notiz[id]. Gibt die Zahl der gespeicherten Zeilen. Reine Auswertung über $post. */
function prognose_aus_post(array $post): array
{
    $out = [];
    foreach ((array)($post['art'] ?? []) as $id => $art) {
        $id = (int)$id;
        $betrag = (string)(($post['betrag'] ?? [])[$id] ?? '');
        $out[$id] = [
            'art'    => prognose_art((string)$art),
            'betrag' => $betrag === '' ? 0.0 : (float)str_replace(['.', ','], ['', '.'], preg_replace('/\.(?=\d{3}(\D|$))/', '', $betrag) ?? $betrag),
            'notiz'  => (string)(($post['notiz'] ?? [])[$id] ?? ''),
        ];
    }
    return $out;
}

/* ==================================================================== */
/* Daten                                                                  */
/* ==================================================================== */

/** Ausgaben je Kategorie und Monat im Zeitraum: [kategorie_id => ['2026-03' => Betrag]]; 0 = ohne Kategorie */
function prognose_monatswerte(string $von, string $bis): array
{
    $out = [];
    foreach (db_all(
        "SELECT COALESCE(kategorie_id, 0) AS k, DATE_FORMAT(datum, '%Y-%m') AS ym, SUM(betrag_brutto) AS betrag
         FROM expenses WHERE art = 'ausgabe' AND " . BUCHUNG_IST . " AND COALESCE(wiederkehrend, '') = '' AND datum BETWEEN ? AND ?
         GROUP BY k, ym",
        [$von, $bis]
    ) as $r) {
        $out[(int)$r['k']][(string)$r['ym']] = (float)$r['betrag'];
    }
    return $out;
}

/** Einmalige (nicht als wiederkehrend markierte) Ausgaben dieses Jahres je Kategorie: [kategorie_id => Betrag] */
function prognose_bisher_einmalig(int $jahr): array
{
    $out = [];
    foreach (db_all(
        "SELECT COALESCE(kategorie_id, 0) AS k, SUM(betrag_brutto) AS betrag FROM expenses
         WHERE jahr = ? AND art = 'ausgabe' AND " . BUCHUNG_IST . " AND COALESCE(wiederkehrend, '') = '' GROUP BY k",
        [$jahr]
    ) as $r) {
        $out[(int)$r['k']] = (float)$r['betrag'];
    }
    return $out;
}

/** Als wiederkehrend markierte Ausgaben des Jahres und des Vorjahres, älteste zuerst */
function prognose_wiederkehrende_buchungen(int $jahr): array
{
    return db_all(
        "SELECT e.id, e.jahr, e.datum, e.bezeichnung, e.lieferant, e.betrag_brutto, e.wiederkehrend, e.status, COALESCE(e.kategorie_id, 0) AS kategorie_id, k.label AS kategorie_label, k.color AS kategorie_color
         FROM expenses e LEFT JOIN list_items k ON k.id = e.kategorie_id
         WHERE e.art = 'ausgabe' AND (" . BUCHUNG_IST . " OR e.status = 'geplant') AND COALESCE(e.wiederkehrend, '') <> '' AND e.jahr BETWEEN ? AND ?
         ORDER BY e.datum, e.id",
        [$jahr - 1, $jahr]
    );
}

/**
 * Wiederkehrende Rechnungen zu Serien bündeln – gleicher Rhythmus, gleiche
 * Kategorie und gleicher Lieferant (ohne Lieferant: gleiche Bezeichnung),
 * damit „Nebenkostenabrechnung 2025" und „Nebenkosten 2026" eine Serie sind –
 * und je Serie den Rest bis Jahresende rechnen: zuletzt gebuchter Betrag ×
 * noch ausstehende Termine. Monatlich: Monate nach der letzten Buchung dieses
 * Jahres (mindestens ab dem laufenden Monat), vierteljährlich je Quartal,
 * jährlich: einmal, falls dieses Jahr noch nicht gebucht. Geplante Buchungen
 * der Serie zählen als schon erfasste Termine. Eine Serie ohne eine einzige
 * bezahlte oder offene Buchung schätzt nichts – ihre geplanten Beträge stehen
 * in der Planung. Reine Funktion.
 */
function prognose_serien(array $buchungen, int $jahr, string $heute): array
{
    $monate = prognose_monate($jahr, $heute);
    $hj = (int)substr($heute, 0, 4);
    $hm = (int)substr($heute, 5, 2);
    $serien = [];
    foreach ($buchungen as $b) {
        $w = wiederkehrend((string)$b['wiederkehrend']);
        if ($w === '') {
            continue;
        }
        $lieferant = mb_strtolower(trim((string)($b['lieferant'] ?? '')));
        $key = $w . '|' . (int)$b['kategorie_id'] . '|' . ($lieferant !== '' ? 'l:' . $lieferant : 'b:' . mb_strtolower(trim((string)$b['bezeichnung'])));
        $s = $serien[$key] ?? ['key' => $key, 'bezeichnung' => (string)$b['bezeichnung'], 'lieferant' => (string)$b['lieferant'], 'rhythmus' => $w,
            'kategorie_id' => (int)$b['kategorie_id'], 'kategorie_label' => (string)($b['kategorie_label'] ?? ''), 'kategorie_color' => (string)($b['kategorie_color'] ?? ''),
            'betrag' => 0.0, 'letzte' => '', 'bisher' => 0.0, 'anzahl_jahr' => 0, 'letzter_monat' => 0, 'geplant_anzahl' => 0, 'geplant_summe' => 0.0];
        if ((string)($b['status'] ?? 'bezahlt') === 'geplant') {
            // Schon als geplante Buchung erfasst: zählt bei der Planung, nicht noch einmal als Schätzung
            if ((int)$b['jahr'] === $jahr) {
                $s['geplant_anzahl']++;
                $s['geplant_summe'] += (float)$b['betrag_brutto'];
            }
            $serien[$key] = $s;
            continue;
        }
        $s['betrag'] = (float)$b['betrag_brutto'];
        $s['letzte'] = (string)$b['datum'];
        $s['bezeichnung'] = (string)$b['bezeichnung'];
        $s['lieferant'] = (string)$b['lieferant'] ?: $s['lieferant'];
        if ((int)$b['jahr'] === $jahr) {
            $s['bisher'] += (float)$b['betrag_brutto'];
            $s['anzahl_jahr']++;
            $s['letzter_monat'] = max($s['letzter_monat'], (int)substr((string)$b['datum'], 5, 2));
        }
        $serien[$key] = $s;
    }
    $zeilen = [];
    $summe = ['bisher' => 0.0, 'rest' => 0.0, 'hochrechnung' => 0.0];
    foreach ($serien as $s) {
        if ($s['letzte'] === '') {
            continue;   // nur geplant, nie gebucht: nichts zu schätzen, die Planung zählt
        }
        $jeJahr = WIEDERKEHREND[$s['rhythmus']]['je_jahr'];
        if ($jahr < $hj) {
            $offen = 0;
        } elseif ($jahr > $hj) {
            $offen = $jeJahr;
        } else {
            // Termine je Jahr, davon die schon vorbei sind: bis zur letzten Buchung oder bis zum Vormonat
            $takt = intdiv(12, $jeJahr);
            $vorbei = max((int)ceil($s['letzter_monat'] / $takt), (int)floor(($hm - 1) / $takt));
            $offen = max(0, $jeJahr - $vorbei);
            if ($jeJahr === 1) {
                $offen = $s['anzahl_jahr'] > 0 ? 0 : 1;
            }
        }
        // Was schon als geplante Buchung im System steht, wird nicht noch einmal geschätzt
        $offen = max(0, $offen - (int)$s['geplant_anzahl']);
        $rest = round($s['betrag'] * $offen, 2);
        $s['offen'] = $offen;
        $s['rest'] = $rest;
        $s['hochrechnung'] = round($s['bisher'] + $rest, 2);
        $s['rhythmus_label'] = WIEDERKEHREND[$s['rhythmus']]['label'];
        $zeilen[] = $s;
        $summe['bisher'] += $s['bisher'];
        $summe['rest'] += $rest;
        $summe['hochrechnung'] += $s['bisher'] + $rest;
    }
    usort($zeilen, static fn($a, $b) => [$b['hochrechnung'], $a['bezeichnung']] <=> [$a['hochrechnung'], $b['bezeichnung']]);
    return ['zeilen' => $zeilen, 'summe' => array_map(static fn($v) => round($v, 2), $summe), 'monate' => $monate];
}

/* ==================================================================== */
/* Reine Rechnung                                                         */
/* ==================================================================== */

/** Die zwölf vollen Monate vor dem Monat von $heute als 'YYYY-MM'-Liste, älteste zuerst. Reine Funktion. */
function prognose_fenster(string $heute, int $monate = PROGNOSE_FENSTER): array
{
    $jahr = (int)substr($heute, 0, 4);
    $monat = (int)substr($heute, 5, 2);
    $out = [];
    for ($i = $monate; $i >= 1; $i--) {
        $t = mktime(0, 0, 0, $monat - $i, 1, $jahr);
        $out[] = date('Y-m', $t);
    }
    return $out;
}

/**
 * Wie viele Monate des Jahres sind um, wie viele bleiben – Bruchteile für den
 * laufenden Monat. Vergangene Jahre: 12 / 0, künftige: 0 / 12. Reine Funktion.
 */
function prognose_monate(int $jahr, string $heute): array
{
    $hj = (int)substr($heute, 0, 4);
    if ($jahr < $hj) {
        return ['vergangen' => 12.0, 'rest' => 0.0];
    }
    if ($jahr > $hj) {
        return ['vergangen' => 0.0, 'rest' => 12.0];
    }
    $m = (int)substr($heute, 5, 2);
    $tag = (int)substr($heute, 8, 2);
    $tage = (int)date('t', mktime(0, 0, 0, $m, 1, $jahr));
    $anteil = $tage > 0 ? ($tag - 1) / $tage : 0.0;
    $vergangen = ($m - 1) + $anteil;
    return ['vergangen' => round($vergangen, 3), 'rest' => round(12 - $vergangen, 3)];
}

/**
 * Durchschnitt und Schwankung einer Kategorie im Fenster. Gezählt wird ab dem
 * ersten Monat mit Buchung; Monate ohne Buchung danach zählen als 0 (Tanken
 * fällt mal aus, Versicherung kommt einmal im Jahr). Reine Funktion.
 */
function prognose_statistik(array $monatswerte, array $fenster): array
{
    $werte = [];
    $gestartet = false;
    $mit = 0;
    foreach ($fenster as $ym) {
        $v = (float)($monatswerte[$ym] ?? 0.0);
        if ($v > 0) {
            $gestartet = true;
            $mit++;
        }
        if ($gestartet) {
            $werte[] = $v;
        }
    }
    $n = count($werte);
    $summe = array_sum($werte);
    $schnitt = $n > 0 ? $summe / $n : 0.0;
    $cv = 0.0;
    if ($n >= 2 && $schnitt > 0) {
        $var = array_sum(array_map(static fn($v) => ($v - $schnitt) ** 2, $werte)) / $n;
        $cv = sqrt($var) / $schnitt;
    }
    return ['summe' => round($summe, 2), 'durchschnitt' => round($schnitt, 2), 'monate' => $n, 'monate_mit' => $mit, 'schwankung' => round($cv, 3)];
}

/** Lesbar: regelmäßig / schwankend / stark schwankend / zu wenig Daten. Reine Funktion. */
function prognose_schwankung_text(array $s): string
{
    if ((int)$s['monate_mit'] < 2) {
        return 'zu wenig Daten';
    }
    $cv = (float)$s['schwankung'];
    return $cv < 0.25 ? 'regelmäßig' : ($cv < 0.75 ? 'schwankend' : 'stark schwankend');
}

/**
 * Hochrechnung je Kategorie. $kategorien: [['id','label','color']], $monatswerte
 * aus prognose_monatswerte(), $bisher: [kategorie_id => Betrag dieses Jahr],
 * $einstellungen: [kategorie_id => ['art','betrag_monat','notiz']]. Reine Funktion.
 */
function prognose_kategorien(array $kategorien, array $monatswerte, array $bisher, array $einstellungen, int $jahr, string $heute): array
{
    $fenster = prognose_fenster($heute);
    $monate = prognose_monate($jahr, $heute);
    $liste = $kategorien;
    $ids = array_map('intval', array_column($liste, 'id'));
    // Kategorien, die nur in alten Buchungen oder ohne Kategorie vorkommen, ergänzen
    foreach (array_unique(array_merge(array_keys($monatswerte), array_keys($bisher))) as $k) {
        if (!in_array((int)$k, $ids, true)) {
            $liste[] = ['id' => (int)$k, 'label' => (int)$k === 0 ? 'Ohne Kategorie' : 'Kategorie #' . (int)$k, 'color' => '#94a3b8'];
        }
    }
    $zeilen = [];
    $summe = ['bisher' => 0.0, 'rest' => 0.0, 'hochrechnung' => 0.0];
    foreach ($liste as $k) {
        $id = (int)$k['id'];
        $st = prognose_statistik($monatswerte[$id] ?? [], $fenster);
        $e = $einstellungen[$id] ?? ['art' => 'auto', 'betrag_monat' => 0, 'notiz' => ''];
        $art = prognose_art((string)$e['art']);
        $b = (float)($bisher[$id] ?? 0.0);
        // Durchschnitt: im laufenden Jahr das bisher Gebuchte durch die vergangenen Monate – das trägt
        // auch dann, wenn alles in einem Monat gebucht wurde (Import, Nachbuchung). Erst im jungen Jahr
        // (unter drei Monaten) zählt das Zwölf-Monats-Fenster. Unter drei Monaten mit Buchungen gibt es
        // keinen belastbaren Durchschnitt – dann wird nicht hochgerechnet, nur das Gebuchte zählt.
        $hj = (int)substr($heute, 0, 4);
        if ($st['monate_mit'] < PROGNOSE_MIN_MONATE) {
            $basis = 'keine';
            $schnitt = 0.0;
        } elseif ($jahr === $hj && $monate['vergangen'] >= PROGNOSE_MIN_MONATE) {
            $basis = 'jahr';
            $schnitt = $b / $monate['vergangen'];
        } else {
            $basis = 'fenster';
            $schnitt = (float)$st['durchschnitt'];
        }
        $jeMonat = match ($art) {
            'fest' => (float)$e['betrag_monat'],
            'aus'  => 0.0,
            default => $schnitt,
        };
        $rest = round($jeMonat * $monate['rest'], 2);
        if ($b <= 0 && $st['monate_mit'] === 0 && $art !== 'fest') {
            continue;   // nichts gebucht, nichts erwartet
        }
        $zeilen[] = [
            'id' => $id, 'label' => (string)$k['label'], 'color' => (string)($k['color'] ?? ''),
            'bisher' => $b, 'statistik' => $st, 'schwankung_text' => prognose_schwankung_text($st),
            'art' => $art, 'betrag_monat' => (float)$e['betrag_monat'], 'notiz' => (string)($e['notiz'] ?? ''),
            'je_monat' => round($jeMonat, 2), 'rest' => $rest, 'hochrechnung' => round($b + $rest, 2),
            'basis' => $basis, 'schnitt' => round($schnitt, 2),
            'empfehlung' => $art === 'auto' && prognose_schwankung_text($st) === 'regelmäßig' ? 'fest' : '',
        ];
        $summe['bisher'] += $b;
        $summe['rest'] += $rest;
        $summe['hochrechnung'] += $b + $rest;
    }
    usort($zeilen, static fn($a, $b) => $b['hochrechnung'] <=> $a['hochrechnung']);
    return ['zeilen' => $zeilen, 'summe' => array_map(static fn($v) => round($v, 2), $summe), 'monate' => $monate, 'fenster' => $fenster];
}

/**
 * Nebenkosten-Hochrechnung eines Zählers aus seinen Monatskosten (null =
 * Monat noch ohne Wert). Fehlende Monate bis zum Jahresende werden mit dem
 * gleichen Monat des Vorjahres gefüllt – Heizung im Winter, Wasser im Sommer
 * –, ohne Vorjahreswert mit dem Durchschnitt der Monate dieses Jahres. Reine Funktion.
 */
function prognose_zaehler_rechnen(array $kostenMonate, int $jahr, string $heute, array $vorjahrMonate = []): array
{
    $monate = prognose_monate($jahr, $heute);
    $mit = array_filter($kostenMonate, static fn($v) => $v !== null);
    $bisher = round(array_sum($mit), 2);
    $schnitt = $mit ? $bisher / count($mit) : 0.0;
    $vjMit = array_filter($vorjahrMonate, static fn($v) => $v !== null);
    $vorjahr = ['summe' => round(array_sum($vjMit), 2), 'monate_mit' => count($vjMit), 'durchschnitt' => $vjMit ? round(array_sum($vjMit) / count($vjMit), 2) : 0.0];
    $rest = 0.0;
    $ausVorjahr = 0;
    $ausSchnitt = 0;
    if ($monate['rest'] > 0) {
        for ($m = 1; $m <= 12; $m++) {
            if (($kostenMonate[$m] ?? null) !== null) {
                continue;
            }
            if (($vorjahrMonate[$m] ?? null) !== null) {
                $rest += (float)$vorjahrMonate[$m];
                $ausVorjahr++;
            } else {
                $rest += $schnitt;
                $ausSchnitt++;
            }
        }
    }
    $rest = round($rest, 2);
    return ['bisher' => $bisher, 'monate_mit' => count($mit), 'durchschnitt' => round($schnitt, 2), 'rest' => $rest, 'hochrechnung' => round($bisher + $rest, 2),
        'vorjahr' => $vorjahr, 'aus_vorjahr' => $ausVorjahr, 'aus_schnitt' => $ausSchnitt];
}

/** Zählen die Nebenkosten aus den Zählern in der Hochrechnung dieses Jahres mit? Schalter auf der Seite, Vorgabe an. */
function prognose_zaehler_aktiv(int $jahr): bool
{
    return state_get('prognose_zaehler_an_' . $jahr, '1') === '1';
}

function prognose_zaehler_schalten(int $jahr, bool $an): void
{
    state_save('prognose_zaehler_an_' . $jahr, $an ? '1' : '0');
}

/** Nebenkosten aus den Zählern: je Hauptzähler Kosten bisher, Vorjahr, Hochrechnung; Einspeisung als Erlös */
function prognose_zaehler(int $jahr, string $heute): array
{
    $aktiv = prognose_zaehler_aktiv($jahr);
    if (!function_exists('meter_query')) {
        return ['zeilen' => [], 'summe' => 0.0, 'erloes' => 0.0, 'aktiv' => $aktiv, 'vorjahr_summe' => 0.0];
    }
    [$von, $bis] = verbrauch_zeitraum($jahr);
    [$vjVon, $vjBis] = verbrauch_zeitraum($jahr - 1);
    $zeilen = [];
    $summe = 0.0;
    $erloes = 0.0;
    $vorjahrSumme = 0.0;
    foreach (meter_query() as $m) {
        $tarifArt = meter_tarif_art($m);
        if ($tarifArt === null) {
            continue;   // Unterzähler und Erzeugung tragen keine eigenen Kosten
        }
        $tarife = tarif_query($tarifArt);
        $kosten = verbrauch_kosten_jahr(readings_bereich((int)$m['id'], $von, $bis), $tarife, $m, $jahr);
        $vorjahr = verbrauch_kosten_jahr(readings_bereich((int)$m['id'], $vjVon, $vjBis), $tarife, $m, $jahr - 1);
        $r = prognose_zaehler_rechnen($kosten['monate'], $jahr, $heute, $vorjahr['monate']);
        $r += ['id' => (int)$m['id'], 'name' => (string)$m['name'], 'art' => (string)$m['art'], 'erloes' => (bool)$kosten['erloes'], 'ohne_tarif' => (int)$kosten['ohne_tarif']];
        $zeilen[] = $r;
        if ($r['erloes']) {
            $erloes += $r['hochrechnung'];
        } else {
            $summe += $r['hochrechnung'];
            $vorjahrSumme += $r['vorjahr']['summe'];
        }
    }
    return ['zeilen' => $zeilen, 'summe' => round($summe, 2), 'erloes' => round($erloes, 2), 'aktiv' => $aktiv, 'vorjahr_summe' => round($vorjahrSumme, 2)];
}

/** Alles für die Seite: Kategorien, Zähler, Jahreszahlen und die Summe unterm Strich */
function prognose_jahr(int $jahr, ?string $heute = null): array
{
    $heute ??= date('Y-m-d');
    $fenster = prognose_fenster($heute);
    $von = $fenster[0] . '-01';
    $bis = date('Y-m-t', strtotime(end($fenster) . '-01'));
    $kategorien = array_map(static fn($k) => ['id' => (int)$k['id'], 'label' => (string)$k['label'], 'color' => (string)($k['color'] ?? '')], list_items(buchung_list_key('ausgabe')));
    $kat = prognose_kategorien($kategorien, prognose_monatswerte($von, $bis), prognose_bisher_einmalig($jahr), prognose_einstellungen($jahr), $jahr, $heute);
    $serien = prognose_serien(prognose_wiederkehrende_buchungen($jahr), $jahr, $heute);
    $zaehler = prognose_zaehler($jahr, $heute);
    $zahlen = budget_jahr_zahlen($jahr);
    $laufend = $kat['summe']['hochrechnung'] + $serien['summe']['hochrechnung'] + ($zaehler['aktiv'] ? $zaehler['summe'] - $zaehler['erloes'] : 0.0);
    $geplant = (float)$zahlen['geplant'];   // geplante Buchungen und Veranstaltungen – gehören mit aufs Jahresende
    return [
        'jahr' => $jahr, 'heute' => $heute, 'kategorien' => $kat, 'serien' => $serien, 'zaehler' => $zaehler, 'zahlen' => $zahlen,
        'laufend' => round($laufend, 2),
        'geplant' => round($geplant, 2),
        'hochrechnung' => round($laufend + $geplant, 2),
        'frei' => round($zahlen['verfuegbar'] - $laufend - $geplant, 2),
        'frei_mit_planung' => round($zahlen['verfuegbar'] - $laufend - $geplant, 2),
    ];
}
