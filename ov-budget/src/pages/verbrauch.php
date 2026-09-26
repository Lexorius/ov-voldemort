<?php
declare(strict_types=1);

if (!can('view_verbrauch')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Der Verbrauch ist nur für die Leitung sichtbar.']);
    return;
}

$jahr = get_int('jahr') ?: (int)date('Y');
$filter = ['art' => get_str('art'), 'aktiv' => get_str('aktiv') === 'alle' ? 'alle' : 'aktiv', 'q' => get_str('q')];
$meters = meter_query($filter);
$tarife = tarif_query();

// Je Zähler die Zahlen, die die Karte zeigt
$jetzt = time();
$karten = [];
foreach ($meters as $m) {
    $staende = readings_alle((int)$m['id']);
    $kosten = verbrauch_kosten_jahr($staende, $tarife, $m, $jahr);
    $karten[(int)$m['id']] = [
        'tage30' => verbrauch_zwischen($staende, $jetzt - 30 * 86400, $jetzt),
        'jahr'   => verbrauch_zwischen($staende, mktime(0, 0, 0, 1, 1, $jahr), min($jetzt, mktime(0, 0, 0, 1, 1, $jahr + 1))),
        'kosten' => $kosten['gesamt'],
        'ohne_tarif' => $kosten['ohne_tarif'],
        'alter'  => meter_stand_alter($m),
        'ha_fehler' => (string)$m['quelle'] === 'ha' ? state_get('verbrauch_ha_fehler_' . (int)$m['id'], '') : '',
    ];
}

$jahre = array_map('intval', array_column(db_all('SELECT DISTINCT YEAR(gelesen_am) AS j FROM meter_readings ORDER BY j DESC'), 'j'));
if (!in_array($jahr, $jahre, true)) {
    $jahre[] = $jahr;
    rsort($jahre);
}

render('verbrauch', [
    'title'  => (string)setting('verbrauch_modul_name', 'Verbrauch'),
    'meters' => $meters,
    'karten' => $karten,
    'stats'  => verbrauch_stats($meters, $tarife, $jahr),
    'tarife' => $tarife,
    'jahr'   => $jahr,
    'jahre'  => $jahre,
    'filter' => $filter,
]);
