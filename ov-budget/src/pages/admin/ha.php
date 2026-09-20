<?php
declare(strict_types=1);

$me = require_role('admin');

$fehler = '';
$hinweis = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch (post_str('action')) {
            case 'test':
                $res = ha_publish(true);
                $hinweis = sprintf('%d Nachricht(en) gesendet, davon %d Anmeldungen. '
                    . 'In Home Assistant erscheint das Gerät „OV-Budget".', $res['nachrichten'], $res['entitaeten']);
                audit('ha.gesendet', 'mqtt', null, $hinweis);
                break;

            case 'entfernen':
                $n = ha_entfernen();
                $hinweis = sprintf('%d Nachricht(en) gesendet – die Entitäten verschwinden aus Home Assistant.', $n);
                audit('ha.entfernt', 'mqtt', null, $hinweis);
                break;
        }
    } catch (Throwable $ex) {
        $fehler = $ex->getMessage();
    }
}

$cfg = mqtt_config();
$fahrzeuge = ha_fahrzeuge();

render('admin/ha', [
    'title'     => 'Home Assistant',
    'cfg'       => $cfg,
    'werte'     => setting_bool('ha_mqtt_aktiv', false) ? ha_werte() : [],
    'fahrzeuge' => $fahrzeuge,
    'basis'     => ha_basis(),
    'letzter'   => (int)state_get('ha_mqtt_letzter_lauf', '0'),
    'anmeldung' => (int)state_get('ha_mqtt_letzte_anmeldung', '0'),
    'fehler'    => $fehler,
    'hinweis'   => $hinweis,
]);
