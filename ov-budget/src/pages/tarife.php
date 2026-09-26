<?php
declare(strict_types=1);

if (!can('view_verbrauch')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Der Verbrauch ist nur für die Leitung sichtbar.']);
    return;
}

$heute = date('Y-m-d');
$tarife = tarif_query();
$jeArt = [];
foreach (METER_ARTEN as $key => $a) {
    $jeArt[$key] = ['aktuell' => tarif_am($tarife, $key, $heute), 'liste' => []];
}
foreach ($tarife as $t) {
    $jeArt[(string)$t['art']]['liste'][] = $t;
}

render('tarife', [
    'title' => 'Tarife',
    'jeArt' => $jeArt,
    'heute' => $heute,
]);
