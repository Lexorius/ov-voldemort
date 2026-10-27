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

$alle = todo_query($filters);
$blatt = seiten_blaettern(count($alle), get_int('seite') ?: 1, LISTE_JE_SEITE);

render('todos', [
    'title'   => (string)setting('todo_modul_name', 'Aufgaben'),
    'rows'    => array_slice($alle, $blatt['offset'], $blatt['pro_seite']),
    'blatt'   => $blatt,
    'blattParams' => seiten_params(),
    'filters' => $filters,
    'scope'   => $scope,
    'users'   => db_all('SELECT id, display_name, username FROM users WHERE is_active = 1 ORDER BY display_name'),
]);
