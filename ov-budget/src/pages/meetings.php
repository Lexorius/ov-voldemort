<?php
declare(strict_types=1);

if (!can('view_meetings')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Besprechungen sind nur für die Leitung sichtbar.']);
    return;
}

// Termine wiederkehrender Besprechungen nachziehen
series_materialize();

$typ = get_int('typ_id');

render('meetings', [
    'title'     => (string)setting('besprechung_modul_name', 'Besprechungen'),
    'kommend'   => meeting_query(['zeit' => 'kommend', 'typ_id' => $typ]),
    'vergangen' => meeting_query(['zeit' => 'vergangen', 'typ_id' => $typ, 'limit' => 50]),
    'speicher'  => count(tp_backlog()),
    'serien'    => series_all(),
    'typ'       => $typ,
]);
