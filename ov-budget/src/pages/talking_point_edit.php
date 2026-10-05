<?php
declare(strict_types=1);

$user = current_user();
series_materialize();
$id = get_int('id');
$tp = $id ? tp_find($id) : null;

if ($id && !$tp) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Thema gibt es nicht (mehr).']);
    return;
}
if ($tp && !tp_editable($tp)) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Dieses Thema kann von dir nicht mehr bearbeitet werden.']);
    return;
}
if (!$tp && !can('create_talking_point')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Themen dürfen derzeit nur von der Leitung eingebracht werden.']);
    return;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $tp) {
        db_exec('DELETE FROM talking_points WHERE id = ?', [$tp['id']]);
        audit('tp.geloescht', 'talking_point', (int)$tp['id'], $tp['titel']);
        flash('success', 'Thema gelöscht.');
        $tp['meeting_id']
            ? redirect_route('meeting', ['id' => $tp['meeting_id']])
            : redirect_route('talking_points');
    }

    [$newId, $errors] = tp_save_from_post($tp, $user);
    if ($newId) {
        flash('success', $tp ? 'Thema gespeichert.' : 'Thema eingebracht.');
        $gespeichert = tp_find($newId);
        if ($gespeichert && $gespeichert['meeting_id']) {
            redirect_route('meeting', ['id' => $gespeichert['meeting_id']]);
        }
        redirect_route('talking_points');
    }
    $tp = array_merge($tp ?? [], $_POST, ['id' => $tp['id'] ?? null]);
}

if (!$tp) {
    $tp = [
        'id' => null, 'meeting_id' => get_int('meeting_id'), 'titel' => '', 'beschreibung' => '',
        'fachgruppe_id' => $user['fachgruppe_id'] ?? null,
        'prioritaet_id' => list_default_id('todo_prioritaet'),
        'status_id' => list_default_id('tp_status'),
        'dauer_min' => null, 'verantwortlich' => '', 'ergebnis' => '',
    ];
}

// Bezüge: gespeichert, aus dem Formular oder aus der Adresse (Fahrzeugakte, Gerät, Termin)
$links = !empty($tp['id']) ? tp_links((int)$tp['id']) : ['termin' => [], 'event' => [], 'vehicle' => [], 'radio' => [], 'budget' => []];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $links = ['termin' => tp_link_ids($_POST['termine'] ?? []), 'event' => tp_link_ids($_POST['events'] ?? []), 'vehicle' => tp_link_ids($_POST['vehicles'] ?? []), 'radio' => tp_link_ids($_POST['radios'] ?? []), 'budget' => tp_link_ids($_POST['budgets'] ?? [])];
} elseif (empty($tp['id'])) {
    foreach (['termin' => 'termin_id', 'event' => 'event_id', 'vehicle' => 'vehicle_id', 'radio' => 'radio_id', 'budget' => 'budget_jahr'] as $typ => $param) {
        if (get_int($param)) {
            $links[$typ][] = (int)get_int($param);
        }
    }
}

render('talking_point_edit', [
    'title'    => $tp['id'] ? 'Thema bearbeiten' : 'Thema einbringen',
    'tp'       => $tp,
    'errors'   => $errors,
    'geplant'  => meeting_query(['zeit' => 'kommend', 'nur_geplant' => 1]),
    'links'    => $links,
    'auswahl'  => tp_links_auswahl($links, $user),
]);
