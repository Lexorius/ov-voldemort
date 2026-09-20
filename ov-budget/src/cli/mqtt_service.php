<?php
/**
 * Zugangsdaten des MQTT-Brokers beim Home-Assistant-Supervisor erfragen und
 * nach /data/mqtt.json schreiben. Wird beim Start aufgerufen; ohne Supervisor
 * (etwa bei einer Installation außerhalb von Home Assistant) passiert nichts.
 *
 * Aufruf: php /app/src/cli/mqtt_service.php
 */
declare(strict_types=1);

$ziel = $argv[1] ?? '/data/mqtt.json';
$token = (string)getenv('SUPERVISOR_TOKEN');
if ($token === '') {
    echo "Kein Supervisor-Token – MQTT-Zugang bleibt bei den Einstellungen.\n";
    exit(0);
}

$ch = curl_init('http://supervisor/services/mqtt');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
]);
$body = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

$daten = is_string($body) ? json_decode($body, true) : null;
if ($code !== 200 || !is_array($daten) || ($daten['result'] ?? '') !== 'ok') {
    // Kein Mosquitto-Add-on installiert oder kein Zugriff – kein Grund zum Abbruch
    echo "Kein MQTT-Dienst gemeldet (HTTP $code).\n";
    @unlink($ziel);
    exit(0);
}

$d = $daten['data'] ?? [];
$aus = [
    'host'     => (string)($d['host'] ?? ''),
    'port'     => (int)($d['port'] ?? 1883),
    'username' => (string)($d['username'] ?? ''),
    'password' => (string)($d['password'] ?? ''),
    'ssl'      => !empty($d['ssl']),
    'stand'    => date('c'),
];
if ($aus['host'] === '') {
    echo "Der gemeldete MQTT-Dienst hat keinen Host.\n";
    @unlink($ziel);
    exit(0);
}

file_put_contents($ziel, json_encode($aus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
@chmod($ziel, 0640);
printf("MQTT-Broker vom Supervisor: %s:%d\n", $aus['host'], $aus['port']);
