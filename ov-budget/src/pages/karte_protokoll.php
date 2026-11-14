<?php
declare(strict_types=1);

/*
 * Protokoll einer Karte: wann welche Einheit und welches Fahrzeug gesetzt, versetzt oder
 * herausgenommen wurde – und wie lange jedes im Einsatz war.
 *   POST action=fahrzeug_auf   vehicle_id   ein Fahrzeug in den Einsatz nehmen (ohne es zu ziehen)
 *   POST action=fahrzeug_ende  vehicle_id   Einsatz des Fahrzeugs auf dieser Karte beendet
 *   ?csv=1                                  das Protokoll als CSV
 */
$user = require_login();
if (!can('view_karten')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Karten sind nur für die Leitung sichtbar.']);
    return;
}
$karte = karte_find(get_int('id'));
if (!$karte) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Karte gibt es nicht (mehr).']);
    return;
}
$karteId = (int)$karte['id'];
$darfFahrzeuge = can('mark_karten') && can('view_vehicles');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$darfFahrzeuge) {
        flash('error', 'Fahrzeuge in den Einsatz nehmen darfst du nicht.');
        redirect_route('karte_protokoll', ['id' => $karteId]);
    }
    $vehicle = vehicle_find((int)post_int('vehicle_id', 0));
    if (!$vehicle) {
        flash('error', 'Dieses Fahrzeug gibt es nicht (mehr).');
        redirect_route('karte_protokoll', ['id' => $karteId]);
    }
    $aktiv = karten_fahrzeuge_im_einsatz(karten_einsatzzeiten(karten_protokoll($karteId)));
    if (post_str('action') === 'fahrzeug_auf' && empty($aktiv[(int)$vehicle['id']])) {
        karten_protokoll_fahrzeug($karteId, $vehicle, 'gesetzt', $vehicle['geo_lat'] ?? null, $vehicle['geo_lng'] ?? null);
        flash('success', '„' . (string)$vehicle['bezeichnung'] . '" ist im Einsatz – die Zeit läuft ab jetzt.');
    } elseif (post_str('action') === 'fahrzeug_ende' && !empty($aktiv[(int)$vehicle['id']])) {
        karten_protokoll_fahrzeug($karteId, $vehicle, 'entfernt', null, null, ['lat' => $vehicle['geo_lat'] ?? null, 'lng' => $vehicle['geo_lng'] ?? null]);
        flash('success', 'Einsatz von „' . (string)$vehicle['bezeichnung'] . '" beendet.');
    }
    redirect_route('karte_protokoll', ['id' => $karteId]);
}

$daten = karten_protokoll_daten($karteId);

if (get_str('csv') === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kartenprotokoll_' . $karteId . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, csv_sicher(['Zeit', 'Art', 'Einheit / Fahrzeug', 'Was', 'von', 'nach', 'Abschnitt', 'Stärke', 'Wer']), ';');
    foreach ($daten['protokoll'] as $p) {
        fputcsv($out, csv_sicher([$p['zeit'], $p['art'], $p['name'], $p['was'], $p['von'], $p['nach'], $p['abschnitt'], $p['staerke'], $p['wer']]), ';');
    }
    fclose($out);
    exit;
}

$aktiv = [];
foreach ($daten['zeiten'] as $z) {
    if ($z['art'] === 'fahrzeug' && $z['aktiv']) {
        $aktiv[(int)$z['vehicle_id']] = true;
    }
}
render('karte_protokoll', [
    'title'     => 'Protokoll · ' . (string)$karte['name'],
    'karte'     => $karte,
    'einsatz'   => !empty($karte['einsatz_id']) && can('view_einsaetze') ? einsatz_find((int)$karte['einsatz_id']) : null,
    'zeiten'    => $daten['zeiten'],
    'protokoll' => array_reverse($daten['protokoll']),   // das Neueste oben
    'darfFahrzeuge' => $darfFahrzeuge,
    // Fahrzeuge, die noch nicht im Einsatz stehen – zum Aufnehmen
    'fahrzeuge' => $darfFahrzeuge
        ? array_values(array_filter(db_all('SELECT id, bezeichnung, funkrufname FROM vehicles WHERE is_active = 1 ORDER BY bezeichnung'),
            static fn(array $v): bool => empty($aktiv[(int)$v['id']])))
        : [],
]);
