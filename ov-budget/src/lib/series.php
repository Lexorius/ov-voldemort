<?php
declare(strict_types=1);

/**
 * Wiederkehrende Besprechungen.
 *
 * Eine Serie beschreibt nur die Regel. Die einzelnen Termine werden für einen
 * Vorlauf von einigen Wochen als echte Besprechungen angelegt – nur so lassen
 * sich Themen auf "die nächste Dienstbesprechung" setzen und je Termin ein
 * Protokoll führen. Neue Termine rücken beim Aufruf der Seiten automatisch
 * nach.
 *
 * Jeder erzeugte Termin merkt sich sein ursprüngliches Seriendatum. Wird ein
 * Termin verschoben oder abgesagt, erkennt die Serie ihn daran wieder und
 * legt ihn nicht erneut an.
 */

const SERIE_REGELN = [
    'woche'           => 'wöchentlich',
    'monat_wochentag' => 'monatlich an einem Wochentag',
    'monat_tag'       => 'monatlich an einem Kalendertag',
];

const WOCHENTAGE = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
                    5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];

/** "montags", "dienstags" ... */
const WOCHENTAGE_ADVERB = [1 => 'montags', 2 => 'dienstags', 3 => 'mittwochs', 4 => 'donnerstags',
                           5 => 'freitags', 6 => 'samstags', 7 => 'sonntags'];

/** Welcher Wochentag im Monat: 1. bis 4. oder der letzte */
const SERIE_NTE = [1 => '1.', 2 => '2.', 3 => '3.', 4 => '4.', -1 => 'letzten'];

/* ==================================================================== */
/* Reine Berechnung (ohne Datenbank)                                     */
/* ==================================================================== */

function serie_datum(string $ymd): DateTimeImmutable
{
    // UTC: reine Kalenderrechnung ohne Sommerzeit-Sprünge
    return new DateTimeImmutable($ymd, new DateTimeZone('UTC'));
}

/** n-ter Wochentag eines Monats, bei $nte = -1 der letzte; null, wenn es ihn nicht gibt */
function serie_nter_wochentag(int $jahr, int $monat, int $nte, int $wochentag): ?string
{
    $erster = serie_datum(sprintf('%04d-%02d-01', $jahr, $monat));
    $tage = (int)$erster->format('t');

    if ($nte === -1) {
        $letzter = $erster->setDate($jahr, $monat, $tage);
        $zurueck = ((int)$letzter->format('N') - $wochentag + 7) % 7;
        return $letzter->modify('-' . $zurueck . ' days')->format('Y-m-d');
    }

    $vor = ($wochentag - (int)$erster->format('N') + 7) % 7;
    $tag = 1 + $vor + ($nte - 1) * 7;
    return $tag <= $tage ? sprintf('%04d-%02d-%02d', $jahr, $monat, $tag) : null;
}

/**
 * Termine einer Serie im Zeitraum [$von, $bis] (jeweils Y-m-d, einschließlich).
 *
 * $s: regel, intervall, wochentag (1 = Montag … 7 = Sonntag), nte (1–4, -1 = letzter),
 *     monatstag (1–31), start_datum, end_datum (oder leer)
 *
 * Der Rhythmus richtet sich am Startdatum aus: "alle 2 Wochen montags" ab dem
 * 7.9. ergibt 7.9., 21.9., 5.10. – unabhängig davon, ab wann gefragt wird.
 * Gibt es einen Monatstag nicht (31. im April), fällt der Termin auf den
 * letzten Tag des Monats.
 */
