<?php
declare(strict_types=1);

/**
 * Unterkunft: offene Punkte am Gebäude, dazu die Module, die es schon gibt –
 * Räume und Lagerflächen, Gebäudekosten aus dem Budget, Zähler.
 */
if (!can('view_unterkunft')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Unterkunft ist nur für die Leitung sichtbar.']);
    return;
}

$standorte = function_exists('standort_all') ? standort_all() : [];
$nachId = array_column($standorte, null, 'id');

$zustand = in_array(get_str('zustand'), ['offen', 'erledigt', 'alle'], true) ? get_str('zustand') : 'offen';
$standortId = get_int('standort');
$filter = [
    'zustand'       => $zustand,
    'art'           => array_key_exists(get_str('art'), UNTERKUNFT_ARTEN) ? get_str('art') : '',
    'status_id'     => get_int('status_id'),
    'prioritaet_id' => get_int('prioritaet_id'),
    'q'             => get_str('q'),
    'ohne_ort'      => get_str('ohne_ort') === '1',
];
if ($standortId && isset($nachId[$standortId])) {
    // Ein Platz samt allem, was darunter liegt
    $filter['standort_ids'] = array_merge([$standortId], function_exists('standort_nachkommen') ? standort_nachkommen($standortId, $standorte) : []);
}

$punkte = unterkunft_punkte($filter);
$offene = unterkunft_punkte(['zustand' => 'offen']);
$blatt = seiten_blaettern(count($punkte), get_int('seite') ?: 1, LISTE_JE_SEITE);
$jahr = haushaltsjahr();

render('unterkunft', [
    'title'      => (string)setting('unterkunft_modul_name', 'Unterkunft'),
    'standorte'  => $standorte,
    'punkte'     => array_slice($punkte, $blatt['offset'], $blatt['pro_seite']),
    'gesamt'     => count($punkte),
    'blatt'      => $blatt,
    'filter'     => $filter + ['standort' => $standortId && isset($nachId[$standortId]) ? $standortId : null],
    'stats'      => unterkunft_stats($offene),
    'gebaeude'   => unterkunft_gebaeude($standorte, $offene),
    'jahr'       => $jahr,
    'kosten'     => can('view_expenses') && function_exists('objektkonto_auswertung_jahr') ? objektkonto_auswertung_jahr($jahr) : null,
    'zaehler'    => can('view_verbrauch') && function_exists('meter_query') ? ['meter' => meter_query([]), 'stats' => verbrauch_stats_cached($jahr)] : null,
    'defektFormulare' => can('admin') ? db_all("SELECT id, name, last_sync, auto_import FROM divera_forms WHERE ziel = 'defekt' ORDER BY name") : [],
    'melden'     => can('report_unterkunft'),
    'verwalten'  => can('manage_unterkunft'),
]);
