<?php
declare(strict_types=1);

/* Tankungen eines Fahrzeugs als CSV */
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

$kennung = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($vehicle['kennzeichen'] ?: $vehicle['bezeichnung']));
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="tankungen_' . trim((string)$kennung, '_') . '_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");   // BOM, damit Excel die Umlaute richtig anzeigt
foreach (tank_csv_zeilen(tank_uebersicht((int)$vehicle['id'])) as $zeile) {
    fputcsv($out, csv_sicher($zeile));
}
fclose($out);
