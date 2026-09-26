<?php
declare(strict_types=1);

/** Alles, was an einem Zähler passiert: Stand eintragen, löschen, QR-Code, Home Assistant lesen */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('verbrauch');
}

$user = require_login();
if (!can('view_verbrauch')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('dashboard');
}

$meter = meter_find(post_int('meter_id', 0) ?? 0);
if (!$meter && post_str('action') !== 'abholen') {
    flash('error', 'Diesen Zähler gibt es nicht (mehr).');
    redirect_route('verbrauch');
}
$zurueck = $meter ? url('meter', ['id' => (int)$meter['id']]) : url('verbrauch');
$darf = can('manage_verbrauch');

switch (post_str('action')) {

    /* ---------- Stand von Hand ---------- */
    case 'ablesen':
        if (!can('read_meter')) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $datum = post_date('datum') ?: date('Y-m-d');
        $zeit = preg_match('/^\d{2}:\d{2}$/', post_str('zeit')) ? post_str('zeit') : date('H:i');
        [$id, $fehler] = reading_add($meter, post_dec('stand'), $datum . ' ' . $zeit . ':00', 'manuell',
            (string)($user['display_name'] ?: $user['username']), post_str('notiz'), $user, post_bool('ruecklauf') === 1);
        if ($fehler !== null) {
            flash('error', e($fehler));
        } else {
            flash('success', 'Stand eingetragen: ' . e(menge(post_dec('stand'), (string)$meter['einheit'], 3)) . '.');
        }
        if (post_str('zurueck') === 'uebersicht') {
            $zurueck = url('verbrauch');
        }
        break;

    case 'stand_weg':
        if (!$darf) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        flash(reading_delete($meter, post_int('reading_id', 0) ?? 0) ? 'success' : 'error',
            'Stand entfernt.');
        break;

    /* ---------- Home Assistant ---------- */
    case 'ha_lesen':
        if (!$darf) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $r = meter_ha_lesen($meter, $user);
        flash($r['ok'] ? 'success' : 'error', e($r['text']));
        break;

    /* ---------- QR-Code über den Connector ---------- */
    case 'qr_neu':
    case 'qr_weg':
        if (!$darf) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        $erzeugen = post_str('action') === 'qr_neu';
        $bisher = connector_find((int)($meter['qr_connector_id'] ?? 0));
        $con = $erzeugen ? (connector_find(post_int('connector_id')) ?? connector_for('verbrauch')) : null;
        if ($erzeugen && $con === null) {
            flash('error', 'Dafür muss erst ein Connector mit der Verwendung „Zähler" eingerichtet und gekoppelt sein.');
            break;
        }
        db_update('meters', [
            'qr_token'        => $erzeugen ? connector_token_neu() : '',
            'qr_connector_id' => $con !== null ? (int)$con['id'] : null,
        ], 'id = ?', [(int)$meter['id']]);
        audit('zaehler.qr', 'meter', (int)$meter['id'], $erzeugen ? 'erzeugt' : 'zurückgezogen');
        $melden = [];
        foreach ([$bisher, $con] as $c) {
            if ($c !== null && connector_taugt($c, 'verbrauch')) {
                $melden[(int)$c['id']] = $c;
            }
        }
        try {
            foreach ($melden as $c) {
                connector_push_zaehler($c, true);
            }
            flash('success', $erzeugen ? 'QR-Code erzeugt und beim Connector angemeldet.' : 'QR-Code zurückgezogen.');
        } catch (Throwable $ex) {
            flash('warn', 'Gespeichert, aber der Connector war nicht erreichbar: ' . e($ex->getMessage()));
        }
        $zurueck .= '#qr';
        break;

    case 'abholen':
        if (!$darf) {
            flash('error', 'Dafür fehlen dir die Rechte.');
            break;
        }
        try {
            $res = connector_zaehler_sync();
            flash('success', sprintf('%d Meldung(en) geholt, %d übernommen%s.',
                $res['geholt'], $res['uebernommen'], $res['fehler'] ? ', ' . $res['fehler'] . ' unbrauchbar' : ''));
        } catch (Throwable $ex) {
            flash('error', 'Der Connector war nicht erreichbar: ' . e($ex->getMessage()));
        }
        break;

    default:
        flash('error', 'Unbekannte Aktion.');
}

redirect($zurueck);
