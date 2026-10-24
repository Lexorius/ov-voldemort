<?php
declare(strict_types=1);

/** Abstimmung anlegen oder bearbeiten – mit dem Gestalter für die Seiten */
$user = require_login();
if (!can('manage_umfragen')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('umfragen');
}

$id = get_int('id');
$umfrage = $id ? umfrage_find($id) : null;
if ($id && !$umfrage) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Abstimmung gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$neu, $errors] = umfrage_save_from_post($umfrage);
    if ($neu) {
        flash('success', $umfrage ? 'Abstimmung gespeichert.' : 'Abstimmung angelegt. Zum Freigeben braucht sie einen Connector.');
        redirect_route('umfrage', ['id' => $neu]);
    }
    // Eingaben zurück ins Formular – auch die Seiten, so wie sie gestaltet wurden
    $umfrage = array_merge($umfrage ?? [], $_POST, [
        'id'        => $umfrage['id'] ?? null,
        'status'    => $umfrage['status'] ?? 'entwurf',
        'seiten'    => post_str('seiten_json'),
        'name_feld' => post_bool('name_feld') ? 1 : 0,
    ]);
}

if (!$umfrage) {
    $vorgabe = connector_for('umfragen');
    $umfrage = [
        'id' => null, 'titel' => '', 'beschreibung' => '', 'status' => 'entwurf',
        'connector_id' => $vorgabe !== null ? (int)$vorgabe['id'] : null,
        'beginn' => '', 'ende' => date('Y-m-d 20:00:00', strtotime('+7 days')),
        'name_feld' => 0, 'dank' => '',
        'seiten' => json_encode([['titel' => '', 'text' => '', 'felder' => [
            ['id' => 'f1', 'typ' => 'einfach', 'frage' => '', 'pflicht' => 1, 'optionen' => ['Ja', 'Nein', 'Enthaltung']],
        ]]], JSON_UNESCAPED_UNICODE),
    ];
}

// Alle Connectoren für Abstimmungen, dazu der eingetragene, falls er nicht mehr zuständig ist
$connectoren = connector_liste('umfragen');
$eingetragen = connector_find((int)($umfrage['connector_id'] ?? 0));
if ($eingetragen !== null && !in_array((int)$eingetragen['id'], array_map(static fn($c) => (int)$c['id'], $connectoren), true)) {
    $connectoren[] = $eingetragen;
}

$GLOBALS['ovb_umfrage_js'] = true;
render('umfrage_edit', [
    'title'       => $umfrage['id'] ? 'Abstimmung bearbeiten' : 'Abstimmung anlegen',
    'umfrage'     => $umfrage,
    'errors'      => $errors,
    'connectoren' => $connectoren,
    'antwortenAnzahl' => $umfrage['id'] ? (int)db_val('SELECT COUNT(*) FROM umfrage_antworten WHERE umfrage_id = ?', [(int)$umfrage['id']], 0) : 0,
]);
