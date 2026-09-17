<?php
declare(strict_types=1);

require_role('admin');

// Zeit der Anwendung und der Datenbank vergleichen – gehen sie auseinander,
// stimmen Protokollzeiten nicht mit den selbst geschriebenen Zeitstempeln überein
$dbZeit = (string)db_val('SELECT NOW()', [], '');
$versatz = $dbZeit !== '' ? abs(time() - strtotime($dbZeit)) : 0;

render('admin/index', [
    'title' => 'Verwaltung',
    'zeit'  => [
        'zone'     => date_default_timezone_get(),
        'app'      => date('Y-m-d H:i:s'),
        'db'       => $dbZeit,
        'versatz'  => $versatz,
    ],
    'zahlen' => [
        'benutzer' => (int)db_val('SELECT COUNT(*) FROM users', [], 0),
        'wuensche' => (int)db_val('SELECT COUNT(*) FROM wishes', [], 0),
        'aufgaben' => (int)db_val('SELECT COUNT(*) FROM todos', [], 0),
        'listen'   => (int)db_val('SELECT COUNT(*) FROM list_items', [], 0),
    ],
]);
