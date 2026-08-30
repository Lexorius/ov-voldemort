<?php
declare(strict_types=1);

if (is_logged_in()) {
    redirect_route('dashboard');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post_str('username');
    [$ok, $msg] = auth_attempt($username, (string)post('password', ''));
    if ($ok) {
        audit('login', 'user', (int)($_SESSION['uid'] ?? 0) ?: null);
        $to = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        if ($to && str_starts_with($to, '/')) {
            redirect($to);
        }
        redirect_route('dashboard');
    }
    $error = $msg;
}

render('login', ['title' => 'Anmeldung', 'error' => $error, 'username' => $username]);
