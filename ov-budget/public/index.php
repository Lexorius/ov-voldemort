<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

// Ohne Datenbankkonfiguration (Datei oder OVB_*-Umgebungsvariablen)
// geht es zum Einrichtungsassistenten.
if (!app_config('db')) {
    header('Location: install.php');
    exit;
}

$routes = [
    // öffentlich
    'login'              => 'login',
    'logout'             => 'logout',

    // angemeldet
    'dashboard'          => 'dashboard',
    'profile'            => 'profile',

    'wishes'             => 'wishes',
    'wish'               => 'wish_view',
    'wish_edit'          => 'wish_edit',
    'wish_action'        => 'wish_action',
    'wishes_export'      => 'wishes_export',

    'todos'              => 'todos',
    'todo'               => 'todo_view',
    'todo_edit'          => 'todo_edit',
    'todo_action'        => 'todo_action',

    'budget'             => 'budget',
    'budget_edit'        => 'budget_edit',

    'download'           => 'download',

    // Administration
    'admin'              => 'admin/index',
    'admin_users'        => 'admin/users',
    'admin_user_edit'    => 'admin/user_edit',
    'admin_lists'        => 'admin/lists',
    'admin_list_edit'    => 'admin/list_edit',
    'admin_settings'     => 'admin/settings',
    'admin_divera'       => 'admin/divera',
    'admin_divera_form'  => 'admin/divera_form',
    'admin_log'          => 'admin/log',
];

$route = (string)($_GET['p'] ?? 'dashboard');
if (!isset($routes[$route])) {
    $route = 'dashboard';
}

$publicRoutes = ['login', 'logout'];
if (!in_array($route, $publicRoutes, true)) {
    $currentUser = require_login();

    // Startpasswort muss geändert werden, bevor es weitergeht
    if ((int)$currentUser['must_change_pw'] === 1 && $route !== 'profile') {
        flash('warn', 'Bitte vergib zuerst ein eigenes Passwort.');
        redirect_route('profile');
    }
}

csrf_check();

try {
    require dirname(__DIR__) . '/src/pages/' . $routes[$route] . '.php';
} catch (PDOException $ex) {
    http_response_code(500);
    if (app_config('debug', false)) {
        throw $ex;
    }
    error_log('OV-Budget DB-Fehler: ' . $ex->getMessage());
    render('error', [
        'title'   => 'Datenbankfehler',
        'message' => 'Die Anfrage konnte nicht verarbeitet werden. Bitte später erneut versuchen.',
    ]);
} catch (Throwable $ex) {
    http_response_code(500);
    if (app_config('debug', false)) {
        throw $ex;
    }
    error_log('OV-Budget Fehler: ' . $ex->getMessage());
    render('error', [
        'title'   => 'Unerwarteter Fehler',
        'message' => 'Da ist etwas schiefgelaufen. Bitte die OV-Leitung informieren.',
    ]);
}
