<?php
declare(strict_types=1);

/*
 * Einsatzauftrag (Fb Fü 3) zu einem Einsatz schreiben. Die Maske füllt den Vordruck im
 * Browser (Bibliothek einsatzauftrag mit jsPDF, siehe Seite „Bibliotheken und Lizenzen");
 * „Speichern" legt das PDF samt den Feldern am Einsatz ab – auf dem Server, in der
 * Dateiablage unter Einsätze.
 *   ?einsatz_id=…            neuer Auftrag
 *   ?einsatz_id=…&vorlage=…  neuer Auftrag mit den Texten eines früheren
 */
$user = require_login();
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

$vorlage = null;
$vorlageDatei = get_int('vorlage') ? einsatz_datei_find(get_int('vorlage')) : null;
if ($vorlageDatei && (int)$vorlageDatei['einsatz_id'] === $id && (string)$vorlageDatei['herkunft'] === 'auftrag') {
    $vorlage = json_decode((string)$vorlageDatei['daten'], true) ?: null;
}

$GLOBALS['ovb_einsatzauftrag_js'] = true;
render('einsatz_auftrag', [
    'title'   => 'Einsatzauftrag · ' . einsatz_bezeichnung($einsatz),
    'einsatz' => $einsatz,
    'daten'   => einsatz_auftrag_vorgabe($einsatz, $dateien, $user, (string)setting('ov_name', ''), $vorlage),
    'vorlage' => $vorlage ? $vorlageDatei : null,
    'konfig'  => [
        'ablage'    => url('einsatz_action'),
        'csrf'      => csrf_token(),
        'einsatzId' => $id,
        'zurueck'   => url('einsatz', ['id' => $id]) . '#dateien',
        'fusszeile' => (string)setting('ov_name', ''),
    ],
]);
