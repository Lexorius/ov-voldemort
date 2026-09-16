<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');

$id = get_int('id');
$serie = $id ? series_find($id) : null;

if ($id && !$serie) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Serie gibt es nicht (mehr).']);
    return;
}

$errors = [];
$vorschau = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action', 'save');

    if ($action === 'delete' && $serie) {
        // Unberührte künftige Termine gehen mit, alles mit Inhalt bleibt als Einzeltermin
        $bilanz = series_prune_future((int)$serie['id']);
        db_exec('DELETE FROM meeting_series WHERE id = ?', [$serie['id']]);
        audit('serie.geloescht', 'meeting_series', (int)$serie['id'], $serie['titel']);
        flash('success', sprintf(
            'Serie gelöscht, %d leere künftige Termine entfernt.%s',
            $bilanz['geloescht'],
            $bilanz['behalten'] > 0
                ? sprintf(' %d Termine mit Themen oder Notizen bleiben als einzelne Besprechungen erhalten.', $bilanz['behalten'])
                : ''
        ));
        redirect_route('meetings');
    }

    if ($action === 'preview') {
        // Nur anzeigen, nicht speichern – die Eingaben bleiben im Formular
        [$errors, $daten] = series_from_post();
        $serie = array_merge($serie ?? [], $daten, ['id' => $serie['id'] ?? null]);
        if (!$errors) {
            $vorschau = series_occurrences($daten, max(date('Y-m-d'), (string)$daten['start_datum']), date('Y-m-d', strtotime('+1 year')));
        }
    } else {
        [$newId, $errors, $daten] = series_save_from_post($serie, $user);
        if ($newId) {
            $bilanz = $serie ? series_prune_future($newId) : ['geloescht' => 0, 'behalten' => 0];
            $neu = series_materialize($newId);

            $text = $serie ? 'Serie gespeichert.' : 'Serie angelegt.';
            $text .= sprintf(' %d Termine für die nächsten %d Tage angelegt.', $neu, series_horizon_days());
            if ($serie && $bilanz['behalten'] > 0) {
                $text .= sprintf(' %d künftige Termine mit Themen, Notizen oder eigenen Änderungen bleiben unverändert.',
                    $bilanz['behalten']);
            }
            flash('success', $text);
            redirect_route('meeting_series_edit', ['id' => $newId]);
        }
        $serie = array_merge($serie ?? [], $daten, ['id' => $serie['id'] ?? null]);
    }
}

if (!$serie) {
    $typ = get_int('typ_id') ?: list_default_id('besprechung_typ');
    $serie = [
        'id' => null, 'titel' => $typ ? list_label($typ, '') : '', 'typ_id' => $typ,
        'regel' => 'woche', 'intervall' => 2, 'wochentag' => 1, 'nte' => 2, 'monatstag' => 1,
        'beginn' => '19:30:00', 'ende' => null, 'ort' => '', 'leitung' => '', 'teilnehmer' => '',
        'beschreibung' => '', 'start_datum' => date('Y-m-d'), 'end_datum' => null, 'is_active' => 1,
    ];
}

// Bei bestehenden Serien die kommenden Termine zeigen
$termine = [];
if (!empty($serie['id']) && $vorschau === null) {
    $termine = db_all(
        "SELECT m.id, m.datum, m.serien_datum, m.status, m.beginn,
                (SELECT COUNT(*) FROM talking_points tp WHERE tp.meeting_id = m.id) AS punkte
         FROM meetings m WHERE m.series_id = ? AND m.datum >= CURDATE() ORDER BY m.datum",
        [$serie['id']]
    );
}

render('meeting_series_edit', [
    'title'    => $serie['id'] ? 'Serie bearbeiten' : 'Wiederkehrende Besprechung',
    'serie'    => $serie,
    'errors'   => $errors,
    'vorschau' => $vorschau,
    'termine'  => $termine,
]);
