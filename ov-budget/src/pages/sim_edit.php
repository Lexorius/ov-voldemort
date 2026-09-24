<?php
declare(strict_types=1);

$user = require_login();
if (!can('manage_sims')) {
    flash('error', 'SIM-Karten darf nur die Leitung pflegen.');
    redirect_route('sims');
}

$id = get_int('id');
$sim = sim_find($id);
if ($id && !$sim) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese SIM-Karte gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $sim) {
        sim_delete($sim);
        flash('success', 'SIM-Karte gelöscht.');
        redirect_route('sims');
    }
    [$neu, $errors] = sim_save_from_post($sim, $user);
    if ($neu) {
        flash('success', $sim ? 'SIM-Karte gespeichert.' : 'SIM-Karte angelegt.');
        redirect_route('sims');
    }
    // Eingaben bei Fehlern erhalten
    $sim = array_merge($sim ?? [], $_POST, ['id' => $sim['id'] ?? null]);
}

if (!$sim) {
    $sim = [
        'id' => null, 'rufnummer' => '', 'iccid' => '',
        'typ_id' => list_default_id('sim_typ'), 'status_id' => list_default_id('sim_status'),
        'anbieter' => '', 'tarif' => '', 'datenvolumen' => '', 'kosten_monat' => null,
        'vertrag_bis' => null, 'pin' => '', 'puk' => '', 'geraet' => '',
        // Aus der Fahrzeugakte heraus ist das Fahrzeug schon gesetzt
        'ziel_typ' => get_str('ziel_typ') !== '' ? sim_ziel_typ(get_str('ziel_typ')) : 'ov',
        'ziel_id' => get_int('ziel_id'),
        'ausgegeben_am' => null, 'notiz' => '', 'is_active' => 1,
    ];
}

render('sim_edit', [
    'title'       => $sim['id'] ? 'SIM-Karte bearbeiten' : 'SIM-Karte anlegen',
    'sim'         => $sim,
    'errors'      => $errors,
    'fahrzeuge'   => db_all('SELECT id, bezeichnung, kennzeichen FROM vehicles
                             WHERE is_active = 1 ORDER BY bezeichnung'),
    'fachgruppen' => list_items('fachgruppe'),
    'personen'    => db_all('SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name'),
]);
