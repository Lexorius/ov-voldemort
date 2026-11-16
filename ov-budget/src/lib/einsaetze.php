<?php
declare(strict_types=1);

/**
 * Einsätze: was der Ortsverband gefahren ist – über einen oder mehrere Tage.
 *
 * Ein Einsatz besteht aus Zeilen: Datum, von–bis, Fachgruppe, Fahrzeuge,
 * Stärke (Führer / Unterführer / Helfer) und Tätigkeit. Eine Zeile ist die
 * Einsatzzeit einer Einheit an einem Tag – mehrere Zeilen je Tag sind
 * mehrere Fachgruppen oder Schichten, jede mit eigenen Uhrzeiten und
 * eigenen Fahrzeugen. Daraus rechnet das Modul Einsatztage, Einsatzstunden
 * und Helferstunden, auch je Fachgruppe und je Fahrzeug – die Zahlen, die
 * die Einsatzabrechnung braucht. Beginn und Ende am Einsatz selbst und
 * seine Fahrzeuge (einsatz_vehicles) sind aus den Zeilen abgeleitet.
 *
 * Geld wird nicht hier gebucht, sondern im Budget: Ausgaben und Einnahmen
 * tragen den Bezug zum Einsatz (expenses.einsatz_id), der Einsatz zeigt,
 * was er gekostet und eingebracht hat.
 */

const EINSATZ_STATUS = [
    'laufend'       => ['label' => 'Läuft',                       'farbe' => '#b91c1c'],
    'beendet'       => ['label' => 'Beendet – Abrechnung offen',  'farbe' => '#b45309'],
    'abgerechnet'   => ['label' => 'Abgerechnet',                 'farbe' => '#0369a1'],
    'abgeschlossen' => ['label' => 'Abgeschlossen',               'farbe' => '#15803d'],
];
/** So viele Zeilen (Tage, Schichten) nimmt ein Einsatz höchstens an */
const EINSATZ_TAGE_MAX = 400;
const EINSATZ_WOCHENTAGE = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

function einsatz_status(string $status): string
{
    return array_key_exists($status, EINSATZ_STATUS) ? $status : 'laufend';
}

function einsatz_status_label(string $status): string
{
    return EINSATZ_STATUS[einsatz_status($status)]['label'];
}

/** Abzeichen in der Farbe des Stands. Reine Funktion. */
function einsatz_status_badge(string $status): string
{
    $s = EINSATZ_STATUS[einsatz_status($status)];
    return '<span class="badge" style="background:' . $s['farbe'] . '">' . e($s['label']) . '</span>';
}

/* ==================================================================== */
/* Tage, Stunden, Stärke – reine Funktionen                              */
/* ==================================================================== */

/** „7:5" oder „07:05:00" zu „07:05"; leer bleibt null, Unsinn wird false. Reine Funktion. */
function einsatz_zeit(mixed $roh): string|false|null
{
    $roh = trim((string)$roh);
    if ($roh === '') {
        return null;
    }
    if (!preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', $roh, $m) || (int)$m[1] > 23 || (int)$m[2] > 59) {
        return false;
    }
    return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
}

/**
 * Die Zeilen aus dem Formular lesen. Erwartet die Spalten als gleich lange
 * Listen: tag_datum[], tag_von[], tag_bis[], tag_fachgruppe[], tag_staerke[],
 * tag_taetigkeit[] – dazu tag_key[] mit einer Kennung je Zeile, unter der
 * ihre Fahrzeuge stehen: tag_fz[kennung][], die gefahrenen Kilometer
 * tag_km[kennung][fahrzeug] und die Betriebsstunden tag_bh[kennung][fahrzeug].
 * Wer Kilometer oder Betriebsstunden einträgt, hat das Fahrzeug gewählt.
 * Ganz leere Zeilen fallen weg; sortiert wird nach Datum und Beginn.
 * Gibt [tage, fehler] zurück. Reine Funktion über das übergebene Feld.
 */
function einsatz_tage_aus_post(array $post): array
{
    $tage = [];
    $fehler = [];
    $spalte = static fn(string $name): array => array_values((array)($post[$name] ?? []));
    $datum = $spalte('tag_datum');
    $von = $spalte('tag_von');
    $bis = $spalte('tag_bis');
    $staerke = $spalte('tag_staerke');
    $taetigkeit = $spalte('tag_taetigkeit');
    $fachgruppe = $spalte('tag_fachgruppe');
    $kennung = $spalte('tag_key');
    $fzJeZeile = (array)($post['tag_fz'] ?? []);
    $kmJeZeile = (array)($post['tag_km'] ?? []);
    $bhJeZeile = (array)($post['tag_bh'] ?? []);
    $zeilen = max(count($datum), count($von), count($bis), count($staerke), count($taetigkeit), count($fachgruppe));

    for ($i = 0; $i < $zeilen; $i++) {
        $d = trim((string)($datum[$i] ?? ''));
        $v = trim((string)($von[$i] ?? ''));
        $b = trim((string)($bis[$i] ?? ''));
        $s = trim((string)($staerke[$i] ?? ''));
        $t = trim((string)($taetigkeit[$i] ?? ''));
        $fg = (int)($fachgruppe[$i] ?? 0);
        $schluessel = (string)($kennung[$i] ?? '');
        $fz = array_values(array_unique(array_filter(array_map('intval', (array)($fzJeZeile[$schluessel] ?? [])), static fn($x) => $x > 0)));
        $km = [];
        $bh = [];
        foreach ([[$kmJeZeile, &$km, 99999.9], [$bhJeZeile, &$bh, 9999.9]] as [$quelle, &$ziel, $grenze]) {
            foreach ((array)($quelle[$schluessel] ?? []) as $vid => $wert) {
                $zahl = einsatz_km($wert);
                if ((int)$vid > 0 && $zahl !== null) {
                    $ziel[(int)$vid] = min($grenze, $zahl);
                    if (!in_array((int)$vid, $fz, true)) {
                        $fz[] = (int)$vid;
                    }
                }
            }
            unset($ziel);
        }
        if ($d === '' && $v === '' && $b === '' && $s === '' && $t === '' && $fg <= 0 && !$fz) {
            continue;
        }
        $nr = 'Zeile ' . (count($tage) + count($fehler) + 1);
        $geprueft = einsatz_zeile_pruefen($nr, $d, $v, $b, $s);
        if (is_string($geprueft)) {
            $fehler[] = $geprueft;
            continue;
        }
        [$zv, $zb, $st] = $geprueft;
        $tage[] = [
            'datum' => $d, 'von' => $zv, 'bis' => $zb,
            'fachgruppe_id' => $fg > 0 ? $fg : null,
            'staerke_f' => (int)$st[0], 'staerke_u' => (int)$st[1], 'staerke_h' => (int)$st[2],
            'taetigkeit' => mb_substr($t, 0, 500),
            'vehicles' => $fz,
            'km' => $km,
            'bh' => $bh,
        ];
    }
    if (count($tage) > EINSATZ_TAGE_MAX) {
        $fehler[] = sprintf('Ein Einsatz nimmt höchstens %d Zeilen an.', EINSATZ_TAGE_MAX);
    }
    usort($tage, static fn($x, $y) => [$x['datum'], (string)$x['von']] <=> [$y['datum'], (string)$y['von']]);
    return [$tage, $fehler];
}

/**
 * Datum, Uhrzeiten und Stärke einer Formularzeile prüfen – für eigene wie für fremde Kräfte.
 * Gibt [von, bis, [f, u, h]] zurück oder den Fehlertext. Reine Funktion.
 */
function einsatz_zeile_pruefen(string $nr, string $d, string $v, string $b, string $s): array|string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return $d === '' ? $nr . ': Bitte ein Datum angeben.' : $nr . ': „' . $d . '" ist kein gültiges Datum.';
    }
    $zv = einsatz_zeit($v);
    $zb = einsatz_zeit($b);
    if ($zv === false || $zb === false) {
        return sprintf('%s (%s): Uhrzeiten bitte als hh:mm angeben.', $nr, de_date($d));
    }
    if ($zv === null && $zb !== null) {
        return sprintf('%s (%s): Zum Ende fehlt der Beginn.', $nr, de_date($d));
    }
    // dieselbe Schreibweise wie auf den Karten: „1/2/9", „1/2/9/12" oder eine einzelne Zahl
    $st = karten_staerke_parsen($s);
    if ($st === null) {
        return sprintf('%s (%s): Stärke bitte als Führer/Unterführer/Helfer angeben, etwa 1/2/9 – oder als einzelne Zahl.', $nr, de_date($d));
    }
    return [$zv, $zb, $st];
}

