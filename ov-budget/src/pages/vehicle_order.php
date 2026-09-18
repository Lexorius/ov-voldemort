<?php
declare(strict_types=1);

if (!can('view_vehicles')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Fahrzeuge sind nur für die Leitung sichtbar.']);
    return;
}

$order = order_find((int)get_int('id', 0));
if (!$order) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Auftrag gibt es nicht (mehr).']);
    return;
}

render('vehicle_order', [
    'title'   => $order['nummer'] . ' · ' . $order['titel'],
    'order'   => $order,
    'vehicle' => vehicle_find((int)$order['vehicle_id']),
    'verlauf' => journal_query((int)$order['vehicle_id'], ['ref_typ' => 'auftrag', 'ref_id' => (int)$order['id']]),
    'dateien' => vfile_list((int)$order['vehicle_id'], (int)$order['id']),
]);
