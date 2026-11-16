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
 *   …&muster=…               mit den Texten einer gespeicherten Vorlage (dieser Stelle oder global)
 *   POST action=vorlage_speichern  name, daten, [global=1] – die Maske als Vorlage merken; Antwort als JSON
 *   POST action=vorlage_weg        vorlage_id
 */
$user = require_login();
$ovName = (string)setting('ov_name', '');
$vorlageDatei = null;
$vorlage = null;
// Vorlagen gehören zu diesem Einsatz, zu dieser Veranstaltung – oder sind global
$eventZiel = get_int('event_id') ?: null;
$einsatzZiel = $eventZiel ? null : (get_int('einsatz_id') ?: null);
$muster = einsatz_auftrag_vorlage_find(get_int('muster'), $einsatzZiel, $eventZiel);

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
    if (!$vorlage && $muster) {
        $vorlage = json_decode((string)$muster['daten'], true) ?: null;
        $vorlageDatei = ['titel' => 'Vorlage ' . (string)$muster['name'], 'orig_name' => ''];
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
    if (!$vorlage && $muster) {
        $vorlage = json_decode((string)$muster['daten'], true) ?: null;
        $vorlageDatei = ['titel' => 'Vorlage ' . (string)$muster['name'], 'orig_name' => ''];
    }
    $daten = einsatz_auftrag_vorgabe($einsatz, $dateien, $user, $ovName, $vorlage);
    $ziel = ['name' => einsatz_bezeichnung($einsatz), 'url' => url('einsatz', ['id' => $id]), 'zurueck' => url('einsatz', ['id' => $id]) . '#dateien',
             'wo' => 'am Einsatz', 'art' => 'Einsatz'];
    $konfig = ['ablage' => url('einsatz_action'), 'felder' => ['action' => 'pdf', 'herkunft' => 'auftrag', 'einsatz_id' => $id]];
}

$selbst = url('einsatz_auftrag', $eventZiel ? ['event_id' => $id] : ['einsatz_id' => $id]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'vorlage_weg') {
        $weg = einsatz_auftrag_vorlage_find(post_int('vorlage_id'), $einsatzZiel, $eventZiel);
        if ($weg) {
            einsatz_auftrag_vorlage_delete($weg);
            flash('success', 'Vorlage „' . (string)$weg['name'] . '" gelöscht.');
        }
        redirect($selbst);
    }
    // Die Maske als Vorlage merken – ruft das Skript der Maske, deshalb JSON
    $ergebnis = einsatz_auftrag_vorlage_speichern(post_str('name'), post_str('daten'), post_str('global') === '1', $einsatzZiel, $eventZiel, $user);
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(is_string($ergebnis) ? 422 : 200);
    echo json_encode(is_string($ergebnis) ? ['ok' => false, 'fehler' => $ergebnis] : ['ok' => true] + $ergebnis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$GLOBALS['ovb_einsatzauftrag_js'] = true;
render('einsatz_auftrag', [
    'title'   => 'Einsatzauftrag · ' . $ziel['name'],
    'ziel'    => $ziel,
    'daten'   => $daten,
    'vorlage' => $vorlage ? $vorlageDatei : null,
    'vorlagen' => einsatz_auftrag_vorlagen($einsatzZiel, $eventZiel),
    'selbst'  => $selbst,
    'konfig'  => $konfig + ['csrf' => csrf_token(), 'zurueck' => $ziel['zurueck'], 'fusszeile' => $ovName, 'vorlagen' => $selbst],
]);
