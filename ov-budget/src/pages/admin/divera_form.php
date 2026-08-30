<?php
declare(strict_types=1);

$me = require_role('admin');

$form = db_row('SELECT * FROM divera_forms WHERE id = ?', [get_int('id', 0)]);
if (!$form) {
    http_response_code(404);
    render('error', ['title' => 'Nicht gefunden', 'message' => 'Dieses Formular ist nicht eingebunden.']);
    return;
}

$fehler = '';
$vorschau = [];
$hinweis = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action');

    if ($action === 'save' || $action === 'preview') {
        $map = [];
        foreach (array_keys(divera_map_targets()) as $target) {
            $map[$target] = post_str('map_' . $target);
        }
        db_update('divera_forms', [
            'name'                  => mb_substr(post_str('name') ?: $form['name'], 0, 200),
            'field_map'             => json_encode($map, JSON_UNESCAPED_UNICODE),
            'auto_import'           => post_bool('auto_import'),
            'default_status_id'     => post_int('default_status_id'),
            'default_fachgruppe_id' => post_int('default_fachgruppe_id'),
        ], 'id = ?', [$form['id']]);
        audit('divera.formular.zuordnung', 'divera_form', (int)$form['id'], $form['form_id']);
        $form = db_row('SELECT * FROM divera_forms WHERE id = ?', [$form['id']]);

        if ($action === 'save') {
            flash('success', 'Zuordnung gespeichert.');
            redirect_route('admin_divera_form', ['id' => $form['id']]);
        }
    }

    try {
        if ($action === 'probe') {
            $entries = divera_fetch_entries((string)$form['form_id']);
            $felder = [];
            foreach ($entries as $entry) {
                foreach (array_keys($entry['fields']) as $k) {
                    $felder[$k] = true;
                }
            }
            db_update('divera_forms', [
                'raw_schema' => json_encode([
                    'felder'  => array_keys($felder),
                    'beispiel' => $entries[0]['fields'] ?? [],
                    'anzahl'  => count($entries),
                ], JSON_UNESCAPED_UNICODE),
            ], 'id = ?', [$form['id']]);
            $form = db_row('SELECT * FROM divera_forms WHERE id = ?', [$form['id']]);
            $hinweis = sprintf('%d Eintrag/Einträge gelesen, %d verschiedene Felder gefunden.', count($entries), count($felder));
        }

        if ($action === 'preview') {
            $res = divera_import_form($form, (int)$me['id'], true);
            $vorschau = $res['preview'];
            $hinweis = sprintf(
                '%d Einträge insgesamt, %d bereits importiert, %d würden neu angelegt.',
                $res['total'],
                $res['skipped'],
                $res['created']
            );
        }

        if ($action === 'import') {
            $res = divera_import_form($form, (int)$me['id']);
            flash('success', sprintf('%d neue Wünsche angelegt, %d bereits vorhanden, %d fehlerhaft.',
                $res['created'], $res['skipped'], $res['failed']));
            redirect_route('admin_divera_form', ['id' => $form['id']]);
        }
    } catch (DiveraException $ex) {
        $fehler = $ex->getMessage();
    }
}

$schema = json_decode((string)$form['raw_schema'], true) ?: [];

render('admin/divera_form', [
    'title'    => 'Feldzuordnung',
    'form'     => $form,
    'map'      => json_decode((string)$form['field_map'], true) ?: [],
    'felder'   => $schema['felder'] ?? [],
    'beispiel' => $schema['beispiel'] ?? [],
    'fehler'   => $fehler,
    'hinweis'  => $hinweis,
    'vorschau' => $vorschau,
]);
