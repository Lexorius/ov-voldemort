<?php
declare(strict_types=1);

/* Mangel oder Aufgabe an der Unterkunft melden oder ändern */
$user = require_login();
if (!can('report_unterkunft') && !can('manage_unterkunft')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('unterkunft');
}

$id = get_int('id');
$punkt = $id ? unterkunft_punkt_find($id) : null;
if ($id && !$punkt) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Punkt gibt es nicht (mehr).']);
    return;
}
if ($punkt && !unterkunft_darf_aendern($punkt, $user)) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Diesen Punkt hat jemand anderes gemeldet oder er ist abgeschlossen – ändern kann ihn die Leitung.']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$neu, $errors] = unterkunft_punkt_save_from_post($punkt, $user);
    if ($neu) {
        flash('success', $punkt ? 'Gespeichert.' : 'Gemeldet – danke. Der Punkt steht jetzt in der Liste der Unterkunft.');
        redirect_route('unterkunft_punkt', ['id' => $neu]);
    }
    $punkt = array_merge($punkt ?? [], $_POST, ['id' => $punkt['id'] ?? null]);
}

$standorte = function_exists('standort_all') ? standort_all(true) : [];
render('unterkunft_punkt_edit', [
    'title'     => $punkt && !empty($punkt['id']) ? 'Punkt bearbeiten' : 'Mangel oder Aufgabe melden',
    'punkt'     => $punkt ?? [
        'id' => null, 'titel' => '', 'beschreibung' => '', 'art' => array_key_exists(get_str('art'), UNTERKUNFT_ARTEN) ? get_str('art') : 'mangel',
        'standort_id' => get_int('standort_id'), 'ort_text' => '', 'prioritaet_id' => list_default_id('todo_prioritaet'),
        'zustaendig_id' => null, 'faellig_am' => null, 'firma' => '',
    ],
    'standorte' => $standorte,
    'personen'  => can('manage_unterkunft') ? db_all('SELECT id, display_name FROM users WHERE is_active = 1 ORDER BY display_name') : [],
    'errors'    => $errors,
    'verwalten' => can('manage_unterkunft'),
]);
