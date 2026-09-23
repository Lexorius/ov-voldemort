<?php
declare(strict_types=1);

/** Ein einzelner Connector: anlegen, koppeln, Zugänge anmelden, abholen */

$me = require_role('admin');

$c = connector_find(get_int('id'));
$fehler = '';
$hinweis = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch (post_str('action')) {
            case 'speichern':
                [$id, $probleme] = connector_save_from_post($c);
                if ($probleme) {
                    $fehler = implode(' ', $probleme);
                    break;
                }
                flash('success', $c ? 'Gespeichert.' : 'Connector angelegt. Jetzt koppeln.');
                redirect_route('admin_connector', ['id' => $id]);
                // redirect_route beendet die Ausführung
                break;

            case 'koppeln':
                if (!$c) {
                    throw new ConnectorException('Diesen Connector gibt es nicht.');
                }
                $antwort = connector_pair($c, post_str('code'));
                $hinweis = 'Gekoppelt. Der Connector meldet sich als Fassung '
                    . (string)($antwort['version'] ?? '?') . '.';
                $c = connector_find((int)$c['id']);
                if ($c && connector_taugt($c, 'fahrzeuge')) {
                    connector_push_vehicles($c);
                }
                break;

            case 'trennen':
                if ($c) {
                    connector_unpair($c);
                    $hinweis = 'Die Kopplung ist hier gelöscht. Auf dem Server bitte daten/kopplung.json '
                        . 'entfernen, dann lässt sich neu koppeln.';
                }
                break;

            case 'senden':
                if ($c) {
                    $n = connector_push_vehicles($c);
                    $hinweis = sprintf('%d Fahrzeug(e) beim Connector angemeldet.', $n);
                }
                break;

            case 'abholen':
                if ($c) {
                    $res = connector_fetch($c);
                    $hinweis = sprintf('%d Meldung(en) geholt, %d übernommen, %d Parkposition(en)%s.',
                        $res['geholt'], $res['uebernommen'], $res['parkpositionen'],
                        $res['fehler'] ? ', ' . $res['fehler'] . ' unbrauchbar' : '');
                }
                break;

            case 'loeschen':
                if ($c) {
                    connector_delete($c);
                    flash('success', 'Der Connector ist gelöscht. QR-Codes, die darauf zeigten, gehen ins Leere.');
                    redirect_route('admin_connectors');
                }
                break;
        }
    } catch (Throwable $ex) {
        $fehler = $ex->getMessage();
    }
    if ($c) {
        $c = connector_find((int)$c['id']);
    }
}

render('admin/connector', [
    'title'     => $c ? (string)$c['name'] : 'Connector anlegen',
    'c'         => $c,
    'aktiv'     => setting_bool('connector_aktiv', false),
    'fahrzeuge' => $c ? db_all(
        "SELECT id, bezeichnung, kennzeichen, qr_token, geo_at, geo_quelle
         FROM vehicles WHERE qr_token <> '' AND qr_connector_id = ? ORDER BY bezeichnung",
        [(int)$c['id']]
    ) : [],
    'fehler'    => $fehler,
    'hinweis'   => $hinweis,
]);
