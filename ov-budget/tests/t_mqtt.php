<?php
declare(strict_types=1);
/* MQTT-Pakete und die Meldungen an Home Assistant */
$GLOBALS['settings'] = [
    'waehrung' => 'EUR', 'ov_name' => 'THW OV Musterstadt',
    'ha_mqtt_aktiv' => '1', 'ha_mqtt_basis' => 'ovbudget', 'ha_mqtt_intervall_minuten' => '5',
    'ha_mqtt_fahrzeuge' => '1', 'ha_mqtt_position' => '1', 'ha_mqtt_port' => '1883',
];
$GLOBALS['merker'] = [];

// Datenbank-Attrappe: Einstellungen aus dem Array, Merkwerte im Array
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'FROM settings')) {
        return $GLOBALS['merker'][$p[0] ?? ''] ?? $d;
    }
    return $d;
}
function db_exec(string $sql, array $p = []): int {
    if (str_contains($sql, 'INTO settings')) { $GLOBALS['merker'][$p[0]] = (string)$p[1]; }
    return 1;
}
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }

$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/settings.php';
require $app . '/src/lib/lists.php';
require $app . '/src/lib/mqtt.php';
require $app . '/src/lib/ha_export.php';

$ok = 0; $fail = 0;
$check = function (string $name, bool $cond) use (&$ok, &$fail) {
    if ($cond) { $ok++; } else { $fail++; echo "FAIL: $name\n"; }
};
$ist = function (string $name, mixed $got, mixed $want) use (&$ok, &$fail) {
    if ($got === $want) { $ok++; return; }
    $fail++; echo "FEHL  $name\n      erwartet: " . var_export($want, true) . "\n      erhalten: " . var_export($got, true) . "\n";
};

/* ---------- Restlänge ---------- */
$ist('Restlänge klein', bin2hex(mqtt_remaining_length(0)), '00');
$ist('Restlänge 127', bin2hex(mqtt_remaining_length(127)), '7f');
$ist('Restlänge 128 zweistellig', bin2hex(mqtt_remaining_length(128)), '8001');
$ist('Restlänge 321', bin2hex(mqtt_remaining_length(321)), 'c102');
$ist('Restlänge 16383', bin2hex(mqtt_remaining_length(16383)), 'ff7f');
$ist('Restlänge 2097152', bin2hex(mqtt_remaining_length(2097152)), '80808001');
try { mqtt_remaining_length(300000000); $check('zu lang abgewiesen', false); }
catch (MqttException $e) { $check('zu lang abgewiesen', true); }

$ist('Zeichenkette mit Länge', bin2hex(mqtt_string('MQTT')), '00044d515454');
$ist('leere Zeichenkette', bin2hex(mqtt_string('')), '0000');

/* ---------- CONNECT ---------- */
$p = mqtt_connect_packet('client1');
$ist('CONNECT-Kennung', bin2hex($p[0]), '10');
$check('Protokollname und Fassung 4', str_contains($p, "\x00\x04MQTT\x04"));
$ist('Flags ohne Anmeldung', bin2hex($p[9]), '02');
$check('Client-Kennung enthalten', str_contains($p, 'client1'));
$ist('Restlänge stimmt', ord($p[1]), strlen($p) - 2);

$p = mqtt_connect_packet('c', 'benutzer', 'geheim', 60);
$ist('Flags mit Benutzer und Passwort', bin2hex($p[9]), 'c2');
$check('Anmeldedaten enthalten', str_contains($p, 'benutzer') && str_contains($p, 'geheim'));
$ist('Keepalive 60', bin2hex(substr($p, 10, 2)), '003c');
$p = mqtt_connect_packet('c', 'benutzer');
$ist('nur Benutzer', bin2hex($p[9]), '82');

/* ---------- PUBLISH ---------- */
$p = mqtt_publish_packet('ovbudget/status', '{"a":1}');
$ist('PUBLISH ohne retain', bin2hex($p[0]), '30');
$check('Thema und Inhalt', str_contains($p, 'ovbudget/status') && str_contains($p, '{"a":1}'));
$ist('PUBLISH mit retain', bin2hex(mqtt_publish_packet('a/b', 'x', true)[0]), '31');
$ist('Restlänge PUBLISH', ord($p[1]), strlen($p) - 2);
foreach (['', 'a/+/b', 'a/#'] as $böse) {
    try { mqtt_publish_packet($böse, 'x'); $check('ungültiges Thema: ' . $böse, false); }
    catch (MqttException $e) { $check('ungültiges Thema: ' . ($böse ?: '(leer)'), true); }
}
$ist('DISCONNECT', bin2hex(mqtt_disconnect_packet()), 'e000');

