<?php
declare(strict_types=1);

/** Jahresbericht über alle Zähler – zum Drucken und für Präsentationen */
if (!can('view_verbrauch')) {
    http_response_code(403);
    exit('Kein Zugriff.');
}

$jahr = get_int('jahr') ?: (int)date('Y');

echo render_partial('verbrauch_bericht', [
    'jahr'    => $jahr,
    'bericht' => verbrauch_jahresbericht(meter_query(['aktiv' => 'alle']), tarif_query(), $jahr),
]);
