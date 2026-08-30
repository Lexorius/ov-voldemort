<?php
declare(strict_types=1);

require_role('admin');

$key = get_str('key', 'fachgruppe');
if (!array_key_exists($key, LIST_KEYS)) {
    $key = 'fachgruppe';
}

// Sortierung per Formular speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'sort') {
    foreach ((array)post('sort', []) as $itemId => $order) {
        db_exec('UPDATE list_items SET sort_order = ? WHERE id = ? AND list_key = ?', [(int)$order, (int)$itemId, $key]);
    }
    flash('success', 'Reihenfolge gespeichert.');
    redirect_route('admin_lists', ['key' => $key]);
}

$items = db_all(
    'SELECT li.*,
            (SELECT COUNT(*) FROM wishes w WHERE w.status_id = li.id OR w.fachgruppe_id = li.id
                OR w.kategorie_id = li.id OR w.dringlichkeit_id = li.id OR w.einheit_id = li.id) AS wunsch_nutzung,
            (SELECT COUNT(*) FROM users u WHERE u.fachgruppe_id = li.id) AS user_nutzung
     FROM list_items li WHERE li.list_key = ? ORDER BY li.sort_order, li.label',
    [$key]
);

render('admin/lists', ['title' => 'Auswahllisten', 'key' => $key, 'items' => $items]);
