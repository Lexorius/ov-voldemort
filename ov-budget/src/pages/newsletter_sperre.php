<?php
declare(strict_types=1);

/** Sperrliste des Newsletters: wer nichts mehr bekommt – über den Abmeldelink oder von Hand */
$user = require_login();
if (!can('view_newsletter')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Der Newsletter ist nur für die Leitung sichtbar.']);
    return;
}
$alle = newsletter_sperre_liste();
$blatt = seiten_blaettern(count($alle), get_int('seite') ?: 1, 100);
render('newsletter_sperre', [
    'title'       => 'Sperrliste',
    'rows'        => array_slice($alle, $blatt['offset'], $blatt['pro_seite']),
    'blatt'       => $blatt,
    'darf'        => can('manage_newsletter'),
    'connectoren' => connector_liste('newsletter'),
    'letzterAbruf' => (int)state_get('connector_newsletter_letzter_abruf', '0'),
]);
