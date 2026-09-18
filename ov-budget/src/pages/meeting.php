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
$teilnehmer = attendance_list((int)$meeting['id']);
$vorgabe = setting_int('tp_dauer_vorgabe', 10);
$dauern = array_map(static fn($p) => $p['dauer_min'] !== null ? (int)$p['dauer_min'] : null, $punkte);

render('meeting', [
    'title'    => $meeting['titel'],
    'meeting'  => $meeting,
    'punkte'   => $punkte,
    'zeiten'   => meeting_agenda_times($meeting['beginn'], $dauern, $vorgabe),
    'gesamt'   => meeting_total_minutes($dauern, $vorgabe),
    'speicher' => can('manage_meetings') && $meeting['status'] === 'geplant' ? tp_backlog() : [],
    'teilnehmer'  => $teilnehmer,
    'anwesenheit' => attendance_stats($teilnehmer),
    'kandidaten'  => can('manage_meetings') ? attendance_candidate_users((int)$meeting['id']) : [],
    'kontakte'    => can('manage_meetings') && can('view_contacts')
        ? attendance_candidate_contacts((int)$meeting['id'], get_str('kontakt_suche')) : [],
    'kontaktSuche' => get_str('kontakt_suche'),
    'verteiler'   => can('manage_meetings') && can('view_contacts')
        ? db_all('SELECT id, name FROM contact_groups WHERE is_active = 1 ORDER BY name') : [],
    'quelle'      => can('manage_meetings') && !$teilnehmer ? attendance_source_meeting($meeting) : null,
    'aufgaben'    => meeting_todos((int)$meeting['id']),
    'personen'    => can('manage_meetings') && can('create_todo')
        ? db_all('SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name') : [],
]);
