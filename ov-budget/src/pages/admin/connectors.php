<?php
declare(strict_types=1);

/** Übersicht aller Connectoren */

$me = require_role('admin');

$hinweis = '';
$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'abholen') {
    try {
        $res = connector_fetch_all();
        $hinweis = sprintf('%d Meldung(en) geholt, %d übernommen, %d Parkposition(en)%s.',
            $res['geholt'], $res['uebernommen'], $res['parkpositionen'],
            $res['fehler'] ? ', ' . $res['fehler'] . ' unbrauchbar' : '');
    } catch (Throwable $ex) {
        $fehler = $ex->getMessage();
    }
}

$liste = connector_all();

// Wie viele Fahrzeuge hängen an welchem Connector?
$fahrzeuge = [];
foreach (db_all("SELECT qr_connector_id, COUNT(*) AS anzahl FROM vehicles
                 WHERE qr_token <> '' GROUP BY qr_connector_id") as $r) {
    $fahrzeuge[(int)$r['qr_connector_id']] = (int)$r['anzahl'];
}

render('admin/connectors', [
    'title'     => 'Connectoren',
    'liste'     => $liste,
    'fahrzeuge' => $fahrzeuge,
    'aktiv'     => setting_bool('connector_aktiv', false),
    'hinweis'   => $hinweis,
    'fehler'    => $fehler,
]);
