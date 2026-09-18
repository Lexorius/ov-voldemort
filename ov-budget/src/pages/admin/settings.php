<?php
declare(strict_types=1);

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nur die Gruppe speichern, die im Formular zu sehen war
    $gruppen = settings_grouped();
    $group = get_str('group');
    if (!isset($gruppen[$group])) {
        flash('error', 'Unbekannte Gruppe – nichts gespeichert.');
        redirect_route('admin_settings');
    }
    foreach (settings_from_post($gruppen[$group], $_POST) as $skey => $wert) {
        setting_save($skey, $wert);
    }
    audit('einstellungen.gespeichert', '', null, $group);
    flash('success', 'Einstellungen gespeichert.');
    redirect_route('admin_settings', ['group' => $group]);
}

$gruppen = settings_grouped();
$group = get_str('group', array_key_first($gruppen) ?: 'Allgemein');
if (!isset($gruppen[$group])) {
    $group = array_key_first($gruppen) ?: 'Allgemein';
}

render('admin/settings', [
    'title'   => 'Einstellungen',
    'gruppen' => $gruppen,
    'group'   => $group,
]);
