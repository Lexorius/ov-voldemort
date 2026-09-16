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

echo render_partial('meeting_print', [
    'meeting' => $meeting,
    'punkte'  => $punkte,
    'zeiten'  => meeting_agenda_times($meeting['beginn'], $dauern, $vorgabe),
    'gesamt'  => meeting_total_minutes($dauern, $vorgabe),
    'art'     => $art,
]);
