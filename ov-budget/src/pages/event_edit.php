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
    [$neu, $errors] = event_save_from_post($event, $user);
    if ($neu) {
        flash('success', $event ? 'Veranstaltung gespeichert.' : 'Veranstaltung angelegt.');
        redirect_route('event', ['id' => $neu]);
    }
    // Eingaben bei Fehlern erhalten
    $event = array_merge($event ?? [], $_POST);
}

$jahr = (int)($event['jahr'] ?? setting_int('haushaltsjahr', (int)date('Y')));

render('event_edit', [
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
