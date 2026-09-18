<?php
declare(strict_types=1);

if (!can('view_vehicles')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Fahrzeuge sind nur für die Leitung sichtbar.']);
    return;
}

$vehicle = vehicle_find((int)get_int('id', 0));
if (!$vehicle) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Fahrzeug gibt es nicht (mehr).']);
    return;
}

$id = (int)$vehicle['id'];
$art = get_str('art');

render('vehicle', [
    'title'    => $vehicle['bezeichnung'],
    'vehicle'  => $vehicle,
    'fristen'  => vehicle_deadlines($vehicle, setting_int('fahrzeug_frist_warnung_tage', 30)),
    'auftraege' => order_query(['vehicle_id' => $id]),
    'journal'  => journal_query($id, ['art' => $art, 'limit' => get_int('alles') === 1 ? 0 : 100]),
    'journalArt' => $art,
    'gesamt'   => (int)db_val('SELECT COUNT(*) FROM vehicle_journal WHERE vehicle_id = ?', [$id], 0),
    'extraFields' => vehicle_extra_fields(),
    'extra'    => extra_values($vehicle),
    'pruefung' => get_str('pruefen') === '1' ? journal_verify($id) : null,
    'bilder'   => array_values(array_filter(vfile_list($id, null, 'bild'), static fn($f) => !$f['order_id'])),
    'dokumente' => array_values(array_filter(vfile_list($id), static fn($f) => $f['art'] === 'dokument' || $f['order_id'])),
    'titelbild' => vfile_cover($id),
]);
