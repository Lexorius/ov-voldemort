<?php
declare(strict_types=1);

/* Karten: die Kartenausschnitte in der Übersicht */
$user = require_login();
if (!can('view_karten')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Karten sind nur für die Leitung sichtbar.']);
    return;
}

render('karten', [
    'title'     => (string)setting('karten_modul_name', 'Karten'),
    'karten'    => karten_liste(),
    'symbole'   => (int)db_val('SELECT COUNT(*) FROM karten_symbole', [], 0),
    'verwalten' => can('manage_karten'),
]);
