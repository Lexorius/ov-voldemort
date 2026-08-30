<?php
declare(strict_types=1);

/** View innerhalb des Layouts rendern */
function render(string $view, array $vars = []): void
{
    $__content = render_partial($view, $vars);
    $title = $vars['title'] ?? null;
    include dirname(__DIR__, 2) . '/views/layout.php';
}

/** View ohne Layout als String */
function render_partial(string $view, array $vars = []): string
{
    $file = dirname(__DIR__, 2) . '/views/' . $view . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('View nicht gefunden: ' . $view);
    }
    extract($vars, EXTR_SKIP);
    ob_start();
    include $file;
    return (string)ob_get_clean();
}

/** Aktive Navigation markieren */
function nav_active(string $route, string ...$also): string
{
    $cur = $_GET['p'] ?? 'dashboard';
    return ($cur === $route || in_array($cur, $also, true)) ? ' is-active' : '';
}
