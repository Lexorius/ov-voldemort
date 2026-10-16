<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');

$id = get_int('id');
$meeting = $id ? meeting_find($id) : null;

if ($id && !$meeting) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Besprechung gibt es nicht (mehr).']);
    return;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $meeting && !empty($meeting['series_id'])) {
        flash('warn', 'Termine einer Serie werden abgesagt statt gelöscht – sonst legt die Serie sie erneut an.');
        redirect_route('meeting', ['id' => $meeting['id']]);
    }
    if (post_str('action') === 'delete' && $meeting) {
        // Punkte wandern per ON DELETE SET NULL zurück in den Themenspeicher
        db_exec('DELETE FROM meetings WHERE id = ?', [$meeting['id']]);
        audit('besprechung.geloescht', 'meeting', (int)$meeting['id'], $meeting['titel']);
        flash('success', 'Besprechung gelöscht. Noch offene und vertagte Themen liegen wieder im Themenspeicher.');
        redirect_route('meetings');
    }

    // Wer das Boot einplant, plant den Anhänger mit – vor dem Speichern ergänzen
    $traegerErgaenzt = [];
    if (can('view_vehicles') && post_str('fahrzeuge_feld') === '1' && function_exists('vehicle_mit_traegern')) {
        [$alleFahrzeuge, $traegerErgaenzt] = vehicle_mit_traegern(wahl_ids('vehicles'));
        $_POST['vehicles'] = array_map('strval', $alleFahrzeuge);
        unset($_POST['vehicles_neu']);
    }
    [$newId, $errors] = meeting_save_from_post($meeting, $user);
    if ($newId) {
        flash('success', $meeting ? 'Besprechung gespeichert.' : 'Besprechung angelegt. Jetzt Themen draufsetzen.');
        foreach ($traegerErgaenzt as $te) {
            flash('info', e(sprintf('%s mit eingeplant – trägt %s.', $te['bezeichnung'], $te['wegen'])));
        }
        // Doppelbuchung? Dann warnen, aber speichern
        $gespeichert = meeting_find($newId);
        $gewaehlteFahrzeuge = can('view_vehicles') && post_str('fahrzeuge_feld') === '1' ? wahl_ids('vehicles') : [];
        if ($gespeichert && $gewaehlteFahrzeuge) {
            $z = kalender_besprechung_zeitraum($gespeichert);
            $konflikte = kalender_fahrzeug_konflikte($gewaehlteFahrzeuge, $z['beginn'], $z['bis'], 0, 0, $newId);
            if ($konflikte) {
                flash('warn', e(kalender_konflikt_text($konflikte, db_all('SELECT id, bezeichnung FROM vehicles WHERE is_active = 1'))));
            }
        }
        redirect_route('meeting', ['id' => $newId]);
    }
    $meeting = array_merge($meeting ?? [], $_POST, ['id' => $meeting['id'] ?? null]);
}

if (!$meeting) {
    // Vorbelegung aus einer Vorlage: gleiche Art, Ort und Uhrzeit wie die letzte Besprechung dieser Art
    $typ = get_int('typ_id') ?: list_default_id('besprechung_typ');
    $letzte = $typ ? db_row('SELECT * FROM meetings WHERE typ_id = ? ORDER BY datum DESC LIMIT 1', [$typ]) : null;
    $meeting = [
        'id' => null,
        'titel' => $typ ? list_label($typ, '') : '',
        'typ_id' => $typ,
        'datum' => date('Y-m-d', strtotime('+7 days')),
        'beginn' => $letzte['beginn'] ?? '19:30:00',
        'ende' => $letzte['ende'] ?? null,
        'ende_datum' => null,
        'ort' => $letzte['ort'] ?? '',
        'leitung' => $letzte['leitung'] ?? '',
        'protokoll_von' => '',
        'teilnehmer' => '',
        'beschreibung' => '',
    ];
}

$fahrzeuge = can('view_vehicles')
    ? db_all('SELECT v.id, v.bezeichnung, v.funkrufname, v.kennzeichen, v.sitzplaetze, v.zuladung_kg, t.slug AS typ_slug, t.label AS typ_label
              FROM vehicles v LEFT JOIN list_items t ON t.id = v.typ_id WHERE v.is_active = 1 ORDER BY v.bezeichnung')
    : [];
$fahrzeugeGewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fahrzeugeGewaehlt = wahl_ids('vehicles');
} elseif (!empty($meeting['id'])) {
    $fahrzeugeGewaehlt = array_map('intval', array_column(meeting_fahrzeuge((int)$meeting['id']), 'id'));
}
// Welche Fahrzeuge zur Zeit der Besprechung schon anderswo stecken
$belegt = [];
if ($fahrzeuge && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($meeting['datum'] ?? ''))) {
    $z = kalender_besprechung_zeitraum($meeting);
    $belegt = kalender_fahrzeug_konflikte(array_column($fahrzeuge, 'id'), $z['beginn'], $z['bis'], 0, 0, (int)($meeting['id'] ?? 0));
}

// Externe Teilnehmer aus den Kontakten – landen auf der Anwesenheitsliste
$mitKontakten = can('view_contacts') && function_exists('contact_query');
$kontakteGewaehlt = [];
$kontaktOptionen = [];
if ($mitKontakten) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $kontakteGewaehlt = wahl_ids('kontakte');
    } elseif (!empty($meeting['id'])) {
        $kontakteGewaehlt = attendance_contact_ids((int)$meeting['id']);
    }
    $liste = contact_query(['limit' => 1000]);
    $da = array_map('intval', array_column($liste, 'id'));
    foreach ($kontakteGewaehlt as $cid) {
        if (!in_array($cid, $da, true) && ($c = contact_find($cid))) {
            $liste[] = $c;
        }
    }
    foreach ($liste as $c) {
        $sub = implode(' · ', array_filter([trim((string)($c['organisation'] ?? '')) !== '' && trim(($c['vorname'] ?? '') . ($c['nachname'] ?? '')) !== '' ? (string)$c['organisation'] : '', (string)($c['position'] ?? '')]));
        $kontaktOptionen[] = ['id' => (int)$c['id'], 'text' => contact_name($c), 'sub' => $sub, 'gruppe' => ''];
    }
}

render('meeting_edit', [
    'mitKontakten' => $mitKontakten,
    'kontaktOptionen' => $kontaktOptionen,
    'kontakteGewaehlt' => $kontakteGewaehlt,
    'title'   => $meeting['id'] ? 'Besprechung bearbeiten' : 'Besprechung anlegen',
    'meeting' => $meeting,
    'errors'  => $errors,
    'fahrzeuge' => $fahrzeuge,
    'fahrzeugeGewaehlt' => $fahrzeugeGewaehlt,
    'belegt' => $belegt,
]);
