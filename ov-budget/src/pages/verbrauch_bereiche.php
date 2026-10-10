<?php
declare(strict_types=1);

/** Kosten je Bereich: Verbrauch und Kosten entlang des Standortbaums */
if (!can('view_verbrauch')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Der Verbrauch ist nur für die Leitung sichtbar.']);
    return;
}

$jahr = get_int('jahr') ?: (int)date('Y');
$jahre = verbrauch_jahre();
if (!in_array($jahr, $jahre, true)) {
    $jahre[] = $jahr;
    rsort($jahre);
}
$b = verbrauch_bereiche_jahr($jahr);

render('verbrauch_bereiche', [
    'title'       => 'Kosten je Bereich ' . $jahr,
    'jahr'        => $jahr,
    'jahre'       => $jahre,
    'zeilen'      => $b['zeilen'],
    'ohneBereich' => $b['ohne_bereich'],
    'gesamt'      => $b['gesamt'],
    'bisHeute'    => $b['bis_heute'],
    'plaetzeDa'   => function_exists('standort_all') && standort_all() !== [],
]);
