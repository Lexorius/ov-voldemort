<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');

$id = get_int('id');
$contact = $id ? contact_find($id) : null;

if ($id && !$contact) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Kontakt gibt es nicht (mehr).']);
    return;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $contact) {
        db_exec('DELETE FROM contacts WHERE id = ?', [$contact['id']]);
        audit('kontakt.geloescht', 'contact', (int)$contact['id'], contact_name($contact));
        flash('success', 'Kontakt gelöscht.');
        redirect_route('contacts');
    }

    [$newId, $errors] = contact_save_from_post($contact, $user);
    if ($newId) {
        // Direkt auf einen Verteiler setzen, wenn er mitgegeben wurde
        $gruppe = post_int('add_to_group');
        if ($gruppe) {
            contact_group_add($gruppe, $newId);
        }
        flash('success', $contact ? 'Kontakt gespeichert.' : 'Kontakt angelegt.');
        if (post_str('weiter') === '1') {
            redirect_route('contact_edit');
        }
        redirect_route('contacts');
    }
    $contact = array_merge($contact ?? [], $_POST, ['id' => $contact['id'] ?? null]);
}

if (!$contact) {
    $contact = [
        'id' => null, 'anrede' => '', 'titel' => '', 'vorname' => '', 'nachname' => '',
        'organisation' => '', 'position' => '', 'kategorie_id' => null,
        'email' => '', 'telefon' => '', 'mobil' => '',
        'strasse' => '', 'plz' => '', 'ort' => '', 'land' => '',
        'anschreiben' => '', 'notiz' => '', 'is_active' => 1,
    ];
}

render('contact_edit', [
    'title'     => $contact['id'] ? 'Kontakt bearbeiten' : 'Kontakt anlegen',
    'contact'   => $contact,
    'errors'    => $errors,
    'verteiler' => contact_groups_all(true),
    'mitglied'  => $contact['id']
        ? db_all('SELECT g.id, g.name FROM contact_group_members m
                  JOIN contact_groups g ON g.id = m.group_id
                  WHERE m.contact_id = ? ORDER BY g.name', [$contact['id']])
        : [],
]);
