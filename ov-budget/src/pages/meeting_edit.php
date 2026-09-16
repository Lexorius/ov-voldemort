<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');

$id = get_int('id');
$meeting = $id ? meeting_find($id) : null;

if ($id && !$meeting) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Besprechung gibt es nicht (mehr).']);
    return;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $meeting && !empty($meeting['series_id'])) {
        flash('warn', 'Termine einer Serie werden abgesagt statt gelöscht – sonst legt die Serie sie erneut an.');
        redirect_route('meeting', ['id' => $meeting['id']]);
    }
    if (post_str('action') === 'delete' && $meeting) {
        // Punkte wandern per ON DELETE SET NULL zurück in den Themenspeicher
        db_exec('DELETE FROM meetings WHERE id = ?', [$meeting['id']]);
        audit('besprechung.geloescht', 'meeting', (int)$meeting['id'], $meeting['titel']);
        flash('success', 'Besprechung gelöscht. Noch offene und vertagte Themen liegen wieder im Themenspeicher.');
        redirect_route('meetings');
    }

    [$newId, $errors] = meeting_save_from_post($meeting, $user);
    if ($newId) {
        flash('success', $meeting ? 'Besprechung gespeichert.' : 'Besprechung angelegt. Jetzt Themen draufsetzen.');
        redirect_route('meeting', ['id' => $newId]);
    }
    $meeting = array_merge($meeting ?? [], $_POST, ['id' => $meeting['id'] ?? null]);
}

if (!$meeting) {
    // Vorbelegung aus einer Vorlage: gleiche Art, Ort und Uhrzeit wie die letzte Besprechung dieser Art
    $typ = get_int('typ_id') ?: list_default_id('besprechung_typ');
    $letzte = $typ ? db_row('SELECT * FROM meetings WHERE typ_id = ? ORDER BY datum DESC LIMIT 1', [$typ]) : null;
    $meeting = [
        'id' => null,
        'titel' => $typ ? list_label($typ, '') : '',
        'typ_id' => $typ,
        'datum' => date('Y-m-d', strtotime('+7 days')),
        'beginn' => $letzte['beginn'] ?? '19:30:00',
        'ende' => $letzte['ende'] ?? null,
        'ort' => $letzte['ort'] ?? '',
        'leitung' => $letzte['leitung'] ?? '',
        'protokoll_von' => '',
        'teilnehmer' => '',
        'beschreibung' => '',
    ];
}

render('meeting_edit', [
    'title'   => $meeting['id'] ? 'Besprechung bearbeiten' : 'Besprechung anlegen',
    'meeting' => $meeting,
    'errors'  => $errors,
]);
