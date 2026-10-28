<?php
declare(strict_types=1);

/** Aktionen am Newsletter: Mailtext speichern, planen, sofort, abbrechen, Probe, löschen, Sperrliste, Abmeldungen holen */
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('newsletter');
}
if (!can('manage_newsletter')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('newsletter');
}
$aktion = post_str('action');

/* ---------- Sperrliste und Abmeldungen – ohne Newsletter ---------- */
if ($aktion === 'sperren') {
    $email = trim(post_str('email'));
    if (!mail_adresse_gueltig($email)) {
        flash('error', 'Bitte eine gültige E-Mail-Adresse angeben.');
    } else {
        flash('success', newsletter_sperren($email, 'von Hand: ' . mb_substr(trim(post_str('grund')), 0, 150)) ? $email . ' gesperrt.' : $email . ' stand schon auf der Sperrliste.');
    }
    redirect_route('newsletter_sperre');
}
if ($aktion === 'entsperren') {
    newsletter_entsperren(post_int('sperre_id'));
    flash('success', 'Sperre aufgehoben – die Adresse bekommt wieder Newsletter.');
    redirect_route('newsletter_sperre');
}
if ($aktion === 'abmeldungen') {
    try {
        $r = connector_newsletter_sync();
        flash('success', sprintf('%d Abmeldung(en) geholt, %d neu gesperrt.', $r['geholt'], $r['gesperrt']));
    } catch (Throwable $ex) {
        flash('error', 'Der Connector war nicht erreichbar: ' . e($ex->getMessage()));
    }
    redirect_route('newsletter_sperre');
}

$n = newsletter_find(post_int('id'));
if ($n === null) {
    flash('error', 'Diesen Newsletter gibt es nicht (mehr).');
    redirect_route('newsletter');
}
$zurueck = url('newsletter_detail', ['id' => (int)$n['id']]);

switch ($aktion) {
    case 'text':
        if (!in_array((string)$n['status'], ['entwurf', 'geplant'], true)) {
            flash('error', 'Der Text lässt sich nur vor dem Versand ändern.');
            break;
        }
        db_update('newsletter', ['betreff' => mb_substr(trim(post_str('betreff')), 0, 200), 'text' => trim(str_replace("\r", '', post_str('text')))], 'id = ?', [(int)$n['id']]);
        audit('newsletter.text', 'newsletter', (int)$n['id'], 'bearbeitet');
        flash('success', 'Betreff und Text gespeichert.');
        $zurueck .= '#mail';
        break;

    case 'planen':
    case 'sofort':
        if (!mail_eingerichtet()) {
            flash('error', 'Der E-Mail-Versand ist nicht eingerichtet (Verwaltung → E-Mail).');
            break;
        }
        $r = newsletter_planen($n, $aktion === 'sofort' ? null : post_str('senden_am'), $user);
        flash($r['ok'] ? 'success' : 'error', e($r['text']));
        break;

    case 'abbrechen':
        newsletter_abbrechen($n);
        flash('success', 'Zurück in den Entwurf. Was schon verschickt war, bleibt verschickt.');
        break;

    case 'probe':
        $r = newsletter_probe($n, (string)($user['email'] ?? ''), $user);
        flash($r['ok'] ? 'success' : 'error', e($r['text']));
        break;

    case 'loeschen':
        $f = newsletter_delete($n);
        flash($f === null ? 'success' : 'error', $f === null ? 'Newsletter gelöscht.' : e($f));
        if ($f === null) {
            $zurueck = url('newsletter');
        }
        break;

    default:
        flash('error', 'Unbekannte Aktion.');
}

redirect($zurueck);
