<?php
declare(strict_types=1);

/** Alles, was an einer Veranstaltung geändert wird: Gäste, Dateien, Einladungen */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('events');
}

$user = require_login();
$event = event_find(post_int('event_id', 0));
if (!$event) {
    flash('error', 'Diese Veranstaltung gibt es nicht (mehr).');
    redirect_route('events');
}
if (!can('manage_events')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('event', ['id' => (int)$event['id']]);
}

$zurueck = url('event', ['id' => (int)$event['id']]);

switch (post_str('action')) {

    /* ---------------- Gäste ---------------- */

    case 'gast_add':
        $kontakt = post_int('contact_id');
        $name = post_str('name');
        if ($kontakt === null && $name === '') {
            flash('error', 'Bitte einen Kontakt auswählen oder einen Namen eintragen.');
            break;
        }
        $neu = event_guest_add($event, $kontakt, $name);
        flash($neu === null ? 'warn' : 'success', $neu === null
            ? 'Diese Person steht schon auf der Gästeliste.'
            : 'Eingeladen. Der Einladungscode steht in der Liste.');
        $zurueck .= '#gaeste';
        break;

    case 'gast_gruppe':
        $gruppe = post_int('group_id');
        if (!$gruppe) {
            flash('error', 'Bitte einen Verteiler auswählen.');
            break;
        }
        $n = event_guest_add_group($event, $gruppe);
        flash('success', sprintf('%d Person(en) aus dem Verteiler eingeladen.', $n));
        $zurueck .= '#gaeste';
        break;

    case 'gast_weg':
        $gast = event_guest_find(post_int('guest_id'));
        if ($gast && (int)$gast['event_id'] === (int)$event['id']) {
            event_guest_remove($gast);
            flash('success', 'Von der Gästeliste genommen. Der Einladungslink gilt nicht mehr.');
        }
        $zurueck .= '#gaeste';
        break;

    case 'gast_code':
        $gast = event_guest_find(post_int('guest_id'));
        if ($gast && (int)$gast['event_id'] === (int)$event['id']) {
            event_guest_code_neu($event, $gast);
            flash('success', 'Neuer Einladungscode erzeugt. Der bisherige gilt nicht mehr.');
        }
        $zurueck .= '#gaeste';
        break;

    case 'gast_antwort':
        $gast = event_guest_find(post_int('guest_id'));
        if ($gast && (int)$gast['event_id'] === (int)$event['id']) {
            event_guest_antwort($event, $gast, [
                'status'     => post_str('status'),
                'begleiter'  => post_int('begleiter', 0) ?? 0,
                'vertretung' => post_str('vertretung'),
                'kommentar'  => post_str('kommentar', (string)($gast['kommentar'] ?? '')),
            ], 'mensch');
            flash('success', 'Rückmeldung eingetragen.');
        }
        $zurueck .= '#gaeste';
        break;

    /* ---------------- Dateien ---------------- */

    case 'datei':
        $betrag = post_dec('betrag');
        [$anzahl, $fehler] = efile_store_uploads((int)$event['id'], 'dateien', post_str('titel'),
            $betrag > 0 ? $betrag : null, $user);
        if ($anzahl > 0) {
            flash('success', sprintf('%d Datei(en) hinzugefügt.', $anzahl));
            audit('veranstaltung.datei', 'event', (int)$event['id'], sprintf('%d Datei(en)', $anzahl));
        }
        foreach ($fehler as $f) {
            flash('error', e($f));
        }
        $zurueck .= '#dateien';
        break;

    case 'datei_weg':
        $datei = efile_find(post_int('file_id'));
        if ($datei && (int)$datei['event_id'] === (int)$event['id']) {
            efile_delete($datei);
            audit('veranstaltung.datei_weg', 'event', (int)$event['id'], (string)$datei['orig_name']);
            flash('success', 'Datei entfernt.');
        }
        $zurueck .= '#dateien';
        break;

    /* ---------------- Veranstaltung ---------------- */

    case 'loeschen':
        event_delete($event);
        flash('success', 'Veranstaltung gelöscht. Erfasste Buchungen bleiben im Budget stehen.');
        redirect_route('events');
        break;
}

redirect($zurueck);
