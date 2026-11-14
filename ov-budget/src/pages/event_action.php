<?php
declare(strict_types=1);

/**
 * Alles, was an einer Veranstaltung geändert wird: Gäste, Dateien, Einladungen –
 * dazu der Einsatzauftrag als PDF (Antwort als JSON) und das Umwandeln in einen Einsatz.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_route('events');
}

$user = require_login();

// Ein im Browser gesetzter Einsatzauftrag: action=auftrag_pdf, event_id, daten, pdf[]
if (post_str('action') === 'auftrag_pdf') {
    header('Content-Type: application/json; charset=UTF-8');
    $antwort = static function (array $d, int $code = 200): never {
        http_response_code($code);
        echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    };
    if (!can('manage_events')) {
        $antwort(['ok' => false, 'fehler' => 'Einsatzaufträge an einer Veranstaltung ablegen darfst du nicht.'], 403);
    }
    $ziel = event_find(post_int('event_id', 0));
    if (!$ziel) {
        $antwort(['ok' => false, 'fehler' => 'Diese Veranstaltung gibt es nicht (mehr).'], 404);
    }
    $daten = einsatz_auftrag_daten(post_str('daten'));
    [$anzahl, $fehler, $ids] = event_auftrag_ablegen((int)$ziel['id'], 'pdf', $daten, $user);
    if ($anzahl < 1) {
        $antwort(['ok' => false, 'fehler' => $fehler ? implode(' ', $fehler) : 'Es kam kein PDF an.'], 422);
    }
    $antwort(['ok' => true, 'titel' => einsatz_auftrag_titel($daten), 'datei' => url('event_file', ['id' => $ids[0]]),
              'einsatz' => url('event', ['id' => (int)$ziel['id']]) . '#dateien']);
}
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

    case 'teilnehmer':
        $zahl = post_int('teilnehmer_ist');
        db_update('events', [
            'teilnehmer_ist' => $zahl !== null ? max(0, min(65000, $zahl)) : null,
            'updated_by'     => (int)$user['id'],
        ], 'id = ?', [(int)$event['id']]);
        audit('veranstaltung.teilnehmer', 'event', (int)$event['id'], (string)($zahl ?? ''));
        flash('success', $zahl !== null
            ? sprintf('%d Teilnehmer eingetragen.', $zahl)
            : 'Die Teilnehmerzahl ist wieder offen.');
        $zurueck .= '#teilnehmer';
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
            flash('error', $f);
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

    /* ---------------- Aus der Veranstaltung wird ein Einsatz ---------------- */

    case 'zu_einsatz':
        if (!can('manage_einsaetze')) {
            flash('error', 'Einsätze anlegen darfst du nicht.');
            break;
        }
        $schon = einsatz_zu_veranstaltung((int)$event['id']);
        if ($schon) {
            flash('info', 'Aus dieser Veranstaltung ist schon ein Einsatz entstanden.');
            redirect_route('einsatz', ['id' => (int)$schon['id']]);
        }
        $einsatzId = einsatz_aus_veranstaltung($event, $user, event_personen($event, event_stats(event_guests((int)$event['id']))));
        flash('success', 'Einsatz angelegt. Die Veranstaltung bleibt bestehen; ihre Buchungen hängen jetzt auch am Einsatz.');
        flash('info', 'Bitte Uhrzeiten, Stärke, Fachgruppen und Fahrzeuge je Tag prüfen und ergänzen.');
        redirect(url('einsatz_edit', ['id' => $einsatzId]) . '#tage');

    /* ---------------- Einladungen über den Connector ---------------- */

    case 'einladungen':
        $c = connector_find((int)($event['connector_id'] ?? 0));
        if ($c === null || !connector_taugt($c, 'veranstaltungen')) {
            flash('error', 'Für diese Veranstaltung ist kein gekoppelter Connector eingetragen.');
            break;
        }
        try {
            $res = connector_push_events($c, true);
            flash('success', sprintf('%d Veranstaltung(en) und %d Einladung(en) angemeldet.',
                $res['veranstaltungen'], $res['einladungen']));
        } catch (Throwable $ex) {
            flash('error', 'Der Connector war nicht erreichbar: ' . $ex->getMessage());
        }
        $zurueck .= '#einladungen';
        break;

    case 'abholen':
        $c = connector_find((int)($event['connector_id'] ?? 0));
        if ($c === null || !connector_taugt($c, 'veranstaltungen')) {
            flash('error', 'Für diese Veranstaltung ist kein gekoppelter Connector eingetragen.');
            break;
        }
        try {
            $res = connector_fetch_answers($c);
            flash('success', sprintf('%d Rückmeldung(en) geholt, %d übernommen%s.',
                $res['geholt'], $res['uebernommen'],
                $res['fehler'] ? ', ' . $res['fehler'] . ' unbrauchbar' : ''));
        } catch (Throwable $ex) {
            flash('error', 'Der Connector war nicht erreichbar: ' . $ex->getMessage());
        }
        $zurueck .= '#gaeste';
        break;

    /* ---------------- Veranstaltung ---------------- */

    case 'loeschen':
        event_delete($event);
        flash('success', 'Veranstaltung gelöscht. Erfasste Buchungen bleiben im Budget stehen.');
        redirect_route('events');
        break;
}

redirect($zurueck);
