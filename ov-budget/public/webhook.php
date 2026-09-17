<?php
/**
 * Webhook der Stein.APP.
 *
 * Die Stein.APP ruft diese Adresse auf, sobald sich bei einem Fahrzeug etwas
 * ändert, und weist sich dabei mit dem Header X-Secret aus. Wir holen
 * daraufhin den Stand ab – das ist ein Aufruf und damit schonender als
 * regelmäßiges Abfragen.
 *
 * Einzurichten in der Stein.APP unter den Einstellungen des Ortsverbands.
 * Die Adresse muss von außen erreichbar sein; über Ingress ist sie das nicht.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit("Nur POST.\n");
}

$secret = (string)($_SERVER['HTTP_X_SECRET'] ?? '');
$body = (string)file_get_contents('php://input');

[$code, $meldung] = stein_webhook_handle($secret, $body);

http_response_code($code);
echo $meldung . PHP_EOL;
