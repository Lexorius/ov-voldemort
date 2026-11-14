<?php
declare(strict_types=1);

/* Einsatz anlegen, ändern oder löschen */
$user = require_login();
if (!can('manage_einsaetze')) {
    flash('error', 'Einsätze erfasst die Leitung.');
    redirect_route('einsaetze');
}

$id = get_int('id');
$einsatz = $id ? einsatz_find($id) : null;
if ($id && !$einsatz) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Einsatz gibt es nicht (mehr).']);
    return;
}

$errors = [];
$tage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $einsatz) {
        if (!can('delete_einsaetze')) {
            flash('error', 'Löschen darf nur die Leitung.');
            redirect_route('einsatz', ['id' => (int)$einsatz['id']]);
        }
        einsatz_delete($einsatz);
        flash('success', 'Einsatz „' . einsatz_bezeichnung($einsatz) . '" gelöscht. Seine Buchungen stehen weiter im Budget, nur ohne Bezug.');
        redirect_route('einsaetze');
    }
    [$neu, $errors] = einsatz_save_from_post($einsatz, $user);
    if ($neu) {
        flash('success', $einsatz ? 'Einsatz gespeichert.' : 'Einsatz angelegt.');
        redirect_route('einsatz', ['id' => $neu]);
    }
    // Eingaben bei Fehlern erhalten – auch die Zeilen, die noch nicht stimmen
    $einsatz = array_merge($einsatz ?? [], [
        'id' => $einsatz['id'] ?? null, 'titel' => post_str('titel'), 'nummer' => post_str('nummer'),
        'art_id' => post_int('art_id'), 'status' => einsatz_status(post_str('status')), 'anforderer' => post_str('anforderer'),
        'ort' => post_str('ort'), 'fachgruppe_id' => post_int('fachgruppe_id'),
        'beschreibung' => post_str('beschreibung'), 'notiz' => post_str('notiz'),
    ]);
    $tage = [];
    foreach (array_values((array)($_POST['tag_datum'] ?? [])) as $i => $datum) {
        $tage[] = [
            'datum' => trim((string)$datum),
            'von' => trim((string)(array_values((array)($_POST['tag_von'] ?? []))[$i] ?? '')),
            'bis' => trim((string)(array_values((array)($_POST['tag_bis'] ?? []))[$i] ?? '')),
            'staerke_text' => trim((string)(array_values((array)($_POST['tag_staerke'] ?? []))[$i] ?? '')),
            'taetigkeit' => trim((string)(array_values((array)($_POST['tag_taetigkeit'] ?? []))[$i] ?? '')),
        ];
    }
}

$istNeu = empty($einsatz['id']);
if ($tage === null) {
    // Beim Anlegen steht der heutige Tag schon da
    $tage = $istNeu ? [['datum' => date('Y-m-d'), 'von' => '', 'bis' => '', 'taetigkeit' => '']] : einsatz_tage((int)$einsatz['id']);
}

$fahrzeuge = can('view_vehicles')
    ? db_all('SELECT v.id, v.bezeichnung, v.funkrufname, v.kennzeichen, t.slug AS typ_slug, t.label AS typ_label
              FROM vehicles v LEFT JOIN list_items t ON t.id = v.typ_id WHERE v.is_active = 1 ORDER BY v.bezeichnung')
    : [];
$fahrzeugeGewaehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fahrzeugeGewaehlt = wahl_ids('vehicles');
} elseif (!$istNeu) {
    $fahrzeugeGewaehlt = array_map('intval', array_column(einsatz_fahrzeuge((int)$einsatz['id']), 'id'));
}

render('einsatz_edit', [
    'title'   => $istNeu ? 'Einsatz anlegen' : 'Einsatz bearbeiten',
    'einsatz' => $einsatz ?? [
        'id' => null, 'titel' => '', 'nummer' => einsatz_nummer_vorschlag((int)date('Y')),
        'art_id' => list_default_id('einsatz_art'), 'status' => 'laufend', 'anforderer' => '', 'ort' => '',
        'fachgruppe_id' => null, 'beschreibung' => '', 'notiz' => '',
    ],
    'tage'    => $tage,
    'errors'  => $errors,
    'fahrzeuge' => $fahrzeuge,
    'fahrzeugeGewaehlt' => $fahrzeugeGewaehlt,
    'buchungen' => $istNeu ? 0 : (int)db_val('SELECT COUNT(*) FROM expenses WHERE einsatz_id = ?', [(int)$einsatz['id']], 0),
]);
