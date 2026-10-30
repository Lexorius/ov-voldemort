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
$journalAlle = journal_query($id, ['art' => $art]);
$blatt = seiten_blaettern(count($journalAlle), get_int('seite') ?: 1, LISTE_JE_SEITE);

render('vehicle', [
    'ladung'   => vehicle_ladung($id),
    'termine'  => function_exists('kalender_termine_fahrzeug') ? kalender_termine_fahrzeug($id, date('Y-m-d'), date('Y-m-d', time() + 365 * 86400), current_user()) : [],
    'stellplatzPfad' => !empty($vehicle['standort_id']) && function_exists('standort_pfad') ? standort_pfad((int)$vehicle['standort_id'], standort_all()) : '',
    'wuensche'   => wish_query(['vehicle_id' => $id, 'sort' => 'prio']),
    'buchungen'  => can('view_expenses') && function_exists('expenses_fuer') ? expenses_fuer('vehicle', $id) : [],
    'besprochen' => can('view_meetings') && function_exists('tps_fuer') ? tps_fuer('vehicle', $id) : [],
    'favorit'    => vehicle_is_favorite((int)current_user()['id'], $id),
    'title'    => $vehicle['bezeichnung'],
    'vehicle'  => $vehicle,
    'fristen'  => vehicle_deadlines($vehicle, setting_int('fahrzeug_frist_warnung_tage', 30)),
    'auftraege' => order_query(['vehicle_id' => $id]),
    'journal'  => array_slice($journalAlle, $blatt['offset'], $blatt['pro_seite']),
    'blatt'    => $blatt,
    'journalArt' => $art,
    'gesamt'   => (int)db_val('SELECT COUNT(*) FROM vehicle_journal WHERE vehicle_id = ?', [$id], 0),
    'extraFields' => vehicle_extra_fields(),
    'extra'    => extra_values($vehicle),
    'pruefung' => get_str('pruefen') === '1' ? journal_verify($id) : null,
    'bilder'   => array_values(array_filter(vfile_list($id, null, 'bild'), static fn($f) => !$f['order_id'] && empty($f['tank_id']))),
    'dokumente' => array_values(array_filter(vfile_list($id), static fn($f) => ($f['art'] === 'dokument' || $f['order_id']) && empty($f['tank_id']))),
    'tanken'   => tank_uebersicht($id),
    'titelbild' => vfile_cover($id),
    'sims'     => can('view_sims') ? sim_for_vehicle($id) : [],
    'tankkarten' => can('view_tankkarten') && function_exists('tankkarten_for_vehicle') ? tankkarten_for_vehicle($id, true) : [],
    'funkgeraete' => can('view_radios') ? radio_for_vehicle($id) : [],
]);