/* ---------- Zugangsdaten ---------- */
define('OVB_MQTT_DATEI', __DIR__ . '/alt/mqtt_test.json');
@mkdir(__DIR__ . '/alt');
@unlink(OVB_MQTT_DATEI);
$ist('ohne alles kein Broker', mqtt_config(), null);
file_put_contents(OVB_MQTT_DATEI, json_encode([
    'host' => 'core-mosquitto', 'port' => 1883, 'username' => 'addons', 'password' => 'pw', 'ssl' => false]));
$cfg = mqtt_config();
$ist('Broker vom Supervisor', [$cfg['host'], $cfg['user']], ['core-mosquitto', 'addons']);
$check('Quelle benannt', str_contains((string)$cfg['quelle'], 'Home Assistant'));
try { mqtt_publish([['a/b', 'x', true]], ['host' => '', 'port' => 1883, 'user' => '', 'pass' => '', 'ssl' => false]);
      $check('ohne Host Fehler', false); }
catch (MqttException $e) { $check('ohne Host Fehler', str_contains($e->getMessage(), 'Broker')); }
$ist('nichts zu senden', mqtt_publish([], ['host' => 'x', 'port' => 1, 'user' => '', 'pass' => '', 'ssl' => false]), 0);

/* ---------- Themenbaum ---------- */
$ist('Themenbaum aus den Einstellungen', ha_basis(), 'ovbudget');

/* ---------- Anmeldung ---------- */
$fahrzeuge = [[
    'id' => 3, 'bezeichnung' => 'GKW 1', 'typ_label' => 'GKW', 'kennzeichen' => 'THW 99020',
    'funkrufname' => 'Heros Musterstadt 24/51', 'issi' => '1234567', 'opta' => 'NW HEROS', 'fachgruppe_label' => 'B1',
    'status_label' => 'Einsatzbereit', 'status_slug' => 'einsatzbereit', 'fms_status' => 2, 'fms_note' => '',
    'hu_bis' => '2027-03-31', 'sp_bis' => null, 'km_stand' => 48210, 'offene_auftraege' => 1,
    'geo_lat' => 51.45, 'geo_lng' => 7.01,
]];
$d = ha_discovery_messages('ovbudget', 'THW OV Musterstadt', '1.25.0', $fahrzeuge, true);
$themen = array_column($d, 0);
$check('Sensor angemeldet', in_array('homeassistant/sensor/ovbudget/budget_frei/config', $themen, true));
$check('Fahrzeugsensor angemeldet', in_array('homeassistant/sensor/ovbudget/fz3_fms/config', $themen, true));
$check('Standort als device_tracker', in_array('homeassistant/device_tracker/ovbudget/fz3_position/config', $themen, true));
$check('alles dauerhaft', array_column($d, 2) === array_fill(0, count($d), true));
$cfg = json_decode($d[0][1], true);
$ist('Zustandsthema', $cfg['state_topic'], 'ovbudget/status');
$check('Wert aus JSON gelesen', str_contains($cfg['value_template'], 'value_json.budget_gesamt'));
$ist('eindeutige Kennung', $cfg['unique_id'], 'ovbudget_budget_gesamt');
$ist('Gerät', $cfg['device']['identifiers'], ['ovbudget']);
$ist('Modell ist der OV', $cfg['device']['model'], 'THW OV Musterstadt');
$ist('Euro als Einheit', $cfg['unit_of_measurement'], 'EUR');
$check('Verfügbarkeit', ($cfg['availability_topic'] ?? '') === 'ovbudget/verfuegbar');
$alle = array_map(static fn($m) => json_decode($m[1], true), $d);
$ids = array_column($alle, 'unique_id');
$ist('keine doppelten Kennungen', count($ids), count(array_unique($ids)));
$diag = array_values(array_filter($alle, static fn($c) => ($c['entity_category'] ?? '') === 'diagnostic'));
$ist('acht Diagnose-Entitäten', count($diag), 8);
$zeit = array_values(array_filter($alle, static fn($c) => ($c['device_class'] ?? '') === 'timestamp'));
$ist('sieben Zeitstempel', count($zeit), 7);
$fz = array_values(array_filter($alle, static fn($c) => ($c['unique_id'] ?? '') === 'ovbudget_fz3_status'))[0];
$ist('Fahrzeuggerät', $fz['device']['identifiers'], ['ovbudget_fz_3']);
$ist('am Hauptgerät angehängt', $fz['device']['via_device'], 'ovbudget');
$ist('Fahrzeugname', $fz['device']['name'], 'GKW 1');
$check('Zusatzangaben am Status', str_contains($fz['json_attributes_template'], 'value_json.info'));

