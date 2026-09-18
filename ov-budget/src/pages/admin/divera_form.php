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
        $ziel = post_str('ziel') === 'thema' ? 'thema' : 'wunsch';
        $map = [];
        foreach (array_keys(divera_map_targets($ziel)) as $target) {
            $map[$target] = post_str('map_' . $target);
        }
        if ($ziel !== divera_ziel($form)) {
            // Die Felder passen nicht mehr zusammen – neu vorschlagen lassen
            $schema = json_decode((string)$form['raw_schema'], true) ?: [];
            $map = divera_suggest_map($schema['felder'] ?? [], $ziel);
        }
        db_update('divera_forms', [
            'name'                  => mb_substr(post_str('name') ?: $form['name'], 0, 200),
            'ziel'                  => $ziel,
            'field_map'             => json_encode($map, JSON_UNESCAPED_UNICODE),
            'auto_import'           => post_bool('auto_import'),
            'status_sync'           => post_bool('status_sync'),
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
            // Felder aus der Formulardefinition – so klappt es auch ohne Einträge
            foreach (divera_fetch_form_fields((string)$form['form_id']) as $k) {
                $felder[$k] = true;
            }
            foreach ($entries as $entry) {
                foreach (array_keys($entry['fields']) as $k) {
                    $felder[$k] = true;
                }
            }
            // Leere Zuordnungen mit einem Vorschlag füllen – bestehende bleiben
            $bisher = json_decode((string)($form['field_map'] ?? '{}'), true) ?: [];
            $vorschlag = divera_suggest_map(array_keys($felder), divera_ziel($form));
            $neu = $bisher;
            foreach ($vorschlag as $ziel => $feld) {
                if (trim((string)($neu[$ziel] ?? '')) === '') {
                    $neu[$ziel] = $feld;
                }
            }
            if ($neu !== $bisher) {
                db_update('divera_forms', ['field_map' => json_encode($neu, JSON_UNESCAPED_UNICODE)], 'id = ?', [$form['id']]);
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

        if ($action === 'status_sync') {
            $st = divera_status_sync_form($form);
            if ($st['fehler'] !== '') {
                $fehler = 'Divera hat die Statusänderung abgelehnt: ' . $st['fehler']
                    . ' – Hat der persönliche Schlüssel Bearbeitungsrechte für dieses Formular?';
            } else {
                $hinweis = sprintf('%d Status an Divera gemeldet%s.', $st['gemeldet'],
                    $st['offen'] ? sprintf(', %d weitere beim nächsten Durchlauf', $st['offen']) : '');
            }
        }

        if ($action === 'import') {
            $res = divera_import_form($form, (int)$me['id']);
            divera_status_sync_form($form);
            flash('success', sprintf('%d neue %s, %d bereits vorhanden, %d fehlerhaft.',
                $res['created'], divera_ziel($form) === 'thema' ? 'Themen im Themenspeicher' : 'Wünsche angelegt',
                $res['skipped'], $res['failed']));
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
