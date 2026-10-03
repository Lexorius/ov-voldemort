<?php
declare(strict_types=1);

/** Kalender: Monat oder Liste, Quellen zum Ein- und Ausblenden */

$user = current_user();
$heute = date('Y-m-d');

$monat = get_str('monat');
if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
    $monat = substr($heute, 0, 7);
}
[$jahr, $mon] = array_map('intval', explode('-', $monat));
$ansicht = get_str('ansicht') === 'liste' ? 'liste' : 'monat';

// Quellen: ohne Angabe alle, die die Person sehen darf
$erlaubt = kalender_quellen_fuer($user);
$gewaehlt = array_values(array_intersect((array)($_GET['q'] ?? []), $erlaubt));
$quellen = $gewaehlt ?: $erlaubt;

if ($ansicht === 'liste') {
    $von = $heute;
    $bis = date('Y-m-d', strtotime($heute) + 60 * 86400);
    $raster = null;
} else {
    $raster = kalender_monatsraster($jahr, $mon);
    $von = $raster['von'];
    $bis = $raster['bis'];
}
$eintraege = kalender_sammeln($von, $bis, $user, $quellen);

render('kalender', [
    'title'     => (string)setting('kalender_modul_name', 'Kalender'),
    'ansicht'   => $ansicht,
    'monat'     => $monat,
    'jahr'      => $jahr,
    'mon'       => $mon,
    'heute'     => $heute,
    'raster'    => $raster,
    'eintraege' => $eintraege,
    'jeTag'     => kalender_je_tag($eintraege, $von, $bis),
    'quellen'   => $quellen,
    'erlaubt'   => $erlaubt,
    'darfAnlegen' => can('create_kalender'),
    'haFehler'  => json_decode(state_get('kalender_ha_fehler', '[]'), true) ?: [],
]);
