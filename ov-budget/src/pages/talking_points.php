<?php
declare(strict_types=1);

if (!can('view_meetings')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Besprechungen sind nur für die Leitung sichtbar.']);
    return;
}

series_materialize();

$tab = get_str('tab') === 'archiv' ? 'archiv' : 'offen';
$filters = ['q' => get_str('q'), 'fachgruppe_id' => get_int('fachgruppe_id')];

if ($tab === 'archiv') {
    $datum = static fn(string $k): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', get_str($k)) ? get_str($k) : null;
    $filters += ['status_id' => get_int('status_id'), 'von' => $datum('von'), 'bis' => $datum('bis')];
    $rows = tp_archive($filters);
    $mehr = count($rows) > TP_ARCHIV_LIMIT;
    render('talking_points_archiv', [
        'title'   => 'Themenarchiv',
        'rows'    => array_slice($rows, 0, TP_ARCHIV_LIMIT),
        'mehr'    => $mehr,
        'filters' => $filters,
    ]);
    return;
}

render('talking_points', [
    'title'    => 'Themenspeicher',
    'rows'     => tp_backlog($filters),
    'filters'  => $filters,
    'geplant'  => can('manage_meetings') ? meeting_query(['zeit' => 'kommend', 'nur_geplant' => 1]) : [],
    'diveraThemen' => divera_enabled()
        ? db_all("SELECT id, name, last_sync FROM divera_forms WHERE ziel = 'thema' ORDER BY name") : [],
]);