/**
 * Die fremden Kräfte aus dem Formular: fremd_datum[], fremd_von[], fremd_bis[],
 * fremd_einheit[] (Freitext), fremd_staerke[] und fremd_symbol[] (taktisches Zeichen
 * für die Karte). Ganz leere Zeilen fallen weg. Gibt [zeilen, fehler] zurück.
 * Reine Funktion über das übergebene Feld.
 */
function einsatz_fremde_aus_post(array $post): array
{
    $zeilen = [];
    $fehler = [];
    $spalte = static fn(string $name): array => array_values((array)($post[$name] ?? []));
    $einheit = $spalte('fremd_einheit');
    $datum = $spalte('fremd_datum');
    $von = $spalte('fremd_von');
    $bis = $spalte('fremd_bis');
    $staerke = $spalte('fremd_staerke');
    $symbol = $spalte('fremd_symbol');
    for ($i = 0, $n = max(count($einheit), count($datum), count($von), count($bis), count($staerke)); $i < $n; $i++) {
        $name = mb_substr(trim((string)preg_replace('/\s+/u', ' ', (string)($einheit[$i] ?? ''))), 0, 150);
        $d = trim((string)($datum[$i] ?? ''));
        $v = trim((string)($von[$i] ?? ''));
        $b = trim((string)($bis[$i] ?? ''));
        $s = trim((string)($staerke[$i] ?? ''));
        if ($name === '' && $d === '' && $v === '' && $b === '' && $s === '') {
            continue;
        }
        $nr = 'Fremde Kräfte, Zeile ' . (count($zeilen) + count($fehler) + 1);
        if ($name === '') {
            $fehler[] = $nr . ': Bitte die Einheit nennen.';
            continue;
        }
        $geprueft = einsatz_zeile_pruefen($nr, $d, $v, $b, $s);
        if (is_string($geprueft)) {
            $fehler[] = $geprueft;
            continue;
        }
        [$zv, $zb, $st] = $geprueft;
        $zeilen[] = ['datum' => $d, 'von' => $zv, 'bis' => $zb, 'einheit' => $name,
            'symbol_id' => (int)($symbol[$i] ?? 0) > 0 ? (int)$symbol[$i] : null,
            'staerke_f' => (int)$st[0], 'staerke_u' => (int)$st[1], 'staerke_h' => (int)$st[2]];
    }
    if (count($zeilen) > EINSATZ_TAGE_MAX) {
        $fehler[] = sprintf('Ein Einsatz nimmt höchstens %d Zeilen mit fremden Kräften an.', EINSATZ_TAGE_MAX);
    }
    usort($zeilen, static fn($x, $y) => [$x['datum'], (string)$x['von']] <=> [$y['datum'], (string)$y['von']]);
    return [$zeilen, $fehler];
}

/**
 * Die fremden Einheiten eines Einsatzes zur Auswahl auf der Karte: jede Einheit einmal,
 * mit Stärke und Zeichen ihrer jüngsten Zeile. Erwartet die Zeilen in zeitlicher
 * Reihenfolge (einsatz_fremde). Reine Funktion.
 */
function einsatz_fremde_vorauswahl(array $zeilen): array
{
    $out = [];
    foreach ($zeilen as $z) {
        $name = trim((string)($z['einheit'] ?? ''));
        if ($name === '') {
            continue;
        }
        $st = einsatz_tag_staerke($z);
        $out[mb_strtolower($name)] = ['name' => $name, 'staerke' => $st['gesamt'] > 0 ? $st['f'] . '/' . $st['u'] . '/' . $st['h'] : '',
            'symbol' => (int)($z['symbol_id'] ?? 0)];
    }
    return array_values($out);
}

/**
 * Gefahrene Kilometer oder Betriebsstunden aus dem Formular: „12,5" oder „12.5", auch mit
 * Einheit („40 km", „3,5 h", „3,5 Bh"); leer, null oder Unsinn ist null. Reine Funktion.
 */
function einsatz_km(mixed $roh): ?float
{
    $roh = (string)preg_replace('/\s+|km|bh|std\.?|h$/iu', '', trim((string)$roh));
    // deutsche Schreibweise: Punkt als Tausendertrenner nur, wenn auch ein Komma da ist
    $roh = str_contains($roh, ',') ? str_replace(',', '.', str_replace('.', '', $roh)) : $roh;
    if ($roh === '' || !is_numeric($roh) || (float)$roh <= 0) {
        return null;
    }
    return min(99999.9, round((float)$roh, 1));
}

/** Kilometer als Text: „12,5 km", „340 km". Reine Funktion. */
function einsatz_km_text(float $km): string
{
    return rtrim(rtrim(number_format($km, 1, ',', '.'), '0'), ',') . ' km';
}

/** Betriebsstunden als Text: „3,5 Bh", „12 Bh". Reine Funktion. */
function einsatz_bh_text(float $bh): string
{
    return rtrim(rtrim(number_format($bh, 1, ',', '.'), '0'), ',') . ' Bh';
}

/** „42 km · 3,5 Bh" – was an einem Fahrzeug eingetragen ist; leer, wenn nichts. Reine Funktion. */
function einsatz_fahrzeug_leistung(array $v): string
{
    $teile = [];
    if (($v['km'] ?? null) !== null && (float)$v['km'] > 0) {
        $teile[] = einsatz_km_text((float)$v['km']);
    }
    if (($v['bh'] ?? null) !== null && (float)$v['bh'] > 0) {
        $teile[] = einsatz_bh_text((float)$v['bh']);
    }
    return implode(' · ', $teile);
}

/**
 * Dauer einer Zeile in Minuten – null, solange Beginn oder Ende fehlt.
 * Liegt das Ende nicht nach dem Beginn, endet die Schicht am Folgetag
 * (22:00–06:00 sind acht Stunden, 08:00–08:00 ein ganzer Tag). Reine Funktion.
 */
function einsatz_tag_minuten(array $tag): ?int
{
    $von = einsatz_zeit($tag['von'] ?? '');
    $bis = einsatz_zeit($tag['bis'] ?? '');
    if (!is_string($von) || !is_string($bis)) {
        return null;
    }
    $min = ((int)substr($bis, 0, 2) * 60 + (int)substr($bis, 3, 2)) - ((int)substr($von, 0, 2) * 60 + (int)substr($von, 3, 2));
    return $min <= 0 ? $min + 1440 : $min;
}

/** Stärke einer Zeile: f, u, h, gesamt und der Text „1/2/9/12". Reine Funktion. */
function einsatz_tag_staerke(array $tag): array
{
    $f = max(0, (int)($tag['staerke_f'] ?? 0));
    $u = max(0, (int)($tag['staerke_u'] ?? 0));
    $h = max(0, (int)($tag['staerke_h'] ?? 0));
    return ['f' => $f, 'u' => $u, 'h' => $h, 'gesamt' => $f + $u + $h, 'text' => $f . '/' . $u . '/' . $h . '/' . ($f + $u + $h)];
}

/** Beginn und Ende einer Zeile als Zeitpunkte. Ohne Uhrzeit gilt der ganze Tag. Reine Funktion. */
function einsatz_tag_zeitraum(array $tag): array
{
    $datum = (string)$tag['datum'];
    $von = einsatz_zeit($tag['von'] ?? '');
    $bis = einsatz_zeit($tag['bis'] ?? '');
    $beginn = $datum . ' ' . (is_string($von) ? $von : '00:00') . ':00';
    if (is_string($von) && is_string($bis)) {
        $endeTag = $bis <= $von ? date('Y-m-d', strtotime($datum . ' 12:00:00 +1 day')) : $datum;
        return ['beginn' => $beginn, 'ende' => $endeTag . ' ' . $bis . ':00'];
    }
    // Ende offen (läuft noch) oder ganz ohne Uhrzeit: bis zum Ende des Tages
    return ['beginn' => $beginn, 'ende' => $datum . ' 23:59:59'];
}

