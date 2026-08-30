<?php
declare(strict_types=1);

require_role('admin');

$users = db_all(
    'SELECT u.*, fg.label AS fachgruppe_label,
            (SELECT GROUP_CONCAT(li.label ORDER BY li.sort_order SEPARATOR ", ")
               FROM user_functions uf JOIN list_items li ON li.id = uf.function_id
              WHERE uf.user_id = u.id) AS funktionen
     FROM users u
     LEFT JOIN list_items fg ON fg.id = u.fachgruppe_id
     ORDER BY u.is_active DESC, u.display_name, u.username'
);

render('admin/users', ['title' => 'Benutzer', 'users' => $users]);
