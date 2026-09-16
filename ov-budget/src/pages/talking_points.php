<?php
declare(strict_types=1);

if (!can('view_meetings')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Besprechungen sind nur für die Leitung sichtbar.']);
    return;
}

$filters = ['q' => get_str('q'), 'fachgruppe_id' => get_int('fachgruppe_id')];

render('talking_points', [
    'title'    => 'Themenspeicher',
    'rows'     => tp_backlog($filters),
    'filters'  => $filters,
    'geplant'  => can('manage_meetings') ? meeting_query(['zeit' => 'kommend']) : [],
]);
