<?php
declare(strict_types=1);

/**
 * Einsätze: was der Ortsverband gefahren ist – über einen oder mehrere Tage.
 *
 * Ein Einsatz besteht aus Tagen (oder Schichten): Datum, von–bis, Stärke
 * (Führer / Unterführer / Helfer) und Tätigkeit. Daraus rechnet das Modul
 * Einsatztage, Einsatzstunden und Helferstunden – die Zahlen, die die
 * Einsatzabrechnung braucht. Beginn und Ende am Einsatz selbst sind aus den
 * Tagen abgeleitet und stehen nur für Liste und Kalender in der Tabelle.
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
 * Die Tage aus dem Formular lesen. Erwartet die Spalten als gleich lange
 * Listen: tag_datum[], tag_von[], tag_bis[], tag_staerke[], tag_taetigkeit[].
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
    $zeilen = max(count($datum), count($von), count($bis), count($staerke), count($taetigkeit));

    for ($i = 0; $i < $zeilen; $i++) {
        $d = trim((string)($datum[$i] ?? ''));
        $v = trim((string)($von[$i] ?? ''));
        $b = trim((string)($bis[$i] ?? ''));
        $s = trim((string)($staerke[$i] ?? ''));
        $t = trim((string)($taetigkeit[$i] ?? ''));
        if ($d === '' && $v === '' && $b === '' && $s === '' && $t === '') {
            continue;
        }
        $nr = 'Zeile ' . (count($tage) + count($fehler) + 1);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            $fehler[] = $d === '' ? $nr . ': Bitte ein Datum angeben.' : $nr . ': „' . $d . '" ist kein gültiges Datum.';
            continue;
        }
        $zv = einsatz_zeit($v);
        $zb = einsatz_zeit($b);
        if ($zv === false || $zb === false) {
            $fehler[] = sprintf('%s (%s): Uhrzeiten bitte als hh:mm angeben.', $nr, de_date($d));
            continue;
        }
        if ($zv === null && $zb !== null) {
            $fehler[] = sprintf('%s (%s): Zum Ende fehlt der Beginn.', $nr, de_date($d));
            continue;
        }
        // dieselbe Schreibweise wie auf den Karten: „1/2/9", „1/2/9/12" oder eine einzelne Zahl
        $st = karten_staerke_parsen($s);
        if ($st === null) {
            $fehler[] = sprintf('%s (%s): Stärke bitte als Führer/Unterführer/Helfer angeben, etwa 1/2/9 – oder als einzelne Zahl.', $nr, de_date($d));
            continue;
        }
        $tage[] = [
            'datum' => $d, 'von' => $zv, 'bis' => $zb,
            'staerke_f' => (int)$st[0], 'staerke_u' => (int)$st[1], 'staerke_h' => (int)$st[2],
            'taetigkeit' => mb_substr($t, 0, 500),
        ];
    }
    if (count($tage) > EINSATZ_TAGE_MAX) {
        $fehler[] = sprintf('Ein Einsatz nimmt höchstens %d Zeilen an.', EINSATZ_TAGE_MAX);
    }
    usort($tage, static fn($x, $y) => [$x['datum'], (string)$x['von']] <=> [$y['datum'], (string)$y['von']]);
    return [$tage, $fehler];
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
                u.display_name AS ersteller, ' . einsatz_fahrzeuge_sub('e') . ' AS fahrzeuge_namen
         FROM einsaetze e
         LEFT JOIN list_items a  ON a.id  = e.art_id
         LEFT JOIN list_items fg ON fg.id = e.fachgruppe_id
         LEFT JOIN users      u  ON u.id  = e.created_by
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

/** Tage und Schichten eines Einsatzes in zeitlicher Reihenfolge */
function einsatz_tage(int $einsatzId): array
{
    return db_all('SELECT * FROM einsatz_tage WHERE einsatz_id = ? ORDER BY datum, von, id', [$einsatzId]);
}

function einsatz_fahrzeuge(int $einsatzId): array
{
    return db_all('SELECT v.id, v.bezeichnung, v.funkrufname, v.kennzeichen, v.is_active FROM einsatz_vehicles ev
                   JOIN vehicles v ON v.id = ev.vehicle_id WHERE ev.einsatz_id = ? ORDER BY v.bezeichnung', [$einsatzId]);
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
    // Fahrzeuge nur, wenn das Feld im Formular war
    if (post_str('fahrzeuge_feld') === '1') {
        einsatz_fahrzeuge_setzen($id, wahl_ids('vehicles'));
    }
    return [$id, []];
}

/** Genau diese Tage gehören zum Einsatz */
function einsatz_tage_setzen(int $einsatzId, array $tage): void
{
    db_exec('DELETE FROM einsatz_tage WHERE einsatz_id = ?', [$einsatzId]);
    foreach ($tage as $t) {
        db_insert('einsatz_tage', [
            'einsatz_id' => $einsatzId,
            'datum'      => (string)$t['datum'],
            'von'        => $t['von'] !== null ? $t['von'] . ':00' : null,
            'bis'        => $t['bis'] !== null ? $t['bis'] . ':00' : null,
            'staerke_f'  => (int)$t['staerke_f'],
            'staerke_u'  => (int)$t['staerke_u'],
            'staerke_h'  => (int)$t['staerke_h'],
            'taetigkeit' => (string)$t['taetigkeit'],
        ]);
    }
}

/** Genau diese Fahrzeuge waren im Einsatz */
function einsatz_fahrzeuge_setzen(int $einsatzId, array $ids): void
{
    db_exec('DELETE FROM einsatz_vehicles WHERE einsatz_id = ?', [$einsatzId]);
    foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0))) as $vid) {
        db_insert('einsatz_vehicles', ['einsatz_id' => $einsatzId, 'vehicle_id' => $vid]);
    }
}

/** Einsatz löschen. Tage und Fahrzeuge gehen mit, Buchungen bleiben im Budget und verlieren nur den Bezug. */
function einsatz_delete(array $e): void
{
    db_exec('UPDATE expenses SET einsatz_id = NULL WHERE einsatz_id = ?', [(int)$e['id']]);
    db_exec('DELETE FROM einsaetze WHERE id = ?', [(int)$e['id']]);
    audit('einsatz.geloescht', 'einsatz', (int)$e['id'], einsatz_bezeichnung($e));
}
