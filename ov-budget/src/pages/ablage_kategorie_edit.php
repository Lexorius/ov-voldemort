<?php
declare(strict_types=1);

/* Eigene Kategorie der Dateiablage anlegen, ändern oder löschen – die Kategorien der Module sind gesperrt */
$user = require_login();
if (!can('manage_ablage')) {
    flash('error', 'Kategorien legt die Leitung an.');
    redirect_route('ablage');
}

$id = get_int('id');
$kat = $id ? ablage_kategorie_find($id) : null;
if ($id && !$kat) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diese Kategorie gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $kat) {
        $grund = ablage_kategorie_delete($kat);
        if ($grund === null) {
            flash('success', 'Kategorie „' . (string)$kat['name'] . '" gelöscht.');
            redirect_route('ablage', !empty($kat['parent_id']) ? ['k' => 'k' . (int)$kat['parent_id']] : []);
        }
        flash('error', $grund);
        redirect_route('ablage_kategorie_edit', ['id' => (int)$kat['id']]);
    }
    [$neu, $errors] = ablage_kategorie_save_from_post($kat, $user);
    if ($neu) {
        flash('success', $kat ? 'Gespeichert.' : 'Kategorie angelegt.');
        redirect_route('ablage', ['k' => 'k' . $neu]);
    }
    $kat = array_merge($kat ?? [], [
        'id' => $kat['id'] ?? null, 'name' => post_str('name'), 'beschreibung' => post_str('beschreibung'),
        'parent_id' => post_int('parent_id'), 'nur_leitung' => post_bool('nur_leitung'),
    ]);
}

$rows = ablage_kategorien();
$index = ablage_kategorien_index($rows);
// „+ Unterkategorie" bringt die Eltern mit – aber nur eine eigene Kategorie
$eltern = ablage_schluessel(get_str('eltern'));
$elternId = $eltern['art'] === 'eigen' && isset($index[$eltern['id']]) ? $eltern['id'] : null;

render('ablage_kategorie_edit', [
    'title'      => $kat && !empty($kat['id']) ? 'Kategorie bearbeiten' : 'Kategorie anlegen',
    'kat'        => $kat ?? ['id' => null, 'name' => '', 'beschreibung' => '', 'parent_id' => $elternId, 'nur_leitung' => 0],
    'rows'       => $rows,
    // unter sich selbst oder den eigenen Unterkategorien kann eine Kategorie nicht hängen
    'ausser'     => $kat && !empty($kat['id']) ? array_merge([(int)$kat['id']], ablage_kategorie_nachkommen((int)$kat['id'], $rows)) : [],
    'geerbt'     => !empty($kat['parent_id']) && ablage_kategorie_nur_leitung((int)$kat['parent_id'], $index),
    'leer'       => $kat && !empty($kat['id'])
        && !ablage_kategorie_nachkommen((int)$kat['id'], $rows)
        && (int)($index[(int)$kat['id']]['anzahl'] ?? 0) === 0,
    'errors'     => $errors,
]);
