<?php
declare(strict_types=1);

$user = current_user();

$filters = [
    'q'                => get_str('q'),
    'status_id'        => get_int('status_id'),
    'fachgruppe_id'    => get_int('fachgruppe_id'),
    'kategorie_id'     => get_int('kategorie_id'),
    'dringlichkeit_id' => get_int('dringlichkeit_id'),
    'budget_id'        => get_int('budget_id'),
    'nice'             => get_str('nice'),
    'sort'             => get_str('sort', 'prio'),
    'offen'            => get_str('alle') === '1' ? 0 : 1,
    'mine'             => get_str('mine') === '1' ? (int)$user['id'] : null,
];

$rows = wish_query($filters);
$stats = wish_stats($rows);
$votes = wish_votes_of_user((int)$user['id']);

render('wishes', [
    'title'   => (string)setting('wunsch_modul_name', 'Wünsch dir was'),
    'rows'    => $rows,
    'stats'   => $stats,
    'filters' => $filters,
    'votes'   => $votes,
    'budgets' => db_all('SELECT id, jahr, name FROM budgets ORDER BY jahr DESC, name'),
]);
