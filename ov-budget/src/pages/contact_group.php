<?php
declare(strict_types=1);

if (!can('view_contacts')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Kontakte sind nur für die Leitung sichtbar.']);
    return;
}

$user = current_user();
$id = get_int('id');
$gruppe = $id ? contact_group_find($id) : null;

$errors = [];

/* ---- Verteiler anlegen oder bearbeiten ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can('manage_contacts')) {
        flash('error', 'Dafür fehlen dir die Rechte.');
        redirect_route('contact_groups');
    }

    switch (post_str('action')) {
        case 'save':
            $name = post_str('name');
            if ($name === '') {
                $errors[] = 'Bitte einen Namen für den Verteiler angeben.';
                break;
            }
            $data = [
                'name'         => mb_substr($name, 0, 150),
                'beschreibung' => post_str('beschreibung'),
                'anlass_am'    => post_date('anlass_am'),
                'ort'          => mb_substr(post_str('ort'), 0, 150),
                'is_active'    => post_bool('is_active'),
            ];
            if ($gruppe) {
                db_update('contact_groups', $data, 'id = ?', [$gruppe['id']]);
                audit('verteiler.bearbeitet', 'contact_group', (int)$gruppe['id'], $name);
                flash('success', 'Verteiler gespeichert.');
                redirect_route('contact_group', ['id' => $gruppe['id']]);
            }
            $data['created_by'] = (int)$user['id'];
            $neu = db_insert('contact_groups', $data);
            audit('verteiler.angelegt', 'contact_group', $neu, $name);
            flash('success', 'Verteiler angelegt. Jetzt Kontakte hinzufügen.');
            redirect_route('contact_group', ['id' => $neu]);
            // no break

        case 'delete':
            if ($gruppe) {
                db_exec('DELETE FROM contact_groups WHERE id = ?', [$gruppe['id']]);
                audit('verteiler.geloescht', 'contact_group', (int)$gruppe['id'], $gruppe['name']);
                flash('success', 'Verteiler gelöscht. Die Kontakte selbst bleiben erhalten.');
            }
            redirect_route('contact_groups');
            // no break

        case 'add':
            if ($gruppe) {
                $anzahl = 0;
                foreach ((array)post('contact_ids', []) as $cid) {
                    if ((int)$cid > 0 && contact_group_add((int)$gruppe['id'], (int)$cid)) {
                        $anzahl++;
                    }
                }
                flash('success', sprintf('%d Kontakt(e) hinzugefügt.', $anzahl));
            }
            redirect_route('contact_group', ['id' => $id]);
            // no break

        case 'add_category':
            if ($gruppe && ($kat = post_int('kategorie_id'))) {
                $anzahl = 0;
                foreach (contact_query(['kategorie_id' => $kat, 'nicht_in_group' => (int)$gruppe['id']]) as $c) {
                    if (contact_group_add((int)$gruppe['id'], (int)$c['id'])) {
                        $anzahl++;
                    }
                }
                flash('success', sprintf('%d Kontakt(e) aus der Kategorie hinzugefügt.', $anzahl));
            }
            redirect_route('contact_group', ['id' => $id]);
            // no break

        case 'remove':
            if ($gruppe) {
                contact_group_remove((int)$gruppe['id'], post_int('contact_id', 0) ?? 0);
                flash('success', 'Kontakt aus dem Verteiler entfernt.');
            }
            redirect_route('contact_group', ['id' => $id]);
            // no break

        case 'status':
            if ($gruppe) {
                db_exec(
                    'UPDATE contact_group_members SET status_id = ?, personen = ?, notiz = ?
                     WHERE group_id = ? AND contact_id = ?',
                    [
                        post_int('status_id'),
                        max(0, post_int('personen', 1) ?? 1),
                        mb_substr(post_str('notiz'), 0, 255),
                        (int)$gruppe['id'],
                        post_int('contact_id', 0) ?? 0,
                    ]
                );
                flash('success', 'Rückmeldung vermerkt.');
            }
            redirect_route('contact_group', ['id' => $id]);
            // no break
    }
}

/* ---- Neuen Verteiler anlegen ---- */
if (!$gruppe) {
    if ($id) {
        http_response_code(404);
        render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Verteiler gibt es nicht (mehr).']);
        return;
    }
    render('contact_group_edit', [
        'title'  => 'Verteiler anlegen',
        'gruppe' => ['id' => null, 'name' => '', 'beschreibung' => '', 'anlass_am' => null,
                     'ort' => '', 'is_active' => 1],
        'errors' => $errors,
    ]);
    return;
}

$members = contact_group_members((int)$gruppe['id']);

render('contact_group', [
    'title'    => $gruppe['name'],
    'gruppe'   => $gruppe,
    'members'  => $members,
    'stats'    => contact_group_stats($members),
    'errors'   => $errors,
    'kandidaten' => can('manage_contacts')
        ? contact_query(['nicht_in_group' => (int)$gruppe['id'], 'q' => get_str('suche')])
        : [],
    'suche'    => get_str('suche'),
]);
