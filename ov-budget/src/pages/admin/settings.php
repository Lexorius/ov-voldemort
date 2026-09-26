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
    if ($group === 'Connector') {
        // Fußzeile gleich an die Connectoren bringen – wer nicht erreichbar ist, bekommt sie beim Abruf
        $res = connector_push_einstellungen_all();
        if ($res['gesendet'] > 0) {
            flash('success', sprintf('An %d Connector(en) übertragen.', $res['gesendet']));
        }
        foreach ($res['fehler'] as $name => $grund) {
            flash('warn', 'Connector „' . e((string)$name) . '" nicht erreichbar: ' . e($grund) . ' – wird beim nächsten Abruf nachgeholt.');
        }
    }
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
