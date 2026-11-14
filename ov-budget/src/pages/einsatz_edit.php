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
    $spalte = static fn(string $name): array => array_values((array)($_POST[$name] ?? []));
    $kennung = $spalte('tag_key');
    $tage = [];
    foreach ($spalte('tag_datum') as $i => $datum) {
        $key = (string)($kennung[$i] ?? '');
        $tage[] = [
            'datum' => trim((string)$datum),
            'von' => trim((string)($spalte('tag_von')[$i] ?? '')),
            'bis' => trim((string)($spalte('tag_bis')[$i] ?? '')),
            'fachgruppe_id' => (int)($spalte('tag_fachgruppe')[$i] ?? 0),
            'staerke_text' => trim((string)($spalte('tag_staerke')[$i] ?? '')),
            'taetigkeit' => trim((string)($spalte('tag_taetigkeit')[$i] ?? '')),
            'vehicles' => array_map('intval', (array)($_POST['tag_fz'][$key] ?? [])),
            'km' => array_filter(array_map(static fn($v) => trim((string)$v), (array)($_POST['tag_km'][$key] ?? [])), 'strlen'),
            'bh' => array_filter(array_map(static fn($v) => trim((string)$v), (array)($_POST['tag_bh'][$key] ?? [])), 'strlen'),
        ];
    }
}

$istNeu = empty($einsatz['id']);
if ($tage === null) {
    // Beim Anlegen steht der heutige Tag schon da
    $tage = $istNeu ? [['datum' => date('Y-m-d'), 'von' => '', 'bis' => '', 'taetigkeit' => '']] : einsatz_tage((int)$einsatz['id']);
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
    // Fahrzeuge je Zeile wählbar – auch ausgemusterte, wenn sie in diesem Einsatz schon stehen
    'fahrzeuge' => can('view_vehicles')
        ? db_all('SELECT v.id, v.bezeichnung, v.funkrufname, v.kennzeichen FROM vehicles v
                  WHERE v.is_active = 1 OR v.id IN (SELECT ev.vehicle_id FROM einsatz_vehicles ev WHERE ev.einsatz_id = ?)
                  ORDER BY v.bezeichnung', [(int)($einsatz['id'] ?? 0)])
        : [],
    'buchungen' => $istNeu ? 0 : (int)db_val('SELECT COUNT(*) FROM expenses WHERE einsatz_id = ?', [(int)$einsatz['id']], 0),
]);
