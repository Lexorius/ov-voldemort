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
