<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');

$id = get_int('id');
$expense = $id ? expense_find($id) : null;

// Beim Bearbeiten entscheidet der Datensatz, beim Anlegen die Adresszeile
$art = buchungsart($expense['art'] ?? get_str('art', 'ausgabe'));

if ($id && !$expense) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Buchung gibt es nicht (mehr).']);
    return;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $expense) {
        db_exec('DELETE FROM expenses WHERE id = ?', [$expense['id']]);
        audit($art . '.geloescht', 'expense', (int)$expense['id'], $expense['bezeichnung']);
        flash('success', 'Buchung gelöscht.');
        redirect_route('expenses', ['jahr' => $expense['jahr'], 'art' => $art]);
    }

    [$newId, $errors] = expense_save_from_post($expense, $user);
    if ($newId) {
        $wort = $art === 'einnahme' ? 'Einnahme' : 'Ausgabe';
        flash('success', $wort . ($expense ? ' gespeichert.' : ' erfasst.'));
        $ziel = post_int('jahr') ?: (int)date('Y');
        if (post_str('weiter') === '1') {
            flash('info', 'Nächste ' . $wort . ' eintragen.');
            redirect_route('expense_edit', ['jahr' => $ziel, 'art' => $art]);
        }
        redirect_route('expenses', ['jahr' => $ziel, 'art' => $art]);
    }
    $expense = array_merge($expense ?? [], $_POST, ['id' => $expense['id'] ?? null]);
}

if (!$expense) {
    $jahr = get_int('jahr') ?: setting_int('haushaltsjahr', (int)date('Y'));
    $expense = [
        'id' => null,
        'art' => $art,
        'jahr' => $jahr,
        // Bei einem vergangenen Haushaltsjahr nicht das heutige Datum vorschlagen
        'datum' => $jahr === (int)date('Y') ? date('Y-m-d') : $jahr . '-01-01',
        'bezeichnung' => '', 'beschreibung' => '',
        'kategorie_id' => null, 'fachgruppe_id' => null,
        'budget_id' => get_int('budget_id'), 'wish_id' => get_int('wish_id'),
        'event_id' => get_int('event_id'),
        'betrag_brutto' => '', 'betrag_netto' => '',
        'lieferant' => '', 'beleg_nr' => '', 'referenz' => '',
        'bezahlt_am' => null, 'notiz' => '',
        'status' => buchung_status(get_str('status', 'bezahlt')),
    ];
    // Vorbelegung aus der Adresse, etwa „Verpflegung als geplante Ausgabe" von der Veranstaltung
    if (get_str('bezeichnung') !== '') {
        $expense['bezeichnung'] = mb_substr(get_str('bezeichnung'), 0, 200);
    }
    if (get_str('betrag') !== '') {
        $expense['betrag_brutto'] = round((float)str_replace(',', '.', get_str('betrag')), 2);
    }
    if (get_str('datum') !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', get_str('datum'))) {
        $expense['datum'] = get_str('datum');
        $expense['jahr'] = (int)substr(get_str('datum'), 0, 4);
    }
}

$wort = $art === 'einnahme' ? 'Einnahme' : 'Ausgabe';

render('expense_edit', [
    'title'   => $wort . ($expense['id'] ? ' bearbeiten' : ' erfassen'),
    'art'     => $art,
    'expense' => $expense,
    'errors'  => $errors,
    'budgets' => db_all('SELECT id, jahr, name FROM budgets WHERE is_active = 1 ORDER BY jahr DESC, name'),
    'wishes'  => db_all('SELECT id, bezeichnung FROM wishes ORDER BY created_at DESC LIMIT 200'),
    'events'  => db_all('SELECT id, titel, beginn FROM events ORDER BY beginn DESC LIMIT 200'),
]);
