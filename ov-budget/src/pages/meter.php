<?php
declare(strict_types=1);

if (!can('view_verbrauch')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Der Verbrauch ist nur für die Leitung sichtbar.']);
    return;
}

$meter = meter_find(get_int('id', 0) ?? 0);
if (!$meter) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Zähler gibt es nicht (mehr).']);
    return;
}

$jahr = get_int('jahr') ?: (int)date('Y');
[$von, $bis] = verbrauch_zeitraum($jahr);
$staende = readings_bereich((int)$meter['id'], $von, $bis);
$tarife = tarif_query((string)$meter['art']);

$jahre = verbrauch_jahre((int)$meter['id']);
if (!in_array($jahr, $jahre, true)) {
    $jahre[] = $jahr;
    rsort($jahre);
}

render('meter', [
    'title'      => (string)$meter['name'],
    'meter'      => $meter,
    'jahr'       => $jahr,
    'jahre'      => $jahre,
    'staende'    => readings_query((int)$meter['id'], null, 60),
    'monate'     => verbrauch_monate($staende, $jahr),
    'kosten'     => verbrauch_kosten_jahr($staende, $tarife, $meter, $jahr),
    'abschnitte' => array_reverse(array_slice(verbrauch_abschnitte($staende), -12)),
    'profil'     => verbrauch_profil($staende, mktime(0, 0, 0, 1, 1, $jahr), min(time(), mktime(0, 0, 0, 1, 1, $jahr + 1))),
    'tarifHeute' => meter_tarif_art($meter) ? tarif_am($tarife, (string)meter_tarif_art($meter), date('Y-m-d')) : null,
    'alter'      => meter_stand_alter($meter),
    'haFehler'   => (string)$meter['quelle'] === 'ha' ? state_get('verbrauch_ha_fehler_' . (int)$meter['id'], '') : '',
    'connector'  => connector_find((int)($meter['qr_connector_id'] ?? 0)),
    'unterzaehler' => (int)($meter['unterzaehler'] ?? 0) > 0 ? meter_unterzaehler_mit_anteil($meter, $jahr) : [],
    'zaehlerConnectoren' => can('manage_verbrauch') ? connector_liste('verbrauch') : [],
]);
