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
    if (post_str('action') === 'vorschlagen' && $contact) {
        if (kontakt_kategorie_vorschlagen_lassen((int)$contact['id'])) {
            audit('kontakt.kategorie_anfordern', 'contact', (int)$contact['id'], contact_name($contact));
            flash('success', 'Die KI schlägt in den nächsten Minuten eine Kategorie vor – aus der Auswahlliste, übernommen wird nichts von selbst.');
        } else {
            flash('warn', 'Die KI-Auswertung für Kontakte ist ausgeschaltet.');
        }
        redirect(url('contact_edit', ['id' => (int)$contact['id']]) . '#kategorie');
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

// THW-Dienststellen, zu denen der Kontakt gehört
$dienststellenGewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dienststellenGewaehlt = wahl_ids('dienststellen');
} elseif (!empty($contact['id']) && function_exists('kontakt_dienststellen')) {
    $dienststellenGewaehlt = array_map('intval', array_column(kontakt_dienststellen((int)$contact['id']), 'id'));
}

// KI-Vorschlag für die Kategorie und Stand des Auftrags
$vorschlag = null;
$kiAuftrag = null;
if (!empty($contact['id'])) {
    $vorschlag = kontakt_kategorie_vorschlag((int)$contact['id']);
    $kiAuftrag = ki_modul_aktiv('kontakt')
        ? db_row("SELECT status, fehler FROM ki_auftraege WHERE typ = 'kontakt' AND datei_id = ?", [(int)$contact['id']])
        : null;
}

render('contact_edit', [
    'vorschlag'  => $vorschlag,
    'kiAuftrag'  => $kiAuftrag,
    'kiKontakte' => ki_modul_aktiv('kontakt'),
    'dienststellenGewaehlt' => $dienststellenGewaehlt,
    'dienststellenOptionen' => function_exists('thw_optionen') ? thw_optionen(thw_all(), $dienststellenGewaehlt) : [],
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
