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
    if (in_array(post_str('action'), ['plan_setzen', 'plan_weg'], true) && $s) {
        $bild = post_str('action') === 'plan_setzen' ? standort_bild_find((int)post_int('bild_id', 0)) : null;
        if (post_str('action') === 'plan_setzen' && (!$bild || (int)$bild['standort_id'] !== (int)$s['id'])) {
            flash('error', 'Dieses Bild gehört nicht zu diesem Platz.');
        } else {
            standort_plan_setzen($s, $bild);
            flash('success', $bild ? 'Das Bild ist jetzt der Plan – unten die Unterplätze darauf verorten.' : 'Plan entfernt.');
        }
        redirect(url('admin_standort_edit', ['id' => $s['id']]) . '#plan');
    }
    if (in_array(post_str('action'), ['plan_position', 'plan_position_weg'], true) && $s) {
        $kind = standort_find((int)post_int('kind_id', 0));
        if (!$kind || (int)($kind['parent_id'] ?? 0) !== (int)$s['id']) {
            flash('error', 'Bitte einen Platz wählen, der direkt unter diesem liegt.');
        } elseif (post_str('action') === 'plan_position_weg') {
            standort_plan_position_setzen($kind, null, null);
            flash('success', sprintf('„%s" aus dem Plan genommen.', (string)$kind['name']));
        } else {
            $xy = standort_plan_koordinaten(post_str('x'), post_str('y'));
            if ($xy === null) {
                flash('error', 'Erst im Plan an die Stelle klicken, wo der Platz liegt.');
            } else {
                standort_plan_position_setzen($kind, $xy[0], $xy[1]);
                flash('success', sprintf('„%s" im Plan verortet.', (string)$kind['name']));
            }
        }
        redirect(url('admin_standort_edit', ['id' => $s['id']]) . '#plan');
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

// Fahrzeuge mit diesem Stellplatz als Kacheln: Zeilen wie in der Fahrzeugliste, mit Titelbild und Fristen
$fahrzeuge = $s['id'] ? vehicle_query(['standort_ids' => [(int)$s['id']], 'user_id' => (int)$user['id']]) : [];
$fzFristen = [];
foreach ($fahrzeuge as $v) {
    $fzFristen[(int)$v['id']] = vehicle_deadlines($v, setting_int('fahrzeug_frist_warnung_tage', 30));
}

render('admin/standort_edit', [
    'title'   => $s['id'] ? 'Platz bearbeiten' : 'Platz anlegen',
    's'       => $s,
    'parent'  => $parent,
    'alle'    => $alle,
    'errors'  => $errors,
    'pfad'    => $s['id'] ? standort_pfad((int)$s['id'], $alle) : ($parent ? standort_pfad((int)$parent['id'], $alle) : ''),
    'kinder'  => $s['id'] ? (int)db_val('SELECT COUNT(*) FROM standorte WHERE parent_id = ?', [(int)$s['id']], 0) : 0,
    'bilder'  => $s['id'] ? standort_bilder((int)$s['id']) : [],
    'planKinder' => $s['id'] ? array_values(array_filter($alle, static fn($x) => (int)($x['parent_id'] ?? 0) === (int)$s['id'])) : [],
    'fahrzeuge' => $fahrzeuge,
    'fzTitelbilder' => $fahrzeuge ? vfile_covers(array_column($fahrzeuge, 'id')) : [],
    'fzFristen' => $fzFristen,
    'zaehler'   => $s['id'] ? db_all('SELECT id, name, art, rolle, is_active FROM meters WHERE standort_id = ? ORDER BY name', [(int)$s['id']]) : [],
    'funkgruppen' => $s['id'] ? db_all('SELECT id, name, is_active FROM radio_groups WHERE standort_id = ? ORDER BY name', [(int)$s['id']]) : [],
    'funk'      => $s['id'] ? db_all('SELECT id, bezeichnung, funkrufname, is_active FROM radios WHERE standort_id = ? ORDER BY bezeichnung', [(int)$s['id']]) : [],
    'zustaendig' => $s['id'] ? standort_zustaendige_zaehler((int)$s['id'], $alle, db_all('SELECT id, name, art, rolle, is_active, bereich_id FROM meters WHERE bereich_id IS NOT NULL ORDER BY name')) : [],
]);