function series_occurrences(array $s, string $von, string $bis): array
{
    $start = (string)$s['start_datum'];
    $ende = !empty($s['end_datum']) ? (string)$s['end_datum'] : null;

    $von = max($von, $start);
    if ($ende !== null) {
        $bis = min($bis, $ende);
    }
    if ($von > $bis) {
        return [];
    }

    $n = max(1, (int)($s['intervall'] ?? 1));
    $out = [];

    switch ($s['regel']) {
        case 'woche':
            $wt = min(7, max(1, (int)$s['wochentag']));
            $anker = serie_datum($start);
            $anker = $anker->modify('+' . (($wt - (int)$anker->format('N') + 7) % 7) . ' days');

            // Direkt in die Nähe von $von springen, statt ab Serienbeginn zu zählen
            $schritt = 7 * $n;
            $abstand = (int)$anker->diff(serie_datum($von))->format('%r%a');
            $d = $abstand > 0 ? $anker->modify('+' . (intdiv($abstand + $schritt - 1, $schritt) * $schritt) . ' days') : $anker;

            while ($d->format('Y-m-d') <= $bis) {
                $out[] = $d->format('Y-m-d');
                $d = $d->modify('+' . $schritt . ' days');
            }
            break;

        case 'monat_wochentag':
        case 'monat_tag':
            $s0 = serie_datum($start);
            $jahr0 = (int)$s0->format('Y');
            $monat0 = (int)$s0->format('n');

            // Monate zwischen Serienbeginn und $von, auf den Rhythmus abgerundet
            $v = serie_datum($von);
            $monateBisVon = ((int)$v->format('Y') - $jahr0) * 12 + (int)$v->format('n') - $monat0;
            $k = max(0, intdiv(max(0, $monateBisVon), $n) - 1);

            while (true) {
                $index = $monat0 - 1 + $k * $n;
                $jahr = $jahr0 + intdiv($index, 12);
                $monat = $index % 12 + 1;
                if (sprintf('%04d-%02d-01', $jahr, $monat) > $bis) {
                    break;
                }

                if ($s['regel'] === 'monat_wochentag') {
                    $nte = (int)$s['nte'];
                    $nte = ($nte === -1 || ($nte >= 1 && $nte <= 4)) ? $nte : 1;
                    $datum = serie_nter_wochentag($jahr, $monat, $nte, min(7, max(1, (int)$s['wochentag'])));
                } else {
                    $tage = (int)serie_datum(sprintf('%04d-%02d-01', $jahr, $monat))->format('t');
                    $tag = min($tage, max(1, (int)$s['monatstag']));
                    $datum = sprintf('%04d-%02d-%02d', $jahr, $monat, $tag);
                }

                if ($datum !== null && $datum >= $von && $datum <= $bis) {
                    $out[] = $datum;
                }
                $k++;
            }
            break;
    }

    return $out;
}

/** Lesbare Beschreibung: "alle 2 Wochen montags um 19:30 Uhr" */
function series_describe(array $s): string
{
    $n = max(1, (int)($s['intervall'] ?? 1));
    $wt = min(7, max(1, (int)($s['wochentag'] ?? 1)));

    $text = match ($s['regel'] ?? '') {
        'woche' => $n === 1
            ? 'jeden ' . WOCHENTAGE[$wt]
            : 'alle ' . $n . ' Wochen ' . WOCHENTAGE_ADVERB[$wt],
        'monat_wochentag' => ($n === 1 ? 'jeden ' : 'alle ' . $n . ' Monate am ')
            . (SERIE_NTE[(int)($s['nte'] ?? 1)] ?? '1.') . ' ' . WOCHENTAGE[$wt]
            . ($n === 1 ? ' im Monat' : ''),
        'monat_tag' => ($n === 1 ? 'monatlich' : 'alle ' . $n . ' Monate')
            . ' am ' . min(31, max(1, (int)($s['monatstag'] ?? 1))) . '.',
        default => 'ohne Regel',
    };

    if (!empty($s['beginn']) && preg_match('/^(\d{1,2}):(\d{2})/', (string)$s['beginn'], $m)) {
        $text .= sprintf(' um %02d:%02d Uhr', (int)$m[1], (int)$m[2]);
    }
    return $text;
}

/* ==================================================================== */
/* Datenbank                                                             */
/* ==================================================================== */

function series_find(int $id): ?array
{
    return db_row(
        'SELECT s.*, t.label AS typ_label, t.color AS typ_color
         FROM meeting_series s LEFT JOIN list_items t ON t.id = s.typ_id WHERE s.id = ?',
        [$id]
    );
}

