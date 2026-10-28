<?php
declare(strict_types=1);

/** Newsletter: die Liste, seitenweise */
$user = require_login();
if (!can('view_newsletter')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Der Newsletter ist nur für die Leitung sichtbar.']);
    return;
}
$blatt = seiten_blaettern(newsletter_anzahl(), get_int('seite') ?: 1, NEWSLETTER_JE_SEITE);
render('newsletter', [
    'title'       => (string)setting('newsletter_modul_name', 'Newsletter'),
    'rows'        => newsletter_liste($blatt['offset'], $blatt['pro_seite']),
    'blatt'       => $blatt,
    'darf'        => can('manage_newsletter'),
    'mailAktiv'   => mail_eingerichtet(),
    'connectoren' => connector_liste('newsletter'),
    'gesperrt'    => (int)db_val('SELECT COUNT(*) FROM newsletter_sperre', [], 0),
]);
