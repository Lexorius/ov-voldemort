<?php
declare(strict_types=1);

/** Abstimmungen: die Liste */
$user = require_login();
if (!can('view_umfragen')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Abstimmungen sind nur für die Leitung sichtbar.']);
    return;
}

$liste = umfrage_all();
$jetzt = time();
$gruppen = ['laufend' => [], 'kommend' => [], 'entwurf' => [], 'vorbei' => []];
foreach ($liste as $u) {
    $z = umfrage_zustand($u, $jetzt);
    $u['zustand'] = $z;
    $gruppen[match ($z) {
        'laeuft'  => 'laufend',
        'wartet'  => 'kommend',
        'entwurf' => 'entwurf',
        default   => 'vorbei',
    }][] = $u;
}

render('umfragen', [
    'title'       => (string)setting('umfrage_modul_name', 'Abstimmungen'),
    'gruppen'     => $gruppen,
    'darf'        => can('manage_umfragen'),
    'connectoren' => connector_liste('umfragen'),
]);
