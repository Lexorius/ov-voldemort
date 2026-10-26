<?php
declare(strict_types=1);

/** Lager an einem Platz: Bestand je Getränk, Bewegungen buchen, QR-Code für Leermeldungen */
$user = require_login();
if (!can('view_getraenke')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Das Getränkemodul ist für dich nicht freigegeben.']);
    return;
}

$standort = standort_find(get_int('standort_id'));
if ($standort === null) {
    // Ohne Platz: Platz wählen
    render('getraenke_lager', [
        'title'    => 'Lager',
        'standort' => null,
        'plaetze'  => standort_all(true),
        'lagerplaetze' => getraenke_lagerplaetze(),
        'darf'     => can('manage_getraenke'),
    ]);
    return;
}

$connector = connector_find((int)($standort['getraenke_qr_connector_id'] ?? 0));
if (trim((string)($standort['getraenke_qr_token'] ?? '')) !== '' && $connector !== null) {
    $GLOBALS['ovb_qr_js'] = true;
}

render('getraenke_lager', [
    'title'        => 'Lager ' . (string)$standort['name'],
    'standort'     => $standort,
    'plaetze'      => standort_all(true),
    'lagerplaetze' => getraenke_lagerplaetze(),
    'getraenke'    => getraenke_alle(true),
    'bestand'      => getraenke_bestand_platz((int)$standort['id']),
    'bewegungen'   => getraenke_bewegungen((int)$standort['id'], 40),
    'meldungen'    => array_values(array_filter(getraenke_meldungen_offen(), static fn($m) => (int)$m['standort_id'] === (int)$standort['id'])),
    'connector'    => $connector,
    'connectoren'  => connector_liste('getraenke'),
    'darf'         => can('manage_getraenke'),
]);
