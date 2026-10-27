<?php
declare(strict_types=1);

/** Protokoll einer Besprechung als PDF herunterladen oder im Browser ansehen */
$user = require_login();
if (!can('view_meetings')) {
    http_response_code(403);
    exit('Kein Zugriff.');
}
$meeting = meeting_find(get_int('id'));
if (!$meeting) {
    http_response_code(404);
    exit('Besprechung nicht gefunden.');
}
$pdf = meeting_protokoll_pdf($meeting, meeting_protokoll_daten($meeting));
$name = meeting_protokoll_dateiname($meeting);
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: ' . (get_str('laden') === '1' ? 'attachment' : 'inline') . '; filename="' . $name . '"');
header('Cache-Control: no-store');
header('Content-Security-Policy: default-src \'none\'; object-src \'none\'');
echo $pdf;
