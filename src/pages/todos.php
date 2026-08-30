<?php
declare(strict_types=1);

$user = current_user();
$scope = get_str('scope', 'mine');   // mine | alle | ov | fachgruppe | funktion | user

$filters = [
    'q'         => get_str('q'),
    'status_id' => get_int('status_id'),
    'sort'      => get_str('sort', 'standard'),
    'offen'     => get_str('erledigt') === '1' ? 0 : 1,
];

switch ($scope) {
    case 'alle':
        break;
    case 'ov':
        $filters['target_type'] = 'ov';
        break;
    case 'fachgruppe':
        $filters['target_type'] = 'fachgruppe';
        $filters['target_id'] = get_int('target_id');
        break;
    case 'funktion':
        $filters['target_type'] = 'funktion';
        $filters['target_id'] = get_int('target_id');
        break;
    case 'user':
        $filters['target_type'] = 'user';
        $filters['target_id'] = get_int('target_id') ?: (int)$user['id'];
        break;
    default:
        $scope = 'mine';
        $filters['mine'] = $user;
}

$rows = todo_query($filters);

render('todos', [
    'title'   => (string)setting('todo_modul_name', 'Aufgaben'),
    'rows'    => $rows,
    'filters' => $filters,
    'scope'   => $scope,
    'users'   => db_all('SELECT id, display_name, username FROM users WHERE is_active = 1 ORDER BY display_name'),
]);
