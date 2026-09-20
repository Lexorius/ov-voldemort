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

            case 'push_schluessel':
                webpush_keys_ensure();
                $hinweis = 'VAPID-Schlüssel liegen bereit. Jetzt können sich Browser anmelden.';
                audit('push.schluessel', 'push');
                break;

            case 'push_test':
                $res = push_to_user((int)$me['id'], 'OV-Budget', 'Testnachricht an diesen Browser.',
                    notify_url('?p=dashboard'));
                if ($res['gesendet'] === 0) {
                    $fehler = $res['fehler'] !== '' ? 'Push fehlgeschlagen: ' . $res['fehler']
                        : 'Für dich ist kein Browser angemeldet – das geht im eigenen Profil.';
                } else {
                    $hinweis = sprintf('An %d Browser geschickt%s.', $res['gesendet'],
                        $res['entfernt'] ? sprintf(', %d abgelaufene Anmeldung(en) entfernt', $res['entfernt']) : '');
                }
                break;

            case 'test_notify':
                $ziel = trim((string)($me['ha_notify'] ?? ''));
                if ($ziel === '') {
                    $fehler = 'In deinem Profil ist kein Benachrichtigungsziel hinterlegt.';
                    break;
                }
                ha_notify_send($ziel, 'OV-Budget', 'Testnachricht – die Benachrichtigungen funktionieren.',
                    notify_url('?p=dashboard'));
                $hinweis = 'Testnachricht an ' . $ziel . ' geschickt.';
                audit('ha.test', 'notify', null, $ziel);
                break;

            case 'dienste':
                $hinweis = sprintf('%d Benachrichtigungsziel(e) aus Home Assistant gelesen.',
                    count(ha_notify_dienste(true)));
                break;

            case 'senden':
                $res = notify_flush();
                $hinweis = sprintf('%d Nachricht(en) gesendet%s.', $res['gesendet'],
                    $res['fehler'] ? ', ' . $res['fehler'] . ' fehlgeschlagen: ' . $res['meldung'] : '');
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

$dienste = [];
$diensteFehler = '';
if (notify_enabled()) {
    try {
        $dienste = ha_notify_dienste();
    } catch (Throwable $ex) {
        $diensteFehler = $ex->getMessage();
    }
}

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
    'dienste'   => $dienste,
    'diensteFehler' => $diensteFehler,
    'empfaenger' => db_all("SELECT id, COALESCE(NULLIF(display_name, ''), username) AS name, ha_notify, notify_aktiv
                            FROM users WHERE is_active = 1 ORDER BY name"),
    'warteschlange' => db_all("SELECT n.*, COALESCE(NULLIF(u.display_name, ''), u.username) AS name
                               FROM notifications n JOIN users u ON u.id = n.user_id
                               ORDER BY n.id DESC LIMIT 15"),
    'offen'     => (int)db_val("SELECT COUNT(*) FROM notifications WHERE status = 'offen'", [], 0),
    'push'      => [
        'aktiv'     => setting_bool('push_aktiv', false),
        'schluessel' => webpush_public_key() !== '',
        'abos'      => db_all("SELECT p.geraet, p.created_at, p.last_ok,
                                      COALESCE(NULLIF(u.display_name, ''), u.username) AS name
                               FROM push_subscriptions p JOIN users u ON u.id = p.user_id
                               ORDER BY name, p.id"),
    ],
]);
