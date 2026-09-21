<?php
declare(strict_types=1);

if (!can('view_meetings')) {
    http_response_code(403);
    exit('Kein Zugriff.');
}

$meeting = meeting_find((int)get_int('id', 0));
if (!$meeting) {
    http_response_code(404);
    exit('Besprechung nicht gefunden.');
}

$punkte = meeting_points((int)$meeting['id']);
$vorgabe = setting_int('tp_dauer_vorgabe', 10);
$dauern = array_map(static fn($p) => $p['dauer_min'] !== null ? (int)$p['dauer_min'] : null, $punkte);

// Protokoll erst sinnvoll, wenn es Ergebnisse gibt – sonst Tagesordnung
$art = get_str('art') === 'protokoll' ? 'protokoll' : 'tagesordnung';

// Anmerkungen: Einstellung, beim Drucken umschaltbar (?anmerkungen=1/0)
$umschalter = in_array(get_str('anmerkungen'), ['0', '1'], true) ? get_str('anmerkungen') : null;
$mitAnmerkungen = meeting_print_with_comments($art,
    (string)setting('protokoll_anmerkungen', 'protokoll'), $umschalter);

echo render_partial('meeting_print', [
    'meeting' => $meeting,
    'punkte'  => $punkte,
    'zeiten'  => meeting_agenda_times($meeting['beginn'], $dauern, $vorgabe),
    'gesamt'  => meeting_total_minutes($dauern, $vorgabe),
    'art'     => $art,
    'teilnehmer' => attendance_list((int)$meeting['id']),
    'aufgaben'   => meeting_todos((int)$meeting['id']),
    'anmerkungen' => $mitAnmerkungen
        ? tp_comments_for(array_map(static fn($p) => (int)$p['id'], $punkte)) : [],
    'mitAnmerkungen' => $mitAnmerkungen,
    'mitNamen'   => setting_bool('protokoll_anmerkungen_namen', true),
]);
