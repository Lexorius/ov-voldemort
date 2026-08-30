<?php
declare(strict_types=1);

$user = current_user();
$id = get_int('id');
$wish = $id ? wish_find($id) : null;

if ($id && !$wish) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Wunsch gibt es nicht (mehr).']);
    return;
}
if ($wish && !can('edit_wish', $wish)) {
    http_response_code(403);
    render('error', ['title' => 'Kein Zugriff', 'message' => 'Dieser Wunsch darf von dir nicht bearbeitet werden.']);
    return;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$wishId, $errors] = wish_save_from_post($wish, $user);

    if ($wishId) {
        $uploadErrors = wish_handle_uploads(
            $wishId,
            (int)$user['id'],
            post_str('anlage_typ', 'angebot'),
            post_dec('anlage_betrag') ?: null
        );
        foreach ($uploadErrors as $ue) {
            flash('warn', e($ue));
        }
        flash('success', $wish ? 'Wunsch gespeichert.' : 'Wunsch angelegt. Danke!');
        redirect_route('wish', ['id' => $wishId]);
    }

    // Eingaben für die erneute Anzeige übernehmen
    $wish = array_merge($wish ?? [], $_POST, ['id' => $wish['id'] ?? null]);
}

// Vorbelegung für neue Wünsche
if (!$wish) {
    $wish = [
        'id' => null,
        'bezeichnung' => '', 'beschreibung' => '', 'begruendung' => '',
        'anzahl' => 1, 'einheit_id' => list_default_id('einheit'),
        'netto_einzel' => '', 'netto_gesamt' => '',
        'mwst_satz' => setting_float('mwst_satz', 19.0),
        'fachgruppe_id' => $user['fachgruppe_id'],
        'kategorie_id' => null,
        'dringlichkeit_id' => list_default_id('dringlichkeit'),
        'status_id' => list_default_id('wunsch_status'),
        'budget_id' => null, 'nice_to_have' => 0, 'prioritaet' => 0,
        'benoetigt_bis' => null, 'lieferant' => '', 'artikelnummer' => '', 'link' => '',
        'antragsteller' => $user['display_name'], 'extra' => null, 'source' => 'manuell',
    ];
}

render('wish_edit', [
    'title'   => $wish['id'] ? 'Wunsch bearbeiten' : 'Neuer Wunsch',
    'wish'    => $wish,
    'errors'  => $errors,
    'budgets' => db_all('SELECT id, jahr, name FROM budgets WHERE is_active = 1 ORDER BY jahr DESC, name'),
    'anlagen' => $wish['id'] ? wish_attachments((int)$wish['id']) : [],
]);
