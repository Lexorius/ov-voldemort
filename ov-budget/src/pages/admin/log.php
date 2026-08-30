<?php
declare(strict_types=1);

require_role('admin');

$tab = get_str('tab', 'audit');
if (!in_array($tab, ['audit', 'divera', 'login'], true)) {
    $tab = 'audit';
}

$rows = match ($tab) {
    'divera' => db_all('SELECT * FROM divera_log ORDER BY created_at DESC LIMIT 300'),
    'login'  => db_all('SELECT * FROM login_attempts ORDER BY created_at DESC LIMIT 300'),
    default  => db_all(
        'SELECT a.*, u.display_name, u.username
         FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC LIMIT 300'
    ),
};

render('admin/log', ['title' => 'Protokoll', 'tab' => $tab, 'rows' => $rows]);
