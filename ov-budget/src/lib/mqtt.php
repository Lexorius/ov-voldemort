<?php
declare(strict_types=1);

/**
 * Kleiner MQTT-Absender (Protokoll 3.1.1, QoS 0).
 *
 * Die Anwendung schickt nur Nachrichten und hört nicht zu; dafür braucht es
 * keine Bibliothek. Die Paketbauer sind reine Funktionen und lassen sich
 * deshalb ohne Broker prüfen.
 *
 * Zugangsdaten kommen entweder aus den Einstellungen oder – im Home-Assistant-
 * Add-on – aus /data/mqtt.json, das beim Start vom Supervisor geholt wird.
 */

class MqttException extends RuntimeException
{
}

const MQTT_DIENST_DATEI = '/data/mqtt.json';

/** Restlänge nach MQTT-Art: 7 Bit je Byte, oberstes Bit heißt "geht weiter" */
function mqtt_remaining_length(int $laenge): string
{
    if ($laenge < 0 || $laenge > 268435455) {
        throw new MqttException('Nachricht ist zu lang für MQTT.');
    }
    $out = '';
    do {
        $byte = $laenge % 128;
        $laenge = intdiv($laenge, 128);
        if ($laenge > 0) {
            $byte |= 0x80;
        }
        $out .= chr($byte);
    } while ($laenge > 0);
    return $out;
}

/** Zeichenkette mit vorangestellter Länge (2 Byte) */
function mqtt_string(string $s): string
{
    if (strlen($s) > 65535) {
        throw new MqttException('Zeichenkette ist zu lang für MQTT.');
    }
    return pack('n', strlen($s)) . $s;
}

function mqtt_connect_packet(string $clientId, string $user = '', string $pass = '', int $keepalive = 30): string
{
    $flags = 0x02;                     // clean session
    $rumpf = mqtt_string('MQTT') . chr(4);
    if ($user !== '') {
        $flags |= 0x80;
        if ($pass !== '') {
            $flags |= 0x40;
        }
    }
    $rumpf .= chr($flags) . pack('n', max(0, $keepalive));
    $rumpf .= mqtt_string($clientId);
    if ($user !== '') {
        $rumpf .= mqtt_string($user);
        if ($pass !== '') {
            $rumpf .= mqtt_string($pass);
        }
    }
    return chr(0x10) . mqtt_remaining_length(strlen($rumpf)) . $rumpf;
}

/** PUBLISH mit QoS 0; $retain lässt den Broker den Wert aufbewahren */
function mqtt_publish_packet(string $topic, string $payload, bool $retain = false): string
{
    if ($topic === '' || str_contains($topic, '+') || str_contains($topic, '#')) {
        throw new MqttException('Ungültiges MQTT-Thema: ' . $topic);
    }
    $rumpf = mqtt_string($topic) . $payload;
    return chr(0x30 | ($retain ? 0x01 : 0x00)) . mqtt_remaining_length(strlen($rumpf)) . $rumpf;
}

function mqtt_disconnect_packet(): string
{
    return chr(0xE0) . chr(0x00);
}

/**
 * Zugangsdaten: Einstellungen haben Vorrang, sonst der vom Supervisor
 * gemeldete Broker. Rückgabe null, wenn nichts bekannt ist.
 */
function mqtt_config(): ?array
{
    $host = trim((string)setting('ha_mqtt_host', ''));
    $cfg = [
        'host'     => $host,
        'port'     => setting_int('ha_mqtt_port', 1883),
        'user'     => trim((string)setting('ha_mqtt_user', '')),
        'pass'     => (string)setting('ha_mqtt_passwort', ''),
        'ssl'      => false,
        'quelle'   => 'Einstellungen',
    ];
    if ($host !== '') {
        return $cfg;
    }

    $datei = defined('OVB_MQTT_DATEI') ? OVB_MQTT_DATEI : MQTT_DIENST_DATEI;
    $roh = @file_get_contents($datei);
    $daten = is_string($roh) ? json_decode($roh, true) : null;
    if (!is_array($daten) || trim((string)($daten['host'] ?? '')) === '') {
        return null;
    }
    return [
        'host'   => trim((string)$daten['host']),
        'port'   => (int)($daten['port'] ?? 1883) ?: 1883,
        'user'   => (string)($daten['username'] ?? ''),
        'pass'   => (string)($daten['password'] ?? ''),
        'ssl'    => !empty($daten['ssl']),
        'quelle' => 'Home Assistant (Mosquitto-Add-on)',
    ];
}

/**
 * Nachrichten senden: [[topic, payload, retain], ...].
 * Gibt die Anzahl gesendeter Nachrichten zurück; wirft bei Verbindungs-
 * oder Anmeldefehlern eine MqttException.
 */
function mqtt_publish(array $nachrichten, ?array $cfg = null, int $timeout = 10): int
{
    $cfg ??= mqtt_config();
    if (!$cfg || $cfg['host'] === '') {
        throw new MqttException('Kein MQTT-Broker bekannt. Bitte in den Einstellungen eintragen '
            . 'oder das Mosquitto-Add-on installieren.');
    }
    if (!$nachrichten) {
        return 0;
    }

    $adresse = ($cfg['ssl'] ? 'ssl://' : 'tcp://') . $cfg['host'] . ':' . (int)$cfg['port'];
    $fehlerNr = 0;
    $fehler = '';
    $sock = @stream_socket_client($adresse, $fehlerNr, $fehler, $timeout);
    if (!$sock) {
        throw new MqttException('Keine Verbindung zu ' . $adresse . ' (' . ($fehler ?: 'Zeitüberschreitung') . ').');
    }
    stream_set_timeout($sock, $timeout);

    try {
        $clientId = 'ovbudget-' . substr(sha1((string)($cfg['host'] . getmypid())), 0, 10);
        fwrite($sock, mqtt_connect_packet($clientId, (string)$cfg['user'], (string)$cfg['pass']));

        $antwort = fread($sock, 4);
        if (!is_string($antwort) || strlen($antwort) < 4 || ord($antwort[0]) !== 0x20) {
            throw new MqttException('Der Broker hat die Verbindung nicht bestätigt.');
        }
        $code = ord($antwort[3]);
        if ($code !== 0) {
            throw new MqttException(match ($code) {
                1 => 'Der Broker spricht diese MQTT-Fassung nicht.',
                2 => 'Der Broker hat die Client-Kennung abgelehnt.',
                3 => 'Der Broker ist gerade nicht erreichbar.',
                4 => 'Benutzername oder Passwort stimmen nicht.',
                5 => 'Der Zugang ist nicht erlaubt.',
                default => 'Der Broker hat die Anmeldung abgelehnt (Code ' . $code . ').',
            });
        }

        $anzahl = 0;
        foreach ($nachrichten as $n) {
            [$topic, $payload, $retain] = [$n[0], $n[1], (bool)($n[2] ?? false)];
            if (fwrite($sock, mqtt_publish_packet((string)$topic, (string)$payload, $retain)) === false) {
                throw new MqttException('Senden abgebrochen nach ' . $anzahl . ' Nachricht(en).');
            }
            $anzahl++;
        }
        fwrite($sock, mqtt_disconnect_packet());
        return $anzahl;
    } finally {
        @fclose($sock);
    }
}