function series_all(): array
{
    return db_all(
        'SELECT s.*, t.label AS typ_label, t.color AS typ_color,
                (SELECT MIN(m.datum) FROM meetings m
                  WHERE m.series_id = s.id AND m.datum >= CURDATE() AND m.status = \'geplant\') AS naechster
         FROM meeting_series s
         LEFT JOIN list_items t ON t.id = s.typ_id
         ORDER BY s.is_active DESC, s.titel'
    );
}

/** Wie weit im Voraus Termine angelegt werden */
function series_horizon_days(): int
{
    return min(366, max(7, setting_int('serie_vorlauf_tage', 60)));
}

/**
 * Fehlende Termine anlegen – für alle aktiven Serien oder eine bestimmte.
 * Läuft je Anfrage höchstens einmal; doppelte Termine verhindert der
 * eindeutige Schlüssel auf (series_id, serien_datum).
 */
function series_materialize(?int $seriesId = null): int
{
    static $gelaufen = false;
    if ($seriesId === null && $gelaufen) {
        return 0;
    }
    if ($seriesId === null) {
        $gelaufen = true;
    }

    $heute = date('Y-m-d');
    $bis = date('Y-m-d', strtotime('+' . series_horizon_days() . ' days'));

    $serien = $seriesId !== null
        ? db_all('SELECT * FROM meeting_series WHERE id = ? AND is_active = 1', [$seriesId])
        : db_all('SELECT * FROM meeting_series WHERE is_active = 1');

    $neu = 0;
    foreach ($serien as $s) {
        foreach (series_occurrences($s, $heute, $bis) as $datum) {
            $neu += db_exec(
                'INSERT IGNORE INTO meetings
                   (series_id, serien_datum, datum, titel, typ_id, beginn, ende, ort, leitung,
                    teilnehmer, beschreibung, status, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,\'geplant\',?)',
                [
                    (int)$s['id'], $datum, $datum, $s['titel'], $s['typ_id'], $s['beginn'], $s['ende'],
                    $s['ort'], $s['leitung'], $s['teilnehmer'], $s['beschreibung'], $s['created_by'],
                ]
            );
        }
    }
    return $neu;
}

/**
 * Künftige Termine einer Serie entfernen, die noch niemand angefasst hat:
 * geplant, nicht verschoben, ohne Themen, ohne Notizen. Alles andere bleibt,
 * damit keine Arbeit verloren geht.
 *
 * Rückgabe: ['geloescht' => n, 'behalten' => n]
 */
function series_prune_future(int $seriesId): array
{
    $geloescht = db_exec(
        "DELETE m FROM meetings m
         WHERE m.series_id = ? AND m.datum >= CURDATE() AND m.status = 'geplant'
           AND m.datum = m.serien_datum
           AND COALESCE(m.notizen, '') = '' AND m.protokoll_von = ''
           AND NOT EXISTS (SELECT 1 FROM talking_points tp WHERE tp.meeting_id = m.id)",
        [$seriesId]
    );
    $behalten = (int)db_val(
        'SELECT COUNT(*) FROM meetings WHERE series_id = ? AND datum >= CURDATE()',
        [$seriesId],
        0
    );
    return ['geloescht' => $geloescht, 'behalten' => $behalten];
}

/**
 * Formular lesen und prüfen, ohne zu speichern – für Vorschau und Speichern.
 * Rückgabe: [fehler[], daten]
 */
function series_from_post(): array
{
    $errors = [];

    $titel = post_str('titel');
    if ($titel === '') {
        $errors[] = 'Bitte einen Titel angeben.';
    }
    $regel = post_str('regel');
    if (!array_key_exists($regel, SERIE_REGELN)) {
        $errors[] = 'Bitte einen Rhythmus wählen.';
    }
    $start = post_date('start_datum');
    if (!$start) {
        $errors[] = 'Bitte angeben, ab wann die Serie gilt.';
    }
    $ende = post_date('end_datum');
    if ($start && $ende && $ende < $start) {
        $errors[] = 'Das Ende der Serie liegt vor ihrem Beginn.';
    }

    $uhrzeit = static function (string $feld): ?string {
        $v = post_str($feld);
        return preg_match('/^\d{1,2}:\d{2}$/', $v) ? $v . ':00' : null;
    };

    $nte = post_int('nte', 1) ?? 1;

    $data = [
        'titel'        => mb_substr($titel, 0, 200),
        'typ_id'       => post_int('typ_id'),
        'regel'        => $regel,
        'intervall'    => min(12, max(1, post_int('intervall', 1) ?? 1)),
        'wochentag'    => min(7, max(1, post_int('wochentag', 1) ?? 1)),
        'nte'          => ($nte === -1 || ($nte >= 1 && $nte <= 4)) ? $nte : 1,
        'monatstag'    => min(31, max(1, post_int('monatstag', 1) ?? 1)),
        'beginn'       => $uhrzeit('beginn'),
        'ende'         => $uhrzeit('ende'),
        'ort'          => mb_substr(post_str('ort'), 0, 150),
        'leitung'      => mb_substr(post_str('leitung'), 0, 150),
        'teilnehmer'   => post_str('teilnehmer'),
        'beschreibung' => post_str('beschreibung'),
        'start_datum'  => $start,
        'end_datum'    => $ende,
        'is_active'    => post_bool('is_active'),
    ];

    return [$errors, $data];
}

/** Rückgabe: [id oder null, fehler[], daten] */
function series_save_from_post(?array $existing, array $user): array
{
    [$errors, $data] = series_from_post();
    if ($errors) {
        return [null, $errors, $data];
    }

    if ($existing) {
        db_update('meeting_series', $data, 'id = ?', [$existing['id']]);
        $id = (int)$existing['id'];
        audit('serie.bearbeitet', 'meeting_series', $id, $data['titel']);
    } else {
        $data['created_by'] = (int)$user['id'];
        $id = db_insert('meeting_series', $data);
        audit('serie.angelegt', 'meeting_series', $id, $data['titel']);
    }
    return [$id, [], $data];
}
