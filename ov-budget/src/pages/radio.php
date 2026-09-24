<?php
declare(strict_types=1);

if (!can('view_radios')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Funkgeräte sind nur für die Leitung sichtbar.']);
    return;
}

$radio = radio_find(get_int('id', 0));
if (!$radio) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Funkgerät gibt es nicht (mehr).']);
    return;
}

render('radio', [
    'title'  => (string)$radio['bezeichnung'],
    'radio'  => $radio,
    'karten' => radio_cards((int)$radio['id']),
    'frei'   => can('manage_radios') && can('view_sims') ? radio_cards_free() : [],
    'connector' => connector_find((int)($radio['qr_connector_id'] ?? 0)),
    'bestandConnectoren' => can('manage_radios') ? connector_liste('bestand') : [],
    'warn'   => setting_int('funk_pruefung_warnung_tage', 30),
]);
