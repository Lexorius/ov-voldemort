<?php
declare(strict_types=1);

/* Kartenausschnitt anlegen, ändern oder löschen */
$user = require_login();
if (!can('manage_karten')) {
    flash('error', 'Kartenausschnitte legt die Leitung an.');
    redirect_route('karten');
}

$id = get_int('id');
$karte = $id ? karte_find($id) : null;
if ($id && !$karte) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Karte gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $karte) {
        karte_delete($karte);
        flash('success', 'Karte „' . (string)$karte['name'] . '" gelöscht. Die Symbole in der Bibliothek bleiben.');
        redirect_route('karten');
    }
    [$neu, $errors] = karte_save_from_post($karte, $user);
    if ($neu) {
        flash('success', $karte ? 'Gespeichert.' : 'Karte angelegt – jetzt den Ausschnitt zurechtschieben und oben „Ausschnitt merken" drücken.');
        redirect_route('karte', ['id' => $neu]);
    }
    $karte = array_merge($karte ?? [], [
        'id' => $karte['id'] ?? null, 'name' => post_str('name'), 'beschreibung' => post_str('beschreibung'),
        'mittelpunkt' => post_str('mittelpunkt'), 'zoom' => post_int('zoom'), 'fahrzeuge' => post_bool('fahrzeuge'),
    ]);
}

$start = karten_start();
render('karte_edit', [
    'title'  => $karte && !empty($karte['id']) ? 'Karte bearbeiten' : 'Karte anlegen',
    'karte'  => $karte ?? ['id' => null, 'name' => '', 'beschreibung' => '', 'lat' => $start['lat'], 'lng' => $start['lng'], 'zoom' => $start['zoom'], 'fahrzeuge' => 0],
    'errors' => $errors,
    'marker' => $karte && !empty($karte['id']) ? (int)db_val('SELECT COUNT(*) FROM karten_marker WHERE karte_id = ?', [(int)$karte['id']], 0) : 0,
]);
