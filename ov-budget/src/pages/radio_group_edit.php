<?php
declare(strict_types=1);

$user = require_login();
if (!can('manage_radios')) {
    flash('error', 'Funkgeräte darf nur die Leitung pflegen.');
    redirect_route('radios');
}

$id = get_int('id');
$gruppe = radio_group_find($id);
if ($id && !$gruppe) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Gruppe gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$neu, $errors] = radio_group_save_from_post($gruppe, $user);
    if ($neu) {
        flash('success', $gruppe ? 'Gruppe gespeichert.' : 'Gruppe angelegt.');
        redirect_route('radio_group', ['id' => $neu]);
    }
    // Eingaben bei Fehlern erhalten
    $gruppe = array_merge($gruppe ?? [], $_POST, ['id' => $gruppe['id'] ?? null]);
}

if (!$gruppe) {
    $gruppe = [
        'id' => null, 'name' => '', 'beschreibung' => '', 'lagerort' => '',
        'ziel_typ' => 'ov', 'ziel_id' => null, 'is_active' => 1,
    ];
}

render('radio_group_edit', [
    'title'       => $gruppe['id'] ? 'Gruppe bearbeiten' : 'Gruppe anlegen',
    'gruppe'      => $gruppe,
    'errors'      => $errors,
    'fahrzeuge'   => db_all('SELECT id, bezeichnung, kennzeichen FROM vehicles
                             WHERE is_active = 1 ORDER BY bezeichnung'),
    'fachgruppen' => list_items('fachgruppe'),
    'personen'    => db_all('SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name'),
]);