$ohne = ha_discovery_messages('ovbudget', 'OV', '1.0.0', [], false);
$ist('ohne Fahrzeuge nur Hauptsensoren', count($ohne), count(ha_sensoren()));
$ist('ohne Position kein Tracker', count(array_filter(array_column(
    ha_discovery_messages('ovbudget', 'OV', '1.0.0', $fahrzeuge, false), 0),
    static fn($t) => str_contains($t, 'device_tracker'))), 0);
$weg = ha_discovery_remove($d);
$check('Abmeldung leert die Themen', array_column($weg, 0) === $themen
    && array_column($weg, 1) === array_fill(0, count($d), ''));

/* ---------- Werte ---------- */
$werte = ['budget_frei' => 1234.5, 'stand' => '2026-09-20T10:00:00+02:00'];
$s = ha_state_messages('ovbudget', $werte, $fahrzeuge, true);
$ist('zuerst verfügbar', [$s[0][0], $s[0][1], $s[0][2]], ['ovbudget/verfuegbar', 'online', true]);
$ist('Kennzahlen als JSON', $s[1][0], 'ovbudget/status');
$ist('JSON lesbar', json_decode($s[1][1], true)['budget_frei'], 1234.5);
$ist('Fahrzeugthema', $s[2][0], 'ovbudget/fahrzeug/3/status');
$fzw = json_decode($s[2][1], true);
$ist('Status als Text', $fzw['status'], 'Einsatzbereit');
$ist('FMS als Zahl-Text', $fzw['fms'], '2');
$ist('Kilometer als Zahl', $fzw['km_stand'], 48210);
$ist('leere Frist bleibt leer', $fzw['sp_bis'], null);
$ist('Kennzeichen als Zusatz', $fzw['info']['kennzeichen'], 'THW 99020');
$check('leere Zusätze fallen weg', !array_key_exists('fms_hinweis', $fzw['info']));
$ist('Position eigenes Thema', $s[3][0], 'ovbudget/fahrzeug/3/position');
$ist('Position als Zahlen', json_decode($s[3][1], true)['latitude'], 51.45);
$ohnePos = ha_state_messages('ovbudget', $werte, [['id' => 4, 'bezeichnung' => 'MTW', 'status_label' => 'X',
    'fms_status' => null, 'hu_bis' => null, 'sp_bis' => null, 'km_stand' => null, 'offene_auftraege' => 0,
    'geo_lat' => null, 'geo_lng' => null]], true);
$ist('ohne Koordinaten kein Positionsthema', count($ohnePos), 3);
$ist('ohne FMS null', json_decode($ohnePos[2][1], true)['fms'], null);

$ist('Zeitstempel', ha_zeit(0), null);
$check('Zeitstempel als ISO', str_starts_with((string)ha_zeit(1758358800), date('c', 1758358800)));

/* ---------- Fälligkeit ---------- */
$GLOBALS['merker'] = [];
$f = ha_due(1000000);
$check('erster Lauf fällig', $f['faellig'] && $f['discovery']);
$GLOBALS['merker'] = ['ha_mqtt_letzter_lauf' => '1000000', 'ha_mqtt_letzte_anmeldung' => '1000000'];
$check('kurz danach nicht', !ha_due(1000100)['faellig']);
$check('nach fünf Minuten wieder', ha_due(1000301)['faellig']);
$check('Anmeldung erst nach einem Tag', !ha_due(1000301)['discovery'] && ha_due(1086401)['discovery']);
echo "$ok bestanden, $fail fehlgeschlagen\n";
