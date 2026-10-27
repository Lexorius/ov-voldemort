<?php
declare(strict_types=1);

/**
 * Protokoll einer Besprechung als PDF – und sein Versand per E-Mail an die
 * eingetragenen Empfänger aus den Kontakten.
 *
 * Das PDF entsteht hier im Add-on mit OvbPdf (src/lib/pdf.php), ohne
 * Zusatzsoftware. Inhalt ist derselbe wie in der Druckansicht: Eckdaten,
 * Anwesenheit, Tagesordnungspunkte mit Ergebnis und Anmerkungen, Aufgaben,
 * Notizen.
 */

/* ==================================================================== */
/* Empfänger                                                             */
/* ==================================================================== */

/*
 * Empfänger kommen aus dem Kontaktmodul: einzelne Kontakte und ganze
 * Verteiler. Verteiler werden als solche gemerkt – wer später in den
 * Verteiler kommt, bekommt das nächste Protokoll mit.
 */

/** Einzeln eingetragene Kontakte, nach Namen */
function meeting_protokoll_kontakte(int $meetingId): array
{
    return db_all('SELECT c.* FROM meeting_protokoll_empfaenger e JOIN contacts c ON c.id = e.contact_id
                   WHERE e.meeting_id = ? ORDER BY c.nachname, c.vorname, c.organisation', [$meetingId]);
}

function meeting_protokoll_kontakt_ids(int $meetingId): array
{
    return array_map(static fn(array $k) => (int)$k['id'], meeting_protokoll_kontakte($meetingId));
}

/** Eingetragene Verteiler */
function meeting_protokoll_verteiler(int $meetingId): array
{
    return db_all('SELECT g.* FROM meeting_protokoll_verteiler v JOIN contact_groups g ON g.id = v.group_id
                   WHERE v.meeting_id = ? ORDER BY g.name', [$meetingId]);
}

function meeting_protokoll_verteiler_ids(int $meetingId): array
{
    return array_map(static fn(array $g) => (int)$g['id'], meeting_protokoll_verteiler($meetingId));
}

/** Die einzelnen Kontakte ersetzen – nur Kontakte mit gültiger E-Mail-Adresse bleiben */
function meeting_protokoll_empfaenger_setzen(int $meetingId, array $kontaktIds): int
{
    db_exec('DELETE FROM meeting_protokoll_empfaenger WHERE meeting_id = ?', [$meetingId]);
    $n = 0;
    foreach (array_unique(array_map('intval', $kontaktIds)) as $kid) {
        if ($kid <= 0) {
            continue;
        }
        $k = function_exists('contact_find') ? contact_find($kid) : null;
        if ($k === null || !mail_adresse_gueltig((string)($k['email'] ?? ''))) {
            continue;
        }
        db_insert('meeting_protokoll_empfaenger', ['meeting_id' => $meetingId, 'contact_id' => $kid]);
        $n++;
    }
    return $n;
}

/** Die Verteiler ersetzen */
function meeting_protokoll_verteiler_setzen(int $meetingId, array $groupIds): int
{
    db_exec('DELETE FROM meeting_protokoll_verteiler WHERE meeting_id = ?', [$meetingId]);
    $n = 0;
    foreach (array_unique(array_map('intval', $groupIds)) as $gid) {
        if ($gid <= 0 || !function_exists('contact_group_find') || contact_group_find($gid) === null) {
            continue;
        }
        db_insert('meeting_protokoll_verteiler', ['meeting_id' => $meetingId, 'group_id' => $gid]);
        $n++;
    }
    return $n;
}

/**
 * Alle, die das Protokoll bekommen: einzelne Kontakte plus die Mitglieder der
 * Verteiler, jeder nur einmal und nur mit gültiger E-Mail-Adresse, nach Namen.
 */
function meeting_protokoll_empfaenger(int $meetingId): array
{
    return meeting_protokoll_zusammenfuehren(meeting_protokoll_kontakte($meetingId),
        array_map(static fn(array $g) => function_exists('contact_group_members') ? contact_group_members((int)$g['id']) : [],
            meeting_protokoll_verteiler($meetingId)));
}

/** Kontakte und Verteilermitglieder zusammenführen: einmal je Kontakt, nur mit E-Mail. Reine Funktion. */
function meeting_protokoll_zusammenfuehren(array $kontakte, array $verteilerMitglieder): array
{
    $alle = [];
    foreach (array_merge([$kontakte], $verteilerMitglieder) as $liste) {
        foreach ($liste as $k) {
            $id = (int)($k['id'] ?? 0);
            if ($id > 0 && !isset($alle[$id]) && mail_adresse_gueltig((string)($k['email'] ?? ''))) {
                $alle[$id] = $k;
            }
        }
    }
    // Nach Namen, Umlaute wie ihre Grundbuchstaben – sonst stünde „Ärztin" hinter „Zimmer"
    $schluessel = static fn(array $k): array => array_map(
        static fn(string $t) => str_replace(['ä', 'ö', 'ü', 'ß'], ['a', 'o', 'u', 'ss'], mb_strtolower($t)),
        [(string)($k['nachname'] ?? ''), (string)($k['vorname'] ?? ''), (string)($k['organisation'] ?? '')]);
    uasort($alle, static fn(array $a, array $b) => $schluessel($a) <=> $schluessel($b));
    return array_values($alle);
}

/* ==================================================================== */
/* Inhalt                                                                */
/* ==================================================================== */

/** Alles, was ins Protokoll gehört – aus denselben Quellen wie die Druckansicht */
function meeting_protokoll_daten(array $meeting): array
{
    $punkte = meeting_points((int)$meeting['id']);
    $ids = array_map(static fn($p) => (int)$p['id'], $punkte);
    $mitAnmerkungen = meeting_print_with_comments('protokoll', (string)setting('protokoll_anmerkungen', 'protokoll'), null);
    $tpLinks = function_exists('tp_links_fuer') ? tp_links_fuer($ids) : [];
    return [
        'punkte'      => $punkte,
        'teilnehmer'  => attendance_list((int)$meeting['id']),
        'aufgaben'    => function_exists('todo_query') ? meeting_todos((int)$meeting['id']) : [],
        'anmerkungen' => $mitAnmerkungen && $ids ? tp_comments_for($ids) : [],
        'mitNamen'    => setting_bool('protokoll_anmerkungen_namen', true),
        'tpLinks'     => $tpLinks,
        'tpEvents'    => function_exists('tp_event_kurzberichte') ? tp_event_kurzberichte($tpLinks) : [],
        'tpBudgets'   => function_exists('tp_budget_kurzberichte') ? tp_budget_kurzberichte($tpLinks) : [],
        'ov_name'     => (string)setting('ov_name', ''),
    ];
}

/** "Mi., 27.10.2026, 19:00 bis 21:00 Uhr". Reine Funktion. */
function meeting_protokoll_wann(array $meeting): string
{
    return meeting_datum_text($meeting)
        . (!empty($meeting['beginn']) ? ', ' . substr((string)$meeting['beginn'], 0, 5) . ' Uhr' : '')
        . (!empty($meeting['ende']) ? ' bis ' . substr((string)$meeting['ende'], 0, 5) . ' Uhr' : '');
}

/** Dateiname des PDFs: protokoll_2026-10-27_dienstbesprechung.pdf. Reine Funktion. */
function meeting_protokoll_dateiname(array $meeting): string
{
    $titel = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', str_replace(['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ss', 'Ae', 'Oe', 'Ue'], (string)$meeting['titel'])), '-'));
    return 'protokoll_' . (string)$meeting['datum'] . ($titel !== '' ? '_' . mb_substr($titel, 0, 40) : '') . '.pdf';
}

/**
 * Das Protokoll als PDF-Bytes. $daten aus meeting_protokoll_daten(); die
 * Funktion liest nichts nach und lässt sich so prüfen.
 */
function meeting_protokoll_pdf(array $meeting, array $daten, ?int $jetzt = null): string
{
    $jetzt ??= time();
    $pdf = new OvbPdf('Protokoll ' . (string)$meeting['titel'] . ' – ' . de_date((string)$meeting['datum']), (string)($daten['ov_name'] ?: 'OV-Multitool'), $jetzt);
    if (trim((string)$daten['ov_name']) !== '') {
        $pdf->absatz((string)$daten['ov_name'], false, 0.0, true);
    }
    $pdf->titel((string)$meeting['titel']);
    $pdf->absatz('Protokoll', true);

    $pdf->schluessel('Datum', meeting_protokoll_wann($meeting));
    if (trim((string)($meeting['ort'] ?? '')) !== '') {
        $treffen = function_exists('meeting_treffen_text') ? meeting_treffen_text($meeting) : '';
        $pdf->schluessel('Ort', (string)$meeting['ort'] . ($treffen !== '' ? ' (' . $treffen . ')' : ''));
    }
    if (trim((string)($meeting['typ_label'] ?? '')) !== '') {
        $pdf->schluessel('Art', (string)$meeting['typ_label']);
    }
    if (trim((string)($meeting['leitung'] ?? '')) !== '') {
        $pdf->schluessel('Leitung', (string)$meeting['leitung']);
    }
    $pdf->schluessel('Protokoll', trim((string)($meeting['protokoll_von'] ?? '')) !== '' ? (string)$meeting['protokoll_von'] : '–');

    $teilnehmer = $daten['teilnehmer'];
    $anwesend = attendance_by_status($teilnehmer, ANWESEND_SLUG);
    $freitext = trim((string)($meeting['teilnehmer'] ?? ''));
    if ($anwesend || $freitext !== '') {
        $pdf->schluessel('Anwesend', trim(implode(', ', $anwesend) . ($freitext !== '' ? ($anwesend ? "\n" : '') . $freitext : '')));
    }
    $entschuldigt = attendance_by_status($teilnehmer, ENTSCHULDIGT_SLUG);
    if ($entschuldigt) {
        $pdf->schluessel('Entschuldigt', implode(', ', $entschuldigt));
    }
    $fehlten = attendance_by_status($teilnehmer, FEHLT_SLUG);
    if ($fehlten) {
        $pdf->schluessel('Nicht erschienen', implode(', ', $fehlten));
    }
    $pdf->linie();

    if (!$daten['punkte']) {
        $pdf->absatz('Keine Themen auf der Tagesordnung.', false, 0.0, true);
    }
    foreach ($daten['punkte'] as $i => $p) {
        $pdf->ueberschrift(sprintf('TOP %d: %s', $i + 1, (string)$p['titel']), 2);
        if (trim((string)($p['fachgruppe_label'] ?? '')) !== '') {
            $pdf->absatz((string)$p['fachgruppe_label'], false, 0.0, true);
        }
        if (trim((string)($p['beschreibung'] ?? '')) !== '') {
            $pdf->absatz((string)$p['beschreibung']);
        }
        $links = $daten['tpLinks'][(int)$p['id']] ?? [];
        if ($links && function_exists('tp_links_text')) {
            $pdf->absatz('Bezüge: ' . tp_links_text($links), false, 0.0, true);
            foreach ($links as $l) {
                if ($l['typ'] === 'budget' && isset($daten['tpBudgets'][(int)$l['id']]) && function_exists('tp_budget_kurzbericht_zeilen')) {
                    $pdf->absatz(implode("\n", tp_budget_kurzbericht_zeilen($daten['tpBudgets'][(int)$l['id']])), false, 12.0, true);
                }
                if ($l['typ'] === 'event' && isset($daten['tpEvents'][(int)$l['id']]) && function_exists('tp_event_kurzbericht_zeilen')) {
                    $pdf->absatz(implode("\n", tp_event_kurzbericht_zeilen($daten['tpEvents'][(int)$l['id']])), false, 12.0, true);
                }
            }
        }
        $kopf = array_filter([
            trim((string)($p['status_label'] ?? '')),
            trim((string)($p['verantwortlich'] ?? '')) !== '' ? 'verantwortlich: ' . (string)$p['verantwortlich'] : '',
        ]);
        foreach ($daten['aufgaben'] as $a) {
            if ((int)($a['talking_point_id'] ?? 0) === (int)$p['id']) {
                $kopf[] = 'Aufgabe #' . (int)$a['id'];
            }
        }
        if ($kopf) {
            $pdf->absatz(implode(' · ', $kopf), true, 12.0);
        }
        if (trim((string)($p['ergebnis'] ?? '')) !== '') {
            $pdf->absatz((string)$p['ergebnis'], false, 12.0);
        }
        $anm = $daten['anmerkungen'][(int)$p['id']] ?? [];
        if ($anm) {
            $pdf->absatz('Anmerkungen', true, 12.0);
            foreach ($anm as $k) {
                $wer = $daten['mitNamen'] ? ((string)($k['autor'] ?: 'unbekannt')) . ', ' . date('d.m.', (int)strtotime((string)$k['created_at'])) . ': ' : '';
                $pdf->punkt($wer . (string)$k['body'], 12.0);
            }
            $pdf->abstand(3.0);
        }
    }

    if ($daten['aufgaben']) {
        $pdf->ueberschrift('Aufgaben', 1);
        foreach ($daten['aufgaben'] as $a) {
            $teile = ['#' . (int)$a['id'] . ' ' . (string)$a['titel']];
            if (trim((string)($a['tp_titel'] ?? '')) !== '') {
                $teile[] = 'zu: ' . (string)$a['tp_titel'];
            }
            if (function_exists('todo_target_name')) {
                $ziel = trim(todo_target_name($a));
                if ($ziel !== '') {
                    $teile[] = $ziel;
                }
            }
            if (!empty($a['faellig_am'])) {
                $teile[] = 'bis ' . de_date((string)$a['faellig_am']);
            }
            $pdf->punkt(implode(' · ', $teile), 0.0);
        }
    }

    if (trim((string)($meeting['notizen'] ?? '')) !== '') {
        $pdf->ueberschrift('Notizen', 1);
        $pdf->absatz((string)$meeting['notizen']);
    }

    $pdf->abstand(10.0);
    $pdf->absatz('Erstellt am ' . date('d.m.Y, H:i', $jetzt) . ' Uhr mit OV-Multitool.', false, 0.0, true);
    return $pdf->ausgabe();
}

/* ==================================================================== */
/* Versand                                                               */
/* ==================================================================== */

/** Betreff und Text der Protokoll-Mail, wie das Modul sie vorschlägt. Reine Funktion. */
function meeting_protokoll_vorlage(array $meeting, array $user, string $ovName): array
{
    $datum = de_date((string)$meeting['datum']);
    $text = "Guten Tag,\n\nanbei das Protokoll der Besprechung „" . (string)$meeting['titel'] . '" vom ' . $datum . " als PDF.\n\n"
        . "Mit freundlichen Grüßen\n" . trim((string)($user['display_name'] ?? $user['username'] ?? '')) . ($ovName !== '' ? "\n" . $ovName : '');
    return [
        'betreff' => sprintf('Protokoll %s vom %s', (string)$meeting['titel'], $datum),
        'text'    => $text,
    ];
}

/**
 * Das Protokoll als PDF an alle Empfänger schicken – jeder bekommt seine
 * eigene Nachricht mit demselben Anhang. Rückgabe: ['ok', 'text', 'gesendet', 'fehler']
 */
function meeting_protokoll_senden(array $meeting, array $user, string $betreff, string $text, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $empfaenger = array_values(array_filter(meeting_protokoll_empfaenger((int)$meeting['id']),
        static fn(array $k) => mail_adresse_gueltig((string)($k['email'] ?? ''))));
    if (!$empfaenger) {
        return ['ok' => false, 'text' => 'Für das Protokoll ist kein Kontakt mit E-Mail-Adresse eingetragen.', 'gesendet' => 0, 'fehler' => []];
    }
    $vorlage = meeting_protokoll_vorlage($meeting, $user, (string)setting('ov_name', ''));
    $betreff = trim($betreff) !== '' ? mb_substr(trim($betreff), 0, 200) : $vorlage['betreff'];
    $text = trim(str_replace("\r", '', $text)) !== '' ? trim(str_replace("\r", '', $text)) : $vorlage['text'];
    $pdf = meeting_protokoll_pdf($meeting, meeting_protokoll_daten($meeting), $jetzt);
    $anhang = ['name' => meeting_protokoll_dateiname($meeting), 'mime' => 'application/pdf', 'inhalt' => $pdf];
    $gesendet = [];
    $fehler = [];
    foreach ($empfaenger as $k) {
        $name = function_exists('contact_name') ? contact_name($k) : trim((string)($k['vorname'] ?? '') . ' ' . (string)($k['nachname'] ?? ''));
        try {
            mail_senden((string)$k['email'], $betreff, $text, $name, [$anhang]);
            $gesendet[] = $name . ' (' . (string)$k['email'] . ')';
        } catch (Throwable $ex) {
            $fehler[] = $name . ': ' . $ex->getMessage();
        }
    }
    if (!$gesendet) {
        audit('besprechung.protokoll_fehler', 'meeting', (int)$meeting['id'], mb_substr(implode(' · ', $fehler), 0, 200));
        return ['ok' => false, 'text' => 'Das Protokoll ließ sich nicht verschicken: ' . implode(' · ', $fehler), 'gesendet' => 0, 'fehler' => $fehler];
    }
    db_update('meetings', [
        'protokoll_gesendet_am' => date('Y-m-d H:i:s', $jetzt),
        'protokoll_gesendet_an' => mb_substr(implode(', ', $gesendet), 0, 1000),
    ], 'id = ?', [(int)$meeting['id']]);
    audit('besprechung.protokoll_gesendet', 'meeting', (int)$meeting['id'], count($gesendet) . ' Empfänger');
    return ['ok' => true, 'gesendet' => count($gesendet), 'fehler' => $fehler,
        'text' => sprintf('Protokoll als PDF an %s verschickt%s.', implode(', ', $gesendet), $fehler ? ' – nicht erreicht: ' . implode(' · ', $fehler) : '')];
}
