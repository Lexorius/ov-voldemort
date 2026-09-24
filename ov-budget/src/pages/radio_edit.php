<?php
declare(strict_types=1);

$user = require_login();
if (!can('manage_radios')) {
    flash('error', 'Funkgeräte darf nur die Leitung pflegen.');
    redirect_route('radios');
}

$id = get_int('id');
$radio = radio_find($id);
if ($id && !$radio) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Funkgerät gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$neu, $errors] = radio_save_from_post($radio, $user);
    if ($neu) {
        flash('success', $radio ? 'Funkgerät gespeichert.' : 'Funkgerät angelegt.');
        redirect_route('radio', ['id' => $neu]);
    }
    // Eingaben bei Fehlern erhalten
    $radio = array_merge($radio ?? [], $_POST, ['id' => $radio['id'] ?? null]);
}

if (!$radio) {
    $radio = [
        'id' => null, 'bezeichnung' => '',
        'typ_id' => list_default_id('funk_typ'), 'status_id' => list_default_id('funk_status'),
        'hersteller' => '', 'modell' => '', 'seriennummer' => '', 'inventarnummer' => '',
        'funkrufname' => '',
        // Aus der Fahrzeugakte heraus ist das Fahrzeug schon gesetzt
        'ziel_typ' => get_str('ziel_typ') !== '' ? radio_ziel_typ(get_str('ziel_typ')) : 'ov',
        'ziel_id' => get_int('ziel_id'),
        'standort' => '', 'beschafft_am' => null, 'pruefung_bis' => null,
        'notiz' => '', 'is_active' => 1,
    ];
}

render('radio_edit', [
    'title'       => $radio['id'] ? 'Funkgerät bearbeiten' : 'Funkgerät anlegen',
    'radio'       => $radio,
    'errors'      => $errors,
    'fahrzeuge'   => db_all('SELECT id, bezeichnung, kennzeichen FROM vehicles
                             WHERE is_active = 1 ORDER BY bezeichnung'),
    'fachgruppen' => list_items('fachgruppe'),
    'personen'    => db_all('SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name'),
]);
