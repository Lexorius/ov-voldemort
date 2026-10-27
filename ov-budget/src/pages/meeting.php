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

$protokollEmpfaenger = function_exists('meeting_protokoll_empfaenger') ? meeting_protokoll_empfaenger((int)$meeting['id']) : [];
$protokollVorlage = meeting_protokoll_vorlage($meeting, $user ?? (current_user() ?? []), (string)setting('ov_name', ''));

render('meeting', [
    'protokollEmpfaenger' => $protokollEmpfaenger,
    'protokollVerteiler'  => function_exists('meeting_protokoll_verteiler') ? meeting_protokoll_verteiler((int)$meeting['id']) : [],
    'protokollVorlage'    => $protokollVorlage,
    'mailAktiv'           => mail_eingerichtet(),
    'title'    => $meeting['titel'],
    'meeting'  => $meeting,
    'navi'     => meeting_navi($meeting),
    'entfernung' => ($navi = meeting_navi($meeting)) && function_exists('geo_entfernung') ? geo_entfernung($navi['ziel']) : null,
    'fahrzeuge' => can('view_vehicles') && function_exists('meeting_fahrzeuge') ? meeting_fahrzeuge((int)$meeting['id']) : [],
    'punkte'   => $punkte,
    'tpLinks'  => $tpLinks = tp_links_fuer(array_column($punkte, 'id')),
    'tpEvents' => tp_event_kurzberichte($tpLinks),
    'tpBudgets' => tp_budget_kurzberichte($tpLinks),
    'zeiten'   => meeting_agenda_times($meeting['beginn'], $dauern, $vorgabe),
    'gesamt'   => meeting_total_minutes($dauern, $vorgabe),
    'speicher' => can('manage_meetings') && $meeting['status'] === 'geplant' ? tp_backlog() : [],
    'termine'  => can('manage_meetings') && $meeting['status'] === 'geplant' ? kalender_fuer_besprechung($meeting, current_user()) : [],
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
