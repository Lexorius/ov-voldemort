<?php
declare(strict_types=1);

require_role('admin');

$tab = get_str('tab', 'audit');
if (!in_array($tab, ['audit', 'divera', 'login'], true)) {
    $tab = 'audit';
}

// Die Protokolle werden lang – hier blättert die Datenbank, nicht PHP
$tabelle = match ($tab) { 'divera' => 'divera_log', 'login' => 'login_attempts', default => 'audit_log' };
$blatt = seiten_blaettern((int)db_val('SELECT COUNT(*) FROM ' . $tabelle, [], 0), get_int('seite') ?: 1, 100);
$fenster = ' LIMIT ' . (int)$blatt['pro_seite'] . ' OFFSET ' . (int)$blatt['offset'];
$rows = match ($tab) {
    'divera' => db_all('SELECT * FROM divera_log ORDER BY created_at DESC, id DESC' . $fenster),
    'login'  => db_all('SELECT * FROM login_attempts ORDER BY created_at DESC, id DESC' . $fenster),
    default  => db_all(
        'SELECT a.*, u.display_name, u.username
         FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC, a.id DESC' . $fenster
    ),
};

render('admin/log', ['title' => 'Protokoll', 'tab' => $tab, 'rows' => $rows, 'blatt' => $blatt]);
