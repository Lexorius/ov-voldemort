<?php
declare(strict_types=1);

$user = require_login();
if (!can('manage_events')) {
    flash('error', 'Veranstaltungen darf nur die Leitung anlegen und ändern.');
    redirect_route('events');
}

$id = get_int('id');
$event = $id ? event_find($id) : null;
if ($id && !$event) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Veranstaltung gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Wer das Boot einplant, plant den Anhänger mit – vor dem Speichern ergänzen
    $traegerErgaenzt = [];
    if (can('view_vehicles') && post_str('fahrzeuge_feld') === '1' && function_exists('vehicle_mit_traegern')) {
        [$alleFahrzeuge, $traegerErgaenzt] = vehicle_mit_traegern(wahl_ids('vehicles'));
        $_POST['vehicles'] = array_map('strval', $alleFahrzeuge);
        unset($_POST['vehicles_neu']);
    }
    [$neu, $errors] = event_save_from_post($event, $user);
    if ($neu) {
        flash('success', $event ? 'Veranstaltung gespeichert.' : 'Veranstaltung angelegt.');
        foreach ($traegerErgaenzt as $te) {
            flash('info', e(sprintf('%s mit eingeplant – trägt %s.', $te['bezeichnung'], $te['wegen'])));
        }
        // Doppelbuchung? Dann warnen, aber speichern
        $gespeichert = event_find($neu);
        $gewaehlteFahrzeuge = can('view_vehicles') && post_str('fahrzeuge_feld') === '1' ? wahl_ids('vehicles') : [];
        if ($gespeichert && $gewaehlteFahrzeuge) {
            $konflikte = kalender_fahrzeug_konflikte($gewaehlteFahrzeuge, (string)$gespeichert['beginn'], (string)($gespeichert['ende'] ?: $gespeichert['beginn']), 0, $neu);
            if ($konflikte) {
                flash('warn', e(kalender_konflikt_text($konflikte, db_all('SELECT id, bezeichnung FROM vehicles WHERE is_active = 1'))));
            }
        }
        redirect_route('event', ['id' => $neu]);
    }
    // Eingaben bei Fehlern erhalten
    $event = array_merge($event ?? [], $_POST);
}

$jahr = (int)($event['jahr'] ?? haushaltsjahr());

$standorteGewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $standorteGewaehlt = wahl_ids('standorte');
} elseif (!empty($event['id'])) {
    $standorteGewaehlt = array_map('intval', array_column(standort_links('event_standorte', 'event_id', (int)$event['id']), 'id'));
}

$fahrzeuge = can('view_vehicles')
    ? db_all('SELECT v.id, v.bezeichnung, v.funkrufname, v.kennzeichen, v.sitzplaetze, v.zuladung_kg, t.slug AS typ_slug, t.label AS typ_label
              FROM vehicles v LEFT JOIN list_items t ON t.id = v.typ_id WHERE v.is_active = 1 ORDER BY v.bezeichnung')
    : [];
$fahrzeugeGewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fahrzeugeGewaehlt = wahl_ids('vehicles');
} elseif (!empty($event['id'])) {
    $fahrzeugeGewaehlt = array_map('intval', array_column(event_fahrzeuge((int)$event['id']), 'id'));
}
// Welche Fahrzeuge zur Zeit der Veranstaltung schon anderswo stecken (beim Anlegen erst nach dem Speichern bekannt)
$belegt = $fahrzeuge && !empty($event['beginn'])
    ? kalender_fahrzeug_konflikte(array_column($fahrzeuge, 'id'), (string)$event['beginn'], (string)(($event['ende'] ?? null) ?: $event['beginn']), 0, (int)($event['id'] ?? 0))
    : [];

render('event_edit', [
    'standorte' => function_exists('standort_all') ? standort_all() : [],
    'standorteGewaehlt' => $standorteGewaehlt,
    'fahrzeuge' => $fahrzeuge,
    'fahrzeugeGewaehlt' => $fahrzeugeGewaehlt,
    'belegt' => $belegt,
    'title'  => $event && !empty($event['id']) ? 'Veranstaltung bearbeiten' : 'Veranstaltung anlegen',
    'event'  => $event ?? [
        'id' => null, 'titel' => '', 'beschreibung' => '', 'ort' => '',
        'beginn' => null, 'ende' => null, 'status' => 'geplant',
        'typ_id' => list_default_id('veranstaltung_typ'),
        'gaesteliste' => event_typ_ohne_liste(list_default_id('veranstaltung_typ')) ? 0 : 1,
        'teilnehmer_geplant' => null, 'teilnehmer_ist' => null,
        'budget_id' => null, 'fachgruppe_id' => null, 'kosten_geplant' => 0,
        'connector_id' => (connector_for('veranstaltungen')['id'] ?? null),
        'code_laenge' => setting_int('veranstaltung_code_laenge', 6),
        'begleiter_max' => setting_int('veranstaltung_begleiter_max', 0),
        'kommentare_erlaubt' => setting_bool('veranstaltung_kommentare', true) ? 1 : 0,
        'vertretung_erlaubt' => 1, 'rueckmeldung_bis' => null, 'hinweis' => '', 'notiz' => '',
    ],
    'errors' => $errors,
    'budgets' => db_all('SELECT id, name, jahr FROM budgets WHERE is_active = 1 ORDER BY jahr DESC, name', []),
    'fachgruppen' => list_items('fachgruppe'),
    'connectoren' => connector_all(),
    'ohneListe'   => event_typen_ohne_liste((string)setting('veranstaltung_ohne_gaesteliste', '')),
    'jahr'    => $jahr,
]);
