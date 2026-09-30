<?php
declare(strict_types=1);

$user = require_login();
if (!can('manage_events')) {
    flash('error', 'Dafür fehlen dir die Rechte.');
    redirect_route('verpflegung_saetze');
}

$id = get_int('id');
$satz = $id ? tagessatz_find($id) : null;
if ($id && !$satz) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Diesen Tagessatz gibt es nicht (mehr).']);
    return;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_str('action') === 'loeschen' && $satz) {
        tagessatz_delete($satz);
        flash('success', 'Tagessatz gelöscht.');
        redirect_route('verpflegung_saetze');
    }
    [$neu, $errors] = tagessatz_save_from_post($satz);
    if ($neu) {
        flash('success', $satz ? 'Tagessatz gespeichert.' : 'Tagessatz angelegt.');
        redirect_route('verpflegung_saetze');
    }
    $satz = array_merge($satz ?? [], $_POST, ['id' => $satz['id'] ?? null]);
}

if (!$satz) {
    // Ein neuer Satz übernimmt die Verteilung des bisherigen
    $letzter = tagessatz_am(tagessatz_query(), date('Y-m-d'));
    $satz = [
        'id' => null, 'gueltig_von' => date('Y-01-01'), 'gueltig_bis' => null,
        'tagessatz' => $letzter['tagessatz'] ?? '',
        'anteil_fruehstueck' => $letzter['anteil_fruehstueck'] ?? 20,
        'anteil_mittag' => $letzter['anteil_mittag'] ?? 40,
        'anteil_abend' => $letzter['anteil_abend'] ?? 40,
        'notiz' => '',
    ];
}

render('verpflegung_satz_edit', [
    'title'  => $satz['id'] ? 'Tagessatz bearbeiten' : 'Tagessatz anlegen',
    'satz'   => $satz,
    'errors' => $errors,
]);
