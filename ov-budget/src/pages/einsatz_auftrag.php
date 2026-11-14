<?php
declare(strict_types=1);

/*
 * Einsatzauftrag (Fb Fü 3) schreiben – zu einem Einsatz oder zu einer Veranstaltung
 * (etwa einer Übung). Die Maske füllt den Vordruck im Browser (Bibliothek einsatzauftrag
 * mit jsPDF, siehe Seite „Bibliotheken und Lizenzen"); „Speichern" legt das PDF samt den
 * Feldern auf dem Server ab – am Einsatz bzw. bei den Dateien der Veranstaltung, beides
 * steht in der Dateiablage.
 *   ?einsatz_id=…            neuer Auftrag zu einem Einsatz
 *   ?event_id=…              neuer Auftrag zu einer Veranstaltung
 *   …&vorlage=…              mit den Texten eines früheren Auftrags derselben Stelle
 */
$user = require_login();
$ovName = (string)setting('ov_name', '');
$vorlageDatei = null;
$vorlage = null;

if (get_int('event_id')) {
    if (!can('manage_events')) {
        flash('error', 'Einsatzaufträge zu einer Veranstaltung schreibt, wer Veranstaltungen pflegt.');
        redirect_route('events');
    }
    $event = event_find(get_int('event_id', 0));
    if (!$event) {
        http_response_code(404);
        render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Veranstaltung gibt es nicht (mehr).']);
        return;
    }
    $id = (int)$event['id'];
    $dateien = efile_list($id);
    $vorlageDatei = get_int('vorlage') ? efile_find(get_int('vorlage')) : null;
    if ($vorlageDatei && (int)$vorlageDatei['event_id'] === $id && (string)($vorlageDatei['herkunft'] ?? '') === 'auftrag') {
        $vorlage = json_decode((string)$vorlageDatei['daten'], true) ?: null;
    }
    // Die Veranstaltung in der Form, in der die Vorgabe einen Einsatz erwartet
    $daten = einsatz_auftrag_vorgabe(['nummer' => '', 'titel' => (string)$event['titel'], 'ort' => event_ort_text($event),
        'beschreibung' => (string)($event['beschreibung'] ?? '')], $dateien, $user, $ovName, $vorlage);
    // Eine Übung ist eine Übung – der Ausdruck soll nie für einen echten Auftrag gehalten werden
    if (!$vorlage && (string)($event['typ_slug'] ?? '') === 'uebung') {
        $daten['uebung'] = true;
    }
    $ziel = ['name' => (string)$event['titel'], 'url' => url('event', ['id' => $id]), 'zurueck' => url('event', ['id' => $id]) . '#dateien',
             'wo' => 'an der Veranstaltung', 'art' => 'Veranstaltung'];
    $konfig = ['ablage' => url('event_action'), 'felder' => ['action' => 'auftrag_pdf', 'event_id' => $id]];
} else {
    if (!can('manage_einsaetze')) {
        flash('error', 'Einsatzaufträge schreibt, wer Einsätze erfassen darf.');
        redirect_route('einsaetze');
    }
    $einsatz = einsatz_find(get_int('einsatz_id', 0));
    if (!$einsatz) {
        http_response_code(404);
        render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Einsatz gibt es nicht (mehr).']);
        return;
    }
    $id = (int)$einsatz['id'];
    $dateien = einsatz_dateien($id);
    $vorlageDatei = get_int('vorlage') ? einsatz_datei_find(get_int('vorlage')) : null;
    if ($vorlageDatei && (int)$vorlageDatei['einsatz_id'] === $id && (string)$vorlageDatei['herkunft'] === 'auftrag') {
        $vorlage = json_decode((string)$vorlageDatei['daten'], true) ?: null;
    }
    $daten = einsatz_auftrag_vorgabe($einsatz, $dateien, $user, $ovName, $vorlage);
    $ziel = ['name' => einsatz_bezeichnung($einsatz), 'url' => url('einsatz', ['id' => $id]), 'zurueck' => url('einsatz', ['id' => $id]) . '#dateien',
             'wo' => 'am Einsatz', 'art' => 'Einsatz'];
    $konfig = ['ablage' => url('einsatz_action'), 'felder' => ['action' => 'pdf', 'herkunft' => 'auftrag', 'einsatz_id' => $id]];
}

$GLOBALS['ovb_einsatzauftrag_js'] = true;
render('einsatz_auftrag', [
    'title'   => 'Einsatzauftrag · ' . $ziel['name'],
    'ziel'    => $ziel,
    'daten'   => $daten,
    'vorlage' => $vorlage ? $vorlageDatei : null,
    'konfig'  => $konfig + ['csrf' => csrf_token(), 'zurueck' => $ziel['zurueck'], 'fusszeile' => $ovName],
]);
