<?php
declare(strict_types=1);

require_role('admin');

$letzter = stein_last_sync();
$pause = (int)setting('stein_pause_bis', '0');

render('admin/stein', [
    'title'      => 'Stein.APP',
    'aktiv'      => stein_enabled(),
    'letzter'    => $letzter,
    'intervall'  => stein_interval_minutes(),
    'wartet'     => stein_wait_reason($letzter, stein_interval_minutes(), $pause, time()),
    'offen'      => stein_pending_assets(),
    'fahrzeuge'  => vehicle_query(['nur_aktive' => 1]),
    'verknuepft' => db_all(
        "SELECT id, bezeichnung, stein_asset_id, stein_status, stein_sync_at
         FROM vehicles WHERE stein_asset_id IS NOT NULL AND stein_asset_id <> '' ORDER BY bezeichnung"
    ),
    'protokoll'  => db_all('SELECT * FROM stein_log ORDER BY id DESC LIMIT 20'),
    'webhook'    => trim((string)setting('stein_webhook_secret', '')) !== '',
    'debug'      => stein_debug_on(),
    'mitschnitte' => stein_debug_on() || stein_debug_files() ? stein_debug_files() : [],
]);