/**
 * Die Zahlen eines Einsatzes aus seinen Tagen: Einsatztage (verschiedene
 * Daten), Zeilen, Minuten, Helferminuten (Dauer mal Stärke), größte Stärke,
 * Zeilen ohne Ende und der Zeitraum. Reine Funktion.
 */
function einsatz_summen(array $tage): array
{
    $out = ['tage' => 0, 'zeilen' => count($tage), 'minuten' => 0, 'helferminuten' => 0, 'staerke_max' => 0,
            'staerke_max_text' => '', 'ohne_dauer' => 0, 'beginn' => null, 'ende' => null];
    $daten = [];
    foreach ($tage as $t) {
        $daten[(string)$t['datum']] = true;
        $st = einsatz_tag_staerke($t);
        if ($st['gesamt'] > $out['staerke_max']) {
            $out['staerke_max'] = $st['gesamt'];
            $out['staerke_max_text'] = $st['text'];
        }
        $min = einsatz_tag_minuten($t);
        if ($min === null) {
            $out['ohne_dauer']++;
        } else {
            $out['minuten'] += $min;
            $out['helferminuten'] += $min * $st['gesamt'];
        }
        $z = einsatz_tag_zeitraum($t);
        if ($out['beginn'] === null || $z['beginn'] < $out['beginn']) {
            $out['beginn'] = $z['beginn'];
        }
        if ($out['ende'] === null || $z['ende'] > $out['ende']) {
            $out['ende'] = $z['ende'];
        }
    }
    $out['tage'] = count($daten);
    return $out;
}

/**
 * Dieselben Zahlen je Fachgruppe: [fachgruppe_id => label, tage, zeilen, minuten, helferminuten],
 * Zeilen ohne Fachgruppe unter 0. Größte Helferstunden zuerst. Reine Funktion über Zeilen
 * mit fachgruppe_id und fachgruppe_label.
 */
function einsatz_summen_je_fachgruppe(array $tage): array
{
    $out = [];
    $daten = [];
    foreach ($tage as $t) {
        $id = (int)($t['fachgruppe_id'] ?? 0);
        $out[$id] ??= ['label' => $id > 0 ? (string)($t['fachgruppe_label'] ?? ('Fachgruppe #' . $id)) : 'ohne Fachgruppe',
                       'tage' => 0, 'zeilen' => 0, 'minuten' => 0, 'helferminuten' => 0];
        $out[$id]['zeilen']++;
        $daten[$id][(string)$t['datum']] = true;
        $min = einsatz_tag_minuten($t);
        if ($min !== null) {
            $out[$id]['minuten'] += $min;
            $out[$id]['helferminuten'] += $min * einsatz_tag_staerke($t)['gesamt'];
        }
    }
    foreach ($out as $id => $_) {
        $out[$id]['tage'] = count($daten[$id]);
    }
    uasort($out, static fn($a, $b) => [$b['helferminuten'], $b['minuten'], $a['label']] <=> [$a['helferminuten'], $a['minuten'], $b['label']]);
    return $out;
}

/**
 * Einsatzzeit, gefahrene Kilometer und Betriebsstunden je Fahrzeug: [vehicle_id => bezeichnung,
 * tage, zeilen, minuten, km, bh], längste zuerst. Reine Funktion über Zeilen mit
 * fahrzeuge = [[id, bezeichnung, km, bh], …].
 */
function einsatz_summen_je_fahrzeug(array $tage): array
{
    $out = [];
    $daten = [];
    foreach ($tage as $t) {
        $min = einsatz_tag_minuten($t);
        foreach ((array)($t['fahrzeuge'] ?? []) as $v) {
            $id = (int)$v['id'];
            $out[$id] ??= ['bezeichnung' => (string)($v['bezeichnung'] ?? ('Fahrzeug #' . $id)), 'tage' => 0, 'zeilen' => 0, 'minuten' => 0, 'km' => 0.0, 'bh' => 0.0];
            $out[$id]['zeilen']++;
            $out[$id]['minuten'] += (int)$min;
            $out[$id]['km'] = round($out[$id]['km'] + (float)($v['km'] ?? 0), 1);
            $out[$id]['bh'] = round($out[$id]['bh'] + (float)($v['bh'] ?? 0), 1);
            $daten[$id][(string)$t['datum']] = true;
        }
    }
    foreach ($out as $id => $_) {
        $out[$id]['tage'] = count($daten[$id]);
    }
    uasort($out, static fn($a, $b) => [$b['minuten'], $a['bezeichnung']] <=> [$a['minuten'], $b['bezeichnung']]);
    return $out;
}

/** Minuten als Stunden: „8 h", „7,5 h", „12,25 h". Reine Funktion. */
function einsatz_stunden_text(int $minuten): string
{
    $h = rtrim(rtrim(number_format($minuten / 60, 2, ',', '.'), '0'), ',');
    return $h . ' h';
}

/** „14.11.2026" oder „14.11.2026 – 16.11.2026". Reine Funktion. */
function einsatz_zeitraum_text(array $e): string
{
    $von = substr((string)($e['beginn'] ?? ''), 0, 10);
    $bis = substr((string)(($e['ende'] ?? '') ?: ($e['beginn'] ?? '')), 0, 10);
    if ($von === '') {
        return '';
    }
    return $bis !== '' && $bis !== $von ? de_date($von) . ' – ' . de_date($bis) : de_date($von);
}

/** „Sa 14.11.2026". Reine Funktion. */
function einsatz_tag_text(string $datum): string
{
    $ts = strtotime($datum . ' 12:00:00');
    return $ts ? EINSATZ_WOCHENTAGE[(int)date('w', $ts)] . ' ' . date('d.m.Y', $ts) : $datum;
}

/** „2026-014 · Hochwasser Hunte" – Nummer nur, wenn es eine gibt. Reine Funktion. */
function einsatz_bezeichnung(array $e): string
{
    $nr = trim((string)($e['nummer'] ?? ''));
    return ($nr !== '' ? $nr . ' · ' : '') . (string)($e['titel'] ?? '');
}

/**
 * Die nächste freie Einsatznummer des Jahres nach dem Muster JJJJ-001.
 * Nummern in anderer Schreibweise zählen nicht mit. Reine Funktion.
 */
