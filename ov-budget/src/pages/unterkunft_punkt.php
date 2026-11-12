<?php
declare(strict_types=1);

/* Ein offener Punkt der Unterkunft: Stand, Verlauf, Status setzen, Notiz schreiben */
$user = require_login();
if (!can('view_unterkunft')) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Die Unterkunft ist nur für die Leitung sichtbar.']);
    return;
}

$punkt = unterkunft_punkt_find(get_int('id'));
if (!$punkt) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Punkt gibt es nicht (mehr).']);
    return;
}
$id = (int)$punkt['id'];
$verwalten = can('manage_unterkunft');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {
        case 'status':
            if (!$verwalten) {
                flash('error', 'Den Stand setzt die Leitung.');
                break;
            }
            if (unterkunft_status_setzen($punkt, (int)post_int('status_id', 0), post_str('notiz'), $user)) {
                flash('success', 'Stand gespeichert.');
            } else {
                flash('info', 'Nichts geändert – anderen Status wählen oder eine Notiz schreiben.');
            }
            break;

        case 'notiz':
            if (!can('report_unterkunft') && !$verwalten) {
                flash('error', 'Dafür fehlen dir die Rechte.');
                break;
            }
            if (trim(post_str('notiz')) !== '') {
                unterkunft_log_add($id, $user, trim(post_str('notiz')));
                flash('success', 'Notiz im Verlauf vermerkt.');
            }
            break;

        case 'loeschen':
            if (!$verwalten) {
                flash('error', 'Löschen darf nur die Leitung.');
                break;
            }
            unterkunft_punkt_delete($punkt);
            flash('success', 'Punkt gelöscht.');
            redirect_route('unterkunft');
            // kein break
    }
    redirect_route('unterkunft_punkt', ['id' => $id]);
}

render('unterkunft_punkt', [
    'title'     => (string)$punkt['titel'],
    'punkt'     => $punkt,
    'standorte' => function_exists('standort_all') ? standort_all() : [],
    'verlauf'   => unterkunft_log($id),
    'verwalten' => $verwalten,
    'darfAendern' => unterkunft_darf_aendern($punkt, $user),
    'notizDarf' => $verwalten || can('report_unterkunft'),
]);
