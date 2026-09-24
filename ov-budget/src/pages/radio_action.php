<?php
declare(strict_types=1);

/** Karten buchen, QR-Codes erzeugen, Geräte und Gruppen löschen */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('radios');
}

$user = require_login();
if (!can('manage_radios')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('radios');
}

$radio = radio_find(post_int('radio_id'));
$gruppe = radio_group_find(post_int('group_id'));
$ziel = $radio ?? $gruppe;
if (!$ziel) {
    flash('error', 'Das gibt es nicht (mehr).');
    redirect_route('radios');
}
$istGruppe = $radio === null;
$tabelle = $istGruppe ? 'radio_groups' : 'radios';
$zurueck = $istGruppe
    ? url('radio_group', ['id' => (int)$ziel['id']])
    : url('radio', ['id' => (int)$ziel['id']]);

switch (post_str('action')) {

    /* ---------------- Karten ---------------- */

    case 'karte_buchen':
        $sim = sim_find(post_int('sim_id'));
        if (!$radio || !$sim) {
            flash('error', 'Diese Karte gibt es nicht (mehr).');
            break;
        }
        if ($sim['radio_id'] !== null && (int)$sim['radio_id'] !== (int)$radio['id']) {
            flash('error', 'Diese Karte steckt schon in einem anderen Gerät.');
            break;
        }
        radio_card_add($radio, $sim, $user, post_bool('zuordnung') === 1);
        flash('success', sprintf('%s in „%s" gebucht.', sim_bezeichnung($sim), (string)$radio['bezeichnung']));
        $zurueck .= '#karten';
        break;

    case 'karte_weg':
        $sim = sim_find(post_int('sim_id'));
        if ($radio && $sim && (int)($sim['radio_id'] ?? 0) === (int)$radio['id']) {
            radio_card_remove($sim, $user);
            flash('success', sprintf('%s liegt wieder frei.', sim_bezeichnung($sim)));
        }
        $zurueck .= '#karten';
        break;

    /* ---------------- QR-Code für den Lagerort ---------------- */

    case 'qr_neu':
    case 'qr_weg':
        $erzeugen = post_str('action') === 'qr_neu';
        $bisher = connector_find((int)($ziel['qr_connector_id'] ?? 0));
        $con = $erzeugen
            ? (connector_find(post_int('connector_id')) ?? connector_for('bestand'))
            : null;
        if ($erzeugen && $con === null) {
            flash('error', 'Dafür muss erst ein Connector für den Bestand eingerichtet und gekoppelt sein.');
            break;
        }
        db_update($tabelle, [
            'qr_token'        => $erzeugen ? connector_token_neu() : '',
            'qr_connector_id' => $con !== null ? (int)$con['id'] : null,
        ], 'id = ?', [(int)$ziel['id']]);
        audit('funk.qr', $istGruppe ? 'radio_group' : 'radio', (int)$ziel['id'],
            $erzeugen ? 'erzeugt' : 'zurückgezogen');

        // Beide betroffenen Connectoren auf Stand bringen
        $melden = [];
        foreach ([$bisher, $con] as $c) {
            if ($c !== null && connector_taugt($c, 'bestand')) {
                $melden[(int)$c['id']] = $c;
            }
        }
        try {
            foreach ($melden as $c) {
                connector_push_bestand($c, true);
            }
            flash('success', $erzeugen
                ? 'QR-Code erzeugt und beim Connector angemeldet.'
                : 'QR-Code zurückgezogen.');
        } catch (Throwable $ex) {
            flash('warn', 'Gespeichert, aber der Connector war nicht erreichbar: ' . e($ex->getMessage()));
        }
        $zurueck .= '#qr';
        break;

    case 'abholen':
        try {
            $res = connector_bestand_sync();
            flash('success', sprintf('%d Meldung(en) geholt, %d übernommen%s.',
                $res['geholt'], $res['uebernommen'],
                $res['fehler'] ? ', ' . $res['fehler'] . ' unbrauchbar' : ''));
        } catch (Throwable $ex) {
            flash('error', 'Der Connector war nicht erreichbar: ' . e($ex->getMessage()));
        }
        break;

    /* ---------------- Löschen ---------------- */

    case 'loeschen':
        if ($istGruppe) {
            radio_group_delete($ziel);
            flash('success', 'Gruppe gelöscht. Die Geräte bleiben bestehen.');
            redirect_route('radios');
        }
        radio_delete($ziel);
        flash('success', 'Funkgerät gelöscht. Karten daraus liegen wieder frei.');
        redirect_route('radios');
        break;
}

redirect($zurueck);
