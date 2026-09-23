<?php
declare(strict_types=1);

$me = require_role('admin');

$fehler = '';
$hinweis = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch (post_str('action')) {
            case 'koppeln':
                $antwort = connector_pair(post_str('url'), post_str('code'));
                $hinweis = 'Gekoppelt. Der Connector meldet sich als Fassung '
                    . (string)($antwort['version'] ?? '?') . '.';
                connector_push_vehicles();
                break;

            case 'trennen':
                connector_unpair();
                $hinweis = 'Die Kopplung ist hier gelöscht. Auf dem Server bitte daten/kopplung.json '
                    . 'entfernen, dann lässt sich neu koppeln.';
                break;

            case 'senden':
                $n = connector_push_vehicles();
                $hinweis = sprintf('%d Fahrzeug(e) beim Connector angemeldet.', $n);
                break;

            case 'abholen':
                $res = connector_fetch();
                $hinweis = sprintf('%d Meldung(en) geholt, %d übernommen, %d Parkposition(en)%s.',
                    $res['geholt'], $res['uebernommen'], $res['parkpositionen'],
                    $res['fehler'] ? ', ' . $res['fehler'] . ' unbrauchbar' : '');
                break;
        }
    } catch (Throwable $ex) {
        $fehler = $ex->getMessage();
    }
}

render('admin/connector', [
    'title'     => 'Standortmeldung per QR-Code',
    'aktiv'     => setting_bool('connector_aktiv', false),
    'url'       => connector_url(),
    'gekoppelt' => connector_gekoppelt(),
    'pubkey'    => connector_public_key(),
    'letzter'   => (int)state_get('connector_letzter_abruf', '0'),
    'fahrzeuge' => db_all("SELECT id, bezeichnung, kennzeichen, qr_token, geo_at, geo_quelle
                           FROM vehicles WHERE qr_token <> '' ORDER BY bezeichnung"),
    'fehler'    => $fehler,
    'hinweis'   => $hinweis,
]);
