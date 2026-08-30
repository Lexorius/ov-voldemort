<?php
/**
 * Konfiguration – Kopie dieser Datei als config/config.php anlegen und anpassen.
 * Diese Datei enthält Zugangsdaten und gehört NICHT ins Repository.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'ov_budget',
        'user'    => 'ov_budget',
        'pass'    => 'bitte-aendern',
        'charset' => 'utf8mb4',
    ],

    // Unterverzeichnis, in dem die App läuft. Leer, wenn sie direkt
    // unter der Domain-Wurzel liegt. Beispiel: '/budget'
    'base_path' => '',

    // Ablage der Angebots-Uploads. Sollte NICHT im Webroot liegen.
    'upload_dir' => dirname(__DIR__) . '/storage/uploads',

    // Name des Session-Cookies
    'session_name' => 'ovbudget',

    // true = PHP-Fehler werden angezeigt (nur für die Entwicklung!)
    'debug' => false,
];
