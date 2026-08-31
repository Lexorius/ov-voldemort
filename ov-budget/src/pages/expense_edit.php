<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');

$id = get_int('id');
$expense = $id ? expense_find($id) : null;

if ($id && !$expense) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Ausgabe gibt es nicht (mehr).']);
    return;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $expense) {
        db_exec('DELETE FROM expenses WHERE id = ?', [$expense['id']]);
        audit('ausgabe.geloescht', 'expense', (int)$expense['id'], $expense['bezeichnung']);
        flash('success', 'Ausgabe gelöscht.');
        redirect_route('expenses', ['jahr' => $expense['jahr']]);
    }

    [$newId, $errors] = expense_save_from_post($expense, $user);
    if ($newId) {
        flash('success', $expense ? 'Ausgabe gespeichert.' : 'Ausgabe erfasst.');
        $ziel = post_int('jahr') ?: (int)date('Y');
        if (post_str('weiter') === '1') {
            flash('info', 'Nächste Ausgabe eintragen.');
            redirect_route('expense_edit', ['jahr' => $ziel]);
        }
        redirect_route('expenses', ['jahr' => $ziel]);
    }
    $expense = array_merge($expense ?? [], $_POST, ['id' => $expense['id'] ?? null]);
}

if (!$expense) {
    $jahr = get_int('jahr') ?: setting_int('haushaltsjahr', (int)date('Y'));
    $expense = [
        'id' => null,
        'jahr' => $jahr,
        // Bei einem vergangenen Haushaltsjahr nicht das heutige Datum vorschlagen
        'datum' => $jahr === (int)date('Y') ? date('Y-m-d') : $jahr . '-01-01',
        'bezeichnung' => '', 'beschreibung' => '',
        'kategorie_id' => null, 'fachgruppe_id' => null,
        'budget_id' => get_int('budget_id'), 'wish_id' => get_int('wish_id'),
        'betrag_brutto' => '', 'betrag_netto' => '',
        'mwst_satz' => setting_float('mwst_satz', 19.0),
        'lieferant' => '', 'beleg_nr' => '', 'bezahlt_am' => null, 'notiz' => '',
    ];
}

render('expense_edit', [
    'title'   => $expense['id'] ? 'Ausgabe bearbeiten' : 'Ausgabe erfassen',
    'expense' => $expense,
    'errors'  => $errors,
    'budgets' => db_all('SELECT id, jahr, name FROM budgets WHERE is_active = 1 ORDER BY jahr DESC, name'),
    'wishes'  => db_all('SELECT id, bezeichnung FROM wishes ORDER BY created_at DESC LIMIT 200'),
]);
