<?php
declare(strict_types=1);

require_role('admin');

$letzter = static fn(string $k) => (int)setting($k, '0');

render('admin/divera_fahrzeuge', [
    'title'      => 'Divera-Fahrzeuge',
    'aktiv'      => divera_vehicles_enabled(),
    'grundlage'  => setting_bool('divera_aktiv', false) && trim((string)setting('divera_accesskey', '')) !== '',
    'status'     => $letzter('divera_status_letzter_abruf'),
    'stamm'      => $letzter('divera_stamm_letzter_abruf'),
    'offen'      => dv_pending(),
    'fahrzeuge'  => db_all(
        'SELECT id, bezeichnung, funkrufname, kennzeichen FROM vehicles
         WHERE is_active = 1 AND divera_vehicle_id IS NULL ORDER BY bezeichnung'
    ),
    'verknuepft' => db_all(
        'SELECT id, bezeichnung, funkrufname, opta, ric, divera_vehicle_id, fms_status, fms_at, divera_sync_at
         FROM vehicles WHERE divera_vehicle_id IS NOT NULL ORDER BY bezeichnung'
    ),
    'protokoll'  => db_all("SELECT * FROM divera_log WHERE form_id = 'fahrzeuge' ORDER BY id DESC LIMIT 20"),
]);
