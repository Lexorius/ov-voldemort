<?php
declare(strict_types=1);

if (!can('view_meetings')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Besprechungen sind nur für die Leitung sichtbar.']);
    return;
}

$meeting = meeting_find((int)get_int('id', 0));
if (!$meeting) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Besprechung gibt es nicht (mehr).']);
    return;
}

$punkte = meeting_points((int)$meeting['id']);
$vorgabe = setting_int('tp_dauer_vorgabe', 10);
$dauern = array_map(static fn($p) => $p['dauer_min'] !== null ? (int)$p['dauer_min'] : null, $punkte);

render('meeting', [
    'title'    => $meeting['titel'],
    'meeting'  => $meeting,
    'punkte'   => $punkte,
    'zeiten'   => meeting_agenda_times($meeting['beginn'], $dauern, $vorgabe),
    'gesamt'   => meeting_total_minutes($dauern, $vorgabe),
    'speicher' => can('manage_meetings') && $meeting['status'] === 'geplant' ? tp_backlog() : [],
]);
