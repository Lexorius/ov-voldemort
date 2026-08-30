<?php
declare(strict_types=1);

require_role('admin');

$id = get_int('id');
$item = $id ? db_row('SELECT * FROM list_items WHERE id = ?', [$id]) : null;
$key = $item ? $item['list_key'] : get_str('key', 'fachgruppe');

if (!array_key_exists($key, LIST_KEYS)) {
    $key = 'fachgruppe';
}
if ($id && !$item) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Listeneintrag gibt es nicht (mehr).']);
    return;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $item) {
        try {
            db_exec('DELETE FROM list_items WHERE id = ?', [$item['id']]);
            audit('liste.geloescht', 'list_item', (int)$item['id'], $key . ': ' . $item['label']);
            flash('success', 'Eintrag gelöscht.');
            redirect_route('admin_lists', ['key' => $key]);
        } catch (PDOException) {
            $errors[] = 'Der Eintrag wird noch verwendet und kann nicht gelöscht werden. '
                . 'Setze ihn stattdessen auf "inaktiv".';
        }
    }

    $label = post_str('label');
    if ($label === '') {
        $errors[] = 'Bitte eine Bezeichnung angeben.';
    }

    $slug = post_str('slug') !== '' ? slugify(post_str('slug')) : slugify($label);
    $dup = db_val('SELECT id FROM list_items WHERE list_key = ? AND slug = ? AND id <> ?',
        [$key, $slug, (int)($item['id'] ?? 0)]);
    if ($dup) {
        $errors[] = 'Dieser Schlüssel wird in der Liste bereits verwendet.';
    }

    if (!$errors) {
        $data = [
            'list_key'    => $key,
            'label'       => mb_substr($label, 0, 150),
            'slug'        => mb_substr($slug, 0, 150),
            'description' => mb_substr(post_str('description'), 0, 255),
            'color'       => preg_match('/^#[0-9a-fA-F]{6}$/', post_str('color')) ? post_str('color') : '#64748b',
            'weight'      => post_int('weight', 0),
            'sort_order'  => post_int('sort_order', 0),
            'is_active'   => post_bool('is_active'),
            'is_default'  => post_bool('is_default'),
            'is_final'    => post_bool('is_final'),
        ];

        if ($item) {
            db_update('list_items', $data, 'id = ?', [$item['id']]);
            $itemId = (int)$item['id'];
            audit('liste.bearbeitet', 'list_item', $itemId, $key . ': ' . $label);
        } else {
            $itemId = db_insert('list_items', $data);
            audit('liste.angelegt', 'list_item', $itemId, $key . ': ' . $label);
        }

        // Nur ein Vorgabewert je Liste
        if ($data['is_default']) {
            db_exec('UPDATE list_items SET is_default = 0 WHERE list_key = ? AND id <> ?', [$key, $itemId]);
        }

        flash('success', 'Eintrag gespeichert.');
        redirect_route('admin_lists', ['key' => $key]);
    }

    $item = array_merge($item ?? [], $_POST, ['id' => $item['id'] ?? null, 'list_key' => $key]);
}

if (!$item) {
    $maxSort = (int)db_val('SELECT COALESCE(MAX(sort_order),0) FROM list_items WHERE list_key = ?', [$key], 0);
    $item = [
        'id' => null, 'list_key' => $key, 'label' => '', 'slug' => '', 'description' => '',
        'color' => '#64748b', 'weight' => 0, 'sort_order' => $maxSort + 10,
        'is_active' => 1, 'is_default' => 0, 'is_final' => 0,
    ];
}

render('admin/list_edit', [
    'title'  => $item['id'] ? 'Listeneintrag bearbeiten' : 'Listeneintrag anlegen',
    'item'   => $item,
    'key'    => $key,
    'errors' => $errors,
]);
