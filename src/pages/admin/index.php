<?php
declare(strict_types=1);

require_role('admin');

render('admin/index', [
    'title' => 'Verwaltung',
    'zahlen' => [
        'benutzer' => (int)db_val('SELECT COUNT(*) FROM users', [], 0),
        'wuensche' => (int)db_val('SELECT COUNT(*) FROM wishes', [], 0),
        'aufgaben' => (int)db_val('SELECT COUNT(*) FROM todos', [], 0),
        'listen'   => (int)db_val('SELECT COUNT(*) FROM list_items', [], 0),
    ],
]);
