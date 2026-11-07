<?php
/**
 * Kalender-Abo (ICS) für Home Assistant, Outlook, Google, Thunderbird und
 * das Handy. Aufruf: /ics.php?token=…  (Token unter Verwaltung → Kalender)
 *
 * Ohne Anmeldung und ohne Sitzung: Es gibt nur GET, nur mit gültigem Token,
 * und nur das, was für den ganzen OV oder eine Fachgruppe gilt. Alles
 * Weitere steht in src/lib/ics.php.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

[$code, $kopf, $rumpf] = ics_antwort(
    (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    $_GET,
    (string)($_SERVER['REMOTE_ADDR'] ?? '')
);

http_response_code($code);
foreach ($kopf as $name => $wert) {
    header($name . ': ' . $wert);
}
echo $rumpf;
