<?php
declare(strict_types=1);

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    order_rights_save_from_post();
    setting_save('bestell_eigene_freigeben', post_bool('eigene_freigeben') ? '1' : '0');
    audit('bestellrechte.gespeichert');
    flash('success', 'Bestellberechtigungen gespeichert.');
    redirect_route('admin_order_rights');
}

$rows = order_rights_rows();

// Wirksame Rechte je Benutzer – zur Kontrolle, was die Einstellungen bewirken
$benutzer = [];
foreach (order_rights_users() as $u) {
    $r = order_rights_combine(order_rights_matches((string)$u['role'], $u['funktion_ids'], $rows));
    if ($r['freigeben'] || $r['bestellen']) {
        $benutzer[] = ['id' => (int)$u['id'], 'name' => $u['name']] + $r;
    }
}

render('admin/order_rights', [
    'title'      => 'Bestellberechtigungen',
    'rows'       => $rows,
    'funktionen' => list_items('funktion', false),
    'benutzer'   => $benutzer,
    'eigene'     => setting_bool('bestell_eigene_freigeben', true),
]);
