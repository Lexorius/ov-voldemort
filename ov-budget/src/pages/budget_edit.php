<?php
declare(strict_types=1);

require_role('admin', 'leitung');

$id = get_int('id');
$budget = $id ? db_row('SELECT * FROM budgets WHERE id = ?', [$id]) : null;

if ($id && !$budget) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Budgettopf gibt es nicht (mehr).']);
    return;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $budget) {
        db_exec('DELETE FROM budgets WHERE id = ?', [$budget['id']]);
        audit('budget.geloescht', 'budget', (int)$budget['id'], $budget['name']);
        flash('success', 'Budgettopf gelöscht. Zugeordnete Wünsche bleiben erhalten.');
        redirect_route('budget', ['jahr' => $budget['jahr']]);
    }

    $name = post_str('name');
    $jahr = post_int('jahr', (int)date('Y'));
    if ($name === '') {
        $errors[] = 'Bitte einen Namen für den Topf angeben.';
    }
    if ($jahr < 2000 || $jahr > 2100) {
        $errors[] = 'Bitte ein gültiges Haushaltsjahr angeben.';
    }

    if (!$errors) {
        $data = [
            'jahr'          => $jahr,
            'name'          => mb_substr($name, 0, 150),
            'kategorie_id'  => post_int('kategorie_id'),
            'fachgruppe_id' => post_int('fachgruppe_id'),
            'betrag_netto'  => post_dec('betrag_netto'),
            'beschreibung'  => post_str('beschreibung'),
            'is_active'     => post_bool('is_active'),
        ];
        if ($budget) {
            db_update('budgets', $data, 'id = ?', [$budget['id']]);
            audit('budget.bearbeitet', 'budget', (int)$budget['id'], $name);
        } else {
            $newId = db_insert('budgets', $data);
            audit('budget.angelegt', 'budget', $newId, $name);
        }
        flash('success', 'Budgettopf gespeichert.');
        redirect_route('budget', ['jahr' => $jahr]);
    }

    $budget = array_merge($budget ?? [], $_POST, ['id' => $budget['id'] ?? null]);
}

if (!$budget) {
    $budget = [
        'id' => null,
        'jahr' => get_int('jahr') ?: setting_int('haushaltsjahr', (int)date('Y')),
        'name' => '', 'kategorie_id' => null, 'fachgruppe_id' => null,
        'betrag_netto' => '', 'beschreibung' => '', 'is_active' => 1,
    ];
}

render('budget_edit', [
    'title'  => $budget['id'] ? 'Budgettopf bearbeiten' : 'Budgettopf anlegen',
    'budget' => $budget,
    'errors' => $errors,
]);
