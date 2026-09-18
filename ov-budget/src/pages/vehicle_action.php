<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('vehicles');
}

$user = require_login();
$zurueck = url('vehicles');

switch (post_str('action')) {

    case 'note':
        // Journaleintrag von Hand – anschließend nicht mehr änderbar
        $vehicle = vehicle_find(post_int('vehicle_id', 0) ?? 0);
        $text = trim(post_str('text'));
        if (!$vehicle || !can('report_vehicle')) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $zurueck = url('vehicle', ['id' => $vehicle['id']]) . '#journal';
        if ($text === '') {
            flash('warn', 'Der Eintrag war leer.');
            break;
        }
        journal_add((int)$vehicle['id'], [
            'art'   => 'notiz',
            'titel' => mb_substr(trim(explode("\n", $text)[0]), 0, 120),
            'text'  => $text,
        ], $user);
        audit('fahrzeug.journal', 'vehicle', (int)$vehicle['id'], mb_substr($text, 0, 100));
        flash('success', 'Eintrag im Journal festgehalten.');
        break;

    case 'file_upload':
        // Bilder und Dokumente: Fahrzeug oder Auftrag
        $vehicle = vehicle_find(post_int('vehicle_id', 0) ?? 0);
        if (!$vehicle || !can('report_vehicle')) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $orderId = post_int('order_id') ?: null;
        if ($orderId !== null) {
            $order = order_find($orderId);
            if (!$order || (int)$order['vehicle_id'] !== (int)$vehicle['id']) {
                flash('error', 'Auftrag nicht gefunden.');
                break;
            }
            $zurueck = url('vehicle_order', ['id' => $orderId]) . '#dateien';
        } else {
            $zurueck = url('vehicle', ['id' => $vehicle['id']])
                . (post_str('art') === 'bild' ? '#bilder' : '#dokumente');
        }
        $art = in_array(post_str('art'), ['bild', 'dokument', 'auto'], true) ? post_str('art') : 'auto';
        [$n, $fehler] = vfile_store_uploads((int)$vehicle['id'], $orderId, 'dateien', $art, post_str('titel'), $user);
        foreach ($fehler as $f) {
            flash('warn', e($f));
        }
        if ($n > 0) {
            flash('success', sprintf('%d Datei(en) gespeichert.', $n));
        }
        break;

    case 'file_delete':
        $datei = vfile_find(post_int('file_id', 0) ?? 0);
        if (!$datei || !can('manage_vehicles')) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $zurueck = $datei['order_id']
            ? url('vehicle_order', ['id' => $datei['order_id']]) . '#dateien'
            : url('vehicle', ['id' => $datei['vehicle_id']]) . ($datei['art'] === 'bild' ? '#bilder' : '#dokumente');
        vfile_delete($datei, $user);
        flash('success', 'Datei entfernt. Im Journal steht, dass es sie gab.');
        break;

    case 'file_cover':
        $datei = vfile_find(post_int('file_id', 0) ?? 0);
        if (!$datei || !can('manage_vehicles') || $datei['art'] !== 'bild') {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        vfile_set_cover((int)$datei['vehicle_id'], (int)$datei['id']);
        $zurueck = url('vehicle', ['id' => $datei['vehicle_id']]) . '#bilder';
        flash('success', 'Titelbild gesetzt.');
        break;

    case 'order_status':
        $order = order_find(post_int('order_id', 0) ?? 0);
        if (!$order || !can('manage_vehicles')) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $zurueck = url('vehicle_order', ['id' => $order['id']]);
        $kosten = post_dec('kosten_netto');
        if ($kosten > 0 || (string)post_str('kosten_netto') === '0') {
            db_update('vehicle_orders', ['kosten_netto' => $kosten ?: null], 'id = ?', [(int)$order['id']]);
        }
        $fehler = order_set_status($order, post_int('status_id', 0) ?? 0, post_str('bemerkung'), $user);
        flash($fehler === null ? 'success' : 'error', $fehler ?? 'Stand eingetragen.');
        break;

    case 'stein_sync':
        if (!can('admin')) {
            flash('error', 'Das darf nur die Administration.');
            break;
        }
        $zurueck = url('admin_stein');
        $res = stein_sync(post_str('erzwingen') === '1');
        flash(match ($res['status']) {
            'ok' => 'success',
            'wartet', 'aus' => 'warn',
            default => 'error',
        }, e($res['message']));
        break;

    case 'stein_test':
        if (!can('admin')) {
            flash('error', 'Das darf nur die Administration.');
            break;
        }
        $zurueck = url('admin_stein');
        try {
            $info = stein_userinfo();
            $wer = trim((string)($info['name'] ?? '')) ?: 'unbekannt';
            $mail = trim((string)($info['email'] ?? ''));
            flash('success', sprintf(
                'Verbindung steht. Der Schlüssel gehört zu %s%s%s.',
                e($wer),
                $mail !== '' ? ' (' . e($mail) . ')' : '',
                isset($info['scope']) ? ', Bereich ' . e((string)$info['scope']) : ''
            ));
        } catch (SteinException $ex) {
            flash('error', e($ex->getMessage()));
        }
        break;

    case 'stein_debug_clear':
        if (!can('admin')) {
            flash('error', 'Das darf nur die Administration.');
            break;
        }
        $zurueck = url('admin_stein') . '#mitschnitt';
        $weg = stein_debug_delete_all();
        audit('stein.mitschnitte_geloescht', '', null, (string)$weg);
        flash('success', sprintf('%d Mitschnitt(e) gelöscht.', $weg));
        break;

    case 'dv_sync':
        if (!can('admin')) {
            flash('error', 'Das darf nur die Administration.');
            break;
        }
        $zurueck = url('admin_divera_fahrzeuge');
        foreach ([divera_vehicles_sync_master(true), divera_vehicles_sync_status(true)] as $res) {
            flash($res['status'] === 'ok' ? 'success' : ($res['status'] === 'fehler' ? 'error' : 'warn'), e($res['message']));
        }
        break;

    case 'dv_assign':
        if (!can('admin')) {
            flash('error', 'Das darf nur die Administration.');
            break;
        }
        $zurueck = url('admin_divera_fahrzeuge');
        $diveraId = post_int('divera_id', 0) ?? 0;
        $vehicle = vehicle_find(post_int('vehicle_id', 0) ?? 0);
        if (!$vehicle || $diveraId <= 0) {
            flash('error', 'Bitte ein Fahrzeug auswählen.');
            break;
        }
        if (db_val('SELECT id FROM vehicles WHERE divera_vehicle_id = ?', [$diveraId])) {
            flash('error', 'Dieses Divera-Fahrzeug ist schon zugeordnet.');
            break;
        }
        dv_link((int)$vehicle['id'], $diveraId, 'Von Hand zugeordnet.', $user);
        flash('success', 'Zugeordnet. Der nächste Abruf holt den Funkstatus.');
        break;

    case 'dv_unassign':
        $vehicle = vehicle_find(post_int('vehicle_id', 0) ?? 0);
        if (!$vehicle || !can('manage_vehicles')) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $zurueck = url('vehicle', ['id' => $vehicle['id']]);
        db_update('vehicles', ['divera_vehicle_id' => null, 'fms_status' => null, 'fms_at' => null,
            'fms_note' => '', 'geo_lat' => null, 'geo_lng' => null, 'geo_at' => null, 'divera_besatzung' => null],
            'id = ?', [(int)$vehicle['id']]);
        journal_add((int)$vehicle['id'], [
            'art'      => 'divera',
            'titel'    => 'Verknüpfung mit Divera gelöst',
            'alt_wert' => (string)$vehicle['divera_vehicle_id'],
        ], $user);
        flash('success', 'Verknüpfung mit Divera gelöst.');
        break;

    case 'stein_assign':
        if (!can('admin')) {
            flash('error', 'Das darf nur die Administration.');
            break;
        }
        $zurueck = url('admin_stein');
        $assetId = trim(post_str('asset_id'));
        $vehicleId = post_int('vehicle_id', 0) ?? 0;

        if ($vehicleId === 0) {
            // Neues Fahrzeug aus dem Stein-Eintrag anlegen
            $name = trim(post_str('name')) ?: ('Fahrzeug ' . $assetId);
            $vehicleId = db_insert('vehicles', [
                'bezeichnung'    => mb_substr($name, 0, 150),
                'funkrufname'    => mb_substr(post_str('funk'), 0, 80),
                'kennzeichen'    => mb_substr(post_str('kennzeichen'), 0, 20),
                'status_id'      => list_default_id('fahrzeug_status'),
                'created_by'     => (int)$user['id'],
            ]);
            journal_add($vehicleId, [
                'art'   => 'anlage',
                'titel' => 'Fahrzeugakte angelegt',
                'text'  => 'Aus der Stein.APP übernommen.',
            ], $user);
        }
        $fehler = stein_assign($vehicleId, $assetId, $user);
        flash($fehler === null ? 'success' : 'error',
            $fehler ?? 'Fahrzeug verknüpft. Der nächste Abgleich holt den Stand.');
        break;

    case 'stein_unassign':
        $vehicle = vehicle_find(post_int('vehicle_id', 0) ?? 0);
        if (!$vehicle || !can('manage_vehicles')) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $zurueck = url('vehicle', ['id' => $vehicle['id']]);
        db_update('vehicles', ['stein_asset_id' => null], 'id = ?', [(int)$vehicle['id']]);
        journal_add((int)$vehicle['id'], [
            'art'      => 'stein',
            'titel'    => 'Verknüpfung mit der Stein.APP gelöst',
            'alt_wert' => (string)$vehicle['stein_asset_id'],
        ], $user);
        flash('success', 'Verknüpfung gelöst.');
        break;

    default:
        flash('error', 'Unbekannte Aktion.');
}

redirect($zurueck);
