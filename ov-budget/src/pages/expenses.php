<?php
declare(strict_types=1);

if (!can('view_expenses')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Ausgaben sind nur für die Leitung sichtbar.']);
    return;
}

$jahre = budget_years_known();
$jahr = get_int('jahr') ?: ($jahre[0] ?? setting_int('haushaltsjahr', (int)date('Y')));

$filters = [
    'jahr'          => $jahr,
    'q'             => get_str('q'),
    'kategorie_id'  => get_int('kategorie_id'),
    'fachgruppe_id' => get_int('fachgruppe_id'),
    'budget_id'     => get_int('budget_id'),
    'von'           => get_str('von'),
    'bis'           => get_str('bis'),
    'sort'          => get_str('sort', 'datum'),
];

$rows = expense_query($filters);

render('expenses', [
    'title'        => 'Ausgaben',
    'rows'         => $rows,
    'stats'        => expense_stats($rows),
    'filters'      => $filters,
    'jahr'         => $jahr,
    'jahre'        => $jahre,
    'jahresbudget' => budget_year_betrag($jahr),
    'jahresSumme'  => expense_total($jahr),
    'budgets'      => db_all('SELECT id, jahr, name FROM budgets WHERE jahr = ? ORDER BY name', [$jahr]),
]);
