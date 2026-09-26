<?php
declare(strict_types=1);

/** Reihenfolge der Menüleiste */
require_role('admin');

$module = nav_module();
$keys = array_keys(nav_sortieren($module, nav_reihenfolge()));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {
        case 'hoch':
        case 'runter':
            $keys = nav_verschieben($keys, post_str('key'), post_str('action') === 'hoch' ? -1 : 1);
            state_save('nav_reihenfolge', implode(',', $keys));
            audit('menue.reihenfolge', '', null, implode(',', $keys));
            break;
        case 'standard':
            state_save('nav_reihenfolge', '');
            audit('menue.reihenfolge', '', null, 'Vorgabe');
            flash('success', 'Die Menüleiste steht wieder in der Vorgabereihenfolge.');
            break;
    }
    redirect_route('admin_nav');
}

render('admin/nav', [
    'title'  => 'Menüleiste',
    'module' => nav_sortieren($module, nav_reihenfolge()),
    'eigene' => nav_reihenfolge() !== '',
]);
