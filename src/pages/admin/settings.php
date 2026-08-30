<?php
declare(strict_types=1);

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alle = settings_all();
    foreach ($alle as $skey => $def) {
        $field = 's_' . $skey;

        if ($def['stype'] === 'bool') {
            setting_save($skey, isset($_POST[$field]) ? '1' : '0');
            continue;
        }
        if (!array_key_exists($field, $_POST)) {
            continue;
        }
        $val = (string)$_POST[$field];

        // Leeres Passwortfeld = unverändert lassen
        if ($def['stype'] === 'password' && trim($val) === '') {
            continue;
        }
        setting_save($skey, trim($val));
    }
    audit('einstellungen.gespeichert');
    flash('success', 'Einstellungen gespeichert.');
    redirect_route('admin_settings', ['group' => get_str('group')]);
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
