<?php
declare(strict_types=1);

/* Einsätze: laufende oben, alle übrigen darunter, seitenweise */
$user = require_login();
if (!can('view_einsaetze')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Einsätze sind nur für die Leitung sichtbar.']);
    return;
}

$jahr = get_int('jahr');
$status = get_str('status');
$art = get_int('art_id');
$q = get_str('q');

$filter = ['jahr' => $jahr, 'status' => $status, 'art_id' => $art, 'q' => $q];

$uebrigeAlle = einsatz_query(array_merge($filter, ['laufend' => false]));
$blatt = seiten_blaettern(count($uebrigeAlle), get_int('seite') ?: 1, LISTE_JE_SEITE);

render('einsaetze', [
    'title'    => (string)setting('einsatz_modul_name', 'Einsätze'),
    'laufend'  => einsatz_query(array_merge($filter, ['laufend' => true])),
    'uebrige'  => array_slice($uebrigeAlle, $blatt['offset'], $blatt['pro_seite']),
    'blatt'    => $blatt,
    'blattParams' => seiten_params(),
    'jahre'    => einsatz_years(),
    'jahr'     => $jahr,
    'status'   => $status,
    'art'      => $art,
    'q'        => $q,
    'mitGeld'  => can('view_expenses'),
]);
