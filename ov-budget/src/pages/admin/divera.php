<?php
declare(strict_types=1);

$me = require_role('admin');

$remoteForms = [];
$verbindung = null;   // ['ok'=>bool, 'msg'=>string]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action');

    try {
        switch ($action) {
            case 'test':
                $remoteForms = divera_fetch_forms();
                $verbindung = ['ok' => true, 'msg' => sprintf('Verbindung steht. %d Formular(e) gefunden.', count($remoteForms))];
                break;

            case 'add_form':
                $formId = post_str('form_id');
                $name = post_str('name') ?: ('Formular ' . $formId);
                if ($formId === '') {
                    flash('error', 'Es wurde keine Formular-ID übergeben.');
                    break;
                }
                $exists = db_val('SELECT id FROM divera_forms WHERE form_id = ?', [$formId]);
                if ($exists) {
                    flash('info', 'Dieses Formular ist bereits eingebunden.');
                    redirect_route('admin_divera_form', ['id' => $exists]);
                }
                $newId = db_insert('divera_forms', [
                    'form_id'   => $formId,
                    'name'      => mb_substr($name, 0, 200),
                    'field_map' => '{}',
                ]);
                audit('divera.formular.hinzugefuegt', 'divera_form', $newId, $formId);
                flash('success', 'Formular übernommen. Jetzt bitte die Felder zuordnen.');
                redirect_route('admin_divera_form', ['id' => $newId]);
                // no break

            case 'import':
                $form = db_row('SELECT * FROM divera_forms WHERE id = ?', [post_int('id', 0)]);
                if (!$form) {
                    flash('error', 'Formular nicht gefunden.');
                    break;
                }
                $res = divera_import_form($form, (int)$me['id']);
                audit('divera.import', 'divera_form', (int)$form['id'],
                    sprintf('%d neu, %d übersprungen, %d Fehler', $res['created'], $res['skipped'], $res['failed']));
                flash(
                    $res['failed'] > 0 ? 'warn' : 'success',
                    sprintf(
                        'Import "%s": %d Eintrag/Einträge geprüft – <strong>%d neue Wünsche</strong>, %d bereits vorhanden, %d fehlerhaft.',
                        e($form['name']),
                        $res['total'],
                        $res['created'],
                        $res['skipped'],
                        $res['failed']
                    )
                );
                redirect_route('admin_divera');
                // no break

            case 'delete_form':
                $form = db_row('SELECT * FROM divera_forms WHERE id = ?', [post_int('id', 0)]);
                if ($form) {
                    db_exec('DELETE FROM divera_forms WHERE id = ?', [$form['id']]);
                    audit('divera.formular.entfernt', 'divera_form', (int)$form['id'], $form['form_id']);
                    flash('success', 'Formular entfernt. Bereits importierte Wünsche bleiben bestehen.');
                }
                redirect_route('admin_divera');
                // no break
        }
    } catch (DiveraException $ex) {
        $verbindung = ['ok' => false, 'msg' => $ex->getMessage()];
    }
}

render('admin/divera', [
    'title'       => 'Divera 24/7',
    'forms'       => db_all('SELECT * FROM divera_forms ORDER BY name'),
    'remoteForms' => $remoteForms,
    'verbindung'  => $verbindung,
    'log'         => db_all('SELECT * FROM divera_log ORDER BY created_at DESC LIMIT 15'),
]);