function einsatz_naechste_nummer(int $jahr, array $vorhandene): string
{
    $max = 0;
    foreach ($vorhandene as $nr) {
        if (preg_match('/^' . $jahr . '-(\d{1,6})$/', trim((string)$nr), $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return sprintf('%d-%03d', $jahr, $max + 1);
}

function einsatz_nummer_vorschlag(int $jahr): string
{
    return einsatz_naechste_nummer($jahr, array_column(db_all('SELECT nummer FROM einsaetze WHERE jahr = ?', [$jahr]), 'nummer'));
}

/* ==================================================================== */
/* Geld – reine Funktionen                                               */
/* ==================================================================== */

/**
 * Was ein Einsatz gekostet und eingebracht hat, aus seinen Buchungen.
 * Ist zählt wie im Budget: bezahlt und gebucht. Daneben stehen geplante
 * Ausgaben und Einnahmen, die noch unterwegs sind (erwartet, abgerechnet,
 * gestellt, zugesagt). Reine Funktion.
 */
function einsatz_geld_aus(array $buchungen): array
{
    $out = ['buchungen' => count($buchungen), 'ausgaben' => 0.0, 'ausgaben_geplant' => 0.0,
            'einnahmen' => 0.0, 'einnahmen_offen' => 0.0];
    foreach ($buchungen as $b) {
        $betrag = (float)($b['betrag_brutto'] ?? 0);
        $ist = in_array((string)($b['status'] ?? 'bezahlt'), ['bezahlt', 'offen'], true);
        if ((string)($b['art'] ?? 'ausgabe') === 'einnahme') {
            $out[$ist ? 'einnahmen' : 'einnahmen_offen'] += $betrag;
        } else {
            $out[$ist ? 'ausgaben' : 'ausgaben_geplant'] += $betrag;
        }
    }
    foreach (['ausgaben', 'ausgaben_geplant', 'einnahmen', 'einnahmen_offen'] as $k) {
        $out[$k] = round($out[$k], 2);
    }
    $out['saldo'] = round($out['einnahmen'] - $out['ausgaben'], 2);
    $out['saldo_erwartet'] = round($out['einnahmen'] + $out['einnahmen_offen'] - $out['ausgaben'] - $out['ausgaben_geplant'], 2);
    return $out;
}

/**
 * Vorbelegung einer Buchung, die vom Einsatz aus angelegt wird: Bezug,
 * Einsatznummer als Referenz, Fachgruppe; bei Einnahmen dazu Bezeichnung,
 * Kostenträger, die Kategorie „Einsatzkostenerstattung" und der Stand
 * „erwartet". $kategorieId reicht der Aufrufer herein. Reine Funktion.
 */
function einsatz_buchung_vorgabe(array $einsatz, string $art, ?int $kategorieId = null): array
{
    $out = [
        'einsatz_id'    => (int)$einsatz['id'],
        'referenz'      => mb_substr(trim((string)($einsatz['nummer'] ?? '')), 0, 100),
        'fachgruppe_id' => !empty($einsatz['fachgruppe_id']) ? (int)$einsatz['fachgruppe_id'] : null,
    ];
    if ($art === 'einnahme') {
        $out['bezeichnung'] = mb_substr('Kostenerstattung ' . einsatz_bezeichnung($einsatz), 0, 200);
        $out['lieferant'] = mb_substr(trim((string)($einsatz['anforderer'] ?? '')), 0, 150);
        $out['status'] = 'geplant';
        if ($kategorieId) {
            $out['kategorie_id'] = $kategorieId;
        }
    }
    return $out;
}

/* ==================================================================== */
/* Lesen                                                                 */
/* ==================================================================== */

/** Unterabfrage „GKW 1 · MTW" für Liste, Seite und Kalender. Reine Funktion. */
function einsatz_fahrzeuge_sub(string $alias = 'e'): string
{
    return "(SELECT GROUP_CONCAT(v.bezeichnung ORDER BY v.bezeichnung SEPARATOR ' · ') FROM einsatz_vehicles ev JOIN vehicles v ON v.id = ev.vehicle_id WHERE ev.einsatz_id = $alias.id)";
}

function einsatz_find(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    return db_row(
        'SELECT e.*, a.label AS art_label, a.color AS art_color, fg.label AS fachgruppe_label,
                u.display_name AS ersteller, ev.titel AS event_titel, ' . einsatz_fahrzeuge_sub('e') . ' AS fahrzeuge_namen
         FROM einsaetze e
         LEFT JOIN list_items a  ON a.id  = e.art_id
         LEFT JOIN list_items fg ON fg.id = e.fachgruppe_id
         LEFT JOIN users      u  ON u.id  = e.created_by
         LEFT JOIN events     ev ON ev.id = e.event_id
         WHERE e.id = ?',
        [$id]
    );
}

/**
 * Liste der Einsätze, jüngste zuerst.
 * $f: jahr, status, art_id, q, laufend (true = nur laufende, false = alle anderen), limit
 */
function einsatz_query(array $f = []): array
{
    $w = [];
    $p = [];
    if (!empty($f['jahr'])) {
        $w[] = 'e.jahr = ?';
        $p[] = (int)$f['jahr'];
    }
    if (!empty($f['status'])) {
        $w[] = 'e.status = ?';
        $p[] = einsatz_status((string)$f['status']);
    }
    if (!empty($f['art_id'])) {
        $w[] = 'e.art_id = ?';
        $p[] = (int)$f['art_id'];
    }
    if (!empty($f['q'])) {
        $w[] = '(e.titel LIKE ? OR e.nummer LIKE ? OR e.ort LIKE ? OR e.anforderer LIKE ? OR e.beschreibung LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like, $like);
    }
    if (array_key_exists('laufend', $f) && $f['laufend'] !== null) {
        $w[] = $f['laufend'] ? "e.status = 'laufend'" : "e.status <> 'laufend'";
    }
    $ist = defined('BUCHUNG_IST') ? BUCHUNG_IST : "status IN ('bezahlt','offen')";
    $sql = 'SELECT e.*, a.label AS art_label, a.color AS art_color, fg.label AS fachgruppe_label,
                   ' . einsatz_fahrzeuge_sub('e') . ' AS fahrzeuge_namen,
                   (SELECT COUNT(DISTINCT t.datum) FROM einsatz_tage t WHERE t.einsatz_id = e.id) AS tage,
                   (SELECT COALESCE(MAX(t.staerke_f + t.staerke_u + t.staerke_h), 0) FROM einsatz_tage t WHERE t.einsatz_id = e.id) AS staerke_max,
                   (SELECT COALESCE(SUM(x.betrag_brutto), 0) FROM expenses x WHERE x.einsatz_id = e.id AND x.art = \'ausgabe\' AND x.' . $ist . ') AS ausgaben,
                   (SELECT COALESCE(SUM(x.betrag_brutto), 0) FROM expenses x WHERE x.einsatz_id = e.id AND x.art = \'einnahme\' AND x.' . $ist . ') AS einnahmen
            FROM einsaetze e
            LEFT JOIN list_items a  ON a.id  = e.art_id
            LEFT JOIN list_items fg ON fg.id = e.fachgruppe_id'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY e.beginn DESC, e.id DESC';
    if (!empty($f['limit'])) {
        $sql .= ' LIMIT ' . (int)$f['limit'];
    }
    return db_all($sql, $p);
}

/** Jahre, zu denen es Einsätze gibt – immer mit dem laufenden Jahr */
function einsatz_years(): array
{
    $jahre = array_map(static fn($r) => (int)$r['jahr'], db_all('SELECT DISTINCT jahr FROM einsaetze ORDER BY jahr DESC'));
    $aktuell = (int)date('Y');
    if (!in_array($aktuell, $jahre, true)) {
        $jahre[] = $aktuell;
        rsort($jahre);
    }
    return $jahre;
}

/**
 * Die Zeilen eines Einsatzes in zeitlicher Reihenfolge – je Zeile mit Fachgruppe
 * (fachgruppe_label, fachgruppe_color) und ihren Fahrzeugen: vehicles (ids), km
 * ([id => Kilometer]), bh ([id => Betriebsstunden]) und fahrzeuge ([id, bezeichnung,
 * funkrufname, km, bh]).
 */
function einsatz_tage(int $einsatzId): array
{
    $tage = db_all('SELECT t.*, fg.label AS fachgruppe_label, fg.color AS fachgruppe_color
                    FROM einsatz_tage t LEFT JOIN list_items fg ON fg.id = t.fachgruppe_id
                    WHERE t.einsatz_id = ? ORDER BY t.datum, t.von, t.id', [$einsatzId]);
    $jeZeile = [];
    foreach (db_all('SELECT tv.tag_id, tv.km, tv.betriebsstunden, v.id, v.bezeichnung, v.funkrufname FROM einsatz_tag_vehicles tv
                     JOIN einsatz_tage t ON t.id = tv.tag_id JOIN vehicles v ON v.id = tv.vehicle_id
                     WHERE t.einsatz_id = ? ORDER BY v.bezeichnung', [$einsatzId]) as $r) {
        $jeZeile[(int)$r['tag_id']][] = ['id' => (int)$r['id'], 'bezeichnung' => (string)$r['bezeichnung'], 'funkrufname' => (string)($r['funkrufname'] ?? ''),
            'km' => $r['km'] !== null && $r['km'] !== '' ? (float)$r['km'] : null,
            'bh' => ($r['betriebsstunden'] ?? null) !== null && $r['betriebsstunden'] !== '' ? (float)$r['betriebsstunden'] : null];
    }
    foreach ($tage as &$t) {
        $t['fahrzeuge'] = $jeZeile[(int)$t['id']] ?? [];
        $t['vehicles'] = array_column($t['fahrzeuge'], 'id');
        $t['km'] = array_filter(array_column($t['fahrzeuge'], 'km', 'id'), static fn($k) => $k !== null);
        $t['bh'] = array_filter(array_column($t['fahrzeuge'], 'bh', 'id'), static fn($k) => $k !== null);
    }
    unset($t);
    return $tage;
}

/** Buchungen eines Einsatzes, jüngste zuerst */
function einsatz_buchungen(int $einsatzId): array
{
    return db_all(
        'SELECT e.*, ka.label AS kategorie_label, ka.color AS kategorie_color, b.name AS budget_name
         FROM expenses e
         LEFT JOIN list_items ka ON ka.id = e.kategorie_id
         LEFT JOIN budgets    b  ON b.id  = e.budget_id
         WHERE e.einsatz_id = ?
         ORDER BY e.datum DESC, e.id DESC',
        [$einsatzId]
    );
}

/**
 * Einsätze zur Auswahl im Buchungsformular: die jüngsten, dazu der schon
 * eingetragene, auch wenn er weiter zurückliegt.
 */
function einsatz_auswahl(?int $immer = null, int $limit = 200): array
{
    $rows = db_all('SELECT id, nummer, titel, beginn, ende FROM einsaetze ORDER BY beginn DESC, id DESC LIMIT ' . max(1, $limit));
    if ($immer && !in_array($immer, array_map('intval', array_column($rows, 'id')), true)) {
        $r = db_row('SELECT id, nummer, titel, beginn, ende FROM einsaetze WHERE id = ?', [$immer]);
        if ($r) {
            $rows[] = $r;
        }
    }
    return $rows;
}

/* ==================================================================== */
/* Schreiben                                                             */
/* ==================================================================== */

/** Einsatz aus dem Formular speichern. Gibt [id, fehler[]] zurück. */
function einsatz_save_from_post(?array $e, array $user): array
{
    $fehler = [];
    $titel = mb_substr(post_str('titel'), 0, 200);
    if ($titel === '') {
        $fehler[] = 'Bitte einen Titel angeben – das Einsatzstichwort oder eine kurze Beschreibung.';
    }
    [$tage, $tagFehler] = einsatz_tage_aus_post($_POST);
    $fehler = array_merge($fehler, $tagFehler);
    if (!$tage && !$tagFehler) {
        $fehler[] = 'Bitte mindestens einen Einsatztag eintragen.';
    }
    [$fremde, $fremdFehler] = einsatz_fremde_aus_post($_POST);
    $fehler = array_merge($fehler, $fremdFehler);
    if ($fehler) {
        return [null, $fehler];
    }

    $summen = einsatz_summen($tage);
    $daten = [
        'nummer'        => mb_substr(post_str('nummer'), 0, 40),
        'titel'         => $titel,
        'art_id'        => post_int('art_id'),
        'status'        => einsatz_status(post_str('status', (string)($e['status'] ?? 'laufend'))),
        'anforderer'    => mb_substr(post_str('anforderer'), 0, 200),
        'ort'           => mb_substr(post_str('ort'), 0, 200),
        'beginn'        => $summen['beginn'],
        'ende'          => $summen['ende'],
        'jahr'          => (int)substr((string)$summen['beginn'], 0, 4),
        'fachgruppe_id' => post_int('fachgruppe_id'),
        'beschreibung'  => post_str('beschreibung'),
        'notiz'         => post_str('notiz'),
        'updated_by'    => (int)$user['id'],
    ];
    $detail = einsatz_bezeichnung($daten) . ' / ' . ($summen['tage'] === 1 ? '1 Tag' : $summen['tage'] . ' Tage');
    // Kopf und Zeilen ganz oder gar nicht: scheitert eine Zeile, bleiben die alten stehen
    db()->beginTransaction();
    try {
        if ($e) {
            $id = (int)$e['id'];
            db_update('einsaetze', $daten, 'id = ?', [$id]);
            audit('einsatz.bearbeitet', 'einsatz', $id, $detail);
        } else {
            $daten['created_by'] = (int)$user['id'];
            $id = db_insert('einsaetze', $daten);
            audit('einsatz.angelegt', 'einsatz', $id, $detail);
        }
        einsatz_tage_setzen($id, $tage);
        // Ohne die Felder im Formular (ältere Seite, anderer Aufrufer) bleiben die fremden Kräfte, wie sie sind
        if (array_key_exists('fremd_einheit', $_POST)) {
            einsatz_fremde_setzen($id, $fremde);
        }
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    return [$id, []];
}

/**
 * Genau diese Zeilen gehören zum Einsatz – samt ihren Fahrzeugen. Die Fahrzeuge des
 * Einsatzes (für Liste und Kalender) sind die Summe aus allen Zeilen.
 */
function einsatz_tage_setzen(int $einsatzId, array $tage): void
{
    db_exec('DELETE FROM einsatz_tage WHERE einsatz_id = ?', [$einsatzId]);   // die Fahrzeuge der Zeilen gehen mit
    $alle = [];
    foreach ($tage as $t) {
        $tagId = db_insert('einsatz_tage', [
            'einsatz_id' => $einsatzId,
            'datum'      => (string)$t['datum'],
            'von'        => $t['von'] !== null ? $t['von'] . ':00' : null,
            'bis'        => $t['bis'] !== null ? $t['bis'] . ':00' : null,
            'fachgruppe_id' => !empty($t['fachgruppe_id']) ? (int)$t['fachgruppe_id'] : null,
            'staerke_f'  => (int)$t['staerke_f'],
            'staerke_u'  => (int)$t['staerke_u'],
            'staerke_h'  => (int)$t['staerke_h'],
            'taetigkeit' => (string)$t['taetigkeit'],
        ]);
        foreach (array_values(array_unique(array_filter(array_map('intval', (array)($t['vehicles'] ?? [])), static fn($i) => $i > 0))) as $vid) {
            db_insert('einsatz_tag_vehicles', ['tag_id' => $tagId, 'vehicle_id' => $vid, 'km' => $t['km'][$vid] ?? null, 'betriebsstunden' => $t['bh'][$vid] ?? null]);
            $alle[] = $vid;
        }
    }
    einsatz_fahrzeuge_setzen($einsatzId, $alle);
}

/** Die fremden Kräfte eines Einsatzes in zeitlicher Reihenfolge – mit dem Namen ihres Zeichens (symbol_name) */
function einsatz_fremde(int $einsatzId): array
{
    return db_all('SELECT f.*, s.name AS symbol_name FROM einsatz_fremde f LEFT JOIN karten_symbole s ON s.id = f.symbol_id
                   WHERE f.einsatz_id = ? ORDER BY f.datum, f.von, f.id', [$einsatzId]);
}

/** Genau diese fremden Kräfte gehören zum Einsatz – Zeilen aus einsatz_fremde_aus_post() */
function einsatz_fremde_setzen(int $einsatzId, array $zeilen): void
{
    db_exec('DELETE FROM einsatz_fremde WHERE einsatz_id = ?', [$einsatzId]);
    foreach ($zeilen as $z) {
        db_insert('einsatz_fremde', [
            'einsatz_id' => $einsatzId,
            'datum'      => (string)$z['datum'],
            'von'        => $z['von'] !== null ? $z['von'] . ':00' : null,
            'bis'        => $z['bis'] !== null ? $z['bis'] . ':00' : null,
            'einheit'    => (string)$z['einheit'],
            // ein inzwischen gelöschtes Zeichen soll das Speichern nicht scheitern lassen
            'symbol_id'  => !empty($z['symbol_id']) && karten_symbol_find((int)$z['symbol_id']) ? (int)$z['symbol_id'] : null,
            'staerke_f'  => (int)$z['staerke_f'],
            'staerke_u'  => (int)$z['staerke_u'],
            'staerke_h'  => (int)$z['staerke_h'],
        ]);
    }
}

/** Genau diese Fahrzeuge waren im Einsatz – gerechnet aus den Zeilen, siehe einsatz_tage_setzen() */
function einsatz_fahrzeuge_setzen(int $einsatzId, array $ids): void
{
    db_exec('DELETE FROM einsatz_vehicles WHERE einsatz_id = ?', [$einsatzId]);
    foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0))) as $vid) {
        db_insert('einsatz_vehicles', ['einsatz_id' => $einsatzId, 'vehicle_id' => $vid]);
    }
}

/**
 * Einsatz löschen. Tage, Fahrzeuge und Dokumente gehen mit; Buchungen bleiben im Budget,
 * Karten bleiben bestehen – beide verlieren nur den Bezug.
 */
function einsatz_delete(array $e): void
{
    // erst die Dateien von der Platte – die Zeilen nähme die Datenbank mit, die Dateien nicht
    einsatz_dateien_delete_alle((int)$e['id']);
    db_exec('UPDATE expenses SET einsatz_id = NULL WHERE einsatz_id = ?', [(int)$e['id']]);
    db_exec('DELETE FROM einsaetze WHERE id = ?', [(int)$e['id']]);
    audit('einsatz.geloescht', 'einsatz', (int)$e['id'], einsatz_bezeichnung($e));
}

/* ==================================================================== */
/* Dokumente am Einsatz                                                  */
/* ==================================================================== */

/**
 * Woher ein Dokument stammt: hochgeladen, als Einsatzauftrag (Fb Fü 3) im Browser
 * erzeugt oder als Lagekarte aus dem Kartenmodul gespeichert.
 */
const EINSATZ_DATEI_HERKUNFT = ['upload' => 'Dokument', 'auftrag' => 'Einsatzauftrag', 'karte' => 'Lagekarte'];
/** Die Unterzeilen des Vordrucks Fb Fü 3 (Abschnitte 1, 3 und 4) */
const EINSATZ_AUFTRAG_UNTERPUNKTE = [
    '1a' => 'Gefahren- / Schadenlage', '1b' => 'eigene Lage', '1c' => 'Unterstellung und Abgabe von Kräften',
    '3a' => 'Schwerpunkt, eigene Absicht', '3b' => 'Einzelaufträge',
    '3c' => 'Zusammenarbeit mit anderen Kräften und Koordinierung, Zeitangaben, Schutzmaßnahmen',
    '4a' => 'Verpflegung', '4b' => 'Verbrauchsgüter', '4c' => 'Betriebsstoff', '4d' => 'Instandsetzung',
    '4e' => 'Medizinische Versorgung', '4f' => 'Unterbringung', '4g' => 'Haushalt, Handkasse, Abrechnung',
];
/** Die übrigen Textfelder des Vordrucks: Feld => höchste Länge */
const EINSATZ_AUFTRAG_FELDER = [
    'absender' => 200, 'einsatz' => 200, 'laufendeNummer' => 20, 'empfaenger' => 200, 'auftrag' => 20000, 'fuehrung' => 20000,
    'ortDatum' => 120, 'unterschrift' => 120, 'dienststelle' => 120, 'abgesandtAm' => 40, 'abgesandtUm' => 40, 'abgesandtVon' => 120,
];

function einsatz_datei_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'einsaetze';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

/** Dokumente eines Einsatzes, neueste zuerst */
function einsatz_dateien(int $einsatzId): array
{
    return db_all('SELECT f.*, u.display_name AS hochgeladen_von, k.name AS karte_name
                   FROM einsatz_files f LEFT JOIN users u ON u.id = f.uploaded_by LEFT JOIN karten k ON k.id = f.karte_id
                   WHERE f.einsatz_id = ? ORDER BY f.created_at DESC, f.id DESC', [$einsatzId]);
}

function einsatz_datei_find(?int $id): ?array
{
    return $id ? db_row('SELECT f.*, e.titel AS einsatz_titel, e.nummer AS einsatz_nummer
                         FROM einsatz_files f JOIN einsaetze e ON e.id = f.einsatz_id WHERE f.id = ?', [$id]) : null;
}

/** Pfad zur Datei oder zur Vorschau – oder null */
function einsatz_datei_pfad(array $datei, bool $vorschau = false): ?string
{
    $name = $vorschau && ($datei['thumb_name'] ?? null) ? $datei['thumb_name'] : $datei['stored_name'];
    $pfad = einsatz_datei_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
    return is_file($pfad) ? $pfad : null;
}

/** Ist die Datei ein PDF? Entscheidend ist der Anfang der Datei, nicht der gemeldete Typ. */
function einsatz_ist_pdf(string $pfad, string $name): bool
{
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf' || !is_file($pfad)) {
        return false;
    }
    $h = @fopen($pfad, 'rb');
    $anfang = $h ? (string)fread($h, 5) : '';
    if ($h) {
        fclose($h);
    }
    return $anfang === '%PDF-';
}

/**
 * Dokumente an einen Einsatz hängen. Die Herkunft „auftrag" und „karte" sind im Browser
 * erzeugte PDFs – alles andere als ein PDF wird dort abgewiesen. $daten (Felder des
 * Einsatzauftrags) wird als JSON mitgespeichert. Gibt [anzahl, fehler[], ids[]] zurück.
 */
function einsatz_dateien_ablegen(int $einsatzId, string $feld, string $titel, array $user, string $herkunft = 'upload', ?array $daten = null, ?int $karteId = null): array
{
    $herkunft = isset(EINSATZ_DATEI_HERKUNFT[$herkunft]) ? $herkunft : 'upload';
    [$dateien, $fehler] = upload_dokumente_ablegen($feld, einsatz_datei_dir());
    $ids = [];
    foreach ($dateien as $d) {
        if ($herkunft !== 'upload' && !einsatz_ist_pdf(einsatz_datei_dir() . DIRECTORY_SEPARATOR . basename((string)$d['stored_name']), (string)$d['orig_name'])) {
            foreach ([$d['stored_name'], $d['thumb_name']] as $name) {
                if ($name) {
                    @unlink(einsatz_datei_dir() . DIRECTORY_SEPARATOR . basename((string)$name));
                }
            }
            $fehler[] = sprintf('Erwartet wurde ein PDF – „%s" ist keines.', $d['orig_name']);
            continue;
        }
        $id = db_insert('einsatz_files', [
            'einsatz_id'  => $einsatzId,
            'art'         => $d['art'],
            'herkunft'    => $herkunft,
            'titel'       => trim($titel) !== '' ? mb_substr(trim($titel), 0, 200) : $d['titel'],
            'orig_name'   => $d['orig_name'],
            'stored_name' => $d['stored_name'],
            'thumb_name'  => $d['thumb_name'],
            'mime'        => $herkunft !== 'upload' ? 'application/pdf' : $d['mime'],
            'size_bytes'  => $d['size_bytes'],
            'daten'       => $daten !== null ? (string)json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'karte_id'    => $karteId ?: null,
            'uploaded_by' => (int)$user['id'],
        ]);
        $ids[] = $id;
        audit('einsatz.datei_abgelegt', 'einsatz', $einsatzId, EINSATZ_DATEI_HERKUNFT[$herkunft] . ': ' . $d['orig_name']);
    }
    return [count($ids), $fehler, $ids];
}

function einsatz_datei_delete(array $datei): void
{
    foreach ([$datei['stored_name'] ?? '', $datei['thumb_name'] ?? ''] as $name) {
        if ($name) {
            $pfad = einsatz_datei_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
            if (is_file($pfad)) {
                @unlink($pfad);
            }
        }
    }
    db_exec('DELETE FROM einsatz_files WHERE id = ?', [(int)$datei['id']]);
    audit('einsatz.datei_entfernt', 'einsatz', (int)$datei['einsatz_id'], (string)($datei['titel'] ?: $datei['orig_name']));
}

/** Alle Dokumente eines Einsatzes von der Platte nehmen – bevor der Einsatz selbst gelöscht wird */
function einsatz_dateien_delete_alle(int $einsatzId): int
{
    $dateien = einsatz_dateien($einsatzId);
    foreach ($dateien as $f) {
        einsatz_datei_delete($f);
    }
    return count($dateien);
}

/* ---------------- Einsatzauftrag (Fb Fü 3) ---------------- */

/**
 * Die Felder eines Einsatzauftrags aus dem, was der Browser schickt (JSON-Text oder Feld):
 * nur bekannte Felder, als Text, in der Länge begrenzt. Reine Funktion.
 */
function einsatz_auftrag_daten(mixed $roh): array
{
    if (is_string($roh)) {
        $roh = json_decode($roh, true);
    }
    if (!is_array($roh)) {
        return [];
    }
    $text = static fn(mixed $v, int $max): string => mb_substr(str_replace(["\r\n", "\r"], "\n", is_scalar($v) ? (string)$v : ''), 0, $max);
    $out = [];
    foreach (EINSATZ_AUFTRAG_FELDER as $feld => $max) {
        $wert = $roh[$feld] ?? '';
        // Abschnitt 5 darf auch als Liste kommen: jede Zeile eine Formularzeile
        if ($feld === 'fuehrung' && is_array($wert)) {
            $wert = implode("\n\n", array_map(static fn($z) => is_scalar($z) ? (string)$z : '', $wert));
        }
        $wert = $text($wert, $max);
        if (trim($wert) !== '') {
            $out[$feld] = $wert;
        }
    }
    foreach ((array)($roh['unterpunkte'] ?? []) as $kennung => $wert) {
        if (isset(EINSATZ_AUFTRAG_UNTERPUNKTE[(string)$kennung]) && trim($text($wert, 20000)) !== '') {
            $out['unterpunkte'][(string)$kennung] = $text($wert, 20000);
        }
    }
    if (!empty($roh['uebung'])) {
        $out['uebung'] = true;
    }
    return $out;
}

/** Die nächste laufende Nummer: eins mehr als die höchste der schon gespeicherten Aufträge. Reine Funktion. */
function einsatz_auftrag_naechste_nr(array $dateien): string
{
    $max = 0;
    foreach ($dateien as $d) {
        if ((string)($d['herkunft'] ?? '') !== 'auftrag') {
            continue;
        }
        $daten = is_array($d['daten'] ?? null) ? $d['daten'] : (json_decode((string)($d['daten'] ?? ''), true) ?: []);
        if (preg_match('/^\s*(\d{1,6})\s*$/', (string)($daten['laufendeNummer'] ?? ''), $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return (string)($max + 1);
}

/** „Einsatzauftrag Nr. 3 an B-Gruppe 1. TZ" – wie das Dokument in der Ablage heißt. Reine Funktion. */
function einsatz_auftrag_titel(array $daten): string
{
    $nr = trim((string)($daten['laufendeNummer'] ?? ''));
    $an = trim((string)preg_replace('/\s+/u', ' ', (string)($daten['empfaenger'] ?? '')));
    return mb_substr('Einsatzauftrag' . ($nr !== '' ? ' Nr. ' . $nr : '') . ($an !== '' ? ' an ' . $an : '') . (!empty($daten['uebung']) ? ' (Übung)' : ''), 0, 200);
}

/**
 * Was von einem Einsatzauftrag in eine Vorlage gehört: die Texte – nicht, was zu einem
 * einzelnen Auftrag gehört (laufende Nummer, Ort und Datum, Absendevermerk). Eine globale
 * Vorlage gilt für jeden Einsatz und jede Person, deshalb ohne Einsatz und Unterschrift.
 * Reine Funktion.
 */
function einsatz_auftrag_vorlage_felder(mixed $daten, bool $global): array
{
    $felder = einsatz_auftrag_daten($daten);
    unset($felder['laufendeNummer'], $felder['ortDatum'], $felder['abgesandtAm'], $felder['abgesandtUm'], $felder['abgesandtVon']);
    if ($global) {
        unset($felder['einsatz'], $felder['unterschrift']);
    }
    return $felder;
}

/** Vorlagen zur Auswahl: die dieses Einsatzes bzw. dieser Veranstaltung zuerst, dann die globalen (global = 1) */
function einsatz_auftrag_vorlagen(?int $einsatzId, ?int $eventId): array
{
    return db_all('SELECT v.*, (v.einsatz_id IS NULL AND v.event_id IS NULL) AS global, u.display_name AS von
                   FROM einsatz_auftrag_vorlagen v LEFT JOIN users u ON u.id = v.created_by
                   WHERE (v.einsatz_id IS NULL AND v.event_id IS NULL) OR v.einsatz_id <=> ? AND v.event_id <=> ?
                   ORDER BY global, v.name, v.id', [$einsatzId ?: null, $eventId ?: null]);
}

/** Eine Vorlage – aber nur, wenn sie global ist oder zu diesem Einsatz bzw. dieser Veranstaltung gehört */
function einsatz_auftrag_vorlage_find(?int $id, ?int $einsatzId, ?int $eventId): ?array
{
    foreach ($id ? einsatz_auftrag_vorlagen($einsatzId, $eventId) : [] as $v) {
        if ((int)$v['id'] === $id) {
            return $v;
        }
    }
    return null;
}

/**
 * Die Felder der Maske als Vorlage speichern – global oder für diesen Einsatz bzw. diese
 * Veranstaltung. Ein Name, den es dort schon gibt, wird überschrieben.
 * Gibt die Vorlage zurück (id, name, global) oder einen Fehlertext.
 */
function einsatz_auftrag_vorlage_speichern(string $name, mixed $daten, bool $global, ?int $einsatzId, ?int $eventId, array $user): array|string
{
    $name = mb_substr(trim((string)preg_replace('/\s+/u', ' ', $name)), 0, 150);
    if ($name === '') {
        return 'Bitte einen Namen für die Vorlage angeben.';
    }
    $felder = einsatz_auftrag_vorlage_felder($daten, $global);
    if (!$felder) {
        return 'Die Maske ist leer – es gibt nichts zu speichern.';
    }
    $einsatzId = $global ? null : ($einsatzId ?: null);
    $eventId = $global ? null : ($eventId ?: null);
    $json = (string)json_encode($felder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $vorhanden = db_val('SELECT id FROM einsatz_auftrag_vorlagen WHERE name = ? AND einsatz_id <=> ? AND event_id <=> ?', [$name, $einsatzId, $eventId]);
    if ($vorhanden) {
        $id = (int)$vorhanden;
        db_update('einsatz_auftrag_vorlagen', ['daten' => $json, 'created_by' => (int)$user['id']], 'id = ?', [$id]);
    } else {
        $id = db_insert('einsatz_auftrag_vorlagen', ['einsatz_id' => $einsatzId, 'event_id' => $eventId, 'name' => $name, 'daten' => $json, 'created_by' => (int)$user['id']]);
    }
    audit('einsatzauftrag.vorlage', 'einsatz_auftrag_vorlage', $id, ($global ? 'global: ' : '') . $name);
    return ['id' => $id, 'name' => $name, 'global' => $global, 'ersetzt' => (bool)$vorhanden];
}

function einsatz_auftrag_vorlage_delete(array $vorlage): void
{
    db_exec('DELETE FROM einsatz_auftrag_vorlagen WHERE id = ?', [(int)$vorlage['id']]);
    audit('einsatzauftrag.vorlage_geloescht', 'einsatz_auftrag_vorlage', (int)$vorlage['id'], (string)$vorlage['name']);
}

/**
 * Womit ein neuer Einsatzauftrag beginnt: Absender (der Ortsverband), Einsatz, die nächste
 * laufende Nummer, Ort und Datum, wer unterschreibt. $vorlage: die Felder eines früheren
 * Auftrags, die übernommen werden – Nummer, Empfänger und Absendevermerk beginnen neu.
 * Reine Funktion.
 */
function einsatz_auftrag_vorgabe(array $einsatz, array $dateien, array $user, string $ovName = '', ?array $vorlage = null, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $ort = trim((string)($einsatz['ort'] ?? ''));
    $neu = [
        'absender'       => $ovName,
        'einsatz'        => einsatz_bezeichnung($einsatz),
        'laufendeNummer' => einsatz_auftrag_naechste_nr($dateien),
        'ortDatum'       => ($ort !== '' ? $ort . ', ' : '') . date('d.m.Y', $jetzt),
        'unterschrift'   => (string)($user['display_name'] ?? ''),
    ];
    if (trim((string)($einsatz['beschreibung'] ?? '')) !== '') {
        $neu['unterpunkte']['1a'] = (string)$einsatz['beschreibung'];
    }
    if ($vorlage) {
        $alt = einsatz_auftrag_daten($vorlage);
        unset($alt['laufendeNummer'], $alt['empfaenger'], $alt['ortDatum'], $alt['abgesandtAm'], $alt['abgesandtUm'], $alt['abgesandtVon']);
        $neu = array_merge($neu, $alt, ['laufendeNummer' => $neu['laufendeNummer'], 'ortDatum' => $neu['ortDatum']]);
    }
    return $neu;
}

/* ==================================================================== */
/* Veranstaltungen: Einsatzauftrag ablegen, in einen Einsatz umwandeln    */
/* ==================================================================== */

/**
 * Einen im Browser gesetzten Einsatzauftrag (PDF) bei den Dateien einer Veranstaltung
 * ablegen – etwa für eine Übung. Die Felder des Vordrucks werden als JSON mitgespeichert,
 * damit der nächste Auftrag damit beginnen kann. Gibt [anzahl, fehler[], ids[]] zurück.
 */
function event_auftrag_ablegen(int $eventId, string $feld, array $daten, array $user): array
{
    [$dateien, $fehler] = upload_dokumente_ablegen($feld, efile_dir());
    $ids = [];
    foreach ($dateien as $d) {
        if (!einsatz_ist_pdf(efile_dir() . DIRECTORY_SEPARATOR . basename((string)$d['stored_name']), (string)$d['orig_name'])) {
            foreach ([$d['stored_name'], $d['thumb_name']] as $name) {
                if ($name) {
                    @unlink(efile_dir() . DIRECTORY_SEPARATOR . basename((string)$name));
                }
            }
            $fehler[] = sprintf('Erwartet wurde ein PDF – „%s" ist keines.', $d['orig_name']);
            continue;
        }
        $ids[] = db_insert('event_files', [
            'event_id'    => $eventId,
            'art'         => 'dokument',
            'herkunft'    => 'auftrag',
            'titel'       => einsatz_auftrag_titel($daten),
            'orig_name'   => $d['orig_name'],
            'stored_name' => $d['stored_name'],
            'thumb_name'  => null,
            'mime'        => 'application/pdf',
            'size_bytes'  => $d['size_bytes'],
            'daten'       => (string)json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'uploaded_by' => (int)$user['id'],
        ]);
        audit('veranstaltung.einsatzauftrag', 'event', $eventId, einsatz_auftrag_titel($daten));
    }
    return [count($ids), $fehler, $ids];
}

/** Der Einsatz, der aus dieser Veranstaltung entstanden ist – oder null */
function einsatz_zu_veranstaltung(int $eventId): ?array
{
    return $eventId > 0 ? db_row('SELECT * FROM einsaetze WHERE event_id = ? ORDER BY id LIMIT 1', [$eventId]) : null;
}

/**
 * Die Zeilen eines Einsatzes aus dem Zeitraum einer Veranstaltung: je Kalendertag eine, mit
 * der Fachgruppe und den Fahrzeugen der Veranstaltung und der Teilnehmerzahl als Helfer.
 * Ein einzelner Tag übernimmt Beginn und Ende; über mehrere Tage bleiben die Uhrzeiten leer –
 * wann an welchem Tag gearbeitet wurde, weiß die Veranstaltung nicht. Höchstens 60 Tage.
 * Reine Funktion.
 */
function einsatz_tage_aus_veranstaltung(array $event, array $vehicleIds = [], int $helfer = 0): array
{
    $beginn = (string)($event['beginn'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $beginn)) {
        return [];
    }
    $ende = (string)(($event['ende'] ?? '') ?: $beginn);
    $ersterTag = substr($beginn, 0, 10);
    $letzterTag = max($ersterTag, substr($ende, 0, 10));
    $zeit = static fn(string $zeitpunkt): ?string => strlen($zeitpunkt) >= 16 && substr($zeitpunkt, 11, 5) !== '00:00' ? substr($zeitpunkt, 11, 5) : null;
    $zeile = static fn(string $datum, ?string $von, ?string $bis): array => [
        'datum' => $datum, 'von' => $von, 'bis' => $bis,
        'fachgruppe_id' => !empty($event['fachgruppe_id']) ? (int)$event['fachgruppe_id'] : null,
        'staerke_f' => 0, 'staerke_u' => 0, 'staerke_h' => max(0, min(9999, $helfer)),
        'taetigkeit' => '',
        'vehicles' => array_values(array_unique(array_filter(array_map('intval', $vehicleIds), static fn($i) => $i > 0))),
        'km' => [], 'bh' => [],
    ];
    if ($letzterTag === $ersterTag) {
        $von = $zeit($beginn);
        // ein Ende gibt es nur mit Beginn – und nur, wenn die Veranstaltung eines hat
        $bis = $von !== null && !empty($event['ende']) ? substr($ende, 11, 5) : null;
        return [$zeile($ersterTag, $von, $bis)];
    }
    $tage = [];
    for ($tag = strtotime($ersterTag . ' 12:00:00'), $n = 0; date('Y-m-d', $tag) <= $letzterTag && $n < 60; $tag += 86400, $n++) {
        $tage[] = $zeile(date('Y-m-d', $tag), null, null);
    }
    return $tage;
}

/**
 * Der Kopf des neuen Einsatzes aus einer Veranstaltung. $ort: Plätze und freier Ort als Text.
 * Was vorbei oder abgeschlossen ist, beginnt als „beendet". Reine Funktion.
 */
function einsatz_kopf_aus_veranstaltung(array $event, string $ort, string $nummer, ?int $artId, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $ende = (string)(($event['ende'] ?? '') ?: ($event['beginn'] ?? ''));
    $vorbei = (string)($event['status'] ?? '') === 'abgeschlossen' || ($ende !== '' && strtotime($ende) < $jetzt);
    return [
        'nummer'        => mb_substr($nummer, 0, 40),
        'titel'         => mb_substr((string)($event['titel'] ?? ''), 0, 200),
        'art_id'        => $artId,
        'status'        => $vorbei ? 'beendet' : 'laufend',
        'anforderer'    => '',
        'ort'           => mb_substr($ort, 0, 200),
        'fachgruppe_id' => !empty($event['fachgruppe_id']) ? (int)$event['fachgruppe_id'] : null,
        'event_id'      => (int)$event['id'],
        'beschreibung'  => (string)($event['beschreibung'] ?? ''),
        'notiz'         => 'Aus der Veranstaltung „' . (string)($event['titel'] ?? '') . '" umgewandelt am ' . date('d.m.Y', $jetzt) . '.',
    ];
}

/**
 * Aus einer Veranstaltung einen Einsatz machen. Die Veranstaltung bleibt, wie sie ist, mit
 * Gästeliste und Dateien; der Einsatz übernimmt Titel, Ort, Beschreibung, Fachgruppe, die
 * Tage, die Fahrzeuge und die Teilnehmerzahl als Stärke. Die Buchungen der Veranstaltung
 * hängen danach auch am Einsatz, ihre Einsatzaufträge werden mitgenommen.
 * $helfer: wie viele Personen dabei waren. Gibt die Kennung des Einsatzes zurück.
 */
function einsatz_aus_veranstaltung(array $event, array $user, int $helfer = 0): int
{
    $eventId = (int)$event['id'];
    $fahrzeuge = function_exists('event_fahrzeuge') ? array_map('intval', array_column(event_fahrzeuge($eventId), 'id')) : [];
    $tage = einsatz_tage_aus_veranstaltung($event, $fahrzeuge, $helfer);
    $summen = einsatz_summen($tage);
    $jahr = (int)substr((string)$summen['beginn'], 0, 4);
    $ort = function_exists('event_ort_text') ? event_ort_text($event) : (string)($event['ort'] ?? '');
    $daten = einsatz_kopf_aus_veranstaltung($event, $ort, einsatz_nummer_vorschlag($jahr), list_default_id('einsatz_art')) + [
        'beginn'     => $summen['beginn'],
        'ende'       => $summen['ende'],
        'jahr'       => $jahr,
        'created_by' => (int)$user['id'],
        'updated_by' => (int)$user['id'],
    ];
    $id = db_insert('einsaetze', $daten);
    einsatz_tage_setzen($id, $tage);
    // Was für die Veranstaltung gebucht ist, gehört auch zum Einsatz – der Bezug zur Veranstaltung bleibt
    db_exec('UPDATE expenses SET einsatz_id = ? WHERE event_id = ? AND einsatz_id IS NULL', [$id, $eventId]);
    // Einsatzaufträge mitnehmen: als eigene Kopie, damit jede Seite ihre Dateien für sich löschen kann
    foreach (function_exists('efile_list') ? efile_list($eventId) : [] as $f) {
        if ((string)($f['herkunft'] ?? '') !== 'auftrag') {
            continue;
        }
        $quelle = efile_path($f);
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.pdf';
        if ($quelle === null || !@copy($quelle, einsatz_datei_dir() . DIRECTORY_SEPARATOR . $name)) {
            continue;
        }
        db_insert('einsatz_files', [
            'einsatz_id' => $id, 'art' => 'dokument', 'herkunft' => 'auftrag', 'titel' => (string)$f['titel'], 'orig_name' => (string)$f['orig_name'],
            'stored_name' => $name, 'thumb_name' => null, 'mime' => 'application/pdf', 'size_bytes' => (int)$f['size_bytes'],
            'daten' => $f['daten'] ?? null, 'karte_id' => null, 'uploaded_by' => $f['uploaded_by'] ?? null,
        ]);
    }
    audit('einsatz.aus_veranstaltung', 'einsatz', $id, einsatz_bezeichnung($daten) . ' / Veranstaltung #' . $eventId);
    return $id;
}
