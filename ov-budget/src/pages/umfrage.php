<?php
declare(strict_types=1);

/** Eine Abstimmung: Zugang, Fragen, Ergebnisse */
$user = require_login();
if (!can('view_umfragen')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Abstimmungen sind nur für die Leitung sichtbar.']);
    return;
}

$umfrage = umfrage_find(get_int('id'));
if ($umfrage === null) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Abstimmung gibt es nicht (mehr).']);
    return;
}

$connector = connector_find((int)($umfrage['connector_id'] ?? 0));
$seiten = umfrage_seiten($umfrage);
$antworten = umfrage_antworten($umfrage);
$zustand = umfrage_zustand($umfrage);
if ($connector !== null && connector_gekoppelt($connector) && in_array($zustand, ['wartet', 'laeuft', 'abgelaufen'], true)) {
    $GLOBALS['ovb_qr_js'] = true;
}

render('umfrage', [
    'title'        => (string)$umfrage['titel'],
    'umfrage'      => $umfrage,
    'seiten'       => $seiten,
    'connector'    => $connector,
    'zustand'      => $zustand,
    'adresse'      => $connector !== null ? umfrage_url($connector, (string)$umfrage['code']) : '',
    'antworten'    => $antworten,
    'auswertung'   => umfrage_auswertung($seiten, $antworten),
    'darf'         => can('manage_umfragen'),
    'letzterAbruf' => (int)state_get('umfrage_letzter_abruf', '0'),
    'abholbar'     => $connector !== null && connector_taugt($connector, 'umfragen'),
    'kontakt'      => (int)($umfrage['bericht_kontakt_id'] ?? 0) > 0 ? contact_find((int)$umfrage['bericht_kontakt_id']) : null,
    'mailAktiv'    => mail_eingerichtet(),
]);
