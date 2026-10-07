<?php
declare(strict_types=1);

/** Stell- und Lagerplätze als Baum: anlegen, verschieben, stilllegen, löschen */

$user = require_role('admin', 'leitung');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s = standort_find((int)post_int('id', 0));
    switch (post_str('action')) {
        case 'hoch':
        case 'runter':
            if ($s) {
                standort_move($s, post_str('action'));
            }
            break;
        case 'aktiv':
            if ($s) {
                $neu = (int)$s['is_active'] === 1 ? 0 : 1;
                db_update('standorte', ['is_active' => $neu], 'id = ?', [(int)$s['id']]);
                audit('standort.bearbeitet', 'standort', (int)$s['id'], $neu ? 'wieder aktiv' : 'stillgelegt');
                flash('success', $neu ? sprintf('„%s" ist wieder aktiv.', (string)$s['name']) : sprintf('„%s" ist stillgelegt.', (string)$s['name']));
            }
            break;
        case 'delete':
            if ($s) {
                $fehler = standort_delete($s);
                flash($fehler ? 'error' : 'success', $fehler ?? sprintf('„%s" gelöscht.', (string)$s['name']));
            }
            break;
    }
    redirect_route('admin_standorte');
}

$alle = standort_all();

render('admin/standorte', [
    'title'    => 'Stell- und Lagerplätze',
    'baum'     => standort_baum($alle),
    'alle'     => $alle,
    'zaehlung' => standort_zaehlung($alle),
]);
