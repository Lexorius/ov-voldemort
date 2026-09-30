<?php
declare(strict_types=1);

/** Bericht zu einem Zähler – zum Drucken und für Präsentationen */
if (!can('view_verbrauch')) {
    http_response_code(403);
    exit('Kein Zugriff.');
}

$meter = meter_find(get_int('id', 0) ?? 0);
if (!$meter) {
    http_response_code(404);
    exit('Zähler nicht gefunden.');
}
$jahr = get_int('jahr') ?: (int)date('Y');

echo render_partial('meter_bericht', [
    'meter'   => $meter,
    'jahr'    => $jahr,
    'bericht' => verbrauch_zaehlerbericht($meter, tarif_query((string)$meter['art']), $jahr),
]);
