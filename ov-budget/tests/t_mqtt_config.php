<?php
declare(strict_types=1);
/* MQTT: eigener Broker aus den Einstellungen, eigener Themenbaum, ausgeschaltet */
$GLOBALS['settings'] = [
    'ha_mqtt_aktiv' => '0', 'ha_mqtt_host' => 'eigener.broker', 'ha_mqtt_port' => '8883',
    'ha_mqtt_user' => 'ich', 'ha_mqtt_passwort' => 'geheim', 'ha_mqtt_basis' => 'OV Budget/Test!',
];
$GLOBALS['merker'] = [];
function db_all(string $sql, array $p = []): array {
    if (!str_contains($sql, 'FROM settings')) { return []; }
    $r = [];
    foreach ($GLOBALS['settings'] as $k => $v) {
        $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
    }
    return $r;
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $GLOBALS['merker'][$p[0] ?? ''] ?? $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
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
$ist = function (string $name, mixed $got, mixed $want) use (&$ok, &$fail) {
    if ($got === $want) { $ok++; return; }
    $fail++; echo "FEHL  $name
      erwartet: " . var_export($want, true) . "
      erhalten: " . var_export($got, true) . "
";
};

// Eigener Broker hat Vorrang, auch wenn der Supervisor einen meldet
define('OVB_MQTT_DATEI', __DIR__ . '/alt/mqtt_test.json');
@mkdir(__DIR__ . '/alt');
file_put_contents(OVB_MQTT_DATEI, json_encode(['host' => 'core-mosquitto', 'port' => 1883]));
$cfg = mqtt_config();
$ist('Host aus den Einstellungen', $cfg['host'], 'eigener.broker');
$ist('Port aus den Einstellungen', $cfg['port'], 8883);
$ist('Benutzer und Passwort', [$cfg['user'], $cfg['pass']], ['ich', 'geheim']);
$ist('Quelle benannt', $cfg['quelle'], 'Einstellungen');

$ist('Themenbaum bereinigt', ha_basis(), 'ov_budget_test');
$ist('ausgeschaltet nie fällig', ha_due(2000000), ['faellig' => false, 'discovery' => false]);
$ist('keine Fahrzeuge gemeldet', ha_fahrzeuge(), []);

echo "$ok bestanden, $fail fehlgeschlagen
";
