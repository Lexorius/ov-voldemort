<?php
declare(strict_types=1);

/**
 * Prüfsummen aller Dateien des Connectors festhalten.
 *
 *   php bin/manifest.php
 *
 * Schreibt connector/manifest.json (liest der Connector selbst, um fremde
 * Dateien zu erkennen) und ov-budget/src/connector-manifest.json (der
 * Maßstab von OV-Budget – unabhängig davon, was der Connector behauptet).
 * Nach jeder Änderung an public/ oder src/ neu ausführen; ein Test in der
 * Entwicklung schlägt an, wenn das vergessen wurde.
 */
$wurzel = dirname(__DIR__);
require $wurzel . '/src/connector.php';

$manifest = con_manifest_erzeugen($wurzel);
$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
file_put_contents($wurzel . '/manifest.json', $json);
$ziel = dirname($wurzel) . '/ov-budget/src/connector-manifest.json';
if (is_dir(dirname($ziel))) {
    file_put_contents($ziel, $json);
}
printf("%d Dateien, Fassung %s\n", count($manifest['dateien']), $manifest['version']);
