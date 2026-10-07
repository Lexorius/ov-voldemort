<?php
declare(strict_types=1);

$user = require_role('admin', 'leitung');

$id = get_int('id');
$s = $id ? standort_find($id) : null;
if ($id && !$s) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Platz gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'delete' && $s) {
        $fehler = standort_delete($s);
        flash($fehler ? 'error' : 'success', $fehler ?? sprintf('„%s" gelöscht.', (string)$s['name']));
        redirect_route($fehler ? 'admin_standort_edit' : 'admin_standorte', $fehler ? ['id' => $s['id']] : []);
    }
    if (post_str('action') === 'position' && $s) {
        $lat = post_str('lat');
        $lng = post_str('lng');
        $fehler = is_numeric($lat) && is_numeric($lng)
            ? standort_position_setzen($s, (float)$lat, (float)$lng, is_numeric(post_str('genauigkeit')) ? (float)post_str('genauigkeit') : null, 'geraet', $user)
            : 'Es kam keine Position an.';
        flash($fehler ? 'error' : 'success', $fehler ?? 'Position vom Gerät übernommen.');
        redirect(url('admin_standort_edit', ['id' => $s['id']]) . '#lage');
    }
    if (post_str('action') === 'position_weg' && $s) {
        standort_position_setzen($s, null, null, null, 'mensch', $user);
        flash('success', 'Position entfernt.');
        redirect(url('admin_standort_edit', ['id' => $s['id']]) . '#lage');
    }
    if (post_str('action') === 'bild_upload' && $s) {
        [$n, $fehler] = standort_bilder_speichern((int)$s['id'], 'bilder', post_str('titel'), $user);
        foreach ($fehler as $f) {
            flash('warn', e($f));
        }
        if ($n > 0) {
            flash('success', sprintf('%d Bild(er) gespeichert.', $n));
        }
        redirect(url('admin_standort_edit', ['id' => $s['id']]) . '#bilder');
    }
    if (in_array(post_str('action'), ['bild_cover', 'bild_delete'], true) && $s) {
        $bild = standort_bild_find((int)post_int('bild_id', 0));
        if ($bild && (int)$bild['standort_id'] === (int)$s['id']) {
            if (post_str('action') === 'bild_cover') {
                standort_bild_cover_setzen((int)$s['id'], (int)$bild['id']);
                flash('success', 'Titelbild gesetzt.');
            } else {
                standort_bild_delete($bild);
                flash('success', 'Bild entfernt.');
            }
        }
        redirect(url('admin_standort_edit', ['id' => $s['id']]) . '#bilder');
    }
    if (post_str('action') === 'aktiv' && $s) {
        $neu = (int)$s['is_active'] === 1 ? 0 : 1;
        db_update('standorte', ['is_active' => $neu], 'id = ?', [(int)$s['id']]);
        audit('standort.bearbeitet', 'standort', (int)$s['id'], $neu ? 'wieder aktiv' : 'stillgelegt');
        flash('success', $neu ? 'Wieder aktiv.' : 'Stillgelegt – der Platz lässt sich nicht mehr auswählen.');
        redirect_route('admin_standort_edit', ['id' => $s['id']]);
    }
    [$ids, $errors] = standort_save_from_post($s, $user);
    if ($ids) {
        flash('success', count($ids) > 1 ? count($ids) . ' Plätze angelegt.' : ($s ? 'Platz gespeichert.' : 'Platz angelegt.'));
        redirect_route('admin_standorte');
    }
    $s = array_merge($s ?? [], $_POST, ['id' => $s['id'] ?? null]);
}

$alle = standort_all();
if (!$s) {
    $parent = standort_find((int)get_int('parent_id', 0));
    $kindtypen = standort_kindtypen($parent ? (string)$parent['typ'] : null);
    $s = ['id' => null, 'name' => '', 'typ' => $kindtypen[0] ?? 'gebaeude', 'parent_id' => $parent['id'] ?? null, 'kurz' => '', 'notiz' => '', 'is_active' => 1];
}
$parent = standort_find((int)($s['parent_id'] ?? 0));

render('admin/standort_edit', [
    'title'   => $s['id'] ? 'Platz bearbeiten' : 'Platz anlegen',
    's'       => $s,
    'parent'  => $parent,
    'alle'    => $alle,
    'errors'  => $errors,
    'pfad'    => $s['id'] ? standort_pfad((int)$s['id'], $alle) : ($parent ? standort_pfad((int)$parent['id'], $alle) : ''),
    'kinder'  => $s['id'] ? (int)db_val('SELECT COUNT(*) FROM standorte WHERE parent_id = ?', [(int)$s['id']], 0) : 0,
    'bilder'  => $s['id'] ? standort_bilder((int)$s['id']) : [],
]);
