<?php
declare(strict_types=1);

/** Aktionen im Getränkemodul: buchen, QR, abholen, Bestellung senden/liefern/abrechnen/löschen, Meldung erledigen */
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('getraenke');
}
if (!can('manage_getraenke')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('getraenke');
}
$zurueck = post_str('zurueck') !== '' && str_starts_with(post_str('zurueck'), url('getraenke')) ? post_str('zurueck') : url('getraenke');

switch (post_str('action')) {
    /* ---------- Lager ---------- */
    case 'buchen':
        $standort = standort_find(post_int('standort_id'));
        $getraenk = getraenk_find(post_int('getraenk_id'));
        $art = post_str('art');
        if ($standort === null || $getraenk === null || !isset(GETRAENKE_BEWEGUNG[$art])) {
            flash('error', 'Platz, Getränk und Art der Bewegung angeben.');
            break;
        }
        $neu = getraenke_buchen((int)$getraenk['id'], (int)$standort['id'], $art, post_int('anzahl'), $user, post_str('notiz'));
        flash('success', sprintf('%s %s: jetzt %d × %s an „%s".', GETRAENKE_BEWEGUNG[$art], (string)$getraenk['name'], $neu, (string)$getraenk['gebinde'], (string)$standort['name']));
        $zurueck = url('getraenke_lager', ['standort_id' => (int)$standort['id']]);
        break;

    case 'meldung_erledigt':
        getraenke_meldung_erledigen(post_int('meldung_id'));
        flash('success', 'Meldung als erledigt markiert.');
        break;

    /* ---------- QR-Code am Lagerplatz ---------- */
    case 'qr_neu':
    case 'qr_weg':
        $standort = standort_find(post_int('standort_id'));
        if ($standort === null) {
            flash('error', 'Diesen Platz gibt es nicht.');
            break;
        }
        $erzeugen = post_str('action') === 'qr_neu';
        $bisher = connector_find((int)($standort['getraenke_qr_connector_id'] ?? 0));
        $con = $erzeugen ? (connector_find(post_int('connector_id')) ?? connector_for('getraenke')) : null;
        if ($erzeugen && $con === null) {
            flash('error', 'Dafür muss erst ein Connector mit der Verwendung „Getränke" eingerichtet und gekoppelt sein.');
            break;
        }
        db_update('standorte', [
            'getraenke_qr_token'        => $erzeugen ? connector_token_neu() : '',
            'getraenke_qr_connector_id' => $con !== null ? (int)$con['id'] : null,
        ], 'id = ?', [(int)$standort['id']]);
        audit('getraenke.qr', 'standort', (int)$standort['id'], $erzeugen ? 'erzeugt' : 'zurückgezogen');
        $melden = [];
        foreach ([$bisher, $con] as $c) {
            if ($c !== null && connector_taugt($c, 'getraenke')) {
                $melden[(int)$c['id']] = $c;
            }
        }
        try {
            foreach ($melden as $c) {
                connector_push_getraenke($c, true);
            }
            flash('success', $erzeugen ? 'QR-Code erzeugt und beim Connector angemeldet.' : 'QR-Code zurückgezogen.');
        } catch (Throwable $ex) {
            flash('warn', 'Gespeichert, aber der Connector war nicht erreichbar: ' . e($ex->getMessage()));
        }
        $zurueck = url('getraenke_lager', ['standort_id' => (int)$standort['id']]) . '#qr';
        break;

    case 'abholen':
        try {
            $res = connector_getraenke_sync();
            flash('success', sprintf('%d Meldung(en) geholt, %d übernommen%s.', $res['geholt'], $res['uebernommen'],
                $res['fehler'] ? ', ' . $res['fehler'] . ' unbrauchbar' : ''));
        } catch (Throwable $ex) {
            flash('error', 'Der Connector war nicht erreichbar: ' . e($ex->getMessage()));
        }
        break;

    /* ---------- Bestellungen ---------- */
    case 'senden':
    case 'liefern':
    case 'abrechnen':
    case 'bestellung_loeschen':
        $b = getraenke_bestellung_find(post_int('id'));
        if ($b === null) {
            flash('error', 'Diese Bestellung gibt es nicht (mehr).');
            break;
        }
        $zurueck = url('getraenke_bestellung', ['id' => (int)$b['id']]);
        $aktion = post_str('action');
        if ($aktion === 'senden') {
            if (!mail_eingerichtet()) {
                flash('error', 'Der E-Mail-Versand ist nicht eingerichtet (Verwaltung → E-Mail).');
                break;
            }
            $r = getraenke_bestellung_senden($b, $user);
            flash($r['ok'] ? 'success' : 'error', e($r['text']));
        } elseif ($aktion === 'liefern') {
            $geliefert = [];
            foreach ((array)($_POST['geliefert'] ?? []) as $gid => $n) {
                $geliefert[(int)$gid] = (int)$n;
            }
            $r = getraenke_bestellung_liefern($b, $geliefert, post_int('standort_id'), $user);
            flash($r['ok'] ? 'success' : 'error', e($r['text']));
        } elseif ($aktion === 'abrechnen') {
            if (!can('manage_budget')) {
                flash('error', 'Ausgaben im Budget legt nur die Leitung an.');
                break;
            }
            $r = getraenke_bestellung_abrechnen($b, post_dec('betrag'), post_str('datum'), post_int('kategorie_id'),
                post_str('beleg_nr'), post_date('bezahlt_am'), $user);
            flash($r['ok'] ? 'success' : 'error', e($r['text']));
        } else {
            $f = getraenke_bestellung_delete($b);
            flash($f === null ? 'success' : 'error', $f === null ? 'Bestellung gelöscht.' : e($f));
            if ($f === null) {
                $zurueck = url('getraenke') . '#bestellungen';
            }
        }
        break;

    default:
        flash('error', 'Unbekannte Aktion.');
}

redirect($zurueck);
