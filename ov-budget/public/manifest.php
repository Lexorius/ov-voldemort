<?php
/**
 * Web-App-Manifest für „Zum Startbildschirm hinzufügen" / „App installieren".
 *
 * Liegt wie sw.js neben index.php: Der Browser holt es ohne Anmeldung, und
 * die Pfade darin sind relativ – so stimmen sie auch hinter dem Ingress von
 * Home Assistant. Inhalt und Regeln stehen in src/lib/manifest.php.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

[$code, $kopf, $rumpf] = manifest_antwort(
    (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    (bool)app_config('db')
);

http_response_code($code);
foreach ($kopf as $name => $wert) {
    header($name . ': ' . $wert);
}
echo $rumpf;
